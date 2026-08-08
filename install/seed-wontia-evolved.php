<?php
$pdo = new PDO('mysql:host=mysql-prod;port=3306;dbname=wontia;charset=utf8mb4', 'root', 'Admin2026!', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->exec("DELETE FROM sections WHERE page_id IN (SELECT id FROM pages WHERE site_id = 1)");
$pdo->exec("DELETE FROM pages WHERE site_id = 1");

$pdo->exec("INSERT INTO pages (site_id, title, slug, template, meta_title, meta_description, status, sort_order) VALUES (1, 'WONTIA — Applied Intelligence Platform', 'home', 'default', 'WONTIA — Applied Intelligence Platform | Turn AI into Action', 'Wontia is an Applied Intelligence Platform that enables TIA to understand context, make intelligent decisions, and execute actions across systems and domains.', 'published', 0)");
$pageId = $pdo->lastInsertId();

$pdo->exec("ALTER TABLE sections MODIFY COLUMN config LONGTEXT COMMENT 'BRICK widget configuration'");

$bricks = [
    ['hero', 'hero-evolved', 'Hero Evolved', json_encode([
        'eyebrow' => 'TECHNOLOGY OF APPLIED INTELLIGENCE',
        'headline' => 'Turn AI into <span class="gradient-text">Action</span>',
        'subtitle' => 'Wontia is an Applied Intelligence Platform that enables TIA to understand context, make intelligent decisions, and execute actions across the systems and domains where work happens.',
        'cta_primary_text' => 'Explore Wontia Business',
        'cta_primary_url' => '#business',
        'cta_secondary_text' => 'Discover TIA',
        'cta_secondary_url' => '#tia-command',
    ])],

    ['differentiator', 'differentiator', 'Differentiator Section', json_encode([
        'headline' => 'From AI that <span style="color:#9A9A9A">answers</span><br>to Intelligence that <span class="gradient-text">acts</span>',
        'left_title' => 'Traditional AI',
        'left_items' => ["Question → Answer", "Data → Dashboard → Human Decision", "Reactive responses", "Single interaction", "Read-only"],
        'right_title' => 'Wontia + TIA',
        'right_items' => ["Context → Understand → Decide → Act", "Intelligent orchestration", "Proactive execution", "Multi-step workflows", "Tools + Systems + Verification"],
    ])],

    ['tia', 'tiacommand', 'TIA Command Center', json_encode([
        'headline' => 'Talk to your system. Let TIA act.',
        'subtitle' => 'The TIA Command Center is an operational interface to Wontia. Not a chatbot. An intelligence layer that understands text, voice, commands, and executes across systems.',
        'examples' => ["Show me what needs attention", "Analyze today's operations", "Create the recommended workflow", "Why did you recommend this?", "Execute the approved actions"],
    ])],

    ['business', 'wontia-business', 'Wontia Business', json_encode([
        'status_label' => 'AVAILABLE — CURRENT',
        'headline' => 'Wontia Business',
        'subtitle' => 'The first domain-specific application of Wontia\'s Applied Intelligence architecture. Business operations powered by TIA — not a CRM, but an operating environment where CRM is one capability among many.',
        'capabilities' => [
            ['title' => 'Customer Intelligence', 'desc' => 'Understand leads and clients through behavioral patterns and context.'],
            ['title' => 'Sales Intelligence', 'desc' => 'Pipeline visibility with AI-suggested next actions.'],
            ['title' => 'Operations Intelligence', 'desc' => 'Automated workflows triggered by business events.'],
            ['title' => 'Decision Support', 'desc' => 'TIA analyzes data and recommends the best course of action.'],
            ['title' => 'AI Agents', 'desc' => 'Autonomous agents that execute, verify, and learn.'],
            ['title' => 'Task Orchestration', 'desc' => 'Coordinate people, systems, and timelines automatically.'],
        ],
    ])],

    ['domain-arch', 'domain-arch', 'Domain Architecture', json_encode([
        'headline' => 'One Intelligence. Different Domains.',
        'subtitle' => 'The intelligence layer remains consistent. The domain context, tools, workflows, and actions change.',
        'domains' => [
            ['name' => 'Wontia Business', 'desc' => 'Customer, Sales & Operations Intelligence', 'status' => 'available', 'icon' => 'briefcase'],
            ['name' => 'Wontia Food Security', 'desc' => 'Applied Intelligence for Food Security', 'status' => 'development', 'icon' => 'package'],
            ['name' => 'Wontia Health', 'desc' => 'Health Intelligence Engine', 'status' => 'future', 'icon' => 'heart'],
            ['name' => 'Wontia Agriculture', 'desc' => 'Agriculture Intelligence Engine', 'status' => 'future', 'icon' => 'sun'],
            ['name' => 'Wontia Industry', 'desc' => 'Industrial Intelligence Engine', 'status' => 'future', 'icon' => 'cpu'],
            ['name' => 'Wontia Logistics', 'desc' => 'Supply Chain Intelligence', 'status' => 'future', 'icon' => 'truck'],
            ['name' => 'Wontia Education', 'desc' => 'Learning Intelligence Engine', 'status' => 'future', 'icon' => 'book-open'],
            ['name' => 'More', 'desc' => 'Architecture designed for expansion', 'status' => 'future', 'icon' => 'plus-circle'],
        ],
    ])],

    ['food-security', 'food-security', 'Food Security', json_encode([
        'status_label' => 'NEXT VERTICAL — IN DEVELOPMENT',
        'headline' => 'Wontia Food Security',
        'subtitle' => 'Applied Intelligence for Food Security — proving that the same architecture can extend beyond business into domains that impact lives.',
        'engine_name' => 'Food Security Response Engine',
        'insight_text' => '2.8 tons of food are at high risk of being lost within 36 hours. Redistribution plan ready for review.',
        'metrics' => [
            ['label' => 'Food Available', 'value' => '12.4T'],
            ['label' => 'At Risk', 'value' => '2.8T'],
            ['label' => 'Communities', 'value' => '14'],
            ['label' => 'Meals Enabled', 'value' => '1,900'],
        ],
    ])],

    ['platform-arch', 'platform-arch', 'Platform Architecture', json_encode([
        'headline' => 'One Platform. One Intelligence Layer.',
        'subtitle' => 'Wontia is built on a unified architecture that connects data, intelligence, decisions, and actions across any domain.',
        'layers' => [
            ['label' => 'WONTIA CORE', 'desc' => 'Platform foundation: authentication, permissions, tenants, integrations, APIs', 'color' => '#9B8CDE'],
            ['label' => 'TIA CORE', 'desc' => 'Intelligence engine: understanding, reasoning, learning, context awareness', 'color' => '#B89EFF'],
            ['label' => 'DOMAIN INTELLIGENCE', 'desc' => 'Domain-specific models, ontologies, rules, and knowledge bases', 'color' => '#CFE6FF'],
            ['label' => 'TOOLS · DATA · SYSTEMS', 'desc' => 'Connectors, APIs, databases, third-party services, ERP, CRM', 'color' => '#D9F2E2'],
            ['label' => 'DECISION ENGINE', 'desc' => 'Analysis, prioritization, risk assessment, recommendation logic', 'color' => '#F7E8C8'],
            ['label' => 'ACTION ORCHESTRATION', 'desc' => 'Workflow execution, multi-step automation, human-in-the-loop verification', 'color' => '#DCCFFF'],
            ['label' => 'MEASURABLE OUTCOMES', 'desc' => 'KPIs, impact metrics, audit logs, continuous improvement feedback loop', 'color' => '#00B87D'],
        ],
    ])],

    ['trust', 'trust', 'Trust & Control', json_encode([
        'headline' => 'Intelligence you control. Actions you authorize.',
        'subtitle' => 'Enterprise-grade governance built into every layer. TIA recommends, humans approve, the platform executes — with full transparency and auditability.',
        'flow_steps' => [
            ['step' => 'RECOMMEND', 'desc' => 'TIA analyzes context and proposes the optimal action with supporting rationale and confidence score.'],
            ['step' => 'APPROVE', 'desc' => 'Human reviews, adjusts parameters if needed, and authorizes execution within defined boundaries.'],
            ['step' => 'EXECUTE', 'desc' => 'Wontia orchestrates the approved action across systems, verifying each step and logging everything.'],
        ],
        'pillars' => [
            ['title' => 'Permission-Based Access', 'desc' => 'Role-based permissions at every level. Define who can view, recommend, approve, and execute.', 'icon' => '&#x1F512;', 'bg' => '#EDE9FE'],
            ['title' => 'Policy Enforcement', 'desc' => 'Business rules and compliance policies automatically enforced before any action is executed.', 'icon' => '&#x1F4DC;', 'bg' => '#E8F2FE'],
            ['title' => 'Full Audit Trail', 'desc' => 'Every recommendation, approval, and execution is logged with timestamp, user, and context.', 'icon' => '&#x1F4CB;', 'bg' => '#E6F5EC'],
            ['title' => 'Transparent Reasoning', 'desc' => 'TIA explains why it recommends each action. No black boxes. Complete decision traceability.', 'icon' => '&#x1F50D;', 'bg' => '#FEF3C7'],
        ],
    ])],

    ['future-vision', 'future-vision', 'Future Vision', json_encode([
        'headline' => 'The intelligence layer remains consistent. The domain context changes.',
        'subtitle' => 'WONTIA and TIA form a universal intelligence core. Around it, an expanding ecosystem of domain-specific applications — each with its own tools, data, workflows, and actions. Same intelligence. Different outcomes.',
        'domains' => [
            ['name' => 'Wontia Business', 'desc' => 'Customer, Sales & Operations Intelligence', 'status' => 'available', 'icon' => '&#x1F4BC;'],
            ['name' => 'Wontia Food Security', 'desc' => 'Applied Intelligence for Food Security', 'status' => 'development', 'icon' => '&#x1F4E6;'],
            ['name' => 'Wontia Health', 'desc' => 'Health Intelligence Engine', 'status' => 'future', 'icon' => '&#x2764;'],
            ['name' => 'Wontia Agriculture', 'desc' => 'Agriculture Intelligence Engine', 'status' => 'future', 'icon' => '&#x2600;'],
            ['name' => 'Wontia Industry', 'desc' => 'Industrial Intelligence Engine', 'status' => 'future', 'icon' => '&#x2699;'],
            ['name' => 'Wontia Logistics', 'desc' => 'Supply Chain Intelligence', 'status' => 'future', 'icon' => '&#x1F69A;'],
            ['name' => 'Wontia Education', 'desc' => 'Learning Intelligence Engine', 'status' => 'future', 'icon' => '&#x1F4DA;'],
            ['name' => 'More Domains', 'desc' => 'Architecture designed for continuous expansion', 'status' => 'future', 'icon' => '&#x2795;'],
        ],
    ])],

    ['pricing', 'pricing', 'Pricing', json_encode([
        'title' => 'Precios',
        'subtitle' => 'Encuentra el plan que se adapta a tu equipo.',
        'ice_workspace' => '1',
    ])],

    ['cta', 'cta', 'CTA', json_encode([
        'title' => 'Deja que la inteligencia trabaje por ti',
        'description' => 'Wontia no es un CRM. Es una Applied Intelligence Platform que convierte cada oportunidad en un resultado medible.',
        'button_text' => 'Ingresar a Wontia',
        'button_url' => 'https://app.wontia.com/login',
    ])],

    ['footer', 'footer', 'Footer', json_encode([
        'brand_name' => 'WONTIA',
        'powered_by' => 'Intsolcom, LLC',
        'copyright' => 'Intsolcom, LLC. Todos los derechos reservados.',
        'address_us' => '390 NE 191st St, STE 17284, Miami, FL 33179',
        'phone_us' => '+1 (786) 386-1515',
        'email_us' => 'customer@intsolcom.com',
        'address_co' => 'Cra 53 # 80 - 192, Barranquilla, Colombia',
        'phone_co' => '+57 311 602 0005',
        'email_co' => 'cliente@intsolcom.com',
    ])],
];

$stmt = $pdo->prepare("INSERT INTO sections (page_id, type, widget_type, title, config, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
foreach ($bricks as $i => $b) {
    $stmt->execute([$pageId, $b[0], $b[1], $b[2], $b[3], $i]);
}

echo "Seeded " . count($bricks) . " BRICK widgets. Page ID: $pageId\n";
