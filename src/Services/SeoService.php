<?php
namespace App\Services;

use App\Core\Config;

class SeoService
{
    public static function metaTags(array $page): string
    {
        $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        $requestUrl = $host !== '' ? $scheme . '://' . $host : rtrim((string)Config::get('APP_URL', 'https://wontia.intsolcom.com'), '/');
        $publicBase = rtrim($requestUrl, '/');
        try {
            $publicBase = rtrim((string)\App\Bricks\SeoGlobalLaunch\SeoGlobalLaunchBrick::siteUrl(), '/');
        } catch (\Throwable $e) {
        }
        $publicHost = strtolower((string)parse_url($publicBase, PHP_URL_HOST));
        $title = htmlspecialchars($page['meta_title'] ?? $page['title'] ?? 'Wontia');
        $desc = htmlspecialchars($page['meta_description'] ?? Config::get('site_description', 'Applied Intelligence Platform'));
        $ogImage = $page['og_image'] ?? ($publicBase . '/assets/wontia-og.png');
        $path = trim((string)($page['slug'] ?? ''), '/');
        $canonical = $page['canonical_url'] ?? $publicBase . '/' . $path;
        $canonical = self::normalizeCanonicalHost((string)$canonical, $publicBase, $publicHost);
        $technical = '';
        $technicalUrl = $canonical;
        try {
            $brickCanonical = \App\Bricks\SeoGlobalLaunch\SeoGlobalLaunchBrick::canonicalForUrl($technicalUrl);
            if ($brickCanonical !== '') $canonical = self::normalizeCanonicalHost($brickCanonical, $publicBase, $publicHost);
            $technical = \App\Bricks\SeoGlobalLaunch\SeoGlobalLaunchBrick::technicalHeadTags($technicalUrl);
        } catch (\Throwable $e) {
            $technical = '';
        }

        $html = "    <title>$title</title>\n";
        $html .= "    <meta name=\"description\" content=\"$desc\">\n";
        if (!empty($page['meta_keywords'])) {
            $html .= "    <meta name=\"keywords\" content=\"" . htmlspecialchars($page['meta_keywords']) . "\">\n";
        }
        $html .= "    <meta property=\"og:title\" content=\"$title\">\n";
        $html .= "    <meta property=\"og:description\" content=\"$desc\">\n";
        $html .= "    <meta property=\"og:image\" content=\"$ogImage\">\n";
        $html .= "    <meta property=\"og:url\" content=\"$canonical\">\n";
        $html .= "    <meta property=\"og:type\" content=\"website\">\n";
        $html .= "    <meta name=\"twitter:card\" content=\"summary_large_image\">\n";
        $html .= "    <meta name=\"twitter:title\" content=\"$title\">\n";
        $html .= "    <meta name=\"twitter:description\" content=\"$desc\">\n";
        $html .= "    <link rel=\"canonical\" href=\"$canonical\">\n";
        if ($technical !== '') $html .= "    " . str_replace("\n", "\n    ", rtrim($technical, "\n")) . "\n";
        if (!empty($page['no_index'])) {
            $html .= "    <meta name=\"robots\" content=\"noindex, nofollow\">\n";
        }
        // SEO Tracking (visitas, links, CTA, formularios) — solo páginas públicas indexables.
        if (empty($page['no_index'])) {
            $v = @filemtime(defined('ROOT_DIR') ? ROOT_DIR . '/public/assets/js/seo-track.js' : '');
            if (!$v) $v = Config::get('APP_VERSION', '1');
            $v = rawurlencode((string)$v);
            $html .= "    <script src=\"/assets/js/seo-track.js?v=$v\" defer></script>\n";
        }

        return $html;
    }

    public static function jsonLd(string $type, array $data): string
    {
        $schema = ['@context' => 'https://schema.org', '@type' => $type] + $data;
        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    }

    public static function breadcrumbs(array $items): string
    {
        $list = [];
        $pos = 1;
        foreach ($items as $name => $url) {
            $entry = ['@type' => 'ListItem', 'position' => $pos, 'name' => $name];
            if ($url !== null && $url !== '') $entry['item'] = $url;
            $list[] = $entry;
            $pos++;
        }
        return self::jsonLd('BreadcrumbList', ['itemListElement' => $list]);
    }

    /** Fuerza www y previews internos al host público canónico. */
    private static function normalizeCanonicalHost(string $url, string $publicBase, string $publicHost): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) return $url;
        $host = strtolower((string)$parts['host']);
        $isWww = $publicHost !== '' && $host === 'www.' . $publicHost;
        $isInternal = str_ends_with($host, '.wontia.com');
        if (!$isWww && !$isInternal) return $url;
        $path = (string)($parts['path'] ?? '/');
        if ($path === '') $path = '/';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        return rtrim($publicBase, '/') . $path . $query;
    }
}
