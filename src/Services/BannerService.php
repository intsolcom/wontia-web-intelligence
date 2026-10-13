<?php
namespace App\Services;

use App\Core\Database;

/**
 * BannerService — biblioteca de banners del sitio (multi-tenant) + barra superior.
 * Un registro por banner: nombre, enabled, is_header (cuál va en la barra superior) y config JSON.
 * Migra una vez desde el antiguo setting `banner_global` / `theme.<slug>.topbar_*`.
 */
class BannerService
{
    private static bool $ensured = false;

    public function ensureTables(): void
    {
        if (self::$ensured) return;
        Database::instance()->exec("CREATE TABLE IF NOT EXISTS wwi_banners (
            id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1,
            name VARCHAR(150) NOT NULL, enabled TINYINT NOT NULL DEFAULT 1, is_header TINYINT NOT NULL DEFAULT 0,
            config JSON, sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_site (site_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ensured = true;
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

    // ── settings helpers (migración) ──
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

    private function ensureSeed(): void
    {
        $this->ensureTables();
        $db = Database::instance();
        $c = $db->prepare("SELECT COUNT(*) FROM wwi_banners WHERE site_id = @site_id");
        $c->execute();
        if ((int)$c->fetchColumn() > 0) return;

        $cfg = json_decode((string)$this->setting('banner_global', ''), true);
        if (!is_array($cfg) || !$cfg) {
            $cfg = $this->migrateFromTheme();
        }
        if (!is_array($cfg) || !$cfg) $cfg = $this->defaults();

        $db->prepare("INSERT INTO wwi_banners (site_id, name, enabled, is_header, config) VALUES (@site_id, 'Barra superior', 1, 1, ?)")
            ->execute([json_encode($cfg, JSON_UNESCAPED_UNICODE)]);
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
        if ($get('topbar_items', '') === '' && $get('topbar_bg_color', '') === '') return null;
        $items = json_decode($get('topbar_items', ''), true);
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

    // ── CRUD ──
    public function list(): array
    {
        $this->ensureSeed();
        $st = Database::instance()->prepare("SELECT id, name, enabled, is_header, updated_at FROM wwi_banners WHERE site_id = @site_id ORDER BY is_header DESC, sort_order ASC, id ASC");
        $st->execute();
        return $st->fetchAll();
    }

    public function get(int $id): ?array
    {
        $this->ensureSeed();
        $st = Database::instance()->prepare("SELECT * FROM wwi_banners WHERE id = ? AND site_id = @site_id LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) return null;
        $cfg = json_decode((string)$row['config'], true);
        $row['config'] = is_array($cfg) ? $cfg : [];
        return $row;
    }

    public function create(string $name): array
    {
        $this->ensureSeed();
        $name = mb_substr(trim($name), 0, 150) ?: 'Nuevo banner';
        Database::instance()->prepare("INSERT INTO wwi_banners (site_id, name, enabled, is_header, config) VALUES (@site_id, ?, 1, 0, ?)")
            ->execute([$name, json_encode($this->defaults(), JSON_UNESCAPED_UNICODE)]);
        return ['ok' => true, 'id' => (int)Database::instance()->lastInsertId()];
    }

    public function save(int $id, string $name, array $config): array
    {
        $this->ensureSeed();
        if (!$this->get($id)) return ['ok' => false, 'message' => 'Banner no encontrado'];
        Database::instance()->prepare("UPDATE wwi_banners SET name = ?, config = ? WHERE id = ? AND site_id = @site_id")
            ->execute([mb_substr(trim($name), 0, 150) ?: 'Banner', json_encode($config, JSON_UNESCAPED_UNICODE), $id]);
        return ['ok' => true];
    }

    public function duplicate(int $id): array
    {
        $b = $this->get($id);
        if (!$b) return ['ok' => false, 'message' => 'Banner no encontrado'];
        Database::instance()->prepare("INSERT INTO wwi_banners (site_id, name, enabled, is_header, config) VALUES (@site_id, ?, 0, 0, ?)")
            ->execute([mb_substr($b['name'] . ' (copia)', 0, 150), json_encode($b['config'], JSON_UNESCAPED_UNICODE)]);
        return ['ok' => true, 'id' => (int)Database::instance()->lastInsertId()];
    }

    public function delete(int $id): array
    {
        $this->ensureSeed();
        Database::instance()->prepare("DELETE FROM wwi_banners WHERE id = ? AND site_id = @site_id")->execute([$id]);
        return ['ok' => true];
    }

    public function setEnabled(int $id, bool $enabled): array
    {
        $this->ensureSeed();
        Database::instance()->prepare("UPDATE wwi_banners SET enabled = ? WHERE id = ? AND site_id = @site_id")->execute([$enabled ? 1 : 0, $id]);
        return ['ok' => true];
    }

    public function setHeader(int $id): array
    {
        $this->ensureSeed();
        $db = Database::instance();
        $db->prepare("UPDATE wwi_banners SET is_header = 0 WHERE site_id = @site_id")->execute();
        $db->prepare("UPDATE wwi_banners SET is_header = 1, enabled = 1 WHERE id = ? AND site_id = @site_id")->execute([$id]);
        return ['ok' => true];
    }

    /** Config del banner que va en la barra superior (is_header + enabled); si no, el 1º habilitado; si no, null. */
    public function headerConfig(): ?array
    {
        $this->ensureSeed();
        $db = Database::instance();
        $st = $db->prepare("SELECT config FROM wwi_banners WHERE site_id = @site_id AND is_header = 1 AND enabled = 1 ORDER BY id ASC LIMIT 1");
        $st->execute();
        $raw = $st->fetchColumn();
        if ($raw === false || $raw === null) {
            $st = $db->prepare("SELECT config FROM wwi_banners WHERE site_id = @site_id AND enabled = 1 ORDER BY sort_order ASC, id ASC LIMIT 1");
            $st->execute();
            $raw = $st->fetchColumn();
        }
        if ($raw === false || $raw === null) return null;
        $cfg = json_decode((string)$raw, true);
        return is_array($cfg) ? $cfg : null;
    }
}
