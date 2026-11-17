-- WWI — Operación de pedidos: checklist, confirmación AIP, notificaciones, envíos y reseñas.
-- Multi-tenant, idempotente. No duplica store_orders: se enlaza por order_id.

CREATE TABLE IF NOT EXISTS oc_order_checklists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    order_id INT NOT NULL,
    payment_settled TINYINT NOT NULL DEFAULT 0,
    payment_settled_at DATETIME NULL,
    payment_settled_by INT NULL,
    payment_settlement_ref VARCHAR(190) NULL,
    customer_confirmed TINYINT NOT NULL DEFAULT 0,
    customer_confirmed_at DATETIME NULL,
    confirmation_call_id VARCHAR(100) NULL,
    confirmation_phrase VARCHAR(120) NULL,
    confirmation_confidence DECIMAL(5,4) NULL,
    reconfirmed TINYINT NOT NULL DEFAULT 0,
    reconfirmed_at DATETIME NULL,
    reconfirmed_source VARCHAR(40) NULL,
    provider_reported TINYINT NOT NULL DEFAULT 0,
    provider_reported_at DATETIME NULL,
    provider_id INT NULL,
    notes VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY oc_checklist_order (site_id, order_id),
    KEY oc_checklist_provider (site_id, provider_reported)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS oc_confirmations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    order_id INT NOT NULL,
    call_id VARCHAR(100) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    confirmed TINYINT NOT NULL DEFAULT 0,
    confirmation_phrase VARCHAR(120) NULL,
    confidence DECIMAL(5,4) NULL,
    transcript LONGTEXT NULL,
    recording_url VARCHAR(500) NULL,
    payload_json LONGTEXT NULL,
    attempted_at DATETIME NULL,
    confirmed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY oc_conf_order (site_id, order_id),
    KEY oc_conf_call (site_id, call_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS oc_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    order_id INT NOT NULL,
    event VARCHAR(40) NOT NULL,
    channel VARCHAR(20) NOT NULL,
    recipient VARCHAR(190) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'queued',
    template VARCHAR(100) NULL,
    provider VARCHAR(40) NULL,
    provider_message_id VARCHAR(190) NULL,
    subject VARCHAR(190) NULL,
    body LONGTEXT NULL,
    payload_json LONGTEXT NULL,
    error VARCHAR(500) NULL,
    scheduled_at DATETIME NULL,
    sent_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY oc_notif_order (site_id, order_id, event),
    KEY oc_notif_queue (site_id, status, scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS oc_shipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    order_id INT NOT NULL,
    partner_id INT NULL,
    carrier VARCHAR(100) NULL,
    tracking_number VARCHAR(190) NULL,
    tracking_url VARCHAR(500) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    shipped_at DATETIME NULL,
    delivered_at DATETIME NULL,
    evidence_json LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY oc_ship_order (site_id, order_id),
    KEY oc_ship_tracking (site_id, tracking_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS oc_review_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    order_id INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
    scheduled_at DATETIME NULL,
    sent_at DATETIME NULL,
    coupon_code VARCHAR(40) NULL,
    discount_pct DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    review_url VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY oc_review_order (site_id, order_id),
    KEY oc_review_queue (site_id, status, scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO oc_partners (site_id, name, type, active)
SELECT @site_id, 'Miche', 'supplier', 1
WHERE NOT EXISTS (
    SELECT 1 FROM oc_partners WHERE site_id = @site_id AND name = 'Miche'
);
