<?php
$contract = (new \App\Services\ArtistContractService())->current();
?>
<section class="pc">
  <style>
    .pc{background:#f7f5f1;color:#161616;padding:46px 0 70px}.pc__wrap{max-width:900px;margin:0 auto;padding:0 22px}
    .pc__head{background:#111;color:#fff;border-radius:20px;padding:clamp(22px,4vw,34px);margin-bottom:18px}
    .pc__eyebrow{font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:#ff8a8d;font-weight:850}
    .pc h1{font:800 clamp(1.7rem,4vw,2.6rem)/1.08 Sora,Inter,sans-serif;letter-spacing:-.04em;margin:8px 0 8px}
    .pc__meta{font-size:12px;color:#bbb;line-height:1.6}
    .pc__body{background:#fff;border:1px solid #e5e0d8;border-radius:18px;padding:clamp(18px,3vw,30px);white-space:pre-wrap;font-size:13px;line-height:1.75;color:#333}
    .pc__actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}
    .pc__btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;border-radius:11px;padding:11px 16px;font-size:12.5px;font-weight:850;text-decoration:none}
    .pc__btn--primary{background:#F20D16;color:#fff}.pc__btn--secondary{background:#fff;border:1px solid #ddd8d0;color:#161616}
  </style>
  <div class="pc__wrap">
    <div class="pc__head">
      <div class="pc__eyebrow">SONIKA Partners · Comparte, vende y gana</div>
      <h1>Contrato de participación</h1>
      <div class="pc__meta">Versión <?= htmlspecialchars((string)($contract['version'] ?? '—'), ENT_QUOTES, 'UTF-8') ?> · Vigente desde <?= htmlspecialchars((string)($contract['effective_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?><br>Intsolcom, LLC · Intsolcom S.A.S. · NIT 901749025-8</div>
    </div>
    <div class="pc__body"><?= htmlspecialchars((string)($contract['body'] ?? 'Contrato no disponible.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    <div class="pc__actions"><a class="pc__btn pc__btn--primary" href="/sonika-partners#registro">Quiero ser Partner SONIKA</a><a class="pc__btn pc__btn--secondary" href="/mi-antro?next=contrato">Ya tengo cuenta: firmar contrato</a><a class="pc__btn pc__btn--secondary" href="/sonika-partners">Volver al programa</a></div>
  </div>
</section>
