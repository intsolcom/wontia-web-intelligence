<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\SystemUpdateService;
use App\Services\ThemeConfig;
use App\Services\ThemeService;

class ThemeController
{
    private function svc(): ThemeService
    {
        return new ThemeService();
    }

    private function requireSuperadmin(): void
    {
        if ((string)Session::userRole() !== 'superadmin') {
            Response::error('Solo el superadministrador puede gestionar temas.', 403);
        }
    }

    private function actor(): string
    {
        $u = Session::user() ?: [];
        return (string)($u['username'] ?? $u['email'] ?? 'admin');
    }

    private function isCentral(): bool
    {
        return (new SystemUpdateService())->isCentral();
    }

    public function marketplace(): void
    {
        $svc = $this->svc();
        $themes = $svc->manifests();
        $isCentral = $this->isCentral();
        $data = [
            'themes' => $themes,
            'active' => $svc->activeTheme(),
            'site_id' => $svc->siteId(),
            'is_central' => $isCentral,
            'can_generate' => $isCentral && (string)Session::userRole() === 'superadmin',
            'events' => $isCentral ? $svc->recentEvents(15) : [],
            'serials' => $isCentral ? $svc->serials(30) : [],
        ];
        Response::json(['ok' => true, 'data' => $data]);
    }

    public function activate(Request $req): void
    {
        $this->requireSuperadmin();
        $theme = (string)$req->input('theme', '');
        $r = $this->svc()->activate($theme);
        if (empty($r['ok'])) Response::error($r['message'] ?? 'No se pudo activar', 422, $r);
        Response::json($r);
    }

    public function generateSerial(Request $req): void
    {
        $this->requireSuperadmin();
        if (!$this->isCentral()) {
            Response::error('Solo el servidor central puede generar códigos de instalación.', 403);
        }
        $theme = (string)$req->input('theme', '');
        $label = (string)$req->input('label', '');
        $r = $this->svc()->generateSerial($theme, $label, $this->actor());
        if (empty($r['ok'])) Response::error($r['message'] ?? 'No se pudo generar', 422, $r);
        Response::json($r);
    }

    public function revokeSerial(Request $req, string $id): void
    {
        $this->requireSuperadmin();
        if (!$this->isCentral()) Response::error('Solo el servidor central.', 403);
        $r = $this->svc()->revokeSerial((int)$id, $this->actor());
        if (empty($r['ok'])) Response::error($r['message'] ?? 'No se pudo revocar', 422, $r);
        Response::json($r);
    }

    public function redeem(Request $req): void
    {
        $this->requireSuperadmin();
        $code = (string)$req->input('code', '');
        $r = $this->svc()->redeemSerial($code, $this->actor());
        if (empty($r['ok'])) Response::error($r['message'] ?? 'Código inválido', 422, $r);
        Response::json($r);
    }

    public function events(): void
    {
        $this->requireSuperadmin();
        Response::json(['ok' => true, 'data' => $this->svc()->recentEvents(50)]);
    }

    public function config(Request $req, string $slug): void
    {
        $this->requireSuperadmin();
        $schema = ThemeConfig::schema($slug);
        Response::json(['ok' => true, 'data' => ['slug' => $slug, 'schema' => $schema, 'values' => ThemeConfig::all($slug)]]);
    }

    public function saveConfig(Request $req, string $slug): void
    {
        $this->requireSuperadmin();
        $values = $req->input('values', []);
        if (!is_array($values)) $values = [];
        ThemeConfig::save($slug, $values);
        $this->svc()->event('config_saved', $slug, 'ok', ['fields' => count($values)]);
        Response::json(['ok' => true, 'message' => 'Configuración del tema guardada.']);
    }
}
