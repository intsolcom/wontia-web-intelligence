<?php
namespace App\Bricks\SeoGlobalLaunch;

use App\Core\BrickSystem;
use App\Core\Config;
use App\Core\Database;
use App\Core\AiBrick\AiRouter;

/**
 * SEO GLOBAL LAUNCH — Brick instalable del ecosistema WWI
 * Extraído de marcasbpo.com (admin/seo_global.php) y productizado
 * como módulo standalone multi-tenant.
 *
 * Motor SEO full-site de un clic:
 *  - Scan con score 0-100 (meta, schema, OG, técnico, contenido)
 *  - Generación IA de metadatos para todas las páginas
 *  - Auto-fix IA de posts sin metadata
 *  - Deep-fix IA (expandir contenido, alt text, headings)
 *  - JSON-LD (Organization, BreadcrumbList), head tags renderizables
 *  - Auditoría de contenido y tracker de bots
 *
 * IA vía capa BRICK de WWI (AiRouter → proveedores/políticas/costos)
 * con fallback automático a DeepSeek directo si no hay BRICK provisionado.
 */
class SeoGlobalLaunchBrick
{
    private static bool $tablesReady = false;

    public static function meta(): array
    {
        $jsonPath = __DIR__ . '/brick.json';
        if (file_exists($jsonPath)) {
            $meta = json_decode(file_get_contents($jsonPath), true);
            if ($meta) return $meta;
        }
        return [
            'name' => 'SEO Global Launch',
            'slug' => 'seo-global-launch',
            'version' => '2.2.0',
            'description' => 'Full-site SEO engine with AI autofix',
            'author' => 'Wontia',
            'category' => 'seo',
        ];
    }

    public static function activate(): array
    {
        $meta = self::meta();
        $existing = BrickSystem::findBySlug($meta['slug']);
        if ($existing) {
            self::installTables();
            return ['ok' => true, 'message' => 'Already installed', 'id' => $existing['id']];
        }

        $result = BrickSystem::install([
            'site_id' => (int)Config::get('SITE_ID', '1'),
            'name' => $meta['name'],
            'slug' => $meta['slug'],
            'version' => $meta['version'],
            'description' => $meta['description'],
            'author' => $meta['author'],
            'category' => $meta['category'],
            'brick_class' => self::class,
            'installed_path' => 'src/Bricks/SeoGlobalLaunch/',
            'config' => $meta['config'] ?? [],
        ]);

        if ($result['ok']) self::installTables();
        return $result;
    }

    public static function deactivate(): array
    {
        $meta = self::meta();
        $existing = BrickSystem::findBySlug($meta['slug']);
        if ($existing) return BrickSystem::uninstall((int)$existing['id']);
        return ['ok' => false, 'message' => 'Not installed'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'ai_enabled', 'type' => 'bool', 'label' => 'Usar IA', 'default' => true],
            ['key' => 'ai_fallback_deepseek', 'type' => 'bool', 'label' => 'Fallback DeepSeek directo', 'default' => true],
            ['key' => 'auto_save_scores', 'type' => 'bool', 'label' => 'Guardar historial de scores', 'default' => true],
            ['key' => 'scan_static_pages', 'type' => 'bool', 'label' => 'Escanear páginas estáticas', 'default' => true],
            ['key' => 'scan_blog_posts', 'type' => 'bool', 'label' => 'Escanear posts del blog', 'default' => true],
            ['key' => 'audit_alt_text', 'type' => 'bool', 'label' => 'Auditar alt text', 'default' => true],
            ['key' => 'audit_word_count', 'type' => 'bool', 'label' => 'Auditar word count', 'default' => true],
            ['key' => 'audit_headings', 'type' => 'bool', 'label' => 'Auditar headings H2', 'default' => true],
            ['key' => 'enable_sitemap', 'type' => 'bool', 'label' => 'Sitemap por sitio', 'default' => true],
            ['key' => 'enable_robots', 'type' => 'bool', 'label' => 'Robots.txt por sitio', 'default' => true],
            ['key' => 'enable_canonical', 'type' => 'bool', 'label' => 'Canonical administrable', 'default' => true],
            ['key' => 'enable_hreflang', 'type' => 'bool', 'label' => 'Hreflang ES/EN', 'default' => true],
            ['key' => 'enable_cache', 'type' => 'bool', 'label' => 'Cache de sitemap/robots', 'default' => true],
            ['key' => 'sitemap_cache_ttl', 'type' => 'number', 'label' => 'TTL cache (segundos)', 'default' => 600],
            ['key' => 'sitemap_exclude', 'type' => 'textarea', 'label' => 'Sitemap: handles excluidos', 'default' => ''],
            ['key' => 'robots_disallow', 'type' => 'textarea', 'label' => 'Robots: rutas Disallow', 'default' => "/admin/\n/api/\n/install.php"],
            ['key' => 'robots_allow', 'type' => 'textarea', 'label' => 'Robots: rutas Allow', 'default' => ''],
            ['key' => 'robots_crawl_delay', 'type' => 'number', 'label' => 'Robots: crawl-delay', 'default' => 0],
            ['key' => 'enable_redirects', 'type' => 'bool', 'label' => 'Redirecciones 301/302', 'default' => true],
            ['key' => 'enable_404_monitor', 'type' => 'bool', 'label' => 'Monitor de 404', 'default' => true],
            ['key' => 'enable_broken_links', 'type' => 'bool', 'label' => 'Verificador de enlaces rotos', 'default' => true],
            ['key' => 'broken_links_batch', 'type' => 'number', 'label' => 'Enlaces por lote', 'default' => 10],
            ['key' => 'live_monitor_retention_days', 'type' => 'number', 'label' => 'Retención del monitor (días)', 'default' => 90],
        ];
    }

    private static function configKey(): string
    {
        return 'brick_config_seo-global-launch';
    }

    public static function config(): array
    {
        $out = [];
        foreach (self::configSchema() as $f) {
            if (isset($f['default'])) $out[(string)$f['key']] = $f['default'];
        }
        try {
            $saved = self::setting(self::configKey(), '');
            $decoded = json_decode($saved, true);
            if (is_array($decoded)) {
                foreach ($decoded as $k => $v) {
                    if (!array_key_exists($k, $out)) continue;
                    $out[$k] = $v;
                }
            }
        } catch (\Throwable $e) {
        }
        foreach ($out as $k => $v) {
            if (in_array($k, ['sitemap_cache_ttl', 'robots_crawl_delay', 'broken_links_batch', 'live_monitor_retention_days'], true)) $out[$k] = max(0, (int)$v);
            elseif (in_array($k, ['robots_disallow', 'robots_allow', 'sitemap_exclude'], true)) $out[$k] = (string)$v;
            else $out[$k] = (bool)$v;
        }
        return $out;
    }

    public static function saveConfig(array $in): array
    {
        $clean = [];
        foreach (self::configSchema() as $f) {
            $key = (string)($f['key'] ?? '');
            $type = (string)($f['type'] ?? '');
            if ($key === '' || $type === 'heading') continue;
            $value = $in[$key] ?? ($f['default'] ?? '');
            if ($type === 'bool') $clean[$key] = !empty($value);
            elseif ($type === 'number') $clean[$key] = max(0, (int)$value);
            elseif ($type === 'textarea') $clean[$key] = mb_substr((string)$value, 0, 2000);
            else $clean[$key] = mb_substr(trim((string)$value), 0, 500);
        }
        self::setSetting(self::configKey(), json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        self::clearCache();
        return ['ok' => true, 'config' => self::config()];
    }

    // ─────────────────────────────────────────────
    // Tablas multi-tenant
    // ─────────────────────────────────────────────

    private static function cacheGet(string $key): ?string
    {
        if (empty(self::config()['enable_cache'])) return null;
        try {
            $stmt = Database::instance()->prepare('SELECT cache_value FROM seo_cache WHERE site_id = @site_id AND cache_key = ? AND expires_at > NOW() LIMIT 1');
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
            return $value === false ? null : (string)$value;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function cacheSet(string $key, string $value): void
    {
        if (empty(self::config()['enable_cache'])) return;
        $ttl = max(60, min(900, (int)(self::config()['sitemap_cache_ttl'] ?? 600)));
        try {
            $stmt = Database::instance()->prepare('INSERT INTO seo_cache (site_id, cache_key, cache_value, expires_at) VALUES (@site_id, ?, ?, ?) ON DUPLICATE KEY UPDATE cache_value = VALUES(cache_value), expires_at = VALUES(expires_at)');
            $stmt->execute([$key, $value, date('Y-m-d H:i:s', time() + $ttl)]);
        } catch (\Throwable $e) {
        }
    }

    public static function clearCache(): array
    {
        try {
            Database::instance()->prepare('DELETE FROM seo_cache WHERE site_id = @site_id')->execute();
            return ['ok' => true, 'message' => 'Cache SEO eliminada'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo limpiar la cache'];
        }
    }

    private static function xmlEsc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function normalUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        if (preg_match('~^https?://~i', $url)) return $url;
        if (str_starts_with($url, '/')) return self::siteUrl() . $url;
        return '';
    }

    public static function sitemapXml(int $page = 1): string
    {
        self::installTables();
        $cfg = self::config();
        if (empty($cfg['enable_sitemap'])) return '';
        $page = max(1, $page);
        $cached = self::cacheGet('sitemap:' . $page);
        if ($cached !== null) return $cached;

        $db = Database::instance();
        $rows = [];
        try {
            $pages = $db->query("SELECT slug, updated_at FROM pages WHERE site_id = @site_id AND status = 'published' AND no_index = 0 ORDER BY sort_order ASC")->fetchAll();
            foreach ($pages as $p) {
                $slug = trim((string)($p['slug'] ?? ''), '/');
                $base = in_array($slug, ['home', 'inicio'], true) ? '' : $slug;
                $rows[] = ['url' => self::siteUrl() . '/' . $base, 'lastmod' => (string)($p['updated_at'] ?? ''), 'changefreq' => 'weekly', 'priority' => 0.8];
            }
            $posts = $db->query("SELECT slug, updated_at FROM blog_posts WHERE site_id = @site_id AND status = 'published' ORDER BY published_at DESC")->fetchAll();
            foreach ($posts as $p) {
                $rows[] = ['url' => self::siteUrl() . '/blog/' . rawurlencode((string)$p['slug']), 'lastmod' => (string)($p['updated_at'] ?? ''), 'changefreq' => 'monthly', 'priority' => 0.6];
            }
            // Categorías de tienda (jerarquía canónica del tema).
            $cats = $db->query("SELECT path, updated_at FROM store_categories WHERE site_id = @site_id AND is_active = 1 AND path IS NOT NULL AND path <> '' ORDER BY path ASC")->fetchAll();
            foreach ($cats as $c) {
                $rows[] = ['url' => self::siteUrl() . '/' . ltrim((string)$c['path'], '/'), 'lastmod' => (string)($c['updated_at'] ?? ''), 'changefreq' => 'weekly', 'priority' => 0.7];
            }
            // Catálogo público: sin productos el sitemap solo declara categorías.
            $exclude = array_filter(array_map(static function ($v) {
                return strtolower(trim($v));
            }, preg_split('/[\s,]+/', (string)($cfg['sitemap_exclude'] ?? '')) ?: []));
            $products = $db->query("SELECT catalog_handle, slug, COALESCE(catalog_synced_at, updated_at) updated_at
                FROM store_products
                WHERE site_id = @site_id AND status = 'active' AND visibility = 'public'
                  AND (catalog_handle IS NOT NULL AND catalog_handle <> '' OR slug IS NOT NULL AND slug <> '')
                ORDER BY id ASC")->fetchAll();
            foreach ($products as $p) {
                $handle = trim((string)($p['slug'] ?? '')) ?: trim((string)($p['catalog_handle'] ?? ''));
                if ($handle === '' || in_array(strtolower($handle), $exclude, true)) continue;
                $rows[] = ['url' => self::siteUrl() . '/producto/' . rawurlencode($handle), 'lastmod' => (string)($p['updated_at'] ?? ''), 'changefreq' => 'weekly', 'priority' => 0.6];
            }
        } catch (\Throwable $e) {
            return '';
        }

        // Deduplicación por URL: home, categorías y productos pueden coincidir.
        $seen = [];
        $unique = [];
        foreach ($rows as $r) {
            $key = mb_strtolower((string)$r['url']);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $unique[] = $r;
        }
        $rows = $unique;

        $meta = [];
        try {
            $st = $db->query('SELECT page_url, canonical_url, priority, changefreq, last_modified FROM seo_pages WHERE site_id = @site_id');
            foreach ($st->fetchAll() as $r) $meta[(string)$r['page_url']] = $r;
        } catch (\Throwable $e) {
        }
        foreach ($rows as &$r) {
            $m = $meta[$r['url']] ?? null;
            if ($m) {
                if (!empty($m['canonical_url'])) $r['url'] = (string)$m['canonical_url'];
                if (!empty($m['priority'])) $r['priority'] = (float)$m['priority'];
                if (!empty($m['changefreq'])) $r['changefreq'] = (string)$m['changefreq'];
                if (!empty($m['last_modified'])) $r['lastmod'] = (string)$m['last_modified'];
            }
        }
        unset($r);
        $rows = array_slice($rows, ($page - 1) * 5000, 5000);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($rows as $r) {
            $xml .= '  <url><loc>' . self::xmlEsc((string)$r['url']) . '</loc>';
            if (!empty($r['lastmod'])) $xml .= '<lastmod>' . self::xmlEsc(date('Y-m-d', strtotime((string)$r['lastmod']))) . '</lastmod>';
            $xml .= '<changefreq>' . self::xmlEsc((string)$r['changefreq']) . '</changefreq><priority>' . number_format((float)$r['priority'], 1, '.', '') . '</priority></url>' . "\n";
        }
        $xml .= '</urlset>';
        self::cacheSet('sitemap:' . $page, $xml);
        return $xml;
    }

    public static function robotsTxt(): string
    {
        self::installTables();
        $cfg = self::config();
        if (empty($cfg['enable_robots'])) return '';
        $cached = self::cacheGet('robots');
        if ($cached !== null) return $cached;
        $lines = ['User-agent: *'];
        foreach (preg_split('/\r\n|\r|\n/', (string)($cfg['robots_allow'] ?? '')) as $line) {
            $line = trim($line);
            if ($line !== '') $lines[] = 'Allow: ' . (str_starts_with($line, '/') ? $line : '/' . $line);
        }
        foreach (preg_split('/\r\n|\r|\n/', (string)($cfg['robots_disallow'] ?? '')) as $line) {
            $line = trim($line);
            if ($line !== '') $lines[] = 'Disallow: ' . (str_starts_with($line, '/') ? $line : '/' . $line);
        }
        // Rutas sensibles que nunca deben indexarse, aunque la configuración del panel no las incluya.
        foreach (['/admin', '/admin.php', '/admin-', '/api/', '/install.php', '/mi-antro', '/artista/contrato', '/checkout', '/carrito', '/pedido/', '/cuenta', '/r/'] as $__sensitive) {
            $lines[] = 'Disallow: ' . $__sensitive;
        }
        try {
            $rows = Database::instance()->query("SELECT slug FROM pages WHERE site_id = @site_id AND status = 'published' AND no_index = 1")->fetchAll();
            foreach ($rows as $r) {
                $slug = trim((string)$r['slug'], '/');
                if ($slug !== '') $lines[] = 'Disallow: /' . $slug;
            }
        } catch (\Throwable $e) {
        }
        $delay = max(0, (int)($cfg['robots_crawl_delay'] ?? 0));
        if ($delay > 0) $lines[] = 'Crawl-delay: ' . $delay;
        if (!empty($cfg['enable_sitemap'])) $lines[] = 'Sitemap: ' . self::siteUrl() . '/sitemap.xml';
        $text = implode("\n", array_unique($lines)) . "\n";
        self::cacheSet('robots', $text);
        return $text;
    }

    public static function canonicalForUrl(string $url): string
    {
        $cfg = self::config();
        if (empty($cfg['enable_canonical'])) return '';
        try {
            $st = Database::instance()->prepare('SELECT canonical_url FROM seo_pages WHERE site_id = @site_id AND page_url = ? LIMIT 1');
            $st->execute([$url]);
            return trim((string)($st->fetchColumn() ?: ''));
        } catch (\Throwable $e) {
            return '';
        }
    }

    public static function technicalHeadTags(string $url): string
    {
        $cfg = self::config();
        try {
            $st = Database::instance()->prepare('SELECT robots, hreflang FROM seo_pages WHERE site_id = @site_id AND page_url = ? LIMIT 1');
            $st->execute([$url]);
            $row = $st->fetch();
            if (!$row) return '';
            $html = '';
            if (!empty($cfg['enable_robots']) && trim((string)($row['robots'] ?? '')) !== '') {
                $html .= '<meta name="robots" content="' . htmlspecialchars((string)$row['robots'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
            }
            if (!empty($cfg['enable_hreflang']) && !empty($row['hreflang'])) {
                $langs = json_decode((string)$row['hreflang'], true) ?: [];
                foreach ((array)$langs as $lang => $href) {
                    if (preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', (string)$lang) && is_string($href) && $href !== '') {
                        $html .= '<link rel="alternate" hreflang="' . htmlspecialchars((string)$lang, ENT_QUOTES, 'UTF-8') . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . "\n";
                    }
                }
            }
            return $html;
        } catch (\Throwable $e) {
            return '';
        }
    }

    public static function technical(): array
    {
        self::installTables();
        $cfg = self::config();
        $rows = [];
        try {
            $pages = Database::instance()->query("SELECT slug, title, updated_at FROM pages WHERE site_id = @site_id AND status = 'published' ORDER BY sort_order ASC")->fetchAll();
            foreach ($pages as $p) $rows[] = ['url' => self::siteUrl() . '/' . trim((string)$p['slug'], '/'), 'title' => (string)$p['title'], 'type' => 'page', 'updated_at' => (string)$p['updated_at']];
            $posts = Database::instance()->query("SELECT slug, title, updated_at FROM blog_posts WHERE site_id = @site_id AND status = 'published' ORDER BY published_at DESC")->fetchAll();
            foreach ($posts as $p) $rows[] = ['url' => self::siteUrl() . '/blog/' . rawurlencode((string)$p['slug']), 'title' => (string)$p['title'], 'type' => 'post', 'updated_at' => (string)$p['updated_at']];
        } catch (\Throwable $e) {
        }
        $meta = [];
        try {
            $st = Database::instance()->query('SELECT page_url, canonical_url, robots, hreflang FROM seo_pages WHERE site_id = @site_id');
            foreach ($st->fetchAll() as $r) $meta[(string)$r['page_url']] = $r;
        } catch (\Throwable $e) {
        }
        foreach ($rows as &$r) {
            $m = $meta[$r['url']] ?? [];
            $r['canonical_url'] = (string)($m['canonical_url'] ?? '');
            $r['robots'] = (string)($m['robots'] ?? '');
            $r['hreflang'] = json_decode((string)($m['hreflang'] ?? '[]'), true) ?: [];
        }
        unset($r);
        return ['ok' => true, 'config' => $cfg, 'pages' => $rows, 'sitemap_url' => self::siteUrl() . '/sitemap.xml', 'robots_url' => self::siteUrl() . '/robots.txt'];
    }

    public static function savePageTechnical(array $d): array
    {
        self::installTables();
        $url = self::normalUrl((string)($d['url'] ?? ($d['page_url'] ?? '')));
        if ($url === '') return ['ok' => false, 'message' => 'URL inválida'];
        $canonical = trim((string)($d['canonical_url'] ?? ''));
        if ($canonical !== '' && !preg_match('~^https?://~i', $canonical)) $canonical = self::normalUrl($canonical);
        $robots = strtolower(trim((string)($d['robots'] ?? '')));
        if (!preg_match('/^(index|noindex)(,(follow|nofollow))?$/', $robots)) $robots = '';
        $hreflang = [];
        $rawLang = $d['hreflang'] ?? [];
        if (is_string($rawLang)) {
            foreach (preg_split('/\r\n|\r|\n/', $rawLang) as $line) {
                $parts = array_map('trim', explode('|', $line, 2));
                if (count($parts) === 2 && preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $parts[0])) $hreflang[$parts[0]] = self::normalUrl($parts[1]);
            }
        } elseif (is_array($rawLang)) {
            foreach ($rawLang as $lang => $href) {
                if (preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', (string)$lang) && is_string($href)) $hreflang[(string)$lang] = self::normalUrl($href);
            }
        }
        $type = (string)($d['page_type'] ?? 'page');
        try {
            $st = Database::instance()->prepare('INSERT INTO seo_pages (site_id, page_type, page_slug, page_url, canonical_url, robots, hreflang, last_modified) VALUES (@site_id, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE canonical_url = VALUES(canonical_url), robots = VALUES(robots), hreflang = VALUES(hreflang), last_modified = NOW()');
            $st->execute([$type, basename(parse_url($url, PHP_URL_PATH) ?: ''), $url, $canonical !== '' ? $canonical : null, $robots !== '' ? $robots : null, json_encode($hreflang, JSON_UNESCAPED_UNICODE)]);
            self::clearCache();
            return ['ok' => true, 'url' => $url, 'canonical_url' => $canonical, 'robots' => $robots, 'hreflang' => $hreflang];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo guardar la configuración técnica'];
        }
    }

    private static function pathOf(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        $parts = parse_url($url);
        $path = (string)($parts['path'] ?? '');
        if ($path === '') return '';
        if (!str_starts_with($path, '/')) $path = '/' . $path;
        $query = (string)($parts['query'] ?? '');
        return $query !== '' ? $path . '?' . $query : $path;
    }

    private static function redirectTarget(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        if (preg_match('~^https?://~i', $url)) return $url;
        if (str_starts_with($url, '/')) return $url;
        return '';
    }

    public static function redirectFor(string $uri): ?array
    {
        if (empty(self::config()['enable_redirects'])) return null;
        $path = self::pathOf($uri);
        if ($path === '') return null;
        $bare = (string)parse_url($path, PHP_URL_PATH);
        try {
            $st = Database::instance()->prepare('SELECT id, to_url, status_code FROM seo_redirects WHERE site_id = @site_id AND from_path = ? AND is_active = 1 LIMIT 1');
            $st->execute([$path]);
            $row = $st->fetch();
            if (!$row && $bare !== '' && $bare !== $path) {
                $st->execute([$bare]);
                $row = $st->fetch();
            }
            if (!$row) return null;
            Database::instance()->prepare('UPDATE seo_redirects SET hits = hits + 1, last_hit_at = NOW() WHERE id = ? AND site_id = @site_id')->execute([(int)$row['id']]);
            $to = (string)$row['to_url'];
            $query = (string)parse_url($uri, PHP_URL_QUERY);
            if ($query !== '' && !str_contains($to, '?')) $to .= '?' . $query;
            return ['from' => $path, 'to' => $to, 'status' => (int)$row['status_code']];
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function redirects(): array
    {
        self::installTables();
        try {
            $st = Database::instance()->query('SELECT id, from_path, to_url, status_code, hits, is_active, last_hit_at, created_at FROM seo_redirects WHERE site_id = @site_id ORDER BY id DESC');
            return ['ok' => true, 'redirects' => $st->fetchAll()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudieron cargar las redirecciones', 'redirects' => []];
        }
    }

    public static function saveRedirect(array $d): array
    {
        self::installTables();
        $from = self::pathOf((string)($d['from_path'] ?? ''));
        $to = self::redirectTarget((string)($d['to_url'] ?? ''));
        $status = (int)($d['status_code'] ?? 301);
        if ($from === '' || $from === '/') return ['ok' => false, 'message' => 'Indica una ruta de origen válida'];
        if ($to === '') return ['ok' => false, 'message' => 'Indica una URL de destino válida'];
        if (!in_array($status, [301, 302], true)) $status = 301;
        $toPath = self::pathOf($to);
        if ($toPath === $from) return ['ok' => false, 'message' => 'La redirección apunta a la misma ruta'];
        $visited = [$from];
        $cursor = $toPath;
        for ($i = 0; $i < 10 && $cursor !== ''; $i++) {
            if (in_array($cursor, $visited, true)) return ['ok' => false, 'message' => 'Se detectó un bucle de redirección'];
            $visited[] = $cursor;
            try {
                $st = Database::instance()->prepare('SELECT to_url FROM seo_redirects WHERE site_id = @site_id AND from_path = ? AND is_active = 1 LIMIT 1');
                $st->execute([$cursor]);
                $next = $st->fetchColumn();
            } catch (\Throwable $e) {
                $next = false;
            }
            if ($next === false || $next === null || $next === '') break;
            $cursor = self::pathOf((string)$next);
        }
        $id = (int)($d['id'] ?? 0);
        try {
            if ($id > 0) {
                Database::instance()->prepare('UPDATE seo_redirects SET from_path = ?, to_url = ?, status_code = ?, is_active = ?, updated_at = NOW() WHERE id = ? AND site_id = @site_id')
                    ->execute([$from, $to, $status, !empty($d['is_active']) ? 1 : 0, $id]);
                self::clearCache();
                return ['ok' => true, 'id' => $id];
            }
            $st = Database::instance()->prepare('INSERT INTO seo_redirects (site_id, from_path, to_url, status_code, is_active) VALUES (@site_id, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE to_url = VALUES(to_url), status_code = VALUES(status_code), is_active = VALUES(is_active), updated_at = NOW()');
            $st->execute([$from, $to, $status, !empty($d['is_active']) ? 1 : 0]);
            self::clearCache();
            return ['ok' => true, 'id' => (int)Database::instance()->lastInsertId()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo guardar la redirección'];
        }
    }

    public static function deleteRedirect(int $id): array
    {
        try {
            Database::instance()->prepare('DELETE FROM seo_redirects WHERE id = ? AND site_id = @site_id')->execute([$id]);
            self::clearCache();
            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo eliminar la redirección'];
        }
    }

    public static function importRedirects(string $csv): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $csv) ?: [];
        $created = 0;
        $errors = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            $parts = str_getcsv($line);
            if (count($parts) < 2) continue;
            $r = self::saveRedirect(['from_path' => $parts[0], 'to_url' => $parts[1], 'status_code' => (int)($parts[2] ?? 301), 'is_active' => 1]);
            if (!empty($r['ok'])) $created++;
            else $errors[] = $r['message'] ?? 'Error';
        }
        return ['ok' => true, 'created' => $created, 'errors' => array_slice(array_values(array_unique($errors)), 0, 10)];
    }

    public static function record404(string $uri, string $referrer = ''): void
    {
        if (empty(self::config()['enable_404_monitor'])) return;
        $path = self::pathOf($uri);
        if ($path === '' || preg_match('/\.(?:css|js|png|jpg|jpeg|gif|webp|ico|svg|woff2?|ttf|map)$/i', $path)) return;
        try {
            $st = Database::instance()->prepare('INSERT INTO seo_404 (site_id, url, referrer, hits, first_seen, last_seen) VALUES (@site_id, ?, ?, 1, NOW(), NOW()) ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = NOW(), referrer = VALUES(referrer)');
            $st->execute([$path, mb_substr($referrer, 0, 1000)]);
        } catch (\Throwable $e) {
        }
    }

    public static function notFound(): array
    {
        self::installTables();
        try {
            $st = Database::instance()->query('SELECT id, url, referrer, hits, first_seen, last_seen, resolved FROM seo_404 WHERE site_id = @site_id ORDER BY last_seen DESC LIMIT 200');
            return ['ok' => true, 'items' => $st->fetchAll()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'items' => [], 'message' => 'No se pudo cargar el monitor 404'];
        }
    }

    public static function delete404(int $id): array
    {
        try {
            Database::instance()->prepare('DELETE FROM seo_404 WHERE id = ? AND site_id = @site_id')->execute([$id]);
            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo eliminar el registro'];
        }
    }

    public static function brokenLinks(): array
    {
        self::installTables();
        try {
            $st = Database::instance()->query('SELECT * FROM seo_broken_links WHERE site_id = @site_id ORDER BY checked_at DESC, id DESC LIMIT 300');
            return ['ok' => true, 'items' => $st->fetchAll()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'items' => [], 'message' => 'No se pudieron cargar los enlaces'];
        }
    }

    public static function scanBrokenLinks(): array
    {
        self::installTables();
        $cfg = self::config();
        if (empty($cfg['enable_broken_links'])) return ['ok' => true, 'skipped' => true, 'message' => 'Verificador desactivado'];
        $limit = max(1, min(25, (int)($cfg['broken_links_batch'] ?? 10)));
        $base = self::siteUrl();
        $baseHost = (string)(parse_url($base, PHP_URL_HOST) ?: '');
        $sources = [];
        try {
            $pages = Database::instance()->query("SELECT p.slug, GROUP_CONCAT(s.content SEPARATOR ' ') AS content FROM pages p LEFT JOIN sections s ON s.page_id = p.id WHERE p.site_id = @site_id AND p.status = 'published' GROUP BY p.id, p.slug")->fetchAll();
            foreach ($pages as $p) $sources[] = ['url' => $base . '/' . trim((string)$p['slug'], '/'), 'content' => (string)($p['content'] ?? '')];
            $posts = Database::instance()->query("SELECT slug, content FROM blog_posts WHERE site_id = @site_id AND status = 'published'")->fetchAll();
            foreach ($posts as $p) $sources[] = ['url' => $base . '/blog/' . rawurlencode((string)$p['slug']), 'content' => (string)($p['content'] ?? '')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo leer contenido'];
        }
        $checked = 0;
        $broken = 0;
        $seen = [];
        foreach ($sources as $source) {
            if ($checked >= $limit) break;
            if (!preg_match_all('/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $source['content'], $m)) continue;
            foreach ($m[1] as $href) {
                if ($checked >= $limit) break;
                if (preg_match('/^(#|mailto:|tel:|javascript:|data:)/i', $href)) continue;
                $target = self::normalUrl($href);
                if ($target === '') continue;
                $targetHost = (string)(parse_url($target, PHP_URL_HOST) ?: '');
                if ($targetHost !== '' && $baseHost !== '' && strcasecmp($targetHost, $baseHost) !== 0) continue;
                $key = $source['url'] . '|' . $target;
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $checked++;
                $ch = curl_init($target);
                curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_USERAGENT => 'SONIKA-SEO-BrokenLinks/2.0']);
                curl_exec($ch);
                $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                $error = $status === 0 ? 'timeout/connection' : '';
                curl_close($ch);
                try {
                    if ($status >= 400 || $status === 0) {
                        Database::instance()->prepare('INSERT INTO seo_broken_links (site_id, source_url, target_url, status_code, error, checked_at) VALUES (@site_id, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE status_code = VALUES(status_code), error = VALUES(error), checked_at = NOW()')
                            ->execute([$source['url'], $target, $status, $error]);
                        $broken++;
                    } else {
                        Database::instance()->prepare('DELETE FROM seo_broken_links WHERE site_id = @site_id AND source_url = ? AND target_url = ?')->execute([$source['url'], $target]);
                    }
                } catch (\Throwable $e) {
                }
            }
        }
        return ['ok' => true, 'checked' => $checked, 'broken' => $broken, 'batch' => $limit];
    }

    public static function installTables(): array
    {
        $traffic = \App\Services\SeoTrafficSchemaService::ensure();
        $trafficTables = $traffic['applied'] ?? [];
        if (self::$tablesReady) return ['ok' => true, 'message' => 'SEO tables ready', 'tables' => $trafficTables, 'cached' => true];
        $db = Database::instance();
        try {
            $db->query('SELECT 1 FROM seo_pages LIMIT 1');
            $db->query('SELECT 1 FROM seo_cache LIMIT 1');
            $db->query('SELECT 1 FROM seo_redirects LIMIT 1');
            $db->query('SELECT 1 FROM seo_404 LIMIT 1');
            $db->query('SELECT 1 FROM seo_broken_links LIMIT 1');
            $db->query('SELECT 1 FROM seo_live_requests LIMIT 1');
            $db->query('SELECT 1 FROM seo_ip_intel LIMIT 1');
            $db->query('SELECT 1 FROM seo_ip_blocks LIMIT 1');
            $db->query('SELECT 1 FROM seo_audit_issues LIMIT 1');
            $db->query('SELECT robots, hreflang FROM seo_pages LIMIT 1');
            self::$tablesReady = true;
            return ['ok' => true, 'message' => 'SEO tables ready', 'tables' => $trafficTables, 'cached' => true];
        } catch (\Throwable $e) {
            // Si falta una tabla base, reparamos también el esquema de tráfico.
            $repair = \App\Services\SeoTrafficSchemaService::ensure(true);
            $trafficTables = array_merge($trafficTables, $repair['applied'] ?? []);
        }
        $created = $trafficTables;

        $stmts = [
            "CREATE TABLE IF NOT EXISTS seo_pages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                site_id INT NOT NULL DEFAULT 1,
                page_type VARCHAR(50) NOT NULL,
                page_slug VARCHAR(255),
                page_url VARCHAR(500) NOT NULL,
                seo_title VARCHAR(255),
                seo_description TEXT,
                seo_keywords VARCHAR(500),
                og_title VARCHAR(255),
                og_description TEXT,
                og_image VARCHAR(500),
                twitter_title VARCHAR(255),
                twitter_description TEXT,
                canonical_url VARCHAR(500),
                schema_type VARCHAR(50) DEFAULT 'WebPage',
                schema_data JSON,
                priority DECIMAL(2,1) DEFAULT 0.5,
                changefreq VARCHAR(20) DEFAULT 'monthly',
                last_modified DATETIME,
                is_active TINYINT DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_site_url (site_id, page_url),
                KEY idx_site (site_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS seo_audit_issues (
                id INT AUTO_INCREMENT PRIMARY KEY,
                site_id INT NOT NULL DEFAULT 1,
                run_id INT NOT NULL,
                url VARCHAR(500) NOT NULL,
                issue_type VARCHAR(40) NOT NULL,
                severity VARCHAR(10) NOT NULL DEFAULT 'medium',
                issue VARCHAR(255) NOT NULL,
                detail VARCHAR(500) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_site_run (site_id, run_id),
                KEY idx_site_type (site_id, issue_type, severity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS seo_images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                site_id INT NOT NULL DEFAULT 1,
                page_url VARCHAR(500),
                image_url VARCHAR(500),
                current_alt VARCHAR(255),
                suggested_alt VARCHAR(255),
                needs_fix TINYINT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_site_page (site_id, page_url)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS seo_scores (
                id INT AUTO_INCREMENT PRIMARY KEY,
                site_id INT NOT NULL DEFAULT 1,
                scan_date DATETIME NOT NULL,
                total_score INT DEFAULT 0,
                meta_score INT DEFAULT 0,
                schema_score INT DEFAULT 0,
                og_score INT DEFAULT 0,
                technical_score INT DEFAULT 0,
                content_score INT DEFAULT 0,
                details JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_site_date (site_id, scan_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS seo_cache (
                id INT AUTO_INCREMENT PRIMARY KEY,
                site_id INT NOT NULL DEFAULT 1,
                cache_key VARCHAR(160) NOT NULL,
                cache_value LONGTEXT NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_site_cache (site_id, cache_key),
                KEY idx_site_expires (site_id, expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS seo_redirects (
                id INT AUTO_INCREMENT PRIMARY KEY,
                site_id INT NOT NULL DEFAULT 1,
                from_path VARCHAR(500) NOT NULL,
                to_url VARCHAR(1000) NOT NULL,
                status_code SMALLINT NOT NULL DEFAULT 301,
                hits INT NOT NULL DEFAULT 0,
                is_active TINYINT NOT NULL DEFAULT 1,
                last_hit_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_site_from (site_id, from_path),
                KEY idx_site_active (site_id, is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS seo_404 (
                id INT AUTO_INCREMENT PRIMARY KEY,
                site_id INT NOT NULL DEFAULT 1,
                url VARCHAR(1000) NOT NULL,
                referrer VARCHAR(1000) NULL,
                hits INT NOT NULL DEFAULT 1,
                first_seen DATETIME NOT NULL,
                last_seen DATETIME NOT NULL,
                resolved TINYINT NOT NULL DEFAULT 0,
                UNIQUE KEY uk_site_404 (site_id, url(255)),
                KEY idx_site_404_last (site_id, last_seen)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS seo_broken_links (
                id INT AUTO_INCREMENT PRIMARY KEY,
                site_id INT NOT NULL DEFAULT 1,
                source_url VARCHAR(1000) NOT NULL,
                target_url VARCHAR(1000) NOT NULL,
                status_code INT NOT NULL DEFAULT 0,
                error VARCHAR(500) NULL,
                checked_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_site_broken (site_id, source_url(255), target_url(255)),
                KEY idx_site_broken_status (site_id, status_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS seo_live_requests (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                site_id INT NOT NULL DEFAULT 1,
                request_id VARCHAR(80) NULL,
                method VARCHAR(10) NOT NULL,
                path VARCHAR(500) NOT NULL,
                protocol VARCHAR(10) DEFAULT 'HTTP/1.1',
                status SMALLINT NOT NULL DEFAULT 0,
                duration_ms INT NOT NULL DEFAULT 0,
                ip VARCHAR(64) NULL,
                country CHAR(2) NULL,
                city VARCHAR(120) NULL,
                region VARCHAR(120) NULL,
                device VARCHAR(20) NULL,
                os VARCHAR(40) NULL,
                browser VARCHAR(40) NULL,
                referrer VARCHAR(500) NULL,
                user_agent VARCHAR(500) NULL,
                is_bot TINYINT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_site_created (site_id, created_at),
                KEY idx_site_status (site_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS seo_ip_intel (
                site_id INT NOT NULL DEFAULT 1,
                ip VARCHAR(64) NOT NULL,
                provider VARCHAR(30) NULL,
                country VARCHAR(80) NULL,
                country_code CHAR(2) NULL,
                region VARCHAR(120) NULL,
                city VARCHAR(120) NULL,
                isp VARCHAR(190) NULL,
                org VARCHAR(190) NULL,
                asn VARCHAR(80) NULL,
                asname VARCHAR(190) NULL,
                reverse_host VARCHAR(255) NULL,
                is_proxy TINYINT NOT NULL DEFAULT 0,
                is_hosting TINYINT NOT NULL DEFAULT 0,
                is_mobile TINYINT NOT NULL DEFAULT 0,
                scanned_at DATETIME NOT NULL,
                PRIMARY KEY (site_id, ip),
                KEY idx_site_scanned (site_id, scanned_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS seo_ip_blocks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                site_id INT NOT NULL DEFAULT 1,
                ip VARCHAR(64) NOT NULL,
                reason VARCHAR(500) NULL,
                created_by VARCHAR(190) NULL,
                expires_at DATETIME NULL,
                active TINYINT NOT NULL DEFAULT 1,
                requests_blocked INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uk_site_ip (site_id, ip),
                KEY idx_site_active (site_id, active, expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];

        foreach ($stmts as $sql) {
            $db->exec($sql);
            $created[] = substr($sql, 13, 20);
        }
        foreach ([
            "ALTER TABLE seo_pages ADD COLUMN IF NOT EXISTS robots VARCHAR(100) NULL",
            "ALTER TABLE seo_pages ADD COLUMN IF NOT EXISTS hreflang JSON NULL",
        ] as $alter) {
            try {
                $db->exec($alter);
                $created[] = substr($alter, 0, 40);
            } catch (\Throwable $e) {
            }
        }

        self::$tablesReady = true;
        return ['ok' => true, 'message' => 'SEO Global Launch tables ready', 'tables' => $created];
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    public static function siteUrl(): string
    {
        static $cached = null;
        if ($cached !== null) return $cached;
        // URL pública canónica configurada por el administrador. Tiene
        // prioridad sobre el host de la petición para que sitemap, canonical y
        // cron usen siempre el dominio público, incluso en CLI o previews.
        $configured = trim(self::setting('seo_site_url'));
        if ($configured !== '' && preg_match('~^https?://~i', $configured)) {
            return $cached = rtrim($configured, '/');
        }
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '' && preg_match('/^[a-z0-9.-]+(?::\d+)?$/i', $host)) {
            $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) ? 'https' : 'http';
            return $cached = $scheme . '://' . $host;
        }
        $db = Database::instance();
        $stmt = $db->prepare('SELECT domain FROM sites WHERE id = @site_id');
        $stmt->execute();
        $domain = $stmt->fetchColumn();
        if ($domain) {
            $scheme = Config::get('APP_ENV', 'production') === 'local' ? 'http' : 'https';
            return $cached = $scheme . '://' . rtrim($domain, '/');
        }
        return $cached = rtrim((string)Config::get('APP_URL', 'https://wontia.com'), '/');
    }

    private static function setting(string $key, string $default = ''): string
    {
        $db = Database::instance();
        $stmt = $db->prepare('SELECT `value` FROM settings WHERE `key` = ? AND site_id = @site_id LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? (string)$row['value'] : $default;
    }

    private static function setSetting(string $key, string $value): void
    {
        $db = Database::instance();
        $stmt = $db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        $stmt->execute([$key, $value]);
    }

    /**
     * Llamada IA unificada: BRICK (AiRouter) primero, DeepSeek directo como fallback.
     */
    private static function ai(string $prompt, string $systemPrompt, string $function, float $temperature = 0.4, int $maxTokens = 2000): array
    {
        // 1. Capa BRICK de WWI
        try {
            $router = new AiRouter();
            $result = $router->route([
                'system_id' => 'wontia',
                'module' => 'seo-global-launch',
                'function' => $function,
                'system_prompt' => $systemPrompt,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ]);
            if (($result['ok'] ?? false) && !empty($result['content'])) {
                return [
                    'ok' => true,
                    'content' => (string)$result['content'],
                    'tokens' => (int)($result['usage']['total_tokens'] ?? 0),
                    'model' => (string)($result['model'] ?? ''),
                    'provider' => (string)($result['provider'] ?? ''),
                    'cost' => (float)($result['cost'] ?? 0),
                ];
            }
        } catch (\Throwable $e) {
            // BRICK no disponible — seguir al fallback
        }

        // 2. Fallback DeepSeek directo
        $key = (string)Config::get('DEEPSEEK_API_KEY', '');
        if ($key !== '') {
            $model = self::setting('seo_ai_model', 'deepseek-chat');
            $ch = curl_init('https://api.deepseek.com/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
                CURLOPT_POSTFIELDS => json_encode(['model' => $model, 'messages' => [['role' => 'system', 'content' => $systemPrompt], ['role' => 'user', 'content' => $prompt]], 'temperature' => $temperature, 'max_tokens' => $maxTokens]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
            ]);
            $resp = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($http === 200) {
                $data = json_decode($resp, true);
                $content = trim($data['choices'][0]['message']['content'] ?? '');
                if ($content !== '') {
                    return ['ok' => true, 'content' => $content, 'tokens' => (int)($data['usage']['total_tokens'] ?? 0), 'model' => $model, 'provider' => 'DeepSeek (direct)', 'cost' => 0];
                }
            }
            return ['ok' => false, 'error' => 'DeepSeek HTTP ' . $http];
        }

        return ['ok' => false, 'error' => 'No AI provider available (BRICK not provisioned, DEEPSEEK_API_KEY not set)'];
    }

    // ─────────────────────────────────────────────
    // SCAN
    // ─────────────────────────────────────────────

    public static function scan(): array
    {
        self::installTables();
        $db = Database::instance();
        $base = rtrim(self::siteUrl(), '/');
        $issues = [];
        $universe = 0;
        $metaOk = 0;
        $contentOk = 0;
        $counts = ['pages' => 0, 'categories' => 0, 'products' => 0, 'posts' => 0];

        $addIssue = static function (string $type, string $severity, string $url, string $issue, string $detail = '') use (&$issues): void {
            if (count($issues) >= 4000) return;
            $issues[] = ['type' => $type, 'severity' => $severity, 'url' => $url, 'issue' => $issue, 'detail' => $detail];
        };
        $text = static function ($html): string {
            return trim((string)preg_replace('/\s+/', ' ', strip_tags((string)$html)));
        };

        // 1. Páginas publicadas
        $sitePages = $db->query("SELECT id, slug, title, meta_title, meta_description FROM pages WHERE site_id = @site_id AND status = 'published' ORDER BY sort_order ASC")->fetchAll();
        foreach ($sitePages as $sp) {
            $slug = trim((string)($sp['slug'] ?? ''), '/');
            $url = $base . '/' . (in_array($slug, ['home', 'inicio'], true) ? '' : $slug);
            $counts['pages']++;
            $universe++;
            $title = trim((string)(($sp['meta_title'] ?? '') !== '' ? $sp['meta_title'] : ($sp['title'] ?? '')));
            $desc = trim((string)($sp['meta_description'] ?? ''));
            if ($title === '') {
                $addIssue('meta', 'high', $url, 'Sin title');
            } elseif (mb_strlen($title) > 60) {
                $addIssue('meta', 'low', $url, 'Title largo', mb_strlen($title) . ' caracteres');
            }
            if ($desc === '') {
                $addIssue('meta', 'high', $url, 'Sin meta description');
            } elseif (mb_strlen($desc) < 70) {
                $addIssue('meta', 'medium', $url, 'Meta description corta', mb_strlen($desc) . ' caracteres');
            } elseif (mb_strlen($desc) > 160) {
                $addIssue('meta', 'low', $url, 'Meta description larga', mb_strlen($desc) . ' caracteres');
            }
            if ($title !== '' && $desc !== '' && mb_strlen($title) <= 60 && mb_strlen($desc) >= 70 && mb_strlen($desc) <= 160) $metaOk++;
        }

        // 2. Categorías activas con ruta pública
        $cats = $db->query("SELECT id, name, path, description FROM store_categories WHERE site_id = @site_id AND is_active = 1 AND path IS NOT NULL AND path <> '' ORDER BY path ASC")->fetchAll();
        foreach ($cats as $cat) {
            $url = $base . '/' . ltrim((string)$cat['path'], '/');
            $counts['categories']++;
            $universe++;
            $name = trim((string)($cat['name'] ?? ''));
            $desc = $text($cat['description'] ?? '');
            if ($name === '') {
                $addIssue('meta', 'high', $url, 'Categoría sin nombre');
            } else {
                $metaOk++;
            }
            if ($desc === '') {
                $addIssue('content', 'low', $url, 'Categoría sin descripción editorial');
            } elseif (mb_strlen($desc) < 150) {
                $addIssue('content', 'low', $url, 'Descripción de categoría corta', mb_strlen($desc) . ' caracteres');
            } else {
                $contentOk++;
            }
        }

        // 3. Productos públicos
        $products = $db->query("SELECT id, slug, name, sku, vendor, category_id, price_cents, images, description, seo_title, seo_description
            FROM store_products
            WHERE site_id = @site_id AND status = 'active' AND visibility = 'public'")->fetchAll();
        foreach ($products as $p) {
            $handle = trim((string)($p['slug'] ?? ''));
            if ($handle === '') continue;
            $url = $base . '/producto/' . rawurlencode($handle);
            $counts['products']++;
            $universe++;
            $name = trim((string)($p['name'] ?? ''));
            $title = trim((string)($p['seo_title'] ?? '')) ?: $name;
            $desc = $text($p['seo_description'] ?? '');
            $body = $text($p['description'] ?? '');
            $effective = mb_strlen($body) >= 60 ? $body : $desc;
            if ($title === '') {
                $addIssue('meta', 'high', $url, 'Producto sin título');
            } elseif (mb_strlen($title) > 60) {
                $addIssue('meta', 'low', $url, 'Title largo', mb_strlen($title) . ' caracteres');
            }
            if ($effective === '') {
                $addIssue('content', 'high', $url, 'Sin descripción propia ni SEO description');
            } elseif (mb_strlen($effective) < 70) {
                $addIssue('content', 'low', $url, 'Descripción demasiado corta', mb_strlen($effective) . ' caracteres');
            }
            if ($title !== '' && mb_strlen($title) <= 60 && mb_strlen($effective) >= 70) $metaOk++;
            if (mb_strlen($body) < 20) {
                $addIssue('content', 'medium', $url, 'Producto sin descripción propia');
            } else {
                $contentOk++;
            }
            if (str_contains(strtolower($handle), '-copia')) {
                $addIssue('url', 'medium', $url, 'Slug duplicado (-copia)', 'Regenerar la URL desde el nombre y crear redirección 301');
            }
            $images = trim((string)($p['images'] ?? ''));
            if ($images === '' || $images === '[]') $addIssue('media', 'medium', $url, 'Producto sin imagen');
            if ((int)($p['category_id'] ?? 0) <= 0) $addIssue('taxonomy', 'medium', $url, 'Producto sin categoría');
            if ((int)($p['price_cents'] ?? 0) <= 0) $addIssue('commerce', 'high', $url, 'Producto sin precio');
            if (trim((string)($p['sku'] ?? '')) === '') $addIssue('commerce', 'low', $url, 'Producto sin SKU');
        }

        // 4. Blog (si existe)
        $posts = $db->query("SELECT id, title, slug, meta_title, meta_description FROM blog_posts WHERE site_id = @site_id AND status = 'published' ORDER BY published_at DESC")->fetchAll();
        foreach ($posts as $p) {
            $url = $base . '/blog/' . rawurlencode((string)$p['slug']);
            $counts['posts']++;
            $universe++;
            if (trim((string)($p['meta_title'] ?? '')) === '') $addIssue('meta', 'high', $url, 'Post sin title');
            if (trim((string)($p['meta_description'] ?? '')) === '') $addIssue('meta', 'high', $url, 'Post sin meta description');
        }

        // 5. Muestra HTTP real: home, primera categoría y primer producto.
        $sampleUrls = array_values(array_unique(array_filter([
            $base . '/',
            !empty($cats[0]['path']) ? $base . '/' . ltrim((string)$cats[0]['path'], '/') : null,
            !empty($products[0]['slug']) ? $base . '/producto/' . rawurlencode((string)$products[0]['slug']) : null,
        ])));
        $http = [];
        $schemaOk = 0;
        $ogOk = 0;
        $httpOk = 0;
        $canonicalOk = 0;
        $startedHttp = microtime(true);
        foreach ($sampleUrls as $url) {
            if (microtime(true) - $startedHttp > 25) break;
            $r = self::httpAudit($url);
            $http[] = $r;
            if ((int)$r['http'] === 200) $httpOk++;
            if ((int)$r['jsonld'] > 0) $schemaOk++;
            if ($r['og_title'] !== '' && $r['og_image'] !== '') $ogOk++;
            if ($r['canonical'] !== '') $canonicalOk++;
            if ((int)$r['http'] !== 200) $addIssue('technical', 'high', $url, 'La URL no responde 200', 'HTTP ' . (int)$r['http']);
            elseif ($r['title'] === '') $addIssue('technical', 'high', $url, 'Sin title renderizado');
            if ($r['canonical'] === '') $addIssue('technical', 'medium', $url, 'Sin canonical renderizado');
            if ((int)$r['jsonld'] === 0) $addIssue('schema', 'high', $url, 'Sin datos estructurados JSON-LD');
            if ((int)$r['h1'] === 0) $addIssue('technical', 'low', $url, 'Sin H1');
        }

        // 6. Sitemap y robots efectivos (sin depender de la red).
        $sitemap = '';
        try { $sitemap = self::sitemapXml(); } catch (\Throwable $e) {
        }
        $sitemapUrls = $sitemap !== '' ? substr_count($sitemap, '<loc>') : 0;
        $sitemapProducts = $sitemap !== '' && strpos($sitemap, '/producto/') !== false;
        $robots = '';
        try { $robots = self::robotsTxt(); } catch (\Throwable $e) {
        }
        $robotsSitemap = $robots !== '' && preg_match('~^Sitemap:\s*\S+~mi', $robots) === 1;
        if ($sitemapUrls === 0) $addIssue('technical', 'high', $base . '/sitemap.xml', 'Sitemap vacío o no disponible');
        if (!$sitemapProducts && $counts['products'] > 0) $addIssue('technical', 'high', $base . '/sitemap.xml', 'El sitemap no incluye productos');
        if (!$robotsSitemap) $addIssue('technical', 'medium', $base . '/robots.txt', 'Robots sin referencia al sitemap');

        // Salud técnica de los últimos 7 días (tráfico real de clientes).
        $totalReq7 = 0;
        $req4047 = 0;
        $avgLatency = 0;
        try {
            $row = $db->query("SELECT COUNT(*) total,
                SUM(status = 404 AND path NOT IN (SELECT from_path FROM seo_redirects WHERE site_id = @site_id AND is_active = 1)) n404,
                ROUND(AVG(duration_ms)) latency
                FROM seo_live_requests
                WHERE site_id = @site_id AND is_internal = 0 AND is_bot = 0 AND created_at >= (NOW() - INTERVAL 7 DAY)
                  AND path NOT REGEXP '\\\\.(ico|png|jpe?g|gif|svg|webp|css|js|map|woff2?|ttf|txt|xml)$'")->fetch();
            $totalReq7 = (int)($row['total'] ?? 0);
            $req4047 = (int)($row['n404'] ?? 0);
            $avgLatency = (int)($row['latency'] ?? 0);
        } catch (\Throwable $e) {
        }
        $rate404 = $totalReq7 > 0 ? $req4047 / $totalReq7 : 0;
        if ($rate404 > 0.05) $addIssue('technical', 'high', $base . '/', 'Tasa de 404 elevada', round($rate404 * 100, 1) . '% de las peticiones en 7 días');
        if ($avgLatency > 1500) $addIssue('technical', 'medium', $base . '/', 'Latencia promedio alta', $avgLatency . ' ms');

        // 7. Scores honestos y ponderados.
        $sample = max(1, count($http));
        $sitemapCoverage = $universe > 0 ? min(1, $sitemapUrls / $universe) : 0;
        $latencyFactor = $avgLatency <= 0 ? 1 : max(0, 1 - max(0, $avgLatency - 800) / 1700);
        $score = [
            'meta' => $universe > 0 ? (int)round($metaOk / $universe * 100) : 0,
            'content' => ($counts['categories'] + $counts['products']) > 0 ? (int)round($contentOk / ($counts['categories'] + $counts['products']) * 100) : 0,
            'schema' => (int)round($schemaOk / $sample * 100),
            'og' => (int)round($ogOk / $sample * 100),
            'technical' => (int)round(
                (($httpOk / $sample) * 25)
                + (($canonicalOk / $sample) * 15)
                + ($sitemapCoverage * 20)
                + ($robotsSitemap ? 10 : 0)
                + (max(0, 1 - ($rate404 / 0.20)) * 20)
                + ($latencyFactor * 10)
            ),
        ];
        $totalScore = (int)round($score['meta'] * 0.30 + $score['content'] * 0.20 + $score['schema'] * 0.20 + $score['og'] * 0.10 + $score['technical'] * 0.20);
        $bySeverity = ['high' => 0, 'medium' => 0, 'low' => 0];
        $byType = [];
        foreach ($issues as $i) {
            $bySeverity[$i['severity']] = ($bySeverity[$i['severity']] ?? 0) + 1;
            $byType[$i['type']] = ($byType[$i['type']] ?? 0) + 1;
        }
        $details = [
            'universe' => $universe,
            'counts' => $counts,
            'coverage' => ['meta' => $metaOk, 'content' => $contentOk],
            'sitemap_urls' => $sitemapUrls,
            'sitemap_products' => $sitemapProducts,
            'robots_sitemap' => $robotsSitemap,
            'requests_7d' => $totalReq7,
            'requests_404_7d' => $req4047,
            'rate_404_7d' => round($rate404 * 100, 2),
            'avg_latency_ms' => $avgLatency,
            'http_sample' => $http,
            'issues_by_severity' => $bySeverity,
            'issues_by_type' => $byType,
            'issues_count' => count($issues),
        ];
        $db->prepare('INSERT INTO seo_scores (site_id, scan_date, total_score, meta_score, schema_score, og_score, technical_score, content_score, details) VALUES (@site_id, NOW(), ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$totalScore, $score['meta'], $score['schema'], $score['og'], $score['technical'], $score['content'], json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        $runId = (int)$db->lastInsertId();

        try {
            $db->prepare('DELETE FROM seo_audit_issues WHERE site_id = @site_id')->execute();
            $ins = $db->prepare('INSERT INTO seo_audit_issues (site_id, run_id, url, issue_type, severity, issue, detail) VALUES (@site_id, ?, ?, ?, ?, ?, ?)');
            foreach (array_slice($issues, 0, 2500) as $i) {
                $ins->execute([$runId, $i['url'], $i['type'], $i['severity'], $i['issue'], $i['detail']]);
            }
        } catch (\Throwable $e) {
        }

        return [
            'ok' => true,
            'total_score' => $totalScore,
            'scores' => $score,
            'pages_count' => $universe,
            'posts_count' => $counts['posts'],
            'issues_count' => count($issues),
            'issues_by_severity' => $bySeverity,
            'details' => $details,
            'issues' => array_slice($issues, 0, 20),
        ];
    }

    /** Comprueba el HTML público de una URL para el scan. */
    private static function httpAudit(string $url): array
    {
        $out = ['url' => $url, 'http' => 0, 'title' => '', 'meta_description' => '', 'canonical' => '', 'og_title' => '', 'og_image' => '', 'jsonld' => 0, 'h1' => 0];
        if (!function_exists('curl_init')) return $out;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; SonikaSEOAudit/1.0)',
        ]);
        $html = (string)curl_exec($ch);
        $out['http'] = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($out['http'] !== 200 || $html === '') return $out;
        $pick = static function (string $re) use ($html): string {
            return preg_match($re, $html, $m) ? trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8')) : '';
        };
        $out['title'] = $pick('~<title[^>]*>(.*?)</title>~is');
        $out['meta_description'] = $pick('~<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)~i');
        $out['canonical'] = $pick('~<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']*)~i');
        $out['og_title'] = $pick('~<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']*)~i');
        $out['og_image'] = $pick('~<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']*)~i');
        $out['jsonld'] = preg_match_all('~<script[^>]+type=["\']application/ld\+json["\']~i', $html);
        $out['h1'] = preg_match_all('~<h1\b~i', $html);
        return $out;
    }

    // ─────────────────────────────────────────────
    // GENERATE — metadatos IA para todas las páginas
    // ─────────────────────────────────────────────

    public static function generate(): array
    {
        self::installTables();
        $db = Database::instance();
        $base = self::siteUrl();
        $siteName = self::setting('site_name', 'Wontia');
        $siteDesc = self::setting('site_desc', '');

        $sitePages = $db->query("SELECT slug, title FROM pages WHERE site_id = @site_id AND status = 'published'")->fetchAll();
        $results = [];
        $totalTokens = 0;

        $batchPrompt = "Genera metadatos SEO para {$siteName}" . ($siteDesc ? " ({$siteDesc})" : '') . ". Para cada página, devuelve JSON con: seo_title (máx 60 chars, incluye keyword principal), seo_description (máx 155 chars, persuasivo con CTA), seo_keywords (5-8 keywords separadas por comas), og_title (atractivo para redes sociales), og_description (para compartir en redes). IDIOMA: español (Colombia).\n\nPÁGINAS:\n";
        foreach ($sitePages as $p) {
            $batchPrompt .= "- {$p['title']}: {$base}/{$p['slug']}\n";
        }
        $batchPrompt .= "\nResponde ÚNICAMENTE con un JSON array de objetos: [{\"url\":\"...\",\"seo_title\":\"...\",\"seo_description\":\"...\",\"seo_keywords\":\"...\",\"og_title\":\"...\",\"og_description\":\"...\"}]";

        $ai = self::ai($batchPrompt, 'Eres un experto SEO de e-commerce en español (Colombia). Respondes SOLO con JSON válido.', 'generate', 0.4, 2000);
        if ($ai['ok']) {
            $totalTokens += $ai['tokens'];
            $content = preg_replace('/^```(?:json)?\s*/i', '', trim($ai['content']));
            $content = preg_replace('/\s*```$/', '', $content);
            $generated = json_decode($content, true);
            if ($generated && is_array($generated)) {
                foreach ($generated as $g) {
                    if (empty($g['url'])) continue;
                    self::upsertPage((string)$g['url'], 'page', (string)($g['seo_title'] ?? ''), (string)($g['seo_description'] ?? ''), (string)($g['seo_keywords'] ?? ''), (string)($g['og_title'] ?? ''), (string)($g['og_description'] ?? ''));
                    $results[] = ['url' => $g['url'], 'ok' => true, 'title' => $g['seo_title'] ?? ''];
                }
            } else {
                $results[] = ['url' => 'AI', 'ok' => false, 'error' => 'AI returned invalid JSON'];
            }
        } else {
            $results[] = ['url' => 'AI', 'ok' => false, 'error' => $ai['error']];
        }

        self::scan();

        return [
            'ok' => true,
            'pages_optimized' => count(array_filter($results, fn($r) => $r['ok'] ?? false)),
            'results' => $results,
            'tokens_used' => $totalTokens,
            'ai_model' => $ai['model'] ?? null,
            'ai_provider' => $ai['provider'] ?? null,
        ];
    }

    private static function upsertPage(string $url, string $type, string $title, string $desc, string $kw, string $ogTitle, string $ogDesc): void
    {
        $db = Database::instance();
        $stmt = $db->prepare('INSERT INTO seo_pages (site_id, page_url, page_type, seo_title, seo_description, seo_keywords, og_title, og_description, canonical_url, last_modified) VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE seo_title=?, seo_description=?, seo_keywords=?, og_title=?, og_description=?, last_modified=NOW()');
        $stmt->execute([$url, $type, $title, $desc, $kw, $ogTitle, $ogDesc, $url, $title, $desc, $kw, $ogTitle, $ogDesc]);
    }

    // ─────────────────────────────────────────────
    // AUTOFIX — metadatos IA para posts sin meta
    // ─────────────────────────────────────────────

    public static function autofix(): array
    {
        self::installTables();
        $db = Database::instance();
        $siteName = self::setting('site_name', 'Wontia');

        $posts = $db->query("SELECT id, title, slug, excerpt, content FROM blog_posts WHERE site_id = @site_id AND status = 'published' AND (meta_title IS NULL OR meta_title = '' OR meta_description IS NULL OR meta_description = '')")->fetchAll();
        if (empty($posts)) return ['ok' => true, 'fixed' => 0, 'message' => 'All posts already have SEO metadata'];

        $batchPrompt = "Genera SEO metadata para estos artículos de blog de {$siteName}. Para cada artículo, devuelve SOLO un JSON array de objetos con: id (el mismo id numérico), meta_title (max 60 chars, incluye keyword principal), meta_description (max 155 chars, persuasivo con CTA implícito). IDIOMA: español (Colombia).\n\n";
        foreach ($posts as $p) {
            $excerpt = substr(strip_tags((string)($p['excerpt'] ?? $p['content'] ?? '')), 0, 200);
            $batchPrompt .= "ID:{$p['id']} | Título: {$p['title']} | Extracto: {$excerpt}\n";
        }
        $batchPrompt .= "\nResponde ÚNICAMENTE con: [{\"id\":1,\"meta_title\":\"...\",\"meta_description\":\"...\"},...]";

        $ai = self::ai($batchPrompt, 'Eres un experto SEO de e-commerce en Colombia. Respondes SOLO con JSON válido, sin markdown.', 'autofix', 0.4, 2000);
        if (!$ai['ok']) return ['ok' => false, 'error' => $ai['error']];

        $content = preg_replace('/^```(?:json)?\s*/i', '', trim($ai['content']));
        $content = preg_replace('/\s*```$/', '', $content);
        $generated = json_decode($content, true);
        if (!$generated || !is_array($generated)) return ['ok' => false, 'error' => 'AI returned invalid JSON'];

        $stmt = $db->prepare('UPDATE blog_posts SET meta_title=?, meta_description=? WHERE id=? AND site_id = @site_id');
        $fixed = 0;
        foreach ($generated as $g) {
            if (empty($g['id'])) continue;
            $stmt->execute([(string)($g['meta_title'] ?? ''), (string)($g['meta_description'] ?? ''), (int)$g['id']]);
            $fixed++;
        }

        return ['ok' => true, 'fixed' => $fixed, 'total' => count($posts), 'tokens' => $ai['tokens'], 'ai_model' => $ai['model'] ?? null];
    }

    // ─────────────────────────────────────────────
    // DEEPFIX — expandir contenido, alt text, headings
    // ─────────────────────────────────────────────

    public static function deepfix(): array
    {
        self::installTables();
        $db = Database::instance();
        $results = [];
        $totalTokens = 0;

        // 1. Posts con bajo word count
        $posts = $db->query("SELECT id, title, content, excerpt FROM blog_posts WHERE site_id = @site_id AND status = 'published'")->fetchAll();
        $lowWord = array_filter($posts, fn($p) => str_word_count(strip_tags($p['content'] ?? '')) < 300);
        foreach ($lowWord as $p) {
            $wc = str_word_count(strip_tags($p['content'] ?? ''));
            $prompt = "Expande este artículo de blog a 500-800 palabras manteniendo el tono comercial claro de una tienda de instrumentos musicales en Colombia. Conserva TODOS los datos, cifras y nombres. Agrega 2-3 párrafos adicionales con valor añadido (casos de uso, beneficios, consejos de compra). NO cambies el título ni la estructura existente. Devuelve SOLO el HTML expandido, sin explicaciones.\n\nTítulo: {$p['title']}\nContenido actual ({$wc} palabras): {$p['content']}";
            $r = self::ai($prompt, 'Eres un editor de contenido de e-commerce en español (Colombia). Respondes SOLO con el contenido solicitado, sin markdown, sin explicaciones.', 'deepfix', 0.5, 2500);
            if ($r['ok']) {
                $db->prepare('UPDATE blog_posts SET content=? WHERE id=? AND site_id = @site_id')->execute([$r['content'], $p['id']]);
                $results[] = ['type' => 'word_count', 'post_id' => $p['id'], 'title' => $p['title'], 'ok' => true];
                $totalTokens += $r['tokens'];
            } else {
                $results[] = ['type' => 'word_count', 'post_id' => $p['id'], 'title' => $p['title'], 'ok' => false, 'error' => $r['error']];
            }
        }

        // 2. Cover images sin alt text
        $noAlt = $db->query("SELECT id, title, cover_image FROM blog_posts WHERE site_id = @site_id AND status = 'published' AND cover_image IS NOT NULL AND cover_image != '' AND (cover_alt IS NULL OR cover_alt = '')")->fetchAll();
        foreach ($noAlt as $p) {
            $prompt = "Genera un alt text descriptivo para una imagen de portada de artículo de blog (max 125 chars, SEO-friendly, incluye keyword). Título del artículo: {$p['title']}. Devuelve SOLO el alt text, sin comillas ni explicaciones.";
            $r = self::ai($prompt, 'Eres un experto SEO. Respondes SOLO con el alt text solicitado.', 'deepfix', 0.4, 300);
            if ($r['ok']) {
                $alt = trim(str_replace(['"', "'"], '', $r['content']));
                $db->prepare('UPDATE blog_posts SET cover_alt=? WHERE id=? AND site_id = @site_id')->execute([$alt, $p['id']]);
                $results[] = ['type' => 'alt_text', 'post_id' => $p['id'], 'title' => $p['title'], 'ok' => true, 'alt' => $alt];
                $totalTokens += $r['tokens'];
            } else {
                $results[] = ['type' => 'alt_text', 'post_id' => $p['id'], 'title' => $p['title'], 'ok' => false, 'error' => $r['error']];
            }
        }

        // 3. Posts sin H2
        $noH2 = array_filter($posts, fn($p) => substr_count(strtolower($p['content'] ?? ''), '<h2') < 2);
        foreach ($noH2 as $p) {
            $prompt = "Reestructura este artículo de blog agregando 2-3 encabezados H2 descriptivos y relevantes. NO cambies el contenido existente, SOLO agrega etiquetas <h2> alrededor de las frases existentes que funcionen como títulos de sección. Si no hay frases adecuadas, inserta nuevos H2 ANTES de los párrafos existentes con títulos breves (3-6 palabras). Devuelve SOLO el HTML reestructurado, sin explicaciones.\n\nTítulo: {$p['title']}\nContenido: {$p['content']}";
            $r = self::ai($prompt, 'Eres un editor de contenido B2B experto. Respondes SOLO con el HTML solicitado.', 'deepfix', 0.4, 2500);
            if ($r['ok']) {
                $db->prepare('UPDATE blog_posts SET content=? WHERE id=? AND site_id = @site_id')->execute([$r['content'], $p['id']]);
                $results[] = ['type' => 'headings', 'post_id' => $p['id'], 'title' => $p['title'], 'ok' => true];
                $totalTokens += $r['tokens'];
            } else {
                $results[] = ['type' => 'headings', 'post_id' => $p['id'], 'title' => $p['title'], 'ok' => false, 'error' => $r['error']];
            }
        }

        return ['ok' => true, 'results' => $results, 'tokens' => $totalTokens, 'fixed' => count(array_filter($results, fn($r) => $r['ok'] ?? false))];
    }

    // ─────────────────────────────────────────────
    // Auditoría de contenido
    // ─────────────────────────────────────────────

    public static function issues(): array
    {
        $db = Database::instance();
        $issues = [];
        self::installTables();

        // Hallazgos del último scan real (catálogo completo).
        try {
            $rows = $db->query("SELECT url, issue_type, severity, issue, detail FROM seo_audit_issues WHERE site_id = @site_id ORDER BY FIELD(severity, 'high', 'medium', 'low'), id ASC LIMIT 800")->fetchAll();
            if ($rows) {
                $counts = $db->query("SELECT severity, COUNT(*) c FROM seo_audit_issues WHERE site_id = @site_id GROUP BY severity")->fetchAll(\PDO::FETCH_KEY_PAIR);
                $issues = array_map(static function (array $r): array {
                    return [
                        'type' => $r['issue_type'],
                        'severity' => $r['severity'],
                        'post_id' => 0,
                        'title' => $r['url'],
                        'slug' => '',
                        'url' => $r['url'],
                        'issue' => $r['issue'] . (!empty($r['detail']) ? ' · ' . $r['detail'] : ''),
                        'fix' => '',
                        'manual' => false,
                    ];
                }, $rows);
                return ['ok' => true, 'issues' => $issues, 'total' => array_sum($counts), 'high' => (int)($counts['high'] ?? 0), 'medium' => (int)($counts['medium'] ?? 0), 'low' => (int)($counts['low'] ?? 0)];
            }
        } catch (\Throwable $e) {
        }

        $posts = $db->query("SELECT id, title, slug, meta_title, meta_description, excerpt, content, cover_image, cover_alt FROM blog_posts WHERE site_id = @site_id AND status = 'published'")->fetchAll();
        foreach ($posts as $p) {
            if (empty($p['meta_title'])) {
                $issues[] = ['type' => 'meta', 'severity' => 'high', 'post_id' => $p['id'], 'title' => $p['title'], 'slug' => $p['slug'], 'issue' => 'Missing SEO title', 'fix' => 'Add a descriptive title with primary keyword (50-60 chars)', 'manual' => false];
            }
            if (empty($p['meta_description'])) {
                $issues[] = ['type' => 'meta', 'severity' => 'medium', 'post_id' => $p['id'], 'title' => $p['title'], 'slug' => $p['slug'], 'issue' => 'Missing meta description', 'fix' => 'Add persuasive description with CTA (max 155 chars)', 'manual' => false];
            }
            if (!empty($p['content'])) {
                preg_match_all('/<img[^>]+src="([^"]+)"[^>]*>/i', $p['content'], $imgs);
                foreach ($imgs[0] as $idx => $imgTag) {
                    if (stripos($imgTag, 'alt=') === false || preg_match('/alt=["\']\s*["\']/', $imgTag)) {
                        $issues[] = ['type' => 'alt_text', 'severity' => 'medium', 'post_id' => $p['id'], 'title' => $p['title'], 'slug' => $p['slug'], 'issue' => 'Image missing alt text: ' . ($imgs[1][$idx] ?? ''), 'fix' => 'Add descriptive alt text for accessibility and SEO', 'manual' => true];
                    }
                }
            }
            if (!empty($p['cover_image']) && empty($p['cover_alt'])) {
                $issues[] = ['type' => 'alt_text', 'severity' => 'low', 'post_id' => $p['id'], 'title' => $p['title'], 'slug' => $p['slug'], 'issue' => 'Cover image missing alt text', 'fix' => 'Add alt text describing the cover image', 'manual' => true];
            }
            $wc = str_word_count(strip_tags($p['content'] ?? ''));
            if ($wc < 300 && $wc > 0) {
                $issues[] = ['type' => 'content', 'severity' => 'medium', 'post_id' => $p['id'], 'title' => $p['title'], 'slug' => $p['slug'], 'issue' => "Low word count ({$wc} words)", 'fix' => 'Expand to at least 500-800 words for better SEO ranking', 'manual' => true];
            }
            $h2count = substr_count(strtolower($p['content'] ?? ''), '<h2');
            if ($h2count < 2) {
                $issues[] = ['type' => 'structure', 'severity' => 'low', 'post_id' => $p['id'], 'title' => $p['title'], 'slug' => $p['slug'], 'issue' => "Only {$h2count} H2 heading(s)", 'fix' => 'Add at least 2-3 descriptive H2 headings to structure content', 'manual' => true];
            }
        }

        $seoPages = $db->query('SELECT page_url, seo_title, seo_description FROM seo_pages WHERE site_id = @site_id')->fetchAll();
        foreach ($seoPages as $pg) {
            if (empty($pg['seo_title'])) {
                $issues[] = ['type' => 'meta', 'severity' => 'high', 'post_id' => 0, 'title' => $pg['page_url'], 'slug' => '', 'issue' => 'Page missing SEO title: ' . $pg['page_url'], 'fix' => 'Run SEO Global Launch > Generate to auto-fix', 'manual' => false];
            }
        }

        return ['ok' => true, 'issues' => $issues, 'total' => count($issues), 'high' => count(array_filter($issues, fn($i) => $i['severity'] === 'high')), 'medium' => count(array_filter($issues, fn($i) => $i['severity'] === 'medium')), 'low' => count(array_filter($issues, fn($i) => $i['severity'] === 'low'))];
    }

    // ─────────────────────────────────────────────
    // Datos auxiliares
    // ─────────────────────────────────────────────

    public static function pages(): array
    {
        $db = Database::instance();
        $rows = $db->query('SELECT * FROM seo_pages WHERE site_id = @site_id ORDER BY page_type, page_url')->fetchAll();
        return ['ok' => true, 'pages' => $rows, 'total' => count($rows)];
    }

    public static function scores(): array
    {
        $db = Database::instance();
        $rows = $db->query('SELECT * FROM seo_scores WHERE site_id = @site_id ORDER BY scan_date DESC LIMIT 30')->fetchAll();
        return ['ok' => true, 'scores' => $rows, 'total' => count($rows)];
    }

    public static function bots(): array
    {
        $logCandidates = [
            '/var/log/nginx/access.log',
            '/app/var/log/nginx/access.log',
            '/var/log/nginx/access.log',
        ];
        $logFile = null;
        foreach ($logCandidates as $c) {
            if (file_exists($c)) { $logFile = $c; break; }
        }
        if (!$logFile) return ['ok' => true, 'bots' => [], 'note' => 'Access log not accessible from container'];

        $knownBots = [
            'Googlebot' => ['icon' => '🕷️', 'name' => 'Googlebot'],
            'Bingbot' => ['icon' => '🕷️', 'name' => 'Bingbot'],
            'GPTBot' => ['icon' => '🤖', 'name' => 'OpenAI'],
            'ClaudeBot' => ['icon' => '🤖', 'name' => 'Anthropic'],
            'Slurp' => ['icon' => '🕷️', 'name' => 'Yahoo'],
            'DuckDuckBot' => ['icon' => '🕷️', 'name' => 'DuckDuckGo'],
            'YandexBot' => ['icon' => '🕷️', 'name' => 'Yandex'],
            'facebookexternalhit' => ['icon' => '📘', 'name' => 'Facebook'],
            'Twitterbot' => ['icon' => '𝕏', 'name' => 'Twitter/X'],
            'LinkedInBot' => ['icon' => '💼', 'name' => 'LinkedIn'],
            'AhrefsBot' => ['icon' => '🔍', 'name' => 'Ahrefs'],
            'SemrushBot' => ['icon' => '📊', 'name' => 'SEMrush'],
        ];

        $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) return ['ok' => true, 'bots' => []];
        $lines = array_slice($lines, -500);
        $bots = [];

        foreach ($lines as $line) {
            foreach ($knownBots as $botKey => $botInfo) {
                if (stripos($line, $botKey) !== false) {
                    if (!isset($bots[$botKey])) {
                        $bots[$botKey] = ['icon' => $botInfo['icon'], 'name' => $botInfo['name'], 'visits' => 0, 'pages' => [], 'last' => null];
                    }
                    $bots[$botKey]['visits']++;
                    if (preg_match('/"GET\s+(\S+)\s+HTTP/', $line, $m)) {
                        $page = $m[1];
                        if (!in_array($page, $bots[$botKey]['pages'])) $bots[$botKey]['pages'][] = $page;
                    }
                    if (preg_match('/\[(\d{2}\/\w+\/\d{4}:\d{2}:\d{2}:\d{2})/', $line, $tm)) {
                        $bots[$botKey]['last'] = $tm[1];
                    }
                }
            }
        }

        return ['ok' => true, 'bots' => array_values($bots)];
    }

    public static function overview(): array
    {
        $db = Database::instance();
        self::installTables();
        $meta = self::meta();
        $installed = BrickSystem::findBySlug($meta['slug']);

        $last = $db->query('SELECT * FROM seo_scores WHERE site_id = @site_id ORDER BY scan_date DESC LIMIT 1')->fetch();
        $details = [];
        if ($last && !empty($last['details'])) {
            $decoded = json_decode((string)$last['details'], true);
            if (is_array($decoded)) $details = $decoded;
        }
        $pagesIndexable = (int)$db->query("SELECT COUNT(*) FROM pages WHERE site_id = @site_id AND status = 'published' AND no_index = 0")->fetchColumn();
        $categoriesIndexable = (int)$db->query("SELECT COUNT(*) FROM store_categories WHERE site_id = @site_id AND is_active = 1 AND path IS NOT NULL AND path <> ''")->fetchColumn();
        $productsIndexable = (int)$db->query("SELECT COUNT(*) FROM store_products WHERE site_id = @site_id AND status = 'active' AND visibility = 'public' AND slug IS NOT NULL AND slug <> ''")->fetchColumn();
        $posts = (int)$db->query("SELECT COUNT(*) FROM blog_posts WHERE site_id = @site_id AND status = 'published'")->fetchColumn();
        $postsNoMeta = (int)$db->query("SELECT COUNT(*) FROM blog_posts WHERE site_id = @site_id AND status = 'published' AND (meta_title IS NULL OR meta_title = '' OR meta_description IS NULL OR meta_description = '')")->fetchColumn();
        $indexable = $pagesIndexable + $categoriesIndexable + $productsIndexable + $posts;

        return [
            'ok' => true,
            'data' => [
                'brick' => [
                    'name' => $meta['name'],
                    'slug' => $meta['slug'],
                    'version' => $meta['version'],
                    'installed' => (bool)$installed,
                    'status' => $installed['status'] ?? 'not_installed',
                ],
                'stats' => [
                    'site_url' => self::siteUrl(),
                    'last_scan' => $last['scan_date'] ?? null,
                    'last_score' => (int)($last['total_score'] ?? 0),
                    'seo_pages' => $indexable,
                    'indexable_urls' => $indexable,
                    'pages' => $pagesIndexable,
                    'categories' => $categoriesIndexable,
                    'products' => $productsIndexable,
                    'posts_total' => $posts,
                    'posts_without_meta' => $postsNoMeta,
                    'sitemap_urls' => (int)($details['sitemap_urls'] ?? 0),
                    'issues_high' => (int)($details['issues_by_severity']['high'] ?? 0),
                    'issues_medium' => (int)($details['issues_by_severity']['medium'] ?? 0),
                    'issues_low' => (int)($details['issues_by_severity']['low'] ?? 0),
                    'audit' => $details,
                ],
            ],
        ];
    }

    // ─────────────────────────────────────────────
    // Renderizadores (head tags / schema)
    // ─────────────────────────────────────────────

    public static function headTags(string $url): string
    {
        $db = Database::instance();
        $stmt = $db->prepare('SELECT * FROM seo_pages WHERE page_url = ? AND site_id = @site_id LIMIT 1');
        $stmt->execute([$url]);
        $row = $stmt->fetch();
        if (!$row) return '';

        $esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $html = '';
        if (!empty($row['seo_title'])) $html .= '<title>' . $esc($row['seo_title']) . '</title>' . "\n";
        if (!empty($row['seo_description'])) $html .= '<meta name="description" content="' . $esc($row['seo_description']) . '"/>' . "\n";
        if (!empty($row['seo_keywords'])) $html .= '<meta name="keywords" content="' . $esc($row['seo_keywords']) . '"/>' . "\n";
        if (!empty($row['og_title'])) $html .= '<meta property="og:title" content="' . $esc($row['og_title']) . '"/>' . "\n";
        if (!empty($row['og_description'])) $html .= '<meta property="og:description" content="' . $esc($row['og_description']) . '"/>' . "\n";
        if (!empty($row['og_image'])) $html .= '<meta property="og:image" content="' . $esc($row['og_image']) . '"/>' . "\n";
        $html .= '<meta property="og:url" content="' . $esc($row['page_url']) . '"/>' . "\n";
        $html .= '<meta property="og:type" content="website"/>' . "\n";
        $html .= '<meta property="og:site_name" content="' . $esc(self::setting('site_name', 'Wontia')) . '"/>' . "\n";
        $html .= '<meta name="twitter:card" content="summary_large_image"/>' . "\n";
        if (!empty($row['seo_title'])) $html .= '<meta name="twitter:title" content="' . $esc($row['seo_title']) . '"/>' . "\n";
        if (!empty($row['seo_description'])) $html .= '<meta name="twitter:description" content="' . $esc($row['seo_description']) . '"/>' . "\n";
        if (!empty($row['canonical_url'])) $html .= '<link rel="canonical" href="' . $esc($row['canonical_url']) . '"/>' . "\n";

        if ($url === self::siteUrl() . '/') {
            $html .= '<script type="application/ld+json">' . self::organizationSchema() . '</script>' . "\n";
        }
        $breadcrumb = self::breadcrumb($url);
        if ($breadcrumb) {
            $html .= '<script type="application/ld+json">' . $breadcrumb . '</script>' . "\n";
        }

        return $html;
    }

    public static function organizationSchema(): string
    {
        $siteName = self::setting('site_name', 'Wontia');
        $siteUrl = self::siteUrl();
        $logo = self::setting('logo_url', '');
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $siteName,
            'url' => $siteUrl,
            'logo' => $logo ?: '',
            'description' => self::setting('site_desc', ''),
        ];
        return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public static function breadcrumb(string $url): string
    {
        $base = self::siteUrl();
        $parts = [];
        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        $parts[] = ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $base . '/'];

        if ($path === '/' || $path === '') return '';
        if (strpos($path, '/blog/') === 0) {
            $parts[] = ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => $base . '/blog'];
            $slug = substr($path, 6);
            if ($slug) {
                $db = Database::instance();
                $stmt = $db->prepare('SELECT title FROM blog_posts WHERE slug = ? AND site_id = @site_id LIMIT 1');
                $stmt->execute([rtrim($slug, '/')]);
                $post = $stmt->fetch();
                if ($post) $parts[] = ['@type' => 'ListItem', 'position' => 3, 'name' => $post['title']];
            }
        } else {
            $slug = trim($path, '/');
            if ($slug === '') return '';
            $db = Database::instance();
            $stmt = $db->prepare('SELECT title FROM pages WHERE slug = ? AND site_id = @site_id LIMIT 1');
            $stmt->execute([$slug]);
            $page = $stmt->fetch();
            if ($page) {
                $parts[] = ['@type' => 'ListItem', 'position' => 2, 'name' => $page['title']];
            } else {
                return '';
            }
        }

        return json_encode(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $parts], JSON_UNESCAPED_SLASHES);
    }
}
