<?php
namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\ProductContentLabService;
use App\Services\ProductContentXlsService;
use App\Services\ProductContentVideoService;
use App\Services\YoutubeService;

/**
 * ProductContentLabController — API del Content Fix Lab (Tienda › Contenido).
 */
class ProductContentLabController
{
    private function svc(): ProductContentLabService
    {
        return new ProductContentLabService();
    }

    public function summary(): void
    {
        Response::json(['ok' => true, 'data' => $this->svc()->summary()]);
    }

    public function rules(): void
    {
        Response::json(['ok' => true, 'data' => [
            'stages' => ProductContentLabService::STAGES,
            'statuses' => ProductContentLabService::STATUS_LABELS,
            'minimum_confidence' => 85,
            'review_confidence' => 55,
            'note' => 'Ninguna fuente se aplica automáticamente. La identidad exacta se confirma con marca, tokens de modelo, SKU/MPN, dominio oficial y página de producto.',
        ]]);
    }

    public function items(Request $req): void
    {
        $filters = [
            'q' => (string)$req->get('q', ''),
            'filter' => (string)$req->get('filter', 'empty'),
            'status' => (string)$req->get('status', ''),
            'vendor' => (string)$req->get('vendor', ''),
            'limit' => (int)$req->get('limit', 50),
            'offset' => (int)$req->get('offset', 0),
        ];
        $data = $this->svc()->listProducts($filters);
        Response::json(['ok' => true, 'data' => $data]);
    }

    public function job(Request $req, string $id): void
    {
        $svc = $this->svc();
        $job = $svc->job((int)$id);
        if (!$job) Response::json(['ok' => false, 'message' => 'Trabajo no encontrado.'], 404);
        $activity = [];
        try {
            $st = Database::instance()->prepare('SELECT action, status, detail, actor, created_at FROM product_content_activity WHERE site_id = @site_id AND job_id = ? ORDER BY id DESC LIMIT 40');
            $st->execute([(int)$id]);
            $activity = $st->fetchAll();
        } catch (\Throwable $e) {
        }
        Response::json(['ok' => true, 'data' => ['job' => $job, 'activity' => $activity, 'video' => (new ProductContentVideoService())->status()]]);
    }

    public function research(Request $req): void
    {
        $productId = (int)$req->input('product_id', 0);
        $manualUrl = trim((string)$req->input('manual_url', ''));
        $result = $this->svc()->research($productId, $manualUrl, $this->actor());
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function confirmSource(Request $req): void
    {
        $result = $this->svc()->confirmSource(
            (int)$req->input('job_id', 0),
            (int)$req->input('index', 0),
            (bool)$req->input('confirm', false),
            $this->actor(),
            trim((string)$req->input('manual_url', ''))
        );
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function generate(Request $req): void
    {
        $result = $this->svc()->generate((int)$req->input('job_id', 0), $this->actor());
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function apply(Request $req): void
    {
        $result = $this->svc()->apply((int)$req->input('job_id', 0), $this->actor(), (bool)$req->input('overwrite_seo', false));
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function rollback(Request $req): void
    {
        $result = $this->svc()->rollback((int)$req->input('job_id', 0), $this->actor());
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function miche(Request $req): void
    {
        $result = $this->svc()->compareMiche((int)$req->input('job_id', 0));
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function micheApply(Request $req): void
    {
        $result = $this->svc()->applyMiche((int)$req->input('job_id', 0), $this->actor());
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function micheScan(Request $req): void
    {
        $result = $this->svc()->scanMicheUpdates((int)$req->input('limit', 250), $this->actor());
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function videoStatus(): void
    {
        Response::json(['ok' => true, 'data' => (new ProductContentVideoService())->status()]);
    }

    public function videoPrepare(Request $req): void
    {
        $result = $this->svc()->prepareVideo(
            (int)$req->input('job_id', 0),
            (int)$req->input('index', 0),
            (bool)$req->input('rights', false),
            $this->actor()
        );
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function videoUpload(Request $req): void
    {
        $result = $this->svc()->uploadYoutube((int)$req->input('job_id', 0), (bool)$req->input('rights', false), $this->actor());
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function youtubeStatus(): void
    {
        $data = (new YoutubeService())->status();
        $video = (new ProductContentVideoService())->status();
        $data['cookies_configured'] = !empty($video['cookies_configured']);
        Response::json(['ok' => true, 'data' => $data]);
    }

    public function youtubeCookies(Request $req): void
    {
        $file = $_FILES['cookies'] ?? null;
        if (!is_array($file) || empty($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) {
            Response::json(['ok' => false, 'message' => 'No se recibió el archivo de cookies.'], 422);
            return;
        }
        if ((int)($file['size'] ?? 0) > 2 * 1024 * 1024) {
            Response::json(['ok' => false, 'message' => 'El archivo de cookies supera 2 MB.'], 422);
            return;
        }
        $content = (string)@file_get_contents((string)$file['tmp_name']);
        if ($content === '' || (stripos($content, 'youtube.com') === false && stripos($content, '# Netscape HTTP Cookie File') === false)) {
            Response::json(['ok' => false, 'message' => 'El archivo no parece un cookies.txt de YouTube (formato Netscape).'], 422);
            return;
        }
        $root = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 4);
        $dir = $root . '/storage/_content_lab';
        if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
            Response::json(['ok' => false, 'message' => 'No se pudo crear el directorio privado de cookies.'], 500);
            return;
        }
        $path = $dir . '/youtube_cookies.txt';
        if (!@move_uploaded_file((string)$file['tmp_name'], $path)) {
            Response::json(['ok' => false, 'message' => 'No se pudo guardar el archivo de cookies.'], 500);
            return;
        }
        @chmod($path, 0600);
        try {
            Database::instance()->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, 'youtube_cookies_file', ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
                ->execute([$path]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'message' => 'No se pudo registrar el archivo de cookies.'], 500);
            return;
        }
        Response::json(['ok' => true, 'message' => 'Cookies de YouTube guardadas en almacenamiento privado.']);
    }

    public function youtubeCredentials(Request $req): void
    {
        $result = (new YoutubeService())->saveCredentials((string)$req->input('client_id', ''), (string)$req->input('client_secret', ''));
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function youtubeStart(): void
    {
        $result = (new YoutubeService())->authUrl();
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function youtubeDisconnect(): void
    {
        $result = (new YoutubeService())->disconnect();
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    /** Callback público de Google OAuth. */
    public function youtubeCallback(Request $req): void
    {
        $code = trim((string)$req->get('code', ''));
        $state = trim((string)$req->get('state', ''));
        $base = '/admin.php?panel=store';
        if ($code === '' || $state === '') {
            header('Location: ' . $base . '&yta=error&msg=' . rawurlencode('Google no devolvió código de autorización.') . '#store/contenido', true, 302);
            exit;
        }
        $result = (new YoutubeService())->handleCallback($code, $state);
        if (!empty($result['ok'])) header('Location: ' . $base . '&yta=ok#store/contenido', true, 302);
        else header('Location: ' . $base . '&yta=error&msg=' . rawurlencode((string)($result['message'] ?? 'Error OAuth')) . '#store/contenido', true, 302);
        exit;
    }

    public function exportXls(Request $req): void
    {
        $filters = [
            'q' => (string)$req->get('q', ''),
            'filter' => (string)$req->get('filter', 'empty'),
            'status' => (string)$req->get('status', ''),
            'vendor' => (string)$req->get('vendor', ''),
        ];
        $svc = $this->svc();
        $rows = $svc->exportRows($filters);
        $summary = $svc->summary();
        $summaryOut = [
            'Productos activos' => $summary['products'] ?? 0,
            'Con descripción' => $summary['with_description'] ?? 0,
            'Sin descripción' => $summary['empty'] ?? 0,
            'Descripciones aplicadas' => $summary['applied'] ?? 0,
            'Pendientes de confirmar' => $summary['needs_review'] ?? 0,
            'Generadas por revisar' => $summary['generated'] ?? 0,
            'Actualizaciones Miche' => $summary['miche_updates'] ?? 0,
            'Productos exportados' => count($rows),
        ];
        $xls = (new ProductContentXlsService())->build($rows, $summaryOut, 'Productos a corregir');
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="sonika-contenido-' . date('Ymd-Hi') . '.xls"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo $xls;
        exit;
    }

    private function actor(): string
    {
        $u = \App\Core\Session::user();
        return is_array($u) ? (string)($u['username'] ?? 'admin') : 'admin';
    }
}
