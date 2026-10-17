<?php
$badge = trim((string) medical_tc('hero_badge', 'Salud digital · impulsada por TIA'));
$title = (string) medical_tc('hero_title', 'Tu salud, conectada de una forma mas inteligente.');
$titleWords = preg_split('/\s+/', trim($title)) ?: [];
$titleHtml = htmlspecialchars($title);
if (count($titleWords) > 1) {
    $lastWord = array_pop($titleWords);
    $titleHtml = htmlspecialchars(implode(' ', $titleWords)) . ' <span class="wm-hero2-accent">' . htmlspecialchars($lastWord) . '</span>';
}
$subtitle = trim((string) medical_tc('hero_subtitle', 'Encuentra profesionales, agenda tu consulta, accede a servicios de bienestar y recibe el apoyo de TIA, todo en un solo lugar.'));
$cta1 = (string) medical_tc('hero_cta_label', 'Comenzar ahora');
$cta1Url = (string) medical_tc('hero_cta_url', '/perfil');
$cta2 = (string) medical_tc('hero_cta2_label', 'Conoce como funciona');
$cta2Url = (string) medical_tc('hero_cta2_url', '/tia');
$image = trim((string) medical_tc('hero_image', ''));
$imageAlt = (string) medical_tc('hero_image_alt', 'Acompanamiento de salud digital con TIA');

$benefits = [];
foreach (['benefit_1', 'benefit_2', 'benefit_3', 'benefit_4'] as $key) {
    $value = trim((string) medical_tc($key, ''));
    if ($value !== '') $benefits[] = $value;
}

$tiaTitle = (string) medical_tc('card_tia_title', 'TIA te acompana');
$tiaUrl = (string) medical_tc('card_tia_url', '/tia');
$proTitle = (string) medical_tc('card_pro_title', 'Profesionales verificados');
$proUrl = (string) medical_tc('card_pro_url', '/agenda');
$dirTitle = (string) medical_tc('card_dir_title', 'Especialidades');
$dirUrl = (string) medical_tc('card_dir_url', '/agenda');
$apptTitle = (string) medical_tc('card_appt_title', 'Agenda tu consulta');
$apptUrl = (string) medical_tc('card_appt_url', '/agenda');
$apptNote = (string) medical_tc('card_appt_note', 'Vista previa · sin datos reales');
?>
<section class="wm-hero2">
  <div class="wm-hero2-grid">
    <div class="wm-hero2-copy">
      <?php if ($badge !== ''): ?><span class="wm-hero2-badge"><?= htmlspecialchars($badge) ?></span><?php endif; ?>
      <h1 class="wm-hero2-title"><?= $titleHtml ?></h1>
      <?php if ($subtitle !== ''): ?><p class="wm-hero2-lead"><?= htmlspecialchars($subtitle) ?></p><?php endif; ?>
      <div class="wm-hero2-cta">
        <a class="wm-btn" href="<?= htmlspecialchars($cta1Url) ?>"><?= htmlspecialchars($cta1) ?></a>
        <a class="wm-btn wm-btn-ghost" href="<?= htmlspecialchars($cta2Url) ?>"><?= htmlspecialchars($cta2) ?></a>
      </div>
      <?php if ($benefits): ?>
      <ul class="wm-hero2-benefits">
        <?php foreach ($benefits as $benefit): ?>
          <li><span class="wm-hero2-dot" aria-hidden="true"></span><?= htmlspecialchars($benefit) ?></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
    <div class="wm-hero2-stage">
      <?php if ($image !== ''): ?>
        <img class="wm-hero2-photo" src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($imageAlt) ?>" loading="lazy" decoding="async" />
      <?php else: ?>
        <div class="wm-hero2-photo wm-hero2-photo-empty" role="img" aria-label="<?= htmlspecialchars($imageAlt) ?>">
          <span>Imagen del hero configurable</span>
        </div>
      <?php endif; ?>
      <a class="wm-float wm-float-tia" href="<?= htmlspecialchars($tiaUrl) ?>">
        <span class="wm-float-badge">TIA</span>
        <strong><?= htmlspecialchars($tiaTitle) ?></strong>
        <small>Asistencia digital</small>
      </a>
      <a class="wm-float wm-float-pro" href="<?= htmlspecialchars($proUrl) ?>">
        <strong><?= htmlspecialchars($proTitle) ?></strong>
        <small>Agenda y perfiles</small>
      </a>
      <a class="wm-float wm-float-dir" href="<?= htmlspecialchars($dirUrl) ?>">
        <strong><?= htmlspecialchars($dirTitle) ?></strong>
        <small>Directorio</small>
      </a>
      <a class="wm-float wm-float-appt" href="<?= htmlspecialchars($apptUrl) ?>">
        <strong><?= htmlspecialchars($apptTitle) ?></strong>
        <small><?= htmlspecialchars($apptNote) ?></small>
      </a>
    </div>
  </div>
</section>
