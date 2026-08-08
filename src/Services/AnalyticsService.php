<?php
namespace App\Services;

use App\Core\Database;

class AnalyticsService
{
    public static function track(string $url, string $referrer, string $userAgent, bool $isInternal = false): void
    {
        try {
            $db = Database::instance();
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ipHash = hash('sha256', $ip);
            $device = self::detectDevice($userAgent);
            $country = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'XX';

            $db->prepare("INSERT INTO analytics_views (site_id, page_url, referrer, user_agent, ip_hash, is_internal, country, device, created_at) VALUES (1, :url, :ref, :ua, :ip, :internal, :country, :device, NOW())")
                ->execute([
                    'url' => $url,
                    'ref' => $referrer,
                    'ua' => $userAgent,
                    'ip' => $ipHash,
                    'internal' => $isInternal ? 1 : 0,
                    'country' => $country,
                    'device' => $device,
                ]);
        } catch (\Exception $e) {}
    }

    private static function detectDevice(string $ua): string
    {
        $ua = strtolower($ua);
        if (preg_match('/(tablet|ipad|android(?!.*mobile))/', $ua)) return 'tablet';
        if (preg_match('/(mobile|iphone|android)/', $ua)) return 'mobile';
        return 'desktop';
    }
}
