<?php
define('WONTIA_START', microtime(true));
define('ROOT_DIR', dirname(__DIR__));
define('APP_ENV', getenv('APP_ENV') ?: 'production');

require_once ROOT_DIR . '/vendor/autoload.php';

use App\Core\App;
use App\Core\Config;
use App\Core\Database;
use App\Core\Response;

Config::load();
try {
    \App\Services\SeoInternalTrafficService::capture();
} catch (\Throwable $e) {
}
$__blockPath = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (!preg_match('~^/(admin|api/v1/admin)(/|$)~i', $__blockPath)) {
    try {
        $__clientIp = \App\Services\SeoLiveMonitorService::clientIp();
        if ($__clientIp && \App\Services\SeoIpSecurityService::isBlocked($__clientIp)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo '403 Forbidden';
            exit;
        }
    } catch (\Throwable $e) {
    }
}
\App\Services\SeoLiveMonitorService::register();
$requestedLang = isset($_GET['lang']) ? strtolower(trim((string)$_GET['lang'])) : null;
\App\Core\Lang::init($requestedLang);
if ($requestedLang !== null && in_array($requestedLang, \App\Core\Lang::available(), true)) {
    setcookie('wwi_lang', $requestedLang, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
}

set_exception_handler(function (\Throwable $e) {
    $debug = Config::get('APP_DEBUG', 'false') === 'true';
    error_log("Wontia Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
    if ($debug) {
        Response::error($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 500);
    } else {
        Response::html('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Error</title><style>body{font-family:sans-serif;text-align:center;padding:100px;background:#F6F6F3;color:#2F2F2F}h1{font-size:64px;margin:0;color:#9B8CDE}p{margin:16px 0;color:#6B6B6B}</style></head><body><h1>500</h1><p>Something went wrong. Please try again.</p><a href="/">Go home</a></body></html>', 500);
    }
});

$app = new App();
$app->boot();

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/') ?: '/';

// Consolida el host público: www -> sin www; previews internos noindex.
try {
    $__publicBase = rtrim((string)\App\Bricks\SeoGlobalLaunch\SeoGlobalLaunchBrick::siteUrl(), '/');
    $__publicHost = strtolower((string)parse_url($__publicBase, PHP_URL_HOST));
    $__requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($__publicHost !== '' && $__requestHost !== '') {
        $__method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $__requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        if (!str_starts_with($__requestUri, '/')) $__requestUri = '/';
        $__requestUri = preg_replace('/[\r\n]+/', '', $__requestUri) ?? '/';
        if ($__requestHost === 'www.' . $__publicHost && in_array($__method, ['GET', 'HEAD'], true) && !str_starts_with($__requestUri, '/api/')) {
            header('Location: ' . $__publicBase . $__requestUri, true, 301);
            exit;
        }
        if ($__requestHost !== $__publicHost && str_ends_with($__requestHost, '.wontia.com')) {
            header('X-Robots-Tag: noindex, nofollow');
        }
    }
} catch (\Throwable $e) {
}

// El Hero se edita en caliente y usa imágenes versionadas. Forzamos
// revalidación del HTML de la home para que los cambios de zoom/imagen
// lleguen a móvil sin depender de la caché heurística del navegador.
if ($uri === '/') {
    header('Cache-Control: no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

if (str_starts_with($uri, '/api/')) {
    require ROOT_DIR . '/public/api.php';
    exit;
}

if ($uri === '/admin' || str_starts_with($uri, '/admin')) {
    require ROOT_DIR . '/public/admin.php';
    exit;
}

if ($uri === '/install' || $uri === '/install.php') {
    require ROOT_DIR . '/public/install.php';
    exit;
}

if ($uri === '/sitemap.xml') {
    require ROOT_DIR . '/public/sitemap.php';
    exit;
}

if ($uri === '/google-merchant.xml') {
    require ROOT_DIR . '/public/google-merchant.php';
    exit;
}

if ($uri === '/sitemap-brick.xml') {
    require ROOT_DIR . '/public/sitemap-brick.php';
    exit;
}

try {
    $redirect = \App\Bricks\SeoGlobalLaunch\SeoGlobalLaunchBrick::redirectFor((string)($_SERVER['REQUEST_URI'] ?? $uri));
    if ($redirect && !empty($redirect['to'])) {
        header('Location: ' . $redirect['to'], true, (int)($redirect['status'] ?? 301));
        exit;
    }
} catch (\Throwable $e) {
}

try {
    $db = Database::instance();
    $slug = $uri === '/' ? 'home' : trim($uri, '/');

    $stmt = $db->prepare("SELECT * FROM pages WHERE slug = :slug AND site_id = @site_id AND status = 'published' LIMIT 1");
    $stmt->execute(['slug' => $slug]);
    $page = $stmt->fetch();

    $theme = $db->query("SELECT theme FROM sites WHERE id = @site_id")->fetchColumn() ?: Config::get('theme', 'default');

    if (!$page) {
        $themeRouter = ROOT_DIR . '/templates/themes/' . $theme . '/router.php';
        if (is_file($themeRouter)) {
            $handled = require $themeRouter;
            if ($handled) exit;
        }
        try {
            \App\Bricks\SeoGlobalLaunch\SeoGlobalLaunchBrick::record404($uri, (string)($_SERVER['HTTP_REFERER'] ?? ''));
        } catch (\Throwable $e) {
        }
        http_response_code(404);
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>404 — Wontia</title><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/><style>body{font-family:Inter,sans-serif;background:#F6F6F3;color:#2F2F2F;text-align:center;padding:100px 20px}h1{font-size:80px;color:#9B8CDE;margin-bottom:8px}p{color:#6B6B6B;margin-bottom:24px}a{display:inline-block;padding:12px 28px;border-radius:12px;background:linear-gradient(135deg,#9B8CDE,#B89EFF);color:#fff;text-decoration:none;font-weight:600}</style></head><body><h1>404</h1><p>The page you\'re looking for doesn\'t exist.</p><a href="/">Go home</a></body></html>';
        exit;
    }

    $sec = $db->prepare("SELECT * FROM sections WHERE page_id = :pid AND is_active = 1 ORDER BY sort_order ASC");
    $sec->execute(['pid' => $page['id']]);
    $sections = $sec->fetchAll();

    require ROOT_DIR . '/templates/themes/' . $theme . '/index.php';
} catch (\Exception $e) {
    if (Config::get('APP_DEBUG', 'false') === 'true') {
        echo '<div style="padding:40px;font-family:monospace"><h2>Error</h2><pre>' . $e->getMessage() . '</pre></div>';
    } else {
        echo '<!DOCTYPE html><html><head><title>Wontia</title></head><body style="font-family:sans-serif;text-align:center;padding:100px"><h1>500</h1><p>Something went wrong.</p></body></html>';
    }
}
