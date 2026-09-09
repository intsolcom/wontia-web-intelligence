<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\FactoryService;

class FactoryController
{
    private FactoryService $service;

    public function __construct()
    {
        $this->service = new FactoryService();
    }

    private function requireSuper(): void
    {
        if (Session::userRole() !== 'superadmin') Response::error('Forbidden', 403);
    }

    public function dashboard(): void
    {
        $this->requireSuper();
        Response::json(['ok' => true, 'data' => $this->service->dashboard()]);
    }

    public function sites(): void
    {
        $this->requireSuper();
        Response::json(['ok' => true, 'data' => $this->service->sitesAll()]);
    }

    public function addSite(Request $request): void
    {
        $this->requireSuper();
        $result = $this->service->addSite($request->json());
        $result['ok'] ? Response::success($result, $result['message']) : Response::error($result['message']);
    }

    public function siteStatus(Request $request, $id): void
    {
        $this->requireSuper();
        $status = (string)($request->json()['status'] ?? '');
        $result = $this->service->updateSiteStatus((int)$id, $status);
        $result['ok'] ? Response::success(null, $result['message']) : Response::error($result['message']);
    }

    public function domains(): void
    {
        $this->requireSuper();
        Response::json(['ok' => true, 'data' => $this->service->domainsAll()]);
    }

    public function addDomain(Request $request): void
    {
        $this->requireSuper();
        $result = $this->service->addDomain($request->json());
        $result['ok'] ? Response::success($result, $result['message']) : Response::error($result['message']);
    }

    public function domainStatus(Request $request, $id): void
    {
        $this->requireSuper();
        $status = (string)($request->json()['status'] ?? '');
        $result = $this->service->updateDomainStatus((int)$id, $status);
        $result['ok'] ? Response::success(null, $result['message']) : Response::error($result['message']);
    }

    public function emails(): void
    {
        $this->requireSuper();
        Response::json(['ok' => true, 'data' => $this->service->emailsAll()]);
    }

    public function addEmail(Request $request): void
    {
        $this->requireSuper();
        $result = $this->service->addEmail($request->json());
        $result['ok'] ? Response::success($result, $result['message']) : Response::error($result['message']);
    }

    public function emailStatus(Request $request, $id): void
    {
        $this->requireSuper();
        $status = (string)($request->json()['status'] ?? '');
        $result = $this->service->updateEmailStatus((int)$id, $status);
        $result['ok'] ? Response::success(null, $result['message']) : Response::error($result['message']);
    }

    public function orders(): void
    {
        $this->requireSuper();
        Response::json(['ok' => true, 'data' => $this->service->ordersAll()]);
    }

    public function addOrder(Request $request): void
    {
        $this->requireSuper();
        $result = $this->service->addOrder($request->json());
        $result['ok'] ? Response::success($result, $result['message']) : Response::error($result['message']);
    }

    public function orderStatus(Request $request, $id): void
    {
        $this->requireSuper();
        $status = (string)($request->json()['status'] ?? '');
        $result = $this->service->transitionOrder((int)$id, $status);
        $result['ok'] ? Response::success(null, $result['message']) : Response::error($result['message']);
    }

    public function ledger(): void
    {
        $this->requireSuper();
        Response::json(['ok' => true, 'data' => $this->service->ledgerAll()]);
    }

    public function addLedger(Request $request): void
    {
        $this->requireSuper();
        $result = $this->service->addLedger($request->json());
        $result['ok'] ? Response::success($result, $result['message']) : Response::error($result['message']);
    }

    public function aiUsageBySite(): void
    {
        $this->requireSuper();
        Response::json(['ok' => true, 'data' => $this->service->aiUsageBySite()]);
    }

    public function myPortal(): void
    {
        Response::json(['ok' => true, 'data' => $this->service->myPortal()]);
    }

    public function publicTemplates(): void
    {
        $db = \App\Core\Database::instance();
        $cats = $db->query("SELECT slug, name_es, name_en, icon FROM wwi_template_categories WHERE site_id = @site_id ORDER BY sort_order ASC LIMIT 20")->fetchAll();
        $tpls = $db->query("SELECT t.category_id, c.slug AS category_slug, t.slug, t.name_es, t.name_en, t.status FROM wwi_templates t JOIN wwi_template_categories c ON c.id = t.category_id WHERE t.site_id = @site_id ORDER BY t.sort_order ASC LIMIT 100")->fetchAll();
        Response::json(['ok' => true, 'data' => ['categories' => $cats, 'templates' => $tpls]]);
    }

    public function publicCreateOrder(Request $request): void
    {
        $result = $this->service->createPublicOrder($request->json());
        if ($result['ok']) {
            Response::json(['ok' => true, 'message' => $result['message'], 'data' => [
                'uuid' => $result['uuid'],
                'order_id' => $result['id'],
                'total' => $result['total'],
                'currency' => $result['currency'],
                'status' => $result['status'],
                'plan_name' => $result['plan_name'],
            ]], 201);
        }
        Response::error($result['message'], 400);
    }

    public function publicOrderStatus(Request $request, $uuid): void
    {
        $order = $this->service->findOrderByUuid((string)$uuid);
        if (!$order) Response::error('Order not found', 404);
        Response::json(['ok' => true, 'data' => [
            'uuid' => $order['uuid'],
            'status' => $order['status'],
            'total' => (float)$order['total'],
            'currency' => $order['currency'],
            'plan_id' => (int)$order['plan_id'],
            'domain_name' => $order['domain_name'],
            'created_at' => $order['created_at'],
        ]]);
    }

    public function paymentWebhook(Request $request): void
    {
        $result = $this->service->processPaymentWebhook($request->json());
        if ($result['ok']) Response::json(['ok' => true, 'message' => $result['message']]);
        Response::error($result['message'], $result['status'] ?? 503);
    }

    public function publicPaymentMode(): void
    {
        Response::json(['ok' => true, 'data' => ['provider' => $this->service->paymentConfig()['provider']]]);
    }

    public function publicDummyPay(Request $request, $uuid): void
    {
        $result = $this->service->dummyPay((string)$uuid);
        if ($result['ok']) Response::json(['ok' => true, 'message' => $result['message']]);
        Response::error($result['message'], $result['status'] ?? 503);
    }

    public function publicCreateBrief(Request $request): void
    {
        $result = $this->service->createBrief($request->json());
        if ($result['ok']) Response::json(['ok' => true, 'message' => $result['message'], 'data' => ['id' => $result['id']]], 201);
        Response::error($result['message'], 400);
    }

    public function publicPreviewAttempts(Request $request): void
    {
        Response::json(['ok' => true, 'data' => $this->service->previewAttempts($request->ip())]);
    }

    public function publicCreatePreview(Request $request): void
    {
        $result = $this->service->createPreview((string)($request->json()['prompt'] ?? ''), $request->ip());
        if ($result['ok']) Response::json(['ok' => true, 'message' => 'Preview generándose', 'data' => $result], 201);
        Response::json(['ok' => false, 'message' => $result['message'], 'data' => $result], $result['limit_reached'] ?? false ? 429 : 400);
    }

    public function publicPreviewStatus(Request $request, $uuid): void
    {
        $result = $this->service->previewStatus((string)$uuid);
        if (!$result['ok']) Response::error($result['message'], 404);
        Response::json(['ok' => true, 'data' => $result['data']]);
    }

    public function publicPreviewRender(Request $request, $uuid): void
    {
        $preview = $this->service->previewByUuid((string)$uuid);
        if (!$preview || $preview['status'] !== 'ready') {
            Response::error('Preview not found or not ready', 404);
        }
        $structure = $preview['structure'] ?? [];
        $name = $this->escHtml($structure['business_name'] ?? 'Mi sitio');
        $tagline = $this->escHtml($structure['tagline'] ?? '');
        $nav = $structure['nav'] ?? ['Inicio'];
        $sections = $structure['sections'] ?? [];
        $navHtml = '';
        foreach ($nav as $item) {
            $navHtml .= '<a href="#sec' . md5((string)$item) . '">' . $this->escHtml((string)$item) . '</a>';
        }
        $sectionsHtml = '';
        foreach ($sections as $i => $sec) {
            if (!is_array($sec)) continue;
            $sectionsHtml .= '<section class="pv-sec" id="sec' . md5((string)($sec['title'] ?? $i)) . '">'
                . '<h2>' . $this->escHtml((string)($sec['title'] ?? '')) . '</h2>'
                . ($sec['subtitle'] ? '<p class="pv-sub">' . $this->escHtml((string)$sec['subtitle']) . '</p>' : '')
                . '<div class="pv-content">' . (string)($sec['content'] ?? '') . '</div></section>';
        }
        Response::html('<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>' . $name . ' — Vista previa</title>'
            . '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">'
            . '<style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:Inter,sans-serif;background:#fff;color:#1a1a1e}.pv-band{position:sticky;top:0;z-index:50;background:linear-gradient(120deg,#22d3ee,#8b5cf6);color:#041018;font-size:11px;font-weight:700;text-align:center;padding:6px 10px;letter-spacing:.05em}nav{display:flex;gap:18px;align-items:center;padding:14px 28px;border-bottom:1px solid #eee}nav b{font-weight:800}nav a{color:#555;text-decoration:none;font-size:13px}header.pv-hero{padding:70px 28px 50px;text-align:center;background:#f7f9fd}header.pv-hero h1{font-size:36px;font-weight:800;letter-spacing:-.02em}header.pv-hero p{color:#666;margin-top:10px;font-size:15px}.pv-sec{max-width:760px;margin:0 auto;padding:44px 28px}.pv-sec h2{font-size:22px;font-weight:800;margin-bottom:6px}.pv-sub{color:#666;font-size:13px;margin-bottom:14px}.pv-content{font-size:14px;line-height:1.7;color:#333}.pv-content p{margin-bottom:10px}.pv-content ul{padding-left:20px;margin-bottom:10px}footer{border-top:1px solid #eee;padding:24px;text-align:center;font-size:12px;color:#888}</style></head><body>'
            . '<div class="pv-band">VISTA PREVIA TEMPORAL — expira en 60 minutos · no es tu sitio final</div>'
            . '<nav><b>' . $name . '</b>' . $navHtml . '</nav>'
            . '<header class="pv-hero"><h1>' . $name . '</h1>' . ($tagline ? '<p>' . $tagline . '</p>' : '') . '</header>'
            . $sectionsHtml
            . '<footer>' . $name . ' · Vista previa generada por TIA — Wontia Web Intelligence</footer></body></html>');
    }

    public function publicSuggestDomains(Request $request): void
    {
        $result = $this->service->suggestDomains((string)($request->json()['business'] ?? ''));
        if ($result['ok']) Response::json(['ok' => true, 'data' => $result['domains']]);
        Response::error($result['message'], 400);
    }

    private function escHtml(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    public function briefsList(): void
    {
        $this->requireSuper();
        Response::json(['ok' => true, 'data' => $this->service->briefs()]);
    }

    public function jobsList(): void
    {
        $this->requireSuper();
        Response::json(['ok' => true, 'data' => $this->service->jobsList()]);
    }

    public function jobsRun(): void
    {
        $this->requireSuper();
        Response::json($this->service->runDueJobs(5));
    }

    public function plans(): void
    {
        Response::json(['ok' => true, 'data' => $this->service->plans()]);
    }

    public function savePlan(Request $request, $id = null): void
    {
        $result = $this->service->savePlan($request->json(), $id ? (int)$id : null);
        $result['ok'] ? Response::success($result, $result['message']) : Response::error($result['message']);
    }

    public function deletePlan(Request $request, $id): void
    {
        Response::success($this->service->deletePlan((int)$id));
    }

    public function config(): void
    {
        Response::json(['ok' => true, 'data' => $this->service->config()]);
    }

    public function saveConfig(Request $request): void
    {
        $result = $this->service->saveConfig($request->json());
        $result['ok'] ? Response::success(null, $result['message']) : Response::error($result['message']);
    }

    public function margin(): void
    {
        Response::json(['ok' => true, 'data' => $this->service->marginReport()]);
    }

    public function publicPlans(Request $request): void
    {
        $plans = array_values(array_filter($this->service->plans(), fn($p) => (int)$p['is_active'] === 1));
        Response::json(['ok' => true, 'data' => $plans]);
    }

    public function publicPlan(Request $request, $slug): void
    {
        foreach ($this->service->plans() as $plan) {
            if ($plan['slug'] === $slug && (int)$plan['is_active'] === 1) {
                Response::json(['ok' => true, 'data' => $plan]);
                return;
            }
        }
        Response::error('Plan not found', 404);
    }

    public function ensureTables(): void
    {
        Response::json($this->service->ensureTables());
    }
}
