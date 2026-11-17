<?php
$artistImage = '/assets/uploads/_artist/1/m5c1495112665.webp';
$artistImageAlt = 'Artista en escenario';
?>
<section class="asp">
  <style>
    .asp{background:#f7f5f1;color:#161616;padding:0 0 52px}
    .asp *{box-sizing:border-box}
    .asp__wrap{max-width:1120px;margin:0 auto;padding:0 22px}
    .asp__hero{display:grid;grid-template-columns:minmax(0,.95fr) minmax(0,1.05fr);gap:clamp(22px,4vw,48px);align-items:center;padding:clamp(28px,5vw,64px) 0 40px}
    .asp__media{position:relative;min-height:390px;border-radius:22px;overflow:hidden;background:linear-gradient(145deg,#201018,#0b0b0b);box-shadow:0 24px 60px rgba(17,17,17,.16)}
    .asp__media img{display:block;width:100%;height:100%;min-height:390px;object-fit:cover;object-position:center 30%}
    .asp__media:after{content:"";position:absolute;inset:0;background:linear-gradient(0deg,rgba(8,8,8,.72),transparent 52%)}
    .asp__seal{position:absolute;left:18px;bottom:18px;z-index:1;display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.94);color:#111;border-radius:999px;padding:7px 11px;font-size:11px;font-weight:850;letter-spacing:.03em}
    .asp__seal b{color:#F20D16}
    .asp__copy{padding:8px 0}
    .asp__eyebrow{display:inline-flex;align-items:center;gap:8px;color:#F20D16;font-size:11px;font-weight:850;letter-spacing:.14em;text-transform:uppercase}
    .asp__eyebrow:before{content:"";width:24px;height:2px;background:#F20D16}
    .asp h1{font:850 clamp(2rem,4.8vw,3.7rem)/1.02 Sora,Inter,system-ui,sans-serif;letter-spacing:-.045em;margin:15px 0 14px;max-width:13ch}
    .asp h1 em{font-style:normal;color:#F20D16}
    .asp__lead{font-size:clamp(14px,1.7vw,17px);line-height:1.65;color:#555;max-width:55ch;margin:0}
    .asp__actions{display:flex;gap:9px;flex-wrap:wrap;align-items:center;margin-top:24px}
    .asp__btn{display:inline-flex;align-items:center;justify-content:center;min-height:46px;border-radius:12px;padding:12px 17px;font-size:13px;font-weight:850;text-decoration:none;transition:transform .18s ease,box-shadow .18s ease,background .18s ease}
    .asp__btn:hover,.asp__btn:focus-visible{transform:translateY(-1px);outline:none}
    .asp__btn--primary{background:#F20D16;color:#fff;box-shadow:0 10px 24px rgba(242,13,22,.22)}
    .asp__btn--secondary{background:#fff;color:#161616;border:1px solid #ddd8d0}
    .asp__micro{font-size:11px;color:#777;line-height:1.5;margin:14px 0 0;max-width:52ch}
    .asp__benefits{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:8px 0 38px}
    .asp__benefit{background:#fff;border:1px solid #e5e0d8;border-radius:15px;padding:16px;min-height:126px}
    .asp__num{display:grid;place-items:center;width:27px;height:27px;border-radius:8px;background:#fbe9e9;color:#F20D16;font-size:11px;font-weight:900}
    .asp__benefit b{display:block;font-size:14px;line-height:1.3;margin-top:10px;color:#191919}
    .asp__benefit span{display:block;font-size:12px;line-height:1.5;color:#666;margin-top:5px}
    .asp__cta{display:flex;align-items:center;justify-content:space-between;gap:22px;flex-wrap:wrap;background:#F20D16;color:#fff;border-radius:22px;padding:clamp(22px,4vw,32px);margin:0 0 38px;box-shadow:0 18px 42px rgba(242,13,22,.18)}
    .asp__cta h2{font:800 clamp(1.4rem,2.6vw,2.15rem)/1.08 Sora,Inter,system-ui,sans-serif;letter-spacing:-.03em;margin:8px 0 8px;max-width:18ch}
    .asp__cta p{font-size:12.5px;line-height:1.6;color:rgba(255,255,255,.88);margin:0;max-width:58ch}
    .asp__cta__legal{font-size:10.5px!important;color:rgba(255,255,255,.72)!important;margin-top:10px!important;max-width:62ch!important}
    .asp__cta__status{display:inline-flex;margin-top:10px;color:#fff;font-size:11.5px;font-weight:850;text-decoration:none;border-bottom:1px solid rgba(255,255,255,.45);padding-bottom:2px}
    .asp__cta__button{display:inline-flex;align-items:center;justify-content:center;gap:12px;flex:0 0 auto;min-height:52px;border-radius:13px;padding:14px 19px;background:#fff;color:#161616;font-size:13px;font-weight:900;text-decoration:none;box-shadow:0 12px 26px rgba(0,0,0,.16);transition:transform .18s ease,box-shadow .18s ease}
    .asp__cta__button span{font-size:18px;line-height:1;color:#F20D16}
    .asp__cta__button:hover,.asp__cta__button:focus-visible{transform:translateY(-2px);box-shadow:0 16px 30px rgba(0,0,0,.2);outline:none}
    .asp__middle{display:grid;grid-template-columns:minmax(0,.75fr) minmax(0,1.25fr);gap:28px;align-items:start;background:#111;color:#fff;border-radius:22px;padding:clamp(22px,4vw,34px);margin-bottom:38px}
    .asp__middle .asp__eyebrow{color:#ff8a8d}
    .asp__middle .asp__eyebrow:before{background:#ff8a8d}
    .asp__middle h2{font:800 clamp(1.45rem,2.8vw,2.2rem)/1.08 Sora,Inter,system-ui,sans-serif;letter-spacing:-.03em;margin:12px 0 8px}
    .asp__middle p{font-size:13px;line-height:1.65;color:#c8c8c8;margin:0}
    .asp__steps{display:grid;gap:10px}
    .asp__step{display:grid;grid-template-columns:28px minmax(0,1fr);gap:10px;align-items:start;border-top:1px solid rgba(255,255,255,.13);padding-top:10px}
    .asp__step:first-child{border-top:0;padding-top:0}
    .asp__step b{display:grid;place-items:center;width:24px;height:24px;border-radius:50%;background:#fff;color:#111;font-size:11px}
    .asp__step strong{display:block;font-size:13px;color:#fff}
    .asp__step span{display:block;font-size:11.5px;line-height:1.5;color:#aaa;margin-top:2px}
    .asp__register{display:grid;grid-template-columns:minmax(0,.8fr) minmax(0,1.2fr);gap:28px;align-items:start;background:#fff;border:1px solid #e5e0d8;border-radius:22px;padding:clamp(22px,4vw,34px);scroll-margin-top:24px}
    .asp__register h2{font:800 clamp(1.5rem,2.7vw,2.1rem)/1.08 Sora,Inter,system-ui,sans-serif;letter-spacing:-.03em;margin:0 0 9px}
    .asp__register p{font-size:13px;line-height:1.6;color:#666;margin:0}
    .asp__loginlink{display:inline-flex;margin-top:16px;color:#F20D16;font-size:12.5px;font-weight:850;text-decoration:none;border-bottom:1px solid rgba(242,13,22,.35);padding-bottom:2px}
    .asp__form{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .asp__form label{display:grid;gap:5px;font-size:10.5px;font-weight:800;color:#555;letter-spacing:.02em;text-transform:uppercase}
    .asp__form .full{grid-column:1/-1}
    .asp__form input,.asp__form select{width:100%;min-height:43px;border:1px solid #d9d4cc;border-radius:10px;padding:10px 11px;background:#fff;color:#161616;font:inherit;font-size:13px;text-transform:none;letter-spacing:0}
    .asp__form input:focus,.asp__form select:focus{outline:2px solid rgba(242,13,22,.25);border-color:#F20D16}
    .asp__accept{grid-column:1/-1;display:flex;gap:8px;align-items:flex-start;font-size:11.5px;line-height:1.5;color:#666;text-transform:none;letter-spacing:0;font-weight:500}
    .asp__accept input{min-height:0;width:16px;height:16px;margin-top:2px}
    .asp__submit{grid-column:1/-1;min-height:46px;border:0;border-radius:11px;background:#111;color:#fff;font-weight:850;font-size:13px;cursor:pointer}
    .asp__submit:hover{background:#2a2a2a}
    .asp__msg{grid-column:1/-1;font-size:12.5px;line-height:1.5;min-height:18px;font-weight:700}
    .asp__note{font-size:10.5px;color:#888;line-height:1.5;margin:16px 0 0}
    @media(max-width:900px){.asp__hero{grid-template-columns:1fr;padding-top:24px}.asp__media{min-height:310px}.asp__media img{min-height:310px}.asp__copy{order:-1}.asp__benefits{grid-template-columns:repeat(2,minmax(0,1fr))}.asp__cta{display:grid;grid-template-columns:1fr}.asp__cta__button{width:100%}.asp__middle,.asp__register{grid-template-columns:1fr}}
    @media(max-width:560px){.asp__wrap{padding:0 16px}.asp__hero{gap:18px}.asp__media{min-height:250px;border-radius:16px}.asp__media img{min-height:250px}.asp__benefits{grid-template-columns:1fr}.asp__cta{padding:20px;border-radius:16px}.asp__cta__button{min-height:50px;font-size:12.5px}.asp__middle,.asp__register{padding:20px;border-radius:16px}.asp__form{grid-template-columns:1fr}.asp__form .full{grid-column:auto}.asp__actions{display:grid}.asp__btn{width:100%}}
  </style>
  <div class="asp__wrap">
    <div class="asp__hero">
      <div class="asp__media">
        <img src="<?= $artistImage ?>" alt="<?= htmlspecialchars($artistImageAlt, ENT_QUOTES, 'UTF-8') ?>" loading="eager" fetchpriority="high" />
        <span class="asp__seal"><b>●</b> Programa oficial de artistas</span>
      </div>
      <div class="asp__copy">
        <span class="asp__eyebrow">SONIKA Partners</span>
        <h1>Tu música, tu catálogo y tu página. <em>Tu negocio.</em></h1>
        <p class="asp__lead">Únete al programa comercial de SONIKA y convierte tu influencia musical en una vitrina propia. Recomienda el catálogo oficial, recibe comisiones por ventas y haz que más personas te encuentren y te contraten.</p>
        <div class="asp__actions">
          <a class="asp__btn asp__btn--primary" href="/mi-antro?artista=1">Iniciar sesión como SONIKA Partners</a>
          <a class="asp__btn asp__btn--secondary" href="#registro">Crear cuenta de artista</a>
          <a class="asp__btn asp__btn--secondary" href="/artistas/tutorial">Ver tutorial paso a paso</a>
        </div>
        <p class="asp__micro">Acceso exclusivo para artistas del programa. Si aún no tienes cuenta, completa la solicitud y revisaremos tu perfil.</p>
      </div>
    </div>

    <div class="asp__benefits" aria-label="Beneficios del programa">
      <div class="asp__benefit"><span class="asp__num">01</span><b>Catálogo personal</b><span>Una selección de productos para recomendar desde tu propia página.</span></div>
      <div class="asp__benefit"><span class="asp__num">02</span><b>Link personal</b><span>Comparte un enlace propio y conecta a tu audiencia con SONIKA.</span></div>
      <div class="asp__benefit"><span class="asp__num">03</span><b>Página de artista</b><span>Tu historia, fotos, música y enlaces en una página pública.</span></div>
      <div class="asp__benefit"><span class="asp__num">04</span><b>Comisiones por ventas</b><span>Participa en los ingresos de cada producto vendido con tu enlace.</span></div>
      <div class="asp__benefit"><span class="asp__num">05</span><b>Material multimedia</b><span>Recursos visuales para tus publicaciones y contenido promocional.</span></div>
      <div class="asp__benefit"><span class="asp__num">06</span><b>Directorio Sonika</b><span>Aparece como artista para que clientes y organizadores puedan encontrarte y contratarte.</span></div>
    </div>

    <div class="asp__cta">
      <div>
        <span class="asp__eyebrow" style="color:#fff">Ya haces parte</span>
        <h2>Tu panel de artista está listo.</h2>
        <p>Entra a Mi Cuenta SONIKA Partners para revisar tu catálogo, tu link personal, tus comisiones y el estado de tu perfil.</p>
        <p class="asp__cta__legal">Programa en habilitación. Condiciones, comisiones y disponibilidad sujetas a los términos vigentes. No se garantizan ingresos.</p>
        <a class="asp__cta__status" href="/artista/solicitud">Consultar estado de mi solicitud</a>
      </div>
      <a class="asp__cta__button" href="/mi-antro?artista=1">Ya soy artista: iniciar sesión como SONIKA Partners <span aria-hidden="true">→</span></a>
    </div>

    <div class="asp__middle">
      <div>
        <span class="asp__eyebrow">Cómo funciona</span>
        <h2>Una solicitud. Un panel. Tu vitrina.</h2>
        <p>El programa está diseñado para que artistas y músicos puedan construir una fuente adicional de ingresos sin inventar precios ni prometer lo que no existe.</p>
      </div>
      <div class="asp__steps">
        <div class="asp__step"><b>1</b><span><strong>Envía tu solicitud</strong><span>Cuéntanos quién eres, qué haces y qué instrumentos te representan.</span></span></div>
        <div class="asp__step"><b>2</b><span><strong>Verificamos tu perfil</strong><span>Revisamos tu información y documentos. La aprobación no es automática.</span></span></div>
        <div class="asp__step"><b>3</b><span><strong>Activa tu página y tu link</strong><span>Accede a Mi Cuenta SONIKA Partners, personaliza tu catálogo y comparte tu enlace personal.</span></span></div>
      </div>
    </div>

    <div class="asp__register" id="registro">
      <div>
        <span class="asp__eyebrow">Solicitud de ingreso</span>
        <h2>Haz parte de SONIKA Partners.</h2>
        <p>Completa tus datos. Tu cuenta queda en revisión antes de publicar tu página y activar comisiones.</p>
        <p class="asp__note">Programa en habilitación. Condiciones, comisiones y disponibilidad sujetas a los términos vigentes. No se garantizan ingresos.</p>
        <p style="margin-top:12px"><a href="/artista/solicitud" style="font-size:11.5px;color:#777">Consultar estado de mi solicitud</a></p>
      </div>
      <form class="asp__form" id="artist-register" novalidate>
        <label>Nombre completo<input name="full_name" autocomplete="name" required /></label>
        <label>Correo electrónico<input name="email" type="email" autocomplete="email" required /></label>
        <label>Nombre artístico<input name="artist_name" autocomplete="nickname" /></label>
        <label>Nombre público de tienda<input name="public_name" /></label>
        <label>Tipo de documento<select name="doc_type"><option value="">Selecciona</option><option>CC</option><option>CE</option><option>NIT</option><option>Pasaporte</option></select></label>
        <label>Número de documento<input name="doc_number" inputmode="numeric" /></label>
        <label>Instrumento principal<select name="music_instrument"><option value="">Selecciona</option><option>Guitarra eléctrica</option><option>Guitarra acústica</option><option>Bajo</option><option>Batería</option><option>Teclados</option><option>Voz</option><option>Percusión</option><option>Otro</option></select></label>
        <label>Nivel<select name="music_level"><option value="">Selecciona</option><option>Principiante</option><option>Intermedio</option><option>Avanzado</option><option>Profesional</option></select></label>
        <label>Género musical<input name="music_genre" placeholder="Ej. rock, vallenato, jazz" /></label>
        <label>Contraseña<input name="password" type="password" minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres" required /></label>
        <label class="asp__accept"><input type="checkbox" name="accept_contract" value="1" required /> Acepto los <a href="/terminos-y-condiciones" target="_blank" rel="noopener">términos del programa</a> y la <a href="/politica-de-privacidad" target="_blank" rel="noopener">política de datos</a>.</label>
        <button class="asp__submit" type="submit">Enviar solicitud</button>
        <div class="asp__msg" id="artist-register-msg" role="status" aria-live="polite"></div>
      </form>
    </div>
  </div>
  <script>
    (function () {
      var form = document.getElementById('artist-register');
      var msg = document.getElementById('artist-register-msg');
      if (!form || !msg) return;
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!form.checkValidity()) { form.reportValidity(); return; }
        var button = form.querySelector('button[type=submit]');
        var data = {};
        new FormData(form).forEach(function (value, key) { if (value) data[key] = value; });
        button.disabled = true;
        button.textContent = 'Enviando solicitud...';
        msg.style.color = '#51607a';
        msg.textContent = 'Estamos registrando tu solicitud.';
        fetch('/api/v1/public/affiliates/register', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(data)
        }).then(function (response) { return response.json(); }).then(function (result) {
          button.disabled = false;
          button.textContent = 'Enviar solicitud';
          if (result && result.ok) {
            msg.style.color = '#0d7a58';
            msg.innerHTML = 'Solicitud recibida. Revisaremos tu perfil. <a href="/mi-antro?next=contrato" style="color:#0d7a58;font-weight:800">Inicia sesión y firma tu contrato</a>.';
            form.reset();
          } else {
            msg.style.color = '#b42318';
            msg.textContent = (result && result.message) || 'No se pudo enviar la solicitud.';
          }
        }).catch(function () {
          button.disabled = false;
          button.textContent = 'Enviar solicitud';
          msg.style.color = '#b42318';
          msg.textContent = 'Error de conexión. Intenta de nuevo.';
        });
      });
    })();
  </script>
</section>
