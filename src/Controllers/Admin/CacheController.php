<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\CacheService;

class CacheController
{
    private function requireAdmin(): void
    {
        $u = Session::user() ?: [];
        if (!in_array((string)($u['role'] ?? ''), ['superadmin', 'admin'], true)) Response::error('No autorizado', 403);
    }

    public function overview(Request $req): void
    {
        Response::json(['ok' => true, 'data' => (new CacheService())->overview()]);
    }

    public function purge(Request $req): void
    {
        $this->requireAdmin();
        $d = $req->json();
        $r = (new CacheService())->purge((array)($d['options'] ?? []));
        $r['ok'] ? Response::json($r) : Response::error((string)($r['message'] ?? 'Error'), 400);
    }
}
