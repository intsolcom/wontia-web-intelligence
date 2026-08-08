<?php
namespace App\Services;

use App\Core\Config;

class SeoService
{
    public static function metaTags(array $page): string
    {
        $url = Config::get('APP_URL', 'https://wontia.intsolcom.com');
        $title = htmlspecialchars($page['meta_title'] ?? $page['title'] ?? 'Wontia');
        $desc = htmlspecialchars($page['meta_description'] ?? Config::get('site_description', 'Applied Intelligence Platform'));
        $ogImage = $page['og_image'] ?? "$url/assets/wontia-og.png";
        $canonical = $page['canonical_url'] ?? "$url/" . ($page['slug'] ?? '');

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
        if (!empty($page['no_index'])) {
            $html .= "    <meta name=\"robots\" content=\"noindex, nofollow\">\n";
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
            $list[] = ['@type' => 'ListItem', 'position' => $pos, 'name' => $name, 'item' => $url];
            $pos++;
        }
        return self::jsonLd('BreadcrumbList', ['itemListElement' => $list]);
    }
}
