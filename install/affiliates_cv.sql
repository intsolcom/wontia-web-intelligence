-- SONIKA Artist Hub — CV artístico / trayectoria (Módulo B). Idempotente.
CREATE TABLE IF NOT EXISTS affiliate_cv (
  site_id INT NOT NULL,
  affiliate_id INT NOT NULL,
  bio TEXT DEFAULT NULL,
  experience_years VARCHAR(20) DEFAULT NULL,
  bands JSON DEFAULT NULL,
  achievements JSON DEFAULT NULL,
  links JSON DEFAULT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (site_id, affiliate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
