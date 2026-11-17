<?php
define('ROOT_DIR', '/app');
require '/app/vendor/autoload.php';
\App\Core\Config::load();
$db = \App\Core\Database::instance();
$st = $db->query("SELECT p.slug, p.name, p.product_type, p.category_id, c.path FROM store_products p JOIN store_categories c ON c.id=p.category_id AND c.site_id=p.site_id WHERE p.site_id=@site_id AND c.path IN ('cuerdas','vientos') ORDER BY c.path, p.name");
foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r) echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
$st2 = $db->query("SELECT p.slug, p.name, p.product_type FROM store_products p LEFT JOIN store_categories c ON c.id=p.category_id AND c.site_id=p.site_id WHERE p.site_id=@site_id AND p.category_id IS NULL");
echo '--- sin categoria ---', PHP_EOL;
foreach ($st2->fetchAll(\PDO::FETCH_ASSOC) as $r) echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
