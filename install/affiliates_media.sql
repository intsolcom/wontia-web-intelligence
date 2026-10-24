-- SONIKA Artist Hub — medios del artista (Fase 1: URLs + YouTube + Instagram). Idempotente.
CREATE TABLE IF NOT EXISTS affiliate_media (
  site_id INT NOT NULL,
  affiliate_id INT NOT NULL,
  cover VARCHAR(500) DEFAULT NULL,
  avatar VARCHAR(500) DEFAULT NULL,
  gallery JSON DEFAULT NULL,
  video_url VARCHAR(500) DEFAULT NULL,
  instagram VARCHAR(120) DEFAULT NULL,
  tiktok VARCHAR(500) DEFAULT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (site_id, affiliate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
