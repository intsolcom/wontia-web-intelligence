<?php
namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class SectionController
{
    private function pageInSite(int $pageId): bool
    {
        $stmt = Database::instance()->prepare("SELECT id FROM pages WHERE id = :id AND site_id = @site_id");
        $stmt->execute(['id' => $pageId]);
        return (bool)$stmt->fetch();
    }

    private function sectionInSite(int $id): ?array
    {
        $stmt = Database::instance()->prepare("SELECT s.* FROM sections s JOIN pages p ON p.id = s.page_id WHERE s.id = :id AND p.site_id = @site_id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function index(Request $req, string $pageId): void
    {
        if (!$this->pageInSite((int)$pageId)) Response::error('Page not found', 404);
        $db = Database::instance();
        $sections = $db->prepare("SELECT * FROM sections WHERE page_id = :pid ORDER BY sort_order ASC");
        $sections->execute(['pid' => $pageId]);
        Response::json(['ok' => true, 'data' => $sections->fetchAll()]);
    }

    public function show(Request $req, string $id): void
    {
        $section = $this->sectionInSite((int)$id);
        if (!$section) Response::error('Section not found', 404);
        Response::json(['ok' => true, 'data' => $section]);
    }

    public function store(Request $req, string $pageId): void
    {
        $type = $req->input('type');
        if (!$type) Response::error('Type is required', 400);
        if (!$this->pageInSite((int)$pageId)) Response::error('Page not found', 404);

        $db = Database::instance();
        $maxSort = $db->prepare("SELECT COALESCE(MAX(sort_order), -1) FROM sections WHERE page_id = :pid");
        $maxSort->execute(['pid' => $pageId]);
        $nextSort = (int)$maxSort->fetchColumn() + 1;

        $db->prepare("INSERT INTO sections (page_id, type, widget_type, title, subtitle, content, image, config, sort_order, is_active) VALUES (:pid, :type, :widget_type, :title, :subtitle, :content, :image, :config, :sort_order, :is_active)")
            ->execute([
                'pid' => $pageId,
                'type' => $type,
                'widget_type' => $req->input('widget_type', null),
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
        if (!$this->sectionInSite((int)$id)) Response::error('Section not found', 404);
        $db = Database::instance();
        $fields = ['type', 'widget_type', 'title', 'subtitle', 'content', 'image', 'config', 'sort_order', 'is_active'];
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
        $db->prepare("UPDATE sections SET " . implode(', ', $sets) . " WHERE id = :id AND page_id IN (SELECT id FROM pages WHERE site_id = @site_id)")->execute($params);
        Response::json(['ok' => true]);
    }

    public function destroy(Request $req, string $id): void
    {
        if (!$this->sectionInSite((int)$id)) Response::error('Section not found', 404);
        $db = Database::instance();
        $db->prepare("DELETE FROM sections WHERE id = :id AND page_id IN (SELECT id FROM pages WHERE site_id = @site_id)")->execute(['id' => $id]);
        Response::json(['ok' => true]);
    }

    public function reorder(Request $req): void
    {
        $items = $req->input('items');
        if (!$items || !is_array($items)) Response::error('items array required', 400);
        $db = Database::instance();
        $stmt = $db->prepare("UPDATE sections SET sort_order = :sort WHERE id = :id AND page_id IN (SELECT id FROM pages WHERE site_id = @site_id)");
        foreach ($items as $item) {
            $stmt->execute(['sort' => (int)$item['sort_order'], 'id' => $item['id']]);
        }
        Response::json(['ok' => true]);
    }
}
