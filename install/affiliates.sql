-- SONIKA Artist Partners — Fase 1 (modelo de datos, modo simulación). Idempotente.
CREATE TABLE IF NOT EXISTS affiliate_profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  customer_id INT NULL,
  email VARCHAR(190) NOT NULL,
  full_name VARCHAR(190) NOT NULL DEFAULT '',
  doc_type VARCHAR(16) NOT NULL DEFAULT '',
  doc_number VARCHAR(60) NOT NULL DEFAULT '',
  public_name VARCHAR(120) NOT NULL DEFAULT '',
  artist_name VARCHAR(120) NOT NULL DEFAULT '',
  slug VARCHAR(120) NOT NULL DEFAULT '',
  status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
  music_experience VARCHAR(60) DEFAULT NULL,
  music_instrument VARCHAR(60) DEFAULT NULL,
  music_level VARCHAR(40) DEFAULT NULL,
  music_genre VARCHAR(60) DEFAULT NULL,
  music_desired VARCHAR(60) DEFAULT NULL,
  music_role VARCHAR(60) DEFAULT NULL,
  socials JSON NULL,
  contract_version VARCHAR(24) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_site_email (site_id, email),
  UNIQUE KEY uq_site_slug (site_id, slug),
  INDEX idx_site_status (site_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliate_stores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  affiliate_id INT NOT NULL,
  slug VARCHAR(120) NOT NULL,
  bio TEXT NULL,
  image VARCHAR(500) DEFAULT NULL,
  theme JSON NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_site_slug (site_id, slug),
  INDEX idx_site_aff (site_id, affiliate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliate_collections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  affiliate_id INT NOT NULL,
  store_id INT NULL,
  name VARCHAR(120) NOT NULL DEFAULT '',
  slug VARCHAR(120) NOT NULL DEFAULT '',
  products JSON NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_site_aff (site_id, affiliate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliate_links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  affiliate_id INT NOT NULL,
  code VARCHAR(64) NOT NULL,
  target_url VARCHAR(500) NOT NULL DEFAULT '/',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_code (site_id, code),
  INDEX idx_aff (affiliate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliate_clicks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  affiliate_id INT NOT NULL,
  code VARCHAR(64) DEFAULT NULL,
  session_id VARCHAR(64) DEFAULT NULL,
  page_url VARCHAR(500) DEFAULT NULL,
  referrer VARCHAR(500) DEFAULT NULL,
  utm JSON NULL,
  device VARCHAR(16) DEFAULT NULL,
  country CHAR(2) DEFAULT NULL,
  ip_hash CHAR(64) DEFAULT NULL,
  is_bot TINYINT(1) NOT NULL DEFAULT 0,
  clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_site_aff (site_id, affiliate_id),
  INDEX idx_site_clicked (site_id, clicked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliate_order_attributions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  order_ref VARCHAR(120) NOT NULL,
  affiliate_id INT NOT NULL,
  code VARCHAR(64) DEFAULT NULL,
  model VARCHAR(24) NOT NULL DEFAULT 'last_click_30d',
  fixed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_site_order (site_id, order_ref),
  INDEX idx_aff (affiliate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliate_commissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  affiliate_id INT NOT NULL,
  order_ref VARCHAR(120) NOT NULL,
  base_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  tier_rate DECIMAL(6,4) NOT NULL DEFAULT 0,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  status VARCHAR(24) NOT NULL DEFAULT 'ESTIMATED',
  meta JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_site_order (site_id, order_ref),
  INDEX idx_site_aff (site_id, affiliate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliate_levels (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  code VARCHAR(24) NOT NULL,
  name VARCHAR(60) NOT NULL,
  min_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_site_code (site_id, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliate_scores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  affiliate_id INT NOT NULL,
  period CHAR(7) NOT NULL,
  eligible_sales DECIMAL(14,2) NOT NULL DEFAULT 0,
  score INT NOT NULL DEFAULT 0,
  level_code VARCHAR(24) DEFAULT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_period (site_id, affiliate_id, period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliate_audit (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  affiliate_id INT NULL,
  actor VARCHAR(120) DEFAULT NULL,
  action VARCHAR(60) NOT NULL,
  meta JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_site_aff (site_id, affiliate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
