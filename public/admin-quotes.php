<?php
define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/vendor/autoload.php';

use App\Core\Config;
use App\Core\Session;
use App\Services\QuoteService;

Config::load();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
Session::start();

if (!Session::isLoggedIn()) {
    header('Location: /admin');
    exit;
}
$role = (string)Session::userRole();
if (!in_array($role, QuoteService::ROLES, true)) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}

$svc = new QuoteService();
$svc->ensureTables();
$csrf = (string)Session::get('quote_admin_csrf', '');
if ($csrf === '') {
    $csrf = bin2hex(random_bytes(32));
    Session::set('quote_admin_csrf', $csrf);
}
$tab = in_array((string)($_GET['tab'] ?? 'resumen'), ['resumen', 'banco', 'configuracion'], true) ? (string)($_GET['tab'] ?? 'resumen') : 'resumen';
$flash = Session::flash('quote_admin_flash');
$flashError = Session::flash('quote_admin_error');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['_token'] ?? ''))) {
        Session::flash('quote_admin_error', 'La sesión del formulario expiró. Intenta de nuevo.');
        header('Location: /admin-quotes.php?tab=' . rawurlencode($tab));
        exit;
    }
    $action = (string)($_POST['action'] ?? '');
    $code = strtoupper(trim((string)($_POST['code'] ?? '')));
    try {
        if ($action === 'save_settings') {
            $svc->saveSettings($_POST);
            Session::flash('quote_admin_flash', 'Configuración de cotizaciones actualizada.');
        } elseif ($action === 'extend' && $code !== '') {
            $svc->extendPublic($code, max(1, min(3650, (int)($_POST['days'] ?? 90))));
            Session::flash('quote_admin_flash', 'Vigencia pública extendida.');
        } elseif ($action === 'revoke' && $code !== '') {
            $svc->revokePublic($code);
            Session::flash('quote_admin_flash', 'Enlace público revocado.');
        } elseif ($action === 'regenerate' && $code !== '') {
            $svc->regeneratePublic($code);
            Session::flash('quote_admin_flash', 'Nuevo enlace privado generado.');
        } elseif ($action === 'delete' && $code !== '') {
            $svc->deleteQuote($code);
            Session::flash('quote_admin_flash', 'Cotización eliminada del banco.');
        } else {
            throw new \RuntimeException('Acción no válida.');
        }
    } catch (\Throwable $e) {
        Session::flash('quote_admin_error', $e->getMessage());
    }
    $target = $action === 'save_settings' ? 'configuracion' : 'banco';
    header('Location: /admin-quotes.php?tab=' . $target);
    exit;
}

$settings = $svc->settings();
$filters = ['q' => trim((string)($_GET['q'] ?? '')), 'status' => (string)($_GET['status'] ?? '')];
$quotes = $svc->adminList(250, $filters);
$stats = $svc->stats();
$baseUrl = rtrim((string)($settings['quote_public_base_url'] ?? ''), '/');
if ($baseUrl === '') {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $baseUrl = ($https ? 'https' : 'http') . '://' . (string)($_SERVER['HTTP_HOST'] ?? 'sonika.com.co');
}

function q_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function q_money(int $cents, string $currency = 'COP'): string {
    $sym = $currency === 'USD' ? 'US$' : ($currency === 'COP' ? '$' : $currency . ' ');
    return $sym . number_format($cents / 100, $currency === 'COP' ? 0 : 2, ',', '.');
}
function q_date(?string $date): string {
    if (!$date) return '—';
    try {
        $dt = new DateTime($date, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Bogota'));
        return $dt->format('d/m/Y H:i');
    } catch (\Throwable $e) { return (string)$date; }
}
function q_day(?string $date): string {
    if (!$date) return '—';
    try {
        $dt = new DateTime($date, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Bogota'));
        return $dt->format('d/m/Y');
    } catch (\Throwable $e) { return (string)$date; }
}
function q_status(string $s): string {
    return match ($s) {
        'sent' => 'Enviada',
        'converted' => 'Convertida',
        'expired' => 'Vencida',
        default => 'Borrador',
    };
}
function q_public_state(array $q, bool $enabled): string {
    if (!empty($q['public_revoked_at'])) return 'Revocado';
    if (!empty($q['public_expired'])) return 'Vencido';
    return $enabled ? 'Activo' : 'Desactivado';
}
function q_public_class(array $q, bool $enabled): string {
    if (!empty($q['public_revoked_at']) || !empty($q['public_expired']) || !$enabled) return 'off';
    return 'on';
}
$currency = 'COP';
$funnel = [
    ['Recibidas', (int)$stats['total'], 100],
    ['Vistas', (int)$stats['views'], (int)$stats['total'] ? min(100, round(((int)$stats['views'] / (int)$stats['total']) * 100)) : 0],
    ['Enviadas', (int)$stats['sent'], (int)$stats['total'] ? min(100, round(((int)$stats['sent'] / (int)$stats['total']) * 100)) : 0],
    ['Pedidos', (int)$stats['orders'], (int)$stats['total'] ? min(100, round(((int)$stats['orders'] / (int)$stats['total']) * 100)) : 0],
    ['Pagados', (int)$stats['paid'], (int)$stats['total'] ? min(100, round(((int)$stats['paid'] / (int)$stats['total']) * 100)) : 0],
];
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Cotizaciones · SONIKA</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--ink:#111;--muted:#64748b;--line:#e5e9e7;--soft:#f5f8f7;--brand:#F20D16;--ok:#0b8457;--warn:#8a5a00;--err:#b42318;--blue:#1d4ed8}
body{font-family:Inter,system-ui,-apple-system,sans-serif;background:#f4f7f6;color:var(--ink);font-size:14px;line-height:1.5}
a{color:var(--brand);text-decoration:none}a:hover{text-decoration:underline}
.wrap{max-width:1240px;margin:0 auto;padding:24px 18px 64px}
.top{display:flex;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:18px}.top h1{font-size:22px;letter-spacing:-.02em}.top .sub{color:var(--muted);font-size:12px;margin-top:2px}.top .spacer{flex:1}
.btn{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:8px 12px;border:0;border-radius:8px;background:#111;color:#fff;font:inherit;font-size:11.5px;font-weight:750;cursor:pointer;white-space:nowrap}
.btn:hover{filter:brightness(.94);text-decoration:none}.btn.sec{background:#fff;color:#374151;border:1px solid var(--line)}.btn.sec:hover{border-color:#9ca3af;filter:none;color:#111}.btn.danger{background:#fef2f2;color:#b42318;border:1px solid #fecaca}.btn.green{background:#0b8457;color:#fff}.btn.sm{min-height:30px;padding:5px 8px;font-size:10.5px}
.tabs{display:flex;gap:4px;margin-bottom:16px;border-bottom:1px solid var(--line);overflow-x:auto}.tab{display:inline-flex;padding:10px 13px;border-bottom:2px solid transparent;color:var(--muted);font-size:12px;font-weight:750;white-space:nowrap}.tab:hover{color:#111;text-decoration:none}.tab.on{color:#111;border-bottom-color:var(--brand)}
.flash{padding:10px 12px;border:1px solid #bbf7d0;border-radius:9px;background:#f0fdf4;color:#166534;font-size:12px;margin-bottom:14px}.flash.err{border-color:#fecaca;background:#fef2f2;color:#b42318}
.stats{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:9px;margin-bottom:14px}.stat{background:#fff;border:1px solid var(--line);border-radius:11px;padding:11px 12px;min-width:0}.stat small{display:block;color:var(--muted);font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.stat b{font-size:19px;letter-spacing:-.02em;font-variant-numeric:tabular-nums}.stat span{display:block;color:var(--muted);font-size:10px;margin-top:2px}
.grid-2{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(300px,.7fr);gap:14px;margin-bottom:14px}
.card{background:#fff;border:1px solid var(--line);border-radius:13px;box-shadow:0 8px 24px rgba(16,24,40,.04);overflow:hidden}.card h2{font-size:14px;padding:13px 15px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;gap:8px}.card h2 small{font-size:10px;color:var(--muted);font-weight:500}.card-body{padding:14px 15px}
.funnel{display:grid;gap:9px}.funnel-row{display:grid;grid-template-columns:78px minmax(0,1fr) 44px;align-items:center;gap:9px;font-size:11px}.funnel-row span{color:var(--muted)}.funnel-row b{text-align:right;font-variant-numeric:tabular-nums}.bar{height:9px;border-radius:999px;background:#eef2f1;overflow:hidden}.bar i{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#F20D16,#ff6970)}.bar.blue i{background:linear-gradient(90deg,#1d4ed8,#60a5fa)}.bar.green i{background:linear-gradient(90deg,#0b8457,#34d399)}
.daily{display:flex;align-items:flex-end;gap:5px;height:112px;padding:10px 4px 0;border-bottom:1px solid var(--line)}.day{flex:1;min-width:9px;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;gap:3px;height:100%}.day i{display:block;width:100%;max-width:20px;border-radius:4px 4px 0 0;background:#dbe4e0;min-height:2px}.day i.on{background:#F20D16}.day small{font-size:7px;color:#9ca3af;white-space:nowrap}.daily-legend{display:flex;justify-content:space-between;color:var(--muted);font-size:9px;margin-top:5px}
.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;font-size:11.5px}th{text-align:left;color:var(--muted);font-size:9px;text-transform:uppercase;letter-spacing:.05em;padding:9px 8px;border-bottom:1px solid var(--line);white-space:nowrap}td{padding:10px 8px;border-bottom:1px solid #f1f4f2;vertical-align:top}tr:last-child td{border-bottom:0}.mono{font-variant-numeric:tabular-nums;white-space:nowrap}.tiny{font-size:10px;color:var(--muted);line-height:1.45}.code{font-weight:800;white-space:nowrap}.pill{display:inline-flex;border-radius:999px;padding:3px 7px;font-size:9px;font-weight:800;white-space:nowrap;background:#f3f4f6;color:#4b5563}.pill.converted{background:#ecfdf3;color:#166534}.pill.expired{background:#fef2f2;color:#991b1b}.pill.sent{background:#eff6ff;color:#1e40af}.pill.public-on{background:#ecfdf5;color:#047857}.pill.public-off{background:#fef2f2;color:#b42318}
.actions{display:flex;gap:4px;flex-wrap:wrap;min-width:190px}.actions form{display:inline}
.empty{padding:28px;text-align:center;color:var(--muted)}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:13px}.field{display:grid;gap:5px;min-width:0}.field.full{grid-column:1/-1}.field label{color:#374151;font-size:11px;font-weight:750}.field small{color:var(--muted);font-size:9.5px;line-height:1.4}.input,.select,textarea{width:100%;min-height:38px;border:1px solid #d1d5db;border-radius:8px;padding:8px 10px;background:#fff;font:inherit;font-size:12px;outline:none}.input:focus,.select:focus,textarea:focus{border-color:#0b8457;box-shadow:0 0 0 3px rgba(11,132,87,.12)}textarea{min-height:76px;resize:vertical}.check{display:flex;align-items:flex-start;gap:8px;padding:9px 10px;border:1px solid var(--line);border-radius:8px;background:#fbfcfb}.check input{width:15px;height:15px;margin-top:1px;accent-color:#0b8457}.check span{font-size:11px;color:#374151;line-height:1.4}.form-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:15px}
.notice{border:1px solid #e5e7eb;border-radius:9px;background:#fbfcfb;padding:11px 12px;color:#64748b;font-size:10.5px;line-height:1.55;margin-bottom:14px}
.warn-note{border-color:#fde68a;background:#fffbeb;color:#8a5a00}
.toolbar{display:flex;gap:7px;flex-wrap:wrap;align-items:center;margin-bottom:10px}.toolbar .spacer{flex:1}.toolbar .input{width:min(280px,100%)}
.copy-link{min-height:28px;padding:3px 6px;font-size:9.5px}
@media(max-width:1050px){.stats{grid-template-columns:repeat(3,1fr)}.grid-2{grid-template-columns:1fr}}
@media(max-width:700px){.wrap{padding:16px 11px 50px}.top h1{font-size:19px}.stats{grid-template-columns:1fr 1fr}.form-grid{grid-template-columns:1fr}.field.full{grid-column:auto}.top .spacer{display:none}.top .btn{flex:1}.actions{min-width:150px}}
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div><h1>Banco de cotizaciones</h1><div class="sub">Seguridad, seguimiento y conversión de SONIKA</div></div>
    <span class="spacer"></span>
    <a class="btn sec" href="/admin.php">Volver al admin</a>
    <a class="btn" href="/admin-quotes.php?tab=banco">Ver banco</a>
  </div>
  <?php if ($flash): ?><div class="flash"><?= q_h($flash) ?></div><?php endif; ?>
  <?php if ($flashError): ?><div class="flash err"><?= q_h($flashError) ?></div><?php endif; ?>
  <nav class="tabs"><a class="tab <?= $tab === 'resumen' ? 'on' : '' ?>" href="/admin-quotes.php?tab=resumen">Resumen</a><a class="tab <?= $tab === 'banco' ? 'on' : '' ?>" href="/admin-quotes.php?tab=banco">Cotizaciones</a><a class="tab <?= $tab === 'configuracion' ? 'on' : '' ?>" href="/admin-quotes.php?tab=configuracion">Configuración</a></nav>

  <?php if ($tab === 'resumen'): ?>
  <div class="stats">
    <div class="stat"><small>Cotizaciones</small><b><?= (int)$stats['total'] ?></b><span><?= (int)$stats['public_expired'] ?> enlace(s) vencido(s)</span></div>
    <div class="stat"><small>Vistas</small><b><?= (int)$stats['views'] ?></b><span>accesos registrados</span></div>
    <div class="stat"><small>Enviadas</small><b><?= (int)$stats['sent'] ?></b><span>por WhatsApp / PDF</span></div>
    <div class="stat"><small>Pedidos</small><b><?= (int)$stats['orders'] ?></b><span>desde cotización</span></div>
    <div class="stat"><small>Pagados</small><b><?= (int)$stats['paid'] ?></b><span><?= q_h(q_money((int)$stats['paid_value_cents'])) ?></span></div>
    <div class="stat"><small>Conversión</small><b><?= q_h((string)$stats['paid_rate']) ?>%</b><span>cotización → pago</span></div>
  </div>
  <div class="grid-2">
    <div class="card">
      <h2>Embudo de seguimiento <small>últimos 14 días en el gráfico</small></h2>
      <div class="card-body">
        <div class="funnel">
          <?php foreach ($funnel as $i => $f): ?><div class="funnel-row"><span><?= q_h($f[0]) ?></span><div class="bar <?= $i > 1 ? 'blue' : '' ?>"><i style="width:<?= (int)$f[2] ?>%"></i></div><b><?= (int)$f[1] ?></b></div><?php endforeach; ?>
        </div>
        <div class="daily" aria-label="Cotizaciones por día">
          <?php $__max = 1; foreach (($stats['daily'] ?? []) as $d) $__max = max($__max, (int)$d['quotes']); foreach (($stats['daily'] ?? []) as $d): $__h = max(2, round(((int)$d['quotes'] / $__max) * 90)); ?>
            <div class="day" title="<?= q_h((string)$d['day']) ?>: <?= (int)$d['quotes'] ?>"><i class="<?= (int)$d['quotes'] > 0 ? 'on' : '' ?>" style="height:<?= $__h ?>px"></i><small><?= q_h(substr((string)$d['day'], 8, 2)) ?></small></div>
          <?php endforeach; ?>
        </div>
        <div class="daily-legend"><span>Cotizaciones recibidas</span><span>Hoy</span></div>
      </div>
    </div>
    <div class="card">
      <h2>Estado del banco <small>configuración activa</small></h2>
      <div class="card-body">
        <div class="notice"><b><?= $settings['quote_public_enabled'] ? 'Enlaces públicos activos' : 'Enlaces públicos desactivados' ?></b><br>Retención: <?= (int)$settings['quote_public_days'] ?> días · Vigencia comercial: <?= (int)$settings['quote_valid_days'] ?> días<br><?= $settings['quote_require_verification'] ? 'Acceso protegido por correo/documento.' : 'Acceso solo por enlace privado.' ?></div>
        <div class="notice <?= $stats['total'] > 0 ? '' : 'warn-note' ?>">Valor cotizado: <b><?= q_h(q_money((int)$stats['value_cents'])) ?></b><br>Ticket promedio pagado: <b><?= q_h(q_money((int)$stats['ticket_cents'])) ?></b><br><?= (int)$stats['revoked'] ?> enlace(s) revocado(s).</div>
        <a class="btn sec" href="/admin-quotes.php?tab=configuracion">Ajustar configuración</a>
      </div>
    </div>
  </div>
  <div class="card">
    <h2>Últimas cotizaciones <small>seguimiento operativo</small></h2>
    <div class="table-wrap"><table><thead><tr><th>Código</th><th>Cliente</th><th>Total</th><th>Estado</th><th>Enlace público</th><th>Actividad</th><th></th></tr></thead><tbody>
    <?php if (!$quotes): ?><tr><td colspan="7" class="empty">Aún no hay cotizaciones guardadas.</td></tr><?php else: foreach (array_slice($quotes, 0, 8) as $q): $st = (string)($q['effective_status'] ?? $q['status']); ?>
      <tr><td><b class="code"><?= q_h($q['code']) ?></b><div class="tiny"><?= q_h(q_date((string)$q['created_at'])) ?></div></td><td class="tiny"><b><?= q_h((string)($q['customer_name'] ?? '—')) ?></b><div><?= q_h(($q['customer_type'] ?? '') === 'company' ? 'Persona jurídica' : (($q['customer_type'] ?? '') === 'natural' ? 'Persona natural' : 'Sin datos')) ?></div></td><td class="mono"><b><?= q_h(q_money((int)$q['total_cents'], (string)($q['currency'] ?? 'COP'))) ?></b><div class="tiny"><?= (int)$q['item_count'] ?> art.</div></td><td><span class="pill <?= q_h($st) ?>"><?= q_h(q_status($st)) ?></span><div class="tiny"><?= (int)$q['orders_count'] ?> pedido(s)</div></td><td><span class="pill public-<?= q_public_class($q, (bool)$settings['quote_public_enabled']) ?>"><?= q_h(q_public_state($q, (bool)$settings['quote_public_enabled'])) ?></span><div class="tiny"><?= q_h(q_day((string)($q['public_expires_at'] ?? ''))) ?></div></td><td class="tiny"><?= (int)$q['viewed_count'] ?> vistas · <?= (int)$q['pdf_count'] ?> PDF<br><?= (int)$q['whatsapp_count'] ?> WhatsApp · <?= (int)$q['unlock_count'] ?> accesos</td><td><a class="btn sec sm" href="<?= q_h((string)$q['url']) ?>" target="_blank" rel="noopener">Ver</a></td></tr>
    <?php endforeach; endif; ?>
    </tbody></table></div>
  </div>

  <?php elseif ($tab === 'banco'): ?>
  <div class="card">
    <h2>Cotizaciones públicas <small><?= count($quotes) ?> registro(s) · retención <?= (int)$settings['quote_public_days'] ?> días</small></h2>
    <div class="card-body">
      <div class="toolbar">
        <form method="get" action="/admin-quotes.php" style="display:flex;gap:7px;flex-wrap:wrap;align-items:center"><input type="hidden" name="tab" value="banco"><input class="input" name="q" value="<?= q_h($filters['q']) ?>" placeholder="Código, cliente o pedido"><select class="select" name="status" style="width:auto"><option value="">Todos los estados</option><option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>Borrador</option><option value="sent" <?= $filters['status'] === 'sent' ? 'selected' : '' ?>>Enviada</option><option value="converted" <?= $filters['status'] === 'converted' ? 'selected' : '' ?>>Convertida</option></select><button class="btn sec sm" type="submit">Filtrar</button><a class="btn sec sm" href="/admin-quotes.php?tab=banco">Limpiar</a></form>
        <span class="spacer"></span><span class="tiny">Los enlaces son privados y no contienen datos del cliente.</span>
      </div>
      <div class="table-wrap"><table><thead><tr><th>Código / fecha</th><th>Cliente</th><th>Total</th><th>Estado</th><th>Banco público</th><th>Seguimiento</th><th>Pedido</th><th>Acciones</th></tr></thead><tbody>
      <?php if (!$quotes): ?><tr><td colspan="8" class="empty">No hay cotizaciones con esos filtros.</td></tr><?php else: foreach ($quotes as $q): $st = (string)($q['effective_status'] ?? $q['status']); $pdf = $svc->signedPath($q, 'pdf', 900); ?>
        <tr>
          <td><b class="code"><?= q_h($q['code']) ?></b><div class="tiny"><?= q_h(q_date((string)$q['created_at'])) ?></div></td>
          <td class="tiny"><b><?= q_h((string)($q['customer_name'] ?? '—')) ?></b><div><?= q_h(($q['customer_type'] ?? '') === 'company' ? 'Persona jurídica' : (($q['customer_type'] ?? '') === 'natural' ? 'Persona natural' : 'Sin datos')) ?></div></td>
          <td class="mono"><b><?= q_h(q_money((int)$q['total_cents'], (string)($q['currency'] ?? 'COP'))) ?></b><div class="tiny"><?= (int)$q['item_count'] ?> art. · <?= q_h($q['payment_method'] === 'breb' ? 'Bre-B' : ($q['payment_method'] === 'mercadopago' ? 'Mercado Pago' : ($q['payment_method'] === 'epayco' ? 'ePayco' : 'Asistido'))) ?></div></td>
          <td><span class="pill <?= q_h($st) ?>"><?= q_h(q_status($st)) ?></span></td>
          <td><span class="pill public-<?= q_public_class($q, (bool)$settings['quote_public_enabled']) ?>"><?= q_h(q_public_state($q, (bool)$settings['quote_public_enabled'])) ?></span><div class="tiny">vence <?= q_h(q_day((string)($q['public_expires_at'] ?? ''))) ?></div></td>
          <td class="tiny"><?= (int)$q['viewed_count'] ?> vistas · <?= (int)$q['pdf_count'] ?> PDF<br><?= (int)$q['whatsapp_count'] ?> WhatsApp · <?= (int)$q['unlock_count'] ?> accesos<br><span class="mono"><?= q_h(q_date((string)($q['last_event_at'] ?? ''))) ?></span></td>
          <td class="tiny"><?= (int)$q['orders_count'] ?> creado(s) · <?= (int)$q['paid_orders'] ?> pagado(s)<?php if (!empty($q['order_number'])): ?><br><span class="mono"><?= q_h($q['order_number']) ?></span><?php endif; ?></td>
          <td><div class="actions"><a class="btn sec sm" href="<?= q_h((string)$q['url']) ?>" target="_blank" rel="noopener">Abrir</a><button type="button" class="btn sec sm copy-link" data-url="<?= q_h($baseUrl . (string)$q['url']) ?>">Copiar</button><?php if ($pdf !== ''): ?><a class="btn sec sm" href="<?= q_h($pdf) ?>">PDF</a><?php endif; ?><form method="post"><input type="hidden" name="_token" value="<?= q_h($csrf) ?>"><input type="hidden" name="action" value="regenerate"><input type="hidden" name="code" value="<?= q_h($q['code']) ?>"><button class="btn sec sm" type="submit">Nuevo enlace</button></form><form method="post"><input type="hidden" name="_token" value="<?= q_h($csrf) ?>"><input type="hidden" name="action" value="extend"><input type="hidden" name="code" value="<?= q_h($q['code']) ?>"><input type="hidden" name="days" value="<?= (int)$settings['quote_public_days'] ?>"><button class="btn sec sm" type="submit">Extender</button></form><?php if (empty($q['public_revoked_at'])): ?><form method="post"><input type="hidden" name="_token" value="<?= q_h($csrf) ?>"><input type="hidden" name="action" value="revoke"><input type="hidden" name="code" value="<?= q_h($q['code']) ?>"><button class="btn danger sm" type="submit">Revocar</button></form><?php endif; ?><form method="post" onsubmit="return confirm('¿Eliminar esta cotización y su seguimiento?');"><input type="hidden" name="_token" value="<?= q_h($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="code" value="<?= q_h($q['code']) ?>"><button class="btn danger sm" type="submit">Eliminar</button></form></div></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody></table></div>
    </div>
  </div>

  <?php else: ?>
  <div class="grid-2">
    <div class="card">
      <h2>Configuración de cotizaciones <small>seguridad y retención</small></h2>
      <div class="card-body">
        <form method="post" action="/admin-quotes.php?tab=configuracion">
          <input type="hidden" name="_token" value="<?= q_h($csrf) ?>"><input type="hidden" name="action" value="save_settings">
          <div class="form-grid">
            <label class="check"><input type="checkbox" name="quote_public_enabled" value="1" <?= $settings['quote_public_enabled'] ? 'checked' : '' ?>><span><b>Banco público activo</b><small>Permite abrir cotizaciones desde un enlace privado.</small></span></label>
            <label class="check"><input type="checkbox" name="quote_require_verification" value="1" <?= $settings['quote_require_verification'] ? 'checked' : '' ?>><span><b>Verificación del cliente</b><small>Solicita correo o últimos 4 del documento.</small></span></label>
            <div class="field"><label for="quote_public_days">Días de disponibilidad pública</label><input class="input" id="quote_public_days" name="quote_public_days" type="number" min="1" max="3650" value="<?= (int)$settings['quote_public_days'] ?>" required><small>Por defecto 90 días. El admin puede extender o revocar.</small></div>
            <div class="field"><label for="quote_valid_days">Días de vigencia comercial</label><input class="input" id="quote_valid_days" name="quote_valid_days" type="number" min="1" max="90" value="<?= (int)$settings['quote_valid_days'] ?>" required><small>Fecha de validez de precios.</small></div>
            <div class="field"><label for="quote_access_minutes">Minutos de sesión verificada</label><input class="input" id="quote_access_minutes" name="quote_access_minutes" type="number" min="5" max="1440" value="<?= (int)$settings['quote_access_minutes'] ?>" required><small>Duración del acceso tras verificar.</small></div>
            <div class="field"><label for="quote_company_city">Ciudad de emisión</label><input class="input" id="quote_company_city" name="quote_company_city" maxlength="120" value="<?= q_h($settings['quote_company_city']) ?>"><small>Aparece en el encabezado del documento.</small></div>
            <div class="field"><label for="quote_company_nit">NIT de la compañía</label><input class="input" id="quote_company_nit" name="quote_company_nit" maxlength="40" value="<?= q_h($settings['quote_company_nit']) ?>" placeholder="Dato legal para el pie"><small>Se muestra en el pie cuando esté configurado.</small></div>
            <label class="check"><input type="checkbox" name="quote_pdf_enabled" value="1" <?= $settings['quote_pdf_enabled'] ? 'checked' : '' ?>><span><b>PDF disponible</b><small>Genera el documento A4 sin hojas en blanco.</small></span></label>
            <label class="check"><input type="checkbox" name="quote_whatsapp_enabled" value="1" <?= $settings['quote_whatsapp_enabled'] ? 'checked' : '' ?>><span><b>Compartir PDF por WhatsApp</b><small>En móvil abre el menú nativo de compartir archivo.</small></span></label>
            <div class="field full"><label for="quote_whatsapp_to">Número de WhatsApp para PDF (API opcional)</label><input class="input" id="quote_whatsapp_to" name="quote_whatsapp_to" inputmode="tel" value="<?= q_h($settings['quote_whatsapp_to']) ?>" placeholder="57 300 000 0000"><small>Si configuras la API de WhatsApp Business, el PDF se enviará a este número.</small></div>
            <div class="field"><label for="quote_public_base_url">URL pública base</label><input class="input" id="quote_public_base_url" name="quote_public_base_url" value="<?= q_h($settings['quote_public_base_url']) ?>" placeholder="https://sonika.com.co"><small>Debe ser la URL pública real para que WhatsApp API descargue el PDF.</small></div>
            <div class="field"><label for="quote_whatsapp_phone_id">WhatsApp Phone Number ID</label><input class="input" id="quote_whatsapp_phone_id" name="quote_whatsapp_phone_id" value="<?= q_h($settings['quote_whatsapp_phone_id']) ?>" autocomplete="off"></div>
            <div class="field"><label for="quote_whatsapp_api_token">Token API WhatsApp</label><input class="input" id="quote_whatsapp_api_token" name="quote_whatsapp_api_token" type="password" value="" autocomplete="new-password" placeholder="<?= $settings['quote_whatsapp_api_token'] !== '' ? 'Configurado · escribe para reemplazar' : 'No configurado' ?>"><small>Se guarda en settings. No se muestra después.</small></div>
          </div>
          <div class="form-actions"><button class="btn green" type="submit">Guardar configuración</button><a class="btn sec" href="/admin-quotes.php?tab=banco">Ver banco</a></div>
        </form>
      </div>
    </div>
    <div class="card">
      <h2>Seguridad del banco</h2>
      <div class="card-body">
        <div class="notice"><b>Enlace privado de 256 bits</b><br>Cada cotización usa un token aleatorio distinto del código visible. No contiene nombre, correo, documento ni teléfono.</div>
        <div class="notice"><b>Acceso verificado</b><br>El cliente confirma correo o últimos 4 dígitos del documento. Hay bloqueo temporal tras 5 intentos fallidos.</div>
        <div class="notice"><b>Retención controlada</b><br>El enlace permanece disponible 90 días por defecto. El administrador puede extenderlo, revocarlo o regenerarlo sin eliminar la cotización.</div>
        <div class="notice"><b>Seguimiento</b><br>Se registran vistas, accesos, PDF, WhatsApp, checkout, pedidos pagados y conversión. Las IP se guardan solo como hash.</div>
        <div class="notice warn-note">Para que WhatsApp envíe el PDF automáticamente desde el servidor se requieren credenciales de WhatsApp Business Cloud API. Sin ellas, el botón usa el menú nativo de compartir archivo del dispositivo.</div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<script>
(function () {
  document.querySelectorAll('.copy-link').forEach(function (button) {
    button.addEventListener('click', function () {
      var value = button.getAttribute('data-url') || '';
      var done = function () { var old = button.textContent; button.textContent = 'Copiado'; setTimeout(function () { button.textContent = old; }, 1400); };
      if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(value).then(done).catch(done);
      else { var ta = document.createElement('textarea'); ta.value = value; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); } catch (e) {} ta.remove(); done(); }
    });
  });
})();
</script>
</body>
</html>
