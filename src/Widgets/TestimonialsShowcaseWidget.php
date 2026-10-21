<?php
namespace App\Widgets;

use App\Core\Lang;

class TestimonialsShowcaseWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'testimonials-showcase', 'name' => 'INTSOLCOM: Testimonials Showcase', 'icon' => 'quote', 'category' => 'content', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => 'What our partners say'],
            ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea', 'default' => 'Client results across technology and operations.'],
            ['key' => 'items', 'label' => 'Testimonials', 'type' => 'repeater', 'fields' => [
                ['key' => 'quote', 'label' => 'Quote', 'type' => 'textarea'],
                ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['key' => 'role', 'label' => 'Role / Company', 'type' => 'text'],
                ['key' => 'metric1_value', 'label' => 'Metric 1 value', 'type' => 'text'],
                ['key' => 'metric1_label', 'label' => 'Metric 1 label', 'type' => 'text'],
                ['key' => 'metric2_value', 'label' => 'Metric 2 value', 'type' => 'text'],
                ['key' => 'metric2_label', 'label' => 'Metric 2 label', 'type' => 'text'],
            ], 'default' => [
                ['quote' => 'INTSOLCOM built our entire AI annotation pipeline in 14 days. The quality control and workforce management alone saved us 6 months of internal development. Truly a technology partner, not just a vendor.', 'name' => 'Marcus D.', 'role' => 'CTO, HealthTech Innovations', 'metric1_value' => '14', 'metric1_label' => 'days to deploy', 'metric2_value' => '6 mo', 'metric2_label' => 'development saved'],
                ['quote' => 'We moved our entire customer operations to INTSOLCOM SAS and saw a 60% cost reduction while improving CSAT scores by 12 points. Their WONTIA platform gave us visibility we never had before.', 'name' => 'Elena R.', 'role' => 'VP Operations, Meridian Financial', 'metric1_value' => '60%', 'metric1_label' => 'cost reduction', 'metric2_value' => '12 pts', 'metric2_label' => 'CSAT improvement'],
                ['quote' => 'As a startup, we needed a partner who could scale with us. INTSOLCOM provided nearshore engineering teams that felt like our own employees. The ecosystem approach is the real differentiator.', 'name' => 'David K.', 'role' => 'Founder, Stack AI Labs', 'metric1_value' => 'Nearshore', 'metric1_label' => 'engineering teams', 'metric2_value' => 'Ecosystem', 'metric2_label' => 'approach'],
            ]],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $e = fn($s) => $this->esc((string)$s);
        $es = Lang::current() === 'es';
        $items = $this->safeJson($c['items'] ?? []);
        if (!$items) return '';

        $labels = $es
            ? ['kicker' => 'Resultados de clientes', 'of' => 'de', 'next' => 'Siguiente', 'prev' => 'Anterior', 'select' => 'Selecciona un testimonio']
            : ['kicker' => 'Client results', 'of' => 'of', 'next' => 'Next', 'prev' => 'Previous', 'select' => 'Select a testimonial'];

        $html = '<style>'
            . '.tst{position:relative;background:#0A111C;color:#E8EEF4;padding:96px 0;overflow:hidden}'
            . '.tst *{box-sizing:border-box}'
            . '.tst::before{content:"";position:absolute;inset:0;background:radial-gradient(600px 300px at 15% 0%,rgba(0,163,255,.16),transparent 60%),radial-gradient(500px 260px at 90% 100%,rgba(0,200,150,.12),transparent 60%);pointer-events:none}'
            . '.tst::after{content:"";position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.05) 1px,transparent 1px);background-size:26px 26px;opacity:.5;pointer-events:none}'
            . '.tst-wrap{position:relative;max-width:1120px;margin:0 auto;padding:0 24px;z-index:1}'
            . '.tst-head{max-width:640px;margin-bottom:44px}'
            . '.tst-kicker{display:inline-flex;align-items:center;gap:8px;font-size:11.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#5FD4FF;margin-bottom:14px}'
            . '.tst-kicker i{width:26px;height:1px;background:#00A3FF;display:inline-block}'
            . '.tst-title{font-family:"Space Grotesk",Inter,sans-serif;font-size:clamp(1.7rem,3.2vw,2.5rem);font-weight:700;letter-spacing:-.025em;line-height:1.15;color:#fff;margin:0 0 10px}'
            . '.tst-sub{font-size:1rem;color:#9FB0C2;line-height:1.65;margin:0}'
            . '.tst-grid{display:grid;grid-template-columns:1.5fr .9fr;gap:26px;align-items:stretch}'
            . '.tst-panel{position:relative;background:linear-gradient(160deg,rgba(255,255,255,.06),rgba(255,255,255,.02));border:1px solid rgba(255,255,255,.09);border-radius:24px;padding:38px 40px 32px;backdrop-filter:blur(8px);min-height:360px;display:flex;flex-direction:column}'
            . '.tst-quote-mark{font-family:Georgia,serif;font-size:74px;line-height:.6;color:#00A3FF;opacity:.55;height:42px;user-select:none}'
            . '.tst-quote{font-family:"Space Grotesk",Inter,sans-serif;font-size:clamp(1.05rem,1.6vw,1.35rem);line-height:1.65;color:#F2F6FA;font-weight:500;margin:0 0 26px;flex:1;transition:opacity .28s ease,transform .28s ease}'
            . '.tst-quote.swap{opacity:0;transform:translateY(8px)}'
            . '.tst-metrics{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px}'
            . '.tst-chip{display:flex;flex-direction:column;gap:1px;background:rgba(0,163,255,.1);border:1px solid rgba(0,163,255,.28);border-radius:12px;padding:10px 16px;min-width:96px}'
            . '.tst-chip b{font-family:"Space Grotesk",Inter,sans-serif;font-size:1.15rem;font-weight:700;color:#7CD9FF;line-height:1.1}'
            . '.tst-chip span{font-size:10.5px;font-weight:600;letter-spacing:.04em;color:#9FB0C2}'
            . '.tst-author{display:flex;align-items:center;gap:13px;padding-top:20px;border-top:1px solid rgba(255,255,255,.08)}'
            . '.tst-avatar{width:46px;height:46px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-family:"Space Grotesk",sans-serif;font-weight:700;font-size:17px;color:#06121B;background:linear-gradient(135deg,#5FD4FF,#00C896);flex-shrink:0}'
            . '.tst-name{font-size:15px;font-weight:700;color:#fff;line-height:1.2}'
            . '.tst-role{font-size:12.5px;color:#8FA1B4;margin-top:2px}'
            . '.tst-stars{margin-left:auto;color:#FFC55C;font-size:12px;letter-spacing:2px;white-space:nowrap}'
            . '.tst-side{display:flex;flex-direction:column;gap:10px}'
            . '.tst-opt{display:flex;align-items:center;gap:12px;text-align:left;width:100%;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:14px 16px;cursor:pointer;transition:all .18s ease;font-family:inherit;color:#D8E2EC}'
            . '.tst-opt:hover{background:rgba(255,255,255,.06);border-color:rgba(0,163,255,.35)}'
            . '.tst-opt:focus-visible{outline:3px solid #5FD4FF;outline-offset:2px}'
            . '.tst-opt.on{background:rgba(0,163,255,.1);border-color:rgba(0,163,255,.55);box-shadow:inset 3px 0 0 #00A3FF}'
            . '.tst-opt-av{width:34px;height:34px;border-radius:10px;background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#BEE9FF;flex-shrink:0}'
            . '.tst-opt.on .tst-opt-av{background:linear-gradient(135deg,#5FD4FF,#00C896);color:#06121B}'
            . '.tst-opt-name{font-size:13.5px;font-weight:700;color:#fff;line-height:1.25}'
            . '.tst-opt-role{font-size:11.5px;color:#8FA1B4;margin-top:1px;line-height:1.35}'
            . '.tst-count{margin-top:auto;display:flex;align-items:center;justify-content:space-between;padding:14px 4px 0;color:#7E90A3;font-size:12px;font-weight:600;letter-spacing:.06em}'
            . '.tst-count b{color:#fff;font-family:"Space Grotesk",sans-serif}'
            . '.tst-nav{display:flex;gap:8px}'
            . '.tst-nav button{width:36px;height:36px;border-radius:10px;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.04);color:#CFE0EE;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;transition:all .15s}'
            . '.tst-nav button:hover{background:rgba(0,163,255,.18);border-color:rgba(0,163,255,.5)}'
            . '.tst-nav button:focus-visible{outline:3px solid #5FD4FF;outline-offset:2px}'
            . '@media(max-width:900px){.tst-grid{grid-template-columns:1fr}.tst-panel{padding:30px 26px;min-height:0}.tst-side{flex-direction:row;overflow-x:auto;padding-bottom:4px}.tst-opt{min-width:240px}.tst-count{display:none}.tst{padding:72px 0}}'
            . '@media(max-width:520px){.tst-metrics{gap:8px}.tst-chip{flex:1;min-width:0}.tst-stars{display:none}}'
            . '@media(prefers-reduced-motion:reduce){.tst-quote{transition:none}}'
            . '</style>';

        $html .= '<section class="tst" id="testimonials"><div class="tst-wrap">';
        $html .= '<div class="tst-head"><span class="tst-kicker"><i></i>' . $e($labels['kicker']) . '</span>';
        $html .= '<h2 class="tst-title">' . $e($c['title'] ?? '') . '</h2>';
        $html .= '<p class="tst-sub">' . $e($c['subtitle'] ?? '') . '</p></div>';

        $html .= '<div class="tst-grid">';
        $html .= '<div class="tst-panel" role="tabpanel" id="tst-panel" aria-live="polite">';
        $html .= '<div class="tst-quote-mark" aria-hidden="true">&ldquo;</div>';
        $html .= '<p class="tst-quote" id="tst-quote"></p>';
        $html .= '<div class="tst-metrics" id="tst-metrics"></div>';
        $html .= '<div class="tst-author"><div class="tst-avatar" id="tst-avatar"></div><div><div class="tst-name" id="tst-name"></div><div class="tst-role" id="tst-role"></div></div><div class="tst-stars" aria-label="5 / 5">&#9733;&#9733;&#9733;&#9733;&#9733;</div></div>';
        $html .= '</div>';

        $html .= '<div class="tst-side" role="tablist" aria-label="' . $e($labels['select']) . '">';
        foreach ($items as $i => $t) {
            if (!is_array($t)) continue;
            $initial = mb_strtoupper(mb_substr(trim((string)($t['name'] ?? '?')), 0, 1));
            $html .= '<button type="button" class="tst-opt' . ($i === 0 ? ' on' : '') . '" role="tab" aria-selected="' . ($i === 0 ? 'true' : 'false') . '" data-idx="' . $i . '">'
                . '<span class="tst-opt-av">' . $e($initial) . '</span>'
                . '<span><span class="tst-opt-name">' . $e($t['name'] ?? '') . '</span><span class="tst-opt-role" style="display:block">' . $e($t['role'] ?? '') . '</span></span>'
                . '</button>';
        }
        $html .= '<div class="tst-count"><span><b id="tst-cur">01</b> ' . $e($labels['of']) . ' ' . count($items) . '</span><div class="tst-nav"><button type="button" id="tst-prev" aria-label="' . $e($labels['prev']) . '">&#8592;</button><button type="button" id="tst-next" aria-label="' . $e($labels['next']) . '">&#8594;</button></div></div>';
        $html .= '</div>';
        $html .= '</div></div></section>';

        $data = [];
        foreach ($items as $t) {
            if (!is_array($t)) continue;
            $data[] = [
                'quote' => (string)($t['quote'] ?? ''),
                'name' => (string)($t['name'] ?? ''),
                'role' => (string)($t['role'] ?? ''),
                'm1v' => (string)($t['metric1_value'] ?? ''),
                'm1l' => (string)($t['metric1_label'] ?? ''),
                'm2v' => (string)($t['metric2_value'] ?? ''),
                'm2l' => (string)($t['metric2_label'] ?? ''),
            ];
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $html .= '<script>(function(){var D=' . $json . ';if(!D.length)return;'
            . 'var q=document.getElementById("tst-quote"),m=document.getElementById("tst-metrics"),n=document.getElementById("tst-name"),r=document.getElementById("tst-role"),a=document.getElementById("tst-avatar"),cur=document.getElementById("tst-cur"),opts=document.querySelectorAll(".tst-opt"),i=0;'
            . 'function pad(x){return x<10?"0"+x:""+x}'
            . 'function chip(v,l){if(!v&&!l)return "";return \'<div class="tst-chip"><b>\'+esc(v)+\'</b><span>\'+esc(l)+\'</span></div>\'}'
            . 'function esc(s){return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;")}'
            . 'function show(k){i=(k+D.length)%D.length;var t=D[i];q.classList.add("swap");setTimeout(function(){q.textContent="\\u201C"+t.quote+"\\u201D";m.innerHTML=chip(t.m1v,t.m1l)+chip(t.m2v,t.m2l);n.textContent=t.name;r.textContent=t.role;a.textContent=(t.name||"?").trim().charAt(0).toUpperCase();cur.textContent=pad(i+1);opts.forEach(function(o,j){o.classList.toggle("on",j===i);o.setAttribute("aria-selected",j===i?"true":"false")});q.classList.remove("swap")},140)}'
            . 'opts.forEach(function(o){o.addEventListener("click",function(){show(+o.getAttribute("data-idx"))})});'
            . 'document.getElementById("tst-prev").addEventListener("click",function(){show(i-1)});'
            . 'document.getElementById("tst-next").addEventListener("click",function(){show(i+1)});'
            . 'document.addEventListener("keydown",function(e){if(e.key==="ArrowLeft")show(i-1);if(e.key==="ArrowRight")show(i+1)});'
            . 'q.textContent="\\u201C"+D[0].quote+"\\u201D";m.innerHTML=chip(D[0].m1v,D[0].m1l)+chip(D[0].m2v,D[0].m2l);n.textContent=D[0].name;r.textContent=D[0].role;a.textContent=(D[0].name||"?").trim().charAt(0).toUpperCase();'
            . '})();</script>';

        return $html;
    }
}
