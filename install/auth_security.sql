-- ============================================================
-- WWI AUTH SECURITY — Recuperación de contraseña (OTP por email)
--                    + Autenticación en dos pasos (TOTP / Google Authenticator)
-- Idempotente. La app auto-migra vía AuthSecurityService::ensureTables().
-- ============================================================

ALTER TABLE users ADD COLUMN IF NOT EXISTS twofa_enabled TINYINT NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS twofa_secret VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS twofa_recovery JSON NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS twofa_confirmed_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS auth_otps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    user_id INT NULL,
    email VARCHAR(255) NOT NULL,
    purpose VARCHAR(32) NOT NULL DEFAULT 'password_reset',
    code_hash VARCHAR(255) NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 5,
    ip VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    consumed_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_lookup (site_id, email, purpose, created_at),
    KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS auth_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    user_id INT NULL,
    email VARCHAR(255) NULL,
    event VARCHAR(48) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'ok',
    ip VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    meta JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_site_created (site_id, created_at),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
