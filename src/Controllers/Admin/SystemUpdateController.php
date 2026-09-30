<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\SystemUpdateService;

class SystemUpdateController
{
    private function svc(): SystemUpdateService
    {
        return new SystemUpdateService();
    }

    private function actor(): string
    {
        $u = Session::user() ?: [];
        return (string)($u['name'] ?? $u['username'] ?? $u['email'] ?? Session::userRole() ?? 'admin');
    }

    private function requireAdmin(): void
    {
        if (!in_array((string)Session::userRole(), ['superadmin', 'admin'], true)) {
            Response::error('Solo administradores pueden gestionar actualizaciones', 403);
        }
    }

    public function overview(): void
    {
        $svc = $this->svc();
        Response::json(['ok' => true, 'data' => [
            'local' => $svc->localInfo(),
            'settings' => [
                'channel' => $svc->channel(),
                'central_url' => $svc->centralUrl(),
                'repo' => $svc->repoUrl(),
                'auto_update' => $svc->setting('wwi.auto_update', '0') === '1',
                'window' => (string)$svc->setting('wwi.update_window', ''),
                'has_secret' => $svc->secret() !== '',
            ],
            'channels' => SystemUpdateService::CHANNELS,
            'can_apply' => in_array((string)Session::userRole(), ['superadmin', 'admin'], true),
        ]]);
    }

    public function check(): void
    {
        Response::json($this->svc()->check());
    }

    public function apply(Request $request): void
    {
        $this->requireAdmin();
        $result = $this->svc()->requestUpdate($this->actor(), [
            'channel' => (string)$request->input('channel', ''),
            'backup' => (bool)$request->input('backup', false),
            'force' => (bool)$request->input('force', false),
        ]);
        if (empty($result['ok'])) Response::error($result['message'] ?? 'No se pudo iniciar la actualización', 422, $result);
        Response::json($result);
    }

    public function status(): void
    {
        Response::json(['ok' => true, 'data' => $this->svc()->status()]);
    }

    public function history(): void
    {
        Response::json(['ok' => true, 'data' => $this->svc()->history()]);
    }

    public function saveSettings(Request $request): void
    {
        $this->requireAdmin();
        $svc = $this->svc();
        $in = $request->input('settings', []);
        if (!is_array($in)) $in = [];
        if (isset($in['channel']) && in_array($in['channel'], SystemUpdateService::CHANNELS, true)) $svc->saveSetting('wwi.update_channel', $in['channel']);
        if (isset($in['central_url'])) $svc->saveSetting('wwi.update_central_url', rtrim((string)$in['central_url'], '/'));
        if (isset($in['repo'])) $svc->saveSetting('wwi.update_repo', (string)$in['repo']);
        if (isset($in['auto_update'])) $svc->saveSetting('wwi.auto_update', !empty($in['auto_update']) ? '1' : '0');
        if (isset($in['window'])) $svc->saveSetting('wwi.update_window', (string)$in['window']);
        Response::json(['ok' => true, 'message' => 'Configuración guardada']);
    }

    public function regenerateSecret(): void
    {
        $this->requireAdmin();
        $secret = $this->svc()->generateSecret();
        Response::json(['ok' => true, 'secret' => $secret, 'message' => 'Secreto regenerado — cópialo y pégalo en los demás sitios WWI.']);
    }

    public function publicManifest(): void
    {
        Response::json(['ok' => true, 'data' => $this->svc()->manifest()]);
    }

    public function publicStatus(): void
    {
        Response::json(['ok' => true, 'data' => $this->svc()->status()]);
    }

    public function publicHistory(): void
    {
        Response::json(['ok' => true, 'data' => $this->svc()->history()]);
    }

    public function publicUpdateRequest(Request $request): void
    {
        $ts = (int)$request->input('ts', 0);
        $sign = (string)$request->input('sign', '');
        $svc = $this->svc();
        if (!$svc->verifyCentralRequest($ts, $sign)) {
            Response::error('Solicitud no autorizada', 403);
        }
        $result = $svc->requestUpdate('remote-site-' . (string)$request->input('site_id', '?'), [
            'channel' => (string)$request->input('channel', ''),
            'backup' => true,
            'force' => true,
        ]);
        if (empty($result['ok'])) Response::error($result['message'] ?? 'No se pudo encolar', 422, $result);
        Response::json(['ok' => true, 'message' => 'Actualización aceptada por el servidor central. Se aplicará en menos de 1 minuto.']);
    }
}
