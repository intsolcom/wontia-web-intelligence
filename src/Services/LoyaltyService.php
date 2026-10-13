<?php
namespace App\Services;

use App\Core\Database;

/**
 * LoyaltyService — puntos de fidelidad (brick "Mi Cuenta", Ecommerce).
 * Multi-tenant (@site_id). Otorga puntos por pedido pagado; idempotente por pedido.
 */
class LoyaltyService
{
    private static bool $ensured = false;

    public function ensureTables(): array
    {
        if (self::$ensured) return ['ok' => true, 'cached' => true];
        $db = Database::instance();
        try {
            $st = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_customers' AND COLUMN_NAME = 'points_balance'");
            $st->execute();
            if ((int)$st->fetchColumn() === 0) $db->exec("ALTER TABLE store_customers ADD COLUMN points_balance INT NOT NULL DEFAULT 0");
        } catch (\Throwable $e) {
        }
        $db->exec("CREATE TABLE IF NOT EXISTS store_loyalty_ledger (
            id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, customer_id INT NOT NULL,
            points INT NOT NULL, reason VARCHAR(40) NOT NULL, order_id INT NULL, note VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_ledger (site_id, customer_id, reason, order_id), KEY idx_cust (site_id, customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ensured = true;
        return ['ok' => true, 'cached' => false];
    }

    public function settings(): array
    {
        $out = ['enabled' => false, 'points_per_1000' => 1, 'redeem_cents' => 100];
        try {
            $st = Database::instance()->prepare("SELECT `key`, `value` FROM settings WHERE site_id = @site_id AND `key` IN ('store_loyalty_enabled','store_loyalty_points_per_1000','store_loyalty_redeem_cents')");
            $st->execute();
            foreach ($st->fetchAll() as $r) {
                if ($r['key'] === 'store_loyalty_enabled') $out['enabled'] = ((string)$r['value'] === '1');
                if ($r['key'] === 'store_loyalty_points_per_1000') $out['points_per_1000'] = max(0, (int)$r['value']);
                if ($r['key'] === 'store_loyalty_redeem_cents') $out['redeem_cents'] = max(0, (int)$r['value']);
            }
        } catch (\Throwable $e) {
        }
        return $out;
    }

    public function awardForOrder(string $uuid): array
    {
        $this->ensureTables();
        $s = $this->settings();
        if (!$s['enabled'] || $s['points_per_1000'] <= 0) return ['ok' => true, 'skipped' => 'disabled'];
        $db = Database::instance();
        $st = $db->prepare("SELECT id, total_cents, customer_id FROM store_orders WHERE site_id = @site_id AND uuid = ? AND payment_status = 'paid' LIMIT 1");
        $st->execute([$uuid]);
        $o = $st->fetch();
        if (!$o || empty($o['customer_id'])) return ['ok' => true, 'skipped' => 'no_customer'];
        $cid = (int)$o['customer_id'];
        $pts = (int)floor(((int)$o['total_cents']) / 100000) * (int)$s['points_per_1000'];
        if ($pts <= 0) return ['ok' => true, 'skipped' => 'zero'];
        $chk = $db->prepare("SELECT id FROM store_loyalty_ledger WHERE site_id = @site_id AND order_id = ? AND reason = 'order' LIMIT 1");
        $chk->execute([(int)$o['id']]);
        if ($chk->fetchColumn()) return ['ok' => true, 'skipped' => 'already'];
        $db->prepare("INSERT INTO store_loyalty_ledger (site_id, customer_id, points, reason, order_id, note) VALUES (@site_id, ?, ?, 'order', ?, 'Pedido pagado')")
           ->execute([$cid, $pts, (int)$o['id']]);
        $db->prepare("UPDATE store_customers SET points_balance = points_balance + ? WHERE id = ? AND site_id = @site_id")->execute([$pts, $cid]);
        return ['ok' => true, 'points' => $pts];
    }

    public function balance(int $customerId): int
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT COALESCE(points_balance, 0) FROM store_customers WHERE id = ? AND site_id = @site_id LIMIT 1");
        $st->execute([$customerId]);
        return (int)$st->fetchColumn();
    }

    public function history(int $customerId, int $limit = 50): array
    {
        $this->ensureTables();
        $limit = max(1, min(200, $limit));
        $st = Database::instance()->prepare("SELECT points, reason, note, order_id, created_at FROM store_loyalty_ledger WHERE site_id = @site_id AND customer_id = ? ORDER BY id DESC LIMIT $limit");
        $st->execute([$customerId]);
        return $st->fetchAll();
    }
}
