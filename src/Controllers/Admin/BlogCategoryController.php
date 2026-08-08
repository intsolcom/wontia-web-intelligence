<?php
namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class BlogCategoryController
{
    public function index(): void
    {
        $db = Database::instance();
        $cats = $db->query("SELECT c.*, (SELECT COUNT(*) FROM blog_posts WHERE category_id = c.id) AS post_count FROM blog_categories c WHERE c.site_id = 1 ORDER BY c.sort_order ASC, c.name ASC")->fetchAll();
        Response::json(['ok' => true, 'data' => $cats]);
    }

    public function store(Request $req): void
    {
        $name = $req->input('name');
        if (!$name) Response::error('Name is required', 400);
        $slug = $req->input('slug') ?: \App\Controllers\Admin\PageController::slugify($name);
        $db = Database::instance();
        $db->prepare("INSERT INTO blog_categories (site_id, name, slug, description, color, sort_order) VALUES (1, :name, :slug, :desc, :color, :sort)")
            ->execute(['name' => $name, 'slug' => $slug, 'desc' => $req->input('description', ''), 'color' => $req->input('color', '#BE1341'), 'sort' => (int)$req->input('sort_order', 0)]);
        Response::json(['ok' => true, 'data' => ['id' => $db->lastInsertId()]], 201);
    }

    public function update(Request $req, string $id): void
    {
        $db = Database::instance();
        $fields = ['name', 'slug', 'description', 'color', 'sort_order'];
        $sets = [];
        $params = ['id' => $id];
        foreach ($fields as $f) {
            $val = $req->input($f);
            if ($val !== null) { $sets[] = "$f = :$f"; $params[$f] = $f === 'sort_order' ? (int)$val : $val; }
        }
        if (empty($sets)) Response::error('No fields', 400);
        $db->prepare("UPDATE blog_categories SET " . implode(', ', $sets) . " WHERE id = :id")->execute($params);
        Response::json(['ok' => true]);
    }

    public function destroy(Request $req, string $id): void
    {
        $db = Database::instance();
        $db->prepare("DELETE FROM blog_categories WHERE id = :id")->execute(['id' => $id]);
        Response::json(['ok' => true]);
    }
}
