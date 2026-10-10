<?php
namespace App\Bricks\SeoOptimizer;

use App\Core\BrickSystem;
use App\Core\Config;
use App\Core\Database;
use App\Core\AiBrick\AiRouter;

/**
 * SEO OPTIMIZER BRICK — Optimizador SEO por artículo con IA DeepSeek
 * Extraído de admin/seo_module.php y productizado como módulo standalone multi-tenant.
 *
 * Análisis SEO completo de posts de blog usando IA:
 * - Scorecard 0-100 desglosado (título, meta, contenido, legibilidad, estructura)
 * - Metadatos optimizados: SEO Title, Meta Description, Keywords
 * - Keywords & LSI con volumen, dificultad, intención de búsqueda
 * - Enlaces internos sugeridos (cruce con otros posts)
 * - Sugerencias de mejora concretas y aplicables con un clic
 * - Dashboard con stats generales y últimos análisis
 *
 * IA vía capa BRICK de WWI (AiRouter → proveedores/políticas/costos)
 * con fallback automático a DeepSeek directo si no hay BRICK provisionado.
 */
class SeoOptimizerBrick
{
    public static function meta(): array
    {
        $jsonPath = __DIR__ . '/brick.json';
        if (file_exists($jsonPath)) {
            $meta = json_decode(file_get_contents($jsonPath), true);
            if ($meta) return $meta;
        }
        return [
            'name' => 'SEO Optimizer',
            'slug' => 'seo-optimizer',
            'version' => '1.0.0',
            'description' => 'Per-article SEO optimizer with DeepSeek AI',
            'author' => 'Wontia',
            'category' => 'seo',
        ];
    }

    public static function activate(): array
    {
        $meta = self::meta();
        $existing = BrickSystem::findBySlug($meta['slug']);
        if ($existing) {
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
            'installed_path' => 'src/Bricks/SeoOptimizer/',
            'config' => $meta['config'] ?? [],
        ]);

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
            ['key' => 'auto_apply', 'type' => 'bool', 'label' => 'Aplicar recomendaciones automáticamente', 'default' => true],
            ['key' => 'target_word_count', 'type' => 'number', 'label' => 'Recuento de palabras objetivo', 'default' => 1500],
            ['key' => 'min_headings', 'type' => 'number', 'label' => 'Mínimo de encabezados H2', 'default' => 4],
        ];
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
                'module' => 'seo-optimizer',
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
    // ANALYZE — Análisis SEO de un artículo
    // ─────────────────────────────────────────────

    public static function analyze(int $postId, string $mode = 'full'): array
    {
        self::installTables();
        $db = Database::instance();

        // Obtener post
        $post = $db->prepare("SELECT * FROM blog_posts WHERE id = ?");
        $post->execute([$postId]);
        $post = $post->fetch();
        if (!$post) return ['ok' => false, 'error' => 'Post not found'];

        // Marcar como processing y crear registro
        $now = date('Y-m-d H:i:s');
        $db->prepare("UPDATE seo_analyses SET status='failed' WHERE post_id=? AND status='processing'")->execute([$postId]);
        $stmt = $db->prepare("INSERT INTO seo_analyses (post_id, analysis_type, status) VALUES (?,?, 'processing')");
        $stmt->execute([$postId, $mode]);
        $analysisId = (int) $db->lastInsertId();

        $startTime = microtime(true);

        // Datos del post
        $plainText = strip_tags($post['content'] ?? '');
        $wordCount = str_word_count($plainText);
        $title = $post['title'] ?? '';
        $excerpt = $post['excerpt'] ?? '';
        $currentMetaTitle = $post['meta_title'] ?? '';
        $currentMetaDesc = $post['meta_desc'] ?? '';

        // Posts relacionados para sugerencias de enlaces internos
        $allPosts = $db->query("SELECT id, title, slug, excerpt FROM blog_posts WHERE id != {$postId} AND status = 'published' ORDER BY published_at DESC LIMIT 20")->fetchAll();

        // Análisis de estructura
        $headingCount = substr_count(strtolower($post['content'] ?? ''), '<h2') + substr_count(strtolower($post['content'] ?? ''), '<h3');
        $imageCount = substr_count(strtolower($post['content'] ?? ''), '<img');
        $linkCount = substr_count(strtolower($post['content'] ?? ''), '<a ');

        // Categorías
        $cat = null;
        if ($post['category_id']) {
            $cs = $db->prepare("SELECT * FROM blog_categories WHERE id = ?");
            $cs->execute([$post['category_id']]);
            $cat = $cs->fetch();
        }

        $catName = $cat['name'] ?? 'General';

        // Preparar JSON de posts relacionados
        $allPostsJson = json_encode(array_map(function($p) {
            return ['id' => $p['id'], 'title' => $p['title'], 'slug' => $p['slug'], 'excerpt' => substr(strip_tags((string)($p['excerpt'] ?? '')), 0, 100)];
        }, $allPosts), JSON_UNESCAPED_UNICODE);

        // Limpiar contenido (quitar imágenes, mantener etiquetas básicas)
        $cleanContent = preg_replace('/<img[^>]*>/i', '[IMAGE]', $post['content'] ?? '');
        $cleanContent = strip_tags($cleanContent, '<h1><h2><h3><h4><p><ul><ol><li><strong><em><a><blockquote><code><pre><table><tr><td><th>');

        // Construir prompt
        $prompt = <<<PROMPTEOF
Eres el mejor analista SEO del mundo. Analiza este artículo de blog y genera un informe SEO completo en formato JSON válido (sin markdown, sin comentarios, SOLO el JSON).

ARTÍCULO:
Título: {$title}
URL actual: /blog/{$post['slug']}
Extracto: {$excerpt}
Categoría: {$catName}
Meta title actual: {$currentMetaTitle}
Meta description actual: {$currentMetaDesc}
Palabras: {$wordCount}
Encabezados H2/H3: {$headingCount}
Imágenes: {$imageCount}
Enlaces internos: {$linkCount}
Contenido HTML: {$cleanContent}

ARTÍCULOS RELACIONADOS (para sugerir enlaces internos):
{$allPostsJson}

GENERA ESTE JSON EXACTO (sin variaciones, sin explicaciones, solo el JSON):

{
  "score": 85,
  "title_score": 90,
  "meta_score": 70,
  "content_score": 88,
  "readability_score": 82,
  "structure_score": 75,
  "seo_title": "Título SEO optimizado (máx 60 caracteres, incluye keyword principal al inicio, usa power words)",
  "seo_description": "Meta description persuasiva (máx 155 caracteres, incluye keyword, CTA implícito)",
  "seo_keywords": "keyword1, keyword2, keyword3, keyword4, keyword5",
  "primary_keyword": "keyword principal detectada",
  "secondary_keywords": ["keyword2", "keyword3", "keyword4"],
  "lsi_keywords": ["keyword semantica 1", "keyword semantica 2", "keyword semantica 3", "keyword semantica 4", "keyword semantica 5"],
  "content_suggestions": [
    {"priority": "high", "issue": "Descripción del problema", "fix": "Sugerencia específica de cómo corregirlo", "impact": "Explicación de por qué esto mejora el SEO"},
    {"priority": "medium", "issue": "...", "fix": "...", "impact": "..."}
  ],
  "readability_issues": [
    {"type": "sentence_length", "location": "Párrafo 2", "issue": "Oraciones muy largas (>25 palabras)", "suggestion": "Dividir en oraciones más cortas"},
    {"type": "passive_voice", "location": "Introducción", "issue": "Uso de voz pasiva", "suggestion": "Convertir a voz activa"}
  ],
  "structure_issues": [
    {"type": "heading_hierarchy", "issue": "Descripción", "fix": "Solución"},
    {"type": "missing_elements", "issue": "Falta tabla de contenidos / bullet points / etc", "fix": "Agregar [elemento] en [ubicación]"}
  ],
  "link_suggestions": [
    {"target_post_id": 1, "anchor_text": "texto del enlace sugerido", "relevance": 0.92, "location": "Párrafo 3"},
    {"target_post_id": 2, "anchor_text": "otro enlace", "relevance": 0.78, "location": "Conclusión"}
  ],
  "schema_suggestions": {
    "type": "Article",
    "recommendations": ["Agregar author.name", "Agregar dateModified si se actualiza", "Usar FAQ schema si hay preguntas"],
    "json_ld": "{\"@type\":\"Article\",\"headline\":\"...\"}"
  },
  "competitor_insights": {
    "target_keyword": "keyword principal",
    "avg_word_count_top10": 1800,
    "avg_headings_top10": 8,
    "common_missing_topics": ["tema 1 que los competidores cubren", "tema 2"],
    "recommendation": "Recomendación estratégica para superar a la competencia"
  },
  "keyword_density": {
    "primary_keyword": {"count": 5, "density": 1.2, "ideal": "1.0-2.0%"},
    "secondary": [{"keyword": "kw2", "count": 3, "density": 0.7}]
  },
  "heading_structure": {
    "h1": 1,
    "h2": 4,
    "h3": 2,
    "issues": ["H2 vacío en sección X", "Falta H2 antes de H3"]
  },
  "final_recommendations": [
    "Recomendación 1 prioritaria",
    "Recomendación 2",
    "Recomendación 3"
  ]
}

REGLAS ESTRICTAS:
0. MANTEN EL IDIOMA ORIGINAL. Si el artículo está en inglés, genera el análisis en inglés. Si está en español, en español. NUNCA traduzcas.
1. Todos los scores son de 0-100
2. Las sugerencias deben ser ESPECÍFICAS (no genéricas) — menciona párrafos, secciones, elementos concretos
3. Las keywords deben ser REALISTAS y relevantes al contenido del artículo
4. Los link_suggestions deben usar SOLO los IDs de artículos_relacionados proporcionados (si hay)
5. El JSON debe ser 100% válido — sin comas extra, sin comentarios, sin texto fuera del JSON
6. No inventes IDs si no hay artículos relacionados — usa array vacío []
7. competitor_insights debe basarse en datos realistas del sector BPO/nearshore
8. El campo final_recommendations debe tener exactamente 3 recomendaciones accionables
PROMPTEOF;

        try {
            $ch = curl_init('https://api.deepseek.com/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . (defined('DEEPSEEK_API_KEY') ? DEEPSEEK_API_KEY : ''),
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => 'deepseek-chat',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Eres un analista SEO experto. Respondes ÚNICAMENTE con JSON válido, sin markdown, sin explicaciones, sin comillas extras. El JSON debe ser parseable directamente con json_decode().'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 3000,
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
            ]);

            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $durationMs = round((microtime(true) - $startTime) * 1000);
            curl_close($ch);

            if ($httpCode !== 200) {
                $err = json_decode($resp, true);
                Database::instance()->prepare("UPDATE seo_analyses SET status='failed', raw_ai_response=? WHERE id=?")->execute([$content, $analysisId]);
                return ['ok' => false, 'error' => 'AI API error: ' . ($err['error']['message'] ?? 'HTTP ' . $httpCode)];
            }

            $data = json_decode($resp, true);
            $content = trim($data['choices'][0]['message']['content'] ?? '');
            $tokens = $data['usage']['total_tokens'] ?? 0;

            // Parsear JSON de la respuesta (manejar bloques de markdown)
            $json = self::parseJsonResponse($content);

            if (!$json) {
                Database::instance()->prepare("UPDATE seo_analyses SET status='failed', raw_ai_response=? WHERE id=?")->execute([$content, $analysisId]);
                return ['ok' => false, 'error' => 'Failed to parse AI response as JSON', 'raw' => substr($content, 0, 500)];
            }

            // Guardar análisis
            $stmt = $db->prepare("UPDATE seo_analyses SET 
                status='completed', score=?, title_score=?, meta_score=?, content_score=?, readability_score=?, structure_score=?,
                seo_title=?, seo_description=?, seo_keywords=?, primary_keyword=?, secondary_keywords=?, lsi_keywords=?,
                content_suggestions=?, readability_issues=?, structure_issues=?, link_suggestions=?, schema_suggestions=?,
                competitor_insights=?, word_count=?, keyword_density=?, heading_structure=?,
                canonical_url=?, og_title=?, og_description=?, twitter_title=?, twitter_description=?,
                raw_ai_response=?, ai_model=?, ai_tokens_used=?, ai_duration_ms=?,
                final_recommendations=?
                WHERE id=?");

            $headingStruct = [
                'h1' => 1,
                'h2' => max(1, round($headingCount / 2)),
                'h3' => max(1, round($headingCount / 3)),
                'issues' => [],
            ];

            // Extraer issues del JSON si vienen
            $extractedReadability = $json['readability_issues'] ?? [];
            $extractedStructure = $json['structure_issues'] ?? [];
            $extractedLinks = $json['link_suggestions'] ?? [];
            $extractedSchema = $json['schema_suggestions'] ?? [];
            $extractedCompetitor = $json['competitor_insights'] ?? [];
            $extractedKd = $json['keyword_density'] ?? [];

            $stmt->execute([
                $json['score'] ?? 0,
                $json['title_score'] ?? 0,
                $json['meta_score'] ?? 0,
                $json['content_score'] ?? 0,
                $json['readability_score'] ?? 0,
                $json['structure_score'] ?? 0,
                $json['seo_title'] ?? '',
                $json['seo_description'] ?? '',
                $json['seo_keywords'] ?? '',
                $json['primary_keyword'] ?? '',
                json_encode($json['secondary_keywords'] ?? []),
                json_encode($json['lsi_keywords'] ?? []),
                json_encode($json['content_suggestions'] ?? []),
                json_encode($extractedReadability),
                json_encode($extractedStructure),
                json_encode($extractedLinks),
                json_encode($extractedSchema),
                json_encode($extractedCompetitor),
                $wordCount,
                json_encode($extractedKd),
                json_encode($headingStruct),
                self::siteUrl() . '/blog/' . ($post['slug'] ?? ''),
                $json['seo_title'] ?? '',
                $json['seo_description'] ?? '',
                $json['seo_title'] ?? '',
                $json['seo_description'] ?? '',
                $content,
                'deepseek-chat',
                $tokens,
                $durationMs,
                $json['final_recommendations'] ?? json_encode(['', '', '']),
                $analysisId,
            ]);

            // Guardar keyword analysis
            if (!empty($json['primary_keyword'])) {
                self::saveKeyword($json['primary_keyword'], 0, 0, 'informational');
            }
            foreach (($json['secondary_keywords'] ?? []) as $kw) {
                self::saveKeyword($kw, 0, 0, 'informational');
            }

            // Aplicar auto-fix si está habilitado
            if (self::setting('auto_apply', '1') === '1') {
                self::applyRecommendations($postId, $json);
            }

            // Guardar sugerencias de enlaces internos
            if (!empty($json['link_suggestions'])) {
                $linkStmt = $db->prepare("INSERT IGNORE INTO seo_internal_links (source_post_id, target_post_id, anchor_text, relevance_score, link_type) VALUES (?,?,?,?,?)");
                foreach ($json['link_suggestions'] as $link) {
                    $tid = $link['target_post_id'] ?? 0;
                    if ($tid > 0 && $tid != $postId) {
                        $linkStmt->execute([$postId, $tid, $link['anchor_text'] ?? '', $link['relevance'] ?? 0.5, 'suggested']);
                    }
                }
            }

            return [
                'ok' => true,
                'analysis_id' => $analysisId,
                'score' => $json['score'] ?? 0,
                'seo_title' => $json['seo_title'] ?? '',
                'seo_description' => $json['seo_description'] ?? '',
                'seo_keywords' => $json['seo_keywords'] ?? '',
                'primary_keyword' => $json['primary_keyword'] ?? '',
                'suggestions' => $json['content_suggestions'] ?? [],
                'issues' => array_merge($json['readability_issues'] ?? [], $json['structure_issues'] ?? []),
                'link_suggestions' => $json['link_suggestions'] ?? [],
                'competitor_insights' => $json['competitor_insights'] ?? [],
                'final_recommendations' => $json['final_recommendations'] ?? [],
                'score_breakdown' => [
                    'title' => $json['title_score'] ?? 0,
                    'meta' => $json['meta_score'] ?? 0,
                    'content' => $json['content_score'] ?? 0,
                    'readability' => $json['readability_score'] ?? 0,
                    'structure' => $json['structure_score'] ?? 0,
                ],
                'tokens' => $tokens,
                'duration_ms' => $durationMs,
                'model' => 'deepseek-chat',
            ];

        } catch (\Throwable $e) {
            Database::instance()->prepare("UPDATE seo_analyses SET status='failed', raw_ai_response=? WHERE id=?")->execute([$e->getMessage(), $analysisId]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Parse JSON from AI response, handling markdown code blocks and edge cases
     */
    private static function parseJsonResponse(string $raw): ?array
    {
        $raw = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
        $raw = preg_replace('/\s*```$/', '', $raw);

        $json = json_decode($raw, true);
        if ($json && is_array($json) && isset($json['score'])) return $json;

        if (preg_match('/\{.*"score"\s*:.*\}/s', $raw, $m)) {
            $json = json_decode($m[0], true);
            if ($json && isset($json['score'])) return $json;
        }

        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $raw, $m)) {
            $json = json_decode($m[0], true);
            if ($json && isset($json['score'])) return $json;
        }

        return null;
    }

    private static function saveKeyword(string $kw, int $volume, int $difficulty, string $intent): void
    {
        $stmt = $db = Database::instance()->prepare("INSERT INTO seo_keywords (keyword, search_volume, difficulty, intent, last_analyzed_at) VALUES (?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE search_volume=GREATEST(search_volume,?), difficulty=GREATEST(difficulty,?), last_analyzed_at=NOW()");
        $stmt->execute([strtolower(trim($kw)), $volume, $difficulty, $intent, $volume, $difficulty]);
    }

    /**
     * Aplica recomendaciones al post (título, descripción, keywords)
     */
    private static function applyRecommendations(int $postId, array $analysis): void
    {
        $updates = [];
        $params = [];

        if (!empty($analysis['seo_title']) && (($analysis['title_score'] ?? 100) < 90)) {
            $updates[] = 'meta_title = ?';
            $params[] = substr($analysis['seo_title'], 0, 255);
        }
        if (!empty($analysis['seo_description']) && (($analysis['meta_score'] ?? 100) < 90)) {
            $updates[] = 'meta_desc = ?';
            $params[] = substr($analysis['seo_description'], 0, 500);
        }
        if (!empty($analysis['seo_keywords'])) {
            $updates[] = 'meta_keywords = ?';
            $params[] = $analysis['seo_keywords'];
        }

        if (!empty($updates)) {
            $sql = 'UPDATE blog_posts SET ' . implode(', ', $updates) . ' WHERE id = ?';
            $params[] = $postId;
            Database::instance()->prepare($sql)->execute($params);
        }
    }

    // ─────────────────────────────────────────────
    // GET ANALYSIS — Obtener último análisis de un post
    // ─────────────────────────────────────────────

    public static function getAnalysis(int $postId): ?array
    {
        try { Database::instance()->query("SELECT 1 FROM seo_analyses LIMIT 1"); } catch (\Throwable $e) { self::installTables(); }
        $stmt = Database::instance()->prepare("SELECT * FROM seo_analyses WHERE post_id = ? AND status = 'completed' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$postId]);
        $row = $stmt->fetch();
        if (!$row) return null;

        foreach (['secondary_keywords','lsi_keywords','content_suggestions','readability_issues','structure_issues','link_suggestions','schema_suggestions','competitor_insights','keyword_density','heading_structure','final_recommendations'] as $f) {
            $row[$f] = json_decode($row[$f] ?: '[]', true);
        }

        return $row;
    }

    // ─────────────────────────────────────────────
    // DASHBOARD — Stats del módulo SEO
    // ─────────────────────────────────────────────

    public static function getDashboard(): array
    {
        try { Database::instance()->query("SELECT 1 FROM seo_analyses LIMIT 1"); } catch (\Throwable $e) { self::installTables(); }
        $total = Database::instance()->query("SELECT COUNT(*) FROM seo_analyses WHERE status='completed'")->fetchColumn();
        $avg = Database::instance()->query("SELECT ROUND(AVG(score)) FROM seo_analyses WHERE status='completed'")->fetchColumn() ?: 0;
        $latest = Database::instance()->query("SELECT post_id, score, seo_title, created_at FROM seo_analyses WHERE status='completed' ORDER BY created_at DESC LIMIT 5")->fetchAll();
        $needsAnalysis = Database::instance()->query("SELECT COUNT(*) FROM blog_posts WHERE site_id = @site_id AND status='published' AND id NOT IN (SELECT post_id FROM seo_analyses WHERE status='completed')")->fetchColumn();
        $topKeywords = Database::instance()->query("SELECT keyword, search_volume FROM seo_keywords ORDER BY search_volume DESC LIMIT 10")->fetchAll();
        $linkCount = Database::instance()->query("SELECT COUNT(*) FROM seo_internal_links WHERE is_active=1")->fetchColumn();

        return [
            'ok' => true,
            'total_analyses' => (int)$total,
            'avg_score' => (int)$avg,
            'posts_needing_analysis' => (int)$needsAnalysis,
            'internal_links' => (int)$linkCount,
            'latest_analyses' => $latest,
            'top_keywords' => $topKeywords,
        ];
    }

    // ─────────────────────────────────────────────
    // INSTALL TABLES
    // ─────────────────────────────────────────────

    public static function installTables(): array
    {
        $db = Database::instance();
        $created = [];

        $stmts = [
            "CREATE TABLE IF NOT EXISTS seo_analyses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                post_id INT,
                analysis_type ENUM('full','quick','refresh') DEFAULT 'full',
                score INT DEFAULT 0,
                title_score INT DEFAULT 0,
                meta_score INT DEFAULT 0,
                content_score INT DEFAULT 0,
                readability_score INT DEFAULT 0,
                structure_score INT DEFAULT 0,
                seo_title VARCHAR(255),
                seo_description TEXT,
                seo_keywords VARCHAR(500),
                primary_keyword VARCHAR(100),
                secondary_keywords JSON,
                lsi_keywords JSON,
                content_suggestions JSON,
                readability_issues JSON,
                structure_issues JSON,
                link_suggestions JSON,
                schema_suggestions JSON,
                competitor_insights JSON,
                word_count INT,
                keyword_density JSON,
                heading_structure JSON,
                canonical_url VARCHAR(500),
                og_title VARCHAR(255),
                og_description TEXT,
                twitter_title VARCHAR(255),
                twitter_description TEXT,
                raw_ai_response TEXT,
                ai_model VARCHAR(50),
                ai_tokens_used INT DEFAULT 0,
                ai_duration_ms INT DEFAULT 0,
                final_recommendations JSON,
                status ENUM('pending','processing','completed','failed') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
                INDEX idx_post (post_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS seo_keywords (
                id INT AUTO_INCREMENT PRIMARY KEY,
                keyword VARCHAR(200) NOT NULL,
                search_volume INT DEFAULT 0,
                difficulty INT DEFAULT 0,
                intent VARCHAR(50),
                last_analyzed_at DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_keyword (keyword)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS seo_internal_links (
                id INT AUTO_INCREMENT PRIMARY KEY,
                source_post_id INT,
                target_post_id INT,
                anchor_text VARCHAR(255),
                relevance_score DECIMAL(3,2),
                link_type ENUM('suggested','manual','auto') DEFAULT 'suggested',
                is_active TINYINT DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (source_post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
                FOREIGN KEY (target_post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
                UNIQUE KEY uk_link (source_post_id, target_post_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];

        foreach ($stmts as $sql) {
            $db->exec($sql);
            $created[] = substr($sql, 13, 20);
        }

        return ['ok' => true, 'message' => 'SEO Optimizer tables ready', 'tables' => $created];
    }
}