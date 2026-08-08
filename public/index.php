<?php
/**
 * Wontia Web Intelligence - Front Controller
 */

define('WONTIA_START', microtime(true));
define('ROOT_DIR', dirname(__DIR__));
define('APP_ENV', getenv('APP_ENV') ?: 'production');

require_once ROOT_DIR . '/vendor/autoload.php';

use App\Core\App;

$app = new App();
$app->boot();
$app->dispatch();
