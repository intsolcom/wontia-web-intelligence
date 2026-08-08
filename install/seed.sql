INSERT INTO sites (name, domain, locale, theme) VALUES ('Wontia Web Intelligence', 'wontia.intsolcom.com', 'es', 'default');

INSERT INTO users (site_id, username, email, password_hash, role) VALUES (1, 'admin', 'admin@intsolcom.com', '$2y$12$LJ3m4ys3YOlDkOmMrPJ7OOCCpN.1S3Xv7JfYMm8PbBRHxdsF3POMG', 'superadmin');

INSERT INTO settings (site_id, `key`, `value`) VALUES
(1, 'site_name', 'Wontia Web Intelligence'),
(1, 'site_description', 'Applied Intelligence Platform'),
(1, 'ga_measurement_id', ''),
(1, 'cookie_consent_enabled', '1'),
(1, 'primary_color', '#9B8CDE'),
(1, 'logo_text', 'WONTIA');

INSERT INTO pages (site_id, title, slug, template, meta_title, meta_description, status, sort_order) VALUES
(1, 'Home', 'home', 'default', 'WONTIA — Applied Intelligence Platform', 'WONTIA es una plataforma de inteligencia aplicada que integra IA en cada etapa del proceso comercial.', 'published', 0);

INSERT INTO sections (page_id, type, title, subtitle, content, sort_order, is_active) VALUES
(1, 'hero', 'La plataforma que piensa por ti', 'WONTIA es una Applied Intelligence Platform que integra inteligencia artificial en cada etapa del proceso comercial.', '<p>WONTIA es una plataforma de inteligencia aplicada que integra IA en cada etapa del proceso comercial.</p>', 0, 1),
(1, 'features', 'Capacidades', 'Todo lo que necesitas para vender con inteligencia', '', 1, 1),
(1, 'cta', 'Deja que la inteligencia trabaje por ti', 'Comienza hoy con Wontia', '', 2, 1);
