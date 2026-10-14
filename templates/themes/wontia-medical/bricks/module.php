<?php
$module = (string) ($config['module'] ?? '');
$map = medical_module_map();
$meta = $config['meta'] ?? ($map[$module] ?? ['title' => ucfirst($module), 'desc' => '']);
$title = (string) ($meta['title'] ?? ucfirst($module));
$desc = (string) ($meta['desc'] ?? '');
$apiBase = medical_api_base();

if ($module === 'tia' && medical_brick_exists('tia')) {
    echo medical_brick('tia', $config);
    return;
}
?>
<section class="wm-section">
  <div class="wm-card">
    <span class="wm-chip">Modulo</span>
    <h2><?= htmlspecialchars($title) ?></h2>
    <p class="wm-muted"><?= htmlspecialchars($desc) ?></p>
    <?php if ($apiBase !== ''): ?>
      <p class="wm-muted">Este modulo es servido por la app Wontia Medical.</p>
      <a class="wm-btn" href="<?= htmlspecialchars($apiBase) ?>">Abrir <?= htmlspecialchars($title) ?></a>
    <?php else: ?>
      <p class="wm-note">Integracion pendiente: configura <code>medical_api_url</code> en el tema.</p>
    <?php endif; ?>
  </div>
</section>
