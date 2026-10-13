-- ============================================================
-- WWI — BRICK SHOWCASE (capturas del brick en funcionamiento)
-- Catálogo GLOBAL (sin site_id): es el showcase del Marketplace.
-- Idempotente. Auto-migrado por App\Services\BrickShowcaseService::ensureTables().
-- ============================================================

CREATE TABLE IF NOT EXISTS brick_showcase (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(120) NOT NULL,
    kind VARCHAR(20) NOT NULL DEFAULT 'widget',
    media VARCHAR(500) NOT NULL,
    media_type ENUM('image','video') NOT NULL DEFAULT 'image',
    device ENUM('all','desktop','tablet','mobile') NOT NULL DEFAULT 'all',
    caption VARCHAR(255) NULL,
    alt VARCHAR(255) NULL,
    demo_url VARCHAR(500) NULL,
    availability ENUM('available','in-development','future','concept','prototype') NULL,
    is_cover TINYINT NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    version VARCHAR(40) NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_slug (slug, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
