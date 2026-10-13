-- ============================================================
-- WWI — DEVOLUCIONES / REEMBOLSOS (Ecommerce)
-- Idempotente. Auto-migrado por App\Services\ReturnService::ensureTables().
-- ============================================================

CREATE TABLE IF NOT EXISTS store_order_returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    order_id INT NOT NULL,
    customer_id INT NULL,
    customer_email VARCHAR(255) NULL,
    reason TEXT NULL,
    status ENUM('requested','approved','rejected','refunded') NOT NULL DEFAULT 'requested',
    refund_cents INT DEFAULT 0,
    restock TINYINT NOT NULL DEFAULT 0,
    admin_note VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    UNIQUE KEY uk_ret (site_id, order_id),
    KEY idx_status (site_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
