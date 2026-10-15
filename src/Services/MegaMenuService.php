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

    /** Ruta del catálogo: prioriza el SINCRONIZADO (persistente), luego el snapshot del tema. */
    private function catalogFile(): ?string
    {
        $base = dirname(__DIR__, 2);
        $synced = $base . '/public/assets/uploads/_catalog/catalog.json';
        if (is_file($synced)) return $synced;
        $theme = 'default';
        try {
            $theme = (string)(\App\Core\Database::instance()->query("SELECT theme FROM sites WHERE id = @site_id")->fetchColumn() ?: 'default');
        } catch (\Throwable $e) {
        }
        $theme = preg_replace('/[^a-z0-9_-]/i', '', $theme) ?: 'default';
        $f = $base . '/templates/themes/' . $theme . '/catalog.json';
        return is_file($f) ? $f : null;
    }

    /** Catálogo del tema activo (fallback sin funciones del tema). */
    public function catalog(): array
    {
        $f = $this->catalogFile();
        if (!$f) return [];
        $d = json_decode((string)@file_get_contents($f), true);
        return is_array($d) ? ($d['products'] ?? []) : [];
    }

    /**
     * @param array $categories Cada una: ['slug','name','icon','tags','kw'] (opcional 'url')
     * @param array|null $products
     */
    public function tree(array $categories, ?array $products = null, int $newDays = 90, string $sort = 'alpha'): array
    {
        $products = $products ?? $this->catalog();
        if (!$categories) return $this->tagTree($products, $newDays, $sort);

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

    private function img(array $p): string
    {
        $i = $p['images'][0] ?? null;
        if (is_array($i)) return (string)($i['src'] ?? '');
        if (is_string($i) && $i !== '') return $i;
        return '';
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
    private function tagTree(array $products, int $newDays, string $sort = 'alpha'): array
    {
        $file = $this->catalogFile();
        $sig = md5(($file ? (int)@filemtime($file) : 0) . '|' . count($products) . '|' . $newDays . '|' . $sort);
        $cache = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'wwi_megatag_' . $sig . '.json';
        if (is_file($cache) && (time() - filemtime($cache)) < 3600) {
            $c = json_decode((string)@file_get_contents($cache), true);
            if (is_array($c) && isset($c['categories'])) return $c;
        }
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
            $k = $this->foldKey($l1);
            if (!isset($cats[$k])) {
                $cats[$k] = ['name' => $this->displayLabel($l1), 'kind' => $kind1, 'slug' => $this->slugify($l1), 'products' => [], 'kinds' => []];
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
            $samples = [];
            foreach ($c['products'] as $pp) {
                $im = $this->img($pp['p']);
                foreach ([2, 3, 4, 5] as $lv) {
                    if (!empty($pp['tags'][$lv])) {
                        $fk = $this->foldKey((string)$pp['tags'][$lv]);
                        if (!isset($levels[$lv][$fk])) $levels[$lv][$fk] = ['name' => $this->displayLabel((string)$pp['tags'][$lv]), 'count' => 0, 'img' => ''];
                        $levels[$lv][$fk]['count']++;
                        if ($levels[$lv][$fk]['img'] === '' && $im !== '') $levels[$lv][$fk]['img'] = $im;
                    }
                }
                if ($im !== '' && count($samples) < 8) $samples[] = ['title' => (string)($pp['p']['title'] ?? ''), 'brand' => (string)($pp['p']['vendor'] ?? ''), 'price' => $this->price($pp['p']), 'url' => '/producto/' . (string)($pp['p']['handle'] ?? ''), 'img' => $im];
                $price = $this->price($pp['p']);
                if ($price > 0) $prices[] = $price;
                $created = strtotime((string)($pp['p']['created_at'] ?? '')) ?: 0;
                if ($created > 0 && ($now - $created) <= $newDays * 86400) $new++;
                if ($this->hasOffer($pp['p'])) $offers++;
            }
            $l1url = '/buscar?tax=1:' . rawurlencode((string)$c['name']);
            $mk = function (int $lv, array $vals) use ($c, $sort) {
                if ($sort === 'alpha') { uasort($vals, fn($a, $b) => strcmp($this->sortKey((string)$a['name']), $this->sortKey((string)$b['name']))); }
                else { uasort($vals, fn($a, $b) => $b['count'] <=> $a['count']); }
                $arr = [];
                foreach (array_slice($vals, 0, 24, true) as $fk => $row) {
                    $arr[] = ['name' => (string)$row['name'], 'count' => (int)$row['count'], 'thumb' => (string)($row['img'] ?? ''), 'url' => '/buscar?tax=1:' . rawurlencode((string)$c['name']) . ',' . $lv . ':' . rawurlencode((string)$row['name'])];
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
                'hero' => (string)($samples[0]['img'] ?? ''),
                'samples' => $samples,
                'prices' => $this->priceRanges($prices, $l1url),
            ];
            $out[] = $node;
            $index[] = ['l' => 1, 'c' => (string)$c['name'], 'n' => (string)$c['name'], 'u' => $l1url];
            foreach ($node['types'] as $x) $index[] = ['l' => 2, 'c' => (string)$c['name'], 'n' => $x['name'], 'u' => $x['url']];
            foreach ($node['brands'] as $x) $index[] = ['l' => 3, 'c' => (string)$c['name'], 'n' => $x['name'], 'u' => $x['url']];
            foreach ($node['models'] as $x) $index[] = ['l' => 4, 'c' => (string)$c['name'], 'n' => $x['name'], 'u' => $x['url']];
        }
        if ($sort === 'alpha') { usort($out, fn($a, $b) => strcmp($this->sortKey((string)$a['name']), $this->sortKey((string)$b['name']))); }
        else { usort($out, fn($a, $b) => $b['count'] <=> $a['count']); }
        $result = ['categories' => $out, 'index' => $index, 'stats' => ['products' => count($products), 'categories' => count($out)]];
        @file_put_contents($cache, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $result;
    }

    /** Mapa de plegado de acentos (iconv//TRANSLIT no es fiable en musl/Alpine). */
    private const FOLD = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ñ' => 'n', 'ç' => 'c',
    ];

    /** Correcciones de ortografía (tildes) por palabra — español. */
    private const ACCENT = [
        'acordeon' => 'acordeón', 'armonica' => 'armónica', 'audifono' => 'audífono', 'audifonos' => 'audífonos',
        'microfono' => 'micrófono', 'microfonos' => 'micrófonos', 'bateria' => 'batería', 'baterias' => 'baterías',
        'percusion' => 'percusión', 'iluminacion' => 'iluminación', 'amplificacion' => 'amplificación',
        'camara' => 'cámara', 'camaras' => 'cámaras', 'clasica' => 'clásica', 'clasico' => 'clásico',
        'generico' => 'genérico', 'electronico' => 'electrónico', 'electronica' => 'electrónica',
        'inalambrico' => 'inalámbrico', 'mecanico' => 'mecánico', 'metalico' => 'metálico', 'plastico' => 'plástico',
        'automatico' => 'automático', 'corazon' => 'corazón', 'violin' => 'violín', 'saxofon' => 'saxofón',
        'modulo' => 'módulo', 'adaptador' => 'adaptador', 'amplificador' => 'amplificador', 'afinador' => 'afinador',
        'guitarra' => 'guitarra', 'piano' => 'piano', 'trompeta' => 'trompeta', 'flauta' => 'flauta',
        'grabacion' => 'grabación', 'reproduccion' => 'reproducción', 'conexion' => 'conexión', 'seccion' => 'sección',
    ];

    /** Clave de agrupación: minúsculas + sin tildes + espacios colapsados (para fusionar duplicados). */
    public function foldKey(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = strtr($s, self::FOLD);
        return trim(preg_replace('/\s+/u', ' ', $s) ?: '');
    }

    /** Presentación unificada: "Primera letra mayúscula, resto minúsculas" + tildes (español). */
    private function displayLabel(string $s): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?: '');
        if ($s === '') return '';
        $low = mb_strtolower($s, 'UTF-8');
        $words = explode(' ', $low);
        foreach ($words as &$w) { if (isset(self::ACCENT[$w])) $w = self::ACCENT[$w]; }
        unset($w);
        $low = implode(' ', $words);
        return mb_strtoupper(mb_substr($low, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($low, 1, null, 'UTF-8');
    }

    private function sortKey(string $s): string
    {
        return $this->foldKey($s);
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
