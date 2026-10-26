-- Wontia IA Annotation Manager — internal page (site 1, wontia.com)
-- Aditivo: no toca la home (page 4). Rollback: DELETE FROM sections WHERE page_id = (id nuevo); DELETE FROM pages WHERE slug='ia-annotation' AND site_id=1;

SET @site_id = 1;

INSERT INTO pages (site_id, title, slug, template, meta_title, meta_description, status, sort_order)
VALUES (@site_id, 'Wontia IA Annotation Manager', 'ia-annotation', 'default',
  'Wontia IA Annotation Manager | Sports Annotation Intelligence',
  'Sports annotation platform: assignment, production, QA, payments, Slack and client feedback — powered by WONTIA and TIA.',
  'published', 1);

SET @annotation_id = LAST_INSERT_ID();

INSERT INTO sections (page_id, type, widget_type, title, config, sort_order, is_active) VALUES
(@annotation_id, 'ais-hero', 'ais-hero', 'Hero', '{"eyebrow":"APPLIED INTELLIGENCE SYSTEM","headline":"Wontia IA<br><span class=\"gradient-text\">Annotation Manager</span>","subtitle":"Sports annotation intelligence: assignment, production, QA, payments, Slack and client feedback — orchestrated by TIA. One operation. Full traceability.","cta_primary_text":"Talk to Us","cta_primary_url":"mailto:customer@intsolcom.com","cta_secondary_text":"Back to Wontia","cta_secondary_url":"/"}', 0, 1),
(@annotation_id, 'features', 'features', 'Features', '{"title":"Annotation Intelligence","cards":[{"title":"Assignment Intelligence","desc":"Smart workload distribution across annotators, tracked in real time."},{"title":"Production Workflows","desc":"Stages, rules and deadlines. TIA flags bottlenecks before they block you."},{"title":"QA Control","desc":"Quality gates at every stage. Rejections, corrections and approvals — audited."},{"title":"Payments & Payroll","desc":"Production-based payments calculated automatically. Ready for payroll."},{"title":"Slack Integration","desc":"Client feedback arrives in Slack and lands directly on the affected clips."},{"title":"Client Feedback","desc":"Every request, revision and approval connected to the original annotation."}]}', 1, 1),
(@annotation_id, 'howitworks', 'howitworks', 'How It Works', '{"title":"How it works","subtitle":"From upload to delivery — a single intelligence layer.","steps":[{"title":"Upload & Configure","desc":"Load your match footage and define the annotation playbook for each sport.","image":""},{"title":"Assign & Annotate","desc":"TIA distributes work, tracks progress and keeps every annotator on spec.","image":""},{"title":"QA, Deliver & Pay","desc":"Quality gates, client feedback, delivery and production-based payroll.","image":""}]}', 2, 1),
(@annotation_id, 'trust', 'trust', 'Trust & Control', '{}', 3, 1),
(@annotation_id, 'cta', 'cta', 'CTA', '{"title":"Bring annotation intelligence to your operation","description":"Sports annotation with production control, QA, payments and client feedback — powered by WONTIA and TIA.","button_text":"Talk to Us","button_url":"mailto:customer@intsolcom.com"}', 4, 1),
(@annotation_id, 'footer', 'footer', 'Footer', '{}', 5, 1);

SELECT id, title, slug FROM pages WHERE site_id = @site_id;
