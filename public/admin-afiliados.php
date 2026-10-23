<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Config;
use App\Core\Session;
use App\Services\AffiliateService;

Config::load();
header('Cache-Control: no-store, no-cache, must-revalidate');
Session::start();
if (!Session::isLoggedIn()) { header('Location: /admin'); exit; }
$role = (string)Session::userRole();
if (!in_array($role, ['superadmin', 'admin', 'editor'], true)) { http_response_code(403); echo 'Acceso denegado'; exit; }

$svc = new AffiliateService();
if (isset($_GET['doc'])) {
    $docId = (int)$_GET['doc'];
    $path = '';
    try { $st = \App\Core\Database::instance()->prepare('SELECT path FROM affiliate_documents WHERE id = :id AND site_id = @site_id LIMIT 1'); $st->execute(['id' => $docId]); $path = (string)$st->fetchColumn(); } catch (\Throwable $e) {}
    if ($path !== '' && is_file('/app/public' . $path)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="documento-' . $docId . '.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile('/app/public' . $path);
        exit;
    }
    http_response_code(404);
    echo 'Documento no encontrado.';
    exit;
}
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $st = (string)($_POST['status'] ?? '');
    if ($id > 0 && in_array($st, AffiliateService::STATUSES, true)) {
        if ($svc->setStatus($id, $st)) {
            if ($st === 'APPROVED') $svc->ensureLink($id);
            $msg = 'Actualizado #' . $id . ' → ' . $st;
        }
    }
}
$profiles = $svc->listProfiles(300);
$ready = $svc->ready();
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" /><meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Afiliados — SONIKA Artist Partners</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Inter,system-ui,sans-serif;background:#f4f7f6;color:#17212b;padding:24px}
.head{max-width:1120px;margin:0 auto 18px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.head h1{font-size:22px;letter-spacing:-.02em}
.head a{margin-left:auto;color:#0d7a58;font-weight:700;text-decoration:none;font-size:13px}
.msg{max-width:1120px;margin:0 auto 12px;background:#e5f5ef;color:#0d7a58;border-radius:10px;padding:10px 14px;font-weight:600;font-size:13px}
.tbl{max-width:1120px;margin:0 auto;background:#fff;border:1px solid #e6eeea;border-radius:14px;overflow:hidden;box-shadow:0 10px 28px rgba(16,24,40,.06)}
table{width:100%;border-collapse:collapse;font-size:13.5px}
th,td{padding:11px 14px;text-align:left;border-bottom:1px solid #f0f4f2;vertical-align:middle}
th{background:#f6fbf9;font-size:11.5px;text-transform:uppercase;letter-spacing:.05em;color:#64748b}
td small{color:#94a3b8}
.st{display:inline-block;border-radius:999px;padding:3px 10px;font-size:11px;font-weight:800;background:#eef4fb;color:#155eeb}
.st.APPROVED{background:#e5f5ef;color:#0d7a58}.st.REJECTED,.st.SUSPENDED,.st.TERMINATED{background:#fdecec;color:#b42318}
.st.DRAFT,.st.REVIEW_REQUIRED,.st.EMAIL_PENDING,.st.PHONE_PENDING,.st.DOCUMENTS_PENDING{background:#fff3cd;color:#8a6d00}
form{display:flex;gap:6px;align-items:center}
select{padding:7px 9px;border:1px solid #dbe7e1;border-radius:8px;font:inherit;font-size:12.5px}
button{padding:7px 12px;border:0;border-radius:8px;background:#0d9f72;color:#fff;font-weight:700;font-size:12.5px;cursor:pointer}
.empty{padding:40px;text-align:center;color:#94a3b8}
</style>
</head>
<body>
<div class="head"><h1>Afiliados · SONIKA Artist Partners</h1><a href="/admin">← Volver al panel</a></div>
<?php if ($msg !== ''): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if (!$ready): ?><div class="tbl"><div class="empty">Las tablas de afiliados aún no están creadas.</div></div>
<?php elseif (!$profiles): ?><div class="tbl"><div class="empty">Aún no hay solicitudes.</div></div>
<?php else: ?>
<div class="tbl"><table>
<thead><tr><th>#</th><th>Artista / Correo</th><th>Instrumento</th><th>Estado</th><th>Documentos</th><th>Acción</th></tr></thead>
<tbody>
<?php foreach ($profiles as $p):
    $doc = preg_replace('/\D/', '', (string)($p['doc_number'] ?? ''));
    $docMask = $doc !== '' ? '****' . substr($doc, -4) : '—';
    $name = $p['artist_name'] ?: ($p['public_name'] ?: $p['full_name']);
?>
<tr>
  <td>#<?= (int)$p['id'] ?></td>
  <td><b><?= htmlspecialchars($name) ?></b><br><small><?= htmlspecialchars((string)$p['email']) ?> · Doc <?= htmlspecialchars($docMask) ?></small></td>
  <td><?= htmlspecialchars((string)($p['music_instrument'] ?? '—')) ?></td>
  <td><span class="st <?= htmlspecialchars((string)$p['status']) ?>"><?= htmlspecialchars((string)$p['status']) ?></span></td>
  <td><?php
      try {
          $ds = \App\Core\Database::instance()->prepare('SELECT id, kind FROM affiliate_documents WHERE affiliate_id = :a AND site_id = @site_id ORDER BY id');
          $ds->execute(['a' => (int)$p['id']]);
          $docs = $ds->fetchAll();
      } catch (\Throwable $e) { $docs = []; }
      if (!$docs) echo '<small>—</small>';
      foreach ($docs as $d) echo '<a href="?doc=' . (int)$d['id'] . '" style="display:inline-block;margin:1px 4px 1px 0;font-size:11.5px;color:#155eeb">' . htmlspecialchars((string)$d['kind']) . '</a>';
  ?></td>
  <td><form method="post"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>" />
    <select name="status"><?php foreach (AffiliateService::STATUSES as $s): ?><option value="<?= $s ?>"<?= $s === $p['status'] ? ' selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
    <button type="submit">Guardar</button></form></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</body></html>
