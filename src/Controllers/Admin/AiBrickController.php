<?php
namespace App\Controllers\Admin;

use App\Core\AiBrick\AiBrickService;
use App\Core\AiBrick\AiRouter;
use App\Core\Request;
use App\Core\Response;

class AiBrickController
{
    private AiBrickService $service;
    private AiRouter $router;

    public function __construct()
    {
        $this->service = new AiBrickService();
        $this->router = new AiRouter($this->service);
    }

    public function overview(Request $request): void
    {
        $range = (string)$request->get('range', '30d');
        if (!in_array($range, ['today', '7d', '30d', 'all'], true)) $range = '30d';
        Response::json(['ok' => true, 'data' => $this->service->overview($range)]);
    }

    public function suggestions(): void
    {
        Response::json(['ok' => true, 'data' => $this->service->suggestions()]);
    }

    public function providers(): void
    {
        Response::json(['ok' => true, 'data' => $this->service->providers()]);
    }

    public function saveProvider(Request $request, $id = null): void
    {
        $result = $this->service->saveProvider($request->json(), $id ? (int)$id : null);
        $result['ok'] ? Response::success($result, $result['message']) : Response::error($result['message']);
    }

    public function deleteProvider(Request $request, $id): void
    {
        Response::success($this->service->deleteProvider((int)$id));
    }

    public function models(Request $request): void
    {
        $providerId = $request->get('provider_id');
        Response::json(['ok' => true, 'data' => $this->service->models($providerId ? (int)$providerId : null)]);
    }

    public function saveModel(Request $request, $id = null): void
    {
        $result = $this->service->saveModel($request->json(), $id ? (int)$id : null);
        $result['ok'] ? Response::success($result, $result['message']) : Response::error($result['message']);
    }

    public function deleteModel(Request $request, $id): void
    {
        Response::success($this->service->deleteModel((int)$id));
    }

    public function capabilities(): void
    {
        Response::json(['ok' => true, 'data' => [
            'capabilities' => AiBrickService::CAPABILITIES,
            'adapters' => AiBrickService::ADAPTERS,
            'strategies' => AiBrickService::STRATEGIES,
        ]]);
    }

    public function instances(): void
    {
        Response::json(['ok' => true, 'data' => $this->service->instances()]);
    }

    public function policies(): void
    {
        Response::json(['ok' => true, 'data' => $this->service->policies()]);
    }

    public function savePolicy(Request $request, $id = null): void
    {
        $result = $this->service->savePolicy($request->json(), $id ? (int)$id : null);
        $result['ok'] ? Response::success($result, $result['message']) : Response::error($result['message']);
    }

    public function deletePolicy(Request $request, $id): void
    {
        Response::success($this->service->deletePolicy((int)$id));
    }

    public function usage(Request $request): void
    {
        $range = (string)$request->get('range', '30d');
        if (!in_array($range, ['today', '7d', '30d', 'all'], true)) $range = '30d';
        $from = match ($range) {
            'today' => date('Y-m-d 00:00:00'),
            '7d' => date('Y-m-d H:i:s', strtotime('-7 days')),
            'all' => '2000-01-01 00:00:00',
            default => date('Y-m-d H:i:s', strtotime('-30 days')),
        };
        $group = (string)$request->get('group', 'model');
        Response::json(['ok' => true, 'data' => [
            'range' => $range,
            'group' => $group,
            'rows' => $this->service->usageGroup($group, $from),
            'recent' => $this->service->recentUsage(50),
        ]]);
    }

    public function test(Request $request): void
    {
        $input = $request->json();
        if (empty($input['model_id'])) {
            Response::error('model_id is required');
            return;
        }
        Response::json(['ok' => true, 'data' => $this->router->test($input)]);
    }

    public function aiRequest(Request $request): void
    {
        Response::json(['ok' => true, 'data' => $this->router->route($request->json())]);
    }

    public function command(Request $request): void
    {
        $result = $this->router->command($request->json());
        Response::json(['ok' => $result['ok'] ?? true, 'message' => $result['message'] ?? '', 'data' => $result['data'] ?? null]);
    }

    public function healthCheck(Request $request): void
    {
        $input = $request->json();
        $modelId = (int)($input['model_id'] ?? 0);
        if ($modelId) {
            Response::json($this->router->healthCheckModel($modelId));
            return;
        }
        $results = [];
        foreach ($this->service->models() as $model) {
            if ((int)$model['enabled'] !== 1) continue;
            $provider = $this->service->findProvider((int)$model['provider_id']);
            if (!$provider || $provider['status'] !== 'enabled' || !$this->service->resolveApiKey($provider)) continue;
            $results[] = $this->router->healthCheckModel((int)$model['id']);
        }
        Response::json(['ok' => true, 'data' => $results]);
    }

    public function ensureTables(): void
    {
        Response::json($this->service->ensureTables());
    }
}
