<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\ShareSettingsService;

class ShareSettingsController
{
    public function show(Request $req): void
    {
        if (!$this->canRead()) return;
        Response::json(['ok' => true, 'data' => (new ShareSettingsService())->get()]);
    }

    public function save(Request $req): void
    {
        if (!$this->canWrite()) return;
        $input = $req->json();
        if (isset($input['config']) && is_array($input['config'])) $input = $input['config'];
        $result = (new ShareSettingsService())->save($input);
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function reset(Request $req): void
    {
        if (!$this->canWrite()) return;
        Response::json((new ShareSettingsService())->reset());
    }

    private function canRead(): bool
    {
        if (in_array((string)Session::userRole(), ['superadmin', 'admin', 'editor'], true)) return true;
        Response::error('Sin permiso para ver la configuración de compartir', 403);
        return false;
    }

    private function canWrite(): bool
    {
        if (in_array((string)Session::userRole(), ['superadmin', 'admin'], true)) return true;
        Response::error('Solo superadmin o admin pueden cambiar la configuración de compartir', 403);
        return false;
    }
}
