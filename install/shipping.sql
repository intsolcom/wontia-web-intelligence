CREATE TABLE IF NOT EXISTS shipping_departments (
    code CHAR(2) NOT NULL,
    name VARCHAR(100) NOT NULL,
    capital VARCHAR(150) NULL,
    PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shipping_municipalities (
    code CHAR(5) NOT NULL,
    department_code CHAR(2) NOT NULL,
    name VARCHAR(160) NOT NULL,
    province VARCHAR(120) NULL,
    is_capital TINYINT NOT NULL DEFAULT 0,
    PRIMARY KEY (code),
    KEY idx_shipping_municipality_department (department_code),
    KEY idx_shipping_municipality_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_shipping_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    name VARCHAR(150) NOT NULL,
    department_code CHAR(2) NULL,
    municipality_code CHAR(5) NULL,
    carrier VARCHAR(100) NULL,
    cost_cents INT NOT NULL DEFAULT 0,
    free_over_cents INT NULL DEFAULT NULL,
    eta_min_days INT NULL DEFAULT NULL,
    eta_max_days INT NULL DEFAULT NULL,
    weight_min_grams INT NOT NULL DEFAULT 0,
    weight_max_grams INT NULL DEFAULT NULL,
    markup_pct DECIMAL(6,2) NOT NULL DEFAULT 0,
    external_id VARCHAR(100) NULL,
    last_synced_at DATETIME NULL,
    sync_source VARCHAR(20) NULL,
    cod_enabled TINYINT NOT NULL DEFAULT 1,
    is_active TINYINT NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_shipping_rate_lookup (site_id, is_active, department_code, municipality_code),
    KEY idx_shipping_rate_municipality (site_id, municipality_code),
    KEY idx_shipping_rate_weight (site_id, weight_min_grams, weight_max_grams)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shipping_rate_sync_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    carrier VARCHAR(40) NOT NULL DEFAULT 'coordinadora',
    mode VARCHAR(20) NOT NULL DEFAULT 'sync',
    status VARCHAR(20) NOT NULL DEFAULT 'running',
    started_at DATETIME NOT NULL,
    finished_at DATETIME NULL,
    destinations INT NOT NULL DEFAULT 0,
    quotes INT NOT NULL DEFAULT 0,
    created_count INT NOT NULL DEFAULT 0,
    updated_count INT NOT NULL DEFAULT 0,
    unchanged_count INT NOT NULL DEFAULT 0,
    error_count INT NOT NULL DEFAULT 0,
    message VARCHAR(500) NULL,
    report_json LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sync_site (site_id, carrier, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shipping_rate_sync_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    run_id INT NOT NULL,
    carrier VARCHAR(40) NOT NULL DEFAULT 'coordinadora',
    scope VARCHAR(20) NOT NULL DEFAULT 'national',
    department_code CHAR(2) NULL,
    municipality_code CHAR(5) NULL,
    weight_min_grams INT NOT NULL DEFAULT 0,
    weight_max_grams INT NULL,
    quoted_base_cents BIGINT NOT NULL DEFAULT 0,
    quoted_cents BIGINT NOT NULL DEFAULT 0,
    previous_cents BIGINT NULL,
    applied_cents BIGINT NULL,
    difference_cents BIGINT NOT NULL DEFAULT 0,
    difference_pct DECIMAL(8,2) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'ok',
    message VARCHAR(300) NULL,
    raw_json LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sync_items_run (run_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
