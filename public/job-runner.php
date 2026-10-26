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
$result['store_stock_released'] = (new \App\Services\StoreService())->releaseExpiredOrders(null, 60)['released'];
$result['bricks_auto_launched'] = (new \App\Services\BrickLifecycleService())->processDueLaunches()['launched'];
$result['system_auto_update'] = (new \App\Services\SystemUpdateService())->autoTick();
$result['auth_otps_purged'] = (new \App\Services\AuthSecurityService())->purgeExpiredOtps();
$result['agent_bridge'] = (new \App\Services\AgentBridgeService())->autoRespond(3);
$result['bridge_mirror'] = (new \App\Services\AgentBridgeService())->exportAll()['files'];
try {
    $result['favorites_alerts'] = (new \App\Services\FavoritesAlertService())->runIfDue(360);
} catch (\Throwable $e) {
    $result['favorites_alerts'] = ['error' => $e->getMessage()];
}
try {
    $result['reservations_expired'] = (new \App\Services\ReservationService())->expire();
    $result['cart_recovery'] = (new \App\Services\CartRecoveryService())->runIfDue(60);
} catch (\Throwable $e) {
    $result['cart_recovery'] = ['error' => $e->getMessage()];
}
try {
    $result['pqrf'] = (new \App\Services\PqrfService())->runMaintenance();
} catch (\Throwable $e) {
    $result['pqrf'] = ['error' => $e->getMessage()];
}
echo json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
