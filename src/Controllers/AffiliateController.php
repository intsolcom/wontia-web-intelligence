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

    /** POST /api/v1/public/affiliates/tip — aporte de agradecimiento (público, simulado). */
    public function tip($request)
    {
        $in = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($in)) $in = $_POST;
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        $aff = (new AffiliateService())->getBySlug((string)($in['artist'] ?? ''));
        if (!$aff) { echo json_encode(['ok' => false, 'message' => 'Artista no encontrado.']); return; }
        echo json_encode((new AffiliateService())->tipAdd((int)$aff['id'], $in), JSON_UNESCAPED_UNICODE);
    }

    /** POST /api/v1/public/affiliates/media/upload — sube portada/avatar/galería (WebP + cuota). */
    public function mediaUpload($request)
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        \App\Core\Session::start();
        if (empty($_SESSION['ff_affiliate_id'])) { echo json_encode(['ok' => false, 'message' => 'Sesión requerida.']); return; }
        echo json_encode((new AffiliateService())->mediaUpload((int)$_SESSION['ff_affiliate_id'], $_FILES, $_POST), JSON_UNESCAPED_UNICODE);
    }

    /** POST /api/v1/public/affiliates/media — guarda medios (cover/gallery/YouTube/Instagram). */
    public function mediaSave($request)
    {
        $in = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($in)) $in = $_POST;
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        \App\Core\Session::start();
        if (empty($_SESSION['ff_affiliate_id'])) { echo json_encode(['ok' => false, 'message' => 'Sesión requerida.']); return; }
        echo json_encode((new AffiliateService())->mediaSave((int)$_SESSION['ff_affiliate_id'], $in), JSON_UNESCAPED_UNICODE);
    }

    /** POST /api/v1/public/affiliates/cv — guarda la trayectoria del artista. */
    public function cvSave($request)
    {
        $in = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($in)) $in = $_POST;
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        \App\Core\Session::start();
        if (empty($_SESSION['ff_affiliate_id'])) { echo json_encode(['ok' => false, 'message' => 'Sesión requerida.']); return; }
        echo json_encode((new AffiliateService())->cvSave((int)$_SESSION['ff_affiliate_id'], is_array($in) ? $in : []), JSON_UNESCAPED_UNICODE);
    }

    /** POST /api/v1/public/affiliates/payout/request — paso 1: envía OTP para solicitar retiro. */
    public function payoutRequest($request)
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        \App\Core\Session::start();
        if (empty($_SESSION['ff_affiliate_id'])) { echo json_encode(['ok' => false, 'message' => 'Sesión requerida.']); return; }
        echo json_encode((new AffiliateService())->payoutRequest((int)$_SESSION['ff_affiliate_id']), JSON_UNESCAPED_UNICODE);
    }

    /** POST /api/v1/public/affiliates/payout/confirm — paso 2: OTP → registra el retiro. */
    public function payoutConfirm($request)
    {
        $in = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($in)) $in = $_POST;
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        \App\Core\Session::start();
        if (empty($_SESSION['ff_affiliate_id'])) { echo json_encode(['ok' => false, 'message' => 'Sesión requerida.']); return; }
        echo json_encode((new AffiliateService())->payoutConfirm((int)$_SESSION['ff_affiliate_id'], (string)($in['code'] ?? '')), JSON_UNESCAPED_UNICODE);
    }

    /** POST /api/v1/public/affiliates/recover — envía OTP para recuperar contraseña. */
    public function recoverRequest($request)
    {
        $in = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($in)) $in = $_POST;
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode((new AffiliateService())->recoverRequest((string)($in['email'] ?? '')), JSON_UNESCAPED_UNICODE);
    }

    /** POST /api/v1/public/affiliates/recover/confirm — código OTP + nueva contraseña. */
    public function recoverConfirm($request)
    {
        $in = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($in)) $in = $_POST;
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode((new AffiliateService())->recoverConfirm((string)($in['email'] ?? ''), (string)($in['code'] ?? ''), (string)($in['password'] ?? '')), JSON_UNESCAPED_UNICODE);
    }

    /** POST /api/v1/public/affiliates/login — paso 1: correo + contraseña → envía OTP (no abre sesión). */
    public function login($request)
    {
        $in = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($in)) $in = $_POST;
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode((new AffiliateService())->login((string)($in['email'] ?? ''), (string)($in['password'] ?? '')), JSON_UNESCAPED_UNICODE);
    }

    /** POST /api/v1/public/affiliates/login/verify — paso 2: correo + código OTP → abre sesión. */
    public function loginVerify($request)
    {
        $in = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($in)) $in = $_POST;
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        \App\Core\Session::start();
        $p = (new AffiliateService())->loginVerify((string)($in['email'] ?? ''), (string)($in['code'] ?? ''));
        if (!$p) { echo json_encode(['ok' => false, 'message' => 'Código incorrecto o vencido.']); return; }
        $_SESSION['ff_affiliate_id'] = $p['id'];
        echo json_encode(['ok' => true, 'slug' => $p['slug'], 'name' => $p['name']], JSON_UNESCAPED_UNICODE);
    }

    /** POST /api/v1/public/affiliates/logout */
    public function logout($request)
    {
        \App\Core\Session::start();
        unset($_SESSION['ff_affiliate_id']);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }

    /** POST /api/v1/public/affiliates/contract — solicitud de contratación (público). */
    public function contract($request)
    {
        $in = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($in)) $in = $_POST;
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        $slug = (string)($in['artist'] ?? '');
        $svc = new AffiliateService();
        $aff = $svc->getBySlug($slug);
        if (!$aff) { echo json_encode(['ok' => false, 'message' => 'Artista no encontrado.']); return; }
        echo json_encode($svc->contractRequestAdd((int)$aff['id'], $in), JSON_UNESCAPED_UNICODE);
    }

    /** POST /api/v1/public/affiliates/document — subida privada (email + últimos 4 del doc + archivo). */
    public function uploadDocument($request)
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $doc = (string)($_POST['doc'] ?? '');
        $kind = preg_replace('/[^a-z_]/', '', (string)($_POST['kind'] ?? 'id_front')) ?: 'id_front';
        $svc = new AffiliateService();
        $prof = $svc->statusLookup($email, $doc);
        if (!$prof) { echo json_encode(['ok' => false, 'message' => 'Datos no válidos.']); return; }
        if (empty($_FILES['file']) || !is_array($_FILES['file']) || ($_FILES['file']['error'] ?? 1) !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'message' => 'Archivo no recibido.']); return;
        }
        $f = $_FILES['file'];
        if ((int)$f['size'] > 8 * 1024 * 1024) { echo json_encode(['ok' => false, 'message' => 'Máximo 8 MB.']); return; }
        $mime = function_exists('mime_content_type') ? (string)@mime_content_type($f['tmp_name']) : (string)($f['type'] ?? '');
        $allow = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
        if (!isset($allow[$mime])) { echo json_encode(['ok' => false, 'message' => 'Formato no permitido (PDF/JPG/PNG).']); return; }
        $dir = '/app/public/assets/uploads/_private/affiliate_docs/' . (int)$prof['id'];
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $name = bin2hex(random_bytes(10)) . '.' . $allow[$mime];
        $dest = rtrim($dir, '/') . '/' . $name;
        if (!@move_uploaded_file($f['tmp_name'], $dest)) { echo json_encode(['ok' => false, 'message' => 'No se pudo guardar.']); return; }
        @chmod($dest, 0640);
        $rel = '/assets/uploads/_private/affiliate_docs/' . (int)$prof['id'] . '/' . $name;
        try {
            \App\Core\Database::instance()->prepare('INSERT INTO affiliate_documents (site_id, affiliate_id, kind, path, status) VALUES (@site_id, :a, :k, :p, "pending")')
                ->execute(['a' => (int)$prof['id'], 'k' => $kind, 'p' => $rel]);
        } catch (\Throwable $e) { echo json_encode(['ok' => false, 'message' => 'Error de registro.']); return; }
        echo json_encode(['ok' => true, 'message' => 'Documento recibido.']);
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
