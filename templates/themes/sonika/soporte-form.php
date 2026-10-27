<?php
$__spsName = '';
$__spsEmail = '';
$__spsPhone = '';
if (isset($__supCustomer) && is_array($__supCustomer)) {
    $__spsName = (string)($__supCustomer['name'] ?? '');
    $__spsEmail = (string)($__supCustomer['email'] ?? '');
    $__spsPhone = (string)($__supCustomer['phone'] ?? '');
}
$__spsEsc = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<style>
.sps{--sps-brand:#F20D16;--sps-ink:#111;--sps-muted:#6B6B6B;--sps-line:#E6E6E6;--sps-soft:#F7F7F7;font-family:Sora,system-ui,sans-serif;color:var(--sps-ink)}
.sps *,.sps *::before,.sps *::after{box-sizing:border-box}
.sps form{display:grid;gap:0}
.sps__top{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px}
.sps__step-label{font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--sps-muted)}
.sps__bar{height:5px;border-radius:99px;background:#EDEDED;overflow:hidden;margin-bottom:18px}
.sps__bar span{display:block;height:100%;width:25%;border-radius:99px;background:linear-gradient(90deg,var(--sps-brand),#FF5A61);transition:width .22s ease}
.sps__step[hidden]{display:none!important}
.sps__step h3{margin:0 0 5px;font-size:clamp(19px,2.1vw,24px);line-height:1.15;letter-spacing:-.02em}
.sps__hint{margin:0 0 18px;color:var(--sps-muted);font-size:12.5px;line-height:1.5}
.sps__field{margin:0 0 15px}
.sps__label{display:block;margin:0 0 6px;font-size:12.5px;font-weight:700}
.sps__label .req{color:var(--sps-brand)}
.sps__input,.sps__select,.sps__textarea{display:block;width:100%;min-height:46px;padding:11px 12px;border:1px solid var(--sps-line);border-radius:9px;background:#fff;color:var(--sps-ink);font:inherit;font-size:16px;line-height:1.45;transition:border-color .15s ease,box-shadow .15s ease}
.sps__textarea{min-height:140px;resize:vertical}
.sps__input:focus-visible,.sps__select:focus-visible,.sps__textarea:focus-visible{outline:none;border-color:var(--sps-brand);box-shadow:0 0 0 3px rgba(242,13,22,.1)}
.sps__help{margin:5px 0 0;color:var(--sps-muted);font-size:11.5px;line-height:1.45}
.sps__err{margin:5px 0 0;color:#B42318;font-size:12px;font-weight:650;line-height:1.4}
.sps__err:empty{display:none}
.sps__nav{display:flex;align-items:center;justify-content:space-between;gap:9px;margin-top:18px;padding-top:16px;border-top:1px solid var(--sps-line)}
.sps__btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:44px;padding:11px 18px;border:1px solid transparent;border-radius:9px;background:var(--sps-brand);color:#fff;font:inherit;font-size:13.5px;font-weight:750;cursor:pointer;text-decoration:none;transition:filter .14s ease,transform .14s ease}
.sps__btn:hover{filter:brightness(.94)}
.sps__btn:active{transform:translateY(1px)}
.sps__btn:focus-visible{outline:3px solid rgba(242,13,22,.3);outline-offset:2px}
.sps__btn--ghost{background:#fff;color:var(--sps-ink);border-color:var(--sps-line)}
.sps__btn--ghost:hover{border-color:var(--sps-brand);color:var(--sps-brand);filter:none}
.sps__btn[disabled]{opacity:.6;cursor:wait}
.sps__summary{display:grid;gap:7px;margin:0 0 16px;padding:12px;border:1px solid var(--sps-line);border-radius:10px;background:var(--sps-soft)}
.sps__row{display:grid;grid-template-columns:96px minmax(0,1fr);gap:10px;font-size:12.5px;line-height:1.45}
.sps__row dt{color:var(--sps-muted);font-weight:600}
.sps__row dd{margin:0;font-weight:650;overflow-wrap:anywhere}
.sps__prefs{display:grid;gap:7px;margin:0 0 14px;padding:0;border:0}
.sps__prefs legend{margin:0 0 7px;padding:0;font-size:12.5px;font-weight:700}
.sps__pref{display:flex;align-items:center;gap:8px;min-height:38px;padding:7px 10px;border:1px solid var(--sps-line);border-radius:8px;background:#fff;font-size:12.5px;cursor:pointer}
.sps__pref:has(input:checked){border-color:var(--sps-brand);background:#FFF7F7}
.sps__pref input{accent-color:var(--sps-brand);width:17px;height:17px}
.sps__auth{display:flex;align-items:flex-start;gap:9px;padding:12px;border:1px solid var(--sps-line);border-radius:10px;background:#fff;font-size:12.5px;line-height:1.5}
.sps__auth input{flex:0 0 auto;width:19px;height:19px;margin:1px 0 0;accent-color:var(--sps-brand)}
.sps__auth a{color:var(--sps-brand);font-weight:650;text-decoration:underline;text-underline-offset:2px}
.sps__status{min-height:18px;margin:10px 0 0;font-size:12.5px;line-height:1.45;font-weight:600;color:var(--sps-muted)}
.sps__status--err{color:#B42318}
.sps__status--ok{color:#0A6B49}
.sps__note{margin:12px 0 0;color:var(--sps-muted);font-size:11.5px;line-height:1.5}
.sps__success{text-align:center;padding:24px 8px 8px}
.sps__success[hidden]{display:none!important}
.sps__check{display:grid;place-items:center;width:56px;height:56px;margin:0 auto 13px;border-radius:50%;background:#F0FBF6;color:#0A6B49;font-size:25px;font-weight:900}
.sps__success h3{margin:0 0 6px;font-size:21px;letter-spacing:-.02em}
.sps__success p{margin:0 auto 16px;max-width:44ch;color:var(--sps-muted);font-size:13px;line-height:1.6}
@media(max-width:560px){.sps__row{grid-template-columns:1fr;gap:2px}.sps__nav{align-items:stretch;flex-direction:column-reverse}.sps__nav .sps__btn{width:100%}}
@media(prefers-reduced-motion:reduce){.sps *{transition:none!important;animation:none!important}}
</style>

<div class="sps">
  <form id="sps-form" novalidate>
    <div class="sps__top"><span class="sps__step-label">Paso <b id="sps-step-current">1</b> de 4</span><span class="sps__step-label" id="sps-step-name">Tus datos</span></div>
    <div class="sps__bar" aria-hidden="true"><span id="sps-bar"></span></div>

    <div class="sps__step" data-step="1">
      <h3>¿Cómo te llamas?</h3>
      <p class="sps__hint">Así sabremos cómo dirigirnos a ti.</p>
      <div class="sps__field">
        <label class="sps__label" for="sps-name">Nombre completo <span class="req">*</span></label>
        <input class="sps__input" id="sps-name" name="name" value="<?= $__spsEsc($__spsName) ?>" maxlength="120" autocomplete="name" required>
        <p class="sps__err" data-for="name" aria-live="polite"></p>
      </div>
      <div class="sps__field">
        <label class="sps__label" for="sps-email">Correo electrónico <span class="req">*</span></label>
        <input class="sps__input" id="sps-email" name="email" type="email" value="<?= $__spsEsc($__spsEmail) ?>" maxlength="190" autocomplete="email" required>
        <p class="sps__err" data-for="email" aria-live="polite"></p>
      </div>
      <div class="sps__nav"><span></span><button class="sps__btn" type="button" data-next="1">Continuar</button></div>
    </div>

    <div class="sps__step" data-step="2" hidden>
      <h3>¿En qué podemos ayudarte?</h3>
      <p class="sps__hint">Elige el motivo y, si aplica, el pedido relacionado.</p>
      <div class="sps__field">
        <label class="sps__label" for="sps-service">Motivo <span class="req">*</span></label>
        <select class="sps__select" id="sps-service" name="service" required>
          <option value="">Selecciona una opción</option>
          <option value="Asesoría para elegir un producto">Asesoría para elegir un producto</option>
          <option value="Consulta sobre un pedido">Consulta sobre un pedido</option>
          <option value="Envío o entrega">Envío o entrega</option>
          <option value="Pago">Pago</option>
          <option value="Garantía o devolución">Garantía o devolución</option>
          <option value="Soporte técnico">Soporte técnico</option>
          <option value="Otro">Otro</option>
        </select>
        <p class="sps__err" data-for="service" aria-live="polite"></p>
      </div>
      <div class="sps__field">
        <label class="sps__label" for="sps-order">Número de pedido <span style="font-weight:400;color:#6B6B6B">(opcional)</span></label>
        <input class="sps__input" id="sps-order" name="order_ref" maxlength="60" autocomplete="off" placeholder="Ej.: PED-261014-62968">
        <p class="sps__help">Lo encuentras en <a href="/cuenta?tab=orders" style="color:#F20D16;font-weight:650">Mi cuenta → Pedidos</a>.</p>
        <p class="sps__err" data-for="order_ref" aria-live="polite"></p>
      </div>
      <div class="sps__field">
        <label class="sps__label" for="sps-phone">Teléfono <span style="font-weight:400;color:#6B6B6B">(opcional)</span></label>
        <input class="sps__input" id="sps-phone" name="phone" type="tel" value="<?= $__spsEsc($__spsPhone) ?>" maxlength="40" inputmode="tel" autocomplete="tel">
        <p class="sps__help">Necesario si prefieres que te contactemos por WhatsApp o llamada.</p>
        <p class="sps__err" data-for="phone" aria-live="polite"></p>
      </div>
      <div class="sps__nav"><button class="sps__btn sps__btn--ghost" type="button" data-back="2">Atrás</button><button class="sps__btn" type="button" data-next="2">Continuar</button></div>
    </div>

    <div class="sps__step" data-step="3" hidden>
      <h3>Cuéntanos con detalle.</h3>
      <p class="sps__hint">Incluye fechas, modelo o cualquier dato que nos ayude a entender tu caso.</p>
      <div class="sps__field">
        <label class="sps__label" for="sps-message">Descripción <span class="req">*</span></label>
        <textarea class="sps__textarea" id="sps-message" name="message" maxlength="4000" placeholder="Describe tu consulta..." required></textarea>
        <p class="sps__help"><span id="sps-message-count">0</span>/4000 caracteres · mínimo 10</p>
        <p class="sps__err" data-for="message" aria-live="polite"></p>
      </div>
      <div class="sps__nav"><button class="sps__btn sps__btn--ghost" type="button" data-back="3">Atrás</button><button class="sps__btn" type="button" data-next="3">Continuar</button></div>
    </div>

    <div class="sps__step" data-step="4" hidden>
      <h3>Revisa y envía.</h3>
      <p class="sps__hint">Verifica tus datos y elige cómo prefieres que te respondamos.</p>
      <dl class="sps__summary" id="sps-summary"></dl>
      <fieldset class="sps__prefs">
        <legend>Medio preferido de respuesta <span class="req">*</span></legend>
        <label class="sps__pref"><input type="radio" name="reply_preference" value="email" checked> Correo electrónico</label>
        <label class="sps__pref"><input type="radio" name="reply_preference" value="whatsapp"> WhatsApp</label>
        <label class="sps__pref"><input type="radio" name="reply_preference" value="phone"> Llamada telefónica</label>
      </fieldset>
      <div class="sps__auth">
        <input type="checkbox" id="sps-auth" name="auth_accepted" value="1" required>
        <div>
          <label for="sps-auth" style="cursor:pointer">Autorizo el tratamiento de mis datos personales para atender esta solicitud. <span class="req">*</span></label>
          <span style="display:block;margin-top:3px;color:#6B6B6B;font-size:11.5px">Consulta: <a href="/politica-de-privacidad" target="_blank" rel="noopener">privacidad</a> · <a href="/terminos-y-condiciones" target="_blank" rel="noopener">términos y condiciones</a> · <a href="/politica-de-devoluciones" target="_blank" rel="noopener">devoluciones</a>.</span>
        </div>
      </div>
      <p class="sps__err" data-for="auth_accepted" aria-live="polite"></p>
      <div class="sps__nav"><button class="sps__btn sps__btn--ghost" type="button" data-back="4">Atrás</button><button class="sps__btn" type="submit" id="sps-submit">Enviar mensaje</button></div>
      <p class="sps__status" id="sps-status" role="status" aria-live="polite"></p>
      <p class="sps__note">No compartas contraseñas, datos de tarjetas ni información sensible. Para casos formales usa <a href="/pqrf" style="color:#F20D16;font-weight:650">PQRF</a>.</p>
    </div>

    <input type="hidden" name="source" value="sonika_support">
    <div style="position:absolute;left:-9999px;height:1px;width:1px;overflow:hidden" aria-hidden="true">
      <label for="sps-website">Website</label>
      <input type="text" id="sps-website" name="website" tabindex="-1" autocomplete="off">
    </div>
  </form>

  <div class="sps__success" id="sps-success" hidden>
    <div class="sps__check">✓</div>
    <h3>Mensaje recibido</h3>
    <p>Gracias. Revisaremos tu solicitud y te contactaremos por el medio que elegiste.</p>
    <button class="sps__btn sps__btn--ghost" type="button" id="sps-again">Enviar otro mensaje</button>
  </div>
</div>

<script>
(function(){
  var form=document.getElementById('sps-form');
  if(!form)return;
  var steps=[].slice.call(form.querySelectorAll('.sps__step'));
  var bar=document.getElementById('sps-bar');
  var stepCurrent=document.getElementById('sps-step-current');
  var stepName=document.getElementById('sps-step-name');
  var status=document.getElementById('sps-status');
  var submit=document.getElementById('sps-submit');
  var success=document.getElementById('sps-success');
  var again=document.getElementById('sps-again');
  var current=1,total=4,names=['Tus datos','Motivo','Descripción','Revisión'];
  function esc(v){var d=document.createElement('div');d.textContent=v==null?'':String(v);return d.innerHTML}
  function input(id){var e=document.getElementById(id);return e?e:null}
  function val(id){var e=input(id);return e?String(e.value||'').trim():''}
  function setErr(name,msg){var e=form.querySelector('.sps__err[data-for="'+name+'"]');if(e)e.textContent=msg||'';}
  function clearErrors(){['name','email','service','order_ref','phone','message','auth_accepted'].forEach(function(n){setErr(n,'')});status.textContent='';status.className='sps__status';}
  function setStatus(msg,type){status.textContent=msg||'';status.className='sps__status'+(type?' sps__status--'+type:'');}
  function show(n){current=Math.max(1,Math.min(total,n));steps.forEach(function(s,i){s.hidden=(i+1)!==current});bar.style.width=((current/total)*100)+'%';stepCurrent.textContent=current;stepName.textContent=names[current-1]||'';if(current===4)buildSummary();var first=steps[current-1].querySelector('input:not([type="hidden"]):not([type="radio"]):not([type="checkbox"]),select,textarea');if(first){try{first.focus({preventScroll:true})}catch(e){}}}
  function validEmail(v){return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)}
  function pref(){var e=form.querySelector('input[name="reply_preference"]:checked');return e?e.value:'email'}
  function validate(n){
    clearErrors();
    if(n===1){
      if(val('sps-name').length<2){setErr('name','Escribe tu nombre.');return false}
      if(!validEmail(val('sps-email'))){setErr('email','Ingresa un correo válido.');return false}
    }
    if(n===2){
      if(!val('sps-service')){setErr('service','Selecciona el motivo.');return false}
      var ref=val('sps-order');if(ref&&!/^[A-Za-z0-9#._\-\/ ]{3,60}$/.test(ref)){setErr('order_ref','El número de pedido no tiene un formato válido.');return false}
    }
    if(n===3){if(val('sps-message').length<10){setErr('message','Cuéntanos un poco más (mínimo 10 caracteres).');return false}}
    if(n===4){
      if(!input('sps-auth').checked){setErr('auth_accepted','Debes autorizar el tratamiento de tus datos.');return false}
      var p=pref();if((p==='whatsapp'||p==='phone')&&val('sps-phone').length<7){setErr('phone','Escribe un teléfono para este medio de respuesta.');show(2);return false}
    }
    return true;
  }
  function row(k,v){return '<div class="sps__row"><dt>'+esc(k)+'</dt><dd>'+esc(v||'—')+'</dd></div>'}
  function buildSummary(){
    var sel=input('sps-service');var service=sel&&sel.selectedIndex>=0?sel.options[sel.selectedIndex].text:'';
    var prefLabel={email:'Correo electrónico',whatsapp:'WhatsApp',phone:'Llamada telefónica'}[pref()]||'Correo electrónico';
    document.getElementById('sps-summary').innerHTML=
      row('Nombre',val('sps-name'))+
      row('Correo',val('sps-email'))+
      row('Motivo',service)+
      row('Pedido',val('sps-order'))+
      row('Teléfono',val('sps-phone'))+
      row('Descripción',val('sps-message'))+
      row('Respuesta',prefLabel);
  }
  form.querySelectorAll('[data-next]').forEach(function(b){b.addEventListener('click',function(){var n=+b.getAttribute('data-next');if(validate(n))show(n+1)})});
  form.querySelectorAll('[data-back]').forEach(function(b){b.addEventListener('click',function(){clearErrors();show(+b.getAttribute('data-back')-1)})});
  var msg=input('sps-message');if(msg)msg.addEventListener('input',function(){var c=document.getElementById('sps-message-count');if(c)c.textContent=String(msg.value.length)});
  form.addEventListener('keydown',function(e){if(e.key==='Enter'&&e.target.tagName!=='TEXTAREA'&&current<total){e.preventDefault();var b=steps[current-1].querySelector('[data-next]');if(b)b.click()}});
  form.addEventListener('submit',async function(e){
    e.preventDefault();
    if(!validate(4))return;
    submit.disabled=true;submit.textContent='Enviando…';setStatus('Enviando tu mensaje…');
    var payload={name:val('sps-name'),email:val('sps-email'),phone:val('sps-phone'),service:val('sps-service'),order_ref:val('sps-order'),message:val('sps-message'),reply_preference:pref(),auth_accepted:1,source:'sonika_support',website:val('sps-website')};
    try{
      var res=await fetch('/api/v1/public/contact',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
      var data=await res.json().catch(function(){return {}});
      if(!res.ok||!data.ok){
        setStatus((data&&data.message)||'No pudimos enviar el mensaje. Intenta de nuevo.','err');
        submit.disabled=false;submit.textContent='Enviar mensaje';
        return;
      }
      form.hidden=true;success.hidden=false;setStatus('');
      success.setAttribute('tabindex','-1');success.focus&&success.focus();
    }catch(err){
      setStatus('No pudimos conectar. Revisa tu conexión e intenta de nuevo.','err');
      submit.disabled=false;submit.textContent='Enviar mensaje';
    }
  });
  if(again)again.addEventListener('click',function(){form.reset();form.hidden=false;success.hidden=true;submit.disabled=false;submit.textContent='Enviar mensaje';clearErrors();show(1)});
  show(1);
})();
</script>
