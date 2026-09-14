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

$legacy = ['hero', 'features', 'tia', 'aip', 'howitworks', 'pricing', 'cta', 'footer', 'codeembed',
    'hero-evolved', 'differentiator', 'tiacommand', 'wontia-business', 'domain-arch', 'food-security',
    'platform-arch', 'trust', 'future-vision', 'ais-hero', 'ais-concept'];

$bricks = WidgetRegistry::all();
$ok = 0;
$fail = 0;
$skip = 0;

foreach ($bricks as $id => $b) {
    if (in_array($id, $legacy, true)) {
        $skip++;
        continue;
    }
    $cfg = markerConfig($b['configSchema'] ?? []);
    if (!$cfg) {
        $skip++;
        continue;
    }
    try {
        $html = WidgetRegistry::render($id, $cfg);
    } catch (\Throwable $e) {
        echo "FAIL $id render error: " . $e->getMessage() . "\n";
        $fail++;
        continue;
    }
    $text = preg_replace('/<(script|style)[^>]*>.*?<\/\1>/is', '', $html);
    $text = strip_tags((string)$text);
    $text = preg_replace('/⟦[^⟧]*⟧/u', '', (string)$text);
    $text = html_entity_decode((string)$text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/[^\p{L}]+/u', ' ', (string)$text);
    $words = preg_split('/\s+/u', trim((string)$text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $words = array_values(array_unique(array_filter($words, function ($w) {
        return mb_strlen($w) >= 3;
    })));
    if ($words) {
        echo "FAIL $id hardcoded text: " . implode(', ', array_slice($words, 0, 12)) . "\n";
        $fail++;
    } else {
        echo "OK   $id\n";
        $ok++;
    }
}

echo "\nResult: $ok OK / $fail FAIL / $skip skipped (legacy or no text fields)\n";
exit($fail > 0 ? 1 : 0);
