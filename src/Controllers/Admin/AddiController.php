<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AddiService;

class AddiController
{
    private function svc(): AddiService
    {
        return new AddiService();
    }

    private function authorize(): void
    {
        if (!in_array((string)Session::userRole(), ['superadmin', 'admin'], true)) {
            Response::error('No autorizado para administrar Addi.', 403);
        }
    }

    public function overview(): void
    {
        $this->authorize();
        try {
            Response::json(['ok' => true, 'data' => $this->svc()->overview()]);
        } catch (\Throwable $e) {
            Response::error('No se pudo cargar Addi: ' . $e->getMessage(), 500);
        }
    }

    public function settingsSave(Request $req): void
    {
        $this->authorize();
        try {
            Response::json(['ok' => true, 'data' => $this->svc()->saveSettings($req->json())]);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Response::error('No se pudo guardar Addi: ' . $e->getMessage(), 500);
        }
    }

    public function test(): void
    {
        $this->authorize();
        try {
            Response::json(['ok' => true, 'data' => $this->svc()->testConnection()]);
        } catch (\Throwable $e) {
            Response::error('No se pudo probar Addi: ' . $e->getMessage(), 500);
        }
    }

    public function cancel(Request $req, string $uuid): void
    {
        $this->authorize();
        try {
            $amount = $req->input('amount_cents', null);
            $result = $this->svc()->cancel($uuid, $amount !== null ? (int)$amount : null);
            Response::json($result, !empty($result['ok']) ? 200 : 422);
        } catch (\Throwable $e) {
            Response::error('No se pudo cancelar en Addi: ' . $e->getMessage(), 500);
        }
    }
}
