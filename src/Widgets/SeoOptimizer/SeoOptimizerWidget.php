<?php
namespace App\Widgets;

use App\Core\Database;

class SeoOptimizerWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'seo-optimizer', 'name' => 'SEO Optimizer', 'icon' => 'search', 'category' => 'seo', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'target_post_id', 'label' => 'Post objetivo (0 = último analizado)', 'type' => 'number', 'default' => 0],
            ['key' => 'show_score', 'label' => 'Mostrar scorecard', 'type' => 'bool', 'default' => true],
            ['key' => 'show_meta', 'label' => 'Mostrar metadatos', 'type' => 'bool', 'default' => true],
            ['key' => 'show_keywords', 'label' => 'Mostrar keywords', 'type' => 'bool', 'default' => true],
            ['key' => 'show_suggestions', 'label' => 'Mostrar sugerencias', 'type' => 'bool', 'default' => true],
            ['key' => 'show_recommendations', 'label' => 'Mostrar recomendaciones', 'type' => 'bool', 'default' => true],
        ];
    }

    public static function defaultConfig(): array
    {
        return [
            'target_post_id' => 0,
            'show_score' => true,
            'show_meta' => true,
            'show_keywords' => true,
            'show_suggestions' => true,
            'show_recommendations' => true,
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:var(--w-surface);border:1px solid var(--w-border);border-radius:8px;padding:20px;text-align:center;color:var(--w-muted);font-size:12px">' . htmlspecialchars(static::meta()['name']) . '</div>';
    }

    public function render(array $config = []): string
    {
        $config = $this->mergeConfig($config);
        $postId = (int)($config['target_post_id'] ?? 0);
        $showScore = (bool)($config['show_score'] ?? true);
        $showMeta = (bool)($config['show_meta'] ?? true);
        $showKeywords = (bool)($config['show_keywords'] ?? true);
        $showSuggestions = (bool)($config['show_suggestions'] ?? true);
        $showRecommendations = (bool)($config['show_recommendations'] ?? true);

        $db = Database::instance();

        try {
            $db->query('SELECT 1 FROM seo_analyses LIMIT 1');
        } catch (\Throwable $e) {
            return '<div style="padding:40px;text-align:center;color:var(--w-muted)">'
                . '<h3>SEO Optimizer</h3><p>Activa el brick SEO Optimizer para crear las tablas de análisis.</p></div>';
        }

        // Localizar post: el indicado, o el último con análisis completado
        if ($postId <= 0) {
            $stmt = $db->query("SELECT post_id FROM seo_analyses WHERE status = 'completed' ORDER BY created_at DESC LIMIT 1");
            $row = $stmt->fetch();
            $postId = (int)($row['post_id'] ?? 0);
        }

        $post = null;
        if ($postId > 0) {
            $stmt = $db->prepare("SELECT id, site_id, title, meta_title, meta_desc FROM blog_posts WHERE id = ? AND site_id = @site_id LIMIT 1");
            $stmt->execute([$postId]);
            $post = $stmt->fetch() ?: null;
        }

        if (!$post) {
            return '<div style="padding:40px;text-align:center;color:var(--w-muted)">'
                . '<h3>SEO Optimizer</h3><p>Aún no hay análisis SEO. Analiza un artículo para ver su scorecard.</p></div>';
        }

        $analysis = null;
        $stmt = $db->prepare("SELECT * FROM seo_analyses WHERE post_id = ? AND status = 'completed' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$postId]);
        $analysis = $stmt->fetch() ?: null;

        ob_start();
        echo '<div class="seo-optimizer-widget" style="display:flex;flex-direction:column;gap:14px">';

        if ($showScore) {
            $score = (int)($analysis['score'] ?? 0);
            $color = $score >= 80 ? '#34d399' : ($score >= 50 ? '#fbbf24' : '#f87171');
            echo '<div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">';
            echo '<div style="width:96px;height:96px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.9rem;font-weight:800;border:4px solid ' . $color . ';color:' . $color . '">' . $score . '</div>';
            echo '<div style="flex:1;min-width:200px;font-size:.78rem;line-height:1.9">';
            $breakdown = [
                'Título' => (int)($analysis['title_score'] ?? 0),
                'Meta' => (int)($analysis['meta_score'] ?? 0),
                'Contenido' => (int)($analysis['content_score'] ?? 0),
                'Legibilidad' => (int)($analysis['readability_score'] ?? 0),
                'Estructura' => (int)($analysis['structure_score'] ?? 0),
            ];
            foreach ($breakdown as $label => $val) {
                echo '<div><strong>' . $this->esc($label) . ':</strong> ' . $val . '/100</div>';
            }
            echo '</div></div>';
        }

        if ($showMeta) {
            $seoTitle = ($analysis && !empty($analysis['seo_title'])) ? $analysis['seo_title'] : ($post['meta_title'] ?: $post['title']);
            $seoDesc = ($analysis && !empty($analysis['seo_description'])) ? $analysis['seo_description'] : ($post['meta_desc'] ?? '');
            echo '<div style="font-size:.82rem"><strong>SEO Title:</strong> ' . $this->esc((string)$seoTitle) . '</div>';
            echo '<div style="font-size:.82rem"><strong>Meta Description:</strong> ' . $this->esc((string)$seoDesc) . '</div>';
        }

        if ($showKeywords && $analysis) {
            $primary = (string)($analysis['primary_keyword'] ?? '');
            $secondary = $this->safeJson($analysis['secondary_keywords'] ?? '[]');
            echo '<div style="font-size:.82rem"><strong>Keyword principal:</strong> ' . $this->esc($primary);
            if ($secondary) echo ' · <span style="color:var(--w-muted)">' . $this->esc(implode(', ', array_slice($secondary, 0, 6))) . '</span>';
            echo '</div>';
        }

        if ($showSuggestions && $analysis) {
            $issues = array_merge(
                $this->safeJson($analysis['readability_issues'] ?? '[]'),
                $this->safeJson($analysis['structure_issues'] ?? '[]')
            );
            if ($issues) {
                echo '<div style="font-size:.8rem"><strong>Sugerencias:</strong><ul style="margin:6px 0 0 18px">';
                foreach (array_slice($issues, 0, 4) as $issue) {
                    echo '<li>' . $this->esc((string)($issue['suggestion'] ?? $issue['fix'] ?? $issue['issue'] ?? '')) . '</li>';
                }
                echo '</ul></div>';
            }
        }

        if ($showRecommendations && $analysis) {
            $recs = $this->safeJson($analysis['final_recommendations'] ?? '[]');
            if ($recs) {
                echo '<div style="font-size:.82rem"><strong>Recomendaciones finales:</strong><ul style="margin:6px 0 0 18px">';
                foreach (array_slice($recs, 0, 3) as $rec) {
                    echo '<li>' . $this->esc((string)$rec) . '</li>';
                }
                echo '</ul></div>';
            }
        }

        echo '</div>';
        return ob_get_clean();
    }
}
