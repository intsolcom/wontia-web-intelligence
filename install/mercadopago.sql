-- Mercado Pago Brick: pagos, webhooks y sesiones OAuth
CREATE TABLE IF NOT EXISTS mercadopago_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    order_id INT NULL,
    order_uuid CHAR(36) NULL,
    order_number VARCHAR(40) NULL,
    environment VARCHAR(20) NOT NULL DEFAULT 'sandbox',
    mode VARCHAR(20) NOT NULL DEFAULT 'checkout_pro',
    preference_id VARCHAR(120) NULL,
    payment_id VARCHAR(120) NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'created',
    status_detail VARCHAR(160) NULL,
    payment_method_id VARCHAR(60) NULL,
    payment_type_id VARCHAR(40) NULL,
    installments INT NOT NULL DEFAULT 1,
    amount_cents BIGINT NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'COP',
    payer_email VARCHAR(190) NULL,
    external_reference VARCHAR(120) NULL,
    init_point TEXT NULL,
    raw_create LONGTEXT NULL,
    raw_last LONGTEXT NULL,
    last_synced_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY mp_site_order (site_id, order_uuid),
    UNIQUE KEY mp_site_payment (site_id, payment_id),
    KEY idx_mp_status (site_id, status, created_at),
    KEY idx_mp_preference (site_id, preference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mercadopago_webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    event_key VARCHAR(190) NOT NULL,
    topic VARCHAR(40) NULL,
    resource_id VARCHAR(120) NULL,
    signature_valid TINYINT NULL,
    processed TINYINT NOT NULL DEFAULT 0,
    result VARCHAR(80) NULL,
    payload LONGTEXT NULL,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    UNIQUE KEY mp_site_event (site_id, event_key),
    KEY idx_mp_hook_topic (site_id, topic, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mercadopago_oauth (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    state VARCHAR(120) NOT NULL,
    code_verifier VARCHAR(190) NULL,
    redirect_uri VARCHAR(500) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY mp_site_state (site_id, state),
    KEY idx_mp_oauth_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
