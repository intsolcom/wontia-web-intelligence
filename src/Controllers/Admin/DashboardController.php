<?php
namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class DashboardController
{
    public function index(): void
    {
        $db = Database::instance();
        $totalPages = $db->query("SELECT COUNT(*) FROM pages WHERE site_id = 1")->fetchColumn();
        $totalPosts = $db->query("SELECT COUNT(*) FROM blog_posts WHERE site_id = 1")->fetchColumn();
        $publishedPosts = $db->query("SELECT COUNT(*) FROM blog_posts WHERE site_id = 1 AND status = 'published'")->fetchColumn();
        $draftPosts = $totalPosts - $publishedPosts;
        $totalMedia = $db->query("SELECT COUNT(*) FROM media WHERE site_id = 1")->fetchColumn();
        $totalViews = $db->query("SELECT COUNT(*) FROM analytics_views WHERE site_id = 1")->fetchColumn();

        $recentPages = $db->query("SELECT id, title, slug, status, updated_at FROM pages WHERE site_id = 1 ORDER BY updated_at DESC LIMIT 5")->fetchAll();
        $recentPosts = $db->query("SELECT id, title, slug, status, views, updated_at FROM blog_posts WHERE site_id = 1 ORDER BY updated_at DESC LIMIT 5")->fetchAll();

        Response::json([
            'ok' => true,
            'data' => [
                'stats' => [
                    'total_pages' => (int)$totalPages,
                    'total_posts' => (int)$totalPosts,
                    'published_posts' => (int)$publishedPosts,
                    'draft_posts' => (int)$draftPosts,
                    'total_media' => (int)$totalMedia,
                    'total_views' => (int)$totalViews,
                ],
                'recent_pages' => $recentPages,
                'recent_posts' => $recentPosts,
            ]
        ]);
    }
}
