<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\BrickShowcaseService;

/**
 * Showcase de un brick (capturas). Lectura: cualquier admin autenticado.
 * Escritura: solo superadmin | admin (developer).
 */
class BrickShowcaseController
{
    private function canWrite(): bool
    {
        if (!in_array((string)Session::userRole(), ['superadmin', 'admin'], true)) {
            Response::error('Solo administradores pueden gestionar el showcase del brick.', 403);
            return false;
        }
        return true;
    }

    public function index(Request $req, string $type = ''): void
    {
        Response::json(['ok' => true, 'data' => (new BrickShowcaseService())->list($type)]);
    }

    public function save(Request $req, string $type = ''): void
    {
        if (!$this->canWrite()) return;
        $d = $req->json();
        $d['slug'] = $type;
        $u = Session::user() ?: [];
        $d['created_by'] = (int)($u['id'] ?? 0);
        $r = (new BrickShowcaseService())->add($d);
        if (empty($r['ok'])) Response::error($r['message'] ?? 'Error', 422, $r);
        Response::json($r);
    }

    public function update(Request $req, string $type = '', string $id = ''): void
    {
        if (!$this->canWrite()) return;
        Response::json((new BrickShowcaseService())->update((int)$id, $req->json()));
    }

    public function delete(Request $req, string $type = '', string $id = ''): void
    {
        if (!$this->canWrite()) return;
        Response::json((new BrickShowcaseService())->delete((int)$id));
    }

    public function reorder(Request $req, string $type = ''): void
    {
        if (!$this->canWrite()) return;
        Response::json((new BrickShowcaseService())->reorder($type, (array)$req->input('ids', [])));
    }

    public function cover(Request $req, string $type = '', string $id = ''): void
    {
        if (!$this->canWrite()) return;
        Response::json((new BrickShowcaseService())->setCover($type, (int)$id));
    }
}
