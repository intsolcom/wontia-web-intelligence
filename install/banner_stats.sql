-- ============================================================
-- WWI — BANNER STATS (impresiones y clics por aviso + A/B)
-- Multi-tenant (@site_id). Idempotente.
-- ============================================================

CREATE TABLE IF NOT EXISTS wwi_banner_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    banner_uid VARCHAR(64) NOT NULL,
    variant VARCHAR(8) NOT NULL DEFAULT 'a',
    impressions INT NOT NULL DEFAULT 0,
    clicks INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_bn (site_id, banner_uid, variant)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
