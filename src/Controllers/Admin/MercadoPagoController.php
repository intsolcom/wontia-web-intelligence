<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\MercadoPagoService;

class MercadoPagoController
{
    private function svc(): MercadoPagoService
    {
        return new MercadoPagoService();
    }

    private function authorize(): void
    {
        if (!in_array((string)Session::userRole(), ['superadmin', 'admin'], true)) {
            Response::error('No autorizado para administrar Mercado Pago.', 403);
        }
    }

    public function overview(Request $req): void
    {
        $this->authorize();
        try {
            Response::json(['ok' => true, 'data' => $this->svc()->overview((int)$req->get('limit', 60))]);
        } catch (\Throwable $e) {
            Response::error('No se pudo cargar el panel de Mercado Pago.', 500);
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
            Response::error('No se pudo guardar la configuración de Mercado Pago.', 500);
        }
    }

    public function test(): void
    {
        $this->authorize();
        try {
            Response::json(['ok' => true, 'data' => $this->svc()->testConnection()]);
        } catch (\Throwable $e) {
            Response::error('No se pudo probar la conexión con Mercado Pago.', 500);
        }
    }

    public function diagnosticPreference(Request $req): void
    {
        $this->authorize();
        try {
            $amount = (int)($req->input('amount_cents', 1000000));
            $r = $this->svc()->createDiagnosticPreference($amount);
            Response::json($r, !empty($r['ok']) ? 200 : 422);
        } catch (\Throwable $e) {
            Response::error('No se pudo crear la preferencia de diagnóstico.', 500);
        }
    }

    public function paymentShow(Request $req, string $id): void
    {
        $this->authorize();
        try {
            $payment = $this->svc()->payment((int)$id);
            if (!$payment) Response::error('Pago no encontrado.', 404);
            Response::json(['ok' => true, 'data' => $payment]);
        } catch (\Throwable $e) {
            Response::error('No se pudo cargar el pago.', 500);
        }
    }

    public function paymentSync(Request $req, string $id): void
    {
        $this->authorize();
        try {
            $payment = $this->svc()->payment((int)$id);
            if (!$payment) Response::error('Pago no encontrado.', 404);
            $paymentId = trim((string)($payment['payment_id'] ?? ''));
            if ($paymentId === '') Response::error('El pago todavía no tiene ID de Mercado Pago.', 422);
            $result = $this->svc()->syncPayment($paymentId);
            Response::json($result, !empty($result['ok']) ? 200 : 422);
        } catch (\Throwable $e) {
            Response::error('No se pudo sincronizar el pago.', 500);
        }
    }

    public function refund(Request $req, string $id): void
    {
        $this->authorize();
        try {
            $payment = $this->svc()->payment((int)$id);
            if (!$payment) Response::error('Pago no encontrado.', 404);
            $paymentId = trim((string)($payment['payment_id'] ?? ''));
            if ($paymentId === '') Response::error('El pago no tiene ID de Mercado Pago.', 422);
            $amount = $req->input('amount_cents', null);
            $result = $this->svc()->refund($paymentId, $amount !== null ? (int)$amount : null);
            Response::json($result, !empty($result['ok']) ? 200 : 422);
        } catch (\Throwable $e) {
            Response::error('No se pudo reembolsar el pago.', 500);
        }
    }

    public function paymentMethods(): void
    {
        $this->authorize();
        try {
            Response::json(['ok' => true, 'data' => $this->svc()->paymentMethods()]);
        } catch (\Throwable $e) {
            Response::error('No se pudieron consultar los medios de pago.', 500);
        }
    }

    public function installments(Request $req): void
    {
        $this->authorize();
        try {
            $amount = (int)$req->get('amount_cents', 0);
            $method = trim((string)$req->get('payment_method_id', ''));
            Response::json(['ok' => true, 'data' => $this->svc()->installments($amount, $method)]);
        } catch (\Throwable $e) {
            Response::error('No se pudieron consultar las cuotas.', 500);
        }
    }

    public function oauthStart(): void
    {
        $this->authorize();
        try {
            $result = $this->svc()->oauthStart();
            Response::json($result, !empty($result['ok']) ? 200 : 422);
        } catch (\Throwable $e) {
            Response::error('No se pudo iniciar la conexión OAuth.', 500);
        }
    }

    public function oauthDisconnect(): void
    {
        $this->authorize();
        try {
            Response::json(['ok' => true, 'data' => $this->svc()->oauthDisconnect()]);
        } catch (\Throwable $e) {
            Response::error('No se pudo desconectar la cuenta.', 500);
        }
    }
}
