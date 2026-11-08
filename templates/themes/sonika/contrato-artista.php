<?php
$contract = (new \App\Services\ArtistContractService())->current();
$acceptance = $contract ? (new \App\Services\ArtistContractService())->latestAcceptance((int)$affiliate['id'], (int)$contract['id']) : null;
$signed = !empty($acceptance['signature_image']);
$signatureSrc = $signed ? (string)$acceptance['signature_image'] : '';
?>
<section class="ac">
  <style>
    .ac{background:#f6f4ef;color:#171717;padding:32px 0 56px}
    .ac *{box-sizing:border-box}
    .ac__wrap{max-width:980px;margin:0 auto;padding:0 20px}
    .ac__head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:18px}
    .ac__head h1{font:850 clamp(1.7rem,3vw,2.5rem)/1.05 Sora,Inter,system-ui,sans-serif;letter-spacing:-.035em;margin:5px 0}
    .ac__eyebrow{font-size:10px;font-weight:850;letter-spacing:.13em;text-transform:uppercase;color:#F20D16}
    .ac__meta{font-size:11.5px;color:#6b7280}
    .ac__status{display:inline-flex;align-items:center;gap:7px;border-radius:999px;padding:6px 10px;font-size:11px;font-weight:800;background:#fef3c7;color:#8a5a00}
    .ac__status--ok{background:#ecfdf5;color:#047857}
    .ac__card{background:#fff;border:1px solid #e5e0d8;border-radius:18px;box-shadow:0 16px 38px rgba(17,17,17,.06);overflow:hidden}
    .ac__doc{max-height:52vh;overflow:auto;padding:22px;font-size:12.5px;line-height:1.7;color:#374151;white-space:normal}
    .ac__doc h2{font-size:14px;color:#111;margin:18px 0 6px}
    .ac__doc p{margin:0 0 10px}
    .ac__doc strong{color:#111}
    .ac__sign{padding:20px 22px;border-top:1px solid #ece8e1;background:#fbfaf7}
    .ac__sign h2{font:800 18px Sora,Inter,system-ui,sans-serif;margin:0 0 4px;color:#111}
    .ac__sign p{font-size:12px;line-height:1.6;color:#666;margin:0 0 14px}
    .ac__fields{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .ac__fields label{display:grid;gap:5px;font-size:10.5px;font-weight:800;color:#555;text-transform:uppercase;letter-spacing:.03em}
    .ac__fields input{width:100%;min-height:42px;border:1px solid #d9d4cc;border-radius:10px;padding:9px 11px;font:inherit;font-size:13px;text-transform:none;letter-spacing:0;background:#fff}
    .ac__pad-wrap{margin-top:14px;position:relative}
    .ac__pad{display:block;width:100%;height:220px;border:1px dashed #b9b2a8;border-radius:12px;background:#fff;touch-action:none;cursor:crosshair}
    .ac__pad-actions{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:7px;flex-wrap:wrap}
    .ac__hint{font-size:10.5px;color:#7c7469}
    .ac__clear{border:0;background:none;color:#b42318;font:inherit;font-size:11px;font-weight:750;cursor:pointer;text-decoration:underline;text-underline-offset:2px}
    .ac__accept{display:flex;align-items:flex-start;gap:8px;margin-top:14px;font-size:11.5px;line-height:1.5;color:#555;text-transform:none;letter-spacing:0;font-weight:500}
    .ac__accept input{width:17px;height:17px;margin-top:2px;flex:0 0 auto}
    .ac__submit{margin-top:14px;width:100%;min-height:46px;border:0;border-radius:11px;background:#F20D16;color:#fff;font:850 13px Sora,Inter,system-ui,sans-serif;cursor:pointer}
    .ac__submit:disabled{background:#d1d5db;color:#6b7280;cursor:not-allowed}
    .ac__msg{min-height:19px;margin-top:9px;font-size:12px;font-weight:700;color:#4b5563}
    .ac__signed{display:grid;grid-template-columns:minmax(0,1fr) 220px;gap:20px;align-items:center;padding:20px 22px;background:#ecfdf5;border-top:1px solid #c6f0dc}
    .ac__signed b{display:block;color:#047857;font-size:14px;margin-bottom:5px}
    .ac__signed p{font-size:11.5px;line-height:1.6;color:#4b6359;margin:0}
    .ac__signed img{width:100%;max-height:110px;object-fit:contain;background:#fff;border:1px solid #c6f0dc;border-radius:9px;padding:6px}
    .ac__hash{font-family:ui-monospace,Menlo,monospace;font-size:9.5px;word-break:break-all;color:#6b7280;margin-top:6px}
    .ac__back{display:inline-flex;margin-top:16px;color:#51607a;font-size:12px;font-weight:750;text-decoration:none}
    @media(max-width:650px){.ac{padding-top:20px}.ac__wrap{padding:0 14px}.ac__doc{padding:16px;max-height:48vh}.ac__sign{padding:16px}.ac__fields{grid-template-columns:1fr}.ac__signed{grid-template-columns:1fr}.ac__signed img{max-height:140px}}
  </style>
  <div class="ac__wrap">
    <div class="ac__head">
      <div><span class="ac__eyebrow">SONIKA Partners</span><h1>Contrato del Partner</h1><div class="ac__meta">Versión <?= sonika_esc((string)($contract['version'] ?? '—')) ?> · <?= sonika_esc((string)($contract['effective_at'] ?? '')) ?><br>Intsolcom, LLC · Intsolcom S.A.S. · NIT 901749025-8</div></div>
      <span class="ac__status<?= $signed ? ' ac__status--ok' : '' ?>"><?= $signed ? 'Contrato firmado' : 'Firma pendiente' ?></span>
    </div>
    <div class="ac__card">
      <article class="ac__doc"><?= nl2br(sonika_esc((string)($contract['body'] ?? 'Contrato no disponible.'))) ?></article>
      <?php if ($signed): ?>
      <div class="ac__signed">
        <div><b>Firma electrónica registrada</b><p><?= sonika_esc((string)($acceptance['signer_name'] ?? '')) ?> · <?= sonika_esc((string)($acceptance['signed_at'] ?? '')) ?><br>Documento: <?= sonika_esc((string)($acceptance['signer_document'] ?? '')) ?></p><div class="ac__hash">Contrato: <?= sonika_esc((string)($acceptance['contract_hash'] ?? '')) ?><br>Firma: <?= sonika_esc((string)($acceptance['signature_hash'] ?? '')) ?></div></div>
        <?php if (str_starts_with($signatureSrc, 'data:image/png;base64,')): ?><img src="<?= sonika_esc($signatureSrc) ?>" alt="Firma del artista" /><?php endif; ?>
      </div>
      <?php else: ?>
      <div class="ac__sign">
        <h2>Firma para aceptar</h2>
        <p>Lee el contrato completo. Después escribe tu nombre y documento, firma con el dedo en el móvil o con el mouse en el computador y confirma la aceptación.</p>
        <form id="artistContractForm" novalidate>
          <div class="ac__fields"><label>Nombre completo<input id="acName" value="<?= sonika_esc((string)($affiliate['full_name'] ?? '')) ?>" autocomplete="name" required /></label><label>Documento<input id="acDocument" value="<?= sonika_esc((string)($affiliate['doc_number'] ?? '')) ?>" inputmode="numeric" required /></label></div>
          <div class="ac__pad-wrap"><canvas id="acSignature" class="ac__pad" aria-label="Área para firmar"></canvas><div class="ac__pad-actions"><span class="ac__hint">Firma dentro del recuadro. Puedes usar el dedo o el mouse.</span><button type="button" class="ac__clear" id="acClear">Limpiar firma</button></div></div>
          <label class="ac__accept"><input type="checkbox" id="acAccept" required /> He leído el contrato completo y acepto sus condiciones, incluida la autorización de uso de imagen, voz y contenidos, sin contraprestación distinta de las comisiones del programa.</label>
          <button type="submit" class="ac__submit" id="acSubmit">Firmar contrato</button>
          <div class="ac__msg" id="acMsg" role="status" aria-live="polite"></div>
        </form>
      </div>
      <?php endif; ?>
    </div>
    <a class="ac__back" href="/artista/<?= sonika_esc((string)($affiliate['slug'] ?? '')) ?>/panel">← Volver a Mi cuenta Partner SONIKA</a>
  </div>
  <script>
    (function () {
      var form = document.getElementById('artistContractForm');
      if (!form) return;
      var canvas = document.getElementById('acSignature');
      var ctx = canvas.getContext('2d');
      var clear = document.getElementById('acClear');
      var msg = document.getElementById('acMsg');
      var submit = document.getElementById('acSubmit');
      var drawing = false;
      var ink = false;
      function fit() {
        var rect = canvas.getBoundingClientRect();
        var ratio = Math.min(2, window.devicePixelRatio || 1);
        canvas.width = Math.max(300, Math.round(rect.width * ratio));
        canvas.height = Math.round(220 * ratio);
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.lineWidth = 2.2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#111827';
      }
      function point(event) {
        var rect = canvas.getBoundingClientRect();
        return { x: event.clientX - rect.left, y: event.clientY - rect.top };
      }
      function start(event) {
        event.preventDefault();
        drawing = true;
        ink = true;
        var p = point(event);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
        if (canvas.setPointerCapture) try { canvas.setPointerCapture(event.pointerId); } catch (e) {}
      }
      function move(event) {
        if (!drawing) return;
        event.preventDefault();
        var p = point(event);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
      }
      function end(event) {
        if (event) event.preventDefault();
        drawing = false;
      }
      fit();
      window.addEventListener('resize', function () { if (!ink) fit(); });
      canvas.addEventListener('pointerdown', start);
      canvas.addEventListener('pointermove', move);
      canvas.addEventListener('pointerup', end);
      canvas.addEventListener('pointercancel', end);
      canvas.addEventListener('pointerleave', end);
      if (clear) clear.addEventListener('click', function () {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ink = false;
        if (msg) msg.textContent = '';
      });
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        var name = (document.getElementById('acName') || {}).value || '';
        var documentNumber = (document.getElementById('acDocument') || {}).value || '';
        var accepted = !!(document.getElementById('acAccept') || {}).checked;
        if (!name.trim() || !documentNumber.trim()) { if (msg) { msg.style.color = '#b42318'; msg.textContent = 'Completa tu nombre y documento.'; } return; }
        if (!ink) { if (msg) { msg.style.color = '#b42318'; msg.textContent = 'Firma dentro del recuadro antes de continuar.'; } return; }
        if (!accepted) { if (msg) { msg.style.color = '#b42318'; msg.textContent = 'Debes aceptar el contrato para firmar.'; } return; }
        submit.disabled = true;
        submit.textContent = 'Registrando firma...';
        if (msg) { msg.style.color = '#51607a'; msg.textContent = 'Guardando firma electrónica.'; }
        fetch('/api/v1/public/affiliates/contract/sign', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ name: name.trim(), document: documentNumber.trim(), accept: true, signature: canvas.toDataURL('image/png') })
        }).then(function (response) { return response.json(); }).then(function (result) {
          submit.disabled = false;
          submit.textContent = 'Firmar contrato';
          if (result && result.ok) {
            if (msg) { msg.style.color = '#047857'; msg.textContent = 'Contrato firmado. Actualizando estado...'; }
            setTimeout(function () { location.reload(); }, 700);
          } else if (msg) { msg.style.color = '#b42318'; msg.textContent = (result && result.message) || 'No se pudo firmar el contrato.'; }
        }).catch(function () {
          submit.disabled = false;
          submit.textContent = 'Firmar contrato';
          if (msg) { msg.style.color = '#b42318'; msg.textContent = 'Error de conexión. Intenta de nuevo.'; }
        });
      });
    })();
  </script>
</section>
