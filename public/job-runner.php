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
try {
    $result['store_orders_auto_archived'] = (new \App\Services\StoreService())->autoArchiveOrders();
} catch (\Throwable $e) {
    $result['store_orders_auto_archived'] = ['error' => $e->getMessage()];
}
try {
    $__storeSettings = (new \App\Services\StoreService())->settings();
    if (($__storeSettings['partner_contract_reminder_enabled'] ?? '0') === '1') {
        $result['contract_reminders'] = (new \App\Services\ArtistContractService())->sendPendingReminders((int)($__storeSettings['partner_contract_reminder_days'] ?? 3));
    }
} catch (\Throwable $e) {
    $result['contract_reminders'] = ['error' => $e->getMessage()];
}
try {
    $result['gift_card_deliveries'] = (new \App\Services\GiftCardService())->deliverScheduled(50);
} catch (\Throwable $e) {
    $result['gift_card_deliveries'] = ['error' => $e->getMessage()];
}
try {
    $result['gift_card_expired_reservations'] = (new \App\Services\GiftCardService())->expireReservations((int)($__storeSettings['gift_card_hold_hours'] ?? 24));
} catch (\Throwable $e) {
    $result['gift_card_expired_reservations'] = ['error' => $e->getMessage()];
}
try {
    $result['coordinadora_weekly'] = (new \App\Services\CoordinadoraService())->runIfDue();
} catch (\Throwable $e) {
    $result['coordinadora_weekly'] = ['error' => $e->getMessage()];
}
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
try {
    $result['ciu_auto_sync'] = (new \App\Services\CiuService())->syncIfDue(6);
} catch (\Throwable $e) {
    $result['ciu_auto_sync'] = ['error' => $e->getMessage()];
}
try {
    $result['seo_daily_metrics'] = (new \App\Services\SeoDailyMetricsService())->rebuildIfDue(10);
} catch (\Throwable $e) {
    $result['seo_daily_metrics'] = ['error' => $e->getMessage()];
}
echo json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
