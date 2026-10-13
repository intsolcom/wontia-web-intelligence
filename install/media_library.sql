-- ============================================================
-- WWI — MEDIA LIBRARY 2.0 (carpetas, metadatos, optimización)
-- Idempotente. Auto-migrado por App\Services\MediaService::ensureTables().
-- ============================================================

ALTER TABLE media ADD COLUMN IF NOT EXISTS folder_id INT NULL;
ALTER TABLE media ADD COLUMN IF NOT EXISTS original_name VARCHAR(255) NULL;
ALTER TABLE media ADD COLUMN IF NOT EXISTS original_size INT NULL;
ALTER TABLE media ADD COLUMN IF NOT EXISTS original_ext VARCHAR(10) NULL;
ALTER TABLE media ADD COLUMN IF NOT EXISTS converted_size INT NULL;
ALTER TABLE media ADD COLUMN IF NOT EXISTS converted_ext VARCHAR(10) NULL;
ALTER TABLE media ADD COLUMN IF NOT EXISTS variants JSON NULL;
ALTER TABLE media ADD COLUMN IF NOT EXISTS hash CHAR(40) NULL;
ALTER TABLE media ADD COLUMN IF NOT EXISTS kind VARCHAR(12) NULL;
ALTER TABLE media ADD COLUMN IF NOT EXISTS is_brand TINYINT NOT NULL DEFAULT 0;
ALTER TABLE media ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS media_folders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    parent_id INT NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL,
    kind VARCHAR(12) NOT NULL DEFAULT 'mixed',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_folder (site_id, slug),
    KEY idx_parent (site_id, parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
