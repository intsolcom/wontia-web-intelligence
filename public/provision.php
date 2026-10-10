<?php
/**
 * WWI — Alta de cliente nuevo (CLI). Uso:
 *   docker exec wontia-wwi php /app/public/provision.php \
 *     --client="Panadería La Esquina" --email=dueno@dominio.com \
 *     --domain=panaderia.com --plan=starter --theme=default [--sector=restaurant]
 *
 * Crea el pedido, provisiona el tenant (site), fija tema/nombre, genera
 * credenciales del cliente y dispara el deploy. Idempotente por dominio.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/vendor/autoload.php';
\App\Core\Config::load();

use App\Core\Database;
use App\Services\FactoryService;

function wwi_arg(string $name, $def = null) {
    foreach ($GLOBALS['argv'] as $i => $v) {
        if ($v === "--$name") return $GLOBALS['argv'][$i + 1] ?? true;
        if (strpos($v, "--$name=") === 0) return substr($v, strlen($name) + 3);
    }
    return $def;
}

$client   = trim((string)wwi_arg('client', ''));
$email    = trim((string)wwi_arg('email', ''));
$domain   = trim((string)wwi_arg('domain', ''));
$planSlug = trim((string)wwi_arg('plan', ''));
$theme    = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)wwi_arg('theme', 'default'))) ?: 'default';
$sector   = trim((string)wwi_arg('sector', ''));

if ($client === '' || $email === '') {
    fwrite(STDERR, "Uso: php provision.php --client=\"Nombre\" --email=correo [--domain=dominio.com] [--plan=slug] [--theme=slug]\n");
    exit(2);
}

$factory = new FactoryService();
$db = Database::instance();

// 1) Resolver plan
$plan = $planSlug !== '' ? $factory->planBySlug($planSlug) : null;
if (!$plan) {
    $row = $db->query("SELECT * FROM wwi_plans WHERE site_id = @site_id AND is_active = 1 ORDER BY price_cop ASC LIMIT 1")->fetch();
    $plan = $row ?: null;
}
if (!$plan) {
    fwrite(STDERR, "No hay planes disponibles en Factory. Crea uno antes de provisionar.\n");
    exit(3);
}

// 2) Idempotencia por dominio
if ($domain !== '') {
    $chk = $db->prepare("SELECT tenant_id FROM wwi_orders WHERE site_id = @site_id AND domain_name = ? AND tenant_id IS NOT NULL ORDER BY id DESC LIMIT 1");
    $chk->execute([$domain]);
    $existing = (int)$chk->fetchColumn();
    if ($existing > 0) {
        echo json_encode(['ok' => true, 'existing' => true, 'tenant_id' => $existing, 'message' => 'El dominio ya tiene un sitio provisionado.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        exit(0);
    }
}

// 3) Crear pedido + provisionar
$order = $factory->addOrder([
    'customer_name' => $client,
    'customer_email' => $email,
    'plan_id' => (int)$plan['id'],
    'domain_name' => $domain,
    'locale' => 'es',
]);
if (empty($order['ok'])) { fwrite(STDERR, json_encode($order) . "\n"); exit(4); }
$orderId = (int)$order['id'];

try {
    $prov = $factory->provisionSite(['order_id' => $orderId]);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Error provisionando: ' . $e->getMessage() . "\n");
    exit(5);
}
if (empty($prov['ok'])) { fwrite(STDERR, json_encode($prov) . "\n"); exit(5); }
$tenantId = (int)$prov['tenant_id'];

// 4) Tema + nombre + starter (el menú se auto-siembra al primer render del tema)
$db->prepare("UPDATE sites SET theme = :t WHERE id = :id")->execute(['t' => $theme, 'id' => $tenantId]);
$db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (:sid, 'site_name', :v) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
   ->execute(['sid' => $tenantId, 'v' => $client]);
$db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (:sid, 'logo_text', :v) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
   ->execute(['sid' => $tenantId, 'v' => $client]);
if ($sector !== '') {
    $db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (:sid, 'site_sector', :v) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
       ->execute(['sid' => $tenantId, 'v' => $sector]);
}

// 5) Credenciales del cliente (temporales, para entregar)
$u = $db->prepare("SELECT id, username, email FROM users WHERE site_id = :sid AND role = 'client' ORDER BY id ASC LIMIT 1");
$u->execute(['sid' => $tenantId]);
$user = $u->fetch();
$password = bin2hex(random_bytes(6));
if ($user) {
    $db->prepare("UPDATE users SET password_hash = :h WHERE id = :id")->execute(['h' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), 'id' => $user['id']]);
}

// 6) Disparar jobs (email + deploy)
$factory->runDueJobs(10);

$host = $domain !== '' ? $domain : ('cliente' . $tenantId . '.wontia.com');
echo json_encode([
    'ok' => true,
    'tenant_id' => $tenantId,
    'site_uuid' => $prov['site_uuid'] ?? null,
    'plan' => $plan['slug'] ?? null,
    'theme' => $theme,
    'domain' => $domain,
    'admin_url' => 'https://' . $host . '/admin.php',
    'username' => $user['username'] ?? null,
    'password' => $password,
    'note' => 'El contenedor y el SSL se crean por el auto-deployer en <1 min. El menú/config del tema se autosiembran al primer render.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
