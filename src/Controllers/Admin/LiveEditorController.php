<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\LiveEditorService;

class LiveEditorController
{
    private function user(): array
    {
        return Session::user() ?: [];
    }

    public function commentsList(Request $req, string $sectionId): void
    {
        Response::json(['ok' => true, 'data' => (new LiveEditorService())->comments((int)$sectionId)]);
    }

    public function commentAdd(Request $req, string $sectionId): void
    {
        $u = $this->user();
        $r = (new LiveEditorService())->addComment((int)$sectionId, (int)($u['id'] ?? 0), (string)($u['username'] ?? ''), (string)$req->input('body', ''));
        $r['ok'] ? Response::success($r, $r['message']) : Response::error($r['message'], 400);
    }

    public function commentStatus(Request $req, string $id): void
    {
        $r = (new LiveEditorService())->commentStatus((int)$id, (string)$req->input('status', 'open'));
        $r['ok'] ? Response::success(null, 'ok') : Response::error('No encontrado', 404);
    }

    public function commentDelete(Request $req, string $id): void
    {
        $r = (new LiveEditorService())->deleteComment((int)$id);
        $r['ok'] ? Response::success(null, 'Eliminado') : Response::error('No encontrado', 404);
    }

    public function versionsList(Request $req, string $sectionId): void
    {
        Response::json(['ok' => true, 'data' => (new LiveEditorService())->versions((int)$sectionId)]);
    }

    public function versionRestore(Request $req, string $id): void
    {
        $r = (new LiveEditorService())->restoreVersion((int)$id);
        $r['ok'] ? Response::success(null, $r['message']) : Response::error($r['message'], 400);
    }

    public function presencePing(Request $req): void
    {
        $u = $this->user();
        $sid = $req->input('section_id');
        $pid = $req->input('page_id');
        $cx = $req->input('cursor_x');
        $cy = $req->input('cursor_y');
        (new LiveEditorService())->presencePing(
            (int)($u['id'] ?? 0),
            (string)($u['username'] ?? ''),
            $sid ? (int)$sid : null,
            $pid ? (int)$pid : null,
            $cx !== null ? (float)$cx : null,
            $cy !== null ? (float)$cy : null
        );
        Response::success(null, 'ok');
    }

    public function presenceList(Request $req): void
    {
        $u = $this->user();
        Response::json(['ok' => true, 'data' => (new LiveEditorService())->activePresence((int)($u['id'] ?? 0))]);
    }

    public function variantsList(Request $req, string $sectionId): void
    {
        Response::json(['ok' => true, 'data' => (new LiveEditorService())->variants((int)$sectionId)]);
    }

    public function variantSave(Request $req, string $sectionId): void
    {
        $id = $req->input('id');
        $config = $req->input('config', []);
        if (!is_array($config)) $config = [];
        $r = (new LiveEditorService())->variantSave(
            (int)$sectionId,
            $id ? (int)$id : null,
            (string)$req->input('name', 'B'),
            $config,
            (int)$req->input('weight', 50),
            (int)$req->input('is_active', 1)
        );
        $r['ok'] ? Response::success($r, $r['message']) : Response::error($r['message'], 400);
    }

    public function variantDelete(Request $req, string $id): void
    {
        $r = (new LiveEditorService())->variantDelete((int)$id);
        $r['ok'] ? Response::success(null, 'Variante eliminada') : Response::error('No encontrada', 404);
    }
}
