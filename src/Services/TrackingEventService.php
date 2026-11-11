<?php
namespace App\Services;

use App\Core\Database;

/**
 * Ledger interno del Tracking & Conversion Brick.
 * Una fila por evento y plataforma. No sustituye seo_tracking_events.
 */
class TrackingEventService
{
    private static bool $ready = false;

    public const EVENT_NAMES = ['page_view', 'view_content', 'add_to_cart', 'initiate_checkout', 'purchase', 'test'];
    public const CLIENT_STATUSES = ['generated', 'dispatched', 'failed', 'skipped_no_consent'];

    public const DESTINATIONS = [
        'meta' => [
            'page_view' => 'PageView',
            'view_content' => 'ViewContent',
            'add_to_cart' => 'AddToCart',
            'initiate_checkout' => 'InitiateCheckout',
            'purchase' => 'Purchase',
            'test' => 'TestEvent',
        ],
        'ga4' => [
            'page_view' => 'page_view',
            'view_content' => 'view_item',
            'add_to_cart' => 'add_to_cart',
            'initiate_checkout' => 'begin_checkout',
            'purchase' => 'purchase',
            'test' => 'test_event',
        ],
        'internal' => [
            'test' => 'TestEvent',
        ],
    ];

    public function ensureTables(): bool
    {
        if (self::$ready) return true;
        $db = Database::instance();
        try {
            $db->query('SELECT 1 FROM tracking_events LIMIT 1');
            $db->query('SELECT 1 FROM tracking_order_attribution LIMIT 1');
            self::$ready = true;
            return true;
        } catch (\Throwable $e) {
        }

        $path = (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2)) . '/install/tracking_conversion.sql';
        if (!is_file($path)) return false;
        $sql = preg_replace('/^\s*--.*$/m', '', (string)file_get_contents($path));
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            try {
                $db->exec($statement);
            } catch (\Throwable $e) {
            }
        }
        try {
            $db->query('SELECT 1 FROM tracking_events LIMIT 1');
            $db->query('SELECT 1 FROM tracking_order_attribution LIMIT 1');
            self::$ready = true;
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function insert(array $row): bool
    {
        if (!$this->ensureTables()) return false;
        $eventName = (string)($row['event_name'] ?? '');
        $platform = (string)($row['platform'] ?? '');
        if (!in_array($eventName, self::EVENT_NAMES, true)) return false;
        if (!isset(self::DESTINATIONS[$platform][$eventName])) return false;

        $status = (string)($row['status'] ?? 'generated');
        if (!in_array($status, array_merge(self::CLIENT_STATUSES, ['pending_payment', 'ready', 'cancelled']), true)) {
            $status = 'generated';
        }
        $paramsJson = null;
        $params = $row['params'] ?? null;
        if (is_array($params)) {
            $clean = $this->sanitizeParams($params);
            $encoded = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded !== false) $paramsJson = $encoded;
        } elseif (is_string($params) && $params !== '') {
            $decoded = json_decode($params, true);
            if (is_array($decoded)) {
                $encoded = json_encode($this->sanitizeParams($decoded), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($encoded !== false) $paramsJson = $encoded;
            }
        }
        $currency = strtoupper(trim((string)($row['currency'] ?? '')));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) $currency = null;
        $occurredAt = $this->dateOrNow((string)($row['occurred_at'] ?? ''));
        $dispatchedAt = $status === 'dispatched' ? date('Y-m-d H:i:s') : null;

        try {
            Database::instance()->prepare(
                'INSERT INTO tracking_events (
                    site_id, event_id, event_name, platform, destination_event, session_id, visitor_hash,
                    order_uuid, order_number, consent_snapshot, params, value_cents, currency, status, error,
                    occurred_at, dispatched_at
                ) VALUES (
                    @site_id, :event_id, :event_name, :platform, :destination, :session_id, :visitor_hash,
                    :order_uuid, :order_number, :consent, :params, :value_cents, :currency, :status, :error,
                    :occurred_at, :dispatched_at
                ) ON DUPLICATE KEY UPDATE
                    destination_event = VALUES(destination_event),
                    session_id = COALESCE(VALUES(session_id), session_id),
                    visitor_hash = COALESCE(VALUES(visitor_hash), visitor_hash),
                    order_uuid = COALESCE(VALUES(order_uuid), order_uuid),
                    order_number = COALESCE(VALUES(order_number), order_number),
                    consent_snapshot = VALUES(consent_snapshot),
                    params = COALESCE(VALUES(params), params),
                    value_cents = COALESCE(VALUES(value_cents), value_cents),
                    currency = COALESCE(VALUES(currency), currency),
                    status = CASE
                        WHEN status IN (\'dispatched\', \'cancelled\') AND VALUES(status) IN (\'pending_payment\', \'ready\') THEN status
                        ELSE VALUES(status)
                    END,
                    error = IF(VALUES(status) IN (\'pending_payment\', \'ready\'), NULL, VALUES(error)),
                    dispatched_at = IF(VALUES(status) = \'dispatched\', COALESCE(dispatched_at, NOW()), dispatched_at)'
            )->execute([
                'event_id' => mb_substr((string)($row['event_id'] ?? ''), 0, 80),
                'event_name' => $eventName,
                'platform' => $platform,
                'destination' => (string)(self::DESTINATIONS[$platform][$eventName] ?? ''),
                'session_id' => $this->nullable((string)($row['session_id'] ?? ''), 64),
                'visitor_hash' => $this->nullable((string)($row['visitor_hash'] ?? ''), 64),
                'order_uuid' => $this->nullable((string)($row['order_uuid'] ?? ''), 36),
                'order_number' => $this->nullable((string)($row['order_number'] ?? ''), 30),
                'consent' => $this->nullable((string)($row['consent_snapshot'] ?? ''), 120),
                'params' => $paramsJson,
                'value_cents' => isset($row['value_cents']) ? max(0, min(1000000000000, (int)$row['value_cents'])) : null,
                'currency' => $currency,
                'status' => $status,
                'error' => $this->nullable((string)($row['error'] ?? ''), 255),
                'occurred_at' => $occurredAt,
                'dispatched_at' => $dispatchedAt,
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function createPurchaseDraft(string $orderUuid, string $orderNumber, array $params, array $consent, array $platforms): int
    {
        $created = 0;
        $params['order_id'] = $params['order_id'] ?? $orderUuid;
        $params['order_number'] = $params['order_number'] ?? $orderNumber;
        foreach (array_values(array_unique($platforms)) as $platform) {
            if (!in_array($platform, ['meta', 'ga4'], true)) continue;
            if ($platform === 'meta' && empty($consent['marketing'])) continue;
            if ($platform === 'ga4' && empty($consent['analytics'])) continue;
            $ok = $this->insert([
                'event_id' => 'purchase_' . $orderUuid,
                'event_name' => 'purchase',
                'platform' => $platform,
                'destination_event' => self::DESTINATIONS[$platform]['purchase'],
                'order_uuid' => $orderUuid,
                'order_number' => $orderNumber,
                'consent_snapshot' => TrackingConsentService::snapshot(),
                'params' => $params,
                'value_cents' => (int)($params['value_cents'] ?? 0),
                'currency' => (string)($params['currency'] ?? ''),
                'status' => 'pending_payment',
                'error' => null,
                'occurred_at' => date('Y-m-d H:i:s'),
            ]);
            if ($ok) $created++;
        }
        return $created;
    }

    public function markPurchaseReady(string $orderUuid): int
    {
        if (!$this->ensureTables()) return 0;
        try {
            $stmt = Database::instance()->prepare(
                "UPDATE tracking_events SET status = 'ready', error = NULL, updated_at = NOW()
                 WHERE site_id = @site_id AND order_uuid = :uuid AND event_name = 'purchase' AND status = 'pending_payment'"
            );
            $stmt->execute(['uuid' => $orderUuid]);
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function markPurchaseCancelled(string $orderUuid, string $reason = ''): int
    {
        if (!$this->ensureTables()) return 0;
        try {
            $stmt = Database::instance()->prepare(
                "UPDATE tracking_events SET status = 'cancelled', error = :reason, updated_at = NOW()
                 WHERE site_id = @site_id AND order_uuid = :uuid AND event_name = 'purchase'
                   AND status IN ('pending_payment', 'ready', 'failed')"
            );
            $stmt->execute(['uuid' => $orderUuid, 'reason' => mb_substr($reason, 0, 255)]);
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function purchaseRows(string $orderUuid): array
    {
        if (!$this->ensureTables()) return [];
        try {
            $stmt = Database::instance()->prepare(
                "SELECT * FROM tracking_events
                 WHERE site_id = @site_id AND order_uuid = :uuid AND event_name = 'purchase'
                   AND status IN ('ready', 'failed')
                 ORDER BY platform ASC"
            );
            $stmt->execute(['uuid' => $orderUuid]);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$row) {
                $row['params'] = json_decode((string)($row['params'] ?? '{}'), true) ?: [];
            }
            unset($row);
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function recordClient(array $payload): int
    {
        if (!$this->ensureTables()) return 0;
        $eventName = (string)($payload['event_name'] ?? '');
        if (!in_array($eventName, self::EVENT_NAMES, true)) return 0;
        $eventId = mb_substr(trim((string)($payload['event_id'] ?? '')), 0, 80);
        if (!preg_match('/^[A-Za-z0-9_:.\-]{6,80}$/', $eventId)) return 0;

        $platforms = is_array($payload['platforms'] ?? null) ? $payload['platforms'] : [];
        $params = $this->sanitizeParams(is_array($payload['params'] ?? null) ? $payload['params'] : []);
        $orderUuid = $this->nullable((string)($payload['order_uuid'] ?? ''), 36);
        $orderNumber = $this->nullable((string)($payload['order_number'] ?? ''), 30);
        $consent = $this->nullable((string)($payload['consent_snapshot'] ?? ''), 120);
        $valueCents = isset($payload['value_cents']) ? max(0, min(1000000000000, (int)$payload['value_cents'])) : null;
        $currency = strtoupper(trim((string)($payload['currency'] ?? '')));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) $currency = null;

        $count = 0;
        foreach (['meta', 'ga4'] as $platform) {
            $state = is_array($platforms[$platform] ?? null) ? $platforms[$platform] : null;
            if ($state === null) continue;
            $status = (string)($state['status'] ?? 'generated');
            if (!in_array($status, self::CLIENT_STATUSES, true)) $status = 'failed';
            $error = $this->nullable((string)($state['error'] ?? ''), 255);

            if ($eventName === 'purchase') {
                try {
                    $stmt = Database::instance()->prepare(
                        "UPDATE tracking_events
                         SET status = :status, error = :error,
                             dispatched_at = IF(:status2 = 'dispatched', COALESCE(dispatched_at, NOW()), dispatched_at),
                             updated_at = NOW()
                         WHERE site_id = @site_id AND event_id = :event_id AND platform = :platform AND event_name = 'purchase'
                           AND status IN ('ready', 'failed')
                           AND EXISTS (
                               SELECT 1 FROM store_orders o
                               WHERE o.uuid = tracking_events.order_uuid AND o.site_id = @site_id AND o.payment_status = 'paid'
                           )"
                    );
                    $stmt->execute([
                        'status' => $status,
                        'status2' => $status,
                        'error' => $error,
                        'event_id' => $eventId,
                        'platform' => $platform,
                    ]);
                    if ($stmt->rowCount() > 0) $count++;
                } catch (\Throwable $e) {
                }
                continue;
            }

            $ok = $this->insert([
                'event_id' => $eventId,
                'event_name' => $eventName,
                'platform' => $platform,
                'destination_event' => (string)(self::DESTINATIONS[$platform][$eventName] ?? ''),
                'session_id' => (string)($payload['session_id'] ?? ''),
                'visitor_hash' => (string)($payload['visitor_hash'] ?? ''),
                'order_uuid' => $orderUuid,
                'order_number' => $orderNumber,
                'consent_snapshot' => $consent,
                'params' => $params,
                'value_cents' => $valueCents,
                'currency' => $currency,
                'status' => $status,
                'error' => $error,
                'occurred_at' => date('Y-m-d H:i:s'),
            ]);
            if ($ok) $count++;
        }
        return $count;
    }

    public function recent(array $filters = []): array
    {
        if (!$this->ensureTables()) return [];
        $where = ['site_id = @site_id'];
        $params = [];
        foreach (['platform', 'event_name', 'status', 'order_uuid'] as $key) {
            $value = trim((string)($filters[$key] ?? ''));
            if ($value === '') continue;
            $where[] = "$key = :$key";
            $params[$key] = $value;
        }
        $limit = max(1, min(200, (int)($filters['limit'] ?? 50)));
        try {
            $stmt = Database::instance()->prepare(
                'SELECT * FROM tracking_events WHERE ' . implode(' AND ', $where) . " ORDER BY id DESC LIMIT $limit"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$row) {
                $row['params'] = json_decode((string)($row['params'] ?? '{}'), true) ?: [];
            }
            unset($row);
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function stats(int $days = 1): array
    {
        if (!$this->ensureTables()) return ['ready' => false];
        $days = max(1, min(365, $days));
        $since = 'created_at >= (NOW() - INTERVAL ' . $days . ' DAY)';
        $db = Database::instance();
        $one = function (string $sql) use ($db): int {
            try { return (int)$db->query($sql)->fetchColumn(); } catch (\Throwable $e) { return 0; }
        };
        return [
            'ready' => true,
            'days' => $days,
            'total' => $one("SELECT COUNT(*) FROM tracking_events WHERE site_id = @site_id AND $since"),
            'dispatched' => $one("SELECT COUNT(*) FROM tracking_events WHERE site_id = @site_id AND status = 'dispatched' AND $since"),
            'failed' => $one("SELECT COUNT(*) FROM tracking_events WHERE site_id = @site_id AND status = 'failed' AND $since"),
            'pending_payment' => $one("SELECT COUNT(*) FROM tracking_events WHERE site_id = @site_id AND status = 'pending_payment' AND $since"),
            'ready' => $one("SELECT COUNT(*) FROM tracking_events WHERE site_id = @site_id AND status = 'ready' AND $since"),
            'purchases' => $one("SELECT COUNT(*) FROM tracking_events WHERE site_id = @site_id AND event_name = 'purchase' AND status = 'dispatched' AND $since"),
            'meta' => $one("SELECT COUNT(*) FROM tracking_events WHERE site_id = @site_id AND platform = 'meta' AND $since"),
            'ga4' => $one("SELECT COUNT(*) FROM tracking_events WHERE site_id = @site_id AND platform = 'ga4' AND $since"),
        ];
    }

    public function lastDispatched(string $platform): ?array
    {
        if (!$this->ensureTables()) return null;
        try {
            $stmt = Database::instance()->prepare(
                "SELECT event_name, destination_event, order_number, dispatched_at FROM tracking_events
                 WHERE site_id = @site_id AND platform = :platform AND status = 'dispatched'
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute(['platform' => $platform]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function lastError(string $platform): ?array
    {
        if (!$this->ensureTables()) return null;
        try {
            $stmt = Database::instance()->prepare(
                "SELECT event_name, destination_event, error, created_at FROM tracking_events
                 WHERE site_id = @site_id AND platform = :platform AND status = 'failed' AND error IS NOT NULL
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute(['platform' => $platform]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function insertTest(): array
    {
        if (!$this->ensureTables()) return ['ok' => false, 'message' => 'No se pudieron crear las tablas.'];
        $id = 'test_' . date('YmdHis') . '_' . bin2hex(random_bytes(3));
        $ok = $this->insert([
            'event_id' => $id,
            'event_name' => 'test',
            'platform' => 'internal',
            'destination_event' => 'TestEvent',
            'consent_snapshot' => TrackingConsentService::snapshot(),
            'params' => ['page_path' => '/admin', 'event_id' => $id, 'value' => 0, 'currency' => 'COP'],
            'value_cents' => 0,
            'currency' => 'COP',
            'status' => 'generated',
            'occurred_at' => date('Y-m-d H:i:s'),
        ]);
        return $ok
            ? ['ok' => true, 'message' => 'Evento de prueba registrado en el debugger.', 'event_id' => $id]
            : ['ok' => false, 'message' => 'No se pudo registrar el evento de prueba.'];
    }

    private function sanitizeParams(array $params, int $depth = 0): array
    {
        if ($depth > 4) return [];
        $allowed = [
            'event', 'event_id', 'page_path', 'page_location', 'content_ids', 'content_type', 'content_name',
            'contents', 'num_items', 'value', 'value_cents', 'currency', 'order_id', 'order_number',
            'transaction_id', 'items', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
            'fbclid', 'gclid', 'consent', 'session_id', 'id', 'item_id', 'sku', 'product_id', 'handle',
            'name', 'item_name', 'price', 'price_cents', 'item_price', 'quantity', 'qty', 'category', 'item_category',
            'brand', 'item_brand',
        ];
        $out = [];
        $count = 0;
        foreach ($params as $key => $value) {
            if ($count >= 50) break;
            $key = is_int($key) ? $key : mb_substr((string)$key, 0, 60);
            if (is_string($key) && !in_array($key, $allowed, true)) continue;
            if (is_array($value)) {
                $value = $this->sanitizeParams($value, $depth + 1);
            } elseif (is_string($value)) {
                $value = mb_substr($value, 0, 500);
            } elseif (!is_scalar($value) && $value !== null) {
                continue;
            }
            $out[$key] = $value;
            $count++;
        }
        $json = json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false && strlen($json) > 16000) return [];
        return $out;
    }

    private function nullable(string $value, int $max): ?string
    {
        $value = trim($value);
        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function dateOrNow(string $value): string
    {
        $ts = strtotime($value);
        return $ts ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
    }
}
