<?php
namespace App\Bricks\RecommendedProducts;

use App\Core\BrickSystem;
use App\Core\Config;
use App\Services\RecommendationService;

class RecommendedProductsBrick
{
    public static function meta(): array
    {
        $path = __DIR__ . '/brick.json';
        if (is_file($path)) {
            $meta = json_decode((string)file_get_contents($path), true);
            if (is_array($meta)) return $meta;
        }
        return [
            'name' => 'Productos recomendados',
            'slug' => 'recommended-products',
            'version' => '1.0.0',
            'description' => 'Sugerencias de venta cruzada en el carrito con descuento progresivo.',
            'author' => 'Wontia',
            'category' => 'ecommerce',
        ];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'enabled', 'label' => 'Activar recomendados', 'type' => 'toggle', 'default' => true],
            ['key' => 'title', 'label' => 'Título', 'type' => 'text', 'default' => 'Completa tu sonido'],
            ['key' => 'max', 'label' => 'Tarjetas visibles', 'type' => 'select', 'default' => 3],
            ['key' => 'discount_enabled', 'label' => 'Descuento de venta cruzada', 'type' => 'toggle', 'default' => true],
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
            'site_id' => (int)Config::get('SITE_ID', '1'),
            'name' => $meta['name'],
            'slug' => $meta['slug'],
            'version' => $meta['version'],
            'category' => $meta['category'],
            'description' => $meta['description'],
            'author' => $meta['author'],
            'brick_class' => self::class,
            'installed_path' => 'src/Bricks/RecommendedProducts',
            'config' => self::defaultConfig(),
        ]);
    }

    public function overview(): array
    {
        return [
            'settings' => (new RecommendationService())->publicConfig(),
        ];
    }
}
