-- SONIKA Partners — programa único de distribuidores (Comparte, vende y gana).
-- Migración idempotente. La vía recomendada es:
--   php -r 'require "vendor/autoload.php"; App\Core\Config::load(); App\Services\PartnerProgramSchemaService::ensure(true);'
-- La categoría interna (artist/influencer/natural) NO cambia comisión, contrato ni derechos.

ALTER TABLE affiliate_profiles
    ADD COLUMN IF NOT EXISTS profile_category VARCHAR(20) NOT NULL DEFAULT 'natural',
    ADD COLUMN IF NOT EXISTS niche VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS audience_size VARCHAR(60) NULL,
    ADD COLUMN IF NOT EXISTS platforms JSON NULL,
    ADD COLUMN IF NOT EXISTS verification_status VARCHAR(24) NOT NULL DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS public_profile_enabled TINYINT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS payout_method VARCHAR(40) NULL,
    ADD COLUMN IF NOT EXISTS tax_id VARCHAR(40) NULL,
    ADD COLUMN IF NOT EXISTS risk_score INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS approved_by VARCHAR(190) NULL;

UPDATE affiliate_profiles SET profile_category = 'artist' WHERE profile_category IS NULL OR profile_category = '';

ALTER TABLE affiliate_commissions
    ADD COLUMN IF NOT EXISTS points INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS month_key CHAR(7) NULL,
    ADD COLUMN IF NOT EXISTS formula_version VARCHAR(24) NULL,
    ADD COLUMN IF NOT EXISTS outlet_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS max_item_price DECIMAL(14,2) NOT NULL DEFAULT 0;

UPDATE affiliate_commissions SET month_key = DATE_FORMAT(created_at, '%Y-%m') WHERE month_key IS NULL OR month_key = '';

ALTER TABLE affiliate_contracts
    ADD COLUMN IF NOT EXISTS profile_scope VARCHAR(30) NOT NULL DEFAULT 'all';
