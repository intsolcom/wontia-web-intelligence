<?php
namespace App\Widgets;

use App\Core\Lang;

/**
 * LanguageSwitcherWidget — brick "Selector de idioma" (categoría header).
 * Interruptor: izquierda = idioma base (ES), derecha = inglés (EN).
 * Guarda la preferencia en la cookie 'wwi_lang' y recarga. Multi-tenant.
 */
class LanguageSwitcherWidget extends Widget
{
    private static bool $assetsPrinted = false;

    public static function meta(): array
    {
        return ['id' => 'language-switcher', 'name' => 'Selector de idioma', 'icon' => 'globe', 'category' => 'header', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'label_left', 'label' => 'Etiqueta izquierda (idioma base)', 'type' => 'text', 'default' => 'ESP'],
            ['key' => 'label_right', 'label' => 'Etiqueta derecha', 'type' => 'text', 'default' => 'ENG'],
            ['key' => 'label_left_short', 'label' => 'Etiqueta corta izquierda (móvil)', 'type' => 'text', 'default' => 'ES'],
            ['key' => 'label_right_short', 'label' => 'Etiqueta corta derecha (móvil)', 'type' => 'text', 'default' => 'EN'],
        ];
    }

    public static function defaultConfig(): array
    {
        return ['label_left' => 'ESP', 'label_right' => 'ENG', 'label_left_short' => 'ES', 'label_right_short' => 'EN'];
    }

    public static function adminPreview(): string
    {
        return '<div style="padding:18px;border:1px dashed #ccc;border-radius:8px;color:#666">🌐 Selector de idioma — interruptor ESP | ENG.</div>';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $langs = Lang::available();
        if (count($langs) < 2) return '';
        $base = (string)$langs[0];
        $alt = (string)$langs[1];
        $cur = Lang::current();
        $leftOn = ($cur !== $alt);

        $lLeft = trim((string)($c['label_left'] ?? '')) ?: 'ESP';
        $lRight = trim((string)($c['label_right'] ?? '')) ?: 'ENG';
        $sLeft = trim((string)($c['label_left_short'] ?? '')) ?: 'ES';
        $sRight = trim((string)($c['label_right_short'] ?? '')) ?: 'EN';

        $h = $this->assets();
        $h .= '<div class="wwi-lang" role="group" aria-label="' . $this->esc('Idioma') . '">';
        $h .= '<button type="button" class="wwi-lang__opt' . ($leftOn ? ' is-on' : '') . '" data-wwi-lang="' . $this->esc($base) . '" aria-pressed="' . ($leftOn ? 'true' : 'false') . '" title="' . $this->esc($lLeft) . '"><span class="wwi-lang__full">' . $this->esc($lLeft) . '</span><span class="wwi-lang__short">' . $this->esc($sLeft) . '</span></button>';
        $h .= '<button type="button" class="wwi-lang__opt' . ($leftOn ? '' : ' is-on') . '" data-wwi-lang="' . $this->esc($alt) . '" aria-pressed="' . ($leftOn ? 'false' : 'true') . '" title="' . $this->esc($lRight) . '"><span class="wwi-lang__full">' . $this->esc($lRight) . '</span><span class="wwi-lang__short">' . $this->esc($sRight) . '</span></button>';
        $h .= '</div>';
        return $h;
    }

    private function assets(): string
    {
        if (self::$assetsPrinted) return '';
        self::$assetsPrinted = true;
        return <<<'HTML'
<style>
.wwi-lang{display:inline-flex;align-items:center;gap:2px;padding:3px;border:1px solid var(--line,#E6E6E6);border-radius:11px;background:var(--soft,#F5F5F5)}
.wwi-lang__opt{border:0;background:transparent;color:var(--muted,#777);font:inherit;font-size:11px;font-weight:700;letter-spacing:.04em;padding:6px 10px;border-radius:8px;cursor:pointer;line-height:1;transition:background .15s ease,color .15s ease}
.wwi-lang__opt:hover{color:var(--ink,#111)}
.wwi-lang__opt.is-on{background:var(--brand,#111);color:#fff}
.wwi-lang__opt:focus-visible{outline:2px solid var(--brand,#111);outline-offset:2px}
.wwi-lang__short{display:none}
@media(max-width:760px){.wwi-lang__full{display:none}.wwi-lang__short{display:inline}}
@media(prefers-reduced-motion:reduce){.wwi-lang__opt{transition:none}}
</style>
<script>if(!window.wwiLangSet){window.wwiLangSet=function(l){document.cookie='wwi_lang='+l+';path=/;max-age=31536000';location.reload();};
document.addEventListener('click',function(e){var b=e.target&&e.target.closest?e.target.closest('[data-wwi-lang]'):null;if(!b)return;e.preventDefault();window.wwiLangSet(b.getAttribute('data-wwi-lang'));});}</script>
HTML;
    }
}
