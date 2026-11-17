-- SEO Global Launch 2.3 — aislamiento de tráfico propio y atribución Meta.
-- Ejecutar con MariaDB 10.5+ / MySQL 8+. La vía recomendada es:
--   php -r 'require "vendor/autoload.php"; App\Core\Config::load(); App\Services\SeoTrafficSchemaService::ensure(true);'
-- Este archivo documenta la migración y puede ejecutarse de forma idempotente.

CREATE TABLE IF NOT EXISTS seo_internal_networks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    cidr VARCHAR(64) NOT NULL,
    label VARCHAR(190) NULL,
    created_by VARCHAR(190) NULL,
    active TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uk_site_cidr (site_id, cidr),
    KEY idx_site_active (site_id, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS seo_internal_devices (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE seo_live_requests
    ADD COLUMN IF NOT EXISTS is_internal TINYINT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS internal_reason VARCHAR(40) NULL,
    ADD COLUMN IF NOT EXISTS device_id VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS session_id VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS visitor_hash VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS is_page_view TINYINT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS landing_url VARCHAR(500) NULL,
    ADD COLUMN IF NOT EXISTS query_string VARCHAR(500) NULL,
    ADD COLUMN IF NOT EXISTS utm_source VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS utm_medium VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS utm_campaign VARCHAR(160) NULL,
    ADD COLUMN IF NOT EXISTS utm_term VARCHAR(160) NULL,
    ADD COLUMN IF NOT EXISTS utm_content VARCHAR(160) NULL,
    ADD COLUMN IF NOT EXISTS fbclid VARCHAR(255) NULL;

CREATE INDEX IF NOT EXISTS idx_site_internal_created ON seo_live_requests (site_id, is_internal, created_at);
CREATE INDEX IF NOT EXISTS idx_site_pageview ON seo_live_requests (site_id, is_page_view, created_at);
CREATE INDEX IF NOT EXISTS idx_site_visitor ON seo_live_requests (site_id, visitor_hash);
CREATE INDEX IF NOT EXISTS idx_site_utm ON seo_live_requests (site_id, utm_source, created_at);

ALTER TABLE seo_tracking_events
    ADD COLUMN IF NOT EXISTS is_internal TINYINT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS internal_reason VARCHAR(40) NULL,
    ADD COLUMN IF NOT EXISTS device_id VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS visitor_hash VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS landing_url VARCHAR(500) NULL,
    ADD COLUMN IF NOT EXISTS utm_source VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS utm_medium VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS utm_campaign VARCHAR(160) NULL,
    ADD COLUMN IF NOT EXISTS utm_term VARCHAR(160) NULL,
    ADD COLUMN IF NOT EXISTS utm_content VARCHAR(160) NULL,
    ADD COLUMN IF NOT EXISTS fbclid VARCHAR(255) NULL;

CREATE INDEX IF NOT EXISTS idx_site_internal ON seo_tracking_events (site_id, is_internal, created_at);
CREATE INDEX IF NOT EXISTS idx_site_visitor ON seo_tracking_events (site_id, visitor_hash);
CREATE INDEX IF NOT EXISTS idx_site_utm ON seo_tracking_events (site_id, utm_source, created_at);

ALTER TABLE analytics_views
    ADD COLUMN IF NOT EXISTS session_id VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS visitor_hash VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS is_bot TINYINT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS utm_source VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS utm_medium VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS utm_campaign VARCHAR(160) NULL,
    ADD COLUMN IF NOT EXISTS utm_term VARCHAR(160) NULL,
    ADD COLUMN IF NOT EXISTS utm_content VARCHAR(160) NULL,
    ADD COLUMN IF NOT EXISTS fbclid VARCHAR(255) NULL;

CREATE INDEX IF NOT EXISTS idx_site_internal_bot ON analytics_views (site_id, is_internal, is_bot, created_at);
CREATE INDEX IF NOT EXISTS idx_site_utm ON analytics_views (site_id, utm_source, created_at);

ALTER TABLE store_orders
    ADD COLUMN IF NOT EXISTS is_internal TINYINT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS internal_reason VARCHAR(40) NULL;

CREATE INDEX IF NOT EXISTS idx_site_internal ON store_orders (site_id, is_internal, created_at);

-- v5 — Pauta Meta: columnas generadas para no repetir LOWER/COALESCE/OR
-- en cada consulta del reporte. El índice deja solo las filas pagadas.
ALTER TABLE analytics_views
    ADD COLUMN IF NOT EXISTS is_meta_paid TINYINT AS (
        CASE WHEN (
            (fbclid IS NOT NULL AND fbclid <> '') OR
            LOWER(COALESCE(utm_source,'')) IN ('facebook','meta','fb','instagram','ig','messenger','an','facebook_ads','instagram_ads','meta_ads') OR
            (LOWER(COALESCE(utm_source,'')) = '' AND LOWER(COALESCE(utm_medium,'')) IN ('paid_social','paidsocial','social_paid'))
        ) AND (
            (fbclid IS NOT NULL AND fbclid <> '') OR
            LOWER(COALESCE(utm_medium,'')) IN ('paid','paid_social','paidsocial','social_paid','cpc','ppc','ads','ad','paid-social','paid social','social paid','meta_ads','meta ads','instagram_ads','facebook_ads')
        ) THEN 1 ELSE 0 END
    ) VIRTUAL;
ALTER TABLE seo_tracking_events
    ADD COLUMN IF NOT EXISTS is_meta_paid TINYINT AS (
        CASE WHEN (
            (fbclid IS NOT NULL AND fbclid <> '') OR
            LOWER(COALESCE(utm_source,'')) IN ('facebook','meta','fb','instagram','ig','messenger','an','facebook_ads','instagram_ads','meta_ads') OR
            (LOWER(COALESCE(utm_source,'')) = '' AND LOWER(COALESCE(utm_medium,'')) IN ('paid_social','paidsocial','social_paid'))
        ) AND (
            (fbclid IS NOT NULL AND fbclid <> '') OR
            LOWER(COALESCE(utm_medium,'')) IN ('paid','paid_social','paidsocial','social_paid','cpc','ppc','ads','ad','paid-social','paid social','social paid','meta_ads','meta ads','instagram_ads','facebook_ads')
        ) THEN 1 ELSE 0 END
    ) VIRTUAL;
ALTER TABLE seo_live_requests
    ADD COLUMN IF NOT EXISTS is_meta_paid TINYINT AS (
        CASE WHEN (
            (fbclid IS NOT NULL AND fbclid <> '') OR
            LOWER(COALESCE(utm_source,'')) IN ('facebook','meta','fb','instagram','ig','messenger','an','facebook_ads','instagram_ads','meta_ads') OR
            (LOWER(COALESCE(utm_source,'')) = '' AND LOWER(COALESCE(utm_medium,'')) IN ('paid_social','paidsocial','social_paid'))
        ) AND (
            (fbclid IS NOT NULL AND fbclid <> '') OR
            LOWER(COALESCE(utm_medium,'')) IN ('paid','paid_social','paidsocial','social_paid','cpc','ppc','ads','ad','paid-social','paid social','social paid','meta_ads','meta ads','instagram_ads','facebook_ads')
        ) THEN 1 ELSE 0 END
    ) VIRTUAL;
CREATE INDEX IF NOT EXISTS idx_site_meta_paid ON analytics_views (site_id, is_meta_paid, created_at);
CREATE INDEX IF NOT EXISTS idx_site_meta_paid ON seo_tracking_events (site_id, is_meta_paid, created_at);
CREATE INDEX IF NOT EXISTS idx_site_meta_paid ON seo_live_requests (site_id, is_meta_paid, created_at);

-- Histórico: corrige las vistas antiguas insertadas como is_bot=0 con UA de bot.
-- Ejecutar con: SET @site_id = <id del sitio>;
UPDATE analytics_views SET is_bot = 1
WHERE site_id = @site_id AND is_bot = 0
  AND user_agent IS NOT NULL AND user_agent <> ''
  AND user_agent REGEXP 'bot|crawl|spider|slurp|bing|yandex|ahrefs|semrush|facebookexternalhit|preview|headless';

-- Migracion de slugs SEO (2026-11-17). Respaldo y trazabilidad.
CREATE TABLE IF NOT EXISTS seo_slug_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    product_id INT NOT NULL,
    old_slug VARCHAR(255) NOT NULL,
    new_slug VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'applied',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_site_product (site_id, product_id),
    KEY idx_site_old (site_id, old_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Correcciones de catalogo con antes/despues (taxonomia, tags, categoria).
CREATE TABLE IF NOT EXISTS seo_catalog_fixes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    product_id INT NOT NULL,
    fix_type VARCHAR(40) NOT NULL,
    before_json JSON NULL,
    after_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_site_product (site_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
