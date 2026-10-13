<?php
namespace App\Widgets;

use App\Core\Session;

/**
 * AccountWidget — brick "Mi Cuenta" (botón de cuenta del header).
 * Reemplaza el botón hardcodeado del header de los themes. Configurable por CMS
 * (etiqueta, enlace, icono, mostrar etiqueta) y consciente de la sesión
 * (si hay sesión, muestra el nombre del usuario). Estado: INCUBADORA.
 */
class AccountWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'account', 'name' => 'Mi Cuenta', 'icon' => 'user', 'category' => 'header', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'label', 'label' => 'Etiqueta', 'type' => 'text', 'default' => 'Mi cuenta'],
            ['key' => 'url', 'label' => 'Enlace (cuenta/login)', 'type' => 'text', 'default' => '/cuenta'],
            ['key' => 'icon', 'label' => 'Icono (emoji)', 'type' => 'text', 'default' => '👤'],
            ['key' => 'show_label', 'label' => 'Mostrar etiqueta', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_name', 'label' => 'Mostrar nombre del usuario si hay sesión', 'type' => 'toggle', 'default' => true],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $label = (string)($c['label'] ?? 'Mi cuenta');
        $url = (string)($c['url'] ?? '/cuenta');
        $icon = (string)($c['icon'] ?? '👤');
        $showLabel = !empty($c['show_label']);

        if (!empty($c['show_name'])) {
            $u = Session::user();
            if (is_array($u) && !empty($u['username'])) $label = (string)$u['username'];
        }

        $html = '<a class="icon-btn" href="' . $this->esc($url) . '"' . ($showLabel ? '' : ' aria-label="' . $this->esc($label) . '"') . '>';
        $html .= '<span class="ic" aria-hidden="true">' . $this->esc($icon) . '</span>';
        if ($showLabel && $label !== '') $html .= '<span class="lbl">' . $this->esc($label) . '</span>';
        $html .= '</a>';
        return $html;
    }
}
