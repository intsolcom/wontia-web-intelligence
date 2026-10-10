-- ============================================================
-- WWI THEME MARKETPLACE + LICENCIAS PREMIUM
-- Idempotente. La app auto-migra vía ThemeService::ensureTables().
-- ============================================================

-- Seriales de instalación (GLOBAL: solo el servidor central los genera).
CREATE TABLE IF NOT EXISTS theme_serials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code_hash VARCHAR(255) NOT NULL,
    theme_slug VARCHAR(100) NOT NULL,
    label VARCHAR(120) NULL,
    tier VARCHAR(20) NOT NULL DEFAULT 'premium',
    created_by VARCHAR(120) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    bound_site_id INT NULL,
    bound_at DATETIME NULL,
    revoked_at DATETIME NULL,
    UNIQUE KEY uk_code (code_hash),
    KEY idx_slug (theme_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Licencias activas por sitio.
CREATE TABLE IF NOT EXISTS theme_licenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    theme_slug VARCHAR(100) NOT NULL,
    serial_id INT NULL,
    status ENUM('active','revoked') NOT NULL DEFAULT 'active',
    activated_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_site_theme (site_id, theme_slug),
    KEY idx_serial (serial_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Auditoría de acciones del mercado de temas.
CREATE TABLE IF NOT EXISTS theme_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NULL,
    theme_slug VARCHAR(100) NULL,
    event VARCHAR(48) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'ok',
    actor VARCHAR(120) NULL,
    meta JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
