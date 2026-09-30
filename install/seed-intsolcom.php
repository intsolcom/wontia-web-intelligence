<?php
require '/app/vendor/autoload.php';
$db = App\Core\Database::instance();

function cols($db, $table) {
    $out = [];
    foreach ($db->query("SHOW COLUMNS FROM `$table`") as $r) $out[] = $r['Field'];
    return $out;
}
$pageCols = cols($db, 'pages');
$secCols  = cols($db, 'sections');

function insSection($db, $secCols, $pageId, $widget, $title, $config, $sort) {
    $c = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $vals = [
        'page_id' => $pageId, 'type' => 'widget', 'widget_type' => $widget,
        'title' => $title, 'subtitle' => '', 'content' => '',
        'config' => $c, 'sort_order' => $sort, 'is_active' => 1,
    ];
    $fields = array_intersect(array_keys($vals), $secCols);
    $keys = implode(',', $fields);
    $ph = implode(',', array_fill(0, count($fields), '?'));
    $db->prepare("INSERT INTO sections ($keys) VALUES ($ph)")->execute(array_map(fn($k) => $vals[$k], $fields));
}

function insHtml($db, $secCols, $pageId, $content, $sort) {
    $vals = ['page_id' => $pageId, 'type' => 'html', 'widget_type' => null, 'title' => '', 'subtitle' => '', 'content' => $content, 'config' => '{}', 'sort_order' => $sort, 'is_active' => 1];
    $fields = array_intersect(array_keys($vals), $secCols);
    $keys = implode(',', $fields);
    $ph = implode(',', array_fill(0, count($fields), '?'));
    $db->prepare("INSERT INTO sections ($keys) VALUES ($ph)")->execute(array_map(fn($k) => $vals[$k], $fields));
}

function ensurePage($db, $pageCols, $slug, $title, $sort) {
    $st = $db->prepare("SELECT id FROM pages WHERE site_id = 13 AND slug = ?");
    $st->execute([$slug]);
    $id = $st->fetchColumn();
    if ($id) {
        $db->prepare("UPDATE pages SET title = ?, status = 'published', sort_order = ? WHERE id = ?")->execute([$title, $sort, (int)$id]);
    } else {
        $db->prepare("INSERT INTO pages (site_id, title, slug, template, status, sort_order) VALUES (13, ?, ?, 'intsolcom', 'published', ?)")->execute([$title, $slug, $sort]);
        $id = $db->lastInsertId();
    }
    $db->prepare("DELETE FROM sections WHERE page_id = ?")->execute([(int)$id]);
    return (int)$id;
}

$footer = [
    'brand_name' => 'INTSOLCOM',
    'powered_by' => 'Technology Holding • United States & Colombia',
    'copyright' => '© 2026 INTSOLCOM, LLC. All rights reserved.',
    'address_us' => '390 NE 191st St, STE 17284, Miami, FL 33179',
    'phone_us' => '+1 (786) 386-1515',
    'email_us' => 'contact@intsolcom.com',
    'address_co' => 'Carrera 53 #79-01, Barranquilla, Colombia',
    'phone_co' => '',
    'email_co' => '',
    'solutions' => [
        ['name' => 'WONTIA IA Annotation Suite', 'url' => '/technology/wontia-ia-annotation-suite', 'status' => 'available'],
        ['name' => 'WONTIA AIP', 'url' => '/technology/wontia-aip', 'status' => 'available'],
        ['name' => 'WONTIA Food Security', 'url' => '/technology/wontia-food-security', 'status' => 'available'],
    ],
];

$done = [];

// ── TECHNOLOGY ──
$id = ensurePage($db, $pageCols, 'technology', 'Technology', 10);
insSection($db, $secCols, $id, 'hero', 'Products', [
    'badge_text' => 'Products', 'title' => 'Technology <span class="gradient-text">Portfolio</span>',
    'subtitle' => 'Software platforms and AI products built for enterprise.',
    'cta_primary_text' => 'Request a Demo', 'cta_primary_url' => '/contact', 'cta_secondary_text' => '', 'cta_secondary_url' => '',
], 0);
insSection($db, $secCols, $id, 'features', 'Technology & Products', [
    'title' => 'Technology products built for business impact',
    'cards' => [
        ['title' => 'WONTIA IA ANNOTATION SUITE', 'desc' => 'AI data annotation at scale. Manage projects, verify quality, and measure your annotation teams.'],
        ['title' => 'WONTIA AIP', 'desc' => 'Your intelligence layer. WONTIA AIP powers every WONTIA product with TIA — Technology of Applied Intelligence. Understand context, make decisions, execute actions.'],
        ['title' => 'WONTIA FOOD SECURITY', 'desc' => 'Applied intelligence for food security. Detect risk, prioritize response, coordinate action, and measure impact.'],
    ],
], 1);
insSection($db, $secCols, $id, 'features', 'The WONTIA Ecosystem', [
    'title' => 'One intelligence. Three products.',
    'cards' => [
        ['title' => 'WONTIA IA ANNOTATION SUITE', 'desc' => 'AI data annotation at scale — with its own landing page at iaam.com.'],
        ['title' => 'WONTIA AIP', 'desc' => 'Applied Intelligence System powered by TIA. Visit wontia.com.'],
        ['title' => 'WONTIA FOOD SECURITY', 'desc' => 'Applied intelligence for food security: detect, prioritize, coordinate, measure.'],
    ],
], 2);
insSection($db, $secCols, $id, 'cta', '', ['title' => 'Ready to see our technology in action?', 'description' => 'Schedule a demo with our team and discover how our platforms can transform your operations.', 'button_text' => 'Request a Demo', 'button_url' => '/contact'], 3);
insSection($db, $secCols, $id, 'footer', '', $footer, 4);
$done[] = 'technology';

// ── BUSINESS UNITS ──
$id = ensurePage($db, $pageCols, 'business-units', 'Business Units', 20);
insSection($db, $secCols, $id, 'hero', 'Business Units', [
    'badge_text' => 'Business Units', 'title' => 'Specialized divisions within the <span class="gradient-text">Intsolcom ecosystem.</span>',
    'subtitle' => 'Each business unit serves a distinct function — from operational delivery in Colombia to commercial service brands.',
    'cta_primary_text' => 'Contact', 'cta_primary_url' => '/contact', 'cta_secondary_text' => '', 'cta_secondary_url' => '',
], 0);
insSection($db, $secCols, $id, 'features', 'Our Units', [
    'title' => 'One ecosystem, specialized units',
    'cards' => [
        ['title' => 'INTSOLCOM SAS — Colombia', 'desc' => 'Operational delivery hub in Barranquilla. Nearshore technology services, BPO operations, AI data annotation, QA, and talent management.'],
        ['title' => 'Technology & Products', 'desc' => 'WONTIA AIP, WONTIA Food Security, and WONTIA IA Annotation Suite — owned and operated software platforms.'],
        ['title' => 'Business Development — USA', 'desc' => 'Strategic commercial presence in the United States: partnerships, international sales, and innovation management.'],
    ],
], 1);
insSection($db, $secCols, $id, 'cta', '', ['title' => 'Ready to work with the Intsolcom ecosystem?', 'description' => 'Let\'s discuss how INTSOLCOM can accelerate your growth through technology and operational excellence.', 'button_text' => 'Start a Conversation', 'button_url' => '/contact'], 2);
insSection($db, $secCols, $id, 'footer', '', $footer, 3);
$done[] = 'business-units';

// ── INDUSTRIES ──
$id = ensurePage($db, $pageCols, 'industries', 'Industries', 30);
insSection($db, $secCols, $id, 'hero', 'Industries', [
    'badge_text' => 'Industries', 'title' => 'Enterprise solutions <span class="gradient-text">across sectors</span>',
    'subtitle' => 'Our technology and operational expertise serves organizations across diverse industries.',
    'cta_primary_text' => 'Contact', 'cta_primary_url' => '/contact', 'cta_secondary_text' => '', 'cta_secondary_url' => '',
], 0);
insSection($db, $secCols, $id, 'features', 'Industries', [
    'title' => 'Industries we serve',
    'cards' => [
        ['title' => 'Healthcare', 'desc' => 'Technology and operations for health organizations.'],
        ['title' => 'Technology', 'desc' => 'Partners for software and AI companies.'],
        ['title' => 'Financial Services', 'desc' => 'Nearshore teams and intelligent operations.'],
        ['title' => 'AI & Data', 'desc' => 'Annotation and data operations at scale.'],
        ['title' => 'Retail', 'desc' => 'Customer operations and intelligent workflows.'],
        ['title' => 'Logistics', 'desc' => 'Operational support and process automation.'],
        ['title' => 'Real Estate', 'desc' => 'Back-office and sales operations.'],
        ['title' => 'Professional Services', 'desc' => 'Executive support and process excellence.'],
        ['title' => 'Manufacturing', 'desc' => 'QA, support, and data services.'],
        ['title' => 'Hospitality', 'desc' => 'Customer service and reservations support.'],
    ],
], 1);
insSection($db, $secCols, $id, 'cta', '', ['title' => 'Ready to work with the Intsolcom ecosystem?', 'description' => 'Let\'s discuss how INTSOLCOM can accelerate your growth through technology and operational excellence.', 'button_text' => 'Start a Conversation', 'button_url' => '/contact'], 2);
insSection($db, $secCols, $id, 'footer', '', $footer, 3);
$done[] = 'industries';

// ── RESOURCES ──
$id = ensurePage($db, $pageCols, 'resources', 'Resources', 40);
insSection($db, $secCols, $id, 'hero', 'Insights', [
    'badge_text' => 'Resources', 'title' => 'Insights & <span class="gradient-text">Resources</span>',
    'subtitle' => 'Articles, whitepapers, and guides from the Intsolcom ecosystem.',
    'cta_primary_text' => 'Contact', 'cta_primary_url' => '/contact', 'cta_secondary_text' => '', 'cta_secondary_url' => '',
], 0);
insSection($db, $secCols, $id, 'features', 'Resources', [
    'title' => 'Knowledge from our ecosystem',
    'cards' => [
        ['title' => 'Articles', 'desc' => 'Perspectives on AI, nearshore operations, and technology products.'],
        ['title' => 'Guides', 'desc' => 'Spec Driven Development and operational best practices.'],
        ['title' => 'Blog', 'desc' => 'Latest from the INTSOLCOM blog.'],
    ],
], 1);
insSection($db, $secCols, $id, 'cta', '', ['title' => 'Ready to work with the Intsolcom ecosystem?', 'description' => 'Let\'s discuss how INTSOLCOM can accelerate your growth through technology and operational excellence.', 'button_text' => 'Start a Conversation', 'button_url' => '/contact'], 2);
insSection($db, $secCols, $id, 'footer', '', $footer, 3);
$done[] = 'resources';

// ── NEARSHORE DEVELOPMENT ──
$id = ensurePage($db, $pageCols, 'nearshore-development', 'Nearshore Development', 50);
insSection($db, $secCols, $id, 'hero', 'Nearshore Development', [
    'badge_text' => 'Nearshore Hub', 'title' => 'Build software with <span class="gradient-text">professional methodology.</span>',
    'subtitle' => 'Dedicated development teams operating from Barranquilla, Colombia. Same timezone as the US. Bilingual engineers. Spec Driven Development. No freelancers. Real product engineering.',
    'cta_primary_text' => 'Build Your Team', 'cta_primary_url' => 'https://marcasbpo.com/buildyourteam', 'cta_secondary_text' => 'Talk to Us', 'cta_secondary_url' => '/contact',
], 0);
insSection($db, $secCols, $id, 'wwi-steps', 'Methodology', [
    'title' => 'Spec Driven Development. Explained simply.',
    'subtitle' => 'Before a single line of code is written, we define exactly what you need. You validate. We build. No surprises.',
    'steps' => [
        ['title' => 'SPEC', 'desc' => 'We define together EXACTLY what you need. Zero ambiguity. You approve before any code is written.'],
        ['title' => 'DESIGN', 'desc' => 'Architecture, UX, UI — everything designed first. You see mockups, not promises.'],
        ['title' => 'DEVELOP', 'desc' => 'The team builds against the specification. No scope creep. No surprises.'],
        ['title' => 'TEST', 'desc' => 'Every feature validated against what you approved. Nothing ships without testing.'],
        ['title' => 'DEPLOY', 'desc' => 'Published on your infrastructure or ours. You decide.'],
        ['title' => 'OPTIMIZE', 'desc' => 'Continuous improvement based on real usage data. We don\'t disappear after launch.'],
    ],
], 1);
insSection($db, $secCols, $id, 'features', 'Why Colombia', [
    'title' => 'Colombia. The development hub for the Americas.',
    'cards' => [
        ['title' => 'EST Timezone', 'desc' => 'Your team works when you work. Daily standups at 9 AM your time.'],
        ['title' => 'Truly Bilingual', 'desc' => 'C1-C2 English. Real communication with your stakeholders.'],
        ['title' => 'Pre-Vetted Talent', 'desc' => 'We present engineers who passed our technical assessment.'],
        ['title' => 'Cultural Fit', 'desc' => 'Colombia shares a work culture with the US. Zero cultural friction.'],
        ['title' => 'Cost Efficient', 'desc' => '60-70% less than equivalent US-based teams. Same quality, better economics.'],
        ['title' => '3 Hours from Miami', 'desc' => 'Direct flights. Visit your team whenever you want.'],
    ],
], 2);
insSection($db, $secCols, $id, 'cta', '', ['title' => 'Ready to build with a professional nearshore team?', 'description' => 'Schedule a 15-minute call. We will review your project and prepare a free spec review. No commitment.', 'button_text' => 'Schedule a Call', 'button_url' => '/contact'], 3);
insSection($db, $secCols, $id, 'footer', '', $footer, 4);
$done[] = 'nearshore-development';

// ── HOLDING ──
$id = ensurePage($db, $pageCols, 'holding', 'Holding', 60);
insSection($db, $secCols, $id, 'hero', 'Ecosystem', [
    'badge_text' => 'Business Ecosystem', 'title' => 'The <span class="gradient-text">Intsolcom</span> Business Ecosystem',
    'subtitle' => 'Two entities, one ecosystem. Strategic business development in the United States. Operational delivery in Colombia.',
    'cta_primary_text' => 'Start a Conversation', 'cta_primary_url' => '/contact', 'cta_secondary_text' => '', 'cta_secondary_url' => '',
], 0);
insSection($db, $secCols, $id, 'features', 'Mission & Vision', [
    'title' => 'Mission & Vision',
    'cards' => [
        ['title' => 'Mission', 'desc' => 'Build technology products and operate business services that transform how companies work — combining strategic presence in the United States with operational excellence in Colombia.'],
        ['title' => 'Vision', 'desc' => 'Be the leading business ecosystem bridging U.S. strategic capabilities with Colombian operational excellence.'],
        ['title' => 'Business Model', 'desc' => 'Two pillars, one ecosystem: Business Operations (USA + Colombia) and Technology & Products (WONTIA AIP, WONTIA Food Security, WONTIA IA Annotation Suite).'],
    ],
], 1);
insSection($db, $secCols, $id, 'cta', '', ['title' => 'Ready to work with the Intsolcom ecosystem?', 'description' => 'Let\'s discuss how the Intsolcom ecosystem can accelerate your growth through technology products and operational capabilities.', 'button_text' => 'Start a Conversation', 'button_url' => '/contact'], 2);
insSection($db, $secCols, $id, 'footer', '', $footer, 3);
$done[] = 'holding';

// ── CONTACT ──
$id = ensurePage($db, $pageCols, 'contact', 'Contact', 70);
insSection($db, $secCols, $id, 'hero', 'Contact', [
    'badge_text' => 'Contact', 'title' => 'Let\'s <span class="gradient-text">talk</span>',
    'subtitle' => 'Partner with the Intsolcom ecosystem.',
    'cta_primary_text' => '', 'cta_primary_url' => '', 'cta_secondary_text' => '', 'cta_secondary_url' => '',
], 0);
insSection($db, $secCols, $id, 'wwi-contact', 'Contact', [
    'title' => 'Contact us',
    'subtitle' => 'Partner with a technology holding that delivers. We respond the same day.',
    'email' => 'contact@intsolcom.com', 'email_label' => 'Email',
    'phone' => '+1 (786) 386-1515', 'phone_label' => 'USA Phone',
    'whatsapp' => '', 'button_text' => '',
    'address' => '390 NE 191st St, STE 17284, Miami, FL 33179 • Carrera 53 #79-01, Barranquilla, Colombia',
    'address_label' => 'Offices (USA · Colombia)', 'map_embed' => '',
], 1);
insSection($db, $secCols, $id, 'cta', '', ['title' => 'Ready to work with the Intsolcom ecosystem?', 'description' => 'Let\'s discuss how INTSOLCOM can accelerate your growth through technology and operational excellence.', 'button_text' => 'Start a Conversation', 'button_url' => '/contact'], 2);
insSection($db, $secCols, $id, 'footer', '', $footer, 3);
$done[] = 'contact';

// ── PRIVACY ──
$id = ensurePage($db, $pageCols, 'privacy', 'Privacy Policy', 80);
insSection($db, $secCols, $id, 'hero', 'Privacy', [
    'badge_text' => 'Legal', 'title' => 'Privacy <span class="gradient-text">Policy</span>',
    'subtitle' => 'Last updated: January 2026', 'cta_primary_text' => '', 'cta_primary_url' => '', 'cta_secondary_text' => '', 'cta_secondary_url' => '',
], 0);
insHtml($db, $secCols, $id, '<h2>1. Information We Collect</h2><p>When you contact us through our website forms, we collect the information you voluntarily provide, including your name, email address, company name, phone number, and message content. We also collect standard web analytics data including page views, browser type, and referring URLs through standard server logs.</p><h2>2. How We Use Your Information</h2><p>We use the information you provide to: respond to your inquiries and provide the services you request; send relevant information about our products and services (with your consent); improve our website and user experience; and comply with legal obligations.</p><h2>3. Information Sharing</h2><p>We do not sell, trade, or rent your personal information to third parties. We may share information with our operational entities (including INTSOLCOM SAS in Colombia) solely for the purpose of delivering the services you have requested. We may disclose information when required by law or to protect our rights.</p><h2>4. Data Security</h2><p>We implement appropriate technical and organizational measures to protect your personal data against unauthorized access, alteration, disclosure, or destruction. Our website uses SSL/TLS encryption for all data transmission.</p><h2>5. Cookies</h2><p>Our website may use essential cookies for functionality. We do not use tracking cookies or third-party advertising networks. You can disable cookies in your browser settings.</p><h2>6. Your Rights</h2><p>You have the right to access, correct, or delete your personal information. You may also object to or restrict certain processing of your data. To exercise these rights, contact us at the email below.</p><h2>7. International Data Transfers</h2><p>As a company with operations in the United States and Colombia, your data may be transferred between these jurisdictions. We ensure appropriate safeguards are in place for any such transfers.</p><h2>8. Contact Us</h2><p>For privacy-related inquiries: INTSOLCOM LLC, 390 NE 191st St, STE 17284, Miami, FL 33179. Email: info@intsolcom.com</p>', 1);
insSection($db, $secCols, $id, 'footer', '', $footer, 2);
$done[] = 'privacy';

// ── TERMS ──
$id = ensurePage($db, $pageCols, 'terms', 'Terms of Service', 90);
insSection($db, $secCols, $id, 'hero', 'Terms', [
    'badge_text' => 'Legal', 'title' => 'Terms of <span class="gradient-text">Service</span>',
    'subtitle' => 'Last updated: January 2026', 'cta_primary_text' => '', 'cta_primary_url' => '', 'cta_secondary_text' => '', 'cta_secondary_url' => '',
], 0);
insHtml($db, $secCols, $id, '<h2>1. Acceptance of Terms</h2><p>By accessing or using the INTSOLCOM website (intsolcom.com) and any related services, you agree to be bound by these Terms of Service. If you do not agree, please do not use our website or services.</p><h2>2. Services Description</h2><p>INTSOLCOM LLC and INTSOLCOM SAS (collectively, "INTSOLCOM," "we," "us") provide technology products, software platforms, business process outsourcing, and nearshore development services. Detailed service descriptions and agreements are provided separately for each engagement.</p><h2>3. Intellectual Property</h2><p>All content on this website, including text, graphics, logos, images, and software, is the property of INTSOLCOM or its licensors and is protected by United States and international intellectual property laws. WONTIA AIP, WONTIA Food Security, WONTIA IA Annotation Suite, and Marcas BPO are trademarks of INTSOLCOM. All rights reserved.</p><h2>4. Use of Website</h2><p>You agree not to: use the website for any unlawful purpose; attempt to gain unauthorized access to our systems; interfere with the proper functioning of the website; scrape, data mine, or extract content without permission; or misrepresent your identity or affiliation.</p><h2>5. Limitation of Liability</h2><p>INTSOLCOM provides this website and its content on an "as is" basis. We make no warranties, express or implied, regarding the accuracy, completeness, or availability of the website. To the fullest extent permitted by law, INTSOLCOM shall not be liable for any indirect, incidental, special, or consequential damages arising from your use of this website.</p><h2>6. Third-Party Links</h2><p>Our website may contain links to third-party websites (such as marcasbpo.com). We are not responsible for the content or practices of these external sites.</p><h2>7. Governing Law</h2><p>These Terms shall be governed by and construed in accordance with the laws of the State of Delaware, United States, without regard to its conflict of law provisions.</p><h2>8. Changes to Terms</h2><p>We reserve the right to modify these Terms at any time. Changes will be effective immediately upon posting. Continued use of the website constitutes acceptance of the modified Terms.</p><h2>9. Contact</h2><p>For questions about these Terms: INTSOLCOM LLC, 390 NE 191st St, STE 17284, Miami, FL 33179. Email: info@intsolcom.com</p>', 1);
insSection($db, $secCols, $id, 'footer', '', $footer, 2);
$done[] = 'terms';

// ── BLOG ──
$id = ensurePage($db, $pageCols, 'blog', 'Blog', 100);
insSection($db, $secCols, $id, 'hero', 'Insights', [
    'badge_text' => 'Blog', 'title' => 'Latest from <span class="gradient-text">our blog</span>',
    'subtitle' => 'Perspectives on AI, nearshore operations, and the WONTIA ecosystem.',
    'cta_primary_text' => '', 'cta_primary_url' => '', 'cta_secondary_text' => '', 'cta_secondary_url' => '',
], 0);
insSection($db, $secCols, $id, 'features', 'Blog', [
    'title' => 'Recent insights',
    'cards' => [
        ['title' => 'Spec Driven Development', 'desc' => 'Why every software project needs a spec first.'],
        ['title' => 'Nearshore vs Offshore', 'desc' => 'Why Colombia beats the alternatives for US companies.'],
        ['title' => 'From CRM to Applied Intelligence', 'desc' => 'The evolution of WONTIA into an AIS.'],
    ],
], 1);
insSection($db, $secCols, $id, 'footer', '', $footer, 2);
$done[] = 'blog';

// ── SITE CONFIG ──
$del = $db->prepare("DELETE FROM settings WHERE site_id = 13 AND `key` = ?");
$ins = $db->prepare("INSERT INTO settings (site_id, `key`, value) VALUES (13, ?, ?)");
$set = function ($k, $v) use ($del, $ins) {
    $del->execute([$k]);
    $ins->execute([$k, $v]);
};
$set('theme', 'intsolcom');
$set('wwi_brand_primary', '#00C896');
$set('site_name', 'INTSOLCOM');
$set('nav_items', json_encode([
    ['text' => 'Technology', 'url' => '/technology'],
    ['text' => 'Nearshore Dev', 'url' => '/nearshore-development'],
    ['text' => 'Business Units', 'url' => '/business-units'],
    ['text' => 'Contact', 'url' => '/contact', 'cta' => true],
], JSON_UNESCAPED_UNICODE));
$db->prepare("UPDATE sites SET theme = 'intsolcom' WHERE id = 13")->execute();

echo "SEED_DONE: " . implode(', ', $done) . "\n";
