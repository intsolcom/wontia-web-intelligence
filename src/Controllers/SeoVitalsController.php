<?php
namespace App\Controllers;

use App\Services\SeoVitalsService;

class SeoVitalsController
{
    /** POST /api/v1/public/seo/vitals — beacon de Core Web Vitals (público, con consentimiento). */
    public function record($request)
    {
        $body = json_decode((string)file_get_contents('php://input'), true);
        $items = is_array($body) ? ($body['items'] ?? []) : [];
        $items = is_array($items) ? $items : [];
        $n = (new SeoVitalsService())->record($items);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => true, 'n' => $n]);
    }
}
