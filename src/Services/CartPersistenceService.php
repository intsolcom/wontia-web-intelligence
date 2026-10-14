<?php
namespace App\Services;

use App\Core\Database;

/**
 * CartPersistenceService — persistencia del carrito por cuenta (cross-device) + merge.
 * Multi-tenant.
 */
class CartPersistenceService
{
    private static bool $ensured = false;

    public function ensureTables(): void
    {
        if (self::$ensured) return;
        Database::instance()->exec("CREATE TABLE IF NOT EXISTS store_carts (
            id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, customer_id INT NOT NULL,
            items JSON, coupon VARCHAR(40) NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_cart (site_id, customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ensured = true;
    }

    public function load(int $customerId): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT items, coupon FROM store_carts WHERE site_id = @site_id AND customer_id = ? LIMIT 1");
        $st->execute([$customerId]);
        $r = $st->fetch();
        if (!$r) return ['items' => [], 'coupon' => ''];
        $items = json_decode((string)$r['items'], true);
        return ['items' => is_array($items) ? $items : [], 'coupon' => (string)($r['coupon'] ?? '')];
    }

    public function save(int $customerId, array $items, string $coupon = ''): void
    {
        $this->ensureTables();
        $db = Database::instance();
        $json = json_encode(array_values($items), JSON_UNESCAPED_UNICODE);
        $c = $db->prepare("SELECT id FROM store_carts WHERE site_id = @site_id AND customer_id = ? LIMIT 1");
        $c->execute([$customerId]);
        if ($c->fetchColumn()) {
            $db->prepare("UPDATE store_carts SET items = ?, coupon = ? WHERE site_id = @site_id AND customer_id = ?")->execute([$json, substr($coupon, 0, 40), $customerId]);
        } else {
            $db->prepare("INSERT INTO store_carts (site_id, customer_id, items, coupon) VALUES (@site_id, ?, ?, ?)")->execute([$customerId, $json, substr($coupon, 0, 40)]);
        }
    }

    public function clear(int $customerId): void
    {
        $this->ensureTables();
        Database::instance()->prepare("DELETE FROM store_carts WHERE site_id = @site_id AND customer_id = ?")->execute([$customerId]);
    }

    /** Une dos carritos por `handle`: suma cantidades (tope 99). */
    public function merge(array $a, array $b): array
    {
        $out = [];
        foreach (array_merge($a, $b) as $it) {
            if (!is_array($it)) continue;
            $h = (string)($it['handle'] ?? '');
            if ($h === '') continue;
            if (!isset($out[$h])) {
                $out[$h] = $it;
                $out[$h]['qty'] = max(1, (int)($it['qty'] ?? 1));
            } else {
                $out[$h]['qty'] = min(99, (int)$out[$h]['qty'] + max(1, (int)($it['qty'] ?? 1)));
            }
        }
        return array_values($out);
    }
}
