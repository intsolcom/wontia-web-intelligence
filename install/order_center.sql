-- WWI — Central de pedidos (estructura base, multi-tenant, idempotente)
-- Fase 1: solo se crean las estructuras. NO se insertan datos de demostración.

CREATE TABLE IF NOT EXISTS oc_partners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    name VARCHAR(190) NOT NULL,
    type VARCHAR(30) NOT NULL DEFAULT 'logistics',
    contact_email VARCHAR(190) NULL,
    contact_phone VARCHAR(50) NULL,
    active TINYINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY oc_partners_site (site_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS oc_cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    uuid CHAR(36) NOT NULL,
    order_id INT NULL,
    order_number VARCHAR(30) NULL,
    reference VARCHAR(60) NULL,
    customer_name VARCHAR(255) NULL,
    customer_phone VARCHAR(50) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'received',
    priority VARCHAR(15) NOT NULL DEFAULT 'normal',
    owner_org VARCHAR(20) NOT NULL DEFAULT 'sonika',
    assignee_user_id INT NULL,
    partner_id INT NULL,
    opened_at DATETIME NULL,
    closed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY oc_cases_uuid (site_id, uuid),
    KEY oc_cases_order (site_id, order_id),
    KEY oc_cases_status (site_id, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS oc_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    case_id INT NOT NULL,
    type VARCHAR(40) NOT NULL,
    title VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    priority VARCHAR(15) NOT NULL DEFAULT 'normal',
    owner_org VARCHAR(20) NOT NULL DEFAULT 'sonika',
    assignee_user_id INT NULL,
    assignee_partner_id INT NULL,
    due_at DATETIME NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    KEY oc_tasks_case (site_id, case_id),
    KEY oc_tasks_status (site_id, status),
    KEY oc_tasks_due (site_id, due_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS oc_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    case_id INT NOT NULL,
    task_id INT NULL,
    type VARCHAR(60) NOT NULL,
    source VARCHAR(30) NOT NULL DEFAULT 'sonika',
    actor_type VARCHAR(20) NULL,
    actor_id INT NULL,
    payload JSON NULL,
    occurred_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY oc_events_case (site_id, case_id),
    KEY oc_events_type (site_id, type)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS oc_handoffs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    case_id INT NOT NULL,
    task_id INT NULL,
    from_org VARCHAR(20) NOT NULL,
    to_org VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'sent',
    sent_at DATETIME NULL,
    available_at DATETIME NULL,
    accepted_at DATETIME NULL,
    returned_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY oc_handoffs_case (site_id, case_id),
    KEY oc_handoffs_task (site_id, task_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS oc_evidence (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    case_id INT NOT NULL,
    task_id INT NULL,
    category VARCHAR(40) NOT NULL DEFAULT 'general',
    stage VARCHAR(40) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    review_notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY oc_evidence_case (site_id, case_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS oc_evidence_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    evidence_id INT NOT NULL,
    original_name VARCHAR(255) NULL,
    mime VARCHAR(100) NULL,
    size INT NULL,
    sha256 CHAR(64) NULL,
    storage_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY oc_evidence_files_ev (site_id, evidence_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS oc_incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    case_id INT NOT NULL,
    task_id INT NULL,
    type VARCHAR(40) NOT NULL,
    severity VARCHAR(15) NOT NULL DEFAULT 'medium',
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    opened_by INT NULL,
    opened_at DATETIME NULL,
    resolved_at DATETIME NULL,
    resolution TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY oc_incidents_case (site_id, case_id),
    KEY oc_incidents_status (site_id, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS oc_sla_policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    task_type VARCHAR(40) NOT NULL,
    target_minutes INT NOT NULL DEFAULT 0,
    warn_percent INT NOT NULL DEFAULT 80,
    escalate_minutes INT NOT NULL DEFAULT 0,
    enabled TINYINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY oc_sla_policies_type (site_id, task_type)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS oc_sla_clocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    task_id INT NOT NULL,
    owner_org VARCHAR(20) NOT NULL DEFAULT 'sonika',
    started_at DATETIME NULL,
    due_at DATETIME NULL,
    paused_at DATETIME NULL,
    paused_ms BIGINT NOT NULL DEFAULT 0,
    pause_reason VARCHAR(255) NULL,
    stopped_at DATETIME NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'running',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY oc_sla_clocks_task (site_id, task_id),
    KEY oc_sla_clocks_status (site_id, status)
) ENGINE=InnoDB;
