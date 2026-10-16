<?php
$eyebrow = (string) medical_tc('hero_eyebrow', 'Salud digital');
$title = (string) medical_tc('hero_title', 'Tu salud, acompanada por TIA.');
$subtitle = (string) medical_tc('hero_subtitle', '');
$ctaLabel = (string) medical_tc('hero_cta_label', 'Hablar con TIA');
$ctaUrl = (string) medical_tc('hero_cta_url', '/tia');
?>
<section class="wm-hero">
  <div class="wm-hero-inner">
    <?php if ($eyebrow !== ''): ?><p class="wm-eyebrow"><?= htmlspecialchars($eyebrow) ?></p><?php endif; ?>
    <h1 class="wm-title"><?= htmlspecialchars($title) ?></h1>
    <?php if ($subtitle !== ''): ?><p class="wm-lead"><?= htmlspecialchars($subtitle) ?></p><?php endif; ?>
    <div class="wm-hero-cta">
      <a class="wm-btn" href="<?= htmlspecialchars($ctaUrl) ?>"><?= htmlspecialchars($ctaLabel) ?></a>
      <a class="wm-btn wm-btn-ghost" href="/agenda">Ver agenda</a>
    </div>
    <p class="wm-hint">TIA acompanha e informa; no diagnostica, no prescribe ni modifica tratamientos.</p>
  </div>
</section>
