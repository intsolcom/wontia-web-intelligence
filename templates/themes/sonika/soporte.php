<?php
use App\Services\CustomerService;
use App\Services\PqrfService;
use App\Widgets\WidgetRegistry;

$__supSvc = new PqrfService();
$__supChannels = $__supSvc->channels();
$__supCustomer = (new CustomerService())->current();
$__supTia = (string)wwi_setting('tia_embed_enabled', '1') !== '0';
$__supEmail = trim((string)($__supChannels['contact_email'] ?? 'clientes@sonika.com.co'));
$__supWa = trim((string)($__supChannels['whatsapp_url'] ?? ''));
if ($__supWa !== '') {
    $__supWa .= (str_contains($__supWa, '?') ? '&' : '?') . 'text=' . rawurlencode('Hola, necesito ayuda con un producto o pedido de SONIKA.');
}
$__supProducts = [];
foreach (sonika_products() as $__p) {
    $__img = sonika_img($__p, 1200);
    if ($__img === '') continue;
    $__supProducts[] = $__p;
    if (count($__supProducts) >= 8) break;
}
$__supFallback = '/assets/uploads/wontia_5ddad38e1699f7db.webp';
$__supImg = static function (int $i) use ($__supProducts, $__supFallback): string {
    if (!$__supProducts) return $__supFallback;
    $p = $__supProducts[$i % count($__supProducts)];
    return sonika_img($p, 1200) ?: $__supFallback;
};
$__supSupport = [
    ['url' => $__supImg(0), 'title' => 'Asesoría para elegir', 'text' => 'Cuéntanos qué instrumento buscas, tu nivel y tu presupuesto; te orientamos con productos reales.', 'cta_label' => $__supTia ? 'Hablar con TIA' : 'Ver canales', 'cta_url' => $__supTia ? '#tia-chat' : '#contacto', 'alt' => 'Asesoría SONIKA'],
    ['url' => $__supImg(1), 'title' => 'Acompañamiento en tu compra', 'text' => 'Resolvemos dudas de envíos, pagos, disponibilidad y garantías por canales oficiales.', 'cta_label' => 'Ver canales', 'cta_url' => '#contacto', 'alt' => 'Acompañamiento SONIKA'],
    ['url' => $__supImg(2), 'title' => 'Después de tu compra', 'text' => 'Si necesitas garantía, devolución o soporte técnico, tienes canales y PQRF con seguimiento.', 'cta_label' => 'Ver políticas', 'cta_url' => '/politica-de-devoluciones', 'alt' => 'Soporte postventa SONIKA'],
    ['url' => $__supImg(3), 'title' => 'Atención formal', 'text' => 'Radica peticiones, quejas, reclamos o sugerencias y sigue el estado de tu caso.', 'cta_label' => 'Ir a PQRF', 'cta_url' => '/pqrf', 'alt' => 'PQRF SONIKA'],
];
$__supProductSlides = [];
foreach (array_slice($__supProducts, 0, 2) as $__p) {
    $__supProductSlides[] = [
        'url' => sonika_img($__p, 1200),
        'title' => (string)($__p['title'] ?? 'Producto SONIKA'),
        'text' => (string)($__p['vendor'] ?? ''),
        'cta_label' => 'Conoce nuestro catálogo',
        'cta_url' => '/producto/' . rawurlencode((string)($__p['handle'] ?? '')),
        'alt' => (string)($__p['title'] ?? 'Producto SONIKA'),
    ];
}
$__supSlides = [];
$__supPi = 0;
for ($__i = 0; $__i < count($__supSupport); $__i += 2) {
    $__supSlides[] = $__supSupport[$__i];
    if (isset($__supSupport[$__i + 1])) $__supSlides[] = $__supSupport[$__i + 1];
    if (isset($__supProductSlides[$__supPi])) $__supSlides[] = $__supProductSlides[$__supPi++];
}
while (isset($__supProductSlides[$__supPi])) $__supSlides[] = $__supProductSlides[$__supPi++];
$__supMedia = [
    'width' => '100',
    'align' => 'center',
    'aspect' => '4:3',
    'max_height' => '560',
    'pad' => '0',
    'radius' => '18',
    'transition' => 'fade',
    'kenburns' => '0',
    'thumb_style' => 'dots',
    'thumb_count' => '8',
    'autoplay_ms' => '6500',
    'loop' => '1',
    'overlay' => '1',
    'caption_pos' => 'bottom',
    'lightbox' => '0',
    'fullscreen' => '0',
    'copy_link' => '0',
    'video_autoplay' => '0',
    'controls_autohide' => '0',
    'label' => 'Galería de apoyo SONIKA',
    'slides' => $__supSlides,
];

sonika_site_head('Centro de Soporte — SONIKA', 'Atención SONIKA antes, durante y después de tu compra: chat con TIA, formulario, WhatsApp, correo y PQRF.');
sonika_breadcrumb(['Soporte' => null]);
?>

<div class="sonika-support">
<style>
.sonika-support{--sup-brand:#F20D16;--sup-brand2:#C70A12;--sup-ink:#111;--sup-muted:#6B6B6B;--sup-line:#E6E6E6;--sup-soft:#F7F7F7;background:#F7F7F7;color:var(--sup-ink);font-family:Sora,system-ui,sans-serif}
.sonika-support *,.sonika-support *::before,.sonika-support *::after{box-sizing:border-box}
.sonika-support__hero{position:relative;overflow:hidden;background:radial-gradient(850px 380px at 82% -12%,rgba(242,13,22,.35),transparent 62%),radial-gradient(600px 300px at 8% 110%,rgba(242,13,22,.14),transparent 62%),#0b0b0b;color:#fff;padding:clamp(38px,6vw,68px) 0 clamp(34px,5vw,58px)}
.sonika-support__hero::after{content:"";position:absolute;inset:0;pointer-events:none;background-image:linear-gradient(rgba(255,255,255,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.035) 1px,transparent 1px);background-size:56px 56px;mask-image:linear-gradient(180deg,rgba(0,0,0,.6),transparent 80%)}
.sonika-support__hero .wrap{position:relative;z-index:1}
.sonika-support__eyebrow{display:flex;align-items:center;gap:10px;margin:0 0 13px;color:#FF6B71;font-size:11.5px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
.sonika-support__eyebrow::before{content:"";width:36px;height:2px;background:var(--sup-brand)}
.sonika-support h1{margin:0 0 10px;font-size:clamp(30px,5vw,50px);line-height:1.03;letter-spacing:-.045em;color:#fff;font-family:Sora,system-ui,sans-serif}
.sonika-support__lead{margin:0;max-width:66ch;color:#C9C9C9;font-size:clamp(14.5px,1.8vw,17px);line-height:1.65}
.sonika-support__chips{display:flex;flex-wrap:wrap;gap:8px;margin:20px 0 0}
.sonika-support__chip{display:inline-flex;align-items:center;gap:7px;min-height:34px;padding:6px 11px;border-radius:99px;border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.055);color:#E8E8E8;font-size:11.5px;font-weight:650}
.sonika-support__chip::before{content:"";width:6px;height:6px;border-radius:50%;background:var(--sup-brand)}
.sonika-support__actions{display:flex;flex-wrap:wrap;gap:9px;margin:23px 0 0}
.sonika-support__btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:46px;padding:12px 19px;border-radius:10px;border:1px solid transparent;background:var(--sup-brand);color:#fff;font:inherit;font-size:14px;font-weight:750;text-decoration:none;cursor:pointer;transition:filter .15s ease,transform .15s ease,border-color .15s ease,color .15s ease}
.sonika-support__btn:hover{filter:brightness(.94)}
.sonika-support__btn:active{transform:translateY(1px)}
.sonika-support__btn:focus-visible{outline:3px solid rgba(242,13,22,.42);outline-offset:3px}
.sonika-support__btn--ghost{background:transparent;border-color:rgba(255,255,255,.28);color:#fff}
.sonika-support__btn--ghost:hover{border-color:#fff;filter:none}
.sonika-support__body{padding:clamp(24px,4vw,48px) 0 clamp(42px,7vw,80px)}
.sonika-support__grid{display:grid;grid-template-columns:minmax(0,1.08fr) minmax(0,.92fr);gap:clamp(18px,2.8vw,36px);align-items:start}
.sonika-support__media{min-width:0}
.sonika-support__media .wwms{--accent2:var(--sup-brand);--border:#E6E6E6;--panel:#fff;--text:#fff;--muted:#fff;width:100%;padding:0!important}
.sonika-support__media .wwms-inner{max-width:none;padding:0}
.sonika-support__media .wwms-block{width:100%!important;max-width:100%}
.sonika-support__media .wwms-stage{border-radius:18px;border-color:#E6E6E6;box-shadow:0 16px 42px rgba(0,0,0,.12)}
.sonika-support__media .wwms-overlay .wwms-cta{background:var(--sup-brand);color:#fff;border-radius:9px;padding:10px 16px;font-weight:750;text-decoration:none}
.sonika-support__media .wwms-overlay .wwms-cta:hover{background:var(--sup-brand2);filter:none}
.sonika-support__media .wwms-overlay h3{color:#fff}
.sonika-support__media .wwms-overlay p{color:rgba(255,255,255,.86)}
.sonika-support__media .wwms-thumbs .t{background:#fff;color:#555;border-color:#E6E6E6}
.sonika-support__media .wwms-thumbs .t:hover{color:var(--sup-brand);border-color:var(--sup-brand)}
.sonika-support__media .wwms-thumbs .t.on{background:rgba(242,13,22,.12);border-color:var(--sup-brand);color:var(--sup-ink)}
.sonika-support__media .wwms-thumbs .t .prog{background:var(--sup-brand)}
.sonika-support__media .wwms-btn:focus-visible,.sonika-support__media .wwms-pause:focus-visible,.sonika-support__media .wwms-mini:focus-visible,.sonika-support__media .wwms-thumbs .t:focus-visible{outline-color:var(--sup-brand)}
.sonika-support__trust{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;margin:13px 0 0}
.sonika-support__trust-item{display:grid;gap:3px;padding:11px 12px;border:1px solid var(--sup-line);border-radius:11px;background:#fff}
.sonika-support__trust-item b{display:flex;align-items:center;gap:6px;font-size:11.5px;line-height:1.3;color:var(--sup-ink)}
.sonika-support__trust-item b::before{content:"";width:6px;height:6px;border-radius:50%;background:var(--sup-brand);flex:none}
.sonika-support__trust-item span{color:var(--sup-muted);font-size:10.5px;line-height:1.4}
.sonika-support__side{min-width:0}
.sonika-support__side-head{margin:0 0 13px}
.sonika-support__side-head p{margin:0 0 4px;color:var(--sup-brand);font-size:10.5px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
.sonika-support__side-head h2{margin:0;font-size:clamp(20px,2.2vw,27px);line-height:1.15;letter-spacing:-.03em;color:var(--sup-ink)}
.sup-acc{background:#fff;border:1px solid var(--sup-line);border-radius:14px;overflow:hidden;margin:0 0 9px;box-shadow:0 5px 16px rgba(0,0,0,.035);scroll-margin-top:96px}
.sup-acc summary{display:flex;align-items:center;gap:10px;min-height:64px;padding:12px 13px;cursor:pointer;list-style:none;outline:none}
.sup-acc summary::-webkit-details-marker{display:none}
.sup-acc summary::after{content:"+";flex:0 0 auto;width:25px;height:25px;border:1px solid var(--sup-line);border-radius:50%;display:grid;place-items:center;font-size:15px;font-weight:700;line-height:1;color:#777;transition:border-color .15s ease,color .15s ease,transform .15s ease}
.sup-acc[open] summary::after{content:"−";border-color:var(--sup-brand);color:var(--sup-brand)}
.sup-acc summary:focus-visible{outline:3px solid rgba(242,13,22,.26);outline-offset:-3px;border-radius:12px}
.sup-acc--primary{border-color:var(--sup-brand);box-shadow:0 10px 26px rgba(242,13,22,.08)}
.sup-acc--primary summary{background:linear-gradient(135deg,#FFF7F7,#fff)}
.sup-acc__ico{flex:0 0 auto;width:38px;height:38px;border-radius:10px;background:#111;color:#fff;display:grid;place-items:center;font-size:10px;font-weight:800;letter-spacing:.04em}
.sup-acc--primary .sup-acc__ico{background:var(--sup-brand)}
.sup-acc__txt{flex:1 1 auto;min-width:0}
.sup-acc__txt b{display:block;font-size:13.5px;line-height:1.3;font-weight:750;color:var(--sup-ink)}
.sup-acc__txt small{display:block;margin-top:2px;color:var(--sup-muted);font-size:11px;line-height:1.4}
.sup-acc__badge{flex:0 0 auto;border-radius:99px;padding:3px 8px;background:var(--sup-brand);color:#fff;font-size:9px;font-weight:800;letter-spacing:.05em;text-transform:uppercase}
.sup-acc__panel{padding:14px;border-top:1px solid var(--sup-line);background:#fff;color:#444;font-size:13px;line-height:1.6}
.sup-acc__panel p{margin:0 0 10px}
.sup-acc__panel p:last-child{margin-bottom:0}
.sup-acc__panel--tia{padding:0}
.sup-acc__panel--tia .sup-acc__note{padding:10px 13px 13px;margin:0;color:var(--sup-muted);font-size:11.5px;line-height:1.5;border-top:1px solid var(--sup-line)}
.sup-acc__note{color:var(--sup-muted);font-size:11.5px;line-height:1.5}
.sup-link{display:inline-flex;align-items:center;justify-content:center;gap:7px;width:100%;min-height:44px;padding:11px 15px;border:1px solid transparent;border-radius:9px;background:var(--sup-brand);color:#fff;font-size:13px;font-weight:750;text-decoration:none;text-align:center;transition:filter .15s ease,border-color .15s ease,color .15s ease}
.sup-link:hover{filter:brightness(.94)}
.sup-link:focus-visible{outline:3px solid rgba(242,13,22,.3);outline-offset:2px}
.sup-link--wa{background:#25D366}
.sup-link--wa:hover{filter:brightness(.95)}
.sup-link--mail{background:#fff;color:var(--sup-ink);border-color:var(--sup-line)}
.sup-link--mail:hover{border-color:var(--sup-brand);color:var(--sup-brand);filter:none}
.sonika-support__quick{display:flex;flex-wrap:wrap;gap:12px;margin:12px 0 0;font-size:12px}
.sonika-support__quick a{color:var(--sup-brand);font-weight:700;text-decoration:none}
.sonika-support__quick a:hover{text-decoration:underline}
.sonika-support__quick span{color:var(--sup-muted)}
@media(min-width:1025px){.sonika-support__media{position:sticky;top:96px}}
@media(max-width:1024px){.sonika-support__grid{grid-template-columns:1fr}.sonika-support__side{order:-1}.sonika-support__media{order:2;position:static}.sonika-support__trust{grid-template-columns:1fr 1fr 1fr}}
@media(max-width:640px){.sonika-support__hero{padding:30px 0 26px}.sonika-support__lead{font-size:14px}.sonika-support__actions{flex-direction:column;align-items:stretch}.sonika-support__btn{width:100%}.sonika-support__body{padding:18px 0 38px}.sonika-support__trust{grid-template-columns:1fr}.sonika-support__grid{gap:14px}.sup-acc summary{min-height:58px;padding:10px 11px}.sup-acc__ico{width:34px;height:34px;font-size:9px}.sup-acc__txt b{font-size:13px}.sup-acc__badge{display:none}.sonika-support__side-head{margin-bottom:10px}}
@media(prefers-reduced-motion:reduce){.sonika-support *{transition:none!important;animation:none!important;scroll-behavior:auto!important}}
</style>

<section class="sonika-support__hero">
  <div class="wrap">
    <p class="sonika-support__eyebrow">Soporte SONIKA</p>
    <h1>Estamos para ayudarte.</h1>
    <p class="sonika-support__lead">Antes, durante y después de tu compra. Elige el canal que prefieras: TIA, formulario, WhatsApp o PQRF.</p>
    <div class="sonika-support__chips">
      <span class="sonika-support__chip">Canales oficiales</span>
      <span class="sonika-support__chip">Asistente TIA</span>
      <span class="sonika-support__chip">PQRF con seguimiento</span>
    </div>
    <div class="sonika-support__actions">
      <?php if ($__supTia): ?>
      <a class="sonika-support__btn" href="#tia-chat">Hablar con TIA</a>
      <?php endif; ?>
      <a class="sonika-support__btn sonika-support__btn--ghost" href="#contacto">Formulario de contacto</a>
    </div>
  </div>
</section>

<section class="sonika-support__body">
  <div class="wrap">
    <div class="sonika-support__grid">
      <div class="sonika-support__media">
        <?= WidgetRegistry::render('media-slider', $__supMedia) ?>
        <div class="sonika-support__trust">
          <div class="sonika-support__trust-item"><b>Atención completa</b><span>Antes, durante y después de tu compra.</span></div>
          <div class="sonika-support__trust-item"><b>TIA + personas</b><span>Chat automatizado y canales con atención humana.</span></div>
          <div class="sonika-support__trust-item"><b>PQRF con radicado</b><span>Casos formales con seguimiento.</span></div>
        </div>
      </div>

      <aside class="sonika-support__side" aria-label="Canales de soporte">
        <div class="sonika-support__side-head"><p>Atención multicanal</p><h2>Elige cómo hablar con nosotros.</h2></div>

        <?php if ($__supTia): ?>
        <details class="sup-acc sup-acc--primary" id="tia-chat" open>
          <summary>
            <span class="sup-acc__ico">TIA</span>
            <span class="sup-acc__txt"><b>Chat con TIA IA</b><small>Asistente automático · respuestas por IA</small></span>
            <span class="sup-acc__badge">Principal</span>
          </summary>
          <div class="sup-acc__panel sup-acc__panel--tia">
            <div data-tia-support></div>
            <p class="sup-acc__note">TIA puede orientarte sobre productos, envíos, pagos y garantías. Para pedidos concretos o reclamaciones formales, usa Mi Cuenta, PQRF o WhatsApp.</p>
          </div>
        </details>
        <?php endif; ?>

        <details class="sup-acc" id="contacto"<?= $__supTia ? '' : ' open' ?>>
          <summary>
            <span class="sup-acc__ico">FORM</span>
            <span class="sup-acc__txt"><b>Formulario de contacto</b><small>Cuéntanos tu caso y te respondemos por el medio que elijas</small></span>
          </summary>
          <div class="sup-acc__panel">
            <?php require __DIR__ . '/soporte-form.php'; ?>
          </div>
        </details>

        <?php if ($__supWa !== ''): ?>
        <details class="sup-acc" id="whatsapp">
          <summary>
            <span class="sup-acc__ico">WA</span>
            <span class="sup-acc__txt"><b>WhatsApp</b><small>Atención por una persona</small></span>
          </summary>
          <div class="sup-acc__panel">
            <p>Escríbenos por WhatsApp para consultas de productos, pedidos, envíos o garantías. Este canal lo atiende una persona, no TIA.</p>
            <a class="sup-link sup-link--wa" href="<?= sonika_esc($__supWa) ?>" target="_blank" rel="noopener noreferrer">Escribir por WhatsApp</a>
            <p class="sup-acc__note" style="margin-top:8px">No compartas contraseñas ni datos de tarjetas por WhatsApp.</p>
          </div>
        </details>
        <?php endif; ?>

        <details class="sup-acc" id="pqrf">
          <summary>
            <span class="sup-acc__ico">PQR</span>
            <span class="sup-acc__txt"><b>PQRF · Radica tu solicitud</b><small>Peticiones, quejas, reclamos, sugerencias y felicitaciones</small></span>
          </summary>
          <div class="sup-acc__panel">
            <p>Para casos formales con radicado y seguimiento. Requiere iniciar sesión en tu cuenta de cliente.</p>
            <a class="sup-link" href="/pqrf">Ir a PQRF</a>
          </div>
        </details>

        <details class="sup-acc" id="email">
          <summary>
            <span class="sup-acc__ico">@</span>
            <span class="sup-acc__txt"><b>Correo</b><small><?= sonika_esc($__supEmail) ?></small></span>
          </summary>
          <div class="sup-acc__panel">
            <p>Escríbenos directamente al correo oficial de atención.</p>
            <a class="sup-link sup-link--mail" href="mailto:<?= sonika_esc($__supEmail) ?>"><?= sonika_esc($__supEmail) ?></a>
          </div>
        </details>

        <div class="sonika-support__quick"><a href="/cuenta?tab=orders">Mi cuenta · Pedidos</a><span>·</span><a href="/pqrf">PQRF</a><span>·</span><a href="/politicas">Políticas</a></div>
      </aside>
    </div>
  </div>
</section>

<script>
(function(){
  var accs=[].slice.call(document.querySelectorAll('.sonika-support .sup-acc'));
  if(!accs.length)return;
  function scrollBehavior(){return window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'}
  function openHash(){
    var h=(location.hash||'').replace('#','');
    if(!h)return;
    accs.forEach(function(d){
      if(d.id!==h)return;
      d.open=true;
      var s=d.querySelector('summary');
      if(s){try{s.focus({preventScroll:true})}catch(e){}}
      try{d.scrollIntoView({block:'start',behavior:scrollBehavior()})}catch(e){}
    });
  }
  accs.forEach(function(d){
    d.addEventListener('toggle',function(){
      if(!d.open)return;
      accs.forEach(function(o){if(o!==d)o.open=false;});
    });
  });
  window.addEventListener('hashchange',openHash);
  openHash();
})();
</script>
</div>

<?php sonika_site_footer(); ?>
