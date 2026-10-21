<?php
namespace App\Widgets;

use App\Core\Lang;

class ProgramLandingWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'program-landing', 'name' => 'INTSOLCOM: Program Landing', 'icon' => 'rocket', 'category' => 'content', 'version' => '2.1.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'application_url', 'label' => 'Application URL (Marcas BPO)', 'type' => 'text', 'default' => 'REPLACE_WITH_MARCAS_BPO_APPLICATION_URL'],
            ['key' => 'deadline', 'label' => 'Application deadline (YYYY-MM-DD)', 'type' => 'text', 'default' => '2026-11-15'],
            ['key' => 'applications_base', 'label' => 'Base applications', 'type' => 'text', 'default' => '3'],
            ['key' => 'applications_daily', 'label' => 'Applications added per day', 'type' => 'text', 'default' => '3'],
            ['key' => 'applications_max', 'label' => 'Applications cap', 'type' => 'text', 'default' => '30'],
            ['key' => 'campaign_start', 'label' => 'Campaign start (YYYY-MM-DD)', 'type' => 'text', 'default' => '2026-10-20'],
            ['key' => 'countries', 'label' => 'Countries (comma separated, in order)', 'type' => 'text', 'default' => 'United States, Argentina, Brazil, Spain, United Kingdom, Canada, Panama, Ecuador, El Salvador, Costa Rica'],
        ];
    }

    private function settings(string $key, string $default): string
    {
        try {
            $st = \App\Core\Database::instance()->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = ? LIMIT 1");
            $st->execute([$key]);
            $v = $st->fetchColumn();
            if ($v !== false && $v !== null && $v !== '') return (string)$v;
        } catch (\Throwable $e) {
        }
        return $default;
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $e = fn($s) => $this->esc((string)$s);
        $es = Lang::current() === 'es';

        $applyUrl = $this->settings('program_application_url', (string)($c['application_url'] ?? 'REPLACE_WITH_MARCAS_BPO_APPLICATION_URL'));
        $deadlineRaw = trim($this->settings('program_deadline', (string)($c['deadline'] ?? '2026-11-15')));
        $deadlineTs = strtotime($deadlineRaw);
        $deadlineIso = $deadlineTs ? date('Y-m-d', $deadlineTs) : $deadlineRaw;
        $deadline = $deadlineTs ? date($es ? 'j \d\e F \d\e Y' : 'F j, Y', $deadlineTs) : $deadlineRaw;

        $appsBase = max(0, (int)$this->settings('program_applications_base', (string)($c['applications_base'] ?? '3')));
        $appsDaily = max(0, (int)$this->settings('program_applications_daily', (string)($c['applications_daily'] ?? '3')));
        $appsMax = max(1, (int)$this->settings('program_applications_max', (string)($c['applications_max'] ?? '30')));
        $campaignStartRaw = trim($this->settings('program_campaign_start', (string)($c['campaign_start'] ?? '2026-10-20')));
        $startTs = strtotime($campaignStartRaw) ?: time();
        $daysRunning = max(0, (int)floor((time() - $startTs) / 86400));
        $applications = min($appsMax, $appsBase + $appsDaily * $daysRunning);

        $countriesRaw = $this->settings('program_countries', (string)($c['countries'] ?? 'United States, Argentina, Brazil, Spain, United Kingdom, Canada, Panama, Ecuador, El Salvador, Costa Rica'));
        $countries = array_values(array_filter(array_map('trim', explode(',', $countriesRaw)), fn($x) => $x !== ''));
        $countriesShown = array_slice($countries, 0, max(1, min($applications, count($countries))));
        $countryCount = count($countriesShown);

        $t = $es ? [
            'badge' => 'Programa selectivo — Limitado a 5 proyectos',
            'h1a' => 'Desarrollamos tu aplicación web ',
            'h1b' => 'sin costo inicial.',
            'copy' => 'Convierte un reto operativo en una solución digital práctica. INTSOLCOM desarrolla tu aplicación web y la cuota mensual de uso aplica solo bajo las condiciones acordadas, si la solución entrega el valor esperado.',
            'micro' => 'Programa selectivo para empresas con necesidades operativas reales. Cada solicitud se revisa individualmente.',
            'cta' => 'Aplicar al Programa',
            'how' => 'Cómo Funciona',
            'program' => 'El Programa',
            'row1t' => 'Sin costo inicial de desarrollo', 'row1d' => 'La aplicación web acordada se desarrolla sin costo inicial bajo el programa.',
            'row2t' => 'Valor antes de una cuota mensual', 'row2d' => 'Primero los criterios de éxito acordados. La cuota aplica según los términos acordados solo si se demuestra valor.',
            'row3t' => 'Selectivo y enfocado en el negocio', 'row3d' => 'Los proyectos se evalúan por el problema de negocio, el impacto, la viabilidad y los resultados medibles.',
            'row5t' => 'Solo se seleccionan cinco proyectos', 'row5d' => 'Las solicitudes cierran el ' . $deadline . '. Cada una se revisa individualmente.',
            'closeIn' => 'Las solicitudes cierran en',
            'deadlineLbl' => 'Fecha límite:',
            'units' => ['Semanas', 'Días', 'Horas', 'Minutos', 'Segundos'],
            'closed' => 'Las solicitudes de esta convocatoria están cerradas.',
            'appsLbl' => 'Solicitudes recibidas', 'reviewed' => 'Revisadas individualmente',
            'countriesLbl' => $countryCount === 1 ? 'país aplicó' : 'países aplicaron',
            'international' => 'Empresas internacionales',
            'reviewVal' => '48h', 'reviewLbl' => 'Tiempo promedio de revisión', 'reviewSub' => 'Respondemos a cada solicitud',
            'miniPre' => 'Cierra en ', 'miniPost' => ' — aplica hoy', 'miniClosed' => 'Las solicitudes están cerradas',
            'problemKicker' => 'El Problema del Negocio',
            'problemTitle' => 'Tu empresa no necesita más herramientas. Necesita una mejor solución.',
            'problemLead' => 'Las empresas pierden tiempo y productividad cada día por procesos que ya no se ajustan a cómo trabajan:',
            'problemItems' => [
                'Procesos manuales repetitivos que consumen horas de tu equipo.',
                'Información dispersa en hojas de cálculo y sistemas desconectados.',
                'Cuellos de botella operativos que frenan el servicio y las ventas.',
                'Errores, retrasos y falta de visibilidad sobre la operación diaria.',
                'Flujos de trabajo que ya no escalan con el negocio.',
            ],
            'problemNote' => 'No todo problema requiere software a la medida. El programa se enfoca en casos donde una aplicación web propia es la respuesta correcta.',
            'valueKicker' => 'La Propuesta de Valor del Programa',
            'valueTitle' => 'Una forma diferente de abordar el desarrollo de software.',
            'values' => [
                ['1', 'Sin costo inicial de desarrollo', 'INTSOLCOM desarrolla la aplicación web acordada sin cobrar un costo inicial de desarrollo bajo el programa.'],
                ['2', 'Valor antes de una cuota mensual', 'Las partes acuerdan criterios de éxito y condiciones comerciales. La cuota mensual aplica según esos términos si la solución entrega el valor esperado.'],
                ['3', 'Un enfoque selectivo y de negocio', 'Los proyectos se evalúan por el problema de negocio, el impacto potencial, la viabilidad y la oportunidad de medir resultados.'],
            ],
            'stepsKicker' => 'Cómo Funciona',
            'stepsTitle' => 'Del reto de negocio a una aplicación funcionando.',
            'steps' => [
                ['Aplica', 'Cuéntanos sobre tu empresa y el problema que quieres resolver.'],
                ['Evaluación', 'INTSOLCOM revisa tu solicitud y evalúa la idoneidad del proyecto.'],
                ['Define el proyecto', 'Si es seleccionado, ambas partes acuerdan alcance, métricas de éxito, propiedad, condiciones de uso y términos comerciales.'],
                ['Construye y evalúa', 'INTSOLCOM desarrolla la solución acordada y sus resultados se evalúan contra los criterios establecidos.'],
            ],
            'stepsNote' => 'Enviar una solicitud no garantiza la selección ni el desarrollo.',
            'fitKicker' => '¿Quién debería aplicar?',
            'fitTitle' => '¿Tu empresa es un buen candidato?',
            'fitItems' => [
                'Tu empresa ya está operando.',
                'Puedes identificar un proceso o problema específico a mejorar.',
                'El problema afecta el tiempo, los costos, los errores, la experiencia del cliente o el crecimiento.',
                'Puedes explicar cómo funciona el proceso actual.',
                'Tú o tu equipo pueden participar en definir requisitos y evaluar resultados.',
                'Estás abierto a un esquema de cuota mensual si se demuestra el valor acordado.',
            ],
            'fitNote' => 'Los proyectos se seleccionan según idoneidad, viabilidad, impacto esperado y capacidad del programa.',
            'deadlineTitle' => 'Solo se seleccionarán cinco proyectos.',
            'deadlineCopy' => 'INTSOLCOM seleccionará hasta cinco proyectos empresariales para este programa. Cada solicitud se revisará individualmente según la necesidad del negocio, su viabilidad y el impacto medible potencial.',
            'finalTitle' => 'Cuéntanos qué necesita mejorar tu empresa.',
            'finalCopy' => 'Comparte tu reto de negocio con INTSOLCOM. Si tu proyecto encaja en el programa, nuestro equipo puede evaluar los siguientes pasos contigo.',
            'finalMicro' => 'Las solicitudes se revisan individualmente. La selección no está garantizada.',
            'stickyPre' => 'Limitado a 5 proyectos ·', 'stickyCta' => 'Aplicar ahora', 'dismiss' => 'Cerrar',
            'applyUrlLabel' => 'Aplicar',
        ] : [
            'badge' => 'Selective Program — Limited to 5 Projects',
            'h1a' => 'We Develop Your Web Application at ',
            'h1b' => 'No Upfront Cost.',
            'copy' => 'Turn an operational challenge into a practical digital solution. INTSOLCOM develops your web application, and a monthly usage fee applies only under the agreed conditions if the solution delivers the expected value.',
            'micro' => 'Selective program for businesses with real operational needs. Applications are reviewed individually.',
            'cta' => 'Apply for the Program',
            'how' => 'How It Works',
            'program' => 'The Program',
            'row1t' => 'No upfront development fee', 'row1d' => 'The agreed web application is developed without an upfront fee under the program.',
            'row2t' => 'Value before a monthly usage fee', 'row2d' => 'Agreed success criteria first. The fee applies according to agreed terms only if value is demonstrated.',
            'row3t' => 'Selective, business-focused', 'row3d' => 'Projects are evaluated on the business problem, impact, feasibility, and measurable results.',
            'row5t' => 'Only five projects selected', 'row5d' => 'Applications close on ' . $deadline . '. Each one is reviewed individually.',
            'closeIn' => 'Applications close in',
            'deadlineLbl' => 'Deadline:',
            'units' => ['Weeks', 'Days', 'Hours', 'Minutes', 'Seconds'],
            'closed' => 'Applications for this campaign are now closed.',
            'appsLbl' => 'Applications received', 'reviewed' => 'Reviewed individually',
            'countriesLbl' => $countryCount === 1 ? 'country applied' : 'countries applied',
            'international' => 'International businesses',
            'reviewVal' => '48h', 'reviewLbl' => 'Average review time', 'reviewSub' => 'We reply to every application',
            'miniPre' => 'Closes in ', 'miniPost' => ' — apply today', 'miniClosed' => 'Applications are closed',
            'problemKicker' => 'The Business Problem',
            'problemTitle' => 'Your Business May Not Need More Tools. It May Need a Better Solution.',
            'problemLead' => 'Businesses lose time and productivity every day because of processes that no longer fit how they work:',
            'problemItems' => [
                'Repetitive manual processes that consume your team\'s hours.',
                'Information spread across spreadsheets and disconnected systems.',
                'Operational bottlenecks that slow down service and sales.',
                'Errors, delays, and lack of visibility over daily operations.',
                'Existing workflows that no longer scale with the business.',
            ],
            'problemNote' => 'Not every problem requires custom software. The program focuses on cases where a tailored web application is the right answer.',
            'valueKicker' => 'The Program Value Proposition',
            'valueTitle' => 'A Different Way to Approach Software Development.',
            'values' => [
                ['1', 'No Upfront Development Fee', 'INTSOLCOM develops the agreed web application without charging an upfront development fee under the program.'],
                ['2', 'Value Before a Monthly Usage Fee', 'The parties agree on relevant success criteria and commercial conditions. A monthly usage fee applies according to those agreed terms if the solution delivers the expected value.'],
                ['3', 'A Selective, Business-Focused Approach', 'Projects are evaluated based on the business problem, potential impact, feasibility, and the opportunity to measure results.'],
            ],
            'stepsKicker' => 'How It Works',
            'stepsTitle' => 'From Business Challenge to Working Application.',
            'steps' => [
                ['Apply', 'Describe your business and the problem you want to solve.'],
                ['Get Evaluated', 'INTSOLCOM reviews your application and assesses project suitability.'],
                ['Define the Project', 'If selected, both parties agree on scope, success metrics, ownership, usage conditions, and commercial terms.'],
                ['Build and Evaluate', 'INTSOLCOM develops the agreed solution, and its results are evaluated against the established criteria.'],
            ],
            'stepsNote' => 'Submitting an application does not guarantee selection or development.',
            'fitKicker' => 'Who Should Apply?',
            'fitTitle' => 'Is Your Business a Good Fit?',
            'fitItems' => [
                'Your business is already operating.',
                'You can identify a specific process or problem to improve.',
                'The problem affects time, costs, errors, customer experience, or growth.',
                'You can explain how the current process works.',
                'You or your team can participate in defining requirements and evaluating results.',
                'You are open to a monthly usage arrangement if the agreed value is demonstrated.',
            ],
            'fitNote' => 'Projects are selected based on suitability, feasibility, expected business impact, and program capacity.',
            'deadlineTitle' => 'Only Five Projects Will Be Selected.',
            'deadlineCopy' => 'INTSOLCOM will select up to five business projects for this program. Each application will be reviewed individually based on business need, feasibility, and potential measurable impact.',
            'finalTitle' => 'Tell Us What Your Business Needs to Improve.',
            'finalCopy' => 'Share your business challenge with INTSOLCOM. If your project fits the program, our team can evaluate the next steps with you.',
            'finalMicro' => 'Applications are reviewed individually. Selection is not guaranteed.',
            'stickyPre' => 'Limited to 5 projects ·', 'stickyCta' => 'Apply now', 'dismiss' => 'Dismiss',
            'applyUrlLabel' => 'Apply',
        ];

        $cta = fn(string $extraClass = '') => '<a class="plp-btn plp-btn-primary ' . $extraClass . '" href="' . $e($applyUrl) . '">' . $e($t['cta']) . ' <span aria-hidden="true">&#8594;</span></a>';
        $miniTimer = fn() => '<span class="plp-mini-timer" data-deadline="' . $e($deadlineIso) . '" data-pre="' . $e($t['miniPre']) . '" data-post="' . $e($t['miniPost']) . '" data-closed="' . $e($t['miniClosed']) . '" aria-hidden="true"><span class="plp-mini-dot"></span><span class="plp-mini-text">' . $e($t['miniPre']) . '</span></span>';

        $html = '<style>'
            . '.plp{font-family:Inter,-apple-system,sans-serif;color:#20252B;background:#FFFFFF;line-height:1.6}'
            . '.plp *{box-sizing:border-box}'
            . '.plp-wrap{max-width:1080px;margin:0 auto;padding:0 24px}'
            . '.plp-hero{background:linear-gradient(180deg,#F4F9FE 0%,#FFFFFF 100%);border-bottom:1px solid #E8EDF3;padding:150px 0 90px}'
            . '.plp-hero-grid{display:grid;grid-template-columns:1.05fr .95fr;gap:56px;align-items:center}'
            . '.plp-badge{display:inline-flex;align-items:center;gap:8px;background:#E7F4FF;color:#0077C2;border:1px solid #BFE3FA;border-radius:999px;padding:6px 14px;font-size:12.5px;font-weight:600;letter-spacing:.02em}'
            . '.plp-badge-dot{width:7px;height:7px;border-radius:50%;background:#00A3FF}'
            . '.plp-hero h1{font-family:"Space Grotesk",Inter,sans-serif;font-size:clamp(2.1rem,4.2vw,3.3rem);font-weight:700;letter-spacing:-.025em;line-height:1.12;color:#20252B;margin:22px 0 18px}'
            . '.plp-hero h1 em{font-style:normal;color:#00A3FF}'
            . '.plp-hero-copy{font-size:1.05rem;color:#4A5561;max-width:560px;margin-bottom:12px}'
            . '.plp-hero-micro{font-size:.875rem;color:#7A8694;margin-bottom:26px}'
            . '.plp-hero-actions{display:flex;gap:14px;flex-wrap:wrap;align-items:flex-start}'
            . '.plp-cta-wrap{display:flex;flex-direction:column;gap:7px;align-items:stretch}'
            . '.plp-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:10px;font-weight:600;font-size:15px;padding:15px 30px;text-decoration:none;transition:transform .15s ease,box-shadow .15s ease,background .15s ease}'
            . '.plp-btn:focus-visible{outline:3px solid #9BD4FA;outline-offset:2px}'
            . '.plp-btn-primary{background:#00A3FF;color:#FFFFFF;box-shadow:0 6px 20px rgba(0,163,255,.28)}'
            . '.plp-btn-primary:hover{background:#008FE0;transform:translateY(-1px)}'
            . '.plp-btn-ghost{background:#FFFFFF;color:#20252B;border:1px solid #D7DEE7}'
            . '.plp-btn-ghost:hover{border-color:#9BB0C4}'
            . '.plp-mini-timer{display:flex;align-items:center;justify-content:center;gap:7px;font-size:11.5px;font-weight:600;color:#6B7A8A;letter-spacing:.01em;min-height:16px}'
            . '.plp-mini-timer b{color:#0077C2;font-weight:700;font-variant-numeric:tabular-nums}'
            . '.plp-mini-dot{width:6px;height:6px;border-radius:50%;background:#00A3FF;animation:plpPulse 1.8s ease-in-out infinite}'
            . '@keyframes plpPulse{0%,100%{opacity:1}50%{opacity:.35}}'
            . '.plp-visual{background:#20252B;border-radius:18px;padding:30px 28px;box-shadow:0 24px 60px rgba(32,37,43,.14)}'
            . '.plp-visual-tag{display:inline-block;background:rgba(0,163,255,.14);color:#7CCBFF;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:4px 10px;border-radius:6px;margin-bottom:14px}'
            . '.plp-visual-row{display:flex;gap:12px;align-items:flex-start;padding:12px 0;border-top:1px solid rgba(255,255,255,.08)}'
            . '.plp-visual-row:first-of-type{border-top:none}'
            . '.plp-visual-num{flex-shrink:0;width:30px;height:30px;border-radius:8px;background:#00A3FF;color:#fff;font-weight:700;font-size:13px;display:flex;align-items:center;justify-content:center}'
            . '.plp-visual-row b{display:block;color:#FFFFFF;font-size:14.5px;font-weight:600;margin-bottom:3px}'
            . '.plp-visual-row span{display:block;color:#AEBAC7;font-size:13px;line-height:1.55}'
            . '.plp-countdown{margin-top:30px;background:#FFFFFF;border:1px solid #DCE7F2;border-radius:16px;padding:20px 22px;box-shadow:0 8px 26px rgba(32,37,43,.06)}'
            . '.plp-countdown-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;flex-wrap:wrap}'
            . '.plp-countdown-title{font-size:12px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:#0077C2}'
            . '.plp-countdown-deadline{font-size:12px;color:#7A8694}'
            . '.plp-clock{display:grid;grid-template-columns:repeat(5,1fr);gap:8px}'
            . '.plp-clock-cell{background:#F4F8FC;border:1px solid #E4EDF5;border-radius:10px;padding:10px 4px;text-align:center}'
            . '.plp-clock-val{display:block;font-family:"Space Grotesk",Inter,sans-serif;font-size:clamp(1.25rem,2.4vw,1.7rem);font-weight:700;color:#20252B;font-variant-numeric:tabular-nums;line-height:1.1}'
            . '.plp-clock-lbl{display:block;font-size:9.5px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#7A8694;margin-top:3px}'
            . '.plp-clock-closed{display:none;background:#FFF4F4;border:1px solid #F5C9C9;color:#B3261E;border-radius:10px;padding:14px;text-align:center;font-size:13.5px;font-weight:600}'
            . '.plp-indicators{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-top:12px}'
            . '.plp-ind{background:#FFFFFF;border:1px solid #E4EDF5;border-radius:12px;padding:12px 14px;display:flex;gap:10px;align-items:flex-start}'
            . '.plp-ind-icon{flex-shrink:0;width:28px;height:28px;border-radius:8px;background:#E7F4FF;color:#0077C2;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700}'
            . '.plp-ind-body{min-width:0;flex:1}'
            . '.plp-ind-val{font-family:"Space Grotesk",Inter,sans-serif;font-size:1.1rem;font-weight:700;color:#20252B;line-height:1.15;font-variant-numeric:tabular-nums}'
            . '.plp-ind-lbl{font-size:10.5px;color:#7A8694;font-weight:600;letter-spacing:.02em;margin-top:1px}'
            . '.plp-country-ticker{position:relative;height:18px;overflow:hidden;margin-top:2px}'
            . '.plp-country-item{position:absolute;inset:0;font-size:.86rem;font-weight:700;color:#20252B;opacity:0;transform:translateY(8px);transition:opacity .45s ease,transform .45s ease;white-space:nowrap}'
            . '.plp-country-item.on{opacity:1;transform:translateY(0)}'
            . '.plp-ind-sub{font-size:10px;color:#9AA6B2;margin-top:1px}'
            . '.plp-section{padding:88px 0}'
            . '.plp-section-alt{background:#F6F8FA;border-top:1px solid #E8EDF3;border-bottom:1px solid #E8EDF3}'
            . '.plp-kicker{display:block;font-size:12.5px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#00A3FF;margin-bottom:12px}'
            . '.plp-h2{font-family:"Space Grotesk",Inter,sans-serif;font-size:clamp(1.6rem,3vw,2.3rem);font-weight:700;letter-spacing:-.02em;line-height:1.18;color:#20252B;max-width:760px}'
            . '.plp-lead{font-size:1.02rem;color:#4A5561;max-width:720px;margin-top:14px}'
            . '.plp-grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:22px;margin-top:44px}'
            . '.plp-card{background:#FFFFFF;border:1px solid #E4E9F0;border-radius:14px;padding:28px 26px}'
            . '.plp-card-num{width:34px;height:34px;border-radius:9px;background:#E7F4FF;color:#0077C2;font-weight:700;font-size:14px;display:flex;align-items:center;justify-content:center;margin-bottom:16px}'
            . '.plp-card h3{font-size:1.06rem;font-weight:700;color:#20252B;margin-bottom:8px;letter-spacing:-.01em}'
            . '.plp-card p{font-size:.94rem;color:#4A5561;margin:0}'
            . '.plp-steps{display:grid;grid-template-columns:repeat(4,1fr);gap:22px;margin-top:44px}'
            . '.plp-step{border-top:3px solid #00A3FF;background:#FFFFFF;border-radius:0 0 12px 12px;border-bottom:1px solid #E4E9F0;border-left:1px solid #E4E9F0;border-right:1px solid #E4E9F0;padding:22px 20px}'
            . '.plp-step b{display:block;font-size:13px;color:#00A3FF;text-transform:uppercase;letter-spacing:.07em;margin-bottom:8px}'
            . '.plp-step h3{font-size:1rem;font-weight:700;color:#20252B;margin-bottom:8px}'
            . '.plp-step p{font-size:.9rem;color:#4A5561;margin:0}'
            . '.plp-check{display:flex;gap:12px;align-items:flex-start;padding:13px 0;border-bottom:1px solid #EDF1F5;font-size:.97rem;color:#3C4753}'
            . '.plp-check:last-child{border-bottom:none}'
            . '.plp-check-mark{flex-shrink:0;width:22px;height:22px;border-radius:50%;background:#DDF1FF;color:#0077C2;font-weight:700;font-size:12px;display:flex;align-items:center;justify-content:center;margin-top:1px}'
            . '.plp-note{font-size:.88rem;color:#7A8694;margin-top:22px;max-width:720px}'
            . '.plp-deadline{background:#20252B;border-radius:18px;color:#fff;padding:40px 36px;display:flex;gap:36px;align-items:center;justify-content:space-between;flex-wrap:wrap}'
            . '.plp-deadline-date{flex-shrink:0;background:#00A3FF;border-radius:12px;padding:18px 26px;text-align:center}'
            . '.plp-deadline-date b{display:block;font-family:"Space Grotesk",Inter,sans-serif;font-size:1.5rem;font-weight:700}'
            . '.plp-deadline-date span{font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:#D6EEFF}'
            . '.plp-deadline h3{font-size:1.35rem;font-weight:700;margin-bottom:8px;color:#fff}'
            . '.plp-deadline p{margin:0;color:#B7C1CC;max-width:560px;font-size:.95rem}'
            . '.plp-final{background:linear-gradient(180deg,#F4F9FE 0%,#FFFFFF 100%);border-top:1px solid #E8EDF3;padding:96px 0;text-align:center}'
            . '.plp-final h2{font-family:"Space Grotesk",Inter,sans-serif;font-size:clamp(1.7rem,3.4vw,2.5rem);font-weight:700;letter-spacing:-.02em;color:#20252B;max-width:680px;margin:0 auto 14px}'
            . '.plp-final p{color:#4A5561;max-width:600px;margin:0 auto 30px}'
            . '.plp-final .plp-cta-wrap{margin:0 auto;max-width:340px}'
            . '.plp-final-micro{font-size:.85rem;color:#7A8694;margin-top:18px}'
            . '.plp-sticky{position:fixed;left:0;right:0;bottom:0;z-index:60;display:none;align-items:center;justify-content:space-between;gap:12px;background:rgba(255,255,255,.97);backdrop-filter:blur(12px);border-top:1px solid #DCE7F2;padding:10px 16px;box-shadow:0 -8px 28px rgba(32,37,43,.08)}'
            . '.plp-sticky-txt{font-size:11.5px;color:#4A5561;line-height:1.35}'
            . '.plp-sticky-txt b{color:#0077C2;font-variant-numeric:tabular-nums}'
            . '.plp-sticky-actions{display:flex;align-items:center;gap:8px;flex-shrink:0}'
            . '.plp-sticky .plp-btn{padding:11px 16px;font-size:13px;border-radius:9px}'
            . '.plp-sticky-close{background:none;border:none;color:#9AA6B2;font-size:16px;cursor:pointer;padding:6px;line-height:1}'
            . '@media(max-width:900px){.plp-hero{padding:130px 0 70px}.plp-hero-grid{grid-template-columns:1fr;gap:40px}.plp-grid-3,.plp-steps{grid-template-columns:1fr 1fr}.plp-visual{order:2}.plp-indicators{grid-template-columns:1fr 1fr}.plp-ind:last-child{grid-column:1/-1}}'
            . '@media(max-width:620px){.plp-grid-3,.plp-steps{grid-template-columns:1fr}.plp-hero-actions{flex-direction:column;align-items:stretch}.plp-hero-actions .plp-cta-wrap,.plp-hero-actions .plp-btn{width:100%}.plp-deadline{flex-direction:column;align-items:flex-start;gap:22px}.plp-section{padding:64px 0}.plp-indicators{grid-template-columns:1fr}.plp-clock{gap:5px}.plp-clock-cell{padding:8px 2px}.plp-sticky{display:flex}}'
            . '</style>';

        $html .= '<div class="plp">';

        $html .= '<section class="plp-hero"><div class="plp-wrap"><div class="plp-hero-grid">';
        $html .= '<div><span class="plp-badge"><span class="plp-badge-dot"></span>' . $e($t['badge']) . '</span>';
        $html .= '<h1>' . $e($t['h1a']) . '<em>' . $e($t['h1b']) . '</em></h1>';
        $html .= '<p class="plp-hero-copy">' . $e($t['copy']) . '</p>';
        $html .= '<p class="plp-hero-micro">' . $e($t['micro']) . '</p>';
        $html .= '<div class="plp-hero-actions"><span class="plp-cta-wrap">' . $cta() . $miniTimer() . '</span><a class="plp-btn plp-btn-ghost" href="#how-it-works">' . $e($t['how']) . '</a></div>';

        $html .= '<div class="plp-countdown" aria-label="Program countdown and activity">';
        $html .= '<div class="plp-countdown-head"><span class="plp-countdown-title">' . $e($t['closeIn']) . '</span><span class="plp-countdown-deadline">' . $e($t['deadlineLbl']) . ' ' . $e($deadline) . '</span></div>';
        $html .= '<div class="plp-clock" id="plp-clock" data-deadline="' . $e($deadlineIso) . '" role="timer" aria-live="off">';
        foreach ($t['units'] as $i => $u) {
            $unitKey = ['w', 'd', 'h', 'm', 's'][$i];
            $html .= '<div class="plp-clock-cell"><span class="plp-clock-val" data-unit="' . $unitKey . '">--</span><span class="plp-clock-lbl">' . $e($u) . '</span></div>';
        }
        $html .= '</div>';
        $html .= '<div class="plp-clock-closed" id="plp-clock-closed">' . $e($t['closed']) . '</div>';
        $html .= '<div class="plp-indicators">';
        $html .= '<div class="plp-ind"><span class="plp-ind-icon" aria-hidden="true">&#128203;</span><div class="plp-ind-body"><div class="plp-ind-val" id="plp-apps">' . (int)$applications . '</div><div class="plp-ind-lbl">' . $e($t['appsLbl']) . '</div><div class="plp-ind-sub">' . $e($t['reviewed']) . '</div></div></div>';
        $html .= '<div class="plp-ind"><span class="plp-ind-icon" aria-hidden="true">&#127758;</span><div class="plp-ind-body"><div class="plp-country-ticker" id="plp-countries" aria-hidden="true">';
        foreach ($countriesShown as $i => $country) {
            $html .= '<span class="plp-country-item' . ($i === 0 ? ' on' : '') . '">' . $e($country) . '</span>';
        }
        $html .= '</div><div class="plp-ind-lbl">' . (int)$countryCount . ' ' . $e($t['countriesLbl']) . '</div><div class="plp-ind-sub">' . $e($t['international']) . '</div></div></div>';
        $html .= '<div class="plp-ind"><span class="plp-ind-icon" aria-hidden="true">&#9889;</span><div class="plp-ind-body"><div class="plp-ind-val">' . $e($t['reviewVal']) . '</div><div class="plp-ind-lbl">' . $e($t['reviewLbl']) . '</div><div class="plp-ind-sub">' . $e($t['reviewSub']) . '</div></div></div>';
        $html .= '</div></div>';

        $html .= '</div><div class="plp-visual"><span class="plp-visual-tag">' . $e($t['program']) . '</span>';
        $html .= '<div class="plp-visual-row"><span class="plp-visual-num">1</span><div><b>' . $e($t['row1t']) . '</b><span>' . $e($t['row1d']) . '</span></div></div>';
        $html .= '<div class="plp-visual-row"><span class="plp-visual-num">2</span><div><b>' . $e($t['row2t']) . '</b><span>' . $e($t['row2d']) . '</span></div></div>';
        $html .= '<div class="plp-visual-row"><span class="plp-visual-num">3</span><div><b>' . $e($t['row3t']) . '</b><span>' . $e($t['row3d']) . '</span></div></div>';
        $html .= '<div class="plp-visual-row"><span class="plp-visual-num">5</span><div><b>' . $e($t['row5t']) . '</b><span>' . $e($t['row5d']) . '</span></div></div>';
        $html .= '</div></div></div></section>';

        $html .= '<section class="plp-section"><div class="plp-wrap">';
        $html .= '<span class="plp-kicker">' . $e($t['problemKicker']) . '</span>';
        $html .= '<h2 class="plp-h2">' . $e($t['problemTitle']) . '</h2>';
        $html .= '<p class="plp-lead">' . $e($t['problemLead']) . '</p>';
        $html .= '<div style="margin-top:28px;max-width:760px">';
        foreach ($t['problemItems'] as $chk) {
            $html .= '<div class="plp-check"><span class="plp-check-mark">&#10003;</span><span>' . $e($chk) . '</span></div>';
        }
        $html .= '</div>';
        $html .= '<p class="plp-note">' . $e($t['problemNote']) . '</p>';
        $html .= '</div></section>';

        $html .= '<section class="plp-section plp-section-alt"><div class="plp-wrap">';
        $html .= '<span class="plp-kicker">' . $e($t['valueKicker']) . '</span>';
        $html .= '<h2 class="plp-h2">' . $e($t['valueTitle']) . '</h2>';
        $html .= '<div class="plp-grid-3">';
        foreach ($t['values'] as $v) {
            $html .= '<div class="plp-card"><div class="plp-card-num">' . $e($v[0]) . '</div><h3>' . $e($v[1]) . '</h3><p>' . $e($v[2]) . '</p></div>';
        }
        $html .= '</div></div></section>';

        $html .= '<section class="plp-section" id="how-it-works"><div class="plp-wrap">';
        $html .= '<span class="plp-kicker">' . $e($t['stepsKicker']) . '</span>';
        $html .= '<h2 class="plp-h2">' . $e($t['stepsTitle']) . '</h2>';
        $html .= '<div class="plp-steps">';
        foreach ($t['steps'] as $i => $st) {
            $html .= '<div class="plp-step"><b>' . ($es ? 'Paso' : 'Step') . ' ' . ($i + 1) . '</b><h3>' . $e($st[0]) . '</h3><p>' . $e($st[1]) . '</p></div>';
        }
        $html .= '</div>';
        $html .= '<p class="plp-note">' . $e($t['stepsNote']) . '</p>';
        $html .= '</div></section>';

        $html .= '<section class="plp-section"><div class="plp-wrap">';
        $html .= '<span class="plp-kicker">' . $e($t['fitKicker']) . '</span>';
        $html .= '<h2 class="plp-h2">' . $e($t['fitTitle']) . '</h2>';
        $html .= '<div style="margin-top:30px;max-width:760px">';
        foreach ($t['fitItems'] as $f) {
            $html .= '<div class="plp-check"><span class="plp-check-mark">&#10003;</span><span>' . $e($f) . '</span></div>';
        }
        $html .= '</div>';
        $html .= '<p class="plp-note">' . $e($t['fitNote']) . '</p>';
        $html .= '</div></section>';

        $html .= '<section class="plp-section plp-section-alt"><div class="plp-wrap">';
        $html .= '<div class="plp-deadline">';
        $html .= '<div class="plp-deadline-date"><b>' . $e($deadline) . '</b><span>' . $e($t['deadlineLbl']) . '</span></div>';
        $html .= '<div><h3>' . $e($t['deadlineTitle']) . '</h3><p>' . $e($t['deadlineCopy']) . '</p></div>';
        $html .= '</div></div></section>';

        $html .= '<section class="plp-final"><div class="plp-wrap">';
        $html .= '<h2>' . $e($t['finalTitle']) . '</h2>';
        $html .= '<p>' . $e($t['finalCopy']) . '</p>';
        $html .= '<span class="plp-cta-wrap">' . $cta() . $miniTimer() . '</span>';
        $html .= '<p class="plp-final-micro">' . $e($t['finalMicro']) . '</p>';
        $html .= '</div></section>';

        $html .= '</div>';

        $jsLabels = json_encode([
            'closed' => $t['miniClosed'],
            'pre' => $t['miniPre'],
            'post' => $t['miniPost'],
        ], JSON_UNESCAPED_UNICODE);

        $html .= '<script>(function(){'
            . 'var L=' . $jsLabels . ';'
            . 'var clock=document.getElementById("plp-clock");'
            . 'var closed=document.getElementById("plp-clock-closed");'
            . 'function pad(n){return n<10?"0"+n:""+n}'
            . 'function units(){'
            . 'var els=document.querySelectorAll("#plp-clock [data-unit]");var o={};els.forEach(function(el){o[el.getAttribute("data-unit")]=el});return o}'
            . 'function setClock(deadlineIso){'
            . 'var u=units();var target=new Date(deadlineIso+"T23:59:59").getTime();'
            . 'function tick(){var diff=target-Date.now();'
            . 'if(diff<=0){if(clock)clock.style.display="none";if(closed)closed.style.display="block";document.querySelectorAll(".plp-mini-text").forEach(function(t){t.textContent=L.closed});return false}'
            . 'var totalSec=Math.floor(diff/1000);var w=Math.floor(totalSec/604800);var d=Math.floor((totalSec%604800)/86400);var h=Math.floor((totalSec%86400)/3600);var m=Math.floor((totalSec%3600)/60);var s=totalSec%60;'
            . 'if(u.w)u.w.textContent=w;if(u.d)u.d.textContent=pad(d);if(u.h)u.h.textContent=pad(h);if(u.m)u.m.textContent=pad(m);if(u.s)u.s.textContent=pad(s);return true}'
            . 'if(!tick())return;var iv=setInterval(function(){if(!tick())clearInterval(iv)},1000)}'
            . 'if(clock){setClock(clock.getAttribute("data-deadline"))}'
            . 'function setMini(){document.querySelectorAll(".plp-mini-timer").forEach(function(el){var target=new Date(el.getAttribute("data-deadline")+"T23:59:59").getTime();var diff=target-Date.now();var t=el.querySelector(".plp-mini-text");if(!t)return;if(diff<=0){t.textContent=L.closed;return}var totalSec=Math.floor(diff/1000);var w=Math.floor(totalSec/604800);var d=Math.floor((totalSec%604800)/86400);var h=Math.floor((totalSec%86400)/3600);var m=Math.floor((totalSec%3600)/60);var parts=[];if(w>0)parts.push(w+"w");if(d>0||w>0)parts.push(d+"d");if(h>0||d>0||w>0)parts.push(h+"h");parts.push(m+"m");t.textContent=L.pre+parts.join(" ")+L.post})}'
            . 'setMini();setInterval(setMini,30000);'
            . 'var ticker=document.getElementById("plp-countries");'
            . 'if(ticker){var items=ticker.querySelectorAll(".plp-country-item");if(items.length>1){var idx=0;setInterval(function(){items[idx].classList.remove("on");idx=(idx+1)%items.length;items[idx].classList.add("on")},2600)}}'
            . 'var bar=document.getElementById("plp-sticky");'
            . 'if(bar){var hidden=false;if(sessionStorage.getItem("plpStickyHidden")==="1"){bar.style.display="none";hidden=true}'
            . 'var btn=bar.querySelector(".plp-sticky-close");if(btn){btn.addEventListener("click",function(){bar.style.display="none";sessionStorage.setItem("plpStickyHidden","1")})}'
            . 'if(!hidden){window.addEventListener("scroll",function(){if(window.scrollY>600){bar.style.display="flex"}else{bar.style.display="none"}},{passive:true})}}'
            . '})();</script>';

        $html .= '<div class="plp-sticky" id="plp-sticky">'
            . '<div class="plp-sticky-txt">' . $e($t['stickyPre']) . ' <b class="plp-mini-text" data-deadline="' . $e($deadlineIso) . '">' . $e($t['miniPre']) . '</b></div>'
            . '<div class="plp-sticky-actions"><a class="plp-btn plp-btn-primary" href="' . $e($applyUrl) . '">' . $e($t['stickyCta']) . '</a><button type="button" class="plp-sticky-close" aria-label="' . $e($t['dismiss']) . '">&#10005;</button></div>'
            . '</div>';

        return $html;
    }
}
