<?php
namespace App\Widgets;

class WidgetRegistry
{
    private static array $widgets = [];
    private static bool $discovered = false;

    public static function discover(): void
    {
        if (self::$discovered) return;
        $dir = __DIR__;
        foreach (glob($dir . '/*Widget.php') as $file) {
            $class = basename($file, '.php');
            if ($class === 'Widget') continue;
            $fqcn = "App\\Widgets\\$class";
            if (class_exists($fqcn) && is_subclass_of($fqcn, Widget::class)) {
                $meta = $fqcn::meta();
                self::$widgets[$meta['id']] = $fqcn;
            }
        }
        self::$discovered = true;
    }

    public static function all(): array
    {
        self::discover();
        $result = [];
        foreach (self::$widgets as $id => $class) {
            $result[$id] = $class::meta();
            $result[$id]['configSchema'] = $class::configSchema();
            $result[$id]['defaultConfig'] = $class::defaultConfig();
            $result[$id]['adminPreview'] = $class::adminPreview();
        }
        return $result;
    }

    public static function get(string $type): ?string
    {
        self::discover();
        return self::$widgets[$type] ?? null;
    }

    public static function render(string $type, array $config = []): string
    {
        $class = self::get($type);
        if (!$class) return '<!-- BRICK not found: ' . htmlspecialchars($type) . ' -->';
        $instance = new $class();
        return $instance->render($config);
    }

    public static function types(): array
    {
        self::discover();
        return array_keys(self::$widgets);
    }

    public static function count(): int
    {
        self::discover();
        return count(self::$widgets);
    }
}
