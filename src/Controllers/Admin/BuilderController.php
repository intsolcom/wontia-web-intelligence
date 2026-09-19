<?php
namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\BuilderService;
use App\Widgets\WidgetRegistry;

class BuilderController
{
    private function svc(): BuilderService
    {
        return new BuilderService();
    }

    private function requirePage(Request $req, string $id): int
    {
        $pageId = (int)$id;
        $stmt = Database::instance()->prepare("SELECT id FROM pages WHERE id = :id AND site_id = @site_id");
        $stmt->execute(['id' => $pageId]);
        if (!$stmt->fetchColumn()) Response::error('Pagina no encontrada', 404);
        return $pageId;
    }

    public function ensureTables(): void
    {
        Response::json(['ok' => true, 'data' => $this->svc()->ensureTables()]);
    }

    public function tree(Request $req): void
    {
        $pageId = $this->requirePage($req, (string)$req->get('page_id', '0'));
        Response::json(['ok' => true, 'data' => $this->svc()->tree($pageId), 'ready' => $this->svc()->ready()]);
    }

    public function palette(): void
    {
        $atomic = [
            ['type' => 'text', 'label' => 'Texto', 'icon' => 'T'],
            ['type' => 'image', 'label' => 'Imagen', 'icon' => '▣'],
            ['type' => 'button', 'label' => 'Boton', 'icon' => '➜'],
            ['type' => 'video', 'label' => 'Video', 'icon' => '▶'],
            ['type' => 'divider', 'label' => 'Separador', 'icon' => '—'],
            ['type' => 'spacer', 'label' => 'Espacio', 'icon' => '␣'],
        ];
        $bricks = [];
        foreach (WidgetRegistry::all() as $id => $meta) {
            $bricks[] = ['slug' => $id, 'label' => $meta['name'] ?? $id, 'category' => $meta['category'] ?? 'general', 'icon' => $meta['icon'] ?? 'cube'];
        }
        Response::json(['ok' => true, 'data' => ['atomic' => $atomic, 'bricks' => $bricks]]);
    }

    public function createRow(Request $req): void
    {
        $d = $req->json();
        $pageId = $this->requirePage($req, (string)($d['page_id'] ?? '0'));
        $rowId = $this->svc()->createRow($pageId, (int)($d['position'] ?? -1), (array)($d['layout'] ?? []));
        $cols = max(1, min(4, (int)($d['columns'] ?? 1)));
        $span = (int)floor(12 / $cols);
        for ($i = 0; $i < $cols; $i++) $this->svc()->createColumn($rowId, $i === $cols - 1 ? 12 - $span * ($cols - 1) : $span);
        Response::json(['ok' => true, 'data' => ['row_id' => $rowId]], 201);
    }

    public function updateNode(Request $req, string $type, string $id): void
    {
        if (!in_array($type, ['row', 'column', 'slot'], true)) Response::error('Tipo invalido', 400);
        $this->svc()->updateNode($type, (int)$id, $req->json());
        Response::json(['ok' => true]);
    }

    public function deleteNode(Request $req, string $type, string $id): void
    {
        if (!in_array($type, ['row', 'column', 'slot', 'block'], true)) Response::error('Tipo invalido', 400);
        $pageId = (int)$req->get('page_id', 0);
        if (!$pageId) Response::error('page_id requerido', 400);
        $this->requirePage($req, (string)$pageId);
        $this->svc()->deleteNode($type, (int)$id, $pageId);
        Response::json(['ok' => true]);
    }

    public function createColumn(Request $req): void
    {
        $d = $req->json();
        $rowId = (int)($d['row_id'] ?? 0);
        $colId = $this->svc()->createColumn($rowId, (int)($d['span'] ?? 12), (int)($d['position'] ?? -1), (array)($d['layout'] ?? []));
        $this->svc()->normalizeRow($rowId);
        Response::json(['ok' => true, 'data' => ['column_id' => $colId]], 201);
    }

    public function createBlock(Request $req): void
    {
        $d = $req->json();
        try {
            $id = $this->svc()->createBlock((int)($d['slot_id'] ?? 0), (string)($d['type'] ?? 'text'), $d['brick_slug'] ?? null, (int)($d['position'] ?? -1), (array)($d['props'] ?? []), isset($d['section_id']) ? (int)$d['section_id'] : null);
            Response::json(['ok' => true, 'data' => ['block_id' => $id]], 201);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function updateBlock(Request $req, string $id): void
    {
        $this->svc()->updateBlock((int)$id, $req->json());
        Response::json(['ok' => true]);
    }

    public function duplicateBlock(Request $req, string $id): void
    {
        try {
            Response::json(['ok' => true, 'data' => ['block_id' => $this->svc()->duplicateBlock((int)$id)]]);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function moveBlock(Request $req, string $id): void
    {
        $d = $req->json();
        try {
            $this->svc()->moveBlock((int)$id, (int)($d['slot_id'] ?? 0), (int)($d['position'] ?? 0));
            Response::json(['ok' => true]);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function reorder(Request $req, string $type): void
    {
        $d = $req->json();
        $this->svc()->reorder($type, (array)($d['items'] ?? []));
        Response::json(['ok' => true]);
    }

    public function convert(Request $req): void
    {
        $d = $req->json();
        $pageId = $this->requirePage($req, (string)($d['page_id'] ?? '0'));
        try {
            Response::json(['ok' => true, 'data' => $this->svc()->convertPage($pageId)]);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 409);
        }
    }

    public function publish(Request $req): void
    {
        $d = $req->json();
        $pageId = $this->requirePage($req, (string)($d['page_id'] ?? '0'));
        $rev = $this->svc()->snapshot($pageId, (string)($d['label'] ?? 'Publicacion'));
        Response::json(['ok' => true, 'data' => ['revision_id' => $rev]]);
    }

    public function revisions(Request $req): void
    {
        $pageId = $this->requirePage($req, (string)$req->get('page_id', '0'));
        Response::json(['ok' => true, 'data' => $this->svc()->revisions($pageId)]);
    }

    public function restoreRevision(Request $req, string $id): void
    {
        try {
            Response::json(['ok' => true, 'data' => $this->svc()->restoreRevision((int)$id)]);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function trash(Request $req): void
    {
        $pageId = $this->requirePage($req, (string)$req->get('page_id', '0'));
        Response::json(['ok' => true, 'data' => $this->svc()->trash($pageId)]);
    }

    public function restoreTrash(Request $req, string $id): void
    {
        try {
            Response::json(['ok' => true, 'data' => $this->svc()->restoreTrash((int)$id)]);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function render(Request $req): void
    {
        $pageId = $this->requirePage($req, (string)$req->get('page_id', '0'));
        Response::json(['ok' => true, 'data' => ['html' => $this->svc()->renderPage($pageId)]]);
    }
}
