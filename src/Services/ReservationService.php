<?php
namespace App\Services;

use App\Core\Database;

/**
 * ReservationService — reserva de inventario con TTL (tienda nativa, store_products).
 * Mantiene un contador `reserved` por producto/variante; disponible = stock − reserved.
 * Multi-tenant. La liberación corre por cron (job-runner) al expirar.
 */
class ReservationService
{
    private static bool $ensured = false;

    public function ensureTables(): void
    {
        if (self::$ensured) return;
        $db = Database::instance();
        $db->exec("CREATE TABLE IF NOT EXISTS store_cart_reservations (
            id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, token VARCHAR(64) NOT NULL,
            product_id INT NOT NULL, variant_id INT NULL, qty INT NOT NULL DEFAULT 1,
            status ENUM('active','released','expired','consumed') NOT NULL DEFAULT 'active',
            expires_at DATETIME NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_tok (site_id, token, status), KEY idx_exp (site_id, status, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        foreach ([
            ['store_products', 'reserved', "ALTER TABLE store_products ADD COLUMN reserved INT NOT NULL DEFAULT 0"],
            ['store_variants', 'reserved', "ALTER TABLE store_variants ADD COLUMN reserved INT NOT NULL DEFAULT 0"],
        ] as $c) {
            try {
                $st = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
                $st->execute([$c[0], $c[1]]);
                if ((int)$st->fetchColumn() === 0) $db->exec($c[2]);
            } catch (\Throwable $e) {
            }
        }
        self::$ensured = true;
    }

    private function cleanToken(string $t): string
    {
        return substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $t), 0, 64);
    }

    public function reserve(string $token, array $items, int $ttlMinutes = 20): array
    {
        $this->ensureTables();
        $token = $this->cleanToken($token);
        if ($token === '') return ['ok' => false, 'message' => 'Token requerido.'];
        $ttl = max(5, min(120, $ttlMinutes));
        $this->release($token);

        $db = Database::instance();
        $expires = date('Y-m-d H:i:s', time() + $ttl * 60);
        $reserved = [];
        $failed = [];
        foreach (array_slice($items, 0, 50) as $it) {
            if (!is_array($it)) continue;
            $pid = (int)($it['product_id'] ?? 0);
            $vid = !empty($it['variant_id']) ? (int)$it['variant_id'] : 0;
            $qty = max(1, min(99, (int)($it['qty'] ?? 1)));
            if ($pid <= 0) continue;
            $ok = false;
            if ($vid > 0) {
                $u = $db->prepare("UPDATE store_variants SET reserved = reserved + ? WHERE id = ? AND site_id = @site_id AND stock - reserved >= ?");
                $u->execute([$qty, $vid, $qty]);
                $ok = $u->rowCount() > 0;
            } else {
                $u = $db->prepare("UPDATE store_products SET reserved = reserved + ? WHERE id = ? AND site_id = @site_id AND track_stock = 1 AND stock - reserved >= ?");
                $u->execute([$qty, $pid, $qty]);
                $ok = $u->rowCount() > 0;
            }
            if (!$ok) { $failed[] = ['product_id' => $pid, 'variant_id' => $vid ?: null, 'qty' => $qty]; continue; }
            $db->prepare("INSERT INTO store_cart_reservations (site_id, token, product_id, variant_id, qty, status, expires_at) VALUES (@site_id, ?, ?, ?, ?, 'active', ?)")
               ->execute([$token, $pid, $vid ?: null, $qty, $expires]);
            $reserved[] = ['product_id' => $pid, 'variant_id' => $vid ?: null, 'qty' => $qty];
        }
        return ['ok' => true, 'expires_at' => $expires, 'reserved' => $reserved, 'failed' => $failed];
    }

    public function release(string $token, string $status = 'released'): array
    {
        $this->ensureTables();
        $token = $this->cleanToken($token);
        if ($token === '') return ['ok' => true];
        $db = Database::instance();
        $st = $db->prepare("SELECT * FROM store_cart_reservations WHERE site_id = @site_id AND token = ? AND status = 'active'");
        $st->execute([$token]);
        foreach ($st->fetchAll() as $r) {
            $qty = (int)$r['qty'];
            if ((int)$r['variant_id'] > 0) {
                $db->prepare("UPDATE store_variants SET reserved = GREATEST(0, reserved - ?) WHERE id = ? AND site_id = @site_id")->execute([$qty, (int)$r['variant_id']]);
            } else {
                $db->prepare("UPDATE store_products SET reserved = GREATEST(0, reserved - ?) WHERE id = ? AND site_id = @site_id")->execute([$qty, (int)$r['product_id']]);
            }
            $db->prepare("UPDATE store_cart_reservations SET status = ? WHERE id = ?")->execute([$status, (int)$r['id']]);
        }
        return ['ok' => true];
    }

    public function expire(int $limit = 200): int
    {
        $this->ensureTables();
        $db = Database::instance();
        $st = $db->prepare("SELECT * FROM store_cart_reservations WHERE site_id = @site_id AND status = 'active' AND expires_at < NOW() LIMIT $limit");
        $st->execute();
        $rows = $st->fetchAll();
        foreach ($rows as $r) {
            $qty = (int)$r['qty'];
            if ((int)$r['variant_id'] > 0) $db->prepare("UPDATE store_variants SET reserved = GREATEST(0, reserved - ?) WHERE id = ? AND site_id = @site_id")->execute([$qty, (int)$r['variant_id']]);
            else $db->prepare("UPDATE store_products SET reserved = GREATEST(0, reserved - ?) WHERE id = ? AND site_id = @site_id")->execute([$qty, (int)$r['product_id']]);
            $db->prepare("UPDATE store_cart_reservations SET status = 'expired' WHERE id = ?")->execute([(int)$r['id']]);
        }
        return count($rows);
    }
}
