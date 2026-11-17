<?php
define('ROOT_DIR', '/app');
require '/app/vendor/autoload.php';
\App\Core\Config::load();
$db = \App\Core\Database::instance();
$slug = 'microfono-shure';
$st = $db->prepare('SELECT id, category_id FROM store_products WHERE site_id = @site_id AND slug = ? LIMIT 1');
$st->execute([$slug]);
$orig = $st->fetch(\PDO::FETCH_ASSOC);
if (!$orig) { echo "producto no encontrado\n"; exit(1); }
$st = $db->prepare('SELECT id FROM store_categories WHERE site_id = @site_id AND path = ? LIMIT 1');
$st->execute(['accesorios']);
$accId = (int)$st->fetchColumn();
$db->prepare("UPDATE store_products SET category_id = ?, category_locked = 1, category_source = 'manual' WHERE id = ? AND site_id = @site_id")->execute([$accId, (int)$orig['id']]);
$map = \App\Services\CatalogTaxonomyService::buildMap(null, true);
$row = $map['products'][$slug] ?? [];
echo 'locked_map=' . json_encode(['p'=>$row['p']??null,'f'=>$row['f']??null,'t'=>$row['t']??null,'s'=>$row['s']??null]) . PHP_EOL;
$res = \App\Services\CatalogTaxonomyService::apply(true);
$st = $db->prepare('SELECT category_id, category_locked, category_source FROM store_products WHERE id = ? AND site_id = @site_id');
$st->execute([(int)$orig['id']]);
$after = $st->fetch(\PDO::FETCH_ASSOC);
echo 'after_apply=' . json_encode($after) . ' expected_cat=' . $accId . PHP_EOL;
// Revertir
$db->prepare("UPDATE store_products SET category_id = ?, category_locked = 0, category_source = '' WHERE id = ? AND site_id = @site_id")->execute([(int)$orig['category_id'], (int)$orig['id']]);
$map2 = \App\Services\CatalogTaxonomyService::buildMap(null, true);
$row2 = $map2['products'][$slug] ?? [];
echo 'reverted_map=' . json_encode(['p'=>$row2['p']??null,'f'=>$row2['f']??null,'t'=>$row2['t']??null,'s'=>$row2['s']??null]) . PHP_EOL;
$st = $db->prepare('SELECT category_id, category_locked, category_source FROM store_products WHERE id = ? AND site_id = @site_id');
$st->execute([(int)$orig['id']]);
echo 'reverted_db=' . json_encode($st->fetch(\PDO::FETCH_ASSOC)) . PHP_EOL;
