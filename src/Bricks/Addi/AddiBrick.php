<?php
namespace App\Bricks\Addi;

use App\Core\BrickSystem;
use App\Services\AddiService;

class AddiBrick
{
    public static function meta(): array
    {
        $path = __DIR__ . '/brick.json';
        if (is_file($path)) {
            $meta = json_decode((string)file_get_contents($path), true);
            if (is_array($meta)) return $meta;
        }
        return [
            'name' => 'Addi',
            'slug' => 'addi',
            'version' => '1.0.0',
            'description' => 'Pago a cuotas con Addi.',
            'author' => 'Wontia',
            'category' => 'ecommerce',
        ];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'enabled', 'label' => 'Activar Addi', 'type' => 'toggle', 'default' => false],
            ['key' => 'environment', 'label' => 'Ambiente', 'type' => 'select', 'default' => 'staging', 'options' => ['staging' => 'Staging', 'production' => 'Producción']],
            ['key' => 'ally_slug', 'label' => 'Ally slug', 'type' => 'text', 'default' => ''],
            ['key' => 'widget_enabled', 'label' => 'Widget en productos', 'type' => 'toggle', 'default' => true],
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
        if (BrickSystem::findBySlug($meta['slug'])) return;
        BrickSystem::install([
            'name' => $meta['name'],
            'slug' => $meta['slug'],
            'version' => $meta['version'],
            'category' => $meta['category'],
            'description' => $meta['description'],
            'author' => $meta['author'],
            'brick_class' => self::class,
            'installed_path' => 'src/Bricks/Addi',
            'config' => self::defaultConfig(),
        ]);
    }

    public function overview(): array
    {
        return (new AddiService())->overview();
    }
}
