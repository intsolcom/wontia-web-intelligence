<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\MercadoPagoService;
use App\Services\StoreService;

/**
 * Endpoints públicos de Mercado Pago: webhook, retorno OAuth, estado del
 * pedido y creación de pagos con tarjeta tokenizada (Checkout API).
 */
class MercadoPagoController
{
    public function config(): void
    {
        try {
            Response::json(['ok' => true, 'data' => (new MercadoPagoService())->publicConfig()]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'message' => 'Mercado Pago no está disponible.'], 500);
        }
    }

    public function webhook(Request $req): void
    {
        $raw = (string)file_get_contents('php://input');
        $payload = $req->json();
        if (!$payload) $payload = $_POST ?: [];
        $headers = $this->headers();
        $queryId = (string)($_GET['data_id'] ?? ($_GET['data.id'] ?? ($req->get('data_id', '') ?: '')));
        $queryType = (string)($_GET['type'] ?? ($_GET['topic'] ?? ($req->get('type', '') ?: '')));
        if ($queryType !== '' && empty($payload['type']) && empty($payload['topic'])) $payload['type'] = $queryType;
        try {
            $result = (new MercadoPagoService())->handleWebhook(is_array($payload) ? $payload : [], $headers, $raw, $queryId);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'message' => 'No se pudo procesar la notificación.'], 500);
            return;
        }
        Response::json([
            'ok' => !empty($result['ok']),
            'message' => (string)($result['message'] ?? ''),
            'payment_status' => $result['payment_status'] ?? null,
        ], (int)($result['status'] ?? 200));
    }

    public function oauthCallback(Request $req): void
    {
        $code = trim((string)$req->get('code', ''));
        $state = trim((string)$req->get('state', ''));
        $error = trim((string)$req->get('error', ''));
        $admin = '/admin.php?panel=mercadopago';
        if ($error !== '') {
            header('Location: ' . $admin . '&mp_oauth=error&mp_msg=' . rawurlencode($error));
            return;
        }
        try {
            $result = (new MercadoPagoService())->oauthCallback($code, $state);
        } catch (\Throwable $e) {
            $result = ['ok' => false, 'message' => 'No se pudo completar la conexión con Mercado Pago.'];
        }
        if (!empty($result['ok'])) {
            header('Location: ' . $admin . '&mp_oauth=ok');
            return;
        }
        header('Location: ' . $admin . '&mp_oauth=error&mp_msg=' . rawurlencode((string)($result['message'] ?? 'Error OAuth')));
    }

    public function status(Request $req): void
    {
        $uuid = trim((string)$req->get('uuid', ''));
        if ($uuid === '') {
            Response::error('Pedido requerido', 400);
            return;
        }
        header('Cache-Control: no-store');
        try {
            Response::json((new MercadoPagoService())->statusForOrder($uuid));
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'message' => 'No se pudo consultar el pago.'], 500);
        }
    }

    /**
     * Crea (o recrea) la preferencia de Checkout Pro para un pedido pendiente.
     */
    public function preference(Request $req): void
    {
        $uuid = trim((string)$req->input('uuid', ''));
        $order = $this->order($uuid);
        if (!$order) {
            Response::error('Pedido no encontrado', 404);
            return;
        }
        if ((string)($order['payment_method'] ?? '') !== 'mercadopago') {
            Response::error('El pedido no usa Mercado Pago', 422);
            return;
        }
        if ((string)($order['payment_status'] ?? '') === 'paid') {
            Response::json(['ok' => true, 'data' => ['status' => 'paid', 'redirect_url' => '']]);
            return;
        }
        try {
            $result = (new MercadoPagoService())->createPreference($order);
        } catch (\Throwable $e) {
            $result = ['ok' => false, 'message' => 'No se pudo crear la preferencia de pago.'];
        }
        if (empty($result['ok'])) {
            Response::json(['ok' => false, 'message' => (string)($result['message'] ?? 'No se pudo iniciar el pago.')], 422);
            return;
        }
        Response::json(['ok' => true, 'data' => $result]);
    }

    /**
     * Procesa un pago con tarjeta ya tokenizada en el navegador (Checkout API).
     */
    public function pay(Request $req): void
    {
        $body = $req->json();
        $uuid = trim((string)($body['uuid'] ?? $req->input('uuid', '')));
        $order = $this->order($uuid);
        if (!$order) {
            Response::error('Pedido no encontrado', 404);
            return;
        }
        if ((string)($order['payment_method'] ?? '') !== 'mercadopago') {
            Response::error('El pedido no usa Mercado Pago', 422);
            return;
        }
        if ((string)($order['payment_status'] ?? '') === 'paid') {
            Response::json(['ok' => true, 'data' => ['status' => 'paid', 'approved' => true, 'message' => 'Este pedido ya está pagado.']]);
            return;
        }
        $formData = (array)($body['formData'] ?? $body);
        $token = trim((string)($formData['token'] ?? ''));
        if ($token === '') {
            Response::error('Falta el token de la tarjeta. Vuelve a intentarlo.', 422);
            return;
        }
        try {
            $result = (new MercadoPagoService())->createPayment($order, $formData);
        } catch (\Throwable $e) {
            $result = ['ok' => false, 'message' => 'No se pudo procesar el pago con Mercado Pago.'];
        }
        Response::json([
            'ok' => !empty($result['ok']),
            'message' => (string)($result['message'] ?? ''),
            'data' => $result,
        ], !empty($result['ok']) ? 200 : 422);
    }

    private function order(string $uuid): ?array
    {
        if ($uuid === '' || !preg_match('/^[a-f0-9-]{36}$/i', $uuid)) return null;
        $svc = new StoreService();
        if (!$svc->ready()) return null;
        return $svc->orderByUuid($uuid);
    }

    private function headers(): array
    {
        $out = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with((string)$key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr((string)$key, 5)));
                $out[$name] = (string)$value;
            }
        }
        if (function_exists('getallheaders')) {
            foreach ((array)getallheaders() as $key => $value) {
                $out[strtolower((string)$key)] = (string)$value;
            }
        }
        return $out;
    }
}
