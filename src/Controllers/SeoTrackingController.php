<?php
namespace App\Controllers;

use App\Services\SeoTrackingService;

class SeoTrackingController
{
    /** POST /api/v1/public/seo/track — beacon de eventos del navegador (público). */
    public function track($request)
    {
        $body = json_decode((string)file_get_contents('php://input'), true);
        $events = is_array($body) ? ($body['events'] ?? []) : [];
        $n = (new SeoTrackingService())->record(is_array($events) ? $events : []);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => true, 'n' => $n]);
    }

    /** GET /api/v1/admin/seo/tracking?days=7 — agregados (admin). */
    public function stats($request)
    {
        $days = (int)($_GET['days'] ?? 7);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode((new SeoTrackingService())->stats($days), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
