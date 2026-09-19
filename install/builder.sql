-- WWI INLINE BUILDER — Fase 1: árbol de construcción (filas → columnas → slots → bloques)
-- Aditivo y compatible: las páginas sin filas siguen renderizando sus secciones como siempre.
-- Todas las tablas son multi-tenant con site_id = @site_id. Idempotente.

CREATE TABLE IF NOT EXISTS wwi_page_rows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    page_id INT NOT NULL,
    sort_order INT DEFAULT 0,
    layout JSON,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_rows_page (site_id, page_id, sort_order)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wwi_page_columns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    row_id INT NOT NULL,
    sort_order INT DEFAULT 0,
    span TINYINT DEFAULT 12,
    layout JSON,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_cols_row (site_id, row_id, sort_order)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wwi_page_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    column_id INT NOT NULL,
    sort_order INT DEFAULT 0,
    layout JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_slots_col (site_id, column_id, sort_order)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wwi_page_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    slot_id INT NOT NULL,
    sort_order INT DEFAULT 0,
    type VARCHAR(30) NOT NULL DEFAULT 'text',
    brick_slug VARCHAR(80) NULL,
    section_id INT NULL,
    props JSON,
    styles JSON,
    responsive JSON,
    visibility JSON,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_blocks_slot (site_id, slot_id, sort_order),
    KEY idx_blocks_section (site_id, section_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wwi_page_revisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    page_id INT NOT NULL,
    user_id INT NULL,
    username VARCHAR(120) NULL,
    label VARCHAR(200) NULL,
    tree JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rev_page (site_id, page_id, id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wwi_page_trash (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    page_id INT NOT NULL,
    node_type ENUM('row','column','slot','block') NOT NULL,
    node_id INT NOT NULL,
    payload JSON,
    deleted_by INT NULL,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_trash_page (site_id, page_id, deleted_at)
) ENGINE=InnoDB;
