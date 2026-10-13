<?php
namespace App\Services;

use App\Core\Database;

/**
 * PaymentMethodService — métodos de pago guardados del cliente (Ecommerce).
 * Multi-tenant (@site_id). El token del proveedor se almacena cifrado y NUNCA se expone.
 */
class PaymentMethodService
{
    private static bool $ensured = false;
    public const PROVIDERS = ['wompi', 'cod', 'manual'];

    public function ensureTables(): array
    {
        if (self::$ensured) return ['ok' => true, 'cached' => true];
        Database::instance()->exec("CREATE TABLE IF NOT EXISTS store_customer_payment_methods (
            id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1, customer_id INT NOT NULL,
            provider ENUM('wompi','cod','manual') NOT NULL DEFAULT 'cod', label VARCHAR(80) NULL,
            token_hash VARCHAR(255) NULL, last4 CHAR(4) NULL, brand VARCHAR(40) NULL,
            is_default TINYINT NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_cust (site_id, customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ensured = true;
        return ['ok' => true, 'cached' => false];
    }

    public function list(int $customerId): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT id, provider, label, last4, brand, is_default, created_at FROM store_customer_payment_methods WHERE site_id = @site_id AND customer_id = ? ORDER BY is_default DESC, id DESC");
        $st->execute([$customerId]);
        return $st->fetchAll();
    }

    public function save(int $customerId, array $d): array
    {
        $this->ensureTables();
        $provider = (string)($d['provider'] ?? 'cod');
        if (!in_array($provider, self::PROVIDERS, true)) return ['ok' => false, 'message' => 'Método de pago inválido.'];
        $label = mb_substr(trim((string)($d['label'] ?? '')), 0, 80);
        $token = trim((string)($d['token'] ?? ''));
        $last4 = preg_replace('/\D/', '', (string)($d['last4'] ?? ''));
        $brand = mb_substr(trim((string)($d['brand'] ?? '')), 0, 40);
        if ($provider === 'wompi' && $token === '' && $last4 === '') {
            return ['ok' => false, 'message' => 'Para tarjetas necesitamos una referencia del proveedor (tokenización aún no habilitada).'];
        }
        $db = Database::instance();
        $tokenEnc = $token !== '' ? TwoFactorService::encrypt($token) : null;
        $isDefault = !empty($d['is_default']);
        $db->prepare("INSERT INTO store_customer_payment_methods (site_id, customer_id, provider, label, token_hash, last4, brand, is_default) VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?)")
           ->execute([$customerId, $provider, $label !== '' ? $label : ucfirst($provider), $tokenEnc, $last4 !== '' ? $last4 : null, $brand !== '' ? $brand : null, $isDefault ? 1 : 0]);
        $id = (int)$db->lastInsertId();
        if ($isDefault) $this->setDefault($customerId, $id);
        return ['ok' => true, 'id' => $id];
    }

    public function delete(int $customerId, int $id): array
    {
        $this->ensureTables();
        Database::instance()->prepare("DELETE FROM store_customer_payment_methods WHERE id = ? AND customer_id = ? AND site_id = @site_id")->execute([$id, $customerId]);
        return ['ok' => true];
    }

    public function setDefault(int $customerId, int $id): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $db->prepare("UPDATE store_customer_payment_methods SET is_default = 0 WHERE customer_id = ? AND site_id = @site_id")->execute([$customerId]);
        $db->prepare("UPDATE store_customer_payment_methods SET is_default = 1 WHERE id = ? AND customer_id = ? AND site_id = @site_id")->execute([$id, $customerId]);
        return ['ok' => true];
    }

    public function defaultProvider(int $customerId): string
    {
        if ($customerId <= 0) return '';
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT provider FROM store_customer_payment_methods WHERE site_id = @site_id AND customer_id = ? ORDER BY is_default DESC, id DESC LIMIT 1");
        $st->execute([$customerId]);
        return (string)($st->fetchColumn() ?: '');
    }
}
