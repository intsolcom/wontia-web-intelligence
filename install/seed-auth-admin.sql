-- ============================================================
-- WWI AUTH — Fija el correo de recuperación del administrador
-- Necesario para que el OTP de "¿Olvidaste tu contraseña?"
-- llegue a webmaster@intsolcom.com (correo registrado).
-- Idempotente. Ejecutar una vez (o cuando cambie el correo).
-- ============================================================

-- 1. Usuario(s) administrador -> correo de recuperación
UPDATE users
SET email = 'webmaster@intsolcom.com'
WHERE username = 'admin'
  AND email <> 'webmaster@intsolcom.com';

-- 2. Superadmins con correo placeholder -> correo de recuperación
UPDATE users
SET email = 'webmaster@intsolcom.com'
WHERE role = 'superadmin'
  AND (email IS NULL OR email = '' OR email LIKE 'admin@%');

-- 3. Verificación
SELECT id, site_id, username, email, role, twofa_enabled
FROM users
WHERE email = 'webmaster@intsolcom.com' OR username = 'admin';
