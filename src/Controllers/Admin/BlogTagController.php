<?php
namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class BlogTagController
{
    public function index(): void
    {
        $db = Database::instance();
        $tags = $db->query("SELECT t.*, (SELECT COUNT(*) FROM blog_post_tags WHERE tag_id = t.id) AS post_count FROM blog_tags t WHERE t.site_id = 1 ORDER BY t.name ASC")->fetchAll();
        Response::json(['ok' => true, 'data' => $tags]);
    }

    public function store(Request $req): void
    {
        $name = $req->input('name');
        if (!$name) Response::error('Name is required', 400);
        $slug = $req->input('slug') ?: \App\Controllers\Admin\PageController::slugify($name);
        $db = Database::instance();
        $db->prepare("INSERT INTO blog_tags (site_id, name, slug) VALUES (1, :name, :slug)")->execute(['name' => $name, 'slug' => $slug]);
        Response::json(['ok' => true, 'data' => ['id' => $db->lastInsertId()]], 201);
    }

    public function destroy(Request $req, string $id): void
    {
        $db = Database::instance();
        $db->prepare("DELETE FROM blog_tags WHERE id = :id")->execute(['id' => $id]);
        Response::json(['ok' => true]);
    }
}
