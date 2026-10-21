<?php
namespace App\Services;

use App\Core\Database;

/**
 * SEO Tracking — recolección y agregación de eventos de visitantes.
 * Mejoras incluidas: UTM, profundidad de scroll, tiempo en página, dispositivo/OS/browser,
 * país (Cloudflare), hash de IP (privacidad, sin PII), detección de bots, sesiones y envíos efectivos.
 */
class SeoTrackingService
{
    public const TYPES = ['page_view', 'page_leave', 'link_click', 'cta_click', 'form_submit', 'outbound'];

    public function ready(): bool
    {
        try { Database::instance()->query('SELECT 1 FROM seo_tracking_events LIMIT 1'); return true; }
        catch (\Throwable $e) { return false; }
    }

    public function isBot(string $ua): bool
    {
        return (bool)preg_match('/bot|crawl|spider|slurp|bing|yandex|ahrefs|semrush|facebookexternalhit|preview|headless/i', $ua);
    }

    public function device(string $ua): string
    {
        $ua = strtolower($ua);
        if (preg_match('/(tablet|ipad|android(?!.*mobile))/', $ua)) return 'tablet';
        if (preg_match('/(mobile|iphone|android)/', $ua)) return 'mobile';
        return 'desktop';
    }

    public function os(string $ua): string
    {
        $ua = strtolower($ua);
        foreach (['windows' => 'Windows', 'android' => 'Android', 'iphone|ipad|ios' => 'iOS', 'mac os|macintosh' => 'macOS', 'linux' => 'Linux', 'cros' => 'ChromeOS'] as $re => $name) {
            if (preg_match('/' . $re . '/', $ua)) return $name;
        }
        return 'Otro';
    }

    public function browser(string $ua): string
    {
        $ua = strtolower($ua);
        foreach (['edg' => 'Edge', 'opr|opera' => 'Opera', 'chrome|crios' => 'Chrome', 'firefox|fxios' => 'Firefox', 'safari' => 'Safari'] as $re => $name) {
            if (preg_match('/' . $re . '/', $ua)) return $name;
        }
        return 'Otro';
    }

    /** Recibe un lote de eventos del navegador y los persiste. */
    public function record(array $events): int
    {
        if (!$this->ready()) return 0;
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($this->isBot($ua)) return 0;
        $device = $this->device($ua);
        $os = $this->os($ua);
        $browser = $this->browser($ua);
        $country = strtoupper(substr((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ($_SERVER['HTTP_X_COUNTRY'] ?? '')), 0, 2)) ?: null;
        $ipHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));

        $stmt = Database::instance()->prepare(
            'INSERT INTO seo_tracking_events (site_id, session_id, type, page_url, target, label, referrer, utm, duration_ms, scroll_pct, device, os, browser, country, ip_hash, is_bot, created_at)
             VALUES (@site_id, :sid, :type, :page, :target, :label, :ref, :utm, :dur, :scroll, :device, :os, :browser, :country, :ip, 0, NOW())'
        );
        $n = 0;
        foreach (array_slice($events, 0, 40) as $e) {
            $type = (string)($e['type'] ?? '');
            if (!in_array($type, self::TYPES, true)) continue;
            $sid = substr(preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($e['session_id'] ?? '')), 0, 64);
            if ($sid === '') continue;
            $utm = null;
            if (!empty($e['utm']) && is_array($e['utm'])) $utm = json_encode(array_slice($e['utm'], 0, 8), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            try {
                $stmt->execute([
                    'sid' => $sid,
                    'type' => $type,
                    'page' => mb_substr((string)($e['page_url'] ?? ''), 0, 500),
                    'target' => $e['target'] !== '' ? mb_substr((string)$e['target'], 0, 500) : null,
                    'label' => $e['label'] !== '' ? mb_substr((string)$e['label'], 0, 255) : null,
                    'ref' => mb_substr((string)($e['referrer'] ?? ''), 0, 500),
                    'utm' => $utm,
                    'dur' => isset($e['duration_ms']) ? max(0, min(86400000, (int)$e['duration_ms'])) : null,
                    'scroll' => isset($e['scroll_pct']) ? max(0, min(100, (int)$e['scroll_pct'])) : null,
                    'device' => $device, 'os' => $os, 'browser' => $browser,
                    'country' => $country, 'ip' => $ipHash,
                ]);
                $n++;
            } catch (\Throwable $ex) {
            }
        }
        return $n;
    }

    /** Agregados para el dashboard SEO. */
    public function stats(int $days = 7): array
    {
        if (!$this->ready()) return ['ready' => false];
        $days = max(1, min(365, $days));
        $db = Database::instance();
        $since = "created_at >= (NOW() - INTERVAL $days DAY)";
        $scalar = function (string $sql) use ($db) { try { return $db->query($sql)->fetchColumn(); } catch (\Throwable $e) { return 0; } };
        $sessions = (int)$scalar("SELECT COUNT(DISTINCT session_id) FROM seo_tracking_events WHERE site_id = @site_id AND $since");
        $views = (int)$scalar("SELECT COUNT(*) FROM seo_tracking_events WHERE site_id = @site_id AND type='page_view' AND $since");
        $clicks = (int)$scalar("SELECT COUNT(*) FROM seo_tracking_events WHERE site_id = @site_id AND type IN ('link_click','cta_click','outbound') AND $since");
        $cta = (int)$scalar("SELECT COUNT(*) FROM seo_tracking_events WHERE site_id = @site_id AND type='cta_click' AND $since");
        $submits = (int)$scalar("SELECT COUNT(*) FROM seo_tracking_events WHERE site_id = @site_id AND type='form_submit' AND $since");
        $avgMs = (int)$scalar("SELECT AVG(duration_ms) FROM seo_tracking_events WHERE site_id = @site_id AND type='page_leave' AND duration_ms > 0 AND $since");
        $avgScroll = (int)$scalar("SELECT AVG(scroll_pct) FROM seo_tracking_events WHERE site_id = @site_id AND scroll_pct IS NOT NULL AND $since");
        $conv = $views > 0 ? round($submits / $views * 100, 1) : 0;

        $group = function (string $col) use ($db, $since) {
            $out = [];
            try {
                foreach ($db->query("SELECT $col AS k, COUNT(*) c FROM seo_tracking_events WHERE site_id = @site_id AND $since AND $col IS NOT NULL AND $col <> '' GROUP BY $col ORDER BY c DESC LIMIT 8")->fetchAll() as $r) $out[] = ['k' => $r['k'], 'c' => (int)$r['c']];
            } catch (\Throwable $e) {}
            return $out;
        };

        $topLinks = [];
        try {
            foreach ($db->query("SELECT target, COUNT(*) c FROM seo_tracking_events WHERE site_id = @site_id AND type IN ('link_click','cta_click','outbound') AND target IS NOT NULL AND $since GROUP BY target ORDER BY c DESC LIMIT 10")->fetchAll() as $r) $topLinks[] = ['target' => $r['target'], 'c' => (int)$r['c']];
        } catch (\Throwable $e) {}

        $daily = [];
        try {
            foreach ($db->query("SELECT DATE(created_at) d, COUNT(DISTINCT session_id) s, SUM(type='page_view') v, SUM(type='form_submit') f FROM seo_tracking_events WHERE site_id = @site_id AND $since GROUP BY DATE(created_at) ORDER BY d")->fetchAll() as $r) {
                $daily[] = ['d' => $r['d'], 'sessions' => (int)$r['s'], 'views' => (int)$r['v'], 'submits' => (int)$r['f']];
            }
        } catch (\Throwable $e) {}

        return [
            'ready' => true, 'days' => $days,
            'sessions' => $sessions, 'views' => $views, 'clicks' => $clicks, 'cta' => $cta, 'submits' => $submits,
            'conversion' => $conv, 'avg_time_s' => (int)round($avgMs / 1000), 'avg_scroll' => $avgScroll,
            'devices' => $group('device'), 'os' => $group('os'), 'browsers' => $group('browser'), 'countries' => $group('country'),
            'top_links' => $topLinks, 'daily' => $daily,
        ];
    }
}
