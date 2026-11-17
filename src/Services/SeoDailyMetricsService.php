<?php
namespace App\Services;

use App\Core\Database;

/**
 * SeoDailyMetricsService — agregados diarios para el dashboard ejecutivo.
 *
 * No reemplaza las tablas de eventos: las resume por día para que el Resumen
 * y la comparación de períodos no tengan que escanear millones de filas.
 * Fuentes:
 *   - seo_live_requests: visitas, visitantes, sesiones, 404 y latencia.
 *   - seo_tracking_events: embudo con consentimiento.
 *   - store_orders: pedidos pagados e ingresos.
 *   - analytics_views: respaldo y pauta Meta.
 */
class SeoDailyMetricsService
{
    private const SETTING_LAST_RUN = 'seo_daily_metrics_last_run';

    public function ensureTable(): void
    {
        Database::instance()->exec("CREATE TABLE IF NOT EXISTS seo_daily_metrics (
            site_id INT NOT NULL,
            metric_date DATE NOT NULL,
            client_views INT NOT NULL DEFAULT 0,
            client_visitors INT NOT NULL DEFAULT 0,
            client_sessions INT NOT NULL DEFAULT 0,
            analytics_views INT NOT NULL DEFAULT 0,
            consent_page_views INT NOT NULL DEFAULT 0,
            consent_sessions INT NOT NULL DEFAULT 0,
            internal_views INT NOT NULL DEFAULT 0,
            bot_views INT NOT NULL DEFAULT 0,
            product_views INT NOT NULL DEFAULT 0,
            cart_adds INT NOT NULL DEFAULT 0,
            checkout_starts INT NOT NULL DEFAULT 0,
            purchases INT NOT NULL DEFAULT 0,
            revenue_cents BIGINT NOT NULL DEFAULT 0,
            meta_visits INT NOT NULL DEFAULT 0,
            meta_visitors INT NOT NULL DEFAULT 0,
            orders_paid INT NOT NULL DEFAULT 0,
            orders_revenue_cents BIGINT NOT NULL DEFAULT 0,
            requests_404 INT NOT NULL DEFAULT 0,
            avg_latency_ms INT NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (site_id, metric_date),
            KEY idx_site_date (site_id, metric_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /** Reconstruye los últimos N días. Idempotente. */
    public function rebuild(int $days = 90): array
    {
        $this->ensureTable();
        \App\Services\SeoTrafficSchemaService::ensure();
        $days = max(1, min(365, $days));
        $db = Database::instance();
        $from = 'created_at >= (CURDATE() - INTERVAL ' . $days . ' DAY)';
        $byDate = [];

        $put = static function (array $rows, array $fields) use (&$byDate): void {
            foreach ($rows as $row) {
                $date = (string)($row['d'] ?? '');
                if ($date === '') continue;
                if (!isset($byDate[$date])) $byDate[$date] = [];
                foreach ($fields as $field) {
                    if (array_key_exists($field, $row)) $byDate[$date][$field] = (int)$row[$field];
                }
            }
        };

        try {
            $put($db->query("SELECT DATE(created_at) d,
                COUNT(*) client_views,
                COUNT(DISTINCT visitor_hash) client_visitors,
                COUNT(DISTINCT session_id) client_sessions,
                ROUND(AVG(duration_ms)) avg_latency_ms
                FROM seo_live_requests
                WHERE site_id = @site_id AND is_internal = 0 AND is_bot = 0 AND is_page_view = 1 AND $from
                GROUP BY DATE(created_at)")->fetchAll(\PDO::FETCH_ASSOC),
                ['client_views', 'client_visitors', 'client_sessions', 'avg_latency_ms']);
        } catch (\Throwable $e) {
        }

        try {
            $put($db->query("SELECT DATE(created_at) d, COUNT(*) requests_404
                FROM seo_live_requests
                WHERE site_id = @site_id AND is_internal = 0 AND is_bot = 0 AND status = 404 AND $from
                GROUP BY DATE(created_at)")->fetchAll(\PDO::FETCH_ASSOC),
                ['requests_404']);
        } catch (\Throwable $e) {
        }

        try {
            $put($db->query("SELECT DATE(created_at) d,
                SUM(is_internal = 1 AND is_bot = 0) internal_views,
                SUM(is_bot = 1) bot_views
                FROM seo_live_requests
                WHERE site_id = @site_id AND is_page_view = 1 AND $from
                GROUP BY DATE(created_at)")->fetchAll(\PDO::FETCH_ASSOC),
                ['internal_views', 'bot_views']);
        } catch (\Throwable $e) {
        }

        try {
            $put($db->query("SELECT DATE(created_at) d,
                COUNT(*) analytics_views
                FROM analytics_views
                WHERE site_id = @site_id AND is_internal = 0 AND is_bot = 0 AND $from
                GROUP BY DATE(created_at)")->fetchAll(\PDO::FETCH_ASSOC),
                ['analytics_views']);
        } catch (\Throwable $e) {
        }

        try {
            $put($db->query("SELECT DATE(created_at) d,
                SUM(type = 'page_view') consent_page_views,
                COUNT(DISTINCT session_id) consent_sessions,
                SUM(type = 'product_view') product_views,
                SUM(type = 'cart_add') cart_adds,
                SUM(type = 'checkout_start') checkout_starts,
                SUM(type = 'purchase') purchases,
                COALESCE(SUM(CASE WHEN type = 'purchase' THEN COALESCE(value_cents, CAST(label AS UNSIGNED), 0) ELSE 0 END), 0) revenue_cents
                FROM seo_tracking_events
                WHERE site_id = @site_id AND is_internal = 0 AND is_bot = 0 AND $from
                GROUP BY DATE(created_at)")->fetchAll(\PDO::FETCH_ASSOC),
                ['consent_page_views', 'consent_sessions', 'product_views', 'cart_adds', 'checkout_starts', 'purchases', 'revenue_cents']);
        } catch (\Throwable $e) {
        }

        try {
            $put($db->query("SELECT DATE(created_at) d,
                COUNT(*) meta_visits,
                COUNT(DISTINCT visitor_hash) meta_visitors
                FROM analytics_views
                WHERE site_id = @site_id AND is_internal = 0 AND is_bot = 0 AND is_meta_paid = 1 AND $from
                GROUP BY DATE(created_at)")->fetchAll(\PDO::FETCH_ASSOC),
                ['meta_visits', 'meta_visitors']);
        } catch (\Throwable $e) {
        }

        try {
            $put($db->query("SELECT DATE(created_at) d,
                COUNT(*) orders_paid,
                COALESCE(SUM(total_cents), 0) orders_revenue_cents
                FROM store_orders
                WHERE site_id = @site_id AND is_internal = 0 AND payment_status = 'paid' AND $from
                GROUP BY DATE(created_at)")->fetchAll(\PDO::FETCH_ASSOC),
                ['orders_paid', 'orders_revenue_cents']);
        } catch (\Throwable $e) {
        }

        $cols = ['client_views','client_visitors','client_sessions','analytics_views','consent_page_views','consent_sessions','internal_views','bot_views','product_views','cart_adds','checkout_starts','purchases','revenue_cents','meta_visits','meta_visitors','orders_paid','orders_revenue_cents','requests_404','avg_latency_ms'];
        $sql = 'INSERT INTO seo_daily_metrics (site_id, metric_date, ' . implode(', ', $cols) . ') VALUES (@site_id, ?, ' . implode(', ', array_fill(0, count($cols), '?')) . ')
            ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(static fn($c) => "`$c` = VALUES(`$c`)", $cols));
        $stmt = $db->prepare($sql);
        $written = 0;
        foreach ($byDate as $date => $values) {
            $row = [$date];
            foreach ($cols as $col) $row[] = (int)($values[$col] ?? 0);
            try {
                $stmt->execute($row);
                $written++;
            } catch (\Throwable $e) {
            }
        }

        try {
            $db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
                ->execute([self::SETTING_LAST_RUN, (string)time()]);
        } catch (\Throwable $e) {
        }

        return ['ok' => true, 'days' => $days, 'dates' => $written, 'from' => $from];
    }

    /** Reconstruye como máximo cada N minutos; pensado para job-runner. */
    public function rebuildIfDue(int $minutes = 10): array
    {
        $minutes = max(1, min(1440, $minutes));
        $last = 0;
        try {
            $st = Database::instance()->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = ? LIMIT 1");
            $st->execute([self::SETTING_LAST_RUN]);
            $last = (int)$st->fetchColumn();
        } catch (\Throwable $e) {
        }
        if ($last > 0 && (time() - $last) < $minutes * 60) {
            return ['ok' => true, 'cached' => true, 'last_run' => $last];
        }
        return $this->rebuild(7);
    }

    public function series(int $days = 30): array
    {
        $this->ensureTable();
        $days = max(1, min(365, $days));
        try {
            $st = Database::instance()->prepare("SELECT * FROM seo_daily_metrics WHERE site_id = @site_id AND metric_date >= (CURDATE() - INTERVAL $days DAY) ORDER BY metric_date ASC");
            $st->execute();
            return $st->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
