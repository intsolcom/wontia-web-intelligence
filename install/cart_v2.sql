-- ============================================================
-- WWI — CARRITO v2: cupones + persistencia por cuenta
-- Multi-tenant. Idempotente.
-- ============================================================

CREATE TABLE IF NOT EXISTS store_coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    code VARCHAR(40) NOT NULL,
    type ENUM('fixed','percent') NOT NULL DEFAULT 'percent',
    value INT NOT NULL DEFAULT 0,
    min_order_cents INT NOT NULL DEFAULT 0,
    max_uses INT NOT NULL DEFAULT 0,
    used INT NOT NULL DEFAULT 0,
    active TINYINT NOT NULL DEFAULT 1,
    expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_code (site_id, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS store_carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    customer_id INT NOT NULL,
    items JSON,
    coupon VARCHAR(40) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cart (site_id, customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
