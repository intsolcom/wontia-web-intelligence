<?php
namespace App\Widgets;

class MediaSliderWidget extends Widget
{
    private static bool $assetsPrinted = false;

    public static function meta(): array
    {
        return ['id' => 'media-slider', 'name' => 'Media: Slider', 'icon' => 'image', 'category' => 'media', 'version' => '2.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'width', 'label' => 'Ancho (100, 66 o 33)', 'type' => 'text', 'inline' => true, 'default' => '100'],
            ['key' => 'align', 'label' => 'Alineacion (left, center, right)', 'type' => 'text', 'default' => 'center'],
            ['key' => 'aspect', 'label' => 'Proporcion (16:9, 4:3, 1:1, 21:9, auto)', 'type' => 'text', 'inline' => true, 'default' => '16:9'],
            ['key' => 'max_height', 'label' => 'Altura maxima px (0 = auto)', 'type' => 'text', 'default' => '620'],
            ['key' => 'pad', 'label' => 'Espaciado vertical px', 'type' => 'text', 'default' => '84'],
            ['key' => 'radius', 'label' => 'Radio de bordes px', 'type' => 'text', 'default' => '18'],
            ['key' => 'transition', 'label' => 'Transicion (slide, fade, zoom)', 'type' => 'text', 'inline' => true, 'default' => 'slide'],
            ['key' => 'kenburns', 'label' => 'Ken Burns en imagenes (1/0)', 'type' => 'text', 'default' => '0'],
            ['key' => 'thumb_style', 'label' => 'Miniaturas (dots, thumbs, bars, numbers, pill, film)', 'type' => 'text', 'inline' => true, 'default' => 'thumbs'],
            ['key' => 'thumb_count', 'label' => 'Maximo de miniaturas visibles', 'type' => 'text', 'default' => '8'],
            ['key' => 'autoplay_ms', 'label' => 'Autoplay ms (0 = desactivado)', 'type' => 'text', 'default' => '5500'],
            ['key' => 'loop', 'label' => 'Infinito (1/0)', 'type' => 'text', 'default' => '1'],
            ['key' => 'overlay', 'label' => 'Mostrar texto sobre la imagen (1/0)', 'type' => 'text', 'default' => '1'],
            ['key' => 'caption_pos', 'label' => 'Posicion del texto (bottom, top, center)', 'type' => 'text', 'default' => 'bottom'],
            ['key' => 'lightbox', 'label' => 'Lightbox al hacer clic (1/0)', 'type' => 'text', 'default' => '1'],
            ['key' => 'fullscreen', 'label' => 'Boton pantalla completa (1/0)', 'type' => 'text', 'default' => '1'],
            ['key' => 'copy_link', 'label' => 'Boton copiar enlace (1/0)', 'type' => 'text', 'default' => '1'],
            ['key' => 'video_autoplay', 'label' => 'Reproducir video activo (1/0)', 'type' => 'text', 'default' => '1'],
            ['key' => 'controls_autohide', 'label' => 'Ocultar controles al inactivo (1/0)', 'type' => 'text', 'default' => '1'],
            ['key' => 'label', 'label' => 'Etiqueta accesible', 'type' => 'text', 'default' => 'Galeria de medios'],
            ['key' => 'slides', 'label' => 'Slides', 'type' => 'repeater', 'fields' => [
                ['key' => 'type', 'label' => 'Tipo (image/youtube/vimeo/mp4)', 'type' => 'text'],
                ['key' => 'url', 'label' => 'URL o ruta', 'type' => 'text'],
                ['key' => 'poster', 'label' => 'Poster (videos)', 'type' => 'text'],
                ['key' => 'title', 'label' => 'Titulo', 'type' => 'text'],
                ['key' => 'text', 'label' => 'Texto', 'type' => 'text'],
                ['key' => 'cta_label', 'label' => 'Boton', 'type' => 'text'],
                ['key' => 'cta_url', 'label' => 'Boton URL', 'type' => 'text'],
                ['key' => 'alt', 'label' => 'Alt (SEO)', 'type' => 'text'],
            ], 'default' => [
                ['type' => 'image', 'url' => 'https://images.unsplash.com/photo-1522199755839-a2bacb67c546?w=1600&q=80', 'title' => 'Tu sitio listo para vender', 'text' => 'Diseno, contenido y velocidad en un solo lugar.', 'alt' => 'Escritorio con laptop y diseno web'],
                ['type' => 'image', 'url' => 'https://images.unsplash.com/photo-1467232004584-a241de8bcf5d?w=1600&q=80', 'title' => 'Pensado para moviles', 'text' => 'Cada seccion se adapta al espacio disponible.', 'alt' => 'Persona usando el celular'],
                ['type' => 'image', 'url' => 'https://images.unsplash.com/photo-1551434678-e076c223a692?w=1600&q=80', 'title' => 'Equipos que avanzan', 'text' => 'Contenido administrable sin codigo.', 'alt' => 'Equipo de trabajo colaborando'],
            ]],
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:#0d1024;border:1px solid rgba(148,163,184,.25);border-radius:12px;padding:14px">'
            . '<div style="height:74px;border-radius:8px;background:linear-gradient(120deg,rgba(124,60,255,.5),rgba(84,190,255,.35));margin-bottom:9px"></div>'
            . '<div style="display:flex;gap:5px">'
            . '<span style="width:34px;height:22px;border-radius:5px;background:rgba(255,255,255,.16)"></span>'
            . '<span style="width:34px;height:22px;border-radius:5px;background:rgba(255,255,255,.16)"></span>'
            . '<span style="width:34px;height:22px;border-radius:5px;background:rgba(183,140,255,.5)"></span>'
            . '<span style="width:34px;height:22px;border-radius:5px;background:rgba(255,255,255,.16)"></span>'
            . '</div><div style="font-size:10px;color:#8593ab;margin-top:8px">Media Slider · 100/66/33% · 6 miniaturas · video + imagen</div></div>';
    }

    private function assets(): string
    {
        if (self::$assetsPrinted) return '';
        self::$assetsPrinted = true;
        return <<<'HTML'
<style id="wwms-css">
.wwms{position:relative;width:100%}
.wwms-inner{max-width:1200px;margin:0 auto;padding:0 24px;box-sizing:border-box}
.wwms-block{position:relative;margin:0 auto;width:var(--wwms-w,100%)}
.wwms-block.al-left{margin-left:0;margin-right:auto}
.wwms-block.al-center{margin-left:auto;margin-right:auto}
.wwms-block.al-right{margin-left:auto;margin-right:0}
.wwms-stage{position:relative;border-radius:var(--wwms-r,18px);overflow:hidden;background:rgba(0,0,0,.35);border:1px solid var(--border,rgba(148,163,184,.18));box-shadow:0 24px 70px rgba(0,0,0,.28);contain:content}
.wwms-stage[data-ratio="16:9"]{aspect-ratio:16/9}
.wwms-stage[data-ratio="4:3"]{aspect-ratio:4/3}
.wwms-stage[data-ratio="1:1"]{aspect-ratio:1/1}
.wwms-stage[data-ratio="21:9"]{aspect-ratio:21/9}
.wwms-stage[data-ratio="auto"]{aspect-ratio:auto;min-height:240px}
.wwms-stage:fullscreen{border-radius:0;border:0}
.wwms-track{display:flex;height:100%;transition:transform .55s cubic-bezier(.22,1,.36,1);will-change:transform;touch-action:pan-y}
.wwms-track[data-trans="fade"],.wwms-track[data-trans="zoom"]{display:block;position:relative;transform:none!important}
.wwms-track[data-trans="fade"] .wwms-slide,.wwms-track[data-trans="zoom"] .wwms-slide{position:absolute;inset:0;opacity:0;transition:opacity .55s ease,transform .55s ease}
.wwms-track[data-trans="fade"] .wwms-slide.on,.wwms-track[data-trans="zoom"] .wwms-slide.on{opacity:1}
.wwms-track[data-trans="zoom"] .wwms-slide{transform:scale(1.06)}
.wwms-track[data-trans="zoom"] .wwms-slide.on{transform:scale(1)}
.wwms-slide{position:relative;min-width:100%;height:100%;overflow:hidden;display:flex;align-items:center;justify-content:center}
.wwms-slide img,.wwms-slide video,.wwms-slide iframe{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border:0;display:block;background:#0b0b12}
.wwms-slide[data-fit="contain"] img,.wwms-slide[data-fit="contain"] video{object-fit:contain}
.wwms-slide.kb.on img{animation:wwmsKB 9s ease-out forwards}
.wwms-slide.loading::after{content:'';position:absolute;inset:0;background:linear-gradient(100deg,rgba(255,255,255,.04) 20%,rgba(255,255,255,.11) 50%,rgba(255,255,255,.04) 80%);background-size:220% 100%;animation:wwmsShimmer 1.4s infinite}
.wwms-slide.error::after{content:'No se pudo cargar el medio';position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#9aa3b8;font-size:13px;background:#12121c}
.wwms-overlay{position:absolute;left:0;right:0;padding:26px;color:#fff;z-index:3;pointer-events:none}
.wwms-overlay>*{pointer-events:auto}
.wwms-overlay[data-pos="bottom"]{bottom:0;background:linear-gradient(to top,rgba(6,6,16,.9),rgba(6,6,16,.45) 55%,transparent)}
.wwms-overlay[data-pos="top"]{top:0;background:linear-gradient(to bottom,rgba(6,6,16,.9),rgba(6,6,16,.45) 55%,transparent)}
.wwms-overlay[data-pos="center"]{top:50%;transform:translateY(-50%);text-align:center;background:linear-gradient(to right,rgba(6,6,16,.55),rgba(6,6,16,.2),rgba(6,6,16,.55))}
.wwms-overlay h3{margin:0 0 6px;font-size:clamp(18px,2.2vw,26px);font-weight:800;letter-spacing:-.02em}
.wwms-overlay p{margin:0;font-size:13.5px;opacity:.85;max-width:640px;line-height:1.6}
.wwms-overlay[data-pos="center"] p{margin:0 auto}
.wwms-overlay .wwms-cta{display:inline-block;margin-top:12px;padding:10px 18px;border-radius:10px;background:#fff;color:#111;font-size:13px;font-weight:700;text-decoration:none}
.wwms-cta:hover{filter:brightness(.94)}
.wwms-btn{position:absolute;top:50%;transform:translateY(-50%);z-index:4;width:42px;height:42px;border-radius:50%;border:1px solid rgba(255,255,255,.28);background:rgba(10,10,20,.55);backdrop-filter:blur(10px);color:#fff;font-size:17px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:opacity .3s,background .18s}
.wwms-btn:hover{background:rgba(10,10,20,.8)}
.wwms-btn:focus-visible,.wwms-pause:focus-visible,.wwms-mini:focus-visible,.wwms-thumbs .t:focus-visible{outline:2px solid #b78cff;outline-offset:2px}
.wwms-prev{left:14px}
.wwms-next{right:14px}
[dir="rtl"] .wwms-prev{left:auto;right:14px}
[dir="rtl"] .wwms-next{right:auto;left:14px}
.wwms-topbar{position:absolute;top:12px;right:12px;z-index:4;display:flex;gap:8px;align-items:center;transition:opacity .3s}
.wwms-count{font-family:'JetBrains Mono',Consolas,monospace;font-size:11px;color:#fff;background:rgba(10,10,20,.6);border:1px solid rgba(255,255,255,.2);padding:5px 10px;border-radius:999px;backdrop-filter:blur(8px)}
.wwms-mini{width:32px;height:32px;border-radius:50%;border:1px solid rgba(255,255,255,.22);background:rgba(10,10,20,.6);color:#fff;cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(8px)}
.wwms-mini:hover{background:rgba(10,10,20,.85)}
.wwms-progress{position:absolute;top:0;left:0;right:0;height:3px;z-index:5;background:rgba(255,255,255,.14)}
.wwms-progress i{display:block;height:100%;width:0;background:linear-gradient(90deg,#7c3cff,#b78cff)}
.wwms[data-autohide="1"].hide-ui .wwms-btn,.wwms[data-autohide="1"].hide-ui .wwms-topbar{opacity:0;pointer-events:none}
.wwms-play{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;z-index:2;background:rgba(6,6,16,.35);cursor:pointer}
.wwms-play span{width:68px;height:68px;border-radius:50%;background:rgba(255,255,255,.94);color:#111;display:flex;align-items:center;justify-content:center;font-size:22px;box-shadow:0 18px 40px rgba(0,0,0,.4);transition:.2s}
.wwms-play:hover span{transform:scale(1.08)}
.wwms-thumbs{display:flex;gap:8px;margin-top:12px;align-items:center;justify-content:center;flex-wrap:nowrap;overflow-x:auto;padding:2px;scrollbar-width:none;scroll-behavior:smooth}
.wwms-thumbs::-webkit-scrollbar{display:none}
.wwms-thumbs .t{border:1px solid var(--border,rgba(148,163,184,.25));background:var(--panel,rgba(255,255,255,.05));color:var(--muted,#9aa3b8);cursor:pointer;transition:.18s;font:inherit;position:relative;flex:0 0 auto}
.wwms-thumbs .t:hover{border-color:var(--accent2,#b78cff);color:var(--text,#fff)}
.wwms-thumbs .t.on{border-color:var(--accent2,#b78cff);color:var(--text,#fff);background:linear-gradient(120deg,rgba(124,60,255,.22),rgba(84,190,255,.16))}
.wwms-thumbs .t .prog{position:absolute;left:0;bottom:0;height:3px;width:0;background:var(--accent2,#b78cff);border-radius:0 0 3px 3px}
.wwms-thumbs .t.on .prog.run{animation:wwmsBar var(--wwms-dur,5500ms) linear forwards}
.wwms-thumbs[data-style="dots"] .t{width:11px;height:11px;border-radius:50%;padding:0}
.wwms-thumbs[data-style="dots"] .t.on{transform:scale(1.25)}
.wwms-thumbs[data-style="dots"] .t .prog{display:none}
.wwms-thumbs[data-style="thumbs"] .t{width:92px;height:58px;border-radius:10px;overflow:hidden;padding:0}
.wwms-thumbs[data-style="thumbs"] .t img{width:100%;height:100%;object-fit:cover;display:block;opacity:.72;transition:.2s}
.wwms-thumbs[data-style="thumbs"] .t.on img,.wwms-thumbs[data-style="thumbs"] .t:hover img{opacity:1}
.wwms-thumbs .t i{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;font-size:15px;text-shadow:0 2px 8px rgba(0,0,0,.6)}
.wwms-thumbs[data-style="bars"] .t{flex:1 1 0;height:5px;border-radius:999px;padding:0;min-width:26px;overflow:hidden}
.wwms-thumbs[data-style="bars"] .t.on{background:linear-gradient(90deg,#7c3cff,#b78cff);border-color:transparent}
.wwms-thumbs[data-style="bars"] .t .prog{display:none}
.wwms-thumbs[data-style="numbers"] .t{padding:7px 12px;border-radius:9px;font-family:'JetBrains Mono',Consolas,monospace;font-size:12px;min-width:38px}
.wwms-thumbs[data-style="pill"] .t{padding:8px 15px;border-radius:999px;font-size:12.5px;max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.wwms-thumbs[data-style="film"]{gap:6px;background:linear-gradient(#111,#1c1c28);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:10px 12px}
.wwms-thumbs[data-style="film"] .t{width:74px;height:46px;border-radius:6px;overflow:hidden;padding:0}
.wwms-thumbs[data-style="film"] .t img{width:100%;height:100%;object-fit:cover;opacity:.75}
.wwms-thumbs[data-style="film"] .t.on img{opacity:1}
.wwms-thumbs[data-style="film"] .t::before,.wwms-thumbs[data-style="film"] .t::after{content:'';position:absolute;left:0;right:0;height:4px;background-image:radial-gradient(circle,rgba(255,255,255,.55) 1px,transparent 1.4px);background-size:8px 4px;opacity:.5}
.wwms-thumbs[data-style="film"] .t::before{top:0}
.wwms-thumbs[data-style="film"] .t::after{bottom:0}
.wwms.single .wwms-btn,.wwms.single .wwms-thumbs,.wwms.single .wwms-count,.wwms.single .wwms-pause{display:none}
.wwms-lightbox{position:fixed;inset:0;z-index:1200;background:rgba(4,4,12,.95);display:none;align-items:center;justify-content:center;padding:22px;overscroll-behavior:contain}
.wwms-lightbox.open{display:flex}
.wwms-lightbox .lb-body{position:relative;max-width:min(1400px,96vw);max-height:92vh;width:100%;display:flex;align-items:center;justify-content:center}
.wwms-lightbox .lb-media{position:relative;overflow:hidden;border-radius:12px;touch-action:none;display:flex;align-items:center;justify-content:center}
.wwms-lightbox img,.wwms-lightbox video{max-width:100%;max-height:92vh;border-radius:12px;display:block;background:#000}
.wwms-lightbox img.zoomable{cursor:zoom-in;transition:transform .18s ease-out;transform:scale(var(--z,1)) translate(var(--px,0px),var(--py,0px))}
.wwms-lightbox img.zoomed{cursor:grab}
.wwms-lightbox img.dragging{cursor:grabbing;transition:none}
.wwms-lightbox iframe{width:min(1400px,96vw);height:min(800px,86vh);border:0;border-radius:12px}
.wwms-lightbox .lb-x{position:absolute;top:-46px;right:0;background:none;border:none;color:#fff;font-size:26px;cursor:pointer;opacity:.8}
.wwms-lightbox .lb-x:hover{opacity:1}
.wwms-lightbox .lb-nav{position:absolute;top:50%;transform:translateY(-50%);width:46px;height:46px;border-radius:50%;border:1px solid rgba(255,255,255,.25);background:rgba(12,12,24,.6);color:#fff;font-size:19px;cursor:pointer;z-index:3}
.wwms-lightbox .lb-prev{left:-58px}
.wwms-lightbox .lb-next{right:-58px}
.wwms-lightbox .lb-tools{position:absolute;top:-46px;left:0;display:flex;gap:6px}
.wwms-lightbox .lb-tools button{width:34px;height:34px;border-radius:9px;border:1px solid rgba(255,255,255,.22);background:rgba(12,12,24,.6);color:#fff;cursor:pointer;font-size:14px}
.wwms-lightbox .lb-tools button:hover{background:rgba(12,12,24,.9)}
.wwms-lightbox .lb-cap{position:absolute;bottom:-44px;left:0;right:0;text-align:center;color:#fff;font-size:13px;opacity:.9;line-height:1.5}
.wwms-lightbox .lb-cap b{font-family:'JetBrains Mono',Consolas,monospace;font-size:11px;opacity:.7;display:block}
.wwms-live{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
.wwms-toast{position:absolute;left:50%;bottom:14px;transform:translateX(-50%);background:rgba(12,12,24,.9);color:#fff;border:1px solid rgba(255,255,255,.2);border-radius:999px;padding:8px 16px;font-size:12px;z-index:6;opacity:0;transition:opacity .25s;pointer-events:none}
.wwms-toast.show{opacity:1}
@keyframes wwmsKB{from{transform:scale(1)}to{transform:scale(1.08)}}
@keyframes wwmsShimmer{from{background-position:120% 0}to{background-position:-120% 0}}
@keyframes wwmsBar{from{width:0}to{width:100%}}
@media(max-width:900px){
.wwms-block{width:100%!important}
.wwms-block.al-left,.wwms-block.al-center,.wwms-block.al-right{margin-left:auto;margin-right:auto}
.wwms-lightbox .lb-prev{left:6px}
.wwms-lightbox .lb-next{right:6px}
.wwms-lightbox .lb-x{top:-40px;right:6px}
.wwms-lightbox .lb-tools{top:-40px;left:6px}
}
@media(prefers-reduced-motion:reduce){
.wwms-track,.wwms-track[data-trans="fade"] .wwms-slide,.wwms-track[data-trans="zoom"] .wwms-slide{transition:none}
.wwms-slide.kb.on img{animation:none}
.wwms-thumbs .t.on .prog.run{animation:none}
.wwms-slide.loading::after{animation:none}
}
@media print{
.wwms-btn,.wwms-thumbs,.wwms-topbar,.wwms-progress{display:none!important}
}
</style>
HTML;
    }

    private function js(): string
    {
        return <<<'HTML'
<script>if(!window.WWMS){window.WWMS=1;
window.WWMSinit=function(root){
    if(!root||root.__wwms)return;root.__wwms=1;
    var track=root.querySelector('.wwms-track');
    var slides=Array.prototype.slice.call(root.querySelectorAll('.wwms-slide'));
    var thumbs=root.querySelector('.wwms-thumbs');
    var counter=root.querySelector('.wwms-count');
    var prog=root.querySelector('.wwms-progress i');
    var pauseBtn=root.querySelector('.wwms-pause');
    var toast=root.querySelector('.wwms-toast');
    var ms=parseInt(root.getAttribute('data-autoplay'))||0;
    var loop=root.getAttribute('data-loop')==='1';
    var lightboxOn=root.getAttribute('data-lightbox')==='1';
    var videoAuto=root.getAttribute('data-videoauto')==='1';
    var autohide=root.getAttribute('data-autohide')==='1';
    var i=0,timer=null,paused=false,hover=false,visible=true,progressT=null,idleT=null;
    var calm=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var conn=navigator.connection||{};
    if(conn.saveData||/2g/.test(conn.effectiveType||''))ms=0;
    if(slides.length<2){root.classList.add('single');ms=0}
    if(ms>0&&navigator.getBattery){navigator.getBattery().then(function(b){if(b&&b.level<0.2&&!b.charging)ms=0}).catch(function(){})}
    function pad(n){return (n<10?'0':'')+n}
    function toastMsg(t){if(!toast)return;toast.textContent=t;toast.classList.add('show');setTimeout(function(){toast.classList.remove('show')},1800)}
    function announce(){var live=root.querySelector('.wwms-live');if(live)live.textContent='Slide '+(i+1)+' de '+slides.length}
    function renderThumbs(){
        if(!thumbs)return;
        var style=thumbs.getAttribute('data-style');
        var max=parseInt(thumbs.getAttribute('data-max'))||8;
        thumbs.innerHTML='';
        slides.forEach(function(s,idx){
            var b=document.createElement('button');
            b.type='button';b.className='t'+(idx===i?' on':'');
            b.setAttribute('role','tab');b.setAttribute('aria-selected',idx===i?'true':'false');
            b.setAttribute('aria-label','Ir al slide '+(idx+1));
            if(style==='thumbs'||style==='film'){
                var img=document.createElement('img');
                img.loading='lazy';img.alt='';img.src=s.getAttribute('data-thumb')||'';
                b.appendChild(img);
                if(s.getAttribute('data-video')==='1'){var ic=document.createElement('i');ic.textContent='▶';b.appendChild(ic)}
            }else if(style==='numbers'){b.textContent=pad(idx+1)}
            else if(style==='pill'){b.textContent=(s.getAttribute('data-title')||('Slide '+(idx+1))).slice(0,26)}
            else if(style==='bars'){b.innerHTML='&nbsp;'}
            var p=document.createElement('span');p.className='prog';b.appendChild(p);
            b.addEventListener('click',function(){go(idx,true)});
            if(style!=='film'&&style!=='thumbs'&&idx>=max){b.style.display='none'}
            thumbs.appendChild(b);
        });
    }
    function setThumbState(){
        if(!thumbs)return;
        Array.prototype.forEach.call(thumbs.children,function(c,idx){
            c.classList.toggle('on',idx===i);
            c.setAttribute('aria-selected',idx===i?'true':'false');
            var p=c.querySelector('.prog');
            if(p){p.classList.remove('run');void p.offsetWidth;if(idx===i&&ms>0&&!paused)p.classList.add('run')}
        });
        var active=thumbs.children[i];
        if(active&&active.scrollIntoView){try{active.scrollIntoView({block:'nearest',inline:'nearest',behavior:calm?'auto':'smooth'})}catch(e){}}
    }
    function preloadNext(){
        var n=(i+1)%slides.length;
        var s=slides[n];
        if(!s||s.getAttribute('data-type')!=='image')return;
        var u=s.getAttribute('data-src');
        if(u){var im=new Image();im.src=u}
    }
    function mountVideo(s){
        if(s.getAttribute('data-mounted')==='1')return;
        var t=s.getAttribute('data-type'),src=s.getAttribute('data-src');
        if(t==='youtube'||t==='vimeo'){
            var f=document.createElement('iframe');
            f.loading='lazy';
            f.src=(t==='youtube'?'https://www.youtube-nocookie.com/embed/':'https://player.vimeo.com/video/')+src+'?autoplay=1&mute=1&rel=0&playsinline=1'+(s.getAttribute('data-start')?'&start='+s.getAttribute('data-start'):'');
            f.setAttribute('allow','autoplay; encrypted-media; picture-in-picture');
            f.setAttribute('allowfullscreen','');
            f.setAttribute('title',s.getAttribute('data-title')||'Video');
            var ph=s.querySelector('.wwms-play');if(ph)ph.remove();
            s.appendChild(f);
        }else if(t==='mp4'){
            var v=document.createElement('video');
            v.src=src;v.loop=true;v.playsInline=true;v.setAttribute('playsinline','');v.preload='metadata';
            v.muted=root.getAttribute('data-muted')!=='0';
            v.controls=root.getAttribute('data-vcontrols')!=='0';
            if(s.getAttribute('data-poster'))v.poster=s.getAttribute('data-poster');
            v.setAttribute('aria-label',s.getAttribute('data-title')||'Video');
            v.addEventListener('error',function(){s.classList.add('error')});
            s.appendChild(v);
            if(v.muted){
                var mb=document.createElement('button');
                mb.type='button';mb.className='wwms-mini';mb.textContent='🔇';
                mb.style.cssText='position:absolute;right:12px;bottom:12px;z-index:4';
                mb.setAttribute('aria-label','Activar sonido');
                mb.addEventListener('click',function(){v.muted=!v.muted;mb.textContent=v.muted?'🔇':'🔊';mb.setAttribute('aria-label',v.muted?'Activar sonido':'Silenciar')});
                s.appendChild(mb);
            }
        }
        s.setAttribute('data-mounted','1');
    }
    function unmountVideo(s){
        var v=s.querySelector('video');if(v)v.pause();
        var f=s.querySelector('iframe');
        if(f){f.remove();s.setAttribute('data-mounted','0');
            if(!s.querySelector('.wwms-play')){
                var ph=document.createElement('div');ph.className='wwms-play';ph.innerHTML='<span>▶</span>';
                ph.addEventListener('click',function(){mountVideo(s)});
                s.appendChild(ph);
            }
        }
    }
    function go(n,user){
        if(slides.length<2&&!user)return;
        if(n<0)n=loop?slides.length-1:0;
        if(n>=slides.length)n=loop?0:slides.length-1;
        if(n===i&&user)return;
        var prev=slides[i];
        i=n;
        if(track.getAttribute('data-trans')==='slide'){track.style.transform='translateX(-'+(i*100)+'%)'}
        slides.forEach(function(s,idx){
            var on=idx===i;
            s.classList.toggle('on',on);
            s.setAttribute('aria-hidden',on?'false':'true');
        });
        if(prev)unmountVideo(prev);
        var cur=slides[i];
        if(cur){
            if(videoAuto&&cur.getAttribute('data-video')==='1')mountVideo(cur);
            var v=cur.querySelector('video');if(v&&videoAuto){try{v.play()}catch(e){}}
        }
        if(counter)counter.textContent=(i+1)+' / '+slides.length;
        setThumbState();announce();preloadNext();restart();
        if(user&&lightboxOn&&root.__lbOpen)WWMSlightbox(root,i);
        if(user){try{if(history.replaceState)history.replaceState(null,'','#slide-'+(i+1))}catch(e){}}
        try{root.dispatchEvent(new CustomEvent('wwms:change',{detail:{index:i,count:slides.length}}))}catch(e){}
    }
    function restart(){
        if(prog){prog.style.transition='none';prog.style.width='0';setTimeout(function(){prog.style.transition=''},30)}
        if(timer)clearInterval(timer);
        if(progressT)clearInterval(progressT);
        setThumbState();
        if(!ms||paused||hover||calm||!visible)return;
        var started=Date.now();
        timer=setInterval(function(){go(i+1)},ms);
        progressT=setInterval(function(){
            if(!prog)return;
            var p=Math.min(1,(Date.now()-started)/ms);
            prog.style.width=(p*100)+'%';
            if(p>=1)clearInterval(progressT);
        },120);
    }
    function setPaused(p){
        paused=p;
        if(pauseBtn){pauseBtn.textContent=paused?'▶':'❚❚';pauseBtn.setAttribute('aria-label',paused?'Reanudar':'Pausar')}
        restart();
        try{root.dispatchEvent(new CustomEvent('wwms:pause',{detail:{paused:paused}}))}catch(e){}
    }
    function idleWake(){
        root.classList.remove('hide-ui');
        if(idleT)clearTimeout(idleT);
        if(autohide)idleT=setTimeout(function(){if(!hover)root.classList.add('hide-ui')},2600);
    }
    root.querySelectorAll('.wwms-prev').forEach(function(b){b.addEventListener('click',function(){go(i-1,true)})});
    root.querySelectorAll('.wwms-next').forEach(function(b){b.addEventListener('click',function(){go(i+1,true)})});
    if(pauseBtn)pauseBtn.addEventListener('click',function(){setPaused(!paused)});
    root.addEventListener('mouseenter',function(){hover=true;idleWake()});
    root.addEventListener('mousemove',idleWake);
    root.addEventListener('mouseleave',function(){hover=false;root.classList.remove('hide-ui');restart()});
    root.addEventListener('focusin',function(){hover=true;root.classList.remove('hide-ui')});
    root.addEventListener('focusout',function(){hover=false;restart()});
    document.addEventListener('visibilitychange',function(){if(document.hidden){if(timer)clearInterval(timer)}else restart()});
    root.addEventListener('keydown',function(e){
        if(e.key==='ArrowLeft'){e.preventDefault();go(i-1,true)}
        else if(e.key==='ArrowRight'){e.preventDefault();go(i+1,true)}
        else if(e.key==='Home'){e.preventDefault();go(0,true)}
        else if(e.key==='End'){e.preventDefault();go(slides.length-1,true)}
        else if(e.key===' '){e.preventDefault();setPaused(!paused)}
    });
    var startX=null,startY=null;
    track.addEventListener('touchstart',function(e){startX=e.touches[0].clientX;startY=e.touches[0].clientY},{passive:true});
    track.addEventListener('touchend',function(e){
        if(startX===null)return;
        var dx=e.changedTouches[0].clientX-startX;
        var dy=e.changedTouches[0].clientY-startY;
        if(Math.abs(dx)>40&&Math.abs(dx)>Math.abs(dy))go(dx<0?i+1:i-1,true);
        startX=null;startY=null;
    },{passive:true});
    track.addEventListener('pointerdown',function(e){if(e.pointerType==='mouse')startX=e.clientX});
    track.addEventListener('pointerup',function(e){if(e.pointerType==='mouse'&&startX!==null){var dx=e.clientX-startX;if(Math.abs(dx)>60)go(dx<0?i+1:i-1,true);startX=null}});
    if('IntersectionObserver' in window){
        var io=new IntersectionObserver(function(entries){
            entries.forEach(function(en){
                visible=en.isIntersecting;
                if(visible){restart()}else if(timer){clearInterval(timer)}
            });
        },{threshold:0.25});
        io.observe(root);
    }
    slides.forEach(function(s){
        var ph=s.querySelector('.wwms-play');
        if(ph){
            ph.addEventListener('click',function(){mountVideo(s)});
            ph.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();mountVideo(s)}});
        }
        var img=s.querySelector('img.wwms-open');
        if(img){
            s.classList.add('loading');
            var done=function(){s.classList.remove('loading')};
            if(img.complete)done();else{img.addEventListener('load',done);img.addEventListener('error',function(){s.classList.remove('loading');s.classList.add('error')})}
            img.addEventListener('click',function(){if(lightboxOn)WWMSlightbox(root,i)});
        }
        var src=s.getAttribute('data-src');
        if(src&&(s.getAttribute('data-type')==='mp4')&&s.querySelector('video')){
            var v=s.querySelector('video');
            v.addEventListener('loadeddata',function(){s.classList.remove('loading')});
        }
    });
    var fullBtn=root.querySelector('.wwms-full');
    if(fullBtn){
        fullBtn.addEventListener('click',function(){
            var st=root.querySelector('.wwms-stage');
            if(document.fullscreenElement){document.exitFullscreen()}
            else if(st&&st.requestFullscreen){st.requestFullscreen().catch(function(){})}
        });
    }
    var copyBtn=root.querySelector('.wwms-copy');
    if(copyBtn){
        copyBtn.addEventListener('click',function(){
            var url=location.origin+location.pathname+'#slide-'+(i+1);
            var done=function(){toastMsg('Enlace copiado')};
            if(navigator.share&&window.matchMedia('(pointer:coarse)').matches){
                navigator.share({title:document.title,url:url}).catch(function(){});
                return;
            }
            if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(url).then(done).catch(function(){toastMsg(url)})}
            else{toastMsg(url)}
        });
    }
    var hash=parseInt((location.hash.match(/slide-(\d+)/)||[])[1]);
    if(hash>0&&hash<=slides.length)i=hash-1;
    if(track.getAttribute('data-trans')!=='slide'){slides.forEach(function(s,idx){s.classList.toggle('on',idx===i)})}
    track.style.transition='none';
    go(i,false);
    setTimeout(function(){track.style.transition=''},40);
    renderThumbs();
    idleWake();
};
window.WWMSlightbox=function(root,idx){
    var slides=Array.prototype.slice.call(root.querySelectorAll('.wwms-slide'));
    var lb=document.getElementById('wwms-lb');
    if(!lb){
        lb=document.createElement('div');lb.id='wwms-lb';lb.className='wwms-lightbox';
        lb.setAttribute('role','dialog');lb.setAttribute('aria-modal','true');
        lb.innerHTML='<div class="lb-body"><div class="lb-tools"><button class="lb-zout" aria-label="Alejar">−</button><button class="lb-zin" aria-label="Acercar">+</button><button class="lb-reset" aria-label="Restablecer zoom">⤢</button></div><button class="lb-x" aria-label="Cerrar">✕</button><button class="lb-nav lb-prev" aria-label="Anterior">‹</button><button class="lb-nav lb-next" aria-label="Siguiente">›</button><div class="lb-media"></div><div class="lb-cap"></div></div>';
        document.body.appendChild(lb);
        lb.addEventListener('click',function(e){if(e.target===lb)close()});
        lb.querySelector('.lb-x').addEventListener('click',close);
        lb.querySelector('.lb-prev').addEventListener('click',function(){nav(-1)});
        lb.querySelector('.lb-next').addEventListener('click',function(){nav(1)});
        lb.querySelector('.lb-zin').addEventListener('click',function(){zoom(0.35)});
        lb.querySelector('.lb-zout').addEventListener('click',function(){zoom(-0.35)});
        lb.querySelector('.lb-reset').addEventListener('click',function(){resetZoom()});
        document.addEventListener('keydown',function(e){
            if(!lb.classList.contains('open'))return;
            if(e.key==='Escape')close();
            else if(e.key==='ArrowLeft')nav(-1);
            else if(e.key==='ArrowRight')nav(1);
            else if(e.key==='+'||e.key==='=')zoom(0.35);
            else if(e.key==='-')zoom(-0.35);
            else if(e.key==='Tab'){
                var f=lb.querySelectorAll('button');if(!f.length)return;
                var first=f[0],last=f[f.length-1];
                if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus()}
                else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus()}
            }
        });
        var media=lb.querySelector('.lb-media');
        var z=1,px=0,py=0,drag=false,sx=0,sy=0;
        function applyZ(){var im=media.querySelector('img.zoomable');if(!im)return;im.style.setProperty('--z',z);im.style.setProperty('--px',px+'px');im.style.setProperty('--py',py+'px');im.classList.toggle('zoomed',z>1)}
        function zoom(d){z=Math.min(4,Math.max(1,z+d));if(z===1){px=0;py=0}applyZ()}
        function resetZoom(){z=1;px=0;py=0;applyZ()}
        media.addEventListener('wheel',function(e){if(!media.querySelector('img.zoomable'))return;e.preventDefault();zoom(e.deltaY<0?0.2:-0.2)},{passive:false});
        media.addEventListener('dblclick',function(){z>1?resetZoom():zoom(1)});
        media.addEventListener('pointerdown',function(e){
            var im=media.querySelector('img.zoomable');if(!im||z<=1)return;
            drag=true;sx=e.clientX-px;sy=e.clientY-py;im.classList.add('dragging');media.setPointerCapture(e.pointerId);
        });
        media.addEventListener('pointermove',function(e){if(!drag)return;px=e.clientX-sx;py=e.clientY-sy;applyZ()});
        media.addEventListener('pointerup',function(e){drag=false;var im=media.querySelector('img.zoomable');if(im)im.classList.remove('dragging')});
        var tY=null;
        lb.addEventListener('touchstart',function(e){if(e.touches.length===1)tY=e.touches[0].clientY},{passive:true});
        lb.addEventListener('touchend',function(e){
            if(tY===null)return;
            var dy=e.changedTouches[0].clientY-tY;
            if(dy>110)close();
            tY=null;
        },{passive:true});
        window.__wwmsLbZoom=function(d){zoom(d)};
    }
    root.__lbOpen=true;root.__lbIdx=idx;
    function close(){
        lb.classList.remove('open');root.__lbOpen=false;
        var v=lb.querySelector('video');if(v)v.pause();
        lb.querySelector('.lb-media').innerHTML='';
        document.body.style.overflow='';
        if(root.__lastFocus&&root.__lastFocus.focus)try{root.__lastFocus.focus()}catch(e){}
    }
    function nav(d){
        var n=(root.__lbIdx+d+slides.length)%slides.length;
        root.__lbIdx=n;go(n,false);paint();
    }
    function paint(){
        var s=slides[root.__lbIdx];
        var media=lb.querySelector('.lb-media');
        var t=s.getAttribute('data-type'),src=s.getAttribute('data-src');
        media.innerHTML='';
        if(t==='youtube'||t==='vimeo'){
            var f=document.createElement('iframe');
            f.src=(t==='youtube'?'https://www.youtube-nocookie.com/embed/':'https://player.vimeo.com/video/')+src+'?autoplay=1&rel=0&playsinline=1';
            f.setAttribute('allow','autoplay; encrypted-media; picture-in-picture');f.setAttribute('allowfullscreen','');
            media.appendChild(f);
        }else if(t==='mp4'){
            var v=document.createElement('video');v.src=src;v.controls=true;v.autoplay=true;v.playsInline=true;
            if(s.getAttribute('data-poster'))v.poster=s.getAttribute('data-poster');
            media.appendChild(v);
        }else{
            var im=document.createElement('img');im.src=src;im.alt=s.getAttribute('data-alt')||'';im.className='zoomable';
            media.appendChild(im);
            if(window.__wwmsLbZoom)window.__wwmsLbZoom(0);
        }
        var cap=lb.querySelector('.lb-cap');
        var title=s.getAttribute('data-title')||'';
        cap.innerHTML=(title?'<b>'+title+'</b>':'')+'Slide '+(root.__lbIdx+1)+' de '+slides.length+' · usa ← → · +/− · Esc';
    }
    root.__lastFocus=document.activeElement;
    paint();
    lb.classList.add('open');
    document.body.style.overflow='hidden';
    var x=lb.querySelector('.lb-x');if(x)x.focus();
};
document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('.wwms').forEach(WWMSinit);
});
}</script>
HTML;
    }

    private function parseEmbed(string $url): array
    {
        $u = trim($url);
        if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,})#i', $u, $m)) {
            $start = '';
            if (preg_match('#[?&](?:t|start)=(\d+)#', $u, $t)) $start = $t[1];
            return ['type' => 'youtube', 'src' => $m[1], 'start' => $start];
        }
        if (preg_match('#vimeo\.com/(?:video/)?(\d{6,})#i', $u, $m)) {
            return ['type' => 'vimeo', 'src' => $m[1], 'start' => ''];
        }
        return ['type' => '', 'src' => $u, 'start' => ''];
    }

    private function thumbFor(array $s): string
    {
        if (!empty($s['poster'])) return (string)$s['poster'];
        if (($s['type'] ?? '') === 'image') return (string)($s['url'] ?? '');
        $embed = $this->parseEmbed((string)($s['url'] ?? ''));
        if ($embed['type'] === 'youtube') return 'https://i.ytimg.com/vi/' . $embed['src'] . '/hqdefault.jpg';
        if ($embed['type'] === 'vimeo') return 'https://vumbnail.com/' . $embed['src'] . '.jpg';
        return '';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $slides = $this->safeJson($c['slides'] ?? []);
        $html = $this->assets();
        if (!$slides) {
            return $html . '<section class="wwms" style="padding:40px 0!important"><div class="wwms-inner"><div class="wwms-block al-center" style="--wwms-w:100%"><div class="wwms-stage" data-ratio="16:9" style="display:flex;align-items:center;justify-content:center;color:var(--muted)">Sin slides configurados</div></div></div></section>';
        }
        $width = in_array((string)$c['width'], ['100', '66', '33'], true) ? (string)$c['width'] : '100';
        $align = in_array((string)$c['align'], ['left', 'center', 'right'], true) ? (string)$c['align'] : 'center';
        $ratio = in_array((string)$c['aspect'], ['16:9', '4:3', '1:1', '21:9', 'auto'], true) ? (string)$c['aspect'] : '16:9';
        $transition = in_array((string)$c['transition'], ['slide', 'fade', 'zoom'], true) ? (string)$c['transition'] : 'slide';
        $thumbStyle = in_array((string)$c['thumb_style'], ['dots', 'thumbs', 'bars', 'numbers', 'pill', 'film'], true) ? (string)$c['thumb_style'] : 'thumbs';
        $captionPos = in_array((string)$c['caption_pos'], ['bottom', 'top', 'center'], true) ? (string)$c['caption_pos'] : 'bottom';
        $maxHeight = max(0, (int)$c['max_height']);
        $pad = max(0, (int)$c['pad']);
        $radius = max(0, (int)$c['radius']);
        $autoplay = max(0, (int)$c['autoplay_ms']);
        $loop = (string)$c['loop'] === '0' ? '0' : '1';
        $overlay = (string)$c['overlay'] === '0' ? '0' : '1';
        $lightbox = (string)$c['lightbox'] === '0' ? '0' : '1';
        $fullscreen = (string)$c['fullscreen'] === '0' ? '0' : '1';
        $copyLink = (string)$c['copy_link'] === '0' ? '0' : '1';
        $videoAuto = (string)$c['video_autoplay'] === '0' ? '0' : '1';
        $autohide = (string)$c['controls_autohide'] === '0' ? '0' : '1';
        $kenburns = (string)$c['kenburns'] === '1' ? '1' : '0';
        $thumbMax = max(3, min(20, (int)$c['thumb_count']));
        $total = 0;
        foreach ($slides as $s) if (is_array($s) && trim((string)($s['url'] ?? '')) !== '') $total++;

        $html .= '<section class="wwms" data-autoplay="' . $autoplay . '" data-loop="' . $loop . '" data-lightbox="' . $lightbox . '"'
            . ' data-videoauto="' . $videoAuto . '" data-autohide="' . $autohide . '" data-muted="1" data-vcontrols="1"'
            . ' style="padding:' . $pad . 'px 0!important" aria-label="' . $this->esc((string)$c['label']) . '">';
        $html .= '<div class="wwms-inner"><div class="wwms-block al-' . $align . '" style="--wwms-w:' . $width . '%;--wwms-r:' . $radius . 'px;--wwms-dur:' . ($autoplay ?: 5500) . 'ms">';
        $html .= '<div class="wwms-stage" data-ratio="' . $ratio . '"' . ($maxHeight > 0 ? ' style="max-height:' . $maxHeight . 'px"' : '') . '>';
        $html .= '<div class="wwms-progress" aria-hidden="true"><i></i></div>';
        $html .= '<div class="wwms-track" data-trans="' . $transition . '" role="group" aria-roledescription="carrusel">';
        $html .= '<span class="wwms-live" role="status" aria-live="polite"></span>';

        $first = true;
        $idx = 0;
        foreach ($slides as $s) {
            if (!is_array($s)) continue;
            $rawType = (string)($s['type'] ?? 'image');
            $url = trim((string)($s['url'] ?? ''));
            if ($url === '') continue;
            $embed = $this->parseEmbed($url);
            $type = in_array($rawType, ['image', 'youtube', 'vimeo', 'mp4'], true) ? $rawType : 'image';
            if ($embed['type'] !== '' && $type !== 'image') $type = $embed['type'];
            $src = ($embed['type'] !== '' && $type !== 'image') ? $embed['src'] : $url;
            $poster = (string)($s['poster'] ?? '');
            $title = (string)($s['title'] ?? '');
            $text = (string)($s['text'] ?? '');
            $ctaLabel = (string)($s['cta_label'] ?? '');
            $ctaUrl = (string)($s['cta_url'] ?? '');
            $alt = (string)($s['alt'] ?? $title);
            $isVideo = in_array($type, ['youtube', 'vimeo', 'mp4'], true);

            $html .= '<div class="wwms-slide' . ($kenburns === '1' && $type === 'image' ? ' kb' : '') . '" data-type="' . $type . '" data-src="' . $this->esc($src) . '"'
                . ' data-poster="' . $this->esc($poster) . '" data-start="' . $this->esc((string)($embed['start'] ?? '')) . '"'
                . ' data-title="' . $this->esc($title) . '" data-alt="' . $this->esc($alt) . '" data-video="' . ($isVideo ? '1' : '0') . '"'
                . ' data-thumb="' . $this->esc($this->thumbFor(['type' => $type, 'url' => $url, 'poster' => $poster])) . '"'
                . ' aria-roledescription="slide" aria-label="' . ($idx + 1) . ' de ' . $total . '"' . ($first ? ' aria-hidden="false"' : ' aria-hidden="true"') . '>';

            if ($type === 'image') {
                $html .= '<img class="wwms-open" src="' . $this->esc($src) . '" alt="' . $this->esc($alt) . '"' . ($first ? ' fetchpriority="high"' : ' loading="lazy"') . ' decoding="async"/>';
            } elseif ($type === 'mp4') {
                $html .= '<video muted loop playsinline preload="none"' . ($poster !== '' ? ' poster="' . $this->esc($poster) . '"' : '') . ' aria-label="' . $this->esc($title !== '' ? $title : 'Video') . '"><source src="' . $this->esc($src) . '" type="video/mp4"/></video>';
                $html .= '<div class="wwms-play" role="button" tabindex="0" aria-label="Reproducir video"><span>▶</span></div>';
            } else {
                if ($poster !== '') {
                    $html .= '<img src="' . $this->esc($poster) . '" alt="' . $this->esc($alt) . '" loading="lazy" decoding="async"/>';
                }
                $html .= '<div class="wwms-play" role="button" tabindex="0" aria-label="Reproducir video"><span>▶</span></div>';
            }

            if ($overlay === '1' && ($title !== '' || $text !== '' || $ctaLabel !== '')) {
                $html .= '<div class="wwms-overlay" data-pos="' . $captionPos . '"><div class="wwms-inner" style="padding:0">';
                if ($title !== '') $html .= '<h3 data-editable="slides.' . $idx . '.title">' . $this->esc($title) . '</h3>';
                if ($text !== '') $html .= '<p data-editable="slides.' . $idx . '.text">' . $this->esc($text) . '</p>';
                if ($ctaLabel !== '' && $ctaUrl !== '') $html .= '<a class="wwms-cta" href="' . $this->esc($ctaUrl) . '">' . $this->esc($ctaLabel) . '</a>';
                $html .= '</div></div>';
            }
            $html .= '</div>';
            $first = false;
            $idx++;
        }

        $html .= '</div>';
        $html .= '<div class="wwms-topbar"><span class="wwms-count">1 / ' . $total . '</span>';
        if ($copyLink) $html .= '<button type="button" class="wwms-mini wwms-copy" aria-label="Copiar enlace del slide">🔗</button>';
        if ($fullscreen) $html .= '<button type="button" class="wwms-mini wwms-full" aria-label="Pantalla completa">⛶</button>';
        if ($autoplay > 0) $html .= '<button type="button" class="wwms-pause" aria-label="Pausar">❚❚</button>';
        $html .= '</div>';
        $html .= '<button type="button" class="wwms-btn wwms-prev" aria-label="Anterior">‹</button>';
        $html .= '<button type="button" class="wwms-btn wwms-next" aria-label="Siguiente">›</button>';
        $html .= '<div class="wwms-toast" aria-hidden="true"></div>';
        $html .= '</div>';
        $html .= '<div class="wwms-thumbs" data-style="' . $thumbStyle . '" data-max="' . $thumbMax . '" role="tablist" aria-label="Miniaturas"></div>';
        $html .= '</div></div></section>';
        $html .= $this->js();
        return $html;
    }
}
