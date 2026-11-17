<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/vendor/autoload.php';

\App\Core\Config::load();
$service = new \App\Services\CiuService();
$force = in_array('--force', $argv ?? [], true);
$result = $force ? $service->syncAll() : $service->syncIfDue(6);
echo json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
