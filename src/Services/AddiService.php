<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

class AddiService
{
    private const DEFAULTS = [
        'addi_enabled' => '0',
        'addi_environment' => 'staging',
        'addi_ally_slug' => '',
        'addi_client_id' => '',
        'addi_client_secret' => '',
        'addi_callback_user' => '',
        'addi_callback_password' => '',
        'addi_callback_url' => '',
        'addi_redirection_url' => '',
        'addi_logo_url' => '',
        'addi_label' => 'Paga con Addi',
        'addi_widget_enabled' => '1',
        'addi_widget_title' => 'Págalo a cuotas con Addi',
        'addi_widget_styles' => '',
        'addi_force_visible' => '0',
        'addi_last_config' => '',
        'addi_last_config_at' => '',
    ];

    private static bool $ensured = false;
    private static ?array $cache = null;

    public function ensureTables(): array
    {
        if (self::$ensured) return ['ok' => true, 'cached' => true];
        $db = Database::instance();
        try {
            $db->query('SELECT 1 FROM addi_transactions LIMIT 1');
        } catch (\Throwable $e) {
            $path = (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2)) . '/install/addi.sql';
            if (is_file($path)) {
                $sql = preg_replace('/^\s*--.*$/m', '', (string)file_get_contents($path));
                foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
                    if ($statement !== '') $db->exec($statement . ';');
                }
            }
        }
        $this->migrateTables($db);
        self::$ensured = true;
        return ['ok' => true, 'cached' => false];
    }

    /**
     * Migra instalaciones anteriores: cada solicitud Addi necesita un orderId único
     * por intento (no el UUID del pedido), y el callback debe poder resolver ese intento.
     */
    private function migrateTables(\PDO $db): void
    {
        try {
            $col = $db->query("SHOW COLUMNS FROM addi_transactions LIKE 'addi_order_id'")->fetch();
            if (!$col) {
                $db->exec('ALTER TABLE addi_transactions ADD COLUMN addi_order_id VARCHAR(100) NULL AFTER order_uuid');
                $db->exec("UPDATE addi_transactions SET addi_order_id = order_uuid WHERE addi_order_id IS NULL OR addi_order_id = ''");
                $db->exec('ALTER TABLE addi_transactions MODIFY addi_order_id VARCHAR(100) NOT NULL');
            }
        } catch (\Throwable $e) {
        }
        try {
            $idx = $db->query("SHOW INDEX FROM addi_transactions WHERE Key_name = 'addi_site_order'")->fetch();
            if ($idx) $db->exec('ALTER TABLE addi_transactions DROP INDEX addi_site_order');
        } catch (\Throwable $e) {
        }
        try {
            $idx = $db->query("SHOW INDEX FROM addi_transactions WHERE Key_name = 'addi_site_order_id'")->fetch();
            if (!$idx) $db->exec('ALTER TABLE addi_transactions ADD UNIQUE KEY addi_site_order_id (site_id, addi_order_id)');
        } catch (\Throwable $e) {
        }
        try {
            $idx = $db->query("SHOW INDEX FROM addi_transactions WHERE Key_name = 'idx_addi_order_uuid'")->fetch();
            if (!$idx) $db->exec('ALTER TABLE addi_transactions ADD KEY idx_addi_order_uuid (site_id, order_uuid)');
        } catch (\Throwable $e) {
        }
        try {
            $cols = $db->query("SHOW COLUMNS FROM addi_transactions WHERE Field IN ('amount_cents','approved_amount_cents')")->fetchAll();
            foreach ($cols as $column) {
                if (stripos((string)($column['Type'] ?? ''), 'bigint') === false) {
                    $field = preg_replace('/[^a-z_]/i', '', (string)$column['Field']);
                    if ($field !== '') $db->exec('ALTER TABLE addi_transactions MODIFY ' . $field . ' BIGINT NOT NULL DEFAULT 0');
                }
            }
        } catch (\Throwable $e) {
        }
    }

    public function settings(): array
    {
        if (self::$cache !== null) return self::$cache;
        $map = [];
        try {
            $keys = array_keys(self::DEFAULTS);
            $in = implode(',', array_fill(0, count($keys), '?'));
            $stmt = Database::instance()->prepare("SELECT `key`, `value` FROM settings WHERE site_id = @site_id AND `key` IN ($in)");
            $stmt->execute($keys);
            foreach ($stmt->fetchAll() as $row) $map[(string)$row['key']] = (string)$row['value'];
        } catch (\Throwable $e) {
        }
        $s = [];
        foreach (self::DEFAULTS as $key => $default) $s[$key] = $map[$key] ?? $default;
        $env = in_array($s['addi_environment'], ['staging', 'production'], true) ? $s['addi_environment'] : 'staging';
        $clientSecret = TwoFactorService::decrypt((string)$s['addi_client_secret']);
        $callbackPassword = TwoFactorService::decrypt((string)$s['addi_callback_password']);
        $lastConfig = json_decode((string)$s['addi_last_config'], true) ?: [];
        return self::$cache = [
            'enabled' => $s['addi_enabled'] === '1',
            'environment' => $env,
            'ally_slug' => trim($s['addi_ally_slug']),
            'client_id' => trim($s['addi_client_id']),
            'client_secret' => $clientSecret,
            'callback_user' => trim($s['addi_callback_user']),
            'callback_password' => $callbackPassword,
            'callback_url' => trim($s['addi_callback_url']),
            'redirection_url' => trim($s['addi_redirection_url']),
            'logo_url' => trim($s['addi_logo_url']),
            'label' => trim($s['addi_label']) ?: 'Paga con Addi',
            'widget_enabled' => $s['addi_widget_enabled'] !== '0',
            'widget_title' => trim($s['addi_widget_title']) ?: 'Págalo a cuotas con Addi',
            'widget_styles' => trim($s['addi_widget_styles']),
            'force_visible' => $s['addi_force_visible'] === '1',
            'last_config' => is_array($lastConfig) ? $lastConfig : [],
            'last_config_at' => (string)$s['addi_last_config_at'],
            'callback_url_resolved' => $this->resolveCallbackUrl(trim($s['addi_callback_url'])),
            'redirection_url_resolved' => $this->resolveRedirectionTemplate(trim($s['addi_redirection_url'])),
        ];
    }

    public function adminSettings(): array
    {
        $s = $this->settings();
        return [
            'enabled' => $s['enabled'],
            'environment' => $s['environment'],
            'environment_label' => $s['environment'] === 'production' ? 'Producción' : 'Staging',
            'ally_slug' => $s['ally_slug'],
            'client_id' => $s['client_id'],
            'client_secret_set' => $s['client_secret'] !== '',
            'callback_user' => $s['callback_user'],
            'callback_password_set' => $s['callback_password'] !== '',
            'callback_url' => $s['callback_url_resolved'],
            'redirection_url' => $s['redirection_url_resolved'],
            'logo_url' => $s['logo_url'],
            'label' => $s['label'],
            'widget_enabled' => $s['widget_enabled'],
            'widget_title' => $s['widget_title'],
            'widget_styles' => $s['widget_styles'],
            'force_visible' => $s['force_visible'],
            'last_config' => $s['last_config'],
            'last_config_at' => $s['last_config_at'],
            'configured' => $this->configured($s),
        ];
    }

    public function saveSettings(array $data): array
    {
        $current = $this->settings();
        $values = [
            'addi_enabled' => !empty($data['enabled']) ? '1' : '0',
            'addi_environment' => in_array(($data['environment'] ?? ''), ['staging', 'production'], true) ? (string)$data['environment'] : 'staging',
            'addi_ally_slug' => mb_substr(trim((string)($data['ally_slug'] ?? '')), 0, 120),
            'addi_client_id' => mb_substr(trim((string)($data['client_id'] ?? '')), 0, 190),
            'addi_callback_user' => mb_substr(trim((string)($data['callback_user'] ?? '')), 0, 120),
            'addi_callback_url' => $this->cleanUrl((string)($data['callback_url'] ?? '')),
            'addi_redirection_url' => $this->cleanUrl((string)($data['redirection_url'] ?? '')),
            'addi_logo_url' => $this->cleanUrl((string)($data['logo_url'] ?? '')),
            'addi_label' => mb_substr(trim((string)($data['label'] ?? '')), 0, 80) ?: 'Paga con Addi',
            'addi_widget_enabled' => !empty($data['widget_enabled']) ? '1' : '0',
            'addi_widget_title' => mb_substr(trim((string)($data['widget_title'] ?? '')), 0, 120) ?: 'Págalo a cuotas con Addi',
            'addi_force_visible' => !empty($data['force_visible']) ? '1' : '0',
        ];
        $styles = (string)($data['widget_styles'] ?? '');
        if ($styles !== '') {
            $decoded = json_decode($styles, true);
            if (!is_array($decoded)) throw new \InvalidArgumentException('Los estilos del widget deben ser JSON válido.');
            $values['addi_widget_styles'] = mb_substr(json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 4000);
        } else {
            $values['addi_widget_styles'] = '';
        }
        if ($values['addi_ally_slug'] !== $current['ally_slug'] || $values['addi_environment'] !== $current['environment']) {
            $values['addi_last_config'] = '';
            $values['addi_last_config_at'] = '';
        }
        $clientSecret = trim((string)($data['client_secret'] ?? ''));
        if (!empty($data['clear_client_secret'])) {
            $values['addi_client_secret'] = '';
        } elseif ($clientSecret !== '') {
            $values['addi_client_secret'] = TwoFactorService::encrypt($clientSecret);
        }
        $callbackPassword = trim((string)($data['callback_password'] ?? ''));
        if (!empty($data['clear_callback_password'])) {
            $values['addi_callback_password'] = '';
        } elseif ($callbackPassword !== '') {
            $values['addi_callback_password'] = TwoFactorService::encrypt($callbackPassword);
        }
        $db = Database::instance();
        $stmt = $db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        foreach ($values as $key => $value) $stmt->execute([$key, (string)$value]);
        self::$cache = null;
        return $this->adminSettings();
    }

    public function publicMethod(): ?array
    {
        $s = $this->settings();
        if (!$s['enabled'] || !$this->configured($s)) return null;
        return [
            'id' => 'addi',
            'label' => $s['label'],
            'description' => 'Compra ahora y paga a cuotas con Addi.',
            'logo' => $s['logo_url'],
            'redirect' => true,
            'min_amount' => (int)($s['last_config']['minAmount'] ?? 0),
            'max_amount' => (int)($s['last_config']['maxAmount'] ?? 0),
            'is_active' => (bool)($s['last_config']['isActiveAlly'] ?? false),
            'policy' => $s['last_config']['policy'] ?? null,
        ];
    }

    public function isConfigured(): bool
    {
        return $this->configured($this->settings());
    }

    public function config(int $amountCents): array
    {
        $s = $this->settings();
        if ($s['ally_slug'] === '') return ['ok' => false, 'message' => 'Falta el ally slug de Addi.'];
        $amount = max(0, (int)round($amountCents / 100));
        $result = $this->http('https://channels-public-api.addi.com/allies/' . rawurlencode($s['ally_slug']) . '/config?requestedamount=' . $amount, ['method' => 'GET']);
        if ($result['status'] < 200 || $result['status'] >= 300) {
            return ['ok' => false, 'message' => $this->errorMessage($result), 'status' => $result['status']];
        }
        $data = json_decode($result['body'], true);
        if (!is_array($data)) return ['ok' => false, 'message' => 'Addi devolvió una respuesta inválida.'];
        $data['fetched_at'] = date('Y-m-d H:i:s');
        $this->saveLastConfig($data);
        return ['ok' => true, 'data' => $data];
    }

    public function authToken(): array
    {
        $s = $this->settings();
        if (!$this->configured($s)) return ['ok' => false, 'message' => 'La integración de Addi no está configurada.'];
        $env = $this->endpoints($s['environment']);
        $body = json_encode([
            'audience' => $env['audience'],
            'grant_type' => 'client_credentials',
            'client_id' => $s['client_id'],
            'client_secret' => $s['client_secret'],
        ], JSON_UNESCAPED_SLASHES);
        $result = $this->http($env['auth'] . '/oauth/token', [
            'method' => 'POST',
            'body' => $body,
            'headers' => ['Content-Type: application/json', 'Accept: application/json'],
        ]);
        if ($result['status'] < 200 || $result['status'] >= 300) {
            return ['ok' => false, 'message' => $this->errorMessage($result), 'status' => $result['status']];
        }
        $data = json_decode($result['body'], true);
        if (empty($data['access_token'])) return ['ok' => false, 'message' => 'Addi no devolvió un token.', 'status' => $result['status']];
        return ['ok' => true, 'token' => (string)$data['access_token'], 'expires_in' => (int)($data['expires_in'] ?? 0)];
    }

    public function testConnection(): array
    {
        $s = $this->settings();
        $config = $this->config(0);
        $auth = null;
        if ($this->configured($s)) $auth = $this->authToken();
        return [
            'ok' => !empty($config['ok']) && (!is_array($auth) || !empty($auth['ok'])),
            'environment' => $s['environment'],
            'configured' => $this->configured($s),
            'callback_url' => $s['callback_url_resolved'],
            'config' => $config,
            'auth' => is_array($auth) ? ['ok' => $auth['ok'], 'message' => $auth['message'] ?? '', 'expires_in' => $auth['expires_in'] ?? 0] : null,
        ];
    }

    public function createApplication(array $order): array
    {
        $this->ensureTables();
        $s = $this->settings();
        if (!$this->configured($s)) return ['ok' => false, 'message' => 'La integración de Addi no está configurada.'];
        $orderUuid = (string)($order['uuid'] ?? '');
        if ($orderUuid === '') return ['ok' => false, 'message' => 'El pedido no tiene identificador.'];
        $addiOrderId = $this->newAttemptId($order);
        $address = (array)($order['shipping_address'] ?? []);
        $customerName = trim((string)($order['customer_name'] ?? ''));
        if ($customerName === '' || empty($address)) return ['ok' => false, 'message' => 'Faltan datos del cliente para Addi.'];
        $nameParts = preg_split('/\s+/', $customerName, 2) ?: [];
        $firstName = (string)($nameParts[0] ?? '');
        $lastName = (string)($nameParts[1] ?? 'Cliente');
        $city = (string)($address['city'] ?? $address['municipality'] ?? '');
        $line = (string)($address['line1'] ?? '');
        $document = preg_replace('/\D+/', '', (string)($order['customer_document'] ?? ''));
        $phone = preg_replace('/\D+/', '', (string)($order['customer_phone'] ?? ''));
        if ($document === '' || $phone === '' || $line === '' || $city === '') return ['ok' => false, 'message' => 'Faltan documento, teléfono o dirección para Addi.'];
        $items = [];
        $index = 0;
        foreach ((array)($order['items'] ?? []) as $item) {
            $index++;
            $sku = !empty($item['product_id']) ? 'P-' . (int)$item['product_id'] : 'ITEM-' . $index;
            $items[] = [
                'sku' => $sku,
                'name' => mb_substr((string)($item['name'] ?? 'Producto'), 0, 200),
                'quantity' => (string)max(1, (int)($item['qty'] ?? 1)),
                'unitPrice' => $this->amount((int)($item['price_cents'] ?? 0)),
                'pictureUrl' => (string)($item['image'] ?? ''),
                'category' => 'Producto',
                'brand' => '',
            ];
        }
        $payload = [
            'orderId' => $addiOrderId,
            'totalAmount' => $this->amount((int)($order['total_cents'] ?? 0)),
            'shippingAmount' => $this->amount((int)($order['shipping_cents'] ?? 0)),
            'totalTaxesAmount' => $this->amount((int)($order['tax_cents'] ?? 0)),
            'currency' => (string)($order['currency'] ?? 'COP'),
            'client' => [
                'idType' => 'CC',
                'idNumber' => $document,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => (string)($order['customer_email'] ?? ''),
                'cellphone' => $phone,
                'cellphoneCountryCode' => '+57',
                'address' => ['lineOne' => $line, 'city' => $city, 'country' => 'CO'],
            ],
            'shippingAddress' => ['lineOne' => $line, 'city' => $city, 'country' => 'CO'],
            'billingAddress' => ['lineOne' => $line, 'city' => $city, 'country' => 'CO'],
            'items' => $items,
            'allyUrlRedirection' => [
                'logoUrl' => $s['logo_url'],
                'callbackUrl' => $s['callback_url_resolved'],
                'redirectionUrl' => $this->redirectionFor($orderUuid),
            ],
        ];
        $this->log($order, $addiOrderId, 'CREATED', 0, '', '', '');
        $auth = $this->authToken();
        if (empty($auth['ok'])) {
            $message = (string)($auth['message'] ?? 'No se pudo autenticar con Addi.');
            $this->log($order, $addiOrderId, 'ERROR', 0, '', '', $message);
            return ['ok' => false, 'message' => $message];
        }
        $env = $this->endpoints($s['environment']);
        $result = $this->http($env['api'] . '/v1/online-applications', [
            'method' => 'POST',
            'body' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'headers' => [
                'Authorization: Bearer ' . $auth['token'],
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Idempotency-Key: ' . $addiOrderId,
            ],
        ]);
        $redirect = (string)($result['location'] ?? '');
        if ($result['status'] < 300 || $result['status'] >= 400 || $redirect === '') {
            $message = $this->errorMessage($result);
            $this->log($order, $addiOrderId, 'ERROR', (int)$result['status'], '', '', $message);
            return ['ok' => false, 'message' => $message, 'status' => $result['status']];
        }
        $this->log($order, $addiOrderId, 'REDIRECT', (int)$result['status'], '', $redirect, '');
        return [
            'ok' => true,
            'redirect_url' => $redirect,
            'status' => $result['status'],
            'addi_order_id' => $addiOrderId,
        ];
    }

    public function handleCallback(string $raw, string $user, string $password): array
    {
        $this->ensureTables();
        $s = $this->settings();
        if ($s['callback_user'] === '' || $s['callback_password'] === '') {
            return ['ok' => false, 'status' => 503, 'message' => 'Callback de Addi sin credenciales configuradas.'];
        }
        if (!hash_equals($s['callback_user'], $user) || !hash_equals($s['callback_password'], $password)) {
            return ['ok' => false, 'status' => 401, 'message' => 'No autorizado.'];
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['orderId'])) return ['ok' => false, 'status' => 400, 'message' => 'Payload inválido.'];
        $addiOrderId = (string)$data['orderId'];
        $db = Database::instance();
        $stmt = $db->prepare('SELECT * FROM addi_transactions WHERE site_id = @site_id AND addi_order_id = ? LIMIT 1');
        $stmt->execute([$addiOrderId]);
        $attempt = $stmt->fetch() ?: [];
        if (!$attempt) return ['ok' => false, 'status' => 404, 'message' => 'Intento de Addi no encontrado.'];
        $orderUuid = (string)($attempt['order_uuid'] ?? '');
        $order = (new StoreService())->orderByUuid($orderUuid);
        if (!$order) return ['ok' => false, 'status' => 404, 'message' => 'Pedido no encontrado.'];
        $status = strtoupper((string)($data['status'] ?? ''));
        $statuses = ['APPROVED', 'PENDING', 'REJECTED', 'ABANDONED', 'DECLINED', 'INTERNAL_ERROR'];
        if (!in_array($status, $statuses, true)) return ['ok' => false, 'status' => 400, 'message' => 'Estado de Addi inválido.'];
        $hasApprovedAmount = array_key_exists('approvedAmount', $data);
        $approved = (int)round((float)($data['approvedAmount'] ?? 0) * 100);
        $appId = mb_substr((string)($data['applicationId'] ?? ''), 0, 100);
        $alreadyPaid = (string)($order['payment_status'] ?? '') === 'paid';
        $this->log($order, $addiOrderId, $status, 200, $appId, '', '', $raw, $approved);
        if ($status === 'APPROVED') {
            if ($hasApprovedAmount && $approved !== (int)$order['total_cents']) {
                $db->prepare("UPDATE addi_transactions SET status = 'AMOUNT_MISMATCH', error = ?, callback_json = ?, callback_at = NOW() WHERE site_id = @site_id AND addi_order_id = ?")
                    ->execute(['Monto aprobado no coincide con el pedido.', $raw, $addiOrderId]);
                return ['ok' => true, 'status' => 200, 'body' => $raw];
            }
            if (!$alreadyPaid) {
                (new StoreService())->markPaid($orderUuid, $appId !== '' ? $appId : $addiOrderId, 'addi');
            }
            $meta = json_decode((string)($order['payment_meta'] ?? ''), true) ?: [];
            $meta['addi_order_id'] = $addiOrderId;
            $meta['addi_application_id'] = $appId;
            $meta['addi_status'] = $status;
            $meta['addi_approved_amount_cents'] = $hasApprovedAmount ? $approved : (int)$order['total_cents'];
            $db->prepare('UPDATE store_orders SET payment_meta = ? WHERE id = ? AND site_id = @site_id')->execute([json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int)$order['id']]);
        } elseif ($status !== 'PENDING' && !$alreadyPaid) {
            (new StoreService())->updateOrder((int)$order['id'], ['payment_status' => 'failed']);
        }
        if ($status !== 'APPROVED') {
            $meta = json_decode((string)($order['payment_meta'] ?? ''), true) ?: [];
            $meta['addi_order_id'] = $addiOrderId;
            $meta['addi_application_id'] = $appId;
            $meta['addi_status'] = $status;
            $db->prepare('UPDATE store_orders SET payment_meta = ? WHERE id = ? AND site_id = @site_id')->execute([json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int)$order['id']]);
        }
        return ['ok' => true, 'status' => 200, 'body' => $raw];
    }

    public function cancel(string $addiOrderId, ?int $amountCents = null): array
    {
        $this->ensureTables();
        $s = $this->settings();
        $db = Database::instance();
        $stmt = $db->prepare('SELECT * FROM addi_transactions WHERE site_id = @site_id AND addi_order_id = ? LIMIT 1');
        $stmt->execute([$addiOrderId]);
        $attempt = $stmt->fetch() ?: [];
        if (!$attempt) {
            // Compatibilidad con el panel anterior, que enviaba el UUID del pedido.
            $stmt = $db->prepare("SELECT * FROM addi_transactions WHERE site_id = @site_id AND order_uuid = ? AND status = 'APPROVED' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$addiOrderId]);
            $attempt = $stmt->fetch() ?: [];
        }
        if (!$attempt) return ['ok' => false, 'message' => 'Transacción Addi no encontrada.'];
        $order = (new StoreService())->orderByUuid((string)($attempt['order_uuid'] ?? ''));
        if (!$order || (string)$order['payment_method'] !== 'addi') return ['ok' => false, 'message' => 'Pedido Addi no encontrado.'];
        if (strtoupper((string)($attempt['status'] ?? '')) !== 'APPROVED') {
            return ['ok' => false, 'message' => 'Solo se puede cancelar una transacción aprobada por Addi.'];
        }
        $auth = $this->authToken();
        if (empty($auth['ok'])) return $auth;
        $amount = $amountCents ?? (int)($attempt['amount_cents'] ?? $order['total_cents']);
        $attemptId = (string)$attempt['addi_order_id'];
        $env = $this->endpoints($s['environment']);
        $result = $this->http($env['api'] . '/v1/online-applications/cancellations', [
            'method' => 'POST',
            'body' => json_encode([
                'orderId' => $attemptId,
                'amount' => $this->amount($amount),
            ], JSON_UNESCAPED_SLASHES),
            'headers' => ['Authorization: Bearer ' . $auth['token'], 'Content-Type: application/json', 'Accept: application/json'],
        ]);
        if ($result['status'] < 200 || $result['status'] >= 300) {
            $this->log($order, $attemptId, 'CANCEL_ERROR', (int)$result['status'], '', '', $this->errorMessage($result));
            return ['ok' => false, 'message' => $this->errorMessage($result), 'status' => $result['status']];
        }
        $this->log($order, $attemptId, 'CANCELLED', (int)$result['status'], '', '', '');
        if ($amount >= (int)$order['total_cents']) (new StoreService())->updateOrder((int)$order['id'], ['payment_status' => 'refunded']);
        return ['ok' => true, 'message' => 'Cancelación solicitada a Addi.', 'status' => $result['status']];
    }

    public function overview(int $limit = 100): array
    {
        $this->ensureTables();
        $s = $this->settings();
        $db = Database::instance();
        $stmt = $db->prepare('SELECT t.*, o.customer_name, o.customer_email, o.payment_status, o.fulfillment_status FROM addi_transactions t LEFT JOIN store_orders o ON o.uuid = t.order_uuid AND o.site_id = t.site_id WHERE t.site_id = @site_id ORDER BY t.id DESC LIMIT ' . max(1, min(500, $limit)));
        $stmt->execute();
        $transactions = $stmt->fetchAll();
        $stats = ['total' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0, 'amount_cents' => 0];
        try {
            $st = $db->query("SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) AS approved, SUM(CASE WHEN status IN ('PENDING','REDIRECT','CREATED') THEN 1 ELSE 0 END) AS pending, SUM(CASE WHEN status IN ('REJECTED','DECLINED','ABANDONED','INTERNAL_ERROR','ERROR') THEN 1 ELSE 0 END) AS rejected, SUM(CASE WHEN status = 'APPROVED' THEN approved_amount_cents ELSE 0 END) AS amount_cents FROM addi_transactions WHERE site_id = @site_id");
            $stats = array_map('intval', $st->fetch() ?: $stats);
        } catch (\Throwable $e) {
        }
        return [
            'settings' => $this->adminSettings(),
            'transactions' => $transactions,
            'stats' => $stats,
            'endpoints' => $this->endpoints($s['environment']),
        ];
    }

    public function widgetScriptUrl(): string
    {
        return 'https://s3.amazonaws.com/widgets.addi.com/bundle.min.js';
    }

    public function widgetHtml(float $price): string
    {
        $s = $this->settings();
        if (!$s['enabled'] || !$s['widget_enabled'] || !$this->configured($s) || $price <= 0) return '';
        if (($s['last_config']['isActiveAlly'] ?? null) !== true && !$s['force_visible']) return '';
        $min = (int)($s['last_config']['minAmount'] ?? 0);
        $max = (int)($s['last_config']['maxAmount'] ?? 0);
        if ($min > 0 && $price < $min) return '';
        if ($max > 0 && $price > $max) return '';
        $styles = '';
        if ($s['widget_styles'] !== '') {
            $decoded = json_decode($s['widget_styles'], true);
            if (is_array($decoded)) $styles = " custom-widget-styles='" . htmlspecialchars(json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') . "'";
        }
        return '<div class="sonika-addi-widget" style="margin:10px 0 14px;padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;background:#fafafa"><div class="sonika-addi-widget__title" style="font-size:11px;font-weight:700;color:#374151;margin-bottom:6px">' . htmlspecialchars($s['widget_title'], ENT_QUOTES, 'UTF-8') . '</div><addi-widget price="' . (int)round($price) . '" ally-slug="' . htmlspecialchars($s['ally_slug'], ENT_QUOTES, 'UTF-8') . '"' . $styles . '></addi-widget></div>';
    }

    public function widgetAssets(): string
    {
        $s = $this->settings();
        if (!$s['enabled'] || !$s['widget_enabled'] || !$this->configured($s)) return '';
        if (($s['last_config']['isActiveAlly'] ?? null) !== true && !$s['force_visible']) return '';
        return '<script src="' . htmlspecialchars($this->widgetScriptUrl(), ENT_QUOTES, 'UTF-8') . '" defer></script>';
    }

    private function configured(array $s): bool
    {
        return $s['ally_slug'] !== '' && $s['client_id'] !== '' && $s['client_secret'] !== '';
    }

    private function endpoints(string $environment): array
    {
        if ($environment === 'production') {
            return ['auth' => 'https://auth.addi.com', 'api' => 'https://api.addi.com', 'audience' => 'https://api.addi.com'];
        }
        return ['auth' => 'https://auth.addi-staging.com', 'api' => 'https://api.addi-staging.com', 'audience' => 'https://api.staging.addi.com'];
    }

    private function amount(int $cents): string
    {
        return number_format(max(0, $cents) / 100, 2, '.', '');
    }

    /**
     * Addi exige un orderId único por intento. No se puede reutilizar el UUID del
     * pedido: si el cliente abandona y vuelve a intentar, debe ser otro orderId.
     */
    private function newAttemptId(array $order): string
    {
        $base = (string)($order['order_number'] ?? $order['uuid'] ?? 'ADDI');
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base) ?: 'ADDI';
        return mb_substr('SONIKA-' . $base . '-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4))), 0, 100);
    }

    private function redirectionFor(string $orderUuid): string
    {
        $base = $this->settings()['redirection_url_resolved'];
        return str_replace('{order_id}', rawurlencode($orderUuid), $base);
    }

    private function resolveCallbackUrl(string $configured): string
    {
        if ($configured !== '' && preg_match('~^https?://~i', $configured)) return $configured;
        $appUrl = rtrim((string)Config::get('APP_URL', 'https://sonika.com.co'), '/');
        return $appUrl . '/api/v1/public/addi/callback';
    }

    private function resolveRedirectionTemplate(string $configured): string
    {
        if ($configured !== '' && preg_match('~^https?://~i', $configured)) {
            return str_contains($configured, '{order_id}') ? $configured : $configured . (str_contains($configured, '?') ? '&' : '?') . 'order_id={order_id}';
        }
        $appUrl = rtrim((string)Config::get('APP_URL', 'https://sonika.com.co'), '/');
        return $appUrl . '/pedido/{order_id}';
    }

    private function cleanUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') return '';
        return preg_match('~^https?://~i', $value) ? mb_substr($value, 0, 900) : '';
    }

    private function saveLastConfig(array $config): void
    {
        try {
            $db = Database::instance();
            $stmt = $db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, 'addi_last_config', ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
            $stmt->execute([json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            $stmt = $db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, 'addi_last_config_at', ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
            $stmt->execute([date('Y-m-d H:i:s')]);
            self::$cache = null;
        } catch (\Throwable $e) {
        }
    }

    private function log(array $order, string $addiOrderId, string $status, int $httpStatus = 0, string $applicationId = '', string $redirect = '', string $error = '', string $callback = '', int $approvedCents = 0): void
    {
        try {
            $this->ensureTables();
            $db = Database::instance();
            $orderUuid = (string)($order['uuid'] ?? '');
            if ($addiOrderId === '') $addiOrderId = $orderUuid;
            $stmt = $db->prepare('INSERT INTO addi_transactions (site_id, order_uuid, addi_order_id, order_number, application_id, status, amount_cents, approved_amount_cents, currency, redirect_url, error, callback_json, callback_at) VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE application_id = COALESCE(VALUES(application_id), application_id), status = VALUES(status), approved_amount_cents = GREATEST(approved_amount_cents, VALUES(approved_amount_cents)), redirect_url = COALESCE(VALUES(redirect_url), redirect_url), error = VALUES(error), callback_json = COALESCE(VALUES(callback_json), callback_json), callback_at = IF(VALUES(callback_json) IS NULL, callback_at, NOW())');
            $stmt->execute([
                $orderUuid,
                $addiOrderId,
                (string)($order['order_number'] ?? ''),
                $applicationId !== '' ? $applicationId : null,
                mb_substr($status, 0, 30),
                (int)($order['total_cents'] ?? 0),
                max(0, $approvedCents),
                (string)($order['currency'] ?? 'COP'),
                $redirect !== '' ? mb_substr($redirect, 0, 1000) : null,
                $error !== '' ? mb_substr($error, 0, 500) : null,
                $callback !== '' ? $callback : null,
                $callback !== '' ? date('Y-m-d H:i:s') : null,
            ]);
        } catch (\Throwable $e) {
        }
    }

    private function errorMessage(array $result): string
    {
        $body = json_decode((string)($result['body'] ?? ''), true);
        if (is_array($body)) {
            if (!empty($body['message'])) return (string)$body['message'];
            if (!empty($body['error_description'])) return (string)$body['error_description'];
            if (!empty($body['error'])) return (string)$body['error'];
        }
        if (!empty($result['error'])) return (string)$result['error'];
        return 'Addi respondió con HTTP ' . (int)($result['status'] ?? 0) . '.';
    }

    private function http(string $url, array $options = []): array
    {
        if (!function_exists('curl_init')) return ['status' => 0, 'body' => '', 'headers' => [], 'location' => '', 'error' => 'La extensión cURL de PHP no está disponible.'];
        $headers = [];
        $ch = curl_init($url);
        $method = strtoupper((string)($options['method'] ?? 'GET'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(['User-Agent: SONIKA-WWI-Addi/1.0'], (array)($options['headers'] ?? [])));
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string)($options['body'] ?? ''));
        }
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $line) use (&$headers) {
            $length = strlen($line);
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            return $length;
        });
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = $body === false ? (string)curl_error($ch) : '';
        curl_close($ch);
        return ['status' => $status, 'body' => $body === false ? '' : (string)$body, 'headers' => $headers, 'location' => (string)($headers['location'] ?? ''), 'error' => $error];
    }
}
