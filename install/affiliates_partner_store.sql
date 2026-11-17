-- SONIKA Partners — enlaces amigables, “recordarme” y tienda del Partner. Idempotente.
-- Se puede ejecutar varias veces sin borrar datos.

CREATE TABLE IF NOT EXISTS affiliate_link_aliases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  code VARCHAR(64) NOT NULL,
  affiliate_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_site_code (site_id, code),
  INDEX idx_aff (affiliate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliate_remember_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  affiliate_id INT NOT NULL,
  selector VARCHAR(32) NOT NULL,
  validator_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME NULL,
  UNIQUE KEY uq_site_selector (site_id, selector),
  INDEX idx_aff (affiliate_id),
  INDEX idx_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La colección “Mi tienda” se crea automáticamente al elegir el primer producto.
-- No se requiere migrar datos existentes.
