<?php
$__contract = (new \App\Services\ArtistContractService())->current();
$__contractUrl = '/sonika-partners/contrato';
?>
<section class="sp">
  <style>
    .sp{background:#f7f5f1;color:#161616;padding:0 0 56px}.sp *{box-sizing:border-box}
    .sp__wrap{max-width:1140px;margin:0 auto;padding:0 22px}
    .sp__hero{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);gap:clamp(22px,4vw,48px);align-items:center;padding:clamp(28px,5vw,66px) 0 38px}
    .sp__eyebrow{display:inline-flex;align-items:center;gap:8px;color:#F20D16;font-size:11px;font-weight:850;letter-spacing:.14em;text-transform:uppercase}
    .sp__eyebrow:before{content:"";width:24px;height:2px;background:#F20D16}
    .sp h1{font:850 clamp(2.1rem,5vw,4rem)/1.02 Sora,Inter,system-ui,sans-serif;letter-spacing:-.05em;margin:15px 0 14px;max-width:12ch}
    .sp h1 em{font-style:normal;color:#F20D16}
    .sp__lead{font-size:clamp(14px,1.7vw,17px);line-height:1.65;color:#555;max-width:56ch;margin:0}
    .sp__actions{display:flex;gap:9px;flex-wrap:wrap;align-items:center;margin-top:24px}
    .sp__btn{display:inline-flex;align-items:center;justify-content:center;min-height:46px;border-radius:12px;padding:12px 17px;font-size:13px;font-weight:850;text-decoration:none;transition:transform .18s ease,box-shadow .18s ease,background .18s ease}
    .sp__btn:hover,.sp__btn:focus-visible{transform:translateY(-1px);outline:none}
    .sp__btn--primary{background:#F20D16;color:#fff;box-shadow:0 10px 24px rgba(242,13,22,.22)}
    .sp__btn--secondary{background:#fff;color:#161616;border:1px solid #ddd8d0}
    .sp__micro{font-size:11px;color:#777;line-height:1.5;margin:14px 0 0;max-width:54ch}
    .sp__card{background:linear-gradient(145deg,#17110e,#0b0b0b);border-radius:22px;padding:clamp(20px,3vw,28px);color:#fff;box-shadow:0 24px 60px rgba(17,17,17,.18);position:relative;overflow:hidden}
    .sp__card:after{content:"SONIKA";position:absolute;right:-20px;bottom:-30px;font:900 110px/1 Sora,Inter,sans-serif;color:rgba(255,255,255,.04);letter-spacing:-.08em}
    .sp__card h2{font:800 22px/1.15 Sora,Inter,sans-serif;margin:0 0 6px;letter-spacing:-.03em}
    .sp__card p{font-size:12.5px;color:#c9c9c9;line-height:1.65;margin:0}
    .sp__calc{display:grid;gap:8px;margin-top:16px;position:relative;z-index:1}
    .sp__calc label{font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#aaa}
    .sp__calc input{width:100%;min-height:44px;border:1px solid rgba(255,255,255,.2);border-radius:10px;background:rgba(255,255,255,.06);color:#fff;padding:10px 12px;font:inherit;font-size:15px;font-weight:700}
    .sp__calc input:focus{outline:2px solid rgba(242,13,22,.45);border-color:#F20D16}
    .sp__calc-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .sp__result{margin-top:8px;padding:12px;border-radius:12px;background:rgba(242,13,22,.12);border:1px solid rgba(242,13,22,.25)}
    .sp__result b{display:block;font-size:26px;line-height:1.1;color:#fff}.sp__result span{display:block;font-size:10.5px;color:#d3d3d3;margin-top:3px;line-height:1.4}
    .sp__result small{display:block;font-size:10px;color:#aaa;margin-top:7px}
    .sp__benefits{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:8px 0 38px}
    .sp__benefit{background:#fff;border:1px solid #e5e0d8;border-radius:15px;padding:16px;min-height:126px}
    .sp__num{display:grid;place-items:center;width:27px;height:27px;border-radius:8px;background:#fbe9e9;color:#F20D16;font-size:11px;font-weight:900}
    .sp__benefit b{display:block;font-size:14px;line-height:1.3;margin-top:10px;color:#191919}
    .sp__benefit span{display:block;font-size:12px;line-height:1.5;color:#666;margin-top:5px}
    .sp__section{padding:0 0 38px}.sp__section h2{font:800 clamp(1.5rem,3vw,2.3rem)/1.08 Sora,Inter,sans-serif;letter-spacing:-.04em;margin:0 0 10px;max-width:18ch}
    .sp__section p{font-size:13px;line-height:1.65;color:#666;max-width:72ch;margin:0}
    .sp__formula{display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);gap:22px;align-items:start;background:#fff;border:1px solid #e5e0d8;border-radius:22px;padding:clamp(20px,4vw,32px)}
    .sp__formula-box{background:#111;color:#fff;border-radius:16px;padding:20px;position:relative;overflow:hidden}
    .sp__formula-box:after{content:"+";position:absolute;right:14px;top:8px;font:900 80px/1 Sora,Inter,sans-serif;color:rgba(255,255,255,.08)}
    .sp__formula-box b{display:block;font-size:13px;letter-spacing:.06em;text-transform:uppercase;color:#ff8a8d;margin-bottom:8px}
    .sp__formula-box strong{display:block;font:800 clamp(1.1rem,2.4vw,1.5rem)/1.25 Sora,Inter,sans-serif;letter-spacing:-.02em}
    .sp__table{width:100%;border-collapse:collapse;font-size:13px}
    .sp__table th{text-align:left;font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:#777;padding:8px 10px;border-bottom:1px solid #e5e0d8}
    .sp__table td{padding:10px;border-bottom:1px solid #f0ece5;vertical-align:top}
    .sp__table tr:last-child td{border-bottom:0}.sp__table b{font-size:15px}
    .sp__pill{display:inline-block;border-radius:999px;padding:3px 8px;font-size:10px;font-weight:800;background:#fbe9e9;color:#B42318}
    .sp__examples{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:8px}
    .sp__examples-title{font-size:10.5px;font-weight:900;letter-spacing:.10em;text-transform:uppercase;color:#8a641e;margin:16px 0 0}
    .sp__example{position:relative;border:1px solid #e5e0d8;border-radius:14px;padding:14px;background:linear-gradient(160deg,#fff 0%,#fffaf7 100%);font-size:12px;line-height:1.5;overflow:hidden;transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease}
    .sp__example:hover{transform:translateY(-2px);border-color:#f3c4c7;box-shadow:0 12px 26px rgba(17,17,17,.08)}
    .sp__example:after{content:"";position:absolute;right:-26px;top:-26px;width:86px;height:86px;border-radius:50%;background:radial-gradient(circle,rgba(242,13,22,.12),transparent 70%);pointer-events:none}
    .sp__example-kicker{display:inline-block;border-radius:999px;padding:3px 8px;margin-bottom:7px;background:#fbe9e9;color:#B42318;font-size:9.5px;font-weight:900;letter-spacing:.10em;text-transform:uppercase}
    .sp__example b{position:relative;display:block;font:900 clamp(1.45rem,3vw,1.95rem)/1 Sora,Inter,sans-serif;letter-spacing:-.045em;color:#111;margin:0 0 4px}
    .sp__example-title{position:relative;display:block;font-size:12px;font-weight:800;color:#333;line-height:1.35}
    .sp__example-meta{position:relative;display:block;font-size:10.5px;color:#777;line-height:1.45;margin-top:5px}
    .sp__example-bar{position:relative;display:flex;height:5px;border-radius:999px;background:#f0ece5;overflow:hidden;margin-top:10px}
    .sp__example-bar span{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,#F20D16,#ff6b71)}
    .sp__examples-note{grid-column:1/-1;font-size:10.5px;color:#888;line-height:1.45;margin:2px 0 0}
    .sp__profiles{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:14px}
    .sp__profile{border:1px solid #e5e0d8;border-radius:15px;padding:15px;background:#fff}
    .sp__profile b{display:block;font-size:14px}.sp__profile span{display:block;font-size:11.5px;line-height:1.55;color:#666;margin-top:4px}
    .sp__profile em{display:inline-block;font-style:normal;font-size:9.5px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#8a641e;background:#fbf1d7;border-radius:99px;padding:3px 7px;margin-bottom:8px}
    .sp__register{display:grid;grid-template-columns:minmax(0,.8fr) minmax(0,1.2fr);gap:28px;align-items:start;background:#fff;border:1px solid #e5e0d8;border-radius:22px;padding:clamp(22px,4vw,34px);scroll-margin-top:24px}
    .sp__register h2{font:800 clamp(1.5rem,2.7vw,2.1rem)/1.08 Sora,Inter,sans-serif;letter-spacing:-.03em;margin:0 0 9px}
    .sp__register p{font-size:13px;line-height:1.6;color:#666;margin:0}
    .sp__form{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .sp__form label{display:grid;gap:5px;font-size:10.5px;font-weight:800;color:#555;letter-spacing:.02em;text-transform:uppercase}
    .sp__form .full{grid-column:1/-1}.sp__form input,.sp__form select{width:100%;min-height:43px;border:1px solid #d9d4cc;border-radius:10px;padding:10px 11px;background:#fff;color:#161616;font:inherit;font-size:13px;text-transform:none;letter-spacing:0}
    .sp__form input:focus,.sp__form select:focus{outline:2px solid rgba(242,13,22,.25);border-color:#F20D16}
    .sp__accept{grid-column:1/-1;display:flex;gap:8px;align-items:flex-start;font-size:11.5px;line-height:1.5;color:#666;text-transform:none;letter-spacing:0;font-weight:500}
    .sp__accept input{min-height:0;width:16px;height:16px;margin-top:2px}
    .sp__submit{grid-column:1/-1;min-height:46px;border:0;border-radius:11px;background:#111;color:#fff;font-weight:850;font-size:13px;cursor:pointer}
    .sp__submit:hover{background:#2a2a2a}.sp__msg{grid-column:1/-1;font-size:12.5px;line-height:1.5;min-height:18px;font-weight:700}
    .sp__note{font-size:10.5px;color:#888;line-height:1.5;margin:16px 0 0}
    .sp__category{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:8px 0 12px}
    .sp__cat{border:1px solid #d9d4cc;border-radius:11px;padding:10px;background:#fff;cursor:pointer;text-align:left;font:inherit}
    .sp__cat b{display:block;font-size:11.5px;color:#161616}.sp__cat span{display:block;font-size:10px;color:#777;margin-top:2px}
    .sp__cat.on{border-color:#F20D16;background:#fff7f7;box-shadow:0 0 0 2px rgba(242,13,22,.08)}
    .sp__group{display:contents}.sp__group[hidden]{display:none}
    .sp__faq{display:grid;gap:8px;margin-top:12px}.sp__faq details{background:#fff;border:1px solid #e5e0d8;border-radius:12px;padding:12px 14px}.sp__faq summary{font-size:12.5px;font-weight:800;cursor:pointer}.sp__faq p{font-size:12px;color:#666;line-height:1.6;margin:8px 0 0}
    @media(max-width:900px){.sp__hero{grid-template-columns:1fr;padding-top:24px}.sp__card{order:-1}.sp__benefits{grid-template-columns:repeat(2,minmax(0,1fr))}.sp__formula,.sp__register{grid-template-columns:1fr}.sp__profiles{grid-template-columns:1fr}}
    @media(max-width:560px){.sp__wrap{padding:0 16px}.sp__benefits{grid-template-columns:1fr}.sp__examples{grid-template-columns:1fr}.sp__form{grid-template-columns:1fr}.sp__form .full{grid-column:auto}.sp__actions{display:grid}.sp__btn{width:100%}.sp__category{grid-template-columns:1fr}.sp__calc-row{grid-template-columns:1fr}}
  </style>
  <div class="sp__wrap">
    <section class="sp__hero">
      <div>
        <span class="sp__eyebrow">SONIKA Partners</span>
        <h1>Comparte. Vende. <em>Gana.</em></h1>
        <p class="sp__lead">Únete a <strong>SONIKA Partners</strong> y gana comisiones por compartir instrumentos, audio, iluminación y accesorios de SONIKA. No importa si eres artista, creador de contenido o persona natural: el programa es el mismo.</p>
        <div class="sp__actions">
          <a class="sp__btn sp__btn--primary" href="#registro">Quiero ser Partner SONIKA</a>
          <a class="sp__btn sp__btn--secondary" href="#comisiones">Ver comisiones</a>
          <a class="sp__btn sp__btn--secondary" href="/mi-antro?next=contrato">Ya soy Partner: entrar</a>
        </div>
        <p class="sp__micro">Programa de distribuidores. Comisiones por ventas válidas. Techo máximo 5 %. No se garantizan ingresos. La categoría interna no cambia tu comisión ni el contrato.</p>
      </div>
      <div class="sp__card" aria-label="Calculadora rápida de comisiones">
        <h2>Calcula tu comisión</h2>
        <p>Mueve tus ventas y pedidos del mes. La fórmula premia valor y volumen.</p>
        <div class="sp__calc">
          <div class="sp__calc-row">
            <div><label for="sp-sales">Ventas del mes (COP)</label><input id="sp-sales" type="number" min="0" step="10000" value="3000000" inputmode="numeric"></div>
            <div><label for="sp-orders">Pedidos válidos</label><input id="sp-orders" type="number" min="0" step="1" value="30" inputmode="numeric"></div>
          </div>
          <div class="sp__result" aria-live="polite"><b id="sp-commission">$135.000</b><span id="sp-rate">4,5 % · 60 puntos</span><small id="sp-next">Con 60 puntos estás en 4,5 %. Con 120 puntos llegas al 5,0 %.</small></div>
        </div>
      </div>
    </section>

    <section class="sp__benefits" aria-label="Beneficios del programa">
      <div class="sp__benefit"><span class="sp__num">01</span><b>Un solo programa</b><span>Artistas, influencers y personas naturales entran con las mismas reglas y la misma comisión.</span></div>
      <div class="sp__benefit"><span class="sp__num">02</span><b>Link y QR propios</b><span>Comparte tu enlace, tu código y tu QR. Cada venta válida se atribuye a tu cuenta.</span></div>
      <div class="sp__benefit"><span class="sp__num">03</span><b>Puntos SONIKA</b><span>Cada $100.000 vendidos y cada pedido válido suman puntos. Más puntos, mejor porcentaje.</span></div>
      <div class="sp__benefit"><span class="sp__num">04</span><b>Hasta 5 %</b><span>La comisión total nunca supera el 5 % de la venta neta elegible.</span></div>
      <div class="sp__benefit"><span class="sp__num">05</span><b>Panel y calculadora</b><span>Consulta clics, ventas, comisión estimada, estado de pago y cuánto te falta para subir.</span></div>
      <div class="sp__benefit"><span class="sp__num">06</span><b>Herramientas según tu perfil</b><span>Tu categoría interna activa módulos opcionales: página de artista, media kit o kit simple para compartir.</span></div>
    </section>

    <section class="sp__section" id="comisiones">
      <h2>Así se calcula tu comisión</h2>
      <p>No premiamos solo el ticket alto ni solo el número de pedidos. Premiamos las dos cosas. Cada peso vendido y cada pedido válido suman puntos.</p>
      <div class="sp__formula" style="margin-top:14px">
        <div class="sp__formula-box"><b>Fórmula</b><strong>Puntos = (Ventas netas / $100.000) + Pedidos válidos</strong><p style="margin-top:10px;color:#c9c9c9;font-size:12px;line-height:1.6">Se toman las unidades completas de $100.000. Cada pedido válido suma 1 punto. La tasa se recalcula al final del mes y se aplica a todas las ventas del mes.</p></div>
        <div>
          <table class="sp__table"><thead><tr><th>Puntos del mes</th><th>Comisión</th></tr></thead><tbody>
            <tr><td>0 – 9</td><td><b>3,0 %</b></td></tr>
            <tr><td>10 – 29</td><td><b>3,5 %</b></td></tr>
            <tr><td>30 – 59</td><td><b>4,0 %</b></td></tr>
            <tr><td>60 – 119</td><td><b>4,5 %</b></td></tr>
            <tr><td>120 o más</td><td><span class="sp__pill">Techo 5,0 %</span></td></tr>
          </tbody></table>
          <p class="sp__examples-title">Ejemplos de un mes real</p>
          <div class="sp__examples">
            <div class="sp__example"><span class="sp__example-kicker">Primer mes</span><b>$72.000</b><span class="sp__example-title">12 pedidos de $150.000</span><span class="sp__example-meta">$1.800.000 vendidos · 30 pts · 4,0 %</span><span class="sp__example-bar"><span style="width:80%"></span></span></div>
            <div class="sp__example"><span class="sp__example-kicker">Comunidad activa</span><b>$162.000</b><span class="sp__example-title">40 pedidos de $90.000</span><span class="sp__example-meta">$3.600.000 vendidos · 76 pts · 4,5 %</span><span class="sp__example-bar"><span style="width:90%"></span></span></div>
            <div class="sp__example"><span class="sp__example-kicker">Ticket alto</span><b>$270.000</b><span class="sp__example-title">5 pedidos de $1.200.000</span><span class="sp__example-meta">$6.000.000 vendidos · 65 pts · 4,5 %</span><span class="sp__example-bar"><span style="width:90%"></span></span></div>
            <div class="sp__example"><span class="sp__example-kicker">Top Partner</span><b>$600.000</b><span class="sp__example-title">60 pedidos de $200.000</span><span class="sp__example-meta">$12.000.000 vendidos · 180 pts · 5,0 %</span><span class="sp__example-bar"><span style="width:100%"></span></span></div>
            <p class="sp__examples-note">Ejemplos ilustrativos con ventas netas elegibles. No son una promesa de ingresos.</p>
          </div>
        </div>
      </div>
      <p class="sp__note">Pedido válido: pagado, no devuelto, no cancelado, no interno, no fragmentado, con ticket neto mínimo de $1.000 COP. La venta neta excluye IVA, impuestos, envío, gift cards, financiación, descuentos, devoluciones y contracargos. Outlet y productos de margen inferior al 15 %: 2 % fijo. Servicios: máximo 5 %. La comisión total nunca supera el 5 %.</p>
    </section>

    <section class="sp__section">
      <h2>Un programa, tres formas de vivirlo</h2>
      <p>Tu categoría es interna. Solo personaliza tu experiencia y las herramientas que te sugerimos. No cambia la comisión, el contrato ni tus derechos.</p>
      <div class="sp__profiles">
        <div class="sp__profile"><em>Artista</em><b>Músico, cantante, instrumentista</b><span>Página de artista, repertorio, eventos, booking y Directorio Sonika. Misma comisión.</span></div>
        <div class="sp__profile"><em>Influencer</em><b>Creador de contenido</b><span>Creator page, media kit, campañas, product seeding y calendario. Misma comisión.</span></div>
        <div class="sp__profile"><em>Persona natural</em><b>Fan, cliente, embajador</b><span>Link, QR y kit simple para compartir por WhatsApp. Sin página pública. Misma comisión.</span></div>
      </div>
    </section>

    <section class="sp__register" id="registro">
      <div>
        <span class="sp__eyebrow">Solicitud de ingreso</span>
        <h2>Únete a SONIKA Partners.</h2>
        <p>Completa tus datos. Todos entran al mismo programa, con la misma fórmula y el mismo contrato. La aprobación no es automática: revisamos tu perfil.</p>
        <p class="sp__note">Programa en habilitación. Comisiones y condiciones sujetas al contrato vigente. No se garantizan ingresos.</p>
        <p style="margin-top:12px"><a href="/artista/solicitud" style="font-size:11.5px;color:#777">Consultar estado de mi solicitud</a> · <a href="<?= htmlspecialchars($__contractUrl, ENT_QUOTES, 'UTF-8') ?>" style="font-size:11.5px;color:#777">Leer contrato</a></p>
      </div>
      <form class="sp__form" id="partner-register" novalidate>
        <div class="full">
          <label>¿Cómo te describes? <span style="font-weight:500;text-transform:none;color:#888">(categoría interna, no cambia tu comisión)</span></label>
          <div class="sp__category" id="sp-category">
            <button type="button" class="sp__cat on" data-cat="artist"><b>Artista</b><span>Músico, cantante, instrumentista</span></button>
            <button type="button" class="sp__cat" data-cat="influencer"><b>Influencer</b><span>Creador de contenido</span></button>
            <button type="button" class="sp__cat" data-cat="natural"><b>Persona natural</b><span>Fan, cliente, embajador</span></button>
          </div>
          <input type="hidden" name="profile_category" id="sp-profile-category" value="artist">
        </div>
        <label>Nombre completo<input name="full_name" autocomplete="name" required></label>
        <label>Correo electrónico<input name="email" type="email" autocomplete="email" required></label>
        <label>Nombre público<input name="public_name" placeholder="Como quieres aparecer"></label>
        <label id="sp-artist-name-label">Nombre artístico<input name="artist_name" autocomplete="nickname"></label>
        <label>Tipo de documento<select name="doc_type"><option value="">Selecciona</option><option>CC</option><option>CE</option><option>NIT</option><option>Pasaporte</option></select></label>
        <label>Número de documento<input name="doc_number" inputmode="numeric"></label>
        <div class="sp__group full" data-group="artist">
          <label>Instrumento principal<select name="music_instrument"><option value="">Selecciona</option><option>Guitarra eléctrica</option><option>Guitarra acústica</option><option>Bajo</option><option>Batería</option><option>Teclados</option><option>Voz</option><option>Percusión</option><option>Otro</option></select></label>
          <label>Género musical<input name="music_genre" placeholder="Ej. rock, vallenato, jazz"></label>
        </div>
        <div class="sp__group full" data-group="influencer">
          <label>Nicho o categoría<input name="niche" placeholder="Ej. música, tecnología, lifestyle"></label>
          <label>Audiencia aproximada<input name="audience_size" placeholder="Ej. 10.000 seguidores"></label>
          <label>Plataformas<input name="platforms" placeholder="Instagram, TikTok, YouTube"></label>
        </div>
        <div class="sp__group full" data-group="natural">
          <label>¿Cómo vas a compartir?<input name="niche" placeholder="WhatsApp, grupos, clientes, familia"></label>
        </div>
        <label>Instagram<input name="social_instagram" placeholder="@usuario"></label>
        <label>YouTube / TikTok<input name="social_video" placeholder="Canal o @usuario"></label>
        <label>Contraseña<input name="password" type="password" minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres" required></label>
        <label>Método de pago preferido<select name="payout_method"><option value="">Selecciona</option><option>Transferencia bancaria</option><option>Nequi</option><option>Daviplata</option></select></label>
        <label class="sp__accept"><input type="checkbox" name="accept_contract" value="1" required> Acepto el <a href="<?= htmlspecialchars($__contractUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">contrato de SONIKA Partners</a> y la <a href="/politica-de-privacidad" target="_blank" rel="noopener">política de datos</a>.</label>
        <button class="sp__submit" type="submit">Enviar solicitud</button>
        <div class="sp__msg" id="partner-register-msg" role="status" aria-live="polite"></div>
      </form>
    </section>

    <section class="sp__section" style="padding-top:34px">
      <h2>Preguntas frecuentes</h2>
      <div class="sp__faq">
        <details><summary>¿Necesito ser artista o tener seguidores?</summary><p>No. Puedes participar como persona natural. La categoría solo personaliza tu experiencia; la comisión y el contrato son iguales.</p></details>
        <details><summary>¿Cómo me pagan?</summary><p>La comisión se calcula mensualmente, se valida después de la ventana de devoluciones y se paga con aprobación manual. Hay un mínimo de $100.000 COP y retención de 15 días.</p></details>
        <details><summary>¿Qué cuenta como pedido válido?</summary><p>Un pedido pagado, no devuelto, no cancelado, no interno, no fragmentado, con ticket neto mínimo de $1.000 COP.</p></details>
        <details><summary>¿La comisión puede superar el 5 %?</summary><p>No. La comisión total, incluyendo ajustes y campañas, nunca supera el 5 % de la venta neta elegible.</p></details>
        <details><summary>¿Puedo cambiar de categoría?</summary><p>Sí. La categoría es interna y no altera tu contrato. Puedes solicitar el cambio al equipo para personalizar tus herramientas.</p></details>
      </div>
    </section>
  </div>
  <script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => [
      ['@type' => 'Question', 'name' => '¿Necesito ser artista o tener seguidores?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'No. Puedes participar como persona natural. La categoría solo personaliza tu experiencia; la comisión y el contrato son iguales.']],
      ['@type' => 'Question', 'name' => '¿Cómo me pagan?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'La comisión se calcula mensualmente, se valida después de la ventana de devoluciones y se paga con aprobación manual. Hay un mínimo de $100.000 COP y retención de 15 días.']],
      ['@type' => 'Question', 'name' => '¿La comisión puede superar el 5 %?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'No. La comisión total nunca supera el 5 % de la venta neta elegible.']],
    ],
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <script>
    (function () {
      var sales = document.getElementById('sp-sales');
      var orders = document.getElementById('sp-orders');
      var commission = document.getElementById('sp-commission');
      var rateEl = document.getElementById('sp-rate');
      var nextEl = document.getElementById('sp-next');
      var tiers = [[0, 9, 0.030], [10, 29, 0.035], [30, 59, 0.040], [60, 119, 0.045], [120, Infinity, 0.050]];
      function calc() {
        var s = Math.max(0, parseFloat(sales.value) || 0);
        var o = Math.max(0, parseInt(orders.value, 10) || 0);
        var points = Math.floor(s / 100000) + o;
        var rate = 0.05;
        var next = null;
        for (var i = 0; i < tiers.length; i++) {
          if (points >= tiers[i][0] && points <= tiers[i][1]) { rate = tiers[i][2]; next = i < tiers.length - 1 ? tiers[i + 1][0] : null; break; }
        }
        var amount = s * rate;
        var fmt = function (n) { return '$' + Math.round(n).toLocaleString('es-CO'); };
        commission.textContent = fmt(amount);
        rateEl.textContent = (rate * 100).toFixed(1).replace('.', ',') + ' % · ' + points + ' puntos';
        nextEl.textContent = next ? ('Con ' + next + ' puntos llegas al ' + (tiers[Math.min(tiers.length - 1, tiers.findIndex(function (t) { return t[0] === next; }))][2] * 100).toFixed(1).replace('.', ',') + ' %.') : 'Estás en el techo del 5 %.';
      }
      if (sales && orders) { sales.addEventListener('input', calc); orders.addEventListener('input', calc); calc(); }

      var form = document.getElementById('partner-register');
      var msg = document.getElementById('partner-register-msg');
      var category = 'artist';
      var buttons = document.querySelectorAll('#sp-category .sp__cat');
      var hidden = document.getElementById('sp-profile-category');
      var artistNameLabel = document.getElementById('sp-artist-name-label');
      function setCategory(cat) {
        category = cat;
        if (hidden) hidden.value = cat;
        buttons.forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-cat') === cat); });
        document.querySelectorAll('[data-group]').forEach(function (g) {
          var show = g.getAttribute('data-group') === cat;
          g.hidden = !show;
          g.querySelectorAll('input,select,textarea').forEach(function (i) { i.disabled = !show; });
        });
        if (artistNameLabel) artistNameLabel.style.display = cat === 'artist' ? '' : 'none';
        var artistName = form ? form.querySelector('input[name=artist_name]') : null;
        if (artistName) artistName.disabled = cat !== 'artist';
      }
      buttons.forEach(function (b) { b.addEventListener('click', function () { setCategory(b.getAttribute('data-cat')); }); });
      setCategory('artist');

      if (form) form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!form.checkValidity()) { form.reportValidity(); return; }
        var button = form.querySelector('button[type=submit]');
        var data = {};
        new FormData(form).forEach(function (value, key) {
          if (key === 'social_instagram' || key === 'social_video') return;
          if (value) data[key] = value;
        });
        data.profile_category = category;
        var socials = {};
        var ig = form.querySelector('input[name=social_instagram]');
        var sv = form.querySelector('input[name=social_video]');
        if (ig && ig.value.trim()) socials.instagram = ig.value.trim();
        if (sv && sv.value.trim()) socials.video = sv.value.trim();
        if (Object.keys(socials).length) data.socials = socials;
        button.disabled = true; button.textContent = 'Enviando solicitud...';
        msg.style.color = '#51607a'; msg.textContent = 'Estamos registrando tu solicitud.';
        fetch('/api/v1/public/affiliates/register', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
          .then(function (r) { return r.json(); })
          .then(function (result) {
            button.disabled = false; button.textContent = 'Enviar solicitud';
            if (result && result.ok) {
              msg.style.color = '#0d7a58';
              msg.innerHTML = 'Solicitud recibida. Revisaremos tu perfil. <a href="/mi-antro?next=contrato" style="color:#0d7a58;font-weight:800">Inicia sesión y firma tu contrato</a>.';
              form.reset(); setCategory('artist');
            } else {
              msg.style.color = '#b42318'; msg.textContent = (result && result.message) || 'No se pudo enviar la solicitud.';
            }
          })
          .catch(function () { button.disabled = false; button.textContent = 'Enviar solicitud'; msg.style.color = '#b42318'; msg.textContent = 'Error de conexión. Intenta de nuevo.'; });
      });
    })();
  </script>
</section>
