-- SONIKA Artist Partners — Fase 8: premios/logros. Idempotente.
CREATE TABLE IF NOT EXISTS affiliate_awards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  affiliate_id INT NULL,
  kind VARCHAR(30) NOT NULL DEFAULT 'achievement',
  title VARCHAR(190) NOT NULL,
  description VARCHAR(500) DEFAULT NULL,
  level_code VARCHAR(24) DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'granted',
  evidence VARCHAR(500) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_site_aff (site_id, affiliate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
