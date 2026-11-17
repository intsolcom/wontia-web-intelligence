<?php
define('ROOT_DIR', '/app');
require '/app/vendor/autoload.php';
\App\Core\Config::load();
$db = \App\Core\Database::instance();
$st = $db->query("SELECT slug, name, status, visibility FROM store_products WHERE site_id=@site_id AND slug IN ('punto-test','producto-de-prueba')");
echo json_encode($st->fetchAll(\PDO::FETCH_ASSOC), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
