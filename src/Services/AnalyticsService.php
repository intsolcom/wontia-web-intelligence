<?php
namespace App\Services;

use App\Core\Database;

class AnalyticsService
{
    public static function track(string $url, string $referrer, string $userAgent, bool $isInternal = false): void
    {
        if (!self::analyticsConsent()) return;
        try {
            if (preg_match('~^/cotizacion/[^/?]+(?:/.*)?$~i', $url)) $url = '/cotizacion/[privada]';
            if (preg_match('/bot|crawl|spider|slurp|bing|yandex|ahrefs|semrush|facebookexternalhit|preview|headless/i', $userAgent)) return;
            if (!$isInternal) $isInternal = SeoInternalTrafficService::isInternal();

            $ip = SeoInternalTrafficService::clientIp() ?? '127.0.0.1';
            $ipHash = hash('sha256', $ip);
            $visitorHash = SeoInternalTrafficService::visitorHash($ip, $userAgent);
            $device = self::detectDevice($userAgent);
            $country = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'XX';

            $sid = SeoInternalTrafficService::sessionId();
            if ($sid === null) {
                $sid = 's' . bin2hex(random_bytes(8));
                $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
                setcookie('wwi_seo_sid', $sid, [
                    'expires' => time() + 1800,
                    'path' => '/',
                    'secure' => $secure,
                    'httponly' => false,
                    'samesite' => 'Lax',
                ]);
                $_COOKIE['wwi_seo_sid'] = $sid;
            }

            $utm = SeoInternalTrafficService::utmFromRequest();
            $db = Database::instance();
            $db->prepare("INSERT INTO analytics_views (site_id, page_url, referrer, user_agent, ip_hash, is_internal, country, device, session_id, visitor_hash, is_bot, utm_source, utm_medium, utm_campaign, utm_term, utm_content, fbclid, created_at)
                VALUES (@site_id, :url, :ref, :ua, :ip, :internal, :country, :device, :session_id, :visitor_hash, 0, :utm_source, :utm_medium, :utm_campaign, :utm_term, :utm_content, :fbclid, NOW())")
                ->execute([
                    'url' => $url,
                    'ref' => $referrer,
                    'ua' => mb_substr($userAgent, 0, 500),
                    'ip' => $ipHash,
                    'internal' => $isInternal ? 1 : 0,
                    'country' => $country,
                    'device' => $device,
                    'session_id' => $sid,
                    'visitor_hash' => $visitorHash,
                    'utm_source' => $utm['utm_source'],
                    'utm_medium' => $utm['utm_medium'],
                    'utm_campaign' => $utm['utm_campaign'],
                    'utm_term' => $utm['utm_term'],
                    'utm_content' => $utm['utm_content'],
                    'fbclid' => $utm['fbclid'],
                ]);
        } catch (\Exception $e) {}
    }

    private static function analyticsConsent(): bool
    {
        $raw = (string)($_COOKIE['sonika_cookie_consent'] ?? '');
        if ($raw === 'all') return true;
        if ($raw === '' || $raw === 'necessary') return false;
        $data = json_decode($raw, true);
        return is_array($data) && !empty($data['analytics']);
    }

    private static function detectDevice(string $ua): string
    {
        $ua = strtolower($ua);
        if (preg_match('/(smarttv|smart-tv|tizen|webos|netcast|bravia|aft[ a-z]|appletv|roku|hbbtv|vidaa)/', $ua)) return 'tv';
        if (preg_match('/(tablet|ipad|android(?!.*mobile))/', $ua)) return 'tablet';
        if (preg_match('/(mobile|iphone|android)/', $ua)) return 'mobile';
        return 'desktop';
    }
}
