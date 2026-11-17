<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\GiftCardService;

class GiftCardController
{
    private function svc(): GiftCardService
    {
        return new GiftCardService();
    }

    public function purchase(Request $req): void
    {
        try {
            $result = $this->svc()->createPurchase($req->json(), $req->ip());
            Response::json(['ok' => true, 'data' => [
                'order_number' => $result['order']['order_number'] ?? '',
                'uuid' => $result['order']['uuid'] ?? '',
                'payment' => $result['payment'] ?? null,
            ]], 201);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            Response::error('No se pudo crear el pedido de la tarjeta de regalo.', 500);
        }
    }

    public function validate(Request $req): void
    {
        try {
            $body = $req->json();
            $code = (string)($body['code'] ?? '');
            $pin = (string)($body['pin'] ?? '');
            $amount = max(0, (int)($body['amount_cents'] ?? 0));
            $result = $this->svc()->validateCode($code, $pin, $amount, true, $req->ip());
            Response::json(['ok' => !empty($result['ok']), 'data' => $result, 'message' => (string)($result['message'] ?? '')], !empty($result['ok']) ? 200 : 422);
        } catch (\Throwable $e) {
            Response::error('No se pudo validar la tarjeta.', 500);
        }
    }

    public function balance(Request $req): void
    {
        try {
            $body = $req->json();
            $result = $this->svc()->balanceInquiry((string)($body['code'] ?? ''), (string)($body['pin'] ?? ''), $req->ip());
            Response::json(['ok' => !empty($result['ok']), 'data' => $result, 'message' => (string)($result['message'] ?? '')], !empty($result['ok']) ? 200 : 422);
        } catch (\Throwable $e) {
            Response::error('No se pudo consultar la tarjeta.', 500);
        }
    }
}
