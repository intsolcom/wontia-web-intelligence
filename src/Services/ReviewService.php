<?php
namespace App\Services;

use App\Core\Database;

/**
 * ReviewService — reseñas de producto (brick "Mi Cuenta", Ecommerce).
 * Multi-tenant (@site_id). Moderación (pending/approved/rejected) + compra verificada.
 */
class ReviewService
{
    private static bool $ensured = false;

    public function ensureTables(): array
    {
        if (self::$ensured) return ['ok' => true, 'cached' => true];
        Database::instance()->exec("CREATE TABLE IF NOT EXISTS store_product_reviews (
            id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, product_ref VARCHAR(255) NOT NULL,
            customer_id INT NULL, author_name VARCHAR(120) NOT NULL, rating TINYINT NOT NULL DEFAULT 5,
            title VARCHAR(160) NULL, body TEXT NULL, status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            verified_purchase TINYINT NOT NULL DEFAULT 0, helpful INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_prod (site_id, product_ref, status), KEY idx_cust (site_id, customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ensured = true;
        return ['ok' => true, 'cached' => false];
    }

    public function settings(): array
    {
        $out = ['auto_approve' => true, 'require_purchase' => false];
        try {
            $st = Database::instance()->prepare("SELECT `key`, `value` FROM settings WHERE site_id = @site_id AND `key` IN ('store_reviews_auto_approve','store_reviews_require_purchase')");
            $st->execute();
            foreach ($st->fetchAll() as $r) {
                $v = (string)$r['value'];
                if ($r['key'] === 'store_reviews_auto_approve') $out['auto_approve'] = ($v === '' || $v === '1');
                if ($r['key'] === 'store_reviews_require_purchase') $out['require_purchase'] = ($v === '1');
            }
        } catch (\Throwable $e) {
        }
        return $out;
    }

    public function summary(string $ref): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT COUNT(*) c, AVG(rating) avg, SUM(rating=5) r5, SUM(rating=4) r4, SUM(rating=3) r3, SUM(rating=2) r2, SUM(rating=1) r1
            FROM store_product_reviews WHERE site_id = @site_id AND product_ref = ? AND status = 'approved'");
        $st->execute([$ref]);
        $r = $st->fetch() ?: [];
        $c = (int)($r['c'] ?? 0);
        return [
            'count' => $c,
            'average' => $c ? round((float)$r['avg'], 1) : 0,
            'dist' => [5 => (int)($r['r5'] ?? 0), 4 => (int)($r['r4'] ?? 0), 3 => (int)($r['r3'] ?? 0), 2 => (int)($r['r2'] ?? 0), 1 => (int)($r['r1'] ?? 0)],
        ];
    }

    public function forProduct(string $ref, int $limit = 20): array
    {
        $this->ensureTables();
        $limit = max(1, min(100, $limit));
        $st = Database::instance()->prepare("SELECT id, author_name, rating, title, body, verified_purchase, helpful, created_at FROM store_product_reviews WHERE site_id = @site_id AND product_ref = ? AND status = 'approved' ORDER BY helpful DESC, id DESC LIMIT $limit");
        $st->execute([$ref]);
        return $st->fetchAll();
    }

    public function add(int $customerId, string $name, array $d): array
    {
        $this->ensureTables();
        $ref = trim((string)($d['product_ref'] ?? ''));
        $rating = (int)($d['rating'] ?? 0);
        $body = trim((string)($d['body'] ?? ''));
        if ($ref === '') return ['ok' => false, 'message' => 'Falta el producto.'];
        if ($rating < 1 || $rating > 5) return ['ok' => false, 'message' => 'La calificación debe ser de 1 a 5 estrellas.'];
        if (mb_strlen($body) < 5) return ['ok' => false, 'message' => 'Escribe al menos unas palabras.'];
        $s = $this->settings();
        $verified = $this->purchased($customerId, (string)($d['email'] ?? ''), $ref) ? 1 : 0;
        if ($s['require_purchase'] && !$verified) return ['ok' => false, 'message' => 'Solo puedes reseñar productos que hayas comprado.'];
        $db = Database::instance();
        $dc = $db->prepare("SELECT id FROM store_product_reviews WHERE site_id = @site_id AND product_ref = ? AND customer_id = ? LIMIT 1");
        $dc->execute([$ref, $customerId]);
        if ($dc->fetchColumn()) return ['ok' => false, 'message' => 'Ya reseñaste este producto.'];
        $status = $s['auto_approve'] ? 'approved' : 'pending';
        $db->prepare("INSERT INTO store_product_reviews (site_id, product_ref, customer_id, author_name, rating, title, body, status, verified_purchase) VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?, ?)")
           ->execute([$ref, $customerId, mb_substr($name, 0, 120), $rating, mb_substr(trim((string)($d['title'] ?? '')), 0, 160), $body, $status, $verified]);
        return ['ok' => true, 'id' => (int)$db->lastInsertId(), 'status' => $status, 'message' => $status === 'approved' ? '¡Gracias por tu reseña!' : 'Reseña enviada. Se publicará tras revisión.'];
    }

    public function mine(int $customerId): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT id, product_ref, rating, title, body, status, created_at FROM store_product_reviews WHERE site_id = @site_id AND customer_id = ? ORDER BY id DESC");
        $st->execute([$customerId]);
        return $st->fetchAll();
    }

    public function alreadyReviewed(int $customerId, string $ref): bool
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT id FROM store_product_reviews WHERE site_id = @site_id AND customer_id = ? AND product_ref = ? LIMIT 1");
        $st->execute([$customerId, $ref]);
        return (bool)$st->fetchColumn();
    }

    public function listAll(string $status = '', string $ref = '', int $limit = 200): array
    {
        $this->ensureTables();
        $w = 'site_id = @site_id';
        $p = [];
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) { $w .= ' AND status = ?'; $p[] = $status; }
        if ($ref !== '') { $w .= ' AND product_ref = ?'; $p[] = $ref; }
        $limit = max(1, min(500, $limit));
        $st = Database::instance()->prepare("SELECT * FROM store_product_reviews WHERE $w ORDER BY id DESC LIMIT $limit");
        $st->execute($p);
        return $st->fetchAll();
    }

    public function pendingCount(): int
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT COUNT(*) FROM store_product_reviews WHERE site_id = @site_id AND status = 'pending'");
        $st->execute();
        return (int)$st->fetchColumn();
    }

    public function moderate(int $id, string $status): array
    {
        $this->ensureTables();
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) return ['ok' => false, 'message' => 'Estado inválido.'];
        Database::instance()->prepare("UPDATE store_product_reviews SET status = ? WHERE id = ? AND site_id = @site_id")->execute([$status, $id]);
        return ['ok' => true];
    }

    public function delete(int $id): array
    {
        $this->ensureTables();
        Database::instance()->prepare("DELETE FROM store_product_reviews WHERE id = ? AND site_id = @site_id")->execute([$id]);
        return ['ok' => true];
    }

    public function markHelpful(int $id): array
    {
        $this->ensureTables();
        Database::instance()->prepare("UPDATE store_product_reviews SET helpful = helpful + 1 WHERE id = ? AND site_id = @site_id AND status = 'approved'")->execute([$id]);
        return ['ok' => true];
    }

    private function purchased(int $customerId, string $email, string $ref): bool
    {
        try {
            $st = Database::instance()->prepare("SELECT i.* FROM store_order_items i JOIN store_orders o ON o.id = i.order_id AND o.site_id = i.site_id WHERE i.site_id = @site_id AND o.payment_status = 'paid' AND (o.customer_id = ? OR o.customer_email = ?)");
            $st->execute([$customerId, $email]);
            foreach ($st->fetchAll() as $row) {
                foreach ($row as $v) {
                    if ($v !== null && $v !== '' && stripos((string)$v, $ref) !== false) return true;
                }
            }
        } catch (\Throwable $e) {
        }
        return false;
    }
}
