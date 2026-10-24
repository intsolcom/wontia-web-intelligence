-- SONIKA Artist Hub — muro de contenido (Módulo C). Idempotente.
CREATE TABLE IF NOT EXISTS affiliate_posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  affiliate_id INT NOT NULL,
  content TEXT NOT NULL,
  image VARCHAR(500) DEFAULT NULL,
  link VARCHAR(500) DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'published',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_site_aff (site_id, affiliate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
