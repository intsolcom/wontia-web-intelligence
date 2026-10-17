<?php
/**
 * WWI — Capability Robot ("Screen Agent").
 * Script inline mínimo (<1 KB), sin red, sin PII, <1 ms: marca <html> con
 * data-cap="high|mid|low", data-input, data-motion, data-data para permitir
 * degradación elegante (interactivo -> estático + leyenda). SSR asume "high".
 */
if (!function_exists('wwi_cap_head')) {
    function wwi_cap_head(): string {
        return <<<'HTML'
<script>(function(){try{
var d=document.documentElement,n=navigator,c=n.connection||{},
wa=!!(window.AudioContext||window.webkitAudioContext),
gl=(function(){try{var x=document.createElement('canvas');return !!(x.getContext&&(x.getContext('webgl')||x.getContext('experimental-webgl')))}catch(e){return false}})(),
mm=function(q){try{return matchMedia(q).matches}catch(e){return false}},
cores=n.hardwareConcurrency||4,mem=n.deviceMemory||4,et=(c.effectiveType||''),save=!!c.saveData,
reduce=mm('(prefers-reduced-motion: reduce)'),dsave=mm('(prefers-reduced-data: reduce)')||save,touch=mm('(pointer: coarse)'),
s=2;if(cores<=2)s--;if(mem<=2)s--;if(et==='slow-2g'||et==='2g'||et==='3g')s--;if(!wa)s--;if(!gl)s--;
var cap=(s>=2?'high':(s===1?'mid':'low'));
d.setAttribute('data-cap',cap);d.setAttribute('data-input',touch?'touch':'pointer');d.setAttribute('data-motion',reduce?'reduced':'full');d.setAttribute('data-data',dsave?'save':'full');
window.WWICap={cap:function(){return d.getAttribute('data-cap')||'high'},isLow:function(){return this.cap()==='low'},reduced:function(){return d.getAttribute('data-motion')==='reduced'},
notice:function(text,rec){var el=document.getElementById('wwi-cap-notice');if(!el)return;el.innerHTML='<span class="wwi-cap-notice__t">'+text+'</span>'+(rec?' <b class="wwi-cap-notice__d">'+rec+'</b>':'')+' <button type="button" class="wwi-cap-notice__x" aria-label="Cerrar" onclick="this.parentNode.hidden=true">\u2715</button>';el.hidden=false}};
}catch(e){}})();</script>
HTML;
    }
}
if (!function_exists('wwi_cap_notice')) {
    function wwi_cap_notice(): string {
        return '<div id="wwi-cap-notice" class="wwi-cap-notice" role="status" hidden></div>';
    }
}
if (!function_exists('wwi_cap_css')) {
    function wwi_cap_css(): string {
        return '[hidden]{display:none!important}.wwi-cap-notice{position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:9500;max-width:min(92vw,560px);background:#111;color:#fff;border-radius:12px;padding:10px 14px;font-size:12.5px;line-height:1.45;box-shadow:0 12px 34px rgba(0,0,0,.3);display:flex;gap:8px;align-items:center}.wwi-cap-notice__d{color:#ffd9db}.wwi-cap-notice__x{border:0;background:transparent;color:#fff;cursor:pointer;font-size:14px;line-height:1}html[data-data="save"] .wwi-cap-heavy{display:none!important}';
    }
}
