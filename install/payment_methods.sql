-- ============================================================
-- WWI — MÉTODOS DE PAGO GUARDADOS DEL CLIENTE (Ecommerce)
-- Idempotente. Auto-migrado por App\Services\PaymentMethodService::ensureTables().
-- El token del proveedor se guarda CIFRADO (AES-256-GCM); nunca se expone.
-- ============================================================

CREATE TABLE IF NOT EXISTS store_customer_payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    customer_id INT NOT NULL,
    provider ENUM('wompi','cod','manual') NOT NULL DEFAULT 'cod',
    label VARCHAR(80) NULL,
    token_hash VARCHAR(255) NULL,
    last4 CHAR(4) NULL,
    brand VARCHAR(40) NULL,
    is_default TINYINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cust (site_id, customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
