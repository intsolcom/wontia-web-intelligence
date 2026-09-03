-- WWI FACTORY — Fase 0: fundación de datos (planes, órdenes, pagos, provisioning, dominios,
-- email, plantillas, auditoría TIA, snapshots). Reutiliza settings para el Configuration Engine.
-- Todas las tablas son multi-tenant con site_id = @site_id. Nothing hardcoded.

CREATE TABLE IF NOT EXISTS wwi_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    slug VARCHAR(50) NOT NULL,
    name_es VARCHAR(150) NOT NULL,
    name_en VARCHAR(150) NOT NULL,
    description_es VARCHAR(500),
    description_en VARCHAR(500),
    price_cop DECIMAL(12,2) NOT NULL DEFAULT 0,
    price_usd DECIMAL(12,2) NOT NULL DEFAULT 0,
    billing_type ENUM('one_time','monthly','annual') DEFAULT 'one_time',
    duration_months INT DEFAULT 0,
    features JSON,
    limits JSON,
    margin_cost_items JSON,
    min_margin_pct DECIMAL(6,2) DEFAULT 25.00,
    sort_order INT DEFAULT 0,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY site_plan (site_id, slug)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wwi_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    uuid CHAR(36) NOT NULL,
    customer_name VARCHAR(255),
    customer_email VARCHAR(255),
    customer_phone VARCHAR(50),
    plan_id INT,
    domain_name VARCHAR(255),
    status ENUM('CREATED','PENDING_PAYMENT','PAID','PROVISIONING','READY','FAILED','CANCELLED','REFUNDED') DEFAULT 'CREATED',
    subtotal DECIMAL(12,2) DEFAULT 0,
    tax DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) DEFAULT 0,
    currency VARCHAR(5) DEFAULT 'COP',
    locale VARCHAR(5) DEFAULT 'es',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY site_order_uuid (site_id, uuid),
    KEY idx_orders_status (site_id, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wwi_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    order_id INT,
    provider VARCHAR(30) NOT NULL,
    provider_ref VARCHAR(255),
    amount DECIMAL(12,2) DEFAULT 0,
    currency VARCHAR(5) DEFAULT 'COP',
    status ENUM('pending','approved','declined','refunded','failed') DEFAULT 'pending',
    signature_verified TINYINT DEFAULT 0,
    raw_webhook TEXT,
    verified_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY site_provider_ref (site_id, provider, provider_ref),
    KEY idx_payments_order (site_id, order_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wwi_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    order_id INT,
    plan_id INT,
    status ENUM('active','past_due','cancelled','expired') DEFAULT 'active',
    started_at DATETIME,
    ends_at DATETIME,
    renews_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_subs_site (site_id, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wwi_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    order_id INT,
    number VARCHAR(50),
    amount DECIMAL(12,2) DEFAULT 0,
    currency VARCHAR(5) DEFAULT 'COP',
    status ENUM('issued','paid','void','overdue') DEFAULT 'issued',
    issued_at DATETIME,
    due_at DATETIME,
    paid_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY site_invoice (site_id, number)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wwi_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    type VARCHAR(50) NOT NULL,
    status ENUM('queued','running','done','failed','retrying') DEFAULT 'queued',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    payload JSON,
    error VARCHAR(1000),
    scheduled_at DATETIME,
    next_retry_at DATETIME,
    locked_at DATETIME,
    completed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_jobs_due (status, scheduled_at),
    KEY idx_jobs_site (site_id, type)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wwi_provisioning_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    order_id INT,
    job_id INT,
    step VARCHAR(50) NOT NULL,
    status ENUM('queued','running','done','failed') DEFAULT 'queued',
    payload JSON,
    error VARCHAR(1000),
    started_at DATETIME,
    completed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_prov_site (site_id, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wwi_domains (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    name VARCHAR(255) NOT NULL,
    tld VARCHAR(20),
    status ENUM('SEARCH','AVAILABLE','REGISTERING','REGISTERED','DNS_PENDING','ACTIVE','EXPIRING','EXPIRED') DEFAULT 'AVAILABLE',
    provider VARCHAR(50),
    provider_ref VARCHAR(255),
    registration_cost DECIMAL(12,2) DEFAULT 0,
    renewal_cost DECIMAL(12,2) DEFAULT 0,
    currency VARCHAR(5) DEFAULT 'USD',
    registered_at DATETIME,
    expires_at DATETIME,
    dns_config JSON,
    ssl_status ENUM('none','pending','active','error') DEFAULT 'none',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY site_domain (site_id, name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wwi_email_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    domain_id INT,
    mailbox VARCHAR(150) NOT NULL,
    display_name VARCHAR(255),
    status ENUM('REQUESTED','PROVISIONING','ACTIVE','SUSPENDED','DELETED') DEFAULT 'REQUESTED',
    provider VARCHAR(50),
    provider_ref VARCHAR(255),
    password_hash VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY site_mailbox (site_id, mailbox)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wwi_template_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    slug VARCHAR(50) NOT NULL,
    name_es VARCHAR(150) NOT NULL,
    name_en VARCHAR(150) NOT NULL,
    icon VARCHAR(10) DEFAULT 'W',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY site_tplcat (site_id, slug)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wwi_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    category_id INT,
    slug VARCHAR(80) NOT NULL,
    name_es VARCHAR(150) NOT NULL,
    name_en VARCHAR(150) NOT NULL,
    description_es VARCHAR(500),
    description_en VARCHAR(500),
    preview_url VARCHAR(500),
    preset JSON,
    status ENUM('active','beta','coming_soon','deprecated') DEFAULT 'active',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY site_template (site_id, slug)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wwi_ai_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    user_id INT,
    session_id VARCHAR(64),
    command TEXT,
    action VARCHAR(100),
    target_entity VARCHAR(50),
    target_id INT,
    payload JSON,
    result JSON,
    model VARCHAR(150),
    provider VARCHAR(100),
    cost DECIMAL(12,6) DEFAULT 0,
    tokens INT DEFAULT 0,
    status ENUM('executed','preview','cancelled','failed') DEFAULT 'executed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_aia_site (site_id, created_at),
    KEY idx_aia_session (session_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wwi_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    user_id INT,
    entity VARCHAR(50),
    entity_id INT,
    action VARCHAR(100),
    before_json JSON,
    after_json JSON,
    ip VARCHAR(64),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_site (site_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wwi_site_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    label VARCHAR(100),
    snapshot JSON,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_versions_site (site_id, id)
) ENGINE=InnoDB;

-- ── SEED: Planes (bilingüe, precios editables; margin_cost_items incluye dominio .com
-- con tarifa mayorista post 1-nov-2026 US$10.97/año como referencia NO hardcodeada) ──

INSERT IGNORE INTO wwi_plans (site_id, slug, name_es, name_en, description_es, description_en, price_cop, price_usd, billing_type, duration_months, features, limits, margin_cost_items, min_margin_pct, sort_order) VALUES
(1, 'web-starter', 'Web Starter', 'Web Starter', 'Sitio web informativo con dominio, hosting, SSL, 3 correos, SEO básico y TIA integrada.', 'Informational website with domain, hosting, SSL, 3 mailboxes, basic SEO and integrated TIA.', 299000, 75, 'one_time', 12,
 '["domain_1y","hosting","ssl","email_3","responsive","seo_basic","social_links","whatsapp","contact_form","google_maps","analytics","favicon","sitemap","robots","404","editor","tia_agent","backup"]',
 '{"sites":1,"mailboxes":3,"storage_mb":1024,"ai_monthly_usd":5,"products":0}',
 '[{"item":"domain","usd":10.97},{"item":"email","usd":12},{"item":"hosting","usd":24},{"item":"ai","usd":5},{"item":"payment_fee","pct":3.5},{"item":"support","usd":10}]',
 25, 1),
(1, 'web-business', 'Web Business', 'Web Business', 'Todo Web Starter + secciones avanzadas, blog y SEO optimizado por TIA.', 'Everything in Web Starter + advanced sections, blog and TIA-optimized SEO.', 449000, 115, 'one_time', 12,
 '["web_starter_all","blog","seo_advanced","analytics_bi","extra_sections","ai_seo_agent"]',
 '{"sites":1,"mailboxes":5,"storage_mb":5120,"ai_monthly_usd":15,"products":0}',
 '[{"item":"domain","usd":10.97},{"item":"email","usd":18},{"item":"hosting","usd":24},{"item":"ai","usd":15},{"item":"payment_fee","pct":3.5},{"item":"support","usd":15}]',
 25, 2),
(1, 'web-catalog', 'Web Catalog', 'Web Catalog', 'Catálogo de productos con hasta 15 productos, carga asistida y optimización de imágenes.', 'Product catalog with up to 15 products, assisted loading and image optimization.', 599000, 150, 'one_time', 12,
 '["web_business_all","catalog","product_pages","image_optimization","catalog_import"]',
 '{"sites":1,"mailboxes":5,"storage_mb":10240,"ai_monthly_usd":25,"products":15}',
 '[{"item":"domain","usd":10.97},{"item":"email","usd":18},{"item":"hosting","usd":30},{"item":"ai","usd":25},{"item":"payment_fee","pct":3.5},{"item":"support","usd":20}]',
 25, 3),
(1, 'web-catalog-pro', 'Web Catalog Pro', 'Web Catalog Pro', 'Catálogo ampliado. Precio variable según productos y almacenamiento.', 'Expanded catalog. Variable price based on products and storage.', 0, 0, 'one_time', 12,
 '["web_catalog_all","variable_pricing","bulk_import"]',
 '{"sites":1,"mailboxes":10,"storage_mb":51200,"ai_monthly_usd":50,"products":250}',
 '[{"item":"domain","usd":10.97},{"item":"email","usd":30},{"item":"hosting","usd":60},{"item":"ai","usd":50},{"item":"payment_fee","pct":3.5},{"item":"support","usd":30}]',
 30, 4),
(1, 'web-master', 'Web Master', 'Web Master', 'Suscripción mensual: mantenimiento, mejoras continuas y soporte TIA prioritario.', 'Monthly subscription: maintenance, continuous improvements and priority TIA support.', 149000, 38, 'monthly', 0,
 '["everything","monthly_improvements","priority_tia","dedicated_support"]',
 '{"sites":1,"mailboxes":25,"storage_mb":102400,"ai_monthly_usd":100,"products":0}',
 '[{"item":"email","usd":30},{"item":"hosting","usd":40},{"item":"ai","usd":100},{"item":"payment_fee","pct":3.5},{"item":"support","usd":40}]',
 30, 5);

-- ── SEED: Config del motor (precios de servicios y reglas comerciales — editables vía settings) ──

INSERT IGNORE INTO settings (site_id, `key`, `value`) VALUES
(1, 'wwi.product_load_0_15_cop', '99000'),
(1, 'wwi.product_load_16_30_cop', '149000'),
(1, 'wwi.product_load_31_50_cop', '249000'),
(1, 'wwi.domain_default_tld', '.com'),
(1, 'wwi.domain_cost_usd', '10.97'),
(1, 'wwi.domain_renewal_usd', '10.97'),
(1, 'wwi.margin_guard_enabled', '1'),
(1, 'wwi.min_margin_pct', '25'),
(1, 'wwi.site_promise_hours', '24'),
(1, 'wwi.locales', '["es","en"]');

-- ── SEED: Categorías de plantillas (bilingüe; las 100 plantillas se derivan en Fase 2) ──

INSERT IGNORE INTO wwi_template_categories (site_id, slug, name_es, name_en, sort_order) VALUES
(1, 'restaurants', 'Restaurantes', 'Restaurants', 1),
(1, 'legal', 'Abogados', 'Lawyers', 2),
(1, 'medical', 'Medicos y Odontologia', 'Medical & Dental', 3),
(1, 'real-estate', 'Inmobiliarias', 'Real Estate', 4),
(1, 'construction', 'Construccion y Arquitectura', 'Construction & Architecture', 5),
(1, 'tourism', 'Hoteles y Turismo', 'Hotels & Tourism', 6),
(1, 'transport', 'Transporte y Logistica', 'Transport & Logistics', 7),
(1, 'beauty', 'Belleza y Barberia', 'Beauty & Barbershops', 8),
(1, 'fitness', 'Gimnasios', 'Fitness', 9),
(1, 'education', 'Educacion', 'Education', 10),
(1, 'consulting', 'Consultores y Coaches', 'Consultants & Coaches', 11),
(1, 'agencies', 'Agencias y BPO', 'Agencies & BPO', 12),
(1, 'tech', 'Tecnologia y Software', 'Tech & Software', 13),
(1, 'retail', 'Comercio y Tiendas', 'Retail & Stores', 14),
(1, 'automotive', 'Talleres y Automotores', 'Workshops & Automotive', 15),
(1, 'fashion', 'Moda', 'Fashion', 16),
(1, 'agriculture', 'Agricultura y Ganaderia', 'Agriculture & Livestock', 17),
(1, 'pets', 'Veterinarias y Mascotas', 'Vets & Pets', 18),
(1, 'events', 'Eventos y Bodas', 'Events & Weddings', 19),
(1, 'cafes', 'Cafeterias y Panaderias', 'Cafes & Bakeries', 20);

-- ── Fase 0.5: Portal (sitios con lifecycle, rol client, órdenes por tenant, saldos) ──

ALTER TABLE sites ADD COLUMN IF NOT EXISTS plan_id INT NULL;
ALTER TABLE sites ADD COLUMN IF NOT EXISTS status VARCHAR(30) DEFAULT 'DRAFT';
ALTER TABLE sites ADD COLUMN IF NOT EXISTS uuid CHAR(36) NULL;
ALTER TABLE sites ADD COLUMN IF NOT EXISTS owner_user_id INT NULL;
ALTER TABLE users MODIFY role ENUM('superadmin','admin','editor','client') DEFAULT 'admin';
ALTER TABLE wwi_orders ADD COLUMN IF NOT EXISTS tenant_id INT NULL;

CREATE TABLE IF NOT EXISTS wwi_balance_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    direction ENUM('credit','debit') NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    reason VARCHAR(200),
    ref VARCHAR(100),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ledger_site (site_id, created_at)
) ENGINE=InnoDB;
