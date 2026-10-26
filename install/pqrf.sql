-- PQRF F1 — esquema base (idempotente)
-- Ejecutar una vez por base de datos. Las columnas se añaden con IF NOT EXISTS (MariaDB 10.11).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS pqrf_cases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    case_number VARCHAR(32) NOT NULL,
    customer_id INT NULL,
    type VARCHAR(20) NOT NULL,
    category VARCHAR(60) NULL,
    subject VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    order_ref VARCHAR(60) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'RECEIVED',
    priority VARCHAR(20) NOT NULL DEFAULT 'NORMAL',
    assignee_id INT NULL,
    tracking_token_hash CHAR(64) NOT NULL,
    tracking_token_expires_at DATETIME NULL,
    idempotency_key VARCHAR(64) NULL,
    contact_name VARCHAR(120) NOT NULL,
    contact_email VARCHAR(190) NOT NULL,
    contact_phone VARCHAR(40) NULL,
    auth_accepted TINYINT(1) NOT NULL DEFAULT 0,
    due_at DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pqrf_number (case_number),
    KEY idx_pqrf_site_status (site_id, status),
    KEY idx_pqrf_customer (customer_id),
    KEY idx_pqrf_due (due_at),
    KEY idx_pqrf_token (site_id, tracking_token_hash),
    UNIQUE KEY uq_pqrf_idem (site_id, idempotency_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS pqrf_case_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    actor_type VARCHAR(20) NOT NULL DEFAULT 'SYSTEM',
    actor_id INT NULL,
    event VARCHAR(40) NOT NULL,
    from_status VARCHAR(30) NULL,
    to_status VARCHAR(30) NULL,
    note TEXT NULL,
    visible_to_customer TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pqrf_ev_case (case_id, created_at),
    CONSTRAINT fk_pqrf_ev_case FOREIGN KEY (case_id) REFERENCES pqrf_cases (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS pqrf_case_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(190) NOT NULL,
    storage_path VARCHAR(255) NOT NULL,
    mime VARCHAR(100) NOT NULL,
    bytes INT UNSIGNED NOT NULL,
    sha256 CHAR(64) NULL,
    visibility VARCHAR(10) NOT NULL DEFAULT 'private',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pqrf_att_case (case_id),
    CONSTRAINT fk_pqrf_att_case FOREIGN KEY (case_id) REFERENCES pqrf_cases (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE pqrf_cases ADD COLUMN IF NOT EXISTS tracking_token_expires_at DATETIME NULL AFTER tracking_token_hash;
ALTER TABLE pqrf_cases ADD COLUMN IF NOT EXISTS idempotency_key VARCHAR(64) NULL AFTER tracking_token_expires_at;
