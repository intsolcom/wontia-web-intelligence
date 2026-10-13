<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Services\BannerStatsService;

class BannerAdminController
{
    public function stats(Request $req): void
    {
        $svc = new BannerStatsService();
        Response::json(['ok' => true, 'data' => ['items' => $svc->list(300), 'summary' => $svc->summary()]]);
    }

    public function clear(Request $req): void
    {
        Response::json((new BannerStatsService())->clear());
    }
}
