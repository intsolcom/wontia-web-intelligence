-- Seed: Wontia Landing Page as BRICK widgets
-- Clears generic seed and inserts wontia.com content

DELETE FROM sections WHERE page_id IN (SELECT id FROM pages WHERE site_id = 1);
DELETE FROM pages WHERE site_id = 1;

INSERT INTO pages (site_id, title, slug, template, meta_title, meta_description, status, sort_order) VALUES
(1, 'WONTIA — Applied Intelligence Platform', 'home', 'default', 
 'WONTIA — Applied Intelligence Platform', 
 'WONTIA es una plataforma de inteligencia aplicada que integra IA en cada etapa del proceso comercial.',
 'published', 0);

SET @page_id = LAST_INSERT_ID();

-- Brick 1: Hero
INSERT INTO sections (page_id, type, widget_type, title, config, sort_order, is_active) VALUES
(@page_id, 'hero', 'hero', 'Hero Section', 
'{"badge_text":"Plataforma de Inteligencia Aplicada","title":"La plataforma que <span class=\"gradient-text\">piensa</span> por ti","subtitle":"WONTIA es una <strong>Applied Intelligence Platform</strong> que integra inteligencia artificial en cada etapa del proceso comercial. No es un CRM: es un sistema operativo para equipos que venden con precision quirurgica, automatizan sin perder el toque humano y toman decisiones basadas en datos reales.","cta_primary_text":"Comenzar ahora","cta_primary_url":"https://app.wontia.com/login?tab=register","cta_secondary_text":"Conoce a TIA","cta_secondary_url":"#tia"}',
0, 1);

-- Brick 2: Features Grid (6 cards)
INSERT INTO sections (page_id, type, widget_type, title, config, sort_order, is_active) VALUES
(@page_id, 'features', 'features', '', 
'{"title":"","cards":[{"title":"Pipeline Inteligente","desc":"Visualiza cada oportunidad en su etapa exacta. TIA sugiere el siguiente paso, activa flujos automaticos y te alerta cuando algo requiere atencion.","svg_a":"pipeline","svg_b":"pipeline_hover"},{"title":"Automatizacion por Etapa","desc":"Flujos de trabajo vinculados a cada etapa. Correos, WhatsApp, tareas y notificaciones se activan automaticamente.","svg_a":"automation","svg_b":"automation_hover"},{"title":"Multi-Canal Unificado","desc":"WhatsApp, email, Facebook, Instagram. Una sola bandeja. Responde sin cambiar de herramienta.","svg_a":"multichannel","svg_b":"multichannel_hover"},{"title":"IA que Ejecuta","desc":"TIA no solo sugiere: ejecuta. Redacta, envia, programa y analiza. Tu equipo solo supervisa.","svg_a":"ia","svg_b":"ia_hover"},{"title":"KPIs en Tiempo Real","desc":"Metricas vivas de conversion, ingresos y velocidad del pipeline. Decisiones basadas en datos reales.","svg_a":"kpi","svg_b":"kpi_hover"},{"title":"Roles y Permisos","desc":"Super admin, admin, manager, team leader, agente. Cada rol ve lo que necesita. Sin friccion.","svg_a":"roles","svg_b":"roles_hover"}]}',
1, 1);

-- Brick 3: TIA Section
INSERT INTO sections (page_id, type, widget_type, title, config, sort_order, is_active) VALUES
(@page_id, 'tia', 'tia', 'TIA Section',
'{"badge":"Tecnologia de Inteligencia Aplicada","title":"TIA es el <span class=\"gradient-text\">cerebro</span> de Wontia","description":"TIA no es un chatbot. Es un sistema de inteligencia aplicada que opera en cada capa de la plataforma:","items":[{"title":"Analisis Predictivo","desc":"Identifica oportunidades con alta probabilidad de cierre antes que tu equipo."},{"title":"Ejecucion Autonoma","desc":"Redacta y envia correos, WhatsApp y mensajes basados en el contexto del lead."},{"title":"Orquestacion de Flujos","desc":"Activa tareas, recordatorios y seguimientos cuando se cumplen condiciones."},{"title":"Asistencia en Vivo","desc":"Responde preguntas, crea leads, navega y ejecuta comandos por voz o texto."},{"title":"Aprendizaje Continuo","desc":"Cada interaccion alimenta el modelo. TIA mejora con cada decision."}],"blob_message":"Analice tu pipeline. Tienes 3 oportunidades listas para cerrar esta semana. Quieres que prepare las propuestas?"}',
2, 1);

-- Brick 4: AIP Section
INSERT INTO sections (page_id, type, widget_type, title, config, sort_order, is_active) VALUES
(@page_id, 'aip', 'aip', 'AIP Section',
'{"badge":"Applied Intelligence Platform","title":"El concepto <span class=\"gradient-text\">AIP</span>","description":"AIP —Applied Intelligence Platform— es la filosofia que define como Wontia integra la inteligencia artificial. La IA no es una funcionalidad: es el sistema operativo.","cards":[{"title":"Inteligencia Aplicada, no Artificial","desc":"La IA no reemplaza a tu equipo. Lo potencia. TIA decide basandose en datos reales de tu negocio, no en patrones genericos."},{"title":"Orquestacion, no Automatizacion","desc":"Wontia orquesta procesos completos. TIA evalua, elige el canal, redacta, agenda y mide la respuesta. Todo en secuencia."},{"title":"Defensa en Profundidad","desc":"Capas de validacion: reglas de negocio, permisos, configuracion de etapa, historial del lead. Nada se ejecuta sin filtros."},{"title":"Metricas que Importan","desc":"Velocidad, tasa de respuesta, tiempo de cierre, efectividad de TIA. Medimos resultados de negocio, no clicks."}]}',
3, 1);

-- Brick 5: How It Works
INSERT INTO sections (page_id, type, widget_type, title, config, sort_order, is_active) VALUES
(@page_id, 'howitworks', 'howitworks', 'Como funciona',
'{"title":"Como funciona","subtitle":"Tres pasos. Una plataforma. Cero friccion.","steps":[{"title":"Conecta tus canales","desc":"WhatsApp, email, Facebook, Instagram. Conectalos en minutos. TIA reconoce el origen de cada lead.","image":"canales-wontia.jpg"},{"title":"Define tu pipeline","desc":"Crea etapas, reglas y flujos. TIA te guia con plantillas inteligentes por industria.","image":""},{"title":"Deja que TIA opere","desc":"TIA analiza, ejecuta, sugiere y aprende. Tu equipo supervisa y cierra negocios.","image":""}]}',
4, 1);

-- Brick 6: Pricing (ICE)
INSERT INTO sections (page_id, type, widget_type, title, config, sort_order, is_active) VALUES
(@page_id, 'pricing', 'pricing', 'Precios',
'{"title":"Precios","subtitle":"Encuentra el plan que se adapta a tu equipo. Todos incluyen acceso a TIA.","ice_workspace":"1"}',
5, 1);

-- Brick 7: CTA
INSERT INTO sections (page_id, type, widget_type, title, config, sort_order, is_active) VALUES
(@page_id, 'cta', 'cta', 'Final CTA',
'{"title":"Deja que la inteligencia trabaje por ti","description":"Wontia no es un CRM. Es una Applied Intelligence Platform que convierte cada oportunidad en un resultado medible.","button_text":"Ingresar a Wontia","button_url":"https://app.wontia.com/login"}',
6, 1);

-- Brick 8: Footer
INSERT INTO sections (page_id, type, widget_type, title, config, sort_order, is_active) VALUES
(@page_id, 'footer', 'footer', 'Footer',
'{"brand_name":"WONTIA","powered_by":"Intsolcom, LLC","copyright":"Intsolcom, LLC. Todos los derechos reservados.","address_us":"390 NE 191st St, STE 17284, Miami, FL 33179","phone_us":"+1 (786) 386-1515","email_us":"customer@intsolcom.com","address_co":"Cra 53 # 80 - 192, Barranquilla, Colombia","phone_co":"+57 311 602 0005","email_co":"cliente@intsolcom.com"}',
7, 1);
