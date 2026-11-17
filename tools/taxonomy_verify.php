<?php
define('ROOT_DIR', '/app');
require '/app/vendor/autoload.php';
\App\Core\Config::load();
$out = [];
$out['import'] = (new \App\Services\CatalogProductService())->sync();
$db = \App\Core\Database::instance();
$out['categories_total'] = (int)$db->query('SELECT COUNT(*) FROM store_categories WHERE site_id = @site_id')->fetchColumn();
$out['categories_top'] = (int)$db->query('SELECT COUNT(*) FROM store_categories WHERE site_id = @site_id AND level = 1')->fetchColumn();
$out['categories_dup_path'] = $db->query('SELECT path, COUNT(*) c FROM store_categories WHERE site_id = @site_id AND path IS NOT NULL GROUP BY path HAVING c > 1')->fetchAll(\PDO::FETCH_ASSOC);
$out['products_total'] = (int)$db->query('SELECT COUNT(*) FROM store_products WHERE site_id = @site_id')->fetchColumn();
$out['products_no_category'] = (int)$db->query('SELECT COUNT(*) FROM store_products WHERE site_id = @site_id AND category_id IS NULL')->fetchColumn();
$out['products_locked'] = (int)$db->query('SELECT COUNT(*) FROM store_products WHERE site_id = @site_id AND category_locked = 1')->fetchColumn();
$out['products_source_tags'] = (int)$db->query('SELECT COUNT(*) FROM store_products WHERE site_id = @site_id AND source_tags IS NOT NULL')->fetchColumn();
$out['top_distribution'] = $db->query("SELECT c.path, c.name, COUNT(p.id) AS n FROM store_categories c LEFT JOIN store_products p ON p.category_id = c.id AND p.site_id = c.site_id WHERE c.site_id = @site_id AND c.level = 1 GROUP BY c.id ORDER BY n DESC")->fetchAll(\PDO::FETCH_ASSOC);
$out['sample_leaves'] = $db->query("SELECT c.path, COUNT(p.id) AS n FROM store_categories c LEFT JOIN store_products p ON p.category_id = c.id AND p.site_id = c.site_id WHERE c.site_id = @site_id AND c.level = 3 GROUP BY c.id ORDER BY n DESC LIMIT 20")->fetchAll(\PDO::FETCH_ASSOC);
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
