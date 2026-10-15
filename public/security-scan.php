<?php
/**
 * Robot de escaneo de seguridad (CLI). Genera el informe y lo guarda.
 * Lo ejecuta el cron.   php /app/public/security-scan.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/vendor/autoload.php';
\App\Core\Config::load();
$svc = new \App\Services\SecurityScanService();
$r = $svc->scan();
$ok = $svc->save($r);
echo 'SECURITY ' . ($ok ? 'OK' : 'FAIL') . ': score=' . $r['score'] . ' criticos=' . $r['critical'] . ' hallazgos=' . count($r['findings']) . "\n";
