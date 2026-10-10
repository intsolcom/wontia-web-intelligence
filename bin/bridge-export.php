<?php
/** WWI — Exporta los puentes de agentes a agent-bridges/<site>.md (espejo/versionado en git). */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/vendor/autoload.php';
\App\Core\Config::load();
$r = (new \App\Services\AgentBridgeService())->exportAll();
echo json_encode($r, JSON_UNESCAPED_SLASHES) . PHP_EOL;
