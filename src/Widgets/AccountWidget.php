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
        return ['id' => 'account', 'name' => 'Mi Cuenta', 'icon' => 'user', 'category' => 'header', 'version' => '1.1.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'label', 'label' => 'Etiqueta (invitado)', 'type' => 'text', 'default' => 'Mi cuenta'],
            ['key' => 'login_label', 'label' => 'Etiqueta (sin sesión)', 'type' => 'text', 'default' => 'Iniciar sesión'],
            ['key' => 'url', 'label' => 'Enlace de cuenta', 'type' => 'text', 'default' => '/cuenta'],
            ['key' => 'icon', 'label' => 'Icono (emoji)', 'type' => 'text', 'default' => '👤'],
            ['key' => 'show_label', 'label' => 'Mostrar etiqueta', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_menu', 'label' => 'Menú desplegable', 'type' => 'toggle', 'default' => true],
        ];
    }

    public function render(array $config = []): string
    {
        $cfg = $this->mergeConfig($config);
        $url = (string)($cfg['url'] ?? '/cuenta');
        $icon = (string)($cfg['icon'] ?? '👤');
        $showLabel = !empty($cfg['show_label']);
        $showMenu = !empty($cfg['show_menu']);

        $cust = null;
        try { $cust = (new CustomerService())->current(); } catch (\Throwable $e) {}
        $logged = is_array($cust) && !empty($cust['id']);
        $label = $logged
            ? (string)($cust['name'] ?: $cust['email'])
            : (string)($cfg['login_label'] ?? $cfg['label'] ?? 'Iniciar sesión');

        $h = '';
        if ($showMenu) $h .= $this->assets();
        $h .= '<div class="ska-acc" data-logged="' . ($logged ? '1' : '0') . '">';
        $h .= '<a class="icon-btn" href="' . $this->esc($url) . '"' . ($showLabel ? '' : ' aria-label="' . $this->esc($label) . '"') . ($showMenu ? ' aria-haspopup="true"' : '') . '>';
        $h .= '<span class="ic" aria-hidden="true">' . $this->esc($icon) . '</span>';
        if ($showLabel && $label !== '') $h .= '<span class="lbl">' . $this->esc($label) . '</span>';
        $h .= '</a>';
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
.ska-acc{position:relative;display:inline-flex}
.ska-acc-menu{position:absolute;right:0;top:calc(100% + 8px);min-width:180px;background:#fff;border:1px solid #E6E6E6;border-radius:12px;box-shadow:0 18px 44px rgba(0,0,0,.14);padding:6px;opacity:0;visibility:hidden;transform:translateY(6px);transition:.16s;z-index:150}
.ska-acc:hover .ska-acc-menu,.ska-acc:focus-within .ska-acc-menu{opacity:1;visibility:visible;transform:none}
.ska-acc-menu a{display:block;padding:9px 12px;border-radius:8px;color:#111;font-size:13px;font-weight:600;text-decoration:none}
.ska-acc-menu a:hover{background:#F5F5F5;color:#F20D16}
</style>
<script>if(!window.skaLogout){window.skaLogout=function(){fetch('/api/v1/public/account/logout',{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'}).then(function(){window.location.reload()}).catch(function(){window.location.reload()})};}</script>
HTML;
    }
}
