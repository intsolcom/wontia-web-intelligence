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
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isProtected = str_starts_with($path, '/api/v1/admin') || str_starts_with($path, '/api/v1/brick');
$origin = rtrim((string)Config::get('APP_URL', '*'), '/');
header('Access-Control-Allow-Origin: ' . ($isProtected ? ($origin ?: '*') : '*'));
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Brick-Key, X-Requested-With');
header('Vary: Origin');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

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

$router->get('/api/v1/public/plans', [\App\Controllers\Admin\FactoryController::class, 'publicPlans']);
$router->get('/api/v1/public/plans/{slug}', [\App\Controllers\Admin\FactoryController::class, 'publicPlan']);
$router->get('/api/v1/public/templates', [\App\Controllers\Admin\FactoryController::class, 'publicTemplates']);
$router->post('/api/v1/public/orders', [\App\Controllers\Admin\FactoryController::class, 'publicCreateOrder']);
$router->get('/api/v1/public/orders/{uuid}', [\App\Controllers\Admin\FactoryController::class, 'publicOrderStatus']);
$router->post('/api/v1/public/payments/webhook', [\App\Controllers\Admin\FactoryController::class, 'paymentWebhook']);
$router->get('/api/v1/public/payment-mode', [\App\Controllers\Admin\FactoryController::class, 'publicPaymentMode']);
$router->post('/api/v1/public/payments/dummy/{uuid}', [\App\Controllers\Admin\FactoryController::class, 'publicDummyPay']);
$router->post('/api/v1/public/briefs', [\App\Controllers\Admin\FactoryController::class, 'publicCreateBrief']);
$router->get('/api/v1/public/previews/attempts', [\App\Controllers\Admin\FactoryController::class, 'publicPreviewAttempts']);
$router->post('/api/v1/public/previews', [\App\Controllers\Admin\FactoryController::class, 'publicCreatePreview']);
$router->post('/api/v1/public/previews/from-template', [\App\Controllers\Admin\FactoryController::class, 'publicPreviewFromTemplate']);
$router->get('/api/v1/public/previews/{uuid}', [\App\Controllers\Admin\FactoryController::class, 'publicPreviewStatus']);
$router->get('/api/v1/public/preview/{uuid}', [\App\Controllers\Admin\FactoryController::class, 'publicPreviewRender']);
$router->post('/api/v1/public/previews/suggest-domains', [\App\Controllers\Admin\FactoryController::class, 'publicSuggestDomains']);
$router->get('/api/v1/public/domain/check', function ($request) {
    $name = (string)($request->get('name', ''));
    Response::json(['ok' => true, 'data' => (new \App\Services\DomainCheckerService())->check($name)]);
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

$router->get('/api/v1/brick/health', function () {
    $db = \App\Core\Database::instance();
    $providers = 0;
    try {
        $providers = (int)$db->query("SELECT COUNT(*) FROM ai_providers WHERE site_id = @site_id")->fetchColumn();
    } catch (\Exception $e) {}
    Response::json([
        'ok' => true,
        'component' => 'BRICK',
        'version' => '1.0.0',
        'db' => $providers > 0 || $providers === 0,
        'providers_registered' => $providers,
    ]);
});

$router->group('/api/v1/brick', function (Router $r) {
    $r->post('/request', [\App\Controllers\Admin\AiBrickController::class, 'aiRequest']);
    $r->post('/command', [\App\Controllers\Admin\AiBrickController::class, 'command']);
}, [\App\Middleware\BrickKeyMiddleware::class]);

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
    $r->get('/sections/{id}', [\App\Controllers\Admin\SectionController::class, 'show']);
    $r->post('/pages/{pageId}/sections', [\App\Controllers\Admin\SectionController::class, 'store']);
    $r->put('/sections/{id}', [\App\Controllers\Admin\SectionController::class, 'update']);
    $r->delete('/sections/{id}', [\App\Controllers\Admin\SectionController::class, 'destroy']);
    $r->put('/sections/reorder', [\App\Controllers\Admin\SectionController::class, 'reorder']);

    $r->get('/bricks', [\App\Controllers\Admin\BrickController::class, 'index']);
    $r->get('/bricks/usage', [\App\Controllers\Admin\BrickController::class, 'usage']);
    $r->get('/bricks/{type}', [\App\Controllers\Admin\BrickController::class, 'show']);
    $r->get('/bricks/{type}/preview', [\App\Controllers\Admin\BrickController::class, 'preview']);

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

    $r->get('/brick/overview', [\App\Controllers\Admin\AiBrickController::class, 'overview']);
    $r->get('/brick/suggestions', [\App\Controllers\Admin\AiBrickController::class, 'suggestions']);
    $r->get('/brick/providers', [\App\Controllers\Admin\AiBrickController::class, 'providers']);
    $r->post('/brick/providers', [\App\Controllers\Admin\AiBrickController::class, 'saveProvider']);
    $r->put('/brick/providers/{id}', [\App\Controllers\Admin\AiBrickController::class, 'saveProvider']);
    $r->delete('/brick/providers/{id}', [\App\Controllers\Admin\AiBrickController::class, 'deleteProvider']);
    $r->get('/brick/models', [\App\Controllers\Admin\AiBrickController::class, 'models']);
    $r->post('/brick/models', [\App\Controllers\Admin\AiBrickController::class, 'saveModel']);
    $r->put('/brick/models/{id}', [\App\Controllers\Admin\AiBrickController::class, 'saveModel']);
    $r->delete('/brick/models/{id}', [\App\Controllers\Admin\AiBrickController::class, 'deleteModel']);
    $r->get('/brick/capabilities', [\App\Controllers\Admin\AiBrickController::class, 'capabilities']);
    $r->get('/brick/instances', [\App\Controllers\Admin\AiBrickController::class, 'instances']);
    $r->get('/brick/policies', [\App\Controllers\Admin\AiBrickController::class, 'policies']);
    $r->post('/brick/policies', [\App\Controllers\Admin\AiBrickController::class, 'savePolicy']);
    $r->put('/brick/policies/{id}', [\App\Controllers\Admin\AiBrickController::class, 'savePolicy']);
    $r->delete('/brick/policies/{id}', [\App\Controllers\Admin\AiBrickController::class, 'deletePolicy']);
    $r->get('/brick/usage', [\App\Controllers\Admin\AiBrickController::class, 'usage']);
    $r->post('/brick/test', [\App\Controllers\Admin\AiBrickController::class, 'test']);
    $r->post('/brick/request', [\App\Controllers\Admin\AiBrickController::class, 'aiRequest']);
    $r->post('/brick/command', [\App\Controllers\Admin\AiBrickController::class, 'command']);
    $r->post('/brick/health/check', [\App\Controllers\Admin\AiBrickController::class, 'healthCheck']);
    $r->post('/brick/ensure-tables', [\App\Controllers\Admin\AiBrickController::class, 'ensureTables']);

    $r->get('/factory/plans', [\App\Controllers\Admin\FactoryController::class, 'plans']);
    $r->post('/factory/plans', [\App\Controllers\Admin\FactoryController::class, 'savePlan']);
    $r->put('/factory/plans/{id}', [\App\Controllers\Admin\FactoryController::class, 'savePlan']);
    $r->delete('/factory/plans/{id}', [\App\Controllers\Admin\FactoryController::class, 'deletePlan']);
    $r->get('/factory/config', [\App\Controllers\Admin\FactoryController::class, 'config']);
    $r->put('/factory/config', [\App\Controllers\Admin\FactoryController::class, 'saveConfig']);
    $r->get('/factory/margin', [\App\Controllers\Admin\FactoryController::class, 'margin']);
    $r->post('/factory/ensure-tables', [\App\Controllers\Admin\FactoryController::class, 'ensureTables']);
    $r->get('/factory/dashboard', [\App\Controllers\Admin\FactoryController::class, 'dashboard']);
    $r->get('/factory/sites', [\App\Controllers\Admin\FactoryController::class, 'sites']);
    $r->post('/factory/sites', [\App\Controllers\Admin\FactoryController::class, 'addSite']);
    $r->put('/factory/sites/{id}/status', [\App\Controllers\Admin\FactoryController::class, 'siteStatus']);
    $r->post('/factory/sites/{id}/credentials', [\App\Controllers\Admin\FactoryController::class, 'siteCredentials']);
    $r->get('/factory/domains', [\App\Controllers\Admin\FactoryController::class, 'domains']);
    $r->post('/factory/domains', [\App\Controllers\Admin\FactoryController::class, 'addDomain']);
    $r->put('/factory/domains/{id}/status', [\App\Controllers\Admin\FactoryController::class, 'domainStatus']);
    $r->get('/factory/emails', [\App\Controllers\Admin\FactoryController::class, 'emails']);
    $r->post('/factory/emails', [\App\Controllers\Admin\FactoryController::class, 'addEmail']);
    $r->put('/factory/emails/{id}/status', [\App\Controllers\Admin\FactoryController::class, 'emailStatus']);
    $r->get('/factory/orders', [\App\Controllers\Admin\FactoryController::class, 'orders']);
    $r->post('/factory/orders', [\App\Controllers\Admin\FactoryController::class, 'addOrder']);
    $r->put('/factory/orders/{id}/status', [\App\Controllers\Admin\FactoryController::class, 'orderStatus']);
    $r->get('/factory/ledger', [\App\Controllers\Admin\FactoryController::class, 'ledger']);
    $r->post('/factory/ledger', [\App\Controllers\Admin\FactoryController::class, 'addLedger']);
    $r->get('/factory/ai-usage', [\App\Controllers\Admin\FactoryController::class, 'aiUsageBySite']);
    $r->get('/factory/my-portal', [\App\Controllers\Admin\FactoryController::class, 'myPortal']);
    $r->get('/factory/jobs', [\App\Controllers\Admin\FactoryController::class, 'jobsList']);
    $r->post('/factory/jobs/run', [\App\Controllers\Admin\FactoryController::class, 'jobsRun']);
    $r->get('/factory/briefs', [\App\Controllers\Admin\FactoryController::class, 'briefsList']);
    $r->post('/system/update', [\App\Controllers\Admin\FactoryController::class, 'systemUpdate']);
    $r->get('/system/updates', [\App\Controllers\Admin\FactoryController::class, 'systemUpdates']);
    $r->get('/system/status', [\App\Controllers\Admin\FactoryController::class, 'systemStatus']);
    $r->post('/system/notify-sites', [\App\Controllers\Admin\FactoryController::class, 'systemNotifySites']);

    $r->post('/tia/command', [\App\Controllers\Admin\TiaAgentController::class, 'command']);
    $r->post('/tia/confirm', [\App\Controllers\Admin\TiaAgentController::class, 'confirm']);
    $r->get('/tia/sections', [\App\Controllers\Admin\TiaAgentController::class, 'sections']);
    $r->get('/tia/history', [\App\Controllers\Admin\TiaAgentController::class, 'history']);

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

    // SEO Global Launch brick
    $r->get('/seo-global-launch', [\App\Controllers\Admin\SeoGlobalLaunchController::class, 'overview']);
    $r->post('/seo-global-launch/activate', [\App\Controllers\Admin\SeoGlobalLaunchController::class, 'activate']);
    $r->post('/seo-global-launch/deactivate', [\App\Controllers\Admin\SeoGlobalLaunchController::class, 'deactivate']);
    $r->post('/seo-global-launch/install-tables', [\App\Controllers\Admin\SeoGlobalLaunchController::class, 'installTables']);
    $r->post('/seo-global-launch/scan', [\App\Controllers\Admin\SeoGlobalLaunchController::class, 'scan']);
    $r->post('/seo-global-launch/generate', [\App\Controllers\Admin\SeoGlobalLaunchController::class, 'generate']);
    $r->post('/seo-global-launch/autofix', [\App\Controllers\Admin\SeoGlobalLaunchController::class, 'autofix']);
    $r->post('/seo-global-launch/deepfix', [\App\Controllers\Admin\SeoGlobalLaunchController::class, 'deepfix']);
    $r->get('/seo-global-launch/pages', [\App\Controllers\Admin\SeoGlobalLaunchController::class, 'pages']);
    $r->get('/seo-global-launch/issues', [\App\Controllers\Admin\SeoGlobalLaunchController::class, 'issues']);
    $r->get('/seo-global-launch/scores', [\App\Controllers\Admin\SeoGlobalLaunchController::class, 'scores']);
    $r->get('/seo-global-launch/bots', [\App\Controllers\Admin\SeoGlobalLaunchController::class, 'bots']);

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
