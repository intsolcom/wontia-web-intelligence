<?php
if (!defined('ROOT_DIR')) define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/vendor/autoload.php';
\App\Core\Config::load();

$page = max(1, (int)($_GET['page'] ?? 1));
try {
    $xml = \App\Bricks\SeoGlobalLaunch\SeoGlobalLaunchBrick::sitemapXml($page);
} catch (\Throwable $e) {
    $xml = '';
}
if ($xml === '') {
    http_response_code(404);
    exit;
}
header('Content-Type: application/xml; charset=utf-8');
header('X-SEO-Source: seo-global-launch');
echo $xml;
