<?php
namespace App\Controllers\Admin;

use App\Core\Response;
use App\Core\Session;
use App\Services\SearchAnalyticsService;

class SearchAnalyticsController
{
    public function index(): void
    {
        if (!in_array((string)Session::userRole(), ['superadmin', 'admin', 'editor'], true)) {
            Response::error('Sin permiso para ver la analítica de búsquedas.', 403);
        }
        $svc = new SearchAnalyticsService();
        Response::json(['ok' => true, 'data' => [
            'top' => $svc->top(20),
            'zero' => $svc->zeroResults(20),
            'recent' => $svc->recent(50),
        ]]);
    }
}
