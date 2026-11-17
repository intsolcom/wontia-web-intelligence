<?php
namespace App\Services;

use App\Core\Database;

/**
 * SeoTrafficSchemaService — migración idempotente de las tablas de tráfico.
 *
 * Añade a la analítica existente los campos necesarios para separar tráfico
 * interno (yo) del tráfico de clientes y para atribuir campañas de Meta.
 * No elimina ni reescribe datos: solo crea tablas/columnas e índices que falten.
 */
class SeoTrafficSchemaService
{
    public const VERSION = 6;
    private const SETTING = 'seo_traffic_schema_version';

    private static bool $ensured = false;

    public static function ensure(bool $force = false): array
    {
        if (self::$ensured && !$force) {
            return ['ok' => true, 'applied' => [], 'cached' => true];
        }
        $db = Database::instance();
        if (!$force) {
            try {
                $stmt = $db->prepare('SELECT `value` FROM settings WHERE `key` = ? AND site_id = @site_id LIMIT 1');
                $stmt->execute([self::SETTING]);
                if ((int)$stmt->fetchColumn() >= self::VERSION) {
                    self::$ensured = true;
                    return ['ok' => true, 'applied' => [], 'cached' => true];
                }
            } catch (\Throwable $e) {
            }
        }

        $applied = [];
        $failed = [];

        $exec = static function (string $sql, string $label) use ($db, &$applied, &$failed): void {
            try {
                $db->exec($sql);
                $applied[] = $label;
            } catch (\Throwable $e) {
                $failed[] = $label . ': ' . $e->getMessage();
            }
        };

        $exec("CREATE TABLE IF NOT EXISTS seo_live_requests (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            request_id VARCHAR(80) NULL,
            method VARCHAR(10) NOT NULL,
            path VARCHAR(500) NOT NULL,
            protocol VARCHAR(10) DEFAULT 'HTTP/1.1',
            status SMALLINT NOT NULL DEFAULT 0,
            duration_ms INT NOT NULL DEFAULT 0,
            ip VARCHAR(64) NULL,
            country CHAR(2) NULL,
            city VARCHAR(120) NULL,
            region VARCHAR(120) NULL,
            device VARCHAR(20) NULL,
            os VARCHAR(40) NULL,
            browser VARCHAR(40) NULL,
            referrer VARCHAR(500) NULL,
            user_agent VARCHAR(500) NULL,
            is_bot TINYINT NOT NULL DEFAULT 0,
            is_internal TINYINT NOT NULL DEFAULT 0,
            internal_reason VARCHAR(40) NULL,
            device_id VARCHAR(64) NULL,
            session_id VARCHAR(64) NULL,
            visitor_hash VARCHAR(64) NULL,
            is_page_view TINYINT NOT NULL DEFAULT 0,
            landing_url VARCHAR(500) NULL,
            query_string VARCHAR(500) NULL,
            utm_source VARCHAR(120) NULL,
            utm_medium VARCHAR(120) NULL,
            utm_campaign VARCHAR(160) NULL,
            utm_term VARCHAR(160) NULL,
            utm_content VARCHAR(160) NULL,
            fbclid VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_site_created (site_id, created_at),
            KEY idx_site_status (site_id, status),
            KEY idx_site_internal_created (site_id, is_internal, created_at),
            KEY idx_site_pageview (site_id, is_page_view, created_at),
            KEY idx_site_visitor (site_id, visitor_hash),
            KEY idx_site_utm (site_id, utm_source, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'table:seo_live_requests');

        $exec("CREATE TABLE IF NOT EXISTS seo_internal_networks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            cidr VARCHAR(64) NOT NULL,
            label VARCHAR(190) NULL,
            created_by VARCHAR(190) NULL,
            active TINYINT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uk_site_cidr (site_id, cidr),
            KEY idx_site_active (site_id, active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'table:seo_internal_networks');

        $exec("CREATE TABLE IF NOT EXISTS seo_internal_devices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL DEFAULT 1,
            device_id VARCHAR(64) NOT NULL,
            label VARCHAR(190) NULL,
            user_agent VARCHAR(500) NULL,
            created_by VARCHAR(190) NULL,
            last_seen_at DATETIME NULL,
            revoked_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uk_site_device (site_id, device_id),
            KEY idx_site_active (site_id, revoked_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'table:seo_internal_devices');

        // Seo tracking events: crear si el sitio aún no la tiene.
        $exec("CREATE TABLE IF NOT EXISTS seo_tracking_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL,
            session_id VARCHAR(64) NOT NULL,
            type VARCHAR(32) NOT NULL,
            page_url VARCHAR(500) NOT NULL DEFAULT '',
            target VARCHAR(500) DEFAULT NULL,
            label VARCHAR(255) DEFAULT NULL,
            referrer VARCHAR(500) DEFAULT NULL,
            utm JSON DEFAULT NULL,
            duration_ms INT DEFAULT NULL,
            scroll_pct TINYINT DEFAULT NULL,
            device VARCHAR(16) DEFAULT NULL,
            os VARCHAR(32) DEFAULT NULL,
            browser VARCHAR(32) DEFAULT NULL,
            country CHAR(2) DEFAULT NULL,
            ip_hash CHAR(64) DEFAULT NULL,
            is_bot TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_site_type (site_id, type),
            INDEX idx_site_created (site_id, created_at),
            INDEX idx_session (site_id, session_id),
            INDEX idx_target (site_id, target(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'table:seo_tracking_events');

        $columns = [
            'seo_live_requests' => [
                'is_internal' => "TINYINT NOT NULL DEFAULT 0",
                'internal_reason' => "VARCHAR(40) NULL",
                'device_id' => "VARCHAR(64) NULL",
                'session_id' => "VARCHAR(64) NULL",
                'visitor_hash' => "VARCHAR(64) NULL",
                'is_page_view' => "TINYINT NOT NULL DEFAULT 0",
                'landing_url' => "VARCHAR(500) NULL",
                'query_string' => "VARCHAR(500) NULL",
                'utm_source' => "VARCHAR(120) NULL",
                'utm_medium' => "VARCHAR(120) NULL",
                'utm_campaign' => "VARCHAR(160) NULL",
                'utm_term' => "VARCHAR(160) NULL",
                'utm_content' => "VARCHAR(160) NULL",
                'fbclid' => "VARCHAR(255) NULL",
            ],
            'seo_tracking_events' => [
                'is_internal' => "TINYINT NOT NULL DEFAULT 0",
                'internal_reason' => "VARCHAR(40) NULL",
                'device_id' => "VARCHAR(64) NULL",
                'visitor_hash' => "VARCHAR(64) NULL",
                'landing_url' => "VARCHAR(500) NULL",
                'utm_source' => "VARCHAR(120) NULL",
                'utm_medium' => "VARCHAR(120) NULL",
                'utm_campaign' => "VARCHAR(160) NULL",
                'utm_term' => "VARCHAR(160) NULL",
                'utm_content' => "VARCHAR(160) NULL",
                'fbclid' => "VARCHAR(255) NULL",
                'event_id' => "VARCHAR(80) NULL",
                'event_name' => "VARCHAR(40) NULL",
                'items_json' => "JSON NULL",
                'value_cents' => "BIGINT NULL",
                'currency' => "CHAR(3) NULL",
                'consent_snapshot' => "VARCHAR(20) NULL",
                'is_test' => "TINYINT NOT NULL DEFAULT 0",
            ],
            'analytics_views' => [
                'session_id' => "VARCHAR(64) NULL",
                'visitor_hash' => "VARCHAR(64) NULL",
                'is_bot' => "TINYINT NOT NULL DEFAULT 0",
                'utm_source' => "VARCHAR(120) NULL",
                'utm_medium' => "VARCHAR(120) NULL",
                'utm_campaign' => "VARCHAR(160) NULL",
                'utm_term' => "VARCHAR(160) NULL",
                'utm_content' => "VARCHAR(160) NULL",
                'fbclid' => "VARCHAR(255) NULL",
            ],
            'store_orders' => [
                'is_internal' => "TINYINT NOT NULL DEFAULT 0",
                'internal_reason' => "VARCHAR(40) NULL",
            ],
        ];

        foreach ($columns as $table => $cols) {
            if (!self::tableExists($db, $table)) continue;
            foreach ($cols as $column => $definition) {
                $exec("ALTER TABLE `$table` ADD COLUMN IF NOT EXISTS `$column` $definition", "column:$table.$column");
            }
        }

        $indexes = [
            ['seo_live_requests', 'idx_site_internal_created', '(site_id, is_internal, created_at)'],
            ['seo_live_requests', 'idx_site_pageview', '(site_id, is_page_view, created_at)'],
            ['seo_live_requests', 'idx_site_visitor', '(site_id, visitor_hash)'],
            ['seo_live_requests', 'idx_site_utm', '(site_id, utm_source, created_at)'],
            ['seo_tracking_events', 'idx_site_internal', '(site_id, is_internal, created_at)'],
            ['seo_tracking_events', 'idx_site_visitor', '(site_id, visitor_hash)'],
            ['seo_tracking_events', 'idx_site_utm', '(site_id, utm_source, created_at)'],
            ['seo_tracking_events', 'idx_site_event_name', '(site_id, event_name, created_at)'],
            ['analytics_views', 'idx_site_internal_bot', '(site_id, is_internal, is_bot, created_at)'],
            ['analytics_views', 'idx_site_utm', '(site_id, utm_source, created_at)'],
            ['store_orders', 'idx_site_internal', '(site_id, is_internal, created_at)'],
        ];
        foreach ($indexes as [$table, $name, $definition]) {
            if (!self::tableExists($db, $table)) continue;
            if (self::indexExists($db, $table, $name) === false) {
                $exec("ALTER TABLE `$table` ADD INDEX `$name` $definition", "index:$table.$name");
            }
        }

        // Deduplicación de eventos del navegador: un event_id solo puede existir
        // una vez por sitio. Los registros históricos tienen event_id NULL y no
        // colisionan porque el índice único permite múltiples NULL.
        if (self::tableExists($db, 'seo_tracking_events') && self::indexExists($db, 'seo_tracking_events', 'uq_site_event_id') === false) {
            $exec("ALTER TABLE `seo_tracking_events` ADD UNIQUE INDEX `uq_site_event_id` (`site_id`, `event_id`)", 'index:seo_tracking_events.uq_site_event_id');
        }

        // Columna generada y virtual para reconocer pauta de Meta sin repetir
        // LOWER/COALESCE/OR en cada consulta. El índice permite que el reporte
        // de Pauta Meta lea solo las filas pagadas en vez de escanear la tabla.
        $metaPaidExpr = <<<'SQL'
CASE WHEN (
    (fbclid IS NOT NULL AND fbclid <> '') OR
    LOWER(COALESCE(utm_source,'')) IN ('facebook','meta','fb','instagram','ig','messenger','an','facebook_ads','instagram_ads','meta_ads') OR
    (LOWER(COALESCE(utm_source,'')) = '' AND LOWER(COALESCE(utm_medium,'')) IN ('paid_social','paidsocial','social_paid'))
) AND (
    (fbclid IS NOT NULL AND fbclid <> '') OR
    LOWER(COALESCE(utm_medium,'')) IN ('paid','paid_social','paidsocial','social_paid','cpc','ppc','ads','ad','paid-social','paid social','social paid','meta_ads','meta ads','instagram_ads','facebook_ads')
) THEN 1 ELSE 0 END
SQL;
        foreach (['analytics_views', 'seo_tracking_events', 'seo_live_requests'] as $table) {
            if (!self::tableExists($db, $table)) continue;
            $exec("ALTER TABLE `$table` ADD COLUMN IF NOT EXISTS `is_meta_paid` TINYINT AS ($metaPaidExpr) VIRTUAL", "column:$table.is_meta_paid");
            if (self::indexExists($db, $table, 'idx_site_meta_paid') === false) {
                $exec("ALTER TABLE `$table` ADD INDEX `idx_site_meta_paid` (site_id, is_meta_paid, created_at)", "index:$table.idx_site_meta_paid");
            }
        }

        // Histórico: las vistas antiguas se insertaron con is_bot=0 aunque el
        // user-agent fuera de un bot. Se corrige una sola vez para que el
        // reporte pueda confiar en is_bot y no repetir REGEXP en cada consulta.
        $exec("UPDATE analytics_views SET is_bot = 1
            WHERE site_id = @site_id AND is_bot = 0
              AND user_agent IS NOT NULL AND user_agent <> ''
              AND user_agent REGEXP 'bot|crawl|spider|slurp|bing|yandex|ahrefs|semrush|facebookexternalhit|preview|headless'",
            'backfill:analytics_views.is_bot');

        if (!$failed) {
            try {
                $db->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
                    ->execute([self::SETTING, (string)self::VERSION]);
            } catch (\Throwable $e) {
            }
            self::$ensured = true;
        }

        return [
            'ok' => !$failed,
            'applied' => $applied,
            'failed' => $failed,
            'cached' => false,
        ];
    }

    private static function tableExists(\PDO $db, string $table): bool
    {
        try {
            $db->query('SELECT 1 FROM `' . $table . '` LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function indexExists(\PDO $db, string $table, string $name): ?bool
    {
        try {
            $stmt = $db->query('SHOW INDEX FROM `' . $table . '` WHERE Key_name = ' . $db->quote($name));
            return (bool)$stmt->fetch();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
