-- SONIKA Artist Partners — Fase 2: contratos versionados, aceptaciones, OTP, documentos. Idempotente.
CREATE TABLE IF NOT EXISTS affiliate_contracts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  code VARCHAR(40) NOT NULL DEFAULT 'artist_partners',
  version VARCHAR(20) NOT NULL,
  title VARCHAR(190) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  effective_at DATE NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'published',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_site_code_ver (site_id, code, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliate_acceptances (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  affiliate_id INT NOT NULL,
  contract_id INT NOT NULL,
  contract_code VARCHAR(40) NOT NULL,
  contract_version VARCHAR(20) NOT NULL,
  accepted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ip_hash CHAR(64) DEFAULT NULL,
  user_agent VARCHAR(300) DEFAULT NULL,
  UNIQUE KEY uq_site_aff_contract (site_id, affiliate_id, contract_id),
  INDEX idx_aff (site_id, affiliate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliate_otps (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  email VARCHAR(190) NOT NULL,
  channel VARCHAR(10) NOT NULL DEFAULT 'email',
  code_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  attempts TINYINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_site_email (site_id, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliate_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  affiliate_id INT NOT NULL,
  kind VARCHAR(30) NOT NULL DEFAULT 'id_front',
  path VARCHAR(500) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_site_aff (site_id, affiliate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
