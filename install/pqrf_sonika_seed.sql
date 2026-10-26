-- PQRF F1 — configuración y enlace en el hub público de SONIKA (site_id = 12).
-- Idempotente: INSERT IGNORE y comprobación de que el enlace aún no exista.

SET NAMES utf8mb4;

INSERT IGNORE INTO settings (site_id, `key`, `value`) VALUES
    (12, 'pqrf_public_url', 'https://sonika.com.co'),
    (12, 'pqrf_contact_email', 'clientes@sonika.com.co'),
    (12, 'pqrf_admin_email', 'clientes@sonika.com.co'),
    (12, 'pqrf_whatsapp', '3045650333'),
    (12, 'pqrf_contact_phone', '3045650333'),
    (12, 'pqrf_token_days', '180'),
    (12, 'pqrf_due_hours', '0'),
    (12, 'pqrf_email_enabled', '1'),
    (12, 'pqrf_reminders', '1'),
    (12, 'pqrf_max_files', '5'),
    (12, 'pqrf_max_mb', '5');

UPDATE sections s
JOIN pages p ON p.id = s.page_id
SET s.content = REPLACE(
        s.content,
        '<div class="legal-foot">',
        '<h2 id="pqrs"><a href="/pqrf">PQRS / Radica tu solicitud</a></h2><p>Radica y sigue tu petición, queja, reclamo, sugerencia o felicitación desde tu cuenta de cliente.</p><div class="legal-foot">'
    )
WHERE p.slug = 'politicas'
  AND p.site_id = 12
  AND s.content LIKE '%<div class="legal-foot">%'
  AND s.content NOT LIKE '%href="/pqrf"%';
