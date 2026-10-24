-- SONIKA Artist Partners — autenticación (Fase 2b). Idempotente (ignora si la columna ya existe).
ALTER TABLE affiliate_profiles ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL AFTER email;
ALTER TABLE affiliate_profiles ADD COLUMN last_login_at DATETIME DEFAULT NULL;
