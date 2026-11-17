<?php
namespace App\Services;

class SocialProofService
{
    private const DEFAULT_NAMES = [
        'María Fernanda Gutiérrez', 'Andrés Felipe Mejía', 'Luz Marina Daza', 'Jorge Luis Ospina',
        'Yeison Alberto Romero', 'Carolina Sofía Araújo', 'Juan David Camargo', 'Sandra Milena Barrios',
        'José Gregorio Maestre', 'Diana Carolina Redondo', 'Wilmer Antonio Cárdenas', 'Laura Vanessa Mendoza',
        'Héctor Manuel Peñaloza', 'Katherine Paola Rangel', 'Luis Fernando Ustariz', 'Mónica Alexandra Guerra',
        'Efraín José Salas', 'Angie Paola Villalba', 'Ricardo Andrés Molina', 'Natalia Andrea Cuello',
        'Samuel David Argote', 'Estefanía Rocío Lozano', 'Omar Enrique Pérez', 'Valentina Ortiz',
        'Cristian Camilo Dangond', 'María José Medina', 'Jhon Fredy Bolaños', 'Paola Andrea Noriega',
        'Álvaro José Pavajeau', 'Daniela Sofía Carrillo',
    ];

    private const DEFAULT_CITIES = ['Valledupar, Cesar'];

    private const DEFAULT_PRODUCTS = [
        ['name' => 'Acordeón Hohner Rey Vallenato ADG', 'category' => 'Vientos y acordeones', 'url' => '/tienda'],
        ['name' => 'Guitarra eléctrica Fender American Pro II Stratocaster', 'category' => 'Guitarras y amplificadores', 'url' => '/tienda'],
        ['name' => 'Bajo eléctrico Gretsch Streamliner Jet Club', 'category' => 'Bajos y amplificadores', 'url' => '/tienda'],
        ['name' => 'Audífono Shure SRH240', 'category' => 'Audio', 'url' => '/tienda'],
        ['name' => 'PAR LED Big Dipper LP005', 'category' => 'Iluminación', 'url' => '/tienda'],
        ['name' => 'Interfaz Focusrite Scarlett Solo', 'category' => 'Audio', 'url' => '/tienda'],
        ['name' => 'Micrófono Shure SM58', 'category' => 'Micrófonos', 'url' => '/tienda'],
        ['name' => 'Amplificador Boss Katana 50 MkII', 'category' => 'Guitarras y amplificadores', 'url' => '/tienda'],
        ['name' => 'Cuerdas Elixir Nanoweb 10-46', 'category' => 'Accesorios', 'url' => '/tienda'],
        ['name' => 'Teclado Casio CT-S300', 'category' => 'Teclados', 'url' => '/tienda'],
        ['name' => 'Batería acústica Pearl Roadshow', 'category' => 'Percusión', 'url' => '/tienda'],
        ['name' => 'Controlador MIDI Arturia KeyLab Essential', 'category' => 'Teclados', 'url' => '/tienda'],
        ['name' => 'Consola de mezcla Yamaha MG10XU', 'category' => 'Audio', 'url' => '/tienda'],
        ['name' => 'Monitores de estudio KRK Rokit 5', 'category' => 'Audio', 'url' => '/tienda'],
        ['name' => 'Pedal Boss DS-1 Distortion', 'category' => 'Accesorios', 'url' => '/tienda'],
        ['name' => 'Cable XLR Mogami 6 metros', 'category' => 'Accesorios', 'url' => '/tienda'],
        ['name' => 'Afinador clip Korg Pitchclip', 'category' => 'Accesorios', 'url' => '/tienda'],
        ['name' => 'Funda para guitarra acústica Gator', 'category' => 'Accesorios', 'url' => '/tienda'],
        ['name' => 'Micrófono inalámbrico Shure BLX24', 'category' => 'Micrófonos', 'url' => '/tienda'],
        ['name' => 'Cabina activa JBL EON615', 'category' => 'Audio', 'url' => '/tienda'],
    ];

    private ?array $catalogCache = null;
    private ?array $cityCache = null;

    public function defaults(): array
    {
        return [
            'enabled' => true,
            'position' => 'bottom-left',
            'animation_in' => 'slide-up',
            'animation_out' => 'fade',
            'interval_min' => 300,
            'interval_max' => 300,
            'initial_delay' => 12,
            'visible_seconds' => 8,
            'age_min' => 1,
            'age_max' => 60,
            'theme' => 'light',
            'accent' => '#F20D16',
            'dismissible' => true,
            'product_link' => true,
            'names' => self::DEFAULT_NAMES,
            'cities' => [],
            'products' => [],
            'categories' => [],
        ];
    }

    public function config(): array
    {
        $s = (new StoreService())->settings();
        $raw = json_decode((string)($s['store_social_proof'] ?? ''), true);
        return $this->normalize(is_array($raw) ? $raw : []);
    }

    public function normalize(array $raw): array
    {
        $d = $this->defaults();
        $out = [
            'enabled' => $this->bool($raw['enabled'] ?? $d['enabled']),
            'position' => $this->oneOf((string)($raw['position'] ?? $d['position']), ['bottom-left', 'bottom-right', 'bottom-center'], $d['position']),
            'animation_in' => $this->oneOf((string)($raw['animation_in'] ?? $d['animation_in']), ['slide-up', 'fade', 'scale'], $d['animation_in']),
            'animation_out' => $this->oneOf((string)($raw['animation_out'] ?? $d['animation_out']), ['fade', 'slide-down', 'scale'], $d['animation_out']),
            'interval_min' => $this->clamp((int)($raw['interval_min'] ?? $d['interval_min']), 300, 86400),
            'interval_max' => $this->clamp((int)($raw['interval_max'] ?? $d['interval_max']), 300, 86400),
            'initial_delay' => $this->clamp((int)($raw['initial_delay'] ?? $d['initial_delay']), 5, 120),
            'visible_seconds' => $this->clamp((int)($raw['visible_seconds'] ?? $d['visible_seconds']), 3, 60),
            'age_min' => $this->clamp((int)($raw['age_min'] ?? $d['age_min']), 1, 60),
            'age_max' => $this->clamp((int)($raw['age_max'] ?? $d['age_max']), 1, 60),
            'theme' => $this->oneOf((string)($raw['theme'] ?? $d['theme']), ['light', 'dark'], $d['theme']),
            'accent' => preg_match('/^#[0-9a-f]{6}$/i', (string)($raw['accent'] ?? '')) ? (string)$raw['accent'] : $d['accent'],
            'dismissible' => $this->bool($raw['dismissible'] ?? $d['dismissible']),
            'product_link' => $this->bool($raw['product_link'] ?? $d['product_link']),
            'names' => $this->lines($raw['names'] ?? $d['names'], 120),
            'cities' => $this->lines($raw['cities'] ?? [], 1200),
            'products' => $this->lines($raw['products'] ?? [], 250),
            'categories' => $this->lines($raw['categories'] ?? [], 60),
        ];
        if ($out['interval_max'] < $out['interval_min']) $out['interval_max'] = $out['interval_min'];
        if ($out['age_max'] < $out['age_min']) $out['age_max'] = $out['age_min'];
        if (!$out['names']) $out['names'] = $d['names'];
        return $out;
    }

    public function normalizeJson($raw): string
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) $raw = [];
        return json_encode($this->normalize($raw), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function settingsJson(): string
    {
        return json_encode($this->config(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function clientConfig(): array
    {
        $c = $this->config();
        if (!$c['enabled']) return ['enabled' => false];
        return [
            'enabled' => true,
            'endpoint' => '/api/v1/public/store/social-proof',
            'position' => $c['position'],
            'animation_in' => $c['animation_in'],
            'animation_out' => $c['animation_out'],
            'interval_min' => $c['interval_min'],
            'interval_max' => $c['interval_max'],
            'initial_delay' => $c['initial_delay'],
            'visible_seconds' => $c['visible_seconds'],
            'theme' => $c['theme'],
            'accent' => $c['accent'],
            'dismissible' => $c['dismissible'],
            'product_link' => $c['product_link'],
        ];
    }

    public function stats(): array
    {
        $c = $this->config();
        $products = $this->resolvedProducts($c);
        $cities = $this->resolvedCities($c);
        $combinations = count($c['names']) * count($products) * max(1, count($cities));
        return [
            'names' => count($c['names']),
            'cities' => count($cities),
            'products' => count($products),
            'categories' => count($c['categories']),
            'combinations' => $combinations,
            'minimum_met' => $combinations >= 500,
            'source' => $c['products'] ? 'manual' : ($this->catalogProducts() ? 'catalog' : 'fallback'),
            'city_source' => $c['cities'] ? 'manual' : ($this->shippingCities() ? 'shipping' : 'fallback'),
        ];
    }

    public function randomEvent(): ?array
    {
        $c = $this->config();
        if (!$c['enabled']) return null;
        $products = $this->resolvedProducts($c);
        $cities = $this->resolvedCities($c);
        if (!$products || !$cities) return null;
        $product = $products[random_int(0, count($products) - 1)];
        return [
            'name' => $c['names'][random_int(0, count($c['names']) - 1)],
            'city' => $cities[random_int(0, count($cities) - 1)],
            'product' => $product['name'],
            'category' => $product['category'],
            'url' => $product['url'],
            'age_seconds' => random_int($c['age_min'], $c['age_max']),
            'visible_seconds' => $c['visible_seconds'],
            'accent' => $c['accent'],
        ];
    }

    private function resolvedCities(array $c): array
    {
        if (!empty($c['cities'])) return $c['cities'];
        $shipping = $this->shippingCities();
        return $shipping ?: self::DEFAULT_CITIES;
    }

    private function shippingCities(): array
    {
        if ($this->cityCache !== null) return $this->cityCache;
        try {
            $this->cityCache = (new ShippingService())->cityLabels();
        } catch (\Throwable $e) {
            $this->cityCache = [];
        }
        return $this->cityCache;
    }

    private function resolvedProducts(array $c): array
    {
        $manual = $this->manualProducts($c['products']);
        if (!$manual) $manual = $this->catalogProducts();
        if (!$manual) $manual = self::DEFAULT_PRODUCTS;
        if ($c['categories']) {
            $allowed = array_map(fn($x) => mb_strtolower($x), $c['categories']);
            $filtered = array_values(array_filter($manual, function ($p) use ($allowed) {
                $cat = mb_strtolower((string)($p['category'] ?? ''));
                foreach ($allowed as $a) {
                    if ($a !== '' && (str_contains($cat, $a) || str_contains($a, $cat))) return true;
                }
                return false;
            }));
            if ($filtered) $manual = $filtered;
        }
        return $manual;
    }

    private function manualProducts(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            $parts = array_map('trim', explode('|', (string)$line));
            $name = $this->clean($parts[0] ?? '', 160);
            if ($name === '') continue;
            $category = $this->clean($parts[1] ?? '', 80) ?: 'Catálogo';
            $url = $this->safeUrl($parts[2] ?? '');
            $out[] = ['name' => $name, 'category' => $category, 'url' => $url];
            if (count($out) >= 250) break;
        }
        return $out;
    }

    private function catalogProducts(): array
    {
        if ($this->catalogCache !== null) return $this->catalogCache;
        $paths = [
            ROOT_DIR . '/public/assets/uploads/_catalog/catalog.json',
            ROOT_DIR . '/templates/themes/sonika/catalog.json',
        ];
        $out = [];
        foreach ($paths as $path) {
            if (!is_file($path)) continue;
            $data = json_decode((string)@file_get_contents($path), true);
            foreach ((array)($data['products'] ?? []) as $p) {
                $name = $this->clean($p['title'] ?? '', 160);
                $handle = trim((string)($p['handle'] ?? ''));
                if ($name === '' || $handle === '') continue;
                $out[] = [
                    'name' => $name,
                    'category' => $this->clean($p['product_type'] ?? ($p['type'] ?? ''), 80) ?: 'Catálogo',
                    'url' => '/producto/' . rawurlencode($handle),
                ];
                if (count($out) >= 500) break 2;
            }
        }
        $this->catalogCache = $out;
        return $out;
    }

    private function lines($value, int $max): array
    {
        if (is_string($value)) $value = preg_split('/\r\n|\r|\n/', $value) ?: [];
        if (!is_array($value)) return [];
        $out = [];
        foreach ($value as $line) {
            $line = $this->clean($line, 180);
            if ($line === '') continue;
            $out[] = $line;
            if (count($out) >= $max) break;
        }
        return array_values(array_unique($out));
    }

    private function clean($value, int $max): string
    {
        $value = trim(strip_tags((string)$value));
        return mb_substr($value, 0, $max);
    }

    private function safeUrl(string $url): string
    {
        $url = trim($url);
        if ($url !== '' && preg_match('~^(https?://|/)~i', $url)) return $url;
        return '/tienda';
    }

    private function bool($value): bool
    {
        if (is_bool($value)) return $value;
        $value = strtolower(trim((string)$value));
        return !in_array($value, ['0', 'false', 'no', 'off', ''], true);
    }

    private function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }

    private function oneOf(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}
