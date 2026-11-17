<?php
define('ROOT_DIR', '/app');
require '/app/vendor/autoload.php';
\App\Core\Config::load();
require '/app/templates/themes/sonika/lib.php';
$cats = sonika_categories();
$products = sonika_products();
$svc = new \App\Services\CatalogReconciliationService();
$report = $svc->analyze($cats, $products);
file_put_contents('/tmp/taxonomy_reconcile.json', json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
echo json_encode([
    'stats' => $report['stats'] ?? [],
    'severity' => $report['severity'] ?? [],
    'unmapped' => $report['issues']['mismatch']['count'] ?? 0,
    'unmapped_samples' => array_slice((array)($report['issues']['mismatch']['samples'] ?? []), 0, 15),
    'empty_categories' => $report['issues']['empty_categories'] ?? [],
    'no_tag' => $report['issues']['no_tag']['count'] ?? 0,
    'coverage' => $report['coverage'] ?? [],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
