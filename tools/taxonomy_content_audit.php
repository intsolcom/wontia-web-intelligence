<?php
define('ROOT_DIR', '/app');
require '/app/vendor/autoload.php';
\App\Core\Config::load();
$db = \App\Core\Database::instance();
$out = [];
$cols = $db->query('SHOW COLUMNS FROM sections')->fetchAll(\PDO::FETCH_ASSOC);
$out['section_columns'] = array_column($cols, 'Field');
$textCols = [];
foreach ($cols as $c) {
    $t = strtolower((string)$c['Type']);
    if (str_contains($t, 'text') || str_contains($t, 'json')) $textCols[] = (string)$c['Field'];
}
$out['text_columns'] = $textCols;
$where = [];
foreach ($textCols as $col) {
    $where[] = "`$col` LIKE '%/guitarras%'";
    $where[] = "`$col` LIKE '%/pianos%'";
    $where[] = "`$col` LIKE '%/acordeon%'";
    $where[] = "`$col` LIKE '%/amplificadores%'";
    $where[] = "`$col` LIKE '%/microfono-miche%'";
    $where[] = "`$col` LIKE '%/cabeza-movil%'";
    $where[] = "`$col` LIKE '%/cuerdas-guitarras%'";
}
if ($where) {
    try {
        $st = $db->query('SELECT id, page_id, type FROM sections WHERE site_id=@site_id AND (' . implode(' OR ', $where) . ')');
        $out['sections_legacy'] = $st->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\Throwable $e) { $out['sections_legacy'] = $e->getMessage(); }
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
