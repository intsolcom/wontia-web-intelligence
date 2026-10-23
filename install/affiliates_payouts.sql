-- SONIKA Artist Partners — Fase 6: liquidaciones por lote (modo simulacion). Idempotente.
CREATE TABLE IF NOT EXISTS affiliate_payout_batches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  period CHAR(7) NOT NULL,
  total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  items_count INT NOT NULL DEFAULT 0,
  status VARCHAR(24) NOT NULL DEFAULT 'PENDING_APPROVAL',
  note VARCHAR(255) DEFAULT NULL,
  created_by VARCHAR(120) DEFAULT NULL,
  approved_by VARCHAR(120) DEFAULT NULL,
  paid_by VARCHAR(120) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  approved_at DATETIME DEFAULT NULL,
  paid_at DATETIME DEFAULT NULL,
  INDEX idx_site_status (site_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliate_payout_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  batch_id INT NOT NULL,
  affiliate_id INT NOT NULL,
  commission_id INT NOT NULL,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_site_commission (site_id, commission_id),
  INDEX idx_batch (batch_id),
  INDEX idx_aff (site_id, affiliate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
