<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

class SistecreditoService
{
    private static bool $ensured = false;

    private const DEFAULTS = [
        'enabled' => '0',
        'environment' => 'staging',
        'sc_origen' => 'Staging',
        'payment_method_id' => '2',
        'currency' => 'COP',
        'timeout_seconds' => '25',
        'sandbox_status' => 'PendingForPaymentMethod',
        'client_id' => '',
        'client_secret' => '',
        'ally_slug' => '',
        'redirect_url' => '',
        'notification_url' => '',
    ];

    private const SECRET_KEYS = ['client_secret'];
    private const SANDBOX_STATUSES = ['PendingForPaymentMethod', 'Rejected', 'Pending', 'Approved', 'Cancelled'];
    private const BASE_URL = 'https://api.credinet.co/pay';

    public function ensureTables(): array
    {
        if (self::$ensured) return ['ok' => true, 'cached' => true];
        $root = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
        $path = $root . '/install/sistecredito.sql';
        if (!is_file($path)) return ['ok' => false, 'message' => 'No se encontro el esquema de Sistecrédito.'];
        $sql = preg_replace('/^\s*--.*$/m', '', (string)file_get_contents($path));
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement !== '') Database::instance()->exec($statement . ';');
        }
        self::$ensured = true;
        return ['ok' => true, 'cached' => false];
    }

    public function config(): array
    {
        $this->ensureTables();
        $out = self::DEFAULTS;
        try {
            $st = Database::instance()->query("SELECT setting_key, setting_value FROM sistecredito_settings WHERE site_id = @site_id");
            foreach ($st->fetchAll() as $row) {
                $key = (string)($row['setting_key'] ?? '');
                if (!array_key_exists($key, $out)) continue;
                $value = (string)($row['setting_value'] ?? '');
                $out[$key] = in_array($key, self::SECRET_KEYS, true) ? TwoFactorService::decrypt($value) : $value;
            }
        } catch (\Throwable $e) {
        }
        return $out;
    }

    public function adminConfig(): array
    {
        $c = $this->config();
        $secret = (string)$c['client_secret'];
        $c['vendor_id'] = (string)$c['client_id'];
        $c['store_id'] = (string)$c['ally_slug'];
        $c['subscription_key_set'] = $secret !== '';
        unset($c['client_id'], $c['ally_slug'], $c['client_secret']);
        $c['payment_method_id'] = 2;
        $c['currency'] = 'COP';
        $c['configured'] = $this->isConfigured();
        $c['enabled'] = (string)$c['enabled'];
        $c['checkout_ready'] = $this->isAvailable();
        $c['connection_status'] = $c['configured'] ? 'configured' : 'pending';
        $c['last_saved_at'] = '';
        try {
            $st = Database::instance()->query("SELECT MAX(updated_at) FROM sistecredito_settings WHERE site_id = @site_id");
            $c['last_saved_at'] = (string)($st->fetchColumn() ?: '');
        } catch (\Throwable $e) {
        }
        return $c;
    }

    public function saveSettings(array $data): array
    {
        $this->ensureTables();
        $current = $this->config();
        $environment = in_array((string)($data['environment'] ?? ''), ['staging', 'production'], true) ? (string)$data['environment'] : $current['environment'];
        $scOrigen = trim((string)($data['sc_origen'] ?? '')) ?: ($environment === 'staging' ? 'Staging' : 'Production');
        $sandboxStatus = (string)($data['sandbox_status'] ?? $current['sandbox_status']);
        if (!in_array($sandboxStatus, self::SANDBOX_STATUSES, true)) $sandboxStatus = 'PendingForPaymentMethod';
        $values = [
            'enabled' => !empty($data['enabled']) ? '1' : '0',
            'environment' => $environment,
            'sc_origen' => mb_substr($scOrigen, 0, 40),
            'payment_method_id' => '2',
            'currency' => 'COP',
            'timeout_seconds' => (string)max(5, min(120, (int)($data['timeout_seconds'] ?? 25))),
            'sandbox_status' => $sandboxStatus,
            'client_id' => mb_substr(trim((string)($data['vendor_id'] ?? $data['client_id'] ?? '')), 0, 190),
            'ally_slug' => mb_substr(trim((string)($data['store_id'] ?? $data['ally_slug'] ?? '')), 0, 190),
            'redirect_url' => $this->safeUrl((string)($data['redirect_url'] ?? '')),
            'notification_url' => $this->safeUrl((string)($data['notification_url'] ?? '')),
            'client_secret' => '',
        ];
        $secret = trim((string)($data['subscription_key'] ?? $data['client_secret'] ?? ''));
        if ($secret !== '') $values['client_secret'] = TwoFactorService::encrypt($secret);
        $stmt = Database::instance()->prepare("INSERT INTO sistecredito_settings (site_id, setting_key, setting_value, is_secret) VALUES (@site_id, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_secret = VALUES(is_secret), updated_at = NOW()");
        foreach ($values as $key => $value) {
            if ($key === 'client_secret' && $value === '') continue;
            $stmt->execute([$key, (string)$value, in_array($key, self::SECRET_KEYS, true) ? 1 : 0]);
        }
        return $this->adminConfig();
    }

    public function isConfigured(): bool
    {
        $c = $this->config();
        return trim((string)$c['client_id']) !== ''
            && trim((string)$c['client_secret']) !== ''
            && trim((string)$c['ally_slug']) !== ''
            && $c['currency'] === 'COP';
    }

    public function isAvailable(): bool
    {
        $c = $this->config();
        return (string)$c['enabled'] === '1' && $this->isConfigured();
    }

    public function publicMethod(): array
    {
        $available = $this->isAvailable();
        return [
            'id' => 'sistecredito',
            'label' => 'Sistecrédito',
            'description' => 'Compra ahora y paga a cuotas con Sistecrédito.',
            'active' => $available,
            'disabled' => !$available,
            'coming_soon' => !$available,
        ];
    }

    public function createApplication(array $order): array
    {
        $this->ensureTables();
        if (!$this->isAvailable()) {
            return ['ok' => false, 'message' => 'Sistecrédito no está habilitado o configurado.'];
        }
        $orderId = (int)($order['id'] ?? 0);
        $uuid = (string)($order['uuid'] ?? '');
        if ($orderId <= 0 || $uuid === '') return ['ok' => false, 'message' => 'Pedido inválido.'];
        $db = Database::instance();
        $existing = $db->prepare("SELECT * FROM store_payment_intents WHERE site_id = @site_id AND order_id = ? AND provider = 'sistecredito' AND idempotency_key = ? LIMIT 1");
        $existing->execute([$orderId, 'sistecredito-' . $uuid]);
        $intent = $existing->fetch() ?: null;
        if ($intent && !empty($intent['provider_payment_id'])) {
            $fetched = $this->fetchTransaction((string)$intent['provider_payment_id']);
            if (!empty($fetched['ok'])) $this->persistStatusFromData($order, $intent, $fetched['data']);
            $latest = $this->intent((int)$intent['id']);
            $redirect = (string)($latest['redirect_url'] ?? '');
            $status = (string)($latest['status'] ?? 'pending');
            return ['ok' => true, 'provider_payment_id' => (string)$intent['provider_payment_id'], 'redirect_url' => $redirect, 'status' => $status, 'pending' => $redirect === '' && !in_array($status, ['failed', 'cancelled', 'rejected'], true)];
        }

        $payload = $this->buildPayload($order);
        $response = $this->request('POST', '/create', $payload);
        if (empty($response['ok'])) {
            return ['ok' => false, 'message' => (string)($response['message'] ?? 'No se pudo crear la transacción con Sistecrédito.')];
        }
        $data = (array)($response['data'] ?? []);
        $providerId = (string)($data['_id'] ?? '');
        $redirect = $this->redirectFromData($data);
        $status = $this->statusFromData($data);
        if ($providerId !== '' && $redirect === '' && !in_array($status, ['Rejected', 'Cancelled', 'Expired', 'Abandoned', 'Failed'], true)) {
            for ($i = 0; $i < 6; $i++) {
                usleep(600000);
                $fetched = $this->fetchTransaction($providerId);
                if (!empty($fetched['ok'])) {
                    $data = (array)$fetched['data'];
                    $redirect = $this->redirectFromData($data);
                    $status = $this->statusFromData($data);
                    if ($redirect !== '' || in_array($status, ['Rejected', 'Cancelled', 'Expired', 'Abandoned', 'Failed'], true)) break;
                }
            }
        }
        $intent = $this->saveIntent($order, $providerId, $status, $redirect, $response['raw'] ?? '', $data);
        if ($providerId === '' || $this->isHttpFailure($response)) {
            return ['ok' => false, 'message' => (string)($response['message'] ?? 'Sistecrédito rechazó la solicitud.')];
        }
        if ($this->mapStatus($status) === 'failed') {
            return ['ok' => false, 'message' => 'Sistecrédito rechazó la solicitud.', 'status' => 'failed'];
        }
        $pending = $redirect === '' && !in_array($this->mapStatus($status), ['failed', 'paid'], true);
        return [
            'ok' => true,
            'provider_payment_id' => $providerId,
            'redirect_url' => $redirect,
            'status' => $this->mapStatus($status),
            'pending' => $pending,
            'intent_id' => (int)($intent['id'] ?? 0),
        ];
    }

    public function fetchTransaction(string $transactionId): array
    {
        if ($transactionId === '') return ['ok' => false, 'message' => 'Transacción vacía.'];
        return $this->request('GET', '/GetTransactionResponse?transactionId=' . rawurlencode($transactionId), null);
    }

    public function statusForOrder(string $uuid): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT i.*, o.uuid, o.order_number, o.payment_status FROM store_payment_intents i JOIN store_orders o ON o.id = i.order_id AND o.site_id = i.site_id WHERE i.site_id = @site_id AND o.uuid = ? AND i.provider = 'sistecredito' ORDER BY i.id DESC LIMIT 1");
        $st->execute([$uuid]);
        $intent = $st->fetch();
        if (!$intent) return ['ok' => false, 'status' => 'missing', 'payment_status' => 'pending'];
        if (!empty($intent['provider_payment_id'])) {
            $fetched = $this->fetchTransaction((string)$intent['provider_payment_id']);
            if (!empty($fetched['ok'])) {
                $this->persistStatusFromData($intent, $intent, $fetched['data']);
                $intent = $this->intent((int)$intent['id']) ?: $intent;
                $mapped = (string)($intent['status'] ?? 'pending');
                if ($mapped === 'paid') {
                    try { (new StoreService())->markPaid((string)$intent['uuid'], (string)$intent['provider_payment_id'], 'sistecredito'); } catch (\Throwable $e) {}
                } elseif ($mapped === 'failed') {
                    Database::instance()->prepare("UPDATE store_orders SET payment_status = 'failed', updated_at = NOW() WHERE uuid = ? AND site_id = @site_id")->execute([(string)$intent['uuid']]);
                }
            }
        }
        return ['ok' => true, 'status' => (string)$intent['status'], 'payment_status' => (string)($intent['payment_status'] ?? 'pending'), 'redirect_url' => (string)($intent['redirect_url'] ?? '')];
    }

    public function handleCallback(array $payload, string $raw = '', string $ip = ''): array
    {
        $this->ensureTables();
        $data = (array)($payload['data'] ?? $payload);
        $providerId = (string)($data['_id'] ?? $data['transactionId'] ?? '');
        $invoice = (string)($data['invoice'] ?? ($data['orderId'] ?? ''));
        if ($providerId === '') return ['ok' => false, 'message' => 'Notificación sin identificador.'];
        $verified = $this->fetchTransaction($providerId);
        if (empty($verified['ok'])) return ['ok' => false, 'message' => 'No se pudo verificar la transacción con Sistecrédito.'];
        $verifiedData = (array)($verified['data'] ?? []);
        $verifiedId = (string)($verifiedData['_id'] ?? '');
        $verifiedInvoice = (string)($verifiedData['invoice'] ?? '');
        if ($verifiedId !== $providerId) return ['ok' => false, 'message' => 'Identificador no coincide.'];
        if ($invoice !== '' && $verifiedInvoice !== '' && $invoice !== $verifiedInvoice) return ['ok' => false, 'message' => 'Factura no coincide.'];
        $eventKey = hash('sha256', $providerId . '|' . $this->statusFromData($verifiedData) . '|' . $verifiedInvoice);
        $intentStmt = Database::instance()->prepare("SELECT * FROM store_payment_intents WHERE site_id = @site_id AND provider = 'sistecredito' AND (provider_payment_id = ? OR order_id IN (SELECT id FROM store_orders WHERE site_id = @site_id AND order_number = ?)) ORDER BY id DESC LIMIT 1");
        $intentStmt->execute([$providerId, $verifiedInvoice]);
        $intent = $intentStmt->fetch();
        if (!$intent) return ['ok' => false, 'message' => 'Intención no encontrada.'];
        $event = Database::instance()->prepare("INSERT IGNORE INTO store_payment_events (site_id, intent_id, provider, provider_event_key, provider_status, verified_status, validation_result, payload_sanitized, received_at, processed_at) VALUES (@site_id, ?, 'sistecredito', ?, ?, ?, 'verified', ?, NOW(), NOW())");
        $this->persistStatusFromData($intent, $intent, $verifiedData);
        $status = $this->mapStatus($this->statusFromData($verifiedData));
        $orderStmt = Database::instance()->prepare("SELECT * FROM store_orders WHERE site_id = @site_id AND id = ? LIMIT 1");
        $orderStmt->execute([(int)$intent['order_id']]);
        $order = $orderStmt->fetch();
        if (!$order) return ['ok' => false, 'message' => 'Pedido no encontrado.'];
        try {
            if ($status === 'paid') {
                (new StoreService())->markPaid((string)$order['uuid'], $providerId, 'sistecredito');
            } elseif ($status === 'failed') {
                Database::instance()->prepare("UPDATE store_orders SET payment_status = 'failed', updated_at = NOW() WHERE id = ? AND site_id = @site_id")->execute([(int)$order['id']]);
            }
            $meta = json_decode((string)($order['payment_meta'] ?? ''), true) ?: [];
            $meta['sistecredito_status'] = $status;
            $meta['sistecredito_provider_payment_id'] = $providerId;
            Database::instance()->prepare("UPDATE store_orders SET payment_meta = ?, updated_at = NOW() WHERE id = ? AND site_id = @site_id")
                ->execute([json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int)$order['id']]);
            $event->execute([(int)$intent['id'], $eventKey, (string)$this->statusFromData($verifiedData), $status, mb_substr(json_encode($data, JSON_UNESCAPED_UNICODE), 0, 10000)]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo actualizar el pedido.'];
        }
        return ['ok' => true, 'status' => $status];
    }

    public function overview(int $limit = 20): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $stats = ['intents' => 0, 'pending' => 0, 'approved' => 0, 'failed' => 0, 'events' => 0];
        try {
            $st = $db->query("SELECT COUNT(*) AS intents,
                SUM(CASE WHEN status IN ('created','redirected','pending') THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN status IN ('rejected','cancelled','expired','failed') THEN 1 ELSE 0 END) AS failed
                FROM store_payment_intents WHERE site_id = @site_id AND provider = 'sistecredito'");
            foreach (($st->fetch() ?: []) as $k => $v) $stats[$k] = (int)$v;
            $stats['events'] = (int)$db->query("SELECT COUNT(*) FROM store_payment_events WHERE site_id = @site_id AND provider = 'sistecredito'")->fetchColumn();
        } catch (\Throwable $e) {
        }
        return [
            'settings' => $this->adminConfig(),
            'stats' => $stats,
            'intents' => $this->intents($limit),
            'checkout_ready' => $this->isAvailable(),
            'endpoints' => ['create' => self::BASE_URL . '/create', 'status' => self::BASE_URL . '/GetTransactionResponse'],
        ];
    }

    public function intents(int $limit = 100, string $status = ''): array
    {
        $this->ensureTables();
        $limit = max(1, min(200, $limit));
        $sql = "SELECT i.*, o.order_number, o.payment_status AS order_payment_status, o.fulfillment_status, o.customer_name
            FROM store_payment_intents i LEFT JOIN store_orders o ON o.id = i.order_id AND o.site_id = i.site_id
            WHERE i.site_id = @site_id AND i.provider = 'sistecredito'";
        $params = [];
        if ($status !== '') { $sql .= " AND i.status = :status"; $params['status'] = $status; }
        $sql .= " ORDER BY i.id DESC LIMIT $limit";
        $st = Database::instance()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public function intent(int $id): ?array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT i.*, o.order_number, o.payment_status AS payment_status, o.payment_status AS order_payment_status, o.fulfillment_status, o.customer_name, o.total_cents AS order_total_cents
            FROM store_payment_intents i LEFT JOIN store_orders o ON o.id = i.order_id AND o.site_id = i.site_id
            WHERE i.id = :id AND i.site_id = @site_id AND i.provider = 'sistecredito' LIMIT 1");
        $st->execute(['id' => $id]);
        $row = $st->fetch();
        if (!$row) return null;
        $ev = Database::instance()->prepare("SELECT id, provider_event_key, provider_status, verified_status, validation_result, received_at, processed_at FROM store_payment_events WHERE site_id = @site_id AND intent_id = :id ORDER BY id DESC LIMIT 50");
        $ev->execute(['id' => $id]);
        $row['events'] = $ev->fetchAll();
        return $row;
    }

    private function buildPayload(array $order): array
    {
        $c = $this->config();
        $uuid = (string)($order['uuid'] ?? '');
        $base = rtrim((string)Config::get('APP_URL', 'https://sonika.com.co'), '/');
        $redirect = trim((string)$c['redirect_url']) ?: $base . '/pedido/' . rawurlencode($uuid);
        $notification = trim((string)$c['notification_url']) ?: $base . '/api/v1/public/store/sistecredito/callback';
        $isStaging = (string)$c['environment'] === 'staging';
        $sandboxStatus = in_array((string)$c['sandbox_status'], self::SANDBOX_STATUSES, true) ? (string)$c['sandbox_status'] : 'PendingForPaymentMethod';
        return [
            'invoice' => (string)($order['order_number'] ?? ''),
            'description' => 'Pedido SONIKA ' . (string)($order['order_number'] ?? ''),
            'paymentMethod' => ['paymentMethodId' => 2],
            'currency' => 'COP',
            'value' => (int)round(((int)($order['total_cents'] ?? 0)) / 100),
            'tax' => (int)round(((int)($order['tax_cents'] ?? 0)) / 100),
            'taxBase' => (int)round(((int)($order['subtotal_net_cents'] ?? 0)) / 100),
            'sandbox' => ['isActive' => $isStaging, 'status' => $sandboxStatus],
            'urlResponse' => $redirect,
            'urlConfirmation' => $notification,
            'methodConfirmation' => 'POST',
            'client' => [
                'docType' => 'CC',
                'document' => (string)($order['customer_document'] ?? ''),
            ],
            'extraData' => ['order_uuid' => $uuid, 'order_number' => (string)($order['order_number'] ?? '')],
        ];
    }

    private function saveIntent(array $order, string $providerId, string $status, string $redirect, string $raw, array $data): array
    {
        $db = Database::instance();
        $existing = $db->prepare("SELECT * FROM store_payment_intents WHERE site_id = @site_id AND order_id = ? AND provider = 'sistecredito' AND idempotency_key = ? LIMIT 1");
        $existing->execute([(int)$order['id'], 'sistecredito-' . (string)$order['uuid']]);
        $row = $existing->fetch() ?: null;
        $mapped = $this->mapStatus($status);
        $expires = date('Y-m-d H:i:s', time() + 900);
        if ($row) {
            $db->prepare("UPDATE store_payment_intents SET provider_payment_id = ?, status = ?, redirect_url = ?, expires_at = ?, raw_create_response = ?, raw_last_status = ?, last_checked_at = NOW(), updated_at = NOW() WHERE id = ? AND site_id = @site_id")
                ->execute([$providerId !== '' ? $providerId : $row['provider_payment_id'], $mapped, $redirect, $expires, $raw, json_encode($data, JSON_UNESCAPED_UNICODE), (int)$row['id']]);
            $row['status'] = $mapped;
            $row['redirect_url'] = $redirect;
            return $row;
        }
        $db->prepare("INSERT INTO store_payment_intents (site_id, order_id, provider, provider_payment_id, idempotency_key, status, amount_cents, currency, payment_method_id, redirect_url, expires_at, raw_create_response, raw_last_status, last_checked_at, created_at, updated_at) VALUES (@site_id, ?, 'sistecredito', ?, ?, ?, ?, 'COP', 2, ?, ?, ?, ?, NOW(), NOW(), NOW())")
            ->execute([(int)$order['id'], $providerId, 'sistecredito-' . (string)$order['uuid'], $mapped, (int)($order['total_cents'] ?? 0), $redirect, $expires, $raw, json_encode($data, JSON_UNESCAPED_UNICODE)]);
        return ['id' => (int)$db->lastInsertId(), 'status' => $mapped, 'redirect_url' => $redirect];
    }

    private function persistStatusFromData(array $order, array $intent, array $data): void
    {
        $providerId = (string)($intent['provider_payment_id'] ?? '');
        $redirect = $this->redirectFromData($data);
        $status = $this->mapStatus($this->statusFromData($data));
        if ($providerId === '') return;
        $db = Database::instance();
        $db->prepare("UPDATE store_payment_intents SET status = ?, redirect_url = CASE WHEN ? <> '' THEN ? ELSE redirect_url END, raw_last_status = ?, last_checked_at = NOW(), updated_at = NOW() WHERE id = ? AND site_id = @site_id")
            ->execute([$status, $redirect, $redirect, json_encode($data, JSON_UNESCAPED_UNICODE), (int)$intent['id']]);
    }

    private function redirectFromData(array $data): string
    {
        return (string)($data['paymentMethodResponse']['paymentRedirectUrl'] ?? $data['paymentRedirectUrl'] ?? '');
    }

    private function statusFromData(array $data): string
    {
        return (string)($data['transactionStatus'] ?? $data['paymentMethodResponse']['statusResponse'] ?? '');
    }

    private function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'approved' => 'approved',
            'rejected', 'cancelled', 'canceled', 'expired', 'abandoned', 'failed' => 'failed',
            'pendingforpaymentmethod', 'pending', 'started' => 'pending',
            default => 'pending',
        };
    }

    private function request(string $method, string $path, ?array $body): array
    {
        $c = $this->config();
        $headers = [
            'SCLocation: 0,0',
            'SCOrigen: ' . ((string)$c['environment'] === 'production' ? 'Production' : 'Staging'),
            'country: co',
            'Content-Type: application/json',
            'Ocp-Apim-Subscription-Key: ' . (string)$c['client_secret'],
            'ApplicationKey: ' . (string)$c['ally_slug'],
            'ApplicationToken: ' . (string)$c['client_id'],
        ];
        $ch = curl_init(self::BASE_URL . $path);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => max(5, min(120, (int)$c['timeout_seconds'])),
            CURLOPT_CUSTOMREQUEST => $method,
        ];
        if ($body !== null) $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($raw === false) return ['ok' => false, 'message' => 'Error de conexión con Sistecrédito: ' . $error, 'raw' => ''];
        $json = json_decode((string)$raw, true);
        if (!is_array($json)) return ['ok' => false, 'message' => 'Respuesta inválida de Sistecrédito.', 'raw' => (string)$raw, 'http' => $http];
        $errorCode = (int)($json['errorCode'] ?? 0);
        if ($http < 200 || $http >= 300 || $errorCode > 0) {
            $message = (string)($json['message'] ?? 'Sistecrédito rechazó la solicitud.');
            return ['ok' => false, 'message' => $message, 'raw' => (string)$raw, 'http' => $http, 'data' => (array)($json['data'] ?? [])];
        }
        return ['ok' => true, 'raw' => (string)$raw, 'http' => $http, 'data' => (array)($json['data'] ?? $json)];
    }

    private function isHttpFailure(array $response): bool
    {
        return empty($response['ok']);
    }

    private function safeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        if (!preg_match('~^https?://~i', $url)) return '';
        return mb_substr($url, 0, 500);
    }
}
