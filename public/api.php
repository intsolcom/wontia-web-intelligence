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
Session::start();

header('Content-Type: application/json; charset=utf-8');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isProtected = str_starts_with($path, '/api/v1/admin') || str_starts_with($path, '/api/v1/brick');
$origin = rtrim((string)Config::get('APP_URL', '*'), '/');
header('Access-Control-Allow-Origin: ' . ($isProtected ? ($origin ?: '*') : '*'));
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Brick-Key, X-Requested-With, Idempotency-Key');
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

$router->get('/api/v1/public/settings', function () {    $siteId = (int)($_GET['site_id'] ?? 1);
    $db = Database::instance();
    $stmt = $db->prepare("SELECT `key`, `value` FROM settings WHERE site_id = :sid");
    $stmt->execute(['sid' => $siteId]);
    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[$row['key']] = $row['value'];
    }
    Response::json(['ok' => true, 'data' => $settings]);
});

$router->post('/api/v1/public/contact', [\App\Controllers\ContactController::class, 'submit']);
$router->post('/api/v1/public/program/track', [\App\Controllers\ProgramTrackingController::class, 'track']);

$router->get('/api/v1/public/asset-version', function () {
    $js = ROOT_DIR . '/public/assets/js/admin.js';
    $css = ROOT_DIR . '/public/assets/css/admin.css';
    Response::json(['ok' => true, 'data' => [
        'js' => is_file($js) ? (int)filemtime($js) : 0,
        'css' => is_file($css) ? (int)filemtime($css) : 0,
    ]]);
});

$router->get('/api/v1/public/system/manifest', [\App\Controllers\Admin\SystemUpdateController::class, 'publicManifest']);
$router->get('/api/v1/public/system/status', [\App\Controllers\Admin\SystemUpdateController::class, 'publicStatus']);
$router->get('/api/v1/public/system/history', [\App\Controllers\Admin\SystemUpdateController::class, 'publicHistory']);
$router->post('/api/v1/public/system/update-request', [\App\Controllers\Admin\SystemUpdateController::class, 'publicUpdateRequest']);

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
    $svc = new \App\Services\DomainCheckerService();
    if (!$svc->allowCheck((string)$request->ip())) {
        Response::error('Demasiadas verificaciones seguidas. Espera un minuto e intenta de nuevo.', 429);
        return;
    }
    Response::json(['ok' => true, 'data' => $svc->check($name)]);
});

$router->post('/api/v1/public/search/ai', [\App\Controllers\SearchAiController::class, 'suggest']);
$router->post('/api/v1/public/tia/chat', [\App\Controllers\TiaChatController::class, 'chat']);

$router->post('/api/v1/public/pqrf', [\App\Controllers\PqrfPublicController::class, 'create']);
$router->get('/api/v1/public/pqrf/track', [\App\Controllers\PqrfPublicController::class, 'track']);
$router->post('/api/v1/public/pqrf/track', [\App\Controllers\PqrfPublicController::class, 'trackMessage']);
$router->get('/api/v1/public/pqrf/attachment', [\App\Controllers\PqrfPublicController::class, 'download']);

$router->post('/api/v1/public/account/register', [\App\Controllers\CustomerAccountController::class, 'register']);
$router->post('/api/v1/public/account/login', [\App\Controllers\CustomerAccountController::class, 'login']);
$router->post('/api/v1/public/account/forgot', [\App\Controllers\CustomerAccountController::class, 'forgot']);
$router->post('/api/v1/public/account/reset', [\App\Controllers\CustomerAccountController::class, 'reset']);
$router->post('/api/v1/public/account/verify-email', [\App\Controllers\CustomerAccountController::class, 'verifyEmail']);
$router->post('/api/v1/public/account/verify-email/resend', [\App\Controllers\CustomerAccountController::class, 'verifyEmailResend']);
$router->post('/api/v1/public/account/favorites/{ref}/alert', [\App\Controllers\CustomerAccountController::class, 'favoriteAlert']);
$router->get('/api/v1/public/account/reviews', [\App\Controllers\CustomerAccountController::class, 'reviews']);
$router->post('/api/v1/public/account/reviews', [\App\Controllers\CustomerAccountController::class, 'reviewAdd']);
$router->get('/api/v1/public/account/loyalty', [\App\Controllers\CustomerAccountController::class, 'loyalty']);
$router->post('/api/v1/public/account/orders/{uuid}/return', [\App\Controllers\CustomerAccountController::class, 'orderReturn']);
$router->get('/api/v1/public/account/returns', [\App\Controllers\CustomerAccountController::class, 'returns']);
$router->get('/api/v1/public/account/payment-methods', [\App\Controllers\CustomerAccountController::class, 'paymentMethods']);
$router->post('/api/v1/public/account/payment-methods', [\App\Controllers\CustomerAccountController::class, 'paymentMethodSave']);
$router->post('/api/v1/public/account/payment-methods/{id}/default', [\App\Controllers\CustomerAccountController::class, 'paymentMethodDefault']);
$router->delete('/api/v1/public/account/payment-methods/{id}', [\App\Controllers\CustomerAccountController::class, 'paymentMethodDelete']);
$router->post('/api/v1/public/account/logout', [\App\Controllers\CustomerAccountController::class, 'logout']);
$router->get('/api/v1/public/account/me', [\App\Controllers\CustomerAccountController::class, 'me']);
$router->get('/api/v1/public/account/export', [\App\Controllers\CustomerAccountController::class, 'exportData']);
$router->delete('/api/v1/public/account', [\App\Controllers\CustomerAccountController::class, 'deleteAccount']);
$router->put('/api/v1/public/account/profile', [\App\Controllers\CustomerAccountController::class, 'profile']);
$router->get('/api/v1/public/account/orders', [\App\Controllers\CustomerAccountController::class, 'orders']);
$router->get('/api/v1/public/account/addresses', [\App\Controllers\CustomerAccountController::class, 'addresses']);
$router->post('/api/v1/public/account/addresses', [\App\Controllers\CustomerAccountController::class, 'addressSave']);
$router->delete('/api/v1/public/account/addresses/{id}', [\App\Controllers\CustomerAccountController::class, 'addressDelete']);
$router->get('/api/v1/public/account/favorites', [\App\Controllers\CustomerAccountController::class, 'favorites']);
$router->post('/api/v1/public/account/favorites', [\App\Controllers\CustomerAccountController::class, 'favoriteAdd']);
$router->delete('/api/v1/public/account/favorites/{ref}', [\App\Controllers\CustomerAccountController::class, 'favoriteDelete']);
$router->get('/api/v1/public/store/config', [\App\Controllers\StorePublicController::class, 'config']);
$router->get('/api/v1/public/store/products', [\App\Controllers\StorePublicController::class, 'products']);
$router->get('/api/v1/public/store/products/{slug}', [\App\Controllers\StorePublicController::class, 'product']);
$router->get('/api/v1/public/store/categories', [\App\Controllers\StorePublicController::class, 'categories']);
$router->get('/api/v1/public/store/zones', [\App\Controllers\StorePublicController::class, 'zones']);
$router->post('/api/v1/public/store/orders', [\App\Controllers\StorePublicController::class, 'createOrder']);
$router->post('/api/v1/public/store/catalog-order', [\App\Controllers\StorePublicController::class, 'catalogOrder']);
$router->get('/api/v1/public/store/orders/{uuid}', [\App\Controllers\StorePublicController::class, 'orderStatus']);
$router->post('/api/v1/public/store/payments/wompi/webhook', [\App\Controllers\StorePublicController::class, 'wompiWebhook']);
$router->get('/api/v1/public/store/reviews', [\App\Controllers\StorePublicController::class, 'reviews']);
$router->post('/api/v1/public/store/reviews/{id}/helpful', [\App\Controllers\StorePublicController::class, 'reviewHelpful']);
$router->post('/api/v1/public/banner/event', [\App\Controllers\BannerEventController::class, 'event']);
$router->post('/api/v1/public/seo/track', [\App\Controllers\SeoTrackingController::class, 'track']);
$router->post('/api/v1/public/affiliates/register', [\App\Controllers\AffiliateController::class, 'register']);
$router->post('/api/v1/public/affiliates/status', [\App\Controllers\AffiliateController::class, 'status']);
$router->post('/api/v1/public/affiliates/otp/send', [\App\Controllers\AffiliateController::class, 'sendOtp']);
$router->post('/api/v1/public/affiliates/otp/verify', [\App\Controllers\AffiliateController::class, 'verifyOtp']);
$router->post('/api/v1/public/affiliates/document', [\App\Controllers\AffiliateController::class, 'uploadDocument']);
$router->post('/api/v1/public/affiliates/contract', [\App\Controllers\AffiliateController::class, 'contract']);
$router->post('/api/v1/public/affiliates/tip', [\App\Controllers\AffiliateController::class, 'tip']);
$router->post('/api/v1/public/affiliates/login', [\App\Controllers\AffiliateController::class, 'login']);
$router->post('/api/v1/public/affiliates/login/verify', [\App\Controllers\AffiliateController::class, 'loginVerify']);
$router->post('/api/v1/public/affiliates/recover', [\App\Controllers\AffiliateController::class, 'recoverRequest']);
$router->post('/api/v1/public/affiliates/recover/confirm', [\App\Controllers\AffiliateController::class, 'recoverConfirm']);
$router->post('/api/v1/public/affiliates/payout/request', [\App\Controllers\AffiliateController::class, 'payoutRequest']);
$router->post('/api/v1/public/affiliates/payout/confirm', [\App\Controllers\AffiliateController::class, 'payoutConfirm']);
$router->post('/api/v1/public/affiliates/media', [\App\Controllers\AffiliateController::class, 'mediaSave']);
$router->post('/api/v1/public/affiliates/media/upload', [\App\Controllers\AffiliateController::class, 'mediaUpload']);
$router->post('/api/v1/public/affiliates/cv', [\App\Controllers\AffiliateController::class, 'cvSave']);
$router->get('/api/v1/public/affiliates/session', [\App\Controllers\AffiliateController::class, 'sessionInfo']);
$router->post('/api/v1/public/affiliates/session/extend', [\App\Controllers\AffiliateController::class, 'sessionExtend']);
$router->post('/api/v1/public/affiliates/logout', [\App\Controllers\AffiliateController::class, 'logout']);
$router->post('/api/v1/public/cart/validate', [\App\Controllers\CartController::class, 'validate']);
$router->post('/api/v1/public/cart/coupon', [\App\Controllers\CartController::class, 'coupon']);
$router->post('/api/v1/public/cart/sync', [\App\Controllers\CartController::class, 'sync']);
$router->post('/api/v1/public/cart/reserve', [\App\Controllers\CartController::class, 'reserve']);
$router->post('/api/v1/public/variants/{id}/track', function ($request, $id) {
    $type = (string)$request->input('type', 'view');
    $ok = (new \App\Services\LiveEditorService())->variantTrack((int)$id, $type);
    Response::json(['ok' => $ok]);
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
$router->post('/api/v1/admin/auth/forgot', [\App\Controllers\Admin\AuthController::class, 'forgot']);
$router->post('/api/v1/admin/auth/verify-otp', [\App\Controllers\Admin\AuthController::class, 'verifyOtp']);
$router->post('/api/v1/admin/auth/reset-password', [\App\Controllers\Admin\AuthController::class, 'resetPassword']);
$router->post('/api/v1/admin/auth/2fa/verify', [\App\Controllers\Admin\AuthController::class, 'twofaVerify']);

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

$router->post('/api/v1/agent-bridge/{site}', [\App\Controllers\Admin\AgentBridgeController::class, 'agentPost']);

$router->group('/api/v1/admin', function (Router $r) {
    $r->post('/auth/logout', [\App\Controllers\Admin\AuthController::class, 'logout']);
    $r->get('/auth/me', [\App\Controllers\Admin\AuthController::class, 'me']);

    $r->get('/auth/2fa/status', [\App\Controllers\Admin\AuthController::class, 'twofaStatus']);
    $r->post('/auth/2fa/setup', [\App\Controllers\Admin\AuthController::class, 'twofaSetup']);
    $r->post('/auth/2fa/enable', [\App\Controllers\Admin\AuthController::class, 'twofaEnable']);
    $r->post('/auth/2fa/disable', [\App\Controllers\Admin\AuthController::class, 'twofaDisable']);
    $r->post('/auth/2fa/recovery/regenerate', [\App\Controllers\Admin\AuthController::class, 'twofaRecoveryRegenerate']);

    $r->get('/dashboard', [\App\Controllers\Admin\DashboardController::class, 'index']);

    $r->get('/pages', [\App\Controllers\Admin\PageController::class, 'index']);
    $r->get('/pages/{id}', [\App\Controllers\Admin\PageController::class, 'show']);
    $r->post('/pages', [\App\Controllers\Admin\PageController::class, 'store']);
    $r->put('/pages/{id}', [\App\Controllers\Admin\PageController::class, 'update']);
    $r->delete('/pages/{id}', [\App\Controllers\Admin\PageController::class, 'destroy']);

    $r->get('/pages/{pageId}/sections', [\App\Controllers\Admin\SectionController::class, 'index']);
    $r->get('/pages/{pageId}/search', [\App\Controllers\Admin\LiveEditorController::class, 'search']);
    $r->get('/editor/telemetry', [\App\Controllers\Admin\LiveEditorController::class, 'telemetry']);
    $r->post('/pages/{pageId}/sections', [\App\Controllers\Admin\SectionController::class, 'store']);
    $r->put('/sections/reorder', [\App\Controllers\Admin\SectionController::class, 'reorder']);
    $r->get('/sections/{id}/render', [\App\Controllers\Admin\SectionController::class, 'render']);
    $r->get('/sections/{id}/comments', [\App\Controllers\Admin\LiveEditorController::class, 'commentsList']);
    $r->post('/sections/{id}/comments', [\App\Controllers\Admin\LiveEditorController::class, 'commentAdd']);
    $r->get('/sections/{id}/versions', [\App\Controllers\Admin\LiveEditorController::class, 'versionsList']);
    $r->get('/sections/{id}/history', [\App\Controllers\Admin\LiveEditorController::class, 'history']);
    $r->get('/sections/{id}/variants', [\App\Controllers\Admin\LiveEditorController::class, 'variantsList']);
    $r->post('/sections/{id}/variants', [\App\Controllers\Admin\LiveEditorController::class, 'variantSave']);
    $r->delete('/variants/{id}', [\App\Controllers\Admin\LiveEditorController::class, 'variantDelete']);
    $r->put('/comments/{id}', [\App\Controllers\Admin\LiveEditorController::class, 'commentStatus']);
    $r->delete('/comments/{id}', [\App\Controllers\Admin\LiveEditorController::class, 'commentDelete']);
    $r->post('/versions/{id}/restore', [\App\Controllers\Admin\LiveEditorController::class, 'versionRestore']);
    $r->post('/presence', [\App\Controllers\Admin\LiveEditorController::class, 'presencePing']);
    $r->get('/presence', [\App\Controllers\Admin\LiveEditorController::class, 'presenceList']);
    $r->get('/source/plans', [\App\Controllers\Admin\LiveEditorController::class, 'sourcePlansList']);
    $r->put('/source/plans/{id}', [\App\Controllers\Admin\LiveEditorController::class, 'sourcePlanSave']);
    $r->get('/source/templates', [\App\Controllers\Admin\LiveEditorController::class, 'sourceTemplatesList']);
    $r->put('/source/templates/{id}', [\App\Controllers\Admin\LiveEditorController::class, 'sourceTemplateSave']);
$r->get('/sections/{id}', [\App\Controllers\Admin\SectionController::class, 'show']);
$r->put('/sections/{id}', [\App\Controllers\Admin\SectionController::class, 'update']);
$r->delete('/sections/{id}', [\App\Controllers\Admin\SectionController::class, 'destroy']);
$r->post('/sections/{id}/duplicate', [\App\Controllers\Admin\SectionController::class, 'duplicate']);
$r->get('/patterns', [\App\Controllers\Admin\SectionController::class, 'patternsList']);
$r->post('/sections/{id}/save-pattern', [\App\Controllers\Admin\SectionController::class, 'savePattern']);
$r->post('/patterns/{id}/insert', [\App\Controllers\Admin\SectionController::class, 'insertPattern']);
$r->delete('/patterns/{id}', [\App\Controllers\Admin\SectionController::class, 'deletePattern']);
$r->get('/trash', [\App\Controllers\Admin\SectionController::class, 'trashList']);
$r->post('/trash/{id}/restore', [\App\Controllers\Admin\SectionController::class, 'trashRestore']);
$r->delete('/trash/{id}', [\App\Controllers\Admin\SectionController::class, 'trashPurge']);

    $r->get('/bricks', [\App\Controllers\Admin\BrickController::class, 'index']);
    $r->get('/bricks/usage', [\App\Controllers\Admin\BrickController::class, 'usage']);
    $r->get('/bricks/metrics', [\App\Controllers\Admin\BrickController::class, 'metrics']);
    $r->get('/bricks/{type}', [\App\Controllers\Admin\BrickController::class, 'show']);
    $r->get('/bricks/{type}/config', [\App\Controllers\Admin\BrickController::class, 'config']);
    $r->post('/bricks/{type}/config', [\App\Controllers\Admin\BrickController::class, 'saveConfig']);
    $r->get('/bricks/{type}/tree', [\App\Controllers\Admin\BrickController::class, 'tree']);

    // Central de pedidos (Fase 1: lectura)
    $r->get('/orders-center/overview', [\App\Controllers\Admin\OrderCenterController::class, 'overview']);
    $r->get('/orders-center/orders', [\App\Controllers\Admin\OrderCenterController::class, 'orders']);
    $r->get('/orders-center/orders/{id}', [\App\Controllers\Admin\OrderCenterController::class, 'orderShow']);

    // Conciliación de catálogo (robot de coherencia)
    $r->get('/catalog/reconciliation', [\App\Controllers\Admin\CatalogReconciliationController::class, 'report']);
    $r->post('/catalog/reconciliation/scan', [\App\Controllers\Admin\CatalogReconciliationController::class, 'scan']);
    $r->get('/catalog/suggestions', [\App\Controllers\Admin\CatalogReconciliationController::class, 'suggestions']);
    $r->post('/catalog/classify', [\App\Controllers\Admin\CatalogReconciliationController::class, 'classify']);
    $r->post('/catalog/suggestions/apply', [\App\Controllers\Admin\CatalogReconciliationController::class, 'applySuggestions']);
    $r->post('/catalog/suggestions/reject', [\App\Controllers\Admin\CatalogReconciliationController::class, 'rejectSuggestions']);
    $r->post('/catalog/assign', [\App\Controllers\Admin\CatalogReconciliationController::class, 'assign']);

    // Robot de escaneo de seguridad
    $r->get('/security/scan', [\App\Controllers\Admin\SecurityScanController::class, 'report']);
    $r->post('/security/scan', [\App\Controllers\Admin\SecurityScanController::class, 'scan']);

    // SONIKA HERO BUILDER
    $r->get('/hero', [\App\Controllers\Admin\HeroController::class, 'index']);
    $r->post('/hero/slides', [\App\Controllers\Admin\HeroController::class, 'save']);
    $r->delete('/hero/slides/{id}', [\App\Controllers\Admin\HeroController::class, 'delete']);
    $r->post('/hero/slides/{id}/duplicate', [\App\Controllers\Admin\HeroController::class, 'duplicate']);
    $r->post('/hero/slides/{id}/publish', [\App\Controllers\Admin\HeroController::class, 'publish']);
    $r->get('/hero/slides/{id}/versions', [\App\Controllers\Admin\HeroController::class, 'versions']);
    $r->get('/hero/versions/{id}', [\App\Controllers\Admin\HeroController::class, 'version']);
    $r->post('/hero/versions/{id}/restore', [\App\Controllers\Admin\HeroController::class, 'restore']);
    $r->post('/hero/reorder', [\App\Controllers\Admin\HeroController::class, 'reorder']);
    $r->get('/bricks/{type}/preview', [\App\Controllers\Admin\BrickController::class, 'preview']);
    $r->get('/bricks/{type}/ratings', [\App\Controllers\Admin\BrickController::class, 'ratings']);
    $r->post('/bricks/{type}/rate', [\App\Controllers\Admin\BrickController::class, 'rate']);
    $r->post('/bricks/{type}/event', [\App\Controllers\Admin\BrickController::class, 'event']);
$r->get('/bricks/{type}/showcase', [\App\Controllers\Admin\BrickShowcaseController::class, 'index']);
$r->post('/bricks/{type}/showcase', [\App\Controllers\Admin\BrickShowcaseController::class, 'save']);
$r->put('/bricks/{type}/showcase/reorder', [\App\Controllers\Admin\BrickShowcaseController::class, 'reorder']);
$r->put('/bricks/{type}/showcase/{id}', [\App\Controllers\Admin\BrickShowcaseController::class, 'update']);
$r->delete('/bricks/{type}/showcase/{id}', [\App\Controllers\Admin\BrickShowcaseController::class, 'delete']);
$r->post('/bricks/{type}/showcase/{id}/cover', [\App\Controllers\Admin\BrickShowcaseController::class, 'cover']);
$r->get('/banners/stats', [\App\Controllers\Admin\BannerAdminController::class, 'stats']);
$r->get('/banners', [\App\Controllers\Admin\BannerAdminController::class, 'listBanners']);
$r->post('/banners', [\App\Controllers\Admin\BannerAdminController::class, 'createBanner']);
$r->delete('/banners/stats', [\App\Controllers\Admin\BannerAdminController::class, 'clear']);
$r->get('/banners/templates', [\App\Controllers\Admin\BannerAdminController::class, 'templates']);
$r->post('/banners/templates', [\App\Controllers\Admin\BannerAdminController::class, 'templateSave']);
$r->post('/banners/templates/{id}/apply', [\App\Controllers\Admin\BannerAdminController::class, 'templateApply']);
$r->delete('/banners/templates/{id}', [\App\Controllers\Admin\BannerAdminController::class, 'templateDelete']);
$r->get('/banners/{id}', [\App\Controllers\Admin\BannerAdminController::class, 'showBanner']);
$r->put('/banners/{id}', [\App\Controllers\Admin\BannerAdminController::class, 'saveBanner']);
$r->delete('/banners/{id}', [\App\Controllers\Admin\BannerAdminController::class, 'deleteBanner']);
$r->post('/banners/{id}/toggle', [\App\Controllers\Admin\BannerAdminController::class, 'toggleBanner']);
$r->post('/banners/{id}/header', [\App\Controllers\Admin\BannerAdminController::class, 'headerBanner']);
$r->post('/banners/{id}/duplicate', [\App\Controllers\Admin\BannerAdminController::class, 'duplicateBanner']);

    $r->get('/bricklifecycle', [\App\Controllers\Admin\BrickLifecycleController::class, 'index']);
    $r->post('/bricklifecycle/ensure-tables', [\App\Controllers\Admin\BrickLifecycleController::class, 'ensureTables']);
    $r->post('/bricklifecycle/run-due', [\App\Controllers\Admin\BrickLifecycleController::class, 'runDue']);
    $r->get('/bricklifecycle/{slug}/readiness', [\App\Controllers\Admin\BrickLifecycleController::class, 'readiness']);
    $r->get('/bricklifecycle/{slug}/events', [\App\Controllers\Admin\BrickLifecycleController::class, 'events']);
    $r->post('/bricklifecycle/{slug}/launch', [\App\Controllers\Admin\BrickLifecycleController::class, 'launch']);
    $r->post('/bricklifecycle/{slug}/schedule', [\App\Controllers\Admin\BrickLifecycleController::class, 'schedule']);
    $r->post('/bricklifecycle/{slug}/incubate', [\App\Controllers\Admin\BrickLifecycleController::class, 'incubate']);
    $r->post('/bricklifecycle/{slug}/maturity', [\App\Controllers\Admin\BrickLifecycleController::class, 'maturity']);
    $r->post('/bricklifecycle/{slug}/hide', [\App\Controllers\Admin\BrickLifecycleController::class, 'hide']);
    $r->post('/bricklifecycle/{slug}/interest', [\App\Controllers\Admin\BrickLifecycleController::class, 'interest']);

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
    $r->post('/factory/mail/sync', [\App\Controllers\Admin\FactoryController::class, 'syncMail']);
    $r->get('/factory/margin', [\App\Controllers\Admin\FactoryController::class, 'margin']);
    $r->post('/factory/ensure-tables', [\App\Controllers\Admin\FactoryController::class, 'ensureTables']);
    $r->get('/factory/dashboard', [\App\Controllers\Admin\FactoryController::class, 'dashboard']);
    $r->get('/factory/sites', [\App\Controllers\Admin\FactoryController::class, 'sites']);
    $r->post('/factory/sites', [\App\Controllers\Admin\FactoryController::class, 'addSite']);
    $r->post('/factory/provision', [\App\Controllers\Admin\FactoryController::class, 'provision']);
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
    $r->get('/system/update/overview', [\App\Controllers\Admin\SystemUpdateController::class, 'overview']);
    $r->post('/system/update/check', [\App\Controllers\Admin\SystemUpdateController::class, 'check']);
    $r->post('/system/update/apply', [\App\Controllers\Admin\SystemUpdateController::class, 'apply']);
    $r->get('/system/update/status', [\App\Controllers\Admin\SystemUpdateController::class, 'status']);
    $r->get('/system/update/history', [\App\Controllers\Admin\SystemUpdateController::class, 'history']);
    $r->post('/system/update/settings', [\App\Controllers\Admin\SystemUpdateController::class, 'saveSettings']);
    $r->post('/system/update/secret', [\App\Controllers\Admin\SystemUpdateController::class, 'regenerateSecret']);

    $r->post('/tia/command', [\App\Controllers\Admin\TiaAgentController::class, 'command']);
    $r->post('/tia/confirm', [\App\Controllers\Admin\TiaAgentController::class, 'confirm']);
    $r->get('/tia/sections', [\App\Controllers\Admin\TiaAgentController::class, 'sections']);
    $r->get('/tia/history', [\App\Controllers\Admin\TiaAgentController::class, 'history']);

    $r->get('/store/overview', [\App\Controllers\Admin\StoreController::class, 'overview']);
    $r->post('/store/ensure-tables', [\App\Controllers\Admin\StoreController::class, 'ensureTables']);
    $r->post('/store/maintenance', [\App\Controllers\Admin\StoreController::class, 'maintenance']);
    $r->get('/store/products', [\App\Controllers\Admin\StoreController::class, 'products']);
    $r->post('/store/products', [\App\Controllers\Admin\StoreController::class, 'productSave']);
    $r->get('/store/products/{id}', [\App\Controllers\Admin\StoreController::class, 'productShow']);
    $r->put('/store/products/{id}', [\App\Controllers\Admin\StoreController::class, 'productSave']);
    $r->delete('/store/products/{id}', [\App\Controllers\Admin\StoreController::class, 'productDelete']);
    $r->get('/store/categories', [\App\Controllers\Admin\StoreController::class, 'categories']);
    $r->post('/store/categories', [\App\Controllers\Admin\StoreController::class, 'categorySave']);
    $r->put('/store/categories/{id}', [\App\Controllers\Admin\StoreController::class, 'categorySave']);
    $r->delete('/store/categories/{id}', [\App\Controllers\Admin\StoreController::class, 'categoryDelete']);
    $r->get('/store/orders', [\App\Controllers\Admin\StoreController::class, 'orders']);
    $r->get('/store/orders/{id}', [\App\Controllers\Admin\StoreController::class, 'orderShow']);
    $r->put('/store/orders/{id}', [\App\Controllers\Admin\StoreController::class, 'orderUpdate']);
    $r->get('/store/zones', [\App\Controllers\Admin\StoreController::class, 'zones']);
    $r->post('/store/zones', [\App\Controllers\Admin\StoreController::class, 'zoneSave']);
    $r->put('/store/zones/{id}', [\App\Controllers\Admin\StoreController::class, 'zoneSave']);
    $r->delete('/store/zones/{id}', [\App\Controllers\Admin\StoreController::class, 'zoneDelete']);
$r->get('/store/settings', [\App\Controllers\Admin\StoreController::class, 'settings']);
$r->put('/store/settings', [\App\Controllers\Admin\StoreController::class, 'settingsSave']);
$r->get('/store/reviews', [\App\Controllers\Admin\StoreController::class, 'reviews']);
$r->patch('/store/reviews/{id}', [\App\Controllers\Admin\StoreController::class, 'reviewModerate']);
$r->delete('/store/reviews/{id}', [\App\Controllers\Admin\StoreController::class, 'reviewDelete']);
$r->get('/store/returns', [\App\Controllers\Admin\StoreController::class, 'returns']);
$r->patch('/store/returns/{id}', [\App\Controllers\Admin\StoreController::class, 'returnModerate']);
$r->get('/store/coupons', [\App\Controllers\Admin\StoreController::class, 'coupons']);
$r->post('/store/coupons', [\App\Controllers\Admin\StoreController::class, 'couponSave']);
$r->put('/store/coupons/{id}', [\App\Controllers\Admin\StoreController::class, 'couponSave']);
$r->delete('/store/coupons/{id}', [\App\Controllers\Admin\StoreController::class, 'couponDelete']);

    $r->get('/builder/tree', [\App\Controllers\Admin\BuilderController::class, 'tree']);
    $r->get('/builder/palette', [\App\Controllers\Admin\BuilderController::class, 'palette']);
    $r->post('/builder/ensure-tables', [\App\Controllers\Admin\BuilderController::class, 'ensureTables']);
    $r->post('/builder/rows', [\App\Controllers\Admin\BuilderController::class, 'createRow']);
    $r->patch('/builder/{type}/node/{id}', [\App\Controllers\Admin\BuilderController::class, 'updateNode']);
    $r->delete('/builder/{type}/node/{id}', [\App\Controllers\Admin\BuilderController::class, 'deleteNode']);
    $r->post('/builder/columns', [\App\Controllers\Admin\BuilderController::class, 'createColumn']);
    $r->post('/builder/blocks', [\App\Controllers\Admin\BuilderController::class, 'createBlock']);
    $r->patch('/builder/blocks/{id}', [\App\Controllers\Admin\BuilderController::class, 'updateBlock']);
    $r->post('/builder/blocks/{id}/duplicate', [\App\Controllers\Admin\BuilderController::class, 'duplicateBlock']);
    $r->post('/builder/blocks/{id}/move', [\App\Controllers\Admin\BuilderController::class, 'moveBlock']);
    $r->post('/builder/reorder/{type}', [\App\Controllers\Admin\BuilderController::class, 'reorder']);
    $r->post('/builder/convert', [\App\Controllers\Admin\BuilderController::class, 'convert']);
    $r->post('/builder/publish', [\App\Controllers\Admin\BuilderController::class, 'publish']);
    $r->get('/builder/revisions', [\App\Controllers\Admin\BuilderController::class, 'revisions']);
    $r->post('/builder/revisions/{id}/restore', [\App\Controllers\Admin\BuilderController::class, 'restoreRevision']);
    $r->get('/builder/trash', [\App\Controllers\Admin\BuilderController::class, 'trash']);
    $r->post('/builder/trash/{id}/restore', [\App\Controllers\Admin\BuilderController::class, 'restoreTrash']);
    $r->get('/builder/render', [\App\Controllers\Admin\BuilderController::class, 'render']);
$r->get('/builder/blocks/{id}/history', [\App\Controllers\Admin\BuilderController::class, 'blockHistory']);
$r->post('/builder/history/{id}/restore', [\App\Controllers\Admin\BuilderController::class, 'restoreBlockHistory']);
$r->get('/builder/components', [\App\Controllers\Admin\BuilderController::class, 'components']);
$r->post('/builder/components', [\App\Controllers\Admin\BuilderController::class, 'saveComponent']);
$r->delete('/builder/components/{id}', [\App\Controllers\Admin\BuilderController::class, 'deleteComponent']);
$r->get('/builder/comments', [\App\Controllers\Admin\BuilderController::class, 'commentsList']);
$r->post('/builder/comments', [\App\Controllers\Admin\BuilderController::class, 'commentAdd']);
$r->patch('/builder/comments/{id}', [\App\Controllers\Admin\BuilderController::class, 'commentStatus']);
$r->delete('/builder/comments/{id}', [\App\Controllers\Admin\BuilderController::class, 'commentDelete']);

$r->get('/media', [\App\Controllers\Admin\MediaController::class, 'index']);    $r->post('/media/upload', [\App\Controllers\Admin\MediaController::class, 'upload']);
$r->get('/media/folders', [\App\Controllers\Admin\MediaController::class, 'folders']);
$r->post('/media/folders', [\App\Controllers\Admin\MediaController::class, 'folderSave']);
$r->put('/media/folders/{id}', [\App\Controllers\Admin\MediaController::class, 'folderSave']);
$r->delete('/media/folders/{id}', [\App\Controllers\Admin\MediaController::class, 'folderDelete']);
$r->get('/media/stats', [\App\Controllers\Admin\MediaController::class, 'stats']);
$r->post('/media/sync-brand', [\App\Controllers\Admin\MediaController::class, 'syncBrand']);
$r->get('/media/{id}/usage', [\App\Controllers\Admin\MediaController::class, 'usage']);
$r->post('/media/{id}/restore', [\App\Controllers\Admin\MediaController::class, 'restore']);
$r->post('/media/{id}/move', [\App\Controllers\Admin\MediaController::class, 'move']);
$r->put('/media/{id}/alt', [\App\Controllers\Admin\MediaController::class, 'saveAlt']);
$r->delete('/media/{id}', [\App\Controllers\Admin\MediaController::class, 'destroy']);
$r->delete('/media/{id}/purge', [\App\Controllers\Admin\MediaController::class, 'purge']);

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
    $r->get('/seo/tracking', [\App\Controllers\SeoTrackingController::class, 'stats']);
    $r->get('/affiliates', [\App\Controllers\Admin\AffiliateAdminController::class, 'index']);
    $r->post('/affiliates/{id}/status', [\App\Controllers\Admin\AffiliateAdminController::class, 'status']);

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
    $r->get('/search/analytics', [\App\Controllers\Admin\SearchAnalyticsController::class, 'index']);
    $r->put('/analytics/ga4', [\App\Controllers\Admin\AnalyticsController::class, 'updateGa4']);

    $r->get('/settings', [\App\Controllers\Admin\SettingsController::class, 'index']);
    $r->put('/settings', [\App\Controllers\Admin\SettingsController::class, 'update']);
    $r->get('/cache', [\App\Controllers\Admin\CacheController::class, 'overview']);
    $r->post('/cache/purge', [\App\Controllers\Admin\CacheController::class, 'purge']);
    $r->get('/themes', [\App\Controllers\Admin\SettingsController::class, 'themes']);
    $r->put('/themes/active', [\App\Controllers\Admin\SettingsController::class, 'setTheme']);

    $r->get('/themes/marketplace', [\App\Controllers\Admin\ThemeController::class, 'marketplace']);
    $r->post('/themes/activate', [\App\Controllers\Admin\ThemeController::class, 'activate']);
    $r->post('/themes/redeem', [\App\Controllers\Admin\ThemeController::class, 'redeem']);
    $r->post('/themes/serials', [\App\Controllers\Admin\ThemeController::class, 'generateSerial']);
    $r->delete('/themes/serials/{id}', [\App\Controllers\Admin\ThemeController::class, 'revokeSerial']);
    $r->get('/themes/events', [\App\Controllers\Admin\ThemeController::class, 'events']);
    $r->get('/themes/{slug}/config', [\App\Controllers\Admin\ThemeController::class, 'config']);
    $r->put('/themes/{slug}/config', [\App\Controllers\Admin\ThemeController::class, 'saveConfig']);

    $r->get('/nav', [\App\Controllers\Admin\NavController::class, 'index']);
    $r->post('/nav/items', [\App\Controllers\Admin\NavController::class, 'saveItem']);
    $r->delete('/nav/items/{id}', [\App\Controllers\Admin\NavController::class, 'deleteItem']);
    $r->post('/nav/reorder', [\App\Controllers\Admin\NavController::class, 'reorder']);
$r->get('/nav/style', [\App\Controllers\Admin\NavController::class, 'style']);
$r->post('/nav/style', [\App\Controllers\Admin\NavController::class, 'saveStyle']);

    $r->get('/agent-bridge', [\App\Controllers\Admin\AgentBridgeController::class, 'index']);
    $r->get('/agent-bridge/export', [\App\Controllers\Admin\AgentBridgeController::class, 'export']);
    $r->get('/agent-bridge/tasks', [\App\Controllers\Admin\AgentBridgeController::class, 'tasks']);
    $r->patch('/agent-bridge/tasks/{id}', [\App\Controllers\Admin\AgentBridgeController::class, 'taskStatus']);
    $r->post('/agent-bridge', [\App\Controllers\Admin\AgentBridgeController::class, 'post']);
    $r->patch('/agent-bridge/{id}', [\App\Controllers\Admin\AgentBridgeController::class, 'setStatus']);
    $r->get('/agent-bridge/agents', [\App\Controllers\Admin\AgentBridgeController::class, 'agents']);
    $r->post('/agent-bridge/agents', [\App\Controllers\Admin\AgentBridgeController::class, 'createAgent']);

    $r->get('/users', [\App\Controllers\Admin\UserController::class, 'index']);
    $r->post('/users', [\App\Controllers\Admin\UserController::class, 'store']);
    $r->put('/users/{id}', [\App\Controllers\Admin\UserController::class, 'update']);
    $r->delete('/users/{id}', [\App\Controllers\Admin\UserController::class, 'destroy']);

    $r->get('/program/analytics', [\App\Controllers\ProgramTrackingController::class, 'analytics']);
    $r->get('/program/export', [\App\Controllers\ProgramTrackingController::class, 'export']);
}, [AuthMiddleware::class]);

$router->dispatch($method, $uri);
