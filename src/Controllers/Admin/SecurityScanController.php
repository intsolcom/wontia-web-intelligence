<?php
namespace App\Controllers\Admin;

use App\Core\Response;
use App\Core\Session;
use App\Services\SecurityScanService;

/**
 * SecurityScanController — robot de escaneo de seguridad.
 * Acceso: superadmin | admin.
 */
class SecurityScanController
{
    private function guard(): SecurityScanService
    {
        if (!in_array((string)Session::userRole(), ['superadmin', 'admin'], true)) {
            Response::error('Sin permiso para el escaneo de seguridad.', 403);
        }
        return new SecurityScanService();
    }

    public function report(): void
    {
        $svc = $this->guard();
        $r = $svc->load();
        if (!$r) { $r = $svc->scan(); $svc->save($r); }
        Response::json(['ok' => true, 'data' => $r]);
    }

    public function scan(): void
    {
        $svc = $this->guard();
        $r = $svc->scan();
        $svc->save($r);
        Response::json(['ok' => true, 'data' => $r]);
    }
}
