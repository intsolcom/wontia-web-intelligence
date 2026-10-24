-- SONIKA Artist Hub — solicitudes de contratación (Módulo D). Idempotente.
CREATE TABLE IF NOT EXISTS affiliate_contract_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  affiliate_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(40) DEFAULT NULL,
  event_type VARCHAR(40) DEFAULT NULL,
  event_date DATE DEFAULT NULL,
  place VARCHAR(190) DEFAULT NULL,
  budget VARCHAR(40) DEFAULT NULL,
  message TEXT DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_site_aff (site_id, affiliate_id),
  INDEX idx_site_status (site_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
