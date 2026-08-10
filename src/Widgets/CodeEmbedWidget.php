<?php
namespace App\Widgets;

class CodeEmbedWidget extends Widget
{
    public static function meta(): array
    {
        return [
            'id' => 'codeembed',
            'name' => 'Code Embed',
            'icon' => 'code',
            'category' => 'embed',
            'version' => '1.0.0',
            'description' => 'Universal code injector: HTML, JS, CSS, iframes, external scripts. Sandbox, Shadow DOM, lazy load.',
        ];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'mode', 'label' => 'Embed Mode', 'type' => 'select', 'options' => [
                'html' => 'HTML Block',
                'javascript' => 'JavaScript',
                'iframe' => 'iFrame',
                'stylesheet' => 'CSS Stylesheet',
                'external-script' => 'External Script',
                'external-style' => 'External Stylesheet',
                'mixed' => 'Mixed HTML/JS/CSS',
            ], 'default' => 'html'],
            ['key' => 'code', 'label' => 'Code / Content', 'type' => 'code', 'default' => ''],
            ['key' => 'external_url', 'label' => 'External URL', 'type' => 'text', 'default' => '', 'help' => 'For iframe src, script src, or stylesheet href'],
            ['key' => 'sandbox', 'label' => 'Sandbox (isolated iframe)', 'type' => 'toggle', 'default' => false, 'help' => 'Wrap content in a sandboxed iframe for security'],
            ['key' => 'shadow_dom', 'label' => 'Shadow DOM Isolation', 'type' => 'toggle', 'default' => false, 'help' => 'Isolate styles using Shadow DOM (mode=html only)'],
            ['key' => 'lazy_load', 'label' => 'Lazy Load', 'type' => 'toggle', 'default' => false, 'help' => 'Defer loading until element is visible in viewport'],
            ['key' => 'position', 'label' => 'Injection Position', 'type' => 'select', 'options' => [
                'body' => 'Body (inline)',
                'head' => 'Head (injects into <head>)',
                'before-body-end' => 'Before </body>',
            ], 'default' => 'body'],
            ['key' => 'condition_device', 'label' => 'Device Condition', 'type' => 'select', 'options' => [
                '' => 'All Devices',
                'desktop' => 'Desktop Only',
                'tablet' => 'Tablet Only',
                'mobile' => 'Mobile Only',
                'desktop,tablet' => 'Desktop + Tablet',
                'tablet,mobile' => 'Tablet + Mobile',
            ], 'default' => ''],
            ['key' => 'condition_path', 'label' => 'URL Path Match (regex)', 'type' => 'text', 'default' => '', 'help' => 'Only load on matching URLs, e.g. /blog/.*'],
            ['key' => 'execute_once', 'label' => 'Execute Once Per Session', 'type' => 'toggle', 'default' => false, 'help' => 'For JS mode: run only once using sessionStorage flag'],
            ['key' => 'nonce_attr', 'label' => 'CSP Nonce', 'type' => 'text', 'default' => '', 'help' => 'Content Security Policy nonce value'],
            ['key' => 'id_attr', 'label' => 'Element ID', 'type' => 'text', 'default' => ''],
            ['key' => 'class_attr', 'label' => 'CSS Classes', 'type' => 'text', 'default' => ''],
            ['key' => 'iframe_width', 'label' => 'iFrame Width', 'type' => 'text', 'default' => '100%'],
            ['key' => 'iframe_height', 'label' => 'iFrame Height', 'type' => 'text', 'default' => '400'],
            ['key' => 'cache_bust', 'label' => 'Cache Buster', 'type' => 'toggle', 'default' => false, 'help' => 'Append timestamp to external URLs to bypass cache'],
            ['key' => 'fallback', 'label' => 'Fallback Content', 'type' => 'textarea', 'default' => '', 'help' => 'Shown if embed fails to load'],
        ];
    }

    public static function defaultConfig(): array
    {
        return [
            'mode' => 'html',
            'code' => '',
            'external_url' => '',
            'sandbox' => false,
            'shadow_dom' => false,
            'lazy_load' => false,
            'position' => 'body',
            'condition_device' => '',
            'condition_path' => '',
            'execute_once' => false,
            'nonce_attr' => '',
            'id_attr' => '',
            'class_attr' => '',
            'iframe_width' => '100%',
            'iframe_height' => '400',
            'cache_bust' => false,
            'fallback' => '',
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:linear-gradient(135deg,#1e1e2e,#2d2d44);border-radius:12px;padding:20px;font-family:monospace">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
                <span style="width:10px;height:10px;border-radius:50%;background:#ff5f56;display:inline-block"></span>
                <span style="width:10px;height:10px;border-radius:50%;background:#ffbd2e;display:inline-block"></span>
                <span style="width:10px;height:10px;border-radius:50%;background:#27c93f;display:inline-block"></span>
                <span style="font-size:11px;color:#888;margin-left:8px">code-embed</span>
            </div>
            <div style="font-size:12px;color:#a8d8a8">&lt;!-- HTML --&gt;</div>
            <div style="font-size:12px;color:#79c0ff">&lt;script&gt;</div>
            <div style="font-size:12px;color:#d2a8ff;margin-left:16px">// JavaScript</div>
            <div style="font-size:12px;color:#79c0ff">&lt;/script&gt;</div>
            <div style="font-size:12px;color:#ffa657">&lt;style&gt;</div>
            <div style="font-size:12px;color:#d2a8ff;margin-left:16px">/* CSS */</div>
            <div style="font-size:12px;color:#ffa657">&lt;/style&gt;</div>
            <div style="font-size:10px;color:#888;margin-top:10px">Sandbox &middot; Shadow DOM &middot; Lazy Load &middot; CSP-Ready</div>
        </div>';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $uid = 'ce-' . substr(md5(uniqid('', true)), 0, 10);

        if (!$this->shouldRender($c)) return '';

        if ($c['position'] === 'head') {
            return $this->renderHead($c) . '<!-- CodeEmbed:' . $uid . ' -->';
        }

        if ($c['position'] === 'before-body-end') {
            return '<!-- CodeEmbed:' . $uid . ' -->' . $this->renderBodyEnd($c);
        }

        return $this->renderBody($c, $uid);
    }

    private function shouldRender(array $c): bool
    {
        if ($c['condition_device']) {
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $isMobile = (bool) preg_match('/(android|iphone|ipad|ipod|blackberry|webos|mobile)/i', $ua);
            $isTablet = (bool) preg_match('/(ipad|tablet|android(?!.*mobile))/i', $ua) || ($isMobile && !preg_match('/mobile/i', $ua));
            $isDesktop = !$isMobile && !$isTablet;

            $devices = array_map('trim', explode(',', $c['condition_device']));
            $allowed = false;
            foreach ($devices as $d) {
                if (($d === 'desktop' && $isDesktop) || ($d === 'tablet' && $isTablet) || ($d === 'mobile' && $isMobile)) {
                    $allowed = true; break;
                }
            }
            if (!$allowed) return false;
        }

        if ($c['condition_path']) {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            if (!preg_match('#' . $c['condition_path'] . '#', $path)) return false;
        }

        return true;
    }

    private function renderHead(array $c): string
    {
        $out = '';
        $code = $c['code'];
        $url = $c['external_url'];

        if ($c['cache_bust'] && $url) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . '_cb=' . time();
        }

        $nonce = $c['nonce_attr'] ? ' nonce="' . $this->esc($c['nonce_attr']) . '"' : '';

        switch ($c['mode']) {
            case 'javascript':
                if ($c['execute_once']) {
                    $out .= '<script' . $nonce . '>' . $this->wrapOnce($c) . '</script>';
                } else {
                    $out .= '<script' . $nonce . '>' . $code . '</script>';
                }
                break;
            case 'external-script':
                if ($url) $out .= '<script src="' . $this->esc($url) . '"' . $nonce . ($c['lazy_load'] ? ' defer' : '') . '></script>';
                break;
            case 'stylesheet':
                $out .= '<style' . $nonce . '>' . $code . '</style>';
                break;
            case 'external-style':
                if ($url) $out .= '<link rel="stylesheet" href="' . $this->esc($url) . '"' . $nonce . '>';
                break;
            case 'mixed':
                $out .= $code;
                break;
            default:
                break;
        }
        return $out;
    }

    private function renderBody(array $c, string $uid): string
    {
        $id = $c['id_attr'] ? ' id="' . $this->esc($c['id_attr']) . '"' : '';
        $class = $c['class_attr'] ? ' class="' . $this->esc($c['class_attr']) . '"' : '';
        $wrapperAttrs = 'data-codeembed="' . $uid . '"' . $id . $class;

        $content = $this->buildContent($c);

        if ($c['sandbox']) {
            $srcdoc = $this->buildSrcdoc($c);
            $sandboxAttrs = 'allow-scripts allow-same-origin';
            $html = '<iframe srcdoc="' . $this->escAttr($srcdoc) . '" sandbox="' . $sandboxAttrs . '" ';
            $html .= 'width="' . $this->esc($c['iframe_width']) . '" height="' . $this->esc($c['iframe_height']) . '" ';
            $html .= 'frameborder="0" loading="lazy" style="border:none;max-width:100%" ';
            $html .= 'title="embedded content"' . $wrapperAttrs . '></iframe>';
            return $html;
        }

        if ($c['shadow_dom'] && in_array($c['mode'], ['html', 'mixed'])) {
            $shadowContent = $this->escJs($content);
            $html = '<div ' . $wrapperAttrs . '></div>';
            $html .= '<script>(function(){var el=document.querySelector(\'[data-codeembed="' . $uid . '"]\');';
            $html .= 'if(el&&el.attachShadow){var sh=el.attachShadow({mode:"open"});';
            $html .= 'sh.innerHTML=' . json_encode($content) . ';';
            $html .= '}})();</script>';
            return $html;
        }

        if ($c['lazy_load']) {
            $encoded = base64_encode($content);
            $fallback = $c['fallback'] ? $this->esc($c['fallback']) : '<div style="min-height:60px;background:#f0f0f0;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#999;font-size:12px">Loading...</div>';
            $html = '<div ' . $wrapperAttrs . '>' . $fallback . '</div>';
            $html .= '<script>(function(){var el=document.querySelector(\'[data-codeembed="' . $uid . '"]\');';
            $html .= 'if(!el)return;';
            $html .= 'var o=new IntersectionObserver(function(e){if(e[0].isIntersecting){o.disconnect();';
            $html .= 'el.outerHTML=atob(' . json_encode($encoded) . ');';
            $html .= '}},{rootMargin:"100px"});o.observe(el);})();</script>';
            return $html;
        }

        $html = '<div ' . $wrapperAttrs . '>' . $content . '</div>';
        return $html;
    }

    private function renderBodyEnd(array $c): string
    {
        return $this->buildContent($c);
    }

    private function buildContent(array $c): string
    {
        $code = $c['code'];
        $url = $c['external_url'];
        if ($c['cache_bust'] && $url) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . '_cb=' . time();
        }
        $nonce = $c['nonce_attr'] ? ' nonce="' . $this->esc($c['nonce_attr']) . '"' : '';

        switch ($c['mode']) {
            case 'html':
                return $code;

            case 'javascript':
                if ($c['execute_once']) {
                    return '<script' . $nonce . '>' . $this->wrapOnce($c) . '</script>';
                }
                return '<script' . $nonce . '>' . $code . '</script>';

            case 'iframe':
                $src = $url ?: 'about:blank';
                if (!$url && $code) {
                    $src = 'data:text/html;charset=utf-8,' . rawurlencode($code);
                }
                return '<iframe src="' . $this->esc($src) . '" width="' . $this->esc($c['iframe_width']) . '" height="' . $this->esc($c['iframe_height']) . '" frameborder="0" loading="lazy" style="border:none;max-width:100%" allowfullscreen title="embedded content"></iframe>';

            case 'stylesheet':
                return '<style' . $nonce . '>' . $code . '</style>';

            case 'external-script':
                $defer = $c['lazy_load'] ? ' defer' : '';
                if (!$url) return '<!-- CodeEmbed: no external_url provided -->';
                $nop = '';
                return '<script src="' . $this->esc($url) . '"' . $nonce . $defer . '></script>';

            case 'external-style':
                if (!$url) return '<!-- CodeEmbed: no external_url provided -->';
                return '<link rel="stylesheet" href="' . $this->esc($url) . '"' . $nonce . '>';

            case 'mixed':
                return $code;

            default:
                return $code;
        }
    }

    private function buildSrcdoc(array $c): string
    {
        $code = $c['code'];
        $url = $c['external_url'];
        $mode = $c['mode'];

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        $html .= '<style>body{margin:0;padding:0;font-family:system-ui,sans-serif}</style></head><body>';

        if ($mode === 'iframe' && $url) {
            $html .= '<iframe src="' . $this->esc($url) . '" width="100%" height="100%" frameborder="0"></iframe>';
        } elseif ($mode === 'external-script') {
            $html .= '<div id="root"></div><script src="' . $this->esc($url) . '"></script>';
        } else {
            $html .= $code;
        }

        $html .= '</body></html>';
        return $html;
    }

    private function wrapOnce(array $c): string
    {
        $uid = 'ce-once-' . substr(md5($c['code']), 0, 12);
        return 'if(!sessionStorage.getItem("' . $uid . '")){sessionStorage.setItem("' . $uid . '","1");' . $c['code'] . '}';
    }

    private function escAttr(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function escJs(string $s): string
    {
        return str_replace(['\\', "'", "\n", "\r"], ['\\\\', "\\'", '\\n', '\\r'], $s);
    }
}
