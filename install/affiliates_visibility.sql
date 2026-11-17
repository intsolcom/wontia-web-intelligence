-- SONIKA Partners — visibilidad de perfiles públicos. Idempotente.
-- Interruptor global apagado por defecto: el sitio y el catálogo original
-- se mantienen intactos y el Partner solo aporta su enlace de atribución.
INSERT INTO settings (site_id, `key`, `value`)
SELECT id, 'affiliate_public_profiles_enabled', '0' FROM sites
ON DUPLICATE KEY UPDATE `key` = `key`;

-- Migración única recomendada al desplegar el modo catálogo:
-- deja los perfiles existentes ocultos hasta que cada Partner lo autorice.
-- No la ejecutes en cada despliegue porque resetearía preferencias posteriores.
--
-- UPDATE affiliate_profiles SET public_profile_enabled = 0 WHERE public_profile_enabled = 1;
--
-- Los enlaces de atribución se actualizan solos en el siguiente clic a /r/{code}
-- o al guardar la visibilidad desde el panel del Partner.
