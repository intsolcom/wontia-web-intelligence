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
            is_recovery TINYINT NOT NULL DEFAULT 0,
            config JSON, sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_site (site_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        try {
            Database::instance()->exec("ALTER TABLE wwi_banners ADD COLUMN IF NOT EXISTS is_recovery TINYINT NOT NULL DEFAULT 0");
        } catch (\Throwable $e) {
        }
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
            'bg_color' => '#ffffff', 'bg_color2' => '#f1eaff', 'text_color' => '#171722', 'font_size' => '0.8', 'preset' => 'none',
            'bold' => true, 'italic' => false, 'align' => 'center', 'bg_image' => '', 'overlay' => '55', 'bare' => true,
            'dismissible' => false, 'close_label' => 'Entendido', 'frequency' => 'always',
            'marquee' => false, 'critical' => false, 'countdown_to' => '', 'exit_intent' => false, 'track' => true, 'ab' => false,
            'exit_intent_min_time' => 8, 'exit_intent_min_scroll' => 0, 'exit_intent_sensitivity' => 12, 'exit_intent_fallback_ms' => 0,
            'exit_intent_cart_only' => false, 'exit_intent_exclude_checkout' => true, 'exit_intent_mobile' => false,
            'inactivity_seconds' => 0, 'scroll_trigger_pct' => 0, 'time_trigger_seconds' => 0, 'behavior_tracking' => true,
            'show_cart_summary' => false, 'cart_storage_key' => 'sonika_cart', 'cart_cta_label' => 'Terminar mi compra',
            'cart_cta_url' => '/checkout', 'recovery_coupon' => '',
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
        $st = Database::instance()->prepare("SELECT id, name, enabled, is_header, is_recovery, updated_at FROM wwi_banners WHERE site_id = @site_id ORDER BY is_header DESC, is_recovery DESC, sort_order ASC, id ASC");
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

    public function setRecovery(int $id, bool $enabled = true): array
    {
        $this->ensureSeed();
        if (!$this->get($id)) return ['ok' => false, 'message' => 'Banner no encontrado'];
        $db = Database::instance();
        if ($enabled) $db->prepare("UPDATE wwi_banners SET is_recovery = 0 WHERE site_id = @site_id")->execute();
        $db->prepare("UPDATE wwi_banners SET is_recovery = ?, enabled = IF(?, 1, enabled) WHERE id = ? AND site_id = @site_id")
            ->execute([$enabled ? 1 : 0, $enabled ? 1 : 0, $id]);
        return ['ok' => true];
    }

    /** Avisos globales de recuperación (modales) que se renderizan en el footer del tema. */
    public function recoveryConfigs(int $limit = 3): array
    {
        $this->ensureSeed();
        $limit = max(1, min(10, $limit));
        $st = Database::instance()->prepare("SELECT name, config FROM wwi_banners WHERE site_id = @site_id AND enabled = 1 AND is_recovery = 1 ORDER BY sort_order ASC, id ASC LIMIT $limit");
        $st->execute();
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $cfg = json_decode((string)$row['config'], true);
            if (!is_array($cfg)) continue;
            $cfg = array_merge($this->defaults(), $cfg);
            $name = trim((string)($row['name'] ?? ''));
            if ($name !== '') $cfg['_name'] = $name;
            $out[] = $cfg;
        }
        return $out;
    }

    /** Config del banner que va en la barra superior (is_header + enabled); si no, el 1º habilitado; si no, null. */
    public function headerConfig(): ?array
    {
        $this->ensureSeed();
        $db = Database::instance();
        $st = $db->prepare("SELECT name, config FROM wwi_banners WHERE site_id = @site_id AND is_header = 1 AND enabled = 1 ORDER BY id ASC LIMIT 1");
        $st->execute();
        $row = $st->fetch();
        if (!$row) {
            $st = $db->prepare("SELECT name, config FROM wwi_banners WHERE site_id = @site_id AND enabled = 1 ORDER BY sort_order ASC, id ASC LIMIT 1");
            $st->execute();
            $row = $st->fetch();
        }
        if (!$row) return null;
        $cfg = json_decode((string)$row['config'], true);
        if (!is_array($cfg)) return null;
        $name = trim((string)($row['name'] ?? ''));
        if ($name !== '') $cfg['_name'] = $name;
        return $cfg;
    }
}
