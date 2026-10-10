-- ============================================================
-- WWI CMS CONTRACT — Navegación administrable (multi-tenant)
-- Idempotente. Auto-migrado por App\Services\NavService::ensureTables().
-- ============================================================

CREATE TABLE IF NOT EXISTS nav_menus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    location VARCHAR(50) NOT NULL,
    name VARCHAR(120) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_site_loc (site_id, location)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nav_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    menu_id INT NOT NULL,
    parent_id INT NULL,
    label VARCHAR(160) NOT NULL,
    url VARCHAR(500) NOT NULL DEFAULT '#',
    sort_order INT NOT NULL DEFAULT 0,
    is_visible TINYINT NOT NULL DEFAULT 1,
    target VARCHAR(20) NOT NULL DEFAULT '_self',
    KEY idx_menu (menu_id, sort_order),
    KEY idx_site (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
