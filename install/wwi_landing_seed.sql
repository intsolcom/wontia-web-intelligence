-- WWI Landing — tenant 5 (wwi.wontia.com)
-- Crea la página home con 8 secciones widget. Todo el contenido vive en DB y es editable
-- desde el admin (Pages → home → Sections).

INSERT IGNORE INTO sites (id, name, domain, locale, theme, is_active, status, uuid)
VALUES (5, 'WWI Factory', 'wwi.wontia.com', 'es', 'wwi', 1, 'PUBLISHED', UUID());

INSERT IGNORE INTO pages (site_id, title, slug, template, meta_title, meta_description, status, sort_order)
VALUES (5, 'Wontia Web Intelligence — Tu sitio web en 24 horas', 'home', 'wwi',
        'Wontia Web Intelligence | Tu sitio web profesional en 24 horas',
        'Compra. Cuéntanos quién eres. TIA construye tu sitio web con dominio, hosting, SSL, correos y SEO — sin programador, sin diseñador, sin agencia.',
        'published', 0);

DELETE FROM sections WHERE page_id = (SELECT id FROM pages WHERE site_id = 5 AND slug = 'home');

INSERT INTO sections (page_id, type, widget_type, title, config, sort_order, is_active) VALUES
((SELECT id FROM pages WHERE site_id = 5 AND slug = 'home'), 'widget', 'wwi-hero', 'Hero', '{}', 0, 1),
((SELECT id FROM pages WHERE site_id = 5 AND slug = 'home'), 'widget', 'wwi-plans', 'Planes', '{}', 1, 1),
((SELECT id FROM pages WHERE site_id = 5 AND slug = 'home'), 'widget', 'wwi-benefits', 'Beneficios', '{}', 2, 1),
((SELECT id FROM pages WHERE site_id = 5 AND slug = 'home'), 'widget', 'wwi-steps', 'Como funciona', '{}', 3, 1),
((SELECT id FROM pages WHERE site_id = 5 AND slug = 'home'), 'widget', 'wwi-templates', 'Plantillas', '{}', 4, 1),
((SELECT id FROM pages WHERE site_id = 5 AND slug = 'home'), 'widget', 'wwi-faq', 'FAQ', '{}', 5, 1),
((SELECT id FROM pages WHERE site_id = 5 AND slug = 'home'), 'widget', 'wwi-cta', 'CTA final', '{}', 6, 1),
((SELECT id FROM pages WHERE site_id = 5 AND slug = 'home'), 'widget', 'wwi-footer', 'Footer', '{}', 7, 1);
-- Checkout (se agrega después del seed inicial; el re-run completo lo incluye)
DELETE FROM sections WHERE page_id = (SELECT id FROM pages WHERE site_id = 5 AND slug = 'home') AND widget_type = 'wwi-checkout';
UPDATE sections SET sort_order = 8 WHERE page_id = (SELECT id FROM pages WHERE site_id = 5 AND slug = 'home') AND widget_type = 'wwi-footer';
INSERT INTO sections (page_id, type, widget_type, title, config, sort_order, is_active)
VALUES ((SELECT id FROM pages WHERE site_id = 5 AND slug = 'home'), 'widget', 'wwi-checkout', 'Checkout', '{}', 7, 1);

INSERT IGNORE INTO settings (site_id, key, alue) VALUES
(5, 'wwi.payment_provider', ''),
(5, 'wwi.wompi_integrity_key', '');
