<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Widgets\WidgetRegistry;

\App\Core\Config::load();

function markerConfig(array $schema): array
{
    $cfg = [];
    foreach ($schema as $f) {
        $type = $f['type'] ?? 'text';
        $key = $f['key'] ?? '';
        if ($key === '') continue;
        if ($type === 'repeater') {
            $item = [];
            foreach (($f['fields'] ?? []) as $sub) {
                if (!empty($sub['key'])) $item[$sub['key']] = '⟦' . $key . '.' . $sub['key'] . '⟧';
            }
            $cfg[$key] = [$item];
        } elseif (in_array($type, ['text', 'textarea', 'richtext', 'link', 'image'], true)) {
            $cfg[$key] = '⟦' . $key . '⟧';
        }
    }
    return $cfg;
}

$bricks = WidgetRegistry::all();
$ok = 0;
$fail = 0;
$skip = 0;

foreach ($bricks as $id => $b) {
    try {
        $html = WidgetRegistry::render($id, markerConfig($b['configSchema'] ?? []));
    } catch (\Throwable $e) {
        echo "FAIL $id - render error: " . $e->getMessage() . "\n";
        $fail++;
        continue;
    }
    if (strlen($html) < 200) {
        echo "SKIP $id - dummy (no render)\n";
        $skip++;
        continue;
    }
    $contract = $b['editContract'] ?? [];
    $missing = [];
    foreach (($contract['editable'] ?? []) as $key) {
        if (strpos($html, 'data-editable="' . $key . '"') === false) $missing[] = $key;
    }
    if ($missing) {
        echo "FAIL $id - inline fields without data-editable: " . implode(', ', $missing) . "\n";
        $fail++;
    } else {
        echo "OK   $id - " . count($contract['editable'] ?? []) . " inline, " . count($contract['repeaters'] ?? []) . " repeaters, " . count($contract['sources'] ?? []) . " sources\n";
        $ok++;
    }
}

echo "\nResult: $ok OK / $fail FAIL / $skip dummy\n";
exit($fail > 0 ? 1 : 0);
