<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * ThemeService — motor del mercado de temas de WWI (extiende el sistema mínimo existente).
 *  - Registro de temas vía manifiesto theme.json (+ manifiesto sintetizado para temas sin él).
 *  - Activación con control de licencia (premium requiere serial).
 *  - Seriales de instalación GLOBALES (solo el servidor central los genera), 16 chars.
 *  - Licencias por sitio + auditoría.
 */
class ThemeService
{
    private static bool $ensured = false;

    public function ensureTables(): array
    {
        if (self::$ensured) return ['ok' => true, 'cached' => true];
        $db = Database::instance();
        $db->exec("CREATE TABLE IF NOT EXISTS theme_serials (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code_hash VARCHAR(255) NOT NULL,
            theme_slug VARCHAR(100) NOT NULL,
            label VARCHAR(120) NULL,
            tier VARCHAR(20) NOT NULL DEFAULT 'premium',
            created_by VARCHAR(120) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            bound_site_id INT NULL,
            bound_at DATETIME NULL,
            revoked_at DATETIME NULL,
            UNIQUE KEY uk_code (code_hash),
            KEY idx_slug (theme_slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS theme_licenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL,
            theme_slug VARCHAR(100) NOT NULL,
            serial_id INT NULL,
            status ENUM('active','revoked') NOT NULL DEFAULT 'active',
            activated_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_site_theme (site_id, theme_slug),
            KEY idx_serial (serial_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS theme_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NULL,
            theme_slug VARCHAR(100) NULL,
            event VARCHAR(48) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'ok',
            actor VARCHAR(120) NULL,
            meta JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ensured = true;
        return ['ok' => true, 'cached' => false];
    }

    // ─────────────────────────────────────────────
    // Registro / manifiestos
    // ─────────────────────────────────────────────

    public function themesDir(): string
    {
        return ROOT_DIR . '/templates/themes';
    }

    public function exists(string $slug): bool
    {
        if (!preg_match('/^[a-z0-9\-]{2,50}$/', $slug)) return false;
        return is_file($this->themesDir() . '/' . $slug . '/index.php');
    }

    private function defaultManifest(string $slug): array
    {
        $nice = ucwords(str_replace(['-', '_'], ' ', $slug));
        return [
            'name' => $nice,
            'slug' => $slug,
            'version' => '1.0.0',
            'author' => 'Wontia',
            'description' => 'Tema base de Wontia Web Intelligence.',
            'tier' => 'free',
            'category' => 'general',
            'license' => 'GPL-compatible',
            'features' => [],
            'tokens' => [],
        ];
    }

    public function manifest(string $slug): ?array
    {
        if (!$this->exists($slug)) return null;
        $base = $this->defaultManifest($slug);
        $jsonPath = $this->themesDir() . '/' . $slug . '/theme.json';
        if (is_file($jsonPath)) {
            $m = json_decode((string)file_get_contents($jsonPath), true);
            if (is_array($m)) $base = array_merge($base, $m);
        }
        $base['slug'] = $slug;
        return $base;
    }

    public function activeTheme(): string
    {
        try {
            $v = Database::instance()->query("SELECT theme FROM sites WHERE id = @site_id")->fetchColumn();
            return (string)($v ?: 'default');
        } catch (\Throwable $e) {
            return 'default';
        }
    }

    public function siteId(): int
    {
        return (int)Config::get('SITE_ID', '1');
    }

    public function manifests(): array
    {
        $this->ensureTables();
        $active = $this->activeTheme();
        $out = [];
        foreach (glob($this->themesDir() . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $slug = basename($dir);
            if ($slug === '_shared') continue;
            if (!is_file($dir . '/index.php')) continue;
            $m = $this->manifest($slug);
            $premium = ($m['tier'] ?? 'free') === 'premium';
            $licensed = !$premium || $this->isLicensed($slug);
            $m['installed'] = true;
            $m['active'] = ($slug === $active);
            $m['licensed'] = $licensed;
            $m['locked'] = $premium && !$licensed;
            $m['preview_url'] = '/?theme_preview=' . rawurlencode($slug);
            $out[] = $m;
        }
        usort($out, fn($a, $b) => strcmp((string)$a['name'], (string)$b['name']));
        return $out;
    }

    // ─────────────────────────────────────────────
    // Licencias
    // ─────────────────────────────────────────────

    public function isLicensed(string $slug, ?int $siteId = null): bool
    {
        $this->ensureTables();
        $siteId = $siteId ?? $this->siteId();
        try {
            $stmt = Database::instance()->prepare('SELECT status FROM theme_licenses WHERE site_id = ? AND theme_slug = ? LIMIT 1');
            $stmt->execute([$siteId, $slug]);
            $row = $stmt->fetch();
            return $row && $row['status'] === 'active';
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function licenseFor(string $slug, ?int $siteId = null): ?array
    {
        $this->ensureTables();
        $siteId = $siteId ?? $this->siteId();
        try {
            $stmt = Database::instance()->prepare('SELECT l.*, s.label AS serial_label, s.bound_at FROM theme_licenses l LEFT JOIN theme_serials s ON s.id = l.serial_id WHERE l.site_id = ? AND l.theme_slug = ? LIMIT 1');
            $stmt->execute([$siteId, $slug]);
            return $stmt->fetch() ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function activate(string $slug): array
    {
        $m = $this->manifest($slug);
        if (!$m) return ['ok' => false, 'message' => 'Theme no encontrado.'];
        if (($m['tier'] ?? 'free') === 'premium' && !$this->isLicensed($slug)) {
            return ['ok' => false, 'message' => 'Este theme es premium. Introduce un código de instalación válido.'];
        }
        Database::instance()->prepare('UPDATE sites SET theme = ? , updated_at = NOW() WHERE id = @site_id')
            ->execute([$slug]);
        $this->event('activate', $slug, 'ok');
        return ['ok' => true, 'message' => 'Tema activado: ' . $m['name'], 'theme' => $slug];
    }

    // ─────────────────────────────────────────────
    // Seriales (solo el servidor central)
    // ─────────────────────────────────────────────

    private function serialHash(string $code): string
    {
        return hash_hmac('sha256', 'theme_serial|' . $code, (string)Config::get('JWT_SECRET', 'wwi'));
    }

    private function randomSerial(int $length = 16): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%*?-_.';
        $out = '';
        $max = strlen($alphabet) - 1;
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }

    public function generateSerial(string $themeSlug, string $label = '', string $actor = 'admin'): array
    {
        $this->ensureTables();
        $m = $this->manifest($themeSlug);
        if (!$m) return ['ok' => false, 'message' => 'Theme no encontrado.'];
        $code = $this->randomSerial(16);
        $hash = $this->serialHash($code);
        Database::instance()->prepare('INSERT INTO theme_serials (code_hash, theme_slug, label, tier, created_by) VALUES (?,?,?,?,?)')
            ->execute([$hash, $themeSlug, substr($label, 0, 120), $m['tier'] ?? 'premium', substr($actor, 0, 120)]);
        $this->event('serial_generated', $themeSlug, 'ok', ['label' => $label]);
        return ['ok' => true, 'code' => $code, 'theme' => $themeSlug, 'message' => 'Código generado. Cópialo y entrégalo al cliente; no se volverá a mostrar.'];
    }

    public function redeemSerial(string $code, string $actor = 'admin', ?int $siteId = null): array
    {
        $this->ensureTables();
        $siteId = $siteId ?? $this->siteId();
        $code = trim($code);
        if ($code === '') return ['ok' => false, 'message' => 'Introduce un código.'];
        $hash = $this->serialHash($code);
        $db = Database::instance();
        $stmt = $db->prepare('SELECT * FROM theme_serials WHERE code_hash = ? LIMIT 1');
        $stmt->execute([$hash]);
        $serial = $stmt->fetch();
        if (!$serial) {
            $this->event('serial_redeem', null, 'error', ['reason' => 'not_found'], $actor);
            return ['ok' => false, 'message' => 'Código de instalación inválido.'];
        }
        if (!empty($serial['revoked_at'])) {
            $this->event('serial_redeem', $serial['theme_slug'], 'error', ['reason' => 'revoked'], $actor);
            return ['ok' => false, 'message' => 'Ese código fue revocado.'];
        }
        if (!empty($serial['bound_site_id']) && (int)$serial['bound_site_id'] !== $siteId) {
            $this->event('serial_redeem', $serial['theme_slug'], 'error', ['reason' => 'bound_other_site'], $actor);
            return ['ok' => false, 'message' => 'Ese código ya fue usado en otro sitio.'];
        }
        $themeSlug = (string)$serial['theme_slug'];
        $m = $this->manifest($themeSlug);
        if (!$m) return ['ok' => false, 'message' => 'El tema asociado al código ya no está disponible.'];

        $db->prepare('INSERT INTO theme_licenses (site_id, theme_slug, serial_id, status, activated_at) VALUES (?,?,?, \'active\', NOW()) ON DUPLICATE KEY UPDATE serial_id = VALUES(serial_id), status = \'active\', activated_at = NOW()')
            ->execute([$siteId, $themeSlug, (int)$serial['id']]);
        $db->prepare('UPDATE theme_serials SET bound_site_id = ?, bound_at = NOW() WHERE id = ?')
            ->execute([$siteId, (int)$serial['id']]);
        $this->event('serial_redeem', $themeSlug, 'ok', ['serial_id' => (int)$serial['id']], $actor);
        return ['ok' => true, 'theme' => $themeSlug, 'message' => 'Licencia activada. Ya puedes usar «' . $m['name'] . '».'];
    }

    public function revokeSerial(int $serialId, string $actor = 'admin'): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $stmt = $db->prepare('SELECT * FROM theme_serials WHERE id = ? LIMIT 1');
        $stmt->execute([$serialId]);
        $serial = $stmt->fetch();
        if (!$serial) return ['ok' => false, 'message' => 'Serial no encontrado.'];
        $db->prepare('UPDATE theme_serials SET revoked_at = NOW() WHERE id = ?')->execute([$serialId]);
        if (!empty($serial['bound_site_id'])) {
            $db->prepare('UPDATE theme_licenses SET status = \'revoked\' WHERE serial_id = ?')->execute([$serialId]);
        }
        $this->event('serial_revoked', (string)$serial['theme_slug'], 'ok', ['serial_id' => $serialId], $actor);
        return ['ok' => true, 'message' => 'Serial revocado.'];
    }

    public function serials(int $limit = 50): array
    {
        $this->ensureTables();
        $stmt = Database::instance()->prepare('SELECT id, theme_slug, label, tier, created_by, created_at, bound_site_id, bound_at, revoked_at FROM theme_serials ORDER BY id DESC LIMIT ' . (int)$limit);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ─────────────────────────────────────────────

    public function event(string $event, ?string $slug, string $status = 'ok', array $meta = [], ?string $actor = null): void
    {
        try {
            $this->ensureTables();
            Database::instance()->prepare('INSERT INTO theme_events (site_id, theme_slug, event, status, actor, meta) VALUES (@site_id,?,?,?,?,?)')
                ->execute([$slug, $event, $status, $actor ?? ($_SESSION['user']['username'] ?? null), $meta ? json_encode($meta) : null]);
        } catch (\Throwable $e) {
        }
    }

    public function recentEvents(int $limit = 30): array
    {
        $this->ensureTables();
        $stmt = Database::instance()->prepare('SELECT id, site_id, theme_slug, event, status, actor, created_at FROM theme_events ORDER BY id DESC LIMIT ' . (int)$limit);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
