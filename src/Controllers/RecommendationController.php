<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\RecommendationService;

class RecommendationController
{
    public function forCart(Request $req): void
    {
        try {
            $body = $req->json();
            $items = (array)($body['items'] ?? []);
            $limit = (int)($body['limit'] ?? 6);
            $history = (array)($body['history'] ?? []);
            $service = new RecommendationService();
            $data = $service->forCart($items, $limit, $history);
            Response::json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            Response::error('No se pudieron cargar las recomendaciones', 500);
        }
    }
}
