<?php
namespace App\Services;

class CookieConsentService
{
    public static function render(): string
    {
        $trackingActive = false;
        try {
            $trackingActive = TrackingConversionService::isEnabled();
        } catch (\Throwable $e) {
        }

        $gaId = '';
        if (!$trackingActive) {
            $gaId = trim((string)\App\Core\Config::get('ga_measurement_id', ''));
            if ($gaId === '') {
                try {
                    $stmt = \App\Core\Database::instance()->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = 'ga_measurement_id' LIMIT 1");
                    $stmt->execute();
                    $gaId = trim((string)($stmt->fetchColumn() ?: ''));
                } catch (\Throwable $e) {
                }
            }
        }

        $gaId = preg_replace('/[^A-Z0-9\-]/i', '', (string)$gaId);
        $gaScript = '';
        if ($gaId !== '') {
            $gaScript = "
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    window.__wwiGaConfigured = false;
    function loadGA() {
        var raw = '';
        try { raw = localStorage.getItem('wontia_cookie_consent') || ''; } catch (e) {}
        if (!raw) {
            var cm = document.cookie.match(/(?:^|; )sonika_cookie_consent=([^;]*)/);
            if (cm) { try { raw = decodeURIComponent(cm[1]); } catch (e) { raw = cm[1]; } }
        }
        var allowed = false;
        if (raw === 'all') allowed = true;
        else if (raw && raw !== 'necessary') {
            try { allowed = !!JSON.parse(raw).analytics; } catch (e) {}
        }
        if (!allowed) return;
        if (!document.querySelector('script[data-ga-loader]')) {
            var s = document.createElement('script');
            s.async = true;
            s.src = 'https://www.googletagmanager.com/gtag/js?id=$gaId';
            s.setAttribute('data-ga-loader', '1');
            document.head.appendChild(s);
        }
        if (!window.__wwiGaConfigured) {
            gtag('config', '$gaId');
            window.__wwiGaConfigured = true;
        }
    }
    loadGA();
    </script>";
        }

        $es = \App\Core\Lang::current() === 'es';
        $ct = $es
            ? ['text' => 'Usamos cookies para mejorar tu experiencia.', 'more' => 'Más información', 'customize' => 'Personalizar', 'reject' => 'Solo necesarias', 'accept' => 'Aceptar todo', 'save' => 'Guardar preferencias', 'analytics' => 'Analítica', 'marketing' => 'Marketing', 'title' => 'Preferencias de cookies', 'ask' => '¿Permitir cookies de analítica?']
            : ['text' => 'We use cookies to improve your experience.', 'more' => 'Learn more', 'customize' => 'Customize', 'reject' => 'Necessary only', 'accept' => 'Accept all', 'save' => 'Save preferences', 'analytics' => 'Analytics', 'marketing' => 'Marketing', 'title' => 'Cookie preferences', 'ask' => 'Allow analytics cookies?'];

        $trackingHtml = '';
        try {
            $trackingHtml = TrackingConversionService::footerHtml();
        } catch (\Throwable $e) {
            $trackingHtml = '';
        }

        return $gaScript . '
<style>
#wontia-cookie-banner{position:fixed;bottom:0;left:0;right:0;z-index:999999;background:#1a1d27;color:#e1e4ed;padding:16px 24px;font-size:13px;line-height:1.6;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;box-shadow:0 -4px 20px rgba(0,0,0,.2)}
#wontia-cookie-banner a{color:#B89EFF;text-decoration:underline}
.wontia-cookie-btns{display:flex;gap:8px;flex-wrap:wrap}
.wontia-cookie-btns button{padding:8px 18px;border-radius:8px;border:none;cursor:pointer;font-size:12px;font-weight:600}
.wontia-cookie-accept{background:#00B87D;color:#fff}
.wontia-cookie-reject{background:#2a2d3a;color:#8b8fa3}
.wontia-cookie-custom{background:transparent;color:#B89EFF;border:1px solid #B89EFF!important}
#wontia-cookie-panel{flex-basis:100%;border-top:1px solid rgba(255,255,255,.12);padding-top:12px;margin-top:4px;display:flex;align-items:center;gap:16px;flex-wrap:wrap}
#wontia-cookie-panel label{display:inline-flex;align-items:center;gap:7px;font-size:12px;color:#cdd3e0;cursor:pointer}
#wontia-cookie-panel input{accent-color:#00B87D}
.wontia-cookie-save{background:#B89EFF;color:#1a1d27}
#wontia-cookie-reopen{position:fixed;left:14px;bottom:14px;z-index:999998;border:1px solid rgba(255,255,255,.28);background:rgba(26,29,39,.92);color:#e1e4ed;border-radius:999px;padding:7px 11px;font-size:11px;font-weight:700;cursor:pointer;box-shadow:0 8px 22px rgba(0,0,0,.22);display:none}
#wontia-cookie-reopen:hover{background:#2a2d3a}
@media(max-width:640px){#wontia-cookie-banner{padding:13px 14px;font-size:12px}.wontia-cookie-btns{width:100%}.wontia-cookie-btns button{flex:1;min-width:120px}}
</style>
<div id="wontia-cookie-banner" style="display:none" role="dialog" aria-label="' . htmlspecialchars($ct['title'], ENT_QUOTES, 'UTF-8') . '">
  <div>' . htmlspecialchars($ct['text'], ENT_QUOTES, 'UTF-8') . ' <a href="/privacy">' . htmlspecialchars($ct['more'], ENT_QUOTES, 'UTF-8') . '</a></div>
  <div class="wontia-cookie-btns">
    <button type="button" class="wontia-cookie-custom" onclick="wontiaCookieCustomize()">' . htmlspecialchars($ct['customize'], ENT_QUOTES, 'UTF-8') . '</button>
    <button type="button" class="wontia-cookie-reject" onclick="wontiaCookieReject()">' . htmlspecialchars($ct['reject'], ENT_QUOTES, 'UTF-8') . '</button>
    <button type="button" class="wontia-cookie-accept" onclick="wontiaCookieAccept()">' . htmlspecialchars($ct['accept'], ENT_QUOTES, 'UTF-8') . '</button>
  </div>
  <div id="wontia-cookie-panel" hidden>
    <strong style="font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#8b8fa3">' . htmlspecialchars($ct['title'], ENT_QUOTES, 'UTF-8') . '</strong>
    <label><input type="checkbox" id="wontia-consent-analytics"> ' . htmlspecialchars($ct['analytics'], ENT_QUOTES, 'UTF-8') . '</label>
    <label><input type="checkbox" id="wontia-consent-marketing"> ' . htmlspecialchars($ct['marketing'], ENT_QUOTES, 'UTF-8') . '</label>
    <button type="button" class="wontia-cookie-save" onclick="wontiaCookieSave()">' . htmlspecialchars($ct['save'], ENT_QUOTES, 'UTF-8') . '</button>
  </div>
</div>
<button type="button" id="wontia-cookie-reopen" onclick="wontiaCookieOpen()" aria-label="' . htmlspecialchars($ct['title'], ENT_QUOTES, 'UTF-8') . '">Cookies</button>
<script>
function wwCookieSet(v) {
    var maxAge = 15552000;
    document.cookie = "sonika_cookie_consent=" + encodeURIComponent(v) + "; Path=/; Max-Age=" + maxAge + "; SameSite=Lax" + (location.protocol === "https:" ? "; Secure" : "");
    try { localStorage.setItem("wontia_cookie_consent", v); } catch (e) {}
    try { window.dispatchEvent(new CustomEvent("wontia:consent", { detail: v })); } catch (e) {}
}
function wwCookieParse(raw) {
    var out = { necessary: true, analytics: false, marketing: false };
    raw = String(raw || "").trim();
    if (!raw) return out;
    if (raw === "all") { out.analytics = true; out.marketing = true; return out; }
    if (raw === "necessary") return out;
    try {
        var d = JSON.parse(raw);
        if (d && typeof d === "object") { out.analytics = !!d.analytics; out.marketing = !!d.marketing; }
    } catch (e) {}
    return out;
}
function wwCookieRaw() {
    var raw = "";
    try { raw = localStorage.getItem("wontia_cookie_consent") || ""; } catch (e) {}
    if (!raw) {
        var m = document.cookie.match(/(?:^|; )sonika_cookie_consent=([^;]*)/);
        if (m) { try { raw = decodeURIComponent(m[1]); } catch (e) { raw = m[1]; } }
    }
    return raw;
}
function wwCookieCurrent() { return wwCookieParse(wwCookieRaw()); }
function wwCookieHas() { return wwCookieRaw() !== ""; }
function wwCookieJson(a, m) { return JSON.stringify({ necessary: true, analytics: !!a, marketing: !!m }); }
function wwCookieSyncChecks() {
    var c = wwCookieCurrent();
    var a = document.getElementById("wontia-consent-analytics");
    var m = document.getElementById("wontia-consent-marketing");
    if (a) a.checked = !!c.analytics;
    if (m) m.checked = !!c.marketing;
}
function wwCookieApply(a, m) {
    var value = wwCookieJson(a, m);
    wwCookieSet(value);
    var b = document.getElementById("wontia-cookie-banner");
    if (b) b.style.display = "none";
    var re = document.getElementById("wontia-cookie-reopen");
    if (re) re.style.display = "block";
    if (window.WWITracking) {
        if (a || m) window.WWITracking.refresh();
        else window.WWITracking.revoke();
    } else if (a || m) {
        location.reload();
    }
    if (a && typeof loadGA === "function") loadGA();
}
function wontiaCookieAccept() { wwCookieApply(true, true); }
function wontiaCookieReject() { wwCookieApply(false, false); }
function wontiaCookieCustomize() {
    var b = document.getElementById("wontia-cookie-banner");
    if (b) b.style.display = "flex";
    var p = document.getElementById("wontia-cookie-panel");
    if (p) p.hidden = false;
    wwCookieSyncChecks();
}
function wontiaCookieSave() {
    var a = document.getElementById("wontia-consent-analytics");
    var m = document.getElementById("wontia-consent-marketing");
    wwCookieApply(!!(a && a.checked), !!(m && m.checked));
}
function wontiaCookieOpen() {
    var b = document.getElementById("wontia-cookie-banner");
    if (b) b.style.display = "flex";
    var p = document.getElementById("wontia-cookie-panel");
    if (p) p.hidden = false;
    wwCookieSyncChecks();
    if (b) { try { b.scrollIntoView({ behavior: "smooth", block: "end" }); } catch (e) {} }
}
(function () {
    var rawLocal = "";
    try { rawLocal = localStorage.getItem("wontia_cookie_consent") || ""; } catch (e) {}
    if (rawLocal) wwCookieSet(rawLocal);
    var b = document.getElementById("wontia-cookie-banner");
    var re = document.getElementById("wontia-cookie-reopen");
    if (wwCookieHas()) {
        if (b) b.style.display = "none";
        if (re) re.style.display = "block";
    } else if (b) {
        b.style.display = "flex";
    }
    wwCookieSyncChecks();
    document.addEventListener("click", function (e) {
        var a = e.target && e.target.closest ? e.target.closest("[data-cookie-prefs]") : null;
        if (!a) return;
        e.preventDefault();
        wontiaCookieOpen();
    });
})();
</script>' . $trackingHtml;
    }
}
