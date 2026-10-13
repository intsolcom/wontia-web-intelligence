<?php
namespace App\Services;

use App\Core\Database;

/**
 * ReturnService — devoluciones y reembolsos de pedidos (Ecommerce).
 * Multi-tenant (@site_id). El cliente solicita; el admin aprueba/rechaza/reembolsa.
 */
class ReturnService
{
    private static bool $ensured = false;

    public function ensureTables(): array
    {
        if (self::$ensured) return ['ok' => true, 'cached' => true];
        Database::instance()->exec("CREATE TABLE IF NOT EXISTS store_order_returns (
            id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, order_id INT NOT NULL,
            customer_id INT NULL, customer_email VARCHAR(255) NULL, reason TEXT NULL,
            status ENUM('requested','approved','rejected','refunded') NOT NULL DEFAULT 'requested',
            refund_cents INT DEFAULT 0, restock TINYINT NOT NULL DEFAULT 0, admin_note VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, resolved_at DATETIME NULL,
            UNIQUE KEY uk_ret (site_id, order_id), KEY idx_status (site_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ensured = true;
        return ['ok' => true, 'cached' => false];
    }

    public function request(string $uuid, int $customerId, string $email, string $reason): array
    {
        $this->ensureTables();
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) return ['ok' => false, 'message' => 'Cuéntanos el motivo de la devolución.'];
        $db = Database::instance();
        $st = $db->prepare("SELECT * FROM store_orders WHERE site_id = @site_id AND uuid = ? LIMIT 1");
        $st->execute([$uuid]);
        $o = $st->fetch();
        if (!$o) return ['ok' => false, 'message' => 'Pedido no encontrado.'];
        if ((string)$o['payment_status'] !== 'paid') return ['ok' => false, 'message' => 'Solo puedes devolver pedidos pagados.'];
        if ((string)$o['fulfillment_status'] === 'cancelled') return ['ok' => false, 'message' => 'El pedido está cancelado.'];
        $chk = $db->prepare("SELECT status FROM store_order_returns WHERE site_id = @site_id AND order_id = ? LIMIT 1");
        $chk->execute([(int)$o['id']]);
        $cur = $chk->fetchColumn();
        if ($cur) return ['ok' => true, 'status' => $cur, 'message' => 'Ya registramos una solicitud para este pedido.'];
        $db->prepare("INSERT INTO store_order_returns (site_id, order_id, customer_id, customer_email, reason) VALUES (@site_id, ?, ?, ?, ?)")
           ->execute([(int)$o['id'], $customerId ?: null, $email, mb_substr($reason, 0, 2000)]);
        return ['ok' => true, 'id' => (int)$db->lastInsertId(), 'status' => 'requested', 'message' => 'Solicitud enviada. Te contactaremos pronto.'];
    }

    public function mine(int $customerId): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT r.id, r.status, r.reason, r.refund_cents, r.admin_note, r.created_at, o.order_number, o.uuid
            FROM store_order_returns r LEFT JOIN store_orders o ON o.id = r.order_id AND o.site_id = r.site_id
            WHERE r.site_id = @site_id AND r.customer_id = ? ORDER BY r.id DESC");
        $st->execute([$customerId]);
        return $st->fetchAll();
    }

    public function listAll(string $status = ''): array
    {
        $this->ensureTables();
        $w = 'r.site_id = @site_id';
        $p = [];
        if (in_array($status, ['requested', 'approved', 'rejected', 'refunded'], true)) { $w .= ' AND r.status = ?'; $p[] = $status; }
        $st = Database::instance()->prepare("SELECT r.*, o.order_number, o.uuid, o.total_cents, o.payment_status FROM store_order_returns r LEFT JOIN store_orders o ON o.id = r.order_id AND o.site_id = r.site_id WHERE $w ORDER BY r.id DESC LIMIT 300");
        $st->execute($p);
        return $st->fetchAll();
    }

    public function requestedCount(): int
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT COUNT(*) FROM store_order_returns WHERE site_id = @site_id AND status = 'requested'");
        $st->execute();
        return (int)$st->fetchColumn();
    }

    public function setStatus(int $id, string $status, bool $restock, string $note = ''): array
    {
        $this->ensureTables();
        if (!in_array($status, ['requested', 'approved', 'rejected', 'refunded'], true)) return ['ok' => false, 'message' => 'Estado inválido.'];
        $db = Database::instance();
        $st = $db->prepare("SELECT r.*, o.total_cents FROM store_order_returns r LEFT JOIN store_orders o ON o.id = r.order_id AND o.site_id = r.site_id WHERE r.id = ? AND r.site_id = @site_id LIMIT 1");
        $st->execute([$id]);
        $r = $st->fetch();
        if (!$r) return ['ok' => false, 'message' => 'Solicitud no encontrada.'];
        if ((string)$r['status'] === 'refunded' && $status === 'refunded') return ['ok' => true, 'already' => true];
        $refundCents = (int)($r['total_cents'] ?? 0);
        $db->prepare("UPDATE store_order_returns SET status = ?, restock = ?, admin_note = ?, refund_cents = ?, resolved_at = NOW() WHERE id = ? AND site_id = @site_id")
           ->execute([$status, $restock ? 1 : 0, mb_substr($note, 0, 255), $status === 'refunded' ? $refundCents : 0, $id]);
        if ($status === 'refunded') {
            $db->prepare("UPDATE store_orders SET payment_status = 'refunded', admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n[devolucion] Reembolsado') WHERE id = ? AND site_id = @site_id")
               ->execute([(int)$r['order_id']]);
            if ($restock) {
                $items = $db->prepare("SELECT product_id, variant_id, qty FROM store_order_items WHERE order_id = ? AND site_id = @site_id");
                $items->execute([(int)$r['order_id']]);
                foreach ($items->fetchAll() as $it) {
                    if (!empty($it['variant_id'])) {
                        $db->prepare("UPDATE store_variants SET stock = stock + ? WHERE id = ? AND site_id = @site_id")->execute([(int)$it['qty'], (int)$it['variant_id']]);
                    } elseif (!empty($it['product_id'])) {
                        $db->prepare("UPDATE store_products SET stock = stock + ? WHERE id = ? AND site_id = @site_id AND track_stock = 1")->execute([(int)$it['qty'], (int)$it['product_id']]);
                    }
                }
            }
            try {
                (new LoyaltyService())->reverseOrderPoints((int)$r['order_id'], 'refund');
            } catch (\Throwable $e) {
            }
        }
        return ['ok' => true, 'status' => $status];
    }
}
