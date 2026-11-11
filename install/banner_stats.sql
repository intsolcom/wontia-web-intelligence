-- ============================================================
-- WWI — BANNER STATS (impresiones, clics, salida, cierres y recuperación + A/B)
-- Multi-tenant (@site_id). Idempotente.
-- ============================================================

CREATE TABLE IF NOT EXISTS wwi_banner_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    banner_uid VARCHAR(64) NOT NULL,
    variant VARCHAR(8) NOT NULL DEFAULT 'a',
    impressions INT NOT NULL DEFAULT 0,
    clicks INT NOT NULL DEFAULT 0,
    exit_intents INT NOT NULL DEFAULT 0,
    behavior_triggers INT NOT NULL DEFAULT 0,
    dismissals INT NOT NULL DEFAULT 0,
    recovery_clicks INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_bn (site_id, banner_uid, variant)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE wwi_banner_stats ADD COLUMN IF NOT EXISTS exit_intents INT NOT NULL DEFAULT 0;
ALTER TABLE wwi_banner_stats ADD COLUMN IF NOT EXISTS behavior_triggers INT NOT NULL DEFAULT 0;
ALTER TABLE wwi_banner_stats ADD COLUMN IF NOT EXISTS dismissals INT NOT NULL DEFAULT 0;
ALTER TABLE wwi_banner_stats ADD COLUMN IF NOT EXISTS recovery_clicks INT NOT NULL DEFAULT 0;
