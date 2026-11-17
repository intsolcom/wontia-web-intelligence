<?php
$path = $argv[1] ?? '';
if ($path === '' || !is_file($path)) {
    fwrite(STDERR, "Uso: php sanitize_miche_catalog.php /ruta/catalog.json\n");
    exit(1);
}

$strip = function ($value) use (&$strip): mixed {
    if (is_array($value)) {
        foreach ($value as $key => $item) $value[$key] = $strip($item);
        return $value;
    }
    if (!is_string($value)) return $value;
    $value = preg_replace('~<a\b[^>]*href\s*=\s*("[^"]*miche\.com\.co[^"]*"|\'[^\']*miche\.com\.co[^\']*\')[^>]*>(.*?)</a>~is', '$2', $value) ?? $value;
    $value = preg_replace('~(?:https?://)?(?:www\.)?miche\.com\.co/?~i', '', $value) ?? $value;
    $value = preg_replace('~<(p|span|div)>\s*</\1>~i', '', $value) ?? $value;
    return trim($value);
};

$before = (string)file_get_contents($path);
$refs = substr_count(strtolower($before), 'miche.com.co');
$data = json_decode($before, true);
if (!is_array($data)) {
    fwrite(STDERR, "JSON inválido.\n");
    exit(1);
}
if (isset($data['products'])) $data['products'] = $strip($data['products']);
if (isset($data['source'])) $data['source'] = 'sonika_catalog';
$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    fwrite(STDERR, "No se pudo codificar JSON.\n");
    exit(1);
}
$tmp = $path . '.sanitize.tmp';
if (file_put_contents($tmp, $json) === false) {
    fwrite(STDERR, "No se pudo escribir el catálogo.\n");
    exit(1);
}
rename($tmp, $path);
$after = (string)file_get_contents($path);
echo json_encode([
    'ok' => true,
    'before_refs' => $refs,
    'after_refs' => substr_count(strtolower($after), 'miche.com.co'),
    'products' => count((array)($data['products'] ?? [])),
    'bytes' => strlen($after),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
