<?php
define('ROOT_DIR', '/app');
require '/app/vendor/autoload.php';
\App\Core\Config::load();
$db = \App\Core\Database::instance();
$cols = $db->query('SHOW COLUMNS FROM sections')->fetchAll(\PDO::FETCH_ASSOC);
$textCols = [];
foreach ($cols as $c) {
    $t = strtolower((string)$c['Type']);
    if (str_contains($t, 'text') || str_contains($t, 'json')) $textCols[] = (string)$c['Field'];
}
$where = [];
foreach ($textCols as $col) {
    $where[] = "s.`$col` LIKE '%/guitarras%'";
    $where[] = "s.`$col` LIKE '%/pianos%'";
    $where[] = "s.`$col` LIKE '%/acordeon%'";
    $where[] = "s.`$col` LIKE '%/amplificadores%'";
    $where[] = "s.`$col` LIKE '%/microfono-miche%'";
    $where[] = "s.`$col` LIKE '%/cabeza-movil%'";
    $where[] = "s.`$col` LIKE '%/cuerdas-guitarras%'";
}
$st = $db->query('SELECT s.id, s.page_id, s.type, p.slug AS page_slug, p.title AS page_title FROM sections s JOIN pages p ON p.id = s.page_id AND p.site_id = @site_id WHERE (' . implode(' OR ', $where) . ')');
echo json_encode($st->fetchAll(\PDO::FETCH_ASSOC), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
