<?php
/**
 * Wontia Medical â€” Hero (segÃºn imagen de referencia aprobada).
 * 2 columnas: copy + beneficios (izq.) | foto + tarjetas flotantes (der.).
 * ImÃ¡genes configurables; si no existen, NO se inventan (se solicitan).
 */
if (!function_exists('wm_ic')) {
    function wm_ic(string $name, int $size = 20, string $cls = ''): string
    {
        $p = [
            'user' => '<circle cx="12" cy="8" r="3.6"/><path d="M4.5 20c0-3.6 3.4-6 7.5-6s7.5 2.4 7.5 6"/>',
            'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.2 2.7-5.4 6-5.4s6 2.2 6 5.4"/><path d="M16 5.5a3 3 0 0 1 0 5.4"/><path d="M17 14.8c2.4.5 4 2.3 4 4.7"/>',
            'steth' => '<path d="M6 3v6a5 5 0 0 0 10 0V3"/><path d="M11 14v2.5a4.5 4.5 0 0 0 9 0V15"/><circle cx="20.5" cy="13" r="1.6"/>',
            'arrow' => '<path d="M5 12h13"/><path d="M13 6l6 6-6 6"/>',
            'chev' => '<path d="M9 5l7 7-7 7"/>',
            'spark' => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/>',
            'mic' => '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M6 11a6 6 0 0 0 12 0"/><path d="M12 17v3"/>',
            'cam' => '<rect x="3" y="6" width="12" height="12" rx="2"/><path d="M15 10l6-3v10l-6-3z"/>',
            'phone' => '<path d="M6.5 4h3l1.5 4-2 1.5a11 11 0 0 0 5.5 5.5L16 13l4 1.5v3A2.5 2.5 0 0 1 17.5 20C10.6 19.5 4.5 13.4 4 6.5A2.5 2.5 0 0 1 6.5 4z"/>',
            'cal' => '<rect x="3.5" y="5" width="17" height="15" rx="2.5"/><path d="M3.5 9.5h17"/><path d="M8 3v4M16 3v4"/>',
            'brain' => '<path d="M9 4a3 3 0 0 0-3 3 3 3 0 0 0-1 5.8A3 3 0 0 0 7 18a3 3 0 0 0 5 1V5.5A2 2 0 0 0 9 4z"/><path d="M15 4a3 3 0 0 1 3 3 3 3 0 0 1 1 5.8A3 3 0 0 1 17 18a3 3 0 0 1-5 1"/>',
            'apple' => '<path d="M12 7c0-2 1.5-4 4-4 0 2-1.5 4-4 4z"/><path d="M12 7c-3-1.5-7 .3-7 5 0 4.5 3 9 5.5 9 1 0 1-.6 1.5-.6s.5.6 1.5.6c2.5 0 5.5-4.5 5.5-9 0-4.7-4-6.5-7-5z"/>',
            'flask' => '<path d="M9 3h6"/><path d="M10 3v6l-6 10.5A1.6 1.6 0 0 0 5.4 22h13.2a1.6 1.6 0 0 0 1.4-2.5L14 9V3"/>',
            'pill' => '<rect x="3" y="9" width="18" height="7" rx="3.5" transform="rotate(-35 12 12)"/><path d="M9 15L15 9"/>',
            'shield' => '<path d="M12 3l7 3v5c0 5-3.2 8.4-7 10-3.8-1.6-7-5-7-10V6z"/><path d="M9 12l2 2 4-4"/>',
            'play' => '<circle cx="12" cy="12" r="9"/><path d="M10 8.5v7l6-3.5z"/>',
            'star' => '<path d="M12 3l2.6 5.6 6 .7-4.5 4 1.3 5.9L12 16.9 6.6 19.2 7.9 13.3 3.4 9.3l6-.7z" fill="currentColor" stroke="none"/>',
            'dot' => '<circle cx="12" cy="12" r="4" fill="currentColor" stroke="none"/>',
            'pin' => '<path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/>',
            'search' => '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/>',
        ];
        $d = $p[$name] ?? $p['arrow'];
        return '<svg class="wm-ic ' . $cls . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
    }
}

$badge    = trim((string) medical_tc('hero_badge', 'Salud digital Â· Impulsada por TIA'));
$title    = (string) medical_tc('hero_title', 'Tu salud, conectada de una forma mÃ¡s inteligente.');
$subtitle = trim((string) medical_tc('hero_subtitle', 'Encuentra profesionales, agenda tu consulta, accede a servicios de bienestar y recibe el apoyo de TIA, todo en un solo lugar.'));
$cta1     = (string) medical_tc('hero_cta_label', 'Comenzar ahora');
$cta1Url  = (string) medical_tc('hero_cta_url', '/perfil');
$cta2     = (string) medical_tc('hero_cta2_label', 'Conoce cÃ³mo funciona');
$cta2Url  = (string) medical_tc('hero_cta2_url', '/#como-funciona');
$bg       = trim((string) medical_tc('hero_bg', ''));
$photo    = trim((string) medical_tc('hero_image', '/assets/themes/wontia-medical/hero-paciente.jpg'));
$photoAlt = (string) medical_tc('hero_image_alt', 'Paciente usando la app de Wontia Medical');
$tiaMsg   = (string) medical_tc('hero_tia_msg', 'Hola, soy TIA ðŸ‘‹ Â¿CÃ³mo puedo ayudarte hoy?');
$proName  = (string) medical_tc('hero_pro_name', 'Dra. MarÃ­a GonzÃ¡lez');
$proSpec  = (string) medical_tc('hero_pro_specialty', 'Medicina General');
$proRate  = (string) medical_tc('hero_pro_rating', '4.9');
$proRev   = (string) medical_tc('hero_pro_reviews', '124 reseÃ±as');
$proAvail = (string) medical_tc('hero_pro_available', 'Disponible hoy');
$proImg   = trim((string) medical_tc('hero_pro_image', '/assets/themes/wontia-medical/hero-profesional.jpg'));
$teleImg  = trim((string) medical_tc('hero_tele_image', '/assets/themes/wontia-medical/hero-teleconsulta.jpg'));
$note     = (string) medical_tc('hero_preview_note', 'Vista previa Â· datos de ejemplo');
$locLabel = (string) medical_tc('hero_loc_label', 'AtenciÃ³n presencial');
$locTime  = (string) medical_tc('hero_loc_time', 'A menos de 30 minutos');

$tiaOptions = [];
foreach (['Buscar un profesional', 'Agendar una cita', 'InformaciÃ³n de servicios', 'Consejos de bienestar'] as $i => $def) {
    $tiaOptions[] = (string) medical_tc('hero_tia_opt_' . ($i + 1), $def);
}
$services = [];
foreach (['Consulta mÃ©dica', 'PsicologÃ­a', 'NutriciÃ³n', 'Laboratorios', 'Farmacias'] as $i => $def) {
    $services[] = (string) medical_tc('hero_service_' . ($i + 1), $def);
}
$svcIcons = ['steth', 'brain', 'apple', 'flask', 'pill'];

$benefits = [
    ['brain', (string) medical_tc('benefit_1', 'TIA'), (string) medical_tc('benefit_1_desc', 'Asistente de salud siempre disponible')],
    ['users', (string) medical_tc('benefit_2', 'Profesionales verificados'), (string) medical_tc('benefit_2_desc', 'MÃ©dicos y especialistas en todo el paÃ­s')],
    ['cal', (string) medical_tc('benefit_3', 'Agenda sencilla'), (string) medical_tc('benefit_3_desc', 'Consulta en lÃ­nea o presencial')],
    ['shield', (string) medical_tc('benefit_4', 'Privacidad y seguridad'), (string) medical_tc('benefit_4_desc', 'Tus datos siempre protegidos')],
];

$titleHtml = htmlspecialchars($title);
$titleHtml = preg_replace('/\b(mÃ¡s|mas)\b/iu', '<span class="wm-c-magenta">$1</span>', $titleHtml) ?: $titleHtml;
$titleHtml = preg_replace('/\b(inteligente)(\.?)/iu', '<span class="wm-c-indigo">$1</span>$2', $titleHtml) ?: $titleHtml;
?>
<section class="wm-hero3" aria-label="Portada Wontia Medical">
  <div class="wm-hero3-bg<?= $bg !== '' ? ' has-img' : '' ?>"<?= $bg !== '' ? ' style="background-image:url(' . htmlspecialchars($bg, ENT_QUOTES) . ')"' : '' ?> aria-hidden="true"></div>
  <div class="wm-hero3-veil" aria-hidden="true"></div>
  <div class="wm-hero3-inner">
    <div class="wm-hero3-copy">
      <?php if ($badge !== ''): ?><span class="wm-hero3-badge"><?= wm_ic('spark', 15) ?><?= htmlspecialchars($badge) ?></span><?php endif; ?>
      <h1 class="wm-hero3-title"><?= $titleHtml ?></h1>
      <?php if ($subtitle !== ''): ?><p class="wm-hero3-lead"><?= htmlspecialchars($subtitle) ?></p><?php endif; ?>
      <div class="wm-hero3-ctas">
        <a class="wm-cta wm-cta--primary" href="<?= htmlspecialchars($cta1Url) ?>"><span class="wm-cta-label"><?= htmlspecialchars($cta1) ?></span><span class="wm-cta-ar"><?= wm_ic('arrow', 18) ?></span></a>
        <a class="wm-cta wm-cta--ghost" href="<?= htmlspecialchars($cta2Url) ?>"><span class="wm-cta-ic"><?= wm_ic('play', 20) ?></span><span class="wm-cta-label"><?= htmlspecialchars($cta2) ?></span></a>
      </div>
      <ul class="wm-hero3-benefits">
        <?php foreach ($benefits as $b): ?>
        <li>
          <span class="wm-ben-ic"><?= wm_ic($b[0], 20) ?></span>
          <span class="wm-ben-tx"><strong><?= htmlspecialchars($b[1]) ?></strong><small><?= htmlspecialchars($b[2]) ?></small></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="wm-hero3-stage">
      <div class="wm-hero3-photo">
        <?php if ($photo !== ''): ?><img src="<?= htmlspecialchars($photo) ?>" alt="<?= htmlspecialchars($photoAlt) ?>" loading="eager" decoding="async" /><?php endif; ?>
      </div>

      <div class="wm-c3 wm-c3--tia">
        <span class="wm-c3-tia-ic"><?= wm_ic('spark', 18) ?></span>
        <span class="wm-c3-body"><strong>TIA</strong><small>Asistente de salud inteligente</small></span>
        <span class="wm-c3-msg"><?= htmlspecialchars($tiaMsg) ?></span>
        <ul class="wm-tia-opts">
          <?php foreach ($tiaOptions as $opt): ?><li><a href="/tia"><?= wm_ic('spark', 14) ?><span><?= htmlspecialchars($opt) ?></span><?= wm_ic('chev', 13) ?></a></li><?php endforeach; ?>
        </ul>
      </div>

      <a class="wm-c3 wm-c3--pro" href="/agenda">
        <span class="wm-c3-pro-img"><?php if ($proImg !== ''): ?><img src="<?= htmlspecialchars($proImg) ?>" alt="" loading="lazy" /><?php else: ?><?= wm_ic('user', 22) ?><?php endif; ?></span>
        <span class="wm-c3-body"><strong><?= htmlspecialchars($proName) ?></strong><small><?= htmlspecialchars($proSpec) ?></small>
          <?php if ($proRate !== ''): ?><span class="wm-pro-rate"><?= wm_ic('star', 13) ?> <?= htmlspecialchars($proRate) ?> <em>(<?= htmlspecialchars($proRev) ?>)</em></span><?php endif; ?>
          <?php if ($proAvail !== ''): ?><span class="wm-pro-avail"><?= wm_ic('dot', 10) ?> <?= htmlspecialchars($proAvail) ?></span><?php endif; ?>
        </span>
        <span class="wm-pro-cta"><?= wm_ic('cal', 14) ?> Agendar cita</span>
      </a>

      <a class="wm-c3 wm-c3--tele" href="/teleconsulta">
        <span class="wm-c3-video"><?php if ($teleImg !== ''): ?><img src="<?= htmlspecialchars($teleImg) ?>" alt="" loading="lazy" /><?php else: ?><?= wm_ic('cam', 26) ?><?php endif; ?>
          <span class="wm-c3-ctrl"><span><?= wm_ic('mic', 14) ?></span><span><?= wm_ic('cam', 14) ?></span><span class="is-end"><?= wm_ic('phone', 14) ?></span></span>
        </span>
      </a>

      <a class="wm-c3 wm-c3--svc" href="/agenda">
        <strong class="wm-c3-svc-title">Nuestros servicios</strong>
        <ul>
          <?php foreach ($services as $i => $label): ?><li><a href="/agenda"><?= wm_ic($svcIcons[$i] ?? 'steth', 16) ?><span><?= htmlspecialchars($label) ?></span><?= wm_ic('chev', 13) ?></a></li><?php endforeach; ?>
        </ul>
      </a>

      <a class="wm-c3 wm-c3--loc" href="/agenda">
        <span class="wm-loc-map" aria-hidden="true"><?= wm_ic('pin', 22) ?></span>
        <span class="wm-c3-body"><strong><?= htmlspecialchars($locLabel) ?></strong><small><?= htmlspecialchars($locTime) ?></small></span>
      </a>
    </div>
  </div>
  <?php if ($note !== ''): ?><p class="wm-hero3-note"><?= htmlspecialchars($note) ?></p><?php endif; ?>
</section>
