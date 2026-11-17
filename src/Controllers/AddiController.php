<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AddiService;
use App\Services\StoreService;

class AddiController
{
    public function config(Request $req): void
    {
        try {
            $amount = (int)$req->get('amount_cents', 0);
            Response::json((new AddiService())->config($amount));
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'message' => 'No se pudo consultar Addi.'], 500);
        }
    }

    public function status(Request $req): void
    {
        try {
            $uuid = trim((string)$req->get('uuid', ''));
            if ($uuid === '') {
                Response::json(['ok' => false, 'message' => 'Pedido inválido.'], 422);
                return;
            }
            $order = (new StoreService())->orderByUuid($uuid);
            if (!$order || (string)($order['payment_method'] ?? '') !== 'addi') {
                Response::json(['ok' => false, 'message' => 'Pedido Addi no encontrado.'], 404);
                return;
            }
            $db = Database::instance();
            $stmt = $db->prepare('SELECT addi_order_id, application_id, status, amount_cents, approved_amount_cents, redirect_url, updated_at FROM addi_transactions WHERE site_id = @site_id AND order_uuid = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$uuid]);
            $attempt = $stmt->fetch() ?: [];
            Response::json([
                'ok' => true,
                'payment_status' => (string)($order['payment_status'] ?? 'pending'),
                'addi_status' => (string)($attempt['status'] ?? ''),
                'addi_order_id' => (string)($attempt['addi_order_id'] ?? ''),
                'application_id' => (string)($attempt['application_id'] ?? ''),
                'redirect_url' => (string)($attempt['redirect_url'] ?? ''),
                'updated_at' => (string)($attempt['updated_at'] ?? ''),
            ]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'message' => 'No se pudo consultar el estado de Addi.'], 500);
        }
    }

    public function callback(): void
    {
        $raw = (string)file_get_contents('php://input');
        $user = (string)($_SERVER['PHP_AUTH_USER'] ?? '');
        $password = (string)($_SERVER['PHP_AUTH_PW'] ?? '');
        $authorization = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
        if ($authorization === '' && function_exists('getallheaders')) {
            $headers = getallheaders();
            $authorization = (string)($headers['Authorization'] ?? ($headers['authorization'] ?? ''));
        }
        if ($user === '' && str_starts_with($authorization, 'Basic ')) {
            $decoded = base64_decode(substr($authorization, 6)) ?: '';
            $parts = explode(':', $decoded, 2);
            $user = (string)($parts[0] ?? '');
            $password = (string)($parts[1] ?? '');
        }
        try {
            $result = (new AddiService())->handleCallback($raw, $user, $password);
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => 'No se pudo procesar el callback.']);
            return;
        }
        http_response_code((int)($result['status'] ?? 500));
        header('Content-Type: application/json; charset=utf-8');
        if (!empty($result['ok'])) {
            echo (string)($result['body'] ?? $raw);
        } else {
            echo json_encode(['ok' => false, 'message' => (string)($result['message'] ?? 'Error')], JSON_UNESCAPED_UNICODE);
        }
    }
}
