<?php
define('ROOT_DIR', '/app');
require '/app/vendor/autoload.php';
\App\Core\Config::load();
$db = \App\Core\Database::instance();
$slugs = ['guitarras','acordeon','pianos','amplificadores','microfono-miche','cabeza-movil','cuerdas-guitarras'];
$in = implode(',', array_fill(0, count($slugs), '?'));
$st = $db->prepare("SELECT id, slug, name, path, level FROM store_categories WHERE site_id = @site_id AND (path IS NULL OR path = '') AND slug IN ($in)");
$st->execute($slugs);
$rows = $st->fetchAll(\PDO::FETCH_ASSOC);
echo "legacy rows: " . count($rows) . PHP_EOL;
foreach ($rows as $r) echo json_encode($r, JSON_UNESCAPED_UNICODE) . PHP_EOL;
$ids = array_map(fn($r) => (int)$r['id'], $rows);
if ($ids) {
    $in2 = implode(',', array_fill(0, count($ids), '?'));
    $st2 = $db->prepare("SELECT COUNT(*) FROM store_products WHERE site_id = @site_id AND category_id IN ($in2)");
    $st2->execute($ids);
    $n = (int)$st2->fetchColumn();
    echo "products pointing to legacy rows: $n" . PHP_EOL;
    if ($n === 0) {
        $db->prepare("DELETE FROM store_categories WHERE site_id = @site_id AND id IN ($in2)")->execute($ids);
        echo "deleted: " . count($ids) . PHP_EOL;
    }
}
$total = (int)$db->query('SELECT COUNT(*) FROM store_categories WHERE site_id = @site_id')->fetchColumn();
echo "categories total: $total" . PHP_EOL;
