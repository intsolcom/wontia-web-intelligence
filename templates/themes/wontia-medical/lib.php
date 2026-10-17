<?php
use App\Services\AnalyticsService;
use App\Services\CookieConsentService;
use App\Services\SeoService;

require_once ROOT_DIR . '/templates/themes/_shared/brand.php';

if (!function_exists('medical_tc')) {
    function medical_tc(string $key, $default = null)
    {
        $v = wwi_tc('wontia-medical', $key, $default);
        return ($v === '' || $v === null) ? $default : $v;
    }
}

if (!function_exists('medical_api_base')) {
    function medical_api_base(): string
    {
        $url = (string) medical_tc('medical_api_url', getenv('MEDICAL_API_URL') ?: '');
        $url = rtrim(trim($url), '/');
        return $url;
    }
}

if (!function_exists('medical_api_key')) {
    function medical_api_key(): string
    {
        $k = getenv('MEDICAL_API_KEY');
        return is_string($k) ? trim($k) : '';
    }
}

if (!function_exists('medical_api_get')) {
    function medical_api_get(string $path): ?array
    {
        $base = medical_api_base();
        if ($base === '' || !function_exists('curl_init')) return null;
        $url = $base . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        $headers = ['Accept: application/json'];
        if (medical_api_key() !== '') $headers[] = 'X-Api-Key: ' . medical_api_key();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($body) || $code < 200 || $code >= 300) return null;
        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }
}

if (!function_exists('medical_module_enabled')) {
    function medical_module_enabled(string $slug): bool
    {
        $v = medical_tc('module_' . $slug, true);
        return !($v === false || $v === '0' || $v === 'false' || $v === 0);
    }
}

if (!function_exists('medical_module_map')) {
    function medical_module_map(): array
    {
        return [
            'tia' => ['title' => 'TIA', 'desc' => 'Bienestar, objetivos y orientacion general. TIA no diagnostica ni receta.'],
            'agenda' => ['title' => 'Agenda', 'desc' => 'Especialidades, profesionales y reserva de citas.'],
            'teleconsulta' => ['title' => 'Teleconsulta', 'desc' => 'Sala virtual con consentimiento informado.'],
            'expediente' => ['title' => 'Expediente', 'desc' => 'Datos declarados, documentos y registro de accesos.'],
            'tienda' => ['title' => 'Tienda', 'desc' => 'Productos de bienestar y pedidos.'],
            'suscripciones' => ['title' => 'Suscripciones', 'desc' => 'Planes, beneficios y limites de uso.'],
            'perfil' => ['title' => 'Perfil', 'desc' => 'Tu cuenta, preferencias y seguridad.'],
        ];
    }
}

if (!function_exists('medical_brick_file')) {
    function medical_brick_file(string $slug): string
    {
        $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower($slug));
        return __DIR__ . '/bricks/' . $slug . '.php';
    }
}

if (!function_exists('medical_brick_exists')) {
    function medical_brick_exists(string $slug): bool
    {
        return is_file(medical_brick_file($slug));
    }
}

if (!function_exists('medical_brick')) {
    function medical_brick(string $slug, array $config = []): string
    {
        $file = medical_brick_file($slug);
        if (!is_file($file)) return '';
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }
}

if (!function_exists('medical_nav_items')) {
    function medical_nav_items(): array
    {
        $items = [['label' => 'Inicio', 'url' => '/']];
        foreach (medical_module_map() as $slug => $meta) {
            if (!medical_module_enabled($slug)) continue;
            $items[] = ['label' => $meta['title'], 'url' => '/' . $slug];
        }
        return $items;
    }
}

if (!function_exists('medical_page_start')) {
    function medical_page_start(array $meta = [], string $active = ''): void
    {
        $title = (string) ($meta['title'] ?? 'Wontia Medical');
        $meta = array_merge([
            'title' => $title,
            'meta_title' => $meta['meta_title'] ?? ($title . ' — Wontia Medical'),
            'meta_description' => $meta['meta_description'] ?? 'Salud digital impulsada por TIA.',
            'slug' => $meta['slug'] ?? '',
        ], $meta);
        AnalyticsService::track($_SERVER['REQUEST_URI'] ?? '/', $_SERVER['HTTP_REFERER'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        $wwiSsoToken = '';
        try {
            if (class_exists(\App\Core\Session::class) && class_exists(\App\Middleware\AuthMiddleware::class)) {
                \App\Core\Session::start();
                if (\App\Core\Session::isLoggedIn()) {
                    $wwiUser = \App\Core\Session::user();
                    if (is_array($wwiUser) && !empty($wwiUser['email'])) {
                        $wwiSsoToken = \App\Middleware\AuthMiddleware::generateJwt($wwiUser);
                    }
                }
            }
        } catch (\Throwable $e) {
            $wwiSsoToken = '';
        }
        ?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <?= class_exists(SeoService::class) ? SeoService::metaTags($meta) : '' ?>
    <?= wwi_favicon_links() ?: '<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 32 32\'%3E%3Crect width=\'32\' height=\'32\' rx=\'10\' fill=\'%232F9E6F\'/%3E%3Ctext x=\'16\' y=\'23\' font-family=\'sans-serif\' font-size=\'20\' font-weight=\'800\' fill=\'white\' text-anchor=\'middle\'%3EW%3C/text%3E%3C/svg%3E" />' ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="/assets/themes/wontia-medical/medical.css?v=<?= rawurlencode(\App\Core\Config::get('APP_VERSION', '1')) ?>" />
    <script>window.WM_API_BASE = <?= json_encode(medical_api_base()) ?>;<?php if ($wwiSsoToken !== ''): ?>window.WWISSO_TOKEN = <?= json_encode($wwiSsoToken) ?>;<?php endif; ?></script>
    <script src="/assets/themes/wontia-medical/medical-app.js"></script>
</head>
<body>
<header class="wm-nav">
  <a class="wm-brand" href="/">
    <?php if (wwi_brand()['logo'] !== ''): ?>
      <?= wwi_logo_img(140) ?>
    <?php else: ?>
      <span class="wm-brand-mark">W</span>
    <?php endif; ?>
    <span class="wm-brand-text"><?= htmlspecialchars(wwi_brand()['text'] ?: 'Wontia Medical') ?></span>
  </a>
  <nav class="wm-menu">
    <?php foreach (medical_nav_items() as $item): ?>
      <a href="<?= htmlspecialchars($item['url']) ?>" class="<?= $active === trim($item['url'], '/') ? 'on' : '' ?>"><?= htmlspecialchars($item['label']) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="wm-nav-actions">
    <a class="wm-btn wm-btn-ghost" href="/perfil">Mi cuenta</a>
    <button type="button" class="wm-hamburger" id="wm-hamburger" aria-label="Menu" aria-expanded="false" aria-controls="wm-mobile-nav">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>
<nav id="wm-mobile-nav" class="wm-mobile-nav" aria-label="Navegacion movil">
  <?php foreach (medical_nav_items() as $item): ?>
    <a href="<?= htmlspecialchars($item['url']) ?>"><?= htmlspecialchars($item['label']) ?></a>
  <?php endforeach; ?>
  <a href="/perfil">Mi cuenta</a>
</nav>
<script>
(function () {
  var btn = document.getElementById('wm-hamburger');
  var nav = document.getElementById('wm-mobile-nav');
  if (!btn || !nav) return;
  btn.addEventListener('click', function () {
    var open = nav.classList.toggle('open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
})();
</script>
<main class="wm-main">
<?php
    }
}

if (!function_exists('medical_page_end')) {
    function medical_page_end(): void
    {
        $about = (string) medical_tc('footer_about', '');
        $phone = (string) medical_tc('contact_phone', '');
        $email = (string) medical_tc('contact_email', '');
        ?>
</main>
<footer class="wm-footer">
  <div class="wm-footer-inner">
    <div>
      <strong><?= htmlspecialchars(wwi_site_name('Wontia Medical')) ?></strong>
      <?php if ($about !== ''): ?><p class="wm-muted"><?= htmlspecialchars($about) ?></p><?php endif; ?>
    </div>
    <div class="wm-footer-contact">
      <?php if ($phone !== ''): ?><span><?= htmlspecialchars($phone) ?></span><?php endif; ?>
      <?php if ($email !== ''): ?><a href="mailto:<?= htmlspecialchars($email) ?>"><?= htmlspecialchars($email) ?></a><?php endif; ?>
    </div>
  </div>
</footer>
<?= class_exists(CookieConsentService::class) ? CookieConsentService::render() : '' ?>
</body>
</html>
<?php
    }
}
