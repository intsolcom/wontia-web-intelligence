<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/vendor/autoload.php';

\App\Core\Config::load();

$force = in_array('--force', $argv ?? [], true);
$svc = new \App\Services\CartRecoveryService();
echo json_encode($force ? $svc->run() : $svc->runIfDue(0), JSON_UNESCAPED_UNICODE) . "\n";
