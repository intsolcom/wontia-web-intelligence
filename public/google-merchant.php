<?php
/**
 * Feed de productos para Google Merchant Center.
 * URL: https://sonika.com.co/google-merchant.xml
 * Cache: 1 hora en /app/cache/google-merchant.xml
 */
if (!defined('ROOT_DIR')) define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/vendor/autoload.php';
\App\Core\Config::load();

$cacheFile = ROOT_DIR . '/cache/google-merchant.xml';
$ttl = 3600;
$serve = static function (string $xml): void {
    header('Content-Type: application/xml; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: public, max-age=3600');
    echo $xml;
};
if (is_file($cacheFile) && (time() - (int)filemtime($cacheFile)) < $ttl) {
    $serve((string)file_get_contents($cacheFile));
    exit;
}

$base = 'https://sonika.com.co';
try {
    $base = rtrim((string)\App\Bricks\SeoGlobalLaunch\SeoGlobalLaunchBrick::siteUrl(), '/');
} catch (\Throwable $e) {
}

$db = \App\Core\Database::instance();
$rows = $db->query("SELECT p.id, p.name, p.slug, p.description, p.seo_description, p.price_cents, p.compare_price_cents,
        p.stock, p.track_stock, p.images, p.vendor, p.sku, c.path AS category_path
    FROM store_products p
    LEFT JOIN store_categories c ON c.id = p.category_id AND c.site_id = p.site_id
    WHERE p.site_id = @site_id AND p.status = 'active' AND p.visibility = 'public'
    ORDER BY p.id ASC")->fetchAll(\PDO::FETCH_ASSOC);

$esc = static fn($v): string => htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
$clean = static function ($v, int $max): string {
    $v = strip_tags((string)$v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', (string)$v) ?? '';
    $v = trim((string)preg_replace('/\s+/u', ' ', $v));
    if (mb_strlen($v) > $max) {
        $v = mb_substr($v, 0, $max);
        $pos = mb_strrpos($v, ' ');
        if ($pos !== false && $pos > (int)($max * 0.6)) $v = mb_substr($v, 0, $pos);
    }
    return $v;
};
// Google Merchant no permite texto promocional en el título.
$cleanTitle = static function (string $v): string {
    $v = preg_replace('/^BLACKDAY\s+/iu', '', $v) ?? $v;
    $v = preg_replace('/\s+(PROMOCI[ÓO]N|PROMOCION|OFERTA)\s*$/iu', '', $v) ?? $v;
    $v = preg_replace('/\s+GRATIS\s*$/iu', '', $v) ?? $v;
    return trim((string)preg_replace('/\s{2,}/u', ' ', $v));
};
$dupSkus = [];
try {
    foreach ($db->query("SELECT sku FROM store_products WHERE site_id=@site_id AND status='active' AND sku<>'' GROUP BY sku HAVING COUNT(*) > 1")->fetchAll(\PDO::FETCH_COLUMN) as $dupSku) {
        $dupSkus[(string)$dupSku] = true;
    }
} catch (\Throwable $e) {
    $dupSkus = [];
}
$categoryMap = [
    'cuerdas' => 'Musical Instruments & Equipment > Guitars & Basses',
    'percusion' => 'Musical Instruments & Equipment > Drums & Percussion',
    'vientos' => 'Musical Instruments & Equipment > Wind & Woodwind Instruments',
    'teclados' => 'Musical Instruments & Equipment > Keyboards & Pianos',
    'audio' => 'Electronics > Audio > Audio Components',
    'microfonos' => 'Electronics > Audio > Microphones',
    'iluminacion' => 'Electronics > Audio > Stage Lighting & Effects',
    'accesorios' => 'Musical Instruments & Equipment > Instrument Accessories',
];

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n<channel>\n";
$xml .= '<title>SONIKA — Instrumentos Musicales</title>' . "\n";
$xml .= '<link>' . $esc($base . '/') . '</link>' . "\n";
$xml .= '<description>Instrumentos musicales, audio, iluminación y accesorios en Colombia.</description>' . "\n";

foreach ($rows as $r) {
    $id = (string)(int)($r['id'] ?? 0);
    $slug = trim((string)($r['slug'] ?? ''));
    if ($id === '0' || $slug === '') continue;
    $name = $cleanTitle($clean($r['name'] ?? '', 150));
    if ($name === '') continue;
    $body = $clean($r['description'] ?? '', 5000);
    if (mb_strlen($body) < 60) $body = $clean($r['seo_description'] ?? '', 5000);
    $vendor = $clean($r['vendor'] ?? '', 100);
    if ($vendor === '') $vendor = 'SONIKA';
    if (mb_strlen($body) < 60) $body = $clean($name . ' de ' . $vendor . '. Compra en SONIKA con envío a toda Colombia.', 5000);

    $imgs = json_decode((string)($r['images'] ?? ''), true) ?: [];
    $imgs = array_values(array_filter(array_map(static function ($img): string {
        if (is_array($img)) return trim((string)($img['src'] ?? ''));
        return trim((string)$img);
    }, $imgs)));
    if (!$imgs) continue;
    $image = $imgs[0];
    if (!preg_match('~^https?://~i', $image)) $image = $base . '/' . ltrim($image, '/');

    $price = max(0, (int)($r['price_cents'] ?? 0));
    if ($price <= 0) continue;
    $compare = max(0, (int)($r['compare_price_cents'] ?? 0));
    $regular = $compare > $price ? $compare : $price;
    $currency = 'COP';
    $available = ((int)($r['track_stock'] ?? 0) === 0 || (int)($r['stock'] ?? 0) > 0) ? 'in stock' : 'out of stock';
    $path = trim((string)($r['category_path'] ?? ''), '/');
    $top = strtolower((string)(explode('/', $path)[0] ?? ''));
    $googleCat = $categoryMap[$top] ?? '';
    $productType = $path !== '' ? str_replace('/', ' > ', $path) : 'Instrumentos musicales';
    $sku = $clean($r['sku'] ?? '', 100);
    $skuUnique = $sku !== '' && !isset($dupSkus[$sku]);

    $xml .= "<item>\n";
    $xml .= '<g:id>' . $esc($id) . "</g:id>\n";
    $xml .= '<g:title>' . $esc($name) . "</g:title>\n";
    $xml .= '<g:description>' . $esc($body) . "</g:description>\n";
    $xml .= '<g:link>' . $esc($base . '/producto/' . rawurlencode($slug)) . "</g:link>\n";
    $xml .= '<g:image_link>' . $esc($image) . "</g:image_link>\n";
    foreach (array_slice($imgs, 1, 4) as $extra) {
        if (!preg_match('~^https?://~i', $extra)) $extra = $base . '/' . ltrim($extra, '/');
        $xml .= '<g:additional_image_link>' . $esc($extra) . "</g:additional_image_link>\n";
    }
    $xml .= '<g:availability>' . $esc($available) . "</g:availability>\n";
    $xml .= '<g:price>' . number_format($regular / 100, 2, '.', '') . ' ' . $currency . "</g:price>\n";
    if ($compare > $price && $price > 0) {
        $xml .= '<g:sale_price>' . number_format($price / 100, 2, '.', '') . ' ' . $currency . "</g:sale_price>\n";
    }
    $xml .= "<g:condition>new</g:condition>\n";
    $xml .= '<g:brand>' . $esc($vendor) . "</g:brand>\n";
    if ($skuUnique) {
        $xml .= "<g:identifier_exists>yes</g:identifier_exists>\n";
        $xml .= '<g:mpn>' . $esc($sku) . "</g:mpn>\n";
    } else {
        $xml .= "<g:identifier_exists>no</g:identifier_exists>\n";
    }
    $xml .= '<g:product_type>' . $esc($productType) . "</g:product_type>\n";
    if ($googleCat !== '') $xml .= '<g:google_product_category>' . $esc($googleCat) . "</g:google_product_category>\n";
    $xml .= '<g:custom_label_0>' . $esc($top !== '' ? $top : 'otros') . "</g:custom_label_0>\n";
    $xml .= '<g:custom_label_1>' . $esc($vendor) . "</g:custom_label_1>\n";
    $xml .= "</item>\n";
}
$xml .= "</channel>\n</rss>\n";

$tmp = $cacheFile . '.tmp';
if (@file_put_contents($tmp, $xml) !== false) @rename($tmp, $cacheFile);
$serve($xml);
