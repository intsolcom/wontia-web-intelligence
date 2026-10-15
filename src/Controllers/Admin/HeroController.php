<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\HeroService;

/**
 * HeroController — SONIKA HERO BUILDER (admin). Rol superadmin|admin|editor.
 */
class HeroController
{
    private function svc(): HeroService
    {
        if (!in_array((string)Session::userRole(), ['superadmin', 'admin', 'editor'], true)) {
            Response::error('Sin permiso para el Hero Builder.', 403);
        }
        return new HeroService();
    }

    public function index(): void
    {
        $s = $this->svc();
        Response::json(['ok' => true, 'data' => ['slides' => $s->all(), 'types' => $s->contentTypes()]]);
    }

    public function save(Request $req): void
    {
        $s = $this->svc();
        $d = (array)$req->json();
        $u = Session::user();
        $d['actor'] = (string)($u['username'] ?? $u['name'] ?? 'admin');
        Response::json($s->save($d));
    }

    public function delete(Request $req, string $id): void { Response::json($this->svc()->delete((int)$id)); }
    public function duplicate(Request $req, string $id): void { Response::json($this->svc()->duplicate((int)$id)); }

    public function reorder(Request $req): void
    {
        $ids = (array)(((array)$req->json())['ids'] ?? []);
        Response::json($this->svc()->reorder($ids));
    }

    public function publish(Request $req, string $id): void { Response::json($this->svc()->publish((int)$id)); }
    public function versions(Request $req, string $id): void { Response::json(['ok' => true, 'data' => $this->svc()->versions((int)$id)]); }
    public function version(Request $req, string $id): void
    {
        $v = $this->svc()->version((int)$id);
        if (!$v) Response::error('Versión no encontrada', 404);
        Response::json(['ok' => true, 'data' => $v]);
    }
    public function restore(Request $req, string $id): void { Response::json($this->svc()->restore((int)$id)); }
}
