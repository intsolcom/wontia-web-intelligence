<?php
namespace App\Services;

use App\Core\Database;

/**
 * SeoMetaAdsService — resultados reales de la pauta de Meta.
 *
 * Fuentes:
 *  - analytics_views: visitas de servidor (incluye fbclid/UTM) para el período.
 *  - seo_tracking_events: embudo y compras atribuidas con consentimiento.
 *  - seo_live_requests: comprobación de landings de los últimos 90 días.
 *
 * El tráfico interno (is_internal=1) y los bots quedan fuera de todas las
 * métricas de clientes. La pauta se reconoce por fbclid o por UTM de Meta.
 */
class SeoMetaAdsService
{
    private const META_SOURCES = "'facebook','meta','fb','instagram','ig','messenger','an','facebook_ads','instagram_ads','meta_ads'";
    private const PAID_MEDIUMS = "'paid','paid_social','paidsocial','social_paid','cpc','ppc','ads','ad','paid-social','paid social','social paid','meta_ads','meta ads','instagram_ads','facebook_ads'";

    /** @var array<string,bool> */
    private static array $metaPaidColumnCache = [];

    public function report(int $days = 30): array
    {
        $days = max(1, min(365, $days));
        $db = Database::instance();
        $since = 'created_at >= (NOW() - INTERVAL ' . $days . ' DAY)';
        $rows = static function (string $sql) use ($db) {
            try { return $db->query($sql)->fetchAll(); } catch (\Throwable $e) { return []; }
        };

        $paidA = $this->paidCondition('analytics_views');
        $paidE = $this->paidCondition('seo_tracking_events');
        $paidL = $this->paidCondition('seo_live_requests');

        // Una sola lectura de las vistas de pauta. El resto del reporte agrega
        // sobre esta tabla temporal (o sobre una subconsulta si el usuario de
        // base de datos no permite tablas temporales). Antes se escaneaba
        // analytics_views seis veces con una condición no indexable.
        $paidSelect = "SELECT visitor_hash, ip_hash, session_id, page_url,
            utm_campaign, utm_source, utm_medium, utm_content, utm_term, created_at
            FROM analytics_views
            WHERE site_id = @site_id AND is_internal = 0 AND is_bot = 0 AND $since AND $paidA";
        $paidFrom = '(' . $paidSelect . ') paid_views';
        $tmpReady = false;
        try {
            $db->exec('DROP TEMPORARY TABLE IF EXISTS tmp_seo_meta_paid');
            $db->exec('CREATE TEMPORARY TABLE tmp_seo_meta_paid AS ' . $paidSelect);
            $paidFrom = 'tmp_seo_meta_paid';
            $tmpReady = true;
        } catch (\Throwable $e) {
            // Fallback correcto aunque más lento: subconsulta derivada.
        }

        $meta = $rows("SELECT COUNT(*) visits,
            COUNT(DISTINCT COALESCE(NULLIF(visitor_hash, ''), ip_hash)) visitors,
            COUNT(DISTINCT COALESCE(NULLIF(session_id, ''), NULLIF(visitor_hash, ''), ip_hash)) sessions
            FROM $paidFrom")[0] ?? [];
        $total = $rows("SELECT COUNT(*) views
            FROM analytics_views WHERE site_id = @site_id AND is_internal = 0 AND is_bot = 0 AND $since")[0] ?? [];

        $funnel = $rows("SELECT
            COALESCE(SUM(type = 'product_view'), 0) product_views,
            COALESCE(SUM(type = 'cart_add'), 0) cart_adds,
            COALESCE(SUM(type = 'checkout_start'), 0) checkout_starts,
            COALESCE(SUM(type = 'purchase'), 0) purchases,
            COALESCE(SUM(CASE WHEN type = 'purchase' THEN CAST(label AS UNSIGNED) ELSE 0 END), 0) revenue_cents,
            COUNT(DISTINCT session_id) event_sessions,
            COUNT(DISTINCT visitor_hash) event_visitors,
            COUNT(*) event_count
            FROM seo_tracking_events WHERE site_id = @site_id AND is_internal = 0 AND is_bot = 0 AND $since AND $paidE")[0] ?? [];

        $campaigns = $rows("SELECT
            COALESCE(NULLIF(utm_campaign, ''), '(sin campaña)') campaign,
            COALESCE(NULLIF(utm_source, ''), '(sin fuente)') src,
            COALESCE(NULLIF(utm_medium, ''), '(sin medio)') medium,
            COALESCE(NULLIF(utm_content, ''), '') content,
            COALESCE(NULLIF(utm_term, ''), '') term,
            COUNT(*) visits,
            COUNT(DISTINCT COALESCE(NULLIF(visitor_hash, ''), ip_hash)) visitors,
            COUNT(DISTINCT COALESCE(NULLIF(session_id, ''), NULLIF(visitor_hash, ''), ip_hash)) sessions
            FROM $paidFrom
            GROUP BY campaign, src, medium, content, term
            ORDER BY visits DESC, visitors DESC LIMIT 50");

        $eventCampaigns = $rows("SELECT COALESCE(NULLIF(utm_campaign, ''), '(sin campaña)') campaign,
            COALESCE(NULLIF(utm_source, ''), '(sin fuente)') src,
            COALESCE(NULLIF(utm_medium, ''), '(sin medio)') medium,
            COUNT(DISTINCT session_id) sessions
            FROM seo_tracking_events
            WHERE site_id = @site_id AND is_internal = 0 AND is_bot = 0 AND $since AND $paidE
            GROUP BY campaign, src, medium");
        $eventSessionsByKey = [];
        foreach ($eventCampaigns as $ec) $eventSessionsByKey[(string)$ec['campaign'] . '|' . (string)$ec['src'] . '|' . (string)$ec['medium']] = (int)$ec['sessions'];

        $landing = $rows("SELECT SUBSTRING_INDEX(page_url, '?', 1) landing,
            COUNT(*) visits,
            COUNT(DISTINCT COALESCE(NULLIF(visitor_hash, ''), ip_hash)) visitors
            FROM $paidFrom
            GROUP BY landing ORDER BY visits DESC LIMIT 25");

        $daily = $rows("SELECT DATE(created_at) day,
            COUNT(*) visits,
            COUNT(DISTINCT COALESCE(NULLIF(visitor_hash, ''), ip_hash)) visitors
            FROM $paidFrom
            GROUP BY DATE(created_at) ORDER BY day ASC");

        $sources = $rows("SELECT COALESCE(NULLIF(utm_source, ''), '(sin fuente)') src,
            COALESCE(NULLIF(utm_medium, ''), '(sin medio)') medium,
            COUNT(*) visits,
            COUNT(DISTINCT COALESCE(NULLIF(visitor_hash, ''), ip_hash)) visitors
            FROM $paidFrom
            GROUP BY src, medium ORDER BY visits DESC LIMIT 15");

        $campaignFunnel = $rows("SELECT
            COALESCE(NULLIF(utm_campaign, ''), '(sin campaña)') campaign,
            COALESCE(SUM(type = 'cart_add'), 0) cart_adds,
            COALESCE(SUM(type = 'checkout_start'), 0) checkout_starts,
            COALESCE(SUM(type = 'purchase'), 0) purchases,
            COALESCE(SUM(CASE WHEN type = 'purchase' THEN CAST(label AS UNSIGNED) ELSE 0 END), 0) revenue_cents
            FROM seo_tracking_events
            WHERE site_id = @site_id AND is_internal = 0 AND is_bot = 0 AND $since AND $paidE
            GROUP BY campaign ORDER BY purchases DESC, cart_adds DESC LIMIT 25");

        $liveDays = min(90, $days);
        $liveSince = 'created_at >= (NOW() - INTERVAL ' . $liveDays . ' DAY)';
        $liveRows = $rows("SELECT visitor_hash
            FROM seo_live_requests
            WHERE site_id = @site_id AND is_internal = 0 AND is_bot = 0 AND is_page_view = 1 AND $liveSince AND $paidL");
        $liveVisitorSet = [];
        foreach ($liveRows as $__lr) {
            $__vh = trim((string)($__lr['visitor_hash'] ?? ''));
            if ($__vh !== '') $liveVisitorSet[$__vh] = true;
        }
        $live = ['landing_requests' => count($liveRows), 'visitors' => count($liveVisitorSet)];

        $orders = $rows("SELECT COUNT(*) total,
            COALESCE(SUM(payment_status = 'paid'), 0) paid,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_cents ELSE 0 END), 0) revenue_cents
            FROM store_orders WHERE site_id = @site_id AND is_internal = 0 AND $since")[0] ?? [];

        $ranges = [];
        foreach ([1, 7, 30] as $rangeDays) {
            $rangeSince = 'created_at >= (NOW() - INTERVAL ' . $rangeDays . ' DAY)';
            $v = $rows("SELECT COUNT(*) visits,
                COUNT(DISTINCT COALESCE(NULLIF(visitor_hash, ''), ip_hash)) visitors,
                COUNT(DISTINCT COALESCE(NULLIF(session_id, ''), NULLIF(visitor_hash, ''), ip_hash)) sessions
                FROM $paidFrom WHERE $rangeSince")[0] ?? [];
            $f = $rows("SELECT COALESCE(SUM(type = 'cart_add'), 0) cart_adds,
                COALESCE(SUM(type = 'checkout_start'), 0) checkout_starts,
                COALESCE(SUM(type = 'purchase'), 0) purchases,
                COALESCE(SUM(CASE WHEN type = 'purchase' THEN CAST(label AS UNSIGNED) ELSE 0 END), 0) revenue_cents,
                COUNT(DISTINCT session_id) event_sessions
                FROM seo_tracking_events WHERE site_id = @site_id AND is_internal = 0 AND is_bot = 0 AND $rangeSince AND $paidE")[0] ?? [];
            $eventSessions = (int)($f['event_sessions'] ?? 0);
            $ranges[] = [
                'days' => $rangeDays,
                'label' => $rangeDays === 1 ? '24 horas' : ($rangeDays . ' días'),
                'visits' => (int)($v['visits'] ?? 0),
                'visitors' => max((int)($v['visitors'] ?? 0), $eventSessions),
                'sessions' => max((int)($v['sessions'] ?? 0), $eventSessions),
                'event_sessions' => $eventSessions,
                'cart_adds' => (int)($f['cart_adds'] ?? 0),
                'checkout_starts' => (int)($f['checkout_starts'] ?? 0),
                'purchases' => (int)($f['purchases'] ?? 0),
                'revenue_cents' => (int)($f['revenue_cents'] ?? 0),
            ];
        }

        $visits = (int)($meta['visits'] ?? 0);
        $serverVisitors = (int)($meta['visitors'] ?? 0);
        $serverSessions = (int)($meta['sessions'] ?? 0);
        $eventSessions = (int)($funnel['event_sessions'] ?? 0);
        $eventCount = (int)($funnel['event_count'] ?? 0);
        $visitors = max($serverVisitors, $eventSessions);
        $sessions = max($serverSessions, $eventSessions);
        $totalViews = (int)($total['views'] ?? 0);
        $purchases = (int)($funnel['purchases'] ?? 0);
        $checkoutStarts = (int)($funnel['checkout_starts'] ?? 0);

        if ($tmpReady) {
            try { $db->exec('DROP TEMPORARY TABLE IF EXISTS tmp_seo_meta_paid'); } catch (\Throwable $e) {}
        }

        return [
            'ok' => true,
            'days' => $days,
            'has_data' => $visits > 0 || $eventCount > 0 || (int)($funnel['cart_adds'] ?? 0) > 0 || $purchases > 0,
            'summary' => [
                'visits' => $visits,
                'visitors' => $visitors,
                'sessions' => $sessions,
                'server_visitors' => $serverVisitors,
                'server_sessions' => $serverSessions,
                'event_sessions' => $eventSessions,
                'event_count' => (int)($funnel['event_count'] ?? 0),
                'landing_requests' => (int)($live['landing_requests'] ?? 0),
                'landing_visitors' => (int)($live['visitors'] ?? 0),
                'total_client_views' => $totalViews,
                'share_pct' => $totalViews > 0 ? round($visits / $totalViews * 100, 2) : 0,
                'product_views' => (int)($funnel['product_views'] ?? 0),
                'cart_adds' => (int)($funnel['cart_adds'] ?? 0),
                'checkout_starts' => $checkoutStarts,
                'purchases' => $purchases,
                'revenue_cents' => (int)($funnel['revenue_cents'] ?? 0),
                'conversion_pct' => $visits > 0 ? round($purchases / $visits * 100, 2) : 0,
                'checkout_rate_pct' => $visits > 0 ? round($checkoutStarts / $visits * 100, 2) : 0,
            ],
            'ranges' => $ranges,
            'campaigns' => array_map(static function ($r) use ($eventSessionsByKey) {
                $key = (string)$r['campaign'] . '|' . (string)$r['src'] . '|' . (string)$r['medium'];
                $eventSessions = (int)($eventSessionsByKey[$key] ?? 0);
                return [
                    'campaign' => (string)$r['campaign'],
                    'source' => (string)$r['src'],
                    'medium' => (string)$r['medium'],
                    'content' => (string)$r['content'],
                    'term' => (string)$r['term'],
                    'visits' => (int)$r['visits'],
                    'visitors' => max((int)$r['visitors'], $eventSessions),
                    'sessions' => max((int)$r['sessions'], $eventSessions),
                    'event_sessions' => $eventSessions,
                ];
            }, $campaigns),
            'landing' => array_map(static function ($r) {
                return [
                    'landing' => (string)$r['landing'],
                    'visits' => (int)$r['visits'],
                    'visitors' => (int)$r['visitors'],
                ];
            }, $landing),
            'daily' => array_map(static function ($r) {
                return ['day' => (string)$r['day'], 'visits' => (int)$r['visits'], 'visitors' => (int)$r['visitors']];
            }, $daily),
            'sources' => array_map(static function ($r) {
                return [
                    'source' => (string)$r['src'],
                    'medium' => (string)$r['medium'],
                    'visits' => (int)$r['visits'],
                    'visitors' => (int)$r['visitors'],
                ];
            }, $sources),
            'campaign_funnel' => array_map(static function ($r) {
                return [
                    'campaign' => (string)$r['campaign'],
                    'cart_adds' => (int)$r['cart_adds'],
                    'checkout_starts' => (int)$r['checkout_starts'],
                    'purchases' => (int)$r['purchases'],
                    'revenue_cents' => (int)$r['revenue_cents'],
                ];
            }, $campaignFunnel),
            'funnel' => [
                'product_views' => (int)($funnel['product_views'] ?? 0),
                'cart_adds' => (int)($funnel['cart_adds'] ?? 0),
                'checkout_starts' => $checkoutStarts,
                'purchases' => $purchases,
                'revenue_cents' => (int)($funnel['revenue_cents'] ?? 0),
            ],
            'orders_context' => [
                'total' => (int)($orders['total'] ?? 0),
                'paid' => (int)($orders['paid'] ?? 0),
                'revenue_cents' => (int)($orders['revenue_cents'] ?? 0),
            ],
            'notes' => [
                'Las visitas y visitantes se miden en el servidor y no dependen del consentimiento.',
                'Si el servidor no puede distinguir IPs históricas por un proxy, Visitantes/Sesiones toman también las sesiones del navegador para no subestimar la pauta.',
                'El embudo y las compras atribuidas usan eventos del navegador con consentimiento; si el cliente no acepta analítica, se muestra 0 en esas columnas.',
                'La pauta se reconoce por fbclid o por UTM de Meta (facebook, meta, fb, instagram, ig, messenger, an) con medio pagado. El social orgánico no se suma como pauta.',
                'Para ver el nombre de la campaña y del anuncio en el reporte, agrega utm_campaign y utm_content a la URL del anuncio. fbclid confirma el clic, pero no contiene el nombre de la campaña.',
                'Los pedidos pagados del período se muestran como contexto; no se atribuyen a Meta sin una señal de campaña.',
            ],
            'generated_at' => date('c'),
        ];
    }

    /**
     * Reconstruye UTM/fbclid desde las URLs históricas ya guardadas.
     * No inventa datos: solo extrae parámetros que existen en page_url o utm.
     */
    public function backfillAttribution(int $limit = 2000): array
    {
        $db = Database::instance();
        $limit = max(1, min(10000, $limit));
        $out = ['analytics_views' => 0, 'seo_tracking_events' => 0];

        try {
            $stmt = $db->prepare("SELECT id, page_url FROM analytics_views
                WHERE site_id = @site_id AND (utm_source IS NULL AND fbclid IS NULL)
                AND (page_url LIKE '%utm_source=%' OR page_url LIKE '%utm_medium=%' OR page_url LIKE '%utm_campaign=%' OR page_url LIKE '%utm_term=%' OR page_url LIKE '%utm_content=%' OR page_url LIKE '%fbclid=%')
                ORDER BY id DESC LIMIT " . $limit);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            $upd = $db->prepare("UPDATE analytics_views SET
                utm_source = :utm_source, utm_medium = :utm_medium, utm_campaign = :utm_campaign,
                utm_term = :utm_term, utm_content = :utm_content, fbclid = :fbclid,
                visitor_hash = COALESCE(NULLIF(visitor_hash, ''), ip_hash)
                WHERE id = :id AND site_id = @site_id");
            foreach ($rows as $row) {
                $data = $this->parseUrlParams((string)$row['page_url']);
                if (!$data['utm_source'] && !$data['utm_medium'] && !$data['utm_campaign'] && !$data['fbclid']) continue;
                $upd->execute([
                    'utm_source' => $data['utm_source'], 'utm_medium' => $data['utm_medium'],
                    'utm_campaign' => $data['utm_campaign'], 'utm_term' => $data['utm_term'],
                    'utm_content' => $data['utm_content'], 'fbclid' => $data['fbclid'],
                    'id' => (int)$row['id'],
                ]);
                $out['analytics_views']++;
            }
        } catch (\Throwable $e) {
        }

        try {
            $stmt = $db->prepare("SELECT id, page_url, utm FROM seo_tracking_events
                WHERE site_id = @site_id AND (utm_source IS NULL AND fbclid IS NULL)
                AND (utm IS NOT NULL OR page_url LIKE '%utm_source=%' OR page_url LIKE '%utm_medium=%' OR page_url LIKE '%utm_campaign=%' OR page_url LIKE '%utm_term=%' OR page_url LIKE '%utm_content=%' OR page_url LIKE '%fbclid=%')
                ORDER BY id DESC LIMIT " . $limit);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            $upd = $db->prepare("UPDATE seo_tracking_events SET
                utm_source = :utm_source, utm_medium = :utm_medium, utm_campaign = :utm_campaign,
                utm_term = :utm_term, utm_content = :utm_content, fbclid = :fbclid,
                landing_url = COALESCE(landing_url, :landing_url),
                visitor_hash = COALESCE(NULLIF(visitor_hash, ''), ip_hash)
                WHERE id = :id AND site_id = @site_id");
            foreach ($rows as $row) {
                $data = ['utm_source' => null, 'utm_medium' => null, 'utm_campaign' => null, 'utm_term' => null, 'utm_content' => null, 'fbclid' => null];
                $raw = json_decode((string)($row['utm'] ?? ''), true);
                if (is_array($raw)) {
                    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid'] as $k) {
                        if (!empty($raw[$k]) && is_scalar($raw[$k])) $data[$k] = mb_substr(trim((string)$raw[$k]), 0, 255);
                    }
                }
                $parsed = $this->parseUrlParams((string)$row['page_url']);
                foreach ($data as $k => $v) if (!$v && isset($parsed[$k])) $data[$k] = $parsed[$k];
                if (!$data['utm_source'] && !$data['utm_medium'] && !$data['utm_campaign'] && !$data['fbclid']) continue;
                $upd->execute([
                    'utm_source' => $data['utm_source'], 'utm_medium' => $data['utm_medium'],
                    'utm_campaign' => $data['utm_campaign'], 'utm_term' => $data['utm_term'],
                    'utm_content' => $data['utm_content'], 'fbclid' => $data['fbclid'],
                    'landing_url' => mb_substr((string)$row['page_url'], 0, 500),
                    'id' => (int)$row['id'],
                ]);
                $out['seo_tracking_events']++;
            }
        } catch (\Throwable $e) {
        }

        return ['ok' => true, 'updated' => $out, 'message' => 'Atribución histórica reconstruida desde las URLs guardadas.'];
    }

    private function paidCondition(string $table): string
    {
        if ($this->hasMetaPaidColumn($table)) return "($table.is_meta_paid = 1)";
        $source = "LOWER(COALESCE($table.utm_source, ''))";
        $medium = "LOWER(COALESCE($table.utm_medium, ''))";
        $fb = "($table.fbclid IS NOT NULL AND $table.fbclid <> '')";
        $meta = "($fb OR $source IN (" . self::META_SOURCES . ") OR ($source = '' AND $medium IN ('paid_social','paidsocial','social_paid')))";
        $paid = "($fb OR $medium IN (" . self::PAID_MEDIUMS . "))";
        return "($meta AND $paid)";
    }

    private function hasMetaPaidColumn(string $table): bool
    {
        if (array_key_exists($table, self::$metaPaidColumnCache)) return self::$metaPaidColumnCache[$table];
        try {
            $st = Database::instance()->query("SHOW COLUMNS FROM `$table` LIKE 'is_meta_paid'");
            return self::$metaPaidColumnCache[$table] = (bool)$st->fetch();
        } catch (\Throwable $e) {
            return self::$metaPaidColumnCache[$table] = false;
        }
    }

    private function parseUrlParams(string $url): array
    {
        $out = ['utm_source' => null, 'utm_medium' => null, 'utm_campaign' => null, 'utm_term' => null, 'utm_content' => null, 'fbclid' => null];
        $query = (string)parse_url($url, PHP_URL_QUERY);
        if ($query === '') {
            $pos = strpos($url, '?');
            if ($pos !== false) $query = substr($url, $pos + 1);
        }
        if ($query === '') return $out;
        parse_str($query, $params);
        foreach ($out as $key => $_) {
            if (isset($params[$key]) && is_scalar($params[$key])) {
                $value = trim((string)$params[$key]);
                if ($value !== '') $out[$key] = mb_substr($value, 0, $key === 'fbclid' ? 255 : 160);
            }
        }
        return $out;
    }
}
