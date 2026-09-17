-- WWI STORE — Ecommerce por tenant (Fase 3)
-- Todas las tablas multi-tenant con site_id = @site_id. Idempotente.

CREATE TABLE IF NOT EXISTS store_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL,
    description VARCHAR(500),
    image VARCHAR(500),
    sort_order INT DEFAULT 0,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY site_store_cat_slug (site_id, slug)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS store_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    category_id INT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT,
    short_description VARCHAR(500),
    price_cents INT NOT NULL DEFAULT 0,
    compare_price_cents INT DEFAULT 0,
    currency VARCHAR(5) DEFAULT 'COP',
    sku VARCHAR(80),
    stock INT DEFAULT 0,
    track_stock TINYINT DEFAULT 1,
    weight_grams INT DEFAULT 0,
    images JSON,
    tags JSON,
    status ENUM('draft','active','archived') DEFAULT 'draft',
    is_featured TINYINT DEFAULT 0,
    seo_title VARCHAR(255),
    seo_description VARCHAR(500),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY site_store_prod_slug (site_id, slug),
    KEY idx_store_prod_status (site_id, status),
    KEY idx_store_prod_cat (site_id, category_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS store_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    product_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    options JSON,
    price_cents INT DEFAULT 0,
    stock INT DEFAULT 0,
    sku VARCHAR(80),
    sort_order INT DEFAULT 0,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_store_var_prod (site_id, product_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS store_customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    name VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    document VARCHAR(50),
    address JSON,
    notes TEXT,
    orders_count INT DEFAULT 0,
    total_spent_cents INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_store_cust_email (site_id, email),
    KEY idx_store_cust_phone (site_id, phone)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS store_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    uuid CHAR(36) NOT NULL,
    order_number VARCHAR(30),
    customer_id INT NULL,
    customer_name VARCHAR(255),
    customer_email VARCHAR(255),
    customer_phone VARCHAR(50),
    customer_document VARCHAR(50),
    shipping_address JSON,
    shipping_zone_id INT NULL,
    subtotal_cents INT DEFAULT 0,
    shipping_cents INT DEFAULT 0,
    discount_cents INT DEFAULT 0,
    total_cents INT DEFAULT 0,
    currency VARCHAR(5) DEFAULT 'COP',
    payment_method VARCHAR(30) DEFAULT 'cod',
    payment_status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
    fulfillment_status ENUM('new','confirmed','preparing','shipped','delivered','cancelled') DEFAULT 'new',
    provider_ref VARCHAR(255),
    notes TEXT,
    admin_notes TEXT,
    ip_hash VARCHAR(64) NULL,
    stock_released TINYINT DEFAULT 0,
    paid_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY site_store_order_uuid (site_id, uuid),
    KEY idx_store_orders_payment (site_id, payment_status),
    KEY idx_store_orders_fulfillment (site_id, fulfillment_status),
    KEY idx_store_orders_email (site_id, customer_email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS store_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    order_id INT NOT NULL,
    product_id INT NULL,
    variant_id INT NULL,
    name VARCHAR(255),
    variant_name VARCHAR(150),
    price_cents INT DEFAULT 0,
    qty INT DEFAULT 1,
    subtotal_cents INT DEFAULT 0,
    image VARCHAR(500),
    KEY idx_store_items_order (site_id, order_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS store_shipping_zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    name VARCHAR(150) NOT NULL,
    regions VARCHAR(500),
    cost_cents INT DEFAULT 0,
    free_over_cents INT DEFAULT 0,
    eta_days VARCHAR(50),
    is_active TINYINT DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_store_zones_active (site_id, is_active)
) ENGINE=InnoDB;
