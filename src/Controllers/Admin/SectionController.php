<?php
namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class SectionController
{
    public function index(Request $req, string $pageId): void
    {
        $db = Database::instance();
        $sections = $db->prepare("SELECT * FROM sections WHERE page_id = :pid ORDER BY sort_order ASC");
        $sections->execute(['pid' => $pageId]);
        Response::json(['ok' => true, 'data' => $sections->fetchAll()]);
    }

    public function store(Request $req, string $pageId): void
    {
        $type = $req->input('type');
        if (!$type) Response::error('Type is required', 400);

        $db = Database::instance();
        $maxSort = $db->prepare("SELECT COALESCE(MAX(sort_order), -1) FROM sections WHERE page_id = :pid");
        $maxSort->execute(['pid' => $pageId]);
        $nextSort = (int)$maxSort->fetchColumn() + 1;

        $db->prepare("INSERT INTO sections (page_id, type, title, subtitle, content, image, config, sort_order, is_active) VALUES (:pid, :type, :title, :subtitle, :content, :image, :config, :sort_order, :is_active)")
            ->execute([
                'pid' => $pageId,
                'type' => $type,
                'title' => $req->input('title', ''),
                'subtitle' => $req->input('subtitle', ''),
                'content' => $req->input('content', ''),
                'image' => $req->input('image', ''),
                'config' => json_encode($req->input('config', [])),
                'sort_order' => $nextSort,
                'is_active' => (int)$req->input('is_active', 1),
            ]);
        $id = $db->lastInsertId();
        Response::json(['ok' => true, 'data' => ['id' => $id]], 201);
    }

    public function update(Request $req, string $id): void
    {
        $db = Database::instance();
        $fields = ['type', 'title', 'subtitle', 'content', 'image', 'config', 'sort_order', 'is_active'];
        $sets = [];
        $params = ['id' => $id];
        foreach ($fields as $f) {
            $val = $req->input($f);
            if ($val !== null) {
                $sets[] = "$f = :$f";
                $params[$f] = $f === 'config' ? json_encode($val) : ($f === 'is_active' || $f === 'sort_order' ? (int)$val : $val);
            }
        }
        if (empty($sets)) Response::error('No fields to update', 400);
        $db->prepare("UPDATE sections SET " . implode(', ', $sets) . " WHERE id = :id")->execute($params);
        Response::json(['ok' => true]);
    }

    public function destroy(Request $req, string $id): void
    {
        $db = Database::instance();
        $db->prepare("DELETE FROM sections WHERE id = :id")->execute(['id' => $id]);
        Response::json(['ok' => true]);
    }

    public function reorder(Request $req): void
    {
        $items = $req->input('items');
        if (!$items || !is_array($items)) Response::error('items array required', 400);
        $db = Database::instance();
        $stmt = $db->prepare("UPDATE sections SET sort_order = :sort WHERE id = :id");
        foreach ($items as $item) {
            $stmt->execute(['sort' => (int)$item['sort_order'], 'id' => $item['id']]);
        }
        Response::json(['ok' => true]);
    }
}
