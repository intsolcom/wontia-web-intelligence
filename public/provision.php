<?php
/**
 * WWI — Alta de cliente nuevo (CLI). Delega en FactoryService::onboardClient().
 * Uso:
 *   docker exec wontia-wwi php /app/public/provision.php \
 *     --client="Panadería La Esquina" --email=dueno@dominio.com \
 *     --domain=panaderia.com --plan=starter --theme=default [--sector=restaurant]
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/vendor/autoload.php';
\App\Core\Config::load();

function wwi_arg(string $name, $def = null) {
    foreach ($GLOBALS['argv'] as $i => $v) {
        if ($v === "--$name") return $GLOBALS['argv'][$i + 1] ?? true;
        if (strpos($v, "--$name=") === 0) return substr($v, strlen($name) + 3);
    }
    return $def;
}

$r = (new \App\Services\FactoryService())->onboardClient([
    'client' => (string)wwi_arg('client', ''),
    'email' => (string)wwi_arg('email', ''),
    'domain' => (string)wwi_arg('domain', ''),
    'plan' => (string)wwi_arg('plan', ''),
    'theme' => (string)wwi_arg('theme', 'default'),
    'sector' => (string)wwi_arg('sector', ''),
]);
echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(empty($r['ok']) ? 1 : 0);
