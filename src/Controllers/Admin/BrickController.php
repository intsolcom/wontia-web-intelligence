<?php
namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Widgets\WidgetRegistry;

class BrickController
{
    public function index(): void
    {
        $bricks = WidgetRegistry::all();
        $usage = $this->usageCounts();
        foreach ($bricks as $id => &$b) {
            $b['uses_ai'] = $this->widgetUsesAi($id);
            $b['usage_count'] = $usage[$id] ?? 0;
        }
        unset($b);
        Response::json(['ok' => true, 'data' => $bricks, 'total' => WidgetRegistry::count()]);
    }

    public function show(Request $request, string $type = ''): void
    {
        $class = WidgetRegistry::get($type);
        if (!$class) Response::error('BRICK not found', 404);
        Response::json(['ok' => true, 'data' => [
            'meta' => $class::meta(),
            'configSchema' => $class::configSchema(),
            'defaultConfig' => $class::defaultConfig(),
            'adminPreview' => $class::adminPreview(),
        ]]);
    }

    public function usage(): void
    {
        Response::json(['ok' => true, 'data' => $this->usageCounts()]);
    }

    public function preview(Request $request, string $type = ''): void
    {
        $class = WidgetRegistry::get($type);
        if (!$class) Response::error('BRICK not found', 404);
        $config = $class::defaultConfig();
        $html = (new $class())->render(is_array($config) ? $config : []);
        $theme = 'default';
        try {
            $theme = (string)(Database::instance()->query("SELECT theme FROM sites WHERE id = @site_id")->fetchColumn() ?: 'default');
        } catch (\Throwable $e) {
        }
        $theme = preg_replace('/[^a-z0-9_-]/i', '', $theme) ?: 'default';
        $themeFile = ROOT_DIR . '/templates/themes/' . $theme . '/index.php';
        $css = '';
        $js = '';
        if (is_file($themeFile)) {
            $src = (string)file_get_contents($themeFile);
            if (preg_match_all('/<style>(.*?)<\/style>/s', $src, $m)) {
                $css = implode("\n", $m[1]);
            }
            if (preg_match_all('/<script>(.*?)<\/script>/s', $src, $m)) {
                $js = implode("\n;\n", $m[1]);
            }
        }
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="es" data-theme="dark"><head><meta charset="utf-8"/>'
            . '<meta name="viewport" content="width=device-width,initial-scale=1.0"/>'
            . '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet"/>'
            . '<style>' . $css . '</style>'
            . '<style>html,body{overflow-x:hidden}body{padding:0}.brick-preview{padding:4px 0}.brick-preview .wrap{max-width:100%}</style>'
            . '</head><body><div class="brick-preview">' . $html . '</div>'
            . '<script>window.__WWI_PREVIEW__=true;</script>'
            . '<script>' . $js . '</script>'
            . '</body></html>';
    }

    private function usageCounts(): array
    {
        try {
            $db = Database::instance();
            $stmt = $db->query("SELECT s.widget_type, COUNT(*) AS c FROM sections s JOIN pages p ON p.id = s.page_id WHERE p.site_id = @site_id AND s.widget_type IS NOT NULL AND s.widget_type <> '' GROUP BY s.widget_type");
            $out = [];
            foreach ($stmt->fetchAll() as $row) {
                $out[$row['widget_type']] = (int)$row['c'];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function widgetUsesAi(string $type): bool
    {
        $class = WidgetRegistry::get($type);
        if (!$class) return false;
        try {
            $file = (new \ReflectionClass($class))->getFileName();
            if (!$file || !is_file($file)) return false;
            $src = (string)file_get_contents($file);
            return str_contains($src, 'AiRouter') || str_contains($src, 'AiBrickService') || str_contains($src, 'AiRequest');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
