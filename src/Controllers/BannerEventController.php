<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\BannerStatsService;

/** Endpoint público (beacon) para métricas del brick Banner. */
class BannerEventController
{
    public function event(Request $req): void
    {
        $uid = (string)$req->input('uid', '');
        if ($uid === '') {
            Response::json(['ok' => false]);
            return;
        }
        Response::json((new BannerStatsService())->track($uid, (string)$req->input('type', 'impression'), (string)$req->input('variant', 'a')));
    }
}
