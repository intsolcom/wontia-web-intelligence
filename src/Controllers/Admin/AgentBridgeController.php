<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AgentBridgeService;

class AgentBridgeController
{
    private function svc(): AgentBridgeService
    {
        return new AgentBridgeService();
    }

    private function requireAdmin(): void
    {
        if (!in_array((string)Session::userRole(), ['superadmin', 'admin'], true)) {
            Response::error('Solo administradores pueden usar el canal de agentes.', 403);
        }
    }

    public function index(Request $req): void
    {
        $this->requireAdmin();
        $svc = $this->svc();
        $siteId = $req->get('site_id');
        $feed = $svc->feed($siteId !== null && $siteId !== '' ? (int)$siteId : null, 200);
        Response::json(['ok' => true, 'data' => [
            'messages' => $feed,
            'agents' => $svc->agents(),
            'max_id' => $svc->maxId(),
            'me' => 'head-team-leader',
        ]]);
    }

    public function post(Request $req): void
    {
        $this->requireAdmin();
        $u = Session::user() ?: [];
        $r = $this->svc()->post([
            'site_id' => $req->input('site_id', null),
            'site_slug' => (string)$req->input('site_slug', ''),
            'from_role' => (string)($req->input('from_role', 'head-team-leader') ?: 'head-team-leader'),
            'to_role' => (string)($req->input('to_role', 'agent') ?: 'agent'),
            'type' => (string)$req->input('type', 'ORDEN'),
            'subject' => (string)$req->input('subject', ''),
            'body' => (string)$req->input('body', ''),
            'status' => (string)$req->input('status', 'abierto'),
            'meta' => ['posted_by' => $u['username'] ?? null],
        ]);
        if (empty($r['ok'])) Response::error($r['message'] ?? 'No se pudo publicar', 422, $r);
        Response::json($r);
    }

    public function setStatus(Request $req, string $id): void
    {
        $this->requireAdmin();
        Response::json($this->svc()->setStatus((int)$id, (string)$req->input('status', 'resuelto')));
    }

    public function tasks(Request $req): void
    {
        $this->requireAdmin();
        $status = (string)$req->get('status', '');
        Response::json(['ok' => true, 'data' => $this->svc()->tasks($status !== '' ? $status : null)]);
    }

    public function taskStatus(Request $req, string $id): void
    {
        $this->requireAdmin();
        Response::json($this->svc()->setTaskStatus((int)$id, (string)$req->input('status', 'done')));
    }

    public function agents(): void
    {
        $this->requireAdmin();
        Response::json(['ok' => true, 'data' => $this->svc()->agents()]);
    }

    public function createAgent(Request $req): void
    {
        $this->requireAdmin();
        if ((string)Session::userRole() !== 'superadmin') Response::error('Solo el superadministrador.', 403);
        $r = $this->svc()->createAgent(
            (string)$req->input('slug', ''),
            (string)$req->input('name', ''),
            $req->input('site_id', null) !== null ? (int)$req->input('site_id') : null,
            (bool)$req->input('rotate', false)
        );
        if (empty($r['ok'])) Response::error($r['message'] ?? 'Error', 422, $r);
        Response::json($r);
    }

    /** API para AGENTES de sitio (autenticación por X-Agent-Key). */
    public function agentPost(Request $req, string $site): void
    {
        $key = (string)($req->header('X-Agent-Key') ?? '');
        $agent = $this->svc()->verifyByKey($key);
        if (!$agent) Response::error('Clave de agente inválida', 401);
        $r = $this->svc()->post([
            'site_id' => $agent['site_id'] !== null ? (int)$agent['site_id'] : null,
            'site_slug' => $site,
            'from_role' => (string)$agent['slug'],
            'to_role' => 'head-team-leader',
            'type' => (string)$req->input('type', 'NOTA'),
            'subject' => (string)$req->input('subject', ''),
            'body' => (string)$req->input('body', ''),
            'status' => (string)$req->input('status', 'abierto'),
            'meta' => ['agent' => $agent['slug']],
        ]);
        if (empty($r['ok'])) Response::error($r['message'] ?? 'No se pudo publicar', 422, $r);
        Response::json($r);
    }

    /** Exporta el puente (markdown) para espejo/versionado. */
    public function export(Request $req): void
    {
        $this->requireAdmin();
        $siteSlug = (string)$req->get('site', '');
        $siteId = $req->get('site_id', null);
        $md = $this->svc()->exportMarkdown(
            ($siteId !== null && $siteId !== '') ? (int)$siteId : null,
            $siteSlug !== '' ? $siteSlug : null
        );
        header('Content-Type: text/markdown; charset=utf-8');
        header('Content-Disposition: attachment; filename="bridge-' . ($siteSlug ?: 'all') . '.md"');
        echo $md;
        exit;
    }
}
