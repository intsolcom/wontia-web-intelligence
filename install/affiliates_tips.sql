-- SONIKA Artist Hub — aportes de agradecimiento (Módulo F, voluntarios, simulados). Idempotente.
CREATE TABLE IF NOT EXISTS affiliate_tips (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  affiliate_id INT NOT NULL,
  sender_name VARCHAR(120) DEFAULT NULL,
  sender_email VARCHAR(190) DEFAULT NULL,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  message VARCHAR(500) DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'simulated',
  reference VARCHAR(60) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_site_aff (site_id, affiliate_id),
  INDEX idx_site_status (site_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
