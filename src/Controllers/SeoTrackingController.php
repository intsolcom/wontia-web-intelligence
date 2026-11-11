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
        $events = is_array($events) ? $events : [];
        $n = (new SeoTrackingService())->record($events);
        try {
            if (\App\Services\TrackingConsentService::allows('analytics')) {
                $abandon = new \App\Services\CartAbandonmentService();
                foreach ($events as $event) {
                    if (!is_array($event)) continue;
                    $type = (string)($event['type'] ?? '');
                    if (!in_array($type, ['cart_add', 'cart_snapshot', 'checkout_start', 'checkout_abandon'], true)) continue;
                    $items = $event['cart_items'] ?? null;
                    if (!is_array($items) || !$items) continue;
                    $abandon->capture((string)($event['session_id'] ?? ''), $items, [
                        'utm' => is_array($event['utm'] ?? null) ? $event['utm'] : [],
                        'fbclid' => (string)($event['fbclid'] ?? ''),
                        'gclid' => (string)($event['gclid'] ?? ''),
                        'page_url' => (string)($event['page_url'] ?? ''),
                        'device' => (string)($event['device'] ?? ''),
                    ]);
                }
            }
        } catch (\Throwable $e) {
        }
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
