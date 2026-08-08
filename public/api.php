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
