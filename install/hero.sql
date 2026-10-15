-- WWI — SONIKA HERO BUILDER (estructura base, multi-tenant, idempotente)
CREATE TABLE IF NOT EXISTS wwi_hero_slides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    uuid CHAR(36) NOT NULL,
    name VARCHAR(150) NOT NULL DEFAULT 'Slide',
    status ENUM('draft','active') NOT NULL DEFAULT 'draft',
    sort_order INT NOT NULL DEFAULT 0,
    layout VARCHAR(20) NOT NULL DEFAULT 'split',
    left_type VARCHAR(30) NOT NULL DEFAULT 'text',
    left_data JSON NULL,
    right_type VARCHAR(30) NOT NULL DEFAULT 'instrument',
    right_data JSON NULL,
    design JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY wwi_hero_uuid (site_id, uuid),
    KEY wwi_hero_sort (site_id, sort_order)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wwi_hero_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    slide_id INT NOT NULL,
    data JSON NULL,
    created_by VARCHAR(150) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY wwi_hero_ver_slide (site_id, slide_id)
) ENGINE=InnoDB;
