<?php
namespace App\Bricks\MercadoPago;

use App\Core\BrickSystem;
use App\Services\MercadoPagoService;

class MercadoPagoBrick
{
    public static function meta(): array
    {
        $path = __DIR__ . '/brick.json';
        if (is_file($path)) {
            $meta = json_decode((string)file_get_contents($path), true);
            if (is_array($meta)) return $meta;
        }
        return [
            'name' => 'Mercado Pago',
            'slug' => 'mercadopago',
            'version' => '1.0.0',
            'description' => 'Pasarela Mercado Pago para el checkout.',
            'author' => 'Wontia',
            'category' => 'ecommerce',
        ];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'enabled', 'label' => 'Activar Mercado Pago', 'type' => 'toggle', 'default' => false],
            ['key' => 'environment', 'label' => 'Ambiente', 'type' => 'select', 'default' => 'sandbox', 'options' => ['sandbox' => 'Pruebas', 'production' => 'Producción']],
            ['key' => 'mode', 'label' => 'Modalidad', 'type' => 'select', 'default' => 'checkout_pro', 'options' => ['checkout_pro' => 'Checkout Pro', 'checkout_api' => 'Checkout API (brick)']],
            ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'default' => ''],
            ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'default' => ''],
            ['key' => 'public_key', 'label' => 'Public Key', 'type' => 'text', 'default' => ''],
            ['key' => 'access_token', 'label' => 'Access Token (opcional)', 'type' => 'password', 'default' => ''],
            ['key' => 'webhook_secret', 'label' => 'Clave secreta de webhooks', 'type' => 'password', 'default' => ''],
            ['key' => 'max_installments', 'label' => 'Cuotas máximas', 'type' => 'number', 'default' => 12],
            ['key' => 'binary_mode', 'label' => 'Rechazo binario', 'type' => 'toggle', 'default' => true],
        ];
    }

    public static function defaultConfig(): array
    {
        $out = [];
        foreach (self::configSchema() as $field) $out[$field['key']] = $field['default'] ?? '';
        return $out;
    }

    public static function activate(): void
    {
        $meta = self::meta();
        (new MercadoPagoService())->ensureTables();
        if (BrickSystem::findBySlug($meta['slug'])) return;
        BrickSystem::install([
            'site_id' => (int)(\App\Core\Config::get('SITE_ID', '1')),
            'name' => $meta['name'],
            'slug' => $meta['slug'],
            'version' => $meta['version'],
            'category' => $meta['category'],
            'description' => $meta['description'],
            'author' => $meta['author'],
            'brick_class' => self::class,
            'installed_path' => 'src/Bricks/MercadoPago',
            'config' => self::defaultConfig(),
        ]);
    }

    public static function installTables(): array
    {
        return (new MercadoPagoService())->ensureTables();
    }

    public function overview(): array
    {
        return (new MercadoPagoService())->overview();
    }
}
