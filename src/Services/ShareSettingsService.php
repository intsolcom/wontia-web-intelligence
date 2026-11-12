<?php
namespace App\Services;

use App\Core\Database;

/**
 * Configuración administrable de "Compartir producto".
 * Multi-tenant. Se guarda como JSON en settings (site_id, key).
 */
class ShareSettingsService
{
    public const KEY = 'sonika_share_config';
    private static ?array $cache = null;

    public function defaults(): array
    {
        return [
            'enabled' => 1,
            'enabled_cart' => 1,
            'enabled_product' => 1,
            'show_unavailable' => 0,

            'button_height' => 48,
            'button_height_product' => 60,
            'button_radius' => 12,
            'button_font_size' => 12,
            'button_font_size_product' => 13,
            'button_full_width' => 1,
            'button_shadow' => 1,
            'button_bg' => '#ffffff',
            'button_text' => '#111111',
            'button_border' => '#ddd9d0',
            'button_sub' => '#6f6b63',
            'partner_bg' => '#111111',
            'partner_text' => '#ffffff',
            'partner_accent' => '#ff5a61',
            'partner_sub' => '#fca5a5',
            'card_bg' => '#faf9f6',
            'card_border' => '#e7e5df',
            'card_radius' => 16,

            'modal_accent' => '#F20D16',
            'modal_ink' => '#111111',
            'modal_muted' => '#6b7280',
            'modal_soft' => '#6f6b63',
            'modal_bg' => '#faf9f6',
            'modal_line' => '#e7e5df',
            'modal_dialog_bg' => '#ffffff',

            'button_friend_label' => 'Compartir',
            'button_friend_sub' => 'Con un amigo',
            'button_partner_label' => 'Comparte y gana',
            'button_partner_sub' => 'Partner SONIKA',
            'partner_note_mode' => 'auto',
            'partner_note_text' => '',

            'channels' => [
                'whatsapp' => 1,
                'whatsapp_status' => 1,
                'instagram_story' => 1,
                'facebook' => 1,
                'facebook_story' => 1,
                'email' => 1,
                'copy_link' => 1,
                'native_share' => 1,
            ],

            'show_composer' => 1,
            'show_link_box' => 1,
            'show_templates' => 1,
            'show_commission' => 1,
            'show_points' => 1,

            'image_format' => 'story',
            'image_bg' => '#F6F6F3',
            'image_ink' => '#111111',
            'image_accent' => '#F20D16',
            'image_muted' => '#6f6b63',
            'image_brand' => 'SONIKA',
            'image_cta' => 'Compra en sonika.com.co',
            'image_footer' => 'Envíos a toda Colombia · Garantía SONIKA',
            'image_show_link' => 1,
            'image_link_label_friend' => 'ENLACE DEL PRODUCTO',
            'image_link_label_partner' => 'TU ENLACE DE PARTNER',

            'friend_title' => 'Una recomendación que vale la pena.',
            'friend_sub' => 'Elige el canal. El mensaje ya está listo; tú decides cómo enviarlo.',
            'partner_title' => 'Tu recomendación tiene recompensa.',
            'partner_sub' => 'Comparte con tu enlace. Tú pones la recomendación; SONIKA pone la tienda.',
            'join_title' => 'Comparte lo que amas. Gana por hacerlo.',
            'join_sub' => 'Tu recomendación puede convertirse en una comisión. Así funciona el programa.',
            'pending_title' => 'Tu perfil está en revisión.',
            'pending_sub' => 'Cuando tu solicitud sea aprobada, tus enlaces activarán comisiones automáticamente.',
            'support_line' => 'Comprando con mi enlace apoyas mi proyecto musical.',

            'default_template' => 'directo',
            'template_directo_friend' => 'Mira este {title} en SONIKA ({price}). Te lo recomiendo: {url}',
            'template_directo_partner' => 'Mira este {title} en SONIKA ({price}). Lo elegí para ti: {url}',
            'template_curioso_friend' => '¿Ya viste este {title}? Está en SONIKA por {price}. Míralo aquí: {url}',
            'template_curioso_partner' => '¿Ya viste este {title}? Está en SONIKA por {price}. Te dejo mi enlace: {url}',
            'template_recomiendo_friend' => 'Te recomiendo este {title}. Lo encuentras en SONIKA por {price}: {url}',
            'template_recomiendo_partner' => 'Te recomiendo este {title} de SONIKA. Sé que te va a gustar: {url}',
        ];
    }

    public function get(): array
    {
        if (self::$cache !== null) return self::$cache;
        $saved = [];
        try {
            $st = Database::instance()->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = ? LIMIT 1");
            $st->execute([self::KEY]);
            $raw = $st->fetchColumn();
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) $saved = $decoded;
            }
        } catch (\Throwable $e) {
        }
        self::$cache = $this->sanitize($this->merge($this->defaults(), $saved));
        return self::$cache;
    }

    public function save(array $input): array
    {
        $current = $this->get();
        $merged = $this->merge($current, $input);
        $clean = $this->sanitize($merged);
        try {
            Database::instance()->prepare(
                "INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, ?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
            )->execute([self::KEY, json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo guardar la configuración'];
        }
        self::$cache = null;
        return ['ok' => true, 'data' => $this->get()];
    }

    public function reset(): array
    {
        try {
            Database::instance()->prepare("DELETE FROM settings WHERE site_id = @site_id AND `key` = ?")->execute([self::KEY]);
        } catch (\Throwable $e) {
        }
        self::$cache = null;
        return ['ok' => true, 'data' => $this->get()];
    }

    public function enabledFor(string $context = 'cart'): bool
    {
        $s = $this->get();
        if (empty($s['enabled'])) return false;
        if ($context === 'cart' && empty($s['enabled_cart'])) return false;
        if ($context === 'product' && empty($s['enabled_product'])) return false;
        return true;
    }

    /** Variables CSS que aplican la apariencia configurada sin tocar el CSS base. */
    public function cssVars(): array
    {
        $s = $this->get();
        return [
            '--skshare-btn-radius' => $s['button_radius'] . 'px',
            '--skshare-btn-height' => $s['button_height'] . 'px',
            '--skshare-btn-height-product' => $s['button_height_product'] . 'px',
            '--skshare-btn-font' => $s['button_font_size'] . 'px',
            '--skshare-btn-font-product' => $s['button_font_size_product'] . 'px',
            '--skshare-btn-width' => !empty($s['button_full_width']) ? '100%' : 'auto',
            '--skshare-btn-justify' => !empty($s['button_full_width']) ? 'stretch' : 'start',
            '--skshare-btn-bg' => $s['button_bg'],
            '--skshare-btn-fg' => $s['button_text'],
            '--skshare-btn-border' => $s['button_border'],
            '--skshare-btn-sub' => $s['button_sub'],
            '--skshare-partner-bg' => $s['partner_bg'],
            '--skshare-partner-fg' => $s['partner_text'],
            '--skshare-partner-accent' => $s['partner_accent'],
            '--skshare-partner-sub' => $s['partner_sub'],
            '--skshare-card-bg' => $s['card_bg'],
            '--skshare-card-border' => $s['card_border'],
            '--skshare-card-radius' => $s['card_radius'] . 'px',
            '--skshare-shadow' => !empty($s['button_shadow']) ? '0 8px 18px rgba(17,17,17,.12)' : 'none',
            '--skshare-modal-accent' => $s['modal_accent'],
            '--skshare-modal-ink' => $s['modal_ink'],
            '--skshare-modal-muted' => $s['modal_muted'],
            '--skshare-modal-soft' => $s['modal_soft'],
            '--skshare-modal-bg' => $s['modal_bg'],
            '--skshare-modal-line' => $s['modal_line'],
            '--skshare-modal-dialog-bg' => $s['modal_dialog_bg'],
        ];
    }

    private function merge(array $base, array $input): array
    {
        $out = $base;
        foreach ($input as $key => $value) {
            if ($key === 'channels' && is_array($value)) {
                $out['channels'] = array_merge((array)($base['channels'] ?? []), $value);
            } elseif (is_scalar($value) || $value === null) {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    private function sanitize(array $in): array
    {
        $d = $this->defaults();
        $out = $d;
        $bools = [
            'enabled', 'enabled_cart', 'enabled_product', 'show_unavailable',
            'button_full_width', 'button_shadow',
            'show_composer', 'show_link_box', 'show_templates', 'show_commission', 'show_points',
            'image_show_link',
        ];
        foreach ($bools as $k) $out[$k] = !empty($in[$k]) ? 1 : 0;

        $out['button_height'] = $this->clamp($in['button_height'] ?? $d['button_height'], 36, 86);
        $out['button_height_product'] = $this->clamp($in['button_height_product'] ?? $d['button_height_product'], 40, 96);
        $out['button_radius'] = $this->clamp($in['button_radius'] ?? $d['button_radius'], 4, 28);
        $out['button_font_size'] = $this->clamp($in['button_font_size'] ?? $d['button_font_size'], 10, 20);
        $out['button_font_size_product'] = $this->clamp($in['button_font_size_product'] ?? $d['button_font_size_product'], 10, 22);
        $out['card_radius'] = $this->clamp($in['card_radius'] ?? $d['card_radius'], 0, 28);

        $colors = [
            'button_bg', 'button_text', 'button_border', 'button_sub',
            'partner_bg', 'partner_text', 'partner_accent', 'partner_sub',
            'card_bg', 'card_border',
            'modal_accent', 'modal_ink', 'modal_muted', 'modal_soft', 'modal_bg', 'modal_line', 'modal_dialog_bg',
            'image_bg', 'image_ink', 'image_accent', 'image_muted',
        ];
        foreach ($colors as $k) $out[$k] = $this->color($in[$k] ?? $d[$k], $d[$k]);

        $texts = [
            'button_friend_label' => 40, 'button_friend_sub' => 80,
            'button_partner_label' => 40, 'button_partner_sub' => 80,
            'partner_note_text' => 120,
            'image_brand' => 40, 'image_cta' => 120, 'image_footer' => 160,
            'image_link_label_friend' => 60, 'image_link_label_partner' => 60,
            'friend_title' => 200, 'friend_sub' => 300,
            'partner_title' => 200, 'partner_sub' => 300,
            'join_title' => 200, 'join_sub' => 300,
            'pending_title' => 200, 'pending_sub' => 300,
            'support_line' => 200,
            'template_directo_friend' => 600, 'template_directo_partner' => 600,
            'template_curioso_friend' => 600, 'template_curioso_partner' => 600,
            'template_recomiendo_friend' => 600, 'template_recomiendo_partner' => 600,
        ];
        foreach ($texts as $k => $max) $out[$k] = $this->text($in[$k] ?? $d[$k], $max, $d[$k]);

        $out['partner_note_mode'] = $this->choice($in['partner_note_mode'] ?? $d['partner_note_mode'], ['auto', 'commission', 'rate', 'custom', 'none'], $d['partner_note_mode']);
        $out['image_format'] = $this->choice($in['image_format'] ?? $d['image_format'], ['story', 'square'], $d['image_format']);
        $out['default_template'] = $this->choice($in['default_template'] ?? $d['default_template'], ['directo', 'curioso', 'recomiendo'], $d['default_template']);

        $channels = is_array($in['channels'] ?? null) ? $in['channels'] : [];
        $out['channels'] = [];
        foreach ($d['channels'] as $k => $v) {
            $out['channels'][$k] = !empty($channels[$k]) ? 1 : 0;
        }
        return $out;
    }

    private function clamp($v, int $min, int $max): int
    {
        $n = (int)$v;
        return max($min, min($max, $n));
    }

    private function color($v, string $default): string
    {
        $v = trim((string)$v);
        return preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?([0-9a-f]{2})?$/i', $v) ? $v : $default;
    }

    private function text($v, int $max, string $default): string
    {
        $v = trim((string)$v);
        if ($v === '') return $default;
        return mb_substr($v, 0, $max);
    }

    private function choice($v, array $allowed, string $default): string
    {
        return in_array($v, $allowed, true) ? (string)$v : $default;
    }
}
