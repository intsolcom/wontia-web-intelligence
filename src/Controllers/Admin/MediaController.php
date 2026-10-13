<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Services\MediaService;

class MediaController
{
    private function svc(): MediaService
    {
        return new MediaService();
    }

    public function index(Request $req): void
    {
        $svc = $this->svc();
        try {
            $svc->syncBrandAssets();
        } catch (\Throwable $e) {
        }
        $items = $svc->listMedia([
            'folder_id' => $req->get('folder_id', ''),
            'kind' => $req->get('kind', ''),
            'q' => $req->get('q', ''),
            'trash' => (int)$req->get('trash', 0),
            'brand' => (int)$req->get('brand', 0),
            'limit' => (int)$req->get('limit', 60),
        ]);
        Response::json(['ok' => true, 'data' => $items, 'total' => count($items)]);
    }

    public function upload(Request $req): void
    {
        $r = $this->svc()->upload($_FILES['file'] ?? [], (string)$req->input('alt_text', ''), ((int)$req->input('folder_id', 0)) ?: null);
        if (empty($r['ok'])) Response::error($r['message'] ?? 'Error', 400, $r);
        Response::json($r, 201);
    }

    public function destroy(Request $req, string $id): void
    {
        Response::json($this->svc()->trash((int)$id));
    }

    public function purge(Request $req, string $id): void
    {
        Response::json($this->svc()->purge((int)$id));
    }

    public function restore(Request $req, string $id): void
    {
        Response::json($this->svc()->restore((int)$id));
    }

    public function move(Request $req, string $id): void
    {
        Response::json($this->svc()->move((int)$id, ((int)$req->input('folder_id', 0)) ?: null));
    }

    public function saveAlt(Request $req, string $id): void
    {
        Response::json($this->svc()->saveAlt((int)$id, (string)$req->input('alt_text', '')));
    }

    public function folders(Request $req): void
    {
        Response::json(['ok' => true, 'data' => $this->svc()->folders()]);
    }

    public function folderSave(Request $req, ?string $id = null): void
    {
        $r = $this->svc()->folderSave($req->json(), $id ? (int)$id : null);
        if (empty($r['ok'])) Response::error($r['message'] ?? 'Error', 422, $r);
        Response::json($r);
    }

    public function folderDelete(Request $req, string $id): void
    {
        Response::json($this->svc()->folderDelete((int)$id));
    }

    public function stats(Request $req): void
    {
        Response::json(['ok' => true, 'data' => $this->svc()->stats()]);
    }

    public function usage(Request $req, string $id): void
    {
        Response::json($this->svc()->usage((int)$id));
    }

    public function syncBrand(Request $req): void
    {
        Response::json(['ok' => true, 'added' => $this->svc()->syncBrandAssets()]);
    }
}
