<?php
namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\NavService;

class NavController
{
    private function svc(): NavService
    {
        return new NavService();
    }

    private function requireEditor(): void
    {
        if (!in_array((string)Session::userRole(), ['superadmin', 'admin', 'editor'], true)) {
            Response::error('Sin permiso para gestionar la navegación.', 403);
        }
    }

    public function index(): void
    {
        $this->requireEditor();
        $svc = $this->svc();
        $svc->ensureTables();
        $svc->seedDefaults();
        $menus = [];
        foreach (NavService::LOCATIONS as $loc => $label) {
            $menus[$loc] = ['label' => $label, 'items' => $svc->items($loc, false)];
        }
        Response::json(['ok' => true, 'data' => ['locations' => NavService::LOCATIONS, 'menus' => $menus]]);
    }

    public function saveItem(Request $req): void
    {
        $this->requireEditor();
        $r = $this->svc()->saveItem([
            'id' => (int)$req->input('id', 0),
            'location' => (string)$req->input('location', 'primary'),
            'label' => (string)$req->input('label', ''),
            'url' => (string)$req->input('url', '#'),
            'parent_id' => $req->input('parent_id', null),
            'sort_order' => (int)$req->input('sort_order', 0),
            'is_visible' => (bool)$req->input('is_visible', true),
            'target' => (string)$req->input('target', '_self'),
        ]);
        if (empty($r['ok'])) Response::error($r['message'] ?? 'No se pudo guardar', 422, $r);
        Response::json($r);
    }

    public function deleteItem(Request $req, string $id): void
    {
        $this->requireEditor();
        Response::json($this->svc()->deleteItem((int)$id));
    }

    public function reorder(Request $req): void
    {
        $this->requireEditor();
        $loc = (string)$req->input('location', 'primary');
        $ids = $req->input('ids', []);
        if (!is_array($ids)) $ids = [];
        Response::json($this->svc()->reorder($loc, $ids));
    }

    public function style(): void
    {
        $this->requireEditor();
        $st = Database::instance()->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = 'nav_menu_style' LIMIT 1");
        $st->execute();
        $v = $st->fetchColumn();
        $data = $v ? (json_decode((string)$v, true) ?: []) : [];
        Response::json(['ok' => true, 'data' => array_merge(self::defaultStyle(), is_array($data) ? $data : [])]);
    }

    public function saveStyle(Request $req): void
    {
        $this->requireEditor();
        $in = (array)$req->json();
        $align = in_array((string)($in['align'] ?? ''), ['left', 'center', 'right'], true) ? (string)$in['align'] : 'left';
        $font = trim((string)($in['font'] ?? 'Sora'));
        if ($font === '' || !preg_match('/^[A-Za-z0-9 \-\']+$/', $font)) $font = 'Sora';
        $out = [
            'align' => $align,
            'bg' => self::hex((string)($in['bg'] ?? ''), '#111111'),
            'color' => self::hex((string)($in['color'] ?? ''), '#ffffff'),
            'hover_bg' => self::hex((string)($in['hover_bg'] ?? ''), '#1f1f1f'),
            'accent' => self::hex((string)($in['accent'] ?? ''), '#F20D16'),
            'font' => $font,
            'size' => max(11, min(22, (int)($in['size'] ?? 14))),
        ];
        $db = Database::instance();
        $db->prepare("DELETE FROM settings WHERE site_id = @site_id AND `key` = 'nav_menu_style'")->execute();
        $db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, 'nav_menu_style', ?)")
            ->execute([json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        Response::json(['ok' => true, 'data' => $out]);
    }

    private static function defaultStyle(): array
    {
        return ['align' => 'left', 'bg' => '#111111', 'color' => '#ffffff', 'hover_bg' => '#1f1f1f', 'accent' => '#F20D16', 'font' => 'Sora', 'size' => 14];
    }

    private static function hex(string $v, string $d): string
    {
        $v = trim($v);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtolower($v) : $d;
    }
}
