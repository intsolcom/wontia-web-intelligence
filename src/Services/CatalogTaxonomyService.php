<?php
namespace App\Services;

use App\Core\Database;

/**
 * CatalogTaxonomyService — taxonomía canónica de SONIKA.
 *
 * SONIKA es la dueña de la taxonomía. Miche solo aporta datos de producto
 * (título, tipo, tags, marca). Este servicio traduce esos datos a la jerarquía
 * aprobada y es la ÚNICA fuente de verdad para:
 *   - navegación pública (categorías padre → familia → tipo),
 *   - clasificación de productos (mapa persistente, sin reclasificar en cada request),
 *   - espejo en `store_categories` (con parent_id/path/level),
 *   - protección frente al sync: una categoría aprobada/manual no se sobrescribe.
 *
 * El mapa se guarda en public/assets/uploads/_catalog/taxonomy_map.json y se
 * regenera después de cada importación. Los productos con category_locked=1 en
 * base de datos siempre ganan sobre la clasificación automática.
 */
class CatalogTaxonomyService
{
    public const VERSION = 4;

    /** Rutas antiguas que deben resolverse a la nueva jerarquía (solo compatibilidad interna). */
    public const LEGACY_ALIASES = [
        'guitarras' => 'cuerdas/guitarras',
        'acordeon' => 'vientos/acordeones',
        'pianos' => 'teclados/pianos',
        'amplificadores' => 'audio/amplificadores',
        'microfono-miche' => 'microfonos',
        'cabeza-movil' => 'iluminacion/cabezas-moviles',
        'cuerdas-guitarras' => 'accesorios',
        'acusticas-amplificadas' => 'cuerdas/guitarras/electroacusticas',
        'ukelele' => 'cuerdas/ukeleles',
        'saxofon' => 'vientos/saxofones',
        'bongoes' => 'percusion/bongos',
        'tumbadoras' => 'percusion/congas',
        'guitarra' => 'cuerdas/guitarras',
        'bateria' => 'percusion/baterias',
    ];

    /** Nodos canónicos renombrados: path viejo => path nuevo. */
    public const LEGACY_NODE_PATHS = [
        'cuerdas/guitarras/acusticas-amplificadas' => 'cuerdas/guitarras/electroacusticas',
    ];

    /** Categorías antiguas de `store_categories` que se renombran al padre canónico. */
    public const LEGACY_TOP = [
        'guitarras' => 'cuerdas',
        'acordeon' => 'vientos',
        'pianos' => 'teclados',
        'amplificadores' => 'audio',
        'microfono-miche' => 'microfonos',
        'cabeza-movil' => 'iluminacion',
        'cuerdas-guitarras' => 'accesorios',
    ];

    private const FOLD = ['á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ã'=>'a','é'=>'e','è'=>'e','ë'=>'e','ê'=>'e','í'=>'i','ì'=>'i','ï'=>'i','î'=>'i','ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u','ñ'=>'n','ç'=>'c'];

    /**
     * Árbol canónico: padre → familia → tipo.
     * El nivel 3 solo se usa cuando aporta una distinción real de compra.
     * Cada nodo tiene nombre visible; el slug/URL se deriva de la clave.
     */
    private const TREE = [
        'percusion' => [
            'name' => 'Percusión', 'icon' => 'drum', 'tags' => 'Baterías · Platillos · Congas · Parches',
            'kw' => ['PERCUS','BATER','REDOBL','PLATILL','CONGA','TUMBA','BONGO','DJEMBE','CAJON','CAJA','TIMBAL','MARAC','CAMPAN','PANDER','TAMBOR','GUIRO','CLAVE','CASTAN','XILOF','METALOF','PARCHE','BAQUETA','SHAKER','GUACHE','JAM BLOCK'],
            'children' => [
                'baterias' => ['name' => 'Baterías', 'children' => [
                    'acusticas' => ['name' => 'Baterías Acústicas'],
                    'electronicas' => ['name' => 'Baterías Electrónicas'],
                ]],
                'redoblantes' => ['name' => 'Redoblantes'],
                'platillos' => ['name' => 'Platillos'],
                'congas' => ['name' => 'Congas y Tumbadoras'],
                'bongos' => ['name' => 'Bongos'],
                'cajones' => ['name' => 'Cajones'],
                'timbales' => ['name' => 'Timbales'],
                'panderetas' => ['name' => 'Panderetas'],
                'baquetas' => ['name' => 'Baquetas'],
                'parches' => ['name' => 'Parches'],
                'campanas' => ['name' => 'Campanas y Bloques'],
                'shakers' => ['name' => 'Shakers y Güiros'],
                'cajas-vallenatas' => ['name' => 'Cajas Vallenatas'],
                'maracas' => ['name' => 'Maracas'],
                'djembes' => ['name' => 'Djembes'],
                'tambores' => ['name' => 'Tambores'],
                'metalofonos' => ['name' => 'Metalófonos y Xilófonos'],
                'otros' => ['name' => 'Otras Percusiones'],
            ],
        ],
        'cuerdas' => [
            'name' => 'Cuerdas', 'icon' => 'guitar', 'tags' => 'Guitarras · Bajos · Ukeleles · Violines',
            'kw' => ['GUITAR','BAJO','BASS','UKELELE','VIOLIN','VIOLA','VIOLONCHELO','CONTRABAJO','ARPA','CUATRO','TIPLE','ENCORDADO','CUERDA'],
            'children' => [
                'guitarras' => ['name' => 'Guitarras', 'children' => [
                    'acusticas' => ['name' => 'Guitarras Acústicas'],
                    'electroacusticas' => ['name' => 'Guitarras Electroacústicas'],
                    'electricas' => ['name' => 'Guitarras Eléctricas'],
                    'clasicas' => ['name' => 'Guitarras Clásicas'],
                    'combos' => ['name' => 'Combos de Guitarra'],
                    'otras' => ['name' => 'Otras Guitarras'],
                ]],
                'bajos' => ['name' => 'Bajos', 'children' => [
                    'electricos' => ['name' => 'Bajos Eléctricos'],
                    'acusticos' => ['name' => 'Bajos Acústicos'],
                    'combos' => ['name' => 'Combos de Bajo'],
                    'otros' => ['name' => 'Otros Bajos'],
                ]],
                'ukeleles' => ['name' => 'Ukeleles'],
                'violines' => ['name' => 'Violines'],
                'violas' => ['name' => 'Violas'],
                'violoncellos' => ['name' => 'Violoncellos'],
                'contrabajos' => ['name' => 'Contrabajos'],
                'arpas' => ['name' => 'Arpas'],
                'cuatros-tiples' => ['name' => 'Cuatros y Tiples'],
                'encordados' => ['name' => 'Encordados'],
                'otros' => ['name' => 'Otras Cuerdas'],
            ],
        ],
        'vientos' => [
            'name' => 'Vientos', 'icon' => 'accordion', 'tags' => 'Acordeones · Saxos · Trompetas · Flautas',
            'kw' => ['ACORDE','ARMONICA','CLARINET','FLAUTA','GAITA','MELODICA','SAXOF','TROMB','TROMPET','CORNO','OBOE','FAGOT','OCARINA','BOQUILLA','CAÑA'],
            'children' => [
                'acordeones' => ['name' => 'Acordeones'],
                'armonicas' => ['name' => 'Armónicas'],
                'melodicas' => ['name' => 'Melódicas'],
                'flautas' => ['name' => 'Flautas'],
                'saxofones' => ['name' => 'Saxofones'],
                'trompetas' => ['name' => 'Trompetas'],
                'clarinetes' => ['name' => 'Clarinetes'],
                'gaitas' => ['name' => 'Gaitas'],
                'trombones' => ['name' => 'Trombones'],
                'boquillas-canas' => ['name' => 'Boquillas y Cañas'],
                'otros' => ['name' => 'Otros Vientos'],
            ],
        ],
        'teclados' => [
            'name' => 'Teclados', 'icon' => 'piano', 'tags' => 'Pianos · Sintetizadores · Controladores',
            'kw' => ['PIANO','TECLADO','SYNTH','SINTETIZ','CONTROLADOR','MIDI','PAD','CASIO','CELVIANO','PRIVIA'],
            'children' => [
                'pianos' => ['name' => 'Pianos'],
                'teclados' => ['name' => 'Teclados'],
                'sintetizadores' => ['name' => 'Sintetizadores'],
                'controladores' => ['name' => 'Controladores'],
                'pads' => ['name' => 'Pads'],
                'otros' => ['name' => 'Otros Teclados'],
            ],
        ],
        'audio' => [
            'name' => 'Audio', 'icon' => 'speaker', 'tags' => 'Amplificadores · Cabinas · Mixers · Audífonos',
            'kw' => ['AMPLIF','CABINA','MIXER','PARLANT','PLANTA','PROCESAD','INTERFAZ','INTERFACE','MONITOR','AUDIFONO','CONSOLA','POTENCIA','CROSSOVER','ECUALIZ','DI ','SPEAKER','TOCADISCOS','REPRODUCTOR'],
            'children' => [
                'amplificadores' => ['name' => 'Amplificadores'],
                'cabinas' => ['name' => 'Cabinas'],
                'mixers-consolas' => ['name' => 'Mixers y Consolas'],
                'parlantes' => ['name' => 'Parlantes'],
                'monitores' => ['name' => 'Monitores'],
                'interfaces' => ['name' => 'Interfaces'],
                'audifonos' => ['name' => 'Audífonos'],
                'plantas' => ['name' => 'Plantas de Potencia'],
                'procesadores' => ['name' => 'Procesadores'],
                'controladores' => ['name' => 'Controladores DJ'],
                'tocadiscos' => ['name' => 'Tocadiscos y Reproductores'],
                'otros' => ['name' => 'Otros Audio'],
            ],
        ],
        'microfonos' => [
            'name' => 'Micrófonos', 'icon' => 'mic', 'tags' => 'Dinámicos · Condensador · Inalámbricos',
            'kw' => ['MICROFON','MICRO','SHURE','INALAMBR','CONDENSADOR','DINAMICO'],
            'children' => [
                'dinamicos' => ['name' => 'Micrófonos Dinámicos'],
                'condensador' => ['name' => 'Micrófonos de Condensador'],
                'inalambricos' => ['name' => 'Micrófonos Inalámbricos'],
                'otros' => ['name' => 'Otros Micrófonos'],
            ],
        ],
        'iluminacion' => [
            'name' => 'Iluminación', 'icon' => 'light', 'tags' => 'Cabezas móviles · Par LED · Truss · Láseres',
            'kw' => ['CABEZA','PAR LED','PARLED','LED','HUMO','TRUSS','TENSOR','DIMMER','STROBO','MOVIL','LUZ','LUCE','BLINDER','REFLECTOR','LASER'],
            'children' => [
                'cabezas-moviles' => ['name' => 'Cabezas Móviles'],
                'par-led' => ['name' => 'Par LED'],
                'truss' => ['name' => 'Truss y Estructuras'],
                'maquinas-humo' => ['name' => 'Máquinas de Humo'],
                'tensores' => ['name' => 'Tensores'],
                'luces' => ['name' => 'Luces y Láseres'],
                'cortinas' => ['name' => 'Cortinas'],
                'otros' => ['name' => 'Otra Iluminación'],
            ],
        ],
        'accesorios' => [
            'name' => 'Accesorios', 'icon' => 'box', 'tags' => 'Soportes · Correas · Forros · Cables · Picks',
            'kw' => ['FORRO','FUNDA','SOPORTE','STAND','SILLA','ATRIL','RACK','CLAMP','CABLE','ACOPLE','LIMPIADOR','PROTECT','EMPUNADURA','AFINADOR','SORDINA','SLIDE','BANQUETA','CORREA','PICK','CAPODASTRO','REPUESTO','PEDAL','KIT','BAQUETERO','CLAVIJERO'],
            'children' => [
                'soportes-estructuras' => ['name' => 'Soportes y Estructuras'],
                'correas' => ['name' => 'Correas'],
                'forros-fundas' => ['name' => 'Forros, Fundas y Estuches'],
                'cables-adaptadores' => ['name' => 'Cables y Adaptadores'],
                'picks' => ['name' => 'Picks'],
                'capodastros' => ['name' => 'Capodastros'],
                'afinadores' => ['name' => 'Afinadores'],
                'sordinas' => ['name' => 'Sordinas'],
                'slides' => ['name' => 'Slides'],
                'repuestos' => ['name' => 'Repuestos'],
                'kits' => ['name' => 'Kits y Sets'],
                'limpiadores' => ['name' => 'Limpiadores'],
                'baqueteros' => ['name' => 'Baqueteros'],
                'cortavientos' => ['name' => 'Cortavientos'],
                'clavijeros' => ['name' => 'Clavijeros'],
                'protectores' => ['name' => 'Protectores'],
                'clips' => ['name' => 'Clips'],
                'souvenirs' => ['name' => 'Souvenirs'],
                'pedales' => ['name' => 'Pedales'],
                'otros' => ['name' => 'Otros Accesorios'],
            ],
        ],
    ];

    private static ?array $index = null;
    private static ?array $map = null;
    private static array $classCache = [];
    private static array $countCache = [];

    // ── Árbol / navegación ─────────────────────────────────────────────

    /** Índice plano: path => nodo. Incluye url, parent, level y child_keys. */
    public static function index(): array
    {
        if (self::$index !== null) return self::$index;
        $nodes = [];
        $walk = static function (array $branch, string $parent, int $level) use (&$walk, &$nodes): void {
            $sort = 0;
            foreach ($branch as $slug => $def) {
                $key = $parent === '' ? (string)$slug : $parent . '/' . (string)$slug;
                $nodes[$key] = [
                    'key' => $key,
                    'path' => $key,
                    'slug' => (string)$slug,
                    'name' => (string)($def['name'] ?? $slug),
                    'parent' => $parent,
                    'level' => $level,
                    'icon' => (string)($def['icon'] ?? ''),
                    'tags' => (string)($def['tags'] ?? ''),
                    'kw' => (array)($def['kw'] ?? []),
                    'sort' => $sort++,
                    'child_keys' => [],
                ];
                if (!empty($def['children'])) $walk((array)$def['children'], $key, $level + 1);
            }
        };
        $walk(self::TREE, '', 1);
        foreach ($nodes as $key => $node) {
            if ($node['parent'] !== '' && isset($nodes[$node['parent']])) {
                $nodes[$node['parent']]['child_keys'][] = $key;
            }
        }
        foreach ($nodes as $key => $node) {
            $nodes[$key]['url'] = '/' . $key;
            $nodes[$key]['depth'] = $node['level'];
        }
        return self::$index = $nodes;
    }

    public static function version(): int
    {
        return self::VERSION;
    }

    /** Devuelve un nodo por path, alias antiguo o slug local único. */
    public static function find(string $path): ?array
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '') return null;
        if (isset(self::LEGACY_ALIASES[$path])) $path = self::LEGACY_ALIASES[$path];
        $nodes = self::index();
        if (isset($nodes[$path])) return self::decorate($nodes[$path]);
        $folded = self::fold($path);
        foreach ($nodes as $key => $node) {
            if (self::fold($key) === $folded) return self::decorate($node);
        }
        $matches = [];
        foreach ($nodes as $key => $node) {
            if ($node['slug'] === $path) $matches[] = $key;
        }
        if (count($matches) === 1) return self::decorate($nodes[$matches[0]]);
        return null;
    }

    public static function topLevel(): array
    {
        $out = [];
        foreach (self::index() as $key => $node) {
            if ($node['level'] === 1) $out[] = self::decorate($node);
        }
        return $out;
    }

    public static function children(string $key): array
    {
        $nodes = self::index();
        if (!isset($nodes[$key])) return [];
        $out = [];
        foreach ($nodes[$key]['child_keys'] as $childKey) $out[] = self::decorate($nodes[$childKey]);
        return $out;
    }

    /** Todos los paths del nodo y sus descendientes (incluido el propio nodo). */
    public static function descendants(string $key): array
    {
        $nodes = self::index();
        if (!isset($nodes[$key])) return [];
        $out = [$key];
        foreach ($nodes[$key]['child_keys'] as $childKey) {
            foreach (self::descendants($childKey) as $sub) $out[] = $sub;
        }
        return array_values(array_unique($out));
    }

    /** Ancestros de arriba hacia abajo, sin incluir el nodo. */
    public static function ancestors(string $key): array
    {
        $nodes = self::index();
        $out = [];
        $cur = $nodes[$key]['parent'] ?? '';
        while ($cur !== '' && isset($nodes[$cur])) {
            array_unshift($out, self::decorate($nodes[$cur]));
            $cur = $nodes[$cur]['parent'];
        }
        return $out;
    }

    public static function categoryUrl(string $key): string
    {
        $node = self::find($key);
        return $node ? $node['url'] : '/' . ltrim($key, '/');
    }

    private static function decorate(array $node): array
    {
        $node['url'] = '/' . $node['path'];
        $node['depth'] = $node['level'];
        if (!isset($node['children']) || !is_array($node['children'])) $node['children'] = [];
        return $node;
    }

    /** Árbol expandido (para menús): incluye children como nodos completos. */
    public static function expanded(string $key, int $depth = 3): ?array
    {
        $node = self::find($key);
        if (!$node) return null;
        if ($depth > 1) {
            $children = [];
            foreach (self::children($node['key']) as $child) {
                $children[] = self::expanded($child['key'], $depth - 1) ?? $child;
            }
            $node['children'] = $children;
        }
        return $node;
    }

    // ── Clasificación de producto ─────────────────────────────────────

    /**
     * Clasifica un producto Miche en la taxonomía canónica.
     * Devuelve parent/family/type como paths completos + status/reason/secondary.
     */
    public static function classify(array $product): array
    {
        $title = (string)($product['title'] ?? '');
        $ptype = (string)($product['product_type'] ?? '');
        $tags = (array)($product['tags'] ?? []);
        $catTags = [];
        $typeTags = [];
        foreach ($tags as $tag) {
            if (!is_string($tag)) continue;
            if (preg_match('/^1\)\s*Categor[ií]a_(.+)$/u', trim($tag), $m)) $catTags[] = trim($m[1]);
            elseif (preg_match('/^2\)\s*Tipo_(.+)$/u', trim($tag), $m)) $typeTags[] = trim($m[1]);
        }
        $invalidPtype = (bool)preg_match('~snowboard|outlet|semana|dia sin iva|día sin iva|promo|ejecitador|ejercitador~u', self::fold($ptype));

        $cands = [];
        $add = static function (string $text, string $source) use (&$cands): void {
            $text = self::fold($text);
            if ($text === '') return;
            $base = ['category_tag' => 1000, 'title' => 400, 'type_tag' => 200, 'product_type' => 100][$source] ?? 100;
            foreach (self::rules() as $rule) {
                if (!preg_match($rule[0], $text)) continue;
                $family = (string)$rule[2];
                $b = $family === 'otros' ? max(100, $base - 400) : $base;
                $cands[] = [
                    'p' => (string)$rule[1],
                    'f' => $family,
                    't' => (string)$rule[3],
                    'weight' => (int)$rule[4] + $b,
                    'source' => $source,
                    'match' => $text,
                ];
            }
        };
        foreach ($catTags as $tag) $add($tag, 'category_tag');
        $add($title, 'title');
        foreach ($typeTags as $tag) $add($tag, 'type_tag');
        if ($ptype !== '') $add($ptype, 'product_type');

        $forced = self::contextCandidates($title, $ptype);
        foreach ($forced as $f) {
            $cands[] = [
                'p' => (string)$f[0], 'f' => (string)$f[1], 't' => (string)$f[2],
                'weight' => (int)$f[3], 'source' => 'context', 'match' => $title,
            ];
        }

        if (!$cands) {
            return [
                'parent' => '', 'family' => '', 'type' => '', 'status' => $invalidPtype ? 'quarantine' : 'pending',
                'reason' => $invalidPtype ? 'product_type inválido y sin categoría reconocible' : 'sin categoría reconocible',
                'secondary' => [], 'invalid_ptype' => $invalidPtype, 'cat_tags' => $catTags, 'type_tags' => $typeTags,
            ];
        }

        usort($cands, static fn($a, $b) => $b['weight'] <=> $a['weight']);
        $top = $cands[0];
        if ($top['f'] === 'otros') {
            foreach ($cands as $c) {
                if ($c['p'] === $top['p'] && $c['f'] !== 'otros') { $top = $c; break; }
            }
        }
        // Micrófonos: la familia se decide por el modelo/título, no solo por el
        // tag genérico "Micrófono". Inalámbrico > condensador > dinámico.
        if ($top['p'] === 'microfonos') {
            $mh = self::fold($title . ' ' . $ptype);
            $wireless = (bool)preg_match('~inalambr|inalámbr|uhf|vhf|blx|slx|slxd|re3-|wm8|lark|aurora x-|xg-5|xw-6|ts-331|ts6310|ts-k201|art-1101|art-730|ts-2200|hm 200|da-235|da-237|sistema ina|transmisor|receptor|antena~u', $mh);
            $condenser = (bool)preg_match('~condensador|condesador|condesa|at20|pga181|\bc-1\b|\bc-1u\b|\bc-3\b|nt1|nt2|tlm|c414|pga81|pga98|beta 98|beta 87|sm35|mx153|re97|re520|pc-k|pcm|gx-|ta-55c|bm-630|sgc|by-mm|tcm-|tcm4|\bv1\b|\bv4\b|ms-118|hm700|hm-870|solapa|lavalier|estudio|usb|diadema~u', $mh);
            if ($wireless) $top['f'] = 'inalambricos';
            elseif ($condenser) $top['f'] = 'condensador';
            else $top['f'] = 'dinamicos';
            $top['t'] = '';
        }

        $conflict = '';
        if (!$forced) {
            $strongParents = [];
            foreach ($cands as $c) {
                if ($c['source'] === 'category_tag' && $c['weight'] >= 1000 && $c['p'] !== 'accesorios') $strongParents[$c['p']] = true;
            }
            if (count($strongParents) > 1) $conflict = 'varios padres: ' . implode(', ', array_keys($strongParents));
        }

        $familyKey = $top['p'] . '/' . $top['f'];
        $type = (string)$top['t'];
        if ($type === '') {
            $type = self::subtype($top['p'], $top['f'], $title . ' ' . $ptype . ' ' . implode(' ', $typeTags));
        }
        if ($type !== '' && !self::nodeExists($familyKey . '/' . $type)) $type = '';
        if ($type === '') {
            $children = self::children($familyKey);
            if ($children) {
                $pick = null;
                foreach ($children as $child) {
                    if ($child['slug'] === 'otros' || $child['slug'] === 'otras') { $pick = $child; break; }
                }
                $type = (string)($pick['slug'] ?? $children[0]['slug']);
            }
        }
        $typeKey = $type !== '' ? $familyKey . '/' . $type : '';
        if (!self::nodeExists($familyKey)) {
            $familyKey = $top['p'];
            $typeKey = '';
        }
        if (!self::nodeExists($top['p'])) {
            return [
                'parent' => '', 'family' => '', 'type' => '', 'status' => 'pending',
                'reason' => 'regla apunta a un padre inexistente: ' . $top['p'], 'secondary' => [],
                'invalid_ptype' => $invalidPtype, 'cat_tags' => $catTags, 'type_tags' => $typeTags,
            ];
        }

        $secondary = [];
        foreach ($cands as $c) {
            if ($c['p'] === $top['p']) continue;
            if ($c['p'] === 'accesorios' || $top['p'] === 'accesorios') {
                $secFamily = $c['p'] . '/' . $c['f'];
                if (!self::nodeExists($secFamily)) $secFamily = $c['p'];
                if (!isset($secondary[$c['p']])) $secondary[$c['p']] = $secFamily;
            }
        }

        $status = $forced || $top['weight'] >= 1000 ? 'high' : ($top['weight'] >= 500 ? 'medium' : 'low');
        if ($conflict !== '') $status = 'review';

        return [
            'parent' => $top['p'],
            'family' => $familyKey,
            'type' => $typeKey,
            'status' => $status,
            'reason' => $conflict !== '' ? $conflict : ('regla: ' . $top['match'] . ' (' . $top['source'] . ')'),
            'secondary' => array_values($secondary),
            'invalid_ptype' => $invalidPtype,
            'cat_tags' => $catTags,
            'type_tags' => $typeTags,
        ];
    }

    /** Conteo de productos de una categoría (incluye descendientes), desde el mapa. */
    public static function categoryCount(string $key): int
    {
        $map = self::loadMap();
        if (isset($map['counts'][$key])) return (int)$map['counts'][$key];
        $set = array_fill_keys(self::descendants($key), true);
        $n = 0;
        foreach (self::loadCatalog() as $p) {
            foreach (self::productKeys($p) as $pk) {
                if (isset($set[$pk])) { $n++; break; }
            }
        }
        return $n;
    }

    /** Imagen representativa de la categoría desde el mapa (primera imagen de sus productos). */
    public static function categoryImage(string $key): string
    {
        $map = self::loadMap();
        return (string)($map['images'][$key] ?? '');
    }

    /**
     * Paths de categoría de un producto para la grilla pública.
     *
     * Se usa SOLO la categoría principal. Las secundarias quedan guardadas en el
     * mapa (`sec`) para una curaduría posterior: usarlas automáticamente hacía que
     * forros, correas y cables aparecieran dentro de Guitarras o Micrófonos.
     */
    public static function productKeys(array $product): array
    {
        $tx = self::forProduct($product);
        $primary = (string)($tx['type'] ?? '');
        if ($primary === '') $primary = (string)($tx['family'] ?? '');
        if ($primary === '') $primary = (string)($tx['parent'] ?? '');
        return $primary !== '' ? [$primary] : [];
    }

    /** Clasificación efectiva: mapa persistente si existe; si no, clasificación en vivo (cacheada). */
    public static function forProduct(array $product): array
    {
        $handle = (string)($product['handle'] ?? '');
        $catalogHandle = (string)($product['_catalog_handle'] ?? '');
        $map = self::loadMap();
        // El mapa histórico está indexado por el handle del catálogo de origen.
        // Si el producto ya usa un slug SEO nuevo, se prueba también ese handle.
        foreach (array_unique(array_filter([$handle, $catalogHandle])) as $key) {
            if (isset($map['products'][$key])) {
                $row = (array)$map['products'][$key];
                return [
                    'parent' => (string)($row['p'] ?? ''),
                    'family' => (string)($row['f'] ?? ''),
                    'type' => (string)($row['t'] ?? ''),
                    'status' => (string)($row['s'] ?? ''),
                    'reason' => (string)($row['r'] ?? 'map'),
                    'secondary' => array_values((array)($row['sec'] ?? [])),
                    'source' => 'map',
                ];
            }
        }
        if ($handle !== '' && isset(self::$classCache[$handle])) return self::$classCache[$handle];
        $tx = self::classify($product);
        $tx['source'] = 'live';
        if ($handle !== '') self::$classCache[$handle] = $tx;
        return $tx;
    }

    // ── Mapa persistente ──────────────────────────────────────────────

    public static function catalogPath(): string
    {
        $root = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
        $synced = $root . '/public/assets/uploads/_catalog/catalog.json';
        if (is_file($synced)) return $synced;
        $theme = 'sonika';
        try {
            $theme = (string)(Database::instance()->query("SELECT theme FROM sites WHERE id = @site_id")->fetchColumn() ?: 'sonika');
        } catch (\Throwable $e) {
        }
        $theme = preg_replace('/[^a-z0-9_-]/i', '', $theme) ?: 'sonika';
        return $root . '/templates/themes/' . $theme . '/catalog.json';
    }

    public static function mapPath(): string
    {
        $root = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
        return $root . '/public/assets/uploads/_catalog/taxonomy_map.json';
    }

    private static function loadCatalog(): array
    {
        $f = self::catalogPath();
        if (!is_file($f)) return [];
        $d = json_decode((string)@file_get_contents($f), true);
        return is_array($d) && !empty($d['products']) ? (array)$d['products'] : [];
    }

    public static function loadMap(bool $refresh = false): array
    {
        if ($refresh) self::$map = null;
        if (self::$map !== null) return self::$map;
        $f = self::mapPath();
        if (!is_file($f)) return self::$map = [];
        $d = json_decode((string)@file_get_contents($f), true);
        if (!is_array($d) || (int)($d['version'] ?? 0) !== self::VERSION) return self::$map = [];
        return self::$map = $d;
    }

    /**
     * Genera el mapa handle → categoría para todo el catálogo.
     * Respeta las categorías bloqueadas (category_locked=1) de la base de datos.
     */
    public static function buildMap(?array $products = null, bool $write = true): array
    {
        $products = $products ?? self::loadCatalog();
        $locked = self::lockedAssignments();
        $out = [
            'version' => self::VERSION,
            'generated_at' => date('c'),
            'catalog_mtime' => (int)(@filemtime(self::catalogPath()) ?: 0),
            'products' => [],
        ];
        $statuses = [];
        $pending = [];
        $counts = [];
        $images = [];
        foreach ($products as $p) {
            $handle = trim((string)($p['handle'] ?? ''));
            if ($handle === '') continue;
            $tx = self::classify($p);
            $row = [
                'p' => (string)$tx['parent'],
                'f' => (string)$tx['family'],
                't' => (string)$tx['type'],
                's' => (string)$tx['status'],
                'r' => (string)$tx['reason'],
            ];
            if (!empty($tx['secondary'])) $row['sec'] = array_values((array)$tx['secondary']);
            if (isset($locked[$handle])) {
                $row = array_merge($row, $locked[$handle]);
                $row['s'] = 'locked';
                $row['r'] = 'categoría aprobada manualmente';
            }
            $out['products'][$handle] = $row;
            $statuses[$row['s']] = ($statuses[$row['s']] ?? 0) + 1;
            if ($row['p'] === '') $pending[] = $handle;
            $primary = (string)$row['t'];
            if ($primary === '') $primary = (string)$row['f'];
            if ($primary === '') $primary = (string)$row['p'];
            if ($primary !== '') {
                $img = '';
                foreach ((array)($p['images'] ?? []) as $im) {
                    $src = is_array($im) ? (string)($im['src'] ?? '') : (string)$im;
                    if ($src !== '') { $img = $src; break; }
                }
                $parts = explode('/', $primary);
                $acc = '';
                foreach ($parts as $part) {
                    $acc = $acc === '' ? $part : $acc . '/' . $part;
                    $counts[$acc] = ($counts[$acc] ?? 0) + 1;
                    if ($img !== '' && empty($images[$acc])) $images[$acc] = $img;
                }
            }
        }
        $out['counts'] = $counts;
        $out['images'] = $images;
        $out['summary'] = [
            'products' => count($out['products']),
            'statuses' => $statuses,
            'pending' => count($pending),
            'pending_samples' => array_slice($pending, 0, 30),
        ];
        if ($write) {
            $f = self::mapPath();
            $dir = dirname($f);
            if (!is_dir($dir)) @mkdir($dir, 0777, true);
            $tmp = $f . '.tmp';
            if (@file_put_contents($tmp, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false) @rename($tmp, $f);
            self::$map = $out;
        }
        return $out;
    }

    /** Categorías fijadas a mano: handle => [p,f,t]. */
    private static function lockedAssignments(): array
    {
        $out = [];
        try {
            $db = Database::instance();
            $st = $db->query("SELECT p.slug, p.catalog_handle, c.path FROM store_products p JOIN store_categories c ON c.id = p.category_id AND c.site_id = p.site_id WHERE p.site_id = @site_id AND p.category_locked = 1 AND c.path IS NOT NULL AND c.path <> ''");
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $handle = trim((string)($r['catalog_handle'] ?? '')) ?: trim((string)($r['slug'] ?? ''));
                $path = trim((string)($r['path'] ?? ''));
                if ($handle === '' || $path === '') continue;
                $parts = explode('/', $path);
                $out[$handle] = [
                    'p' => (string)($parts[0] ?? ''),
                    'f' => (string)($parts[0] ?? '') . (isset($parts[1]) ? '/' . $parts[1] : ''),
                    't' => $path,
                ];
            }
        } catch (\Throwable $e) {
        }
        return $out;
    }

    // ── Espejo en base de datos ───────────────────────────────────────

    public static function ensureSchema(): void
    {
        $db = Database::instance();
        $catCols = [];
        foreach ($db->query('SHOW COLUMNS FROM store_categories')->fetchAll(\PDO::FETCH_ASSOC) as $r) $catCols[(string)$r['Field']] = true;
        $addCat = [];
        if (!isset($catCols['parent_id'])) $addCat[] = 'ADD COLUMN parent_id INT NULL';
        if (!isset($catCols['path'])) $addCat[] = 'ADD COLUMN path VARCHAR(255) NULL';
        if (!isset($catCols['level'])) $addCat[] = 'ADD COLUMN level TINYINT NOT NULL DEFAULT 1';
        if (!isset($catCols['icon'])) $addCat[] = 'ADD COLUMN icon VARCHAR(40) NULL';
        if (!isset($catCols['source'])) $addCat[] = "ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'sonika'";
        if (!isset($catCols['external_key'])) $addCat[] = 'ADD COLUMN external_key VARCHAR(120) NULL';
        if ($addCat) $db->exec('ALTER TABLE store_categories ' . implode(', ', $addCat));
        try {
            $idx = [];
            foreach ($db->query('SHOW INDEX FROM store_categories')->fetchAll(\PDO::FETCH_ASSOC) as $r) $idx[(string)$r['Key_name']] = true;
            if (isset($idx['site_store_cat_slug'])) $db->exec('ALTER TABLE store_categories DROP INDEX site_store_cat_slug');
            if (!isset($idx['site_cat_path'])) $db->exec('ALTER TABLE store_categories ADD UNIQUE KEY site_cat_path (site_id, path)');
            if (!isset($idx['idx_store_cat_slug'])) $db->exec('ALTER TABLE store_categories ADD KEY idx_store_cat_slug (site_id, slug)');
        } catch (\Throwable $e) {
        }

        $prodCols = [];
        foreach ($db->query('SHOW COLUMNS FROM store_products')->fetchAll(\PDO::FETCH_ASSOC) as $r) $prodCols[(string)$r['Field']] = true;
        $addProd = [];
        if (!isset($prodCols['category_locked'])) $addProd[] = 'ADD COLUMN category_locked TINYINT(1) NOT NULL DEFAULT 0';
        if (!isset($prodCols['category_source'])) $addProd[] = "ADD COLUMN category_source VARCHAR(20) NOT NULL DEFAULT ''";
        if (!isset($prodCols['source_tags'])) $addProd[] = 'ADD COLUMN source_tags JSON NULL';
        if ($addProd) $db->exec('ALTER TABLE store_products ' . implode(', ', $addProd));
    }

    /**
     * Migra las categorías legacy, crea el árbol canónico y devuelve path => id.
     * No elimina categorías manuales que no pertenezcan a la taxonomía.
     */
    public static function seedDatabase(): array
    {
        $db = Database::instance();
        self::ensureSchema();

        // 1) Renombrar las 7 categorías legacy al padre canónico (o fusionar si ya existe).
        foreach (self::LEGACY_TOP as $old => $new) {
            try {
                $st = $db->prepare("SELECT id FROM store_categories WHERE site_id = @site_id AND slug = ? AND (path IS NULL OR path = '') LIMIT 1");
                $st->execute([$old]);
                $oldId = (int)$st->fetchColumn();
                if (!$oldId) continue;
                $st2 = $db->prepare('SELECT id FROM store_categories WHERE site_id = @site_id AND slug = ? LIMIT 1');
                $st2->execute([$new]);
                $newId = (int)$st2->fetchColumn();
                if ($newId && $newId !== $oldId) {
                    $db->prepare('UPDATE store_products SET category_id = ? WHERE category_id = ? AND site_id = @site_id')->execute([$newId, $oldId]);
                    $db->prepare('DELETE FROM store_categories WHERE id = ? AND site_id = @site_id')->execute([$oldId]);
                } else {
                    $name = self::index()[$new]['name'] ?? $new;
                    $db->prepare("UPDATE store_categories SET name = ?, slug = ?, path = ?, level = 1, source = 'sonika' WHERE id = ? AND site_id = @site_id")
                        ->execute([$name, $new, $new, $oldId]);
                }
            } catch (\Throwable $e) {
            }
        }

        // 2) Insertar/actualizar todos los nodos, en orden padre → hijo.
        $ids = [];
        foreach (self::index() as $key => $node) {
            $parentId = null;
            if ($node['parent'] !== '' && isset($ids[$node['parent']])) $parentId = $ids[$node['parent']];
            $st = $db->prepare('SELECT id FROM store_categories WHERE site_id = @site_id AND path = ? LIMIT 1');
            $st->execute([$key]);
            $id = (int)$st->fetchColumn();
            if (!$id && $node['level'] === 1) {
                $st = $db->prepare("SELECT id FROM store_categories WHERE site_id = @site_id AND slug = ? AND (path IS NULL OR path = '') LIMIT 1");
                $st->execute([$node['slug']]);
                $id = (int)$st->fetchColumn();
            }
            if ($id) {
                $db->prepare("UPDATE store_categories SET name = IF(source = 'manual', name, ?), slug = ?, parent_id = ?, path = ?, level = ?, icon = IF(source = 'manual', icon, ?), source = source, external_key = ?, is_active = 1 WHERE id = ? AND site_id = @site_id")
                    ->execute([$node['name'], $node['slug'], $parentId, $key, $node['level'], $node['icon'], $key, $id]);
            } else {
                $db->prepare("INSERT INTO store_categories (site_id, name, slug, description, image, sort_order, is_active, parent_id, path, level, icon, source, external_key)
                    VALUES (@site_id, ?, ?, '', '', ?, 1, ?, ?, ?, ?, 'sonika', ?)")
                    ->execute([$node['name'], $node['slug'], (int)$node['sort'], $parentId, $key, $node['level'], $node['icon'], $key]);
                $id = (int)$db->lastInsertId();
            }
            $ids[$key] = $id;
        }

        // 3) Limpiar nodos canónicos renombrados (mover productos y borrar el viejo).
        foreach (self::LEGACY_NODE_PATHS as $oldPath => $newPath) {
            if ($oldPath === $newPath || empty($ids[$newPath])) continue;
            try {
                $st = $db->prepare('SELECT id FROM store_categories WHERE site_id = @site_id AND path = ? LIMIT 1');
                $st->execute([$oldPath]);
                $oldId = (int)$st->fetchColumn();
                if (!$oldId || $oldId === (int)$ids[$newPath]) continue;
                $db->prepare('UPDATE store_products SET category_id = ? WHERE category_id = ? AND site_id = @site_id')->execute([(int)$ids[$newPath], $oldId]);
                $db->prepare('DELETE FROM store_categories WHERE id = ? AND site_id = @site_id')->execute([$oldId]);
            } catch (\Throwable $e) {
            }
        }
        return $ids;
    }

    /** Asigna category_id a los productos no bloqueados según el mapa. */
    public static function assignProducts(array $map, bool $onlyMissing = false): int
    {
        $db = Database::instance();
        $catIds = [];
        try {
            foreach ($db->query('SELECT id, path FROM store_categories WHERE site_id = @site_id AND path IS NOT NULL AND path <> \'\'')->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $catIds[(string)$r['path']] = (int)$r['id'];
            }
        } catch (\Throwable $e) {
            return 0;
        }
        if (!$catIds) return 0;
        $sql = 'SELECT id, slug, catalog_handle, category_id, name, product_type, tags FROM store_products WHERE site_id = @site_id AND category_locked = 0';
        if ($onlyMissing) $sql .= ' AND category_id IS NULL';
        $rows = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        $upd = $db->prepare("UPDATE store_products SET category_id = ?, category_source = 'auto' WHERE id = ? AND site_id = @site_id AND category_locked = 0");
        $assigned = 0;
        $products = (array)($map['products'] ?? []);
        foreach ($rows as $row) {
            $handle = trim((string)($row['catalog_handle'] ?? '')) ?: trim((string)($row['slug'] ?? ''));
            if ($handle === '') continue;
            if (isset($products[$handle])) {
                $p = (array)$products[$handle];
            } else {
                // Producto local que no está en catalog.json: se clasifica en vivo
                // para que el espejo de base de datos también quede en la hoja.
                $tags = json_decode((string)($row['tags'] ?? ''), true);
                $tx = self::classify([
                    'handle' => $handle,
                    'title' => (string)($row['name'] ?? ''),
                    'product_type' => (string)($row['product_type'] ?? ''),
                    'tags' => is_array($tags) ? $tags : [],
                ]);
                $p = ['p' => (string)$tx['parent'], 'f' => (string)$tx['family'], 't' => (string)$tx['type']];
            }
            $key = (string)($p['t'] ?? '');
            if ($key === '') $key = (string)($p['f'] ?? '');
            if ($key === '') $key = (string)($p['p'] ?? '');
            if ($key === '' || !isset($catIds[$key])) continue;
            if ((int)$row['category_id'] === $catIds[$key]) continue;
            $upd->execute([$catIds[$key], (int)$row['id']]);
            $assigned++;
        }
        return $assigned;
    }

    /** Aplica todo: esquema + árbol + mapa + asignación de productos. */
    public static function apply(bool $write = true): array
    {
        try { self::ensureSchema(); } catch (\Throwable $e) {
        }
        $products = self::loadCatalog();
        $map = self::buildMap($products, $write);
        $ids = self::seedDatabase();
        $assigned = self::assignProducts($map);
        self::clearCaches();
        return [
            'ok' => true,
            'products' => count($products),
            'categories' => count($ids),
            'assigned' => $assigned,
            'map' => self::mapPath(),
            'summary' => $map['summary'] ?? [],
        ];
    }

    /** Sincroniza solo si el catálogo cambió (mtime), si cambió la versión de taxonomía o si falta el mapa. */
    public static function syncIfNeeded(): array
    {
        $mtime = (int)(@filemtime(self::catalogPath()) ?: 0);
        $stamp = $mtime . ':' . self::VERSION;
        $key = 'sonika_taxonomy_sync_mtime';
        try {
            $st = Database::instance()->prepare('SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = ? LIMIT 1');
            $st->execute([$key]);
            $saved = (string)$st->fetchColumn();
            if ($saved === $stamp && is_file(self::mapPath())) {
                return ['ok' => true, 'cached' => true, 'mtime' => $mtime, 'version' => self::VERSION];
            }
        } catch (\Throwable $e) {
        }
        $res = self::apply(true);
        try {
            Database::instance()->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
                ->execute([$key, $stamp]);
        } catch (\Throwable $e) {
        }
        return $res;
    }

    public static function clearCaches(): int
    {
        self::$map = null;
        self::$classCache = [];
        self::$countCache = [];
        $n = 0;
        $dir = rtrim(sys_get_temp_dir(), '/\\');
        foreach (glob($dir . DIRECTORY_SEPARATOR . 'wwi_mega_*.json') ?: [] as $f) {
            if (@unlink($f)) $n++;
        }
        return $n;
    }

    // ── Reglas internas ───────────────────────────────────────────────

    private static function nodeExists(string $key): bool
    {
        return isset(self::index()[$key]);
    }

    private static function fold(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = strtr($s, self::FOLD);
        return trim(preg_replace('/\s+/u', ' ', $s) ?: '');
    }

    private static function rules(): array
    {
        static $rules = null;
        if ($rules !== null) return $rules;
        $rules = [
            // Cuerdas
            ['~guitarra|guitarras|combo guitarra~u', 'cuerdas', 'guitarras', '', 100],
            ['~\bbajo\b|\bbajos\b|combo bajo|\bbass\b~u', 'cuerdas', 'bajos', '', 100],
            ['~ukelele|ukeleles~u', 'cuerdas', 'ukeleles', '', 100],
            ['~violin|violines~u', 'cuerdas', 'violines', '', 100],
            ['~viola|violas~u', 'cuerdas', 'violas', '', 100],
            ['~violoncello|violonchelo|violonchelos|chelo|cello~u', 'cuerdas', 'violoncellos', '', 100],
            ['~contrabajo|contrabajos~u', 'cuerdas', 'contrabajos', '', 140],
            ['~arpa|arpas~u', 'cuerdas', 'arpas', '', 100],
            ['~cuatro|cuatros|tiple|tiples~u', 'cuerdas', 'cuatros-tiples', '', 100],
            ['~encordado|encordados|juego de cuerdas~u', 'cuerdas', 'encordados', '', 100],
            // Percusión
            ['~bateria|baterias|batería|baterías~u', 'percusion', 'baterias', '', 100],
            ['~redoblante|redoblantes|tarola|tarolas~u', 'percusion', 'redoblantes', '', 100],
            ['~platillo|platillos~u', 'percusion', 'platillos', '', 100],
            ['~conga|congas|tumbadora|tumbadoras|tumba|tumbas~u', 'percusion', 'congas', '', 100],
            ['~bongo|bongos|bongoe|bongoes~u', 'percusion', 'bongos', '', 100],
            ['~cajon|cajones|cajón~u', 'percusion', 'cajones', '', 100],
            ['~timbal|timbales~u', 'percusion', 'timbales', '', 100],
            ['~pandereta|panderetas~u', 'percusion', 'panderetas', '', 100],
            ['~baqueta|baquetas~u', 'percusion', 'baquetas', '', 100],
            ['~parche|parches~u', 'percusion', 'parches', '', 100],
            ['~campana|campanas|jam block|jam blocks~u', 'percusion', 'campanas', '', 100],
            ['~shaker|shakers|guasa|guasá|guiro|güiro|guiros~u', 'percusion', 'shakers', '', 100],
            ['~caja vallenata|caja vallenatas~u', 'percusion', 'cajas-vallenatas', '', 100],
            ['~maraca|maracas~u', 'percusion', 'maracas', '', 100],
            ['~djembe|djembes~u', 'percusion', 'djembes', '', 100],
            ['~tambor|tambores|tambora|tamboras~u', 'percusion', 'tambores', '', 100],
            ['~metalofono|metalófono|xilofono|xilófono~u', 'percusion', 'metalofonos', '', 100],
            ['~marimba|quiribillo|trinquete|palo de lluvia~u', 'percusion', 'otros', '', 100],
            // Vientos
            ['~acordeon|acordeón|acordeones|diatonica|diatónica~u', 'vientos', 'acordeones', '', 100],
            ['~armonica|armónica|armonicas|armónicas~u', 'vientos', 'armonicas', '', 100],
            ['~melodica|melódica|melodicas~u', 'vientos', 'melodicas', '', 100],
            ['~flauta|flautas|quena|ocarina|flauta de pan~u', 'vientos', 'flautas', '', 100],
            ['~saxofon|saxofón|saxo|saxos~u', 'vientos', 'saxofones', '', 100],
            ['~trompeta|trompetas|pistones|piston|pistón~u', 'vientos', 'trompetas', '', 100],
            ['~clarinete|clarinetes~u', 'vientos', 'clarinetes', '', 100],
            ['~gaita|gaitas~u', 'vientos', 'gaitas', '', 100],
            ['~trombon|trombón|trombones~u', 'vientos', 'trombones', '', 100],
            ['~boquilla|boquillas|caña|cañas|cana|canas~u', 'vientos', 'boquillas-canas', '', 90],
            // Teclados
            ['~piano|pianos~u', 'teclados', 'pianos', '', 100],
            ['~teclado|teclados~u', 'teclados', 'teclados', '', 100],
            ['~sintetizador|sintetizadores|synth|analogo|analógico~u', 'teclados', 'sintetizadores', '', 100],
            ['~controlador|controladores|midi|ipad~u', 'teclados', 'controladores', '', 100],
            ['~\bpad\b|\bpads\b~u', 'teclados', 'pads', '', 100],
            // Audio
            ['~amplificador|amplificadores|\bamp\b|\bamps\b~u', 'audio', 'amplificadores', '', 100],
            ['~cabina|cabinas~u', 'audio', 'cabinas', '', 100],
            ['~mixer|mixers|mezclador|mezcladores|consola|consolas~u', 'audio', 'mixers-consolas', '', 100],
            ['~parlante|parlantes|speaker|speakers~u', 'audio', 'parlantes', '', 100],
            ['~monitor|monitores~u', 'audio', 'monitores', '', 100],
            ['~interfaz|interfaces|intefaces|di box|caja directa~u', 'audio', 'interfaces', '', 100],
            ['~audifono|audífono|audifonos|auricular|auriculares~u', 'audio', 'audifonos', '', 100],
            ['~planta de potencia|power amp~u', 'audio', 'plantas', '', 100],
            ['~procesador|procesadores|filtro|filtros|distribuidor|driver|medusa|dispersor|splitter|spliter~u', 'audio', 'procesadores', '', 90],
            ['~tocadiscos|giradiscos|reproductor|reproductores~u', 'audio', 'tocadiscos', '', 100],
            // Micrófonos
            ['~inalambrico|inalámbrico|transmisor|transmisores|receptor|receptores|antena|antenas|diadema~u', 'microfonos', 'inalambricos', '', 100],
            ['~condensador~u', 'microfonos', 'condensador', '', 100],
            ['~microfono|micrófono|microfonos|micrófonos|dinamico|dinámico~u', 'microfonos', 'dinamicos', '', 100],
            // Iluminación
            ['~cabeza movil|cabezas moviles|cabeza móvil|cabezas móviles~u', 'iluminacion', 'cabezas-moviles', '', 100],
            ['~par led|parled~u', 'iluminacion', 'par-led', '', 100],
            ['~truss~u', 'iluminacion', 'truss', '', 100],
            ['~humo|maquina de humo|máquina de humo|liquido de humo~u', 'iluminacion', 'maquinas-humo', '', 100],
            ['~tensor|tensores~u', 'iluminacion', 'tensores', '', 100],
            ['~luz|luces|lampara|lámpara|laser|videobeam~u', 'iluminacion', 'luces', '', 90],
            ['~cortina|cortinas~u', 'iluminacion', 'cortinas', '', 90],
            // Accesorios
            ['~stand|stands|soporte|soportes|silla|sillas|rack|racks|atril|atriles|base|bases|tarima|tarimas|manija|manijas|pata|patas|\bpin\b|\bpins\b|trinche|trinches|esquinera|esquineras|bracket|brackets|clamp|clamps|acople|acoples|suspension|suspensión~u', 'accesorios', 'soportes-estructuras', '', 80],
            ['~correa|correas~u', 'accesorios', 'correas', '', 80],
            ['~forro|forros|funda|fundas|estuche|estuches~u', 'accesorios', 'forros-fundas', '', 80],
            ['~cable|cables|adaptador|adaptadores|conector|conectores|plug|plugs|splitter|spliter|medusa~u', 'accesorios', 'cables-adaptadores', '', 80],
            ['~pick|picks|pua|puas|púa|púas~u', 'accesorios', 'picks', '', 80],
            ['~capodastro|capodastros|capo|capos~u', 'accesorios', 'capodastros', '', 80],
            ['~afinador|afinadores~u', 'accesorios', 'afinadores', '', 80],
            ['~sordina|sordinas~u', 'accesorios', 'sordinas', '', 80],
            ['~slide|slides~u', 'accesorios', 'slides', '', 80],
            ['~repuesto|repuestos~u', 'accesorios', 'repuestos', '', 70],
            ['~kit|kits|\bset\b|\bsets\b~u', 'accesorios', 'kits', '', 60],
            ['~limpiador|limpiadores|polish|poli|limpieza~u', 'accesorios', 'limpiadores', '', 80],
            ['~baquetero|baqueteros|baquerito|vaquerito~u', 'accesorios', 'baqueteros', '', 80],
            ['~cortaviento|cortavientos~u', 'accesorios', 'cortavientos', '', 80],
            ['~clavijero|clavijeros~u', 'accesorios', 'clavijeros', '', 80],
            ['~protector|protectores~u', 'accesorios', 'protectores', '', 70],
            ['~clip|clips~u', 'accesorios', 'clips', '', 70],
            ['~souvenir|souvenirs~u', 'accesorios', 'souvenirs', '', 70],
            ['~pedal|pedales~u', 'accesorios', 'pedales', '', 60],
            ['~colofonia|rosin~u', 'accesorios', 'otros', '', 70],
            // Genéricos (bajo peso)
            ['~\bcuerdas\b~u', 'cuerdas', 'encordados', '', 20],
            ['~\bvientos\b~u', 'vientos', 'otros', '', 20],
            ['~\bpercusion\b|\bpercusión\b~u', 'percusion', 'otros', '', 20],
            ['~\bteclados\b~u', 'teclados', 'otros', '', 20],
            ['~\baudio\b|\bsonido\b|\bgrabacion\b|\bgrabación\b~u', 'audio', 'otros', '', 15],
            ['~\bmicrofonos\b|\bmicros\b~u', 'microfonos', 'dinamicos', '', 20],
            ['~\biluminacion\b|\biluminación\b~u', 'iluminacion', 'otros', '', 20],
            ['~\baccesorio\b|\baccesorios\b~u', 'accesorios', 'otros', '', 15],
        ];
        return $rules;
    }

    private static function contextCandidates(string $title, string $ptype): array
    {
        $h = self::fold($title . ' ' . $ptype);
        if (preg_match('~^amplificador|^amplificadores|^amplificacor|^planta|^plantas|^cabezote|^cabezal~u', $h) && !preg_match('~combo~u', $h)) return [['audio','amplificadores','',3000]];
        if (preg_match('~^combo stand~u', $h)) return [['accesorios','soportes-estructuras','',3000]];
        if (preg_match('~^combo guitarra~u', $h)) return [['cuerdas','guitarras','combos',3000]];
        if (preg_match('~^combo bajo~u', $h)) return [['cuerdas','bajos','combos',3000]];
        if (preg_match('~^combo.*(audio|grab|monitor|interfaz|micro|tarjeta)~u', $h)) return [['audio','interfaces','',3000]];
        if (preg_match('~acordeon|acordeón~u', $h)) return [['vientos','acordeones','',3000]];
        if (preg_match('~^boquilla|^caña|^cana~u', $h)) return [['vientos','boquillas-canas','',3000]];
        if (preg_match('~audifono|audífono|auricular~u', $h)) return [['audio','audifonos','',3000]];
        if (preg_match('~caja directa|di box~u', $h)) return [['audio','interfaces','',3000]];
        if (preg_match('~caja vallenata~u', $h)) return [['percusion','cajas-vallenatas','',3000]];
        if (preg_match('~pedal bombo~u', $h)) return [['percusion','otros','',3000]];
        if (preg_match('~pedal.*(teclado|piano|sostenido|sustain)~u', $h)) return [['accesorios','pedales','',3000]];
        if (preg_match('~pedal.*(efecto|distor|overdrive|delay|reverb|chorus|fuzz|wah)~u', $h)) return [['audio','procesadores','',3000]];
        if (preg_match('~^cuerdas|^encordado|juego de cuerdas~u', $h)) return [['cuerdas','encordados','',3000]];
        if (preg_match('~^(correa|forro|funda|estuche|stand|soporte|silla|rack|atril|base|tarima|manija|pata|pin|trinche|esquinera|bracket|clamp|acople|suspension|cable|adaptador|conector|plug|splitter|spliter|medusa|pick|pua|púa|capodastro|capo|afinador|sordina|slide|repuesto|kit|set|limpiador|polish|poli|limpieza|baquetero|baquerito|vaquerito|cortaviento|clavijero|protector|clip|souvenir|pedal|colofonia|rosin)\b~u', $h)) {
            foreach (self::accessoryContext() as $rx => $fam) {
                if (preg_match($rx, $h)) return [['accesorios',$fam,'',3000]];
            }
            return [['accesorios','otros','',3000]];
        }
        if (preg_match('~^bombillo|^bombilla|^led\b~u', $h) && !preg_match('~par ?led~u', $h)) return [['iluminacion','luces','',3000]];
        if (preg_match('~ejercitador|ejecitador~u', $h)) return [['accesorios','otros','',3000]];
        if (preg_match('~triangulo|triángulo|clave|afuche|castanuela|castañuela|cienpies|cienpiés|guacharaca|guache|shekere|vibra|corona turca|escobilla~u', $h)) return [['percusion','otros','',3000]];
        if (preg_match('~megafono|megáfono~u', $h)) return [['audio','otros','',3000]];
        if (preg_match('~payo|paño|pano|microfibra|string cleaner|slick~u', $h)) return [['accesorios','limpiadores','',3000]];
        if (preg_match('~^campana~u', $h)) return [['percusion','campanas','',3000]];
        if (preg_match('~^controlador.*(luz|luces|dmx|chauvet)~u', $h)) return [['iluminacion','luces','',3000]];
        if (preg_match('~^controlador.*(dj|pioneer|ddj|xdj|denon|numark)~u', $h)) return [['audio','controladores','',3000]];
        if (preg_match('~^barra.*(lighting|dj|luz|luces)~u', $h)) return [['iluminacion','luces','',3000]];
        if (preg_match('~^sistema (in)alambrico|^sistema inalmbrico~u', $h)) return [['microfonos','inalambricos','',3000]];
        if (preg_match('~^marimba|^quiribillo|^trinquete|^palo de lluvia~u', $h)) return [['percusion','otros','',3000]];
        return [];
    }

    private static function accessoryContext(): array
    {
        static $map = null;
        if ($map !== null) return $map;
        return $map = [
            '~^correa|^correas~u' => 'correas',
            '~^forro|^forros|^funda|^fundas|^estuche|^estuches~u' => 'forros-fundas',
            '~^stand|^stands|^soporte|^soportes|^silla|^sillas|^rack|^racks|^atril|^atriles|^base|^bases|^tarima|^tarimas|^manija|^manijas|^pata|^patas|^pin|^pins|^trinche|^trinches|^esquinera|^esquineras|^bracket|^brackets|^clamp|^clamps|^acople|^acoples|^suspension|^suspensión~u' => 'soportes-estructuras',
            '~^cable|^cables|^adaptador|^adaptadores|^conector|^conectores|^plug|^plugs|^splitter|^spliter|^medusa~u' => 'cables-adaptadores',
            '~^pick|^picks|^pua|^puas|^púa|^púas~u' => 'picks',
            '~^capodastro|^capodastros|^capo|^capos~u' => 'capodastros',
            '~^afinador|^afinadores~u' => 'afinadores',
            '~^sordina|^sordinas~u' => 'sordinas',
            '~^slide|^slides~u' => 'slides',
            '~^repuesto|^repuestos~u' => 'repuestos',
            '~^kit|^kits|^set|^sets~u' => 'kits',
            '~^limpiador|^limpiadores|^polish|^poli|^limpieza~u' => 'limpiadores',
            '~^baquetero|^baqueteros|^baquerito|^vaquerito~u' => 'baqueteros',
            '~^cortaviento|^cortavientos~u' => 'cortavientos',
            '~^clavijero|^clavijeros~u' => 'clavijeros',
            '~^protector|^protectores~u' => 'protectores',
            '~^clip|^clips~u' => 'clips',
            '~^souvenir|^souvenirs~u' => 'souvenirs',
            '~^pedal|^pedales~u' => 'pedales',
            '~^colofonia|^rosin~u' => 'otros',
        ];
    }

    private static function subtype(string $parent, string $family, string $hay): string
    {
        $h = self::fold($hay);
        if ($parent === 'cuerdas' && $family === 'guitarras') {
            if (preg_match('~combo~u', $h)) return 'combos';
            if (preg_match('~electroacust|electro-acust|electro acust|acustica amplificada|amplificada~u', $h)) return 'electroacusticas';
            if (preg_match('~clasic|clásic|nylon~u', $h)) return 'clasicas';
            if (preg_match('~electric|\belec\b~u', $h)) return 'electricas';
            if (preg_match('~acustic|acústic|folk|\bac\b~u', $h)) return 'acusticas';
            return 'otras';
        }
        if ($parent === 'cuerdas' && $family === 'bajos') {
            if (preg_match('~combo~u', $h)) return 'combos';
            if (preg_match('~electroacust|electro-acust|electro acust|acustic|acústic~u', $h)) return 'acusticos';
            if (preg_match('~electric|\belec\b~u', $h)) return 'electricos';
            return 'otros';
        }
        if ($parent === 'percusion' && $family === 'baterias') {
            if (preg_match('~electronic|electrónic|electrica|eléctrica~u', $h)) return 'electronicas';
            return 'acusticas';
        }
        return '';
    }
}
