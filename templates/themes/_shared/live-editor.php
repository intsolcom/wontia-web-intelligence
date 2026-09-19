<?php
/* WWI Live Editor - partial compartido por todos los temas.
   Requiere: $page en scope y que el tema emita .wwi-section[data-sid] (contrato).
   Fuente unica de verdad: no duplicar por tema. */
?>
<script>window.__WWI_EDIT_CTX__=<?= json_encode(['pageId' => (int)($page['id'] ?? 0), 'pageTitle' => (string)($page['title'] ?? ''), 'pageSlug' => (string)($page['slug'] ?? '')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<style>
.wwi-ed-bar{position:fixed;bottom:18px;right:18px;z-index:99998;background:var(--panel);border:1px solid var(--border2);border-radius:999px;padding:8px 14px;display:flex;gap:12px;align-items:center;font-size:12px;box-shadow:0 10px 30px rgba(0,0,0,.4)}
.wwi-ed-bar .u{color:var(--muted);font-size:11px}
.wwi-ed-toggle{display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:600}
.wwi-ed-side{position:fixed;top:0;right:0;bottom:0;width:380px;max-width:92vw;background:var(--panel);border-left:1px solid var(--border2);z-index:100000;display:flex;flex-direction:column;transform:translateX(105%);transition:transform .25s cubic-bezier(.22,1,.36,1);box-shadow:-20px 0 60px rgba(0,0,0,.45)}
.wwi-ed-open .wwi-ed-side{transform:none}
.wwi-ed-resize{position:absolute;left:-3px;top:0;bottom:0;width:6px;cursor:col-resize}
.wwi-ed-resize:hover{background:rgba(34,211,238,.4)}
.wwi-ed-head{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.wwi-ed-crumb{flex:1;min-width:0}
.wwi-ed-crumb b{display:block;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.wwi-ed-crumb span{font-size:10px;color:var(--muted)}
.wwi-ed-x{background:none;border:none;color:var(--muted);font-size:18px;cursor:pointer}
.wwi-ed-tabs{display:flex;border-bottom:1px solid var(--border)}
.wwi-ed-tab{flex:1;padding:10px 6px;background:none;border:none;color:var(--muted);font-size:12px;font-weight:600;cursor:pointer;border-bottom:2px solid transparent}
.wwi-ed-tab.on{color:var(--text);border-bottom-color:var(--accent)}
.wwi-ed-body{flex:1;overflow-y:auto;padding:16px}
.wwi-ed-body label{display:block;font-size:11px;color:var(--muted);margin-bottom:10px}
.wwi-ed-body input,.wwi-ed-body textarea,.wwi-ed-body select{width:100%;margin-top:4px;background:var(--bg2);border:1px solid var(--border2);border-radius:8px;padding:8px 10px;color:var(--text);font-size:12px;font-family:inherit;outline:none}
.wwi-ed-body textarea{min-height:70px;resize:vertical}
.wwi-ed-check{display:flex;align-items:center;gap:8px}
.wwi-ed-check input{width:auto;margin:0}
.wwi-ed-actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:12px}
.wwi-ed-save{background:linear-gradient(120deg,#22d3ee,#8b5cf6);color:#041018;font-weight:700;border:none;border-radius:8px;padding:8px 14px;cursor:pointer;font-size:12px}
.wwi-ed-btn{background:var(--panel2);border:1px solid var(--border2);color:var(--text);border-radius:8px;padding:7px 12px;cursor:pointer;font-size:12px;font-family:inherit}
.wwi-ed-btn:hover{border-color:var(--accent);color:var(--accent)}
.wwi-ed-cat{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.wwi-ed-cat button{background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:12px 10px;color:var(--text);cursor:pointer;font-size:12px;text-align:left;transition:border-color .15s,transform .15s;font-family:inherit}
.wwi-ed-cat button:hover{border-color:var(--accent);transform:translateY(-2px)}
.wwi-ed-cat button i{display:block;font-style:normal;font-size:16px;margin-bottom:4px}
.wwi-ed-cat button small{display:block;color:var(--muted);font-size:10px;margin-top:2px}
.wwi-ed-tree-item{display:flex;align-items:center;gap:6px;padding:8px 10px;border:1px solid var(--border);border-radius:8px;margin-bottom:6px;cursor:pointer;font-size:12px;background:var(--bg2)}
.wwi-ed-tree-item:hover{border-color:var(--border2)}
.wwi-ed-tree-item.on{border-color:var(--accent);background:rgba(34,211,238,.08)}
.wwi-ed-tree-item .nm{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.wwi-ed-media{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px}
.wwi-ed-media button{padding:0;border:1px solid var(--border);border-radius:8px;overflow:hidden;cursor:pointer;background:var(--bg2);aspect-ratio:1}
.wwi-ed-media img{width:100%;height:100%;object-fit:cover;display:block}
.wwi-ed-hint{font-size:12px;color:var(--muted);line-height:1.7}
.wwi-ed-toast{position:fixed;bottom:80px;right:18px;z-index:100001;background:rgba(52,211,153,.16);border:1px solid rgba(52,211,153,.4);color:#34d399;padding:10px 16px;border-radius:10px;font-size:12px;backdrop-filter:blur(8px)}
.wwi-ed-toast.err{background:rgba(248,113,113,.16);border-color:rgba(248,113,113,.4);color:#f87171}
.wwi-section{position:relative}
.wwi-edit-on .wwi-section{outline:1px dashed transparent;transition:outline-color .2s}
.wwi-edit-on .wwi-section:hover{outline-color:rgba(34,211,238,.5)}
.wwi-edit-on .wwi-section.wwi-sel{outline:2px solid rgba(34,211,238,.8);outline-offset:-2px}
.wwi-edit-on [data-editable]{cursor:text}
.wwi-edit-on [data-editable]:hover{outline:1px dashed rgba(139,92,246,.8);outline-offset:2px;border-radius:4px}
.wwi-edit-on [data-editable].wwi-sel-el{outline:2px solid rgba(139,92,246,.9);outline-offset:2px;border-radius:4px}
.wwi-ed-tools{display:none;position:sticky;top:72px;z-index:99990;justify-content:flex-end;gap:4px;height:34px;margin:0 10px -34px 0}
.wwi-edit-on .wwi-section:hover>.wwi-ed-tools{display:flex}
.wwi-ed-tools button{width:30px;height:30px;border-radius:8px;border:1px solid var(--border2);background:rgba(6,8,15,.85);color:var(--text);cursor:pointer;font-size:13px;backdrop-filter:blur(8px)}
.wwi-ed-tools button:hover{border-color:var(--accent);color:var(--accent)}
.wwi-show-editables [data-editable]{outline:1px dashed rgba(139,92,246,.75);outline-offset:2px;border-radius:3px}
.wwi-show-editables [data-source]{outline:1px dashed rgba(52,211,153,.8);outline-offset:2px;border-radius:3px}
.wwi-show-editables .wwi-section{outline:1px dashed rgba(34,211,238,.35);outline-offset:-1px}
.wwi-ed-err{border-color:#f87171!important;box-shadow:0 0 0 2px rgba(248,113,113,.18)!important}
.wwi-ed-errmsg{font-size:10px;color:#f87171;margin-top:3px}
.wwi-ed-rt-tools{display:flex;gap:4px;margin-bottom:6px}
.wwi-ed-rt-body{min-height:70px;background:var(--bg2);border:1px solid var(--border2);border-radius:8px;padding:8px 10px;font-size:12px;color:var(--text);outline:none;line-height:1.6}
.wwi-ed-rt-body:focus{border-color:var(--accent)}
.wwi-ed-comment.on{border-color:var(--accent);box-shadow:0 0 0 2px rgba(34,211,238,.2)}
.wwi-ed-rep{margin-bottom:12px;border:1px solid var(--border);border-radius:10px;padding:10px}
.wwi-rep-item{background:var(--bg2);border:1px solid var(--border);border-radius:8px;padding:8px 10px;margin-bottom:8px}
.wwi-rep-head{display:flex;justify-content:space-between;align-items:center;font-size:10px;color:var(--muted);margin-bottom:6px}
.wwi-rep-item label{display:block;font-size:10px;color:var(--muted);margin-bottom:6px}
.wwi-rep-item input,.wwi-rep-item textarea{width:100%;margin-top:3px;background:var(--bg);border:1px solid var(--border2);border-radius:6px;padding:6px 8px;color:var(--text);font-size:12px;font-family:inherit;outline:none}
.wwi-rep-item textarea{min-height:54px;resize:vertical}
.wwi-ed-cursor{position:fixed;z-index:100003;pointer-events:none;transform:translate(-2px,-2px);transition:left .8s linear,top .8s linear}
.wwi-ed-cursor .dot{display:block;width:10px;height:10px;border-radius:50%;background:#22d3ee;box-shadow:0 0 0 3px rgba(34,211,238,.25)}
.wwi-ed-cursor .nm{position:absolute;left:14px;top:-2px;font-size:10px;background:rgba(6,8,15,.85);color:#67e8f9;border:1px solid rgba(34,211,238,.4);border-radius:6px;padding:1px 6px;white-space:nowrap}
@media(max-width:720px){.wwi-hide-mobile{display:none!important}}
@media(min-width:721px) and (max-width:1100px){.wwi-hide-tablet{display:none!important}}
.wwi-ed-comment{background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:10px 12px;margin-bottom:8px}
.wwi-ed-comment.done{opacity:.55}
.wwi-ed-comment .hd{display:flex;justify-content:space-between;font-size:10px;color:var(--muted);margin-bottom:4px}
.wwi-ed-comment .bd{font-size:12px;line-height:1.6;white-space:pre-wrap}
.wwi-ed-comment .ac{display:flex;gap:6px;margin-top:8px}
.wwi-ed-modal{position:fixed;inset:0;z-index:100002;background:rgba(4,6,10,.7);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:18px}
.wwi-ed-modal-box{width:100%;max-width:480px;max-height:80vh;overflow-y:auto;background:var(--panel);border:1px solid var(--border2);border-radius:14px;padding:16px}
.wwi-dropzone{height:10px;margin:8px 0;border-radius:6px;background:rgba(34,211,238,.10);border:1px dashed rgba(34,211,238,.45);transition:background .15s}
.wwi-dropzone.over{background:rgba(34,211,238,.4)}
.wwi-client .wwi-ed-tab[data-tab="add"]{display:none}
.wwi-client .wwi-ed-tools button[data-act="up"],.wwi-client .wwi-ed-tools button[data-act="down"],.wwi-client .wwi-ed-tools button[data-act="toggle"]{display:none}
@media(prefers-reduced-motion:reduce){.wwi-edit-on .wwi-section{transition:none}.wwi-ed-side{transition:none}.wwi-dropzone{transition:none}}
</style>
<script>
(function(){
var token=null;
   try{token=localStorage.getItem('wwi_token')||null}catch(e){}
   if(!token||window.__WWI_PREVIEW__)return;
   (function(){
       if(!document.getElementById('wb-css')){
           var l=document.createElement('link');l.id='wb-css';l.rel='stylesheet';l.href='/assets/css/builder.css';document.head.appendChild(l);
       }
       if(!window.__WWI_BUILDER_LOADING){
           window.__WWI_BUILDER_LOADING=1;
           var s=document.createElement('script');s.src='/assets/js/builder.js';s.defer=true;document.body.appendChild(s);
       }
   })();
    var CTX=window.__WWI_EDIT_CTX__||{};
    var S={open:false,tab:'content',sel:null,sec:null,timer:null,undo:[],saving:false,rep:{},repFields:{}};
    function api(url,opts){
        opts=opts||{};
        opts.headers=opts.headers||{};
        opts.headers['Authorization']='Bearer '+token;
        if(opts.body){opts.headers['Content-Type']='application/json';opts.body=JSON.stringify(opts.body)}
        return fetch(url,opts).then(function(r){return r.json()});
    }
    function el(id){return document.getElementById(id)}
    function q(sel){return document.querySelector(sel)}
    function esc(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}
    function toast(msg,err){
        var t=document.createElement('div');
        t.className='wwi-ed-toast'+(err?' err':'');
        t.textContent=msg;
        document.body.appendChild(t);
        setTimeout(function(){t.remove()},2600);
    }
    function setStatus(t){var s=el('wwi-ed-status');if(s)s.textContent=t||''}
    api('/api/v1/admin/auth/me').then(function(d){
        if(!d||!d.ok||!d.user)return;
        build(d.user);
    }).catch(function(){});
    function build(user){
        var bar=document.createElement('div');
        bar.className='wwi-ed-bar';
        bar.innerHTML='<span class="u">✎ '+esc(user.username||'')+'</span><label class="wwi-ed-toggle"><input type="checkbox" id="wwi-ed-on"/> Editar sitio</label><label class="wwi-ed-toggle"><input type="checkbox" id="wwi-ed-client"/> Modo cliente</label><label class="wwi-ed-toggle"><input type="checkbox" id="wwi-ed-show"/> Mostrar editables</label>';
        document.body.appendChild(bar);
        el('wwi-ed-show').addEventListener('change',function(){
            document.body.classList.toggle('wwi-show-editables',this.checked);
        });
        el('wwi-ed-client').checked=localStorage.getItem('wwi_ed_client')==='1';
        document.body.classList.toggle('wwi-client',el('wwi-ed-client').checked);
        el('wwi-ed-client').addEventListener('change',function(){
            try{localStorage.setItem('wwi_ed_client',this.checked?'1':'0')}catch(e){}
            document.body.classList.toggle('wwi-client',this.checked);
            renderBody();
        });
        var side=document.createElement('aside');
        side.className='wwi-ed-side';
        side.id='wwi-ed-side';
        side.innerHTML='<div class="wwi-ed-resize" id="wwi-ed-resize"></div>'
            +'<div class="wwi-ed-head"><div class="wwi-ed-crumb"><b id="wwi-ed-crumb">'+esc(CTX.pageTitle||'Página')+'</b><span id="wwi-ed-status"></span><span id="wwi-ed-peers"></span></div><button class="wwi-ed-x" id="wwi-ed-close" title="Cerrar">✕</button></div>'
            +'<div class="wwi-ed-tabs"><button class="wwi-ed-tab on" data-tab="content">Contenido</button><button class="wwi-ed-tab" data-tab="add">Añadir</button><button class="wwi-ed-tab" data-tab="page">Página</button><button class="wwi-ed-tab" data-tab="comments">Comentarios</button><button class="wwi-ed-tab" data-tab="quality">Calidad</button></div>'
            +'<div class="wwi-ed-body" id="wwi-ed-body"></div>';
        document.body.appendChild(side);
        document.querySelectorAll('.wwi-ed-tab').forEach(function(b){
            b.addEventListener('click',function(){S.tab=b.dataset.tab;renderTabs();renderBody()});
        });
        el('wwi-ed-close').onclick=function(){openSide(false);el('wwi-ed-on').checked=false;document.body.classList.remove('wwi-edit-on')};
        el('wwi-ed-on').addEventListener('change',function(){
            document.body.classList.toggle('wwi-edit-on',this.checked);
            openSide(this.checked);
            if(this.checked)decorate();
        });
        if(localStorage.getItem('wwi_ed_on')==='1'){
            document.body.classList.add('wwi-edit-on');
            el('wwi-ed-on').checked=true;
            openSide(true);
        }
        decorate();
        bindSelect();
        initResize();
        if(window.MutationObserver&&q('main')){
            var mo=new MutationObserver(function(){if(document.body.classList.contains('wwi-edit-on'))decorate()});
            mo.observe(q('main'),{childList:true,subtree:true});
        }
        document.addEventListener('keydown',function(e){
            if(e.key==='Escape'){deselect()}
            else if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='z'&&S.sel){e.preventDefault();undo()}
        });
        renderBody();
        startPresence();
    }
    function renderTabs(){
        document.querySelectorAll('.wwi-ed-tab').forEach(function(b){b.classList.toggle('on',b.dataset.tab===S.tab)});
    }
    function openSide(open){
        S.open=open;
        document.body.classList.toggle('wwi-ed-open',open);
        try{localStorage.setItem('wwi_ed_on',open?'1':'0')}catch(e){}
    }
    function initResize(){
        var h=el('wwi-ed-resize');if(!h)return;
        var w=parseInt(localStorage.getItem('wwi_ed_w')||'380',10);
        if(w>=300&&w<=720)el('wwi-ed-side').style.width=w+'px';
        h.addEventListener('mousedown',function(e){
            e.preventDefault();
            var startX=e.clientX,startW=el('wwi-ed-side').offsetWidth;
            function mv(ev){var nw=Math.min(720,Math.max(300,startW+(startX-ev.clientX)));el('wwi-ed-side').style.width=nw+'px'}
            function up(){
                document.removeEventListener('mousemove',mv);
                document.removeEventListener('mouseup',up);
                try{localStorage.setItem('wwi_ed_w',String(el('wwi-ed-side').offsetWidth))}catch(e){}
            }
            document.addEventListener('mousemove',mv);
            document.addEventListener('mouseup',up);
        });
    }
    function bindSelect(){
        document.addEventListener('click',function(e){
            if(!document.body.classList.contains('wwi-edit-on'))return;
            if(e.target.closest('.wwi-ed-side')||e.target.closest('.wwi-ed-bar')||e.target.closest('.wwi-ed-toast')||e.target.closest('.wwi-ed-tools'))return;
            if(e.target.closest('[contenteditable="true"]'))return;
            var src=e.target.closest('[data-source]');
            var edit=e.target.closest('[data-editable]');
            if(src&&!edit){
                e.preventDefault();e.stopPropagation();
                var sp=String(src.getAttribute('data-source')).split(':');
                if(sp[0]==='settings'){showSettingsSource(sp.slice(2));return}
                showSource(sp[0],sp[1]?parseInt(sp[1],10):null,sp[2]||null,sp[3]!==undefined?parseInt(sp[3],10):null);
                return;
            }
            var sec=e.target.closest('.wwi-section[data-sid]');
            if(sec){
                e.preventDefault();e.stopPropagation();
                var sid=parseInt(sec.dataset.sid,10);
                if(edit)selectElement(sid,edit.getAttribute('data-editable'));
                else selectSection(sid);
                return;
            }
            deselect();
        },true);
        document.addEventListener('mouseover',function(e){
            if(!document.body.classList.contains('wwi-edit-on'))return;
            if(e.target.closest('.wwi-ed-side')||e.target.closest('.wwi-ed-bar')||e.target.closest('.wwi-ed-tools'))return;
            var edit=e.target.closest('[data-editable]');
            document.querySelectorAll('[data-editable].wwi-sel-el').forEach(function(x){if(x!==edit)x.classList.remove('wwi-sel-el')});
            if(edit)edit.classList.add('wwi-sel-el');
        });
        document.addEventListener('dblclick',function(e){
            if(!document.body.classList.contains('wwi-edit-on'))return;
            var edit=e.target.closest('[data-editable]');
            if(!edit)return;
            e.preventDefault();e.stopPropagation();
            var sec=edit.closest('.wwi-section[data-sid]');
            if(!sec)return;
            var sid=parseInt(sec.dataset.sid,10);
            edit.setAttribute('contenteditable','true');
            edit.style.outline='2px solid rgba(139,92,246,.9)';
            edit.style.outlineOffset='2px';
            try{edit.focus()}catch(err){}
            var done=false;
            var finish=function(commit){
                if(done)return;done=true;
                edit.removeAttribute('contenteditable');
                edit.style.outline='';edit.style.outlineOffset='';
                edit.removeEventListener('blur',onBlur);
                if(!commit)return;
                var val=edit.textContent.replace(/\s+/g,' ').trim();
                loadSection(sid,function(s){saveElement(s,edit.getAttribute('data-editable'),val)});
            };
            var onBlur=function(){finish(true)};
            edit.addEventListener('blur',onBlur);
            edit.addEventListener('keydown',function(ev){
                if(ev.key==='Enter'&&!ev.shiftKey){ev.preventDefault();finish(true)}
                else if(ev.key==='Escape'){ev.preventDefault();finish(false)}
            });
        });
    }
    function deselect(){
        S.sel=null;S.sec=null;
        document.querySelectorAll('.wwi-sel').forEach(function(x){x.classList.remove('wwi-sel')});
        document.querySelectorAll('.wwi-sel-el').forEach(function(x){x.classList.remove('wwi-sel-el')});
        if(S.tab==='content')renderBody();
    }
    function selectSection(sid){
        document.querySelectorAll('.wwi-sel').forEach(function(x){x.classList.remove('wwi-sel')});
        var sec=q('.wwi-section[data-sid="'+sid+'"]');
        if(sec)sec.classList.add('wwi-sel');
        S.sel={kind:'section',sid:sid};
        openSide(true);S.tab='content';renderTabs();
        setStatus('Cargando…');
        loadSection(sid,function(s){S.sec=s;setStatus('');renderBody()});
    }
    function selectElement(sid,key){
        document.querySelectorAll('.wwi-sel').forEach(function(x){x.classList.remove('wwi-sel')});
        var sec=q('.wwi-section[data-sid="'+sid+'"]');
        if(sec)sec.classList.add('wwi-sel');
        S.sel={kind:'element',sid:sid,key:key};
        openSide(true);S.tab='content';renderTabs();
        setStatus('Cargando…');
        loadSection(sid,function(s){S.sec=s;setStatus('');renderBody()});
    }
    function loadSection(sid,cb){
        api('/api/v1/admin/sections/'+sid).then(function(d){
            if(!d.ok||!d.data){toast('No se pudo cargar la sección',true);return}
            var s=d.data;
            var chain=Promise.resolve({data:{configSchema:[]}});
            if(s.widget_type)chain=api('/api/v1/admin/bricks/'+encodeURIComponent(s.widget_type)).catch(function(){return{data:{}}});
            chain.then(function(bd){
                s._schema=(bd.data&&bd.data.configSchema)||[];
                s._sources=(bd.data&&bd.data.editContract&&bd.data.editContract.sources)||{};
                cb(s);
            });
        });
    }
    function cfgOf(s){try{return JSON.parse(s.config||'{}')||{}}catch(e){return{}}}
    function renderBody(){
        var b=el('wwi-ed-body');if(!b)return;
        if(S.timer){clearTimeout(S.timer);S.timer=null}
        if(S.tab==='add'){renderAdd(b);return}
        if(S.tab==='page'){renderPage(b);return}
        if(S.tab==='quality'){renderQuality(b);return}
        if(S.tab==='comments'){renderComments(b);return}
        renderContent(b);
    }
    function renderContent(b){
        if(!S.sel){
            b.innerHTML='<div class="wwi-ed-hint">Haz clic en una <strong>sección</strong> del sitio para editarla, o directamente en un <strong>texto / botón / precio</strong>.<br><br>Arrastra el borde izquierdo para redimensionar. Los cambios se guardan automáticamente (Ctrl+Z deshace).</div>'
                +'<div style="margin:14px 0 8px"><input id="wwi-ed-q" placeholder="🔍 Buscar contenido en esta página…" style="width:100%;background:var(--bg2);border:1px solid var(--border2);border-radius:8px;padding:8px 10px;color:var(--text);font-size:12px;outline:none;font-family:inherit"/></div><div id="wwi-ed-qres"></div>';
            var qEl=el('wwi-ed-q');
            qEl.addEventListener('input',function(){if(this.value.length>=2)doSearch(this.value);else{var r=el('wwi-ed-qres');if(r)r.innerHTML=''}});
            return;
        }
        var s=S.sec;if(!s){b.innerHTML='<div class="wwi-ed-hint">Cargando…</div>';return}
        if(S.sel.kind==='element'){renderElement(b,s);return}
        S.rep={};S.repFields={};
        var cfg=cfgOf(s);
        var h='<div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px">'+esc(s.widget_type||s.type||'sección')+'</div>';
        h+='<label>Título<input id="wwi-ed-title" value="'+esc(s.title||'')+'"/></label>';
        h+='<label>Subtítulo<textarea id="wwi-ed-subtitle">'+esc(s.subtitle||'')+'</textarea></label>';
        if(s.type==='html'||s.type==='custom'){
            h+='<label>Contenido (HTML)<textarea id="wwi-ed-content" style="min-height:150px;font-family:monospace;font-size:11px">'+esc(s.content||'')+'</textarea></label>';
        }
        (s._schema||[]).forEach(function(f){
            if(f.type==='code'&&document.body.classList.contains('wwi-client'))return;
            var v=(cfg[f.key]!==undefined&&cfg[f.key]!==null)?cfg[f.key]:(f.default!==undefined?f.default:'');
            var id='wwi-ed-f-'+f.key;
            if(f.type==='toggle')h+='<label class="wwi-ed-check"><input type="checkbox" id="'+id+'" '+(v?'checked':'')+'/> '+esc(f.label)+'</label>';
            else if(f.type==='select'){
                var o='';
                for(var k in (f.options||{}))o+='<option value="'+esc(k)+'"'+(String(v)===String(k)?' selected':'')+'>'+esc(f.options[k])+'</option>';
                h+='<label>'+esc(f.label)+'<select id="'+id+'">'+o+'</select></label>';
            }
            else if(f.type==='image')h+='<label><span style="display:flex;justify-content:space-between;align-items:center">'+esc(f.label)+defBtn(f)+'</span><div style="display:flex;gap:6px;margin-top:4px"><input id="'+id+'" value="'+esc(v)+'" placeholder="URL de imagen"/><button type="button" class="wwi-ed-btn" data-imgpick="'+f.key+'">📁</button></div></label><div id="wwi-imgp-'+f.key+'" style="margin-bottom:10px">'+(v?'<img src="'+esc(v)+'" style="max-width:100%;border-radius:8px"/>':'')+'</div>';
            else if(f.type==='link')h+='<label><span style="display:flex;justify-content:space-between;align-items:center">'+esc(f.label)+defBtn(f)+'</span><div style="display:flex;gap:6px;margin-top:4px"><input id="'+id+'" value="'+esc(v)+'" placeholder="#seccion, /pagina o https://…"/><button type="button" class="wwi-ed-btn" data-linkpick="'+f.key+'">🔗</button></div></label>';
            else if(f.type==='richtext')h+='<div style="margin-bottom:10px"><label style="display:flex;justify-content:space-between;align-items:center">'+esc(f.label)+defBtn(f)+aiBtn(f)+'</label><div class="wwi-ed-rt-tools"><button type="button" class="wwi-ed-btn" data-rt="bold" data-rkey="'+f.key+'"><b>B</b></button><button type="button" class="wwi-ed-btn" data-rt="italic" data-rkey="'+f.key+'"><i>I</i></button><button type="button" class="wwi-ed-btn" data-rt="insertUnorderedList" data-rkey="'+f.key+'">• Lista</button><button type="button" class="wwi-ed-btn" data-rt="createLink" data-rkey="'+f.key+'">🔗</button></div><div class="wwi-ed-rt-body" id="wwi-rt-'+f.key+'" contenteditable="true">'+(typeof v==='string'?v:'')+'</div><input type="hidden" id="'+id+'" value="'+esc(typeof v==='object'?JSON.stringify(v):v)+'"/></div>';
            else if(f.type==='textarea'||f.type==='code')h+='<label><span style="display:flex;justify-content:space-between;align-items:center">'+esc(f.label)+defBtn(f)+aiBtn(f)+'</span><textarea id="'+id+'">'+esc(typeof v==='object'?JSON.stringify(v):v)+'</textarea></label>';
            else if(f.type==='repeater'){
                S.repFields[f.key]=f.fields||[];
                var rv=cfg[f.key];
                if(typeof rv==='string'){try{rv=JSON.parse(rv||'[]')}catch(e){rv=[]}}
                S.rep[f.key]=Array.isArray(rv)?rv.slice():[];
                h+='<div class="wwi-ed-rep"><div style="font-size:11px;color:var(--muted);margin-bottom:6px;display:flex;justify-content:space-between;align-items:center"><span>'+esc(f.label)+'</span><span>'+aiBtnRep(f)+defBtn(f)+'</span></div><div id="wwi-rep-'+f.key+'"></div><button class="wwi-ed-btn" data-repadd="'+f.key+'" style="margin-top:6px">+ Añadir</button></div>';
            }
            else h+='<label><span style="display:flex;justify-content:space-between;align-items:center">'+esc(f.label)+defBtn(f)+aiBtn(f)+'</span><input id="'+id+'" value="'+esc(v)+'"/></label>';
        });
        var srcs=s._sources||{};
        if(Object.keys(srcs).length){
            h+='<div style="margin:10px 0;padding:10px;border:1px dashed var(--border2);border-radius:10px"><div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:6px">Contenido de fuente</div>';
            Object.keys(srcs).forEach(function(k){
                h+='<button class="wwi-ed-btn" data-src="'+esc(k)+'" style="margin:0 6px 6px 0">🧩 Editar '+esc((srcs[k]&&srcs[k].label)||k)+'</button>';
            });
            h+='<div style="font-size:10px;color:var(--muted);margin-top:2px">Estos datos alimentan el ecosistema y afectan a los sitios que usan esta fuente.</div></div>';
        }
        h+='<label class="wwi-ed-check"><input type="checkbox" id="wwi-ed-active" '+(s.is_active==1||s.is_active==='1'?'checked':'')+'/> Visible en el sitio</label>';
        h+='<label class="wwi-ed-check"><input type="checkbox" id="wwi-ed-hm" '+(cfg._hide_mobile?'checked':'')+'/> Ocultar en móvil</label>';
        h+='<label class="wwi-ed-check"><input type="checkbox" id="wwi-ed-ht" '+(cfg._hide_tablet?'checked':'')+'/> Ocultar en tablet</label>';
        var client=document.body.classList.contains('wwi-client');
        h+='<div class="wwi-ed-actions"><button class="wwi-ed-save" id="wwi-ed-save">Guardar</button><button class="wwi-ed-btn" id="wwi-ed-hist">🕘 Historial</button><button class="wwi-ed-btn" id="wwi-ed-ab">🧪 A/B</button>'+(client?'':'<button class="wwi-ed-btn" id="wwi-ed-up" title="Subir">▲</button><button class="wwi-ed-btn" id="wwi-ed-down" title="Bajar">▼</button><button class="wwi-ed-btn" id="wwi-ed-dup">Duplicar</button><button class="wwi-ed-btn" id="wwi-ed-del" style="color:#f87171">Eliminar</button>')+'</div>';
        b.innerHTML=h;
        b.querySelectorAll('[data-imgpick]').forEach(function(btn){
            btn.onclick=function(){
                pickMedia(function(url){
                    var key=btn.dataset.imgpick;
                    var inp=el('wwi-ed-f-'+key);if(inp)inp.value=url;
                    var pv=el('wwi-imgp-'+key);if(pv)pv.innerHTML='<img src="'+esc(url)+'" style="max-width:100%;border-radius:8px"/>';
                    scheduleAuto(function(){saveSection(s,collect(s),true)});
                });
            };
        });
        b.querySelectorAll('[data-linkpick]').forEach(function(btn){
            btn.onclick=function(){
                pickPage(function(url){
                    var inp=el('wwi-ed-f-'+btn.dataset.linkpick);
                    if(inp)inp.value=url;
                    scheduleAuto(function(){saveSection(s,collect(s),true)});
                });
            };
        });
        b.querySelectorAll('[data-rt]').forEach(function(btn){
            btn.addEventListener('mousedown',function(e){e.preventDefault()});
            btn.onclick=function(){
                var key=btn.dataset.rkey;
                var body=el('wwi-rt-'+key);if(!body)return;
                body.focus();
                var cmd=btn.dataset.rt;
                if(cmd==='createLink'){var u=window.prompt('URL del enlace:','https://');if(u)document.execCommand('createLink',false,u)}
                else document.execCommand(cmd,false,null);
                syncRt(key,s);
            };
        });
        b.querySelectorAll('.wwi-ed-rt-body').forEach(function(body){
            var key=body.id.replace('wwi-rt-','');
            body.addEventListener('input',function(){syncRt(key,s)});
            body.addEventListener('blur',function(){syncRt(key,s)});
        });
        b.querySelectorAll('[data-def]').forEach(function(btn){
            btn.onclick=function(e){
                e.preventDefault();e.stopPropagation();
                var key=btn.dataset.def;
                var f=(s._schema||[]).filter(function(x){return x.key===key})[0];
                if(!f)return;
                var val=f.default!==undefined?f.default:'';
                if(f.type==='repeater'){S.rep[key]=Array.isArray(val)?val.slice():[];repRender(s,key)}
                else if(f.type==='richtext'){var body=el('wwi-rt-'+key);if(body)body.innerHTML=String(val);var hi=el('wwi-ed-f-'+key);if(hi)hi.value=String(val)}
                else{var inp=el('wwi-ed-f-'+key);if(inp){if(f.type==='toggle')inp.checked=!!val;else inp.value=val}}
                scheduleAuto(function(){saveSection(s,collect(s),true)});
                toast('Valor por defecto restaurado');
            };
        });
        b.querySelectorAll('[data-ai]').forEach(function(btn){
            btn.onclick=function(e){e.preventDefault();e.stopPropagation();aiField(s,btn.dataset.ai,btn)};
        });
        b.querySelectorAll('[data-airep]').forEach(function(btn){
            btn.onclick=function(e){e.preventDefault();e.stopPropagation();aiRepeater(s,btn.dataset.airep,btn)};
        });
        b.querySelectorAll('[data-repadd]').forEach(function(btn){
            btn.onclick=function(){
                var key=btn.dataset.repadd;
                var fields=S.repFields[key]||[];
                var item={};
                fields.forEach(function(ff){item[ff.key]=''});
                S.rep[key].push(item);
                repRender(s,key);
                scheduleAuto(function(){saveSection(s,collect(s),true)});
            };
        });
        Object.keys(S.rep).forEach(function(key){repRender(s,key)});
        b.querySelectorAll('[data-src]').forEach(function(btn){btn.onclick=function(){showSource(btn.dataset.src,null)}});
        b.querySelectorAll('input,textarea,select').forEach(function(inp){
            inp.addEventListener('input',function(){scheduleAuto(function(){saveSection(s,collect(s),true)})});
        });
        el('wwi-ed-save').onclick=function(){saveSection(s,collect(s),false)};
        el('wwi-ed-hist').onclick=function(){showVersions(s.id)};
        el('wwi-ed-ab').onclick=function(){showVariants(s.id)};
        if(!client){
            el('wwi-ed-up').onclick=function(){moveSection(s.id,-1)};
            el('wwi-ed-down').onclick=function(){moveSection(s.id,1)};
            el('wwi-ed-dup').onclick=function(){duplicateSection(s.id)};
            el('wwi-ed-del').onclick=function(){deleteSection(s.id,s.title||s.widget_type||('#'+s.id))};
        }
    }
    function collect(s){
        var data={title:(el('wwi-ed-title')||{value:''}).value,subtitle:(el('wwi-ed-subtitle')||{value:''}).value,is_active:el('wwi-ed-active')&&el('wwi-ed-active').checked?1:0};
        if(s.type==='html'||s.type==='custom')data.content=(el('wwi-ed-content')||{value:''}).value;
        var cfg=cfgOf(s);
        (s._schema||[]).forEach(function(f){
            var fEl=el('wwi-ed-f-'+f.key);if(!fEl)return;
            if(f.type==='toggle')cfg[f.key]=fEl.checked?1:0;
            else if(f.type==='code'){try{cfg[f.key]=JSON.parse(fEl.value||'null')}catch(e){cfg[f.key]=fEl.value}}
            else cfg[f.key]=fEl.value;
        });
        cfg._hide_mobile=(el('wwi-ed-hm')&&el('wwi-ed-hm').checked)?1:0;
        cfg._hide_tablet=(el('wwi-ed-ht')&&el('wwi-ed-ht').checked)?1:0;
        Object.keys(S.rep).forEach(function(key){cfg[key]=S.rep[key]});
        data.config=cfg;
        return data;
    }
    function repRender(s,key){
        var list=el('wwi-rep-'+key);
        if(!list)return;
        var fields=S.repFields[key]||[];
        var items=S.rep[key]||[];
        var h='';
        items.forEach(function(it,idx){
            h+='<div class="wwi-rep-item"><div class="wwi-rep-head"><span>#'+(idx+1)+'</span><span>';
            h+='<button class="wwi-ed-btn" data-repmv="-1" data-idx="'+idx+'" style="padding:1px 6px">▲</button> ';
            h+='<button class="wwi-ed-btn" data-repmv="1" data-idx="'+idx+'" style="padding:1px 6px">▼</button> ';
            h+='<button class="wwi-ed-btn" data-repdup="'+idx+'" style="padding:1px 6px">⧉</button> ';
            h+='<button class="wwi-ed-btn" data-repdel="'+idx+'" style="padding:1px 6px;color:#f87171">✕</button>';
            h+='</span></div>';
            fields.forEach(function(ff){
                var v=it[ff.key]!==undefined?it[ff.key]:'';
                if(ff.type==='textarea')h+='<label>'+esc(ff.label)+'<textarea data-rf="'+ff.key+'" data-ridx="'+idx+'">'+esc(v)+'</textarea></label>';
                else h+='<label>'+esc(ff.label)+'<input data-rf="'+ff.key+'" data-ridx="'+idx+'" value="'+esc(v)+'"/></label>';
            });
            h+='</div>';
        });
        if(!items.length)h='<div class="wwi-ed-hint">Sin elementos.</div>';
        list.innerHTML=h;
        list.querySelectorAll('[data-rf]').forEach(function(inp){
            inp.addEventListener('input',function(){
                var i=parseInt(inp.dataset.ridx,10);
                if(S.rep[key]&&S.rep[key][i])S.rep[key][i][inp.dataset.rf]=inp.value;
                scheduleAuto(function(){saveSection(s,collect(s),true)});
            });
        });
        list.querySelectorAll('[data-repmv]').forEach(function(btn){
            btn.onclick=function(){
                var i=parseInt(btn.dataset.idx,10),j=i+parseInt(btn.dataset.repmv,10);
                if(j<0||j>=S.rep[key].length)return;
                var t=S.rep[key][i];S.rep[key][i]=S.rep[key][j];S.rep[key][j]=t;
                repRender(s,key);
                scheduleAuto(function(){saveSection(s,collect(s),true)});
            };
        });
        list.querySelectorAll('[data-repdup]').forEach(function(btn){
            btn.onclick=function(){
                var i=parseInt(btn.dataset.repdup,10);
                S.rep[key].splice(i+1,0,JSON.parse(JSON.stringify(S.rep[key][i])));
                repRender(s,key);
                scheduleAuto(function(){saveSection(s,collect(s),true)});
            };
        });
        list.querySelectorAll('[data-repdel]').forEach(function(btn){
            btn.onclick=function(){
                var i=parseInt(btn.dataset.repdel,10);
                S.rep[key].splice(i,1);
                repRender(s,key);
                scheduleAuto(function(){saveSection(s,collect(s),true)});
            };
        });
    }
    function snapshot(s){
        var snap={title:s.title||'',subtitle:s.subtitle||'',is_active:(s.is_active==1||s.is_active==='1')?1:0};
        if(s.type==='html'||s.type==='custom')snap.content=s.content||'';
        if(s.widget_type)snap.config=cfgOf(s);
        return snap;
    }
    function saveSection(s,data,silent){
        if(S.saving)return;
        if(el('wwi-ed-title')){
            var errs=validateFields(s);
            if(errs.length){
                markErrors(errs);
                setStatus('Corrige los campos marcados');
                if(!silent)toast(errs[0].msg,true);
                return;
            }
        }
        S.saving=true;setStatus('Guardando…');
        var prev=snapshot(s);
        api('/api/v1/admin/sections/'+s.id,{method:'PUT',body:data}).then(function(r){
            S.saving=false;
            if(!r.ok){setStatus('Error al guardar');toast(r.message||'Error al guardar',true);return}
            S.undo.push({sid:s.id,data:prev});
            if(S.undo.length>25)S.undo.shift();
            setStatus('Guardado ✓ '+new Date().toTimeString().slice(0,5));
            if(!silent)toast('Guardado ✓');
            s.title=data.title;s.subtitle=data.subtitle;s.is_active=data.is_active;
            if(data.config)s.config=JSON.stringify(data.config);
            if(data.content!==undefined)s.content=data.content;
            refresh(s.id);
        }).catch(function(){S.saving=false;setStatus('Error de conexión');toast('Error de conexión',true)});
    }
    function scheduleAuto(fn){
        if(S.timer)clearTimeout(S.timer);
        S.timer=setTimeout(function(){S.timer=null;fn()},1200);
    }
    function undo(){
        var last=S.undo.pop();
        if(!last){toast('Nada que deshacer',true);return}
        api('/api/v1/admin/sections/'+last.sid,{method:'PUT',body:last.data}).then(function(r){
            if(!r.ok){toast(r.message||'Error al deshacer',true);return}
            toast('Deshecho');
            refresh(last.sid);
            if(S.sel&&S.sel.sid===last.sid)loadSection(last.sid,function(s){S.sec=s;renderBody()});
        });
    }
    function refresh(sid){
        api('/api/v1/admin/sections/'+sid+'/render').then(function(r){
            if(!r.ok||!r.data)return;
            var sec=q('.wwi-section[data-sid="'+sid+'"]');
            if(!sec)return;
            var tb=sec.querySelector('.wwi-ed-tools');
            sec.innerHTML=r.data.html;
            if(tb)sec.insertBefore(tb,sec.firstChild);
        });
    }
    function renderElement(b,s){
        var path=S.sel.key;
        var cfg=cfgOf(s);
        var parts=String(path).split('.');
        var val='',label=path,ftype='text';
        if(parts.length===3){
            var key=parts[0],idx=parseInt(parts[1],10),sub=parts[2];
            var arr=Array.isArray(cfg[key])?cfg[key]:[];
            var item=arr[idx]||{};
            val=item[sub]!==undefined?item[sub]:'';
            var repF=(s._schema||[]).filter(function(x){return x.key===key})[0];
            var subF=repF?((repF.fields||[]).filter(function(x){return x.key===sub})[0]||null):null;
            label=((subF&&subF.label)||sub)+' · '+(repF?repF.label:key)+' #'+(idx+1);
            ftype=(subF&&subF.type)||'text';
        }else{
            var f=(s._schema||[]).filter(function(x){return x.key===parts[0]})[0];
            val=cfg[parts[0]]!==undefined?cfg[parts[0]]:(f&&f.default!==undefined?f.default:'');
            label=(f&&f.label)||parts[0];
            ftype=(f&&f.type)||'text';
        }
        var long=String(val).length>70||ftype==='textarea'||ftype==='code'||ftype==='richtext';
        var h='<div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px">Elemento · '+esc(label)+'</div>';
        h+=long?'<label>'+esc(label)+'<textarea id="wwi-ed-el">'+esc(typeof val==='object'?JSON.stringify(val):val)+'</textarea></label>':'<label>'+esc(label)+'<input id="wwi-ed-el" value="'+esc(val)+'"/></label>';
        h+='<div class="wwi-ed-actions"><button class="wwi-ed-save" id="wwi-ed-el-save">Guardar</button><button class="wwi-ed-btn" id="wwi-ed-el-ai">✨ Mejorar con IA</button><button class="wwi-ed-btn" id="wwi-ed-el-rep">Editar la lista</button><button class="wwi-ed-btn" id="wwi-ed-el-sec">Editar sección completa</button></div>';
        b.innerHTML=h;
        var inp=el('wwi-ed-el');
        inp.addEventListener('input',function(){scheduleAuto(function(){savePath(s,path,inp.value)})});
        el('wwi-ed-el-save').onclick=function(){savePath(s,path,inp.value)};
        el('wwi-ed-el-ai').onclick=function(){aiImprove(inp)};
        el('wwi-ed-el-rep').onclick=function(){selectSection(s.id)};
        el('wwi-ed-el-sec').onclick=function(){selectSection(s.id)};
        try{inp.focus()}catch(e){}
    }
    function savePath(s,path,val){
        var parts=String(path).split('.');
        var cfg=cfgOf(s);
        if(parts.length===1){
            cfg[parts[0]]=val;
        }else if(parts.length===3){
            var key=parts[0],idx=parseInt(parts[1],10),sub=parts[2];
            if(!Array.isArray(cfg[key]))cfg[key]=[];
            while(cfg[key].length<=idx)cfg[key].push({});
            if(!cfg[key][idx]||typeof cfg[key][idx]!=='object')cfg[key][idx]={};
            cfg[key][idx][sub]=val;
        }else{
            return;
        }
        saveSection(s,{title:s.title||'',subtitle:s.subtitle||'',is_active:(s.is_active==1||s.is_active==='1')?1:0,config:cfg});
    }
    function saveElement(s,key,val){savePath(s,key,val)}
    function renderAdd(b){
        var h='<div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px">Insertar en esta página</div><div class="wwi-ed-cat">';
        h+='<button id="wwi-add-brick"><i>🧱</i>Sección / Brick<small>Desde el Marketplace</small></button>';
        h+='<button id="wwi-add-text"><i>¶</i>Texto<small>Párrafo editable</small></button>';
        h+='<button id="wwi-add-title"><i>H</i>Título<small>Encabezado grande</small></button>';
        h+='<button id="wwi-add-btn"><i>▢</i>Botón<small>CTA con enlace</small></button>';
        h+='<button id="wwi-add-img"><i>▣</i>Imagen<small>Desde la biblioteca</small></button>';
        h+='<button id="wwi-add-2col"><i>▥</i>Fila 2 columnas<small>Grid responsive</small></button>';
        h+='<button id="wwi-add-3col"><i>▦</i>Fila 3 columnas<small>Grid responsive</small></button>';
        h+='<button id="wwi-add-code"><i>{ }</i>Código<small>HTML embebido</small></button>';
        h+='<button id="wwi-add-sep"><i>—</i>Separador<small>Línea divisoria</small></button>';
        h+='</div><div id="wwi-add-slot" style="margin-top:14px"></div>';
        b.innerHTML=h;
        el('wwi-add-brick').onclick=brickPicker;
        el('wwi-add-text').onclick=function(){addHtml('<section style="padding:56px 0"><div class="wrap"><p style="font-size:15px;color:var(--muted);line-height:1.8">Escribe aquí tu texto…</p></div></section>','Texto')};
        el('wwi-add-title').onclick=function(){addHtml('<section style="padding:56px 0"><div class="wrap"><h2 style="font-size:28px;font-weight:800;letter-spacing:-.02em">Tu título aquí</h2></div></section>','Título')};
        el('wwi-add-btn').onclick=function(){addHtml('<section style="padding:36px 0"><div class="wrap" style="text-align:center"><a class="btn btn-primary" href="#">Mi botón</a></div></section>','Botón')};
        el('wwi-add-img').onclick=mediaPicker;
        el('wwi-add-2col').onclick=function(){addHtml('<section style="padding:56px 0"><div class="wrap" style="display:grid;grid-template-columns:1fr 1fr;gap:18px"><div class="card">Columna 1</div><div class="card">Columna 2</div></div></section>','Fila 2 columnas')};
        el('wwi-add-3col').onclick=function(){addHtml('<section style="padding:56px 0"><div class="wrap" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px"><div class="card">Columna 1</div><div class="card">Columna 2</div><div class="card">Columna 3</div></div></section>','Fila 3 columnas')};
        el('wwi-add-code').onclick=function(){addHtml('<section style="padding:40px 0"><div class="wrap"><pre style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:16px;overflow:auto;font-size:12px"><code>&lt;!-- tu código aquí --&gt;</code></pre></div></section>','Código')};
        el('wwi-add-sep').onclick=function(){addHtml('<section style="padding:18px 0"><div class="wrap"><hr style="border:none;border-top:1px solid var(--border)"/></div></section>','Separador')};
        b.querySelectorAll('.wwi-ed-cat button').forEach(function(btn){
            if(btn.id==='wwi-add-img'||btn.id==='wwi-add-brick')return;
            btn.setAttribute('draggable','true');
            btn.addEventListener('dragstart',function(e){
                e.dataTransfer.setData('text/plain',JSON.stringify({kind:'tpl',key:btn.id.replace('wwi-add-','')}));
                e.dataTransfer.effectAllowed='copy';
                buildDropzones();
            });
            btn.addEventListener('dragend',cleanupDropzones);
        });
    }
    function addHtml(html,title,cb){
        if(!CTX.pageId){toast('Página no disponible',true);return}
        setStatus('Insertando…');
        api('/api/v1/admin/pages/'+CTX.pageId+'/sections',{method:'POST',body:{type:'html',title:title,content:html,config:{}}}).then(function(r){
            if(!r.ok){setStatus('');toast(r.message||'Error al insertar',true);return}
            appendSection(r.data.id,cb);
            toast('"'+title+'" insertado');
            setStatus('');
        }).catch(function(){setStatus('');toast('Error de conexión',true)});
    }
    function appendSection(id,cb){
        api('/api/v1/admin/sections/'+id+'/render').then(function(r){
            if(!r.ok)return;
            var main=q('main');
            var div=document.createElement('div');
            div.className='wwi-section';
            div.dataset.sid=id;
            div.innerHTML=r.data.html;
            main.appendChild(div);
            decorate();
            if(cb){cb(id)}
            else{
                selectSection(id);
                try{div.scrollIntoView({behavior:'smooth',block:'center'})}catch(e){}
            }
        });
    }
    function brickPicker(){
        var slot=el('wwi-add-slot');if(!slot)return;
        slot.innerHTML='<div class="wwi-ed-hint">Cargando bricks…</div>';
        api('/api/v1/admin/bricks').then(function(d){
            var items=d.data||{};
            var h='<div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px">Bricks disponibles</div><div style="display:flex;flex-direction:column;gap:6px">';
            var n=0;
            for(var id in items){
                var b=items[id];
                if(b.functional===false)continue;
                n++;
                h+='<button class="wwi-ed-btn" data-brick="'+esc(id)+'" data-name="'+esc(b.name)+'" style="text-align:left">'+esc(b.name)+' <small style="color:var(--muted);margin-left:6px">'+esc(b.category||'')+'</small></button>';
            }
            h+='</div>';
            slot.innerHTML=n?h:'<div class="wwi-ed-hint">Sin bricks disponibles</div>';
            slot.querySelectorAll('[data-brick]').forEach(function(btn){
                btn.setAttribute('draggable','true');
                btn.addEventListener('dragstart',function(e){
                    e.dataTransfer.setData('text/plain',JSON.stringify({kind:'brick',id:btn.dataset.brick,name:btn.dataset.name}));
                    e.dataTransfer.effectAllowed='copy';
                    buildDropzones();
                });
                btn.addEventListener('dragend',cleanupDropzones);
                btn.onclick=function(){
                    api('/api/v1/admin/pages/'+CTX.pageId+'/sections',{method:'POST',body:{type:'widget',widget_type:btn.dataset.brick,title:btn.dataset.name,config:{}}}).then(function(r){
                        if(r.ok){appendSection(r.data.id);toast('"'+btn.dataset.name+'" añadido')}
                        else toast(r.message||'Error',true);
                    });
                };
            });
        });
    }
    function mediaPicker(){
        var slot=el('wwi-add-slot');if(!slot)return;
        slot.innerHTML='<div class="wwi-ed-hint">Cargando imágenes…</div>';
        api('/api/v1/admin/media').then(function(d){
            var items=d.data||[];
            if(!items.length){slot.innerHTML='<div class="wwi-ed-hint">No hay imágenes aún. Súbelas desde el admin → Media.</div>';return}
            var h='<div class="wwi-ed-media">';
            items.forEach(function(m){h+='<button data-url="'+esc(m.url)+'" data-alt="'+esc(m.alt_text||m.filename||'')+'"><img src="'+esc(m.url)+'" alt=""/></button>'});
            h+='</div>';
            slot.innerHTML=h;
            slot.querySelectorAll('[data-url]').forEach(function(btn){
                btn.onclick=function(){
                    addHtml('<section style="padding:40px 0"><div class="wrap"><img src="'+btn.dataset.url+'" alt="'+btn.dataset.alt+'" style="width:100%;border-radius:12px"/></div></section>','Imagen');
                };
            });
        });
    }
    function renderPage(b){
        b.innerHTML='<div class="wwi-ed-hint">Cargando…</div>';
        Promise.all([api('/api/v1/admin/pages'),api('/api/v1/admin/pages/'+CTX.pageId+'/sections')]).then(function(res){
            var pages=res[0].data||[],secs=res[1].data||[];
            var h='<div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px">Página actual</div>';
            h+='<div style="font-size:13px;font-weight:700">'+esc(CTX.pageTitle||'')+'</div><div style="font-size:11px;color:var(--muted);margin-bottom:14px">/'+esc(CTX.pageSlug||'')+'</div>';
            h+='<div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px">Secciones ('+secs.length+')</div>';
            h+='<div id="wwi-ed-tree">';
            secs.forEach(function(s){
                h+='<div class="wwi-ed-tree-item'+(S.sel&&S.sel.sid===s.id?' on':'')+'" data-sid="'+s.id+'"><span class="nm">'+esc(s.title||s.widget_type||('#'+s.id))+'</span><button class="wwi-ed-btn" data-act="up" style="padding:2px 7px">▲</button><button class="wwi-ed-btn" data-act="down" style="padding:2px 7px">▼</button></div>';
            });
            h+='</div>';
            h+='<div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin:16px 0 8px">Ir a otra página</div>';
            h+='<div style="display:flex;flex-direction:column;gap:6px">';
            pages.forEach(function(p){
                var url=(p.slug==='home')?'/':'/'+p.slug;
                h+='<a class="wwi-ed-btn" href="'+esc(url)+'" style="text-decoration:none">'+esc(p.title)+' <small style="color:var(--muted);margin-left:6px">/'+esc(p.slug)+'</small></a>';
            });
            h+='</div>';
            b.innerHTML=h;
            b.querySelectorAll('.wwi-ed-tree-item').forEach(function(row){
                row.addEventListener('click',function(e){
                    var act=(e.target&&e.target.dataset)?e.target.dataset.act:null;
                    var sid=parseInt(row.dataset.sid,10);
                    if(act==='up'||act==='down'){e.stopPropagation();moveSection(sid,act==='up'?-1:1);return}
                    selectSection(sid);
                });
            });
        });
    }
    function moveSection(sid,dir){
        var sec=q('.wwi-section[data-sid="'+sid+'"]');
        if(!sec)return;
        var sib=dir<0?sec.previousElementSibling:sec.nextElementSibling;
        if(!sib||!sib.classList.contains('wwi-section'))return;
        if(dir<0)sec.parentNode.insertBefore(sec,sib);
        else sec.parentNode.insertBefore(sib,sec);
        saveOrder();
    }
    function saveOrder(){
        var items=[];
        document.querySelectorAll('.wwi-section[data-sid]').forEach(function(s,i){items.push({id:parseInt(s.dataset.sid,10),sort_order:i})});
        api('/api/v1/admin/sections/reorder',{method:'PUT',body:{items:items}}).then(function(r){
            toast(r.ok?'Orden guardado':'No se pudo guardar el orden',!r.ok);
        });
    }
    function duplicateSection(sid){
        api('/api/v1/admin/sections/'+sid).then(function(d){
            if(!d.ok||!d.data)return;
            var s=d.data;
            var cfg={};try{cfg=JSON.parse(s.config||'{}')||{}}catch(e){}
            api('/api/v1/admin/pages/'+s.page_id+'/sections',{method:'POST',body:{type:s.type,widget_type:s.widget_type,title:(s.title||'')+' (copia)',subtitle:s.subtitle,content:s.content,config:cfg}}).then(function(r){
                if(!r.ok){toast(r.message||'Error',true);return}
                var orig=q('.wwi-section[data-sid="'+sid+'"]');
                api('/api/v1/admin/sections/'+r.data.id+'/render').then(function(rr){
                    if(!rr.ok)return;
                    var main=q('main');
                    var div=document.createElement('div');
                    div.className='wwi-section';
                    div.dataset.sid=r.data.id;
                    div.innerHTML=rr.data.html;
                    if(orig&&orig.nextElementSibling)main.insertBefore(div,orig.nextElementSibling);
                    else main.appendChild(div);
                    decorate();
                    selectSection(r.data.id);
                    saveOrder();
                    toast('Sección duplicada');
                });
            });
        });
    }
    function deleteSection(sid,name){
        if(!window.confirm('¿Eliminar la sección "'+name+'"? Esta acción no se puede deshacer.'))return;
        api('/api/v1/admin/sections/'+sid,{method:'DELETE'}).then(function(r){
            if(!r.ok){toast(r.message||'Error',true);return}
            var sec=q('.wwi-section[data-sid="'+sid+'"]');
            if(sec)sec.remove();
            deselect();
            toast('Sección eliminada');
        });
    }
    function toggleVisible(sid){
        api('/api/v1/admin/sections/'+sid).then(function(d){
            if(!d.ok||!d.data)return;
            var s=d.data;
            var active=(s.is_active==1||s.is_active==='1')?0:1;
            var cfg={};try{cfg=JSON.parse(s.config||'{}')||{}}catch(e){}
            api('/api/v1/admin/sections/'+sid,{method:'PUT',body:{title:s.title,subtitle:s.subtitle,is_active:active,config:cfg}}).then(function(r){
                if(r.ok){
                    var sec=q('.wwi-section[data-sid="'+sid+'"]');
                    if(sec)sec.style.opacity=active?'':'0.4';
                    toast(active?'Sección visible':'Sección oculta');
                }else toast(r.message||'Error',true);
            });
        });
    }
    var TEMPLATES={
        text:{title:'Texto',html:'<section style="padding:56px 0"><div class="wrap"><p style="font-size:15px;color:var(--muted);line-height:1.8">Escribe aquí tu texto…</p></div></section>'},
        title:{title:'Título',html:'<section style="padding:56px 0"><div class="wrap"><h2 style="font-size:28px;font-weight:800;letter-spacing:-.02em">Tu título aquí</h2></div></section>'},
        btn:{title:'Botón',html:'<section style="padding:36px 0"><div class="wrap" style="text-align:center"><a class="btn btn-primary" href="#">Mi botón</a></div></section>'},
        '2col':{title:'Fila 2 columnas',html:'<section style="padding:56px 0"><div class="wrap" style="display:grid;grid-template-columns:1fr 1fr;gap:18px"><div class="card">Columna 1</div><div class="card">Columna 2</div></div></section>'},
        '3col':{title:'Fila 3 columnas',html:'<section style="padding:56px 0"><div class="wrap" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px"><div class="card">Columna 1</div><div class="card">Columna 2</div><div class="card">Columna 3</div></div></section>'},
        code:{title:'Código',html:'<section style="padding:40px 0"><div class="wrap"><pre style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:16px;overflow:auto;font-size:12px"><code>&lt;!-- tu código aquí --&gt;</code></pre></div></section>'},
        sep:{title:'Separador',html:'<section style="padding:18px 0"><div class="wrap"><hr style="border:none;border-top:1px solid var(--border)"/></div></section>'}
    };
    function buildDropzones(){
        cleanupDropzones();
        var main=q('main');if(!main)return;
        document.querySelectorAll('main .wwi-section[data-sid]').forEach(function(sec){
            main.insertBefore(mkZone(sec),sec);
        });
        main.appendChild(mkZone(null));
    }
    function mkZone(before){
        var z=document.createElement('div');
        z.className='wwi-dropzone';
        z.addEventListener('dragover',function(e){e.preventDefault();z.classList.add('over')});
        z.addEventListener('dragleave',function(){z.classList.remove('over')});
        z.addEventListener('drop',function(e){
            e.preventDefault();
            var data={};
            try{data=JSON.parse(e.dataTransfer.getData('text/plain'))||{}}catch(err){}
            dropInsert(before,data);
        });
        return z;
    }
    function cleanupDropzones(){document.querySelectorAll('.wwi-dropzone').forEach(function(z){z.remove()})}
    function dropInsert(before,data){
        cleanupDropzones();
        if(!data||!data.kind)return;
        if(data.kind==='tpl'){
            var t=TEMPLATES[data.key];
            if(!t){toast('Elemento no válido',true);return}
            addHtml(t.html,t.title,function(id){moveBefore(id,before);toast('"'+t.title+'" insertado')});
        }else if(data.kind==='brick'){
            api('/api/v1/admin/pages/'+CTX.pageId+'/sections',{method:'POST',body:{type:'widget',widget_type:data.id,title:data.name,config:{}}}).then(function(r){
                if(!r.ok){toast(r.message||'Error',true);return}
                appendSection(r.data.id,function(){moveBefore(r.data.id,before);toast('"'+data.name+'" añadido')});
            });
        }
    }
    function moveBefore(sid,before){
        var div=q('.wwi-section[data-sid="'+sid+'"]');
        if(!div)return;
        var main=q('main');
        if(before&&before.parentNode===main)main.insertBefore(div,before);
        else main.appendChild(div);
        saveOrder();
    }
    function aiImprove(inp){
        var txt=(inp.value||'').trim();
        if(!txt){toast('Escribe algo primero',true);return}
        setStatus('IA pensando…');
        api('/api/v1/admin/brick/request',{method:'POST',body:{
            system_prompt:'Eres copywriter senior de marketing digital. Mejora el texto manteniendo idioma, significado y tono profesional, claro y persuasivo. Devuelve SOLO el texto mejorado, sin comillas ni explicaciones.',
            messages:[{role:'user',content:txt}],
            system_id:'wontia',module:'live_editor',function:'improve_copy',temperature:0.6,max_tokens:500
        }}).then(function(r){
            setStatus('');
            var out=(r&&r.data&&r.data.content)?String(r.data.content).trim():'';
            if(!out){toast((r&&r.data&&r.data.error)?r.data.error:'IA sin respuesta',true);return}
            inp.value=out;
            toast('Texto mejorado ✨ (revisa y guarda)');
        }).catch(function(){setStatus('');toast('Error de IA',true)});
    }
    function renderQuality(b){
        var issues=[];
        var sections=document.querySelectorAll('main .wwi-section[data-sid]');
        var imgs=document.querySelectorAll('main img');
        var noAlt=0;
        imgs.forEach(function(im){if(!im.getAttribute('alt'))noAlt++});
        if(noAlt)issues.push({sev:'warn',txt:noAlt+' imagen(es) sin atributo alt',target:null});
        var h1=document.querySelectorAll('main h1');
        if(h1.length===0)issues.push({sev:'warn',txt:'La página no tiene H1',target:null});
        if(h1.length>1)issues.push({sev:'warn',txt:h1.length+' elementos H1 (debería haber 1)',target:h1[1]});
        var emptyLinks=0;
        document.querySelectorAll('main a').forEach(function(a){if(!a.textContent.trim()&&!a.querySelector('img'))emptyLinks++});
        if(emptyLinks)issues.push({sev:'warn',txt:emptyLinks+' enlace(s) sin texto',target:null});
        var noLabel=0;
        document.querySelectorAll('main input,main textarea,main select').forEach(function(inp){
            if(inp.type==='hidden')return;
            var has=inp.getAttribute('aria-label')||inp.closest('label');
            var id=inp.getAttribute('id');
            if(!has&&id)has=document.querySelector('label[for="'+id+'"]');
            if(!has)noLabel++;
        });
        if(noLabel)issues.push({sev:'warn',txt:noLabel+' campo(s) sin etiqueta accesible',target:null});
        if(!(document.title||'').trim())issues.push({sev:'bad',txt:'La página no tiene <title>',target:null});
        var meta=q('meta[name="description"]');
        if(!meta||!(meta.getAttribute('content')||'').trim())issues.push({sev:'warn',txt:'Falta meta description',target:null});
        var words=(q('main')?q('main').textContent:'').trim().split(/\s+/).length;
        if(words<120)issues.push({sev:'info',txt:'Poco contenido: '+words+' palabras en la página',target:null});
        var covEditable=0,noEdit=[];
        document.querySelectorAll('main p,main h1,main h2,main h3,main h4,main a,main button,main img,main li').forEach(function(elm){
            var isImg=elm.tagName==='IMG';
            var txt=(elm.textContent||'').trim();
            if(!isImg&&txt.length<4)return;
            if(elm.closest('[data-editable]')||elm.closest('[data-source]')||elm.closest('.wwi-ed-tools')){covEditable++;return}
            noEdit.push(elm);
        });
        if(noEdit.length)issues.push({sev:'info',txt:noEdit.length+' elemento(s) visibles sin editor (cobertura)',target:noEdit[0]});
        var covTotal=covEditable+noEdit.length;
        var covPct=covTotal?Math.round(covEditable/covTotal*100):100;
        var score=100;
        issues.forEach(function(i){score-=i.sev==='bad'?15:(i.sev==='warn'?8:3)});
        if(score<0)score=0;
        var col=score>=85?'#34d399':(score>=60?'#fbbf24':'#f87171');
        var h='<div style="display:flex;align-items:center;gap:12px;margin-bottom:14px"><div style="font-size:30px;font-weight:800;color:'+col+'">'+score+'</div><div><div style="font-size:12px;font-weight:700">Calidad de la página</div><div style="font-size:10px;color:var(--muted)">'+sections.length+' secciones · '+imgs.length+' imágenes · '+words+' palabras · cobertura '+covPct+'%</div></div></div>';
        h+='<div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px">Hallazgos ('+issues.length+')</div>';
        if(!issues.length)h+='<div class="wwi-ed-hint">¡Todo en orden! No se detectaron problemas.</div>';
        issues.forEach(function(i,idx){
            var ic=i.sev==='bad'?'⛔':(i.sev==='warn'?'⚠':'ℹ');
            h+='<div class="wwi-ed-tree-item" data-qi="'+idx+'"><span class="nm">'+ic+' '+esc(i.txt)+'</span></div>';
        });
        h+='<div style="font-size:10px;color:var(--muted);margin-top:12px;line-height:1.6">Chequeos en vivo: alt de imágenes, H1 único, enlaces con texto, etiquetas de formularios, title y meta description, volumen de contenido.</div>';
        h+='<div class="w-card" style="margin-top:12px;padding:10px"><div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:6px">Telemetría de edición</div><div id="wwi-ed-tele" class="wwi-ed-hint">Cargando…</div></div>';
        b.innerHTML=h;
        b.querySelectorAll('[data-qi]').forEach(function(row){
            row.addEventListener('click',function(){
                var i=issues[parseInt(row.dataset.qi,10)];
                if(i&&i.target){
                    var sec=i.target.closest('.wwi-section[data-sid]');
                    if(sec){selectSection(parseInt(sec.dataset.sid,10));try{sec.scrollIntoView({behavior:'smooth',block:'center'})}catch(e){}}
                }
            });
        });
        api('/api/v1/admin/editor/telemetry').then(function(d){
            var t=d.data||{};
            var box=el('wwi-ed-tele');if(!box)return;
            var tf=t.top_fields||[],wg=t.widgets||[];
            var hh='';
            if(tf.length){hh+='<div style="margin-bottom:6px"><b>Campos más editados</b>';tf.slice(0,6).forEach(function(x){hh+='<div>'+esc(x.widget_type||'')+' · '+esc(x.field||'')+' — '+x.c+'</div>'});hh+='</div>'}
            if(wg.length){hh+='<div><b>Widgets más editados</b>';wg.slice(0,5).forEach(function(x){hh+='<div>'+esc(x.widget_type||'')+' — '+x.c+'</div>'});hh+='</div>'}
            box.innerHTML=hh||'Sin datos de edición todavía.';
        });
    }
    function renderComments(b){
        if(!S.sel){b.innerHTML='<div class="wwi-ed-hint">Selecciona una sección en el sitio para ver y añadir comentarios anclados a ella.</div>';return}
        var sid=S.sel.sid;
        b.innerHTML='<div class="wwi-ed-hint">Cargando comentarios…</div>';
        api('/api/v1/admin/sections/'+sid+'/comments').then(function(d){
            var list=d.data||[];
            var h='<div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px">Comentarios de la sección</div>';
            h+='<label>Nuevo comentario<textarea id="wwi-ed-cbody" placeholder="Escribe un comentario o feedback…"></textarea></label>';
            h+='<div class="wwi-ed-actions"><button class="wwi-ed-save" id="wwi-ed-cadd">Comentar</button></div>';
            h+='<div id="wwi-ed-clist" style="margin-top:14px">';
            if(!list.length)h+='<div class="wwi-ed-hint">Sin comentarios todavía.</div>';
            list.forEach(function(c){
                h+='<div class="wwi-ed-comment'+(c.status==='resolved'?' done':'')+'"><div class="hd"><b>'+esc(c.username||'')+'</b><span>'+esc(String(c.created_at||'').slice(0,16))+'</span></div><div class="bd">'+esc(c.body)+'</div><div class="ac"><button class="wwi-ed-btn" data-cact="toggle" data-cid="'+c.id+'" data-cst="'+(c.status==='resolved'?'open':'resolved')+'">'+(c.status==='resolved'?'Reabrir':'Resolver')+'</button><button class="wwi-ed-btn" data-cact="del" data-cid="'+c.id+'" style="color:#f87171">Eliminar</button></div></div>';
            });
            h+='</div>';
            b.innerHTML=h;
            el('wwi-ed-cadd').onclick=function(){
                var body=(el('wwi-ed-cbody')||{value:''}).value.trim();
                if(!body){toast('Escribe un comentario',true);return}
                api('/api/v1/admin/sections/'+sid+'/comments',{method:'POST',body:{body:body}}).then(function(r){
                    if(r.ok){toast('Comentario añadido');renderComments(b)}
                    else toast(r.message||'Error',true);
                });
            };
            b.querySelectorAll('[data-cact]').forEach(function(btn){
                btn.onclick=function(){
                    var cid=btn.dataset.cid;
                    if(btn.dataset.cact==='toggle'){
                        api('/api/v1/admin/comments/'+cid,{method:'PUT',body:{status:btn.dataset.cst}}).then(function(r){if(r.ok)renderComments(b)});
                    }else{
                        api('/api/v1/admin/comments/'+cid,{method:'DELETE'}).then(function(r){if(r.ok){toast('Comentario eliminado');renderComments(b)}});
                    }
                };
            });
        });
    }
    function showVersions(sid){
        var ov=document.createElement('div');
        ov.className='wwi-ed-modal';
        ov.innerHTML='<div class="wwi-ed-modal-box"><div class="wwi-ed-head"><strong>🕘 Historial de versiones</strong><button class="wwi-ed-x" id="wwi-ed-vclose">✕</button></div><div id="wwi-ed-vlist" class="wwi-ed-hint">Cargando…</div></div>';
        document.body.appendChild(ov);
        el('wwi-ed-vclose').onclick=function(){ov.remove()};
        ov.addEventListener('mousedown',function(e){if(e.target===ov)ov.remove()});
        api('/api/v1/admin/sections/'+sid+'/versions').then(function(d){
            var list=d.data||[];
            var box=el('wwi-ed-vlist');
            if(!list.length){box.innerHTML='Sin versiones guardadas aún. Se guardan automáticamente con cada cambio.';return}
            api('/api/v1/admin/sections/'+sid).then(function(sd){
                var cur=sd.data||{};
                var curCfg={};try{curCfg=JSON.parse(cur.config||'{}')||{}}catch(e){}
                var h='';
                list.forEach(function(v){
                    var snap={};try{snap=typeof v.snapshot==='string'?JSON.parse(v.snapshot):(v.snapshot||{})}catch(e){}
                    var ch=[];
                    if((snap.title||'')!==(cur.title||''))ch.push('título');
                    if((snap.subtitle||'')!==(cur.subtitle||''))ch.push('subtítulo');
                    if((snap.content||'')!==(cur.content||''))ch.push('contenido');
                    var sc=snap.config||{};
                    var campos=false;
                    Object.keys(sc).forEach(function(k){if(JSON.stringify(sc[k])!==JSON.stringify(curCfg[k]))campos=true;});
                    if(campos&&ch.indexOf('campos')<0)ch.push('campos');
                    h+='<div class="wwi-ed-tree-item"><span class="nm">'+esc(String(v.created_at||'').slice(0,16))+' · '+esc(v.username||'')+(ch.length?' · <span style="color:#fbbf24">cambios: '+ch.join(', ')+'</span>':' · sin cambios')+'</span><button class="wwi-ed-btn" data-vid="'+v.id+'">Restaurar</button></div>';
                });
                box.innerHTML=h;
                box.querySelectorAll('[data-vid]').forEach(function(btn){
                    btn.onclick=function(){
                        if(!window.confirm('¿Restaurar esta versión? El estado actual quedará en el historial.'))return;
                        api('/api/v1/admin/versions/'+btn.dataset.vid+'/restore',{method:'POST'}).then(function(r){
                            if(!r.ok){toast(r.message||'Error',true);return}
                            toast('Versión restaurada');
                            ov.remove();
                            refresh(sid);
                            if(S.sel&&S.sel.sid===sid)loadSection(sid,function(s){S.sec=s;renderBody()});
                        });
                    };
                });
            });
        });
    }
    var PRESENCE_TIMER=null;
    function startPresence(){
        if(PRESENCE_TIMER)return;
        document.addEventListener('mousemove',function(e){
            S.cursor={x:Math.round(e.clientX/window.innerWidth*10000)/100,y:Math.round(e.clientY/window.innerHeight*10000)/100};
        },{passive:true});
        pingPresence();
        PRESENCE_TIMER=setInterval(pingPresence,6000);
    }
    function renderCursors(list){
        var seen={};
        (list||[]).forEach(function(p){
            if(p.cursor_x==null||p.cursor_y==null)return;
            if(CTX.pageId&&p.page_id&&parseInt(p.page_id,10)!==parseInt(CTX.pageId,10))return;
            seen[p.user_id]=1;
            var c=el('wwi-ed-cur-'+p.user_id);
            if(!c){
                c=document.createElement('div');
                c.id='wwi-ed-cur-'+p.user_id;
                c.className='wwi-ed-cursor';
                c.innerHTML='<span class="dot"></span><span class="nm"></span>';
                document.body.appendChild(c);
            }
            c.style.left=p.cursor_x+'%';
            c.style.top=p.cursor_y+'%';
            c.querySelector('.nm').textContent=p.username||('#'+p.user_id);
        });
        document.querySelectorAll('.wwi-ed-cursor').forEach(function(c){
            var id=parseInt(c.id.replace('wwi-ed-cur-',''),10);
            if(!seen[id])c.remove();
        });
    }
    function pingPresence(){
        var sid=(S.sel&&S.sel.sid)?S.sel.sid:null;
        api('/api/v1/admin/presence',{method:'POST',body:{section_id:sid,page_id:CTX.pageId||null,cursor_x:S.cursor?S.cursor.x:null,cursor_y:S.cursor?S.cursor.y:null}}).then(function(){
            api('/api/v1/admin/presence').then(function(d){
                var list=d.data||[];
                renderCursors(list);
                var pEl=el('wwi-ed-peers');
                if(!pEl)return;
                if(!list.length){pEl.textContent='';return}
                pEl.textContent='· 👤 '+list.map(function(p){return p.username||('#'+p.user_id)}).join(', ');
                var same=list.filter(function(p){return sid&&parseInt(p.section_id,10)===parseInt(sid,10)});
                if(same.length&&!S._warned){
                    S._warned=true;
                    toast('👤 '+same[0].username+' también está editando esta sección');
                }
            });
        }).catch(function(){});
    }
    function showVariants(sid){
        var ov=document.createElement('div');
        ov.className='wwi-ed-modal';
        ov.innerHTML='<div class="wwi-ed-modal-box"><div class="wwi-ed-head"><strong>🧪 A/B testing de la sección</strong><button class="wwi-ed-x" id="wwi-ed-abclose">✕</button></div><div id="wwi-ed-ablist" class="wwi-ed-hint">Cargando…</div><div id="wwi-ed-abedit"></div></div>';
        document.body.appendChild(ov);
        el('wwi-ed-abclose').onclick=function(){ov.remove()};
        ov.addEventListener('mousedown',function(e){if(e.target===ov)ov.remove()});
        function load(){
            api('/api/v1/admin/sections/'+sid+'/variants').then(function(d){
                var list=d.data||[];
                var box=el('wwi-ed-ablist');
                var h='<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px"><span style="font-size:11px;color:var(--muted)">'+list.length+' variante(s) · asignación determinista por visitante</span><button class="wwi-ed-btn" id="wwi-ed-abnew">+ Nueva variante</button></div>';
                if(!list.length)h+='<div class="wwi-ed-hint">Sin variantes. Crea una para testear una versión alternativa de esta sección.</div>';
                list.forEach(function(v){
                    var ctr=v.views>0?Math.round(v.clicks/v.views*100):0;
                    h+='<div class="wwi-ed-comment"><div class="hd"><b>'+esc(v.name||'B')+'</b><span>peso '+v.weight+' · 👁 '+v.views+' · 🖱 '+v.clicks+' · CTR '+ctr+'%</span></div><div class="ac"><button class="wwi-ed-btn" data-va="edit" data-vid="'+v.id+'">Editar</button><button class="wwi-ed-btn" data-va="toggle" data-vid="'+v.id+'">'+(v.is_active==1?'Desactivar':'Activar')+'</button><button class="wwi-ed-btn" data-va="del" data-vid="'+v.id+'" style="color:#f87171">Eliminar</button></div></div>';
                });
                box.innerHTML=h;
                el('wwi-ed-abnew').onclick=function(){editVariant(null,load)};
                box.querySelectorAll('[data-va]').forEach(function(btn){
                    btn.onclick=function(){
                        var vid=parseInt(btn.dataset.vid,10);
                        var v=null;
                        list.forEach(function(x){if(parseInt(x.id,10)===vid)v=x});
                        if(btn.dataset.va==='edit')editVariant(v,load);
                        else if(btn.dataset.va==='del'){
                            if(!window.confirm('¿Eliminar la variante?'))return;
                            api('/api/v1/admin/variants/'+vid,{method:'DELETE'}).then(function(){load()});
                        }else{
                            var cfg={};try{cfg=JSON.parse(v.config||'{}')||{}}catch(e){}
                            api('/api/v1/admin/sections/'+sid+'/variants',{method:'POST',body:{id:vid,name:v.name,config:cfg,weight:v.weight,is_active:v.is_active==1?0:1}}).then(function(){load()});
                        }
                    };
                });
            });
        }
        load();
    }
    function editVariant(v,cb){
        loadSection(S.sel.sid,function(s){
            var cfg={};
            if(v){try{cfg=JSON.parse(v.config||'{}')||{}}catch(e){cfg={}}}
            else{cfg=cfgOf(s)}
            var h='<div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin:10px 0 8px">'+(v?'Editar variante':'Nueva variante (clon de la actual)')+'</div>';
            h+='<label>Nombre<input id="wwi-av-name" value="'+esc(v?v.name:'B')+'"/></label>';
            h+='<label>Peso (1-100)<input id="wwi-av-weight" type="number" min="1" max="100" value="'+(v?v.weight:50)+'"/></label>';
            (s._schema||[]).forEach(function(f){
                var val=cfg[f.key]!==undefined?cfg[f.key]:(f.default!==undefined?f.default:'');
                var id='wwi-av-f-'+f.key;
                if(f.type==='toggle')h+='<label class="wwi-ed-check"><input type="checkbox" id="'+id+'" '+(val?'checked':'')+'/> '+esc(f.label)+'</label>';
                else if(f.type==='textarea'||f.type==='code')h+='<label>'+esc(f.label)+'<textarea id="'+id+'">'+esc(typeof val==='object'?JSON.stringify(val):val)+'</textarea></label>';
                else h+='<label>'+esc(f.label)+'<input id="'+id+'" value="'+esc(val)+'"/></label>';
            });
            h+='<div class="wwi-ed-actions"><button class="wwi-ed-save" id="wwi-av-save">Guardar variante</button></div>';
            var slot=el('wwi-ed-abedit');
            slot.innerHTML=h;
            el('wwi-av-save').onclick=function(){
                var ncfg={};
                (s._schema||[]).forEach(function(f){
                    var fEl=el('wwi-av-f-'+f.key);if(!fEl)return;
                    if(f.type==='toggle')ncfg[f.key]=fEl.checked?1:0;
                    else if(f.type==='code'){try{ncfg[f.key]=JSON.parse(fEl.value||'null')}catch(e){ncfg[f.key]=fEl.value}}
                    else ncfg[f.key]=fEl.value;
                });
                api('/api/v1/admin/sections/'+s.id+'/variants',{method:'POST',body:{id:v?v.id:null,name:el('wwi-av-name').value,weight:parseInt(el('wwi-av-weight').value,10)||50,is_active:v?(v.is_active==1?1:0):1,config:ncfg}}).then(function(r){
                    if(r.ok){toast('Variante guardada');slot.innerHTML='';if(cb)cb()}
                    else toast(r.message||'Error',true);
                });
            };
        });
    }
    function showSource(kind,focusId,field,fidx){
        var ov=document.createElement('div');
        ov.className='wwi-ed-modal';
        ov.innerHTML='<div class="wwi-ed-modal-box"><div class="wwi-ed-head"><strong>🧩 '+(field?'Editar '+esc(field):'Fuente: '+esc(kind))+'</strong><button class="wwi-ed-x" id="wwi-ed-sclose">✕</button></div><div id="wwi-ed-slist" class="wwi-ed-hint">Cargando…</div></div>';
        document.body.appendChild(ov);
        el('wwi-ed-sclose').onclick=function(){ov.remove()};
        ov.addEventListener('mousedown',function(e){if(e.target===ov)ov.remove()});
        var url=kind==='plans'?'/api/v1/admin/source/plans':(kind==='templates'?'/api/v1/admin/source/templates':'');
        if(!url){ov.remove();return}
        api(url).then(function(d){
            var list=d.data||[];
            var box=el('wwi-ed-slist');
            if(!list.length){box.innerHTML='Sin datos en esta fuente.';return}
            var item=null;
            if(focusId)list.forEach(function(x){if(parseInt(x.id,10)===parseInt(focusId,10))item=x});
            if(field&&item){
                var h='<div style="font-size:10px;color:var(--muted);margin-bottom:8px">'+esc(item.name_es||('#'+focusId))+(kind==='plans'?' · Pricing Engine del ecosistema (afecta a todos los sitios)':' · catálogo de plantillas')+'</div>';
                if(field==='feature'){
                    var feats=item.features||[];
                    h+='<label>Feature #'+((fidx||0)+1)+'<input id="wwi-src-val" value="'+esc(feats[fidx]||'')+'"/></label>';
                }else if(field==='status'){
                    h+='<label>Estado<select id="wwi-src-val">'+['active','beta','coming_soon','deprecated'].map(function(st){return '<option value="'+st+'"'+(item.status===st?' selected':'')+'>'+st+'</option>'}).join('')+'</select></label>';
                }else{
                    h+='<label>'+esc(field)+'<input id="wwi-src-val" value="'+esc(item[field]!==undefined?item[field]:'')+'"/></label>';
                }
                h+='<div class="wwi-ed-actions"><button class="wwi-ed-save" id="wwi-src-save">Guardar</button></div>';
                box.innerHTML=h;
                el('wwi-src-save').onclick=function(){
                    var val=el('wwi-src-val').value;
                    var payload={};
                    if(field==='feature'){
                        var feats2=(item.features||[]).slice();
                        feats2[fidx]=val;
                        payload.features=feats2;
                    }else if(field==='price_cop'||field==='price_usd'){
                        payload[field]=parseFloat(val)||0;
                    }else{
                        payload[field]=val;
                    }
                    api((kind==='plans'?'/api/v1/admin/source/plans/':'/api/v1/admin/source/templates/')+focusId,{method:'PUT',body:payload}).then(function(r){
                        if(r.ok){toast('Fuente actualizada');refreshSources();ov.remove()}
                        else toast(r.message||'Error',true);
                    });
                };
                try{el('wwi-src-val').focus()}catch(e){}
                return;
            }
            var h='<div style="font-size:10px;color:var(--muted);margin-bottom:8px">'+(kind==='plans'?'Estos valores alimentan el Pricing Engine del ecosistema (requiere superadmin).':'Estos valores alimentan el catálogo de plantillas del ecosistema.')+'</div>';
            list.forEach(function(it){
                h+='<div class="wwi-ed-comment'+(focusId&&parseInt(it.id,10)===parseInt(focusId,10)?' on':'')+'" data-sid="'+it.id+'">';
                h+='<label>Nombre ES<input data-f="name_es" value="'+esc(it.name_es||'')+'"/></label>';
                h+='<label>Nombre EN<input data-f="name_en" value="'+esc(it.name_en||'')+'"/></label>';
                if(kind==='plans'){
                    h+='<label>Precio COP<input data-f="price_cop" type="number" value="'+esc(it.price_cop)+'"/></label>';
                    h+='<label>Precio USD<input data-f="price_usd" type="number" value="'+esc(it.price_usd)+'"/></label>';
                    h+='<label>Features (una por línea)<textarea data-f="features">'+esc((it.features||[]).join('\n'))+'</textarea></label>';
                }else{
                    h+='<label>Estado<select data-f="status">'+['active','beta','coming_soon','deprecated'].map(function(st){return '<option value="'+st+'"'+(it.status===st?' selected':'')+'>'+st+'</option>'}).join('')+'</select></label>';
                }
                h+='<div class="ac"><button class="wwi-ed-btn" data-ssave="'+it.id+'">Guardar</button></div></div>';
            });
            box.innerHTML=h;
            box.querySelectorAll('[data-ssave]').forEach(function(btn){
                btn.onclick=function(){
                    var card=btn.closest('[data-sid]');
                    var payload={};
                    card.querySelectorAll('[data-f]').forEach(function(inp){
                        var f=inp.dataset.f;
                        if(f==='features')payload[f]=inp.value.split('\n').map(function(x){return x.trim()}).filter(function(x){return x});
                        else if(f==='price_cop'||f==='price_usd')payload[f]=parseFloat(inp.value)||0;
                        else payload[f]=inp.value;
                    });
                    api((kind==='plans'?'/api/v1/admin/source/plans/':'/api/v1/admin/source/templates/')+btn.dataset.ssave,{method:'PUT',body:payload}).then(function(r){
                        if(r.ok){toast('Fuente actualizada');refreshSources()}
                        else toast(r.message||'Error',true);
                    });
                };
            });
        }).catch(function(){var b2=el('wwi-ed-slist');if(b2)b2.textContent='No se pudo cargar la fuente (¿permisos?)';});
    }
    function refreshSources(){
        try{if(window.wwiLoadPlans)window.wwiLoadPlans()}catch(e){}
        try{if(window.wwiLoadTemplates)window.wwiLoadTemplates()}catch(e){}
    }
    function defBtn(f){
        if(f.default===undefined)return '';
        return '<button type="button" class="wwi-ed-btn" data-def="'+f.key+'" title="Restaurar valor por defecto" style="padding:1px 6px;font-size:10px">↺</button>';
    }
    function aiBtn(f){
        if(f.type!=='text'&&f.type!=='textarea'&&f.type!=='richtext')return '';
        return '<button type="button" class="wwi-ed-btn" data-ai="'+f.key+'" title="Generar o mejorar con IA" style="padding:1px 6px;font-size:10px">✨</button>';
    }
    function aiBtnRep(f){
        return '<button type="button" class="wwi-ed-btn" data-airep="'+f.key+'" title="Rellenar la lista con IA" style="padding:1px 6px;font-size:10px">✨ IA</button>';
    }
    function aiField(s,key,btn){
        var f=(s._schema||[]).filter(function(x){return x.key===key})[0];
        if(!f)return;
        var inp=el('wwi-ed-f-'+key);
        var current=f.type==='richtext'?(el('wwi-rt-'+key)?el('wwi-rt-'+key).innerHTML:''):(inp?inp.value:'');
        var label=f.label||key;
        var ask=window.prompt('¿Qué quieres que haga la IA con "'+label+'"?\nEj: mejora el texto · hazlo más corto · traduce al inglés · hazlo más persuasivo','mejora el texto');
        if(ask===null)return;
        setStatus('IA pensando…');
        var sys='Eres copywriter senior de marketing digital. Devuelve SOLO el resultado final (sin comillas ni explicaciones), en el idioma pedido, listo para pegar en una web.';
        var prompt='Campo: '+label+'\nInstrucción: '+ask+'\nTexto actual:\n'+current;
        api('/api/v1/admin/brick/request',{method:'POST',body:{system_prompt:sys,messages:[{role:'user',content:prompt}],system_id:'wontia',module:'live_editor',function:'fill_field',temperature:0.6,max_tokens:700}}).then(function(r){
            setStatus('');
            var out=(r&&r.data&&r.data.content)?String(r.data.content).trim():'';
            if(!out){toast((r&&r.data&&r.data.error)?r.data.error:'IA sin respuesta',true);return}
            if(f.type==='richtext'){var body=el('wwi-rt-'+key);if(body)body.innerHTML=out;if(inp)inp.value=out}
            else if(inp)inp.value=out;
            scheduleAuto(function(){saveSection(s,collect(s),true)});
            toast('Contenido generado ✨');
        }).catch(function(){setStatus('');toast('Error de IA',true)});
    }
    function aiRepeater(s,key,btn){
        var f=(s._schema||[]).filter(function(x){return x.key===key})[0];
        if(!f)return;
        var fields=S.repFields[key]||[];
        var ctx=window.prompt('¿Para qué negocio/sector quieres rellenar "'+(f.label||key)+'"?\nEj: panadería artesanal en Medellín','');
        if(ctx===null)return;
        var count=parseInt(window.prompt('¿Cuántos elementos?','6')||'6',10)||6;
        count=Math.min(20,Math.max(1,count));
        setStatus('IA generando…');
        var keys=fields.map(function(x){return x.key}).join(', ');
        var sys='Devuelve SOLO un JSON array válido (sin markdown) con '+count+' objetos cuyas claves son: '+keys+'. Textos breves, profesionales y específicos.';
        var prompt='Contexto del negocio: '+ctx+'\nGenera los '+count+' elementos.';
        api('/api/v1/admin/brick/request',{method:'POST',body:{system_prompt:sys,messages:[{role:'user',content:prompt}],system_id:'wontia',module:'live_editor',function:'fill_repeater',temperature:0.7,max_tokens:1200}}).then(function(r){
            setStatus('');
            var out=(r&&r.data&&r.data.content)?String(r.data.content).trim():'';
            var json=out.replace(/```json/gi,'').replace(/```/g,'').trim();
            var arr=null;
            try{arr=JSON.parse(json)}catch(e){var m=json.match(/\[[\s\S]*\]/);if(m){try{arr=JSON.parse(m[0])}catch(e2){}}}
            if(!Array.isArray(arr)||!arr.length){toast('La IA no devolvió una lista válida',true);return}
            S.rep[key]=arr.slice(0,count).map(function(it){
                var o={};
                fields.forEach(function(ff){o[ff.key]=(it&&it[ff.key]!=null)?String(it[ff.key]):''});
                return o;
            });
            repRender(s,key);
            scheduleAuto(function(){saveSection(s,collect(s),true)});
            toast('Lista generada con IA ✨');
        }).catch(function(){setStatus('');toast('Error de IA',true)});
    }
    function syncRt(key,s){
        var body=el('wwi-rt-'+key),hi=el('wwi-ed-f-'+key);
        if(body&&hi){hi.value=body.innerHTML;scheduleAuto(function(){saveSection(s,collect(s),true)})}
    }
    function pickMedia(cb){
        var ov=document.createElement('div');
        ov.className='wwi-ed-modal';
        ov.innerHTML='<div class="wwi-ed-modal-box"><div class="wwi-ed-head"><strong>📁 Biblioteca de medios</strong><button class="wwi-ed-x" id="wwi-ed-mclose">✕</button></div><div id="wwi-ed-mlist" class="wwi-ed-hint">Cargando…</div></div>';
        document.body.appendChild(ov);
        el('wwi-ed-mclose').onclick=function(){ov.remove()};
        ov.addEventListener('mousedown',function(e){if(e.target===ov)ov.remove()});
        api('/api/v1/admin/media').then(function(d){
            var items=d.data||[];
            var box=el('wwi-ed-mlist');
            if(!items.length){box.innerHTML='No hay imágenes. Súbelas en el admin → Media.';return}
            box.innerHTML='<div class="wwi-ed-media">'+items.map(function(m){return '<button data-url="'+esc(m.url)+'"><img src="'+esc(m.url)+'" alt=""/></button>'}).join('')+'</div>';
            box.querySelectorAll('[data-url]').forEach(function(btn){btn.onclick=function(){ov.remove();cb(btn.dataset.url)}});
        });
    }
    function pickPage(cb){
        var ov=document.createElement('div');
        ov.className='wwi-ed-modal';
        ov.innerHTML='<div class="wwi-ed-modal-box"><div class="wwi-ed-head"><strong>🔗 Enlazar</strong><button class="wwi-ed-x" id="wwi-ed-pclose">✕</button></div><div id="wwi-ed-plist" class="wwi-ed-hint">Cargando…</div></div>';
        document.body.appendChild(ov);
        el('wwi-ed-pclose').onclick=function(){ov.remove()};
        ov.addEventListener('mousedown',function(e){if(e.target===ov)ov.remove()});
        api('/api/v1/admin/pages').then(function(d){
            var pages=d.data||[];
            var box=el('wwi-ed-plist');
            var h='<div style="display:flex;flex-direction:column;gap:6px">';
            h+='<button class="wwi-ed-btn" data-url="#">Ancla / URL manual…</button>';
            pages.forEach(function(p){h+='<button class="wwi-ed-btn" data-url="'+(p.slug==='home'?'/':'/'+esc(p.slug))+'">'+esc(p.title)+' <small style="color:var(--muted)">/'+esc(p.slug)+'</small></button>'});
            h+='</div>';
            box.innerHTML=h;
            box.querySelectorAll('[data-url]').forEach(function(btn){
                btn.onclick=function(){
                    var u=btn.dataset.url;
                    if(u==='#'){u=window.prompt('URL o ancla:','#seccion')||'';if(!u)return}
                    ov.remove();cb(u);
                };
            });
        });
    }
    function validateFields(s){
        var errs=[];
        (s._schema||[]).forEach(function(f){
            var inp=el('wwi-ed-f-'+f.key);
            if(!inp&&f.type!=='repeater'&&f.type!=='richtext')return;
            var v=f.type==='repeater'?(S.rep[f.key]||[]):(f.type==='toggle'?(inp&&inp.checked?1:0):(inp?inp.value:''));
            if(f.required&&(v===''||v===null||(Array.isArray(v)&&!v.length)))errs.push({key:f.key,msg:'Campo obligatorio'});
            if(f.type==='link'&&v&&!/^(https?:\/\/|#|\/)/.test(v)&&!/^[a-z0-9][a-z0-9\-\/]*$/i.test(v))errs.push({key:f.key,msg:'Enlace inválido'});
            if(f.type==='number'&&v!==''&&v!==null&&isNaN(parseFloat(v)))errs.push({key:f.key,msg:'Debe ser numérico'});
        });
        return errs;
    }
    function markErrors(errs){
        document.querySelectorAll('.wwi-ed-err').forEach(function(x){x.classList.remove('wwi-ed-err')});
        document.querySelectorAll('.wwi-ed-errmsg').forEach(function(x){x.remove()});
        errs.forEach(function(er){
            var inp=el('wwi-ed-f-'+er.key);
            if(inp){
                inp.classList.add('wwi-ed-err');
                var m=document.createElement('div');
                m.className='wwi-ed-errmsg';
                m.textContent=er.msg;
                inp.parentNode.appendChild(m);
            }
        });
    }
    function doSearch(q){
        var res=el('wwi-ed-qres');if(!res)return;
        res.innerHTML='<div class="wwi-ed-hint">Buscando…</div>';
        api('/api/v1/admin/pages/'+(CTX.pageId||0)+'/search?q='+encodeURIComponent(q)).then(function(d){
            var list=d.data||[];
            if(!list.length){res.innerHTML='<div class="wwi-ed-hint">Sin resultados.</div>';return}
            var h='';
            list.forEach(function(it){
                h+='<div class="wwi-ed-tree-item" data-gosec="'+it.section_id+'"><span class="nm"><b>'+esc(it.title||'')+'</b>';
                (it.matches||[]).forEach(function(m){h+='<div style="font-size:10px;color:var(--muted)">'+esc(m.label)+': '+esc(m.snippet)+'</div>'});
                h+='</span></div>';
            });
            res.innerHTML=h;
            res.querySelectorAll('[data-gosec]').forEach(function(row){
                row.onclick=function(){
                    var sid=parseInt(row.dataset.gosec,10);
                    selectSection(sid);
                    var sec=q('.wwi-section[data-sid="'+sid+'"]');
                    if(sec)try{sec.scrollIntoView({behavior:'smooth',block:'center'})}catch(e){}
                };
            });
        });
    }
    function showSettingsSource(path){
        var ov=document.createElement('div');
        ov.className='wwi-ed-modal';
        ov.innerHTML='<div class="wwi-ed-modal-box"><div class="wwi-ed-head"><strong>🧩 Editar navegación</strong><button class="wwi-ed-x" id="wwi-ed-setclose">✕</button></div><div id="wwi-ed-setlist" class="wwi-ed-hint">Cargando…</div></div>';
        document.body.appendChild(ov);
        el('wwi-ed-setclose').onclick=function(){ov.remove()};
        ov.addEventListener('mousedown',function(e){if(e.target===ov)ov.remove()});
        api('/api/v1/admin/settings').then(function(d){
            var all=d.data||{};
            var nav={};
            try{nav=JSON.parse(all['wwi_nav']||'{}')||{}}catch(e){nav={}}
            nav=Object.assign({brand:'WWI',logo_letter:'W',cta:'Crear mi sitio',cta_url:'#planes',links:[]},nav);
            var field=path[0]||'brand';
            var label=field,val='';
            if(field==='link'){
                var idx=parseInt(path[1],10),sub=path[2];
                var l=(nav.links&&nav.links[idx])||{};
                val=l[sub]!==undefined?l[sub]:'';
                label='Enlace #'+(idx+1)+' · '+(sub==='url'?'URL':'Texto');
            }else{
                val=nav[field]!==undefined?nav[field]:'';
                label=({brand:'Marca',logo_letter:'Letra del logo',cta:'Texto del botón',cta_url:'URL del botón'})[field]||field;
            }
            var h='<div style="font-size:10px;color:var(--muted);margin-bottom:8px">Navegación del sitio (aplica a todas las páginas)</div>';
            h+='<label>'+esc(label)+'<input id="wwi-set-val" value="'+esc(val)+'"/></label>';
            h+='<div class="wwi-ed-actions"><button class="wwi-ed-save" id="wwi-set-save">Guardar</button></div>';
            el('wwi-ed-setlist').innerHTML=h;
            el('wwi-set-save').onclick=function(){
                var v=el('wwi-set-val').value;
                if(field==='link'){
                    var idx2=parseInt(path[1],10),sub2=path[2];
                    nav.links=nav.links||[];
                    while(nav.links.length<=idx2)nav.links.push({label:'',url:'#'});
                    nav.links[idx2][sub2]=v;
                }else{
                    nav[field]=v;
                }
                api('/api/v1/admin/settings',{method:'PUT',body:{wwi_nav:JSON.stringify(nav)}}).then(function(r){
                    if(r.ok){toast('Navegación actualizada');setTimeout(function(){window.location.reload()},600)}
                    else toast(r.message||'Error',true);
                });
            };
            try{el('wwi-set-val').focus()}catch(e){}
        }).catch(function(){var b2=el('wwi-ed-setlist');if(b2)b2.textContent='No se pudo cargar la configuración'});
    }
    function decorate(){
        document.querySelectorAll('.wwi-section[data-sid]').forEach(function(sec){
            if(sec.dataset.edReady)return;
            sec.dataset.edReady='1';
            var tb=document.createElement('div');
            tb.className='wwi-ed-tools';
            tb.innerHTML='<button data-act="edit" title="Editar sección">✎</button><button data-act="up" title="Subir">▲</button><button data-act="down" title="Bajar">▼</button><button data-act="toggle" title="Ocultar / mostrar">👁</button>';
            sec.insertBefore(tb,sec.firstChild);
            tb.addEventListener('click',function(e){
                var b=e.target.closest('button');
                if(!b)return;
                e.preventDefault();e.stopPropagation();
                var sid=parseInt(sec.dataset.sid,10);
                if(b.dataset.act==='edit')selectSection(sid);
                else if(b.dataset.act==='up')moveSection(sid,-1);
                else if(b.dataset.act==='down')moveSection(sid,1);
                else if(b.dataset.act==='toggle')toggleVisible(sid);
            });
        });
    }
})();
</script>
<?php if (\App\Core\Session::isLoggedIn()): ?>
<link rel="stylesheet" href="/assets/css/builder.css?v=<?= @filemtime(ROOT_DIR . '/public/assets/css/builder.css') ?: time() ?>"/>
<script src="/assets/js/builder.js?v=<?= @filemtime(ROOT_DIR . '/public/assets/js/builder.js') ?: time() ?>" defer></script>
<?php endif; ?>