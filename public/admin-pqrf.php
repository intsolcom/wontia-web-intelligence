<?php
define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/vendor/autoload.php';

use App\Core\Config;
use App\Core\Session;
use App\Services\PqrfService;

Config::load();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');
Session::start();

if (!Session::isLoggedIn()) {
    header('Location: /admin');
    exit;
}
$__role = (string)Session::userRole();
if (!in_array($__role, PqrfService::ROLES, true)) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}

$svc = new PqrfService();
$svc->ensureTables();
$csrf = (string)Session::get('pqrf_admin_csrf', '');
if ($csrf === '') {
    $csrf = bin2hex(random_bytes(32));
    Session::set('pqrf_admin_csrf', $csrf);
}
$user = Session::user() ?: [];
$actorId = (int)($user['id'] ?? 0);
$flashOk = Session::flash('pqrf_admin_ok');
$flashErr = Session::flash('pqrf_admin_err');

function pqrf_h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function pqrf_local(?string $date): string
{
    if ($date === null || $date === '') return '—';
    try {
        $dt = new DateTime($date, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Bogota'));
        return $dt->format('d/m/Y H:i');
    } catch (\Throwable $e) {
        return (string)$date;
    }
}

function pqrf_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function pqrf_status_class(string $status): string
{
    return match ($status) {
        'RECEIVED' => 'st-received',
        'IN_REVIEW' => 'st-review',
        'INFO_REQUESTED' => 'st-info',
        'IN_PROGRESS' => 'st-progress',
        'RESPONDED' => 'st-responded',
        'CLOSED' => 'st-closed',
        default => 'st-neutral',
    };
}

function pqrf_size(int $bytes): string
{
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 0, ',', '.') . ' KB';
    return $bytes . ' B';
}

$id = (int)($_GET['id'] ?? 0);
$download = (int)($_GET['download'] ?? 0);

if ($download > 0 && $id > 0) {
    $token = (string)($_GET['_token'] ?? '');
    if (!hash_equals($csrf, $token)) {
        http_response_code(403);
        echo 'Solicitud no válida.';
        exit;
    }
    $file = $svc->attachmentForAdmin($id, $download);
    if (!$file) {
        http_response_code(404);
        echo 'Archivo no encontrado.';
        exit;
    }
    $name = str_replace(["\r", "\n"], '', (string)$file['original_name']);
    $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: 'archivo';
    header('Content-Type: ' . (string)($file['mime'] ?? 'application/octet-stream'));
    header('Content-Length: ' . (string)filesize((string)$file['full_path']));
    header('Content-Disposition: attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($name));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    readfile((string)$file['full_path']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['_token'] ?? '');
    $caseId = (int)($_POST['case_id'] ?? 0);
    if (!hash_equals($csrf, $token)) {
        Session::flash('pqrf_admin_err', 'La sesión del formulario expiró. Intenta de nuevo.');
        pqrf_redirect($caseId > 0 ? '/admin-pqrf.php?id=' . $caseId : '/admin-pqrf.php');
    }
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'assign') {
        $r = $svc->assign($caseId, (int)($_POST['assignee_id'] ?? 0) ?: null, $actorId);
    } elseif ($action === 'manage') {
        $r = $svc->setStatus(
            $caseId,
            (string)($_POST['status'] ?? ''),
            $actorId,
            (string)($_POST['priority'] ?? ''),
            (string)($_POST['public_reply'] ?? ''),
            (string)($_POST['internal_note'] ?? '')
        );
    } elseif ($action === 'note') {
        $r = $svc->addNote($caseId, (string)($_POST['note'] ?? ''), !empty($_POST['visible']), $actorId);
    } else {
        $r = ['ok' => false, 'message' => 'Acción no válida.'];
    }
    if (empty($r['ok'])) Session::flash('pqrf_admin_err', (string)($r['message'] ?? 'No se pudo guardar.'));
    else Session::flash('pqrf_admin_ok', (string)($r['message'] ?? 'Guardado.'));
    pqrf_redirect('/admin-pqrf.php' . ($caseId > 0 ? '?id=' . $caseId : ''));
}

$case = $id > 0 ? $svc->findForAdmin($id) : null;
if ($id > 0 && !$case) {
    http_response_code(404);
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>PQRS / PQRF — SONIKA</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--ink:#111;--muted:#64748b;--line:#e6eae8;--soft:#f6f8f7;--brand:#F20D16;--brand-soft:#fff5f5;--ok:#0a6b49;--warn:#8a5a00;--err:#b42318}
body{font-family:Inter,system-ui,-apple-system,sans-serif;background:#f4f7f6;color:var(--ink);font-size:14px;line-height:1.5}
a{color:var(--brand);text-decoration:none}
a:hover{text-decoration:underline}
.wrap{max-width:1180px;margin:0 auto;padding:22px 18px 56px}
.top{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px}
.top h1{font-size:22px;letter-spacing:-.02em;line-height:1.2}
.top .sub{color:var(--muted);font-size:12.5px}
.top .spacer{flex:1}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:40px;padding:9px 14px;border:0;border-radius:9px;background:var(--brand);color:#fff;font:inherit;font-size:13px;font-weight:700;cursor:pointer;transition:filter .14s ease,transform .14s ease}
.btn:hover{filter:brightness(.94);text-decoration:none}
.btn:active{transform:translateY(1px)}
.btn:focus-visible{outline:3px solid rgba(242,13,22,.3);outline-offset:2px}
.btn.sec{background:#fff;color:var(--ink);border:1px solid var(--line)}
.btn.sec:hover{border-color:var(--brand);color:var(--brand);filter:none}
.btn.sm{min-height:34px;padding:7px 11px;font-size:12px}
.flash{margin:0 0 14px;padding:11px 14px;border-radius:10px;font-size:13px;font-weight:600}
.flash.ok{background:#f0fbf6;color:var(--ok);border:1px solid #bfe8d5}
.flash.err{background:#fff5f5;color:var(--err);border:1px solid #f5c2c2}
.stats{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin:0 0 16px}
.stat{background:#fff;border:1px solid var(--line);border-radius:12px;padding:11px 12px}
.stat small{display:block;color:var(--muted);font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px}
.stat b{font-size:19px;line-height:1.1}
.stat.hot b{color:var(--brand)}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 10px 28px rgba(16,24,40,.05)}
.filters{display:grid;grid-template-columns:minmax(180px,1.4fr) repeat(4,minmax(120px,.8fr)) auto;gap:8px;align-items:end;padding:12px}
.field{min-width:0}
.field label{display:block;margin-bottom:4px;color:var(--muted);font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.input,.select,.textarea{display:block;width:100%;min-height:40px;padding:9px 10px;border:1px solid #dbe2df;border-radius:8px;background:#fff;color:var(--ink);font:inherit;font-size:14px}
.textarea{min-height:96px;resize:vertical}
.input:focus-visible,.select:focus-visible,.textarea:focus-visible{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(242,13,22,.1)}
.table-wrap{overflow-x:auto;border-radius:14px}
table{width:100%;border-collapse:collapse;font-size:13px;min-width:860px}
th,td{padding:10px 12px;text-align:left;border-bottom:1px solid #f0f4f2;vertical-align:middle}
th{background:#f6fbf9;color:var(--muted);font-size:10.5px;text-transform:uppercase;letter-spacing:.05em;font-weight:800;white-space:nowrap}
tbody tr:hover{background:#fbfdfc}
td .num{font-weight:800;color:var(--ink);white-space:nowrap}
td small{display:block;color:var(--muted);font-size:11.5px;margin-top:1px}
.pill{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:99px;font-size:10.5px;font-weight:800;white-space:nowrap;line-height:1.5}
.st-received{background:#fff3cd;color:#7a4a05}
.st-review{background:#eef4fb;color:#155eeb}
.st-info{background:#fff8ec;color:#8a5a00}
.st-progress{background:#eef4fb;color:#155eeb}
.st-responded{background:#f0fbf6;color:var(--ok)}
.st-closed{background:#f1f1f1;color:#555}
.st-neutral{background:#f1f1f1;color:#555}
.prio{font-size:11px;font-weight:700;color:#555}
.prio.HIGH{color:#b45309}.prio.URGENT{color:var(--brand)}.prio.LOW{color:#64748b}
.due{font-size:11.5px;color:var(--muted);white-space:nowrap}
.due.over{color:var(--err);font-weight:700}
.empty{padding:44px 18px;text-align:center;color:var(--muted)}
.empty b{display:block;color:var(--ink);font-size:16px;margin-bottom:4px}
.pager{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px}
.pager .links{display:flex;gap:6px;flex-wrap:wrap}
.pager a,.pager span{display:inline-flex;align-items:center;justify-content:center;min-width:36px;min-height:36px;padding:0 10px;border:1px solid var(--line);border-radius:8px;background:#fff;font-size:12px;font-weight:700;color:var(--ink)}
.pager a:hover{border-color:var(--brand);color:var(--brand);text-decoration:none}
.pager .on{background:var(--brand);border-color:var(--brand);color:#fff}
.detail-head{display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:14px}
.detail-head h2{font-size:24px;letter-spacing:-.02em;line-height:1.15}
.detail-head .meta{display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-top:5px}
.detail-grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(280px,.9fr);gap:14px;align-items:start}
.panel{padding:16px}
.panel h3{font-size:13px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin:0 0 10px}
.kv{display:grid;grid-template-columns:110px minmax(0,1fr);gap:7px 10px;font-size:13px}
.kv dt{color:var(--muted);font-weight:600}
.kv dd{margin:0;overflow-wrap:anywhere}
.desc{margin:12px 0 0;padding:12px;border-radius:9px;background:var(--soft);border:1px solid var(--line);white-space:pre-wrap;overflow-wrap:anywhere;font-size:13.5px;line-height:1.6}
.files{list-style:none;margin:0;padding:0;display:grid;gap:7px}
.files a{display:flex;align-items:center;justify-content:space-between;gap:10px;min-height:40px;padding:8px 10px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--ink);font-size:12.5px;font-weight:600}
.files a:hover{border-color:var(--brand);color:var(--brand);text-decoration:none}
.files span{color:var(--muted);font-size:11px;white-space:nowrap}
.timeline{list-style:none;margin:0;padding:0}
.event{position:relative;padding:0 0 15px 22px;border-left:2px solid #ececec}
.event:last-child{border-left-color:transparent;padding-bottom:0}
.event::before{content:"";position:absolute;left:-6px;top:2px;width:10px;height:10px;border-radius:50%;background:#fff;border:3px solid var(--brand)}
.event.int::before{border-color:#9ca3af}
.event-head{display:flex;align-items:baseline;gap:7px;flex-wrap:wrap;margin-bottom:2px}
.event-head b{font-size:12.5px}
.event-head time{color:var(--muted);font-size:11px}
.event p{margin:0;color:#444;font-size:12.5px;line-height:1.5;white-space:pre-wrap;overflow-wrap:anywhere}
.tag{display:inline-block;padding:1px 7px;border-radius:99px;background:#f1f1f1;color:#555;font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:.05em}
.form-grid{display:grid;gap:10px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.form-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:2px}
.hint{color:var(--muted);font-size:11.5px;line-height:1.45;margin:0}
.back{font-size:12.5px;font-weight:700;color:var(--muted)}
.back:hover{color:var(--brand);text-decoration:none}
@media(max-width:900px){.stats{grid-template-columns:repeat(3,minmax(0,1fr))}.filters{grid-template-columns:1fr 1fr}.detail-grid{grid-template-columns:1fr}.form-row{grid-template-columns:1fr}}
@media(max-width:560px){.wrap{padding:16px 12px 46px}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.filters{grid-template-columns:1fr}.input,.select,.textarea{font-size:16px}.btn{min-height:44px}.pager{flex-direction:column;align-items:stretch}.pager .links{justify-content:center}.kv{grid-template-columns:1fr}.top h1{font-size:20px}}
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div>
      <h1>PQRS / PQRF · SONIKA</h1>
      <div class="sub">Bandeja privada de solicitudes de clientes. Los datos personales solo se muestran al equipo autorizado.</div>
    </div>
    <div class="spacer"></div>
    <a class="btn sec" href="/pqrf" target="_blank" rel="noopener">Ver formulario público</a>
    <a class="btn sec" href="/admin">Volver al panel</a>
  </div>

  <?php if ($flashOk): ?><div class="flash ok"><?= pqrf_h($flashOk) ?></div><?php endif; ?>
  <?php if ($flashErr): ?><div class="flash err"><?= pqrf_h($flashErr) ?></div><?php endif; ?>

<?php if ($id > 0 && !$case): ?>
  <div class="card"><div class="empty"><b>Radicado no encontrado</b><p>No existe o no pertenece a este sitio.</p><p style="margin-top:12px"><a class="btn sec" href="/admin-pqrf.php">Volver a la bandeja</a></p></div></div>
<?php elseif ($case): ?>
  <?php
    $events = $svc->eventsForAdmin((int)$case['id']);
    $attachments = $svc->attachmentsFor((int)$case['id']);
    $assignees = $svc->assignees();
    $due = $case['due_at'] ?? null;
    $isOverdue = $due && strtotime((string)$due . ' UTC') < time() && !in_array((string)$case['status'], ['RESPONDED', 'CLOSED'], true);
  ?>
  <div class="detail-head">
    <div>
      <a class="back" href="/admin-pqrf.php">← Bandeja PQRS</a>
      <h2 style="margin-top:5px"><?= pqrf_h((string)$case['case_number']) ?></h2>
      <div class="meta">
        <span class="pill <?= pqrf_status_class((string)$case['status']) ?>"><?= pqrf_h($svc->statusLabel((string)$case['status'])) ?></span>
        <span class="tag"><?= pqrf_h($svc->typeLabel((string)$case['type'])) ?></span>
        <span class="tag"><?= pqrf_h($svc->categoryLabel((string)($case['category'] ?? ''))) ?></span>
        <span class="prio <?= pqrf_h((string)$case['priority']) ?>">Prioridad: <?= pqrf_h($svc->priorityLabel((string)$case['priority'])) ?></span>
      </div>
    </div>
    <div class="spacer"></div>
    <div style="text-align:right;font-size:12px;color:#64748b">
      <div>Creado: <b style="color:#111"><?= pqrf_h(pqrf_local((string)$case['created_at'])) ?></b></div>
      <div>Actualizado: <?= pqrf_h(pqrf_local((string)$case['updated_at'])) ?></div>
      <div class="<?= $isOverdue ? 'due over' : 'due' ?>" style="margin-top:3px"><?= $due ? ('Plazo: ' . pqrf_h(pqrf_local((string)$due))) : 'Plazo: pendiente de validación' ?></div>
    </div>
  </div>

  <div class="detail-grid">
    <div style="display:grid;gap:14px">
      <div class="card panel">
        <h3>Solicitud</h3>
        <dl class="kv">
          <dt>Asunto</dt><dd><b><?= pqrf_h((string)$case['subject']) ?></b></dd>
          <dt>Radicado</dt><dd><?= pqrf_h((string)$case['case_number']) ?></dd>
          <dt>Pedido</dt><dd><?= pqrf_h((string)($case['order_ref'] ?? '') ?: '—') ?></dd>
          <dt>Contacto</dt><dd><?= pqrf_h((string)$case['contact_name']) ?><br><?= pqrf_h((string)$case['contact_email']) ?><?= !empty($case['contact_phone']) ? '<br>' . pqrf_h((string)$case['contact_phone']) : '' ?></dd>
          <dt>Responsable</dt><dd><?= pqrf_h((string)($case['assignee_name'] ?? '') ?: 'Sin asignar') ?></dd>
        </dl>
        <div class="desc"><?= pqrf_h((string)$case['description']) ?></div>
        <?php if ($attachments): ?>
        <h3 style="margin-top:16px">Adjuntos privados</h3>
        <ul class="files">
          <?php foreach ($attachments as $att): ?>
          <li><a href="/admin-pqrf.php?id=<?= (int)$case['id'] ?>&amp;download=<?= (int)$att['id'] ?>&amp;_token=<?= pqrf_h($csrf) ?>"><b><?= pqrf_h((string)$att['original_name']) ?></b><span><?= pqrf_h(pqrf_size((int)$att['bytes'])) ?></span></a></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>

      <div class="card panel">
        <h3>Historial completo</h3>
        <?php if (!$events): ?>
          <p class="hint">Sin eventos registrados.</p>
        <?php else: ?>
        <ol class="timeline">
          <?php foreach ($events as $ev):
            $internal = (int)($ev['visible_to_customer'] ?? 0) === 0;
          ?>
          <li class="event<?= $internal ? ' int' : '' ?>">
            <div class="event-head">
              <b><?= pqrf_h($svc->eventLabel($ev)) ?></b>
              <?php if ($internal): ?><span class="tag">Interno</span><?php endif; ?>
              <time datetime="<?= pqrf_h((string)$ev['created_at']) ?>"><?= pqrf_h(pqrf_local((string)$ev['created_at'])) ?></time>
            </div>
            <?php if (!empty($ev['note'])): ?><p><?= pqrf_h((string)$ev['note']) ?></p><?php endif; ?>
            <?php if (!empty($ev['to_status']) && in_array((string)$ev['event'], ['STATUS_CHANGED'], true)): ?><p style="margin-top:3px;color:#64748b;font-size:11.5px">Estado: <?= pqrf_h($svc->statusLabel((string)$ev['to_status'])) ?></p><?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ol>
        <?php endif; ?>
      </div>
    </div>

    <div style="display:grid;gap:14px">
      <div class="card panel">
        <h3>Asignación</h3>
        <form method="post" class="form-grid">
          <input type="hidden" name="_token" value="<?= pqrf_h($csrf) ?>">
          <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">
          <input type="hidden" name="action" value="assign">
          <div class="field">
            <label for="assignee_id">Responsable</label>
            <select class="select" id="assignee_id" name="assignee_id">
              <option value="0">Sin asignar</option>
              <?php foreach ($assignees as $a): ?>
              <option value="<?= (int)$a['id'] ?>"<?= (int)($case['assignee_id'] ?? 0) === (int)$a['id'] ? ' selected' : '' ?>><?= pqrf_h((string)$a['username']) ?> · <?= pqrf_h((string)$a['role']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-actions"><button class="btn" type="submit">Guardar asignación</button></div>
        </form>
      </div>

      <div class="card panel">
        <h3>Gestión</h3>
        <form method="post" class="form-grid">
          <input type="hidden" name="_token" value="<?= pqrf_h($csrf) ?>">
          <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">
          <input type="hidden" name="action" value="manage">
          <div class="form-row">
            <div class="field">
              <label for="status">Estado</label>
              <select class="select" id="status" name="status">
                <?php foreach (PqrfService::STATUSES as $k => $label): ?>
                <option value="<?= pqrf_h($k) ?>"<?= (string)$case['status'] === $k ? ' selected' : '' ?>><?= pqrf_h($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="priority">Prioridad</label>
              <select class="select" id="priority" name="priority">
                <?php foreach (PqrfService::PRIORITIES as $k => $label): ?>
                <option value="<?= pqrf_h($k) ?>"<?= (string)$case['priority'] === $k ? ' selected' : '' ?>><?= pqrf_h($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="field">
            <label for="public_reply">Respuesta visible para el cliente</label>
            <textarea class="textarea" id="public_reply" name="public_reply" placeholder="Esta respuesta aparecerá en el seguimiento del cliente."></textarea>
            <p class="hint">No incluyas notas internas ni datos de terceros.</p>
          </div>
          <div class="field">
            <label for="internal_note">Nota interna (no visible)</label>
            <textarea class="textarea" id="internal_note" name="internal_note" placeholder="Solo para el equipo."></textarea>
          </div>
          <div class="form-actions"><button class="btn" type="submit">Guardar gestión</button></div>
        </form>
      </div>

      <div class="card panel">
        <h3>Nota rápida</h3>
        <form method="post" class="form-grid">
          <input type="hidden" name="_token" value="<?= pqrf_h($csrf) ?>">
          <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">
          <input type="hidden" name="action" value="note">
          <div class="field">
            <label for="note">Nota</label>
            <textarea class="textarea" id="note" name="note" required placeholder="Escribe una nota breve..."></textarea>
          </div>
          <label class="hint" style="display:flex;align-items:center;gap:7px;cursor:pointer"><input type="checkbox" name="visible" value="1"> Visible para el cliente</label>
          <div class="form-actions"><button class="btn sec" type="submit">Agregar nota</button></div>
        </form>
      </div>
    </div>
  </div>
<?php else: ?>
  <?php
    $filters = [
        'q' => trim((string)($_GET['q'] ?? '')),
        'status' => strtoupper(trim((string)($_GET['status'] ?? ''))),
        'type' => strtoupper(trim((string)($_GET['type'] ?? ''))),
        'priority' => strtoupper(trim((string)($_GET['priority'] ?? ''))),
        'assignee' => trim((string)($_GET['assignee'] ?? '')),
        'due' => trim((string)($_GET['due'] ?? '')),
        'page' => max(1, (int)($_GET['page'] ?? 1)),
    ];
    $stats = $svc->stats();
    $data = $svc->listForAdmin($filters);
    $assignees = $svc->assignees();
    $queryBase = $filters;
    unset($queryBase['page']);
    $pageUrl = function (int $p) use ($queryBase): string {
        $queryBase['page'] = $p;
        return '/admin-pqrf.php?' . http_build_query($queryBase);
    };
  ?>
  <div class="stats">
    <div class="stat"><small>Total</small><b><?= (int)($stats['total'] ?? 0) ?></b></div>
    <div class="stat hot"><small>Recibidos</small><b><?= (int)($stats['received'] ?? 0) ?></b></div>
    <div class="stat"><small>En revisión</small><b><?= (int)($stats['in_review'] ?? 0) ?></b></div>
    <div class="stat"><small>En gestión</small><b><?= (int)($stats['in_progress'] ?? 0) ?></b></div>
    <div class="stat"><small>Sin asignar</small><b><?= (int)($stats['unassigned'] ?? 0) ?></b></div>
    <div class="stat hot"><small>Vencidos</small><b><?= (int)($stats['overdue'] ?? 0) ?></b></div>
  </div>

  <form class="card filters" method="get" action="/admin-pqrf.php">
    <div class="field">
      <label for="q">Buscar</label>
      <input class="input" id="q" name="q" value="<?= pqrf_h($filters['q']) ?>" placeholder="Radicado, asunto, nombre o correo">
    </div>
    <div class="field">
      <label for="status">Estado</label>
      <select class="select" id="status" name="status">
        <option value="">Todos</option>
        <?php foreach (PqrfService::STATUSES as $k => $label): ?>
        <option value="<?= pqrf_h($k) ?>"<?= $filters['status'] === $k ? ' selected' : '' ?>><?= pqrf_h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="type">Tipo</label>
      <select class="select" id="type" name="type">
        <option value="">Todos</option>
        <?php foreach (PqrfService::TYPES as $k => $label): ?>
        <option value="<?= pqrf_h($k) ?>"<?= $filters['type'] === $k ? ' selected' : '' ?>><?= pqrf_h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="priority">Prioridad</label>
      <select class="select" id="priority" name="priority">
        <option value="">Todas</option>
        <?php foreach (PqrfService::PRIORITIES as $k => $label): ?>
        <option value="<?= pqrf_h($k) ?>"<?= $filters['priority'] === $k ? ' selected' : '' ?>><?= pqrf_h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="assignee">Responsable</label>
      <select class="select" id="assignee" name="assignee">
        <option value="">Todos</option>
        <option value="none"<?= $filters['assignee'] === 'none' ? ' selected' : '' ?>>Sin asignar</option>
        <?php foreach ($assignees as $a): ?>
        <option value="<?= (int)$a['id'] ?>"<?= $filters['assignee'] === (string)$a['id'] ? ' selected' : '' ?>><?= pqrf_h((string)$a['username']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-actions" style="margin:0">
      <button class="btn" type="submit">Filtrar</button>
      <a class="btn sec" href="/admin-pqrf.php">Limpiar</a>
    </div>
  </form>

  <div class="card" style="margin-top:14px">
    <?php if (empty($data['items'])): ?>
      <div class="empty"><b>No hay solicitudes con estos filtros.</b><p>La bandeja está al día. Las nuevas solicitudes aparecerán aquí.</p></div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Radicado</th><th>Fecha</th><th>Tipo</th><th>Prioridad</th><th>Estado</th><th>Responsable</th><th>Plazo</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($data['items'] as $row):
            $due = $row['due_at'] ?? null;
            $over = $due && strtotime((string)$due . ' UTC') < time() && !in_array((string)$row['status'], ['RESPONDED', 'CLOSED'], true);
          ?>
            <tr>
              <td><span class="num"><?= pqrf_h((string)$row['case_number']) ?></span><small><?= pqrf_h((string)$row['subject']) ?></small></td>
              <td style="white-space:nowrap"><?= pqrf_h(pqrf_local((string)$row['created_at'])) ?></td>
              <td><?= pqrf_h($svc->typeLabel((string)$row['type'])) ?><small><?= pqrf_h($svc->categoryLabel((string)($row['category'] ?? ''))) ?></small></td>
              <td><span class="prio <?= pqrf_h((string)$row['priority']) ?>"><?= pqrf_h($svc->priorityLabel((string)$row['priority'])) ?></span></td>
              <td><span class="pill <?= pqrf_status_class((string)$row['status']) ?>"><?= pqrf_h($svc->statusLabel((string)$row['status'])) ?></span></td>
              <td><?= pqrf_h((string)($row['assignee_name'] ?? '') ?: 'Sin asignar') ?></td>
              <td><span class="due <?= $over ? 'over' : '' ?>"><?= $due ? pqrf_h(pqrf_local((string)$due)) : 'Pendiente de validación' ?></span></td>
              <td><a class="btn sm sec" href="/admin-pqrf.php?id=<?= (int)$row['id'] ?>">Ver</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="pager">
        <span style="font-size:12px;color:#64748b"><?= (int)$data['total'] ?> solicitud(es) · Página <?= (int)$data['page'] ?> de <?= (int)$data['pages'] ?></span>
        <div class="links">
          <?php if ($data['page'] > 1): ?><a href="<?= pqrf_h($pageUrl($data['page'] - 1)) ?>">Anterior</a><?php endif; ?>
          <?php for ($p = max(1, $data['page'] - 2); $p <= min((int)$data['pages'], $data['page'] + 2); $p++): ?>
            <?php if ($p === (int)$data['page']): ?><span class="on"><?= $p ?></span><?php else: ?><a href="<?= pqrf_h($pageUrl($p)) ?>"><?= $p ?></a><?php endif; ?>
          <?php endfor; ?>
          <?php if ($data['page'] < $data['pages']): ?><a href="<?= pqrf_h($pageUrl($data['page'] + 1)) ?>">Siguiente</a><?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
</div>
</body>
</html>
