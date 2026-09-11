<?php
namespace App\Widgets;

class WwiHeroWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'wwi-hero', 'name' => 'WWI: Hero', 'icon' => 'zap', 'category' => 'header', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'badge', 'label' => 'Badge', 'type' => 'text', 'default' => 'Tu sitio web profesional en 24 horas'],
            ['key' => 'title', 'label' => 'Titulo (usa | para resaltar en gradiente)', 'type' => 'text', 'default' => 'Tu negocio merece estar en Internet|. TIA lo construye.'],
            ['key' => 'subtitle', 'label' => 'Subtitulo', 'type' => 'textarea', 'default' => 'Compra. Cuéntanos quién eres. TIA construye tu sitio web con dominio, hosting, SSL, correos y SEO — sin programador, sin diseñador, sin agencia.'],
            ['key' => 'cta_primary', 'label' => 'CTA Principal', 'type' => 'text', 'default' => 'Quiero mi web'],
            ['key' => 'cta_primary_url', 'label' => 'CTA URL', 'type' => 'text', 'default' => '#planes'],
            ['key' => 'cta_secondary', 'label' => 'CTA Secundario', 'type' => 'text', 'default' => 'Consultar dominio'],
            ['key' => 'show_domain_checker', 'label' => 'Mostrar checker de dominio', 'type' => 'toggle', 'default' => true],
            ['key' => 'price_note', 'label' => 'Texto de precio', 'type' => 'text', 'default' => 'desde $299.000 COP · pago único'],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $titleParts = explode('|', (string)$c['title']);
        $t1 = $this->esc($titleParts[0] ?? '');
        $t2 = $this->esc($titleParts[1] ?? '');
        $html = '<section style="padding:170px 0 90px">';
        $html .= '<div class="wrap" style="text-align:center">';
        if ($c['badge']) $html .= '<div class="badge" style="margin-bottom:18px">' . $this->esc($c['badge']) . '</div>';
        $html .= '<h1 style="font-size:clamp(30px,5vw,50px);font-weight:800;letter-spacing:-.02em;line-height:1.12;max-width:780px;margin:0 auto 18px">' . $t1 . ($t2 ? ' <span class="gradient-text">' . $t2 . '</span>' : '') . '</h1>';
        $html .= '<p style="font-size:15px;color:var(--muted);max-width:620px;margin:0 auto 26px;line-height:1.7">' . $this->esc($c['subtitle']) . '</p>';
        $html .= '<div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-bottom:14px">';
        $html .= '<a class="btn btn-primary" id="wwi-start" style="padding:11px 22px;font-size:14px" href="' . $this->esc($c['cta_primary_url']) . '">' . $this->esc($c['cta_primary']) . '</a>';
        if ($c['cta_secondary']) $html .= '<a class="btn btn-ghost" style="padding:11px 22px;font-size:14px" href="#domain">' . $this->esc($c['cta_secondary']) . '</a>';
        $html .= '</div>';
        if ($c['price_note']) $html .= '<div class="mono" style="font-size:12px;color:var(--muted)">' . $this->esc($c['price_note']) . '</div>';
        if (!empty($c['show_domain_checker'])) {
            $html .= '<div class="panel" style="max-width:640px;margin:34px auto 0;padding:20px" id="domain">';
            $html .= '<div style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:12px">¿Tu dominio está libre?</div>';
            $html .= '<div class="domain-box"><input class="input" id="wwi-domain-input" placeholder="tunegocio.com" onkeydown="if(event.key===\'Enter\')wwiCheckDomain()"/><button class="btn btn-primary" onclick="wwiCheckDomain()">Buscar</button></div>';
            $html .= '<div class="domain-result" id="wwi-domain-result"></div>';
            $html .= '</div>';
        }
        $html .= '<div class="wwi-grid-3 wrap" style="margin-top:44px">';
        $html .= '<div class="panel stat"><div class="v">24h</div><div class="l">o antes, online</div></div>';
        $html .= '<div class="panel stat"><div class="v">0</div><div class="l">programadores necesarios</div></div>';
        $html .= '<div class="panel stat"><div class="v">TIA</div><div class="l">construye por ti</div></div>';
        $html .= '</div>';
        $sectors = ['Restaurantes', 'Abogados', 'Medicos', 'Inmobiliarias', 'Hoteles', 'Gimnasios', 'Consultores', 'Tiendas', 'Cafeterias', 'Veterinarias', 'Arquitectos', 'Transporte', 'Educacion', 'Eventos', 'Moda', 'Tecnologia', 'Agricultura', 'Belleza'];
        $html .= '<div class="marquee" style="max-width:1120px;margin:38px auto 0;border-radius:12px"><div class="track">';
        foreach (array_merge($sectors, $sectors) as $s) {
            $html .= '<span><b>◆</b> ' . $this->esc($s) . '</span>';
        }
        $html .= '</div></div>';
        $html .= '</div></section>';
        return $html;
    }
}
