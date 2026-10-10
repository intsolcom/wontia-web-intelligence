<?php
/**
 * SONIKA — Catalog Sync (CLI)
 * Descarga el catálogo público de Miche (Shopify products.json) página por página,
 * de forma respetuosa (rate-limit + backoff), y genera:
 *   - catalog.json      (productos normalizados de salida)
 *   - prices.json       (snapshot handle -> precio)
 *   - price-changes.log (historial de variaciones de precio = base del Price Monitor)
 *
 * Uso:  php sync.php [ruta_salida/catalog.json]
 * Recomendado ejecutarlo desde el HOST del VPS (alcanza el proveedor; el contenedor
 * es rate-limitado por Shopify). Luego copiar el resultado al contenedor.
 */

$out = $argv[1] ?? (__DIR__ . '/catalog.json');
$base = 'https://www.miche.com.co/collections/all/products.json?limit=250&page=';
$ua = 'SonikaCatalogSync/1.0 (+https://sonika.com.co; admin@sonika.com.co)';
$maxPages = 60;
$delayMs = 800;

function sonika_sync_fetch(string $url, string $ua): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => $ua,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $r = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$code, is_string($r) ? $r : ''];
    }
    $ctx = stream_context_create(['http' => ['timeout' => 25, 'header' => "User-Agent: $ua\r\nAccept: application/json\r\n"]]);
    $r = @file_get_contents($url, false, $ctx);
    return [$r === false ? 0 : 200, is_string($r) ? $r : ''];
}

$all = [];
$page = 1;
$pages = 0;
$stopped = '';
while ($page <= $maxPages) {
    [$code, $raw] = sonika_sync_fetch($base . $page, $ua);
    $tries = 0;
    while ($code === 429 && $tries < 5) {
        $tries++;
        sleep(3 * $tries);
        [$code, $raw] = sonika_sync_fetch($base . $page, $ua);
    }
    if ($code !== 200 || $raw === '') {
        $stopped = "page $page -> HTTP $code";
        fwrite(STDERR, "[sync] $stopped, deteniendo\n");
        break;
    }
    $data = json_decode($raw, true);
    $items = is_array($data) ? ($data['products'] ?? []) : [];
    $n = count($items);
    $all = array_merge($all, $items);
    $pages++;
    if ($n < 250) break;
    $page++;
    usleep($delayMs * 1000);
}

if ($pages === 0) {
    fwrite(STDERR, "[sync] No se obtuvo ningún dato ($stopped). Se conserva el catálogo anterior.\n");
    exit(1);
}

$result = [
    'synced_at' => gmdate('c'),
    'source' => 'https://www.miche.com.co/collections/all/products.json',
    'pages' => $pages,
    'count' => count($all),
    'products' => $all,
];
$tmp = $out . '.tmp';
if (file_put_contents($tmp, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
    fwrite(STDERR, "[sync] No se pudo escribir $tmp\n");
    exit(1);
}
rename($tmp, $out);

$dir = dirname($out);
$pricesOut = $dir . '/prices.json';
$prev = is_file($pricesOut) ? (json_decode((string)file_get_contents($pricesOut), true) ?: []) : [];
$now = [];
$changes = [];
foreach ($all as $p) {
    $h = $p['handle'] ?? '';
    if ($h === '') continue;
    $v = $p['variants'][0] ?? [];
    $price = (float)($v['price'] ?? 0);
    $now[$h] = $price;
    if (isset($prev[$h]) && (float)$prev[$h] !== $price && $price > 0) {
        $changes[] = ['handle' => $h, 'title' => $p['title'] ?? '', 'before' => (float)$prev[$h], 'after' => $price, 'at' => gmdate('c')];
    }
}
file_put_contents($pricesOut . '.tmp', json_encode($now));
rename($pricesOut . '.tmp', $pricesOut);
if (!empty($changes)) {
    file_put_contents($dir . '/price-changes.log', json_encode($changes, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
}

echo "SYNC OK: pages=$pages products=" . count($all) . " price_changes=" . count($changes) . " -> $out\n";
