-- ============================================================
-- WWI — MI CUENTA (Ecommerce) — cuentas de cliente, direcciones y favoritos.
-- Idempotente. Auto-migrado por App\Services\CustomerService::ensureTables().
-- ============================================================

ALTER TABLE store_customers ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL;
ALTER TABLE store_customers ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL;
ALTER TABLE store_customers ADD COLUMN IF NOT EXISTS last_login DATETIME NULL;

CREATE TABLE IF NOT EXISTS store_customer_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    customer_id INT NOT NULL,
    label VARCHAR(60) NULL,
    name VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    line1 VARCHAR(255) NULL,
    line2 VARCHAR(255) NULL,
    city VARCHAR(120) NULL,
    region VARCHAR(120) NULL,
    postal VARCHAR(20) NULL,
    country VARCHAR(80) DEFAULT 'CO',
    is_default TINYINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cust (site_id, customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS store_customer_favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    customer_id INT NOT NULL,
    product_ref VARCHAR(255) NOT NULL,
    title VARCHAR(255) NULL,
    image VARCHAR(500) NULL,
    price_cents INT DEFAULT 0,
    url VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_fav (site_id, customer_id, product_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
