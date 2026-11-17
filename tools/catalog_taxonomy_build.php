<?php
/**
 * Construye el mapa de taxonomía SONIKA y, opcionalmente, lo aplica a la base.
 *
 * Uso:
 *   php tools/catalog_taxonomy_build.php            # solo genera el mapa
 *   php tools/catalog_taxonomy_build.php --apply    # mapa + store_categories + category_id
 *   php tools/catalog_taxonomy_build.php --dry      # resumen sin escribir nada
 */
if (!defined('ROOT_DIR')) define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/vendor/autoload.php';
\App\Core\Config::load();

$apply = in_array('--apply', $argv, true);
$dry = in_array('--dry', $argv, true);

try {
    (new \App\Services\StoreService())->ensureTables();
} catch (\Throwable $e) {
    fwrite(STDERR, 'No se pudieron asegurar las tablas: ' . $e->getMessage() . PHP_EOL);
}

$svc = new \App\Services\CatalogTaxonomyService();
$result = $apply
    ? $svc->apply(true)
    : ($dry ? $svc->buildMap(null, false) : $svc->buildMap(null, true));

if ($dry) {
    $summary = $result['summary'] ?? [];
} else {
    $summary = $apply ? ($result['summary'] ?? []) : ($result['summary'] ?? []);
}

$out = [
    'ok' => true,
    'mode' => $apply ? 'apply' : ($dry ? 'dry' : 'build'),
    'taxonomy_version' => \App\Services\CatalogTaxonomyService::VERSION,
    'map' => \App\Services\CatalogTaxonomyService::mapPath(),
    'result' => $apply ? $result : null,
    'summary' => $summary,
    'categories' => count(\App\Services\CatalogTaxonomyService::index()),
    'top_level' => array_map(static fn($c) => ['key' => $c['key'], 'name' => $c['name'], 'children' => count(\App\Services\CatalogTaxonomyService::children($c['key']))], \App\Services\CatalogTaxonomyService::topLevel()),
];

if ($dry && isset($result['products'])) {
    $out['sample'] = array_slice($result['products'], 0, 10, true);
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
