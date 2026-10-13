<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Session;

/**
 * CustomerService — cuentas de cliente para el ecommerce (brick "Mi Cuenta").
 * Multi-tenant (@site_id). Sesión de cliente separada de la de admin.
 */
class CustomerService
{
    private static bool $ensured = false;
    public const SESSION_KEY = 'store_customer';

    public function ensureTables(): array
    {
        if (self::$ensured) return ['ok' => true, 'cached' => true];
        $db = Database::instance();
        foreach ([
            'password_hash' => "ALTER TABLE store_customers ADD COLUMN password_hash VARCHAR(255) NULL",
            'email_verified_at' => "ALTER TABLE store_customers ADD COLUMN email_verified_at DATETIME NULL",
            'last_login' => "ALTER TABLE store_customers ADD COLUMN last_login DATETIME NULL",
        ] as $col => $sql) {
            try {
                $st = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_customers' AND COLUMN_NAME = ?");
                $st->execute([$col]);
                if ((int)$st->fetchColumn() === 0) $db->exec($sql);
            } catch (\Throwable $e) {
            }
        }
        $db->exec("CREATE TABLE IF NOT EXISTS store_customer_addresses (
            id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, customer_id INT NOT NULL,
            label VARCHAR(60) NULL, name VARCHAR(255) NULL, phone VARCHAR(50) NULL, line1 VARCHAR(255) NULL,
            line2 VARCHAR(255) NULL, city VARCHAR(120) NULL, region VARCHAR(120) NULL, postal VARCHAR(20) NULL,
            country VARCHAR(80) DEFAULT 'CO', is_default TINYINT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_cust (site_id, customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS store_customer_favorites (
            id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, customer_id INT NOT NULL,
            product_ref VARCHAR(255) NOT NULL, title VARCHAR(255) NULL, image VARCHAR(500) NULL,
            price_cents INT DEFAULT 0, url VARCHAR(500) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_fav (site_id, customer_id, product_ref)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        foreach ([
            'alert_price' => "ALTER TABLE store_customer_favorites ADD COLUMN alert_price TINYINT NOT NULL DEFAULT 1",
            'alert_stock' => "ALTER TABLE store_customer_favorites ADD COLUMN alert_stock TINYINT NOT NULL DEFAULT 1",
            'last_price_cents' => "ALTER TABLE store_customer_favorites ADD COLUMN last_price_cents INT NULL",
            'in_stock' => "ALTER TABLE store_customer_favorites ADD COLUMN in_stock TINYINT NULL",
            'notified_price_at' => "ALTER TABLE store_customer_favorites ADD COLUMN notified_price_at DATETIME NULL",
            'notified_stock_at' => "ALTER TABLE store_customer_favorites ADD COLUMN notified_stock_at DATETIME NULL",
        ] as $fcol => $fsql) {
            try {
                $st = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_customer_favorites' AND COLUMN_NAME = ?");
                $st->execute([$fcol]);
                if ((int)$st->fetchColumn() === 0) $db->exec($fsql);
            } catch (\Throwable $e) {
            }
        }
        self::$ensured = true;
        return ['ok' => true, 'cached' => false];
    }

    public function register(string $name, string $email, string $password, string $phone = ''): array
    {
        $this->ensureTables();
        $name = trim($name); $email = trim(strtolower($email));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'message' => 'Nombre y email válidos son obligatorios.'];
        if (strlen($password) < 8) return ['ok' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres.'];
        $db = Database::instance();
        $st = $db->prepare('SELECT id FROM store_customers WHERE site_id = @site_id AND email = ? LIMIT 1');
        $st->execute([$email]);
        if ($st->fetchColumn()) return ['ok' => false, 'message' => 'Ya existe una cuenta con ese email.'];
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 11]);
        $st = $db->prepare('INSERT INTO store_customers (site_id, name, email, phone, password_hash) VALUES (@site_id, ?, ?, ?, ?)');
        $st->execute([$name, $email, $phone, $hash]);
        $id = (int)$db->lastInsertId();
        $this->setSession(['id' => $id, 'name' => $name, 'email' => $email]);
        $this->sendEmailVerification($id, $email);
        return ['ok' => true, 'customer' => ['id' => $id, 'name' => $name, 'email' => $email]];
    }

    public function login(string $email, string $password): array
    {
        $this->ensureTables();
        $email = trim(strtolower($email));
        $db = Database::instance();
        $st = $db->prepare('SELECT * FROM store_customers WHERE site_id = @site_id AND email = ? LIMIT 1');
        $st->execute([$email]);
        $c = $st->fetch();
        if (!$c || empty($c['password_hash']) || !password_verify($password, $c['password_hash'])) {
            return ['ok' => false, 'message' => 'Credenciales inválidas.'];
        }
        $db->prepare('UPDATE store_customers SET last_login = NOW() WHERE id = ?')->execute([$c['id']]);
        $this->setSession(['id' => (int)$c['id'], 'name' => (string)$c['name'], 'email' => (string)$c['email']]);
        return ['ok' => true, 'customer' => ['id' => (int)$c['id'], 'name' => (string)$c['name'], 'email' => (string)$c['email']]];
    }

    public function logout(): void
    {
        Session::remove(self::SESSION_KEY);
    }

    public function setSession(array $c): void
    {
        Session::set(self::SESSION_KEY, $c);
    }

    public function current(): ?array
    {
        $s = Session::get(self::SESSION_KEY);
        if (!is_array($s) || empty($s['id'])) return null;
        $this->ensureTables();
        $st = Database::instance()->prepare('SELECT id, name, email, phone, document, orders_count, total_spent_cents, created_at, email_verified_at FROM store_customers WHERE id = ? AND site_id = @site_id LIMIT 1');
        $st->execute([(int)$s['id']]);
        return $st->fetch() ?: null;
    }

    public function updateProfile(int $id, array $d): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $fields = []; $params = [];
        foreach (['name', 'phone', 'document'] as $f) {
            if (isset($d[$f])) { $fields[] = "$f = ?"; $params[] = (string)$d[$f]; }
        }
        if (!empty($d['password']) && strlen((string)$d['password']) >= 8) {
            $fields[] = 'password_hash = ?'; $params[] = password_hash((string)$d['password'], PASSWORD_BCRYPT, ['cost' => 11]);
        }
        if (!$fields) return ['ok' => true];
        $params[] = $id;
        $db->prepare('UPDATE store_customers SET ' . implode(', ', $fields) . ' WHERE id = ? AND site_id = @site_id')->execute($params);
        $c = $this->current();
        if ($c) $this->setSession(['id' => (int)$c['id'], 'name' => (string)$c['name'], 'email' => (string)$c['email']]);
        return ['ok' => true];
    }

    public function orders(int $customerId, string $email): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare('SELECT id, uuid, order_number, total_cents, currency, payment_status, fulfillment_status, created_at FROM store_orders WHERE site_id = @site_id AND (customer_id = ? OR customer_email = ?) ORDER BY id DESC LIMIT 100');
        $st->execute([$customerId, $email]);
        return $st->fetchAll();
    }

    private function owns(int $customerId, int $rowId): bool
    {
        $st = Database::instance()->prepare('SELECT id FROM store_customer_addresses WHERE id = ? AND customer_id = ? AND site_id = @site_id LIMIT 1');
        $st->execute([$rowId, $customerId]);
        return (bool)$st->fetchColumn();
    }

    public function addresses(int $customerId): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare('SELECT * FROM store_customer_addresses WHERE customer_id = ? AND site_id = @site_id ORDER BY is_default DESC, id DESC');
        $st->execute([$customerId]);
        return $st->fetchAll();
    }

    public function addressSave(int $customerId, array $d): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $id = (int)($d['id'] ?? 0);
        $vals = [
            trim((string)($d['label'] ?? '')), trim((string)($d['name'] ?? '')), trim((string)($d['phone'] ?? '')),
            trim((string)($d['line1'] ?? '')), trim((string)($d['line2'] ?? '')), trim((string)($d['city'] ?? '')),
            trim((string)($d['region'] ?? '')), trim((string)($d['postal'] ?? '')), trim((string)($d['country'] ?? 'CO')) ?: 'CO',
        ];
        if ($vals[3] === '' || $vals[5] === '') return ['ok' => false, 'message' => 'Dirección y ciudad son obligatorias.'];
        if ($id > 0 && $this->owns($customerId, $id)) {
            $db->prepare('UPDATE store_customer_addresses SET label=?, name=?, phone=?, line1=?, line2=?, city=?, region=?, postal=?, country=? WHERE id=? AND customer_id=? AND site_id=@site_id')
               ->execute(array_merge($vals, [$id, $customerId]));
        } else {
            $db->prepare('INSERT INTO store_customer_addresses (site_id, customer_id, label, name, phone, line1, line2, city, region, postal, country) VALUES (@site_id,?,?,?,?,?,?,?,?,?,?)')
               ->execute(array_merge([$customerId], $vals));
            $id = (int)$db->lastInsertId();
        }
        if (!empty($d['is_default'])) {
            $db->prepare('UPDATE store_customer_addresses SET is_default = 0 WHERE customer_id = ? AND site_id = @site_id')->execute([$customerId]);
            $db->prepare('UPDATE store_customer_addresses SET is_default = 1 WHERE id = ? AND site_id = @site_id')->execute([$id]);
        }
        return ['ok' => true, 'id' => $id];
    }

    public function addressDelete(int $customerId, int $id): array
    {
        $this->ensureTables();
        if (!$this->owns($customerId, $id)) return ['ok' => false];
        Database::instance()->prepare('DELETE FROM store_customer_addresses WHERE id = ? AND customer_id = ? AND site_id = @site_id')->execute([$id, $customerId]);
        return ['ok' => true];
    }

    public function favorites(int $customerId): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare('SELECT * FROM store_customer_favorites WHERE customer_id = ? AND site_id = @site_id ORDER BY id DESC');
        $st->execute([$customerId]);
        return $st->fetchAll();
    }

    public function favoriteAdd(int $customerId, array $d): array
    {
        $this->ensureTables();
        $ref = trim((string)($d['product_ref'] ?? ''));
        if ($ref === '') return ['ok' => false, 'message' => 'Falta el producto.'];
        Database::instance()->prepare('INSERT INTO store_customer_favorites (site_id, customer_id, product_ref, title, image, price_cents, url) VALUES (@site_id,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title), image=VALUES(image), price_cents=VALUES(price_cents), url=VALUES(url)')
           ->execute([$customerId, $ref, mb_substr((string)($d['title'] ?? ''), 0, 255), mb_substr((string)($d['image'] ?? ''), 0, 500), (int)($d['price_cents'] ?? 0), mb_substr((string)($d['url'] ?? ''), 0, 500)]);
        return ['ok' => true];
    }

    public function favoriteDelete(int $customerId, string $ref): array
    {
        $this->ensureTables();
        Database::instance()->prepare('DELETE FROM store_customer_favorites WHERE customer_id = ? AND product_ref = ? AND site_id = @site_id')->execute([$customerId, $ref]);
        return ['ok' => true];
    }

    // ── GDPR: portabilidad y borrado ──

    public function exportData(int $id): array
    {
        $c = $this->current();
        return [
            'customer' => $c,
            'addresses' => $this->addresses($id),
            'favorites' => $this->favorites($id),
            'orders' => ($c && !empty($c['email'])) ? $this->orders($id, (string)$c['email']) : [],
            'generated_at' => date('c'),
            'format' => 'Wontia Web Intelligence — exportación de datos personales',
        ];
    }

    public function deleteAccount(int $id, string $email): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $db->prepare('UPDATE store_orders SET customer_id = NULL, customer_name = ?, customer_email = ?, customer_phone = NULL, customer_document = NULL, shipping_address = NULL WHERE site_id = @site_id AND (customer_id = ? OR customer_email = ?)')
           ->execute(['Cliente eliminado', 'eliminado+' . $id . '@anon.invalid', $id, $email]);
        $db->prepare('DELETE FROM store_customer_addresses WHERE customer_id = ? AND site_id = @site_id')->execute([$id]);
        $db->prepare('DELETE FROM store_customer_favorites WHERE customer_id = ? AND site_id = @site_id')->execute([$id]);
        $db->prepare('DELETE FROM store_customers WHERE id = ? AND site_id = @site_id')->execute([$id]);
        $this->logout();
        return ['ok' => true];
    }

    // ── Recuperación de contraseña (OTP por email) ──

    public function forgotPassword(string $email): array
    {
        $this->ensureTables();
        $email = trim(strtolower($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => true];
        $st = Database::instance()->prepare('SELECT id FROM store_customers WHERE site_id = @site_id AND email = ? LIMIT 1');
        $st->execute([$email]);
        if (!$st->fetchColumn()) return ['ok' => true]; // respuesta genérica (no revelar)
        $sec = new AuthSecurityService();
        $code = $sec->createOtp(0, $email, 'customer_reset', 10);
        $sec->sendOtpEmail($email, $code, $this->siteName(), 10);
        return ['ok' => true];
    }

    public function resetPassword(string $email, string $code, string $password): array
    {
        $this->ensureTables();
        $email = trim(strtolower($email));
        if (strlen($password) < 8) return ['ok' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres.'];
        $sec = new AuthSecurityService();
        $r = $sec->verifyOtp($email, $code, 'customer_reset');
        if (empty($r['ok'])) return ['ok' => false, 'message' => 'Código inválido o expirado.'];
        Database::instance()->prepare('UPDATE store_customers SET password_hash = ? WHERE site_id = @site_id AND email = ?')
           ->execute([password_hash($password, PASSWORD_BCRYPT, ['cost' => 11]), $email]);
        return ['ok' => true, 'message' => 'Contraseña actualizada. Ya puedes iniciar sesión.'];
    }

    // ── Verificación de email ──

    public function sendEmailVerification(int $id, string $email): array
    {
        $this->ensureTables();
        $email = trim(strtolower($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false];
        $st = Database::instance()->prepare('SELECT email_verified_at FROM store_customers WHERE id = ? AND site_id = @site_id LIMIT 1');
        $st->execute([$id]);
        if ($st->fetchColumn()) return ['ok' => true, 'already' => true];
        $sec = new AuthSecurityService();
        $code = $sec->createOtp(0, $email, 'customer_verify', 1440);
        $sec->sendOtpEmail($email, $code, $this->siteName(), 1440);
        return ['ok' => true];
    }

    public function verifyEmail(string $email, string $code): array
    {
        $this->ensureTables();
        $email = trim(strtolower($email));
        $sec = new AuthSecurityService();
        $r = $sec->verifyOtp($email, $code, 'customer_verify');
        if (empty($r['ok'])) return ['ok' => false, 'message' => 'Código inválido o expirado.'];
        Database::instance()->prepare('UPDATE store_customers SET email_verified_at = NOW() WHERE site_id = @site_id AND email = ? AND email_verified_at IS NULL')->execute([$email]);
        return ['ok' => true, 'message' => 'Email verificado. ¡Gracias!'];
    }

    // ── Alertas de favoritos (bajada de precio / vuelve en stock) ──

    public function favoriteAlertToggle(int $customerId, string $ref, array $d): array
    {
        $this->ensureTables();
        $ref = trim($ref);
        if ($ref === '') return ['ok' => false, 'message' => 'Falta el producto.'];
        $fields = [];
        $params = [];
        if (array_key_exists('alert_price', $d)) { $fields[] = 'alert_price = ?'; $params[] = !empty($d['alert_price']) ? 1 : 0; }
        if (array_key_exists('alert_stock', $d)) { $fields[] = 'alert_stock = ?'; $params[] = !empty($d['alert_stock']) ? 1 : 0; }
        if (!$fields) return ['ok' => true];
        $params[] = $customerId;
        $params[] = $ref;
        Database::instance()->prepare('UPDATE store_customer_favorites SET ' . implode(', ', $fields) . ' WHERE customer_id = ? AND product_ref = ? AND site_id = @site_id')->execute($params);
        return ['ok' => true];
    }

    private function siteName(): string
    {
        try {
            $st = Database::instance()->prepare('SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = ? LIMIT 1');
            $st->execute(['site_name']);
            $v = $st->fetchColumn();
            if ($v) return (string)$v;
        } catch (\Throwable $e) {
        }
        return 'SONIKA';
    }
}
