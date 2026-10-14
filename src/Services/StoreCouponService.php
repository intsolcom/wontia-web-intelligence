<?php
namespace App\Services;

use App\Core\Database;

/**
 * StoreCouponService — cupones de descuento (multi-tenant).
 * Validación SIEMPRE server-side (vigencia, mínimo, límite de usos).
 */
class StoreCouponService
{
    private static bool $ensured = false;

    public function ensureTables(): void
    {
        if (self::$ensured) return;
        Database::instance()->exec("CREATE TABLE IF NOT EXISTS store_coupons (
            id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, code VARCHAR(40) NOT NULL,
            type ENUM('fixed','percent') NOT NULL DEFAULT 'percent', value INT NOT NULL DEFAULT 0,
            min_order_cents INT NOT NULL DEFAULT 0, max_uses INT NOT NULL DEFAULT 0, used INT NOT NULL DEFAULT 0,
            active TINYINT NOT NULL DEFAULT 1, expires_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_code (site_id, code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ensured = true;
    }

    public function byCode(string $code): ?array
    {
        $this->ensureTables();
        $code = strtoupper(trim($code));
        if ($code === '') return null;
        $st = Database::instance()->prepare("SELECT * FROM store_coupons WHERE site_id = @site_id AND code = ? LIMIT 1");
        $st->execute([$code]);
        return $st->fetch() ?: null;
    }

    public function validate(string $code, int $subtotalCents): array
    {
        $this->ensureTables();
        $code = strtoupper(trim($code));
        if ($code === '') return ['ok' => false, 'message' => 'Ingresa un cupón.', 'discount_cents' => 0];
        $c = $this->byCode($code);
        if (!$c) return ['ok' => false, 'message' => 'Cupón no válido.', 'discount_cents' => 0];
        if ((int)$c['active'] !== 1) return ['ok' => false, 'message' => 'Cupón inactivo.', 'discount_cents' => 0];
        if (!empty($c['expires_at']) && strtotime((string)$c['expires_at']) < time()) return ['ok' => false, 'message' => 'Cupón expirado.', 'discount_cents' => 0];
        if ((int)$c['max_uses'] > 0 && (int)$c['used'] >= (int)$c['max_uses']) return ['ok' => false, 'message' => 'Cupón agotado.', 'discount_cents' => 0];
        if ((int)$c['min_order_cents'] > 0 && $subtotalCents < (int)$c['min_order_cents']) {
            return ['ok' => false, 'message' => 'El mínimo para este cupón no se alcanza.', 'discount_cents' => 0];
        }
        $disc = $c['type'] === 'fixed' ? (int)$c['value'] : (int)round($subtotalCents * ((int)$c['value']) / 100);
        $disc = max(0, min($disc, $subtotalCents));
        return ['ok' => true, 'id' => (int)$c['id'], 'code' => (string)$c['code'], 'type' => (string)$c['type'], 'discount_cents' => $disc];
    }

    public function redeem(int $id): void
    {
        $this->ensureTables();
        Database::instance()->prepare("UPDATE store_coupons SET used = used + 1 WHERE id = ? AND site_id = @site_id")->execute([$id]);
    }

    // ── Admin ──
    public function list(): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT * FROM store_coupons WHERE site_id = @site_id ORDER BY id DESC LIMIT 200");
        $st->execute();
        return $st->fetchAll();
    }

    public function save(array $d, ?int $id = null): array
    {
        $this->ensureTables();
        $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string)($d['code'] ?? '')));
        if ($code === '') return ['ok' => false, 'message' => 'Código requerido.'];
        $type = in_array((string)($d['type'] ?? 'percent'), ['fixed', 'percent'], true) ? (string)$d['type'] : 'percent';
        $value = max(0, (int)($d['value'] ?? 0));
        $min = max(0, (int)($d['min_order_cents'] ?? 0));
        $maxUses = max(0, (int)($d['max_uses'] ?? 0));
        $active = !empty($d['active']) ? 1 : 0;
        $exp = trim((string)($d['expires_at'] ?? ''));
        if ($exp !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp)) $exp = '';
        $db = Database::instance();
        if ($id) {
            $db->prepare("UPDATE store_coupons SET code=?, type=?, value=?, min_order_cents=?, max_uses=?, active=?, expires_at=? WHERE id=? AND site_id=@site_id")
               ->execute([$code, $type, $value, $min, $maxUses, $active, $exp ?: null, $id]);
            return ['ok' => true, 'id' => $id];
        }
        $db->prepare("INSERT INTO store_coupons (site_id, code, type, value, min_order_cents, max_uses, active, expires_at) VALUES (@site_id,?,?,?,?,?,?,?)")
           ->execute([$code, $type, $value, $min, $maxUses, $active, $exp ?: null]);
        return ['ok' => true, 'id' => (int)$db->lastInsertId()];
    }

    public function delete(int $id): array
    {
        $this->ensureTables();
        Database::instance()->prepare("DELETE FROM store_coupons WHERE id = ? AND site_id = @site_id")->execute([$id]);
        return ['ok' => true];
    }
}
