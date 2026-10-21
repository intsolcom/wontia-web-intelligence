<?php
namespace App\Services;

class CookieConsentService
{
    public static function render(): string
    {
        $gaId = \App\Core\Config::get('ga_measurement_id', '');
        $gaScript = '';
        if ($gaId) {
            $gaScript = "
    <script async src='https://www.googletagmanager.com/gtag/js?id=$gaId'></script>
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    function loadGA() {
        if (localStorage.getItem('wontia_cookie_consent') === 'all' || (localStorage.getItem('wontia_cookie_consent') && JSON.parse(localStorage.getItem('wontia_cookie_consent')||'{}').analytics)) {
            gtag('config', '$gaId');
        }
    }
    loadGA();
    </script>";
        }

        $es = \App\Core\Lang::current() === 'es';
        $ct = $es
            ? ['text' => 'Usamos cookies para mejorar tu experiencia.', 'more' => 'Más información', 'customize' => 'Personalizar', 'reject' => 'Rechazar', 'accept' => 'Aceptar todo', 'ask' => '¿Permitir cookies de analítica?']
            : ['text' => 'We use cookies to improve your experience.', 'more' => 'Learn more', 'customize' => 'Customize', 'reject' => 'Reject', 'accept' => 'Accept All', 'ask' => 'Allow analytics cookies?'];

        return $gaScript . '
<style>
#wontia-cookie-banner{position:fixed;bottom:0;left:0;right:0;z-index:999999;background:#1a1d27;color:#e1e4ed;padding:16px 24px;font-size:13px;line-height:1.6;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;box-shadow:0 -4px 20px rgba(0,0,0,.2)}
#wontia-cookie-banner a{color:#B89EFF;text-decoration:underline}
.wontia-cookie-btns{display:flex;gap:8px;flex-wrap:wrap}
.wontia-cookie-btns button{padding:8px 18px;border-radius:8px;border:none;cursor:pointer;font-size:12px;font-weight:600}
.wontia-cookie-accept{background:#00B87D;color:#fff}
.wontia-cookie-reject{background:#2a2d3a;color:#8b8fa3}
.wontia-cookie-custom{background:transparent;color:#B89EFF;border:1px solid #B89EFF!important}
</style>
<div id="wontia-cookie-banner" style="display:none">
  <div>' . htmlspecialchars($ct['text'], ENT_QUOTES, 'UTF-8') . ' <a href="/privacy">' . htmlspecialchars($ct['more'], ENT_QUOTES, 'UTF-8') . '</a></div>
  <div class="wontia-cookie-btns">
    <button class="wontia-cookie-custom" onclick="wontiaCookieCustomize()">' . htmlspecialchars($ct['customize'], ENT_QUOTES, 'UTF-8') . '</button>
    <button class="wontia-cookie-reject" onclick="wontiaCookieReject()">' . htmlspecialchars($ct['reject'], ENT_QUOTES, 'UTF-8') . '</button>
    <button class="wontia-cookie-accept" onclick="wontiaCookieAccept()">' . htmlspecialchars($ct['accept'], ENT_QUOTES, 'UTF-8') . '</button>
  </div>
</div>
<script>
(function(){
    if (localStorage.getItem("wontia_cookie_consent")) return;
    document.getElementById("wontia-cookie-banner").style.display = "flex";
})();
function wontiaCookieAccept(){localStorage.setItem("wontia_cookie_consent","all");document.getElementById("wontia-cookie-banner").remove();if(typeof loadGA==="function")loadGA()}
function wontiaCookieReject(){localStorage.setItem("wontia_cookie_consent","necessary");document.getElementById("wontia-cookie-banner").remove()}
function wontiaCookieCustomize(){var c=confirm(' . json_encode($ct['ask'], JSON_UNESCAPED_UNICODE) . ');localStorage.setItem("wontia_cookie_consent",c?"all":"necessary");document.getElementById("wontia-cookie-banner").remove();if(c&&typeof loadGA==="function")loadGA()}
</script>';
    }
}
