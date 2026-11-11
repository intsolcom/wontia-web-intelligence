<?php
namespace App\Controllers\Admin;

use App\Bricks\TrackingConversion\TrackingConversionBrick;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\TrackingConversionService;
use App\Services\TrackingEventService;

class TrackingConversionController
{
    public function overview(): void
    {
        if (!$this->canRead()) return;
        Response::json(TrackingConversionService::overview());
    }

    public function config(): void
    {
        if (!$this->canRead()) return;
        $cfg = TrackingConversionService::config();
        Response::json(['ok' => true, 'data' => [
            'config' => $cfg,
            'public' => TrackingConversionService::publicConfig(),
            'validation' => TrackingConversionService::validateConfig($cfg),
        ]]);
    }

    public function saveConfig(Request $req): void
    {
        if (!$this->canWrite()) return;
        $input = $req->json();
        if (!is_array($input)) {
            Response::error('Configuración inválida', 400);
            return;
        }
        $result = TrackingConversionService::saveConfig($input);
        Response::json($result);
    }

    public function events(Request $req): void
    {
        if (!$this->canRead()) return;
        $rows = (new TrackingEventService())->recent([
            'platform' => (string)$req->get('platform', ''),
            'event_name' => (string)$req->get('event_name', ''),
            'status' => (string)$req->get('status', ''),
            'order_uuid' => (string)$req->get('order_uuid', ''),
            'limit' => (int)$req->get('limit', 80),
        ]);
        Response::json(['ok' => true, 'data' => $rows]);
    }

    public function diagnose(): void
    {
        if (!$this->canRead()) return;
        Response::json(['ok' => true, 'data' => TrackingConversionService::diagnostics()]);
    }

    public function activate(): void
    {
        if (!$this->canWrite()) return;
        $result = TrackingConversionBrick::activate();
        if (!empty($result['ok'])) {
            $cfg = TrackingConversionService::config();
            $cfg['enabled'] = true;
            TrackingConversionService::saveConfig($cfg);
        }
        Response::json(['ok' => !empty($result['ok']), 'message' => (string)($result['message'] ?? ''), 'data' => $result]);
    }

    public function deactivate(): void
    {
        if (!$this->canWrite()) return;
        $result = TrackingConversionBrick::deactivate();
        Response::json($result);
    }

    public function testEvent(): void
    {
        if (!$this->canWrite()) return;
        Response::json((new TrackingEventService())->insertTest());
    }

    public function export(): void
    {
        if (!$this->canRead()) return;
        Response::json(['ok' => true, 'data' => [
            'brick' => 'tracking-conversion',
            'version' => TrackingConversionService::VERSION,
            'exported_at' => date('c'),
            'config' => TrackingConversionService::config(),
        ]]);
    }

    public function import(Request $req): void
    {
        if (!$this->canWrite()) return;
        $input = $req->json();
        $config = $input['config'] ?? null;
        if (is_string($config)) $config = json_decode($config, true);
        if (!is_array($config)) {
            Response::error('El JSON no contiene una configuración válida', 400);
            return;
        }
        Response::json(TrackingConversionService::saveConfig($config));
    }

    private function canRead(): bool
    {
        $role = (string)Session::userRole();
        if (in_array($role, ['superadmin', 'admin', 'editor'], true)) return true;
        Response::error('Sin permiso para ver Tracking & Conversion', 403);
        return false;
    }

    private function canWrite(): bool
    {
        $role = (string)Session::userRole();
        if (in_array($role, ['superadmin', 'admin'], true)) return true;
        Response::error('Solo superadmin o admin pueden modificar Tracking & Conversion', 403);
        return false;
    }
}
