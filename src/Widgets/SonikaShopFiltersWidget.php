<?php
namespace App\Widgets;

/**
 * SONIKA — Filtros de tienda.
 * Brick configurable para la columna izquierda de /tienda y categorías.
 */
class SonikaShopFiltersWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'shop-filters', 'name' => 'SONIKA — Filtros de tienda', 'icon' => 'search', 'category' => 'ecommerce', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'enabled', 'label' => 'Mostrar filtros', 'type' => 'toggle', 'default' => true],
            ['key' => 'auto_apply', 'label' => 'Aplicar filtros automáticamente (sin recargar)', 'type' => 'toggle', 'default' => true],
            ['key' => 'sticky', 'label' => 'Fijar columna al hacer scroll (PC)', 'type' => 'toggle', 'default' => true],
            ['key' => '_h_text', 'label' => 'Textos y color', 'type' => 'heading'],
            ['key' => 'title', 'label' => 'Título del panel', 'type' => 'text', 'default' => 'Filtros'],
            ['key' => 'live_text', 'label' => 'Texto de aplicación instantánea', 'type' => 'text', 'default' => 'Se aplica al instante'],
            ['key' => 'clear_label', 'label' => 'Texto del botón Limpiar', 'type' => 'text', 'default' => 'Limpiar'],
            ['key' => 'accent', 'label' => 'Color de acento', 'type' => 'color', 'default' => '#F20D16'],
            ['key' => '_h_sections', 'label' => 'Secciones visibles', 'type' => 'heading'],
            ['key' => 'show_price', 'label' => 'Precio', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_categories', 'label' => 'Categorías (solo tienda)', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_subcategories', 'label' => 'Subcategorías (solo Cuerdas)', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_brands', 'label' => 'Marcas', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_types', 'label' => 'Tipos / características', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_promotion', 'label' => 'Promoción y disponibilidad', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_discount', 'label' => 'Descuento mínimo', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_counts', 'label' => 'Mostrar conteos por opción', 'type' => 'toggle', 'default' => true],
            ['key' => 'brand_aliases', 'label' => 'Alias de marcas (una por línea: original = visible)', 'type' => 'textarea', 'default' => '', 'help' => 'Opcional. Ejemplo: Fender Mexico = Fender. Las variantes de Miche se unifican automáticamente como Miche.'],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        if (empty($c['enabled'])) return '';
        if (!function_exists('sonika_shop_filter_sidebar') || !function_exists('sonika_products')) return '';

        $uri = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $path = trim($uri, '/');
        $segments = $path === '' ? [] : explode('/', $path);
        $first = (string)($segments[0] ?? '');
        $cat = function_exists('sonika_category') ? sonika_category($path) : null;
        if ($cat) {
            $products = sonika_category_products((string)$cat['key']);
            $action = (string)$cat['url'];
            $isStore = false;
        } elseif ($first === 'tienda') {
            $products = sonika_catalog_products();
            $action = '/tienda';
            $isStore = true;
        } else {
            $products = sonika_products();
            $action = '/' . $path;
            $isStore = false;
        }

        $opts = [
            'show_price' => !empty($c['show_price']),
            'show_categories' => $isStore && !empty($c['show_categories']),
            'show_subs' => !$isStore && $cat && (string)$cat['key'] === 'cuerdas/guitarras' && !empty($c['show_subcategories']),
            'show_brands' => !empty($c['show_brands']),
            'show_types' => !empty($c['show_types']),
            'show_promotion' => !empty($c['show_promotion']),
            'show_discount' => !empty($c['show_discount']),
            'show_counts' => !empty($c['show_counts']),
            'auto_apply' => !empty($c['auto_apply']),
            'sticky' => !empty($c['sticky']),
            'accent' => (string)($c['accent'] ?? '#F20D16'),
            'title' => (string)($c['title'] ?? 'Filtros'),
            'live_text' => (string)($c['live_text'] ?? 'Se aplica al instante'),
            'clear_label' => (string)($c['clear_label'] ?? 'Limpiar'),
        ];

        $css = function_exists('sonika_shop_css') ? sonika_shop_css() : '';
        return $css . sonika_shop_filter_sidebar($products, $_GET, $action, $opts);
    }
}
