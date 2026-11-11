<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\TrackingConversionService;

class TrackingConversionController
{
    public function event(Request $req): void
    {
        if (!$this->rateLimit($req, 'event', 120)) return;
        $payload = $req->json();
        if (!is_array($payload) || !$payload) {
            Response::json(['ok' => false, 'message' => 'Evento inválido'], 400);
            return;
        }
        $result = TrackingConversionService::recordClientEvent($payload);
        header('Cache-Control: no-store');
        Response::json($result);
    }

    public function purchase(Request $req, string $uuid): void
    {
        if (!$this->rateLimit($req, 'purchase', 40)) return;
        $uuid = trim($uuid);
        if (!preg_match('/^[a-f0-9-]{36}$/i', $uuid)) {
            Response::json(['ok' => false, 'message' => 'Pedido inválido'], 400);
            return;
        }
        $payload = TrackingConversionService::purchasePayload($uuid);
        header('Cache-Control: no-store');
        Response::json(['ok' => true, 'data' => $payload]);
    }

    private function rateLimit(Request $req, string $scope, int $max): bool
    {
        $dir = (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2)) . '/cache/security';
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        $file = $dir . '/tracking_' . preg_replace('/[^a-z]/', '', $scope) . '_' . md5((string)$req->ip()) . '.json';
        $now = time();
        $data = ['c' => 0, 't' => $now];
        if (is_file($file)) {
            $parsed = json_decode((string)@file_get_contents($file), true);
            if (is_array($parsed) && $now - (int)($parsed['t'] ?? $now) < 60) {
                $data = ['c' => (int)($parsed['c'] ?? 0), 't' => (int)($parsed['t'] ?? $now)];
            }
        }
        if ($data['c'] >= $max) {
            Response::json(['ok' => false, 'message' => 'Demasiadas solicitudes'], 429);
            return false;
        }
        $data['c']++;
        @file_put_contents($file, json_encode($data), LOCK_EX);
        return true;
    }
}
