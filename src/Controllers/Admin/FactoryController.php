<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Services\FactoryService;

class FactoryController
{
    private FactoryService $service;

    public function __construct()
    {
        $this->service = new FactoryService();
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
