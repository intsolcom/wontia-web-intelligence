<?php
namespace App\Widgets;

class StoreCatalogWidget extends StoreWidgetBase
{
    public static function meta(): array
    {
        return ['id' => 'store-catalog', 'name' => 'Store: Catalogo', 'icon' => 'grid', 'category' => 'commerce', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titulo', 'type' => 'text', 'inline' => true, 'default' => 'Nuestros productos'],
            ['key' => 'subtitle', 'label' => 'Subtitulo', 'type' => 'textarea', 'inline' => true, 'default' => 'Explora el catalogo y pide en linea.'],
            ['key' => 'per_page', 'label' => 'Productos por pagina', 'type' => 'text', 'default' => '12'],
            ['key' => 'show_filters', 'label' => 'Mostrar filtros (1/0)', 'type' => 'text', 'default' => '1'],
            ['key' => 'show_search', 'label' => 'Mostrar buscador (1/0)', 'type' => 'text', 'default' => '1'],
            ['key' => 'featured_only', 'label' => 'Solo destacados (1/0)', 'type' => 'text', 'default' => '0'],
            ['key' => 'add_label', 'label' => 'Texto boton agregar', 'type' => 'text', 'default' => 'Agregar'],
            ['key' => 'detail_label', 'label' => 'Texto boton ver', 'type' => 'text', 'default' => 'Ver'],
            ['key' => 'empty_text', 'label' => 'Texto sin productos', 'type' => 'text', 'default' => 'No hay productos disponibles por ahora.'],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $svc = $this->store();
        if (!$svc->ready()) {
            return '<section class="st-section"><div class="st-empty">La tienda aun no esta configurada.</div></section>';
        }
        $currency = $svc->settings()['store_currency'] ?: 'COP';
        $perPage = max(4, min(48, (int)$c['per_page']));
        $page = max(1, (int)($_GET['st_page'] ?? 1));
        $catSlug = trim((string)($_GET['st_cat'] ?? ''));
        $q = trim((string)($_GET['st_q'] ?? ''));

        $filters = ['status' => 'active', 'limit' => $perPage, 'offset' => ($page - 1) * $perPage, 'search' => $q];
        if (!empty($c['featured_only']) && $c['featured_only'] !== '0') $filters['featured'] = 1;
        if ($catSlug !== '') {
            foreach ($svc->categories(true) as $cat) {
                if ($cat['slug'] === $catSlug) { $filters['category_id'] = (int)$cat['id']; break; }
            }
        }
        $products = $svc->products($filters);
        $total = $svc->productsCount($filters);
        $pages = max(1, (int)ceil($total / $perPage));
        $base = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';

        $html = $this->assets();
        $html .= '<section class="st-section">';
        $html .= '<div class="st-head">';
        if ($c['title'] !== '') $html .= '<h2 data-editable="title">' . $this->esc($c['title']) . '</h2>';
        if ($c['subtitle'] !== '') $html .= '<p data-editable="subtitle">' . $this->esc($c['subtitle']) . '</p>';
        $html .= '</div>';

        if (!empty($c['show_search']) && $c['show_search'] !== '0') {
            $html .= '<form class="st-filters" method="get" action="' . $this->esc($base) . '">';
            if ($catSlug !== '') $html .= '<input type="hidden" name="st_cat" value="' . $this->esc($catSlug) . '"/>';
            $html .= '<div class="st-search"><input type="text" name="st_q" value="' . $this->esc($q) . '" placeholder="Buscar productos..."/><button class="st-btn st-btn-outline" type="submit">Buscar</button></div>';
            $html .= '</form>';
        }

        if (!empty($c['show_filters']) && $c['show_filters'] !== '0') {
            $cats = $svc->categories(true);
            if ($cats) {
                $html .= '<div class="st-filters">';
                $qs = $q !== '' ? '&st_q=' . urlencode($q) : '';
                $html .= '<a class="st-chip' . ($catSlug === '' ? ' on' : '') . '" href="' . $this->esc($base) . '?st_cat=' . $qs . '">Todos</a>';
                foreach ($cats as $cat) {
                    $html .= '<a class="st-chip' . ($catSlug === $cat['slug'] ? ' on' : '') . '" href="' . $this->esc($base) . '?st_cat=' . urlencode((string)$cat['slug']) . $qs . '">' . $this->esc((string)$cat['name']) . '</a>';
                }
                $html .= '</div>';
            }
        }

        if (!$products) {
            $html .= '<div class="st-empty" data-editable="empty_text">' . $this->esc($c['empty_text']) . '</div>';
        } else {
            $html .= '<div class="st-grid">';
            foreach ($products as $p) {
                $out = (int)$p['track_stock'] === 1 && (int)$p['stock'] <= 0;
                if ((int)$p['track_stock'] === 1 && (int)$p['stock'] > 0 && (int)$p['stock'] <= 3) {
                    $stockNote = 'Ultimas ' . (int)$p['stock'] . ' unidades';
                } else {
                    $stockNote = '';
                }
                $html .= '<article class="st-card">';
                $html .= '<a href="' . $this->esc($base) . '?p=' . urlencode((string)$p['slug']) . '" style="text-decoration:none;color:inherit">' . $this->thumb($p) . '</a>';
                $html .= '<div class="st-body">';
                $html .= '<h3 class="st-name"><a href="' . $this->esc($base) . '?p=' . urlencode((string)$p['slug']) . '">' . $this->esc((string)$p['name']) . '</a></h3>';
                if ($p['short_description']) $html .= '<p class="st-desc">' . $this->esc((string)$p['short_description']) . '</p>';
                $html .= '<div class="st-price">' . $this->esc($this->money((int)$p['price_cents'], $currency));
                if ((int)$p['compare_price_cents'] > (int)$p['price_cents']) $html .= '<span class="st-cmp">' . $this->esc($this->money((int)$p['compare_price_cents'], $currency)) . '</span>';
                $html .= '</div>';
                if ($out) $html .= '<div class="st-stock out">Agotado</div>';
                elseif ($stockNote !== '') $html .= '<div class="st-stock">' . $this->esc($stockNote) . '</div>';
                $html .= '<div class="st-actions">';
                if ($out) {
                    $html .= '<button class="st-btn st-btn-outline" disabled style="flex:1">Agotado</button>';
                } else {
                    $html .= '<button class="st-btn st-btn-primary" onclick="wwiAddToCart(this,' . $this->cartItemJson($p) . ')">' . $this->esc($c['add_label']) . '</button>';
                }
                $html .= '<a class="st-btn st-btn-outline" href="' . $this->esc($base) . '?p=' . urlencode((string)$p['slug']) . '">' . $this->esc($c['detail_label']) . '</a>';
                $html .= '</div></div></article>';
            }
            $html .= '</div>';
        }

        if ($pages > 1) {
            $html .= '<div class="st-pager">';
            $qs = 'st_cat=' . urlencode($catSlug) . '&st_q=' . urlencode($q);
            if ($page > 1) $html .= '<a class="st-btn st-btn-outline" href="' . $this->esc($base) . '?' . $qs . '&st_page=' . ($page - 1) . '">&larr; Anterior</a>';
            $html .= '<span class="st-chip on">' . $page . ' / ' . $pages . '</span>';
            if ($page < $pages) $html .= '<a class="st-btn st-btn-outline" href="' . $this->esc($base) . '?' . $qs . '&st_page=' . ($page + 1) . '">Siguiente &rarr;</a>';
            $html .= '</div>';
        }

        $html .= '</section>';
        return $html;
    }
}
