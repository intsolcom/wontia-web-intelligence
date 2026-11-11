<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * Tracking & Conversion Brick — servicio central.
 * Configuración en settings (`brick_config_tracking-conversion`), sin secretos.
 */
class TrackingConversionService
{
    public const SETTING_KEY = 'brick_config_tracking-conversion';
    public const VERSION = '1.0.0';

    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'meta_enabled' => false,
            'meta_pixel_id' => '',
            'meta_environment' => 'production',
            'meta_test_event_code' => '',
            'ga4_enabled' => false,
            'ga4_measurement_id' => '',
            'ga4_debug' => false,
            'utm_enabled' => true,
            'utm_retention_days' => 90,
            'content_id_type' => 'sku',
            'currency' => 'COP',
            'event_page_view' => true,
            'event_view_content' => true,
            'event_add_to_cart' => true,
            'event_initiate_checkout' => true,
            'event_purchase' => true,
            'consent_required' => true,
            'debug' => false,
            'debug_retention_days' => 30,
            'dedup_ttl_hours' => 48,
            'purchase_retry_enabled' => true,
            'test_mode' => false,
        ];
    }

    public static function config(): array
    {
        $saved = self::readSetting(self::SETTING_KEY);
        $decoded = $saved !== '' ? json_decode($saved, true) : null;
        $merged = array_merge(self::defaults(), is_array($decoded) ? $decoded : []);
        return self::normalizeConfig($merged);
    }

    public static function saveConfig(array $input): array
    {
        $merged = array_merge(self::config(), $input);
        $clean = self::normalizeConfig($merged);
        self::writeSetting(self::SETTING_KEY, (string)json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return ['ok' => true, 'config' => $clean, 'validation' => self::validateConfig($clean)];
    }

    public static function isEnabled(): bool
    {
        try {
            $cfg = self::config();
            return !empty($cfg['enabled']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function installTables(): array
    {
        $svc = new TrackingEventService();
        $ok = $svc->ensureTables();
        return ['ok' => $ok, 'message' => $ok ? 'Tablas listas' : 'No se pudieron crear las tablas'];
    }

    public static function publicConfig(): array
    {
        $cfg = self::config();
        $metaId = self::validPixelId((string)$cfg['meta_pixel_id']) ? (string)$cfg['meta_pixel_id'] : '';
        $ga4Id = self::validGa4Id((string)$cfg['ga4_measurement_id']) ? (string)$cfg['ga4_measurement_id'] : '';
        return [
            'enabled' => !empty($cfg['enabled']),
            'version' => self::VERSION,
            'meta' => [
                'enabled' => !empty($cfg['meta_enabled']) && $metaId !== '',
                'pixel_id' => $metaId,
                'environment' => (string)$cfg['meta_environment'],
                'test_event_code' => (string)$cfg['meta_test_event_code'],
            ],
            'ga4' => [
                'enabled' => !empty($cfg['ga4_enabled']) && $ga4Id !== '',
                'measurement_id' => $ga4Id,
                'debug' => !empty($cfg['ga4_debug']),
            ],
            'events' => [
                'page_view' => !empty($cfg['event_page_view']),
                'view_content' => !empty($cfg['event_view_content']),
                'add_to_cart' => !empty($cfg['event_add_to_cart']),
                'initiate_checkout' => !empty($cfg['event_initiate_checkout']),
                'purchase' => !empty($cfg['event_purchase']),
            ],
            'consent_required' => !empty($cfg['consent_required']),
            'content_id_type' => (string)$cfg['content_id_type'],
            'currency' => (string)$cfg['currency'],
            'debug' => !empty($cfg['debug']),
            'attribution' => [
                'cookie' => TrackingAttributionService::COOKIE,
                'retention_days' => (int)$cfg['utm_retention_days'],
            ],
            'endpoints' => [
                'event' => '/api/v1/public/tracking/event',
                'purchase' => '/api/v1/public/tracking/purchase/',
            ],
        ];
    }

    public static function footerHtml(): string
    {
        $cfg = self::config();
        if (empty($cfg['enabled'])) return '';
        $json = (string)json_encode(self::publicConfig(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $path = (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2)) . '/public/assets/js/tracking-conversion.js';
        $version = is_file($path) ? (string)filemtime($path) : self::VERSION;
        return '<script>window.WWI_TRACKING_CONFIG=' . $json . ';</script>'
            . '<script src="/assets/js/tracking-conversion.js?v=' . rawurlencode($version) . '" defer></script>';
    }

    public static function onOrderCreated(string $orderUuid, array $lines, int $totalCents, string $currency, string $paymentMethod = '', string $orderNumber = ''): void
    {
        try {
            $cfg = self::config();
            if (empty($cfg['enabled']) || empty($cfg['event_purchase'])) return;

            $consent = TrackingConsentService::current();
            if (!empty($cfg['consent_required']) && empty($consent['analytics']) && empty($consent['marketing'])) return;

            $attribution = TrackingAttributionService::fromCookie();
            if ($attribution['first'] || $attribution['last']) {
                (new TrackingAttributionService())->saveForOrder($orderUuid, $orderNumber, $attribution, $consent);
            }

            $items = [];
            $numItems = 0;
            foreach ($lines as $line) {
                if (!is_array($line)) continue;
                $qty = max(1, min(99, (int)($line['qty'] ?? 1)));
                $unitCents = (int)($line['price_cents'] ?? $line['original_unit_cents'] ?? $line['unit_cents'] ?? 0);
                if ($unitCents <= 0 && !empty($line['subtotal_cents'])) {
                    $unitCents = (int)round(((int)$line['subtotal_cents']) / $qty);
                }
                $identity = self::contentIdForLine($line);
                $items[] = [
                    'id' => (string)$identity['content_id'],
                    'sku' => (string)$identity['sku'],
                    'product_id' => (int)$identity['product_id'],
                    'handle' => (string)$identity['handle'],
                    'name' => (string)($line['name'] ?? $line['title'] ?? ''),
                    'price_cents' => max(0, $unitCents),
                    'qty' => $qty,
                ];
                $numItems += $qty;
            }
            if (!$items) return;

            $currency = strtoupper(trim($currency));
            if (!preg_match('/^[A-Z]{3}$/', $currency)) $currency = (string)$cfg['currency'];

            $params = [
                'order_id' => $orderUuid,
                'order_number' => $orderNumber,
                'items' => $items,
                'num_items' => $numItems,
                'value_cents' => max(0, $totalCents),
                'value' => round(max(0, $totalCents) / 100, 2),
                'currency' => $currency,
                'payment_method' => mb_substr($paymentMethod, 0, 30),
            ];

            $platforms = [];
            if (!empty($cfg['meta_enabled']) && (!empty($consent['marketing']) || empty($cfg['consent_required']))) $platforms[] = 'meta';
            if (!empty($cfg['ga4_enabled']) && (!empty($consent['analytics']) || empty($cfg['consent_required']))) $platforms[] = 'ga4';
            if (!$platforms) return;

            (new TrackingEventService())->createPurchaseDraft($orderUuid, $orderNumber, $params, $consent, $platforms);
        } catch (\Throwable $e) {
        }
    }

    public static function onOrderPaid(string $orderUuid): void
    {
        try {
            $cfg = self::config();
            if (empty($cfg['enabled']) || empty($cfg['event_purchase'])) return;
            $events = new TrackingEventService();
            $events->ensureTables();
            if ($events->markPurchaseReady($orderUuid) > 0) return;

            // Pedidos creados antes de activar el Brick: intentar reconstruir el borrador.
            $order = self::orderRow($orderUuid);
            if (!$order || (string)($order['payment_status'] ?? '') !== 'paid') return;
            $lines = [];
            foreach ((array)($order['items'] ?? []) as $item) {
                $lines[] = [
                    'product_id' => (int)($item['product_id'] ?? 0),
                    'name' => (string)($item['name'] ?? ''),
                    'price_cents' => (int)($item['price_cents'] ?? 0),
                    'qty' => (int)($item['qty'] ?? 1),
                ];
            }
            if (!$lines) return;
            self::onOrderCreated(
                $orderUuid,
                $lines,
                (int)($order['total_cents'] ?? 0),
                (string)($order['currency'] ?? $cfg['currency']),
                (string)($order['payment_method'] ?? ''),
                (string)($order['order_number'] ?? '')
            );
            $events->markPurchaseReady($orderUuid);
        } catch (\Throwable $e) {
        }
    }

    public static function onOrderCancelled(string $orderUuid, string $reason = ''): void
    {
        try {
            (new TrackingEventService())->markPurchaseCancelled($orderUuid, $reason);
        } catch (\Throwable $e) {
        }
    }

    public static function purchasePayload(string $orderUuid): ?array
    {
        try {
            $cfg = self::config();
            if (empty($cfg['enabled']) || empty($cfg['event_purchase'])) return null;
            $order = self::orderRow($orderUuid);
            if (!$order || (string)($order['payment_status'] ?? '') !== 'paid') return null;

            $consent = TrackingConsentService::current();
            if (!empty($cfg['consent_required']) && empty($consent['analytics']) && empty($consent['marketing'])) return null;

            $rows = (new TrackingEventService())->purchaseRows($orderUuid);
            if (!$rows) return null;

            $platforms = [];
            $common = null;
            foreach ($rows as $row) {
                $platform = (string)($row['platform'] ?? '');
                if ($platform === 'meta') {
                    if (empty($cfg['meta_enabled'])) continue;
                    if (!empty($cfg['consent_required']) && empty($consent['marketing'])) continue;
                } elseif ($platform === 'ga4') {
                    if (empty($cfg['ga4_enabled'])) continue;
                    if (!empty($cfg['consent_required']) && empty($consent['analytics'])) continue;
                } else {
                    continue;
                }
                if ($common === null) $common = is_array($row['params'] ?? null) ? $row['params'] : [];
                $platforms[] = [
                    'platform' => $platform,
                    'destination_event' => (string)($row['destination_event'] ?? ''),
                    'status' => (string)($row['status'] ?? 'ready'),
                ];
            }
            if (!$platforms || !$common) return null;

            $valueCents = (int)($common['value_cents'] ?? ($order['total_cents'] ?? 0));
            $currency = strtoupper((string)($common['currency'] ?? ($order['currency'] ?? $cfg['currency'])));
            if (!preg_match('/^[A-Z]{3}$/', $currency)) $currency = (string)$cfg['currency'];

            return [
                'event_id' => 'purchase_' . $orderUuid,
                'order_uuid' => $orderUuid,
                'order_number' => (string)($order['order_number'] ?? ''),
                'value_cents' => max(0, $valueCents),
                'value' => round(max(0, $valueCents) / 100, 2),
                'currency' => $currency,
                'items' => is_array($common['items'] ?? null) ? $common['items'] : [],
                'num_items' => (int)($common['num_items'] ?? 0),
                'platforms' => $platforms,
                'consent' => $consent,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function recordClientEvent(array $payload): array
    {
        $count = (new TrackingEventService())->recordClient($payload);
        return ['ok' => true, 'recorded' => $count];
    }

    public static function overview(): array
    {
        $cfg = self::config();
        $events = new TrackingEventService();
        $events->ensureTables();
        $stats7 = $events->stats(7);
        $stats1 = $events->stats(1);
        $health = [
            'meta' => self::healthFor('meta', $cfg, $events),
            'ga4' => self::healthFor('ga4', $cfg, $events),
            'utm' => self::utmHealth($cfg),
        ];
        return [
            'ok' => true,
            'data' => [
                'version' => self::VERSION,
                'enabled' => !empty($cfg['enabled']),
                'config' => $cfg,
                'validation' => self::validateConfig($cfg),
                'health' => $health,
                'stats_7d' => $stats7,
                'stats_24h' => $stats1,
                'recent' => $events->recent(['limit' => 40]),
                'attribution' => (new TrackingAttributionService())->recent(15),
                'diagnostics' => self::diagnostics(),
                'legacy_ga' => self::legacyGaId(),
            ],
        ];
    }

    public static function diagnostics(): array
    {
        $cfg = self::config();
        $checks = [];
        $add = function (string $key, string $label, bool $ok, string $detail, string $severity = 'error', string $action = '') use (&$checks): void {
            $checks[] = ['key' => $key, 'label' => $label, 'ok' => $ok, 'detail' => $detail, 'severity' => $severity, 'action' => $action];
        };

        $tables = (new TrackingEventService())->ensureTables();
        $add('tables', 'Tablas del Brick', $tables, $tables ? 'tracking_events y tracking_order_attribution disponibles.' : 'No se pudieron crear las tablas.', 'error', 'Ejecutar install/tracking_conversion.sql');
        $add('master', 'Brick activo', !empty($cfg['enabled']), !empty($cfg['enabled']) ? 'El Brick está activo.' : 'El Brick está desactivado; no se inyecta tracking.', 'warning', 'Activar en Configuración');
        $add('meta_config', 'Meta Pixel', !empty($cfg['meta_enabled']) ? self::validPixelId((string)$cfg['meta_pixel_id']) : true, !empty($cfg['meta_enabled']) ? (self::validPixelId((string)$cfg['meta_pixel_id']) ? 'Pixel ID válido.' : 'Pixel ID inválido o vacío.') : 'Meta Pixel desactivado.', 'error', 'Configurar un Pixel ID numérico');
        $add('ga4_config', 'GA4', !empty($cfg['ga4_enabled']) ? self::validGa4Id((string)$cfg['ga4_measurement_id']) : true, !empty($cfg['ga4_enabled']) ? (self::validGa4Id((string)$cfg['ga4_measurement_id']) ? 'Measurement ID válido.' : 'Measurement ID inválido o vacío.') : 'GA4 desactivado.', 'error', 'Configurar un ID G-XXXXXXXXXX');
        $add('consent', 'Consentimiento marketing', !empty($cfg['consent_required']), !empty($cfg['consent_required']) ? 'El tracking respeta las categorías analytics y marketing.' : 'El tracking no exige consentimiento; revisar privacidad.', 'warning', 'Mantener consent_required activo');
        $add('legacy_ga', 'GA4 legado', self::legacyGaId() === '' || empty($cfg['enabled']), self::legacyGaId() === '' ? 'No hay GA4 legado configurado.' : ($cfg['enabled'] ? 'GA4 legado configurado; el Brick toma el control para evitar duplicados.' : 'GA4 legado activo.'), 'info', 'Migrar el ID al Brick');

        $catalog = self::catalogIndex();
        $sample = array_slice(array_values($catalog), 0, 20);
        $missingSku = 0;
        foreach ($sample as $product) if (empty($product['sku'])) $missingSku++;
        $add('content_ids', 'Identificadores de producto', $missingSku === 0, $sample ? ($missingSku === 0 ? 'La muestra del catálogo tiene SKU.' : "$missingSku de 20 productos de la muestra no tienen SKU.") : 'No se pudo leer el catálogo del tema.', $missingSku > 0 ? 'warning' : 'error', 'Revisar SKU del catálogo o usar product_id/handle');

        $stats = (new TrackingEventService())->stats(1);
        $add('events_24h', 'Eventos despachados 24 h', (int)($stats['dispatched'] ?? 0) > 0, (int)($stats['dispatched'] ?? 0) > 0 ? ('Eventos despachados: ' . (int)$stats['dispatched']) : 'No hay eventos despachados en 24 h.', 'warning', 'Abrir la tienda con consentimiento y verificar el debugger');
        $add('purchase_flow', 'Flujo de compra', (int)($stats['purchases'] ?? 0) >= 0, 'Compras despachadas 24 h: ' . (int)($stats['purchases'] ?? 0), 'info', 'Probar un pedido pagado en staging');

        $errors = count(array_filter($checks, fn($c) => !$c['ok'] && $c['severity'] === 'error'));
        $warnings = count(array_filter($checks, fn($c) => !$c['ok'] && $c['severity'] === 'warning'));
        return ['score' => max(0, 100 - ($errors * 25) - ($warnings * 8)), 'errors' => $errors, 'warnings' => $warnings, 'checks' => $checks];
    }

    public static function validateConfig(?array $cfg = null): array
    {
        $cfg = $cfg ?? self::config();
        $errors = [];
        $warnings = [];
        if (empty($cfg['enabled'])) $warnings[] = 'El Brick está desactivado.';
        if (!empty($cfg['enabled']) && empty($cfg['meta_enabled']) && empty($cfg['ga4_enabled'])) $warnings[] = 'No hay plataforma de destino activa.';
        if (!empty($cfg['meta_enabled']) && !self::validPixelId((string)$cfg['meta_pixel_id'])) $errors[] = 'Meta Pixel ID inválido o vacío.';
        if (!empty($cfg['ga4_enabled']) && !self::validGa4Id((string)$cfg['ga4_measurement_id'])) $errors[] = 'GA4 Measurement ID inválido o vacío.';
        if (empty($cfg['consent_required'])) $warnings[] = 'El consentimiento no es obligatorio; revisar privacidad.';
        if (!preg_match('/^[A-Z]{3}$/', (string)$cfg['currency'])) $errors[] = 'La moneda debe ser un código ISO de 3 letras.';
        if (!in_array((string)$cfg['content_id_type'], ['sku', 'product_id', 'handle'], true)) $errors[] = 'Tipo de content_id inválido.';
        if ((string)$cfg['meta_environment'] === 'test' && trim((string)$cfg['meta_test_event_code']) === '') $warnings[] = 'Meta en modo test sin test_event_code.';
        if (!empty($cfg['ga4_enabled']) && (string)$cfg['meta_environment'] === 'test') $warnings[] = 'El modo test de Meta no afecta a GA4.';
        return ['ok' => empty($errors), 'errors' => $errors, 'warnings' => $warnings];
    }

    private static function healthFor(string $platform, array $cfg, TrackingEventService $events): array
    {
        $enabled = !empty($cfg[$platform . '_enabled']);
        $label = $platform === 'meta' ? 'Meta Pixel' : 'GA4';
        if (!$enabled) return ['status' => 'disabled', 'label' => $label, 'detail' => 'Desactivado'];
        $valid = $platform === 'meta'
            ? self::validPixelId((string)$cfg['meta_pixel_id'])
            : self::validGa4Id((string)$cfg['ga4_measurement_id']);
        if (!$valid) return ['status' => 'error', 'label' => $label, 'detail' => 'Configuración inválida'];
        $last = $events->lastDispatched($platform);
        if ($last) return ['status' => 'connected', 'label' => $label, 'detail' => 'Último evento: ' . (string)($last['destination_event'] ?? ''), 'last' => $last];
        return ['status' => 'configured_unverified', 'label' => $label, 'detail' => 'Configurado, sin eventos recientes'];
    }

    private static function utmHealth(array $cfg): array
    {
        if (empty($cfg['utm_enabled'])) return ['status' => 'disabled', 'label' => 'UTM', 'detail' => 'Desactivado'];
        return ['status' => 'connected', 'label' => 'UTM', 'detail' => 'Retención ' . (int)$cfg['utm_retention_days'] . ' días'];
    }

    private static function legacyGaId(): string
    {
        $id = trim((string)Config::get('ga_measurement_id', ''));
        if ($id === '') $id = trim(self::readSetting('ga_measurement_id'));
        return $id;
    }

    private static function contentIdForLine(array $line): array
    {
        $sku = trim((string)($line['sku'] ?? ''));
        $productId = (int)($line['product_id'] ?? 0);
        $handle = trim((string)($line['handle'] ?? $line['ref'] ?? ''));
        $catalogId = 0;

        if ($productId > 0) {
            try {
                $stmt = Database::instance()->prepare('SELECT sku, name FROM store_products WHERE id = ? AND site_id = @site_id LIMIT 1');
                $stmt->execute([$productId]);
                $product = $stmt->fetch();
                if ($product) {
                    if ($sku === '') $sku = trim((string)($product['sku'] ?? ''));
                    if (empty($line['name']) && empty($line['title'])) $line['name'] = (string)($product['name'] ?? '');
                }
            } catch (\Throwable $e) {
            }
        }

        if ($handle !== '') {
            $catalog = self::catalogIndex();
            $item = $catalog[$handle] ?? null;
            if ($item) {
                if ($sku === '') $sku = trim((string)($item['sku'] ?? ''));
                $catalogId = (int)($item['id'] ?? 0);
                if ($productId <= 0) $productId = $catalogId;
            }
        }

        $type = (string)self::config()['content_id_type'];
        $contentId = '';
        if ($type === 'product_id') $contentId = $productId > 0 ? (string)$productId : $handle;
        elseif ($type === 'handle') $contentId = $handle !== '' ? $handle : ($productId > 0 ? (string)$productId : $sku);
        else $contentId = $sku !== '' ? $sku : ($productId > 0 ? (string)$productId : $handle);

        return ['content_id' => $contentId, 'sku' => $sku, 'product_id' => $productId, 'handle' => $handle];
    }

    private static function catalogIndex(): array
    {
        static $index = null;
        if (is_array($index)) return $index;
        $index = [];
        $root = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
        $paths = [];
        $theme = 'sonika';
        try {
            $theme = (string)(Database::instance()->query('SELECT theme FROM sites WHERE id = @site_id')->fetchColumn() ?: 'sonika');
        } catch (\Throwable $e) {
        }
        $theme = preg_replace('/[^a-z0-9_-]/i', '', $theme) ?: 'sonika';
        $paths[] = $root . '/public/assets/uploads/_catalog/catalog.json';
        $paths[] = $root . '/templates/themes/' . $theme . '/catalog.json';

        foreach ($paths as $path) {
            if (!is_file($path)) continue;
            $data = json_decode((string)@file_get_contents($path), true);
            if (!is_array($data)) continue;
            foreach ((array)($data['products'] ?? []) as $product) {
                $handle = trim((string)($product['handle'] ?? ''));
                if ($handle === '') continue;
                $variant = is_array($product['variants'][0] ?? null) ? $product['variants'][0] : [];
                $index[$handle] = [
                    'id' => (int)($product['id'] ?? 0),
                    'sku' => trim((string)($variant['sku'] ?? '')),
                    'title' => (string)($product['title'] ?? ''),
                ];
            }
            if ($index) break;
        }
        return $index;
    }

    private static function orderRow(string $orderUuid): ?array
    {
        try {
            $stmt = Database::instance()->prepare('SELECT * FROM store_orders WHERE uuid = ? AND site_id = @site_id LIMIT 1');
            $stmt->execute([$orderUuid]);
            $order = $stmt->fetch();
            if (!$order) return null;
            $items = Database::instance()->prepare('SELECT * FROM store_order_items WHERE order_id = ? AND site_id = @site_id ORDER BY id ASC');
            $items->execute([(int)$order['id']]);
            $order['items'] = $items->fetchAll() ?: [];
            return $order;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function normalizeConfig(array $cfg): array
    {
        $out = [];
        foreach (self::defaults() as $key => $default) {
            $out[$key] = array_key_exists($key, $cfg) ? $cfg[$key] : $default;
        }
        foreach (['enabled', 'meta_enabled', 'ga4_enabled', 'ga4_debug', 'utm_enabled', 'event_page_view', 'event_view_content', 'event_add_to_cart', 'event_initiate_checkout', 'event_purchase', 'consent_required', 'debug', 'purchase_retry_enabled', 'test_mode'] as $key) {
            $out[$key] = !empty($out[$key]);
        }
        $out['meta_pixel_id'] = preg_replace('/\s+/', '', trim((string)$out['meta_pixel_id']));
        $out['meta_environment'] = in_array((string)$out['meta_environment'], ['production', 'test'], true) ? (string)$out['meta_environment'] : 'production';
        $out['meta_test_event_code'] = preg_replace('/[^A-Za-z0-9_-]/', '', trim((string)$out['meta_test_event_code']));
        $out['ga4_measurement_id'] = strtoupper(trim((string)$out['ga4_measurement_id']));
        $out['utm_retention_days'] = max(1, min(365, (int)$out['utm_retention_days']));
        $out['debug_retention_days'] = max(1, min(90, (int)$out['debug_retention_days']));
        $out['dedup_ttl_hours'] = max(1, min(720, (int)$out['dedup_ttl_hours']));
        $out['content_id_type'] = in_array((string)$out['content_id_type'], ['sku', 'product_id', 'handle'], true) ? (string)$out['content_id_type'] : 'sku';
        $out['currency'] = strtoupper(trim((string)$out['currency']));
        if (!preg_match('/^[A-Z]{3}$/', (string)$out['currency'])) $out['currency'] = 'COP';
        return $out;
    }

    private static function validPixelId(string $id): bool
    {
        return (bool)preg_match('/^\d{8,20}$/', $id);
    }

    private static function validGa4Id(string $id): bool
    {
        return (bool)preg_match('/^G-[A-Z0-9]{6,14}$/i', $id);
    }

    private static function readSetting(string $key): string
    {
        try {
            $stmt = Database::instance()->prepare('SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = ? LIMIT 1');
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
            return $value === false || $value === null ? '' : (string)$value;
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function writeSetting(string $key, string $value): void
    {
        Database::instance()->prepare(
            "INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
        )->execute([$key, $value]);
    }
}
