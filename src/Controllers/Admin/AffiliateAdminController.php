<?php
namespace App\Controllers\Admin;

use App\Services\AffiliateService;

class AffiliateAdminController
{
    public function index($request)
    {
        $svc = new AffiliateService();
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => true, 'ready' => $svc->ready(), 'profiles' => $svc->listProfiles(200)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function status($request, $id)
    {
        $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $status = (string)($in['status'] ?? '');
        $svc = new AffiliateService();
        $ok = $svc->setStatus((int)$id, $status);
        if ($ok && $status === 'APPROVED') $svc->ensureLink((int)$id);
        header('Content-Type: application/json');
        echo json_encode(['ok' => $ok, 'status' => $status], JSON_UNESCAPED_UNICODE);
    }
}
