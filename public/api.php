<?php
define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Router;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\AuthMiddleware;
use App\Widgets\WidgetRegistry;

Config::load();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

$router = new Router();

// ── PUBLIC ENDPOINTS ──

$router->get('/api/v1/public/page/{slug}', function ($request, $slug) {
    $siteId = (int)($_GET['site_id'] ?? 1);
    $db = Database::instance();

    $stmt = $db->prepare("SELECT id, title, slug, meta_title, meta_description, meta_keywords, og_image, canonical_url, no_index, template, status FROM pages WHERE slug = :slug AND site_id = :sid AND status = 'published' LIMIT 1");
    $stmt->execute(['slug' => $slug, 'sid' => $siteId]);
    $page = $stmt->fetch();

    if (!$page) {
        Response::error('Page not found', 404);
        return;
    }

    $sec = $db->prepare("SELECT id, type, widget_type, title, subtitle, content, image, config, sort_order FROM sections WHERE page_id = :pid AND is_active = 1 ORDER BY sort_order ASC");
    $sec->execute(['pid' => $page['id']]);
    $sections = $sec->fetchAll();

    $rendered = [];
    foreach ($sections as $s) {
        $cfg = json_decode($s['config'] ?? '{}', true) ?: [];
        $html = '';
        if ($s['widget_type'] && WidgetRegistry::get($s['widget_type'])) {
            $html = WidgetRegistry::render($s['widget_type'], $cfg);
        } elseif (in_array($s['type'], ['custom', 'html'])) {
            $html = $s['content'] ?? '';
        } else {
            $html = '<section data-section="' . ($s['type'] ?? 'generic') . '">' . ($s['content'] ?? '') . '</section>';
        }
        $rendered[] = [
            'id' => $s['id'],
            'type' => $s['type'],
            'widget_type' => $s['widget_type'],
            'title' => $s['title'],
            'subtitle' => $s['subtitle'],
            'content' => $s['content'],
            'image' => $s['image'],
            'config' => $cfg,
            'html' => $html,
        ];
    }

    Response::json([
        'ok' => true,
        'data' => [
            'page' => $page,
            'sections' => $rendered,
        ]
    ]);
});

$router->get('/api/v1/public/settings', function () {
    $siteId = (int)($_GET['site_id'] ?? 1);
    $db = Database::instance();
    $stmt = $db->prepare("SELECT `key`, `value` FROM settings WHERE site_id = :sid");
    $stmt->execute(['sid' => $siteId]);
    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[$row['key']] = $row['value'];
    }
    Response::json(['ok' => true, 'data' => $settings]);
});
$router->get('/api/v1/health', function () {
    $dbOk = false;
    $cacheOk = is_writable(ROOT_DIR . '/cache');
    try {
        Database::instance()->query('SELECT 1');
        $dbOk = true;
    } catch (\Exception $e) {}
    Response::json([
        'ok' => true,
        'app' => 'Wontia Web Intelligence',
        'version' => '1.0.0',
        'db' => $dbOk,
        'cache' => $cacheOk,
        'php' => PHP_VERSION,
    ]);
});

$router->post('/api/v1/admin/auth/login', [\App\Controllers\Admin\AuthController::class, 'login']);

$router->group('/api/v1/admin', function (Router $r) {
    $r->post('/auth/logout', [\App\Controllers\Admin\AuthController::class, 'logout']);
    $r->get('/auth/me', [\App\Controllers\Admin\AuthController::class, 'me']);

    $r->get('/dashboard', [\App\Controllers\Admin\DashboardController::class, 'index']);

    $r->get('/pages', [\App\Controllers\Admin\PageController::class, 'index']);
    $r->get('/pages/{id}', [\App\Controllers\Admin\PageController::class, 'show']);
    $r->post('/pages', [\App\Controllers\Admin\PageController::class, 'store']);
    $r->put('/pages/{id}', [\App\Controllers\Admin\PageController::class, 'update']);
    $r->delete('/pages/{id}', [\App\Controllers\Admin\PageController::class, 'destroy']);

    $r->get('/pages/{pageId}/sections', [\App\Controllers\Admin\SectionController::class, 'index']);
    $r->post('/pages/{pageId}/sections', [\App\Controllers\Admin\SectionController::class, 'store']);
    $r->put('/sections/{id}', [\App\Controllers\Admin\SectionController::class, 'update']);
    $r->delete('/sections/{id}', [\App\Controllers\Admin\SectionController::class, 'destroy']);
    $r->put('/sections/reorder', [\App\Controllers\Admin\SectionController::class, 'reorder']);

    $r->get('/bricks', [\App\Controllers\Admin\BrickController::class, 'index']);
    $r->get('/bricks/{type}', [\App\Controllers\Admin\BrickController::class, 'show']);

    $r->get('/brickhub', [\App\Controllers\Admin\BrickHubController::class, 'marketplace']);
    $r->get('/brickhub/sources', [\App\Controllers\Admin\BrickHubController::class, 'sources']);
    $r->post('/brickhub/sources', [\App\Controllers\Admin\BrickHubController::class, 'addSource']);
    $r->delete('/brickhub/sources/{id}', [\App\Controllers\Admin\BrickHubController::class, 'removeSource']);
    $r->post('/brickhub/sources/verify', [\App\Controllers\Admin\BrickHubController::class, 'verifySource']);
    $r->get('/brickhub/sources/{sourceId}/discover', [\App\Controllers\Admin\BrickHubController::class, 'discoverBricks']);
    $r->post('/brickhub/sync', [\App\Controllers\Admin\BrickHubController::class, 'sync']);
    $r->post('/brickhub/install', [\App\Controllers\Admin\BrickHubController::class, 'install']);
    $r->delete('/brickhub/uninstall/{id}', [\App\Controllers\Admin\BrickHubController::class, 'uninstall']);
    $r->get('/brickhub/updates', [\App\Controllers\Admin\BrickHubController::class, 'checkUpdates']);
    $r->post('/brickhub/updates/apply/{brickId}', [\App\Controllers\Admin\BrickHubController::class, 'applyUpdate']);
    $r->post('/brickhub/updates/apply-all', [\App\Controllers\Admin\BrickHubController::class, 'applyAllUpdates']);
    $r->get('/brickhub/history', [\App\Controllers\Admin\BrickHubController::class, 'updateHistory']);
    $r->get('/brickhub/installed', [\App\Controllers\Admin\BrickHubController::class, 'installedBricks']);
    $r->get('/brickhub/check/{brickId}', [\App\Controllers\Admin\BrickHubController::class, 'checkBrickUpdate']);
    $r->post('/brickhub/broadcast/{slug}', [\App\Controllers\Admin\BrickHubController::class, 'broadcastUpdate']);
    $r->post('/brickhub/scan-local', [\App\Controllers\Admin\BrickHubController::class, 'scanLocal']);
    $r->post('/brickhub/ensure-tables', [\App\Controllers\Admin\BrickHubController::class, 'ensureTables']);
    $r->post('/brickhub/setup', [\App\Controllers\Admin\BrickHubController::class, 'fullSetup']);

    $r->get('/brickhub/notifications', [\App\Controllers\Admin\BrickHubController::class, 'pendingNotifications']);
    $r->post('/brickhub/auto-check', [\App\Controllers\Admin\BrickHubController::class, 'autoCheck']);
    $r->post('/brickhub/push/{brickId}', [\App\Controllers\Admin\BrickHubController::class, 'pushToSites']);
    $r->get('/brickhub/registry', [\App\Controllers\Admin\BrickHubController::class, 'registeredSites']);

    $r->get('/brickhub/mother/pending', [\App\Controllers\Admin\BrickHubController::class, 'motherPending']);
    $r->post('/brickhub/child/notify', [\App\Controllers\Admin\BrickHubController::class, 'childNotify']);
    $r->post('/brickhub/child/register', [\App\Controllers\Admin\BrickHubController::class, 'registerChildSite']);

    $r->get('/media', [\App\Controllers\Admin\MediaController::class, 'index']);
    $r->post('/media/upload', [\App\Controllers\Admin\MediaController::class, 'upload']);
    $r->delete('/media/{id}', [\App\Controllers\Admin\MediaController::class, 'destroy']);

    $r->get('/blog/posts', [\App\Controllers\Admin\BlogController::class, 'index']);
    $r->get('/blog/posts/{id}', [\App\Controllers\Admin\BlogController::class, 'show']);
    $r->post('/blog/posts', [\App\Controllers\Admin\BlogController::class, 'store']);
    $r->put('/blog/posts/{id}', [\App\Controllers\Admin\BlogController::class, 'update']);
    $r->delete('/blog/posts/{id}', [\App\Controllers\Admin\BlogController::class, 'destroy']);
    $r->patch('/blog/posts/{id}/status', [\App\Controllers\Admin\BlogController::class, 'toggleStatus']);
    $r->post('/blog/polish', [\App\Controllers\Admin\BlogController::class, 'polish']);

    $r->get('/blog/categories', [\App\Controllers\Admin\BlogCategoryController::class, 'index']);
    $r->post('/blog/categories', [\App\Controllers\Admin\BlogCategoryController::class, 'store']);
    $r->put('/blog/categories/{id}', [\App\Controllers\Admin\BlogCategoryController::class, 'update']);
    $r->delete('/blog/categories/{id}', [\App\Controllers\Admin\BlogCategoryController::class, 'destroy']);

    $r->get('/blog/tags', [\App\Controllers\Admin\BlogTagController::class, 'index']);
    $r->post('/blog/tags', [\App\Controllers\Admin\BlogTagController::class, 'store']);
    $r->delete('/blog/tags/{id}', [\App\Controllers\Admin\BlogTagController::class, 'destroy']);

    $r->get('/seo', [\App\Controllers\Admin\SeoController::class, 'index']);
    $r->post('/seo/audit', [\App\Controllers\Admin\SeoController::class, 'audit']);

    $r->get('/analytics', [\App\Controllers\Admin\AnalyticsController::class, 'index']);
    $r->put('/analytics/ga4', [\App\Controllers\Admin\AnalyticsController::class, 'updateGa4']);

    $r->get('/settings', [\App\Controllers\Admin\SettingsController::class, 'index']);
    $r->put('/settings', [\App\Controllers\Admin\SettingsController::class, 'update']);

    $r->get('/users', [\App\Controllers\Admin\UserController::class, 'index']);
    $r->post('/users', [\App\Controllers\Admin\UserController::class, 'store']);
    $r->put('/users/{id}', [\App\Controllers\Admin\UserController::class, 'update']);
    $r->delete('/users/{id}', [\App\Controllers\Admin\UserController::class, 'destroy']);
}, [AuthMiddleware::class]);

$router->dispatch($method, $uri);
