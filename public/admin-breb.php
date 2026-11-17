<?php
define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/vendor/autoload.php';

use App\Core\Session;
use App\Services\BrebService;

\App\Core\Config::load();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');
Session::start();

if (!Session::isLoggedIn()) {
    header('Location: /admin');
    exit;
}
$__role = (string)Session::userRole();
if (!in_array($__role, ['superadmin', 'admin', 'editor'], true)) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}

$svc = new BrebService();
$svc->ensureTables();
$csrf = (string)Session::get('breb_admin_csrf', '');
if ($csrf === '') {
    $csrf = bin2hex(random_bytes(32));
    Session::set('breb_admin_csrf', $csrf);
}
$user = Session::user() ?: [];
$actorId = (int)($user['id'] ?? 0);
$flashOk = Session::flash('breb_admin_ok');
$flashErr = Session::flash('breb_admin_err');

function breb_h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function breb_money(int $cents, string $currency = 'COP'): string
{
    $sym = $currency === 'USD' ? 'US$' : ($currency === 'COP' ? '$' : $currency . ' ');
    return $sym . number_format($cents / 100, $currency === 'COP' ? 0 : 2, ',', '.');
}
function breb_date(?string $date): string
{
    if (!$date) return '—';
    try {
        $dt = new DateTime($date, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Bogota'));
        return $dt->format('d/m/Y H:i');
    } catch (\Throwable $e) {
        return (string)$date;
    }
}
function breb_size(int $bytes): string
{
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 0, ',', '.') . ' KB';
    return $bytes . ' B';
}
function breb_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}
function breb_proof_label(string $s): string
{
    return match ($s) {
        'pending' => 'En revisión',
        'approved' => 'Aprobado',
        'rejected' => 'Rechazado',
        'purged' => 'Depurado',
        default => 'Sin comprobante',
    };
}

$download = (int)($_GET['download'] ?? 0);
if ($download > 0) {
    $token = (string)($_GET['_token'] ?? '');
    if (!hash_equals($csrf, $token)) {
        http_response_code(403);
        echo 'Solicitud no válida.';
        exit;
    }
    $file = $svc->proofForAdmin($download);
    if (!$file) {
        http_response_code(404);
        echo 'Comprobante no encontrado.';
        exit;
    }
    $ext = strtolower(pathinfo((string)$file['payment_proof_path'], PATHINFO_EXTENSION));
    $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';
    $name = 'comprobante-' . preg_replace('/[^A-Za-z0-9._-]/', '_', (string)($file['order_number'] ?? $download)) . '.' . $ext;
    header('Content-Type: ' . (string)($file['payment_proof_mime'] ?? 'application/octet-stream'));
    header('Content-Length: ' . (string)filesize((string)$file['full_path']));
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    readfile((string)$file['full_path']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['_token'] ?? '');
    if (!hash_equals($csrf, $token)) {
        Session::flash('breb_admin_err', 'La sesión del formulario expiró. Intenta de nuevo.');
        breb_redirect('/admin-breb.php');
    }
    $action = (string)($_POST['action'] ?? '');
    $r = ['ok' => false, 'message' => 'Acción no válida.'];
    if ($action === 'save_settings') $r = $svc->saveSettings($_POST);
    elseif ($action === 'save_gateways') $r = $svc->saveGateways($_POST);
    elseif ($action === 'upload_qr') $r = $svc->uploadQr($_FILES['qr'] ?? []);
    elseif ($action === 'delete_qr') $r = $svc->deleteQr();
    elseif ($action === 'mark_paid') $r = $svc->markPaid((int)($_POST['order_id'] ?? 0), $actorId);
    elseif ($action === 'reject_proof') $r = $svc->rejectProof((int)($_POST['order_id'] ?? 0), $actorId, (string)($_POST['note'] ?? ''));
    elseif ($action === 'cleanup') $r = $svc->cleanupProofs((string)($_POST['mode'] ?? ''));
    Session::flash(!empty($r['ok']) ? 'breb_admin_ok' : 'breb_admin_err', (string)($r['message'] ?? ''));
    breb_redirect('/admin-breb.php');
}

$s = $svc->settings();
$orders = $svc->orders(200);
$usage = $svc->diskUsage();
$available = $svc->available();
$pending = 0;
$proofPending = 0;
$paid = 0;
$volume = 0;
foreach ($orders as $o) {
    if (($o['payment_status'] ?? '') === 'pending') $pending++;
    if (($o['payment_proof_status'] ?? '') === 'pending') $proofPending++;
    if (($o['payment_status'] ?? '') === 'paid') $paid++;
    $volume += (int)($o['total_cents'] ?? 0);
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Pagos Bre-B — Administración</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--ink:#111;--muted:#64748b;--line:#e5e9e7;--soft:#f5f8f7;--brand:#F20D16;--brand-soft:#fff5f5;--ok:#0a6b49;--warn:#8a5a00;--err:#b42318}
body{font-family:Inter,system-ui,-apple-system,sans-serif;background:#f4f7f6;color:var(--ink);font-size:14px;line-height:1.5}
a{color:var(--brand);text-decoration:none}a:hover{text-decoration:underline}
.wrap{max-width:1240px;margin:0 auto;padding:22px 18px 60px}
.top{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px}
.top h1{font-size:22px;letter-spacing:-.02em}.top .sub{color:var(--muted);font-size:12.5px}.top .spacer{flex:1}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:40px;padding:9px 14px;border:0;border-radius:9px;background:var(--brand);color:#fff;font:inherit;font-size:13px;font-weight:700;cursor:pointer}
.btn:hover{filter:brightness(.94);text-decoration:none}.btn.sec{background:#fff;color:var(--ink);border:1px solid var(--line)}.btn.sec:hover{border-color:var(--brand);color:var(--brand);filter:none}
.btn.sm{min-height:32px;padding:6px 9px;font-size:11.5px}.btn.ghost{background:#fff;color:#374151;border:1px solid var(--line)}
.btn.danger{background:#fff;color:var(--err);border:1px solid #f5c2c2}.btn.danger:hover{background:#fef2f2;filter:none}
.flash{margin:0 0 14px;padding:11px 14px;border-radius:10px;font-size:13px;font-weight:600}.flash.ok{background:#f0fbf6;color:var(--ok);border:1px solid #bfe8d5}.flash.err{background:#fff5f5;color:var(--err);border:1px solid #f5c2c2}
.stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px;margin:0 0 16px}
.stat{background:#fff;border:1px solid var(--line);border-radius:12px;padding:11px 12px}.stat small{display:block;color:var(--muted);font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px}.stat b{font-size:19px;line-height:1.1}.stat.hot b{color:var(--brand)}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 10px 28px rgba(16,24,40,.05)}
.section{padding:16px 18px}.section+.section{border-top:1px solid var(--line)}
.section h2{font-size:15px;letter-spacing:-.01em;margin:0 0 4px}.section .hint{color:var(--muted);font-size:12.5px;margin:0 0 13px}
.grid2{display:grid;grid-template-columns:1.05fr .95fr;gap:16px;align-items:start}
.form{display:grid;gap:11px}.row2{display:grid;grid-template-columns:1fr 1fr;gap:10px}.field{display:grid;gap:4px;min-width:0}
.field label{font-size:11px;font-weight:750;color:#374151}.input,.select,.textarea{width:100%;min-height:38px;border:1px solid #d8dfdc;border-radius:8px;padding:8px 10px;font:inherit;font-size:13.5px;background:#fff}
.textarea{min-height:78px;resize:vertical}.check{display:flex;align-items:flex-start;gap:8px;font-size:12.5px;color:#475569}.check input{width:17px;height:17px;margin-top:1px;accent-color:var(--brand)}
.qr-box{display:flex;gap:14px;align-items:center;flex-wrap:wrap}.qr-img{width:150px;height:150px;object-fit:contain;border:1px solid var(--line);border-radius:12px;padding:7px;background:#fff}.qr-empty{width:150px;height:150px;border:1px dashed #cfd8d3;border-radius:12px;display:flex;align-items:center;justify-content:center;text-align:center;color:var(--muted);font-size:11.5px;padding:12px;background:#fafcfb}
.warn{background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:10px;padding:9px 11px;font-size:12.5px;line-height:1.45;margin:10px 0}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:12.5px}th{text-align:left;color:var(--muted);font-size:10.5px;text-transform:uppercase;letter-spacing:.05em;padding:8px;border-bottom:1px solid var(--line);white-space:nowrap}td{padding:9px 8px;border-bottom:1px solid #f1f4f2;vertical-align:top}tr:last-child td{border-bottom:0}
.pill{display:inline-flex;border-radius:999px;padding:3px 8px;font-size:10.5px;font-weight:800;white-space:nowrap}.pill.pend{background:#fffbeb;color:#92400e}.pill.ok{background:#ecfdf3;color:#166534}.pill.err{background:#fef2f2;color:#991b1b}.pill.neutral{background:#f3f4f6;color:#4b5563}
.mono{font-variant-numeric:tabular-nums;white-space:nowrap}.muted{color:var(--muted)}.tiny{font-size:11.5px;color:var(--muted);line-height:1.45}
.inline{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-top:5px}.inline .input{min-height:30px;padding:4px 7px;font-size:11.5px;width:130px}
.actions{display:flex;gap:5px;flex-wrap:wrap;align-items:center}
.disk{font-size:12px;color:var(--muted)}.disk b{color:var(--ink)}
.empty{padding:22px;text-align:center;color:var(--muted);font-size:13px}
@media(max-width:900px){.grid2{grid-template-columns:1fr}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:560px){.row2{grid-template-columns:1fr}.wrap{padding:16px 12px 50px}.top h1{font-size:19px}}
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div>
      <h1>Pagos Bre-B</h1>
      <div class="sub">QR, descuento, comprobantes y verificación manual · SONIKA</div>
    </div>
    <span class="spacer"></span>
    <a class="btn sec sm" href="/admin.php">← Volver al admin</a>
  </div>
  <?php if ($flashOk): ?><div class="flash ok"><?= breb_h($flashOk) ?></div><?php endif; ?>
  <?php if ($flashErr): ?><div class="flash err"><?= breb_h($flashErr) ?></div><?php endif; ?>
  <?php if (!$available): ?><div class="warn"><b>Bre-B no está visible para clientes.</b> Carga un QR válido y activa el método para comenzar a recibir pagos.</div><?php endif; ?>

  <div class="stats">
    <div class="stat"><small>Pedidos Bre-B</small><b><?= count($orders) ?></b></div>
    <div class="stat hot"><small>Pendientes</small><b><?= $pending ?></b></div>
    <div class="stat"><small>Comprobantes por revisar</small><b><?= $proofPending ?></b></div>
    <div class="stat"><small>Pagados</small><b><?= $paid ?></b></div>
    <div class="stat"><small>Volumen registrado</small><b><?= breb_h(breb_money($volume)) ?></b></div>
  </div>

  <div class="grid2">
    <div class="card">
      <div class="section">
        <h2>QR de Bre-B</h2>
        <p class="hint">El QR se muestra en la página de pago. Sin QR, Bre-B no aparece en el checkout.</p>
        <div class="qr-box">
          <?php if ($s['qr_image'] !== ''): ?>
          <img class="qr-img" src="<?= breb_h($s['qr_image']) ?>" alt="<?= breb_h($s['qr_alt']) ?>" />
          <?php else: ?>
          <div class="qr-empty">Sin QR cargado</div>
          <?php endif; ?>
          <div style="flex:1;min-width:210px">
            <form method="post" enctype="multipart/form-data" class="form">
              <input type="hidden" name="_token" value="<?= breb_h($csrf) ?>">
              <input type="hidden" name="action" value="upload_qr">
              <div class="field"><label for="qr">Imagen QR (PNG, JPG o WebP · máx. 5 MB)</label><input class="input" id="qr" name="qr" type="file" accept="image/png,image/jpeg,image/webp" required></div>
              <div class="actions"><button class="btn" type="submit">Guardar QR</button></div>
            </form>
            <?php if ($s['qr_image'] !== ''): ?>
            <form method="post" style="margin-top:8px" onsubmit="return confirm('¿Eliminar el QR? Bre-B quedará oculto hasta cargar otro.');">
              <input type="hidden" name="_token" value="<?= breb_h($csrf) ?>">
              <input type="hidden" name="action" value="delete_qr">
              <button class="btn danger sm" type="submit">Eliminar QR</button>
            </form>
            <?php endif; ?>
            <p class="tiny" style="margin-top:9px">El QR debe ser el de la cuenta Bre-B de SONIKA. No se modifica ni se genera automáticamente.</p>
          </div>
        </div>
      </div>

      <div class="section">
        <h2>Configuración del pago</h2>
        <p class="hint">El descuento se calcula sobre el valor antes de IVA y se redondea a $100 COP. Nunca se muestra el porcentaje al cliente.</p>
        <form method="post" class="form">
          <input type="hidden" name="_token" value="<?= breb_h($csrf) ?>">
          <input type="hidden" name="action" value="save_settings">
          <label class="check"><input type="checkbox" name="breb_enabled" value="1"<?= $s['enabled'] ? ' checked' : '' ?>> Mostrar Bre-B como método de pago</label>
          <p class="tiny">Los precios del catálogo se interpretan siempre como IVA incluido.</p>
          <label class="check"><input type="checkbox" name="breb_stackable" value="1"<?= $s['stackable'] ? ' checked' : '' ?>> Permitir acumular con cupones y puntos</label>
          <label class="check"><input type="checkbox" name="breb_request_enabled" value="1"<?= $s['request_enabled'] ? ' checked' : '' ?>> Mostrar “Coordinar pago por WhatsApp” (pago asistido)</label>
          <div class="row2">
            <div class="field"><label for="breb_label">Texto del método</label><input class="input" id="breb_label" name="breb_label" value="<?= breb_h($s['label']) ?>" maxlength="80"></div>
            <div class="field"><label for="breb_discount_pct">Descuento Bre-B (%)</label><input class="input" id="breb_discount_pct" name="breb_discount_pct" type="number" min="0" max="50" step="0.1" value="<?= breb_h($s['discount_pct']) ?>"></div>
          </div>
          <div class="row2">
            <div class="field"><label for="breb_tax_rate">IVA (%)</label><input class="input" id="breb_tax_rate" name="breb_tax_rate" type="number" min="0" max="30" step="0.1" value="<?= breb_h($s['tax_rate']) ?>"></div>
            <div class="field"><label for="breb_rounding">Redondeo del descuento</label><select class="select" id="breb_rounding" name="breb_rounding"><option value="100"<?= $s['rounding'] === 100 ? ' selected' : '' ?>>A $100 COP</option><option value="0"<?= $s['rounding'] === 0 ? ' selected' : '' ?>>Sin redondeo</option><option value="1000"<?= $s['rounding'] === 1000 ? ' selected' : '' ?>>A $1.000 COP</option></select></div>
          </div>
          <div class="row2">
            <div class="field"><label for="breb_free_shipping_cop">Envío gratis desde (COP, antes de IVA)</label><input class="input" id="breb_free_shipping_cop" name="breb_free_shipping_cop" type="number" min="0" step="1000" value="<?= breb_h((string)($s['free_shipping_net_cents'] / 100)) ?>"></div>
            <div class="field"><label for="breb_shipping_cop">Costo de envío (COP)</label><input class="input" id="breb_shipping_cop" name="breb_shipping_cop" type="number" min="0" step="1000" value="<?= breb_h((string)($s['shipping_cents'] / 100)) ?>"></div>
          </div>
          <div class="row2">
            <div class="field"><label for="breb_payment_minutes">Vigencia sugerida del pago (min)</label><input class="input" id="breb_payment_minutes" name="breb_payment_minutes" type="number" min="30" max="1440" step="30" value="<?= (int)$s['payment_minutes'] ?>"></div>
            <div class="field"><label for="breb_whatsapp">WhatsApp de pagos</label><input class="input" id="breb_whatsapp" name="breb_whatsapp" value="<?= breb_h($s['whatsapp']) ?>" inputmode="tel"></div>
          </div>
          <div class="field"><label for="breb_qr_alt">Texto alternativo del QR</label><input class="input" id="breb_qr_alt" name="breb_qr_alt" value="<?= breb_h($s['qr_alt']) ?>" maxlength="190"></div>
          <div class="field"><label for="breb_terms_url">URL de términos</label><input class="input" id="breb_terms_url" name="breb_terms_url" value="<?= breb_h($s['terms_url']) ?>" maxlength="300"></div>
          <div class="row2">
            <div class="field"><label for="breb_key">Llave Bre-B (opcional)</label><input class="input" id="breb_key" name="breb_key" value="<?= breb_h($s['key']) ?>" maxlength="120"></div>
            <div class="field"><label for="breb_account_name">Titular (opcional)</label><input class="input" id="breb_account_name" name="breb_account_name" value="<?= breb_h($s['account_name']) ?>" maxlength="160"></div>
          </div>
          <div class="field"><label for="breb_instructions">Instrucciones para el cliente</label><textarea class="textarea" id="breb_instructions" name="breb_instructions" maxlength="800"><?= breb_h($s['instructions']) ?></textarea></div>
          <div class="actions"><button class="btn" type="submit">Guardar configuración</button><span class="tiny">Comprobante máximo: 2 MB</span></div>
        </form>
      </div>

      <div class="section">
        <h2>Otras pasarelas</h2>
        <p class="hint">Se muestran como enlaces externos. El pago se verifica manualmente en esta página; no hay cobro automático.</p>
        <form method="post" class="form">
          <input type="hidden" name="_token" value="<?= breb_h($csrf) ?>">
          <input type="hidden" name="action" value="save_gateways">
          <label class="check"><input type="checkbox" name="breb_mp_enabled" value="1"<?= $s['mp_enabled'] ? ' checked' : '' ?>> Mostrar Mercado Pago</label>
          <div class="row2"><div class="field"><label for="breb_mp_label">Texto</label><input class="input" id="breb_mp_label" name="breb_mp_label" value="<?= breb_h($s['mp_label']) ?>" maxlength="60"></div><div class="field"><label for="breb_mp_link">Enlace de pago</label><input class="input" id="breb_mp_link" name="breb_mp_link" value="<?= breb_h($s['mp_link']) ?>" placeholder="https://..." maxlength="500"></div></div>
          <label class="check" style="margin-top:3px"><input type="checkbox" name="breb_epayco_enabled" value="1"<?= $s['epayco_enabled'] ? ' checked' : '' ?>> Mostrar ePayco</label>
          <div class="row2"><div class="field"><label for="breb_epayco_label">Texto</label><input class="input" id="breb_epayco_label" name="breb_epayco_label" value="<?= breb_h($s['epayco_label']) ?>" maxlength="60"></div><div class="field"><label for="breb_epayco_link">Enlace de pago</label><input class="input" id="breb_epayco_link" name="breb_epayco_link" value="<?= breb_h($s['epayco_link']) ?>" placeholder="https://..." maxlength="500"></div></div>
          <div class="actions"><button class="btn" type="submit">Guardar pasarelas</button></div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="section">
        <h2>Comprobantes en disco</h2>
        <p class="hint">Los comprobantes privados se guardan fuera del acceso público. La descarga solo está disponible desde este panel.</p>
        <p class="disk"><b><?= (int)$usage['files'] ?></b> archivo(s) · <b><?= breb_h(breb_size((int)$usage['bytes'])) ?></b> en almacenamiento privado.</p>
        <div class="actions" style="margin-top:10px">
          <form method="post" onsubmit="return confirm('¿Depurar comprobantes aprobados con más de 30 días?');"><input type="hidden" name="_token" value="<?= breb_h($csrf) ?>"><input type="hidden" name="action" value="cleanup"><input type="hidden" name="mode" value="old"><button class="btn ghost sm" type="submit">Depurar pagados +30 días</button></form>
          <form method="post" onsubmit="return confirm('¿Depurar comprobantes rechazados con más de 7 días?');"><input type="hidden" name="_token" value="<?= breb_h($csrf) ?>"><input type="hidden" name="action" value="cleanup"><input type="hidden" name="mode" value="rejected"><button class="btn ghost sm" type="submit">Depurar rechazados +7 días</button></form>
          <form method="post" onsubmit="return confirm('¿Eliminar archivos huérfanos sin pedido asociado?');"><input type="hidden" name="_token" value="<?= breb_h($csrf) ?>"><input type="hidden" name="action" value="cleanup"><input type="hidden" name="mode" value="orphans"><button class="btn danger sm" type="submit">Eliminar huérfanos</button></form>
        </div>
      </div>

      <div class="section">
        <h2>Pedidos Bre-B</h2>
        <p class="hint">Descarga el comprobante y verifica el pago antes de marcarlo como pagado. Al aprobar, el pedido queda pagado y se acreditan puntos si aplica.</p>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Pedido</th><th>Cliente</th><th>Total</th><th>Pago</th><th>Comprobante</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php if (!$orders): ?>
              <tr><td colspan="6" class="empty">Aún no hay pedidos con Bre-B.</td></tr>
            <?php else: foreach ($orders as $o): $ps = (string)($o['payment_proof_status'] ?? 'none'); ?>
              <tr>
                <td><b><?= breb_h((string)$o['order_number']) ?></b><div class="tiny"><?= breb_h(breb_date((string)($o['created_at'] ?? ''))) ?></div></td>
                <td><?= breb_h((string)$o['customer_name']) ?><div class="tiny"><?= breb_h((string)$o['customer_email']) ?></div></td>
                <td class="mono"><?= breb_h(breb_money((int)$o['total_cents'], (string)($o['currency'] ?? 'COP'))) ?><?php if ((int)($o['discount_cents'] ?? 0) > 0): ?><div class="tiny">Ahorro <?= breb_h(breb_money((int)$o['discount_cents'])) ?></div><?php endif; ?></td>
                <td><span class="pill <?= ($o['payment_status'] ?? '') === 'paid' ? 'ok' : (($o['payment_status'] ?? '') === 'failed' ? 'err' : 'pend') ?>"><?= breb_h(ucfirst((string)($o['payment_status'] ?? ''))) ?></span></td>
                <td><span class="pill <?= $ps === 'approved' ? 'ok' : ($ps === 'rejected' ? 'err' : ($ps === 'pending' ? 'pend' : 'neutral')) ?>"><?= breb_h(breb_proof_label($ps)) ?></span><?php if (!empty($o['payment_proof_at'])): ?><div class="tiny"><?= breb_h(breb_date((string)$o['payment_proof_at'])) ?></div><?php endif; ?></td>
                <td>
                  <div class="actions">
                    <?php if (!empty($o['payment_proof_path'])): ?><a class="btn ghost sm" href="/admin-breb.php?download=<?= (int)$o['id'] ?>&_token=<?= rawurlencode($csrf) ?>">Descargar</a><?php endif; ?>
                    <?php if (($o['payment_status'] ?? '') !== 'paid'): ?>
                    <form method="post" onsubmit="return confirm('¿Marcar este pedido como pagado? Esta acción no se puede deshacer.');"><input type="hidden" name="_token" value="<?= breb_h($csrf) ?>"><input type="hidden" name="action" value="mark_paid"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><button class="btn sm" type="submit">Marcar pagado</button></form>
                    <?php endif; ?>
                  </div>
                  <?php if ($ps === 'pending'): ?>
                  <form method="post" class="inline"><input type="hidden" name="_token" value="<?= breb_h($csrf) ?>"><input type="hidden" name="action" value="reject_proof"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><input class="input" name="note" placeholder="Motivo (opcional)" maxlength="500"><button class="btn danger sm" type="submit">Rechazar</button></form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
