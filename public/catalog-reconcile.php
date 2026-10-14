<?php
/**
 * Robot de conciliación de catálogo (CLI). Escanea el catálogo y guarda el informe
 * de coherencia en el volumen persistente. Lo ejecuta el cron tras cada sync.
 *   php /app/public/catalog-reconcile.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/vendor/autoload.php';

\App\Core\Config::load();
$theme = 'default';
try {
    $theme = (string)(\App\Core\Database::instance()->query("SELECT theme FROM sites WHERE id = @site_id")->fetchColumn() ?: 'default');
} catch (\Throwable $e) {
}
$theme = preg_replace('/[^a-z0-9_-]/i', '', $theme) ?: 'default';
$lib = ROOT_DIR . '/templates/themes/' . $theme . '/lib.php';
$cats = [];
$products = [];
if (is_file($lib)) {
    require_once $lib;
    if (function_exists('sonika_categories')) $cats = sonika_categories();
    if (function_exists('sonika_products')) $products = sonika_products();
}
$svc = new \App\Services\CatalogReconciliationService();
$report = $svc->analyze($cats, $products);
$ok = $svc->save($report);
echo 'RECONCILE ' . ($ok ? 'OK' : 'FAIL') . ': products=' . ($report['stats']['products'] ?? 0)
    . ' sin_categoria=' . ($report['issues']['no_tag']['count'] ?? 0)
    . ' incoherencias=' . ($report['severity']['critical'] ?? 0)
    . ' solapamientos=' . ($report['issues']['overlap']['count'] ?? 0)
    . ' duplicados=' . ($report['issues']['duplicates']['count'] ?? 0) . "\n";
