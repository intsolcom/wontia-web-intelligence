<?php
namespace App\Widgets;

use App\Core\Lang;

class ContactFormWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'contact-form', 'name' => 'INTSOLCOM: Contact Form', 'icon' => 'mail', 'category' => 'content', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => 'Let\'s talk about your project'],
            ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea', 'default' => 'Tell us what you need. We reply within one business day.'],
            ['key' => 'email', 'label' => 'Email', 'type' => 'text', 'default' => 'customer@intsolcom.com'],
            ['key' => 'phone', 'label' => 'USA phone', 'type' => 'text', 'default' => '+1 (786) 386-1515'],
            ['key' => 'address_us', 'label' => 'USA office', 'type' => 'text', 'default' => '390 NE 191st St, STE 17284, Miami, FL 33179'],
            ['key' => 'address_co', 'label' => 'Colombia office', 'type' => 'text', 'default' => 'Cra 55 # 100 - 51 Piso 13, Centro Empresarial Blue Garden'],
            ['key' => 'email_co', 'label' => 'Colombia email', 'type' => 'text', 'default' => 'cliente@intsolcom.com'],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $e = fn($s) => $this->esc((string)$s);
        $es = Lang::current() === 'es';

        $defTitle = 'Let\'s talk about your project';
        $defSub = 'Tell us what you need. We reply within one business day.';
        if ($es) {
            $title = trim((string)($c['title'] ?? ''));
            $sub = trim((string)($c['subtitle'] ?? ''));
            $c['title'] = ($title === '' || $title === $defTitle) ? 'Hablemos de tu proyecto' : $title;
            $c['subtitle'] = ($sub === '' || $sub === $defSub) ? 'Cuéntanos qué necesitas. Respondemos en un día hábil.' : $sub;
        }

        $t = $es ? [
            'title' => (string)($c['title'] ?? '') ?: 'Hablemos de tu proyecto',
            'subtitle' => (string)($c['subtitle'] ?? '') ?: 'Cuéntanos qué necesitas. Respondemos en un día hábil.',
            'step' => 'Paso',
            'of' => 'de',
            'name' => '¿Cómo te llamas?',
            'name_ph' => 'Nombre completo',
            'email' => 'Tu correo de trabajo',
            'email_ph' => 'tu@empresa.com',
            'company' => '¿En qué empresa trabajas?',
            'company_ph' => 'Nombre de la empresa',
            'phone' => 'Teléfono (opcional)',
            'phone_ph' => '+1 555 000 0000',
            'need' => '¿Qué necesitas resolver?',
            'services' => ['Aplicación web a medida', 'Equipos nearshore', 'IA y automatización', 'Anotación de datos', 'Otro'],
            'message' => 'Cuéntanos el reto',
            'message_ph' => '¿Qué proceso quieres mejorar? ¿Qué resultado esperas?',
            'back' => 'Atrás',
            'next' => 'Continuar',
            'send' => 'Enviar mensaje',
            'sending' => 'Enviando...',
            'required' => 'Este campo es obligatorio.',
            'invalid_email' => 'Ingresa un correo válido.',
            'short_message' => 'Cuéntanos un poco más (mínimo 10 caracteres).',
            'success_title' => 'Mensaje recibido',
            'success_text' => 'Gracias. Nuestro equipo revisará tu mensaje y te responderá en un día hábil.',
            'sent_again' => 'Enviar otro mensaje',
            'offices' => 'Oficinas',
            'usa' => 'Estados Unidos',
            'co' => 'Colombia',
            'email_label' => 'Correo',
            'phone_label' => 'Teléfono EE. UU.',
            'response' => 'Respuesta en 1 día hábil',
            'security' => 'Tu información es confidencial. No compartimos tus datos.',
            'error' => 'No pudimos enviar el mensaje. Inténtalo de nuevo.',
        ] : [
            'title' => (string)($c['title'] ?? '') ?: 'Let\'s talk about your project',
            'subtitle' => (string)($c['subtitle'] ?? '') ?: 'Tell us what you need. We reply within one business day.',
            'step' => 'Step',
            'of' => 'of',
            'name' => 'What\'s your name?',
            'name_ph' => 'Full name',
            'email' => 'Your work email',
            'email_ph' => 'you@company.com',
            'company' => 'Where do you work?',
            'company_ph' => 'Company name',
            'phone' => 'Phone (optional)',
            'phone_ph' => '+1 555 000 0000',
            'need' => 'What do you need help with?',
            'services' => ['Custom web application', 'Nearshore teams', 'AI & automation', 'Data annotation', 'Something else'],
            'message' => 'Tell us about the challenge',
            'message_ph' => 'What process do you want to improve? What outcome do you expect?',
            'back' => 'Back',
            'next' => 'Continue',
            'send' => 'Send message',
            'sending' => 'Sending...',
            'required' => 'This field is required.',
            'invalid_email' => 'Please enter a valid email.',
            'short_message' => 'Please tell us a bit more (at least 10 characters).',
            'success_title' => 'Message received',
            'success_text' => 'Thank you. Our team will review your message and reply within one business day.',
            'sent_again' => 'Send another message',
            'offices' => 'Offices',
            'usa' => 'United States',
            'co' => 'Colombia',
            'email_label' => 'Email',
            'phone_label' => 'USA phone',
            'response' => 'Reply within 1 business day',
            'security' => 'Your information is confidential. We never share your data.',
            'error' => 'We could not send your message. Please try again.',
        ];

        $services = $t['services'];

        $html = '<style>'
            . '.cfp{font-family:Inter,-apple-system,sans-serif;background:#F6F8FA;border-top:1px solid #E8EDF3;padding:92px 0;color:#20252B}'
            . '.cfp *{box-sizing:border-box}'
            . '.cfp-wrap{max-width:1080px;margin:0 auto;padding:0 24px;display:grid;grid-template-columns:1.15fr .85fr;gap:38px;align-items:start}'
            . '.cfp-card{background:#FFFFFF;border:1px solid #E1E8F0;border-radius:20px;padding:34px 34px 30px;box-shadow:0 18px 50px rgba(32,37,43,.07)}'
            . '.cfp-head h2{font-family:"Space Grotesk",Inter,sans-serif;font-size:clamp(1.5rem,2.6vw,2rem);font-weight:700;letter-spacing:-.02em;color:#20252B;margin:0 0 8px}'
            . '.cfp-head p{font-size:.95rem;color:#5B6773;margin:0 0 22px}'
            . '.cfp-progress{height:5px;background:#EDF2F7;border-radius:99px;overflow:hidden;margin-bottom:8px}'
            . '.cfp-progress span{display:block;height:100%;width:25%;background:#00A3FF;border-radius:99px;transition:width .35s ease}'
            . '.cfp-stepnum{font-size:11.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#00A3FF;margin-bottom:20px}'
            . '.cfp-step{display:none}'
            . '.cfp-step.on{display:block;animation:cfpFade .25s ease}'
            . '@keyframes cfpFade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}'
            . '.cfp-q{font-size:1.12rem;font-weight:700;color:#20252B;margin:0 0 14px}'
            . '.cfp-input,.cfp-textarea{width:100%;padding:15px 16px;border:1.5px solid #DCE5EE;border-radius:12px;font-family:inherit;font-size:16px;color:#20252B;background:#FBFDFF;outline:none;transition:border-color .18s,box-shadow .18s;margin-bottom:12px}'
            . '.cfp-input:focus,.cfp-textarea:focus{border-color:#00A3FF;box-shadow:0 0 0 4px rgba(0,163,255,.12);background:#fff}'
            . '.cfp-input.err,.cfp-textarea.err{border-color:#D64545;box-shadow:0 0 0 4px rgba(214,69,69,.08)}'
            . '.cfp-textarea{min-height:130px;resize:vertical}'
            . '.cfp-err-msg{display:none;font-size:12px;color:#C0392B;margin:-4px 0 10px}'
            . '.cfp-err-msg.on{display:block}'
            . '.cfp-pills{display:flex;flex-wrap:wrap;gap:9px;margin-bottom:8px}'
            . '.cfp-pill{position:relative;display:inline-flex;align-items:center;gap:8px;padding:11px 16px;border:1.5px solid #DCE5EE;border-radius:99px;font-size:13.5px;font-weight:600;color:#3C4753;cursor:pointer;transition:all .15s;background:#fff}'
            . '.cfp-pill:hover{border-color:#9FD5F9}'
            . '.cfp-pill input{position:absolute;opacity:0;pointer-events:none}'
            . '.cfp-pill.sel{border-color:#00A3FF;background:#EAF6FF;color:#0077C2}'
            . '.cfp-pill-dot{width:15px;height:15px;border-radius:50%;border:1.5px solid #C9D6E3;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0}'
            . '.cfp-pill.sel .cfp-pill-dot{border-color:#00A3FF;background:#00A3FF}'
            . '.cfp-pill.sel .cfp-pill-dot::after{content:"";width:5px;height:5px;border-radius:50%;background:#fff}'
            . '.cfp-nav{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:8px}'
            . '.cfp-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 26px;border-radius:11px;font-weight:700;font-size:14.5px;cursor:pointer;border:1.5px solid transparent;transition:all .15s;font-family:inherit}'
            . '.cfp-btn:focus-visible{outline:3px solid #9BD4FA;outline-offset:2px}'
            . '.cfp-btn-primary{background:#00A3FF;color:#fff;box-shadow:0 8px 20px rgba(0,163,255,.25)}'
            . '.cfp-btn-primary:hover{background:#008FE0}'
            . '.cfp-btn-ghost{background:transparent;border-color:#DCE5EE;color:#5B6773}'
            . '.cfp-btn-ghost:hover{border-color:#9BB0C4;color:#20252B}'
            . '.cfp-btn[disabled]{opacity:.6;pointer-events:none}'
            . '.cfp-hp{position:absolute;left:-9999px;height:1px;width:1px;overflow:hidden}'
            . '.cfp-note{font-size:11.5px;color:#8A97A5;margin-top:14px;line-height:1.5}'
            . '.cfp-success{display:none;text-align:center;padding:26px 8px 10px}'
            . '.cfp-success.on{display:block}'
            . '.cfp-check{width:62px;height:62px;border-radius:50%;background:#E6F8EF;color:#1E9E63;display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 16px}'
            . '.cfp-success h3{font-family:"Space Grotesk",Inter,sans-serif;font-size:1.4rem;font-weight:700;margin:0 0 8px;color:#20252B}'
            . '.cfp-success p{font-size:.95rem;color:#5B6773;max-width:400px;margin:0 auto 18px}'
            . '.cfp-side{background:#20252B;border-radius:20px;padding:32px 28px;color:#fff;position:sticky;top:100px}'
            . '.cfp-side h3{font-family:"Space Grotesk",Inter,sans-serif;font-size:1.15rem;font-weight:700;margin:0 0 6px;color:#fff}'
            . '.cfp-side .cfp-badge{display:inline-flex;align-items:center;gap:7px;font-size:11.5px;font-weight:700;color:#7CCBFF;background:rgba(0,163,255,.12);border:1px solid rgba(0,163,255,.3);border-radius:99px;padding:5px 12px;margin-bottom:18px}'
            . '.cfp-side .cfp-badge i{width:7px;height:7px;border-radius:50%;background:#00A3FF}'
            . '.cfp-side-block{padding:14px 0;border-top:1px solid rgba(255,255,255,.09)}'
            . '.cfp-side-block:first-of-type{border-top:none;padding-top:0}'
            . '.cfp-side-label{font-size:10.5px;letter-spacing:.09em;text-transform:uppercase;color:#8EA0B3;font-weight:700;margin-bottom:6px}'
            . '.cfp-side-val{font-size:14px;color:#E8EEF4;line-height:1.55;font-weight:500}'
            . '.cfp-side a{color:#7CCBFF;text-decoration:none;font-weight:600}'
            . '.cfp-side a:hover{text-decoration:underline}'
            . '.cfp-side-foot{margin-top:18px;padding-top:14px;border-top:1px solid rgba(255,255,255,.09);font-size:11.5px;color:#8EA0B3;line-height:1.5}'
            . '@media(max-width:920px){.cfp-wrap{grid-template-columns:1fr}.cfp-side{position:static;order:2}.cfp{padding:64px 0}}'
            . '@media(max-width:520px){.cfp-card{padding:26px 20px}.cfp-nav{flex-direction:column-reverse;align-items:stretch}.cfp-nav .cfp-btn{width:100%}}'
            . '@media(prefers-reduced-motion:reduce){.cfp-step.on{animation:none}.cfp-progress span{transition:none}}'
            . '</style>';

        $html .= '<section class="cfp" id="contact"><div class="cfp-wrap">';

        $html .= '<div class="cfp-card"><div class="cfp-head"><h2>' . $e($t['title']) . '</h2><p>' . $e($t['subtitle']) . '</p></div>';
        $html .= '<div class="cfp-progress"><span id="cfp-progress-bar"></span></div>';
        $html .= '<div class="cfp-stepnum">' . $e($t['step']) . ' <span id="cfp-stepnum">1</span> ' . $e($t['of']) . ' 4</div>';

        $html .= '<form id="cfp-form" novalidate>';

        $html .= '<div class="cfp-hp"><label for="cfp-website">Website</label><input type="text" id="cfp-website" name="website" tabindex="-1" autocomplete="off"></div>';

        $html .= '<div class="cfp-step on" data-step="1">';
        $html .= '<p class="cfp-q">' . $e($t['name']) . '</p>';
        $html .= '<input class="cfp-input" type="text" name="name" id="cfp-name" placeholder="' . $e($t['name_ph']) . '" autocomplete="name" required>';
        $html .= '<div class="cfp-err-msg" data-for="name">' . $e($t['required']) . '</div>';
        $html .= '<p class="cfp-q" style="margin-top:6px">' . $e($t['email']) . '</p>';
        $html .= '<input class="cfp-input" type="email" name="email" id="cfp-email" placeholder="' . $e($t['email_ph']) . '" autocomplete="email" required>';
        $html .= '<div class="cfp-err-msg" data-for="email">' . $e($t['invalid_email']) . '</div>';
        $html .= '<div class="cfp-nav"><span></span><button type="button" class="cfp-btn cfp-btn-primary" data-next="1">' . $e($t['next']) . ' &#8594;</button></div>';
        $html .= '</div>';

        $html .= '<div class="cfp-step" data-step="2">';
        $html .= '<p class="cfp-q">' . $e($t['company']) . '</p>';
        $html .= '<input class="cfp-input" type="text" name="company" id="cfp-company" placeholder="' . $e($t['company_ph']) . '" autocomplete="organization">';
        $html .= '<p class="cfp-q" style="margin-top:6px">' . $e($t['phone']) . '</p>';
        $html .= '<input class="cfp-input" type="tel" name="phone" id="cfp-phone" placeholder="' . $e($t['phone_ph']) . '" autocomplete="tel">';
        $html .= '<div class="cfp-nav"><button type="button" class="cfp-btn cfp-btn-ghost" data-back="2">&#8592; ' . $e($t['back']) . '</button><button type="button" class="cfp-btn cfp-btn-primary" data-next="2">' . $e($t['next']) . ' &#8594;</button></div>';
        $html .= '</div>';

        $html .= '<div class="cfp-step" data-step="3">';
        $html .= '<p class="cfp-q">' . $e($t['need']) . '</p>';
        $html .= '<div class="cfp-pills" id="cfp-pills">';
        foreach ($services as $i => $svc) {
            $html .= '<label class="cfp-pill' . ($i === 0 ? ' sel' : '') . '"><input type="radio" name="service" value="' . $e($svc) . '"' . ($i === 0 ? ' checked' : '') . '><span class="cfp-pill-dot"></span><span>' . $e($svc) . '</span></label>';
        }
        $html .= '</div>';
        $html .= '<div class="cfp-nav"><button type="button" class="cfp-btn cfp-btn-ghost" data-back="3">&#8592; ' . $e($t['back']) . '</button><button type="button" class="cfp-btn cfp-btn-primary" data-next="3">' . $e($t['next']) . ' &#8594;</button></div>';
        $html .= '</div>';

        $html .= '<div class="cfp-step" data-step="4">';
        $html .= '<p class="cfp-q">' . $e($t['message']) . '</p>';
        $html .= '<textarea class="cfp-textarea" name="message" id="cfp-message" placeholder="' . $e($t['message_ph']) . '" required></textarea>';
        $html .= '<div class="cfp-err-msg" data-for="message">' . $e($t['short_message']) . '</div>';
        $html .= '<div class="cfp-nav"><button type="button" class="cfp-btn cfp-btn-ghost" data-back="4">&#8592; ' . $e($t['back']) . '</button><button type="submit" class="cfp-btn cfp-btn-primary" id="cfp-submit">' . $e($t['send']) . ' &#8594;</button></div>';
        $html .= '<p class="cfp-note">' . $e($t['security']) . '</p>';
        $html .= '</div>';

        $html .= '</form>';

        $html .= '<div class="cfp-success" id="cfp-success"><div class="cfp-check">&#10003;</div><h3>' . $e($t['success_title']) . '</h3><p>' . $e($t['success_text']) . '</p><button type="button" class="cfp-btn cfp-btn-ghost" id="cfp-again">' . $e($t['sent_again']) . '</button></div>';
        $html .= '</div>';

        $html .= '<aside class="cfp-side"><span class="cfp-badge"><i></i>' . $e($t['response']) . '</span>';
        $html .= '<h3>' . $e($t['offices']) . '</h3>';
        $html .= '<div class="cfp-side-block"><div class="cfp-side-label">' . $e($t['email_label']) . '</div><div class="cfp-side-val"><a href="mailto:' . $e($c['email']) . '">' . $e($c['email']) . '</a><br><a href="mailto:' . $e($c['email_co']) . '">' . $e($c['email_co']) . '</a></div></div>';
        $html .= '<div class="cfp-side-block"><div class="cfp-side-label">' . $e($t['phone_label']) . '</div><div class="cfp-side-val"><a href="tel:' . preg_replace('/[^+\d]/', '', (string)$c['phone']) . '">' . $e($c['phone']) . '</a></div></div>';
        $html .= '<div class="cfp-side-block"><div class="cfp-side-label">' . $e($t['usa']) . '</div><div class="cfp-side-val">' . $e($c['address_us']) . '</div></div>';
        $html .= '<div class="cfp-side-block"><div class="cfp-side-label">' . $e($t['co']) . '</div><div class="cfp-side-val">' . $e($c['address_co']) . '</div></div>';
        $html .= '<div class="cfp-side-foot">' . $e($t['security']) . '</div>';
        $html .= '</aside>';

        $html .= '</div></section>';

        $html .= '<script>(function(){'
            . 'var form=document.getElementById("cfp-form");if(!form)return;'
            . 'var steps=form.querySelectorAll(".cfp-step"),bar=document.getElementById("cfp-progress-bar"),num=document.getElementById("cfp-stepnum");'
            . 'var cur=1,total=steps.length,sending=false;'
            . 'var msg={required:' . json_encode($t['required']) . ',email:' . json_encode($t['invalid_email']) . ',short:' . json_encode($t['short_message']) . ',error:' . json_encode($t['error']) . ',sending:' . json_encode($t['sending']) . ',send:' . json_encode($t['send']) . '};'
            . 'function show(n){cur=n;steps.forEach(function(s){s.classList.toggle("on",+s.getAttribute("data-step")===n)});bar.style.width=(n/total*100)+"%";num.textContent=n;var first=form.querySelector(".cfp-step.on input:not([type=radio]):not([type=hidden]),.cfp-step.on textarea");if(first)setTimeout(function(){first.focus()},60)}'
            . 'function err(name,on,text){var el=form.querySelector(".cfp-err-msg[data-for="+name+"]");var inp=form.querySelector("[name="+name+"]");if(inp)inp.classList.toggle("err",on);if(el){if(text)el.textContent=text;el.classList.toggle("on",on)}}'
            . 'function validEmail(v){return /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/.test(v)}'
            . 'function validate(n){var ok=true;'
            . 'if(n===1){var name=form.name.value.trim(),email=form.email.value.trim();err("name",!name);if(!name)ok=false;if(!validEmail(email)){err("email",true,msg.email);ok=false}else{err("email",false)}}'
            . 'if(n===4){var m=form.message.value.trim();if(m.length<10){err("message",true,msg.short);ok=false}else{err("message",false)}}'
            . 'return ok}'
            . 'form.querySelectorAll("[data-next]").forEach(function(b){b.addEventListener("click",function(){var n=+b.getAttribute("data-next");if(validate(n))show(n+1)})});'
            . 'form.querySelectorAll("[data-back]").forEach(function(b){b.addEventListener("click",function(){show(+b.getAttribute("data-back")-1)})});'
            . 'form.querySelectorAll(".cfp-pill").forEach(function(l){l.addEventListener("click",function(){form.querySelectorAll(".cfp-pill").forEach(function(x){x.classList.remove("sel")});l.classList.add("sel")})});'
            . 'form.addEventListener("keydown",function(e){if(e.key==="Enter"&&e.target.tagName!=="TEXTAREA"){var step=form.querySelector(".cfp-step.on");var n=+step.getAttribute("data-step");if(n<total){e.preventDefault();var b=step.querySelector("[data-next]");if(b)b.click()}}});'
            . 'form.addEventListener("submit",function(e){e.preventDefault();if(sending)return;if(!validate(4))return;sending=true;var btn=document.getElementById("cfp-submit");btn.disabled=true;btn.textContent=msg.sending;'
            . 'var payload={name:form.name.value.trim(),email:form.email.value.trim(),company:form.company.value.trim(),phone:form.phone.value.trim(),service:(form.querySelector("input[name=service]:checked")||{}).value||"",message:form.message.value.trim(),website:document.getElementById("cfp-website").value};'
            . 'fetch("/api/v1/public/contact",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(payload)}).then(function(r){return r.json()}).then(function(j){sending=false;btn.disabled=false;btn.textContent=msg.send;if(j&&j.ok){form.style.display="none";document.querySelector(".cfp-progress").style.display="none";document.querySelector(".cfp-stepnum").style.display="none";document.getElementById("cfp-success").classList.add("on")}else{alert((j&&j.message)||msg.error)}}).catch(function(){sending=false;btn.disabled=false;btn.textContent=msg.send;alert(msg.error)});'
            . '});'
            . 'document.getElementById("cfp-again").addEventListener("click",function(){form.reset();form.querySelectorAll(".cfp-pill").forEach(function(x,i){x.classList.toggle("sel",i===0)});form.style.display="";document.querySelector(".cfp-progress").style.display="";document.querySelector(".cfp-stepnum").style.display="";document.getElementById("cfp-success").classList.remove("on");show(1)});'
            . '})();</script>';

        return $html;
    }
}
