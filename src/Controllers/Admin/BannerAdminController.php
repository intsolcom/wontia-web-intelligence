<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Services\BannerService;
use App\Services\BannerStatsService;
use App\Services\BannerTemplateService;

class BannerAdminController
{
    private function svc(): BannerService
    {
        return new BannerService();
    }

    // ── Biblioteca de banners ──

    public function listBanners(Request $req): void
    {
        Response::json(['ok' => true, 'data' => $this->svc()->list()]);
    }

    public function createBanner(Request $req): void
    {
        Response::json($this->svc()->create((string)$req->input('name', '')));
    }

    public function showBanner(Request $req, string $id = ''): void
    {
        $b = $this->svc()->get((int)$id);
        if (!$b) {
            Response::error('Banner no encontrado', 404);
            return;
        }
        Response::json(['ok' => true, 'banner' => $b, 'schema' => \App\Widgets\BannerWidget::configSchema(), 'defaults' => $this->svc()->defaults()]);
    }

    public function saveBanner(Request $req, string $id = ''): void
    {
        $d = $req->json();
        $r = $this->svc()->save((int)$id, (string)($d['name'] ?? ''), (array)($d['config'] ?? []));
        if (empty($r['ok'])) Response::error($r['message'] ?? 'Error', 422, $r);
        Response::json($r);
    }

    public function deleteBanner(Request $req, string $id = ''): void
    {
        Response::json($this->svc()->delete((int)$id));
    }

    public function toggleBanner(Request $req, string $id = ''): void
    {
        Response::json($this->svc()->setEnabled((int)$id, (bool)$req->input('enabled', true)));
    }

    public function headerBanner(Request $req, string $id = ''): void
    {
        Response::json($this->svc()->setHeader((int)$id));
    }

    public function duplicateBanner(Request $req, string $id = ''): void
    {
        Response::json($this->svc()->duplicate((int)$id));
    }

    // ── Métricas ──

    public function stats(Request $req): void
    {
        $svc = new BannerStatsService();
        Response::json(['ok' => true, 'data' => ['items' => $svc->list(300), 'summary' => $svc->summary()]]);
    }

    public function clear(Request $req): void
    {
        Response::json((new BannerStatsService())->clear());
    }

    // ── Plantillas de barra superior ──

    public function templates(Request $req): void
    {
        Response::json(['ok' => true, 'data' => (new BannerTemplateService())->list()]);
    }

    public function templateSave(Request $req): void
    {
        $r = (new BannerTemplateService())->save((string)$req->input('name', ''));
        if (empty($r['ok'])) Response::error($r['message'] ?? 'Error', 422, $r);
        Response::json($r);
    }

    public function templateApply(Request $req, string $id = ''): void
    {
        Response::json((new BannerTemplateService())->apply((int)$id));
    }

    public function templateDelete(Request $req, string $id = ''): void
    {
        Response::json((new BannerTemplateService())->delete((int)$id));
    }
}
