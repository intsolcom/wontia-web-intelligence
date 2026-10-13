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

    public function config(Request $req): void
    {
        $svc = new \App\Services\BannerService();
        Response::json(['ok' => true, 'data' => $svc->getGlobal(), 'defaults' => $svc->defaults(), 'schema' => \App\Widgets\BannerWidget::configSchema()]);
    }

    public function configSave(Request $req): void
    {
        Response::json((new \App\Services\BannerService())->saveGlobal($req->json()));
    }

    public function templates(Request $req): void
    {
        Response::json(['ok' => true, 'data' => (new \App\Services\BannerTemplateService())->list()]);
    }

    public function templateSave(Request $req): void
    {
        $r = (new \App\Services\BannerTemplateService())->save((string)$req->input('name', ''));
        if (empty($r['ok'])) Response::error($r['message'] ?? 'Error', 422, $r);
        Response::json($r);
    }

    public function templateApply(Request $req, string $id = ''): void
    {
        Response::json((new \App\Services\BannerTemplateService())->apply((int)$id));
    }

    public function templateDelete(Request $req, string $id = ''): void
    {
        Response::json((new \App\Services\BannerTemplateService())->delete((int)$id));
    }
}
