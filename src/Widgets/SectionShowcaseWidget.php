<?php
namespace App\Widgets;

use App\Core\Lang;

class SectionShowcaseWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'section-showcase', 'name' => 'INTSOLCOM: Section Showcase', 'icon' => 'layout', 'category' => 'content', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'variant', 'label' => 'Layout (ecosystem | stack | products | capabilities | industries | comparison)', 'type' => 'text', 'default' => 'capabilities'],
            ['key' => 'kicker', 'label' => 'Kicker', 'type' => 'text', 'default' => ''],
            ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => ''],
            ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea', 'default' => ''],
            ['key' => 'items', 'label' => 'Items', 'type' => 'repeater', 'fields' => [
                ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                ['key' => 'desc', 'label' => 'Description', 'type' => 'textarea'],
                ['key' => 'tag', 'label' => 'Tag', 'type' => 'text'],
                ['key' => 'icon', 'label' => 'Icon (emoji)', 'type' => 'text'],
                ['key' => 'chips', 'label' => 'Chips (comma separated)', 'type' => 'text'],
                ['key' => 'url', 'label' => 'URL', 'type' => 'text'],
                ['key' => 'badge', 'label' => 'Badge', 'type' => 'text'],
            ], 'default' => []],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $e = fn($s) => $this->esc((string)$s);
        $es = Lang::current() === 'es';
        $variant = (string)($c['variant'] ?? 'capabilities');
        $items = array_values(array_filter($this->safeJson($c['items'] ?? []), 'is_array'));

        $css = '<style>'
            . '.ss{font-family:Inter,-apple-system,sans-serif;color:#20252B;position:relative;overflow:hidden}'
            . '.ss *{box-sizing:border-box}'
            . '.ss-wrap{max-width:1120px;margin:0 auto;padding:0 24px;position:relative;z-index:1}'
            . '.ss-head{max-width:680px;margin:0 auto 46px;text-align:center}'
            . '.ss-kicker{display:inline-flex;align-items:center;gap:8px;font-size:11.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#00A67D;margin-bottom:14px}'
            . '.ss-kicker::before{content:"";width:22px;height:1px;background:#00C896}'
            . '.ss-title{font-family:"Space Grotesk",Inter,sans-serif;font-size:clamp(1.7rem,3.2vw,2.5rem);font-weight:700;letter-spacing:-.025em;line-height:1.15;color:#0F172A;margin:0 0 12px}'
            . '.ss-sub{font-size:1rem;line-height:1.65;color:#5B6773;margin:0}'
            . '.ss-light{background:#F7F9FB;border-top:1px solid #E8EDF3;border-bottom:1px solid #E8EDF3;padding:90px 0}'
            . '.ss-white{background:#FFFFFF;padding:90px 0}'
            . '.ss-dark{background:#0B1220;padding:96px 0}'
            . '.ss-dark .ss-title{color:#fff}.ss-dark .ss-sub{color:#9FB0C2}.ss-dark .ss-kicker{color:#5FD4FF}.ss-dark .ss-kicker::before{background:#00A3FF}'

            . '.ss-eco{position:relative}'
            . '.ss-eco-hub{display:flex;justify-content:center;margin-bottom:8px}'
            . '.ss-eco-hub-card{display:inline-flex;flex-direction:column;align-items:center;gap:2px;background:#0F172A;color:#fff;border:1px solid rgba(0,200,150,.4);border-radius:16px;padding:18px 34px;box-shadow:0 18px 40px rgba(15,23,42,.18)}'
            . '.ss-eco-hub-card b{font-family:"Space Grotesk",sans-serif;font-size:1.05rem;letter-spacing:-.01em}'
            . '.ss-eco-hub-card span{font-size:10.5px;letter-spacing:.1em;text-transform:uppercase;color:#7FE7C4}'
            . '.ss-eco-conn{display:grid;grid-template-columns:repeat(3,1fr);height:44px;max-width:760px;margin:0 auto}'
            . '.ss-eco-conn i{justify-self:center;width:1px;height:100%;background:linear-gradient(180deg,rgba(0,200,150,.5),rgba(0,200,150,.05))}'
            . '.ss-eco-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}'
            . '.ss-eco-card{background:#fff;border:1px solid #E1E8F0;border-radius:18px;padding:26px 24px;position:relative;transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease}'
            . '.ss-eco-card:hover{transform:translateY(-3px);box-shadow:0 20px 44px rgba(15,23,42,.08);border-color:rgba(0,200,150,.45)}'
            . '.ss-eco-tag{font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#00A67D;margin-bottom:12px;display:block}'
            . '.ss-eco-icon{width:40px;height:40px;border-radius:11px;background:#EAFBF4;display:flex;align-items:center;justify-content:center;font-size:19px;margin-bottom:14px}'
            . '.ss-eco-card h3{font-size:1.05rem;font-weight:700;margin:0 0 8px;color:#0F172A;letter-spacing:-.01em}'
            . '.ss-eco-card p{font-size:.9rem;line-height:1.6;color:#5B6773;margin:0}'

            . '.ss-stack{display:grid;grid-template-columns:1fr 1.1fr;gap:44px;align-items:center}'
            . '.ss-stack-left .ss-head{text-align:left;margin:0 0 24px}'
            . '.ss-chips{display:flex;flex-wrap:wrap;gap:8px}'
            . '.ss-chip{padding:7px 13px;border-radius:999px;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.05);color:#CFE0EE;font-size:11.5px;font-weight:600}'
            . '.ss-term{background:#0F1A2A;border:1px solid rgba(255,255,255,.1);border-radius:18px;overflow:hidden;box-shadow:0 30px 60px rgba(0,0,0,.35)}'
            . '.ss-term-bar{display:flex;align-items:center;gap:7px;padding:12px 16px;border-bottom:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.03)}'
            . '.ss-term-bar i{width:9px;height:9px;border-radius:50%;background:#334155}'
            . '.ss-term-bar i:nth-child(1){background:#F87171}.ss-term-bar i:nth-child(2){background:#FBBF24}.ss-term-bar i:nth-child(3){background:#34D399}'
            . '.ss-term-bar span{margin-left:8px;font-family:ui-monospace,monospace;font-size:11px;color:#64748B}'
            . '.ss-term-body{padding:18px 20px 22px;display:flex;flex-direction:column;gap:14px}'
            . '.ss-term-row{display:flex;gap:12px;align-items:flex-start;padding-bottom:14px;border-bottom:1px solid rgba(255,255,255,.06)}'
            . '.ss-term-row:last-child{border-bottom:none;padding-bottom:0}'
            . '.ss-term-av{width:34px;height:34px;border-radius:9px;background:rgba(0,163,255,.14);color:#7CD9FF;display:flex;align-items:center;justify-content:center;font-family:ui-monospace,monospace;font-size:12px;font-weight:700;flex-shrink:0}'
            . '.ss-term-role{font-family:"Space Grotesk",sans-serif;font-size:13.5px;font-weight:700;color:#fff;margin-bottom:5px}'
            . '.ss-term-tech{font-family:ui-monospace,monospace;font-size:11.5px;color:#8FA6BD;line-height:1.6}'

            . '.ss-prod{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}'
            . '.ss-prod-card{position:relative;background:#fff;border:1px solid #E1E8F0;border-radius:20px;padding:30px 26px;overflow:hidden;display:flex;flex-direction:column;transition:transform .22s ease,box-shadow .22s ease}'
            . '.ss-prod-card::before{content:"";position:absolute;inset:0 0 auto;height:4px;background:linear-gradient(90deg,#00C896,#00A3FF)}'
            . '.ss-prod-card.v2::before{background:linear-gradient(90deg,#00A3FF,#8B5CF6)}'
            . '.ss-prod-card.v3::before{background:linear-gradient(90deg,#F59E0B,#00C896)}'
            . '.ss-prod-card:hover{transform:translateY(-4px);box-shadow:0 26px 56px rgba(15,23,42,.1)}'
            . '.ss-prod-badge{align-self:flex-start;font-size:9.5px;font-weight:800;letter-spacing:.09em;text-transform:uppercase;padding:4px 10px;border-radius:99px;background:#E7F8F1;color:#00845F;margin-bottom:16px}'
            . '.ss-prod-badge.dev{background:#FFF6E5;color:#A16207}'
            . '.ss-prod-icon{font-size:26px;margin-bottom:12px}'
            . '.ss-prod-card h3{font-family:"Space Grotesk",sans-serif;font-size:1.08rem;font-weight:700;letter-spacing:-.01em;margin:0 0 8px;color:#0F172A}'
            . '.ss-prod-card p{font-size:.9rem;line-height:1.6;color:#5B6773;margin:0 0 20px;flex:1}'
            . '.ss-prod-link{font-size:12.5px;font-weight:700;color:#00845F;text-decoration:none;display:inline-flex;align-items:center;gap:6px}'
            . '.ss-prod-link:hover{color:#00A67D}'

            . '.ss-bento{display:grid;grid-template-columns:repeat(4,1fr);grid-auto-rows:150px;gap:14px}'
            . '.ss-bento-item{position:relative;background:#fff;border:1px solid #E1E8F0;border-radius:16px;padding:20px 20px;overflow:hidden;transition:border-color .2s ease,box-shadow .2s ease}'
            . '.ss-bento-item:hover{border-color:rgba(0,200,150,.5);box-shadow:0 16px 36px rgba(15,23,42,.07)}'
            . '.ss-bento-item.big{grid-column:span 2;grid-row:span 2;background:linear-gradient(150deg,#0F172A,#15283F);border-color:rgba(0,200,150,.25);display:flex;flex-direction:column;justify-content:flex-end}'
            . '.ss-bento-item.big h3{color:#fff;font-size:1.35rem}'
            . '.ss-bento-item.big p{color:#A8BBCD;font-size:.95rem}'
            . '.ss-bento-item.big .ss-bento-ico{background:rgba(0,200,150,.16);color:#00E0A8}'
            . '.ss-bento-ico{width:36px;height:36px;border-radius:10px;background:#F1F6FA;display:flex;align-items:center;justify-content:center;font-size:17px;margin-bottom:12px}'
            . '.ss-bento-item h3{font-size:.95rem;font-weight:700;margin:0 0 6px;color:#0F172A;letter-spacing:-.01em}'
            . '.ss-bento-item p{font-size:.8rem;line-height:1.5;color:#64748B;margin:0}'
            . '.ss-bento-num{position:absolute;top:14px;right:16px;font-family:ui-monospace,monospace;font-size:10px;color:#B8C4D0;font-weight:700}'
            . '.ss-bento-item.big .ss-bento-num{color:rgba(255,255,255,.25)}'

            . '.ss-ind{display:grid;grid-template-columns:repeat(5,1fr);gap:12px}'
            . '.ss-ind-card{background:#fff;border:1px solid #E1E8F0;border-radius:14px;padding:16px 14px;transition:all .18s ease}'
            . '.ss-ind-card:hover{border-color:rgba(0,200,150,.5);transform:translateY(-2px)}'
            . '.ss-ind-ico{font-size:18px;margin-bottom:8px;display:block}'
            . '.ss-ind-card b{display:block;font-size:.83rem;font-weight:700;color:#0F172A;margin-bottom:4px;letter-spacing:-.01em}'
            . '.ss-ind-card span{display:block;font-size:.72rem;line-height:1.5;color:#7A8694}'

            . '.ss-item-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:14px}'
            . '.ss-item-chip{font-size:10.5px;font-weight:600;color:#0077C2;background:#EAF5FE;border:1px solid #D3EBFB;border-radius:99px;padding:3px 9px}'
            . '.ss-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}'
            . '.ss-step{position:relative;background:#fff;border:1px solid #E1E8F0;border-radius:16px;padding:24px 22px 20px;transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease}'
            . '.ss-step:hover{transform:translateY(-3px);box-shadow:0 18px 40px rgba(15,23,42,.08);border-color:rgba(0,200,150,.4)}'
            . '.ss-step-num{display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:12px;background:#EAFBF4;color:#00845F;font-family:"Space Grotesk",sans-serif;font-weight:700;font-size:15px;letter-spacing:.02em;margin-bottom:14px}'
            . '.ss-step h3{font-family:"Space Grotesk",sans-serif;font-size:1rem;font-weight:700;letter-spacing:-.01em;margin:0 0 6px;color:#0F172A;text-transform:uppercase;font-size:.85rem;letter-spacing:.06em}'
            . '.ss-step p{font-size:.9rem;line-height:1.6;color:#5B6773;margin:0}'
            . '.ss-note{text-align:center;color:#7A8694;font-size:.88rem;margin-top:26px}'
            . '.ss-cmp{display:grid;grid-template-columns:1fr 1fr;gap:0;border-radius:20px;overflow:hidden;border:1px solid #E1E8F0;background:#fff}'
            . '.ss-cmp-col{padding:34px 32px}'
            . '.ss-cmp-col.trad{background:#FBFCFD;border-right:1px solid #E8EDF3}'
            . '.ss-cmp-col.intsol{background:linear-gradient(160deg,#F2FCF8,#FFFFFF)}'
            . '.ss-cmp-h{display:flex;align-items:center;gap:10px;margin-bottom:20px}'
            . '.ss-cmp-h b{font-family:"Space Grotesk",sans-serif;font-size:1.05rem;font-weight:700;color:#0F172A}'
            . '.ss-cmp-h .dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}'
            . '.ss-cmp-col.trad .dot{background:#CBD5E1}.ss-cmp-col.intsol .dot{background:#00C896}'
            . '.ss-cmp-row{display:flex;gap:10px;align-items:flex-start;padding:10px 0;border-bottom:1px dashed #EDF1F5;font-size:.88rem;line-height:1.5;color:#4A5561}'
            . '.ss-cmp-row:last-child{border-bottom:none}'
            . '.ss-cmp-mark{flex-shrink:0;width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;margin-top:1px}'
            . '.ss-cmp-col.trad .ss-cmp-mark{background:#F1F5F9;color:#94A3B8}'
            . '.ss-cmp-col.intsol .ss-cmp-mark{background:#DCF7EC;color:#00845F}'

            . '@media(max-width:960px){.ss-stack{grid-template-columns:1fr;gap:30px}.ss-prod{grid-template-columns:1fr}.ss-eco-grid{grid-template-columns:1fr}.ss-eco-conn{display:none}.ss-bento{grid-template-columns:repeat(2,1fr);grid-auto-rows:auto}.ss-bento-item.big{grid-column:span 2;grid-row:span 1}.ss-ind{grid-template-columns:repeat(2,1fr)}.ss-cmp{grid-template-columns:1fr}.ss-cmp-col.trad{border-right:none;border-bottom:1px solid #E8EDF3}.ss-steps{grid-template-columns:1fr 1fr}}'
            . '@media(max-width:560px){.ss-light,.ss-white,.ss-dark{padding:64px 0}.ss-ind{grid-template-columns:1fr}.ss-eco-hub-card{padding:16px 22px}.ss-steps{grid-template-columns:1fr}}'
            . '</style>';

        $html = '<div class="ss">' . $css;

        if ($variant === 'ecosystem') {
            $html .= '<section class="ss-light" id="ecosystem"><div class="ss-wrap">';
            $html .= '<div class="ss-head"><span class="ss-kicker">' . $e($c['kicker'] ?? '') . '</span><h2 class="ss-title">' . $e($c['title'] ?? '') . '</h2><p class="ss-sub">' . $e($c['subtitle'] ?? '') . '</p></div>';
            $html .= '<div class="ss-eco"><div class="ss-eco-hub"><div class="ss-eco-hub-card"><b>' . $e($c['hub_title'] ?? 'The Intsolcom Ecosystem') . '</b><span>' . $e($c['hub_sub'] ?? 'United States & Colombia') . '</span></div></div>';
            $html .= '<div class="ss-eco-conn"><i></i><i></i><i></i></div><div class="ss-eco-grid">';
            foreach ($items as $it) {
                $html .= '<article class="ss-eco-card">';
                if (!empty($it['tag'])) $html .= '<span class="ss-eco-tag">' . $e($it['tag']) . '</span>';
                if (!empty($it['icon'])) $html .= '<div class="ss-eco-icon">' . $e($it['icon']) . '</div>';
                $html .= '<h3>' . $e($it['title'] ?? '') . '</h3><p>' . $e($it['desc'] ?? '') . '</p>';
                $chips = array_filter(array_map('trim', explode(',', (string)($it['chips'] ?? ''))));
                if ($chips) {
                    $html .= '<div class="ss-item-chips">';
                    foreach ($chips as $chip) $html .= '<span class="ss-item-chip">' . $e($chip) . '</span>';
                    $html .= '</div>';
                }
                $html .= '</article>';
            }
            $html .= '</div></div></div></section>';
        } elseif ($variant === 'stack') {
            $html .= '<section class="ss-dark" id="neardev"><div class="ss-wrap"><div class="ss-stack">';
            $html .= '<div class="ss-stack-left"><div class="ss-head"><span class="ss-kicker">' . $e($c['kicker'] ?? '') . '</span><h2 class="ss-title">' . $e($c['title'] ?? '') . '</h2><p class="ss-sub">' . $e($c['subtitle'] ?? '') . '</p></div>';
            $html .= '<div class="ss-chips">';
            foreach ($this->safeJson($c['chips'] ?? []) as $chip) {
                $html .= '<span class="ss-chip">' . $e(is_array($chip) ? ($chip['text'] ?? '') : $chip) . '</span>';
            }
            $html .= '</div></div>';
            $html .= '<div class="ss-term"><div class="ss-term-bar"><i></i><i></i><i></i><span>intsolcom@nearshore:~$ team --list</span></div><div class="ss-term-body">';
            foreach ($items as $it) {
                $html .= '<div class="ss-term-row"><div class="ss-term-av">' . $e($it['icon'] ?? '{}') . '</div><div><div class="ss-term-role">' . $e($it['title'] ?? '') . '</div><div class="ss-term-tech">' . $e($it['desc'] ?? '') . '</div></div></div>';
            }
            $html .= '</div></div></div></div></section>';
        } elseif ($variant === 'products') {
            $html .= '<section class="ss-white" id="products"><div class="ss-wrap">';
            $html .= '<div class="ss-head"><span class="ss-kicker">' . $e($c['kicker'] ?? '') . '</span><h2 class="ss-title">' . $e($c['title'] ?? '') . '</h2><p class="ss-sub">' . $e($c['subtitle'] ?? '') . '</p></div>';
            $html .= '<div class="ss-prod">';
            foreach ($items as $i => $it) {
                $dev = stripos((string)($it['badge'] ?? ''), 'development') !== false;
                $html .= '<article class="ss-prod-card' . ($i === 1 ? ' v2' : ($i === 2 ? ' v3' : '')) . '">';
                if (!empty($it['badge'])) $html .= '<span class="ss-prod-badge' . ($dev ? ' dev' : '') . '">' . $e($it['badge']) . '</span>';
                if (!empty($it['icon'])) $html .= '<div class="ss-prod-icon">' . $e($it['icon']) . '</div>';
                $html .= '<h3>' . $e($it['title'] ?? '') . '</h3><p>' . $e($it['desc'] ?? '') . '</p>';
                if (!empty($it['url'])) {
                    $label = $it['link_label'] ?? ($es ? 'Solicitar demo' : 'Request demo');
                    $html .= '<a class="ss-prod-link" href="' . $e($it['url']) . '" target="_blank" rel="noopener">' . $e($label) . ' <span aria-hidden="true">&#8599;</span></a>';
                }
                $html .= '</article>';
            }
            $html .= '</div></div></section>';
        } elseif ($variant === 'industries') {
            $html .= '<section class="ss-white" id="industries"><div class="ss-wrap">';
            $html .= '<div class="ss-head"><span class="ss-kicker">' . $e($c['kicker'] ?? '') . '</span><h2 class="ss-title">' . $e($c['title'] ?? '') . '</h2><p class="ss-sub">' . $e($c['subtitle'] ?? '') . '</p></div>';
            $html .= '<div class="ss-ind">';
            foreach ($items as $it) {
                $html .= '<div class="ss-ind-card">' . (!empty($it['icon']) ? '<span class="ss-ind-ico">' . $e($it['icon']) . '</span>' : '') . '<b>' . $e($it['title'] ?? '') . '</b><span>' . $e($it['desc'] ?? '') . '</span></div>';
            }
            $html .= '</div></div></section>';
        } elseif ($variant === 'steps') {
            $html .= '<section class="ss-white" id="methodology"><div class="ss-wrap">';
            $html .= '<div class="ss-head"><span class="ss-kicker">' . $e($c['kicker'] ?? '') . '</span><h2 class="ss-title">' . $e($c['title'] ?? '') . '</h2><p class="ss-sub">' . $e($c['subtitle'] ?? '') . '</p></div>';
            $html .= '<div class="ss-steps">';
            foreach ($items as $i => $it) {
                $html .= '<article class="ss-step"><span class="ss-step-num">' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) . '</span><h3>' . $e($it['title'] ?? '') . '</h3><p>' . $e($it['desc'] ?? '') . '</p></article>';
            }
            $html .= '</div>';
            if (!empty($c['note'])) $html .= '<p class="ss-note">' . $e($c['note']) . '</p>';
            $html .= '</div></section>';
        } elseif ($variant === 'comparison') {
            $html .= '<section class="ss-light" id="comparison"><div class="ss-wrap">';
            $html .= '<div class="ss-head"><span class="ss-kicker">' . $e($c['kicker'] ?? '') . '</span><h2 class="ss-title">' . $e($c['title'] ?? '') . '</h2><p class="ss-sub">' . $e($c['subtitle'] ?? '') . '</p></div>';
            $html .= '<div class="ss-cmp">';
            foreach ($items as $i => $it) {
                $cls = $i === 0 ? 'trad' : 'intsol';
                $html .= '<div class="ss-cmp-col ' . $cls . '"><div class="ss-cmp-h"><span class="dot"></span><b>' . $e($it['title'] ?? '') . '</b></div>';
                $parts = array_filter(array_map('trim', explode('·', (string)($it['desc'] ?? ''))));
                foreach ($parts as $p) {
                    $mark = $i === 0 ? '&#10005;' : '&#10003;';
                    $html .= '<div class="ss-cmp-row"><span class="ss-cmp-mark">' . $mark . '</span><span>' . $e($p) . '</span></div>';
                }
                $html .= '</div>';
            }
            $html .= '</div></div></section>';
        } else {
            $html .= '<section class="ss-light" id="capabilities"><div class="ss-wrap">';
            $html .= '<div class="ss-head"><span class="ss-kicker">' . $e($c['kicker'] ?? '') . '</span><h2 class="ss-title">' . $e($c['title'] ?? '') . '</h2><p class="ss-sub">' . $e($c['subtitle'] ?? '') . '</p></div>';
            $html .= '<div class="ss-bento">';
            foreach ($items as $i => $it) {
                $big = $i === 0;
                $html .= '<article class="ss-bento-item' . ($big ? ' big' : '') . '">';
                $html .= '<span class="ss-bento-num">' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) . '</span>';
                if (!empty($it['icon'])) $html .= '<div class="ss-bento-ico">' . $e($it['icon']) . '</div>';
                $html .= '<h3>' . $e($it['title'] ?? '') . '</h3><p>' . $e($it['desc'] ?? '') . '</p></article>';
            }
            $html .= '</div></div></section>';
        }

        $html .= '</div>';
        return $html;
    }
}
