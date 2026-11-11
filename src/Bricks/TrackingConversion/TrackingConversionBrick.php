<?php
namespace App\Bricks\TrackingConversion;

use App\Core\BrickSystem;
use App\Core\Config;
use App\Services\TrackingConversionService;
use App\Services\TrackingEventService;

class TrackingConversionBrick
{
    public static function meta(): array
    {
        $path = __DIR__ . '/brick.json';
        if (is_file($path)) {
            $meta = json_decode((string)file_get_contents($path), true);
            if (is_array($meta)) return $meta;
        }
        return [
            'name' => 'WWI Tracking & Conversion',
            'slug' => 'tracking-conversion',
            'version' => TrackingConversionService::VERSION,
            'description' => 'Tracking universal de conversión.',
            'author' => 'Wontia',
            'category' => 'marketing',
        ];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'enabled', 'label' => 'Activar Tracking & Conversion', 'type' => 'toggle', 'default' => false],
            ['key' => 'meta_enabled', 'label' => 'Activar Meta Pixel', 'type' => 'toggle', 'default' => false],
            ['key' => 'meta_pixel_id', 'label' => 'Meta Pixel ID', 'type' => 'text', 'default' => ''],
            ['key' => 'meta_environment', 'label' => 'Ambiente Meta', 'type' => 'select', 'default' => 'production', 'options' => ['production' => 'Producción', 'test' => 'Test']],
            ['key' => 'ga4_enabled', 'label' => 'Activar GA4', 'type' => 'toggle', 'default' => false],
            ['key' => 'ga4_measurement_id', 'label' => 'GA4 Measurement ID', 'type' => 'text', 'default' => ''],
            ['key' => 'utm_enabled', 'label' => 'Activar UTM', 'type' => 'toggle', 'default' => true],
            ['key' => 'utm_retention_days', 'label' => 'Retención UTM (días)', 'type' => 'number', 'default' => 90],
            ['key' => 'content_id_type', 'label' => 'Identificador de producto', 'type' => 'select', 'default' => 'sku', 'options' => ['sku' => 'SKU', 'product_id' => 'Product ID', 'handle' => 'Handle']],
            ['key' => 'currency', 'label' => 'Moneda', 'type' => 'text', 'default' => 'COP'],
            ['key' => 'event_page_view', 'label' => 'PageView', 'type' => 'toggle', 'default' => true],
            ['key' => 'event_view_content', 'label' => 'ViewContent', 'type' => 'toggle', 'default' => true],
            ['key' => 'event_add_to_cart', 'label' => 'AddToCart', 'type' => 'toggle', 'default' => true],
            ['key' => 'event_initiate_checkout', 'label' => 'InitiateCheckout', 'type' => 'toggle', 'default' => true],
            ['key' => 'event_purchase', 'label' => 'Purchase', 'type' => 'toggle', 'default' => true],
            ['key' => 'consent_required', 'label' => 'Exigir consentimiento', 'type' => 'toggle', 'default' => true],
            ['key' => 'debug', 'label' => 'Modo debug', 'type' => 'toggle', 'default' => false],
        ];
    }

    public static function defaultConfig(): array
    {
        return TrackingConversionService::defaults();
    }

    public static function config(): array
    {
        return TrackingConversionService::config();
    }

    public static function saveConfig(array $input): array
    {
        return TrackingConversionService::saveConfig($input);
    }

    public static function activate(): array
    {
        $meta = self::meta();
        $tables = TrackingConversionService::installTables();
        if (empty($tables['ok'])) return $tables;

        $existing = BrickSystem::findBySlug($meta['slug']);
        if ($existing) {
            return ['ok' => true, 'message' => 'El Brick ya estaba instalado', 'id' => $existing['id']];
        }
        return BrickSystem::install([
            'site_id' => (int)Config::get('SITE_ID', '1'),
            'name' => $meta['name'],
            'slug' => $meta['slug'],
            'version' => $meta['version'],
            'description' => $meta['description'],
            'author' => $meta['author'],
            'category' => $meta['category'],
            'brick_class' => self::class,
            'installed_path' => 'src/Bricks/TrackingConversion/',
            'config' => self::defaultConfig(),
        ]);
    }

    public static function deactivate(): array
    {
        $cfg = self::config();
        $cfg['enabled'] = false;
        self::saveConfig($cfg);
        return ['ok' => true, 'message' => 'Tracking desactivado. Las tablas y el historial se conservan.'];
    }

    public static function overview(): array
    {
        return TrackingConversionService::overview();
    }

    public static function adminPreview(): string
    {
        return '<div style="background:linear-gradient(135deg,#0b1220,#111827);border:1px solid #1f2937;border-radius:14px;padding:18px;color:#e5e7eb;font-family:Inter,sans-serif">'
            . '<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px"><span style="width:10px;height:10px;border-radius:50%;background:#22c55e"></span><b style="font-size:13px">WWI Tracking & Conversion</b><span style="margin-left:auto;font-size:9px;color:#94a3b8">v' . htmlspecialchars(TrackingConversionService::VERSION, ENT_QUOTES, 'UTF-8') . '</span></div>'
            . '<div style="font-size:11px;color:#94a3b8;line-height:1.6">Meta Pixel · GA4 · UTM first/last · Purchase verificado · Debugger · Health · Consentimiento analytics/marketing</div>'
            . '<div style="display:flex;gap:6px;margin-top:12px;flex-wrap:wrap"><span style="font-size:9px;border:1px solid #334155;border-radius:999px;padding:3px 7px">PageView</span><span style="font-size:9px;border:1px solid #334155;border-radius:999px;padding:3px 7px">ViewContent</span><span style="font-size:9px;border:1px solid #334155;border-radius:999px;padding:3px 7px">AddToCart</span><span style="font-size:9px;border:1px solid #334155;border-radius:999px;padding:3px 7px">Purchase</span></div>'
            . '</div>';
    }
}
