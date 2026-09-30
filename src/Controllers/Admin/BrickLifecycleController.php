<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\BrickLifecycleService;

class BrickLifecycleController
{
    private function requireAdmin(): void
    {
        if (!in_array((string)Session::userRole(), ['superadmin', 'admin'], true)) {
            Response::error('Solo administradores pueden gestionar el ciclo de vida de bricks', 403);
        }
    }

    private function svc(): BrickLifecycleService
    {
        return new BrickLifecycleService();
    }

    private function actor(): string
    {
        $u = Session::user() ?: [];
        return (string)($u['name'] ?? $u['username'] ?? $u['email'] ?? Session::userRole() ?? 'admin');
    }

    public function index(): void
    {
        $svc = $this->svc();
        $svc->ensureTables();
        $map = [];
        foreach ($svc->all() as $slug => $row) {
            $map[$slug] = $svc->resolve($slug, ['functional' => $row['state'] === 'launched']);
        }
        Response::json(['ok' => true, 'data' => [
            'map' => $map,
            'recent' => $svc->recentEvents(20),
            'config' => ['new_days' => BrickLifecycleService::NEW_DAYS, 'target_days' => BrickLifecycleService::TARGET_DAYS, 'maturities' => BrickLifecycleService::MATURITIES],
            'is_admin' => in_array((string)Session::userRole(), ['superadmin', 'admin'], true),
        ]]);
    }

    public function readiness(Request $request, string $slug = ''): void
    {
        Response::json(['ok' => true, 'data' => $this->svc()->readiness($slug)]);
    }

    public function launch(Request $request, string $slug = ''): void
    {
        $this->requireAdmin();
        $notes = trim((string)$request->input('release_notes', ''));
        if ($notes === '') Response::error('Las notas de versión son obligatorias para lanzar', 422);
        $result = $this->svc()->launch($slug, $this->actor(), [
            'release_notes' => $notes,
            'maturity' => (string)$request->input('maturity', 'stable'),
            'force' => (bool)$request->input('force', false),
        ]);
        if (empty($result['ok'])) Response::error($result['message'] ?? 'No se pudo lanzar', 409, $result);
        Response::json($result);
    }

    public function schedule(Request $request, string $slug = ''): void
    {
        $this->requireAdmin();
        $target = (string)$request->input('target_launch_at', '');
        $result = $this->svc()->schedule($slug, $target, $this->actor(), (string)$request->input('reason', ''));
        if (empty($result['ok'])) Response::error($result['message'] ?? 'No se pudo programar', 422);
        Response::json($result);
    }

    public function incubate(Request $request, string $slug = ''): void
    {
        $this->requireAdmin();
        $result = $this->svc()->incubate($slug, $this->actor(), (string)$request->input('reason', ''));
        if (empty($result['ok'])) Response::error($result['message'] ?? 'No se pudo reinculbar', 422);
        Response::json($result);
    }

    public function hide(Request $request, string $slug = ''): void
    {
        $this->requireAdmin();
        $result = $this->svc()->setHidden($slug, (bool)$request->input('hidden', true), $this->actor());
        if (empty($result['ok'])) Response::error($result['message'] ?? 'No se pudo ocultar', 422);
        Response::json($result);
    }

    public function maturity(Request $request, string $slug = ''): void
    {
        $this->requireAdmin();
        $result = $this->svc()->setMaturity($slug, (string)$request->input('maturity', ''), $this->actor());
        if (empty($result['ok'])) Response::error($result['message'] ?? 'Madurez inválida', 422);
        Response::json($result);
    }

    public function interest(Request $request, string $slug = ''): void
    {
        $result = $this->svc()->addInterest($slug);
        if (empty($result['ok'])) Response::error($result['message'] ?? 'No disponible', 422);
        Response::json($result);
    }

    public function events(Request $request, string $slug = ''): void
    {
        Response::json(['ok' => true, 'data' => $this->svc()->events($slug)]);
    }

    public function ensureTables(): void
    {
        $this->requireAdmin();
        Response::json($this->svc()->ensureTables());
    }

    public function runDue(): void
    {
        $this->requireAdmin();
        Response::json($this->svc()->processDueLaunches());
    }
}
