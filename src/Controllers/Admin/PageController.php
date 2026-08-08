<?php
namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class PageController
{
    public function index(): void
    {
        $db = Database::instance();
        $pages = $db->query("SELECT p.*, (SELECT COUNT(*) FROM sections WHERE page_id = p.id) AS section_count FROM pages p WHERE p.site_id = 1 ORDER BY p.sort_order ASC, p.created_at DESC")->fetchAll();
        Response::json(['ok' => true, 'data' => $pages]);
    }

    public function show(Request $req, string $id): void
    {
        $db = Database::instance();
        $page = $db->prepare("SELECT * FROM pages WHERE id = :id AND site_id = 1");
        $page->execute(['id' => $id]);
        $p = $page->fetch();
        if (!$p) Response::error('Page not found', 404);
        $sections = $db->prepare("SELECT * FROM sections WHERE page_id = :pid ORDER BY sort_order ASC");
        $sections->execute(['pid' => $id]);
        $p['sections'] = $sections->fetchAll();
        Response::json(['ok' => true, 'data' => $p]);
    }

    public function store(Request $req): void
    {
        $title = $req->input('title');
        $slug = $req->input('slug') ?: self::slugify($title);
        if (!$title) Response::error('Title is required', 400);

        $db = Database::instance();
        $db->prepare("INSERT INTO pages (site_id, title, slug, template, meta_title, meta_description, status, sort_order) VALUES (1, :title, :slug, :template, :meta_title, :meta_description, :status, :sort_order)")
            ->execute([
                'title' => $title,
                'slug' => $slug,
                'template' => $req->input('template', 'default'),
                'meta_title' => $req->input('meta_title', $title),
                'meta_description' => $req->input('meta_description', ''),
                'status' => $req->input('status', 'draft'),
                'sort_order' => (int)$req->input('sort_order', 0),
            ]);
        $id = $db->lastInsertId();
        Response::json(['ok' => true, 'data' => ['id' => $id]], 201);
    }

    public function update(Request $req, string $id): void
    {
        $db = Database::instance();
        $existing = $db->prepare("SELECT id FROM pages WHERE id = :id AND site_id = 1");
        $existing->execute(['id' => $id]);
        if (!$existing->fetch()) Response::error('Page not found', 404);

        $fields = ['title', 'slug', 'template', 'meta_title', 'meta_description', 'meta_keywords', 'og_image', 'canonical_url', 'no_index', 'status', 'sort_order'];
        $sets = [];
        $params = ['id' => $id];
        foreach ($fields as $f) {
            $val = $req->input($f);
            if ($val !== null) {
                $sets[] = "$f = :$f";
                $params[$f] = $f === 'no_index' || $f === 'sort_order' ? (int)$val : $val;
            }
        }
        if (empty($sets)) Response::error('No fields to update', 400);

        $db->prepare("UPDATE pages SET " . implode(', ', $sets) . " WHERE id = :id")->execute($params);
        Response::json(['ok' => true]);
    }

    public function destroy(Request $req, string $id): void
    {
        $db = Database::instance();
        $db->prepare("DELETE FROM pages WHERE id = :id AND site_id = 1")->execute(['id' => $id]);
        Response::json(['ok' => true]);
    }

    public static function slugify(string $text): string
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = preg_replace('/[^a-zA-Z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s_]+/', '-', trim($text));
        return strtolower($text);
    }
}
