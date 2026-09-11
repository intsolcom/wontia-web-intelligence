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
            'version' => '1.0.0',
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
        ];
    }

    // ─────────────────────────────────────────────
    // Tablas multi-tenant
    // ─────────────────────────────────────────────

    public static function installTables(): array
    {
        $db = Database::instance();
        $created = [];

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
        ];

        foreach ($stmts as $sql) {
            $db->exec($sql);
            $created[] = substr($sql, 13, 20);
        }

        return ['ok' => true, 'message' => 'SEO Global Launch tables ready', 'tables' => $created];
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    private static function siteUrl(): string
    {
        $db = Database::instance();
        $stmt = $db->prepare('SELECT domain FROM sites WHERE id = @site_id');
        $stmt->execute();
        $domain = $stmt->fetchColumn();
        if ($domain) {
            $scheme = Config::get('APP_ENV', 'production') === 'local' ? 'http' : 'https';
            return $scheme . '://' . rtrim($domain, '/');
        }
        return rtrim((string)Config::get('APP_URL', 'https://wontia.com'), '/');
    }

    private static function setting(string $key, string $default = ''): string
    {
        $db = Database::instance();
        $stmt = $db->prepare('SELECT `value` FROM settings WHERE `key` = ? AND site_id = @site_id LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? (string)$row['value'] : $default;
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
        $base = self::siteUrl();
        $pages = [];
        $issues = [];
        $score = ['meta' => 0, 'schema' => 0, 'og' => 0, 'technical' => 0, 'content' => 0];

        // Static pages del sitio
        $sitePages = $db->query("SELECT id, slug, title, meta_title, meta_description FROM pages WHERE site_id = @site_id AND status = 'published' ORDER BY sort_order ASC")->fetchAll();
        foreach ($sitePages as $sp) {
            $url = $base . '/' . ltrim($sp['slug'], '/');
            $stmt = $db->prepare('SELECT * FROM seo_pages WHERE page_url = ? AND site_id = @site_id');
            $stmt->execute([$url]);
            $row = $stmt->fetch();
            $pages[] = [
                'slug' => $sp['slug'],
                'url' => $url,
                'type' => $sp['slug'] === 'home' ? 'home' : 'page',
                'title' => $sp['title'],
                'has_meta' => !empty($row['seo_title']) || !empty($sp['meta_title']),
                'has_og' => !empty($row['og_title']),
                'seo' => $row ?: null,
            ];
            if (empty($row['seo_title']) && empty($sp['meta_title'])) {
                $issues[] = ['type' => 'meta', 'url' => $url, 'issue' => 'No SEO title'];
                $score['meta'] += 10;
            }
            if (empty($row['og_title'])) {
                $issues[] = ['type' => 'og', 'url' => $url, 'issue' => 'No Open Graph tags'];
                $score['og'] += 10;
            }
        }

        // Blog posts
        $posts = $db->query("SELECT id, title, slug, meta_title, meta_description, excerpt, cover_image, cover_alt, updated_at FROM blog_posts WHERE site_id = @site_id AND status = 'published' ORDER BY published_at DESC")->fetchAll();
        foreach ($posts as $p) {
            $url = $base . '/blog/' . $p['slug'];
            $stmt = $db->prepare('SELECT * FROM seo_pages WHERE page_url = ? AND site_id = @site_id');
            $stmt->execute([$url]);
            $row = $stmt->fetch();
            $pages[] = [
                'slug' => $p['slug'],
                'url' => $url,
                'type' => 'blog_post',
                'title' => $p['title'],
                'has_meta' => !empty($p['meta_title']),
                'has_og' => !empty($row['og_title']),
                'seo' => $row ?: null,
            ];
            if (empty($p['meta_title'])) { $issues[] = ['type' => 'meta', 'url' => $url, 'issue' => 'Post missing meta title']; $score['meta'] += 5; }
            if (empty($p['meta_description'])) { $issues[] = ['type' => 'meta', 'url' => $url, 'issue' => 'Post missing meta description']; $score['meta'] += 5; }
        }

        $totalScore = max(10, 100 - (count($issues) * 3));
        $score['meta'] = max(0, 100 - $score['meta']);
        $score['schema'] = $db->query('SELECT COUNT(*) FROM seo_pages WHERE site_id = @site_id AND schema_type IS NOT NULL')->fetchColumn() > 0 ? 80 : 20;
        $score['og'] = max(0, 100 - $score['og']);
        $score['technical'] = 90;
        $score['content'] = count($posts) > 0 ? 70 : 30;

        $db->prepare('INSERT INTO seo_scores (site_id, scan_date, total_score, meta_score, schema_score, og_score, technical_score, content_score, details) VALUES (@site_id, NOW(), ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$totalScore, $score['meta'], $score['schema'], $score['og'], $score['technical'], $score['content'], json_encode(['issues_count' => count($issues), 'pages_count' => count($pages)])]);

        return [
            'ok' => true,
            'total_score' => $totalScore,
            'scores' => $score,
            'pages_count' => count($pages),
            'posts_count' => count($posts),
            'issues_count' => count($issues),
            'issues' => array_slice($issues, 0, 20),
        ];
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

        $batchPrompt = "Genera metadatos SEO para {$siteName}" . ($siteDesc ? " ({$siteDesc})" : '') . ". Para cada página, devuelve JSON con: seo_title (máx 60 chars, incluye keyword principal), seo_description (máx 155 chars, persuasivo con CTA), seo_keywords (5-8 keywords separadas por comas), og_title (atractivo para redes sociales), og_description (para compartir en redes). IDIOMA: inglés.\n\nPÁGINAS:\n";
        foreach ($sitePages as $p) {
            $batchPrompt .= "- {$p['title']}: {$base}/{$p['slug']}\n";
        }
        $batchPrompt .= "\nResponde ÚNICAMENTE con un JSON array de objetos: [{\"url\":\"...\",\"seo_title\":\"...\",\"seo_description\":\"...\",\"seo_keywords\":\"...\",\"og_title\":\"...\",\"og_description\":\"...\"}]";

        $ai = self::ai($batchPrompt, 'Eres un experto SEO. Respondes SOLO con JSON válido.', 'generate', 0.4, 2000);
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

        $batchPrompt = "Genera SEO metadata para estos artículos de blog de {$siteName}. Para cada artículo, devuelve SOLO un JSON array de objetos con: id (el mismo id numérico), meta_title (max 60 chars, incluye keyword principal), meta_description (max 155 chars, persuasivo con CTA implícito). IDIOMA: inglés.\n\n";
        foreach ($posts as $p) {
            $excerpt = substr(strip_tags((string)($p['excerpt'] ?? $p['content'] ?? '')), 0, 200);
            $batchPrompt .= "ID:{$p['id']} | Título: {$p['title']} | Extracto: {$excerpt}\n";
        }
        $batchPrompt .= "\nResponde ÚNICAMENTE con: [{\"id\":1,\"meta_title\":\"...\",\"meta_description\":\"...\"},...]";

        $ai = self::ai($batchPrompt, 'Eres un experto SEO B2B. Respondes SOLO con JSON válido, sin markdown.', 'autofix', 0.4, 2000);
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
            $prompt = "Expande este artículo de blog a 500-800 palabras manteniendo el tono profesional B2B. Conserva TODOS los datos, cifras y nombres. Agrega 2-3 párrafos adicionales con valor añadido (estadísticas del sector, casos de uso, beneficios). NO cambies el título ni la estructura existente. Devuelve SOLO el HTML expandido, sin explicaciones.\n\nTítulo: {$p['title']}\nContenido actual ({$wc} palabras): {$p['content']}";
            $r = self::ai($prompt, 'Eres un editor de contenido B2B experto. Respondes SOLO con el contenido solicitado, sin markdown, sin explicaciones.', 'deepfix', 0.5, 2500);
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
        $seoPages = (int)$db->query('SELECT COUNT(*) FROM seo_pages WHERE site_id = @site_id')->fetchColumn();
        $posts = (int)$db->query("SELECT COUNT(*) FROM blog_posts WHERE site_id = @site_id AND status = 'published'")->fetchColumn();
        $postsNoMeta = (int)$db->query("SELECT COUNT(*) FROM blog_posts WHERE site_id = @site_id AND status = 'published' AND (meta_title IS NULL OR meta_title = '' OR meta_description IS NULL OR meta_description = '')")->fetchColumn();

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
                    'seo_pages' => $seoPages,
                    'posts_total' => $posts,
                    'posts_without_meta' => $postsNoMeta,
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
