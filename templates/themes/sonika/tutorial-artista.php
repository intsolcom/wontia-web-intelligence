<?php $registerUrl = '/sonika-partners#registro'; ?>
<section class="tut">
  <style>
    .tut{background:#f7f5f1;color:#171717;padding:28px 0 54px}
    .tut *{box-sizing:border-box}
    .tut__wrap{max-width:1120px;margin:0 auto;padding:0 22px}
    .tut__hero{background:#111;color:#fff;border-radius:22px;padding:clamp(24px,4vw,42px);margin-bottom:22px;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:18px;align-items:end}
    .tut__eyebrow{color:#ff8e91;font-size:10px;font-weight:850;letter-spacing:.14em;text-transform:uppercase}
    .tut h1{font:850 clamp(1.8rem,4vw,3rem)/1.04 Sora,Inter,system-ui,sans-serif;letter-spacing:-.045em;margin:12px 0 10px;max-width:16ch}
    .tut__hero p{font-size:13px;line-height:1.65;color:#cfcfcf;max-width:62ch;margin:0}
    .tut__time{display:inline-flex;align-items:center;gap:6px;margin-top:13px;font-size:11px;font-weight:750;color:#d8b46a}
    .tut__hero-cta{display:inline-flex;align-items:center;justify-content:center;min-height:44px;border-radius:11px;padding:11px 15px;background:#fff;color:#111;font:850 12px Sora,Inter,system-ui,sans-serif;text-decoration:none;white-space:nowrap}
    .tut__layout{display:grid;grid-template-columns:245px minmax(0,1fr);gap:24px;align-items:start}
    .tut__nav{position:sticky;top:18px;background:#fff;border:1px solid #e5e0d8;border-radius:16px;padding:15px}
    .tut__nav-title{font-size:10px;font-weight:850;letter-spacing:.12em;text-transform:uppercase;color:#8a8176;margin:0 0 9px}
    .tut__nav a{display:block;border-left:2px solid transparent;padding:7px 9px;color:#5f5a52;font-size:11.5px;line-height:1.4;text-decoration:none;border-radius:0 7px 7px 0}
    .tut__nav a:hover,.tut__nav a:focus-visible{background:#f7f3ed;color:#111;outline:none}
    .tut__nav a.is-active{border-left-color:#F20D16;background:#fff3f3;color:#111;font-weight:800}
    .tut__content{min-width:0}
    .tut__section{background:#fff;border:1px solid #e5e0d8;border-radius:16px;padding:clamp(18px,3vw,26px);margin-bottom:14px;scroll-margin-top:24px}
    .tut__section h2{font:800 clamp(1.3rem,2.2vw,1.7rem)/1.12 Sora,Inter,system-ui,sans-serif;letter-spacing:-.025em;margin:0 0 10px;color:#171717}
    .tut__section h3{font:800 14px/1.3 Sora,Inter,system-ui,sans-serif;margin:16px 0 6px;color:#171717}
    .tut__section p{font-size:12.5px;line-height:1.65;color:#5f5a52;margin:0 0 10px}
    .tut__section ul,.tut__section ol{margin:8px 0 12px;padding-left:20px;color:#5f5a52;font-size:12.5px;line-height:1.7}
    .tut__section li{margin:4px 0}
    .tut__step-kicker{display:inline-flex;align-items:center;gap:7px;background:#fff1f1;color:#c40d15;border-radius:999px;padding:4px 8px;font-size:10px;font-weight:850;letter-spacing:.06em;text-transform:uppercase;margin-bottom:8px}
    .tut__step-kicker b{display:grid;place-items:center;width:20px;height:20px;border-radius:50%;background:#F20D16;color:#fff;font-size:10px}
    .tut__callout{border-left:3px solid #F20D16;background:#fff7f7;border-radius:0 10px 10px 0;padding:11px 13px;font-size:11.5px;line-height:1.6;color:#5b3a3a;margin:12px 0}
    .tut__callout--green{border-color:#0d9f72;background:#f1fbf7;color:#285e4b}
    .tut__link{display:inline-flex;align-items:center;gap:7px;min-height:40px;border:1px solid #d9d4cc;border-radius:9px;padding:9px 12px;color:#171717;font-size:11.5px;font-weight:800;text-decoration:none;margin:3px 5px 3px 0;background:#fff}
    .tut__link--primary{background:#F20D16;border-color:#F20D16;color:#fff}
    .tut__link:hover,.tut__link:focus-visible{filter:brightness(.96);outline:none}
    .tut__check{list-style:none;padding:0!important}
    .tut__check li{position:relative;padding-left:22px;margin:7px 0}
    .tut__check li:before{content:"✓";position:absolute;left:0;top:0;color:#0d9f72;font-weight:900}
    .tut__faq{border-top:1px solid #ece8e1;padding:12px 0}
    .tut__faq:first-of-type{border-top:0;padding-top:0}
    .tut__faq summary{cursor:pointer;font-size:12px;font-weight:800;color:#24211e;list-style:none;display:flex;justify-content:space-between;gap:10px}
    .tut__faq summary::-webkit-details-marker{display:none}
    .tut__faq summary:after{content:"+";font-size:17px;color:#F20D16;line-height:1}
    .tut__faq[open] summary:after{content:"−"}
    .tut__faq p{margin:8px 0 0;font-size:11.5px}
    .tut__final{background:#111;color:#fff;border-radius:16px;padding:24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
    .tut__final h2{color:#fff;margin:0 0 5px;font-size:clamp(1.2rem,2.3vw,1.6rem)}
    .tut__final p{color:#bbb;margin:0;font-size:12px;line-height:1.55}
    .tut__backtop{display:inline-flex;margin-top:12px;color:#777;font-size:11px;font-weight:750;text-decoration:none}
    @media(max-width:900px){.tut__hero{grid-template-columns:1fr}.tut__layout{grid-template-columns:1fr;gap:14px}.tut__nav{position:static;display:flex;overflow-x:auto;gap:4px;padding:8px;scrollbar-width:none}.tut__nav::-webkit-scrollbar{display:none}.tut__nav-title{display:none}.tut__nav a{flex:0 0 auto;border-left:0;border-bottom:2px solid transparent;border-radius:7px;padding:8px 9px;white-space:nowrap}.tut__nav a.is-active{border-left-color:transparent;border-bottom-color:#F20D16}}
    @media(max-width:560px){.tut__wrap{padding:0 14px}.tut__hero{padding:21px;border-radius:16px}.tut__section{padding:17px}.tut__hero-cta{width:100%}.tut__nav a{font-size:11px}}
    @media(prefers-reduced-motion:reduce){.tut{scroll-behavior:auto}}
  </style>
  <div class="tut__wrap">
    <header class="tut__hero">
      <div>
        <div class="tut__eyebrow">SONIKA Partners</div>
        <h1>Cómo crear tu cuenta de SONIKA Partners.</h1>
        <p>Una guía práctica para pasar de la idea al registro, completar tu perfil, firmar tu contrato y activar tu página y tu enlace personal.</p>
        <div class="tut__time">◷ Tiempo estimado: 10–15 minutos · No necesitas tarjeta</div>
      </div>
      <a class="tut__hero-cta" href="<?= $registerUrl ?>">Empezar registro →</a>
    </header>

    <div class="tut__layout">
      <nav class="tut__nav" aria-label="Contenido del tutorial">
        <div class="tut__nav-title">En este tutorial</div>
        <a href="#antes" class="is-active">Antes de empezar</a>
        <a href="#paso-1">1. Entra al programa</a>
        <a href="#paso-2">2. Crea tu cuenta</a>
        <a href="#paso-3">3. Verifica tu correo</a>
        <a href="#paso-4">4. Inicia sesión</a>
        <a href="#paso-5">5. Completa tu perfil</a>
        <a href="#paso-6">6. Firma tu contrato</a>
        <a href="#paso-7">7. Activa tu página</a>
        <a href="#preguntas">Preguntas frecuentes</a>
      </nav>

      <div class="tut__content">
        <section class="tut__section" id="antes">
          <h2>Antes de empezar</h2>
          <p>SONIKA Partners es un programa comercial para artistas y músicos. Puedes crear tu cuenta sin costo y solicitar ingreso; la aprobación no es automática y depende de la revisión del equipo.</p>
          <div class="tut__callout">Ten a mano tu documento de identidad, un correo activo y una contraseña de mínimo 8 caracteres. Si eres menor de edad, necesitas la autorización de tu representante legal.</div>
        </section>

        <section class="tut__section" id="paso-1">
          <div class="tut__step-kicker"><b>1</b> Paso 1 de 7</div>
          <h2>Entra a la página oficial del programa</h2>
          <p>Abre <strong>sonika.com.co/sonika-partners</strong>. Allí encontrarás los beneficios, las condiciones del programa, el acceso para artistas existentes y el formulario de solicitud.</p>
          <a class="tut__link tut__link--primary" href="/sonika-partners">Abrir SONIKA Partners →</a>
          <div class="tut__callout">No busques formularios en redes sociales ni enlaces de terceros: el registro oficial está dentro de sonika.com.co.</div>
        </section>

        <section class="tut__section" id="paso-2">
          <div class="tut__step-kicker"><b>2</b> Paso 2 de 7</div>
          <h2>Crea tu cuenta de artista</h2>
          <p>En la página del programa pulsa <strong>“Crear cuenta de artista”</strong>. Completa el formulario:</p>
          <ul class="tut__check">
            <li>Nombre completo y correo electrónico.</li>
            <li>Nombre artístico y nombre público de tienda, si aplica.</li>
            <li>Tipo y número de documento.</li>
            <li>Instrumento principal, nivel y género musical.</li>
            <li>Contraseña de mínimo 8 caracteres.</li>
            <li>Aceptación de términos y política de datos.</li>
          </ul>
          <p>Envía la solicitud. Verás un mensaje de confirmación y recibirás un código de verificación en tu correo.</p>
          <a class="tut__link" href="<?= $registerUrl ?>">Ir al formulario de registro</a>
        </section>

        <section class="tut__section" id="paso-3">
          <div class="tut__step-kicker"><b>3</b> Paso 3 de 7</div>
          <h2>Verifica tu correo y consulta tu estado</h2>
          <p>Revisa la bandeja de entrada del correo que registraste y busca el mensaje con el código de verificación de SONIKA. Si no aparece, revisa spam o correo no deseado.</p>
          <p>Después puedes consultar el estado de tu solicitud con tu correo y los últimos cuatro dígitos de tu documento:</p>
          <a class="tut__link" href="/artista/solicitud">Consultar estado de mi solicitud →</a>
          <div class="tut__callout">La solicitud pasa por verificación de correo, datos y documentos antes de ser aprobada. El estado se actualizará cuando el equipo termine la revisión.</div>
        </section>

        <section class="tut__section" id="paso-4">
          <div class="tut__step-kicker"><b>4</b> Paso 4 de 7</div>
          <h2>Inicia sesión como SONIKA Partners</h2>
          <p>Cuando tengas tu cuenta, entra a <strong>Mi Cuenta SONIKA Partners</strong> con tu correo y contraseña. Por seguridad, SONIKA enviará un código adicional a tu correo para completar el acceso.</p>
          <a class="tut__link tut__link--primary" href="/mi-antro">Iniciar sesión como SONIKA Partners →</a>
          <p>Ese acceso es exclusivo para artistas; no es el mismo inicio de sesión del cliente de la tienda.</p>
        </section>

        <section class="tut__section" id="paso-5">
          <div class="tut__step-kicker"><b>5</b> Paso 5 de 7</div>
          <h2>Completa tu perfil y tu página</h2>
          <p>Desde Mi Cuenta SONIKA Partners podrás preparar la información que verá tu audiencia:</p>
          <ul class="tut__check">
            <li>Foto de perfil y portada.</li>
            <li>Biografía, instrumento, género y trayectoria.</li>
            <li>Galería, video y redes sociales.</li>
            <li>Tu enlace personal de artista.</li>
            <li>Tu catálogo y colecciones para compartir.</li>
          </ul>
          <div class="tut__callout tut__callout--green">Completa los datos con información real. Las imágenes y datos que publiques también estarán sujetos al contrato y a la autorización de uso correspondiente.</div>
        </section>

        <section class="tut__section" id="paso-6">
          <div class="tut__step-kicker"><b>6</b> Paso 6 de 7</div>
          <h2>Firma tu contrato digital</h2>
          <p>Antes de activar tu participación comercial, lee el contrato completo y fírmalo desde tu teléfono o computador. El módulo está en <strong>Mi Cuenta SONIKA Partners → Contrato</strong>.</p>
          <ol>
            <li>Lee todas las condiciones.</li>
            <li>Escribe tu nombre y documento.</li>
            <li>Firma con el dedo en el móvil o con el mouse en el computador.</li>
            <li>Confirma la aceptación y pulsa <strong>Firmar contrato</strong>.</li>
          </ol>
          <a class="tut__link" href="/artista/contrato">Abrir mi contrato →</a>
          <div class="tut__callout">La firma queda registrada con fecha, hash del contrato y evidencia técnica. No compartas tu contraseña ni tu código de verificación con nadie.</div>
        </section>

        <section class="tut__section" id="paso-7">
          <div class="tut__step-kicker"><b>7</b> Paso 7 de 7</div>
          <h2>Activa tu página y empieza a compartir</h2>
          <p>Cuando tu perfil esté aprobado y tu contrato firmado, podrás compartir tu página y tu enlace personal. Tus ventas atribuidas se registrarán en tu panel.</p>
          <ul class="tut__check">
            <li>Comparte tu enlace personal con tu audiencia.</li>
            <li>Revisa clics, ventas y comisiones en Mi Cuenta SONIKA Partners.</li>
            <li>Mantén actualizados tus datos y tus contenidos.</li>
          </ul>
          <div class="tut__callout">Las comisiones se generan solo por ventas válidas y atribuidas, después de devoluciones, cancelaciones y ajustes. No hay ingresos garantizados.</div>
        </section>

        <section class="tut__section" id="preguntas">
          <h2>Preguntas frecuentes</h2>
          <details class="tut__faq"><summary>¿Crear la cuenta tiene algún costo?</summary><p>No. Crear la cuenta y enviar tu solicitud no tiene costo. Las condiciones comerciales y comisiones se explican en el contrato y en las reglas del programa.</p></details>
          <details class="tut__faq"><summary>¿Es un empleo o una relación laboral?</summary><p>No. La participación es independiente y voluntaria. No genera salario, prestaciones ni subordinación laboral.</p></details>
          <details class="tut__faq"><summary>¿La aprobación es automática?</summary><p>No. El equipo revisa la información y los documentos. La cuenta puede quedar pendiente mientras se completa la verificación.</p></details>
          <details class="tut__faq"><summary>¿Puedo firmar el contrato desde el celular?</summary><p>Sí. El módulo de firma acepta el dedo en móviles y el mouse en computadores.</p></details>
          <details class="tut__faq"><summary>¿Cuándo recibo una comisión?</summary><p>Cuando exista una venta válida atribuida a tu enlace y se cumplan las reglas del programa. Las devoluciones, cancelaciones y contracargos pueden ajustar o anular la comisión.</p></details>
          <details class="tut__faq"><summary>¿Dónde consulto mi estado?</summary><p>En <a href="/artista/solicitud">Consulta de estado</a> con tu correo y los últimos cuatro dígitos de tu documento, o dentro de Mi Cuenta SONIKA Partners cuando ya tengas acceso.</p></details>
        </section>

        <div class="tut__final">
          <div><h2>¿Listo para empezar?</h2><p>El registro toma pocos minutos. La revisión y la firma del contrato son pasos separados y necesarios.</p></div>
          <a class="tut__hero-cta" href="<?= $registerUrl ?>">Crear cuenta de artista →</a>
        </div>
        <a class="tut__backtop" href="#antes">↑ Volver al inicio del tutorial</a>
      </div>
    </div>
  </div>
  <script>
    (function () {
      var links = Array.prototype.slice.call(document.querySelectorAll('.tut__nav a[href^="#"]'));
      var sections = links.map(function (link) { return document.getElementById(link.getAttribute('href').slice(1)); }).filter(Boolean);
      if (!links.length || !sections.length) return;
      function activate(id) {
        links.forEach(function (link) {
          var on = link.getAttribute('href') === '#' + id;
          link.classList.toggle('is-active', on);
          if (on) link.setAttribute('aria-current', 'location');
          else link.removeAttribute('aria-current');
        });
      }
      links.forEach(function (link) {
        link.addEventListener('click', function (event) {
          var target = document.getElementById(link.getAttribute('href').slice(1));
          if (!target) return;
          event.preventDefault();
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
          history.replaceState(null, '', link.getAttribute('href'));
          activate(target.id);
        });
      });
      if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
          var visible = entries.filter(function (entry) { return entry.isIntersecting; }).sort(function (a, b) { return b.intersectionRatio - a.intersectionRatio; })[0];
          if (visible) activate(visible.target.id);
        }, { rootMargin: '-18% 0px -68% 0px', threshold: [0, .1, .5] });
        sections.forEach(function (section) { observer.observe(section); });
      }
    })();
  </script>
</section>
