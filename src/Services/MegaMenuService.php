<?php
namespace App\Services;

/**
 * MegaMenuService — construye el árbol de navegación (categorías hasta 5 niveles)
 * a partir del catálogo real del tema activo, y lo cachea en disco para que el
 * render sea instantáneo. NO crea datos paralelos: usa las facetas existentes
 * (cat/tipo/marca) y los tags estructurados para los niveles 4-5.
 *
 * Aislado: no depende de funciones del tema (recibe las categorías/productos),
 * por lo que es testeable y reutilizable por cualquier tema.
 */
class MegaMenuService
{
    public const CACHE_TTL = 600;

    /** Catálogo del tema activo (fallback sin funciones del tema). */
    public function catalog(): array
    {
        $theme = 'default';
        try {
            $theme = (string)(\App\Core\Database::instance()->query("SELECT theme FROM sites WHERE id = @site_id")->fetchColumn() ?: 'default');
        } catch (\Throwable $e) {
        }
        $theme = preg_replace('/[^a-z0-9_-]/i', '', $theme) ?: 'default';
        $file = dirname(__DIR__, 2) . '/templates/themes/' . $theme . '/catalog.json';
        if (!is_file($file)) return [];
        $d = json_decode((string)@file_get_contents($file), true);
        return is_array($d) ? ($d['products'] ?? []) : [];
    }

    /**
     * @param array $categories Cada una: ['slug','name','icon','tags','kw'] (opcional 'url')
     * @param array|null $products
     */
    public function tree(array $categories, ?array $products = null, int $newDays = 90): array
    {
        $products = $products ?? $this->catalog();
        if (!$categories) return $this->tagTree($products, $newDays);

        $sig = md5(json_encode(array_map(fn($c) => (string)($c['slug'] ?? ''), $categories)) . '|' . count($products) . '|' . $newDays);
        $cache = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'wwi_mega_' . $sig . '.json';
        if (is_file($cache) && (time() - filemtime($cache)) < self::CACHE_TTL) {
            $c = json_decode((string)@file_get_contents($cache), true);
            if (is_array($c) && isset($c['categories'])) return $c;
        }

        $now = time();
        $out = [];
        $index = [];
        foreach ($categories as $cat) {
            $slug = (string)($cat['slug'] ?? '');
            if ($slug === '') continue;
            $name = (string)($cat['name'] ?? $slug);
            $kw = (array)($cat['kw'] ?? []);
            $catUrl = (string)($cat['url'] ?? ('/buscar?cat=' . urlencode($slug)));

            $mine = [];
            foreach ($products as $p) {
                if ($kw && !$this->match($p, $kw)) continue;
                $mine[] = $p;
            }

            $types = [];   // L2 por product_type
            $brands = [];  // L3 por vendor (agregado)
            $models = [];  // L4/L5 por tags estructurados
            $prices = [];
            $new = 0; $offers = 0;

            foreach ($mine as $p) {
                $t = trim((string)($p['product_type'] ?? ''));
                if ($t !== '') $types[$t] = ($types[$t] ?? 0) + 1;
                $v = trim((string)($p['vendor'] ?? ''));
                if ($v !== '') $brands[$v] = ($brands[$v] ?? 0) + 1;

                foreach ($this->structuredTags($p) as $lvl => $val) {
                    if ($lvl >= 4) { $models[$val] = ($models[$val] ?? 0) + 1; }
                }

                $price = $this->price($p);
                if ($price > 0) $prices[] = $price;

                $created = strtotime((string)($p['created_at'] ?? '')) ?: 0;
                if ($created > 0 && ($now - $created) <= $newDays * 86400) $new++;
                if ($this->hasOffer($p)) $offers++;
            }

            arsort($types); arsort($brands); arsort($models);
            $typeList = [];
            foreach (array_slice($types, 0, 24, true) as $tn => $cnt) {
                $typeList[] = ['name' => $tn, 'count' => (int)$cnt, 'url' => $catUrl . '&tipo=' . urlencode($tn)];
            }
            $brandList = [];
            foreach (array_slice($brands, 0, 24, true) as $bn => $cnt) {
                $brandList[] = ['name' => $bn, 'count' => (int)$cnt, 'url' => '/buscar?marca=' . urlencode($bn)];
            }
            $modelList = [];
            foreach (array_slice($models, 0, 16, true) as $mn => $cnt) {
                $modelList[] = ['name' => $mn, 'count' => (int)$cnt, 'url' => '/buscar?q=' . urlencode($mn)];
            }

            $node = [
                'slug' => $slug,
                'name' => $name,
                'icon' => (string)($cat['icon'] ?? ''),
                'subtitle' => (string)($cat['tags'] ?? ''),
                'url' => $catUrl,
                'count' => count($mine),
                'badge_new' => $new > 0,
                'badge_offer' => $offers > 0,
                'types' => $typeList,
                'brands' => $brandList,
                'models' => $modelList,
                'prices' => $this->priceRanges($prices, $catUrl),
            ];
            $out[] = $node;

            $index[] = ['l' => 1, 'c' => $name, 'n' => $name, 'u' => $catUrl];
            foreach ($typeList as $x) $index[] = ['l' => 2, 'c' => $name, 'n' => $x['name'], 'u' => $x['url']];
            foreach ($brandList as $x) $index[] = ['l' => 3, 'c' => $name, 'n' => $x['name'], 'u' => $x['url']];
            foreach ($modelList as $x) $index[] = ['l' => 4, 'c' => $name, 'n' => $x['name'], 'u' => $x['url']];
        }

        $result = ['categories' => $out, 'index' => $index, 'stats' => ['products' => count($products), 'categories' => count($out)]];
        @file_put_contents($cache, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $result;
    }

    private function match(array $p, array $kw): bool
    {
        $hay = strtoupper((string)($p['title'] ?? '') . ' ' . (string)($p['product_type'] ?? '') . ' ' . (string)($p['vendor'] ?? ''));
        foreach ($kw as $k) { if ($k !== '' && str_contains($hay, strtoupper((string)$k))) return true; }
        return false;
    }

    /** Extrae jerarquía de tags "N) Kind_Value" → [nivel => valor]. */
    private function structuredTags(array $p): array
    {
        $out = [];
        foreach ((array)($p['tags'] ?? []) as $tag) {
            if (!is_string($tag)) continue;
            if (preg_match('/^(\d+)\)\s*(?:[^_]+)_(.+)$/u', trim($tag), $m)) {
                $out[(int)$m[1]] = trim($m[2]);
            }
        }
        return $out;
    }

    private function price(array $p): float
    {
        $v = $p['variants'][0] ?? [];
        return (float)($v['price'] ?? 0);
    }

    private function hasOffer(array $p): bool
    {
        foreach ((array)($p['variants'] ?? []) as $v) {
            $c = (float)($v['compare_at_price'] ?? 0);
            $p2 = (float)($v['price'] ?? 0);
            if ($c > 0 && $p2 > 0 && $c > $p2) return true;
        }
        return false;
    }

    private function priceRanges(array $prices, string $catUrl): array
    {
        if (count($prices) < 3) return [];
        sort($prices);
        $min = (int)floor(min($prices));
        $max = (int)ceil(max($prices));
        if ($max <= $min) return [];
        $step = (int)ceil(($max - $min) / 3);
        $ranges = [];
        for ($i = 0; $i < 3; $i++) {
            $lo = $min + $i * $step;
            $hi = ($i === 2) ? $max : ($min + ($i + 1) * $step);
            if ($lo >= $hi) continue;
            $ranges[] = [
                'name' => '$' . number_format($lo, 0, ',', '.') . ' – $' . number_format($hi, 0, ',', '.'),
                'url' => $catUrl . '&min=' . $lo . '&max=' . $hi,
            ];
        }
        return $ranges;
    }

    /**
     * Árbol auto-indexado: se construye SOLO desde el catálogo usando los tags
     * estructurados "N) Kind_Value" (niveles 1..5). Al agregar un producto, sus
     * categorías/tipos/marcas/modelos aparecen automáticamente en el menú.
     * Enlaces con el filtro genérico `/buscar?tax=N:Valor,...` (exacto).
     */
    private function tagTree(array $products, int $newDays): array
    {
        $now = time();
        $cats = [];
        foreach ($products as $p) {
            $tags = $this->structuredTags($p);
            $kinds = $this->structuredKinds($p);
            if (empty($tags[1])) {
                $t = trim((string)($p['product_type'] ?? ''));
                $l1 = $t !== '' ? $t : 'Otros';
                $kind1 = 'Tipo';
            } else {
                $l1 = (string)$tags[1];
                $kind1 = (string)($kinds[1] ?? 'Categoría');
            }
            $k = mb_strtolower($l1, 'UTF-8');
            if (!isset($cats[$k])) {
                $cats[$k] = ['name' => $l1, 'kind' => $kind1, 'slug' => $this->slugify($l1), 'products' => [], 'kinds' => []];
            }
            $cats[$k]['products'][] = ['p' => $p, 'tags' => $tags, 'kinds' => $kinds];
            foreach ([2, 3, 4, 5] as $lv) {
                if (!empty($kinds[$lv]) && empty($cats[$k]['kinds'][$lv])) $cats[$k]['kinds'][$lv] = (string)$kinds[$lv];
            }
        }

        $out = [];
        $index = [];
        foreach ($cats as $c) {
            $levels = [];
            $prices = [];
            $new = 0;
            $offers = 0;
            foreach ($c['products'] as $pp) {
                foreach ([2, 3, 4, 5] as $lv) {
                    if (!empty($pp['tags'][$lv])) {
                        $v = (string)$pp['tags'][$lv];
                        $levels[$lv][$v] = ($levels[$lv][$v] ?? 0) + 1;
                    }
                }
                $price = $this->price($pp['p']);
                if ($price > 0) $prices[] = $price;
                $created = strtotime((string)($pp['p']['created_at'] ?? '')) ?: 0;
                if ($created > 0 && ($now - $created) <= $newDays * 86400) $new++;
                if ($this->hasOffer($pp['p'])) $offers++;
            }
            $l1url = '/buscar?tax=1:' . rawurlencode((string)$c['name']);
            $mk = function (int $lv, array $vals) use ($c) {
                arsort($vals);
                $arr = [];
                foreach (array_slice($vals, 0, 24, true) as $v => $cnt) {
                    $arr[] = ['name' => (string)$v, 'count' => (int)$cnt, 'url' => '/buscar?tax=1:' . rawurlencode((string)$c['name']) . ',' . $lv . ':' . rawurlencode((string)$v)];
                }
                return $arr;
            };
            $node = [
                'slug' => (string)$c['slug'], 'name' => (string)$c['name'], 'kind' => (string)$c['kind'], 'icon' => '', 'subtitle' => '',
                'url' => $l1url, 'count' => count($c['products']), 'badge_new' => $new > 0, 'badge_offer' => $offers > 0,
                'types' => isset($levels[2]) ? $mk(2, $levels[2]) : [],
                'brands' => isset($levels[3]) ? $mk(3, $levels[3]) : [],
                'models' => isset($levels[4]) ? $mk(4, $levels[4]) : [],
                'level5' => isset($levels[5]) ? $mk(5, $levels[5]) : [],
                'kinds' => [2 => (string)($c['kinds'][2] ?? 'Subcategoría'), 3 => (string)($c['kinds'][3] ?? 'Marca'), 4 => (string)($c['kinds'][4] ?? 'Modelo')],
                'prices' => $this->priceRanges($prices, $l1url),
            ];
            $out[] = $node;
            $index[] = ['l' => 1, 'c' => (string)$c['name'], 'n' => (string)$c['name'], 'u' => $l1url];
            foreach ($node['types'] as $x) $index[] = ['l' => 2, 'c' => (string)$c['name'], 'n' => $x['name'], 'u' => $x['url']];
            foreach ($node['brands'] as $x) $index[] = ['l' => 3, 'c' => (string)$c['name'], 'n' => $x['name'], 'u' => $x['url']];
            foreach ($node['models'] as $x) $index[] = ['l' => 4, 'c' => (string)$c['name'], 'n' => $x['name'], 'u' => $x['url']];
        }
        usort($out, fn($a, $b) => $b['count'] <=> $a['count']);
        return ['categories' => $out, 'index' => $index, 'stats' => ['products' => count($products), 'categories' => count($out)]];
    }

    private function slugify(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = preg_replace('/[^a-z0-9]+/u', '-', $s) ?: '';
        return trim($s, '-') ?: 'x';
    }

    /** Devuelve [nivel => "Kind"] de los tags "N) Kind_Value". */
    private function structuredKinds(array $p): array
    {
        $out = [];
        foreach ((array)($p['tags'] ?? []) as $tag) {
            if (!is_string($tag)) continue;
            if (preg_match('/^(\d+)\)\s*([^_]+)_(.+)$/u', trim($tag), $m)) {
                $out[(int)$m[1]] = trim($m[2]);
            }
        }
        return $out;
    }
}
