<?php
namespace App\Controllers;

use App\Core\Response;
use App\Services\OrderOperationsService;

class OrderOperationsController
{
    /**
     * Webhook de Wontia AIP para confirmación/reconfirmación de pedidos.
     * Firma HMAC SHA-256 en X-Wontia-Signature (o X-AIP-Signature).
     */
    public function confirmation(): void
    {
        $raw = (string)file_get_contents('php://input');
        $signature = (string)($_SERVER['HTTP_X_WONTIA_SIGNATURE'] ?? ($_SERVER['HTTP_X_AIP_SIGNATURE'] ?? ''));
        try {
            $result = (new OrderOperationsService())->ingestConfirmation($raw, $signature);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'message' => 'No se pudo procesar la confirmación AIP.'], 500);
            return;
        }
        Response::json($result, (int)($result['status'] ?? (!empty($result['ok']) ? 200 : 422)));
    }
}
