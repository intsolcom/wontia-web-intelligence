<?php
namespace App\Controllers;

use App\Services\AffiliateService;

class AffiliateController
{
    /** POST /api/v1/public/affiliates/register */
    public function register($request)
    {
        $in = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($in)) $in = $_POST;
        $r = (new AffiliateService())->createProfile(is_array($in) ? $in : []);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** POST /api/v1/public/affiliates/otp/send */
    public function sendOtp($request)
    {
        $in = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($in)) $in = $_POST;
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode((new AffiliateService())->sendOtp((string)($in['email'] ?? '')));
    }

    /** POST /api/v1/public/affiliates/otp/verify */
    public function verifyOtp($request)
    {
        $in = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($in)) $in = $_POST;
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode((new AffiliateService())->verifyOtp((string)($in['email'] ?? ''), (string)($in['code'] ?? '')));
    }

    /** POST /api/v1/public/affiliates/status — consulta de estado (correo + últimos 4 del doc). */
    public function status($request)
    {
        $in = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($in)) $in = $_POST;
        $r = (new AffiliateService())->statusLookup((string)($in['email'] ?? ''), (string)($in['doc'] ?? ''));
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        if (!$r) { echo json_encode(['ok' => false, 'message' => 'No encontramos una solicitud con esos datos.']); return; }
        echo json_encode(['ok' => true, 'status' => $r['status'], 'name' => $r['name'], 'created_at' => $r['created_at']], JSON_UNESCAPED_UNICODE);
    }

    /** GET /r/{code} — captura el clic, fija cookie de atribución (30d) y redirige. */
    public function click($request, $code)
    {
        $sid = (string)($_COOKIE['wwi_seo_sid'] ?? '');
        $r = (new AffiliateService())->recordClick((string)$code, $sid);
        if ($r) {
            setcookie('sonika_aff', (string)$r['code'], time() + 30 * 86400, '/', '', false, false);
        }
        header('Location: /', true, 302);
        exit;
    }
}
