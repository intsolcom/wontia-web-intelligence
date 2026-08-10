<?php
use App\Core\Config;
use App\Core\Database;

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

$url = Config::get('APP_URL', 'https://wontia.intsolcom.com');

echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

try {
    $db = Database::instance();

    $pages = $db->query("SELECT slug, updated_at FROM pages WHERE site_id = @site_id AND status = 'published' AND no_index = 0 ORDER BY sort_order ASC")->fetchAll();
    foreach ($pages as $p) {
        $lastmod = date('Y-m-d', strtotime($p['updated_at']));
        $loc = htmlspecialchars($url . '/' . $p['slug']);
        echo "  <url><loc>$loc</loc><lastmod>$lastmod</lastmod><changefreq>weekly</changefreq><priority>0.8</priority></url>\n";
    }

    $posts = $db->query("SELECT slug, updated_at FROM blog_posts WHERE site_id = @site_id AND status = 'published' ORDER BY published_at DESC")->fetchAll();
    foreach ($posts as $p) {
        $lastmod = date('Y-m-d', strtotime($p['updated_at']));
        $loc = htmlspecialchars($url . '/blog/' . $p['slug']);
        echo "  <url><loc>$loc</loc><lastmod>$lastmod</lastmod><changefreq>monthly</changefreq><priority>0.6</priority></url>\n";
    }
} catch (\Exception $e) {}

echo '</urlset>';
