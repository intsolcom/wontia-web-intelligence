<?php
define('ROOT_DIR', '/app');
require '/app/vendor/autoload.php';
\App\Core\Config::load();
$db = \App\Core\Database::instance();
$cats = $db->query('SELECT * FROM store_categories WHERE site_id = @site_id ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
$prods = $db->query('SELECT id, slug, category_id FROM store_products WHERE site_id = @site_id ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
$out = ['created_at' => date('c'), 'categories' => $cats, 'products' => $prods];
file_put_contents('/tmp/taxonomy_backup.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo json_encode(['ok' => true, 'categories' => count($cats), 'products' => count($prods), 'file' => '/tmp/taxonomy_backup.json']), PHP_EOL;
