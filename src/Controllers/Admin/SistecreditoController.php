<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\SistecreditoService;

class SistecreditoController
{
    private function svc(): SistecreditoService
    {
        return new SistecreditoService();
    }

    private function authorize(): void
    {
        if (!in_array((string)Session::userRole(), ['superadmin', 'admin'], true)) {
            Response::error('No autorizado para administrar Sistecrédito.', 403);
        }
    }

    public function overview(Request $req): void
    {
        $this->authorize();
        try {
            Response::json(['ok' => true, 'data' => $this->svc()->overview(20)]);
        } catch (\Throwable $e) {
            Response::error('No se pudo cargar el panel de Sistecrédito.', 500);
        }
    }

    public function settingsSave(Request $req): void
    {
        $this->authorize();
        try {
            Response::json(['ok' => true, 'data' => $this->svc()->saveSettings($req->json())]);
        } catch (\Throwable $e) {
            Response::error('No se pudo guardar la configuración de Sistecrédito.', 500);
        }
    }

    public function intents(Request $req): void
    {
        $this->authorize();
        try {
            Response::json(['ok' => true, 'data' => $this->svc()->intents((int)$req->get('limit', 100), (string)$req->get('status', ''))]);
        } catch (\Throwable $e) {
            Response::error('No se pudieron cargar las intenciones.', 500);
        }
    }

    public function intentShow(Request $req, string $id): void
    {
        $this->authorize();
        try {
            $intent = $this->svc()->intent((int)$id);
            if (!$intent) Response::error('Intención no encontrada.', 404);
            Response::json(['ok' => true, 'data' => $intent]);
        } catch (\Throwable $e) {
            Response::error('No se pudo cargar la intención.', 500);
        }
    }
}
