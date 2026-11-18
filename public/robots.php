<?php
if (!defined('ROOT_DIR')) define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/vendor/autoload.php';
\App\Core\Config::load();

// Consolida www -> dominio canónico también en rutas servidas por nginx.
try {
    $__seoBase = rtrim((string)\App\Bricks\SeoGlobalLaunch\SeoGlobalLaunchBrick::siteUrl(), '/');
    $__seoHost = strtolower((string)parse_url($__seoBase, PHP_URL_HOST));
    $__reqHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($__seoHost !== '' && $__reqHost === 'www.' . $__seoHost) {
        header('Location: ' . $__seoBase . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }
} catch (\Throwable $e) {
}

$robots = '';
try {
    $robots = \App\Bricks\SeoGlobalLaunch\SeoGlobalLaunchBrick::robotsTxt();
} catch (\Throwable $e) {
    $robots = '';
}
header('Content-Type: text/plain; charset=utf-8');
if ($robots !== '') {
    header('X-SEO-Source: seo-global-launch');
    echo $robots;
    exit;
}
$__host = (string)($_SERVER['HTTP_HOST'] ?? '');
$__scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) ? 'https' : 'http';
$__base = $__host !== '' ? $__scheme . '://' . $__host : rtrim((string)\App\Core\Config::get('APP_URL', 'https://sonika.com.co'), '/');
echo "User-agent: *\n";
foreach (['/admin', '/admin.php', '/admin-', '/api/', '/install.php', '/mi-antro', '/artista/contrato', '/checkout', '/carrito', '/pedido/', '/cuenta', '/r/'] as $__path) {
    echo 'Disallow: ' . $__path . "\n";
}
echo "Sitemap: " . $__base . "/sitemap.xml\n";
