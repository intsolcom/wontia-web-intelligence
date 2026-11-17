CREATE TABLE IF NOT EXISTS sistecredito_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    setting_key VARCHAR(100) NOT NULL,
    setting_value LONGTEXT NULL,
    is_secret TINYINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY site_setting (site_id, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_payment_intents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    order_id INT NOT NULL,
    provider VARCHAR(30) NOT NULL DEFAULT 'sistecredito',
    provider_payment_id VARCHAR(128) NULL,
    idempotency_key VARCHAR(128) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'created',
    amount_cents BIGINT NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'COP',
    payment_method_id INT NOT NULL DEFAULT 2,
    redirect_url TEXT NULL,
    expires_at DATETIME NULL,
    raw_create_response LONGTEXT NULL,
    raw_last_status LONGTEXT NULL,
    last_checked_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY site_provider_payment (site_id, provider, provider_payment_id),
    UNIQUE KEY site_order_intent (site_id, order_id, provider, idempotency_key),
    KEY idx_intents_order (site_id, order_id),
    KEY idx_intents_status (site_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_payment_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    intent_id INT NOT NULL,
    provider VARCHAR(30) NOT NULL DEFAULT 'sistecredito',
    provider_event_key VARCHAR(190) NULL,
    provider_status VARCHAR(60) NULL,
    verified_status VARCHAR(60) NULL,
    validation_result VARCHAR(30) NOT NULL DEFAULT 'pending',
    payload_sanitized LONGTEXT NULL,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    UNIQUE KEY site_provider_event (site_id, provider, provider_event_key),
    KEY idx_events_intent (site_id, intent_id, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
