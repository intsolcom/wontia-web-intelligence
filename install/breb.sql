SET NAMES utf8mb4;

ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS subtotal_net_cents INT NOT NULL DEFAULT 0;
ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS tax_cents INT NOT NULL DEFAULT 0;
ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS breb_discount_cents INT NOT NULL DEFAULT 0;
ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS breb_discount_net_cents INT NOT NULL DEFAULT 0;
ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS payment_reference VARCHAR(80) NULL;
ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS payment_link VARCHAR(500) NULL;
ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS payment_proof_path VARCHAR(255) NULL;
ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS payment_proof_mime VARCHAR(100) NULL;
ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS payment_proof_size INT NOT NULL DEFAULT 0;
ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS payment_proof_at DATETIME NULL;
ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS payment_proof_status VARCHAR(20) NOT NULL DEFAULT 'none';
ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS payment_expires_at DATETIME NULL;
ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS payment_verified_at DATETIME NULL;
ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS payment_verified_by INT NULL;
ALTER TABLE store_orders ADD COLUMN IF NOT EXISTS payment_meta LONGTEXT NULL;

INSERT IGNORE INTO settings (site_id, `key`, `value`) VALUES
    (12, 'breb_enabled', '1'),
    (12, 'breb_label', 'Paga a través de Bre-B'),
    (12, 'breb_discount_pct', '3.00'),
    (12, 'breb_prices_include_tax', '1'),
    (12, 'breb_tax_rate', '19.00'),
    (12, 'breb_rounding', '100'),
    (12, 'breb_free_shipping_net_cents', '30000000'),
    (12, 'breb_shipping_cents', '1500000'),
    (12, 'breb_qr_image', ''),
    (12, 'breb_qr_alt', 'QR Bre-B de SONIKA'),
    (12, 'breb_key', ''),
    (12, 'breb_account_name', ''),
    (12, 'breb_instructions', 'Abre tu app bancaria, escanea el QR de Bre-B y transfiere el monto exacto. Luego adjunta el comprobante de pago.'),
    (12, 'breb_proof_max_mb', '2'),
    (12, 'breb_payment_minutes', '120'),
    (12, 'breb_stackable', '1'),
    (12, 'breb_terms_url', '/politicas'),
    (12, 'breb_whatsapp', '3045650333'),
    (12, 'breb_mp_enabled', '0'),
    (12, 'breb_mp_label', 'Mercado Pago'),
    (12, 'breb_mp_link', ''),
    (12, 'breb_epayco_enabled', '0'),
    (12, 'breb_epayco_label', 'ePayco'),
    (12, 'breb_epayco_link', '');
