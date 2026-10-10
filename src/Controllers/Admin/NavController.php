<?php
namespace App\Controllers\Admin;

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
}
