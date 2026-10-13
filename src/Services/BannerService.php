<?php
namespace App\Services;

use App\Core\Database;

/**
 * BannerService — fuente ÚNICA de configuración del brick Banner (global del sitio).
 * Guarda la config completa en settings('banner_global') como JSON.
 * Migra una vez desde los antiguos `theme.<slug>.topbar_*`.
 */
class BannerService
{
    public const SETTING = 'banner_global';

    private function setting(string $key, $def = '')
    {
        try {
            $st = Database::instance()->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = ? LIMIT 1");
            $st->execute([$key]);
            $v = $st->fetchColumn();
            return ($v === false || $v === null) ? $def : $v;
        } catch (\Throwable $e) {
            return $def;
        }
    }

    private function setSetting(string $key, string $value): void
    {
        $db = Database::instance();
        $c = $db->prepare("SELECT COUNT(*) FROM settings WHERE site_id = @site_id AND `key` = ?");
        $c->execute([$key]);
        if ((int)$c->fetchColumn() === 0) $db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, ?, ?)")->execute([$key, $value]);
        else $db->prepare("UPDATE settings SET `value` = ? WHERE site_id = @site_id AND `key` = ?")->execute([$value, $key]);
    }

    public function defaults(): array
    {
        return [
            'mode' => 'bar', 'animation' => 'fade', 'rotation_ms' => 5000, 'letters' => 'none',
            'items' => [
                ['icon' => '🚚', 'text' => 'Envíos a toda Colombia', 'image' => '', 'link' => '', 'link_label' => ''],
                ['icon' => '🛡️', 'text' => 'Compra 100% segura', 'image' => '', 'link' => '', 'link_label' => ''],
                ['icon' => '✅', 'text' => 'Garantía oficial', 'image' => '', 'link' => '', 'link_label' => ''],
            ],
            'bg_color' => '#111111', 'bg_color2' => '', 'text_color' => '#ffffff', 'font_size' => '0.8', 'preset' => 'none',
            'bold' => true, 'italic' => false, 'align' => 'center', 'bg_image' => '', 'overlay' => '55', 'bare' => true,
            'dismissible' => false, 'close_label' => 'Entendido', 'frequency' => 'always',
            'marquee' => false, 'critical' => false, 'countdown_to' => '', 'exit_intent' => false, 'track' => true, 'ab' => false,
            'device' => 'all', 'audience' => 'all', 'pages' => '', 'days' => '', 'from' => '', 'to' => '', 'start_at' => '', 'end_at' => '', 'tz_offset' => '-5',
        ];
    }

    public function getGlobal(): array
    {
        $raw = (string)$this->setting(self::SETTING, '');
        if ($raw === '') {
            $migrated = $this->migrateFromTheme();
            if ($migrated !== null) {
                $this->saveGlobal($migrated);
                return $migrated;
            }
            return [];
        }
        $cfg = json_decode($raw, true);
        return is_array($cfg) ? $cfg : [];
    }

    public function saveGlobal(array $cfg): array
    {
        $this->setSetting(self::SETTING, json_encode($cfg, JSON_UNESCAPED_UNICODE));
        return ['ok' => true];
    }

    private function migrateFromTheme(): ?array
    {
        $slug = 'default';
        try {
            $slug = (string)(Database::instance()->query("SELECT theme FROM sites WHERE id = @site_id")->fetchColumn() ?: 'default');
        } catch (\Throwable $e) {
        }
        $slug = preg_replace('/[^a-z0-9_-]/i', '', $slug) ?: 'default';
        $get = fn(string $k, string $d = '') => (string)$this->setting('theme.' . $slug . '.' . $k, $d);

        $itemsRaw = $get('topbar_items', '');
        $hasAny = $itemsRaw !== '' || $get('topbar_bg_color', '') !== '' || $get('topbar_bg_image', '') !== '' || $get('topbar_text_color', '') !== '';
        if (!$hasAny) return null;

        $items = json_decode($itemsRaw, true);
        return [
            'mode' => 'bar',
            'items' => is_array($items) && $items ? $items : $this->defaults()['items'],
            'animation' => $get('topbar_animation', 'fade'),
            'rotation_ms' => (int)$get('topbar_rotation_ms', '5000'),
            'bg_color' => $get('topbar_bg_color', '#111111'),
            'text_color' => $get('topbar_text_color', '#ffffff'),
            'font_size' => $get('topbar_font_size', '0.8'),
            'bold' => $get('topbar_bold', '1') !== '0',
            'frequency' => $get('topbar_frequency', 'always'),
            'bare' => true,
        ];
    }
}
