-- ============================================================
-- WWI — RESEÑAS DE PRODUCTO (brick Mi Cuenta / Ecommerce)
-- Idempotente. Auto-migrado por App\Services\ReviewService::ensureTables().
-- ============================================================

CREATE TABLE IF NOT EXISTS store_product_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    product_ref VARCHAR(255) NOT NULL,
    customer_id INT NULL,
    author_name VARCHAR(120) NOT NULL,
    rating TINYINT NOT NULL DEFAULT 5,
    title VARCHAR(160) NULL,
    body TEXT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    verified_purchase TINYINT NOT NULL DEFAULT 0,
    helpful INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_prod (site_id, product_ref, status),
    KEY idx_cust (site_id, customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
