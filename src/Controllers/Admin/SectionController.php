<?php
namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Widgets\WidgetRegistry;

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

    public function render(Request $req, string $id): void
    {
        $section = $this->sectionInSite((int)$id);
        if (!$section) Response::error('Section not found', 404);
        $html = '';
        if (!empty($section['widget_type']) && WidgetRegistry::get($section['widget_type'])) {
            $config = json_decode($section['config'] ?? '{}', true) ?: [];
            $html = WidgetRegistry::render($section['widget_type'], $config);
        } else {
            $html = (string)($section['content'] ?? '');
        }
        Response::json(['ok' => true, 'data' => ['html' => $html, 'widget_type' => $section['widget_type']]]);
    }

    public function update(Request $req, string $id): void
    {
        $current = $this->sectionInSite((int)$id);
        if (!$current) Response::error('Section not found', 404);
        $user = \App\Core\Session::user() ?: [];
        (new \App\Services\LiveEditorService())->addVersion((int)$id, (int)($user['id'] ?? 0), (string)($user['username'] ?? ''), [
            'title' => $current['title'],
            'subtitle' => $current['subtitle'],
            'content' => $current['content'],
            'config' => json_decode($current['config'] ?? '{}', true) ?: [],
            'is_active' => (int)$current['is_active'],
            'type' => $current['type'],
            'widget_type' => $current['widget_type'],
            'sort_order' => (int)$current['sort_order'],
        ]);
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
        if (isset($params['config'])) {
            $cfg = json_decode((string)$params['config'], true) ?: [];
            $widgetType = (string)($req->input('widget_type') ?? ($current['widget_type'] ?? ''));
            $class = $widgetType ? WidgetRegistry::get($widgetType) : null;
            if ($class) {
                foreach ($class::configSchema() as $field) {
                    $ftype = $field['type'] ?? '';
                    $key = $field['key'] ?? '';
                    if ($key === '') continue;
                    if ($ftype === 'richtext' && isset($cfg[$key])) {
                        $cfg[$key] = \App\Services\LiveEditorService::sanitizeRichHtml((string)$cfg[$key]);
                    } elseif ($ftype === 'repeater' && isset($cfg[$key]) && is_array($cfg[$key])) {
                        $richSub = [];
                        foreach (($field['fields'] ?? []) as $sub) {
                            if (($sub['type'] ?? '') === 'richtext') $richSub[] = $sub['key'] ?? '';
                        }
                        $richSub = array_values(array_filter($richSub));
                        if ($richSub) {
                            foreach ($cfg[$key] as &$item) {
                                if (!is_array($item)) continue;
                                foreach ($richSub as $rk) {
                                    if (isset($item[$rk])) $item[$rk] = \App\Services\LiveEditorService::sanitizeRichHtml((string)$item[$rk]);
                                }
                            }
                            unset($item);
                        }
                    }
                }
                $params['config'] = json_encode($cfg, JSON_UNESCAPED_UNICODE);
            }
        }
        $db->prepare("UPDATE sections SET " . implode(', ', $sets) . " WHERE id = :id AND page_id IN (SELECT id FROM pages WHERE site_id = @site_id)")->execute($params);
        $changed = [];
        foreach (['title', 'subtitle', 'content'] as $f) {
            if (array_key_exists($f, $params) && (string)$params[$f] !== (string)($current[$f] ?? '')) $changed[] = $f;
        }
        if (array_key_exists('is_active', $params) && (int)$params['is_active'] !== (int)$current['is_active']) $changed[] = 'is_active';
        if (array_key_exists('config', $params)) {
            $newCfg = json_decode((string)$params['config'], true) ?: [];
            $oldCfg = json_decode((string)($current['config'] ?? '{}'), true) ?: [];
            foreach ($newCfg as $k => $v) {
                if (!array_key_exists($k, $oldCfg) || json_encode($oldCfg[$k]) !== json_encode($v)) $changed[] = (string)$k;
            }
        }
        if ($changed) {
            $u = \App\Core\Session::user() ?: [];
            (new \App\Services\LiveEditorService())->trackEdits((int)$id, (string)($current['widget_type'] ?? ''), (int)($u['id'] ?? 0), $changed);
        }
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
