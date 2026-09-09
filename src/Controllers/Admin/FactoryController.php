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
        $rows = $db->query("SELECT slug, name_es, name_en, icon FROM wwi_template_categories WHERE site_id = @site_id ORDER BY sort_order ASC LIMIT 20")->fetchAll();
        Response::json(['ok' => true, 'data' => $rows]);
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
