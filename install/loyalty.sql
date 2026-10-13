-- ============================================================
-- WWI — FIDELIDAD / PUNTOS (brick Mi Cuenta / Ecommerce)
-- Idempotente. Auto-migrado por App\Services\LoyaltyService::ensureTables().
-- ============================================================

ALTER TABLE store_customers ADD COLUMN IF NOT EXISTS points_balance INT NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS store_loyalty_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    customer_id INT NOT NULL,
    points INT NOT NULL,
    reason VARCHAR(40) NOT NULL,
    order_id INT NULL,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ledger (site_id, customer_id, reason, order_id),
    KEY idx_cust (site_id, customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
