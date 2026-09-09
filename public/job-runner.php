<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/vendor/autoload.php';

\App\Core\Config::load();
$service = new \App\Services\FactoryService();
$result = $service->runDueJobs(10);
$result['previews_cleaned'] = $service->cleanupPreviews();
echo json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
