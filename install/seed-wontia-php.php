<?php
$pdo = new PDO('mysql:host=mysql-prod;port=3306;dbname=wontia;charset=utf8mb4', 'root', 'Admin2026!', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->exec("DELETE FROM sections WHERE page_id IN (SELECT id FROM pages WHERE site_id = 1)");
$pdo->exec("DELETE FROM pages WHERE site_id = 1");

$pdo->exec("INSERT INTO pages (site_id, title, slug, template, meta_title, meta_description, status, sort_order) VALUES (1, 'WONTIA — Applied Intelligence Platform', 'home', 'default', 'WONTIA — Applied Intelligence Platform', 'WONTIA es una plataforma de inteligencia aplicada que integra IA en cada etapa del proceso comercial.', 'published', 0)");
$pageId = $pdo->lastInsertId();

$pdo->exec("ALTER TABLE sections MODIFY COLUMN config LONGTEXT COMMENT 'BRICK widget configuration'");

$bricks = [
    ['hero', 'hero', json_encode(['badge_text'=>'Plataforma de Inteligencia Aplicada','title'=>'La plataforma que <span class="gradient-text">piensa</span> por ti','subtitle'=>'WONTIA es una Applied Intelligence Platform que integra inteligencia artificial en cada etapa del proceso comercial. No es un CRM: es un sistema operativo para equipos que venden con precision quirurgica, automatizan sin perder el toque humano y toman decisiones basadas en datos reales.','cta_primary_text'=>'Comenzar ahora','cta_primary_url'=>'https://app.wontia.com/login?tab=register','cta_secondary_text'=>'Conoce a TIA','cta_secondary_url'=>'#tia'])],
    ['features', 'features', json_encode(['title'=>'','cards'=>[
        ['title'=>'Pipeline Inteligente','desc'=>'Visualiza cada oportunidad en su etapa exacta. TIA sugiere el siguiente paso.'],
        ['title'=>'Automatizacion por Etapa','desc'=>'Flujos de trabajo vinculados a cada etapa. Correos, WhatsApp, tareas.'],
        ['title'=>'Multi-Canal Unificado','desc'=>'WhatsApp, email, Facebook, Instagram. Una sola bandeja.'],
        ['title'=>'IA que Ejecuta','desc'=>'TIA no solo sugiere: ejecuta. Redacta, envia, programa y analiza.'],
        ['title'=>'KPIs en Tiempo Real','desc'=>'Metricas vivas de conversion, ingresos y velocidad del pipeline.'],
        ['title'=>'Roles y Permisos','desc'=>'Super admin, admin, manager, team leader, agente. Sin friccion.']
    ]])],
    ['tia', 'tia', json_encode(['badge'=>'Tecnologia de Inteligencia Aplicada','title'=>'TIA es el <span class="gradient-text">cerebro</span> de Wontia','description'=>'TIA no es un chatbot. Es un sistema de inteligencia aplicada que opera en cada capa de la plataforma.','items'=>[
        ['title'=>'Analisis Predictivo','desc'=>'Identifica oportunidades con alta probabilidad de cierre antes que tu equipo.'],
        ['title'=>'Ejecucion Autonoma','desc'=>'Redacta y envia correos, WhatsApp y mensajes basados en el contexto del lead.'],
        ['title'=>'Orquestacion de Flujos','desc'=>'Activa tareas, recordatorios y seguimientos cuando se cumplen condiciones.'],
        ['title'=>'Asistencia en Vivo','desc'=>'Responde preguntas, crea leads, navega y ejecuta comandos por voz o texto.'],
        ['title'=>'Aprendizaje Continuo','desc'=>'Cada interaccion alimenta el modelo. TIA mejora con cada decision.']
    ],'blob_message'=>'Analice tu pipeline. Tienes 3 oportunidades listas para cerrar esta semana.'])],
    ['aip', 'aip', json_encode(['badge'=>'Applied Intelligence Platform','title'=>'El concepto <span class="gradient-text">AIP</span>','description'=>'AIP es la filosofia que define como Wontia integra la inteligencia artificial.','cards'=>[
        ['title'=>'Inteligencia Aplicada, no Artificial','desc'=>'La IA no reemplaza a tu equipo. Lo potencia. TIA decide basandose en datos reales.'],
        ['title'=>'Orquestacion, no Automatizacion','desc'=>'Wontia orquesta procesos completos. TIA evalua, elige el canal, redacta, agenda.'],
        ['title'=>'Defensa en Profundidad','desc'=>'Capas de validacion: reglas de negocio, permisos, configuracion de etapa. Nada sin filtros.'],
        ['title'=>'Metricas que Importan','desc'=>'Velocidad, tasa de respuesta, tiempo de cierre, efectividad de TIA. Medimos resultados de negocio.']
    ]])],
    ['howitworks', 'howitworks', json_encode(['title'=>'Como funciona','subtitle'=>'Tres pasos. Una plataforma. Cero friccion.','steps'=>[
        ['title'=>'Conecta tus canales','desc'=>'WhatsApp, email, Facebook, Instagram. Conectalos en minutos.','image'=>'canales-wontia.jpg'],
        ['title'=>'Define tu pipeline','desc'=>'Crea etapas, reglas y flujos. TIA te guia con plantillas inteligentes.','image'=>''],
        ['title'=>'Deja que TIA opere','desc'=>'TIA analiza, ejecuta, sugiere y aprende. Tu equipo supervisa.','image'=>'']
    ]])],
    ['pricing', 'pricing', json_encode(['title'=>'Precios','subtitle'=>'Encuentra el plan que se adapta a tu equipo.','ice_workspace'=>'1'])],
    ['cta', 'cta', json_encode(['title'=>'Deja que la inteligencia trabaje por ti','description'=>'Wontia no es un CRM. Es una Applied Intelligence Platform que convierte cada oportunidad en un resultado medible.','button_text'=>'Ingresar a Wontia','button_url'=>'https://app.wontia.com/login'])],
    ['footer', 'footer', json_encode(['brand_name'=>'WONTIA','powered_by'=>'Intsolcom, LLC','copyright'=>'Intsolcom, LLC. Todos los derechos reservados.','address_us'=>'390 NE 191st St, STE 17284, Miami, FL 33179','phone_us'=>'+1 (786) 386-1515','email_us'=>'customer@intsolcom.com','address_co'=>'Cra 53 # 80 - 192, Barranquilla, Colombia','phone_co'=>'+57 311 602 0005','email_co'=>'cliente@intsolcom.com'])]
];

$stmt = $pdo->prepare("INSERT INTO sections (page_id, type, widget_type, title, config, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
foreach ($bricks as $i => $b) {
    $stmt->execute([$pageId, $b[0], $b[1], $b[1] . ' Section', $b[2], $i]);
}

echo "Seeded " . count($bricks) . " BRICK widgets. Page ID: $pageId\n";
