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
    public const TYPES = ['page_view', 'page_leave', 'link_click', 'cta_click', 'form_submit', 'outbound', 'product_view', 'view_category', 'search', 'select_item', 'cart_add', 'remove_from_cart', 'cart_snapshot', 'view_cart', 'checkout_start', 'checkout_abandon', 'add_shipping_info', 'add_payment_info', 'purchase', 'whatsapp_click', 'contact_click'];

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
        if (preg_match('/(smarttv|smart-tv|tizen|webos|netcast|bravia|aft[ a-z]|appletv|roku|hbbtv|vidaa)/', $ua)) return 'tv';
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
        if (!$this->consentAllowed()) return 0;
        \App\Services\SeoTrafficSchemaService::ensure();
        if (!$this->ready()) return 0;
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($this->isBot($ua)) return 0;
        $device = $this->device($ua);
        $os = $this->os($ua);
        $browser = $this->browser($ua);
        $country = strtoupper(substr((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ($_SERVER['HTTP_X_COUNTRY'] ?? '')), 0, 2)) ?: null;
        $ip = SeoInternalTrafficService::clientIp() ?? '0.0.0.0';
        $ipHash = hash('sha256', $ip);
        $visitorHash = SeoInternalTrafficService::visitorHash($ip, $ua);
        $internal = SeoInternalTrafficService::detect();
        $consentSnapshot = class_exists('\App\Services\TrackingConsentService') ? \App\Services\TrackingConsentService::snapshot() : null;

        $stmt = Database::instance()->prepare(
            'INSERT INTO seo_tracking_events (site_id, session_id, type, event_id, event_name, page_url, target, label, referrer, utm, duration_ms, scroll_pct, device, os, browser, country, ip_hash, is_bot, is_internal, internal_reason, device_id, visitor_hash, landing_url, utm_source, utm_medium, utm_campaign, utm_term, utm_content, fbclid, items_json, value_cents, currency, consent_snapshot, is_test, created_at)
             VALUES (@site_id, :sid, :type, :event_id, :event_name, :page, :target, :label, :ref, :utm, :dur, :scroll, :device, :os, :browser, :country, :ip, 0, :internal, :reason, :device_id, :visitor_hash, :landing_url, :utm_source, :utm_medium, :utm_campaign, :utm_term, :utm_content, :fbclid, :items_json, :value_cents, :currency, :consent_snapshot, :is_test, NOW())
             ON DUPLICATE KEY UPDATE id = id'
        );
        $n = 0;
        foreach (array_slice($events, 0, 40) as $e) {
            $type = (string)($e['type'] ?? '');
            if (!in_array($type, self::TYPES, true)) continue;
            $sid = substr(preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($e['session_id'] ?? '')), 0, 64);
            if ($sid === '') continue;
            $utmArr = (!empty($e['utm']) && is_array($e['utm'])) ? array_slice($e['utm'], 0, 9) : [];
            $utm = $utmArr ? json_encode($utmArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            $pick = static function (string $key, int $max = 160) use ($utmArr): ?string {
                $value = isset($utmArr[$key]) && is_scalar($utmArr[$key]) ? trim((string)$utmArr[$key]) : '';
                return $value !== '' ? mb_substr($value, 0, $max) : null;
            };
            $eventId = trim((string)($e['event_id'] ?? ''));
            if ($eventId === '' || !preg_match('/^[A-Za-z0-9_:.\-]{6,80}$/', $eventId)) {
                $eventId = 'srv_' . substr(hash('sha256', $sid . '|' . $type . '|' . (string)($e['page_url'] ?? '') . '|' . (string)($e['target'] ?? '') . '|' . microtime(true) . '|' . bin2hex(random_bytes(6))), 0, 40);
            }
            $itemsRaw = $e['items'] ?? $e['cart_items'] ?? null;
            $itemsJson = null;
            if (is_array($itemsRaw) && $itemsRaw) {
                $itemsJson = json_encode(array_slice($itemsRaw, 0, 50), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $valueCents = null;
            if (isset($e['value_cents']) && is_numeric($e['value_cents'])) $valueCents = max(0, (int)$e['value_cents']);
            elseif ($type === 'purchase' && isset($e['label']) && is_numeric($e['label'])) $valueCents = max(0, (int)$e['label']);
            $currency = strtoupper(trim((string)($e['currency'] ?? '')));
            if (!preg_match('/^[A-Z]{3}$/', $currency)) $currency = $valueCents !== null ? 'COP' : null;
            try {
                $stmt->execute([
                    'sid' => $sid,
                    'type' => $type,
                    'event_id' => $eventId,
                    'event_name' => $this->eventName($type),
                    'page' => mb_substr((string)($e['page_url'] ?? ''), 0, 500),
                    'target' => isset($e['target']) && $e['target'] !== '' ? mb_substr((string)$e['target'], 0, 500) : null,
                    'label' => isset($e['label']) && $e['label'] !== '' ? mb_substr((string)$e['label'], 0, 255) : null,
                    'ref' => mb_substr((string)($e['referrer'] ?? ''), 0, 500),
                    'utm' => $utm,
                    'dur' => isset($e['duration_ms']) ? max(0, min(86400000, (int)$e['duration_ms'])) : null,
                    'scroll' => isset($e['scroll_pct']) ? max(0, min(100, (int)$e['scroll_pct'])) : null,
                    'device' => $device, 'os' => $os, 'browser' => $browser,
                    'country' => $country, 'ip' => $ipHash,
                    'internal' => !empty($internal['internal']) ? 1 : 0,
                    'reason' => !empty($internal['internal']) ? mb_substr((string)$internal['reason'], 0, 40) : null,
                    'device_id' => $internal['device_id'] ?? null,
                    'visitor_hash' => $visitorHash,
                    'landing_url' => $pick('landing', 500) ?? (isset($e['landing_url']) ? mb_substr((string)$e['landing_url'], 0, 500) : null),
                    'utm_source' => $pick('utm_source', 120),
                    'utm_medium' => $pick('utm_medium', 120),
                    'utm_campaign' => $pick('utm_campaign'),
                    'utm_term' => $pick('utm_term'),
                    'utm_content' => $pick('utm_content'),
                    'fbclid' => $pick('fbclid', 255),
                    'items_json' => $itemsJson,
                    'value_cents' => $valueCents,
                    'currency' => $currency,
                    'consent_snapshot' => $consentSnapshot,
                    'is_test' => !empty($e['is_test']) ? 1 : 0,
                ]);
                $n++;
            } catch (\Throwable $ex) {
            }
        }
        return $n;
    }

    /** Nombre GA4-compatible para el tipo interno. */
    private function eventName(string $type): string
    {
        static $map = [
            'page_view' => 'page_view',
            'product_view' => 'view_item',
            'view_category' => 'view_item_list',
            'select_item' => 'select_item',
            'cart_add' => 'add_to_cart',
            'remove_from_cart' => 'remove_from_cart',
            'view_cart' => 'view_cart',
            'checkout_start' => 'begin_checkout',
            'add_shipping_info' => 'add_shipping_info',
            'add_payment_info' => 'add_payment_info',
            'purchase' => 'purchase',
            'search' => 'search',
            'whatsapp_click' => 'whatsapp_click',
            'contact_click' => 'contact_click',
        ];
        return $map[$type] ?? $type;
    }

    /** Agregados para el dashboard SEO. */
    private function consentAllowed(): bool
    {
        $raw = (string)($_COOKIE['sonika_cookie_consent'] ?? '');
        if ($raw === 'all') return true;
        if ($raw === '' || $raw === 'necessary') return false;
        $data = json_decode($raw, true);
        return is_array($data) && !empty($data['analytics']);
    }

    public function stats(int $days = 7): array
    {
        if (!$this->ready()) return ['ready' => false];
        $days = max(1, min(365, $days));
        $db = Database::instance();
        $since = "created_at >= (NOW() - INTERVAL $days DAY)";
        $client = 'is_internal = 0 AND is_bot = 0';
        $internal = 'is_internal = 1 AND is_bot = 0';
        $scalar = function (string $sql) use ($db) { try { return $db->query($sql)->fetchColumn(); } catch (\Throwable $e) { return 0; } };
        $sessions = (int)$scalar("SELECT COUNT(DISTINCT session_id) FROM seo_tracking_events WHERE site_id = @site_id AND $client AND $since");
        $views = (int)$scalar("SELECT COUNT(*) FROM seo_tracking_events WHERE site_id = @site_id AND $client AND type='page_view' AND $since");
        $clicks = (int)$scalar("SELECT COUNT(*) FROM seo_tracking_events WHERE site_id = @site_id AND $client AND type IN ('link_click','cta_click','outbound') AND $since");
        $cta = (int)$scalar("SELECT COUNT(*) FROM seo_tracking_events WHERE site_id = @site_id AND $client AND type='cta_click' AND $since");
        $submits = (int)$scalar("SELECT COUNT(*) FROM seo_tracking_events WHERE site_id = @site_id AND $client AND type='form_submit' AND $since");
        $avgMs = (int)$scalar("SELECT AVG(duration_ms) FROM seo_tracking_events WHERE site_id = @site_id AND $client AND type='page_leave' AND duration_ms > 0 AND $since");
        $avgScroll = (int)$scalar("SELECT AVG(scroll_pct) FROM seo_tracking_events WHERE site_id = @site_id AND $client AND scroll_pct IS NOT NULL AND $since");
        $conv = $views > 0 ? round($submits / $views * 100, 1) : 0;

        $internalSessions = (int)$scalar("SELECT COUNT(DISTINCT session_id) FROM seo_tracking_events WHERE site_id = @site_id AND $internal AND $since");
        $internalViews = (int)$scalar("SELECT COUNT(*) FROM seo_tracking_events WHERE site_id = @site_id AND $internal AND type='page_view' AND $since");
        $internalClicks = (int)$scalar("SELECT COUNT(*) FROM seo_tracking_events WHERE site_id = @site_id AND $internal AND type IN ('link_click','cta_click','outbound') AND $since");
        $internalSubmits = (int)$scalar("SELECT COUNT(*) FROM seo_tracking_events WHERE site_id = @site_id AND $internal AND type='form_submit' AND $since");

        $group = function (string $col, string $filter = '') use ($db, $since) {
            $out = [];
            $where = $filter !== '' ? ' AND ' . $filter : '';
            try {
                foreach ($db->query("SELECT $col AS k, COUNT(*) c FROM seo_tracking_events WHERE site_id = @site_id AND $since$where AND $col IS NOT NULL AND $col <> '' GROUP BY $col ORDER BY c DESC LIMIT 8")->fetchAll() as $r) $out[] = ['k' => $r['k'], 'c' => (int)$r['c']];
            } catch (\Throwable $e) {}
            return $out;
        };

        $topLinks = [];
        try {
            foreach ($db->query("SELECT target, COUNT(*) c FROM seo_tracking_events WHERE site_id = @site_id AND $client AND type IN ('link_click','cta_click','outbound') AND target IS NOT NULL AND $since GROUP BY target ORDER BY c DESC LIMIT 10")->fetchAll() as $r) $topLinks[] = ['target' => $r['target'], 'c' => (int)$r['c']];
        } catch (\Throwable $e) {}

        $daily = [];
        try {
            foreach ($db->query("SELECT DATE(created_at) d, COUNT(DISTINCT session_id) s, SUM(type='page_view') v, SUM(type='form_submit') f FROM seo_tracking_events WHERE site_id = @site_id AND $client AND $since GROUP BY DATE(created_at) ORDER BY d")->fetchAll() as $r) {
                $daily[] = ['d' => $r['d'], 'sessions' => (int)$r['s'], 'views' => (int)$r['v'], 'submits' => (int)$r['f']];
            }
        } catch (\Throwable $e) {}

        return [
            'ready' => true, 'days' => $days,
            'sessions' => $sessions, 'views' => $views, 'clicks' => $clicks, 'cta' => $cta, 'submits' => $submits,
            'conversion' => $conv, 'avg_time_s' => (int)round($avgMs / 1000), 'avg_scroll' => $avgScroll,
            'internal' => [
                'sessions' => $internalSessions,
                'views' => $internalViews,
                'clicks' => $internalClicks,
                'submits' => $internalSubmits,
            ],
            'devices' => $group('device', $client), 'os' => $group('os', $client), 'browsers' => $group('browser', $client), 'countries' => $group('country', $client),
            'top_links' => $topLinks, 'daily' => $daily,
        ];
    }
}
