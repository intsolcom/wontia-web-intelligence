<?php
define('ROOT_DIR', '/app');
require '/app/vendor/autoload.php';
\App\Core\Config::load();
$out = [];
$out['products'] = (new \App\Services\CatalogProductService())->syncIfNeeded();
$out['taxonomy'] = (new \App\Services\CatalogCategoryService())->syncIfNeeded();
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
