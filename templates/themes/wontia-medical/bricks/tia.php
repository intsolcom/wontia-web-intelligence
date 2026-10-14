<?php
$apiBase = medical_api_base();
$connected = null;
if ($apiBase !== '') {
    $meta = medical_api_get('/api/v1/meta');
    $connected = is_array($meta);
}
?>
<section class="wm-section wm-tia">
  <div class="wm-card wm-card-tia">
    <span class="wm-chip wm-chip-tia">TIA</span>
    <h2>Tecnologia de Inteligencia Aplicada</h2>
    <p class="wm-muted">
      TIA te acompana con objetivos de bienestar, habitos y orientacion general.
      No emite diagnosticos, no prescribe ni modifica tratamientos: esas decisiones
      corresponden a profesionales habilitados.
    </p>
    <?php if ($connected): ?>
      <p class="wm-status ok">Conexion con la app Wontia Medical verificada.</p>
      <a class="wm-btn wm-btn-tia" href="<?= htmlspecialchars($apiBase) ?>">Abrir TIA</a>
    <?php elseif ($apiBase === ''): ?>
      <p class="wm-note">Integracion pendiente: configura <code>medical_api_url</code> en el tema.</p>
    <?php else: ?>
      <p class="wm-status warn">La app Wontia Medical no respondio. Verifica la conexion.</p>
      <a class="wm-btn wm-btn-tia" href="<?= htmlspecialchars($apiBase) ?>">Abrir app</a>
    <?php endif; ?>
    <p class="wm-hint">Ante sintomas de alarma, busca atencion urgente.</p>
  </div>
</section>
