<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/vendor/autoload.php';

\App\Core\Config::load();

$force = in_array('--force', $argv ?? [], true);
$svc = new \App\Services\FavoritesAlertService();
$result = $force ? $svc->run() : $svc->runIfDue(0);
echo json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
