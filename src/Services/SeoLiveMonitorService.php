<?php
namespace App\Services;

use App\Core\Database;

class SeoLiveMonitorService
{
    public static function register(): void
    {
        if (PHP_SAPI === 'cli') return;
        static $registered = false;
        if ($registered) return;
        $registered = true;
        register_shutdown_function([self::class, 'record']);
    }

    public static function record(): void
    {
        try {
            $path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
            $path = '/' . ltrim($path, '/');
            if (preg_match('~^/(admin|install|assets)(/|$)~i', $path)) return;
            if (preg_match('~^/api/v1/(admin|brick)(/|$)~i', $path)) return;
            if (preg_match('~^/api/v1/public/(seo/track|tia/event)(/|$)~i', $path)) return;
            $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
            if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], true)) return;
            $status = function_exists('http_response_code') ? (int)http_response_code() : 200;
            if ($status < 100 || $status > 599) $status = 200;
            $start = defined('WONTIA_START') ? (float)WONTIA_START : (float)($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
            $duration = max(0, min(600000, (int)round((microtime(true) - $start) * 1000)));
            $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
            $ip = self::clientIp();
            $country = strtoupper(trim((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ($_SERVER['HTTP_X_COUNTRY'] ?? ''))));
            if (!preg_match('/^[A-Z]{2}$/', $country) || $country === 'XX') $country = null;
            $city = self::headerValue(['HTTP_CF_IPCITY', 'HTTP_X_GEO_CITY', 'HTTP_X_CITY', 'HTTP_X_APPENGINE_CITY']);
            $region = self::headerValue(['HTTP_CF_REGION', 'HTTP_X_GEO_REGION', 'HTTP_X_REGION']);
            $device = self::device($ua);
            $os = self::os($ua);
            $browser = self::browser($ua);
            $isBot = self::isBot($ua) || self::isProbePath($path) ? 1 : 0;
            $protocol = substr((string)($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1'), 0, 10);
            $referrer = mb_substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 500);
            $requestId = mb_substr((string)($_SERVER['HTTP_X_REQUEST_ID'] ?? ''), 0, 80);
            $internal = SeoInternalTrafficService::detect();
            $utm = SeoInternalTrafficService::utmFromRequest();
            $visitorHash = SeoInternalTrafficService::visitorHash($ip, $ua);
            $sessionId = SeoInternalTrafficService::sessionId();
            $isPageView = SeoInternalTrafficService::isPageView($method, $status, $path) ? 1 : 0;

            $db = Database::instance();
            $db->prepare('INSERT INTO seo_live_requests (site_id, request_id, method, path, protocol, status, duration_ms, ip, country, city, region, device, os, browser, referrer, user_agent, is_bot, is_internal, internal_reason, device_id, session_id, visitor_hash, is_page_view, landing_url, query_string, utm_source, utm_medium, utm_campaign, utm_term, utm_content, fbclid, created_at) VALUES (@site_id, :request_id, :method, :path, :protocol, :status, :duration, :ip, :country, :city, :region, :device, :os, :browser, :referrer, :ua, :bot, :internal, :reason, :device_id, :session_id, :visitor_hash, :page_view, :landing_url, :query_string, :utm_source, :utm_medium, :utm_campaign, :utm_term, :utm_content, :fbclid, NOW())')
                ->execute([
                    'request_id' => $requestId !== '' ? $requestId : null,
                    'method' => $method,
                    'path' => mb_substr($path, 0, 500),
                    'protocol' => $protocol,
                    'status' => $status,
                    'duration' => $duration,
                    'ip' => $ip,
                    'country' => $country,
                    'city' => $city,
                    'region' => $region,
                    'device' => $device,
                    'os' => $os,
                    'browser' => $browser,
                    'referrer' => $referrer,
                    'ua' => mb_substr($ua, 0, 500),
                    'bot' => $isBot,
                    'internal' => !empty($internal['internal']) ? 1 : 0,
                    'reason' => !empty($internal['internal']) ? mb_substr((string)$internal['reason'], 0, 40) : null,
                    'device_id' => $internal['device_id'] ?? null,
                    'session_id' => $sessionId,
                    'visitor_hash' => $visitorHash,
                    'page_view' => $isPageView,
                    'landing_url' => $utm['landing_url'],
                    'query_string' => $utm['query_string'],
                    'utm_source' => $utm['utm_source'],
                    'utm_medium' => $utm['utm_medium'],
                    'utm_campaign' => $utm['utm_campaign'],
                    'utm_term' => $utm['utm_term'],
                    'utm_content' => $utm['utm_content'],
                    'fbclid' => $utm['fbclid'],
                ]);
            if (random_int(1, 100) === 1) {
                $days = SeoInternalTrafficService::retentionDays();
                $db->prepare('DELETE FROM seo_live_requests WHERE site_id = @site_id AND created_at < (NOW() - INTERVAL ' . $days . ' DAY) LIMIT 5000')->execute();
            }
        } catch (\Throwable $e) {
        }
    }

    public function overview(int $afterId = 0, int $limit = 100, string $audience = 'client'): array
    {
        $limit = max(20, min(200, $limit));
        $afterId = max(0, $afterId);
        $audience = in_array($audience, ['client', 'internal', 'bot', 'all'], true) ? $audience : 'client';
        $where = match ($audience) {
            'internal' => 'is_internal = 1',
            'bot' => 'is_bot = 1',
            'all' => '1 = 1',
            default => 'is_internal = 0 AND is_bot = 0',
        };
        $db = Database::instance();
        $items = [];
        $emptyStats = [
            'requests' => 0, 'responses' => 0, 'ok' => 0, 'redirects' => 0,
            'client_errors' => 0, 'server_errors' => 0, 'unique_ips' => 0,
            'unique_visitors' => 0, 'unique_cities' => 0, 'avg_ms' => 0,
            'mobile' => 0, 'desktop' => 0, 'tablet' => 0, 'tv' => 0,
            'bots' => 0, 'page_views' => 0,
        ];
        $stats = $emptyStats;
        $internalStats = $emptyStats;
        $clientStats = $emptyStats;
        $botStats = $emptyStats;
        try {
            $sql = $afterId > 0
                ? "SELECT * FROM seo_live_requests WHERE site_id = @site_id AND $where AND id > :after ORDER BY id ASC LIMIT " . $limit
                : "SELECT * FROM seo_live_requests WHERE site_id = @site_id AND $where ORDER BY id DESC LIMIT " . $limit;
            $stmt = $db->prepare($sql);
            if ($afterId > 0) $stmt->execute(['after' => $afterId]);
            else $stmt->execute();
            $rows = $stmt->fetchAll();
            if ($afterId === 0) $rows = array_reverse($rows);
            foreach ($rows as $row) {
                $items[] = [
                    'id' => (int)$row['id'],
                    'method' => (string)$row['method'],
                    'path' => (string)$row['path'],
                    'protocol' => (string)($row['protocol'] ?? 'HTTP/1.1'),
                    'status' => (int)$row['status'],
                    'duration_ms' => (int)$row['duration_ms'],
                    'ip' => (string)($row['ip'] ?? ''),
                    'country' => (string)($row['country'] ?? ''),
                    'city' => (string)($row['city'] ?? ''),
                    'region' => (string)($row['region'] ?? ''),
                    'device' => (string)($row['device'] ?? ''),
                    'os' => (string)($row['os'] ?? ''),
                    'browser' => (string)($row['browser'] ?? ''),
                    'is_bot' => (int)($row['is_bot'] ?? 0),
                    'is_internal' => (int)($row['is_internal'] ?? 0),
                    'internal_reason' => (string)($row['internal_reason'] ?? ''),
                    'is_page_view' => (int)($row['is_page_view'] ?? 0),
                    'utm_source' => (string)($row['utm_source'] ?? ''),
                    'utm_campaign' => (string)($row['utm_campaign'] ?? ''),
                    'fbclid' => (string)($row['fbclid'] ?? ''),
                    'created_at' => (string)$row['created_at'],
                ];
            }
            $makeStats = static function (string $filter) use ($db): array {
                $out = [];
                $row = $db->query("SELECT
                    COUNT(*) requests,
                    SUM(status > 0) responses,
                    SUM(status >= 200 AND status < 300) ok,
                    SUM(status >= 300 AND status < 400) redirects,
                    SUM(status >= 400 AND status < 500) client_errors,
                    SUM(status >= 500) server_errors,
                    COUNT(DISTINCT NULLIF(ip, '')) unique_ips,
                    COUNT(DISTINCT NULLIF(visitor_hash, '')) unique_visitors,
                    COUNT(DISTINCT NULLIF(city, '')) unique_cities,
                    COALESCE(AVG(duration_ms), 0) avg_ms,
                    SUM(device = 'mobile') mobile,
                    SUM(device = 'desktop') desktop,
                    SUM(device = 'tablet') tablet,
                    SUM(device = 'tv') tv,
                    SUM(is_bot = 1) bots,
                    COALESCE(SUM(is_page_view = 1), 0) page_views
                    FROM seo_live_requests
                    WHERE site_id = @site_id AND created_at >= (NOW() - INTERVAL 24 HOUR) AND $filter")->fetch();
                return is_array($row) ? $row : [];
            };
            $merge = static function (array $row, array $base): array {
                foreach ($base as $key => $value) $base[$key] = (int)($row[$key] ?? 0);
                return $base;
            };
            $stats = $merge($makeStats($where), $emptyStats);
            $internalStats = $merge($makeStats('is_internal = 1'), $emptyStats);
            $clientStats = $merge($makeStats('is_internal = 0 AND is_bot = 0'), $emptyStats);
            $botStats = $merge($makeStats('is_bot = 1'), $emptyStats);
        } catch (\Throwable $e) {
        }
        return [
            'items' => $items,
            'stats' => $stats,
            'internal_stats' => $internalStats,
            'client_stats' => $clientStats,
            'bot_stats' => $botStats,
            'audience' => $audience,
            'server_time' => date('c'),
        ];
    }

    public static function clientIp(): ?string
    {
        return SeoInternalTrafficService::clientIp();
    }

    private static function headerValue(array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string)($_SERVER[$key] ?? ''));
            if ($value !== '') return mb_substr($value, 0, 120);
        }
        return null;
    }

    private static function isBot(string $ua): bool
    {
        return (bool)preg_match('/bot|crawl|spider|slurp|bing|yandex|ahrefs|semrush|facebookexternalhit|preview|headless/i', $ua);
    }

    /** Sondas de vulnerabilidad típicas: ruido de seguridad, no tráfico SEO. */
    public static function isProbePath(string $path): bool
    {
        $path = strtolower('/' . ltrim($path, '/'));
        static $patterns = [
            '~^/(wp-|wordpress|xmlrpc\.php)~',
            '~^/\.(env|git|aws|htaccess|htpasswd|svn)~',
            '~^/(phpinfo|php_info|info\.php|server-info|server-status|_profiler|actuator|telescope|metrics|healthz?|readyz)([./-]|$)~',
            '~^/(credentials|secrets|auth|config|settings|appsettings|database|sentry|service-account|service_account|meta)([./-]|$)~',
            '~^/env(\.txt|\.js|\.json|\.ya?ml)?$~',
            '~^/(web\.config|config\.(json|ya?ml)|appsettings(\.[a-z]+)?\.json|sentry\.properties)$~',
            '~^/(vendor/|storage/logs|cgi-bin|phpmyadmin|adminer|backup|db\.sql)~',
            '~^/(two-factor-challenge|email/verify|email/verification-notification|password/reset|password/email|email/resend)(/|$)~',
            '~\.(json|env|sql|bak|old|swp|log|tfvars|tfstate|ya?ml|lock|axd)$~',
            '~^/(gcp|aws|azure|firebase|terraform|docker|k8s|kubernetes)[-_.]~',
            '~^/(site|elmah|trace|server)([/.]|$)~',
            '~^/(v1|v2|v3|_catalog)([/.]|$)~',
            '~^/(cms|jenkins|jenkinsfile|netlify\.toml|dockerfile|makefile|vagrantfile|bitbucket-pipelines\.ya?ml|gitlab-ci\.ya?ml|travis\.ya?ml|circleci|phpunit\.xml|behat\.ya?ml|grumphp\.ya?ml|sonar-project\.properties|readme\.md|contributing\.md|changelog\.md|storybook|graphql|graphiql|swagger|api-docs)([/.]|$)~',
            '~\.(md|toml|ini|conf|properties|cfg|dist|sample)$~',
            '~^/(docker-compose\.ya?ml|serverless\.ya?ml|composer\.(json|lock)|package\.json|terraform\.tfstate(\.backup)?)$~',
        ];
        foreach ($patterns as $re) {
            if (preg_match($re, $path)) return true;
        }
        return false;
    }

    private static function device(string $ua): string
    {
        $ua = strtolower($ua);
        if (preg_match('/(smarttv|smart-tv|tizen|webos|netcast|bravia|aft[ a-z]|appletv|roku|hbbtv|vidaa)/', $ua)) return 'tv';
        if (preg_match('/(tablet|ipad|android(?!.*mobile))/', $ua)) return 'tablet';
        if (preg_match('/(mobile|iphone|android)/', $ua)) return 'mobile';
        return 'desktop';
    }

    private static function os(string $ua): string
    {
        $ua = strtolower($ua);
        foreach (['windows' => 'Windows', 'android' => 'Android', 'iphone|ipad|ios' => 'iOS', 'mac os|macintosh' => 'macOS', 'linux' => 'Linux', 'cros' => 'ChromeOS'] as $pattern => $name) {
            if (preg_match('/' . $pattern . '/', $ua)) return $name;
        }
        return 'Otro';
    }

    private static function browser(string $ua): string
    {
        $ua = strtolower($ua);
        foreach (['edg' => 'Edge', 'opr|opera' => 'Opera', 'chrome|crios' => 'Chrome', 'firefox|fxios' => 'Firefox', 'safari' => 'Safari'] as $pattern => $name) {
            if (preg_match('/' . $pattern . '/', $ua)) return $name;
        }
        return 'Otro';
    }
}
