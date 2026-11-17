<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\StockNotificationService;

class StockNotificationController
{
    public function subscribe(Request $req): void
    {
        try {
            $body = $req->json();
            $result = (new StockNotificationService())->subscribe(
                (string)($body['product_ref'] ?? ''),
                (string)($body['name'] ?? ''),
                (string)($body['email'] ?? ''),
                !empty($body['consent']),
                $req->ip()
            );
            Response::json($result, 201);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Response::error('No se pudo registrar la solicitud. Intenta de nuevo.', 500);
        }
    }
}
