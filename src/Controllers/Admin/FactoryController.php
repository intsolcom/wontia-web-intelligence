<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\FactoryService;

class FactoryController
{
    private FactoryService $service;

    public function __construct()
    {
        $this->service = new FactoryService();
    }

    private function requireSuper(): void
    {
        if (Session::userRole() !== 'superadmin') Response::error('Forbidden', 403);
    }

    public function dashboard(): void
    {
        $this->requireSuper();
        Response::json(['ok' => true, 'data' => $this->service->dashboard()]);
    }

    public function sites(): void
    {
        $this->requireSuper();
        Response::json(['ok' => true, 'data' => $this->service->sitesAll()]);
    }

    public function addSite(Request $request): void
    {
        $this->requireSuper();
        $result = $this->service->addSite($request->json());
        $result['ok'] ? Response::success($result, $result['message']) : Response::error($result['message']);
    }

    public function siteStatus(Request $request, $id): void
    {
        $this->requireSuper();
        $status = (string)($request->json()['status'] ?? '');
        $result = $this->service->updateSiteStatus((int)$id, $status);
        $result['ok'] ? Response::success(null, $result['message']) : Response::error($result['message']);
    }

    public function domains(): void
    {
        $this->requireSuper();
        Response::json(['ok' => true, 'data' => $this->service->domainsAll()]);
    }

    public function addDomain(Request $request): void
    {
        $this->requireSuper();
        $result = $this->service->addDomain($request->json());
        $result['ok'] ? Response::success($result, $result['message']) : Response::error($result['message']);
    }

    public function domainStatus(Request $request, $id): void
    {
        $this->requireSuper();
        $status = (string)($request->json()['status'] ?? '');
        $result = $this->service->updateDomainStatus((int)$id, $status);
        $result['ok'] ? Response::success(null, $result['message']) : Response::error($result['message']);
    }

    public function emails(): void
    {
        $this->requireSuper();
        Response::json(['ok' => true, 'data' => $this->service->emailsAll()]);
    }

    public function addEmail(Request $request): void
    {
        $this->requireSuper();
        $result = $this->service->addEmail($request->json());
        $result['ok'] ? Response::success($result, $result['message']) : Response::error($result['message']);
    }

    public function emailStatus(Request $request, $id): void
    {
        $this->requireSuper();
        $status = (string)($request->json()['status'] ?? '');
        $result = $this->service->updateEmailStatus((int)$id, $status);
        $result['ok'] ? Response::success(null, $result['message']) : Response::error($result['message']);
    }

    public function orders(): void
    {
        $this->requireSuper();
        Response::json(['ok' => true, 'data' => $this->service->ordersAll()]);
    }

    public function addOrder(Request $request): void
    {
        $this->requireSuper();
        $result = $this->service->addOrder($request->json());
        $result['ok'] ? Response::success($result, $result['message']) : Response::error($result['message']);
    }

    public function orderStatus(Request $request, $id): void
    {
        $this->requireSuper();
        $status = (string)($request->json()['status'] ?? '');
        $result = $this->service->transitionOrder((int)$id, $status);
        $result['ok'] ? Response::success(null, $result['message']) : Response::error($result['message']);
    }

    public function ledger(): void
    {
        $this->requireSuper();
        Response::json(['ok' => true, 'data' => $this->service->ledgerAll()]);
    }

    public function addLedger(Request $request): void
    {
        $this->requireSuper();
        $result = $this->service->addLedger($request->json());
        $result['ok'] ? Response::success($result, $result['message']) : Response::error($result['message']);
    }

    public function aiUsageBySite(): void
    {
        $this->requireSuper();
        Response::json(['ok' => true, 'data' => $this->service->aiUsageBySite()]);
    }

    public function myPortal(): void
    {
        Response::json(['ok' => true, 'data' => $this->service->myPortal()]);
    }

    public function publicTemplates(): void
    {
        $db = \App\Core\Database::instance();
        $cats = $db->query("SELECT slug, name_es, name_en, icon FROM wwi_template_categories WHERE site_id = @site_id ORDER BY sort_order ASC LIMIT 20")->fetchAll();
        $tpls = $db->query("SELECT t.category_id, c.slug AS category_slug, t.slug, t.name_es, t.name_en, t.status FROM wwi_templates t JOIN wwi_template_categories c ON c.id = t.category_id WHERE t.site_id = @site_id ORDER BY t.sort_order ASC LIMIT 100")->fetchAll();
        Response::json(['ok' => true, 'data' => ['categories' => $cats, 'templates' => $tpls]]);
    }

    public function publicCreateOrder(Request $request): void
    {
        $result = $this->service->createPublicOrder($request->json());
        if ($result['ok']) {
            Response::json(['ok' => true, 'message' => $result['message'], 'data' => [
                'uuid' => $result['uuid'],
                'order_id' => $result['id'],
                'total' => $result['total'],
                'currency' => $result['currency'],
                'status' => $result['status'],
                'plan_name' => $result['plan_name'],
            ]], 201);
        }
        Response::error($result['message'], 400);
    }

    public function publicOrderStatus(Request $request, $uuid): void
    {
        $order = $this->service->findOrderByUuid((string)$uuid);
        if (!$order) Response::error('Order not found', 404);
        Response::json(['ok' => true, 'data' => [
            'uuid' => $order['uuid'],
            'status' => $order['status'],
            'total' => (float)$order['total'],
            'currency' => $order['currency'],
            'plan_id' => (int)$order['plan_id'],
            'domain_name' => $order['domain_name'],
            'created_at' => $order['created_at'],
        ]]);
    }

    public function paymentWebhook(Request $request): void
    {
        $result = $this->service->processPaymentWebhook($request->json());
        if ($result['ok']) Response::json(['ok' => true, 'message' => $result['message']]);
        Response::error($result['message'], $result['status'] ?? 503);
    }

    public function publicPaymentMode(): void
    {
        Response::json(['ok' => true, 'data' => ['provider' => $this->service->paymentConfig()['provider']]]);
    }

    public function publicDummyPay(Request $request, $uuid): void
    {
        $result = $this->service->dummyPay((string)$uuid);
        if ($result['ok']) Response::json(['ok' => true, 'message' => $result['message']]);
        Response::error($result['message'], $result['status'] ?? 503);
    }

    public function publicCreateBrief(Request $request): void
    {
        $result = $this->service->createBrief($request->json());
        if ($result['ok']) Response::json(['ok' => true, 'message' => $result['message'], 'data' => ['id' => $result['id']]], 201);
        Response::error($result['message'], 400);
    }

    public function publicPreviewAttempts(Request $request): void
    {
        Response::json(['ok' => true, 'data' => $this->service->previewAttempts($request->ip())]);
    }

    public function publicCreatePreview(Request $request): void
    {
        $builder = Session::isLoggedIn() && in_array(Session::userRole(), ['superadmin', 'admin', 'editor'], true);
        $result = $this->service->createPreview((string)($request->json()['prompt'] ?? ''), $request->ip(), $builder);
        if ($result['ok']) Response::json(['ok' => true, 'message' => 'Preview generándose', 'data' => $result], 201);
        Response::json(['ok' => false, 'message' => $result['message'], 'data' => $result], $result['limit_reached'] ?? false ? 429 : 400);
    }

    public function publicPreviewStatus(Request $request, $uuid): void
    {
        $result = $this->service->previewStatus((string)$uuid);
        if (!$result['ok']) Response::error($result['message'], 404);
        Response::json(['ok' => true, 'data' => $result['data']]);
    }

    public function publicPreviewRender(Request $request, $uuid): void
    {
        $preview = $this->service->previewByUuid((string)$uuid);
        if (!$preview || $preview['status'] !== 'ready') {
            Response::error('Preview not found or not ready', 404);
        }
        $s = $preview['structure'] ?? [];
        $name = $this->escHtml((string)($s['business_name'] ?? 'Mi sitio'));
        $tagline = $this->escHtml((string)($s['tagline'] ?? ''));
        $colors = is_array($s['colors'] ?? null) ? $s['colors'] : [];
        $primary = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($colors['primary'] ?? '')) ? $colors['primary'] : '#2563eb';
        $secondary = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($colors['secondary'] ?? '')) ? $colors['secondary'] : '#1e3a8a';
        $nav = is_array($s['nav'] ?? null) ? $s['nav'] : ['Inicio', 'Servicios', 'Contacto'];
        $hero = is_array($s['hero'] ?? null) ? $s['hero'] : [];
        $valueProp = is_array($s['value_prop'] ?? null) ? $s['value_prop'] : [];
        $services = is_array($s['services'] ?? null) ? $s['services'] : [];
        $benefits = is_array($s['benefits'] ?? null) ? $s['benefits'] : [];
        $testimonials = is_array($s['testimonials'] ?? null) ? $s['testimonials'] : [];
        $cta = is_array($s['cta'] ?? null) ? $s['cta'] : [];
        $contact = is_array($s['contact'] ?? null) ? $s['contact'] : [];

        $navHtml = '';
        foreach ($nav as $item) {
            $navHtml .= '<a href="#' . $this->escHtml(md5((string)$item)) . '">' . $this->escHtml((string)$item) . '</a>';
        }
        $valueHtml = '';
        foreach ($valueProp as $v) {
            $valueHtml .= '<li>' . $this->escHtml((string)$v) . '</li>';
        }
        $servicesHtml = '';
        foreach ($services as $sv) {
            if (!is_array($sv)) continue;
            $servicesHtml .= '<article class="card"><h3>' . $this->escHtml((string)($sv['title'] ?? '')) . '</h3><p>' . $this->escHtml((string)($sv['desc'] ?? '')) . '</p></article>';
        }
        $benefitsHtml = '';
        foreach ($benefits as $b) {
            if (!is_array($b)) continue;
            $benefitsHtml .= '<div class="benefit"><span class="benefit-ic">✓</span><div><strong>' . $this->escHtml((string)($b['title'] ?? '')) . '</strong><p>' . $this->escHtml((string)($b['desc'] ?? '')) . '</p></div></div>';
        }
        $testimHtml = '';
        foreach ($testimonials as $t) {
            if (!is_array($t)) continue;
            $testimHtml .= '<blockquote><p>"' . $this->escHtml((string)($t['quote'] ?? '')) . '"</p><footer>— ' . $this->escHtml((string)($t['author'] ?? 'Cliente')) . '</footer></blockquote>';
        }
        $css = ":root{--p:$primary;--s:$secondary;--txt:#0f172a;--mut:#5b6b84;--bg:#ffffff;--bg2:#f6f8fb;--bd:#e5e9f2}"
            . "*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Inter',system-ui,sans-serif;color:var(--txt);background:var(--bg);line-height:1.6}"
            . ".pv-band{position:sticky;top:0;z-index:60;background:linear-gradient(90deg,var(--s),var(--p));color:#fff;font-size:11px;font-weight:700;text-align:center;padding:7px 10px;letter-spacing:.05em}"
            . "header.site{display:flex;align-items:center;justify-content:space-between;padding:14px 6%;border-bottom:1px solid var(--bd);position:sticky;top:26px;background:rgba(255,255,255,.95);backdrop-filter:blur(8px)}"
            . "header.site .logo{font-weight:800;letter-spacing:-.02em}header.site nav{display:flex;gap:18px}header.site nav a{color:var(--mut);text-decoration:none;font-size:13px}header.site nav a:hover{color:var(--p)}"
            . ".cta-btn{display:inline-block;background:var(--p);color:#fff;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none}.cta-btn.alt{background:transparent;border:1px solid var(--p);color:var(--p)}"
            . "section{padding:56px 6%}.hero{background:linear-gradient(160deg,var(--bg2),#fff);text-align:center;padding:84px 6%}"
            . ".hero .eyebrow{color:var(--p);font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.1em;margin-bottom:12px}"
            . ".hero h1{font-size:clamp(28px,4.5vw,46px);font-weight:800;letter-spacing:-.02em;max-width:760px;margin:0 auto 14px}"
            . ".hero p{color:var(--mut);max-width:620px;margin:0 auto 24px;font-size:16px}.hero .ctas{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}"
            . "h2.sec{font-size:24px;font-weight:800;letter-spacing:-.01em;text-align:center;margin-bottom:10px}"
            . ".sec-sub{text-align:center;color:var(--mut);max-width:560px;margin:0 auto 32px;font-size:14px}"
            . ".value{max-width:640px;margin:0 auto;padding-left:20px}.value li{margin-bottom:8px;color:#334155}"
            . ".grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}"
            . ".card{background:#fff;border:1px solid var(--bd);border-radius:12px;padding:22px}.card h3{font-size:16px;margin-bottom:8px}.card p{color:var(--mut);font-size:13.5px}"
            . ".about{max-width:720px;margin:0 auto;color:#334155;font-size:15px}"
            . ".benefits{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px}"
            . ".benefit{display:flex;gap:12px;align-items:flex-start;background:var(--bg2);border:1px solid var(--bd);border-radius:12px;padding:16px}"
            . ".benefit-ic{min-width:26px;height:26px;border-radius:50%;background:var(--p);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700}"
            . ".benefit strong{font-size:14px}.benefit p{color:var(--mut);font-size:12.5px;margin-top:3px}"
            . ".testimonials{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px}"
            . "blockquote{background:#fff;border:1px solid var(--bd);border-left:3px solid var(--p);border-radius:10px;padding:18px}blockquote p{font-size:14px;color:#334155}blockquote footer{margin-top:10px;font-size:12px;color:var(--mut);font-weight:600}"
            . ".cta-banner{background:linear-gradient(120deg,var(--s),var(--p));border-radius:16px;color:#fff;text-align:center;padding:48px 6%}"
            . ".cta-banner h2{font-size:26px;font-weight:800;margin-bottom:8px}.cta-banner p{opacity:.9;margin-bottom:20px}.cta-banner .cta-btn{background:#fff;color:var(--p)}"
            . ".contact{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;text-align:center}"
            . ".contact .ci{background:var(--bg2);border:1px solid var(--bd);border-radius:12px;padding:18px}.contact .ci b{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--p);margin-bottom:6px}.contact .ci span{font-size:13px;color:#334155}"
            . "footer.site{border-top:1px solid var(--bd);padding:28px 6%;text-align:center;color:var(--mut);font-size:12px}"
            . "@media(max-width:640px){header.site nav{display:none}}";

        Response::html('<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>' . $name . ' — Vista previa</title>'
            . '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">'
            . '<style>' . $css . '</style></head><body>'
            . '<div class="pv-band">VISTA PREVIA TEMPORAL — expira en 60 minutos · contenido de ejemplo · no es tu sitio final</div>'
            . '<header class="site"><div class="logo">' . $name . '</div><nav>' . $navHtml . '</nav><a href="#contacto" class="cta-btn">Contáctanos</a></header>'
            . '<main>'
            . '<section class="hero" id="inicio">'
            . ($hero['eyebrow'] ?? '' ? '<div class="eyebrow">' . $this->escHtml((string)$hero['eyebrow']) . '</div>' : '')
            . '<h1>' . $this->escHtml((string)($hero['title'] ?? $name)) . '</h1>'
            . '<p>' . $this->escHtml((string)($hero['subtitle'] ?? $tagline)) . '</p>'
            . '<div class="ctas"><a href="#contacto" class="cta-btn">Contáctanos</a><a href="https://wa.me/000" class="cta-btn alt">WhatsApp</a></div></section>'
            . ($valueProp ? '<section id="valor"><h2 class="sec">¿Por qué elegirnos?</h2><ul class="value">' . $valueHtml . '</ul></section>' : '')
            . ($services ? '<section id="servicios" style="background:var(--bg2)"><h2 class="sec">Servicios</h2><p class="sec-sub">' . $tagline . '</p><div class="grid">' . $servicesHtml . '</div></section>' : '')
            . ($s['about'] ?? '' ? '<section id="nosotros"><h2 class="sec">Sobre nosotros</h2><p class="about">' . $this->escHtml((string)$s['about']) . '</p></section>' : '')
            . ($benefits ? '<section id="beneficios" style="background:var(--bg2)"><h2 class="sec">Beneficios</h2><div class="benefits">' . $benefitsHtml . '</div></section>' : '')
            . ($testimonials ? '<section id="testimonios"><h2 class="sec">Lo que dicen de nosotros</h2><p class="sec-sub">Testimonios de ejemplo</p><div class="testimonials">' . $testimHtml . '</div></section>' : '')
            . ($cta ? '<section><div class="cta-banner"><h2>' . $this->escHtml((string)($cta['title'] ?? '¿Listo para empezar?')) . '</h2><p>' . $this->escHtml((string)($cta['subtitle'] ?? '')) . '</p><a href="#contacto" class="cta-btn">Contáctanos</a></div></section>' : '')
            . '<section id="contacto"><h2 class="sec">Contacto</h2><div class="contact">'
            . '<div class="ci"><b>Teléfono</b><span>' . $this->escHtml((string)($contact['phone'] ?? '{{TELEFONO}}')) . '</span></div>'
            . '<div class="ci"><b>Email</b><span>' . $this->escHtml((string)($contact['email'] ?? '{{EMAIL}}')) . '</span></div>'
            . '<div class="ci"><b>Dirección</b><span>' . $this->escHtml((string)($contact['address'] ?? '{{DIRECCION}}')) . '</span></div>'
            . '<div class="ci"><b>WhatsApp</b><span>' . $this->escHtml((string)($contact['whatsapp'] ?? '{{WHATSAPP}}')) . '</span></div>'
            . '</div></section></main>'
            . '<footer class="site">' . $this->escHtml((string)($s['footer_note'] ?? ($name . ' — sitio generado por TIA · Wontia Web Intelligence'))) . '</footer>'
            . '</body></html>');
    }

    public function publicSuggestDomains(Request $request): void
    {
        $result = $this->service->suggestDomains((string)($request->json()['business'] ?? ''));
        if ($result['ok']) Response::json(['ok' => true, 'data' => $result['domains']]);
        Response::error($result['message'], 400);
    }

    public function publicPreviewFromTemplate(Request $request): void
    {
        $result = $this->service->createTemplatePreview((string)($request->json()['template'] ?? ''));
        if ($result['ok']) Response::json(['ok' => true, 'message' => 'Preview de plantilla listo', 'data' => $result], 201);
        Response::error($result['message'], 404);
    }

    public function siteCredentials(Request $request, $id): void
    {
        $this->requireSuper();
        $result = $this->service->resetClientPassword((int)$id);
        if ($result['ok']) Response::json(['ok' => true, 'message' => 'Credenciales regeneradas', 'data' => $result]);
        Response::error($result['message'], 404);
    }

    private function escHtml(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    public function briefsList(): void
    {
        $this->requireSuper();
        Response::json(['ok' => true, 'data' => $this->service->briefs()]);
    }

    public function jobsList(): void
    {
        $this->requireSuper();
        Response::json(['ok' => true, 'data' => $this->service->jobsList()]);
    }

    public function jobsRun(): void
    {
        $this->requireSuper();
        Response::json($this->service->runDueJobs(5));
    }

    public function plans(): void
    {
        Response::json(['ok' => true, 'data' => $this->service->plans()]);
    }

    public function savePlan(Request $request, $id = null): void
    {
        $result = $this->service->savePlan($request->json(), $id ? (int)$id : null);
        $result['ok'] ? Response::success($result, $result['message']) : Response::error($result['message']);
    }

    public function deletePlan(Request $request, $id): void
    {
        Response::success($this->service->deletePlan((int)$id));
    }

    public function config(): void
    {
        Response::json(['ok' => true, 'data' => $this->service->config()]);
    }

    public function saveConfig(Request $request): void
    {
        $result = $this->service->saveConfig($request->json());
        $result['ok'] ? Response::success(null, $result['message']) : Response::error($result['message']);
    }

    public function margin(): void
    {
        Response::json(['ok' => true, 'data' => $this->service->marginReport()]);
    }

    public function publicPlans(Request $request): void
    {
        $plans = array_values(array_filter($this->service->plans(), fn($p) => (int)$p['is_active'] === 1));
        Response::json(['ok' => true, 'data' => $plans]);
    }

    public function publicPlan(Request $request, $slug): void
    {
        foreach ($this->service->plans() as $plan) {
            if ($plan['slug'] === $slug && (int)$plan['is_active'] === 1) {
                Response::json(['ok' => true, 'data' => $plan]);
                return;
            }
        }
        Response::error('Plan not found', 404);
    }

    public function ensureTables(): void
    {
        Response::json($this->service->ensureTables());
    }
}
