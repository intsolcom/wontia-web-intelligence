<?php
/**
 * Auditor de imágenes (CLI) — pre-calcula el score de las imágenes de categoría
 * para que el primer render no descargue. Lo ejecuta el cron tras cada sync.
 *   php /app/public/audit-images.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/vendor/autoload.php';

\App\Core\Config::load();
$theme = 'default';
try {
    $theme = (string)(\App\Core\Database::instance()->query("SELECT theme FROM sites WHERE id = @site_id")->fetchColumn() ?: 'default');
} catch (\Throwable $e) {
}
$theme = preg_replace('/[^a-z0-9_-]/i', '', $theme) ?: 'default';
$lib = ROOT_DIR . '/templates/themes/' . $theme . '/lib.php';
if (!is_file($lib)) { echo "AUDIT: sin lib de tema\n"; exit; }
require_once $lib;
if (!function_exists('sonika_image_audit') || !function_exists('sonika_categories')) { echo "AUDIT: sin helpers\n"; exit; }

$urls = [];
if (function_exists('wwi_tc')) {
    foreach ((array)wwi_tc('sonika', 'home_cat_images', []) as $r) {
        if (!is_array($r) || empty($r['image'])) continue;
        $v = trim((string)$r['image']);
        if (str_starts_with($v, 'http') || str_starts_with($v, '/')) { $urls[] = $v; continue; }
        if (function_exists('sonika_find')) { $p = sonika_find($v); if ($p) { $u = sonika_img($p, 700); if ($u !== '') $urls[] = $u; } }
    }
}
foreach (sonika_categories() as $c) {
    $n = 0;
    foreach (sonika_category_products($c['slug']) as $p) {
        $u = sonika_img($p, 700);
        if ($u === '') continue;
        $urls[] = $u;
        if (++$n >= 12) break;
    }
}
$urls = array_values(array_unique($urls));
$t = microtime(true);
$n = 0;
foreach ($urls as $u) { sonika_image_audit($u); $n++; }
echo 'AUDIT OK: ' . $n . ' imágenes en ' . round(microtime(true) - $t, 1) . "s\n";
