<?php
namespace App\Widgets;

use App\Services\AffiliateService;

/**
 * SONIKA Artist Partners — Brick (programa de afiliados). Desactivable.
 * Fase 1: landing + registro (modo simulación, sin pagos reales). La lógica vive en AffiliateService.
 */
class SonikaAffiliatesWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'sonika-affiliates', 'name' => 'SONIKA — Programa de Artistas (Afiliados)', 'icon' => 'users', 'category' => 'sonika', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'enabled', 'label' => 'Activar el programa (modo simulación)', 'type' => 'toggle', 'default' => false],
            ['key' => 'title', 'label' => 'Título', 'type' => 'text', 'default' => 'SONIKA Artist Partners'],
            ['key' => 'subtitle', 'label' => 'Subtítulo', 'type' => 'text', 'default' => 'Convierte tu música en ingresos. Recomienda el catálogo oficial y gana comisiones.'],
            ['key' => 'show_levels', 'label' => 'Mostrar Sound League (5 niveles)', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_tiers', 'label' => 'Mostrar tramos de comisión', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_register', 'label' => 'Mostrar formulario de registro', 'type' => 'toggle', 'default' => true],
            ['key' => 'note', 'label' => 'Nota legal (revisión pendiente)', 'type' => 'textarea', 'default' => 'Programa en habilitación. Términos y condiciones pendientes de revisión legal. Las comisiones mostradas son de simulación y no constituyen una oferta vinculante.'],
        ];
    }

    public static function defaultConfig(): array
    {
        return ['enabled' => false, 'title' => 'SONIKA Artist Partners', 'subtitle' => 'Convierte tu música en ingresos. Recomienda el catálogo oficial y gana comisiones.', 'show_levels' => true, 'show_tiers' => true, 'show_register' => true, 'note' => 'Programa en habilitación. Términos y condiciones pendientes de revisión legal. Las comisiones mostradas son de simulación y no constituyen una oferta vinculante.'];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        if (empty($c['enabled'])) return '';

        $levels = [
            ['Semicorchea', 'Inicial', '♬'], ['Corchea', 'En crecimiento', '♪'],
            ['Negra', 'Estable', '♩'], ['Blanca', 'Avanzado', '𝅗𝅥'], ['Redonda', 'Élite', '𝅝'],
        ];
        $tiers = [['Hasta 499.999', '0,25%'], ['500.000 – 1.499.999', '0,50%'], ['1.500.000 – 2.999.999', '0,75%'], ['3.000.000 – 5.999.999', '1,00%'], ['6.000.000 – 9.999.999', '1,25%'], ['Desde 10.000.000', '1,50%']];

        $uid = 'ap' . substr(md5(uniqid('', true)), 0, 6);
        $h = '<style>'
            . '.ap{--ap1:#F20D16;--ap2:#101d3a;--apmint:#e5f5ef;background:linear-gradient(180deg,#fff,#f6fbf9);border-top:1px solid #eef2f0;border-bottom:1px solid #eef2f0;padding:56px 0}'
            . '.ap__in{max-width:1100px;margin:0 auto;padding:0 22px}'
            . '.ap__head{max-width:760px;margin:0 auto 30px;text-align:center}'
            . '.ap__kicker{display:inline-block;background:#fff;border:1px solid #f3c2c2;color:var(--ap1);border-radius:999px;padding:6px 14px;font-size:12px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;margin-bottom:14px}'
            . '.ap__title{font:800 clamp(1.8rem,4vw,2.8rem)/1.05 Sora,system-ui,sans-serif;color:var(--ap2);margin:0 0 12px;letter-spacing:-.02em}'
            . '.ap__sub{font-size:1.05rem;color:#51607a;margin:0}'
            . '.ap__grid{display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-top:30px}'
            . '.ap__card{background:#fff;border:1px solid #e9efec;border-radius:18px;padding:22px;box-shadow:0 10px 28px rgba(16,24,40,.06)}'
            . '.ap__card h3{font:800 16px Sora,system-ui;color:var(--ap2);margin:0 0 12px}'
            . '.ap__levels{list-style:none;margin:0;padding:0;display:grid;gap:8px}'
            . '.ap__levels li{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:10px;background:#f6fbf9;border:1px solid #eef2f0}'
            . '.ap__fig{width:30px;height:30px;border-radius:9px;background:var(--ap2);color:#fff;display:grid;place-items:center;font-size:15px}'
            . '.ap__lv-name{font-weight:800;color:var(--ap2);font-size:14px}.ap__lv-sub{font-size:11.5px;color:#64748b}'
            . '.ap__tiers{width:100%;border-collapse:collapse;font-size:13.5px}'
            . '.ap__tiers td{padding:7px 6px;border-bottom:1px dashed #eef2f0;color:#334155}.ap__tiers td:last-child{text-align:right;font-weight:800;color:#0d7a58}'
            . '.ap__form{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px}'
            . '.ap__form .full{grid-column:1/-1}'
            . '.ap__form input,.ap__form select{width:100%;padding:11px 12px;border:1px solid #dbe7e1;border-radius:11px;font:inherit;font-size:14px}'
            . '.ap__btn{grid-column:1/-1;padding:13px;border:0;border-radius:12px;background:var(--ap1);color:#fff;font-weight:800;font-size:15px;cursor:pointer}'
            . '.ap__btn:hover{filter:brightness(.95)}'
            . '.ap__accept{grid-column:1/-1;display:flex;gap:8px;align-items:flex-start;font-size:12.5px;color:#51607a;line-height:1.4}.ap__accept input{margin-top:2px}'
            . '.ap__msg{grid-column:1/-1;font-size:13px;font-weight:600;min-height:18px}'
            . '.ap__note{max-width:900px;margin:26px auto 0;text-align:center;font-size:12px;color:#94a3b8;line-height:1.5}'
            . '@media(max-width:800px){.ap__grid{grid-template-columns:1fr}}'
            . '</style>';
        $h .= '<section class="ap" id="' . $uid . '"><div class="ap__in">';
        $h .= '<div class="ap__head"><span class="ap__kicker">Artist Partners</span><h2 class="ap__title">' . $this->esc((string)$c['title']) . '</h2><p class="ap__sub">' . $this->esc((string)$c['subtitle']) . '</p></div>';
        $h .= '<div class="ap__grid">';

        if (!empty($c['show_levels'])) {
            $h .= '<div class="ap__card"><h3>SONIKA Sound League</h3><ul class="ap__levels">';
            foreach ($levels as $l) $h .= '<li><span class="ap__fig">' . $l[2] . '</span><span><span class="ap__lv-name">' . $this->esc($l[0]) . '</span><br><span class="ap__lv-sub">' . $this->esc($l[1]) . '</span></span></li>';
            $h .= '</ul></div>';
        }
        if (!empty($c['show_tiers'])) {
            $h .= '<div class="ap__card"><h3>Comisiones por tramo (mensual)</h3><table class="ap__tiers">';
            foreach ($tiers as $t) $h .= '<tr><td>' . $this->esc($t[0]) . '</td><td>' . $this->esc($t[1]) . '</td></tr>';
            $h .= '</table><p style="margin:10px 0 0;font-size:12px;color:#64748b">Cálculo progresivo. Valores en modo simulación.</p></div>';
        }
        $h .= '</div>';

        if (!empty($c['show_register'])) {
            $h .= '<div class="ap__card" style="max-width:760px;margin:22px auto 0"><h3>Únete al programa</h3>'
                . '<form class="ap__form" id="' . $uid . '-form">'
                . '<input name="full_name" placeholder="Nombre completo" required>'
                . '<input name="email" type="email" placeholder="Correo electrónico" required>'
                . '<input name="artist_name" placeholder="Nombre artístico">'
                . '<input name="public_name" placeholder="Nombre público (tienda)">'
                . '<input name="doc_type" placeholder="Tipo de documento (CC/CE)">'
                . '<input name="doc_number" placeholder="Número de documento">'
                . '<select name="music_instrument"><option value="">Instrumento principal</option><option>Guitarra eléctrica</option><option>Guitarra acústica</option><option>Bajo</option><option>Batería</option><option>Teclados</option><option>Voz</option><option>Otro</option></select>'
                . '<select name="music_level"><option value="">Nivel</option><option>Principiante</option><option>Intermedio</option><option>Avanzado</option><option>Profesional</option></select>'
                . '<input name="music_genre" placeholder="Género musical">'
                . '<input name="password" type="password" minlength="8" placeholder="Contraseña (mín. 8)" required autocomplete="new-password">'
                . '<label class="ap__accept"><input type="checkbox" name="accept_contract" value="1" required> Acepto los términos y condiciones del programa (versión vigente) y la política de tratamiento de datos.</label>'
                . '<button class="ap__btn" type="submit">Solicitar ingreso</button>'
                . '<div class="ap__msg" id="' . $uid . '-msg"></div>'
                . '</form></div>';
        }

        $h .= '<p class="ap__note">' . $this->esc((string)$c['note']) . '</p>';
        $h .= "</div></section>";
        $h .= '<script>(function(){var f=document.getElementById("' . $uid . '-form");if(!f)return;var m=document.getElementById("' . $uid . '-msg");f.addEventListener("submit",function(e){e.preventDefault();var d={};new FormData(f).forEach(function(v,k){if(v)d[k]=v;});m.style.color="#51607a";m.textContent="Enviando…";fetch("/api/v1/public/affiliates/register",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(d)}).then(function(r){return r.json();}).then(function(j){if(j.ok){m.style.color="#0d7a58";m.textContent="✅ Solicitud recibida. Revisaremos tu perfil y te contactaremos.";f.reset();}else{m.style.color="#d64545";m.textContent=j.message||"No se pudo enviar.";}}).catch(function(){m.style.color="#d64545";m.textContent="Error de conexión.";});});})();</script>';
        return $h;
    }
}
