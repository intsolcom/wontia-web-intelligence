<?php
namespace App\Services;

use App\Core\Database;

/**
 * BannerTemplateService — plantillas de la BARRA SUPERIOR (banner global).
 * Guarda/carga el conjunto de settings `theme.<slug>.topbar_*` del sitio.
 * Multi-tenant (@site_id). No duplica los "Patrones" de secciones.
 */
class BannerTemplateService
{
    private static bool $ensured = false;
    public const KEYS = ['topbar_items', 'topbar_animation', 'topbar_rotation_ms', 'topbar_bg_color', 'topbar_text_color', 'topbar_font_size', 'topbar_bold', 'topbar_frequency'];

    public function ensureTables(): void
    {
        if (self::$ensured) return;
        Database::instance()->exec("CREATE TABLE IF NOT EXISTS wwi_banner_templates (
            id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL DEFAULT 1,
            name VARCHAR(150) NOT NULL, payload JSON, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_site (site_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ensured = true;
    }

    private function activeTheme(): string
    {
        try {
            $v = (string)(Database::instance()->query("SELECT theme FROM sites WHERE id = @site_id")->fetchColumn() ?: 'default');
            return preg_replace('/[^a-z0-9_-]/i', '', $v) ?: 'default';
        } catch (\Throwable $e) {
            return 'default';
        }
    }

    private function getSetting(string $key, $def = '')
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

    public function list(): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT id, name, created_at FROM wwi_banner_templates WHERE site_id = @site_id ORDER BY id DESC LIMIT 100");
        $st->execute();
        return $st->fetchAll();
    }

    public function save(string $name, ?string $slug = null): array
    {
        $this->ensureTables();
        $slug = $slug ?: $this->activeTheme();
        $name = substr(trim($name), 0, 150);
        if ($name === '') return ['ok' => false, 'message' => 'Nombre requerido.'];
        $payload = [];
        foreach (self::KEYS as $k) $payload[$k] = (string)$this->getSetting('theme.' . $slug . '.' . $k, '');
        Database::instance()->prepare("INSERT INTO wwi_banner_templates (site_id, name, payload) VALUES (@site_id, ?, ?)")
            ->execute([$name, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
        return ['ok' => true, 'id' => (int)Database::instance()->lastInsertId()];
    }

    public function apply(int $id, ?string $slug = null): array
    {
        $this->ensureTables();
        $slug = $slug ?: $this->activeTheme();
        $st = Database::instance()->prepare("SELECT payload FROM wwi_banner_templates WHERE id = ? AND site_id = @site_id LIMIT 1");
        $st->execute([$id]);
        $payload = json_decode((string)$st->fetchColumn(), true);
        if (!is_array($payload)) return ['ok' => false, 'message' => 'Plantilla no encontrada.'];
        foreach (self::KEYS as $k) {
            if (!array_key_exists($k, $payload)) continue;
            $v = $payload[$k];
            $this->setSetting('theme.' . $slug . '.' . $k, is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string)$v);
        }
        return ['ok' => true];
    }

    public function delete(int $id): array
    {
        $this->ensureTables();
        Database::instance()->prepare("DELETE FROM wwi_banner_templates WHERE id = ? AND site_id = @site_id")->execute([$id]);
        return ['ok' => true];
    }
}
