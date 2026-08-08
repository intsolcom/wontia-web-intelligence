<?php
namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Response;

class SeoController
{
    public function index(): void
    {
        $db = Database::instance();
        $pagesWithoutMeta = $db->query("SELECT COUNT(*) FROM pages WHERE site_id = 1 AND (meta_description IS NULL OR meta_description = '')")->fetchColumn();
        $postsWithoutMeta = $db->query("SELECT COUNT(*) FROM blog_posts WHERE site_id = 1 AND (meta_description IS NULL OR meta_description = '') AND status = 'published'")->fetchColumn();
        $duplicateTitles = $db->query("SELECT meta_title, COUNT(*) c FROM pages WHERE site_id = 1 AND meta_title IS NOT NULL GROUP BY meta_title HAVING c > 1")->fetchAll();
        $longTitles = $db->query("SELECT title, CHAR_LENGTH(meta_title) len FROM pages WHERE site_id = 1 AND CHAR_LENGTH(meta_title) > 60")->fetchAll();

        Response::json(['ok' => true, 'data' => [
            'pages_without_meta' => (int)$pagesWithoutMeta,
            'posts_without_meta' => (int)$postsWithoutMeta,
            'duplicate_titles' => count($duplicateTitles),
            'long_titles' => count($longTitles),
            'score' => max(0, 100 - ((int)$pagesWithoutMeta * 10) - (count($duplicateTitles) * 5) - (count($longTitles) * 3)),
        ]]);
    }

    public function audit(): void
    {
        $db = Database::instance();
        $issues = [];

        $pages = $db->query("SELECT * FROM pages WHERE site_id = 1")->fetchAll();
        foreach ($pages as $p) {
            $titleLen = strlen($p['meta_title'] ?? '');
            if ($titleLen < 10) $issues[] = ['type' => 'short_title', 'page' => $p['title'], 'slug' => $p['slug']];
            if ($titleLen > 60) $issues[] = ['type' => 'long_title', 'page' => $p['title'], 'slug' => $p['slug']];
            if (empty($p['meta_description'])) $issues[] = ['type' => 'missing_description', 'page' => $p['title'], 'slug' => $p['slug']];
        }

        $posts = $db->query("SELECT * FROM blog_posts WHERE site_id = 1 AND status = 'published'")->fetchAll();
        foreach ($posts as $p) {
            if (empty($p['meta_description'])) $issues[] = ['type' => 'post_missing_description', 'post' => $p['title'], 'slug' => $p['slug']];
            if (empty($p['cover_alt'])) $issues[] = ['type' => 'post_missing_alt', 'post' => $p['title'], 'slug' => $p['slug']];
        }

        Response::json(['ok' => true, 'data' => ['total_issues' => count($issues), 'issues' => $issues]]);
    }
}
