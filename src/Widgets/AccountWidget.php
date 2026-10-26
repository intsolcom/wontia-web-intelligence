<?php
namespace App\Widgets;

use App\Services\CustomerService;

/**
 * AccountWidget — brick "Mi Cuenta" (Ecommerce).
 * Estado de sesión + menú desplegable (Mi cuenta / Mis pedidos / Favoritos / Cerrar sesión).
 * Configurable por CMS. Acoplado en el header. Estado: INCUBADORA.
 */
class AccountWidget extends Widget
{
    private static bool $assetsPrinted = false;

    public static function meta(): array
    {
        return ['id' => 'account', 'name' => 'Mi Cuenta', 'icon' => 'user', 'category' => 'header', 'version' => '1.2.1'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => '_h_display', 'label' => 'Presentación del botón', 'type' => 'heading'],
            ['key' => 'display', 'label' => 'Cómo se muestra', 'type' => 'select', 'options' => ['text' => 'Botón con el texto "Mi cuenta"', 'avatar' => 'Avatar de usuario'], 'default' => 'text'],
            ['key' => 'label', 'label' => 'Texto del botón', 'type' => 'text', 'default' => 'Mi cuenta'],
            ['key' => 'show_name', 'label' => 'Mostrar el nombre del usuario con sesión', 'type' => 'toggle', 'default' => false],
            ['key' => 'avatar_image', 'label' => 'Imagen de avatar (opcional; si no, inicial o silueta)', 'type' => 'image', 'default' => ''],
            ['key' => 'url', 'label' => 'Enlace de cuenta', 'type' => 'text', 'default' => '/cuenta'],
            ['key' => '_h_menu', 'label' => 'Menú desplegable', 'type' => 'heading'],
            ['key' => 'show_menu', 'label' => 'Mostrar menú desplegable', 'type' => 'toggle', 'default' => true],
            ['key' => 'menu_align', 'label' => 'Alineación del menú', 'type' => 'select', 'options' => ['right' => 'Derecha', 'left' => 'Izquierda'], 'default' => 'right'],
            ['key' => 'hide_label_mobile', 'label' => 'En móvil: mostrar solo el icono', 'type' => 'toggle', 'default' => false],
        ];
    }

    public function render(array $config = []): string
    {
        $cfg = $this->mergeConfig($config);
        $url = (string)($cfg['url'] ?? '/cuenta');
        $showMenu = !empty($cfg['show_menu']);
        $display = ((string)($cfg['display'] ?? 'text') === 'avatar') ? 'avatar' : 'text';
        $showName = !empty($cfg['show_name']);
        $align = ((string)($cfg['menu_align'] ?? 'right') === 'left') ? 'left' : 'right';
        $hideMobile = !empty($cfg['hide_label_mobile']);
        $label = trim((string)($cfg['label'] ?? 'Mi cuenta'));
        if ($label === '') $label = 'Mi cuenta';

        $cust = null;
        try { $cust = (new CustomerService())->current(); } catch (\Throwable $e) {}
        $logged = is_array($cust) && !empty($cust['id']);
        $name = $logged ? trim((string)($cust['name'] ?: ($cust['email'] ?? ''))) : '';
        $aria = $name !== '' ? $name : $label;

        $h = '';
        $h .= $this->assets();
        $h .= '<div class="ska-acc" data-logged="' . ($logged ? '1' : '0') . '" data-align="' . $align . '">';

        if ($display === 'avatar') {
            $img = trim((string)($cfg['avatar_image'] ?? ''));
            if ($img !== '') {
                $inner = '<img class="ska-acc-ava-img" src="' . $this->esc($img) . '" alt="' . $this->esc($aria) . '"/>';
            } elseif ($logged && $name !== '') {
                $initial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr($name, 0, 1));
                $inner = '<span class="ska-acc-ava-ini" aria-hidden="true">' . $this->esc($initial) . '</span>';
            } else {
                $inner = '<svg class="ska-acc-ava-svg" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8.5" r="4"/><path d="M4.5 20.5c0-4.1 3.4-6.5 7.5-6.5s7.5 2.4 7.5 6.5"/></svg>';
            }
            $h .= '<a class="icon-btn ska-acc-btn ska-acc-btn-avatar" href="' . $this->esc($url) . '" aria-label="' . $this->esc($aria) . '"'
                . ($showMenu ? ' aria-haspopup="true"' : '') . '>' . $inner . '</a>';
        } else {
            $txt = ($showName && $name !== '') ? $name : $label;
            $h .= '<a class="icon-btn ska-acc-btn ska-acc-btn-text" href="' . $this->esc($url) . '" aria-label="' . $this->esc($txt) . '"'
                . ($showMenu ? ' aria-haspopup="true"' : '') . '>'
                . '<span class="ska-acc-txt' . ($hideMobile ? ' ska-acc-txt-hm' : '') . '">' . $this->esc($txt) . '</span>'
                . '<svg class="ska-acc-caret" width="11" height="11" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                . '</a>';
        }

        if ($showMenu) {
            $h .= '<div class="ska-acc-menu" role="menu">';
            if ($logged) {
                $h .= '<a role="menuitem" href="/cuenta">Mi cuenta</a>';
                $h .= '<a role="menuitem" href="/cuenta?tab=orders">Mis pedidos</a>';
                $h .= '<a role="menuitem" href="/cuenta?tab=favorites">Favoritos</a>';
                $h .= '<a role="menuitem" href="#" onclick="skaLogout();return false">Cerrar sesión</a>';
            } else {
                $h .= '<a role="menuitem" href="/cuenta">Iniciar sesión</a>';
                $h .= '<a role="menuitem" href="/cuenta?tab=register">Crear cuenta</a>';
            }
            $h .= '</div>';
        }
        $h .= '</div>';
        return $h;
    }

    private function assets(): string
    {
        if (self::$assetsPrinted) return '';
        self::$assetsPrinted = true;
        return <<<'HTML'
<style>
.ska-acc{position:relative;display:inline-flex;align-items:center}
.ska-acc-btn-text{display:inline-flex;align-items:center;gap:6px;padding:8px 13px;border:1px solid #E6E6E6;border-radius:999px;font-size:13px;font-weight:600;line-height:1;white-space:nowrap;max-width:220px;text-decoration:none;color:inherit;background:#fff}
.ska-acc-btn-text:hover{border-color:var(--brand,#F20D16);color:var(--brand,#F20D16)}
.ska-acc-txt{overflow:hidden;text-overflow:ellipsis}
.ska-acc-caret{flex:0 0 auto;opacity:.65}
.ska-acc-btn-avatar{display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:11px;overflow:hidden;text-decoration:none;background:#fff;border:1px solid #D9DCE3;color:#5B6270;line-height:1;transition:border-color .15s,color .15s}
.ska-acc-btn-avatar:hover{border-color:#8A94A6;color:#232A36}
.ska-acc-btn-avatar .ska-acc-ava-svg{width:22px;height:22px;fill:none;stroke:#5B6270;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.ska-acc-btn-avatar:hover .ska-acc-ava-svg{stroke:#232A36}
.ska-acc-ava-img{width:100%;height:100%;object-fit:cover;display:block}
.ska-acc-ava-ini{display:inline-flex;align-items:center;justify-content:center;width:100%;height:100%;font-weight:800;font-size:16px;color:#3A4150}
.ska-acc-menu{position:absolute;right:0;top:calc(100% + 8px);min-width:180px;background:#fff;border:1px solid #E6E6E6;border-radius:12px;box-shadow:0 18px 44px rgba(0,0,0,.14);padding:6px;opacity:0;visibility:hidden;transform:translateY(6px);transition:.16s;z-index:150}
@media(max-width:900px){.ska-acc-menu{position:fixed;left:12px;right:12px;bottom:12px;top:auto;width:auto;min-width:0;max-height:70vh;overflow:auto;border-radius:16px;z-index:9995}.ska-acc-menu a{padding:13px 14px;font-size:15px}}
.ska-acc[data-align="left"] .ska-acc-menu{right:auto;left:0}
.ska-acc:hover .ska-acc-menu,.ska-acc:focus-within .ska-acc-menu{opacity:1;visibility:visible;transform:none}
.ska-acc-menu a{display:block;padding:9px 12px;border-radius:8px;color:#111;font-size:13px;font-weight:600;text-decoration:none}
.ska-acc-menu a:hover{background:#F5F5F5;color:#F20D16}
.ska-acc-btn:focus-visible{outline:2px solid var(--brand,#F20D16);outline-offset:2px}
@media(max-width:640px){.ska-acc-txt-hm{display:none}.ska-acc-btn-text{padding:8px 10px}}
@media(prefers-reduced-motion:reduce){.ska-acc-menu{transition:none}}
</style>
<script>if(!window.skaLogout){window.skaLogout=function(){fetch('/api/v1/public/account/logout',{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'}).then(function(){window.location.reload()}).catch(function(){window.location.reload()})};}</script>
HTML;
    }
}
