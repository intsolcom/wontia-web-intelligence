<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

class ProgramAnalyticsService
{
    private static bool $tableChecked = false;

    public static function ensureTable(): void
    {
        if (self::$tableChecked) return;
        self::$tableChecked = true;
        try {
            Database::instance()->exec("CREATE TABLE IF NOT EXISTS program_events (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                site_id INT NOT NULL,
                session_id VARCHAR(64) NOT NULL,
                event VARCHAR(32) NOT NULL,
                page VARCHAR(255) DEFAULT '',
                section VARCHAR(64) DEFAULT '',
                seconds INT DEFAULT 0,
                clicks INT DEFAULT 0,
                scroll_pct TINYINT DEFAULT 0,
                meta VARCHAR(500) DEFAULT '',
                device VARCHAR(16) DEFAULT '',
                os VARCHAR(32) DEFAULT '',
                browser VARCHAR(32) DEFAULT '',
                country CHAR(2) DEFAULT '',
                ip_hash CHAR(64) DEFAULT '',
                is_bot TINYINT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_site_time (site_id, created_at),
                INDEX idx_site_event (site_id, event),
                INDEX idx_session (site_id, session_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            Database::instance()->exec("CREATE TABLE IF NOT EXISTS program_geo_cache (
                ip_hash CHAR(64) PRIMARY KEY,
                country CHAR(2) NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {
        }
    }

    public static function isBot(string $ua): bool
    {
        return (bool)preg_match('/bot|crawler|spider|crawling|headless|slurp|bingpreview|facebookexternalhit|semrush|ahrefs|python-requests|curl|wget|scrapy|uptime|pingdom/i', $ua);
    }

    public static function device(string $ua): string
    {
        $u = strtolower($ua);
        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/', $u)) return 'tablet';
        if (preg_match('/(mobile|iphone|ipod|android|blackberry|opera mini|iemobile)/', $u)) return 'mobile';
        return 'desktop';
    }

    public static function os(string $ua): string
    {
        $u = strtolower($ua);
        if (str_contains($u, 'windows')) return 'Windows';
        if (str_contains($u, 'iphone') || str_contains($u, 'ipad') || str_contains($u, 'ios')) return 'iOS';
        if (str_contains($u, 'android')) return 'Android';
        if (str_contains($u, 'mac os') || str_contains($u, 'macintosh')) return 'macOS';
        if (str_contains($u, 'linux')) return 'Linux';
        return 'Other';
    }

    public static function browser(string $ua): string
    {
        $u = strtolower($ua);
        if (str_contains($u, 'edg/')) return 'Edge';
        if (str_contains($u, 'opr/') || str_contains($u, 'opera')) return 'Opera';
        if (str_contains($u, 'chrome') || str_contains($u, 'crios')) return 'Chrome';
        if (str_contains($u, 'firefox') || str_contains($u, 'fxios')) return 'Firefox';
        if (str_contains($u, 'safari')) return 'Safari';
        return 'Other';
    }

    public static function country(string $ip, string $ipHash): string
    {
        $cf = strtoupper(trim((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '')));
        if (preg_match('/^[A-Z]{2}$/', $cf) && $cf !== 'XX') return $cf;
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) return '';
        $db = Database::instance();
        try {
            $st = $db->prepare("SELECT country FROM program_geo_cache WHERE ip_hash = ? LIMIT 1");
            $st->execute([$ipHash]);
            $c = $st->fetchColumn();
            if ($c !== false && $c !== '') return $c;
        } catch (\Throwable $e) {
        }
        $ctx = stream_context_create(['http' => ['timeout' => 2]]);
        $resp = @file_get_contents('http://ip-api.com/json/' . urlencode($ip) . '?fields=status,countryCode', false, $ctx);
        if ($resp) {
            $j = json_decode($resp, true);
            $cc = strtoupper((string)($j['countryCode'] ?? ''));
            if (($j['status'] ?? '') === 'success' && preg_match('/^[A-Z]{2}$/', $cc)) {
                try {
                    $db->prepare("INSERT INTO program_geo_cache (ip_hash, country) VALUES (?, ?) ON DUPLICATE KEY UPDATE country = VALUES(country)")->execute([$ipHash, $cc]);
                } catch (\Throwable $e) {
                }
                return $cc;
            }
        }
        return '';
    }

    public static function track(string $event, string $sessionId, string $page, array $data = []): bool
    {
        self::ensureTable();
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (self::isBot($ua)) return false;
        $ip = trim((string)($_SERVER['HTTP_X_REAL_IP'] ?? ''));
        if ($ip === '') {
            $xff = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
            $ip = trim((string)(explode(',', $xff)[0] ?? ''));
        }
        if ($ip === '') $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $ipHash = hash('sha256', $ip . '|' . (string)Config::get('JWT_SECRET', 'intsolcom'));
        $siteId = (int)Config::get('SITE_ID', '1');
        $db = Database::instance();

        $recent = $db->prepare("SELECT COUNT(*) FROM program_events WHERE site_id = ? AND ip_hash = ? AND created_at > (NOW() - INTERVAL 1 HOUR)");
        $recent->execute([$siteId, $ipHash]);
        if ((int)$recent->fetchColumn() > 300) return false;

        $meta = '';
        if (!empty($data['meta'])) {
            $meta = is_array($data['meta']) ? json_encode($data['meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)$data['meta'];
            $meta = mb_substr($meta, 0, 500);
        }

        $device = self::device($ua);
        $osName = self::os($ua);
        $browser = self::browser($ua);
        $country = in_array($event, ['view', 'apply_click'], true) ? self::country($ip, $ipHash) : '';

        $st = $db->prepare("INSERT INTO program_events (site_id, session_id, event, page, section, seconds, clicks, scroll_pct, meta, device, os, browser, country, ip_hash) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $st->execute([
            $siteId,
            mb_substr($sessionId, 0, 64),
            mb_substr($event, 0, 32),
            mb_substr($page, 0, 255),
            mb_substr((string)($data['section'] ?? ''), 0, 64),
            max(0, min(86400, (int)($data['seconds'] ?? 0))),
            max(0, (int)($data['clicks'] ?? 0)),
            max(0, min(100, (int)($data['scroll_pct'] ?? 0))),
            $meta,
            $device,
            $osName,
            $browser,
            $country,
            $ipHash,
        ]);
        return true;
    }

    public static function stats(string $from, string $to): array
    {
        self::ensureTable();
        $db = Database::instance();
        $siteId = (int)Config::get('SITE_ID', '1');
        $range = [$siteId, $from . ' 00:00:00', $to . ' 23:59:59'];

        $q = function (string $sql) use ($db, $range) {
            $st = $db->prepare($sql);
            $st->execute($range);
            return $st->fetchAll();
        };

        $views = $q("SELECT COUNT(*) c, COUNT(DISTINCT session_id) s FROM program_events WHERE site_id = ? AND event = 'view' AND created_at BETWEEN ? AND ?");
        $visits = (int)($views[0]['c'] ?? 0);
        $sessions = (int)($views[0]['s'] ?? 0);

        $timeRow = $q("SELECT COALESCE(SUM(seconds),0) t, COUNT(DISTINCT session_id) s FROM program_events WHERE site_id = ? AND event = 'time' AND created_at BETWEEN ? AND ?");
        $totalSeconds = (int)($timeRow[0]['t'] ?? 0);
        $timeSessions = max(1, (int)($timeRow[0]['s'] ?? 0));

        $applyClicks = (int)($q("SELECT COUNT(*) c FROM program_events WHERE site_id = ? AND event = 'apply_click' AND created_at BETWEEN ? AND ?")[0]['c'] ?? 0);
        $applySessions = (int)($q("SELECT COUNT(DISTINCT session_id) c FROM program_events WHERE site_id = ? AND event = 'apply_click' AND created_at BETWEEN ? AND ?")[0]['c'] ?? 0);
        $totalPageClicks = (int)($q("SELECT COALESCE(SUM(clicks),0) c FROM program_events WHERE site_id = ? AND event = 'clicks' AND created_at BETWEEN ? AND ?")[0]['c'] ?? 0);

        $scrollAvg = (int)round((float)($q("SELECT COALESCE(AVG(scroll_pct),0) a FROM program_events WHERE site_id = ? AND event = 'scroll' AND created_at BETWEEN ? AND ?")[0]['a'] ?? 0));
        $scrollFull = (int)($q("SELECT COUNT(DISTINCT session_id) c FROM program_events WHERE site_id = ? AND event = 'scroll' AND scroll_pct >= 100 AND created_at BETWEEN ? AND ?")[0]['c'] ?? 0);

        $group = function (string $col) use ($q) {
            return $q("SELECT `$col` k, COUNT(*) c FROM program_events WHERE site_id = ? AND event = 'view' AND `$col` != '' AND created_at BETWEEN ? AND ? GROUP BY `$col` ORDER BY c DESC LIMIT 12");
        };
        $sections = $q("SELECT section k, COUNT(*) c FROM program_events WHERE site_id = ? AND event = 'section_view' AND section != '' AND created_at BETWEEN ? AND ? GROUP BY section ORDER BY c DESC LIMIT 12");
        $placements = $q("SELECT meta k, COUNT(*) c FROM program_events WHERE site_id = ? AND event = 'apply_click' AND created_at BETWEEN ? AND ? GROUP BY meta ORDER BY c DESC LIMIT 10");
        $daily = $q("SELECT DATE(created_at) d, COUNT(*) c FROM program_events WHERE site_id = ? AND event = 'view' AND created_at BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY d ASC");

        return [
            'visits' => $visits,
            'sessions' => $sessions,
            'avg_seconds' => (int)round($totalSeconds / $timeSessions),
            'apply_clicks' => $applyClicks,
            'apply_sessions' => $applySessions,
            'conversion_pct' => $sessions > 0 ? round($applySessions / $sessions * 100, 1) : 0.0,
            'page_clicks' => $totalPageClicks,
            'scroll_avg' => $scrollAvg,
            'scroll_full_sessions' => $scrollFull,
            'devices' => $group('device'),
            'os' => $group('os'),
            'browsers' => $group('browser'),
            'countries' => $group('country'),
            'sections' => $sections,
            'placements' => $placements,
            'daily' => $daily,
            'recent' => $q("SELECT event, page, section, seconds, clicks, scroll_pct, meta, device, os, browser, country, created_at FROM program_events WHERE site_id = ? AND created_at BETWEEN ? AND ? ORDER BY id DESC LIMIT 40"),
            'from' => $from,
            'to' => $to,
        ];
    }
}
