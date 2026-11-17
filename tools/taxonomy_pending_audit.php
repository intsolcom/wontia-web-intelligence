<?php
define('ROOT_DIR', '/app');
require '/app/vendor/autoload.php';
\App\Core\Config::load();
$db = \App\Core\Database::instance();
$out = [];
// Enlaces legacy en contenido
try {
    $st = $db->query("SELECT id, slug, title FROM pages WHERE site_id=@site_id AND (content LIKE '%/guitarras%' OR content LIKE '%/pianos%' OR content LIKE '%/acordeon%' OR content LIKE '%/amplificadores%' OR content LIKE '%/microfono-miche%' OR content LIKE '%/cabeza-movil%' OR content LIKE '%/cuerdas-guitarras%')");
    $out['pages_legacy_links'] = $st->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Throwable $e) { $out['pages_legacy_links'] = $e->getMessage(); }
try {
    $st = $db->query("SELECT id, slug, title FROM blog_posts WHERE site_id=@site_id AND (content LIKE '%/guitarras%' OR content LIKE '%/pianos%' OR content LIKE '%/acordeon%' OR content LIKE '%/amplificadores%' OR content LIKE '%/microfono-miche%' OR content LIKE '%/cabeza-movil%' OR content LIKE '%/cuerdas-guitarras%')");
    $out['posts_legacy_links'] = $st->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Throwable $e) { $out['posts_legacy_links'] = $e->getMessage(); }
// Ajustes con enlaces legacy
try {
    $st = $db->query("SELECT `key`, LEFT(`value`, 300) AS v FROM settings WHERE site_id=@site_id AND (`value` LIKE '%/guitarras%' OR `value` LIKE '%/pianos%' OR `value` LIKE '%/acordeon%' OR `value` LIKE '%/amplificadores%' OR `value` LIKE '%/microfono-miche%' OR `value` LIKE '%/cabeza-movil%' OR `value` LIKE '%/cuerdas-guitarras%')");
    $out['settings_legacy_links'] = $st->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Throwable $e) { $out['settings_legacy_links'] = $e->getMessage(); }
// Categorías hoja sin productos (directos)
try {
    $st = $db->query("SELECT c.path, c.name, COUNT(p.id) AS n FROM store_categories c LEFT JOIN store_products p ON p.category_id=c.id AND p.site_id=c.site_id WHERE c.site_id=@site_id AND c.level>=2 GROUP BY c.id HAVING n=0 ORDER BY c.path");
    $out['empty_leaves'] = $st->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Throwable $e) { $out['empty_leaves'] = $e->getMessage(); }
// Categorías con menos de 3 productos
try {
    $st = $db->query("SELECT c.path, c.name, COUNT(p.id) AS n FROM store_categories c LEFT JOIN store_products p ON p.category_id=c.id AND p.site_id=c.site_id WHERE c.site_id=@site_id AND c.level>=2 GROUP BY c.id HAVING n<3 ORDER BY n ASC, c.path");
    $out['tiny_leaves'] = $st->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Throwable $e) { $out['tiny_leaves'] = $e->getMessage(); }
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
