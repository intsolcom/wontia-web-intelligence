-- ============================================================
-- WWI — CARRITO v3: reserva de inventario + recuperación de abandonados
-- Multi-tenant. Idempotente.
-- ============================================================

CREATE TABLE IF NOT EXISTS store_cart_reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    token VARCHAR(64) NOT NULL,
    product_id INT NOT NULL,
    variant_id INT NULL,
    qty INT NOT NULL DEFAULT 1,
    status ENUM('active','released','expired','consumed') NOT NULL DEFAULT 'active',
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tok (site_id, token, status),
    KEY idx_exp (site_id, status, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
