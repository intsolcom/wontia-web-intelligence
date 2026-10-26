<?php
use App\Services\CustomerService;
use App\Services\PqrfService;

$__pqrfSvc = new PqrfService();
$__pqrfChannels = $__pqrfSvc->channels();
$__pqrfCustomer = (new CustomerService())->current();
$__pqrfSeg = trim((string)($segments[1] ?? ''), '/');
$__pqrfIsTrack = $__pqrfSeg === 'seguimiento';
$__pqrfToken = trim((string)($_GET['token'] ?? ''));

if ($__pqrfIsTrack) {
    sonika_site_head('Seguimiento de tu solicitud — SONIKA', 'Consulta el estado y el historial de tu solicitud radicada en SONIKA.');
    sonika_breadcrumb(['PQRS' => '/pqrf', 'Seguimiento' => null]);
} else {
    sonika_site_head('PQRS / Radica tu solicitud — SONIKA', 'Radica peticiones, quejas, reclamos, sugerencias o felicitaciones y sigue su estado.');
    sonika_breadcrumb(['PQRS / Radica tu solicitud' => null]);
}

$__pqrfWa = (string)$__pqrfChannels['whatsapp_url'];
$__pqrfMail = (string)$__pqrfChannels['contact_email'];
?>

<style>
.pqrf{--brand:#F20D16;--ink:#111111;--muted:#6B6B6B;--line:#E6E6E6;--soft:#F7F7F7;--ok:#0E9F6E;--warn:#B45309;--err:#B42318;width:100%;max-width:100%;color:var(--ink);font-family:Sora,system-ui,sans-serif}
.pqrf *,.pqrf *::before,.pqrf *::after{box-sizing:border-box}
.pqrf__wrap{max-width:880px;margin-inline:auto;padding:clamp(20px,4vw,44px) clamp(14px,4vw,24px) clamp(40px,8vw,76px)}
.pqrf__eyebrow{display:flex;align-items:center;gap:10px;margin:0 0 12px;color:var(--brand);font-size:11.5px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
.pqrf__eyebrow::before{content:"";width:34px;height:2px;background:var(--brand)}
.pqrf h1{margin:0 0 10px;font-size:clamp(27px,4.2vw,42px);line-height:1.08;letter-spacing:-.035em}
.pqrf__lead{margin:0 0 24px;max-width:66ch;color:var(--muted);font-size:clamp(14.5px,1.7vw,16.5px);line-height:1.65}
.pqrf__card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:clamp(20px,3.4vw,34px);box-shadow:0 16px 44px rgba(17,17,17,.06)}
.pqrf__notice{display:grid;grid-template-columns:34px minmax(0,1fr);gap:12px;align-items:start;margin:0 0 18px;padding:14px 15px;border-radius:12px;background:#FFF5F5;border:1px solid #F5C2C2;color:#7A1015;font-size:13.5px;line-height:1.55}
.pqrf__notice b{display:block;margin-bottom:2px;color:#7A1015}
.pqrf__notice--ok{background:#F0FBF6;border-color:#BFE8D5;color:#0A6B49}
.pqrf__notice--warn{background:#FFF8EC;border-color:#F3D7A5;color:#7A4A05}
.pqrf__notice-icon{display:grid;place-items:center;width:34px;height:34px;border-radius:50%;background:#fff;font-weight:900;font-size:16px}
.pqrf__channels{display:grid;gap:10px;margin:18px 0 0;padding:0;list-style:none}
.pqrf__channels a,.pqrf__channels span{display:flex;align-items:center;gap:10px;min-height:44px;padding:10px 13px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--ink);text-decoration:none;font-size:14px;font-weight:600;overflow-wrap:anywhere}
.pqrf__channels a:hover,.pqrf__channels a:focus-visible{border-color:var(--brand);color:var(--brand)}
.pqrf__channels svg{flex:0 0 auto;width:18px;height:18px;color:var(--brand)}
.pqrf__cta{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-height:48px;padding:13px 22px;border:0;border-radius:10px;background:var(--brand);color:#fff;font:inherit;font-size:15px;font-weight:750;text-decoration:none;cursor:pointer;transition:filter .15s ease,transform .15s ease}
.pqrf__cta:hover{filter:brightness(.94)}
.pqrf__cta:active{transform:translateY(1px)}
.pqrf__cta:focus-visible{outline:3px solid rgba(242,13,22,.32);outline-offset:3px}
.pqrf__cta--ghost{background:#fff;color:var(--ink);border:1px solid var(--line)}
.pqrf__cta--ghost:hover{border-color:var(--brand);color:var(--brand);filter:none}
.pqrf__cta[disabled]{opacity:.6;cursor:wait}
.pqrf__progress{margin:0 0 24px}
.pqrf__progress-top{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px;color:var(--muted);font-size:12px;font-weight:700}
.pqrf__bar{position:relative;height:7px;border-radius:99px;background:#ECECEC;overflow:hidden}
.pqrf__bar span{display:block;height:100%;width:0;border-radius:99px;background:linear-gradient(90deg,var(--brand),#FF5A61);transition:width .22s ease}
.pqrf__step{margin:0;padding:0;border:0;min-width:0}
.pqrf__step[hidden]{display:none!important}
.pqrf__legend{margin:0 0 5px;padding:0;font-size:clamp(19px,2.5vw,25px);font-weight:800;line-height:1.2;letter-spacing:-.02em}
.pqrf__hint{margin:0 0 18px;color:var(--muted);font-size:13px;line-height:1.55}
.pqrf__options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.pqrf__option{position:relative;display:flex;align-items:flex-start;gap:10px;min-height:58px;padding:13px 14px;border:1.5px solid var(--line);border-radius:12px;background:#fff;cursor:pointer;transition:border-color .15s ease,background .15s ease}
.pqrf__option:hover{border-color:#C9C9C9}
.pqrf__option:has(input:checked){border-color:var(--brand);background:#FFF7F7;box-shadow:0 0 0 3px rgba(242,13,22,.08)}
.pqrf__option input{flex:0 0 auto;width:19px;height:19px;margin:2px 0 0;accent-color:var(--brand)}
.pqrf__option span{display:block;font-size:13.5px;font-weight:700;line-height:1.35}
.pqrf__option small{display:block;margin-top:2px;color:var(--muted);font-size:12px;font-weight:400;line-height:1.4}
.pqrf__field{margin:0 0 16px}
.pqrf__label{display:block;margin:0 0 6px;font-size:13px;font-weight:700}
.pqrf__label .req{color:var(--brand)}
.pqrf__input,.pqrf__select,.pqrf__textarea{display:block;width:100%;min-height:46px;padding:11px 12px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--ink);font:inherit;font-size:16px;line-height:1.45;transition:border-color .15s ease,box-shadow .15s ease}
.pqrf__textarea{min-height:150px;resize:vertical}
.pqrf__input:focus-visible,.pqrf__select:focus-visible,.pqrf__textarea:focus-visible,.pqrf__option input:focus-visible{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(242,13,22,.12)}
.pqrf__input[readonly]{background:var(--soft);color:#555}
.pqrf__help{margin:5px 0 0;color:var(--muted);font-size:11.5px;line-height:1.45}
.pqrf__error{margin:5px 0 0;color:var(--err);font-size:12px;font-weight:650;line-height:1.4}
.pqrf__error:empty{display:none}
.pqrf__files{display:grid;gap:8px;margin-top:10px}
.pqrf__file{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:9px 11px;border:1px solid var(--line);border-radius:9px;background:var(--soft);font-size:12.5px;overflow-wrap:anywhere}
.pqrf__file b{font-weight:650}
.pqrf__file span{color:var(--muted);white-space:nowrap}
.pqrf__summary{display:grid;gap:9px;margin:0 0 18px;padding:14px;border:1px solid var(--line);border-radius:11px;background:var(--soft)}
.pqrf__summary-row{display:grid;grid-template-columns:110px minmax(0,1fr);gap:12px;font-size:13px;line-height:1.45}
.pqrf__summary-row dt{color:var(--muted);font-weight:600}
.pqrf__summary-row dd{margin:0;font-weight:650;overflow-wrap:anywhere}
.pqrf__auth{display:flex;align-items:flex-start;gap:10px;padding:13px 14px;border:1.5px solid var(--line);border-radius:11px;background:#fff;font-size:13px;line-height:1.55}
.pqrf__auth input{flex:0 0 auto;width:20px;height:20px;margin:1px 0 0;accent-color:var(--brand)}
.pqrf__auth a{color:var(--brand);font-weight:700;text-decoration:underline;text-underline-offset:2px}
.pqrf__nav{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:22px;padding-top:18px;border-top:1px solid var(--line)}
.pqrf__nav-group{display:flex;align-items:center;gap:9px;flex-wrap:wrap}
.pqrf__alert{margin:0 0 18px;padding:12px 14px;border-radius:10px;border:1px solid #F5C2C2;background:#FFF5F5;color:#7A1015;font-size:13px;line-height:1.5}
.pqrf__alert[hidden]{display:none!important}
.pqrf__success{text-align:center;padding:7px 0 0}
.pqrf__success-icon{display:grid;place-items:center;width:58px;height:58px;margin:0 auto 14px;border-radius:50%;background:#F0FBF6;color:var(--ok);font-size:27px;font-weight:900}
.pqrf__success h2{margin:0 0 7px;font-size:clamp(22px,3.4vw,30px);letter-spacing:-.02em}
.pqrf__success p{margin:0 auto 14px;max-width:54ch;color:var(--muted);font-size:14px;line-height:1.6}
.pqrf__number{display:inline-block;margin:4px 0 14px;padding:9px 14px;border-radius:9px;background:var(--soft);border:1px dashed #C9C9C9;font-size:clamp(17px,2.6vw,22px);font-weight:800;letter-spacing:.03em}
.pqrf__meta{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0 0 8px}
.pqrf__pill{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:99px;background:#EEF4FB;color:#155EEB;font-size:11.5px;font-weight:800;line-height:1.4}
.pqrf__pill--received{background:#FFF3CD;color:#7A4A05}
.pqrf__pill--review{background:#EEF4FB;color:#155EEB}
.pqrf__pill--info{background:#FFF8EC;color:#7A4A05}
.pqrf__pill--progress{background:#EEF4FB;color:#155EEB}
.pqrf__pill--responded{background:#F0FBF6;color:#0A6B49}
.pqrf__pill--closed{background:#F1F1F1;color:#555}
.pqrf__timeline{display:grid;gap:0;margin:18px 0 0;padding:0;list-style:none}
.pqrf__event{position:relative;padding:0 0 18px 25px;border-left:2px solid #ECECEC}
.pqrf__event:last-child{border-left-color:transparent;padding-bottom:0}
.pqrf__event::before{content:"";position:absolute;left:-7px;top:1px;width:12px;height:12px;border-radius:50%;background:#fff;border:3px solid var(--brand)}
.pqrf__event--customer::before{border-color:#155EEB}
.pqrf__event--system::before{border-color:#9CA3AF}
.pqrf__event-head{display:flex;flex-wrap:wrap;align-items:baseline;gap:8px;margin-bottom:3px}
.pqrf__event-head b{font-size:13px;line-height:1.35}
.pqrf__event-head time{color:var(--muted);font-size:11.5px}
.pqrf__event p{margin:0;color:#444;font-size:13px;line-height:1.55;white-space:pre-wrap;overflow-wrap:anywhere}
.pqrf__attach{display:grid;gap:8px;margin:14px 0 0;padding:0;list-style:none}
.pqrf__attach a{display:flex;align-items:center;justify-content:space-between;gap:10px;min-height:44px;padding:9px 12px;border:1px solid var(--line);border-radius:9px;background:#fff;text-decoration:none;color:var(--ink);font-size:13px;font-weight:600;overflow-wrap:anywhere}
.pqrf__attach a:hover{border-color:var(--brand);color:var(--brand)}
.pqrf__attach span{color:var(--muted);font-size:11.5px;white-space:nowrap}
.pqrf__track-form{display:grid;gap:10px;margin-top:14px}
.pqrf__track-form .pqrf__row{display:flex;gap:9px;flex-wrap:wrap}
.pqrf__track-form .pqrf__input{flex:1 1 240px}
.pqrf__small{margin:14px 0 0;color:var(--muted);font-size:12px;line-height:1.55}
.pqrf__small a{color:var(--brand);font-weight:650}
.pqrf__sr{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
@media(max-width:640px){.pqrf__options{grid-template-columns:1fr}.pqrf__summary-row{grid-template-columns:1fr;gap:2px}.pqrf__card{border-radius:14px;padding:18px 15px}.pqrf__nav{align-items:stretch;flex-direction:column-reverse}.pqrf__nav-group{width:100%}.pqrf__nav-group .pqrf__cta{width:100%}}
@media(prefers-reduced-motion:reduce){.pqrf *{transition-duration:.01ms!important;animation-duration:.01ms!important;scroll-behavior:auto!important}}
</style>

<div class="pqrf">
  <div class="pqrf__wrap">
<?php if ($__pqrfIsTrack): ?>
    <p class="pqrf__eyebrow">Seguimiento</p>
    <h1>Tu solicitud, paso a paso.</h1>
    <p class="pqrf__lead">Consulta el estado de tu radicado y responde si necesitamos más información. Este enlace es personal: no lo compartas.</p>
    <div class="pqrf__card" id="pqrf-track" data-token="<?= sonika_esc($__pqrfToken) ?>" aria-live="polite" aria-busy="true">
      <div id="pqrf-track-loading" style="color:#6B6B6B;font-size:14px">Cargando tu solicitud…</div>
      <div id="pqrf-track-error" hidden>
        <div class="pqrf__notice pqrf__notice--warn" role="alert">
          <div class="pqrf__notice-icon">!</div>
          <div><b>No pudimos abrir este seguimiento.</b><span id="pqrf-track-error-text">Revisa el enlace o escríbenos.</span></div>
        </div>
        <div id="pqrf-track-channels"></div>
      </div>
      <div id="pqrf-track-empty" <?= $__pqrfToken !== '' ? 'hidden' : '' ?>>
        <div class="pqrf__notice">
          <div class="pqrf__notice-icon">i</div>
          <div><b>Ingresa el código de tu enlace.</b>Lo encuentras al final del correo de confirmación, después de <code>token=</code>.</div>
        </div>
        <form class="pqrf__track-form" id="pqrf-token-form" novalidate>
          <label class="pqrf__label" for="pqrf-token-input">Código de seguimiento</label>
          <div class="pqrf__row">
            <input class="pqrf__input" id="pqrf-token-input" name="token" autocomplete="off" spellcheck="false" placeholder="Pega aquí el código de tu enlace" required>
            <button class="pqrf__cta" type="submit">Consultar</button>
          </div>
          <p class="pqrf__help">Si no tienes el enlace, escríbenos y te ayudamos a ubicar tu radicado.</p>
        </form>
      </div>
      <div id="pqrf-track-content" hidden></div>
    </div>
    <p class="pqrf__small">¿Necesitas radicar una nueva solicitud? <a href="/pqrf">Ir al formulario</a>.</p>
<?php elseif (!$__pqrfCustomer): ?>
    <p class="pqrf__eyebrow">PQRS · Canal oficial</p>
    <h1>Radica tu solicitud con tu cuenta.</h1>
    <p class="pqrf__lead">Para proteger tus datos y darte seguimiento, las solicitudes se radican desde tu cuenta de cliente SONIKA. Si aún no la tienes, puedes crearla en minutos.</p>
    <div class="pqrf__card">
      <div class="pqrf__notice">
        <div class="pqrf__notice-icon">i</div>
        <div><b>Necesitas iniciar sesión.</b>Así podemos asociar tu solicitud a tu cuenta y avisarte cuando haya novedades.</div>
      </div>
      <a class="pqrf__cta" href="<?= sonika_esc((string)$__pqrfChannels['login_url']) ?>">Iniciar sesión o crear cuenta</a>
      <h2 style="font-size:16px;margin:26px 0 4px">Canales oficiales de atención</h2>
      <p style="margin:0;color:#6B6B6B;font-size:13px;line-height:1.55">Si prefieres escribirnos sin cuenta, usa estos canales. No compartas contraseñas ni datos de tarjetas por estos medios.</p>
      <ul class="pqrf__channels">
        <li><a href="mailto:<?= sonika_esc($__pqrfMail) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg><span><?= sonika_esc($__pqrfMail) ?></span></a></li>
        <?php if ($__pqrfWa !== ''): ?>
        <li><a href="<?= sonika_esc($__pqrfWa) ?>" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.5 8.5 0 0 1-3.8-.9L3 21l1.9-5.1A8.4 8.4 0 0 1 4 11.5a8.4 8.4 0 0 1 8.5-8.4A8.4 8.4 0 0 1 21 11.5Z"/><path d="M8.5 8.5c.5 2 2 3.5 4 4l1-1.5 2 1"/></svg><span>WhatsApp <?= sonika_esc((string)$__pqrfChannels['whatsapp']) ?></span></a></li>
        <?php endif; ?>
        <li><a href="/pqrf/seguimiento"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><span>Consultar un radicado con mi enlace</span></a></li>
      </ul>
    </div>
<?php else: ?>
    <p class="pqrf__eyebrow">PQRS · Radica tu solicitud</p>
    <h1>Cuéntanos qué necesitas.</h1>
    <p class="pqrf__lead">Responderemos por el correo de tu cuenta. Puedes adjuntar hasta 5 archivos JPG, PNG, WebP o PDF de máximo 5 MB cada uno.</p>
    <div class="pqrf__card">
      <div class="pqrf__alert" id="pqrf-alert" role="alert" aria-live="assertive" tabindex="-1" hidden></div>
      <div class="pqrf__progress">
        <div class="pqrf__progress-top"><span id="pqrf-step-label">Paso 1 de 7</span><span id="pqrf-step-name">Tipo de solicitud</span></div>
        <div class="pqrf__bar" role="progressbar" aria-valuemin="1" aria-valuemax="7" aria-valuenow="1" aria-label="Progreso del formulario"><span id="pqrf-bar-fill"></span></div>
      </div>
      <form id="pqrf-form" novalidate>
        <input type="hidden" name="idempotency_key" id="pqrf-idem" value="">

        <fieldset class="pqrf__step" data-step="1">
          <legend class="pqrf__legend">¿Qué tipo de solicitud quieres radicar?</legend>
          <p class="pqrf__hint">Elige la opción que mejor describa tu caso.</p>
          <div class="pqrf__options">
            <?php foreach (PqrfService::TYPES as $__k => $__v): ?>
            <label class="pqrf__option"><input type="radio" name="type" value="<?= sonika_esc($__k) ?>" required><span><?= sonika_esc($__v) ?></span></label>
            <?php endforeach; ?>
          </div>
          <p class="pqrf__error" id="err-type" aria-live="polite"></p>
        </fieldset>

        <fieldset class="pqrf__step" data-step="2" hidden>
          <legend class="pqrf__legend">¿Sobre qué es tu solicitud?</legend>
          <p class="pqrf__hint">Nos ayuda a dirigirla al equipo correcto.</p>
          <label class="pqrf__label" for="pqrf-category">Motivo <span class="req">*</span></label>
          <select class="pqrf__select" id="pqrf-category" name="category" required>
            <option value="">Selecciona una opción</option>
            <?php foreach (PqrfService::CATEGORIES as $__k => $__v): ?>
            <option value="<?= sonika_esc($__k) ?>"><?= sonika_esc($__v) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="pqrf__error" id="err-category" aria-live="polite"></p>
        </fieldset>

        <fieldset class="pqrf__step" data-step="3" hidden>
          <legend class="pqrf__legend">Resume tu solicitud en una frase.</legend>
          <p class="pqrf__hint">Este será el asunto que verá el equipo de SONIKA.</p>
          <label class="pqrf__label" for="pqrf-subject">Asunto <span class="req">*</span></label>
          <input class="pqrf__input" id="pqrf-subject" name="subject" maxlength="180" autocomplete="off" placeholder="Ej.: Pedido incompleto o producto con novedad" required>
          <p class="pqrf__help"><span id="pqrf-subject-count">0</span>/180 caracteres</p>
          <p class="pqrf__error" id="err-subject" aria-live="polite"></p>
        </fieldset>

        <fieldset class="pqrf__step" data-step="4" hidden>
          <legend class="pqrf__legend">Cuéntanos con detalle.</legend>
          <p class="pqrf__hint">Incluye fechas, números de pedido o cualquier dato que nos ayude a entender tu caso.</p>
          <label class="pqrf__label" for="pqrf-description">Descripción <span class="req">*</span></label>
          <textarea class="pqrf__textarea" id="pqrf-description" name="description" maxlength="5000" placeholder="Describe tu solicitud..." required></textarea>
          <p class="pqrf__help"><span id="pqrf-desc-count">0</span>/5000 caracteres · mínimo 20</p>
          <p class="pqrf__error" id="err-description" aria-live="polite"></p>
        </fieldset>

        <fieldset class="pqrf__step" data-step="5" hidden>
          <legend class="pqrf__legend">Tus datos y el pedido.</legend>
          <p class="pqrf__hint">Usaremos el correo de tu cuenta para contactarte. Si tu caso se relaciona con un pedido, indícalo.</p>
          <div class="pqrf__field">
            <label class="pqrf__label" for="pqrf-name">Nombre completo <span class="req">*</span></label>
            <input class="pqrf__input" id="pqrf-name" name="contact_name" value="<?= sonika_esc((string)($__pqrfCustomer['name'] ?? '')) ?>" maxlength="120" autocomplete="name" required>
            <p class="pqrf__error" id="err-contact_name" aria-live="polite"></p>
          </div>
          <div class="pqrf__field">
            <label class="pqrf__label" for="pqrf-email">Correo de la cuenta</label>
            <input class="pqrf__input" id="pqrf-email" type="email" value="<?= sonika_esc((string)($__pqrfCustomer['email'] ?? '')) ?>" readonly aria-readonly="true">
            <p class="pqrf__help">Las respuestas se enviarán a este correo.</p>
            <p class="pqrf__error" id="err-contact_email" aria-live="polite"></p>
          </div>
          <div class="pqrf__field">
            <label class="pqrf__label" for="pqrf-phone">Teléfono de contacto <span style="font-weight:400;color:#6B6B6B">(opcional)</span></label>
            <input class="pqrf__input" id="pqrf-phone" name="contact_phone" value="<?= sonika_esc((string)($__pqrfCustomer['phone'] ?? '')) ?>" maxlength="40" inputmode="tel" autocomplete="tel">
            <p class="pqrf__error" id="err-contact_phone" aria-live="polite"></p>
          </div>
          <div class="pqrf__field">
            <label class="pqrf__label" for="pqrf-order">Número de pedido <span style="font-weight:400;color:#6B6B6B">(opcional)</span></label>
            <input class="pqrf__input" id="pqrf-order" name="order_ref" maxlength="60" autocomplete="off" placeholder="Ej.: PED-261014-62968">
            <p class="pqrf__help">Si no lo recuerdas, puedes encontrarlo en <a href="/cuenta?tab=orders" style="color:#F20D16;font-weight:650">Mi cuenta → Pedidos</a>.</p>
            <p class="pqrf__error" id="err-order_ref" aria-live="polite"></p>
          </div>
        </fieldset>

        <fieldset class="pqrf__step" data-step="6" hidden>
          <legend class="pqrf__legend">¿Quieres adjuntar algo?</legend>
          <p class="pqrf__hint">Máximo 5 archivos. Formatos: JPG, PNG, WebP o PDF. Hasta 5 MB por archivo. Los archivos se guardan de forma privada.</p>
          <label class="pqrf__label" for="pqrf-files">Archivos adjuntos <span style="font-weight:400;color:#6B6B6B">(opcional)</span></label>
          <input class="pqrf__input" id="pqrf-files" name="attachments[]" type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf">
          <p class="pqrf__help" id="pqrf-files-help">Puedes seleccionar varios archivos a la vez.</p>
          <div class="pqrf__files" id="pqrf-files-list"></div>
          <p class="pqrf__error" id="err-attachments" aria-live="polite"></p>
        </fieldset>

        <fieldset class="pqrf__step" data-step="7" hidden>
          <legend class="pqrf__legend">Revisa y autoriza.</legend>
          <p class="pqrf__hint">Verifica que todo esté correcto antes de radicar.</p>
          <dl class="pqrf__summary" id="pqrf-summary"></dl>
          <div class="pqrf__auth">
            <input type="checkbox" id="pqrf-auth" name="auth_accepted" value="1" required>
            <div>
              <label for="pqrf-auth" style="cursor:pointer">Autorizo el tratamiento de mis datos personales y acepto las políticas del servicio. <span class="req">*</span></label>
              <span style="display:block;margin-top:4px;color:#6B6B6B;font-size:12px">Consulta: <a href="/politica-de-privacidad" target="_blank" rel="noopener">privacidad</a> · <a href="/terminos-y-condiciones" target="_blank" rel="noopener">términos y condiciones</a> · <a href="/politica-de-devoluciones" target="_blank" rel="noopener">devoluciones</a>.</span>
            </div>
          </div>
          <p class="pqrf__error" id="err-auth_accepted" aria-live="polite"></p>
          <p class="pqrf__small">No publiques contraseñas, datos de tarjetas ni información sensible en la descripción o los adjuntos.</p>
        </fieldset>

        <div class="pqrf__nav">
          <div class="pqrf__nav-group">
            <button class="pqrf__cta pqrf__cta--ghost" type="button" id="pqrf-prev" hidden>Anterior</button>
            <a class="pqrf__cta pqrf__cta--ghost" href="/cuenta">Mi cuenta</a>
          </div>
          <div class="pqrf__nav-group">
            <button class="pqrf__cta" type="button" id="pqrf-next">Continuar</button>
            <button class="pqrf__cta" type="submit" id="pqrf-submit" hidden>Radicar solicitud</button>
          </div>
        </div>
      </form>
      <div id="pqrf-success" class="pqrf__success" hidden aria-live="polite"></div>
    </div>
    <noscript><div class="pqrf__notice pqrf__notice--warn" style="margin-top:16px"><div class="pqrf__notice-icon">!</div><div><b>Activa JavaScript para usar el formulario.</b>También puedes escribirnos a <a href="mailto:<?= sonika_esc($__pqrfMail) ?>"><?= sonika_esc($__pqrfMail) ?></a>.</div></div></noscript>
<?php endif; ?>
  </div>
</div>

<?php if (!$__pqrfIsTrack && $__pqrfCustomer): ?>
<script>
(function(){
  var form=document.getElementById('pqrf-form');
  if(!form)return;
  var steps=[].slice.call(form.querySelectorAll('.pqrf__step'));
  var total=steps.length,current=1;
  var next=document.getElementById('pqrf-next'),prev=document.getElementById('pqrf-prev'),submit=document.getElementById('pqrf-submit');
  var alertBox=document.getElementById('pqrf-alert'),bar=document.getElementById('pqrf-bar-fill'),stepLabel=document.getElementById('pqrf-step-label'),stepName=document.getElementById('pqrf-step-name');
  var names=['Tipo de solicitud','Motivo','Asunto','Descripción','Tus datos','Adjuntos','Revisión y autorización'];
  var idem=document.getElementById('pqrf-idem');
  if(idem){idem.value=(window.crypto&&crypto.randomUUID)?crypto.randomUUID():('pqrf-'+Date.now()+'-'+Math.random().toString(16).slice(2));}
  function esc(s){var d=document.createElement('div');d.textContent=s==null?'':String(s);return d.innerHTML}
  function showStep(n){
    current=Math.max(1,Math.min(total,n));
    steps.forEach(function(s,i){s.hidden=(i+1)!==current});
    if(bar)bar.style.width=((current/total)*100)+'%';
    if(stepLabel)stepLabel.textContent='Paso '+current+' de '+total;
    if(stepName)stepName.textContent=names[current-1]||'';
    var pb=document.querySelector('.pqrf__progress [role="progressbar"]');if(pb)pb.setAttribute('aria-valuenow',String(current));
    prev.hidden=current===1;
    next.hidden=current===total;
    submit.hidden=current!==total;
    if(current===total)buildSummary();
    var first=steps[current-1].querySelector('input:not([type="hidden"]),select,textarea,button');
    if(first){try{first.focus({preventScroll:true})}catch(e){}}
  }
  function setError(id,msg){var el=document.getElementById('err-'+id);if(el)el.textContent=msg||'';}
  function clearErrors(){['type','category','subject','description','contact_name','contact_email','contact_phone','order_ref','attachments','auth_accepted'].forEach(function(k){setError(k,'')});alertBox.hidden=true;alertBox.textContent='';}
  function alertMsg(msg){alertBox.textContent=msg;alertBox.hidden=false;alertBox.focus&&alertBox.focus();}
  function checked(name){var el=form.querySelector('input[name="'+name+'"]:checked');return el?el.value:''}
  function val(id){var el=document.getElementById(id);return el?String(el.value||'').trim():''}
  function validateStep(n){
    clearErrors();
    if(n===1&&!checked('type')){setError('type','Selecciona el tipo de solicitud.');return false}
    if(n===2&&!val('pqrf-category')){setError('category','Selecciona el motivo de tu solicitud.');return false}
    if(n===3&&val('pqrf-subject').length<5){setError('subject','Escribe un asunto de al menos 5 caracteres.');return false}
    if(n===4&&val('pqrf-description').length<20){setError('description','Cuéntanos con más detalle (mínimo 20 caracteres).');return false}
    if(n===5){
      if(val('pqrf-name').length<2){setError('contact_name','Escribe tu nombre.');return false}
      var ref=val('pqrf-order');
      if(ref&&!/^[A-Za-z0-9#._\-\/ ]{3,60}$/.test(ref)){setError('order_ref','El número de pedido no tiene un formato válido.');return false}
    }
    if(n===6){var f=document.getElementById('pqrf-files');if(f&&f.files&&f.files.length>5){setError('attachments','Puedes adjuntar máximo 5 archivos.');return false}}
    if(n===7&&!(document.getElementById('pqrf-auth')||{}).checked){setError('auth_accepted','Debes autorizar el tratamiento de tus datos para radicar la solicitud.');return false}
    return true;
  }
  next.addEventListener('click',function(){if(validateStep(current))showStep(current+1)});
  prev.addEventListener('click',function(){clearErrors();showStep(current-1)});
  form.addEventListener('keydown',function(e){
    if(e.key==='Enter'&&current<total&&e.target&&e.target.tagName!=='TEXTAREA'){e.preventDefault();if(validateStep(current))showStep(current+1)}
  });
  function summaryRow(k,v){return '<div class="pqrf__summary-row"><dt>'+esc(k)+'</dt><dd>'+esc(v||'—')+'</dd></div>'}
  function buildSummary(){
    var typeLabel=document.querySelector('input[name="type"]:checked');
    var typeText=typeLabel?typeLabel.closest('.pqrf__option').querySelector('span').textContent:'';
    var cat=document.getElementById('pqrf-category');
    var catText=cat&&cat.selectedIndex>=0?cat.options[cat.selectedIndex].text:'';
    var files=document.getElementById('pqrf-files');
    var fileNames=files&&files.files?[].slice.call(files.files).map(function(f){return f.name}).join(', '):'';
    document.getElementById('pqrf-summary').innerHTML=
      summaryRow('Tipo',typeText)+
      summaryRow('Motivo',catText)+
      summaryRow('Asunto',val('pqrf-subject'))+
      summaryRow('Descripción',val('pqrf-description'))+
      summaryRow('Nombre',val('pqrf-name'))+
      summaryRow('Correo',val('pqrf-email'))+
      summaryRow('Teléfono',val('pqrf-phone'))+
      summaryRow('Pedido',val('pqrf-order'))+
      summaryRow('Adjuntos',fileNames);
  }
  var subject=document.getElementById('pqrf-subject'),subjectCount=document.getElementById('pqrf-subject-count');
  if(subject&&subjectCount)subject.addEventListener('input',function(){subjectCount.textContent=String(subject.value.length)});
  var desc=document.getElementById('pqrf-description'),descCount=document.getElementById('pqrf-desc-count');
  if(desc&&descCount)desc.addEventListener('input',function(){descCount.textContent=String(desc.value.length)});
  var files=document.getElementById('pqrf-files'),filesList=document.getElementById('pqrf-files-list'),filesHelp=document.getElementById('pqrf-files-help');
  if(files&&filesList)files.addEventListener('change',function(){
    filesList.innerHTML='';
    var list=[].slice.call(files.files||[]);
    if(list.length>5){setError('attachments','Puedes adjuntar máximo 5 archivos.');files.value='';filesList.innerHTML='';return}
    var total=0,ok=true;
    list.forEach(function(f){
      total+=f.size;
      if(f.size>5*1024*1024){ok=false;setError('attachments','El archivo '+f.name+' supera el límite de 5 MB.');}
      var row=document.createElement('div');row.className='pqrf__file';
      row.innerHTML='<b>'+esc(f.name)+'</b><span>'+esc((f.size/1024/1024).toFixed(2))+' MB</span>';
      filesList.appendChild(row);
    });
    if(!ok){files.value='';filesList.innerHTML='';total=0;list=[]}
    if(filesHelp)filesHelp.textContent=list.length?list.length+' archivo(s) seleccionado(s).':'Puedes seleccionar varios archivos a la vez.';
  });
  form.addEventListener('submit',async function(e){
    e.preventDefault();
    if(!validateStep(1)){showStep(1);return}
    for(var i=2;i<=7;i++){if(!validateStep(i)){showStep(i);return}}
    clearErrors();
    submit.disabled=true;submit.textContent='Radicando…';
    try{
      var fd=new FormData(form);
      if(!fd.get('auth_accepted'))fd.set('auth_accepted','1');
      if(!fd.get('idempotency_key'))fd.set('idempotency_key',idem?String(idem.value):'');
      var res=await fetch('/api/v1/public/pqrf',{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});
      var data=await res.json().catch(function(){return {}});
      if(res.status===401){
        alertMsg((data&&data.message)||'Tu sesión expiró. Inicia sesión de nuevo.');
        submit.disabled=false;submit.textContent='Radicar solicitud';
        return;
      }
      if(res.status===429){
        alertMsg((data&&data.message)||'Demasiados intentos. Espera unos minutos.');
        submit.disabled=false;submit.textContent='Radicar solicitud';
        return;
      }
      if(!res.ok||!data.ok){
        var errs=(data&&data.errors)||{};
        var map={type:1,category:2,subject:3,description:4,contact_name:5,contact_email:5,contact_phone:5,order_ref:5,attachments:6,auth_accepted:7};
        var firstStep=7;
        Object.keys(errs).forEach(function(k){if(map[k]&&map[k]<firstStep)firstStep=map[k];setError(k,errs[k]);});
        alertMsg((data&&data.message)||'Revisa los campos marcados.');
        showStep(firstStep);
        submit.disabled=false;submit.textContent='Radicar solicitud';
        return;
      }
      form.hidden=true;
      var box=document.getElementById('pqrf-success');
      var c=data.case||{};
      var html='<div class="pqrf__success-icon">✓</div><h2>'+(data.duplicate?'Tu solicitud ya estaba registrada':'Solicitud radicada')+'</h2><p>'+(data.duplicate?'No creamos un radicado nuevo para evitar duplicados.':'Guarda este número y el enlace de seguimiento.')+'</p><div class="pqrf__number">'+esc(c.case_number||'')+'</div>';
      if(data.tracking_url){html+='<p><a class="pqrf__cta" href="'+esc(data.tracking_url)+'">Ver estado de mi solicitud</a></p>';}
      else{html+='<p>Revisa el correo de confirmación de tu cuenta para abrir el seguimiento.</p><p><a class="pqrf__cta pqrf__cta--ghost" href="/pqrf/seguimiento">Ir a seguimiento</a></p>';}
      if(data.duplicate){html+='<p>'+esc(data.message||'')+'</p>';}
      else if(data.email_sent){html+='<p>También enviamos la confirmación a '+esc(document.getElementById('pqrf-email').value)+'. Revisa spam si no la ves.</p>';}
      else{html+='<p>No pudimos confirmar el envío del correo, pero tu radicado quedó guardado. Usa el enlace de arriba.</p>';}
      html+='<p><a href="/cuenta" style="color:#6B6B6B;font-size:13px">Volver a mi cuenta</a></p>';
      box.innerHTML=html;box.hidden=false;
      box.setAttribute('tabindex','-1');box.focus&&box.focus();
      window.scrollTo({top:0,behavior:'smooth'});
    }catch(err){
      alertMsg('No pudimos conectar. Revisa tu conexión e intenta de nuevo.');
      submit.disabled=false;submit.textContent='Radicar solicitud';
    }
  });
  showStep(1);
})();
</script>
<?php elseif ($__pqrfIsTrack): ?>
<script>
(function(){
  var box=document.getElementById('pqrf-track');
  if(!box)return;
  var token=(box.getAttribute('data-token')||'').trim();
  var loading=document.getElementById('pqrf-track-loading'),errorBox=document.getElementById('pqrf-track-error'),errorText=document.getElementById('pqrf-track-error-text'),channelsBox=document.getElementById('pqrf-track-channels'),content=document.getElementById('pqrf-track-content'),empty=document.getElementById('pqrf-track-empty');
  function esc(s){var d=document.createElement('div');d.textContent=s==null?'':String(s);return d.innerHTML}
  function fmt(iso){if(!iso)return'';try{return new Date(iso).toLocaleString('es-CO',{dateStyle:'medium',timeStyle:'short'})}catch(e){return iso}}
  function pillClass(st){return st==='RECEIVED'?'pqrf__pill--received':st==='IN_REVIEW'?'pqrf__pill--review':st==='INFO_REQUESTED'?'pqrf__pill--info':st==='IN_PROGRESS'?'pqrf__pill--progress':st==='RESPONDED'?'pqrf__pill--responded':'pqrf__pill--closed'}
  function channels(c){
    if(!c)return'';
    var h='<ul class="pqrf__channels">';
    if(c.contact_email)h+='<li><a href="mailto:'+esc(c.contact_email)+'"><span>'+esc(c.contact_email)+'</span></a></li>';
    if(c.whatsapp_url)h+='<li><a href="'+esc(c.whatsapp_url)+'" target="_blank" rel="noopener noreferrer"><span>WhatsApp '+esc(c.whatsapp||'')+'</span></a></li>';
    h+='<li><a href="/pqrf"><span>Radicar una nueva solicitud</span></a></li></ul>';
    return h;
  }
  function showError(msg,c){
    loading.hidden=true;content.hidden=true;errorBox.hidden=false;errorText.textContent=msg||'Revisa el enlace o escríbenos.';
    channelsBox.innerHTML=channels(c);
  }
  async function load(){
    if(!token){loading.hidden=true;empty.hidden=false;return}
    empty.hidden=true;loading.hidden=false;errorBox.hidden=true;content.hidden=true;
    box.setAttribute('aria-busy','true');
    try{
      var res=await fetch('/api/v1/public/pqrf/track?token='+encodeURIComponent(token),{headers:{'Accept':'application/json'}});
      var data=await res.json().catch(function(){return {}});
      if(!res.ok||!data.ok){showError((data&&data.message)||'No pudimos abrir este seguimiento.',data&&data.channels);return}
      var d=data.data||{};
      var html='<div class="pqrf__meta"><span class="pqrf__pill '+pillClass(d.status)+'">'+esc(d.status_label||d.status)+'</span><span class="pqrf__pill">'+esc(d.type_label||d.type)+'</span></div>';
      html+='<h2 style="margin:0 0 5px;font-size:clamp(21px,3vw,27px);letter-spacing:-.02em">'+esc(d.subject||'Solicitud')+'</h2>';
      html+='<p style="margin:0 0 4px;color:#6B6B6B;font-size:13px">Radicado <b style="color:#111">'+esc(d.case_number||'')+'</b></p>';
      html+='<p style="margin:0;color:#6B6B6B;font-size:12px">Radicado el '+esc(fmt(d.created_at))+' · Última actualización '+esc(fmt(d.updated_at))+'</p>';
      if(d.events&&d.events.length){
        html+='<h3 style="font-size:15px;margin:24px 0 12px">Historial visible</h3><ol class="pqrf__timeline">';
        d.events.forEach(function(e){
          var cls=e.actor==='Titular'?'pqrf__event--customer':(e.actor==='Sistema'?'pqrf__event--system':'');
          html+='<li class="pqrf__event '+cls+'"><div class="pqrf__event-head"><b>'+esc(e.label||e.event)+'</b><time datetime="'+esc(e.created_at||'')+'">'+esc(fmt(e.created_at))+'</time></div>';
          if(e.to_status_label&&e.event==='STATUS_CHANGED')html+='<p>Estado: '+esc(e.to_status_label)+'</p>';
          if(e.note)html+='<p>'+esc(e.note)+'</p>';
          html+='</li>';
        });
        html+='</ol>';
      }
      if(d.attachments&&d.attachments.length){
        html+='<h3 style="font-size:15px;margin:24px 0 8px">Adjuntos</h3><ul class="pqrf__attach">';
        d.attachments.forEach(function(a){
          html+='<li><a href="'+esc(a.url)+'" rel="noopener"><b>'+esc(a.name)+'</b><span>'+esc(((a.bytes||0)/1024/1024).toFixed(2))+' MB</span></a></li>';
        });
        html+='</ul>';
      }
      if(d.can_reply!==false){
        html+='<h3 style="font-size:15px;margin:26px 0 6px">¿Necesitas agregar información?</h3><p style="margin:0 0 8px;color:#6B6B6B;font-size:13px">Escribe un mensaje y lo sumaremos al historial de tu radicado.</p><form id="pqrf-track-msg" style="display:grid;gap:9px"><label class="pqrf__sr" for="pqrf-msg">Mensaje adicional</label><textarea class="pqrf__textarea" id="pqrf-msg" maxlength="3000" placeholder="Escribe tu mensaje..." required></textarea><div><button class="pqrf__cta" type="submit" id="pqrf-msg-btn">Enviar mensaje</button></div><p class="pqrf__error" id="pqrf-msg-error" aria-live="polite"></p></form>';
      }
      html+='<p class="pqrf__small">Este enlace es personal. Si no reconoces esta solicitud, escríbenos a '+esc((d.channels&&d.channels.contact_email)||'clientes@sonika.com.co')+'.</p>';
      content.innerHTML=html;content.hidden=false;loading.hidden=true;box.setAttribute('aria-busy','false');
      var f=document.getElementById('pqrf-track-msg');
      if(f)f.addEventListener('submit',async function(ev){
        ev.preventDefault();var msg=document.getElementById('pqrf-msg').value;var btn=document.getElementById('pqrf-msg-btn');var err=document.getElementById('pqrf-msg-error');
        err.textContent='';btn.disabled=true;btn.textContent='Enviando…';
        try{
          var r=await fetch('/api/v1/public/pqrf/track',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({token:token,message:msg})});
          var j=await r.json().catch(function(){return {}});
          if(!r.ok||!j.ok){err.textContent=(j&&j.message)||'No pudimos enviar el mensaje.';btn.disabled=false;btn.textContent='Enviar mensaje';return}
          await load();
        }catch(e){err.textContent='No pudimos conectar. Intenta de nuevo.';btn.disabled=false;btn.textContent='Enviar mensaje';}
      });
    }catch(e){showError('No pudimos conectar. Intenta de nuevo.');}
  }
  var tf=document.getElementById('pqrf-token-form');
  if(tf)tf.addEventListener('submit',function(e){e.preventDefault();var v=document.getElementById('pqrf-token-input').value.trim();if(v)location.href='/pqrf/seguimiento?token='+encodeURIComponent(v)});
  load();
})();
</script>
<?php endif; ?>

<?php sonika_site_footer(); ?>
