(function(){
var W={};
W.token=null;
try{W.token=localStorage.getItem('wwi_token')||null}catch(e){}

W.api=async function(url,options){
    options=options||{};
    var headers=options.headers||{};
    if(!(options.body instanceof FormData))headers['Content-Type']=headers['Content-Type']||'application/json';
    if(W.token)headers['Authorization']='Bearer '+W.token;
    if(!(options.body instanceof FormData))options.body=JSON.stringify(options.body);
    var r=await fetch(url,Object.assign({},options,{headers:headers}));
    var d=await r.json();
    if(r.status===401){
        try{localStorage.removeItem('wwi_token')}catch(e){}
        W.token=null;
        if(!W._authRedirected){
            W._authRedirected=true;
            W.notify('Sesión expirada — redirigiendo al login','error');
            setTimeout(function(){window.location.href='/admin.php'},800);
        }
    }
    if(!r.ok&&d.message)W.notify(d.message,'error');
    return d;
};

W.calm=function(){
    try{return window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches}catch(e){return false}
};

W.vt=function(fn){
    if(document.startViewTransition&&!W.calm()){
        try{document.startViewTransition(fn);return}catch(e){}
    }
    fn();
};

W.router=function(){
    var hash=window.location.hash.slice(1)||'dashboard';
    var parts=hash.split('/');
    var panel=parts[0];
    var id=parts[1];
    var action=parts[2];
    if(panel==='brickhub'){window.location.replace(window.location.pathname+window.location.search+'#bricks/extensiones');return}
    if(panel==='brick'){window.location.replace(window.location.pathname+window.location.search+'#bricks/ia');return}
    if(panel==='factory'){var ftab=parts[1]||'inicio';if(ftab==='updates')ftab='system';window.location.replace(window.location.pathname+window.location.search+'#wwi/'+ftab);return}
    if((panel==='wwi'||panel==='factory')&&!window.__WWI_IS_CENTRAL){window.location.replace(window.location.pathname+window.location.search+'#dashboard');return}
    if(panel==='seo'){window.location.replace('/admin.php?panel=seo-global-launch&v='+Date.now()+'#seo-global-launch/monitoreo');return}
    if(panel==='sections'){window.location.replace(window.location.pathname+window.location.search+'#pages/secciones'+(parts[1]?'/'+parts[1]:''));return}
    if(panel.indexOf('section-edit-')===0){W.panels.sectionEditor(panel.replace('section-edit-',''));return}
    if(panel==='seo-global-launch'&&!id){id='resumen';}
    if(panel!=='bricks'){W.state.brickHost=null;W.state.bhHost=null}
    document.querySelectorAll('.w-nav-item').forEach(function(a){a.classList.toggle('active',a.dataset.panel===panel)});
    if(panel==='hero'){var _heroNav=document.querySelector('.w-nav-item[data-panel="bricks"]');if(_heroNav)_heroNav.classList.add('active');}
    var title=document.getElementById('panel-title');
    var titles={dashboard:'Dashboard',wwi:'WWI — Sistema',store:'Tienda',sistecredito:'Sistecrédito','order-center':'Central de pedidos','mega-menu':'Mega Menú',hero:'SONIKA Hero Builder',directory:'Directorio Sonika',toques:'Toques',monas:'Moñas',pages:'Pages',sections:'Sections',bricks:'Bricks',brickhub:'BrickHub',brick:'AI BRICK',factory:'Factory',portal:'Mi Portal',blog:'Blog',media:'Media',banners:'Banners',seo:'SEO','seo-global-launch':'SEO Global Launch','tia-command-center':'TIA Command Center',analytics:'Analytics',settings:'Settings',users:'Users'};
    if(title)title.textContent=titles[panel]||panel;
    var app=document.getElementById('wontia-app');
    if(!app)return;
    W.vt(function(){
        app.innerHTML='<div style="text-align:center;padding:60px;color:var(--w-muted)">Loading...</div>';
        try{
        var fn=W.panels[panel];
        if(fn)fn(id,action,parts);
        else if(panel==='blog'&&id&&!action)W.panels.blogEditor(id);
        else W.notify('Panel not found','error');
        }catch(e){app.innerHTML='<div class="w-empty-state"><h3>Error</h3><p>'+e.message+'</p></div>';}
    });
};

W.state={pages:[],posts:[],categories:[],tags:[],currentPage:null,currentPost:null};

W.notify=function(msg,type){
    type=type||'info';
    var c=document.getElementById('w-toast-container');
    var el=document.createElement('div');
    el.className='w-toast w-toast-'+type;
    el.textContent=msg;
    c.appendChild(el);
    setTimeout(function(){el.remove()},4000);
};

W.modal=function(title,body,actions){
    var ov=document.getElementById('w-modal');
    var ct=document.getElementById('w-modal-content');
    if(!ct)return;
    ct.classList.remove('w-modal--media');
    var html='<h3>'+title+'</h3><div>'+body+'</div>';
    if(actions)html+='<div class="w-modal-actions">'+actions+'</div>';
    ct.innerHTML=html;
    ov.style.display='flex';
};

W.closeModal=function(){
    document.getElementById('w-modal').style.display='none';
};

W.confirm=function(msg,cb){
    W.modal('Confirm',msg,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancel</button><button class="w-btn w-btn-danger" id="w-confirm-yes">Confirm</button>');
    document.getElementById('w-confirm-yes').addEventListener('click',function(){W.closeModal();cb()});
};

W.logout=async function(){
    await W.api('/api/v1/admin/auth/logout',{method:'POST'});
    try{localStorage.removeItem('wwi_token')}catch(e){}
    window.location.reload();
};

W.renderPages=function(tab,pageId){
    if(tab&&/^\d+$/.test(String(tab))){W.renderPageEditor(parseInt(tab,10));return}
    tab=tab||'lista';
    W.state.pagesTab=tab;
    var app=document.getElementById('wontia-app');
    if(!app)return;
    var tabs=[['lista','Páginas'],['secciones','Secciones']];
    var bar='<div class="w-brick-tabs">';
    tabs.forEach(function(t){
        bar+='<button class="w-brick-tab'+(tab===t[0]?' active':'')+'" onclick="wontia.pagesGo(\''+t[0]+'\')">'+t[1]+'</button>';
    });
    bar+='</div>';
    app.innerHTML=bar+'<div id="pages-content"></div>';
    if(tab==='secciones')W.renderSectionManager(pageId,'pages-content');
    else W.renderPageList('pages-content');
};

W.pagesGo=function(t){
    window.location.hash=t==='secciones'?'#pages/secciones':'#pages';
};

W.renderPageList=function(containerId){
    var app=document.getElementById(containerId||'wontia-app');
    var html='<div class="w-flex-between w-mb-lg"><div class="w-flex w-gap-sm"><input class="w-input" style="width:200px" placeholder="Search pages..." id="page-search"/></div><button class="w-btn w-btn-primary" onclick="wontia.panels.pageEditor()">+ New Page</button></div>';
    html+='<div id="page-list"></div>';
    app.innerHTML=html;
    W.refreshPages();
    document.getElementById('page-search').addEventListener('input',W.refreshPages);
};

W.refreshPages=async function(){
    var q=document.getElementById('page-search')?document.getElementById('page-search').value:'';
    var d=await W.api('/api/v1/admin/pages');
    W.state.pages=d.data||[];
    var list=W.state.pages.filter(function(p){return !q||p.title.toLowerCase().indexOf(q.toLowerCase())>-1||p.slug.toLowerCase().indexOf(q.toLowerCase())>-1});
    var el=document.getElementById('page-list');
    if(!el)return;
    if(!list.length){el.innerHTML='<div class="w-empty-state"><h3>No pages</h3><p>Create your first page</p></div>';return;}
    var html='';
    list.forEach(function(p){
        html+='<div class="w-page-list-item" onclick="wontia.panels.pageEditor('+p.id+')">';
        html+='<div><div style="font-size:13px;font-weight:600">'+W.esc(p.title)+'</div><div style="font-size:11px;color:var(--w-muted)">/'+W.esc(p.slug)+' &middot; '+p.section_count+' sections</div></div>';
        html+='<div class="w-flex w-gap-sm"><span class="w-badge w-badge-'+(p.status==='published'?'published':'draft')+'">'+p.status+'</span>';
        html+='<a href="#pages/secciones/'+p.id+'" class="w-btn w-btn-secondary w-btn-sm" onclick="event.stopPropagation()">Secciones</a>';
        html+='<button class="w-btn w-btn-danger w-btn-sm" onclick="event.stopPropagation();wontia.deletePage('+p.id+')">Delete</button></div>';
        html+='</div>';
    });
    el.innerHTML=html;
};

W.deletePage=function(id){
    W.confirm('Delete this page?',function(){
        W.api('/api/v1/admin/pages/'+id,{method:'DELETE'}).then(function(){W.refreshPages();W.notify('Page deleted','success')});
    });
};

W.renderPageEditor=function(id){
    var app=document.getElementById('wontia-app');
    app.innerHTML='<div class="w-card"><h3>'+(id?'Edit Page':'New Page')+'</h3><div class="w-form-group"><label class="w-label">Title</label><input class="w-input" id="pe-title" placeholder="Page title"/></div><div class="w-form-group"><label class="w-label">Slug</label><input class="w-input" id="pe-slug" placeholder="page-slug"/></div><div class="w-form-group"><label class="w-label">Status</label><select class="w-select" id="pe-status"><option value="draft">Draft</option><option value="published">Published</option></select></div><div class="w-form-group"><label class="w-label">Meta Title (SEO)</label><input class="w-input" id="pe-meta-title"/></div><div class="w-form-group"><label class="w-label">Meta Description</label><textarea class="w-textarea" id="pe-meta-desc"></textarea></div><div class="w-flex w-gap-sm w-mt"><button class="w-btn w-btn-primary" id="pe-save">Save</button><button class="w-btn w-btn-secondary" onclick="wontia.router()">Cancel</button></div></div>';
    if(id){
        W.api('/api/v1/admin/pages/'+id).then(function(d){
            if(!d.ok||!d.data){W.notify(d.message||'Page not found','error');W.router();return}
            var p=d.data;
            document.getElementById('pe-title').value=p.title||'';
            document.getElementById('pe-slug').value=p.slug||'';
            document.getElementById('pe-status').value=p.status||'draft';
            document.getElementById('pe-meta-title').value=p.meta_title||'';
            document.getElementById('pe-meta-desc').value=p.meta_description||'';
        });
    }
    document.getElementById('pe-title').addEventListener('input',function(){
        if(!id&&!document.getElementById('pe-slug').dataset.manual)document.getElementById('pe-slug').value=W.slugify(this.value);
    });
    document.getElementById('pe-slug').addEventListener('input',function(){this.dataset.manual='1'});
    document.getElementById('pe-save').addEventListener('click',async function(){
        var data={title:document.getElementById('pe-title').value,slug:document.getElementById('pe-slug').value,status:document.getElementById('pe-status').value,meta_title:document.getElementById('pe-meta-title').value,meta_description:document.getElementById('pe-meta-desc').value};
        if(!data.title){W.notify('Title required','error');return}
        var method=id?'PUT':'POST';
        var url=id?'/api/v1/admin/pages/'+id:'/api/v1/admin/pages';
        var r=await W.api(url,{method:method,body:data});
        if(r.ok){W.notify('Saved','success');W.state.currentPage=r.data;if(!id){window.location.hash='#pages/secciones/'+r.data.id}}
    });
};
W.secEditorCss=function(){
    if(document.getElementById('wwi-sec-css'))return '';
    return '<style id="wwi-sec-css">'
        +'.sec-tabs{display:flex;gap:4px;border-bottom:1px solid var(--w-border);margin:14px 0 16px}'
        +'.sec-tab{background:none;border:none;border-bottom:2px solid transparent;padding:9px 14px;font:600 12.5px inherit;color:var(--w-muted);cursor:pointer}'
        +'.sec-tab:hover{color:var(--w-text)}.sec-tab.on{color:var(--w-primary);border-bottom-color:var(--w-primary)}'
        +'.sec-foot{position:sticky;bottom:0;display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:22px;padding:12px 0 2px;background:var(--w-surface);border-top:1px solid var(--w-border)}'
        +'.sec-status{font-size:12px;color:var(--w-muted)}.sec-status.dirty{color:#f59e0b}.sec-status.saved{color:#16a34a}.sec-status.saving{color:var(--w-muted)}.sec-status.error{color:#ef4444}'
        +'.w-rep{display:flex;flex-direction:column;gap:10px}'
        +'.w-rep-card{border:1px solid var(--w-border);border-radius:10px;background:var(--w-bg);overflow:hidden}'
        +'.w-rep-head{display:flex;align-items:center;gap:8px;padding:8px 10px;border-bottom:1px solid var(--w-border);background:var(--w-surface)}'
        +'.w-rep-head b{font-size:11px;color:var(--w-muted)}.w-rep-head .w-drag-handle{cursor:grab;color:var(--w-muted)}'
        +'.w-rep-body{padding:10px;display:flex;flex-direction:column;gap:8px}'
        +'.rep-lb{display:flex;flex-direction:column;gap:4px;font-size:11px;color:var(--w-muted)}'
        +'.w-field-reset{margin-left:8px;background:none;border:none;color:var(--w-muted);cursor:pointer;font-size:12px;padding:0 4px}'
        +'.w-field-reset:hover{color:var(--w-primary)}'
        +'.w-field-err{display:none;font-size:11px;color:#ef4444;margin-top:4px}'
        +'.w-field-err.on{display:block}'
        +'.w-form-group.has-error .w-input,.w-form-group.has-error .w-textarea,.w-form-group.has-error .w-rt-edit{border-color:#ef4444}'
        +'.w-rt{border:1px solid var(--w-border);border-radius:8px;overflow:hidden;background:var(--w-bg)}'
        +'.w-rt-bar{display:flex;gap:2px;padding:4px 6px;border-bottom:1px solid var(--w-border);background:var(--w-surface)}'
        +'.w-rt-b{background:none;border:none;min-width:26px;height:26px;border-radius:5px;cursor:pointer;color:var(--w-text);font-size:12px}'
        +'.w-rt-b:hover{background:var(--w-border)}'
        +'.w-rt-edit{min-height:90px;padding:9px 11px;outline:none;font-size:13px;line-height:1.6}'
        +'.w-rt-edit:focus{border-color:var(--w-primary)}'
        +'.w-rt-edit ul,.w-rt-edit ol{margin:6px 0 6px 20px}.w-rt-edit a{color:var(--w-primary)}'
        +'.w-imgf{display:flex;flex-direction:column;gap:8px}'
        +'.w-imgf-prev{border:1px dashed var(--w-border);border-radius:8px;padding:8px;min-height:40px;display:flex;align-items:center;justify-content:center;background:var(--w-bg)}'
        +'.w-lib{border:1px solid var(--w-border);border-radius:8px;padding:8px;background:var(--w-bg);max-height:260px;overflow:auto}'
        +'.w-lib-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(78px,1fr));gap:8px}'
        +'.w-lib-item{padding:0;border:1px solid var(--w-border);border-radius:6px;overflow:hidden;cursor:pointer;background:none;aspect-ratio:1}'
        +'.w-lib-item:hover{border-color:var(--w-primary)}.w-lib-item img{width:100%;height:100%;object-fit:cover;display:block}'
        +'.sec-a11y{margin:12px 0;padding:10px 12px;border:1px solid rgba(245,158,11,.4);background:rgba(245,158,11,.08);border-radius:8px;font-size:12px;color:#b45309}'
        +'.w-field-ai{margin-left:8px;background:none;border:1px solid rgba(124,60,255,.45);color:var(--w-primary);cursor:pointer;font-size:11px;font-weight:600;padding:1px 8px;border-radius:999px}'
        +'.w-field-ai:hover{background:rgba(124,60,255,.1)}'
        +'.w-ai-menu{position:fixed;z-index:100010;min-width:214px;background:var(--w-surface);border:1px solid var(--w-border);border-radius:10px;box-shadow:0 18px 50px rgba(0,0,0,.35);padding:6px;display:flex;flex-direction:column}'
        +'.w-ai-menu-h{font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--w-muted);font-weight:700;padding:6px 8px 4px}'
        +'.w-ai-menu button{text-align:left;background:none;border:none;padding:8px 10px;border-radius:6px;cursor:pointer;color:var(--w-text);font-size:12.5px}'
        +'.w-ai-menu button:hover{background:var(--w-bg)}'
        +'.sec-panel{margin:14px 0;padding:14px;border:1px solid var(--w-border);border-radius:10px;background:var(--w-bg);font-size:13px}'
        +'.sec-panel-h{font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--w-muted);font-weight:700;margin:14px 0 6px}'
        +'.sec-panel-empty{font-size:12px;color:var(--w-muted)}'
        +'.sec-hist-row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:7px 0;border-bottom:1px solid var(--w-border);font-size:12px}'
        +'.sec-hist-row code{font-size:11px;background:var(--w-surface);padding:1px 5px;border-radius:4px}'
        +'.sec-sugg{display:flex;gap:10px;padding:9px 0;border-bottom:1px solid var(--w-border);font-size:12.5px;line-height:1.5}'
        +'.sec-sugg-a{flex-shrink:0;font-size:9.5px;text-transform:uppercase;letter-spacing:.05em;font-weight:700;color:var(--w-primary);border:1px solid rgba(124,60,255,.4);border-radius:999px;padding:2px 8px;height:fit-content}'
        +'.sec-srch-sec{padding:8px 0;border-bottom:1px solid var(--w-border)}'
        +'.sec-srch-hit{cursor:pointer;padding:5px 8px;border-radius:6px;font-size:12px;color:var(--w-text)}'
        +'.sec-srch-hit:hover{background:var(--w-bg)}'
        +'.sec-srch-hit code{font-size:11px;color:var(--w-primary);margin-right:6px}'
        +'</style>';
};

W.renderSectionEditor=async function(id){
    var app=document.getElementById('wontia-app');
    if(!id){app.innerHTML='<div class="w-empty-state"><h3>Sección no encontrada</h3></div>';return}
    app.innerHTML='<div style="text-align:center;padding:60px;color:var(--w-muted)">Cargando sección…</div>';
    var d;
    try{d=await W.api('/api/v1/admin/sections/'+encodeURIComponent(id))}catch(e){d=null}
    if(!d||!d.ok||!d.data){W.notify((d&&d.message)||'Sección no encontrada','error');W.router();return}
    var s=d.data;
    var pageId=s.page_id;
    var pageTitle='';
    var sections=[];
    if(W.state.currentPage&&String(W.state.currentPage.id)===String(pageId)){pageTitle=W.state.currentPage.title||'';sections=W.state.currentPage.sections||[]}
    if(!sections.length){try{var dl=await W.api('/api/v1/admin/pages/'+pageId+'/sections');if(dl&&dl.ok)sections=dl.data||[]}catch(e){}}
    var pos=-1;sections.forEach(function(x,i){if(String(x.id)===String(s.id))pos=i});
    var prev=pos>0?sections[pos-1]:null;
    var next=(pos>=0&&pos<sections.length-1)?sections[pos+1]:null;

    var widgetType=s.widget_type||'';
    var config={};try{config=JSON.parse(s.config||'{}')||{}}catch(e){config={}}
    var schema=[];
    if(widgetType){try{var d2=await W.api('/api/v1/admin/bricks/'+encodeURIComponent(widgetType));schema=(d2.data&&d2.data.configSchema)||[]}catch(e){schema=[]}}
    W.state.editSchema=schema.length?schema:null;
    W.state.editConfig=config;

    var groups={content:true,design:false,advanced:false};
    schema.forEach(function(f){var g=W.fieldGroup(f);if(g==='design')groups.design=true;else if(g==='advanced')groups.advanced=true});

    var html=W.secEditorCss()+'<div class="w-card">';
    html+='<div class="w-flex-between w-mb" style="flex-wrap:wrap;gap:8px"><div>'
        +'<div style="font-size:11px;color:var(--w-muted);margin-bottom:2px">Páginas › '+W.esc(pageTitle||('Página '+pageId))+' › Sección</div>'
        +'<h3 style="margin:0">'+W.esc(W.sectionLabel(s))+'</h3>'
        +'<span style="font-size:11px;color:var(--w-muted)">'+W.esc(widgetType||s.type||'')+' · '+(pos+1)+' de '+sections.length+'</span></div>'
        +'<div class="w-flex w-gap-sm">'
        +(prev?'<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.editSection('+prev.id+')" title="Anterior">← '+(prev.title?W.esc(prev.title):'Anterior')+'</button>':'')
        +(next?'<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.editSection('+next.id+')" title="Siguiente">'+(next.title?W.esc(next.title):'Siguiente')+' →</button>':'')
        +'<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.secAiReview()">🧭 Revisar IA</button>'
        +'<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.secHistory('+s.id+')">🕘 Historial</button>'
        +'<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.secSavePattern('+s.id+')">💾 Patrón</button>'
        +'<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.secDuplicate('+s.id+')">⧉ Duplicar</button>'
        +'<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.secPreviewToggle('+s.id+')">👁 Vista</button>'
        +'<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.exitSectionEditor()">← Volver</button>'
        +'</div></div>';
    html+='<div class="sec-tabs" id="sec-tabs">'
        +'<button type="button" class="sec-tab on" data-tab="content" onclick="wontia.secTab(\'content\')">Contenido</button>'
        +(groups.design?'<button type="button" class="sec-tab" data-tab="design" onclick="wontia.secTab(\'design\')">Diseño</button>':'')
        +(groups.advanced?'<button type="button" class="sec-tab" data-tab="advanced" onclick="wontia.secTab(\'advanced\')">Avanzado</button>':'')
        +'</div>';
    html+='<input type="hidden" id="sec-id" value="'+s.id+'">';
    html+='<input type="hidden" id="sec-page-id" value="'+pageId+'">';
    html+='<input type="hidden" id="sec-widget-type" value="'+W.esc(widgetType)+'">';
    html+='<div id="sec-fields">';
    html+='<div class="w-form-group" data-fgroup="content"><label class="w-label">Título de la sección</label><input class="w-input" id="es-title" value="'+W.esc(s.title||'')+'" maxlength="200"></div>';
    html+='<div class="w-form-group" data-fgroup="content"><label class="w-label">Subtítulo / descripción</label><textarea class="w-textarea" id="es-subtitle">'+W.esc(s.subtitle||'')+'</textarea></div>';
    html+='<div class="w-form-group" data-fgroup="advanced"><label class="w-label" style="display:flex;align-items:center;gap:8px"><input type="checkbox" id="es-active" '+(s.is_active?'checked':'')+' style="width:auto"/> Visible en el sitio</label></div>';
    html+='<div class="w-form-group" data-fgroup="advanced"><label class="w-label">Visibilidad por dispositivo</label><div class="w-flex" style="flex-wrap:wrap;gap:16px">'
        +'<label style="display:flex;align-items:center;gap:6px;font-size:12px"><input type="checkbox" id="secf-_hide_desktop" style="width:auto" '+(config._hide_desktop?'checked':'')+'/> Ocultar en escritorio</label>'
        +'<label style="display:flex;align-items:center;gap:6px;font-size:12px"><input type="checkbox" id="secf-_hide_tablet" style="width:auto" '+(config._hide_tablet?'checked':'')+'/> Ocultar en tablet</label>'
        +'<label style="display:flex;align-items:center;gap:6px;font-size:12px"><input type="checkbox" id="secf-_hide_mobile" style="width:auto" '+(config._hide_mobile?'checked':'')+'/> Ocultar en móvil</label>'
        +'</div></div>';
    if(W.state.editSchema){
        html+='<div class="schema-fields" id="sec-schema-fields">'+W.renderSchemaFields(W.state.editSchema,config)+'</div>';
    }else{
        html+='<div class="w-form-group" data-fgroup="content"><label class="w-label">Contenido (HTML)</label><textarea class="w-textarea" id="es-content" style="min-height:220px;font-family:monospace">'+W.esc(s.content||'')+'</textarea></div>';
    }
    html+='</div>';
    html+='<div id="sec-a11y" class="sec-a11y" style="display:none"></div>';
    html+='<div id="sec-preview-wrap" style="display:none"></div>';
    html+='<div id="sec-extra" style="display:none"></div>';
    html+='<div class="sec-foot"><span id="sec-status" class="sec-status">Listo</span><span class="w-flex w-gap-sm"><button class="w-btn w-btn-secondary" onclick="wontia.exitSectionEditor()">Volver</button><button class="w-btn w-btn-primary" onclick="wontia.saveSectionEditor()">Guardar</button></span></div>';
    html+='</div>';
    app.innerHTML=html;

    W.state._secList=sections;W.state._curSecId=s.id;
    if(W.state.editSchema)W.repInit(W.state.editSchema);
    W.secTab('content');
    W.initAutosave();
    if(W.secLinkInit)W.secLinkInit();
    W.updateA11y();
    var ff=null;try{ff=sessionStorage.getItem('wwi_focus_field');if(ff)sessionStorage.removeItem('wwi_focus_field')}catch(e){}
    if(ff){
        var base=String(ff).split('[')[0].split('.')[0];
        var inp=document.getElementById('secf-'+base)||document.getElementById('es-'+base);
        var wrap=document.querySelector('[data-field="'+base+'"]');
        if(wrap)wrap.scrollIntoView({block:'center'});
        var target=inp||(wrap?wrap.querySelector('input,textarea,[contenteditable]'):null);
        if(target&&target.focus)setTimeout(function(){try{target.focus()}catch(e){}},80);
    }else{
        var t=document.getElementById('es-title');if(t)t.focus();
    }
};

W.fieldValue=function(key){
    var el=document.getElementById('secf-'+key);if(!el)return '';
    if(el.type==='checkbox')return el.checked?1:0;
    if(el.getAttribute('contenteditable')==='true')return W.sanitizeRich(el.innerHTML||'');
    if(el.type==='color'){var tx=document.getElementById('secf-'+key+'-txt');return tx?tx.value:el.value}
    return el.value;
};
W.condOk=function(el){
    var raw=el.getAttribute('data-showif');if(!raw)return true;
    var c;try{c=JSON.parse(raw)}catch(e){return true}
    var v=W.fieldValue(c.key);
    if(c.equals!==undefined)return String(v)===String(c.equals);
    if(c.not!==undefined)return String(v)!==String(c.not);
    if(c.in!==undefined&&Array.isArray(c.in))return c.in.map(String).indexOf(String(v))>-1;
    if(c.notEmpty)return String(v).trim()!=='';
    return true;
};
W.refreshFieldVisibility=function(){
    var tab=W.state.secTab||'content';
    document.querySelectorAll('#sec-fields [data-fgroup]').forEach(function(el){
        var g=(el.getAttribute('data-fgroup')===tab);
        el.style.display=(g&&W.condOk(el))?'':'none';
    });
};
W.secTab=function(tab){
    W.state.secTab=tab;
    document.querySelectorAll('#sec-tabs .sec-tab').forEach(function(b){b.classList.toggle('on',b.getAttribute('data-tab')===tab)});
    W.refreshFieldVisibility();
};
W.updateA11y=function(){
    var note=document.getElementById('sec-a11y');if(!note)return;
    var hasTag=(W.state.editSchema||[]).some(function(f){return f.key==='title_tag'});
    if(!hasTag){note.style.display='none';return}
    var myTag=W.fieldValue('title_tag')||'h1';
    var others=[];
    (W.state._secList||[]).forEach(function(x){
        if(String(x.id)===String(W.state._curSecId))return;
        var c={};try{c=JSON.parse(x.config||'{}')||{}}catch(e){}
        var tag=c.title_tag||((String(x.widget_type||'').indexOf('hero')>-1)?'h1':'');
        if(String(myTag)==='h1'&&tag==='h1')others.push(x.title||x.widget_type||('#'+x.id));
    });
    if(String(myTag)==='h1'&&others.length){note.style.display='block';note.innerHTML='⚠ Hay '+others.length+' sección(es) más con H1 en esta página ('+others.map(W.esc).join(', ')+'). Para SEO conviene un solo H1.';}
    else{note.style.display='none';note.innerHTML=''}
};

W.setStatus=function(txt,cls){var el=document.getElementById('sec-status');if(!el)return;el.textContent=txt;el.className='sec-status'+(cls?' '+cls:'')};

W.markDirty=function(){
    W._dirty=true;W.setStatus('Sin guardar','dirty');
    if(W._autoT)clearTimeout(W._autoT);
    W._autoT=setTimeout(function(){W.saveSectionEditor(true)},1500);
};

W.captureEditor=function(){
    var a=document.getElementById('es-active');
    var dev={};
    ['_hide_desktop','_hide_tablet','_hide_mobile'].forEach(function(k){var c=document.getElementById('secf-'+k);if(c&&c.checked)dev[k]=1});
    return {title:W.val('es-title'),subtitle:W.val('es-subtitle'),active:(a&&a.checked)?1:0,
        config:W.state.editSchema?W.collectSchemaFields(W.state.editSchema):null,
        content:W.val('es-content'),device:dev};
};
W.applySnapshot=function(s){
    if(!s)return;W._applying=true;
    var t=document.getElementById('es-title');if(t)t.value=s.title||'';
    var su=document.getElementById('es-subtitle');if(su)su.value=s.subtitle||'';
    var a=document.getElementById('es-active');if(a)a.checked=!!s.active;
    ['_hide_desktop','_hide_tablet','_hide_mobile'].forEach(function(k){var c=document.getElementById('secf-'+k);if(c)c.checked=!!(s.device&&s.device[k])});
    if(W.state.editSchema){
        W.state.editConfig=s.config||{};
        var box=document.getElementById('sec-schema-fields');
        if(box){box.innerHTML=W.renderSchemaFields(W.state.editSchema,W.state.editConfig);W.repInit(W.state.editSchema);if(W.secLinkInit)W.secLinkInit()}
    }else{var c=document.getElementById('es-content');if(c)c.value=s.content||''}
    W.refreshFieldVisibility();W.updateA11y();W.markDirty();
    W._applying=false;
};
W.pushUndo=function(){
    if(W._applying)return;
    var snap=W.captureEditor();
    var last=W._undo[W._undo.length-1];
    try{if(last&&JSON.stringify(last)===JSON.stringify(snap))return}catch(e){}
    W._undo.push(snap);if(W._undo.length>40)W._undo.shift();W._redo=[];
};
W.undoEditor=function(){
    if(!W._undo.length){W.notify('Nada que deshacer','info');return}
    W._redo.push(W.captureEditor());
    W.applySnapshot(W._undo.pop());
    W.notify('Deshecho','info');
};
W.redoEditor=function(){
    if(!W._redo.length){W.notify('Nada que rehacer','info');return}
    W._undo.push(W.captureEditor());
    W.applySnapshot(W._redo.pop());
    W.notify('Rehecho','info');
};
W.secPreviewToggle=function(id){
    var box=document.getElementById('sec-preview-wrap');if(!box)return;
    if(box.style.display!=='none'){box.style.display='none';box.innerHTML='';return}
    box.style.display='block';
    box.innerHTML='<div class="w-card" style="padding:12px"><div class="w-flex-between w-mb"><b style="font-size:13px">Vista previa (aproximada)</b><span class="w-flex w-gap-sm"><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.secPreviewLoad('+id+')">Actualizar</button><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.secPreviewToggle('+id+')">Cerrar</button></span></div><div id="sec-preview-frame"></div></div>';
    W.secPreviewLoad(id);
};
W.secPreviewLoad=async function(id){
    var f=document.getElementById('sec-preview-frame');if(!f)return;
    f.innerHTML='<div style="font-size:12px;color:var(--w-muted);padding:8px">Cargando…</div>';
    try{
        var d=await W.api('/api/v1/admin/sections/'+id+'/render');
        var html=(d&&d.data&&d.data.html)||'';
        var doc='<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;font-family:system-ui,sans-serif">'+html+'</body></html>';
        var srcdoc=doc.replace(/&/g,'&amp;').replace(/"/g,'&quot;');
        f.innerHTML='<iframe title="Vista previa" style="width:100%;height:420px;border:1px solid var(--w-border);border-radius:8px;background:#fff" srcdoc="'+srcdoc+'"></iframe>';
    }catch(e){f.innerHTML='<div style="font-size:12px;color:#ef4444;padding:8px">No se pudo cargar la vista previa.</div>'}
};

W.initAutosave=function(){
    var box=document.getElementById('sec-fields');if(!box)return;
    W._dirty=false;W._undo=[];W._redo=[];W._applying=false;W.setStatus('Listo');
    var on=function(e){if(e&&e.target&&e.target.id==='sec-status')return;W.pushUndo();W.markDirty();if(W.refreshFieldVisibility)W.refreshFieldVisibility();if(W.updateA11y)W.updateA11y()};
    box.addEventListener('input',on);
    box.addEventListener('change',on);
    box.addEventListener('blur',function(){W.validateFields()},true);
    box.addEventListener('keydown',function(e){
        var k=(e.key||'').toLowerCase();
        if((e.ctrlKey||e.metaKey)&&k==='z'&&!e.shiftKey){e.preventDefault();W.undoEditor()}
        else if((e.ctrlKey||e.metaKey)&&(k==='y'||(k==='z'&&e.shiftKey))){e.preventDefault();W.redoEditor()}
    });
};

W.exitSectionEditor=function(){
    if(W._dirty&&!window.confirm('Tienes cambios sin guardar. ¿Salir de todos modos?'))return;
    W._dirty=false;
    W.router();
};

W.saveSectionEditor=async function(silent){
    var idEl=document.getElementById('sec-id');if(!idEl)return null;
    var id=idEl.value;
    if(W._saving)return null;
    if(W.state.editSchema&&!W.validateFields()){W.setStatus('Revisa los campos marcados','error');if(!silent)W.notify('Hay campos con errores','error');return null}
    W._saving=true;
    if(!silent)W.setStatus('Guardando…','saving');
    var activeEl=document.getElementById('es-active');
    var data={title:W.val('es-title'),subtitle:W.val('es-subtitle'),is_active:activeEl&&activeEl.checked?1:0};
    if(W.state.editSchema)data.config=W.collectSchemaFields(W.state.editSchema);
    else data.content=W.val('es-content');
    var dev={};
    ['_hide_desktop','_hide_tablet','_hide_mobile'].forEach(function(k){var cb=document.getElementById('secf-'+k);if(cb&&cb.checked)dev[k]=1});
    if(data.config||Object.keys(dev).length)data.config=Object.assign({},data.config||{},dev);
    var r=null;try{r=await W.api('/api/v1/admin/sections/'+id,{method:'PUT',body:data})}catch(e){r=null}
    W._saving=false;
    if(r&&r.ok){
        W._dirty=false;W.setStatus('Guardado','saved');
        if(!silent)W.notify('Sección guardada','success');
        var pid=document.getElementById('sec-page-id');
        if(W.state.currentPage&&pid&&String(W.state.currentPage.id)===String(pid.value)){
            (W.state.currentPage.sections||[]).forEach(function(x){
                if(String(x.id)===String(id)){x.title=data.title;x.subtitle=data.subtitle;x.is_active=data.is_active;try{x.config=JSON.stringify(data.config||{})}catch(e){}}
            });
        }
    }else{
        W.setStatus('Error al guardar','error');
        if(!silent)W.notify((r&&r.message)||'No se pudo guardar','error');
    }
    return r;
};

W.renderSectionManager=function(pageId,containerId){
    var app=document.getElementById(containerId||'wontia-app');
    if(!app)return;
    app.innerHTML='<div id="section-panel"></div>';
    W.loadSections(pageId);
};

W.sectionLabel=function(s){
    if(s.widget_type){
        var n=String(s.widget_type).replace(/^wwi-/,'').replace(/([a-z])([A-Z])/g,'$1 $2').replace(/-/g,' ');
        return n.charAt(0).toUpperCase()+n.slice(1);
    }
    return s.title||'Sección';
};

W.loadSections=async function(pageId){
    var el=document.getElementById('section-panel');
    if(!el)return;
    if(!pageId){
        var pd=await W.api('/api/v1/admin/pages');
        var pages=pd.data||[];
        var html='<div class="w-card" style="padding:14px 18px;margin-bottom:14px"><div style="font-size:13px;font-weight:700">Secciones por página</div><div style="font-size:11px;color:var(--w-muted);margin-top:4px;line-height:1.6">Elige una página para gestionar sus secciones: <strong>arrastra</strong> para reordenar, añade bricks o edítalos.</div></div>';
        if(!pages.length){
            html+='<div class="w-empty-state"><h3>Sin páginas</h3><p>Crea tu primera página en la pestaña Páginas.</p><button class="w-btn w-btn-primary" onclick="wontia.pagesGo(\'lista\')">Ir a Páginas</button></div>';
        }else{
            html+='<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px">';
            pages.forEach(function(p){
                html+='<div class="w-card" style="padding:16px"><div style="font-size:13px;font-weight:600">'+W.esc(p.title)+'</div><div style="font-size:11px;color:var(--w-muted);margin-top:2px">/'+W.esc(p.slug)+' · '+p.section_count+' secciones</div><div style="margin-top:10px"><a class="w-btn w-btn-primary w-btn-sm" href="#pages/secciones/'+p.id+'">Gestionar secciones</a></div></div>';
            });
            html+='</div>';
        }
        el.innerHTML=html;
        return;
    }
    var d=await W.api('/api/v1/admin/pages/'+pageId);
    if(!d.ok||!d.data){
        el.innerHTML='<div class="w-empty-state"><h3>Page not found</h3><p>La página no existe en este sitio.</p><button class="w-btn w-btn-primary" onclick="wontia.pagesGo(\'secciones\')">Elegir otra página</button></div>';
        return;
    }
    W.state.currentPage=d.data;
    if(!W.state.currentPage.sections)W.state.currentPage.sections=[];
    var html='<div class="w-flex-between w-mb-lg"><div><h3 style="font-size:15px">Secciones: '+W.esc(W.state.currentPage.title)+'</h3><span style="font-size:11px;color:var(--w-muted)">'+W.state.currentPage.sections.length+' secciones · arrastra para reordenar</span></div><div class="w-flex w-gap-sm"><button class="w-btn w-btn-secondary" onclick="wontia.showPatterns('+pageId+')">Patrones</button><button class="w-btn w-btn-primary" onclick="wontia.showSectionTypePicker('+pageId+')">+ Añadir sección</button><button class="w-btn w-btn-secondary" onclick="wontia.pagesGo(\'secciones\')">Cambiar página</button></div></div>';
    html+='<div class="w-flex w-gap-sm w-mb" style="flex-wrap:wrap"><input class="w-input" id="sec-search" placeholder="Buscar en el contenido de la página…" style="max-width:340px" oninput="wontia.secSearch('+pageId+')"/><button class="w-btn w-btn-secondary" onclick="wontia.showTrash()">🗑 Papelera</button></div><div id="sec-search-results"></div><div id="sec-trash" style="display:none"></div><div id="sec-patterns" style="display:none"></div>';
    if(!W.state.currentPage.sections.length){
        html+='<div class="w-empty-state"><h3>Sin secciones</h3><p>Añade la primera sección a esta página</p></div>';
    }else{
        W.state.currentPage.sections.forEach(function(s,i){
            html+='<div class="w-card w-mb" draggable="true" data-sid="'+s.id+'" style="cursor:grab">';
            html+='<div class="w-flex-between w-mb"><div class="w-flex w-gap-sm"><span class="w-drag-handle" title="Arrastra para reordenar">⠿</span><span style="font-size:10px;color:var(--w-muted);background:var(--w-bg);padding:2px 8px;border-radius:4px">'+W.esc(W.sectionLabel(s))+'</span><strong style="font-size:13px">'+W.esc(s.title||'Sin título')+'</strong>'+(s.is_active==1||s.is_active==='1'?'':'<span class="w-brick-chip" style="color:#fbbf24">oculta</span>')+'</div><div class="w-flex w-gap-sm"><button class="w-btn w-btn-secondary w-btn-sm" title="Subir" onclick="wontia.moveSection(this,'+s.id+',-1)">▲</button><button class="w-btn w-btn-secondary w-btn-sm" title="Bajar" onclick="wontia.moveSection(this,'+s.id+',1)">▼</button><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.editSection('+s.id+')">Editar</button><button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.deleteSection('+s.id+','+pageId+')">Eliminar</button></div></div>';
            if(s.subtitle)html+='<p style="font-size:12px;color:var(--w-muted);margin-bottom:6px">'+W.esc(s.subtitle)+'</p>';
            html+='</div>';
        });
    }
    el.innerHTML=html;
    W.initSectionDnD(el);
};

W.initSectionDnD=function(container){
    var dragEl=null;
    container.querySelectorAll('[data-sid]').forEach(function(card){
        card.addEventListener('dragstart',function(e){
            dragEl=card;
            card.classList.add('w-dragging');
            try{e.dataTransfer.effectAllowed='move';e.dataTransfer.setData('text/plain',card.dataset.sid)}catch(err){}
        });
        card.addEventListener('dragend',function(){
            card.classList.remove('w-dragging');
            container.querySelectorAll('.w-drag-over').forEach(function(x){x.classList.remove('w-drag-over')});
            if(dragEl)W.saveSectionOrder(container);
            dragEl=null;
        });
        card.addEventListener('dragover',function(e){
            e.preventDefault();
            if(!dragEl||dragEl===card)return;
            var rect=card.getBoundingClientRect();
            var after=(e.clientY-rect.top)>rect.height/2;
            card.classList.add('w-drag-over');
            container.insertBefore(dragEl,after?card.nextSibling:card);
        });
        card.addEventListener('dragleave',function(){card.classList.remove('w-drag-over')});
    });
};

W.saveSectionOrder=async function(container){
    var cards=container.querySelectorAll('[data-sid]');
    if(!cards.length)return;
    var items=[];
    cards.forEach(function(c,i){items.push({id:parseInt(c.dataset.sid,10),sort_order:i})});
    var r=await W.api('/api/v1/admin/sections/reorder',{method:'PUT',body:{items:items}});
    if(r.ok){
        W.notify('Orden guardado','success');
        if(W.state.currentPage&&W.state.currentPage.sections){
            var byId={};
            W.state.currentPage.sections.forEach(function(s){byId[s.id]=s});
            W.state.currentPage.sections=items.map(function(it){return byId[it.id]}).filter(Boolean);
        }
    }else W.notify(r.message||'No se pudo guardar el orden','error');
};

W.moveSection=function(btn,sid,dir){
    var card=btn.closest('[data-sid]');
    if(!card)return;
    var container=card.parentElement;
    if(dir<0&&card.previousElementSibling)container.insertBefore(card,card.previousElementSibling);
    else if(dir>0&&card.nextElementSibling)container.insertBefore(card.nextElementSibling,card);
    else return;
    W.saveSectionOrder(container);
};

W.showSectionTypePicker=async function(pageId){
    var d=await W.api('/api/v1/admin/bricks');
    var bricks=d.data||{};
    var cats={};
    for(var id in bricks){var b=bricks[id];var c=b.category||'general';(cats[c]=cats[c]||[]).push({id:id,name:b.name});}
    var html='<div style="font-size:11px;color:var(--w-muted);margin-bottom:10px">Elige el tipo de sección para esta página. Cada opción es un BRICK funcional del sistema.</div>';
    for(var c in cats){
        html+='<div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--w-muted);margin:12px 0 6px">'+W.esc(c)+'</div><div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">';
        cats[c].forEach(function(b){html+='<button class="w-btn w-btn-secondary" onclick="wontia.addSection('+pageId+',\''+W.esc(b.id)+'\',\''+W.esc(b.name)+'\')">'+W.esc(b.name)+'</button>'});
        html+='</div>';
    }
    W.modal('Añadir sección',html);
};

W.addSection=async function(pageId,widgetId,widgetName){
    W.closeModal();
    var r=await W.api('/api/v1/admin/pages/'+pageId+'/sections',{method:'POST',body:{type:'widget',widget_type:widgetId,title:widgetName||widgetId,config:{}}});
    if(r.ok){W.loadSections(pageId);W.notify('Sección añadida','success')}
    else if(r.message)W.notify(r.message,'error');
};

W.fieldGroup=function(f){return (f&&f.group)||'content'};

W.renderSchemaFields=function(schema,values){
    values=values||{};
    var html='';
    function group(f,inner){
        var lbl='<label class="w-label">'+W.esc(f.label||f.key)+(f.hint?' <span style="font-size:9px;font-weight:400;color:var(--w-muted)">'+W.esc(f.hint)+'</span>':'');
        if(['text','textarea','richtext','url'].indexOf(f.type||'text')>-1)lbl+='<button type="button" class="w-field-ai" title="Asistente IA" onclick="wontia.aiMenu(\''+W.esc(f.key)+'\',this)">✨ IA</button>';
        if(f.default!==undefined)lbl+='<button type="button" class="w-field-reset" title="Restaurar valor por defecto" onclick="wontia.fieldReset(\''+W.esc(f.key)+'\')">↺</button>';
        lbl+='</label>';
        var si=f.showIf?' data-showif="'+W.esc(JSON.stringify(f.showIf))+'"':'';
        return '<div class="w-form-group" data-field="'+W.esc(f.key)+'" data-fgroup="'+W.fieldGroup(f)+'"'+si+'>'+lbl+inner+'<span class="w-field-err" id="secf-'+W.esc(f.key)+'-err"></span></div>';
    }
    function head(f){
        var si=f.showIf?' data-showif="'+W.esc(JSON.stringify(f.showIf))+'"':'';
        return '<div class="sec-fhead" data-fgroup="'+W.fieldGroup(f)+'"'+si+' style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--w-muted);font-weight:700;margin:20px 0 6px;padding-top:12px;border-top:1px solid var(--w-border)">'+W.esc(f.label)+'</div>';
    }
    function selectHtml(f,v){
        var opts=f.options||{};
        var o='';
        if(Array.isArray(opts)){opts.forEach(function(it){var key=it.value!==undefined?it.value:it;var lbl=it.label!==undefined?it.label:key;o+='<option value="'+W.esc(key)+'"'+(String(v)===String(key)?' selected':'')+'>'+W.esc(lbl)+'</option>'})}
        else{for(var k in opts)o+='<option value="'+W.esc(k)+'"'+(String(v)===String(k)?' selected':'')+'>'+W.esc(opts[k])+'</option>'}
        return '<select class="w-select" id="secf-'+W.esc(f.key)+'">'+o+'</select>';
    }
    (schema||[]).forEach(function(f){
        var v=(values[f.key]!==undefined&&values[f.key]!==null&&values[f.key]!=='')?values[f.key]:(f.default!==undefined?f.default:'');
        var vid='secf-'+W.esc(f.key);
        var t=f.type||'text';
        if(t==='heading'){html+=head(f);return}
        if(t==='text'||t==='url')html+=group(f,'<input class="w-input" data-vtype="'+t+'" id="'+vid+'" value="'+W.esc(v)+'"/>');
        else if(t==='textarea')html+=group(f,'<textarea class="w-textarea" data-vtype="textarea" id="'+vid+'" style="min-height:90px">'+W.esc(v)+'</textarea>');
        else if(t==='richtext')html+=group(f,W.renderRichField(f,v));
        else if(t==='image')html+=group(f,W.renderImageField(f,v));
        else if(t==='link')html+=group(f,W.renderLinkField(f,v));
        else if(t==='number')html+=group(f,'<input class="w-input" type="number" id="'+vid+'" value="'+W.esc(v)+'"'+(f.min!==undefined?' min="'+f.min+'"':'')+(f.max!==undefined?' max="'+f.max+'"':'')+(f.step!==undefined?' step="'+f.step+'"':'')+'/>');
        else if(t==='range'){
            var min=f.min!==undefined?f.min:0,max=f.max!==undefined?f.max:100,step=f.step!==undefined?f.step:1;
            html+=group(f,'<div class="w-flex" style="align-items:center;gap:10px"><input type="range" id="'+vid+'" min="'+min+'" max="'+max+'" step="'+step+'" value="'+W.esc(v)+'" style="flex:1" oninput="document.getElementById(\''+vid+'-out\').textContent=this.value"/><output id="'+vid+'-out" style="min-width:44px;text-align:right;font-size:12px;font-weight:700">'+W.esc(v)+'</output></div>');
        }
        else if(t==='color')html+=group(f,'<div class="w-flex" style="align-items:center;gap:10px"><input type="color" id="'+vid+'" value="'+W.esc(v||'#7c3cff')+'" style="width:48px;height:34px;border:1px solid var(--w-border);border-radius:6px;background:none;cursor:pointer" oninput="var t=document.getElementById(\''+vid+'-txt\');if(t)t.value=this.value"/><input class="w-input" id="'+vid+'-txt" value="'+W.esc(v)+'" style="flex:1" placeholder="#7c3cff" oninput="var c=document.getElementById(\''+vid+'\');if(c&&/^#[0-9a-fA-F]{6}$/.test(this.value))c.value=this.value"/></div>');
        else if(t==='toggle')html+=group(f,'<label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox" id="'+vid+'" '+(v?'checked':'')+' style="width:auto"/> '+W.esc(f.on||'Sí')+'</label>');
        else if(t==='hlevel')html+=group(f,selectHtml({key:f.key,options:(f.options&&(Array.isArray(f.options)||Object.keys(f.options).length)?f.options:{h1:'H1 — grande',h2:'H2 — mediano',h3:'H3 — pequeño',h4:'H4'})},v));
        else if(t==='select')html+=group(f,selectHtml(f,v));
        else if(t==='repeater'){
            var arr=v;
            if(typeof arr==='string'){try{arr=JSON.parse(arr)||[]}catch(e){arr=[]}}
            html+=group(f,W.renderRepeaterField(f,Array.isArray(arr)?arr:[]));
        }
        else html+=group(f,'<textarea class="w-textarea" id="'+vid+'" style="min-height:80px;font-family:monospace;font-size:11px">'+W.esc(typeof v==='object'?JSON.stringify(v,null,2):v)+'</textarea>');
    });
    return html;
};

W.sanitizeRich=function(html){
    html=String(html||'');
    var allowed={B:1,STRONG:1,I:1,EM:1,U:1,A:1,UL:1,OL:1,LI:1,BR:1,P:1,SPAN:1};
    var div=document.createElement('div');
    div.innerHTML=html;
    (function walk(node){
        Array.prototype.slice.call(node.childNodes).forEach(function(ch){
            if(ch.nodeType!==1)return;
            if(!allowed[ch.tagName]){
                var parent=ch.parentNode;
                while(ch.firstChild)parent.insertBefore(ch.firstChild,ch);
                parent.removeChild(ch);
                return;
            }
            Array.prototype.slice.call(ch.attributes).forEach(function(at){
                var n=at.name.toLowerCase();
                if(n.indexOf('on')===0||n==='style'||(ch.tagName==='A'&&n!=='href'))ch.removeAttribute(at.name);
            });
            if(ch.tagName==='A'&&/^\s*javascript:/i.test(ch.getAttribute('href')||''))ch.removeAttribute('href');
            walk(ch);
        });
    })(div);
    return div.innerHTML;
};

W.rtCmdId=function(id,cmd){var el=document.getElementById(id);if(!el)return;el.focus();try{document.execCommand(cmd,false,null)}catch(e){}if(W.markDirty)W.markDirty()};
W.rtLinkId=function(id){var el=document.getElementById(id);if(!el)return;var url=window.prompt('URL del enlace:','https://');if(!url)return;el.focus();try{document.execCommand('createLink',false,url)}catch(e){}if(W.markDirty)W.markDirty()};
W.rtCmd=function(key,cmd){W.rtCmdId('secf-'+key,cmd)};
W.rtLink=function(key){W.rtLinkId('secf-'+key)};

W.renderRichField=function(f,v){
    var key=W.esc(f.key);
    var safe=W.sanitizeRich(v);
    return '<div class="w-rt"><div class="w-rt-bar">'
        +'<button type="button" class="w-rt-b" title="Negrita" onclick="wontia.rtCmd(\''+W.esc(f.key)+'\',\'bold\')"><b>B</b></button>'
        +'<button type="button" class="w-rt-b" title="Cursiva" onclick="wontia.rtCmd(\''+W.esc(f.key)+'\',\'italic\')"><i>I</i></button>'
        +'<button type="button" class="w-rt-b" title="Subrayado" onclick="wontia.rtCmd(\''+W.esc(f.key)+'\',\'underline\')"><u>U</u></button>'
        +'<button type="button" class="w-rt-b" title="Lista" onclick="wontia.rtCmd(\''+W.esc(f.key)+'\',\'insertUnorderedList\')">•</button>'
        +'<button type="button" class="w-rt-b" title="Lista numerada" onclick="wontia.rtCmd(\''+W.esc(f.key)+'\',\'insertOrderedList\')">1.</button>'
        +'<button type="button" class="w-rt-b" title="Enlace" onclick="wontia.rtLink(\''+W.esc(f.key)+'\')">🔗</button>'
        +'</div><div class="w-rt-edit" data-vtype="richtext" id="secf-'+key+'" contenteditable="true">'+safe+'</div></div>';
};

W.renderImageField=function(f,v){
    var key=W.esc(f.key);
    var prev=v?'<img src="'+W.esc(v)+'" alt="" style="max-width:100%;max-height:120px;border-radius:6px;display:block"/>':'<span style="font-size:11px;color:var(--w-muted)">Sin imagen</span>';
    return '<div class="w-imgf"><div class="w-imgf-prev" id="secf-'+key+'-prevwrap">'+prev+'</div>'
        +'<div class="w-flex w-gap-sm" style="align-items:center"><input class="w-input" data-vtype="url" id="secf-'+key+'" value="'+W.esc(v)+'" placeholder="https://… o /assets/uploads/…" oninput="wontia.imgPrev(\''+W.esc(f.key)+'\')"/>'
        +'<button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.libToggle(\''+W.esc(f.key)+'\')">Biblioteca</button></div>'
        +(f.alt?'<input class="w-input" id="secf-'+key+'-alt" value="'+W.esc(f.altValue||'')+'" placeholder="Texto alternativo (alt)" style="margin-top:6px"/>':'')
        +'<div class="w-lib" id="secf-'+key+'-lib" style="display:none"></div></div>';
};

W.renderLinkField=function(f,v){
    var key=W.esc(f.key);
    return '<div class="w-flex w-gap-sm" style="align-items:center"><input class="w-input" data-vtype="url" id="secf-'+key+'" value="'+W.esc(v)+'" placeholder="https://… · /pagina · #ancla"/>'
        +'<select class="w-select" data-lnkpages="'+key+'" onchange="wontia.linkPick(\''+W.esc(f.key)+'\',this.value)" style="max-width:180px"><option value="">— Página —</option></select></div>';
};

W.imgPrev=function(key){var el=document.getElementById('secf-'+key);var w=document.getElementById('secf-'+key+'-prevwrap');if(!el||!w)return;var u=el.value;w.innerHTML=u?'<img src="'+W.esc(u)+'" alt="" style="max-width:100%;max-height:120px;border-radius:6px;display:block"/>':'<span style="font-size:11px;color:var(--w-muted)">Sin imagen</span>'};

W.libToggle=function(key){
    W.openMediaExplorer({type:'image',title:'Elegir imagen',onSelect:function(m){W.libPick(key,m.url)}});
};
W.libPick=function(key,url){var el=document.getElementById('secf-'+key);if(el)el.value=url;W.imgPrev(key);var panel=document.getElementById('secf-'+key+'-lib');if(panel)panel.style.display='none';if(W.markDirty)W.markDirty()};

W.linkPick=function(key,val){if(!val)return;var el=document.getElementById('secf-'+key);if(el){el.value=val;if(W.markDirty)W.markDirty()}};

W.secLinkInit=async function(){
    var sels=document.querySelectorAll('[data-lnkpages]');if(!sels.length)return;
    var pages=W.state.pages||[];
    if(!pages.length){try{var d=await W.api('/api/v1/admin/pages');pages=(d&&d.data)||[];W.state.pages=pages}catch(e){pages=[]}}
    sels.forEach(function(sel){
        var cur=document.getElementById('secf-'+sel.getAttribute('data-lnkpages'));
        pages.forEach(function(p){
            var opt=document.createElement('option');opt.value='/'+p.slug;opt.textContent=p.title||p.slug;
            if(cur&&cur.value===opt.value)opt.selected=true;
            sel.appendChild(opt);
        });
    });
};

W.fieldError=function(key,msg){
    var el=document.getElementById('secf-'+key+'-err');
    if(el){el.textContent=msg||'';el.className='w-field-err'+(msg?' on':'')}
    var wrap=document.querySelector('[data-field="'+key+'"]');
    if(wrap)wrap.classList.toggle('has-error',!!msg);
};

W.validateFields=function(){
    var ok=true,schema=W.state.editSchema||[];
    schema.forEach(function(f){
        var t=f.type||'text',key=f.key,el=document.getElementById('secf-'+key);
        var msg='';
        if(t==='url'||t==='link'){
            var val=el?String(el.value).trim():'';
            if(val&&!/^(https?:\/\/|\/|#|mailto:|tel:)/i.test(val))msg='URL no válida (usa https://, /ruta, #ancla, mailto: o tel:)';
        }else if(t==='color'){
            var tx=document.getElementById('secf-'+key+'-txt');var cv=tx?tx.value.trim():'';
            if(cv&&!/^#[0-9a-fA-F]{6}$/.test(cv))msg='Color no válido (#RRGGBB)';
        }else if(t==='number'){
            var nv=el?String(el.value).trim():'';
            if(nv!==''&&isNaN(Number(nv)))msg='Debe ser un número';
            else if(nv!==''&&f.min!==undefined&&Number(nv)<f.min)msg='Mínimo '+f.min;
            else if(nv!==''&&f.max!==undefined&&Number(nv)>f.max)msg='Máximo '+f.max;
        }else if(t==='image'){
            var iv=el?String(el.value).trim():'';
            var alt=document.getElementById('secf-'+key+'-alt');
            if(f.alt&&iv&&alt&&alt.value.trim()==='')msg='Añade un texto alternativo (alt)';
        }
        W.fieldError(key,msg);
        if(msg)ok=false;
    });
    return ok;
};

W.fieldReset=function(key){
    var f=(W.state.editSchema||[]).filter(function(x){return String(x.key)===String(key)})[0];
    if(!f)return;
    var def=(f.default!==undefined?f.default:'');
    var t=f.type||'text';
    if(t==='repeater'){if(W.repReset)W.repReset(key);}
    else if(t==='richtext'){var el=document.getElementById('secf-'+key);if(el)el.innerHTML=W.sanitizeRich(def)}
    else if(t==='toggle'){var el=document.getElementById('secf-'+key);if(el)el.checked=!!def}
    else if(t==='color'){var c=document.getElementById('secf-'+key),tx=document.getElementById('secf-'+key+'-txt');if(c&&/^#[0-9a-fA-F]{6}$/.test(def))c.value=def;if(tx)tx.value=def}
    else{var el=document.getElementById('secf-'+key);if(el)el.value=def}
    if(W.markDirty)W.markDirty();
};

W.secSavePattern=async function(id){
    var name=window.prompt('Nombre del patrón (para reutilizar esta sección):','');
    if(name===null)return;
    var r=await W.api('/api/v1/admin/sections/'+id+'/save-pattern',{method:'POST',body:{name:name||''}});
    if(r&&r.ok)W.notify('Patrón guardado','success');else W.notify((r&&r.message)||'Error','error');
};
W.showPatterns=function(pageId){
    var box=document.getElementById('sec-patterns');if(!box)return;
    if(box.style.display!=='none'){box.style.display='none';box.innerHTML='';return}
    box.style.display='block';W.patternsLoad(pageId);
};
W.patternsLoad=async function(pageId){
    var box=document.getElementById('sec-patterns');if(!box)return;
    box.innerHTML='<div class="w-card" style="padding:14px;font-size:12px;color:var(--w-muted)">Cargando patrones…</div>';
    try{
        var d=await W.api('/api/v1/admin/patterns');
        var list=(d&&d.data)||[];
        var h='<div class="w-card" style="padding:14px"><div class="w-flex-between w-mb"><b style="font-size:13px">Patrones reutilizables</b><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.showPatterns('+pageId+')">Cerrar</button></div>';
        if(!list.length)h+='<div style="font-size:12px;color:var(--w-muted)">Aún no hay patrones. Abre una sección y usa "💾 Patrón".</div>';
        else h+=list.map(function(p){return '<div class="w-flex-between" style="padding:8px 0;border-bottom:1px solid var(--w-border)"><span style="font-size:12.5px">'+W.esc(p.name)+' <span style="color:var(--w-muted);font-size:11px">· '+W.esc(p.widget_type||'')+'</span></span><span class="w-flex w-gap-sm"><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.insertPattern('+p.id+','+pageId+')">Insertar</button><button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.deletePattern('+p.id+','+pageId+')">✕</button></span></div>'}).join('');
        h+='</div>';box.innerHTML=h;
    }catch(e){box.innerHTML='<div class="w-card" style="color:#ef4444;padding:14px">Error cargando patrones.</div>'}
};
W.insertPattern=async function(patternId,pageId){
    var r=await W.api('/api/v1/admin/patterns/'+patternId+'/insert',{method:'POST',body:{page_id:pageId}});
    if(r&&r.ok){W.notify('Patrón insertado','success');await W.loadSections(pageId);W.showPatterns(pageId)}else W.notify((r&&r.message)||'Error','error');
};
W.deletePattern=async function(patternId,pageId){
    var r=await W.api('/api/v1/admin/patterns/'+patternId,{method:'DELETE'});
    if(r&&r.ok){W.notify('Patrón eliminado','success');W.patternsLoad(pageId)}else W.notify((r&&r.message)||'Error','error');
};
W.secSearch=function(pageId){if(W._searchT)clearTimeout(W._searchT);W._searchT=setTimeout(function(){W.secSearchRun(pageId)},300)};
W.secSearchRun=async function(pageId){
    var el=document.getElementById('sec-search'),box=document.getElementById('sec-search-results');
    if(!el||!box)return;
    var q=el.value.trim();
    if(q.length<2){box.innerHTML='';return}
    box.innerHTML='<div style="font-size:12px;color:var(--w-muted);padding:6px 2px">Buscando…</div>';
    try{
        var d=await W.api('/api/v1/admin/pages/'+pageId+'/search?q='+encodeURIComponent(q));
        var res=(d&&d.data)||[];
        if(!res.length){box.innerHTML='<div style="font-size:12px;color:var(--w-muted);padding:6px 2px">Sin resultados.</div>';return}
        var h='<div class="w-card" style="padding:12px;margin-bottom:12px">';
        res.forEach(function(r){
            h+='<div class="sec-srch-sec"><b style="font-size:12.5px">'+W.esc(r.title||('#'+r.section_id))+'</b> <span style="color:var(--w-muted);font-size:11px">'+W.esc(r.widget||'')+'</span>';
            (r.matches||[]).forEach(function(m){
                h+='<div class="sec-srch-hit" onclick="wontia.secSearchGo('+r.section_id+',\''+W.esc(String(m.field).replace(/'/g,"\\'"))+'\')"><code>'+W.esc(m.label||m.field)+'</code> '+W.esc(m.snippet||'')+'</div>';
            });
            h+='</div>';
        });
        h+='</div>';box.innerHTML=h;
    }catch(e){box.innerHTML='<div style="font-size:12px;color:#ef4444;padding:6px 2px">Error en la búsqueda.</div>'}
};
W.secSearchGo=function(sectionId,field){try{sessionStorage.setItem('wwi_focus_field',field||'')}catch(e){}W.editSection(sectionId)};
W.showTrash=function(){
    var box=document.getElementById('sec-trash');if(!box)return;
    if(box.style.display!=='none'){box.style.display='none';box.innerHTML='';return}
    box.style.display='block';W.trashLoad();
};
W.trashLoad=async function(){
    var box=document.getElementById('sec-trash');if(!box)return;
    box.innerHTML='<div class="w-card" style="padding:14px;font-size:12px;color:var(--w-muted)">Cargando papelera…</div>';
    try{
        var d=await W.api('/api/v1/admin/trash');
        var list=(d&&d.data)||[];
        var h='<div class="w-card" style="padding:14px"><div class="w-flex-between w-mb"><b style="font-size:13px">Papelera de secciones</b><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.showTrash()">Cerrar</button></div>';
        if(!list.length)h+='<div style="font-size:12px;color:var(--w-muted)">Vacía.</div>';
        else h+=list.map(function(t){return '<div class="w-flex-between" style="padding:8px 0;border-bottom:1px solid var(--w-border)"><span style="font-size:12.5px">'+W.esc(t.title||('#'+t.section_id))+' <span style="color:var(--w-muted);font-size:11px">· '+W.esc(t.widget_type||'')+' · '+W.esc(t.deleted_by||'')+' · '+W.esc(t.created_at)+'</span></span><span class="w-flex w-gap-sm"><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.trashRestore('+t.id+')">Restaurar</button><button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.trashPurge('+t.id+')">✕</button></span></div>'}).join('');
        h+='</div>';box.innerHTML=h;
    }catch(e){box.innerHTML='<div class="w-card" style="color:#ef4444;padding:14px">Error cargando la papelera.</div>'}
};
W.trashRestore=async function(id){
    var r=await W.api('/api/v1/admin/trash/'+id+'/restore',{method:'POST',body:{}});
    if(r&&r.ok){W.notify('Sección restaurada','success');W.trashLoad();if(r.data&&r.data.page_id&&W.state.currentPage&&String(W.state.currentPage.id)===String(r.data.page_id))W.loadSections(r.data.page_id)}else W.notify((r&&r.message)||'Error','error');
};
W.trashPurge=async function(id){
    var r=await W.api('/api/v1/admin/trash/'+id,{method:'DELETE'});
    if(r&&r.ok){W.notify('Eliminado definitivamente','success');W.trashLoad()}else W.notify((r&&r.message)||'Error','error');
};

W.secPanelClose=function(){var b=document.getElementById('sec-extra');if(b){b.style.display='none';b.innerHTML=''}};
W.secHistory=async function(id){
    var box=document.getElementById('sec-extra');if(!box)return;
    if(box.dataset.open==='hist'&&box.style.display!=='none'){W.secPanelClose();return}
    box.dataset.open='hist';box.style.display='block';
    box.innerHTML='<div class="sec-panel"><b>Historial</b> <span style="color:var(--w-muted);font-size:11px">cargando…</span></div>';
    var vers=[],hist=[];
    try{vers=((await W.api('/api/v1/admin/sections/'+id+'/versions')).data)||[]}catch(e){}
    try{hist=((await W.api('/api/v1/admin/sections/'+id+'/history')).data)||[]}catch(e){}
    var h='<div class="sec-panel"><div class="w-flex-between"><b>Historial y versiones</b><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.secPanelClose()">Cerrar</button></div>';
    h+='<div class="sec-panel-h">Versiones ('+vers.length+')</div>';
    h+=vers.length?vers.map(function(v){return '<div class="sec-hist-row"><span>'+W.esc(v.username||'sistema')+' · '+W.esc(v.created_at)+'</span><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.secRestore('+v.id+','+id+')">Restaurar</button></div>'}).join(''):'<div class="sec-panel-empty">Sin versiones.</div>';
    h+='<div class="sec-panel-h">Cambios por campo ('+hist.length+')</div>';
    h+=hist.length?hist.map(function(e){return '<div class="sec-hist-row"><span><code>'+W.esc(e.field)+'</code> · '+W.esc(e.username||'—')+' · '+W.esc(e.created_at)+'</span></div>'}).join(''):'<div class="sec-panel-empty">Sin registros.</div>';
    h+='</div>';
    box.innerHTML=h;
};
W.secRestore=async function(versionId,sectionId){
    var r=await W.api('/api/v1/admin/versions/'+versionId+'/restore',{method:'POST',body:{}});
    if(r&&r.ok){W.notify('Versión restaurada','success');W.renderSectionEditor(sectionId)}else W.notify((r&&r.message)||'Error','error');
};
W.secDuplicate=async function(id){
    var r=await W.api('/api/v1/admin/sections/'+id+'/duplicate',{method:'POST',body:{}});
    if(r&&r.ok&&r.data&&r.data.id){W.notify('Sección duplicada','success');W.editSection(r.data.id)}else W.notify((r&&r.message)||'Error','error');
};
W.secAiReview=async function(){
    var box=document.getElementById('sec-extra');if(!box)return;
    box.dataset.open='ai';box.style.display='block';
    box.innerHTML='<div class="sec-panel">Analizando la sección con IA… <span style="color:var(--w-muted);font-size:11px">un momento</span></div>';
    var title=W.val('es-title'),sub=W.val('es-subtitle');
    var cfg={};try{cfg=W.state.editConfig||{}}catch(e){}
    var payload='Título: '+title+'\nSubtítulo: '+sub+'\nCampos: '+JSON.stringify(cfg).slice(0,1600);
    try{
        var d=await W.api('/api/v1/admin/brick/request',{method:'POST',body:{
            system_id:'wontia',module:'agent',function:'review',
            system_prompt:'Eres TIA, revisora de contenido web. Respondes SOLO con JSON válido.',
            messages:[{role:'user',content:'Revisa esta sección de una web y devuelve SOLO JSON: {"suggestions":[{"area":"copy|seo|accesibilidad","text":"..."}]} con 3 a 6 sugerencias concretas y accionables. No inventes datos.\n\n'+payload}],
            max_tokens:900,temperature:0.5
        }});
        var raw=String(((d&&d.data&&d.data.content)||''));
        var arr=null;try{arr=JSON.parse(raw).suggestions}catch(e){}
        if(!arr){try{arr=JSON.parse(raw.replace(/^```(json)?/m,'').replace(/```$/m,'')).suggestions}catch(e){}}
        var h='<div class="sec-panel"><div class="w-flex-between"><b>Revisión IA</b><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.secPanelClose()">Cerrar</button></div>';
        if(!Array.isArray(arr)||!arr.length){h+='<div class="sec-panel-empty" style="margin-top:8px">Sin sugerencias.</div>'}
        else h+=arr.map(function(x){return '<div class="sec-sugg"><span class="sec-sugg-a">'+W.esc(x.area||'')+'</span>'+W.esc(x.text||'')+'</div>'}).join('');
        h+='</div>';box.innerHTML=h;
    }catch(e){box.innerHTML='<div class="sec-panel" style="color:#ef4444">Error al revisar con IA.</div>'}
};

W.aiModes={
    improve:'Mejora la redacción: más clara, natural y persuasiva. Mantén el idioma y una longitud similar.',
    shorten:'Acorta el texto conservando el mensaje clave.',
    expand:'Amplía el texto con más detalle y valor, sin inventar datos ni cifras.',
    formal:'Reescribe con un tono formal y profesional.',
    friendly:'Reescribe con un tono cercano, humano y amable.',
    en:'Traduce el texto al inglés.',
    es:'Traduce el texto al español.',
    ab:'Propón una variante alternativa (A/B) con un enfoque distinto pero equivalente.'
};
W.aiClose=function(){var m=document.getElementById('w-ai-menu');if(m)m.remove()};
W.aiMenu=function(key,btn){
    W.aiClose();
    var modes=[['improve','✨ Mejorar'],['shorten','✂️ Acortar'],['expand','➕ Ampliar'],['formal','👔 Tono formal'],['friendly','🙂 Tono cercano'],['en','🇬🇧 Traducir a inglés'],['es','🇪🇸 Traducir a español'],['ab','🔀 Variante A/B']];
    var m=document.createElement('div');m.id='w-ai-menu';m.className='w-ai-menu';
    m.innerHTML='<div class="w-ai-menu-h">Asistente IA</div>'+modes.map(function(x){return '<button type="button" onclick="wontia.aiRun(\''+W.esc(key)+'\',\''+x[0]+'\')">'+x[1]+'</button>'}).join('');
    document.body.appendChild(m);
    var r=btn.getBoundingClientRect();
    m.style.left=Math.max(8,Math.min(window.innerWidth-230,r.left-120))+'px';
    m.style.top=(r.bottom+6)+'px';
    setTimeout(function(){document.addEventListener('click',W.aiClose,{once:true})},0);
};
W.aiRun=async function(key,mode){
    W.aiClose();
    var el=document.getElementById('secf-'+key);if(!el)return;
    var isRt=el.getAttribute('contenteditable')==='true';
    var text=(isRt?el.innerText:el.value)||'';
    if(!text.trim()){W.notify('El campo está vacío','error');return}
    W.setStatus('IA redactando…','saving');
    try{
        var d=await W.api('/api/v1/admin/brick/request',{method:'POST',body:{
            system_id:'wontia',module:'agent',function:'rewrite',
            system_prompt:'Eres TIA, redactora de sitios web de Wontia. Devuelve SOLO el texto resultante, sin comillas, sin markdown y sin explicaciones.',
            messages:[{role:'user',content:(W.aiModes[mode]||W.aiModes.improve)+'\n\nTexto:\n'+text}],
            max_tokens:900,temperature:0.6
        }});
        var out=String(((d&&d.data&&d.data.content)||'')).trim();
        if(!out){W.notify('La IA no devolvió texto','error');W.setStatus('Error','error');return}
        if(isRt)el.innerHTML=W.sanitizeRich(out);else el.value=out;
        if(W.markDirty)W.markDirty();
        W.notify('Texto actualizado por IA','success');
    }catch(e){W.notify('Error al consultar la IA','error');W.setStatus('Error','error')}
};
W.repAiFill=function(key){
    var f=W._repSchema[key];if(!f)return;
    var hint=window.prompt('¿Qué quieres generar? (ej: 6 preguntas frecuentes para un restaurante)','');
    if(!hint)return;
    var keys=(f.fields||[]).map(function(x){return x.key});
    W.repCollect(key);
    W.setStatus('IA generando…','saving');
    W.api('/api/v1/admin/brick/request',{method:'POST',body:{
        system_id:'wontia',module:'agent',function:'repeater',
        system_prompt:'Eres TIA, generadora de contenido de Wontia. Respondes SOLO con JSON válido.',
        messages:[{role:'user',content:'Genera items para una lista. Responde SOLO JSON con este formato: {"items":[{' + keys.map(function(k){return '"'+k+'":"..."'}).join(',') + '}]} (3 a 6 items). Petición: '+hint}],
        max_tokens:1200,temperature:0.6
    }}).then(function(d){
        var raw=String(((d&&d.data&&d.data.content)||''));
        var arr=null;
        try{arr=JSON.parse(raw).items}catch(e){}
        if(!arr){var cl=raw.replace(/^```(json)?/m,'').replace(/```$/m,'');try{arr=JSON.parse(cl).items}catch(e){}}
        if(!Array.isArray(arr)||!arr.length){W.notify('La IA no devolvió items válidos','error');W.setStatus('Error','error');return}
        arr.forEach(function(it){if(it&&typeof it==='object')W._rep[key].push(it)});
        W.repRefresh(key);if(W.markDirty)W.markDirty();
        W.notify('Items añadidos por IA','success');
    }).catch(function(){W.notify('Error al consultar la IA','error');W.setStatus('Error','error')});
};

W.renderRepeaterField=function(f,items){
    var key=f.key;
    W._rep=W._rep||{};W._repSchema=W._repSchema||{};
    W._repSchema[key]=f;
    W._rep[key]=(Array.isArray(items)?items:[]).map(function(it){return (it&&typeof it==='object')?it:{}});
    return '<div class="w-rep" data-rep="'+W.esc(key)+'">'
        +'<div class="w-rep-items" id="rep-items-'+W.esc(key)+'"></div>'
        +'<div class="w-flex w-gap-sm" style="margin-top:8px;flex-wrap:wrap"><button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.repAdd(\''+W.esc(key)+'\')">+ Añadir</button>'
        +'<button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.repAiFill(\''+W.esc(key)+'\')">✨ Rellenar con IA</button>'
        +'<button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.repReset(\''+W.esc(key)+'\')">Restaurar</button>'
        +(f.collapsible?'<button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.repToggleAll(\''+W.esc(key)+'\',true)">Expandir todo</button><button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.repToggleAll(\''+W.esc(key)+'\',false)">Contraer</button>':'')
        +'</div>'
        +'<textarea id="secf-'+W.esc(key)+'" style="display:none">'+W.esc(JSON.stringify(W._rep[key]))+'</textarea>'
        +'</div>';
};

W.repRefresh=function(key){
    var f=W._repSchema[key];if(!f)return;
    var items=W._rep[key]||[];
    var el=document.getElementById('rep-items-'+key);if(!el)return;
    if(!items.length){el.innerHTML='<div style="font-size:12px;color:var(--w-muted);padding:8px 0">Sin elementos. Añade el primero.</div>';W.repSync(key);return}
    var collapsible=!!f.collapsible;
    el.innerHTML=items.map(function(it,i){
        var body=(f.fields||[]).map(function(sf){
            var val=(it&&it[sf.key]!==undefined&&it[sf.key]!==null)?it[sf.key]:'';
            var id='rep-'+key+'-'+i+'-'+sf.key;
            var st=sf.type||'text';
            var cls='rep-lb';
            if(st==='image'||st==='textarea'||st==='richtext'||st==='range'||st==='sizeunit'||st==='composition')cls+=' rep-lb--full';
            if(sf.key==='title'||sf.key==='text'||sf.key==='href')cls+=' rep-lb--full';
            var inp;
            if(st==='richtext'){
                inp='<div class="w-rt"><div class="w-rt-bar">'
                    +'<button type="button" class="w-rt-b" title="Negrita" onclick="wontia.rtCmdId(\''+id+'\',\'bold\')"><b>B</b></button>'
                    +'<button type="button" class="w-rt-b" title="Cursiva" onclick="wontia.rtCmdId(\''+id+'\',\'italic\')"><i>I</i></button>'
                    +'<button type="button" class="w-rt-b" title="Subrayado" onclick="wontia.rtCmdId(\''+id+'\',\'underline\')"><u>U</u></button>'
                    +'<button type="button" class="w-rt-b" title="Lista" onclick="wontia.rtCmdId(\''+id+'\',\'insertUnorderedList\')">•</button>'
                    +'<button type="button" class="w-rt-b" title="Enlace" onclick="wontia.rtLinkId(\''+id+'\')">🔗</button>'
                    +'</div><div class="w-rt-edit rep-in" data-i="'+i+'" data-k="'+W.esc(sf.key)+'" id="'+id+'" contenteditable="true">'+W.sanitizeRich(val)+'</div></div>';
            }
            else if(st==='textarea')inp='<textarea class="w-textarea rep-in" data-i="'+i+'" data-k="'+W.esc(sf.key)+'" id="'+id+'">'+W.esc(val)+'</textarea>';
            else if(st==='image')inp='<div class="rep-img"><img id="'+id+'-prev" src="'+W.esc(val)+'" alt="" style="'+(val?'':'display:none;')+'"/><input class="w-input rep-in" data-i="'+i+'" data-k="'+W.esc(sf.key)+'" id="'+id+'" value="'+W.esc(val)+'" placeholder="https://… o /assets/uploads/…"/><button type="button" class="w-btn w-btn-secondary w-btn-sm rep-media-btn" onclick="wontia.repPickImage(\''+id+'\')">'+W.iconSvg('image',14)+'<span>Biblioteca</span></button></div>';
            else if(st==='toggle'){
                if(collapsible)inp='<label class="rep-switch"><input type="checkbox" class="rep-in" data-i="'+i+'" data-k="'+W.esc(sf.key)+'" id="'+id+'"'+(val?' checked':'')+' onchange="wontia.repSwitch(this)"/><span class="rep-switch__track"></span><span class="rep-switch__txt">'+(val?'Sí':'No')+'</span></label>';
                else inp='<label style="display:flex;align-items:center;gap:6px"><input type="checkbox" class="rep-in" data-i="'+i+'" data-k="'+W.esc(sf.key)+'" id="'+id+'"'+(val?' checked':'')+'/> <span style="font-size:12px;color:var(--w-muted)">'+(val?'Sí':'No')+'</span></label>';
            }
            else if(st==='color')inp='<input type="color" class="rep-in" data-i="'+i+'" data-k="'+W.esc(sf.key)+'" id="'+id+'" value="'+W.esc(val||sf.default||'#111111')+'"/>';
            else if(st==='number')inp='<input type="number" class="w-input rep-in" data-i="'+i+'" data-k="'+W.esc(sf.key)+'" id="'+id+'" value="'+W.esc(val)+'"/>';
            else if(st==='range'){var rv=(val===''||val===null)?(sf.default!==undefined?sf.default:0):val;inp='<div style="display:flex;gap:8px;align-items:center"><input type="range" class="rep-in" data-i="'+i+'" data-k="'+W.esc(sf.key)+'" id="'+id+'" min="'+W.esc(sf.min!==undefined?sf.min:0)+'" max="'+W.esc(sf.max!==undefined?sf.max:100)+'" step="'+W.esc(sf.step!==undefined?sf.step:1)+'" value="'+W.esc(rv)+'" oninput="wontia.repRange(this)"/><span id="'+id+'-out" style="font-size:11px;color:var(--w-muted);min-width:34px;text-align:right">'+W.esc(rv)+'</span></div>';}
            else if(st==='sizeunit'){var sv=(val===''||val===null)?(sf.default!==undefined?sf.default:100):val;var uk=sf.unitKey||'unit';var uv=(it&&it[uk]!==undefined&&it[uk]!==null&&it[uk]!=='')?it[uk]:(sf.defaultUnit||'%');var uns=sf.units||{'%':'%','pt':'pt','px':'px'};var uopts=Object.keys(uns).map(function(u){return '<option value="'+W.esc(u)+'"'+(String(uv)===String(u)?' selected':'')+'>'+W.esc(uns[u])+'</option>'}).join('');inp='<div style="display:flex;gap:6px;align-items:center"><input type="number" class="w-input rep-in" data-i="'+i+'" data-k="'+W.esc(sf.key)+'" id="'+id+'" min="'+W.esc(sf.min!==undefined?sf.min:8)+'" max="'+W.esc(sf.max!==undefined?sf.max:200)+'" step="'+W.esc(sf.step!==undefined?sf.step:1)+'" value="'+W.esc(sv)+'" style="flex:1"/><select class="w-select rep-in" data-i="'+i+'" data-k="'+W.esc(uk)+'" id="'+id+'-unit" style="max-width:160px">'+uopts+'</select></div>';}
            else if(st==='composition'){var comp=W.heroCompMerge(it||{});inp=W.heroCompositionEditor(id,comp,it||{},key,i);}
            else if(st==='select'){var kopts=sf.options||{};inp='<select class="w-select rep-in" data-i="'+i+'" data-k="'+W.esc(sf.key)+'" id="'+id+'">'+(Array.isArray(kopts)?kopts.map(function(o){var v=(o.value!==undefined?o.value:o),ll=(o.label!==undefined?o.label:v);return '<option value="'+W.esc(v)+'"'+(String(val)===String(v)?' selected':'')+'>'+W.esc(ll)+'</option>'}).join(''):Object.keys(kopts).map(function(k2){return '<option value="'+W.esc(k2)+'"'+(String(val)===String(k2)?' selected':'')+'>'+W.esc(kopts[k2])+'</option>'}).join(''))+'</select>';}
            else if(st==='icon')inp='<div style="display:flex;gap:7px;align-items:center"><input class="w-input rep-in" data-i="'+i+'" data-k="'+W.esc(sf.key)+'" id="'+id+'" value="'+W.esc(val)+'" style="flex:1"/><span id="'+id+'-iprev" style="color:#3a3a3a;flex:0 0 auto">'+(W.iconSvg(val,18)||W.esc(val))+'</span><button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.pickIcon(\''+id+'\')">Iconos</button></div>';
            else inp='<input class="w-input rep-in" data-i="'+i+'" data-k="'+W.esc(sf.key)+'" id="'+id+'" value="'+W.esc(val)+'"/>';
            return '<label class="'+cls+'"><span>'+W.esc(sf.label||sf.key)+'</span>'+inp+'</label>';
        }).join('');
        var thumbUrl=it.image||it.desktop||'';
        var title=it.label||it.title||it.alt||('Elemento '+(i+1));
        if(key==='backgrounds'&&!it.alt)title='Fondo '+(i+1);
        var sub=it.href||it.text||'';
        if(key==='backgrounds'&&!sub)sub=it.focal||'';
        if(key==='slides'){title=it.title||('Slide '+(i+1));sub=it.subtitle||it.eyebrow||it.cta_label||'';}
        var off=(it.enabled!==undefined&&(it.enabled===0||it.enabled==='0'||it.enabled===false));
        var thumb=thumbUrl?'<img class="w-rep-thumb" src="'+W.esc(thumbUrl)+'" alt="" loading="lazy">':((key==='benefits'&&it.icon)?'<span class="w-rep-ic">'+W.iconSvg(it.icon,16)+'</span>':'');
        var summary=collapsible?'<span class="w-rep-summary">'+thumb+'<span class="w-rep-summary-t"><b>'+W.esc(title)+'</b>'+(sub?'<small>'+W.esc(sub)+'</small>':'')+'</span>'+(off?'<span class="w-rep-off">Oculto</span>':'')+'</span>':'';
        var actions='<span class="w-rep-actions">'+(collapsible?'<button type="button" class="w-btn w-btn-secondary w-btn-sm w-rep-toggle" aria-expanded="false" onclick="wontia.repToggle(this)">Editar</button>':'')
            +'<button type="button" class="w-btn w-btn-secondary w-btn-sm" title="Subir" onclick="wontia.repMove(\''+key+'\','+i+',-1)">↑</button>'
            +'<button type="button" class="w-btn w-btn-secondary w-btn-sm" title="Bajar" onclick="wontia.repMove(\''+key+'\','+i+',1)">↓</button>'
            +'<button type="button" class="w-btn w-btn-secondary w-btn-sm" title="Duplicar" onclick="wontia.repDup(\''+key+'\','+i+')">⧉</button>'
            +'<button type="button" class="w-btn w-btn-danger w-btn-sm" title="Eliminar" onclick="wontia.repDel(\''+key+'\','+i+')">×</button>'
            +'</span>';
        return '<div class="w-rep-card'+(collapsible?' is-collapsible':'')+'"><div class="w-rep-head"><span class="w-drag-handle" title="Arrastra para reordenar">⠿</span><b class="w-rep-num">#'+(i+1)+'</b>'+summary+actions+'</div><div class="w-rep-body'+(collapsible?' is-collapsed':'')+'">'+body+'</div></div>';
    }).join('');
    if(W.heroCompositionInit)W.heroCompositionInit(el);
    W.repSync(key);
};
W.repSwitch=function(el){var l=el&&el.parentNode;if(!l)return;var t=l.querySelector('.rep-switch__txt');if(t)t.textContent=el.checked?'Sí':'No'};
W.repToggle=function(btn){var card=btn&&btn.closest?btn.closest('.w-rep-card'):null;if(!card)return;var body=card.querySelector('.w-rep-body');if(!body)return;var collapsed=body.classList.toggle('is-collapsed');card.classList.toggle('is-open',!collapsed);btn.setAttribute('aria-expanded',String(!collapsed));btn.textContent=collapsed?'Editar':'Cerrar'};
W.repToggleAll=function(key,open){var el=document.getElementById('rep-items-'+key);if(!el)return;el.querySelectorAll('.w-rep-card').forEach(function(card){var body=card.querySelector('.w-rep-body');var btn=card.querySelector('.w-rep-toggle');if(!body)return;body.classList.toggle('is-collapsed',!open);card.classList.toggle('is-open',!!open);if(btn){btn.setAttribute('aria-expanded',String(!!open));btn.textContent=open?'Cerrar':'Editar'}})};

W.repSync=function(key){var ta=document.getElementById('secf-'+key);if(ta)ta.value=JSON.stringify(W._rep[key]||[])};W.repRange=function(el){var o=document.getElementById(el.id+'-out');if(o)o.textContent=el.value;};
W.repCollect=function(key){
    var el=document.getElementById('rep-items-'+key);if(!el)return;
    W._rep=W._rep||{};W._rep[key]=W._rep[key]||[];
    el.querySelectorAll('.rep-in').forEach(function(inp){
        var i=parseInt(inp.getAttribute('data-i'),10),k=inp.getAttribute('data-k');
        if(!W._rep[key][i])W._rep[key][i]={};
        W._rep[key][i][k]=(inp.getAttribute('contenteditable')==='true')?W.sanitizeRich(inp.innerHTML||''):(inp.type==='checkbox'?(inp.checked?1:0):inp.value);
    });
    W.repSync(key);
};
W.repAdd=function(key){W.repCollect(key);W._rep[key].push({});W.repRefresh(key);if(W.markDirty)W.markDirty()};
W.repDel=function(key,i){W.repCollect(key);W._rep[key].splice(i,1);W.repRefresh(key);if(W.markDirty)W.markDirty()};
W.repDup=function(key,i){W.repCollect(key);var c=JSON.parse(JSON.stringify(W._rep[key][i]||{}));W._rep[key].splice(i+1,0,c);W.repRefresh(key);if(W.markDirty)W.markDirty()};
W.repMove=function(key,i,d){W.repCollect(key);var j=i+d;if(j<0||j>=(W._rep[key]||[]).length)return;var t=W._rep[key][i];W._rep[key][i]=W._rep[key][j];W._rep[key][j]=t;W.repRefresh(key);if(W.markDirty)W.markDirty()};
W.repReset=function(key){var f=W._repSchema[key];W._rep=W._rep||{};W._rep[key]=(f&&Array.isArray(f.default))?JSON.parse(JSON.stringify(f.default)):[];W.repRefresh(key);if(W.markDirty)W.markDirty()};
W.repInit=function(schema){(schema||[]).forEach(function(f){if((f.type||'')==='repeater'&&document.getElementById('rep-items-'+f.key))W.repRefresh(f.key)})};
W.repPickImage=function(inputId){
    W.openMediaExplorer({type:'image',title:'Elegir imagen',onSelect:function(m){W.repSetImage(inputId,m.url)}});
};
W.repSetImage=function(inputId,url){var el=document.getElementById(inputId);if(el)el.value=url;var p=document.getElementById(inputId+'-prev');if(p){p.src=url;p.style.display=url?'':'none'}W.closeModal();if(W.markDirty)W.markDirty()};

W.ICONS={
 'drum':'<circle cx="12" cy="12" r="9"/><path d="M3 12h18M6 5.6v12.8M18 5.6v12.8"/>',
 'guitar':'<path d="M9 3l3 3M15 3l-3 3"/><path d="M12 6c-1.2 2-3 3.7-5 4.7A4.6 4.6 0 0 0 9.6 19h4.8A4.6 4.6 0 0 0 17 10.7C15 9.7 13.2 8 12 6z"/><circle cx="12" cy="14.5" r="1.7"/>',
 'accordion':'<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M9 6v12M15 6v12M3 12h6M15 12h6"/>',
 'piano':'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M8 5v6M12 5v6M16 5v6M3 14h18"/>',
 'speaker':'<rect x="4" y="4" width="16" height="16" rx="2"/><circle cx="12" cy="14" r="3.2"/><path d="M8.5 8h7"/>',
 'mic':'<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M6 11a6 6 0 0 0 12 0M12 17v4M9 21h6"/>',
 'light':'<path d="M12 3l7 5v6l-7 5-7-5V8z"/><path d="M12 3v18M5 8l14 6"/>',
 'box':'<rect x="4" y="7" width="16" height="12" rx="2"/><path d="M8 7V5a4 4 0 0 1 8 0v2M4 12h16"/>',
 'truck':'<path d="M3 7h11v8H3zM14 10h4l3 3v2h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/>',
 'shield':'<path d="M12 3l7 3v6c0 4-3 7-7 9-4-2-7-5-7-9V6z"/><path d="M9 12l2 2 4-4"/>',
 'headset':'<path d="M4 13a8 8 0 0 1 16 0"/><rect x="3" y="13" width="4" height="6" rx="1"/><rect x="17" y="13" width="4" height="6" rx="1"/>',
 'card':'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/>',
 'lock':'<rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V8a4 4 0 0 1 8 0v2"/>',
 'star':'<path d="M12 3l2.6 5.4 5.9.8-4.3 4.1 1 5.9-5.2-2.8L7.8 19l1-5.9L4.5 9.2l5.9-.8z"/>',
 'check':'<path d="M4 12l5 5L20 6"/>',
 'tag':'<path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/>',
 'info':'<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>',
 'phone':'<rect x="6" y="2" width="12" height="20" rx="3"/><path d="M10 5h4M11 18h2"/>'
};
W.iconSvg=function(name,size){var p=W.ICONS[name];if(!p)return '';size=size||22;return '<svg viewBox="0 0 24 24" width="'+size+'" height="'+size+'" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+p+'</svg>';};
W.pickIcon=function(inputId){
    var names=Object.keys(W.ICONS);
    var h='<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(66px,1fr));gap:8px">'+names.map(function(n){return '<button type="button" class="w-btn w-btn-secondary" title="'+n+'" style="display:flex;flex-direction:column;align-items:center;gap:5px;padding:12px 4px;color:#3a3a3a" onclick="wontia.setIcon(\''+inputId+'\',\''+n+'\')">'+W.iconSvg(n,26)+'<span style="font-size:10px;color:var(--w-muted)">'+n+'</span></button>';}).join('')+'</div>';
    h+='<div style="margin-top:12px;display:flex;gap:8px"><input class="w-input" id="icon-custom" placeholder="…o pega un nombre/emoji" style="flex:1"/><button class="w-btn w-btn-secondary" onclick="wontia.setIcon(\''+inputId+'\',document.getElementById(\'icon-custom\').value)">Usar</button></div>';
    W.modal('Elegir icono (flat)',h,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cerrar</button>');
};
W.setIcon=function(inputId,val){var el=document.getElementById(inputId);if(el)el.value=val;var p=document.getElementById(inputId+'-iprev');if(p)p.innerHTML=W.iconSvg(val,20)||W.esc(val);W.closeModal();if(W.markDirty)W.markDirty();};

W.collectSchemaFields=function(schema){
    var cfg={};
    (schema||[]).forEach(function(f){
        var t=f.type||'text';
        if(t==='repeater'){
            if(W.repCollect)W.repCollect(f.key);
            var rta=document.getElementById('secf-'+f.key);
            try{cfg[f.key]=rta?JSON.parse(rta.value||'[]'):[]}catch(e){cfg[f.key]=[]}
            return;
        }
        var el=document.getElementById('secf-'+f.key);
        if(!el&&t!=='image')return;
        if(t==='toggle')cfg[f.key]=el.checked?1:0;
        else if(t==='color'){var txt=document.getElementById('secf-'+f.key+'-txt');cfg[f.key]=txt?txt.value:el.value}
        else if(t==='number')cfg[f.key]=el.value===''?'':Number(el.value);
        else if(t==='richtext')cfg[f.key]=W.sanitizeRich(el.innerHTML||'');
        else if(t==='code'){try{cfg[f.key]=JSON.parse(el.value||'[]')}catch(e){cfg[f.key]=el.value}}
        else cfg[f.key]=el.value;
        if(t==='image'){
            var alt=document.getElementById('secf-'+f.key+'-alt');
            if(alt)cfg[f.key+'_alt']=alt.value;
        }
    });
    return cfg;
};

W.editSection=function(sectionId){
    window.location.hash='section-edit-'+sectionId;
};

W.deleteSection=function(id,pageId){
    W.confirm('¿Eliminar esta sección?',async function(){
        await W.api('/api/v1/admin/sections/'+id,{method:'DELETE'});
        W.loadSections(pageId);
        W.notify('Sección eliminada','success');
    });
};

W.renderBlogList=function(){
    var app=document.getElementById('wontia-app');
    app.innerHTML='<div class="w-flex-between w-mb-lg"><div class="w-flex w-gap-sm"><input class="w-input" style="width:200px" placeholder="Search..." id="blog-search"/><select class="w-select" style="width:120px" id="blog-status"><option value="">All</option><option value="published">Published</option><option value="draft">Draft</option></select></div><button class="w-btn w-btn-primary" onclick="wontia.panels.blogEditor()">+ New Post</button></div><div id="blog-list"></div>';
    W.refreshBlog();
    document.getElementById('blog-search').addEventListener('input',W.refreshBlog);
    document.getElementById('blog-status').addEventListener('change',W.refreshBlog);
};

W.refreshBlog=async function(){
    var q=document.getElementById('blog-search')?document.getElementById('blog-search').value:'';
    var st=document.getElementById('blog-status')?document.getElementById('blog-status').value:'';
    var params=new URLSearchParams();
    if(q)params.set('search',q);
    if(st)params.set('status',st);
    var d=await W.api('/api/v1/admin/blog/posts?'+params.toString());
    W.state.posts=d.data||[];
    var el=document.getElementById('blog-list');
    if(!el)return;
    if(!W.state.posts.length){el.innerHTML='<div class="w-empty-state"><h3>No posts</h3><p>Create your first blog post</p></div>';return;}
    var html='';
    W.state.posts.forEach(function(p){
        html+='<div class="w-card w-mb" style="padding:14px"><div class="w-flex-between"><div><div style="font-size:13px;font-weight:600;cursor:pointer" onclick="wontia.panels.blogEditor('+p.id+')">'+W.esc(p.title)+'</div><div style="font-size:11px;color:var(--w-muted)">'+W.esc(p.category_name||'')+' &middot; '+p.views+' views &middot; '+p.updated_at+'</div></div><div class="w-flex w-gap-sm"><span class="w-badge w-badge-'+(p.status==='published'?'published':'draft')+'">'+p.status+'</span><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.togglePostStatus('+p.id+')">Toggle</button><button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.deletePost('+p.id+')">Del</button></div></div></div>';
    });
    el.innerHTML=html;
};

W.togglePostStatus=async function(id){
    var r=await W.api('/api/v1/admin/blog/posts/'+id+'/status',{method:'PATCH'});
    if(r.ok){W.refreshBlog();W.notify('Status: '+r.status,'success')}
};

W.deletePost=function(id){
    W.confirm('Delete this post?',async function(){await W.api('/api/v1/admin/blog/posts/'+id,{method:'DELETE'});W.refreshBlog();W.notify('Deleted','success')});
};

W.renderBlogEditor=function(id){
    var app=document.getElementById('wontia-app');
    app.innerHTML='<div class="w-card"><h3>'+(id?'Edit Post':'New Post')+'</h3>'+
        '<div class="w-form-group"><label class="w-label">Title</label><input class="w-input" id="be-title"/></div>'+
        '<div class="w-form-group"><label class="w-label">Slug</label><input class="w-input" id="be-slug"/></div>'+
        '<div class="w-form-group"><label class="w-label">Excerpt</label><textarea class="w-textarea" id="be-excerpt" style="min-height:60px"></textarea></div>'+
        '<div class="w-form-group"><label class="w-label">Content (HTML)</label><textarea class="w-textarea" id="be-content" style="min-height:250px;font-family:monospace"></textarea></div>'+
        '<div class="w-form-group"><label class="w-label">Category</label><select class="w-select" id="be-category"></select></div>'+
        '<div class="w-form-group"><label class="w-label">Cover Image URL</label><input class="w-input" id="be-cover"/></div>'+
        '<div class="w-form-group"><label class="w-label">Author</label><input class="w-input" id="be-author" value="Wontia"/></div>'+
        '<div class="w-form-group"><label class="w-label">Status</label><select class="w-select" id="be-status"><option value="draft">Draft</option><option value="published">Published</option></select></div>'+
        '<div class="w-form-group"><label class="w-label">Meta Title</label><input class="w-input" id="be-meta-title"/></div>'+
        '<div class="w-form-group"><label class="w-label">Meta Description</label><textarea class="w-textarea" id="be-meta-desc" style="min-height:40px"></textarea></div>'+
        '<div class="w-flex w-gap-sm w-mt"><button class="w-btn w-btn-primary" id="be-save">Save</button><button class="w-btn w-btn-secondary" id="be-polish">AI Polish</button><button class="w-btn w-btn-secondary" onclick="wontia.router()">Cancel</button></div></div>';
    W.loadCategories();
    if(id){
        W.api('/api/v1/admin/blog/posts/'+id).then(function(d){
            var p=d.data;
            document.getElementById('be-title').value=p.title||'';
            document.getElementById('be-slug').value=p.slug||'';
            document.getElementById('be-excerpt').value=p.excerpt||'';
            document.getElementById('be-content').value=p.content||'';
            document.getElementById('be-cover').value=p.cover_image||'';
            document.getElementById('be-author').value=p.author_name||'';
            document.getElementById('be-status').value=p.status||'draft';
            document.getElementById('be-meta-title').value=p.meta_title||'';
            document.getElementById('be-meta-desc').value=p.meta_description||'';
            setTimeout(function(){
                var sel=document.getElementById('be-category');
                if(sel&&p.category_id){for(var i=0;i<sel.options.length;i++){if(sel.options[i].value==p.category_id){sel.selectedIndex=i;break}}}
            },500);
        });
    }
    document.getElementById('be-title').addEventListener('input',function(){
        if(!id&&!document.getElementById('be-slug').dataset.manual)document.getElementById('be-slug').value=W.slugify(this.value);
    });
    document.getElementById('be-slug').addEventListener('input',function(){this.dataset.manual='1'});
    document.getElementById('be-save').addEventListener('click',async function(){
        var data={title:document.getElementById('be-title').value,slug:document.getElementById('be-slug').value,excerpt:document.getElementById('be-excerpt').value,content:document.getElementById('be-content').value,category_id:document.getElementById('be-category').value||null,cover_image:document.getElementById('be-cover').value,author_name:document.getElementById('be-author').value,status:document.getElementById('be-status').value,meta_title:document.getElementById('be-meta-title').value,meta_description:document.getElementById('be-meta-desc').value};
        if(!data.title){W.notify('Title required','error');return}
        var method=id?'PUT':'POST';
        var url=id?'/api/v1/admin/blog/posts/'+(id||''):'/api/v1/admin/blog/posts';
        var r=await W.api(url,{method:method,body:data});
        if(r.ok){W.notify('Saved','success');if(!id)window.location.hash='#blog';else W.refreshBlog()}
    });
    document.getElementById('be-polish').addEventListener('click',async function(){
        var content=document.getElementById('be-content').value;
        if(!content){W.notify('No content to polish','error');return}
        W.notify('Polishing...','info');
        var r=await W.api('/api/v1/admin/blog/polish',{method:'POST',body:{content:content}});
        if(r.ok&&r.data){document.getElementById('be-content').value=r.data.content;W.notify('Content polished','success')}
    });
};

W.loadCategories=async function(){
    var d=await W.api('/api/v1/admin/blog/categories');
    W.state.categories=d.data||[];
    var sel=document.getElementById('be-category');
    if(!sel)return;
    sel.innerHTML='<option value="">None</option>';
    W.state.categories.forEach(function(c){sel.innerHTML+='<option value="'+c.id+'">'+W.esc(c.name)+'</option>'});
};

W.state=W.state||{};W.state.media={folder:'',kind:'',q:'',trash:0};
W.mediaHuman=function(b){b=parseInt(b)||0;if(b<1024)return b+' B';if(b<1048576)return (b/1024).toFixed(1)+' KB';return (b/1048576).toFixed(2)+' MB'};
W.meKindLabel=function(m){var k=String((m&&m.kind)||'');if(k==='video')return 'Video';if(k==='icon')return 'Icono';if(k==='file')return 'Archivo';return 'Imagen'};
W.meDims=function(m){var w=parseInt(m&&m.width,10)||0,h=parseInt(m&&m.height,10)||0;return (w>0&&h>0)?(w+'×'+h):''};
W.meDate=function(v){if(!v)return '—';var d=new Date(String(v));if(isNaN(d.getTime()))return String(v);try{return d.toLocaleDateString('es-CO',{year:'numeric',month:'short',day:'numeric'})}catch(e){return String(v)}};
W.meThumb=function(m){
    var vs={};try{vs=m.variants?(typeof m.variants==='string'?JSON.parse(m.variants):m.variants):{}}catch(e){vs={}}
    var thumb=(vs.thumb&&vs.thumb.url)?vs.thumb.url:'';
    var label=W.meKindLabel(m);
    if(m.kind==='video')return (thumb?'<img src="'+W.esc(thumb)+'" alt="" loading="lazy"/>':'')+'<span class="w-me-play">▶</span><span class="w-me-kind">'+label+'</span>';
    if(m.kind==='file')return '<div class="w-me-play">▤</div><span class="w-me-kind">'+label+'</span>';
    var src=thumb||m.url;
    return '<img src="'+W.esc(src)+'" alt="'+W.esc(m.alt_text||'')+'" loading="lazy"/><span class="w-me-kind">'+label+'</span>';
};
W.meMetaLine=function(m){var parts=[W.meKindLabel(m)];var d=W.meDims(m);if(d)parts.push(d);parts.push(W.mediaHuman(m.original_size||m.size));if(m.converted_ext)parts.push(String(m.converted_ext).toUpperCase()+(m.converted_size?' '+W.mediaHuman(m.converted_size):''));return parts.join(' · ')};
W.meCompatible=function(m){if(!W._me||!W._me.type)return true;if(!m||!m.kind)return true;if(W._me.type==='image')return m.kind==='image'||m.kind==='icon';if(W._me.type==='video')return m.kind==='video';return true};
W.openMediaExplorer=function(options){
    options=options||{};
    W._me={type:options.type||'',kind:options.type||'',q:'',folder:'',items:[],folders:[],selectedId:null,onSelect:options.onSelect||null,title:options.title||'Biblioteca de Media'};
    var body='<div class="w-me" id="w-me">'
      +'<div class="w-me-top"><div class="w-me-tabs" id="w-me-tabs"></div><div class="w-me-tools"><input class="w-input" id="w-me-search" placeholder="Buscar por nombre, tipo o marca…" value="" onkeydown="if(event.keyCode===13)wontia.meSearch()"/><button type="button" class="w-btn w-btn-secondary" onclick="wontia.meSearch()">Buscar</button><button type="button" class="w-btn w-btn-primary" onclick="wontia.meUploadPick()">Subir</button><input type="file" id="w-me-file" accept="image/*,video/*,.svg,.ico,.pdf" multiple style="display:none" onchange="wontia.meUpload(this.files)"/></div></div>'
      +'<div class="w-me-body"><aside class="w-me-side" id="w-me-side"></aside><div class="w-me-gallery-wrap" id="w-me-gallery-wrap"><div class="w-me-gallery" id="w-me-grid"><div class="w-me-empty">Cargando biblioteca…</div></div></div><aside class="w-me-detail" id="w-me-detail"></aside></div>'
      +'<div class="w-me-foot" id="w-me-foot"></div></div>';
    W.modal(options.title||'Biblioteca de Media',body,'<button type="button" class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cerrar</button><button type="button" class="w-btn w-btn-primary" id="w-me-use" onclick="wontia.meUse()" disabled>Usar seleccionado</button>');
    var box=document.querySelector('#w-modal .w-modal');if(box)box.classList.add('w-modal--media');
    var wrap=document.getElementById('w-me-gallery-wrap');
    if(wrap){
        wrap.addEventListener('dragover',function(e){e.preventDefault();wrap.classList.add('is-drag')});
        wrap.addEventListener('dragleave',function(){wrap.classList.remove('is-drag')});
        wrap.addEventListener('drop',function(e){e.preventDefault();wrap.classList.remove('is-drag');if(e.dataTransfer&&e.dataTransfer.files)W.meUpload(e.dataTransfer.files)});
    }
    W.mediaExplorerLoad();
};
W.mediaExplorerLoad=async function(){
    var s=W._me;if(!s)return;
    var grid=document.getElementById('w-me-grid');if(grid)grid.innerHTML='<div class="w-me-empty">Cargando biblioteca…</div>';
    var qs='?limit=160';if(s.kind)qs+='&kind='+encodeURIComponent(s.kind);if(s.folder!=='')qs+='&folder_id='+encodeURIComponent(s.folder);if(s.q)qs+='&q='+encodeURIComponent(s.q);
    try{
        var d=await W.api('/api/v1/admin/media'+qs);
        var fo=await W.api('/api/v1/admin/media/folders');
        s.items=(d&&d.data)||[];s.folders=(fo&&fo.data)||[];
    }catch(e){s.items=[];s.folders=[];W.notify('No se pudo cargar la biblioteca','error')}
    if(s.selectedId&&!s.items.filter(function(m){return String(m.id)===String(s.selectedId)})[0])s.selectedId=null;
    W.mediaExplorerRender();
};
W.mediaExplorerRender=function(){
    var s=W._me;if(!s)return;
    var tabs=[['','Todos'],['image','Imágenes'],['video','Videos'],['icon','Iconos']];
    var tabsEl=document.getElementById('w-me-tabs');
    if(tabsEl)tabsEl.innerHTML=tabs.map(function(t){return '<button type="button" class="w-me-tab'+(String(s.kind)===String(t[0])?' active':'')+'" onclick="wontia.meKind(\''+t[0]+'\')">'+t[1]+'</button>'}).join('');
    var side=document.getElementById('w-me-side');
    if(side){
        var h='<div class="w-me-side-title">Carpetas</div><button type="button" class="w-me-side-btn'+(s.folder===''?' active':'')+'" onclick="wontia.meFolder(\'\')"><span>Todas</span></button>';
        s.folders.forEach(function(f){h+='<button type="button" class="w-me-side-btn'+(String(s.folder)===String(f.id)?' active':'')+'" onclick="wontia.meFolder(\''+f.id+'\')"><span>'+W.esc(f.name)+'</span><small>'+parseInt(f.count,10)+'</small></button>'});
        h+='<button type="button" class="w-btn w-btn-secondary w-me-side-add" onclick="wontia.meNewFolder()">+ Nueva carpeta</button>';
        side.innerHTML=h;
    }
    var grid=document.getElementById('w-me-grid');
    if(grid){
        if(!s.items.length)grid.innerHTML='<div class="w-me-empty">No hay archivos con estos filtros.<br><button type="button" class="w-btn w-btn-primary" style="margin-top:12px" onclick="wontia.meUploadPick()">Subir un archivo</button></div>';
        else grid.innerHTML=s.items.map(function(m){return W.mediaExplorerCard(m)}).join('');
    }
    var detail=document.getElementById('w-me-detail');if(detail)detail.innerHTML=W.mediaExplorerDetail();
    var foot=document.getElementById('w-me-foot');
    if(foot){var sel=s.items.filter(function(m){return String(m.id)===String(s.selectedId)})[0];foot.innerHTML='<span>'+s.items.length+' archivo(s)</span><span>'+(sel?('Seleccionado: <b>'+W.esc(sel.original_name||sel.filename||'')+'</b>'):'Haz clic en una tarjeta para ver sus datos')+'</span>'}
    var use=document.getElementById('w-me-use');if(use){var m=s.items.filter(function(x){return String(x.id)===String(s.selectedId)})[0];use.disabled=!m||!W.meCompatible(m)}
};
W.mediaExplorerCard=function(m){
    var s=W._me;var sel=String(s.selectedId)===String(m.id);var name=m.original_name||m.filename||('Archivo #'+m.id);var compat=W.meCompatible(m);
    return '<div class="w-me-card'+(sel?' is-selected':'')+(compat?'':' is-incompatible')+'" role="button" tabindex="0" aria-label="'+W.esc(name)+'" onclick="wontia.meSelect('+parseInt(m.id,10)+')" ondblclick="wontia.meUse()" onkeydown="if(event.keyCode===13||event.keyCode===32){event.preventDefault();wontia.meSelect('+parseInt(m.id,10)+')}">'
      +'<div class="w-me-thumb">'+W.meThumb(m)+(sel?'<span class="w-me-check">✓</span>':'')+'<button type="button" class="w-me-del" title="Mover a papelera" onclick="event.stopPropagation();wontia.meDelete('+parseInt(m.id,10)+')">🗑</button></div>'
      +'<div class="w-me-meta"><b title="'+W.esc(name)+'">'+W.esc(name)+'</b><small title="'+W.esc(W.meMetaLine(m))+'">'+W.esc(W.meMetaLine(m))+'</small></div></div>';
};
W.mediaExplorerDetail=function(){
    var s=W._me;if(!s)return '';
    var m=s.items.filter(function(x){return String(x.id)===String(s.selectedId)})[0];
    if(!m)return '<div class="w-me-detail-empty">Selecciona una imagen o un video para ver sus datos, copiar la ruta o adjuntarlo al campo.</div>';
    var name=m.original_name||m.filename||('Archivo #'+m.id);var compat=W.meCompatible(m);
    var folder='Sin carpeta';if(m.folder_id){var f=s.folders.filter(function(x){return String(x.id)===String(m.folder_id)})[0];if(f)folder=f.name}
    var rows=[['Tipo',W.meKindLabel(m)],['Dimensiones',W.meDims(m)||'—'],['Peso',W.mediaHuman(m.original_size||m.size)],['Optimizado',m.converted_ext?(String(m.converted_ext).toUpperCase()+(m.converted_size?' · '+W.mediaHuman(m.converted_size):'')):'—'],['Fecha',W.meDate(m.created_at||m.created)],['Carpeta',folder]];
    if(m.is_brand)rows.push(['Marca','Sí']);
    return '<div class="w-me-prev">'+W.meThumb(m)+'</div>'
      +'<h4>'+W.esc(name)+'</h4><div class="w-me-sub">'+W.esc(m.mime||'')+'</div>'
      +'<div class="w-me-data">'+rows.map(function(r){return '<div><span>'+r[0]+'</span><b>'+W.esc(String(r[1]))+'</b></div>'}).join('')+'</div>'
      +'<div class="w-me-url" title="URL del archivo">'+W.esc(m.url||'')+'</div>'
      +(compat?'':'<div class="w-me-warn">Este campo espera '+W.esc(s.type==='image'?'una imagen':'un video')+'.</div>')
      +'<button type="button" class="w-btn w-btn-primary" onclick="wontia.meUse()"'+(compat?'':' disabled')+'>Usar este archivo</button>'
      +'<button type="button" class="w-btn w-btn-secondary" onclick="wontia.meCopy(\''+W.esc(m.url||'')+'\')">Copiar URL</button>'
      +'<button type="button" class="w-btn w-btn-danger" onclick="wontia.meDelete('+parseInt(m.id,10)+')">Mover a papelera</button>';
};
W.meKind=function(kind){if(!W._me)return;W._me.kind=kind||'';W.mediaExplorerLoad()};
W.meFolder=function(folder){if(!W._me)return;W._me.folder=folder===''?'':String(folder);W.mediaExplorerLoad()};
W.meSearch=function(){if(!W._me)return;W._me.q=(document.getElementById('w-me-search')||{}).value||'';W.mediaExplorerLoad()};
W.meSelect=function(id){if(!W._me)return;W._me.selectedId=id;W.mediaExplorerRender()};
W.meUse=function(){var s=W._me;if(!s)return;var m=s.items.filter(function(x){return String(x.id)===String(s.selectedId)})[0];if(!m||!W.meCompatible(m))return;var cb=s.onSelect;W.closeModal();if(cb)cb(m)};
W.meDelete=async function(id){
    if(!W._me)return;if(!confirm('¿Mover este archivo a la papelera? Podrás restaurarlo desde Media.'))return;
    var r=await W.api('/api/v1/admin/media/'+id,{method:'DELETE'});
    if(r&&r.ok){W.notify('Archivo movido a la papelera','success');W._me.items=W._me.items.filter(function(m){return String(m.id)!==String(id)});if(String(W._me.selectedId)===String(id))W._me.selectedId=null;W.mediaExplorerRender();}
    else W.notify((r&&r.message)||'No se pudo eliminar','error');
};
W.meCopy=function(url){if(!url)return;navigator.clipboard.writeText(url);W.notify('Ruta copiada','success')};
W.meUploadPick=function(){var f=document.getElementById('w-me-file');if(f)f.click()};
W.meUpload=async function(files){
    files=files?Array.prototype.slice.call(files):[];if(!files.length||!W._me)return;
    var s=W._me;var ok=0;var foot=document.getElementById('w-me-foot');if(foot)foot.textContent='Subiendo '+files.length+' archivo(s)…';
    for(var i=0;i<files.length;i++){
        var fd=new FormData();fd.append('file',files[i]);fd.append('folder_id',s.folder||0);
        try{var r=await W.api('/api/v1/admin/media/upload',{method:'POST',body:fd});if(r&&r.ok)ok++}catch(e){}
    }
    W.notify(ok?(ok+' archivo(s) subido(s)'):'No se pudo subir','success');
    await W.mediaExplorerLoad();
};
W.meNewFolder=function(){var name=prompt('Nombre de la carpeta:');if(!name)return;W.api('/api/v1/admin/media/folders',{method:'POST',body:{name:name}}).then(function(r){if(r&&r.ok)W.mediaExplorerLoad();else W.notify((r&&r.message)||'No se pudo crear','error')})};
W.renderMediaManager=function(){
    var app=document.getElementById('wontia-app');
    app.innerHTML='<div class="w-flex-between w-mb-lg"><h3>Biblioteca de Media</h3><div class="w-flex w-gap-sm"><span id="media-stats" style="font-size:11px;color:var(--w-muted);align-self:center"></span><button class="w-btn w-btn-secondary" onclick="wontia.mediaSyncBrand()">↻ Marca</button><button class="w-btn w-btn-primary" id="media-upload-btn">Subir</button></div></div>'
      +'<div class="w-drop-zone w-mb-lg" id="media-drop"><span>Arrastra y suelta aquí (imágenes, videos, iconos) o haz clic para subir</span></div>'
      +'<div id="media-uploader" style="display:none;margin-bottom:16px"></div>'
      +'<input type="file" id="media-file-input" accept="image/*,video/*,.svg,.ico,.pdf" style="display:none" multiple/>'
      +'<div style="display:grid;grid-template-columns:220px 1fr;gap:16px;align-items:start">'
      +'<div id="media-folders"></div>'
      +'<div><div class="w-flex w-gap-sm w-mb-lg" style="flex-wrap:wrap"><input class="w-input" id="media-search" placeholder="Buscar…" style="max-width:280px" onkeydown="if(event.key===\'Enter\')wontia.mediaSearch()"/><button class="w-btn w-btn-secondary" onclick="wontia.mediaSearch()">Buscar</button></div><div id="media-grid"></div></div>'
      +'</div>';
    var dp=document.getElementById('media-drop');
    dp.addEventListener('click',function(){document.getElementById('media-file-input').click()});
    document.getElementById('media-upload-btn').addEventListener('click',function(){document.getElementById('media-file-input').click()});
    document.getElementById('media-file-input').addEventListener('change',W.handleMediaUpload);
    dp.addEventListener('dragover',function(e){e.preventDefault();this.classList.add('drag-over')});
    dp.addEventListener('dragleave',function(){this.classList.remove('drag-over')});
    dp.addEventListener('drop',function(e){e.preventDefault();this.classList.remove('drag-over');W.uploadFiles(e.dataTransfer.files)});
    W.refreshMedia();
};

W.mediaSearch=function(){W.state.media.q=(document.getElementById('media-search')||{}).value||'';W.refreshMedia()};
W.mediaGo=function(folder,kind,trash){W.state.media.folder=folder||'';W.state.media.kind=kind||'';W.state.media.trash=trash?1:0;W.refreshMedia()};

W.refreshMedia=async function(){
    var s=W.state.media;
    var qs='?limit=120';if(s.folder)qs+='&folder_id='+encodeURIComponent(s.folder);if(s.kind)qs+='&kind='+encodeURIComponent(s.kind);if(s.q)qs+='&q='+encodeURIComponent(s.q);if(s.trash)qs+='&trash=1';
    var d=await W.api('/api/v1/admin/media'+qs);
    var st=await W.api('/api/v1/admin/media/stats');
    var fo=await W.api('/api/v1/admin/media/folders');
    var items=d.data||[];var folders=fo.data||[];var stat=st.data||{};
    var stEl=document.getElementById('media-stats');
    if(stEl)stEl.textContent=(stat.total||0)+' archivos · ahorro '+W.mediaHuman(stat.saved_bytes||0)+' con WebP';
    var side=document.getElementById('media-folders');
    if(side){
        var btn=function(label,folder,kind,trash){var on=(String(s.folder)===(folder||'')&&s.kind===(kind||'')&&+s.trash===(trash?1:0));return '<button class="w-btn '+(on?'w-btn-primary':'w-btn-secondary')+'" style="width:100%;justify-content:space-between;margin-bottom:4px" onclick="wontia.mediaGo(\''+folder+'\',\''+kind+'\','+(trash?1:0)+')"><span>'+label+'</span></button>'};
        var h='<div class="w-card" style="padding:10px">'+btn('📁 Todas','','',0)+btn('🖼 Imágenes','','image',0)+btn('🎬 Videos','','video',0)+btn('⭐ Iconos','','icon',0)
          +'<div style="font-size:11px;color:var(--w-muted);margin:10px 0 4px">Carpetas</div>';
        folders.forEach(function(f){h+='<button class="w-btn '+(String(s.folder)===String(f.id)?'w-btn-primary':'w-btn-secondary')+'" style="width:100%;justify-content:space-between;margin-bottom:4px" onclick="wontia.mediaGo(\''+f.id+'\',\'\',0)"><span>'+W.esc(f.name)+'</span><span style="opacity:.7">'+f.count+'</span></button>'});
        h+='<button class="w-btn w-btn-secondary" style="width:100%;margin-top:6px" onclick="wontia.mediaNewFolder()">＋ Nueva carpeta</button>'+btn('🗑 Papelera','','',1)+'</div>';
        side.innerHTML=h;
    }
    var grid=document.getElementById('media-grid');if(!grid)return;
    if(!items.length){grid.innerHTML='<div class="w-empty-state"><p>Sin resultados.</p></div>';return}
    grid.innerHTML='<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px">'+items.map(W.mediaCard).join('')+'</div>';
};

W.mediaCard=function(m){
    var name=m.original_name||m.filename||('Archivo #'+m.id);
    var actions=m.deleted_at
      ?'<button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.mediaRestore('+m.id+')">↩ Restaurar</button><button type="button" class="w-btn w-btn-danger w-btn-sm" onclick="wontia.mediaPurge('+m.id+')">Borrar</button>'
      :'<button type="button" class="w-btn w-btn-secondary w-btn-sm" title="Copiar ruta" onclick="wontia.mediaCopy(\''+W.esc(m.url)+'\')">Copiar</button><button type="button" class="w-btn w-btn-secondary w-btn-sm" title="Mover a carpeta" onclick="wontia.mediaMove('+m.id+')">Mover</button><button type="button" class="w-btn w-btn-danger w-btn-sm" title="Mover a papelera" onclick="wontia.mediaTrash('+m.id+')">🗑</button>';
    return '<div class="w-me-card w-me-card--manager">'
      +'<div class="w-me-thumb">'+W.meThumb(m)+'</div>'
      +'<div class="w-me-meta"><b title="'+W.esc(m.url||'')+'">'+W.esc(name)+'</b><small title="'+W.esc(m.url||'')+'">'+W.esc(W.meMetaLine(m))+(m.is_brand?' · marca':'')+'</small></div>'
      +'<div class="w-me-actions">'+actions+'</div></div>';
};

W.mediaCopy=function(u){navigator.clipboard.writeText(u);W.notify('Ruta copiada','success')};
W.mediaTrash=async function(id){var r=await W.api('/api/v1/admin/media/'+id,{method:'DELETE'});if(r.ok){W.notify('Movido a papelera','success');W.refreshMedia()}};
W.mediaRestore=async function(id){var r=await W.api('/api/v1/admin/media/'+id+'/restore',{method:'POST',body:{}});if(r.ok)W.refreshMedia()};
W.mediaPurge=async function(id){if(!confirm('¿Borrar definitivamente (archivo y variantes)?'))return;var r=await W.api('/api/v1/admin/media/'+id+'/purge',{method:'DELETE'});if(r.ok){W.notify('Borrado','success');W.refreshMedia()}};
W.mediaMove=async function(id){var fo=await W.api('/api/v1/admin/media/folders');var folders=fo.data||[];var opts=folders.map(function(f){return '<option value="'+f.id+'">'+W.esc(f.name)+'</option>'}).join('');W.modal('Mover a carpeta','<select class="w-input" id="mv-folder" style="width:100%"><option value="0">— Sin carpeta —</option>'+opts+'</select>','<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancelar</button><button class="w-btn w-btn-primary" onclick="wontia.mediaMoveDo('+id+')">Mover</button>')};
W.mediaMoveDo=async function(id){var f=document.getElementById('mv-folder').value;var r=await W.api('/api/v1/admin/media/'+id+'/move',{method:'POST',body:{folder_id:f}});if(r.ok){W.closeModal();W.refreshMedia()}};
W.mediaNewFolder=function(){var name=prompt('Nombre de la carpeta:');if(!name)return;W.api('/api/v1/admin/media/folders',{method:'POST',body:{name:name}}).then(function(r){if(r.ok)W.refreshMedia()})};
W.mediaSyncBrand=async function(){var r=await W.api('/api/v1/admin/media/sync-brand',{method:'POST',body:{}});W.notify('Marca sincronizada'+(r.added?(' ('+r.added+')'):''),'success');W.refreshMedia()};

W.handleMediaUpload=function(){W.uploadFiles(this.files)};

W.muEl=function(id){return document.getElementById(id)};
W.muStart=function(file){
    var box=W.muEl('media-uploader');if(!box)return;
    var isImg=(file.type||'').indexOf('image')===0;
    var thumb=isImg?'<img id="mu-img" alt="" style="width:100%;height:100%;object-fit:cover;display:block"/>':'<span style="font-size:22px">🎬</span>';
    box.style.display='block';
    box.innerHTML='<div class="w-card" style="padding:12px;display:flex;gap:12px;align-items:flex-start">'
      +'<div id="mu-thumb" style="width:84px;height:84px;border-radius:10px;background:var(--w-bg-soft,#0d1220);overflow:hidden;display:grid;place-items:center;flex:0 0 auto;border:1px solid var(--w-border,#26303f)">'+thumb+'</div>'
      +'<div style="flex:1;min-width:0">'
        +'<div id="mu-name" style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+W.esc(file.name)+'</div>'
        +'<div id="mu-meta" style="font-size:11px;color:var(--w-muted);margin-top:2px">'+W.mediaHuman(file.size)+' · subiendo…</div>'
        +'<div style="margin-top:10px;height:9px;border-radius:999px;background:var(--w-border,#26303f);overflow:hidden"><div id="mu-bar" style="height:100%;width:0%;background:linear-gradient(90deg,#9B8CDE,#B89EFF);transition:width .15s ease"></div></div>'
        +'<div style="display:flex;justify-content:space-between;margin-top:5px"><span id="mu-status" style="font-size:11px;color:var(--w-muted)">Preparando…</span><span id="mu-pct" style="font-size:12px;font-weight:800">0%</span></div>'
      +'</div></div>';
    if(isImg){try{var u=URL.createObjectURL(file);var im=W.muEl('mu-img');if(im){im.src=u;im.onload=function(){try{URL.revokeObjectURL(u)}catch(e){}}}}catch(e){}}
};
W.muProgress=function(p,st){p=Math.max(0,Math.min(100,p||0));var b=W.muEl('mu-bar');if(b)b.style.width=p+'%';var t=W.muEl('mu-pct');if(t)t.textContent=Math.round(p)+'%';if(st){var s=W.muEl('mu-status');if(s)s.textContent=st;}};
W.muDone=function(msg){W.muProgress(100,'');var s=W.muEl('mu-status');if(s){s.textContent='✓ '+msg;s.style.color='#34d399'}var b=W.muEl('mu-bar');if(b)b.style.background='#34d399';};
W.muError=function(msg){var s=W.muEl('mu-status');if(s){s.textContent='✕ '+msg;s.style.color='#f87171'}var b=W.muEl('mu-bar');if(b)b.style.background='#f87171';var t=W.muEl('mu-pct');if(t)t.textContent='';};

W.uploadFiles=function(files){
    files=Array.prototype.slice.call(files||[]);
    if(!files.length){W.notify('No se seleccionó ningún archivo','error');return;}
    var folder=(W.state.media&&W.state.media.folder)?W.state.media.folder:0;
    var idx=0;
    var next=function(){
        if(idx>=files.length){W.refreshMedia();setTimeout(function(){var el=W.muEl('media-uploader');var s=W.muEl('mu-status');if(el&&s&&s.textContent.indexOf('✓')===0)el.style.display='none';},1800);return;}
        var f=files[idx++];
        W.muStart(f);
        if(f.size>12*1024*1024){W.muError('supera 12MB');W.notify('“'+f.name+'” supera 12MB.','error');setTimeout(next,1200);return;}
        var fd=new FormData();fd.append('file',f);if(folder)fd.append('folder_id',folder);
        var xhr=new XMLHttpRequest();
        xhr.open('POST','/api/v1/admin/media/upload');
        if(W.token)xhr.setRequestHeader('Authorization','Bearer '+W.token);
        xhr.upload.onprogress=function(e){if(e.lengthComputable)W.muProgress(Math.round(e.loaded/e.total*100),'Subiendo…');};
        xhr.onload=function(){
            var r={};try{r=JSON.parse(xhr.responseText||'{}')}catch(e){}
            if(xhr.status>=200&&xhr.status<300&&r.ok){
                W.muProgress(100,'Optimizando…');
                var m=r.data||{};
                if(r.duplicate||(r.data&&r.data.duplicate))W.muDone('Ya existía (duplicado)');
                else W.muDone('Subido'+(m.converted_ext?(' · '+String(m.converted_ext).toUpperCase()+' '+W.mediaHuman(m.converted_size)):''));
            }else if(r.duplicate||(r.data&&r.data.duplicate)){
                W.muDone('Ya existía (duplicado)');
            }else{
                W.muError((r.message)||(r.error&&r.error.message)||(r.data&&r.data.message)||('Error '+(xhr.status||'de red')));
            }
            setTimeout(next,700);
        };
        xhr.onerror=function(){W.muError('Error de red');setTimeout(next,700);};
        xhr.send(fd);
    };
    next();
};

W.renderBanners=function(id){if(id){return W.bnEdit(id)}return W.bnList()};
W.bnList=async function(){
    var app=document.getElementById('wontia-app');
    app.innerHTML='<div style="padding:40px;text-align:center;color:var(--w-muted)">Cargando banners…</div>';
    var rb=await W.api('/api/v1/admin/banners');
    var banners=(rb&&rb.data)||[];
    var h='<div class="w-flex-between w-mb-lg" style="flex-wrap:wrap;gap:8px"><h3 style="margin:0">Banners</h3><button class="w-btn w-btn-primary" onclick="wontia.bnNew()">＋ Nuevo banner</button></div>';
    h+='<div style="font-size:12px;color:var(--w-muted);margin-bottom:12px">Crea, activa/desactiva y elige cuál va en la <b>barra superior</b>. Configura cada uno en su propia página.</div>';
    if(!banners.length){h+='<div class="w-empty-state"><p>Sin banners.</p></div>';}
    else{
        h+='<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px;margin-bottom:8px">';
        banners.forEach(function(b){
            h+='<div class="w-card" style="padding:14px">'
              +'<div class="w-flex-between" style="gap:8px;align-items:flex-start"><div><div style="font-weight:700">'+W.esc(b.name)+'</div><div style="font-size:11px;color:var(--w-muted)">'+W.esc(String(b.updated_at||''))+'</div></div>'
              +(b.is_header?'<span class="w-badge" style="background:rgba(52,211,153,.15);color:#34d399">Barra superior</span>':'')+'</div>'
              +'<div class="w-flex w-gap-sm" style="flex-wrap:wrap;margin-top:10px">'
              +'<button class="w-btn '+(b.enabled?'w-btn-secondary':'w-btn-primary')+' w-btn-sm" onclick="wontia.bnToggle('+b.id+','+(b.enabled?0:1)+')">'+(b.enabled?'● Activo':'○ Inactivo')+'</button>'
              +'<button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.bnGo('+b.id+')">Configurar</button>'
              +'<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.bnDup('+b.id+')" title="Duplicar">⧉</button>'
              +(b.is_header?'':'<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.bnHeader('+b.id+')" title="Usar en la barra superior">↥ Barra</button>')
              +'<button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.bnDel('+b.id+')" title="Eliminar">🗑</button>'
              +'</div></div>';
        });
        h+='</div>';
    }
    var r=await W.api('/api/v1/admin/banners/stats');
    var d=r.data||{};var s=d.summary||{};var items=d.items||[];
    h+='<div class="w-flex-between" style="margin:18px 0 10px"><h3 style="margin:0">Métricas (impresiones / clics / CTR)</h3><button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.bnClearStats()">Borrar métricas</button></div>';
    h+='<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:12px">'
      +'<div class="w-stat-card"><div class="w-stat-value">'+W.num(s.impressions||0)+'</div><div class="w-stat-label">Impresiones</div></div>'
      +'<div class="w-stat-card"><div class="w-stat-value">'+W.num(s.clicks||0)+'</div><div class="w-stat-label">Clics</div></div>'
      +'<div class="w-stat-card"><div class="w-stat-value">'+(s.ctr||0)+'%</div><div class="w-stat-label">CTR</div></div>'
      +'<div class="w-stat-card"><div class="w-stat-value">'+W.num(s.rows||0)+'</div><div class="w-stat-label">Avisos × variante</div></div></div>';
    if(items.length){
        h+='<table class="w-table"><thead><tr><th>Aviso (uid)</th><th>Variante</th><th>Impresiones</th><th>Clics</th><th>CTR</th><th>Última</th></tr></thead><tbody>'
          +items.map(function(x){var ctr=x.impressions?((x.clicks*100/x.impressions).toFixed(2)):0;return '<tr><td>'+W.esc(x.banner_uid)+'</td><td>'+W.esc(String(x.variant).toUpperCase())+'</td><td>'+W.num(x.impressions)+'</td><td>'+W.num(x.clicks)+'</td><td>'+ctr+'%</td><td>'+W.esc(String(x.updated_at||''))+'</td></tr>'}).join('')+'</tbody></table>';
    } else { h+='<div style="color:var(--w-muted);font-size:12px">Sin métricas aún.</div>'; }
    var t=await W.api('/api/v1/admin/banners/templates');
    var tpls=(t&&t.data)||[];
    h+='<div class="w-card" style="margin-top:20px"><div class="w-flex-between" style="flex-wrap:wrap;gap:8px"><h3 style="margin:0">Plantillas de barra superior</h3><div class="w-flex w-gap-sm"><input class="w-input" id="bn-tpl-name" placeholder="Nombre de la plantilla" style="max-width:220px"/><button class="w-btn w-btn-primary" onclick="wontia.bnTplSave()">💾 Guardar la barra actual</button></div></div>';
    h+='<div style="font-size:12px;color:var(--w-muted);margin:8px 0">Guarda el estado actual de la <b>barra superior</b> y reúsalo cuando quieras.</div>';
    if(tpls.length){h+='<table class="w-table"><thead><tr><th>Plantilla</th><th>Creada</th><th></th></tr></thead><tbody>'+tpls.map(function(x){return '<tr><td>'+W.esc(x.name)+'</td><td>'+W.esc(String(x.created_at||''))+'</td><td><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.bnTplApply('+x.id+')">Aplicar</button> <button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.bnTplDel('+x.id+')">🗑</button></td></tr>'}).join('')+'</tbody></table>';}
    else{h+='<div style="color:var(--w-muted);font-size:12px">Sin plantillas guardadas aún.</div>';}
    h+='</div>';
    app.innerHTML=h;
};
W.bnGo=function(id){window.location.hash='#banners/'+id};
W.bnNew=async function(){var n=prompt('Nombre del banner:');if(!n)return;var r=await W.api('/api/v1/admin/banners',{method:'POST',body:{name:n}});if(r&&r.ok&&r.id){window.location.hash='#banners/'+r.id}else W.notify('Error al crear','error')};
W.bnToggle=async function(id,v){var r=await W.api('/api/v1/admin/banners/'+id+'/toggle',{method:'POST',body:{enabled:v}});if(r.ok)W.bnList()};
W.bnHeader=async function(id){var r=await W.api('/api/v1/admin/banners/'+id+'/header',{method:'POST',body:{}});if(r.ok){W.notify('Ahora está en la barra superior','success');W.bnList()}};
W.bnDup=async function(id){var r=await W.api('/api/v1/admin/banners/'+id+'/duplicate',{method:'POST',body:{}});if(r.ok)W.bnList()};
W.bnDel=async function(id){if(!confirm('¿Eliminar este banner?'))return;var r=await W.api('/api/v1/admin/banners/'+id,{method:'DELETE'});if(r.ok){W.notify('Eliminado','success');W.bnList()}};
if(!window.__wwiBnUnload){window.__wwiBnUnload=1;window.addEventListener('beforeunload',function(e){if(W._bnDirty){e.preventDefault();e.returnValue=''}});}
W.bnEdit=async function(id){
    var app=document.getElementById('wontia-app');
    app.innerHTML='<div style="padding:40px;text-align:center;color:var(--w-muted)">Cargando banner…</div>';
    var r=await W.api('/api/v1/admin/banners/'+id);
    if(!r||!r.ok){app.innerHTML='<div class="w-empty-state"><h3>Banner no encontrado</h3><button class="w-btn w-btn-secondary" style="margin-top:10px" onclick="window.location.hash=\'#banners\'">← Volver</button></div>';return}
    var b=r.banner||{};W._bnSchema=r.schema||[];W._bnId=id;
    var h='<div class="w-flex-between w-mb-lg" style="flex-wrap:wrap;gap:8px"><div><div style="font-size:11px;color:var(--w-muted)">Banners › configuración</div><h3 style="margin:0">'+W.esc(b.name||('Banner #'+id))+'</h3></div>'
      +'<div class="w-flex w-gap-sm"><button class="w-btn w-btn-secondary" onclick="window.location.hash=\'#banners\'">← Volver</button><button class="w-btn w-btn-primary" onclick="wontia.bnSave()">💾 Guardar</button></div></div>';
    h+='<div class="w-card"><div class="w-form-group"><label class="w-label">Nombre del banner</label><input class="w-input" id="bn-name" value="'+W.esc(b.name||'')+'" maxlength="150"/></div>';
    h+='<div class="schema-fields" id="bn-editor">'+W.renderSchemaFields(W._bnSchema,b.config||{})+'</div>';
    h+='<div class="sec-foot" style="position:sticky;bottom:0;background:var(--w-bg);padding:12px 0;border-top:1px solid var(--w-border);display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap"><span id="bn-status" class="sec-status">Listo</span><span class="w-flex w-gap-sm"><button class="w-btn w-btn-secondary" onclick="window.location.hash=\'#banners\'">← Volver</button><button class="w-btn w-btn-primary" onclick="wontia.bnSave()">💾 Guardar cambios</button></span></div></div>';
    app.innerHTML=h;
    if(W.repInit)W.repInit(W._bnSchema);if(W.secLinkInit)W.secLinkInit();
    W._bnDirty=false;
    var el=document.getElementById('bn-editor');
    var st=function(s,c){var e=document.getElementById('bn-status');if(e){e.textContent=s;e.className='sec-status'+(c?' '+c:'')}};
    st('Listo');
    var mark=function(){W._bnDirty=true;st('Sin guardar','dirty')};
    el.addEventListener('input',mark);el.addEventListener('change',mark);
    var nm=document.getElementById('bn-name');if(nm)nm.addEventListener('input',mark);
};
W.bnSave=async function(){
    var cfg=W.collectSchemaFields(W._bnSchema||[]);
    var name=((document.getElementById('bn-name')||{}).value)||'';
    var r=await W.api('/api/v1/admin/banners/'+W._bnId,{method:'PUT',body:{name:name,config:cfg}});
    var e=document.getElementById('bn-status');
    if(r&&r.ok){W._bnDirty=false;if(e){e.textContent='Guardado';e.className='sec-status saved'}W.notify('Banner guardado','success')}
    else{if(e){e.textContent='Error';e.className='sec-status err'}W.notify('Error al guardar','error')}
};
W.bnTplSave=async function(){var n=(document.getElementById('bn-tpl-name')||{}).value;if(!n)return W.notify('Pon un nombre','error');var r=await W.api('/api/v1/admin/banners/templates',{method:'POST',body:{name:n}});if(r.ok){W.notify('Plantilla guardada','success');W.bnList()}else W.notify((r.data&&r.data.message)||'Error','error')};
W.bnTplApply=async function(id){if(!confirm('¿Aplicar esta plantilla a la barra superior? Se sobrescribirá la config actual.'))return;var r=await W.api('/api/v1/admin/banners/templates/'+id+'/apply',{method:'POST',body:{}});if(r.ok){W.notify('Plantilla aplicada','success');W.bnList()}};
W.bnTplDel=async function(id){if(!confirm('¿Eliminar la plantilla?'))return;var r=await W.api('/api/v1/admin/banners/templates/'+id,{method:'DELETE'});if(r.ok)W.bnList()};
W.bnClearStats=async function(){if(!confirm('¿Borrar todas las métricas de banners?'))return;var r=await W.api('/api/v1/admin/banners/stats',{method:'DELETE',body:{}});if(r.ok){W.notify('Métricas borradas','success');W.bnList()}};

W.deleteMedia=function(id){W.mediaTrash(id)};

W.renderAnalytics=async function(){
    var app=document.getElementById('wontia-app');
    var d=await W.api('/api/v1/admin/analytics');
    var a=d.data||{};
    var p=await W.api('/api/v1/admin/program/analytics');
    var g=p.data||{};
    var kpi=function(v,l){return '<div class="w-stat-card"><div class="w-stat-value">'+v+'</div><div class="w-stat-label">'+l+'</div></div>'};
    var tbl=function(title,rows,label,mapFn){
        if(!rows||!rows.length)return '';
        return '<div class="w-card"><h3>'+title+'</h3><table class="w-table"><thead><tr><th>'+label+'</th><th>Cantidad</th></tr></thead><tbody>'+rows.map(function(r){return '<tr><td>'+W.esc(mapFn?mapFn(r.k):(r.k||'-'))+'</td><td>'+r.c+'</td></tr>'}).join('')+'</tbody></table></div>';
    };
    var placementLabel=function(m){try{var j=JSON.parse(m||'{}');return (j.placement||'')+(j.href?' → '+j.href:'')}catch(e){return m||'-'}};
    var recent='';
    (g.recent||[]).forEach(function(r){
        recent+='<tr><td><strong>'+W.esc(r.event)+'</strong></td><td style="font-size:11px">'+W.esc(r.page||'')+'</td><td style="font-size:11px">'+W.esc(r.section||'')+'</td><td>'+(r.seconds||0)+'s</td><td>'+(r.clicks||0)+'</td><td>'+(r.scroll_pct||0)+'%</td><td style="font-size:11px">'+W.esc(r.device||'')+' · '+W.esc(r.os||'')+' · '+W.esc(r.browser||'')+'</td><td>'+W.esc(r.country||'')+'</td><td style="font-size:10px">'+W.esc((r.created_at||'').slice(0,19))+'</td></tr>';
    });
    app.innerHTML='<div class="w-stats">'+
        kpi(W.num(a.totals?.['views']||0),'Page Views')+
        kpi(W.num(a.totals?.['unique_visitors']||0),'Unique Visitors')+
        '</div>'+
        '<div class="w-card"><h3>Top Pages</h3><table class="w-table"><thead><tr><th>URL</th><th>Views</th></tr></thead><tbody>'+(a.top_pages||[]).map(function(p){return '<tr><td>'+W.esc(p.page_url)+'</td><td>'+p.views+'</td></tr>'}).join('')+'</tbody></table></div>'+
        '<div class="w-card"><h3>GA4</h3><div class="w-flex w-gap-sm"><input class="w-input" id="ga4-id" value="'+W.esc(a.ga4_id||'')+'" placeholder="G-XXXXXXXXXX"/><button class="w-btn w-btn-primary" onclick="wontia.saveGa4()">Save</button></div></div>'+
        '<div style="display:flex;align-items:center;justify-content:space-between;margin:22px 0 10px"><div><div style="font-size:11px;color:var(--w-muted)">Seguimiento del programa</div><h3 style="margin:0">Programa de desarrollo de apps</h3></div><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.programExport()">⬇ Export CSV</button></div>'+
        '<div class="w-stats">'+
        kpi(W.num(g.visits||0),'Visitas al programa')+
        kpi(W.num(g.sessions||0),'Sesiones únicas')+
        kpi((g.avg_seconds||0)+'s','Tiempo promedio')+
        kpi(W.num(g.apply_clicks||0),'Clicks a aplicar')+
        kpi((g.conversion_pct||0)+'%','Conversión')+
        kpi(W.num(g.page_clicks||0),'Clics en página')+
        kpi((g.scroll_avg||0)+'%','Scroll promedio')+
        '</div>'+
        '<div class="w-grid-2">'+
        tbl('CTA más usado',g.placements,'Ubicación',placementLabel)+
        tbl('Secciones más vistas',g.sections,'Sección')+
        tbl('Dispositivos',g.devices,'Dispositivo')+
        tbl('Sistema operativo',g.os,'SO')+
        tbl('Navegadores',g.browsers,'Navegador')+
        tbl('Países',g.countries,'País')+
        '</div>'+
        (recent?'<div class="w-card"><h3>Últimos eventos</h3><div style="overflow-x:auto"><table class="w-table"><thead><tr><th>Evento</th><th>Página</th><th>Sección</th><th>Tiempo</th><th>Clicks</th><th>Scroll</th><th>Dispositivo</th><th>País</th><th>Fecha</th></tr></thead><tbody>'+recent+'</tbody></table></div></div>':'');
};

W.programExport=async function(){
    try{
        var r=await fetch('/api/v1/admin/program/export',{headers:W.token?{Authorization:'Bearer '+W.token}:{}});
        if(!r.ok){W.notify('Export failed','error');return}
        var b=await r.blob();var u=URL.createObjectURL(b);var a=document.createElement('a');a.href=u;a.download='program-analytics.csv';document.body.appendChild(a);a.click();a.remove();URL.revokeObjectURL(u);
    }catch(e){W.notify('Export failed','error')}
};

W.saveGa4=async function(){
    var id=document.getElementById('ga4-id').value;
    var r=await W.api('/api/v1/admin/analytics/ga4',{method:'PUT',body:{ga_measurement_id:id}});
    if(r.ok)W.notify('GA4 saved','success');
};

W.renderSeo=async function(){
    var app=document.getElementById('wontia-app');
    var d=await W.api('/api/v1/admin/seo');
    var s=d.data||{};
    app.innerHTML='<div class="w-stats">'+
        '<div class="w-stat-card"><div class="w-stat-value">'+s.score+'/100</div><div class="w-stat-label">SEO Score</div></div>'+
        '<div class="w-stat-card"><div class="w-stat-value">'+s.pages_without_meta+'</div><div class="w-stat-label">Missing Meta</div></div>'+
        '<div class="w-stat-card"><div class="w-stat-value">'+s.posts_without_meta+'</div><div class="w-stat-label">Posts No Meta</div></div>'+
        '</div>'+
        '<button class="w-btn w-btn-primary w-mb-lg" onclick="wontia.runSeoAudit()">Run Full Audit</button>'+
        '<div id="seo-results"></div>';
};

W.runSeoAudit=async function(){
    var r=await W.api('/api/v1/admin/seo/audit',{method:'POST'});
    var d=r.data||{};
    var el=document.getElementById('seo-results');
    el.innerHTML='<div class="w-card"><h3>Audit Results ('+d.total_issues+' issues)</h3>'+(d.issues||[]).map(function(i){return '<div style="font-size:12px;padding:6px 0;border-bottom:1px solid var(--w-border)">['+i.type+'] '+(i.page||i.post)+' ('+i.slug+')</div>'}).join('')+'</div>';
};

W.renderTypographyPanel=async function(){
    var panel=document.getElementById('settings-panel'); if(!panel)return;
    panel.innerHTML='<div style="padding:20px;color:var(--w-muted)">Cargando…</div>';
    var d=await W.api('/api/v1/admin/settings'); var s=(d&&d.data)||{};
    var fonts=['Sora','Inter','Manrope','Plus Jakarta Sans','Outfit','Poppins','Montserrat','Figtree','DM Sans','Space Grotesk','Work Sans','Rubik','Nunito Sans','Lexend','Public Sans'];
    var curB=s.font_family_body||'Sora', curD=s.font_family_display||'Sora';
    var opts=function(sel){return fonts.map(function(f){return '<option value="'+W.esc(f)+'"'+(f===sel?' selected':'')+'>'+W.esc(f)+'</option>'}).join('')};
    var h='<div class="w-flex-between" style="flex-wrap:wrap;gap:8px;margin-bottom:10px"><div><div style="font-size:11px;color:var(--w-muted)">Settings › Tipografía</div><h3 style="margin:0">🔤 Tipografía del sitio</h3></div><button class="w-btn w-btn-primary" onclick="wontia.typoSave()">💾 Guardar</button></div>';
    h+='<div style="font-size:12px;color:var(--w-muted);margin-bottom:14px">Se aplica a <b>todo el sitio</b> (cuerpo y títulos) vía tokens de fuente. Fuentes de Google Fonts (pesos 400–800).</div>';
    h+='<div class="w-card" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px">'
      +'<div class="w-form-group"><label class="w-label">Fuente del cuerpo (texto, enlaces, listas)</label><select class="w-select" id="typo-body">'+opts(curB)+'</select></div>'
      +'<div class="w-form-group"><label class="w-label">Fuente de títulos</label><select class="w-select" id="typo-display">'+opts(curD)+'</select></div>'
      +'</div>';
    h+='<div class="w-card" style="margin-top:14px"><h3 style="margin-bottom:10px">Vista previa</h3><div id="typo-preview" style="line-height:1.6"><div style="font-size:30px;font-weight:800" data-disp>Todo tu mundo musical en un solo lugar</div><div style="margin-top:8px;color:var(--w-muted)">Acordeón · Guitarra · Batería · Micrófono · Teclados — texto de cuerpo para leer listas y enlaces.</div><button class="w-btn w-btn-primary" style="margin-top:10px">Botón de ejemplo</button></div></div>';
    panel.innerHTML=h;
    var loadFont=function(f){f=(f||'').trim();if(!f)return;var id='gf-'+f.replace(/\s+/g,'-');if(document.getElementById(id))return;var l=document.createElement('link');l.id=id;l.rel='stylesheet';l.href='https://fonts.googleapis.com/css2?family='+encodeURIComponent(f)+':wght@400;500;600;700;800&display=swap';document.head.appendChild(l);};
    var upd=function(){var b=(document.getElementById('typo-body')||{}).value||'Sora';var dp=(document.getElementById('typo-display')||{}).value||'Sora';loadFont(b);loadFont(dp);var pv=document.getElementById('typo-preview');if(!pv)return;pv.style.fontFamily="'"+b+"',system-ui,sans-serif";var t=pv.querySelector('[data-disp]');if(t)t.style.fontFamily="'"+dp+"',system-ui,sans-serif";};
    var bsel=document.getElementById('typo-body'),dsel=document.getElementById('typo-display');
    if(bsel)bsel.addEventListener('change',upd); if(dsel)dsel.addEventListener('change',upd);
    upd();
};
W.typoSave=async function(){
    var b=(document.getElementById('typo-body')||{}).value||'Sora';
    var dp=(document.getElementById('typo-display')||{}).value||'Sora';
    var r=await W.api('/api/v1/admin/settings',{method:'PUT',body:{font_family_body:b,font_family_display:dp}});
    if(r&&r.ok)W.notify('Tipografía guardada','success');
};
W.renderCatalogReconciliation=async function(){
    var panel=document.getElementById('settings-panel'); if(!panel)return;
    panel.innerHTML='<div style="padding:20px;color:var(--w-muted)">Cargando informe de conciliación…</div>';
    var r=await W.api('/api/v1/admin/catalog/reconciliation');
    var s=await W.api('/api/v1/admin/catalog/suggestions');
    W._crRender(r&&r.data, s&&s.data);
};
W.crScan=async function(){
    var panel=document.getElementById('settings-panel'); if(!panel)return;
    panel.innerHTML='<div style="padding:20px;color:var(--w-muted)">🤖 Escaneando el catálogo completo… (puede tardar)</div>';
    var r=await W.api('/api/v1/admin/catalog/reconciliation/scan',{method:'POST',body:{}});
    if(r&&r.ok)W.notify('Escaneo completado','success');
    W._crRender(r&&r.data);
};
W.crList=function(items,rowFn){
    if(!items.length)return '<div style="font-size:12px;color:var(--w-muted);padding:6px 0">Sin hallazgos.</div>';
    return '<div style="display:flex;flex-direction:column;gap:4px;max-height:340px;overflow:auto">'+items.map(function(x){return '<div style="font-size:12px;padding:4px 0;border-bottom:1px dashed var(--w-border)">'+rowFn(x)+'</div>'}).join('')+'</div>';
};
W._crRender=function(d,sug){
    var panel=document.getElementById('settings-panel'); if(!panel)return;
    if(!d){panel.innerHTML='<div class="w-empty-state"><p>No hay informe aún. Pulsa “Escanear ahora”.</p></div>';return}
    var s=d.stats||{}, sev=d.severity||{}, iss=d.issues||{};
    var kpi=function(l,v,c){return '<div class="w-card" style="flex:1;min-width:130px;text-align:center;margin:0"><div style="font-size:24px;font-weight:800;'+(c?'color:'+c:'')+'">'+W.num(v||0)+'</div><div style="font-size:11px;color:var(--w-muted);margin-top:4px">'+l+'</div></div>'};
    var h='<div class="w-flex-between" style="flex-wrap:wrap;gap:8px;margin-bottom:10px"><div><div style="font-size:11px;color:var(--w-muted)">Settings › Conciliación de catálogo</div><h3 style="margin:0">🤖 Robot de coherencia del catálogo</h3></div><button class="w-btn w-btn-primary" onclick="wontia.crScan()">↻ Escanear ahora</button></div>';
    h+='<div style="font-size:12px;color:var(--w-muted);margin-bottom:10px">Compara la clasificación del tema (keywords) con la taxonomía real (tags) y detecta incoherencias. Último escaneo: '+W.esc(d.generated_at||'—')+'</div>';
    h+='<div class="w-card" style="margin-bottom:14px;border-left:3px solid #7C3AED"><div class="w-flex-between" style="gap:10px;flex-wrap:wrap"><div><h3 style="margin:0 0 4px">📝 Contenido editorial y actualizaciones de Miche</h3><div style="font-size:12px;color:var(--w-muted);line-height:1.6">El Content Fix Lab investiga fichas oficiales, verifica identidad exacta, genera con TIA y compara si Miche mejoró la descripción. También exporta la lista de trabajo en .xls.</div></div><div style="display:flex;gap:6px;flex-wrap:wrap"><button class="w-btn w-btn-primary" onclick="location.hash=\'#store/contenido\';setTimeout(function(){wontia.storeContentLab()},700)">Abrir Content Fix Lab</button><button class="w-btn w-btn-secondary" onclick="location.hash=\'#store/contenido\';setTimeout(function(){wontia.storeContentLabMicheScan()},900)">Escanear contenido Miche</button></div></div></div>';
    h+='<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:14px">'
      +kpi('Productos',s.products)+kpi('Con categoría (tag)',s.with_tag)+kpi('Sin categoría',s.without_tag,'#b45309')
      +kpi('Categorías sin mapear',(s.unmapped_categories||0),'#DC2626')+kpi('Solapamientos',(iss.overlap||{}).count,'#b45309')+kpi('Divergencias',(iss.divergence||{}).count,'#b45309')
      +kpi('Duplicados',(iss.duplicates||{}).count)+kpi('Productos nuevos',(iss.new_products||{}).count,'#7C3AED')+kpi('Huérfanos',(iss.orphans||{}).count,'#DC2626')
      +'</div>';
    var br=d.bricks||{}, bp=d.bricks_pct||{};
    var pc=function(p){return p>=95?'#16a34a':(p>=80?'#d97706':'#DC2626')};
    h+='<div class="w-card"><h3>🧩 Cobertura por brick — ¿los productos aparecen y se pueden buscar?</h3><div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:6px">'
      +kpi('Buscable (título)',W.num(br.searchable)+' · '+(bp.searchable||0)+'%',pc(bp.searchable||0))+kpi('Advance Search',W.num(br.advance_search)+' · '+(bp.advance_search||0)+'%',pc(bp.advance_search||0))+kpi('Páginas de categoría',W.num(br.categorias)+' · '+(bp.categorias||0)+'%',pc(bp.categorias||0))+kpi('Mega Menú',W.num(br.mega_menu)+' · '+(bp.mega_menu||0)+'%',pc(bp.mega_menu||0))
      +'</div><div style="font-size:12px;color:var(--w-muted);margin-top:8px">Semáforo: <b style="color:#16a34a">≥95%</b> · <b style="color:#d97706">80–94%</b> · <b style="color:#DC2626">&lt;80%</b>. Un producto sin categoría (tag), tipo ni marca no aparece en los filtros → <b>huérfano</b>.'+(d.index_cleared?(' · <b style="color:#7C3AED">Índice reconstruido ('+d.index_cleared+')</b>'):'')+'</div></div>';
    h+='<div class="w-card" style="margin-top:14px"><h3>🆕 Productos nuevos (desde el último escaneo)</h3>'+W.crList((iss.new_products||{}).samples||[],function(h){return '<b>'+W.esc(h)+'</b>'})+'</div>';
    h+='<div class="w-card" style="margin-top:14px"><h3>🚫 Huérfanos — sin categoría/tipo/marca (no filtrables)</h3>'+W.crList((iss.orphans||{}).samples||[],function(x){var t=(x&&typeof x==='object')?x.title:x;var hd=(x&&typeof x==='object')?x.handle:'';return '<button class="w-btn w-btn-secondary w-btn-sm" style="float:right" onclick="wontia.crAssign(\''+W.esc(hd)+'\')">Asignar</button>'+W.esc(t)})+'</div>';
    h+='<div class="w-card" style="margin-top:14px"><h3>↔️ Divergencias (tag real ≠ clasificación del tema)</h3>'+W.crList((iss.divergence||{}).samples||[],function(x){return '<button class="w-btn w-btn-secondary w-btn-sm" style="float:right" onclick="wontia.crAssign(\''+W.esc(x.handle||'')+'\')">Asignar</button><b>'+W.esc(x.title)+'</b> <span style="color:var(--w-muted)">tag: '+W.esc(x.tag)+' ('+W.esc(x.tag_slug)+') vs tema: '+((x.kw_slugs||[]).map(W.esc).join(', '))+'</span>'})+'</div>';
    var hist=(d.history||[]).slice(-12).reverse();
    var ui=d.ui||{};
    if(ui&&Array.isArray(ui.issues)){var uih='<div class="w-card" style="margin-top:14px"><h3>🖥️ Auto-revisión de UI (robots)</h3>'+(ui.issues.length?W.crList(ui.issues,function(x){return '<span class="w-badge" style="background:rgba(148,163,184,.18)">'+W.esc(x.level)+'</span> '+W.esc(x.label)}) : '<div style="font-size:12px;color:#16a34a">✓ Sin regresiones de UI detectadas.</div>')+'</div>';h+=uih;}
    if(hist.length){h+='<div class="w-card" style="margin-top:14px"><h3>🕒 Historial de escaneos (últimos '+hist.length+')</h3><div style="overflow:auto"><table style="width:100%;font-size:12px;border-collapse:collapse"><thead><tr style="color:var(--w-muted);text-align:left"><th>Fecha</th><th>Productos</th><th>Nuevos</th><th>Huérfanos</th><th>Sin mapear</th><th>Divergencias</th></tr></thead><tbody>'+hist.map(function(r){return '<tr style="border-top:1px dashed var(--w-border)"><td>'+W.esc(String(r.at||'').replace('T',' ').slice(0,16))+'</td><td>'+W.num(r.products)+'</td><td>'+W.num(r['new'])+'</td><td>'+W.num(r.orphans)+'</td><td>'+W.num(r.unmapped)+'</td><td>'+W.num(r.divergence)+'</td></tr>'}).join('')+'</tbody></table></div></div>';}
    h+='<div class="w-card"><h3>🧭 Categorías del catálogo SIN mapear (requieren clasificación)</h3>'+W.crList((iss.mismatch||{}).samples||[],function(x){return '<b>«'+W.esc(x.tag)+'»</b> <span style="color:var(--w-muted)">'+W.num(x.count||0)+' productos · ej: '+W.esc(x.title)+'</span>'})+'</div>';
    h+='<div class="w-card" style="margin-top:14px"><h3>🔁 Productos en 2+ categorías del tema (solapamiento)</h3>'+W.crList((iss.overlap||{}).samples||[],function(x){return '<b>'+W.esc(x.title)+'</b> <span style="color:var(--w-muted)">'+((x.cats||[]).map(W.esc).join(', '))+'</span>'})+'</div>';
    h+='<div class="w-card" style="margin-top:14px"><h3>📝 Duplicados por escritura (mayúsculas/acentos/plural)</h3>'+W.crList((iss.duplicates||{}).samples||[],function(x){return 'nivel '+x.level+': '+((x.variants||[]).map(W.esc).join(' · '))})+'</div>';
    h+='<div class="w-card" style="margin-top:14px"><h3>❓ Sin categoría (tag) — no aparecen en el árbol por categoría</h3>'+W.crList((iss.no_tag||{}).samples||[],function(t){return W.esc(t)})+'</div>';
    sug=sug||{suggestions:{},untagged:0,overrides:0};
    var pend=Object.keys(sug.suggestions||{});
    h+='<div class="w-card" style="margin-top:14px"><div class="w-flex-between" style="flex-wrap:wrap;gap:8px"><h3 style="margin:0">🤖 Auto-clasificador IA (TIA)</h3><div class="w-flex w-gap-sm"><button class="w-btn w-btn-secondary" onclick="wontia.crClassify()">🤖 Clasificar lote (20)</button>'+(pend.length?'<button class="w-btn w-btn-primary" onclick="wontia.crApply()">✓ Aplicar seleccionadas ('+pend.length+')</button>':'')+'</div></div>';
    h+='<div style="font-size:12px;color:var(--w-muted);margin:6px 0 10px">Productos <b>sin categoría</b>: '+W.num(sug.untagged||0)+' · overrides aplicados: '+W.num(sug.overrides||0)+'. TIA propone la categoría desde el título/marca/tipo; revisa y aplica.</div>';
    if(pend.length){
        h+='<div style="display:flex;flex-direction:column;gap:4px;max-height:340px;overflow:auto">'+pend.map(function(hl){var x=sug.suggestions[hl]||{};return '<div style="display:flex;align-items:center;gap:8px;font-size:12px;padding:4px 0;border-bottom:1px dashed var(--w-border)"><input type="checkbox" class="cr-sug" value="'+W.esc(hl)+'" checked style="width:auto"/><b style="flex:1">'+W.esc(x.title||hl)+'</b><span class="w-badge" style="background:rgba(139,92,246,.18);color:#c4b5fd">'+W.esc(x.cat||'')+'</span><button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.crReject(\''+W.esc(hl)+'\')">✕</button></div>'}).join('')+'</div>';
    } else { h+='<div style="font-size:12px;color:var(--w-muted)">Sin sugerencias pendientes. Pulsa “Clasificar lote”.</div>'; }
    h+='</div>';
    panel.innerHTML=h;
};
W.crClassify=async function(){
    W.notify('Consultando a TIA…','info');
    var r=await W.api('/api/v1/admin/catalog/classify',{method:'POST',body:{limit:20}});
    var res=(r&&r.data&&r.data.result)||{};
    if(r&&r.ok)W.notify(res.ai?('TIA: '+res.suggested+'/'+res.batch+' clasificados'):'IA no disponible (revisa BRICK/DEEPSEEK)', res.ai?'success':'error');
    W.renderCatalogReconciliation();
};
W.crApply=async function(){
    var hs=[]; document.querySelectorAll('.cr-sug:checked').forEach(function(c){hs.push(c.value)});
    if(!hs.length){W.notify('Nada seleccionado','error');return}
    var r=await W.api('/api/v1/admin/catalog/suggestions/apply',{method:'POST',body:{handles:hs}});
    if(r&&r.ok)W.notify('Aplicadas '+((r.data||{}).applied||0),'success');
    W.renderCatalogReconciliation();
};
W.crReject=async function(h){ await W.api('/api/v1/admin/catalog/suggestions/reject',{method:'POST',body:{handles:[h]}}); W.renderCatalogReconciliation(); };
W.crAssign=async function(handle){
    var cat=prompt('Asignar categoría (etiqueta nivel 1) a:\n'+handle+'\n\nEscribe la categoría del catálogo (ej.: Guitarra, Bajo, Piano, Parche, Reproducción…)');
    if(!cat)return;
    var r=await W.api('/api/v1/admin/catalog/assign',{method:'POST',body:{handle:handle,category:cat}});
    if(r&&r.ok)W.notify('Categoría asignada (override): '+cat,'success'); else W.notify('Error al asignar','error');
    W.renderCatalogReconciliation();
};
W.renderHero=async function(id){
    var app=document.getElementById('wontia-app'); if(!app)return;
    if(id==='config')return W.renderHeroThemePage();
    if(id)return W.heroEdit(parseInt(id,10));
    app.innerHTML='<div style="padding:30px;text-align:center;color:var(--w-muted)">Cargando…</div>';
    var r=await W.api('/api/v1/admin/hero'); var d=(r&&r.data)||{}; W._heroTypes=d.types||{}; W._heroSlides=d.slides||[]; W._heroTheme=d.theme||{active:'classic',themes:{},config:{}};
    var h='<div class="w-flex-between" style="flex-wrap:wrap;gap:8px;margin-bottom:12px"><div><div style="font-size:11px;color:var(--w-muted)">Bricks › SONIKA Hero Builder</div><h3 style="margin:0">🚀 SONIKA Hero Builder</h3></div><div class="w-flex w-gap-sm"><button class="w-btn w-btn-secondary" onclick="location.hash=\'#bricks\'">← Bricks</button><button class="w-btn w-btn-primary" onclick="wontia.heroNew()">＋ Nuevo slide</button></div></div>';
    var __ht=W._heroTheme||{}, __hid=__ht.active||'classic', __hl=__ht.themes||{};
    var __hopts=Object.keys(__hl).map(function(k){return '<option value="'+W.esc(k)+'"'+(k===__hid?' selected':'')+'>'+W.esc((__hl[k]&&__hl[k].name)||k)+'</option>';}).join('');
    var __hname=(__hl[__hid]&&__hl[__hid].name)||__hid;
    h+='<div class="w-card" style="margin-bottom:12px;border:1px solid rgba(242,13,22,.3)"><div class="w-flex-between" style="flex-wrap:wrap;gap:10px"><div><div style="font-size:10px;letter-spacing:.12em;text-transform:uppercase;color:var(--w-muted);font-weight:800">Theme del Hero</div><div style="font-size:18px;font-weight:800;margin-top:3px">'+W.esc(__hname)+'</div><div style="font-size:11px;color:var(--w-muted);margin-top:3px">Cambia la experiencia visual sin borrar slides ni configuraciones.</div></div><span class="w-badge" style="background:rgba(52,211,153,.14);color:#0d7a58">ACTIVO</span></div><div class="w-flex w-gap-sm" style="flex-wrap:wrap;margin-top:12px;align-items:center"><select class="w-select" id="hero-theme-select" style="max-width:260px">'+__hopts+'</select><button class="w-btn w-btn-primary" onclick="wontia.heroThemeApply()">Aplicar theme</button><button class="w-btn w-btn-secondary" onclick="wontia.heroThemeConfigOpen()">Configurar contenido</button><button class="w-btn w-btn-secondary" onclick="wontia.heroThemeClassic()">Volver al theme clásico</button></div></div>';
    h+='<div style="font-size:12px;color:var(--w-muted);margin-bottom:12px"><b>Clásico</b> usa los slides de abajo. <b>El Sonido Se Apodera</b> usa su propio contenido, categorías, beneficios y slides editables.</div>';
    h+='<div style="font-size:12px;color:var(--w-muted);margin-bottom:12px">Slides de <b>2 zonas</b>. <b>Borrador</b> = no visible · <b>Activo</b> = publicado. Los motores de instrumento aún no están implementados.</div>';
    if(!W._heroSlides.length){h+='<div class="w-empty-state"><h3>Sin slides</h3><p>Crea el primero.</p></div>';}
    else{
        h+='<div style="display:flex;flex-direction:column;gap:8px">';
        W._heroSlides.forEach(function(s){
            var lt=(W._heroTypes[s.left_type]||{}).label||s.left_type, rt=(W._heroTypes[s.right_type]||{}).label||s.right_type;
            var mode=s.layout==='background'?('FONDO: '+(s.design&&s.design.bg_type?s.design.bg_type:'imagen')):('IZQ: '+lt+' · DER: '+rt);
            h+='<div class="w-card" style="padding:12px 14px"><div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap"><span style="font-weight:800">'+W.esc(s.name)+'</span>'
              +'<span class="w-badge" style="background:'+(s.status==='active'?'rgba(52,211,153,.15);color:#34d399':'rgba(148,163,184,.15);color:#94a3b8')+'">'+(s.status==='active'?'ACTIVO':'BORRADOR')+'</span>'
              +'<span style="font-size:11px;color:var(--w-muted)">'+W.esc(mode)+'</span><span style="flex:1"></span>'
              +'<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.heroGo('+s.id+')">Configurar</button>'
              +'<button class="w-btn w-btn-secondary w-btn-sm" title="Duplicar" onclick="wontia.heroDup('+s.id+')">⧉</button>'
              +(s.status==='active'?'<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.heroSetStatus('+s.id+',\'draft\')">Despublicar</button>':'<button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.heroPub('+s.id+')">🚀 Publicar</button>')
              +'<button class="w-btn w-btn-danger w-btn-sm" title="Eliminar" onclick="wontia.heroDel('+s.id+')">🗑</button></div></div>';
        });
        h+='</div>';
    }
    app.innerHTML=h;
};
W.heroGo=function(id){try{history.replaceState(null,'','#hero/'+id)}catch(e){location.hash='#hero/'+id}W.renderHero(id);};
W.heroBack=function(){try{history.replaceState(null,'','#hero')}catch(e){location.hash='#hero'}W.renderHero();};
W.heroNew=async function(){var r=await W.api('/api/v1/admin/hero/slides',{method:'POST',body:{name:'Nuevo slide',status:'draft',left_type:'text',right_type:'text'}});if(r&&r.ok&&r.slide)W.heroGo(r.slide.id);};
W.heroDup=async function(id){var r=await W.api('/api/v1/admin/hero/slides/'+id+'/duplicate',{method:'POST',body:{}});if(r&&r.ok)W.heroGo(r.id);};
W.heroDel=async function(id){if(!confirm('¿Eliminar este slide?'))return;await W.api('/api/v1/admin/hero/slides/'+id,{method:'DELETE'});W.renderHero();};
W.heroPub=async function(id){var r=await W.api('/api/v1/admin/hero/slides/'+id+'/publish',{method:'POST',body:{}});if(r&&r.ok)W.notify('Publicado','success');else W.notify((r&&r.message)||'No se pudo publicar','error');W.renderHero();};
W.heroSetStatus=async function(id,st){var s=(W._heroSlides||[]).filter(function(x){return x.id===id})[0];if(!s)return;await W.api('/api/v1/admin/hero/slides',{method:'POST',body:{id:id,name:s.name,status:st,layout:s.layout,left_type:s.left_type,left_data:s.left_data,right_type:s.right_type,right_data:s.right_data,design:s.design}});W.renderHero();};
W.heroCompState={};
W.heroCompDefault=function(item){
    var f=String((item&&item.focal)||'50% 50%').match(/(\d{1,3})%\s+(\d{1,3})%/);
    var fx=f?Math.max(0,Math.min(100,parseInt(f[1],10))):50;
    var fy=f?Math.max(0,Math.min(100,parseInt(f[2],10))):50;
    var cp=function(o){return JSON.parse(JSON.stringify(o));};
    var bg={zoom:100,x:0,y:0,focal_x:fx,focal_y:fy,fit:'cover'};
    var el={mode:'auto',anchor:'top-left',x:0,y:0,x_unit:'px',y_unit:'px'};
    var els={};['eyebrow','title','description','ctas'].forEach(function(k){els[k]=cp(el);});
    return {background:{desktop:cp(bg),mobile:cp(bg)},elements:{desktop:cp(els),mobile:cp(els)}};
};
W.heroCompMerge=function(item){
    var base=W.heroCompDefault(item||{});
    var raw=item&&item.composition;
    if(typeof raw==='string'){try{raw=JSON.parse(raw)}catch(e){raw=null}}
    if(!raw||typeof raw!=='object')return base;
    ['desktop','mobile'].forEach(function(d){
        if(raw.background&&raw.background[d])Object.assign(base.background[d],raw.background[d]);
        if(raw.elements&&raw.elements[d]){
            ['eyebrow','title','description','ctas'].forEach(function(k){
                if(raw.elements[d][k])Object.assign(base.elements[d][k],raw.elements[d][k]);
            });
        }
    });
    return base;
};
W.heroCompositionEditor=function(id,comp,item,key,idx){
    var E=[['eyebrow','Eyebrow'],['title','Título'],['description','Descripción'],['ctas','CTAs']];
    var A=[['top-left','↖'],['top-center','↑'],['top-right','↗'],['center-left','←'],['center','•'],['center-right','→'],['bottom-left','↙'],['bottom-center','↓'],['bottom-right','↘']];
    var elOpts=E.map(function(e){return '<option value="'+W.esc(e[0])+'">'+W.esc(e[1])+'</option>'}).join('');
    var anchors=A.map(function(a){return '<button type="button" data-anchor="'+W.esc(a[0])+'" onclick="wontia.heroCompAnchor(\''+W.esc(id)+'\',\''+W.esc(a[0])+'\')" title="'+W.esc(a[0])+'">'+a[1]+'</button>'}).join('');
    var h='<details class="hero-comp" data-id="'+W.esc(id)+'" data-desktop="'+W.esc((item&&item.desktop)||'')+'" data-mobile="'+W.esc((item&&item.mobile)||'')+'" data-focal="'+W.esc((item&&item.focal)||'50% 50%')+'">'
        +'<summary>Composición avanzada: fondo y posición de elementos</summary>'
        +'<div class="hero-comp__body">'
        +'<div class="hero-comp__row"><label class="hero-comp__label">Dispositivo</label><select id="'+W.esc(id)+'-device"><option value="desktop">PC</option><option value="mobile">Móvil</option></select><button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.heroCompCopy(\''+W.esc(id)+'\')">Copiar PC → Móvil</button></div>'
        +'<div class="hero-comp__preview"><img id="'+W.esc(id)+'-preview-img" src="'+W.esc((item&&item.desktop)||'')+'" alt=""/></div>'
        +'<div class="hero-comp__section"><div class="hero-comp__label">Fondo</div>'
        +'<div class="hero-comp__row"><label>Zoom</label><input type="range" id="'+W.esc(id)+'-bg-zoom" min="100" max="250" step="1" value="100"/><span id="'+W.esc(id)+'-bg-zoom-out">100%</span></div>'
        +'<div class="hero-comp__row"><label>Mover X</label><input type="range" id="'+W.esc(id)+'-bg-x" min="-100" max="100" step="1" value="0"/><span id="'+W.esc(id)+'-bg-x-out">0%</span></div>'
        +'<div class="hero-comp__row"><label>Mover Y</label><input type="range" id="'+W.esc(id)+'-bg-y" min="-100" max="100" step="1" value="0"/><span id="'+W.esc(id)+'-bg-y-out">0%</span></div>'
        +'<div class="hero-comp__row"><label>Focal X</label><input type="range" id="'+W.esc(id)+'-bg-fx" min="0" max="100" step="1" value="50"/><span id="'+W.esc(id)+'-bg-fx-out">50%</span></div>'
        +'<div class="hero-comp__row"><label>Focal Y</label><input type="range" id="'+W.esc(id)+'-bg-fy" min="0" max="100" step="1" value="50"/><span id="'+W.esc(id)+'-bg-fy-out">50%</span></div>'
        +'<div class="hero-comp__row"><label>Ajuste</label><select id="'+W.esc(id)+'-bg-fit"><option value="cover">Cubrir</option><option value="contain">Contener</option></select></div>'
        +'</div>'
        +'<div class="hero-comp__section"><div class="hero-comp__label">Elementos</div>'
        +'<div class="hero-comp__row"><label>Elemento</label><select id="'+W.esc(id)+'-el">'+elOpts+'</select><label>Modo</label><select id="'+W.esc(id)+'-el-mode"><option value="auto">Automático</option><option value="manual">Manual</option></select></div>'
        +'<div class="hero-comp__row"><label>Anclaje</label><div class="hero-comp__anchor" id="'+W.esc(id)+'-anchor">'+anchors+'</div></div>'
        +'<div class="hero-comp__row"><label>X</label><input type="number" id="'+W.esc(id)+'-el-x" step="1" value="0"/><select id="'+W.esc(id)+'-el-xu"><option value="px">px</option><option value="%">%</option></select><label>Y</label><input type="number" id="'+W.esc(id)+'-el-y" step="1" value="0"/><select id="'+W.esc(id)+'-el-yu"><option value="px">px</option><option value="%">%</option></select></div>'
        +'<div class="hero-comp__row"><button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.heroCompResetEl(\''+W.esc(id)+'\')">Restablecer elemento</button><span class="hero-comp__hint" id="'+W.esc(id)+'-summary"></span></div>'
        +'</div>'
        +'<textarea class="rep-in" data-i="'+W.esc(idx)+'" data-k="composition" id="'+W.esc(id)+'-json" style="display:none">'+W.esc(JSON.stringify(comp))+'</textarea>'
        +'</div></details>';
    return h;
};
W.heroCompOutputs=function(id){
    var g=function(k){var e=document.getElementById(id+'-'+k);return e?e.value:''};
    var s=function(k,v){var e=document.getElementById(id+'-'+k+'-out');if(e)e.textContent=v};
    s('bg-zoom',(g('bg-zoom')||100)+'%');
    s('bg-x',(g('bg-x')||0)+'%');
    s('bg-y',(g('bg-y')||0)+'%');
    s('bg-fx',(g('bg-fx')||50)+'%');
    s('bg-fy',(g('bg-fy')||50)+'%');
};
W.heroCompPreview=function(id){
    var st=W.heroCompState[id];var img=document.getElementById(id+'-preview-img');if(!st||!img)return;
    var bg=st.comp.background[st.device]||{};
    var src=st.device==='mobile'?(st.item.mobile||st.item.desktop):st.item.desktop;
    if(src)img.src=src;
    img.style.objectFit=bg.fit||'cover';
    img.style.objectPosition=(bg.focal_x||50)+'% '+(bg.focal_y||50)+'%';
    img.style.transform='translate('+(bg.x||0)+'%,'+(bg.y||0)+'%) scale('+((bg.zoom||100)/100)+')';
    img.style.transformOrigin=(bg.focal_x||50)+'% '+(bg.focal_y||50)+'%';
};
W.heroCompSummary=function(id){
    var st=W.heroCompState[id];if(!st)return;
    var e=st.comp.elements[st.device][st.element]||{};
    var out=document.getElementById(id+'-summary');if(out)out.textContent=(st.device==='desktop'?'PC':'Móvil')+' · '+st.element+' · '+(e.mode==='manual'?('manual '+ (e.anchor||'')+' · X '+e.x+e.x_unit+' · Y '+e.y+e.y_unit):'automático');
};
W.heroCompSave=function(id){
    var st=W.heroCompState[id];if(!st)return;
    var g=function(k){var e=document.getElementById(id+'-'+k);return e?e.value:''};
    var bg=st.comp.background[st.device];
    bg.zoom=parseFloat(g('bg-zoom'))||100;bg.x=parseFloat(g('bg-x'))||0;bg.y=parseFloat(g('bg-y'))||0;
    bg.focal_x=parseFloat(g('bg-fx'));if(isNaN(bg.focal_x))bg.focal_x=50;
    bg.focal_y=parseFloat(g('bg-fy'));if(isNaN(bg.focal_y))bg.focal_y=50;
    bg.fit=g('bg-fit')==='contain'?'contain':'cover';
    var el=st.comp.elements[st.device][st.element];
    el.mode=g('el-mode')==='manual'?'manual':'auto';
    el.x=parseFloat(g('el-x'))||0;el.y=parseFloat(g('el-y'))||0;
    el.x_unit=g('el-xu')==='%'?'%':'px';el.y_unit=g('el-yu')==='%'?'%':'px';
    var ta=document.getElementById(id+'-json');if(ta)ta.value=JSON.stringify(st.comp);
    W.heroCompOutputs(id);W.heroCompPreview(id);W.heroCompSummary(id);
};
W.heroCompLoad=function(id){
    var st=W.heroCompState[id];if(!st)return;
    var g=function(k){var e=document.getElementById(id+'-'+k);return e?e.value:''};
    var set=function(k,v){var e=document.getElementById(id+'-'+k);if(e)e.value=v};
    set('device',st.device);set('el',st.element);
    var bg=st.comp.background[st.device]||{};
    set('bg-zoom',bg.zoom||100);set('bg-x',bg.x||0);set('bg-y',bg.y||0);
    set('bg-fx',bg.focal_x===undefined?50:bg.focal_x);set('bg-fy',bg.focal_y===undefined?50:bg.focal_y);
    set('bg-fit',bg.fit||'cover');
    var el=st.comp.elements[st.device][st.element]||{};
    set('el-mode',el.mode||'auto');set('el-x',el.x||0);set('el-y',el.y||0);
    set('el-xu',el.x_unit||'px');set('el-yu',el.y_unit||'px');
    var box=document.getElementById(id+'-anchor');
    if(box)box.querySelectorAll('[data-anchor]').forEach(function(b){b.classList.toggle('on',b.getAttribute('data-anchor')===(el.anchor||'top-left'))});
    W.heroCompOutputs(id);W.heroCompPreview(id);W.heroCompSummary(id);
};
W.heroCompAnchor=function(id,a){
    W.heroCompSave(id);var st=W.heroCompState[id];if(!st)return;
    var el=st.comp.elements[st.device][st.element];el.anchor=a;el.mode='manual';
    var ta=document.getElementById(id+'-json');if(ta)ta.value=JSON.stringify(st.comp);
    W.heroCompLoad(id);
};
W.heroCompCopy=function(id){
    W.heroCompSave(id);var st=W.heroCompState[id];if(!st)return;
    var cp=function(o){return JSON.parse(JSON.stringify(o));};
    st.comp.background.mobile=cp(st.comp.background.desktop);
    ['eyebrow','title','description','ctas'].forEach(function(k){st.comp.elements.mobile[k]=cp(st.comp.elements.desktop[k]);});
    var ta=document.getElementById(id+'-json');if(ta)ta.value=JSON.stringify(st.comp);
    W.heroCompLoad(id);
};
W.heroCompResetEl=function(id){
    var st=W.heroCompState[id];if(!st)return;
    st.comp.elements[st.device][st.element]={mode:'auto',anchor:'top-left',x:0,y:0,x_unit:'px',y_unit:'px'};
    var ta=document.getElementById(id+'-json');if(ta)ta.value=JSON.stringify(st.comp);
    W.heroCompLoad(id);
};
W.heroCompositionInit=function(root){
    if(!root)return;
    root.querySelectorAll('.hero-comp').forEach(function(box){
        if(box.getAttribute('data-comp-init')==='1')return;
        box.setAttribute('data-comp-init','1');
        var id=box.getAttribute('data-id');if(!id)return;
        var item={desktop:box.getAttribute('data-desktop')||'',mobile:box.getAttribute('data-mobile')||'',focal:box.getAttribute('data-focal')||'50% 50%'};
        var ta=document.getElementById(id+'-json');var comp=W.heroCompDefault(item);
        if(ta){try{comp=W.heroCompMerge({composition:JSON.parse(ta.value||'{}'),focal:item.focal})}catch(e){}}
        W.heroCompState[id]={comp:comp,device:'desktop',element:'title',item:item};
        var dev=document.getElementById(id+'-device');
        if(dev)dev.addEventListener('change',function(){W.heroCompSave(id);W.heroCompState[id].device=this.value;W.heroCompLoad(id);});
        var el=document.getElementById(id+'-el');
        if(el)el.addEventListener('change',function(){W.heroCompSave(id);W.heroCompState[id].element=this.value;W.heroCompLoad(id);});
        ['bg-zoom','bg-x','bg-y','bg-fx','bg-fy','bg-fit','el-mode','el-x','el-xu','el-y','el-yu'].forEach(function(k){
            var input=document.getElementById(id+'-'+k);if(!input)return;
            input.addEventListener('input',function(){W.heroCompSave(id)});
            input.addEventListener('change',function(){W.heroCompSave(id)});
        });
        W.heroCompLoad(id);
    });
};
W.heroThemeSchema=function(){
    var cfg=(W._heroTheme&&W._heroTheme.config)||{};
    var defaultCats=[
        {label:'Guitarras',slug:'guitarras',href:'/guitarras',image:'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/3_c0fcc64c-78ef-4675-b9c0-04cc0127a521.png?v=1748015983&width=640',enabled:1},
        {label:'Pianos',slug:'pianos',href:'/pianos',image:'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/1_82429ffb-d1db-43da-b2b2-f5d95dcb861a.png?v=1758898900&width=640',enabled:1},
        {label:'Acordeones',slug:'acordeon',href:'/acordeon',image:'https://cdn.shopify.com/s/files/1/0269/4178/4117/products/ACOR36.png?v=1717789153&width=640',enabled:1},
        {label:'Baterías',slug:'baterias',href:'/buscar?q=bateria',image:'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/1_04b0d32c-6821-410d-865d-c04aba234935.png?v=1776099590&width=640',enabled:1},
        {label:'Bajos',slug:'bajos',href:'/guitarras?sub=bajos',image:'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/BAJOELECFENDERSQDEBUTPBASSSUNBURTMOD0379700503.png?v=1791214002&width=640',enabled:1},
        {label:'Violines',slug:'violines',href:'/buscar?q=violin',image:'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/1_1addf835-272f-4619-a99c-b104923b7845.png?v=1764197651&width=640',enabled:1},
        {label:'Ukelele',slug:'ukelele',href:'/buscar?q=ukelele',image:'https://cdn.shopify.com/s/files/1/0269/4178/4117/products/Ukelele_florecer_miche-instrumentos_musicales_tienda_en_linea-1.jpg?v=1631538264&width=640',enabled:1},
        {label:'Saxofón',slug:'saxofon',href:'/buscar?q=saxofon',image:'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/ARTICULOSMICHE_3_211c9030-1f81-4ba1-b3d5-313d344763a3.png?v=1760458403&width=640',enabled:1},
        {label:'Timbales',slug:'timbales',href:'/buscar?q=timbal',image:'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/LP1415-KP_5a484e93-8e61-4402-a275-91ed107e38b0.webp?v=1727973167&width=640',enabled:1},
        {label:'Bongoes',slug:'bongoes',href:'/buscar?q=bongo',image:'https://cdn.shopify.com/s/files/1/0269/4178/4117/products/BON067.png?v=1586384906&width=640',enabled:1},
        {label:'Tumbadoras',slug:'tumbadoras',href:'/buscar?q=tumbadora',image:'https://cdn.shopify.com/s/files/1/0269/4178/4117/products/CONGACP1212CHERRYENFIBRAVIDRIOTUMBACONAROCONFORTMICHETIENDACON137.png?v=1692204700&width=640',enabled:1},
        {label:'Baquetas',slug:'baquetas',href:'/buscar?q=baqueta',image:'https://cdn.shopify.com/s/files/1/0269/4178/4117/products/BAQ039.png?v=1574455593&width=640',enabled:1},
        {label:'Micrófonos',slug:'microfono-miche',href:'/microfono-miche',image:'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/MICROFONOAUDIOTECHNICAAT2020CONDENSADOR.png?v=1791216440&width=640',enabled:1},
        {label:'Cabinas',slug:'cabinas',href:'/buscar?q=cabina',image:'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/CABINAACTIVAEVETX-15SPUSSUBWOOFERETX-15SP.png?v=1790694499&width=640',enabled:1},
        {label:'Amplificadores Fender',slug:'amplificadores-fender',href:'/buscar?q=amplificador%20fender',image:'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/2330300000_fen_amp_frt_1_nr.png?v=1741811461&width=640',enabled:1},
        {label:'Audio Profesional',slug:'amplificadores',href:'/amplificadores',image:'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/1_175f6da6-d872-4f86-8371-b7976ffb28bc.png?v=1771707885&width=640',enabled:1},
        {label:'Audífonos',slug:'audifonos',href:'/buscar?q=audifono',image:'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/AUDIFONOAUDIOTECHNICAATHM20XBTNEGROATHM20XBT.png?v=1790267571&width=640',enabled:1},
        {label:'Cabezas móviles',slug:'cabezas-moviles',href:'/buscar?q=cabeza%20movil',image:'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/FORMATODECREACIONART_9_71047ce9-a78f-42f3-bf86-d73b40f047c5.png?v=1785446032&width=640',enabled:1},
        {label:'Accesorios',slug:'cuerdas-guitarras',href:'/cuerdas-guitarras',image:'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/1_67a602cb-ddb7-41e0-b756-d3cb589f6893.png?v=1776956607&width=640',enabled:1}
    ];
    var catRows=(Array.isArray(cfg.categories)&&cfg.categories.length)?cfg.categories:defaultCats;
    var catOpts=[{value:'',label:'Sin foco'}];
    catRows.forEach(function(c){if(c&&c.slug)catOpts.push({value:c.slug,label:c.label||c.slug})});
    var defaultBgs=[
        {desktop:'/assets/uploads/wontia_5ddad38e1699f7db.webp',mobile:'/assets/uploads/wontia_5ddad38e1699f7db.webp',alt:'Primer plano de guitarra eléctrica',focal:'50% 45%'},
        {desktop:'/assets/uploads/wontia_2c020fab54677229.webp',mobile:'/assets/uploads/wontia_4a97a4f3251c0c73.png',alt:'Escenario SONIKA con guitarra',focal:'42% 50%'}
    ];
    var defaultSlides=defaultBgs.map(function(bg,i){
        return {id:'slide-'+(i+1),enabled:1,eyebrow:'SONIKA PRESENTA',title:'EL SONIDO\nSE *APODERA*.',title_size_desktop:100,title_size_desktop_unit:'%',title_size_mobile:100,title_size_mobile_unit:'%',composition:W.heroCompDefault({focal:bg.focal}),subtitle:'Tu música. Tu escenario. Tu momento.',cta_label:'Explorar Instrumentos',cta_url:'/guitarras',cta2_label:'Ver la Experiencia',cta2_video:'https://www.youtube.com/watch?v=dsD03vmVe30',cta2_url:'',desktop:bg.desktop,mobile:bg.mobile,alt:bg.alt,focal:bg.focal,overlay:55,accent:'#F20D16',instrument_focus:i===0?'guitarras':''};
    });
    return [
        {key:'slides',label:'Slides del Hero',type:'repeater',collapsible:true,default:defaultSlides,fields:[
            {key:'id',label:'ID interno (enlaces directos)',type:'text'},
            {key:'enabled',label:'Visible',type:'toggle'},
            {key:'eyebrow',label:'Eyebrow / texto superior (ej. SONIKA PRESENTA)',type:'text'},
            {key:'title',label:'Título principal (2 filas; usa *texto* para el acento)',type:'textarea'},
            {key:'title_size_desktop',label:'Tamaño del título en PC (solo el número)',type:'sizeunit',unitKey:'title_size_desktop_unit',units:{'%':'% (porcentaje)','pt':'pt (puntos)','px':'px (tamaño de letra)'},default:100,defaultUnit:'%',min:8,max:200,step:1},
            {key:'title_size_mobile',label:'Tamaño del título en móvil (solo el número)',type:'sizeunit',unitKey:'title_size_mobile_unit',units:{'%':'% (porcentaje)','pt':'pt (puntos)','px':'px (tamaño de letra)'},default:100,defaultUnit:'%',min:8,max:200,step:1},
            {key:'subtitle',label:'Descripción (ej. Equipos de las mejores marcas del mundo)',type:'textarea'},
            {key:'cta_label',label:'Botón principal',type:'text'},
            {key:'cta_url',label:'Enlace del botón principal',type:'text'},
            {key:'cta2_label',label:'Botón secundario',type:'text'},
            {key:'cta2_video',label:'Video YouTube del botón secundario (opcional)',type:'text'},
            {key:'cta2_url',label:'Enlace alternativo del botón secundario (si no hay video)',type:'text'},
            {key:'desktop',label:'Imagen escritorio',type:'image'},
            {key:'mobile',label:'Imagen móvil',type:'image'},
            {key:'alt',label:'Texto alternativo',type:'text'},
            {key:'composition',label:'Composición avanzada (fondo y elementos)',type:'composition'},
            {key:'overlay',label:'Overlay propio (%) — vacío = global',type:'number'},
            {key:'accent',label:'Color de acento del slide',type:'color',default:'#F20D16'},
            {key:'instrument_focus',label:'Instrumento a destacar en el carrusel',type:'select',options:catOpts}
        ]},
        {key:'autoplay',label:'Autoplay del carrusel (ms, 0 = off)',type:'number',default:9000,min:0,max:30000,step:500},
        {key:'_h_layout',label:'Comportamiento y proporción',type:'heading'},
        {key:'height',label:'Altura en escritorio (px)',type:'range',default:448,min:360,max:760,step:1},
        {key:'overlay',label:'Overlay global (%) — slides sin overlay propio',type:'range',default:55,min:0,max:85,step:1},
        {key:'focus_index',label:'Ficha destacada en escritorio (1–6)',type:'range',default:3,min:1,max:6,step:1},
        {key:'focus_index_mobile',label:'Ficha destacada en móvil (1–3)',type:'range',default:2,min:1,max:3,step:1},
        {key:'focus_scale',label:'Escala del destacado en escritorio (%)',type:'range',default:132,min:100,max:133,step:1},
        {key:'focus_scale_mobile',label:'Escala del destacado en móvil (%)',type:'range',default:132,min:100,max:135,step:1},
        {key:'edge_opacity',label:'Opacidad de extremos en escritorio (%)',type:'range',default:41,min:20,max:100,step:1},
        {key:'edge_scale',label:'Escala de fichas de extremos en escritorio (%)',type:'range',default:85,min:70,max:100,step:1},
        {key:'near_scale',label:'Escala de fichas intermedias en escritorio (%)',type:'range',default:90,min:80,max:100,step:1},
        {key:'show_categories',label:'Mostrar panel de instrumentos',type:'toggle',default:1},
        {key:'show_benefits',label:'Mostrar franja de beneficios',type:'toggle',default:1},
        {key:'_h_categories',label:'Instrumentos',type:'heading'},
        {key:'categories',label:'Instrumentos del panel',type:'repeater',collapsible:true,default:defaultCats,fields:[
            {key:'label',label:'Nombre',type:'text'},
            {key:'slug',label:'Clave / slug (opcional)',type:'text'},
            {key:'href',label:'Enlace',type:'text'},
            {key:'image',label:'Imagen (opcional)',type:'image'},
            {key:'enabled',label:'Visible',type:'toggle'}
        ]},
        {key:'instrument_image_ms',label:'Cambio de imagen de cada instrumento (ms)',type:'range',default:5000,min:2000,max:12000,step:500},
        {key:'instrument_label_max',label:'Tamaño máximo del nombre del instrumento (px)',type:'range',default:16,min:10,max:24,step:1},
        {key:'_h_mobile',label:'Experiencia móvil',type:'heading'},
        {key:'mobile_height',label:'Altura fija del Hero en móvil (px)',type:'range',default:580,min:480,max:720,step:10,hint:'Una sola altura para todos los slides. El contenido se adapta dentro del área segura.'},
        {key:'mobile_description_lines',label:'Máximo de líneas de la descripción en móvil',type:'range',default:2,min:1,max:4,step:1,hint:'La descripción se recorta visualmente después de estas líneas.'},
        {key:'mobile_copy_offset',label:'Bajar bloque de texto',type:'number',default:0,min:-100,max:240,step:1,hint:'Positivo baja; negativo sube. La unidad se elige al lado.'},
        {key:'mobile_copy_offset_unit',label:'Unidad del texto',type:'select',default:'px',options:{px:'px',pt:'pt',cm:'cm'}},
        {key:'mobile_cards_offset',label:'Bajar carrusel de instrumentos',type:'number',default:0,min:-100,max:240,step:1,hint:'Mueve solo las fichas de instrumentos dentro del Hero.'},
        {key:'mobile_cards_offset_unit',label:'Unidad del carrusel',type:'select',default:'px',options:{px:'px',pt:'pt',cm:'cm'}},
        {key:'mobile_group_scale',label:'Escala del bloque de texto (%)',type:'range',default:100,min:60,max:130,step:1},
        {key:'mobile_eyebrow_scale',label:'Escala del eyebrow (%)',type:'range',default:100,min:60,max:140,step:1},
        {key:'mobile_title_scale',label:'Escala del título (%)',type:'range',default:100,min:60,max:140,step:1},
        {key:'mobile_sub_scale',label:'Escala de la descripción (%)',type:'range',default:100,min:60,max:140,step:1},
        {key:'mobile_cta_scale',label:'Escala del CTA (%)',type:'range',default:100,min:60,max:140,step:1},
        {key:'mobile_cta_width_mode',label:'Ancho del CTA en móvil',type:'select',default:'full',options:{full:'Ancho completo',auto:'Automático',custom:'Personalizado'}},
        {key:'mobile_cta_width_value',label:'Ancho personalizado del CTA',type:'number',default:100,min:20,max:340,step:1,hint:'En % va de 20 a 100; en px de 40 a 340.'},
        {key:'mobile_cta_width_unit',label:'Unidad del ancho del CTA',type:'select',default:'%',options:{'%':'%',px:'px'}},
        {key:'_h_benefits',label:'Beneficios',type:'heading'},
        {key:'benefits',label:'Franja de beneficios',type:'repeater',collapsible:true,default:[
            {icon:'truck',title:'Envío rápido',text:'a todo Colombia',enabled:1},
            {icon:'shield',title:'Compra segura',text:'pagos 100% protegidos',enabled:1},
            {icon:'card',title:'Cuotas fáciles',text:'con Sistecrédito y más',enabled:1},
            {icon:'headset',title:'Asesoría experta',text:'antes y después de tu compra',enabled:1}
        ],fields:[
            {key:'icon',label:'Icono',type:'icon'},
            {key:'title',label:'Título',type:'text'},
            {key:'text',label:'Subtítulo',type:'text'},
            {key:'enabled',label:'Visible',type:'toggle'}
        ]}
    ];
};
W.heroThemeApply=async function(){
    var sel=document.getElementById('hero-theme-select'); if(!sel)return;
    var r=await W.api('/api/v1/admin/hero/theme',{method:'POST',body:{active_theme:sel.value}});
    if(r&&r.ok){W.notify('Theme activado.','success');W.renderHero();}else W.notify((r&&r.message)||'No se pudo activar el theme','error');
};
W.heroThemeClassic=async function(){
    if(!confirm('¿Volver al theme clásico? La configuración del nuevo theme se conservará.'))return;
    var r=await W.api('/api/v1/admin/hero/theme',{method:'POST',body:{active_theme:'classic'}});
    if(r&&r.ok){W.notify('Theme clásico activado.','success');W.renderHero();}else W.notify((r&&r.message)||'No se pudo volver al theme clásico','error');
};
W.heroThemeConfigOpen=function(){window.location.hash='#hero/config';};
W.renderHeroThemePage=async function(){
    var app=document.getElementById('wontia-app');
    if(!app)return;
    app.innerHTML='<div style="text-align:center;padding:60px;color:var(--w-muted)">Cargando editor del Hero...</div>';
    if(!W._heroTheme||!W._heroTheme.themes){
        try{
            var boot=await W.api('/api/v1/admin/hero');
            if(boot&&boot.data){W._heroTheme=boot.data.theme||{};W._heroSlides=boot.data.slides||[];}
        }catch(e){}
    }
    var theme=W._heroTheme||{};
    var themes=theme.themes||{};
    var active=theme.active||'classic';
    var activeName=(themes[active]&&themes[active].name)||active;
    var schema=W.heroThemeSchema(); W._heroThemeSchema=schema;
    var useDefaults=!!W._heroThemeConfigUseDefaults; W._heroThemeConfigUseDefaults=false;
    var values=useDefaults?{}:(theme.config||{});
    var def=function(k){var f=schema.filter(function(x){return x.key===k})[0];return f&&f.default!==undefined?f.default:''};
    var val=function(k){var v=values[k];return (v!==undefined&&v!==null&&v!=='')?v:def(k)};
    var repCount=function(k){var a=val(k);if(typeof a==='string'){try{a=JSON.parse(a)}catch(e){a=[]}}return Array.isArray(a)?a.length:0};
    var fieldsOf=function(keys){return keys.map(function(k){return schema.filter(function(f){return f.key===k&&f.type!=='heading'})[0]}).filter(Boolean)};
    var groups=[
        {id:'slides',title:'Slides del Hero',desc:'Imagen, copy, botones e instrumento destacado por slide.',help:'Cada slide actualiza la imagen, el eyebrow, el título, la descripción, los CTAs y, si lo eliges, el instrumento que enfoca el carrusel vertical. La estructura del Hero y el panel de instrumentos se mantienen.',icon:'light',keys:['slides','autoplay'],badge:repCount('slides')+' slides'},
        {id:'layout',title:'Proporción y foco',desc:'Altura, overlay, ficha destacada y transparencia.',help:'El foco es la ficha que se agranda. En escritorio se cuenta de 1 a 6; en móvil de 1 a 3. La transparencia y las escalas laterales solo se aplican en PC.',icon:'card',keys:['height','overlay','focus_index','focus_index_mobile','focus_scale','focus_scale_mobile','edge_opacity','edge_scale','near_scale'],badge:val('height')+' px · '+val('focus_scale')+'%'},
        {id:'instruments',title:'Instrumentos',desc:'Fichas, imágenes rotativas, flechas y tamaño de los nombres.',help:'Cada ficha rota hasta 3 imágenes del catálogo. El nombre crece hasta llenar el ancho disponible; los nombres que ya lo llenan se mantienen. Las flechas superior e inferior permiten recorrer toda la lista sin reiniciar el carrusel. La ficha del instrumento elegido en cada slide se resalta.',icon:'guitar',keys:['show_categories','categories','instrument_image_ms','instrument_label_max'],badge:repCount('categories')+' fichas'},
        {id:'benefits',title:'Beneficios',desc:'Franja inferior con los cuatro beneficios.',help:'Cada beneficio tiene icono, título y texto. La secuencia activa avanza cada 10 segundos.',icon:'truck',keys:['show_benefits','benefits'],badge:repCount('benefits')+' ítems'},
        {id:'mobile',title:'Móvil · posición y escala',desc:'Baja el texto y el carrusel, escala cada pieza y define el ancho del CTA.',help:'Mueve el bloque de texto y las fichas de instrumentos sin cambiar la altura del Hero ni empujar otras secciones. Usa el teléfono para arrastrar las asas o los controles numéricos. Las escalas son proporcionales: no refluyen el diseño. El CTA puede ir a ancho completo, automático o personalizado en %/px.',icon:'phone',keys:['mobile_height','mobile_description_lines','mobile_copy_offset','mobile_copy_offset_unit','mobile_cards_offset','mobile_cards_offset_unit','mobile_group_scale','mobile_eyebrow_scale','mobile_title_scale','mobile_sub_scale','mobile_cta_scale','mobile_cta_width_mode','mobile_cta_width_value','mobile_cta_width_unit'],badge:'estudio táctil'}
    ];
    var used={};groups.forEach(function(g){g.keys.forEach(function(k){used[k]=1})});
    var extras=schema.filter(function(f){return f.type!=='heading'&&!used[f.key]});
    if(extras.length)groups.push({id:'extras',title:'Otros ajustes',desc:'Campos adicionales del theme.',help:'Opciones avanzadas.',icon:'info',keys:extras.map(function(f){return f.key}),badge:extras.length+' campos'});
    var firstOpen=W._heroConfigOpenSection||'slides';
    var accHtml=groups.map(function(g,idx){
        var fields=fieldsOf(g.keys); if(!fields.length)return '';
        var open=(g.id===firstOpen)||(!W._heroConfigOpenSection&&idx===0);
        var extra=g.id==='mobile'?W.heroMobileStudio(values):'';
        return '<details class="hte-acc" data-hte-section="'+g.id+'"'+(open?' open':'')+'>'
            +'<summary><span class="hte-ic">'+(W.iconSvg(g.icon,18)||'')+'</span><span class="hte-st"><b>'+W.esc(g.title)+'</b><small>'+W.esc(g.desc)+'</small></span><span class="hte-badge">'+W.esc(g.badge)+'</span><span class="hte-chev">⌄</span></summary>'
            +'<div class="hte-body"><div class="hte-desc">'+W.esc(g.help)+'</div>'+W.renderSchemaFields(fields,values)+extra+'</div></details>';
    }).join('');
    var status=useDefaults?'Valores por defecto cargados (sin guardar)':'Sin cambios';
    var statusClass=useDefaults?' is-dirty':'';
    var h='<div class="hte-page" id="hte-page">'
        +'<div class="hte-head"><div><div class="hte-crumb"><a href="#hero">SONIKA Hero Builder</a><span>/</span><span>Contenido del theme</span></div>'
        +'<h2 class="hte-title">'+W.esc(activeName)+'</h2>'
        +'<p class="hte-sub">Edita el Hero por secciones. Abre una categoría para ver sus campos; los cambios se guardan en este theme sin alterar el theme clásico.</p>'
        +'<div class="hte-status'+statusClass+'" id="ht-status"><span class="hte-dot"></span><span id="ht-status-text">'+W.esc(status)+'</span></div></div>'
        +'<div class="hte-actions"><a class="w-btn w-btn-secondary" href="/" target="_blank" rel="noopener">Ver Hero ↗</a><button type="button" class="w-btn w-btn-secondary" onclick="wontia.heroThemeConfigReset()">Restaurar</button><button type="button" class="w-btn w-btn-primary" id="hero-theme-save" onclick="wontia.heroThemeConfigPageSave()">Guardar cambios</button></div></div>'
        +'<div class="hte-grid"><div class="hte-main">'+accHtml+'</div>'
        +'<aside class="hte-side">'
        +'<div class="hte-side-card"><div class="hte-eyebrow">Theme activo</div><div class="hte-theme">'+W.esc(activeName)+'</div><div class="hte-note">Los cambios se guardan en este theme. El theme clásico y sus slides se conservan intactos.</div></div>'
        +'<div class="hte-side-card"><div class="hte-side-title">Resumen rápido</div><div class="hte-stats">'
        +'<div class="hte-stat"><span>Altura</span><b id="ht-sum-height">'+W.esc(val('height'))+' px</b></div>'
        +'<div class="hte-stat"><span>Foco PC / móvil</span><b id="ht-sum-focus">'+W.esc(val('focus_index'))+' / '+W.esc(val('focus_index_mobile'))+'</b></div>'
        +'<div class="hte-stat"><span>Escala PC / móvil</span><b id="ht-sum-scale">'+W.esc(val('focus_scale'))+'% / '+W.esc(val('focus_scale_mobile'))+'%</b></div>'
        +'<div class="hte-stat"><span>Ajuste móvil</span><b id="ht-sum-mobile">'+W.esc(val('mobile_copy_offset'))+W.esc(val('mobile_copy_offset_unit'))+' / '+W.esc(val('mobile_cards_offset'))+W.esc(val('mobile_cards_offset_unit'))+'</b></div>'
        +'<div class="hte-stat"><span>Extremos PC</span><b id="ht-sum-edge">'+W.esc(val('edge_opacity'))+'%</b></div>'
        +'<div class="hte-stat"><span>Laterales PC</span><b id="ht-sum-sides">'+W.esc(val('edge_scale'))+'% / '+W.esc(val('near_scale'))+'%</b></div>'
        +'<div class="hte-stat"><span>Instrumentos</span><b id="ht-sum-inst">'+repCount('categories')+' fichas</b></div>'
        +'<div class="hte-stat"><span>Beneficios</span><b id="ht-sum-ben">'+repCount('benefits')+' ítems</b></div>'
        +'<div class="hte-stat"><span>Slides</span><b id="ht-sum-slides">'+repCount('slides')+' slides</b></div>'
        +'</div></div>'
        +'<div class="hte-side-card hte-tip"><b>Consejo</b><br>Cada slide puede enfocar un instrumento del carrusel vertical. El autoplay, las flechas y los dots cambian solo el contenido del Hero; la estructura y el panel de instrumentos se mantienen.</div>'
        +'</aside></div></div>';
    app.innerHTML=h;
    if(W.repInit)W.repInit(schema);
    W.heroThemeSyncSummary();
    var page=document.getElementById('hte-page');
    if(page){
        page.addEventListener('input',function(e){W.heroThemeMarkDirty();W.heroThemeSyncSummary();if(e.target&&e.target.closest&&e.target.closest('[data-hte-section="mobile"]'))W.heroMobileStudioApply();});
        page.addEventListener('change',function(e){W.heroThemeMarkDirty();W.heroThemeSyncSummary();if(e.target&&e.target.closest&&e.target.closest('[data-hte-section="mobile"]'))W.heroMobileStudioApply();});
        page.addEventListener('click',function(e){var t=e.target;if(t&&t.closest&&t.closest('.w-field-reset,.w-rep button,.w-rep .w-btn')){W.heroThemeMarkDirty();setTimeout(W.heroThemeSyncSummary,0);}});
        page.querySelectorAll('details[data-hte-section]').forEach(function(d){d.addEventListener('toggle',function(){if(d.open){W._heroConfigOpenSection=d.getAttribute('data-hte-section');page.querySelectorAll('details[data-hte-section]').forEach(function(o){if(o!==d)o.open=false;});if(d.getAttribute('data-hte-section')==='mobile')setTimeout(W.heroMobileStudioApply,0);}});});
    }
};
W.heroThemeSyncSummary=function(){
    var get=function(k){var el=document.getElementById('secf-'+k);if(!el)return null;if(el.type==='checkbox')return el.checked?1:0;return el.value};
    var set=function(id,v){var el=document.getElementById(id);if(el)el.textContent=v};
    set('ht-sum-height',(get('height')||'448')+' px');
    set('ht-sum-focus',(get('focus_index')||'3')+' / '+(get('focus_index_mobile')||'2'));
    set('ht-sum-scale',(get('focus_scale')||'132')+'% / '+(get('focus_scale_mobile')||'132')+'%');
    set('ht-sum-mobile',(get('mobile_copy_offset')||'0')+(get('mobile_copy_offset_unit')||'px')+' / '+(get('mobile_cards_offset')||'0')+(get('mobile_cards_offset_unit')||'px'));
    set('ht-sum-edge',(get('edge_opacity')||'41')+'%');
    set('ht-sum-sides',(get('edge_scale')||'85')+'% / '+(get('near_scale')||'90')+'%');
    var count=function(key){var el=document.getElementById('rep-items-'+key);return el?el.querySelectorAll('.w-rep-card').length:0};
    set('ht-sum-inst',count('categories')+' fichas');
    set('ht-sum-ben',count('benefits')+' ítems');
    set('ht-sum-slides',count('slides')+' slides');
};
W.heroThemeMarkDirty=function(){
    var st=document.getElementById('ht-status'),txt=document.getElementById('ht-status-text');
    if(st)st.className='hte-status is-dirty';
    if(txt)txt.textContent='Cambios sin guardar';
};
W.heroThemeConfigPageSave=async function(){
    var btn=document.getElementById('hero-theme-save');
    var schema=W._heroThemeSchema||W.heroThemeSchema();
    var old=btn?btn.textContent:'';
    if(btn){btn.disabled=true;btn.textContent='Guardando…';}
    var values=W.collectSchemaFields(schema);
    var r=await W.api('/api/v1/admin/hero/theme',{method:'POST',body:{config:values}});
    if(btn){btn.disabled=false;btn.textContent=old||'Guardar cambios';}
    if(r&&r.ok){
        if(r.data&&r.data.config&&W._heroTheme)W._heroTheme.config=r.data.config;
        var st=document.getElementById('ht-status'),txt=document.getElementById('ht-status-text');
        if(st)st.className='hte-status is-saved';
        if(txt)txt.textContent='Guardado '+new Date().toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
        W.notify('Contenido del theme guardado.','success');
    }else{
        W.notify((r&&r.message)||'No se pudo guardar la configuración','error');
    }
};
W.heroMobileStudio=function(){
    return '<div class="hte-mobile-studio" data-mobile-studio>'
        +'<div class="hte-mobile-bar"><div><b>Estudio móvil</b><span>390 × 430 px · arrastra las asas dentro del teléfono</span></div>'
        +'<div class="hte-mobile-presets">'
        +'<button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.heroMobilePreset(\'compact\')">Compacto</button>'
        +'<button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.heroMobilePreset(\'balanced\')">Equilibrado</button>'
        +'<button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.heroMobilePreset(\'editorial\')">Editorial</button>'
        +'<button type="button" class="w-btn w-btn-primary w-btn-sm" onclick="wontia.heroMobileAutoFit()">Auto-ajustar</button>'
        +'<button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.heroMobilePreset(\'reset\')">Restablecer</button>'
        +'</div></div>'
        +'<div class="hte-mobile-grid"><div class="hte-phone"><iframe id="hte-mobile-frame" src="/" title="Vista previa móvil del Hero" loading="lazy" onload="wontia.heroMobileStudioReady(this)"></iframe></div>'
        +'<div class="hte-mobile-side"><div class="hte-mobile-readout" id="hte-mobile-readout">Cargando vista previa…</div>'
        +'<div class="hte-mobile-legend"><span class="hte-mobile-dot hte-mobile-dot--copy"></span>Texto <span class="hte-mobile-dot hte-mobile-dot--cards"></span>Instrumentos</div>'
        +'<div class="hte-mobile-note">Las asas y escalas no cambian la altura fija. La altura se controla con <b>Altura fija del Hero en móvil</b>. Si el contenido no cabe, el medidor lo marca en rojo.</div>'
        +'<a class="w-btn w-btn-secondary w-btn-sm" href="/" target="_blank" rel="noopener" style="margin-top:10px">Abrir Hero ↗</a>'
        +'</div></div></div>';
};

W.heroMobileFrame=function(){return document.getElementById('hte-mobile-frame')};
W.heroMobileField=function(k,d){var el=document.getElementById('secf-'+k);return el?el.value:(d===undefined?'':d)};
W.heroMobilePx=function(value,unit){value=parseFloat(value)||0;if(unit==='pt')return value*96/72;if(unit==='cm')return value*96/2.54;return value};
W.heroMobileFromPx=function(px,unit){if(unit==='pt')return px*72/96;if(unit==='cm')return px*2.54/96;return px};
W.heroMobileClamp=function(px){return Math.max(-100,Math.min(240,px))};
W.heroMobileReadPx=function(key){return W.heroMobilePx(W.heroMobileField(key,0),W.heroMobileField(key+'_unit','px'))};
W.heroMobileWritePx=function(key,px){
    px=W.heroMobileClamp(px);
    var unit=W.heroMobileField(key+'_unit','px');
    var input=document.getElementById('secf-'+key);
    if(input)input.value=Math.round(W.heroMobileFromPx(px,unit)*10)/10;
    W.heroThemeMarkDirty();
    W.heroMobileStudioApply();
};
W.heroMobileReadout=function(html){var el=document.getElementById('hte-mobile-readout');if(el)el.innerHTML=html;};

W.heroMobileStudioReady=function(frame){
    try{
        if(!frame||!frame.contentDocument)return;
        var run=function(){
            try{
                W.heroMobileIsolate(frame);
                W.heroMobileUnitInit();
                W.heroMobileStudioApply();
            }catch(e){}
        };
        run();
        setTimeout(run,700);
        setTimeout(run,1800);
    }catch(e){
        W.heroMobileReadout('<div class="hte-mobile-warn">No se pudo cargar la vista previa. Abre el Hero en otra pestaña para revisarlo.</div>');
    }
};

W.heroMobileIsolate=function(frame){
    var doc=frame.contentDocument, win=frame.contentWindow;
    if(!doc)return;
    var hero=doc.querySelector('.sth');
    if(!hero){
        W.heroMobileReadout('<div class="hte-mobile-warn">Activa el theme “El Sonido Se Apodera” para previsualizar los ajustes móviles.</div>');
        return;
    }
    var node=hero;
    while(node&&node.parentElement&&node.parentElement!==doc.body){
        var parent=node.parentElement;
        Array.prototype.forEach.call(parent.children,function(child){
            if(child===node||child.contains(hero))return;
            if(child.tagName==='SCRIPT'||child.tagName==='STYLE'||child.tagName==='LINK')return;
            child.style.display='none';
        });
        node=parent;
    }
    Array.prototype.forEach.call(doc.body.children,function(child){
        if(child.contains(hero))return;
        if(child.tagName==='SCRIPT'||child.tagName==='STYLE'||child.tagName==='LINK')return;
        child.style.display='none';
    });
    var all=doc.querySelectorAll('body *');
    for(var i=0;i<all.length;i++){
        var el=all[i];
        if(hero.contains(el))continue;
        try{
            var cs=win.getComputedStyle(el);
            if(cs&&cs.position==='fixed')el.style.display='none';
        }catch(e){}
    }
    doc.body.style.background='#07080d';
    doc.body.style.overflow='hidden';
    doc.documentElement.style.overflow='hidden';
    doc.body.style.paddingTop='0';
    hero.style.marginTop='0';
    try{
        var pauseBtn=hero.querySelector('[data-sth-pause]');
        if(pauseBtn&&pauseBtn.getAttribute('aria-pressed')!=='true')pauseBtn.click();
    }catch(e){}
    var style=doc.getElementById('hte-mobile-isolate-style');
    if(!style){
        style=doc.createElement('style');
        style.id='hte-mobile-isolate-style';
        doc.head.appendChild(style);
    }
    style.textContent='html,body{background:#07080d!important;overflow:hidden!important}'
        +'.sth{margin:0!important}'
        +'.sth__copy,.sth__cats{position:relative!important}'
        +'.hte-mobile-grip{position:absolute;right:3px;z-index:99;display:inline-flex;align-items:center;gap:3px;padding:4px 7px;border-radius:999px;background:rgba(242,13,22,.92);color:#fff;font:800 8px/1 Sora,system-ui,sans-serif;letter-spacing:.04em;text-transform:uppercase;cursor:ns-resize;touch-action:none;user-select:none;box-shadow:0 6px 18px rgba(0,0,0,.35);border:1px solid rgba(255,255,255,.35)}'
        +'.hte-mobile-grip--copy{top:4px}'
        +'.hte-mobile-grip--cards{top:50%;background:rgba(16,29,58,.94);transform:translateY(-50%)}'
        +'.hte-mobile-grip:before{content:"\\2195";font-size:12px;line-height:1}'
        +'.hte-mobile-grip:focus-visible{outline:2px solid #fff;outline-offset:2px}';
    try{win.scrollTo(0,0)}catch(e){}
};

W.heroMobileGrips=function(doc){
    if(!doc)return;
    var copy=doc.querySelector('.sth__copy');
    var cats=doc.querySelector('.sth__cats');
    if(copy&&!copy.querySelector('[data-mobile-grip="copy"]')){
        var gc=doc.createElement('div');
        gc.className='hte-mobile-grip hte-mobile-grip--copy';
        gc.setAttribute('data-mobile-grip','copy');
        gc.setAttribute('role','slider');
        gc.setAttribute('aria-label','Mover el texto del Hero');
        gc.innerHTML='Texto';
        copy.appendChild(gc);
        W.heroMobileBindGrip(gc,'mobile_copy_offset');
    }
    if(cats&&!cats.querySelector('[data-mobile-grip="cards"]')){
        var gk=doc.createElement('div');
        gk.className='hte-mobile-grip hte-mobile-grip--cards';
        gk.setAttribute('data-mobile-grip','cards');
        gk.setAttribute('role','slider');
        gk.setAttribute('aria-label','Mover el carrusel de instrumentos');
        gk.innerHTML='Instrumentos';
        cats.appendChild(gk);
        W.heroMobileBindGrip(gk,'mobile_cards_offset');
    }
};

W.heroMobileBindGrip=function(el,key){
    var startY=0,startPx=0,on=false;
    el.setAttribute('tabindex','0');
    el.addEventListener('pointerdown',function(e){
        if(e.button!==undefined&&e.button!==0)return;
        on=true;startY=e.clientY;startPx=W.heroMobileReadPx(key);
        try{el.setPointerCapture(e.pointerId)}catch(err){}
        e.preventDefault();e.stopPropagation();
    });
    el.addEventListener('pointermove',function(e){
        if(!on)return;
        W.heroMobileWritePx(key,startPx+(e.clientY-startY));
        e.preventDefault();e.stopPropagation();
    });
    var end=function(e){on=false;try{el.releasePointerCapture(e.pointerId)}catch(err){}};
    el.addEventListener('pointerup',end);
    el.addEventListener('pointercancel',end);
    el.addEventListener('keydown',function(e){
        if(e.key!=='ArrowUp'&&e.key!=='ArrowDown')return;
        W.heroMobileWritePx(key,W.heroMobileReadPx(key)+(e.key==='ArrowDown'?2:-2));
        e.preventDefault();
    });
};

W.heroMobileStudioApply=function(){
    var frame=W.heroMobileFrame();
    if(!frame)return;
    var doc;
    try{doc=frame.contentDocument}catch(e){W.heroMobileReadout('<div class="hte-mobile-warn">La vista previa no está disponible. Abre el Hero en otra pestaña.</div>');return}
    if(!doc)return;
    var hero=doc.querySelector('.sth');
    if(!hero){W.heroMobileReadout('<div class="hte-mobile-warn">Activa el theme “El Sonido Se Apodera” para usar el estudio móvil.</div>');return;}
    var mh=parseFloat(W.heroMobileField('mobile_height',580))||580;
    var ml=parseInt(W.heroMobileField('mobile_description_lines',2),10)||2;
    hero.style.setProperty('--sth-m-h',Math.max(480,Math.min(720,mh))+'px');
    hero.style.setProperty('--sth-m-sub-lines',Math.max(1,Math.min(4,ml)));
    var copyPx=W.heroMobileClamp(W.heroMobileReadPx('mobile_copy_offset'));
    var cardsPx=W.heroMobileClamp(W.heroMobileReadPx('mobile_cards_offset'));
    hero.style.setProperty('--sth-m-copy-y',copyPx+'px');
    hero.style.setProperty('--sth-m-cards-y',cardsPx+'px');
    hero.style.setProperty('--sth-m-copy-scale',(parseFloat(W.heroMobileField('mobile_group_scale',100))||100)/100);
    hero.style.setProperty('--sth-m-eyebrow-scale',(parseFloat(W.heroMobileField('mobile_eyebrow_scale',100))||100)/100);
    hero.style.setProperty('--sth-m-title-scale',(parseFloat(W.heroMobileField('mobile_title_scale',100))||100)/100);
    hero.style.setProperty('--sth-m-sub-scale',(parseFloat(W.heroMobileField('mobile_sub_scale',100))||100)/100);
    hero.style.setProperty('--sth-m-cta-scale',(parseFloat(W.heroMobileField('mobile_cta_scale',100))||100)/100);
    var mode=W.heroMobileField('mobile_cta_width_mode','full');
    hero.classList.remove('sth--m-cta-full','sth--m-cta-auto','sth--m-cta-custom');
    hero.classList.add('sth--m-cta-'+mode);
    var ctaUnit=W.heroMobileField('mobile_cta_width_unit','%');
    var ctaVal=parseFloat(W.heroMobileField('mobile_cta_width_value',100))||100;
    hero.style.setProperty('--sth-m-cta-w',ctaVal+ctaUnit);
    W.heroMobileGrips(doc);
    var stage=doc.querySelector('.sth__stage')||hero;
    var copy=doc.querySelector('.sth__copy');
    var cats=doc.querySelector('.sth__cats');
    var nav=doc.querySelector('.sth__nav');
    var sr=stage.getBoundingClientRect();
    var bottom=Math.max(
        copy?copy.getBoundingClientRect().bottom:sr.top,
        cats?cats.getBoundingClientRect().bottom:sr.top,
        nav?nav.getBoundingClientRect().bottom:sr.top
    );
    var copyTopOverflow=0;
    if(copy){
        var copyTop=copy.getBoundingClientRect().top;
        var contentTop=copyTop;
        Array.prototype.forEach.call(copy.children,function(el){
            if(!el||el.hidden)return;
            var cr=el.getBoundingClientRect();
            if(cr.height<=0)return;
            contentTop=Math.min(contentTop,cr.top);
        });
        copyTopOverflow=Math.max(0,Math.round(copyTop-contentTop));
    }
    var copyBottomOverflow=copy?Math.max(0,Math.round(copy.scrollHeight-copy.clientHeight)):0;
    var copyOverflow=Math.max(copyTopOverflow,copyBottomOverflow);
    var overflow=Math.max(Math.round(bottom-sr.bottom),copyOverflow);
    var topGap=Math.round((copy?copy.getBoundingClientRect().top:sr.top)-sr.top);
    var html='<div class="hte-mobile-metrics"><span>Altura <b>'+Math.round(mh)+' px</b></span><span>Texto <b>'+Math.round(copyPx)+' px</b></span><span>Carrusel <b>'+Math.round(cardsPx)+' px</b></span><span>Imagen visible <b>'+Math.max(0,topGap)+' px</b></span></div>';
    html+=overflow>0
        ? '<div class="hte-mobile-warn">Atención: el contenido se recorta '+overflow+' px. Reduce el tamaño del título, la descripción o la escala.</div>'
        : '<div class="hte-mobile-ok">Dentro del área segura · '+Math.max(0,-overflow)+' px libres</div>';
    W.heroMobileReadout(html);
};

W.heroMobileUnitInit=function(){
    var initLength=function(key){
        var input=document.getElementById('secf-'+key);
        var unit=document.getElementById('secf-'+key+'_unit');
        if(!input||!unit||unit.getAttribute('data-mobile-init')==='1')return;
        unit.setAttribute('data-mobile-init','1');
        unit.setAttribute('data-prev-unit',unit.value);
        var applyLimits=function(){
            if(unit.value==='pt'){input.min=-75;input.max=180;input.step=0.5;}
            else if(unit.value==='cm'){input.min=-2.65;input.max=6.35;input.step=0.1;}
            else{input.min=-100;input.max=240;input.step=1;}
        };
        applyLimits();
        unit.addEventListener('change',function(){
            var old=unit.getAttribute('data-prev-unit')||unit.value;
            var px=W.heroMobilePx(input.value,old);
            input.value=Math.round(W.heroMobileFromPx(px,unit.value)*10)/10;
            unit.setAttribute('data-prev-unit',unit.value);
            applyLimits();
            W.heroMobileStudioApply();
        });
        input.addEventListener('input',function(){W.heroMobileStudioApply()});
    };
    initLength('mobile_copy_offset');
    initLength('mobile_cards_offset');
    var mode=document.getElementById('secf-mobile_cta_width_mode');
    var value=document.getElementById('secf-mobile_cta_width_value');
    var unit=document.getElementById('secf-mobile_cta_width_unit');
    if(mode&&value&&unit&&mode.getAttribute('data-mobile-init')!=='1'){
        mode.setAttribute('data-mobile-init','1');
        var applyCta=function(){
            if(unit.value==='%'){value.min=20;value.max=100;value.step=1;}
            else{value.min=40;value.max=340;value.step=1;}
            var disabled=mode.value!=='custom';
            value.disabled=disabled;unit.disabled=disabled;
            var gv=value.closest('.w-form-group'),gu=unit.closest('.w-form-group');
            if(gv)gv.style.opacity=disabled?'.45':'1';
            if(gu)gu.style.opacity=disabled?'.45':'1';
        };
        applyCta();
        mode.addEventListener('change',function(){applyCta();W.heroMobileStudioApply()});
        unit.addEventListener('change',function(){applyCta();W.heroMobileStudioApply()});
        value.addEventListener('input',function(){W.heroMobileStudioApply()});
    }
};

W.heroMobileAutoFit=function(){
    var frame=W.heroMobileFrame();
    if(!frame)return;
    var doc;
    try{doc=frame.contentDocument}catch(e){return}
    if(!doc)return;
    var hero=doc.querySelector('.sth');
    if(!hero)return;
    hero.style.setProperty('--sth-m-copy-y','0px');
    hero.style.setProperty('--sth-m-cards-y','0px');
    void hero.offsetHeight;
    var stage=doc.querySelector('.sth__stage')||hero;
    var copy=doc.querySelector('.sth__copy');
    var cats=doc.querySelector('.sth__cats');
    var nav=doc.querySelector('.sth__nav');
    var sr=stage.getBoundingClientRect();
    var bottom=Math.max(
        copy?copy.getBoundingClientRect().bottom:sr.top,
        cats?cats.getBoundingClientRect().bottom:sr.top,
        nav?nav.getBoundingClientRect().bottom:sr.top
    );
    var free=Math.max(0,Math.min(240,Math.floor(sr.bottom-bottom-8)));
    W.heroMobileWritePx('mobile_copy_offset',free);
    W.heroMobileWritePx('mobile_cards_offset',free);
    W.heroThemeSyncSummary();
};

W.heroMobilePreset=function(name){
    var sets={
        compact:{mobile_height:520,mobile_description_lines:2,mobile_copy_offset:8,mobile_cards_offset:0,mobile_copy_offset_unit:'px',mobile_cards_offset_unit:'px',mobile_group_scale:92,mobile_eyebrow_scale:95,mobile_title_scale:90,mobile_sub_scale:95,mobile_cta_scale:95,mobile_cta_width_mode:'auto',mobile_cta_width_value:100,mobile_cta_width_unit:'%'},
        balanced:{mobile_height:580,mobile_description_lines:2,mobile_copy_offset:24,mobile_cards_offset:12,mobile_copy_offset_unit:'px',mobile_cards_offset_unit:'px',mobile_group_scale:100,mobile_eyebrow_scale:100,mobile_title_scale:100,mobile_sub_scale:100,mobile_cta_scale:100,mobile_cta_width_mode:'full',mobile_cta_width_value:100,mobile_cta_width_unit:'%'},
        editorial:{mobile_height:640,mobile_description_lines:3,mobile_copy_offset:36,mobile_cards_offset:18,mobile_copy_offset_unit:'px',mobile_cards_offset_unit:'px',mobile_group_scale:100,mobile_eyebrow_scale:95,mobile_title_scale:100,mobile_sub_scale:100,mobile_cta_scale:100,mobile_cta_width_mode:'custom',mobile_cta_width_value:86,mobile_cta_width_unit:'%'},
        reset:{mobile_height:580,mobile_description_lines:2,mobile_copy_offset:0,mobile_cards_offset:0,mobile_copy_offset_unit:'px',mobile_cards_offset_unit:'px',mobile_group_scale:100,mobile_eyebrow_scale:100,mobile_title_scale:100,mobile_sub_scale:100,mobile_cta_scale:100,mobile_cta_width_mode:'full',mobile_cta_width_value:100,mobile_cta_width_unit:'%'}
    };
    var set=sets[name]||sets.reset;
    Object.keys(set).forEach(function(k){
        var el=document.getElementById('secf-'+k);
        if(!el)return;
        el.value=set[k];
        if(el.type==='range'){var out=document.getElementById('secf-'+k+'-out');if(out)out.textContent=set[k];}
        if(k.indexOf('_unit')>-1)el.setAttribute('data-prev-unit',set[k]);
    });
    ['mobile_copy_offset','mobile_cards_offset'].forEach(function(key){
        var u=document.getElementById('secf-'+key+'_unit'),i=document.getElementById('secf-'+key);
        if(!u||!i)return;
        if(u.value==='pt'){i.min=-75;i.max=180;i.step=.5;}
        else if(u.value==='cm'){i.min=-2.65;i.max=6.35;i.step=.1;}
        else{i.min=-100;i.max=240;i.step=1;}
        u.setAttribute('data-prev-unit',u.value);
    });
    var cu=document.getElementById('secf-mobile_cta_width_unit'),cv=document.getElementById('secf-mobile_cta_width_value');
    if(cu&&cv){if(cu.value==='%'){cv.min=20;cv.max=100;cv.step=1;}else{cv.min=40;cv.max=340;cv.step=1;}}
    W.heroThemeMarkDirty();
    W.heroThemeSyncSummary();
    W.heroMobileStudioApply();
};

W.heroThemeConfigReset=function(){
    if(!confirm('¿Cargar los valores por defecto? Los cambios actuales no se guardarán hasta pulsar Guardar.'))return;
    W._heroThemeConfigUseDefaults=true;
    W.renderHeroThemePage();
};
W.heroSel=function(side,sel){var opts=Object.keys(W._heroTypes||{}).map(function(k){var t=W._heroTypes[k];return '<option value="'+W.esc(k)+'"'+(k===sel?' selected':'')+(t.available?'':' disabled')+'>'+W.esc(t.label)+(t.available?'':' — no disponible')+'</option>'}).join('');return '<select class="w-select" id="hero-'+side+'-type" onchange="wontia.heroEditReload(\''+side+'\')">'+opts+'</select>';};
W.heroFields=function(side,type,data){var d=data||{};
    var gv=function(k,def){return (d[k]===undefined||d[k]===null||d[k]==='')?def:d[k]};
    var sel=function(k,l,opts,def){var cur=String(gv(k,def));return '<div class="w-form-group"><label class="w-label">'+l+'</label><select class="w-select hero-f" data-k="'+k+'" onchange="wontia.heroPreview()">'+opts.map(function(o){return '<option value="'+W.esc(o[0])+'"'+(cur===String(o[0])?' selected':'')+'>'+W.esc(o[1])+'</option>'}).join('')+'</select></div>'};
    var f=function(k,l){return '<div class="w-form-group"><label class="w-label">'+l+'</label><input class="w-input hero-f" data-k="'+k+'" value="'+W.esc(d[k]||'')+'" oninput="wontia.heroPreview()"/></div>'};
    var col=function(k,l,def,tt){return '<div class="w-form-group"><label class="w-label" title="'+W.esc(tt||l)+'">'+l+'</label><input type="color" class="w-input hero-f" data-k="'+k+'" value="'+W.esc(gv(k,def))+'" oninput="wontia.heroPreview()"/></div>'};
    var numf=function(k,l,def,tt){return '<div class="w-form-group"><label class="w-label" title="'+W.esc(tt||l)+'">'+l+'</label><input type="number" class="w-input hero-f" data-k="'+k+'" min="0" max="100" step="1" value="'+W.esc(gv(k,def))+'" oninput="wontia.heroPreview()"/></div>'};
    var t=function(k,l){return '<div class="w-form-group"><label class="w-label">'+l+'</label><textarea class="w-textarea hero-f" data-k="'+k+'" oninput="wontia.heroPreview()">'+W.esc(d[k]||'')+'</textarea></div>'};
    var fi=function(k,l,mt){var id='herof-'+side+'-'+k;return '<div class="w-form-group"><label class="w-label">'+l+'</label><div style="display:flex;gap:6px;align-items:center"><input id="'+id+'" class="w-input hero-f" data-k="'+k+'" value="'+W.esc(d[k]||'')+'" oninput="wontia.heroPreview()"/><button type="button" class="w-btn w-btn-secondary w-btn-sm" title="Biblioteca de Media" onclick="wontia.heroPickMedia(\''+id+'\',\''+mt+'\')">📁</button></div></div>'};
    if(type==='text')return f('eyebrow','Etiqueta (eyebrow)')+f('title','Título (admite saltos de línea)')+f('title_accent','Fragmento del título en ROJO')+f('subtitle','Subtítulo')+t('text','Texto descriptivo')+f('cta','Texto del CTA principal')+f('cta_url','Destino CTA principal')+f('cta2','Texto del CTA secundario')+f('cta2_url','Destino CTA secundario')+'<div style="font-size:11px;color:var(--w-muted);margin:12px 0 4px;font-weight:700">Títulos dinámicos (rotan sin cambiar el slide)</div>'+sel('dyn_enabled','Activar títulos dinámicos',[['0','No'],['1','Sí']],'0')+sel('dyn_effect','Efecto',[['rollup','Roll Up (sube)'],['rolldown','Roll Down (baja)'],['flipchars','Aeropuerto (flip por letra)'],['flip','Volteo 3D (palabra)'],['typewriter','Máquina de escribir'],['slide','Deslizar lateral'],['fade','Fundido'],['blur','Desenfoque'],['scale','Zoom suave']],'rollup')+f('dyn_interval','Velocidad (ms entre cambios · 1200–15000)')+sel('dyn_pause_hover','Pausar al pasar el mouse',[['1','Sí'],['0','No']],'1')+W.renderRepeaterField({key:'hdyn_'+side,fields:[{key:'l1',label:'Línea 1',type:'text'},{key:'l2',label:'Línea 2',type:'text'},{key:'accent',label:'Palabra en ROJO (opcional)',type:'text'}],default:[{l1:'Tu música.',l2:'Tu estilo. Tu sonido.',accent:'Tu sonido.'},{l1:'Guitarras que',l2:'suenan a ti.',accent:'suenan a ti.'},{l1:'Encuentra',l2:'TU sonido.',accent:'TU sonido.'},{l1:'De la vitrina',l2:'a tu escenario.',accent:'tu escenario.'},{l1:'Play. Feel.',l2:'Repeat.',accent:'Feel.'}]},d.dyn_items||[])+'<div style="font-size:11px;color:var(--w-muted);margin:12px 0 4px;font-weight:700">Descripción dinámica (texto pequeño bajo el título)</div>'+sel('dyn_sub_enabled','Activar descripción dinámica',[['0','No'],['1','Sí']],'0')+sel('dyn_sub_effect','Efecto de la descripción',[['fade','Fundido'],['rollup','Roll Up (sube)'],['rolldown','Roll Down (baja)'],['slide','Deslizar lateral'],['blur','Desenfoque'],['scale','Zoom suave'],['typewriter','Máquina de escribir']],'fade')+f('dyn_sub_interval','Velocidad (ms entre cambios · por defecto 10000 = 10 s)')+W.renderRepeaterField({key:'hsub_'+side,fields:[{key:'text',label:'Descripción',type:'text'}],default:[{text:'Explora guitarras eléctricas de las mejores marcas…'},{text:'Prueba su sonido y encuentra la que va contigo.'},{text:'Envío a todo el país · compra 100 % segura.'}]},d.dyn_sub_items||[])+'<div style="font-size:11px;color:var(--w-muted);margin:12px 0 4px;font-weight:700">Estilo de los botones (CTA)</div>'+sel('btn_radius','Bordes',[['pill','Redondeados (pastilla)'],['rounded','Redondeados (suaves)'],['square','Cuadrados']],'pill')+col('btn_bg','Color de fondo — CTA principal','#F20D16','Color de fondo del botón principal')+col('btn_tc','Color de texto — CTA principal','#ffffff','Color del texto del botón principal')+col('btn2_bg','Color de fondo — CTA secundario','#2a2a2a','Color de fondo del botón secundario')+col('btn2_tc','Color de texto — CTA secundario','#ffffff','Color del texto del botón secundario')+'<div style="font-size:11px;color:var(--w-muted);margin:12px 0 4px;font-weight:700">Atributos comerciales</div>'+W.renderRepeaterField({key:'hattrs_'+side,fields:[{key:'icon',label:'Icono',type:'icon'},{key:'title',label:'Título',type:'text'},{key:'text',label:'Texto secundario',type:'text'},{key:'enabled',label:'Activo',type:'toggle'}],default:[{icon:'🔒',title:'Compra segura',text:'y múltiples medios de pago',enabled:1},{icon:'🎸',title:'Productos originales',text:'de las mejores marcas',enabled:1},{icon:'🎧',title:'Asesoría especializada',text:'para tu instrumento ideal',enabled:1}]},d.attrs||[]);
    if(type==='image')return fi('image','Imagen','image')+f('alt','Texto alternativo')+f('title','Título')+f('subtitle','Subtítulo')+t('text','Texto')+f('cta','Texto del CTA')+f('cta_url','Destino del CTA');
    if(type==='video')return sel('source','Fuente del video',[['file','Video propio (mp4)'],['youtube','YouTube']],'file')+fi('video','Archivo de video (mp4) — Biblioteca','video')+f('yt_url','URL de YouTube (solo fuente YouTube)')+fi('poster','Portada / poster','image')+f('alt','Descripción accesible')+f('title','Título (opcional)')+numf('volume','Volumen del video propio (0–100)',100,'Volumen para video propio (mp4). En YouTube el sonido se controla con «Silencio inicial».')+sel('video_size','Tamaño del video',[['full','Todo el ancho de la columna derecha'],['center','Centrado (tamaño contenido)']],'full')+sel('yt_clean','Video limpio (YouTube: sin barra, sin compartir, sin logo)',[['1','Sí'],['0','No']],'1')+sel('autoplay','Autoplay',[['1','Sí'],['0','No']],'1')+sel('mute','Silencio inicial',[['1','Sí'],['0','No']],'1')+sel('loop','Reproducción en bucle',[['1','Sí'],['0','No']],'1')+sel('controls','Mostrar controles',[['0','No'],['1','Sí']],'0');
    if(type==='product')return f('handle','Handle del producto')+f('title','Título (opcional)')+f('subtitle','Subtítulo')+t('text','Texto')+f('cta','Texto del CTA')+f('cta_url','Destino del CTA');
    if(type==='combo')return fi('image','Imagen','image')+f('alt','Texto alternativo')+f('title','Título')+t('text','Texto')+f('cta','CTA')+f('cta_url','Destino');
    if(type==='guitar'){
        var gv=function(k,def){return (d[k]===undefined||d[k]===null||d[k]==='')?def:d[k]};
        var u=String(gv('cal_unit','cm'));
        var num=function(v){v=parseFloat(v);return isNaN(v)?0:v};
        var calRow=function(k,label,min,max,step,val,arrows){
            return '<div class="w-form-group" style="margin-bottom:8px"><label class="w-label" style="display:flex;justify-content:space-between"><span>'+label+'</span><span id="calv-'+k+'" style="opacity:.7">'+val+'</span></label>'
              +'<div style="display:flex;gap:6px;align-items:center">'
              +(arrows?'<button type="button" class="w-btn w-btn-secondary w-btn-sm" title="Disminuir" onclick="wontia.calNudge(\''+k+'\',-1)">◀</button>':'')
              +'<input type="range" class="hero-f" data-k="'+k+'" min="'+min+'" max="'+max+'" step="'+step+'" value="'+val+'" oninput="wontia.calSync(\''+k+'\',this.value);wontia.heroPreview()" style="flex:1"/>'
              +(arrows?'<button type="button" class="w-btn w-btn-secondary w-btn-sm" title="Aumentar" onclick="wontia.calNudge(\''+k+'\',1)">▶</button>':'')
              +'</div></div>';
        };
        var cal='<div class="w-card" style="padding:12px;margin:10px 0"><div style="font-weight:700;margin-bottom:4px">🎯 Calibrador de cuerdas</div>'
          +'<div style="font-size:11px;color:var(--w-muted);margin-bottom:10px">Ajusta las cuerdas sobre la foto (vista previa en vivo abajo). Usa ◀ ▶ para precisión.</div>'
          +'<div class="w-form-group"><label class="w-label">Unidad de calibración</label><select class="w-input hero-f" data-k="cal_unit" onchange="wontia.heroEditReloadKeep(this)"><option value="cm"'+(u==='cm'?' selected':'')+'>centímetros (cm)</option><option value="mm"'+(u==='mm'?' selected':'')+'>milímetros (mm)</option><option value="vb"'+(u==='vb'?' selected':'')+'>unidades de imagen (u)</option></select></div>'
          +'<div class="w-form-group"><label class="w-label">Ancho real de la imagen (cm) — convierte mm/cm</label><input type="number" class="w-input hero-f" data-k="cal_ref" min="5" max="200" step="0.1" value="'+num(gv('cal_ref',40))+'" oninput="wontia.heroPreview()"/></div>'
          +calRow('cal_x','↔ Mover izquierda / derecha',-30,30,0.1,num(gv('cal_x',0)),true)
          +calRow('cal_y','↕ Mover arriba / abajo',-30,30,0.1,num(gv('cal_y',0)),true)
          +calRow('cal_tilt','⟲ Inclinación izq. / der. (°)',-8,8,0.05,num(gv('cal_tilt',0)),true)
          +calRow('cal_spread','⤢ Separación de cuerdas (%)',50,150,1,num(gv('cal_spread',100)),false)
          +'<div style="font-size:11px;color:var(--w-muted);margin:10px 0 4px">↔ Espaciado individual por cuerda (izq / der)</div>'
          +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 12px">'
          +[0,1,2,3,4,5].map(function(i){return '<div class="w-form-group" style="margin-bottom:4px"><label class="w-label" style="display:flex;justify-content:space-between;font-size:11px"><span>Cuerda '+(6-i)+'</span><span id="calv-cal_per'+i+'" style="opacity:.7">'+num(gv('cal_per'+i,0))+'</span></label><div style="display:flex;gap:3px;align-items:center"><button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.calNudge(\'cal_per'+i+'\',-1)">◀</button><input type="range" class="hero-f" data-k="cal_per'+i+'" min="-15" max="15" step="0.1" value="'+num(gv('cal_per'+i,0))+'" oninput="wontia.calSync(\'cal_per'+i+'\',this.value);wontia.heroPreview()" style="flex:1"/><button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.calNudge(\'cal_per'+i+'\',1)">▶</button></div></div>'}).join('')
          +'</div>'
          +'<div class="w-form-group"><label class="w-label">Mostrar guías (puntos + cruces)</label><select class="w-input hero-f" data-k="cal_guides" onchange="wontia.heroPreview()"><option value="0"'+(String(gv('cal_guides','0'))==='0'?' selected':'')+'>No</option><option value="1"'+(String(gv('cal_guides','0'))==='1'?' selected':'')+'>Sí</option></select></div>'
          +'<button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.calReset()">↺ Reiniciar calibración</button>'
        +'</div>';
        return fi('image','Imagen de la guitarra (3:2 · 1536×1024)','image')+f('alt','Texto alternativo')+f('product','…o producto del catálogo (handle, opcional)')+cal+'<div class="w-form-group"><label class="w-label">Coordenadas de cuerdas SVG (avanzado · una polilínea por línea · viewBox 1536×1024)</label><textarea class="w-textarea hero-f" data-k="string_points" oninput="wontia.heroPreview()" style="min-height:100px;font-family:monospace;font-size:11px">'+W.esc((d.string_points&&d.string_points.length?d.string_points:[0,1,2,3,4,5].map(function(){return ''})).join('\n'))+'</textarea></div>';
    }
    return '<div style="font-size:12px;color:var(--w-muted)">Este tipo aún no tiene motor implementado; no se puede publicar.</div>';
};
W.heroPickMedia=function(inputId,type){
    W.openMediaExplorer({type:(type==='video'?'video':(type==='image'?'image':'')),title:'Elegir media',onSelect:function(m){W.heroSetMedia(inputId,m.url)}});
};
W.heroSetMedia=function(inputId,url){var el=document.getElementById(inputId);if(el)el.value=url;W.closeModal();W.heroPreview();};
W.heroEditReload=function(side){var sel=document.getElementById('hero-'+side+'-type');var box=document.getElementById('hero-'+side+'-fields');if(sel&&box){box.innerHTML=W.heroFields(side,sel.value,{});W.heroPreview();}};
W.heroCollectFrom=function(boxId,repSide){var box=document.getElementById(boxId);var out={};if(box)box.querySelectorAll('.hero-f').forEach(function(el){out[el.getAttribute('data-k')]=el.value});if(typeof out.string_points==='string')out.string_points=out.string_points.split('\n').map(function(s){return s.trim()}).filter(function(s){return s});[['hattrs_','attrs'],['hdyn_','dyn_items'],['hsub_','dyn_sub_items']].forEach(function(p){var rk=p[0]+repSide;if(W.repCollect)W.repCollect(rk);var ta=document.getElementById('secf-'+rk);if(ta){try{out[p[1]]=JSON.parse(ta.value||'[]')}catch(e){}}});return out;};
W.heroCollect=function(side){return W.heroCollectFrom('hero-'+side+'-fields',side);};
W.heroBg=function(){var out={};document.querySelectorAll('.hero-bg-f').forEach(function(el){out[el.getAttribute('data-k')]=el.type==='checkbox'?(el.checked?'1':'0'):el.value});return out;};
W.heroBgTypeChange=function(){var v=((document.getElementById('hero-bg-type')||{}).value)||'image';document.querySelectorAll('[data-bg-for]').forEach(function(el){el.style.display=el.getAttribute('data-bg-for')===v?'':'none'});W.heroPreview();};
W.heroLayoutChange=function(v){W._heroLayout=v==='background'?'background':'split';var a=document.getElementById('hero-split-wrap'),b=document.getElementById('hero-bg-wrap');if(a)a.style.display=W._heroLayout==='background'?'none':'grid';if(b)b.style.display=W._heroLayout==='background'?'':'none';W.heroPreview();};
W.heroFillBg=function(source){var src=W.heroCollect(source);var box=document.getElementById('hero-bg-content-fields');if(!box)return;box.innerHTML=W.heroFields('bg','text',src);if(W.repInit)W.repInit([{key:'hattrs_bg',type:'repeater'},{key:'hdyn_bg',type:'repeater'},{key:'hsub_bg',type:'repeater'}]);W.heroPreview();};
W.heroEditReloadKeep=function(el){var box=el&&el.closest?el.closest('#hero-left-fields, #hero-right-fields'):null;if(!box)return;var side=box.id.indexOf('left')>-1?'left':'right';var sel=document.getElementById('hero-'+side+'-type');if(!sel)return;var cur=W.heroCollect(side);box.innerHTML=W.heroFields(side,sel.value,cur);W.heroPreview();};
W.calSync=function(k,v){var el=document.getElementById('calv-'+k);if(el){var s=document.querySelector('#hero-right-fields [data-k="cal_unit"], #hero-left-fields [data-k="cal_unit"]');el.textContent=v+' '+((s&&s.value==='vb')?'u':((s&&s.value)||''));}};
W.calNudge=function(k,dir){var el=document.querySelector('#hero-right-fields [data-k="'+k+'"], #hero-left-fields [data-k="'+k+'"]');if(!el)return;var step=parseFloat(el.step||'1');var v=parseFloat(el.value||'0')+dir*step*5;v=Math.max(parseFloat(el.min===''?(-1e9):el.min),Math.min(parseFloat(el.max===''?1e9:el.max),v));el.value=Math.round(v*100)/100;W.calSync(k,el.value);W.heroPreview();};
W.calReset=function(){var box=document.querySelector('#hero-right-fields')||document.querySelector('#hero-left-fields');if(!box){return}var keys=['cal_x','cal_y','cal_tilt'];for(var i=0;i<6;i++)keys.push('cal_per'+i);keys.forEach(function(k){var el=box.querySelector('[data-k="'+k+'"]');if(el){el.value=0;W.calSync(k,0)}});var sp=box.querySelector('[data-k="cal_spread"]');if(sp){sp.value=100;W.calSync('cal_spread',100)}var g=box.querySelector('[data-k="cal_guides"]');if(g)g.value='0';W.heroPreview();};
W.heroEdit=async function(id){
    var app=document.getElementById('wontia-app'); if(!app)return;
    var r=await W.api('/api/v1/admin/hero'); var d=(r&&r.data)||{}; W._heroTypes=d.types||{}; W._heroSlides=d.slides||[]; W._heroTheme=d.theme||{active:'classic',themes:{},config:{}};
    var s=(W._heroSlides||[]).filter(function(x){return x.id===id})[0];
    if(!s){app.innerHTML='<div class="w-empty-state"><p>Slide no encontrado.</p><button class="w-btn w-btn-secondary" onclick="wontia.heroBack()">← Volver</button></div>';return;}
    W._heroCur=s;
    var h='<div class="w-flex-between" style="flex-wrap:wrap;gap:8px;margin-bottom:12px"><div><div style="font-size:11px;color:var(--w-muted)">Hero Builder › slide</div><h3 style="margin:0">'+W.esc(s.name)+'</h3></div><div class="w-flex w-gap-sm"><button class="w-btn w-btn-secondary" onclick="wontia.heroBack()">← Volver</button><button class="w-btn w-btn-primary" onclick="wontia.heroSave()">💾 Guardar</button></div></div>';
    h+='<div class="w-card"><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px"><div class="w-form-group"><label class="w-label">Nombre</label><input class="w-input" id="hero-name" value="'+W.esc(s.name)+'"/></div><div class="w-form-group"><label class="w-label">Estado</label><select class="w-select" id="hero-status"><option value="draft"'+(s.status!=='active'?' selected':'')+'>Borrador</option><option value="active"'+(s.status==='active'?' selected':'')+'>Activo</option></select></div></div></div>';
    var layout=s.layout==='background'?'background':'split';W._heroLayout=layout;
    h+='<div class="w-card" style="margin-top:12px"><h3>Composición del slide</h3><div style="font-size:12px;color:var(--w-muted);margin-bottom:8px">Elige cómo se organiza este slide.</div><select class="w-select" id="hero-layout" onchange="wontia.heroLayoutChange(this.value)"><option value="split"'+(layout==='split'?' selected':'')+'>Dos zonas (izquierda / derecha)</option><option value="background"'+(layout==='background'?' selected':'')+'>Fondo principal a pantalla completa</option></select></div>';
    var dz=s.design||{};var colsOpts=['50/50','45/55','55/45','40/60','60/40','35/65','65/35'];
    h+='<div class="w-card" style="margin-top:12px"><h3>Diseño</h3><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">'
      +'<div class="w-form-group"><label class="w-label">Fondo</label><input type="color" class="w-input hero-df" data-k="bg" value="'+W.esc(dz.bg||'#111111')+'" oninput="wontia.heroPreview()"/></div>'
      +'<div class="w-form-group"><label class="w-label">Proporción columnas (izq/der)</label><select class="w-select hero-df" data-k="cols" onchange="wontia.heroPreview()">'+colsOpts.map(function(v){return '<option value="'+v+'"'+((dz.cols||'50/50')===v?' selected':'')+'>'+v+'</option>'}).join('')+'</select></div>'
      +'<div class="w-form-group"><label class="w-label">Alineación vertical</label><select class="w-select hero-df" data-k="valign" onchange="wontia.heroPreview()">'+[['center','Centro'],['start','Arriba'],['end','Abajo']].map(function(o){return '<option value="'+o[0]+'"'+((dz.valign||'center')===o[0]?' selected':'')+'>'+o[1]+'</option>'}).join('')+'</select></div>'
      +'<div class="w-form-group"><label class="w-label" title="Espacio superior del contenido (px)">Espacio superior del contenido (px)</label><input type="number" class="w-input hero-df" data-k="pad_top" min="0" max="160" step="4" value="'+W.esc(dz.pad_top||0)+'" oninput="wontia.heroPreview()"/></div>'
      +'<div class="w-form-group"><label class="w-label" title="Espacio lateral del contenido (px)">Espacio lateral (px)</label><input type="number" class="w-input hero-df" data-k="pad_x" min="0" max="80" step="2" value="'+W.esc(dz.pad_x||22)+'" oninput="wontia.heroPreview()"/></div>'
      +'</div></div>';
    h+='<div id="hero-split-wrap" style="display:'+(layout==='background'?'none':'grid')+';grid-template-columns:1fr 1fr;gap:14px;margin-top:14px">';
    h+='<div class="w-card"><h3>Zona izquierda</h3>'+W.heroSel('left',s.left_type)+'<div id="hero-left-fields" style="margin-top:10px">'+W.heroFields('left',s.left_type,s.left_data||{})+'</div></div>';
    h+='<div class="w-card"><h3>Zona derecha</h3>'+W.heroSel('right',s.right_type)+'<div id="hero-right-fields" style="margin-top:10px">'+W.heroFields('right',s.right_type,s.right_data||{})+'</div></div>';
    h+='</div>';
    var bvi=function(k,l){return '<div class="w-form-group"><label class="w-label">'+l+'</label><input class="w-input hero-bg-f" data-k="'+k+'" value="'+W.esc(dz[k]||'')+'" oninput="wontia.heroPreview()"/></div>'};
    var bvm=function(k,l,mt){var id='hero-bg-'+k;return '<div class="w-form-group"><label class="w-label">'+l+'</label><div style="display:flex;gap:6px;align-items:center"><input id="'+id+'" class="w-input hero-bg-f" data-k="'+k+'" value="'+W.esc(dz[k]||'')+'" oninput="wontia.heroPreview()"/><button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.heroPickMedia(\''+id+'\',\''+mt+'\')">Elegir</button></div></div>'};
    var bvn=function(k,l,min,max,step,def){var v=(dz[k]===undefined||dz[k]===null||dz[k]==='')?def:dz[k];return '<div class="w-form-group"><label class="w-label">'+l+'</label><input type="number" class="w-input hero-bg-f" data-k="'+k+'" min="'+min+'" max="'+max+'" step="'+step+'" value="'+W.esc(v)+'" oninput="wontia.heroPreview()"/></div>'};
    var bvs=function(k,l,opts,def){var cur=String((dz[k]===undefined||dz[k]===null||dz[k]==='')?def:dz[k]);return '<div class="w-form-group"><label class="w-label">'+l+'</label><select class="w-select hero-bg-f" data-k="'+k+'" onchange="wontia.heroPreview()">'+opts.map(function(o){return '<option value="'+W.esc(o[0])+'"'+(cur===String(o[0])?' selected':'')+'>'+W.esc(o[1])+'</option>'}).join('')+'</select></div>'};
    var bvc=function(k,l,def){var on=String((dz[k]===undefined||dz[k]===null||dz[k]==='')?def:dz[k])!=='0';return '<label style="font-size:11px;display:inline-flex;gap:6px;align-items:center;border:1px solid var(--w-border);border-radius:8px;padding:6px 8px;background:#fff;color:#333333"><input type="checkbox" class="hero-bg-f" data-k="'+k+'" '+(on?'checked':'')+' onchange="wontia.heroPreview()"/> '+l+'</label>'};
    var bgType=String(dz.bg_type||'image');
    h+='<div id="hero-bg-wrap" style="display:'+(layout==='background'?'':'none')+';margin-top:14px">';
    h+='<div class="w-card"><h3>Fondo principal</h3><div style="font-size:12px;color:var(--w-muted);margin-bottom:10px">El fondo ocupa todo el banner. El contenido se coloca encima y conserva el configurador de texto.</div><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">'
      +'<div class="w-form-group"><label class="w-label">Tipo de fondo</label><select class="w-select hero-bg-f" id="hero-bg-type" data-k="bg_type" onchange="wontia.heroBgTypeChange()">'+[['image','Imagen'],['video','Video MP4'],['youtube','YouTube']].map(function(o){return '<option value="'+o[0]+'"'+(bgType===o[0]?' selected':'')+'>'+o[1]+'</option>'}).join('')+'</select></div>'
      +'</div>'
      +'<div data-bg-for="image">'+bvm('bg_image','Imagen de fondo (escritorio)','image')+bvm('bg_image_mobile','Imagen de fondo para móvil (opcional)','image')+bvi('bg_alt','Texto alternativo (si aporta información)')+'<div style="font-size:10.5px;color:#777;margin-top:-4px">Si no cargas una imagen móvil, se usará la de escritorio.</div></div>'
      +'<div data-bg-for="video">'+bvm('bg_video','Video MP4','video')+'</div>'
      +'<div data-bg-for="youtube">'+bvi('bg_yt','URL de YouTube')+'</div>'
      +bvm('bg_poster','Poster / imagen de respaldo (opcional)','image')
      +'<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:12px">'
      +bvn('bg_x','Foco horizontal (0–100%)',0,100,1,50)
      +bvn('bg_y','Foco vertical (0–100%)',0,100,1,50)
      +bvn('bg_overlay','Oscurecimiento (0–80%)',0,80,1,45)
      +bvs('bg_overlay_style','Estilo del overlay',[['dark','Oscuro general'],['left','Desde la izquierda'],['bottom','Desde abajo'],['none','Sin overlay']],'dark')
      +bvs('content_align','Posición del contenido',[['left','Izquierda'],['center','Centro'],['right','Derecha']],'left')
      +bvn('content_max','Ancho máximo del contenido (px)',320,900,10,560)
      +'</div><div style="grid-column:1/-1;border-top:1px solid var(--w-border);padding-top:12px;margin-top:2px;background:transparent;color:#ffffff"><b style="font-size:12px;color:#ffffff">Contenido en móvil</b><p style="font-size:11px;color:#ffffff;margin:4px 0 8px">Hereda escritorio o personaliza solo la versión móvil. Si eliges personalizar, puedes ocultar elementos y ubicar el bloque arriba, centro o abajo.</p><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">'
      +bvs('mobile_mode','Modo móvil',[['inherit','Heredar escritorio'],['custom','Personalizar para móvil']],'inherit')
      +bvs('mobile_valign','Posición vertical en móvil',[['start','Superior'],['center','Centro'],['end','Inferior']],'center')
      +bvs('mobile_align','Alineación en móvil',[['left','Izquierda'],['center','Centro'],['right','Derecha']],'left')
      +'</div><div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px">'
      +bvc('mobile_show_eyebrow','Eyebrow','1')
      +bvc('mobile_show_title','Título','1')
      +bvc('mobile_show_subtitle','Subtítulo','1')
      +bvc('mobile_show_text','Texto descriptivo','1')
      +bvc('mobile_show_cta','CTA principal','1')
      +bvc('mobile_show_cta2','CTA secundario','1')
      +bvc('mobile_cta_full','CTA a ancho completo','0')
      +'</div></div></div>';
    var bgContent=(s.left_data&&Object.keys(s.left_data).length?s.left_data:(s.right_data||{}));
    h+='<div class="w-card" style="margin-top:14px"><h3>Contenido superpuesto</h3><div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px"><span style="font-size:12px;color:var(--w-muted)">Puedes partir del contenido que ya tienes en una zona.</span><button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.heroFillBg(\'left\')">Usar zona izquierda</button><button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.heroFillBg(\'right\')">Usar zona derecha</button></div><div id="hero-bg-content-fields">'+W.heroFields('bg','text',bgContent)+'</div></div>';
    h+='</div>';
    h+='<div class="w-card" style="margin-top:14px"><div class="w-flex-between" style="flex-wrap:wrap;gap:8px"><h3 style="margin:0">Vista previa</h3><div class="w-flex w-gap-sm" id="hero-dev"><button type="button" class="w-btn w-btn-primary" data-dev="desktop" onclick="wontia.heroDevice(\'desktop\')">🖥 Escritorio</button><button type="button" class="w-btn w-btn-secondary" data-dev="tablet" onclick="wontia.heroDevice(\'tablet\')">▭ Tablet</button><button type="button" class="w-btn w-btn-secondary" data-dev="mobile" onclick="wontia.heroDevice(\'mobile\')">▯ Móvil</button></div></div><div id="hero-preview" style="margin-top:12px"></div></div>';
    h+='<div class="w-card" style="margin-top:14px"><h3>Publicación</h3><div style="font-size:12px;color:var(--w-muted)">Guardar mantiene el borrador sin alterar el sitio. Publicar solo funciona si ambas zonas tienen un tipo con motor disponible.</div><div class="w-flex w-gap-sm" style="margin-top:10px"><button class="w-btn w-btn-primary" onclick="wontia.heroPub('+s.id+')">🚀 Publicar</button><button class="w-btn w-btn-secondary" onclick="wontia.heroVersions('+s.id+')">🕘 Versiones</button></div><div id="hero-vers" style="margin-top:10px"></div></div>';
    app.innerHTML=h;
    if(W.repInit)W.repInit([{key:'hattrs_left',type:'repeater'},{key:'hattrs_right',type:'repeater'},{key:'hdyn_left',type:'repeater'},{key:'hdyn_right',type:'repeater'},{key:'hsub_left',type:'repeater'},{key:'hsub_right',type:'repeater'},{key:'hattrs_bg',type:'repeater'},{key:'hdyn_bg',type:'repeater'},{key:'hsub_bg',type:'repeater'}]);
    W.heroBgTypeChange();
    W.heroLayoutChange(layout);
    W.heroPreview();
};
W._heroDevice='desktop';
W.heroDevice=function(d){W._heroDevice=d;document.querySelectorAll('#hero-dev [data-dev]').forEach(function(b){b.className='w-btn '+(b.getAttribute('data-dev')===d?'w-btn-primary':'w-btn-secondary')});W.heroPreview();};
W.heroPreview=function(){
    var box=document.getElementById('hero-preview'); if(!box)return;
    var layout=W._heroLayout||'split';
    var lt=((document.getElementById('hero-left-type')||{}).value)||'text';
    var rt=((document.getElementById('hero-right-type')||{}).value)||'text';
    var ld=W.heroCollect('left'), rd=W.heroCollect('right');
    var bg=W.heroBg();
    var dev=W._heroDevice||'desktop';
    var w=dev==='desktop'?'100%':(dev==='tablet'?'768px':'380px');
    var dz={};document.querySelectorAll('.hero-df').forEach(function(el){dz[el.getAttribute('data-k')]=el.value});
    var dvz=(dz.cols||'50/50').split('/');
    var cols=(dev==='mobile')?'1fr':((parseInt(dvz[0],10)||50)+'fr '+(parseInt(dvz[1],10)||50)+'fr');
    var pbg=/^#[0-9a-f]{3,8}$/i.test(dz.bg||'')?dz.bg:'#111111';
    var pva=({start:'start',end:'end'})[dz.valign]||'center';
    var padt=parseInt(dz.pad_top,10)||0;
    var padx=((dz.pad_x===''||dz.pad_x==null)?22:(parseInt(dz.pad_x,10)||0));
    var brad=({pill:'999px',rounded:'10px',square:'0'})[dz.btn_radius]||'999px';
    function phl(title,accent){title=title||'Título';accent=(accent||'').trim();if(!accent)return W.esc(title);var ii=title.toLowerCase().indexOf(accent.toLowerCase());if(ii<0)return W.esc(title);return W.esc(title.slice(0,ii))+'<span style="color:#F20D16">'+W.esc(title.slice(ii,ii+accent.length))+'</span>'+W.esc(title.slice(ii+accent.length));}
    function pattrs(d){var a=(d&&d.attrs)||[];if(!Array.isArray(a))return '';var r=a.filter(function(x){return x&&String(x.enabled)!=='0'&&((x.title||'')||(x.text||''))}).map(function(x){return '<li style="display:flex;gap:8px;font-size:12px;opacity:.9">'+(x.icon?'<span>'+W.esc(x.icon)+'</span>':'')+'<span>'+(x.title?'<b>'+W.esc(x.title)+'</b>':'')+(x.text?(x.title?' — ':'')+W.esc(x.text):'')+'</span></li>'}).join('');return r?'<ul style="list-style:none;padding:0;margin:10px 0 0;display:flex;flex-direction:column;gap:6px">'+r+'</ul>':'';}
    function blk(type,d){
        if(type==='text'){var dt=d,tt=d.title,ta2=d.title_accent;if(String(d.dyn_enabled)==='1'&&Array.isArray(d.dyn_items)&&d.dyn_items.length){var f0=d.dyn_items[0]||{};tt=(f0.l1?(f0.l1+(f0.l2?'\n'+f0.l2:'')):(f0.text||d.title));ta2=f0.accent||'';}return '<div class="sh-preview-copy"><div class="sh-preview-title" style="font-family:Sora;font-size:'+(dev==='mobile'?'22px':'30px')+';font-weight:800;line-height:1.1;white-space:pre-line">'+phl(tt,ta2)+(String(d.dyn_enabled)==='1'?' <span style="font-size:11px;opacity:.6;font-weight:600">[dinámico · '+W.esc(d.dyn_effect||'rollup')+']</span>':'')+'</div>'+(function(){var s=d.subtitle;if(String(d.dyn_sub_enabled)==='1'&&Array.isArray(d.dyn_sub_items)&&d.dyn_sub_items.length){s=(d.dyn_sub_items[0]||{}).text||s}return s?'<div class="sh-preview-subtitle" style="margin-top:6px;opacity:.9">'+W.esc(s)+'</div>':''})()+(d.text?'<p class="sh-preview-text" style="margin-top:6px;opacity:.8;font-size:13px">'+W.esc(d.text)+'</p>':'')+((d.cta||d.cta2)?'<div class="sh-preview-ctas" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">'+(d.cta?'<span class="sh-preview-cta" style="background:'+W.esc(d.btn_bg||'#F20D16')+';color:'+W.esc(d.btn_tc||'#fff')+';border-radius:'+brad+';padding:9px 16px;font-weight:800;font-size:13px">'+W.esc(d.cta)+'</span>':'')+(d.cta2?'<span class="sh-preview-cta2" style="background:'+W.esc(d.btn2_bg||'rgba(255,255,255,.14)')+';border:1px solid rgba(255,255,255,.3);color:'+W.esc(d.btn2_tc||'#fff')+';border-radius:'+brad+';padding:9px 16px;font-weight:800;font-size:13px">'+W.esc(d.cta2)+'</span>':'')+'</div>':'')+pattrs(d)+'</div>';}
        if(type==='image')return d.image?('<img src="'+W.esc(d.image)+'" alt="" style="width:100%;border-radius:14px"/>'):'<div style="height:140px;background:#222;border-radius:14px"></div>';
        if(type==='video'){var lbl=(String(d.source)==='youtube')?'▶ YouTube':(d.video?'▶ Video (mp4)':'▶ Video');var pin=d.poster?('<img src="'+W.esc(d.poster)+'" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover"/>'):'';return '<div style="position:relative;aspect-ratio:16/9;background:#0c0c0c;border:1px solid rgba(255,255,255,.1);border-radius:14px;overflow:hidden">'+pin+'<div style="position:absolute;inset:0;display:grid;place-items:center;color:#fff;font-size:13px;font-weight:700;text-shadow:0 1px 5px rgba(0,0,0,.7)">'+lbl+'</div></div>';}
        if(type==='product')return '<div style="display:flex;gap:10px;align-items:center"><div style="width:60px;height:60px;background:#222;border-radius:10px"></div><b>'+W.esc(d.title||d.handle||'Producto')+'</b></div>';
        if(type==='combo')return '<div style="padding:14px;background:#222;border-radius:14px">'+W.esc(d.title||'Combo o promoción')+'</div>';
        if(type==='guitar')return '<div style="padding:14px;background:#1b1b1b;border-radius:14px"><div style="display:flex;flex-direction:column;gap:8px">'+[0,1,2,3,4,5].map(function(){return '<div style="height:6px;background:#aaa;border-radius:999px"></div>'}).join('')+'</div><div style="margin-top:8px;font-size:12px;opacity:.85">🎸 Guitarra interactiva · Clean · Overdrive · Distortion · Fuzz</div></div>';
        return '<div style="opacity:.5;font-size:12px">Instrumento no disponible</div>';
    }
    if(layout==='background'){
        var bgt=bg.bg_type||'image';
        var bgImage=(dev==='mobile'&&bg.bg_image_mobile)?bg.bg_image_mobile:bg.bg_image;
        var bgHtml='';
        if(bgt==='image'&&bgImage){
            bgHtml='<img src="'+W.esc(bgImage)+'" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:'+(parseInt(bg.bg_x,10)||50)+'% '+(parseInt(bg.bg_y,10)||50)+'%"/>';
        }else if(bgt==='video'){
            if(bg.bg_poster)bgHtml='<img src="'+W.esc(bg.bg_poster)+'" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:'+(parseInt(bg.bg_x,10)||50)+'% '+(parseInt(bg.bg_y,10)||50)+'%"/>';
            bgHtml+='<div style="position:absolute;inset:0;display:grid;place-items:center;color:#fff;font-size:13px;font-weight:700;text-shadow:0 1px 5px rgba(0,0,0,.7)">Video de fondo</div>';
        }else if(bgt==='youtube'){
            if(bg.bg_poster)bgHtml='<img src="'+W.esc(bg.bg_poster)+'" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:'+(parseInt(bg.bg_x,10)||50)+'% '+(parseInt(bg.bg_y,10)||50)+'%"/>';
            bgHtml+='<div style="position:absolute;inset:0;display:grid;place-items:center;color:#fff;font-size:13px;font-weight:700;text-shadow:0 1px 5px rgba(0,0,0,.7)">YouTube de fondo</div>';
        }else{
            bgHtml='<div style="position:absolute;inset:0;background:#1b1b1b"></div><div style="position:absolute;inset:0;display:grid;place-items:center;color:#fff;font-size:12px;opacity:.7">Configura una imagen o video de fondo</div>';
        }
        var ov=Math.max(0,Math.min(80,parseInt(bg.bg_overlay,10)||0));var oa=ov/100;
        var os=bg.bg_overlay_style||'dark';var ovCss='transparent';
        if(os==='dark')ovCss='linear-gradient(180deg,rgba(0,0,0,'+(oa*.45)+') 0%,rgba(0,0,0,'+oa+') 100%)';
        else if(os==='left')ovCss='linear-gradient(90deg,rgba(0,0,0,'+oa+') 0%,rgba(0,0,0,'+(oa*.55)+') 55%,rgba(0,0,0,0) 100%)';
        else if(os==='bottom')ovCss='linear-gradient(0deg,rgba(0,0,0,'+oa+') 0%,rgba(0,0,0,'+(oa*.35)+') 58%,rgba(0,0,0,0) 100%)';
        var ca=bg.content_align||'left';var cj=ca==='center'?'center':(ca==='right'?'flex-end':'flex-start');
        var cva=({start:'flex-start',end:'flex-end'})[dz.valign]||'center';
        var mobileCustom=dev==='mobile'&&bg.mobile_mode==='custom';
        if(mobileCustom){
            var mva=bg.mobile_valign||'center';cva=({start:'flex-start',end:'flex-end'})[mva]||'center';
            ca=bg.mobile_align||'left';cj=ca==='center'?'center':(ca==='right'?'flex-end':'flex-start');
        }
        var cmax=Math.max(320,Math.min(900,parseInt(bg.content_max,10)||560));
        var content=W.heroCollectFrom('hero-bg-content-fields','bg');
        var mCss='';
        if(mobileCustom){
            [['mobile_show_title','title'],['mobile_show_subtitle','subtitle'],['mobile_show_text','text'],['mobile_show_cta','cta'],['mobile_show_cta2','cta2']].forEach(function(x){if(String(bg[x[0]]||'1')==='0')mCss+='.sh-preview-mobile .sh-preview-'+x[1]+'{display:none!important}';});
            if(String(bg.mobile_cta_full||'0')==='1')mCss+='.sh-preview-mobile .sh-preview-ctas{flex-direction:column;align-items:stretch}.sh-preview-mobile .sh-preview-ctas span{width:100%;text-align:center}';
            if(String(bg.mobile_show_title||'1')==='0'&&String(bg.mobile_show_subtitle||'1')==='0'&&String(bg.mobile_show_text||'1')==='0'&&String(bg.mobile_show_cta||'1')==='0'&&String(bg.mobile_show_cta2||'1')==='0')mCss+='.sh-preview-mobile .sh-preview-content{display:none!important}';
        }
        box.innerHTML=(mCss?'<style>'+mCss+'</style>':'')+'<div class="'+(mobileCustom?'sh-preview-mobile ':'')+'" style="margin:0 auto;max-width:'+w+';position:relative;min-height:280px;background:'+pbg+';color:#fff;border-radius:14px;overflow:hidden;display:flex;align-items:'+cva+';justify-content:'+cj+';padding:'+padt+'px '+padx+'px"><div style="position:absolute;inset:0;overflow:hidden">'+bgHtml+'<div style="position:absolute;inset:0;pointer-events:none;background:'+ovCss+'"></div></div><div class="sh-preview-content" style="position:relative;z-index:2;width:100%;max-width:'+cmax+'px;'+(ca==='center'?'text-align:center;margin:0 auto':(ca==='right'?'text-align:right;margin-left:auto':''))+'">'+blk('text',content)+'</div></div>';
        return;
    }
    box.innerHTML='<div style="margin:0 auto;max-width:'+w+';background:'+pbg+';color:#fff;border-radius:14px;padding:'+padt+'px '+padx+'px;display:grid;grid-template-columns:'+cols+';gap:16px;align-items:'+pva+'">'+blk(lt,ld)+blk(rt,rd)+'</div>';
};
W.heroSave=async function(){
    var s=W._heroCur||{};
    var layout=W._heroLayout||'split';
    var dz=Object.assign({},s.design||{});document.querySelectorAll('.hero-df').forEach(function(el){dz[el.getAttribute('data-k')]=el.value});Object.assign(dz,W.heroBg());
    var body={id:s.id,name:(document.getElementById('hero-name')||{}).value||s.name,status:(document.getElementById('hero-status')||{}).value||'draft',layout:layout,
      left_type:layout==='background'?'text':(((document.getElementById('hero-left-type')||{}).value)||s.left_type),
      left_data:layout==='background'?W.heroCollectFrom('hero-bg-content-fields','bg'):W.heroCollect('left'),
      right_type:layout==='background'?'none':(((document.getElementById('hero-right-type')||{}).value)||s.right_type),
      right_data:layout==='background'?{}:W.heroCollect('right'),design:dz};
    var r=await W.api('/api/v1/admin/hero/slides',{method:'POST',body:body});
    if(r&&r.ok){W.notify('Slide guardado','success');W.renderHero();}else W.notify((r&&r.message)||'Error','error');
};
W.heroVersions=async function(id){
    var box=document.getElementById('hero-vers'); if(!box)return; box.innerHTML='Cargando…';
    var r=await W.api('/api/v1/admin/hero/slides/'+id+'/versions'); var v=(r&&r.data)||[];
    if(!v.length){box.innerHTML='<div style="font-size:12px;color:var(--w-muted)">Sin versiones.</div>';return}
    box.innerHTML='<div style="font-size:12px;color:var(--w-muted);margin-bottom:4px">Historial (comparar / restaurar):</div>'+v.map(function(x){return '<span style="display:inline-flex;gap:4px;margin:2px"><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.heroDiff('+x.id+')" title="Comparar con el actual">🔍 '+W.esc(String(x.created_at))+'</button><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.heroRestore('+x.id+')" title="Restaurar">↩</button></span>'}).join('');
};
W.heroDiff=async function(vid){
    var r=await W.api('/api/v1/admin/hero/versions/'+vid); var v=(r&&r.data)||{}; var d=v.data||{}; var cur=W._heroCur||{};
    var esc=W.esc; function short(s){s=String(s==null?'':s); return esc(s.length>120?s.slice(0,120)+'…':s)}
    var rows='';
    function row(label,a,b){ if(JSON.stringify(a)===JSON.stringify(b))return ''; return '<tr><td style="vertical-align:top;color:var(--w-muted);white-space:nowrap">'+esc(label)+'</td><td style="font-size:12px">'+short(a)+'</td><td style="font-size:12px">'+short(b)+'</td></tr>'; }
    rows+=row('Nombre',d.name,cur.name);
    rows+=row('Estado',d.status,cur.status);
    rows+=row('Tipo izquierda',d.left_type,cur.left_type);
    rows+=row('Datos izquierda',JSON.stringify(d.left_data),JSON.stringify(cur.left_data));
    rows+=row('Tipo derecha',d.right_type,cur.right_type);
    rows+=row('Datos derecha',JSON.stringify(d.right_data),JSON.stringify(cur.right_data));
    var h=rows?'<table class="w-table" style="width:100%"><thead><tr><th>Campo</th><th>Versión '+esc(String(v.created_at||''))+'</th><th>Actual</th></tr></thead><tbody>'+rows+'</tbody></table>':'<div style="color:var(--w-muted);font-size:13px">Sin diferencias con el contenido actual.</div>';
    W.modal('Diferencias de la versión',h,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cerrar</button><button class="w-btn w-btn-primary" onclick="wontia.heroRestore('+vid+')">↩ Restaurar esta versión</button>');
};
W.heroRestore=async function(id){var r=await W.api('/api/v1/admin/hero/versions/'+id+'/restore',{method:'POST',body:{}});if(r&&r.ok){W.notify('Restaurado','success');W.renderHero();}};
W.renderSettings=function(tab){
    var app=document.getElementById('wontia-app');
    W.state.settingsTab=tab||'general';
    var t=W.state.settingsTab;
    var tabBtn=function(id,label){return '<button class="w-btn '+(t===id?'w-btn-primary':'w-btn-secondary')+'" onclick="wontia.renderSettings(\''+id+'\')">'+label+'</button>';};
    app.innerHTML='<div class="w-flex w-gap-sm" style="margin-bottom:16px;flex-wrap:wrap">'+tabBtn('general','Ajustes')+tabBtn('themes','Themes')+tabBtn('nav','Menús')+tabBtn('logo','Logo & Favicon')+tabBtn('security','Seguridad')+tabBtn('cache','Administración de caché')+tabBtn('update','Update')+tabBtn('typography','Tipografía')+tabBtn('reconciliation','Conciliación de catálogo')+'</div><div id="settings-panel"></div>';
    if(t==='themes')W.renderThemesPanel();
    else if(t==='nav')W.renderNavPanel();
    else if(t==='logo')W.renderLogoPanel();
    else if(t==='security')W.renderSecurityPanel();
    else if(t==='cache')W.renderCachePanel();
    else if(t==='update')W.renderUpdatePanel();
    else if(t==='typography')W.renderTypographyPanel();
    else if(t==='reconciliation')W.renderCatalogReconciliation();
    else W.renderSettingsGeneral();
};

W.renderThemesPanel=async function(){
    var panel=document.getElementById('settings-panel'); if(!panel)return;
    panel.innerHTML='<div style="padding:20px;color:var(--w-muted)">Cargando mercado de temas…</div>';
    var r=await W.api('/api/v1/admin/themes/marketplace'); var d=r.data||{};
    var themes=d.themes||[];
    var tierChip=function(m){
        if(m.tier==='premium'){
            if(m.locked)return '<span class="w-badge" style="background:rgba(242,13,22,.14);color:#f20d16">🔒 PREMIUM</span>';
            return '<span class="w-badge" style="background:rgba(52,211,153,.16);color:#34d399">★ PREMIUM ACTIVO</span>';
        }
        return '<span class="w-badge" style="background:var(--w-surface);color:var(--w-muted)">GRATIS</span>';
    };
    var html='<div class="w-card" style="padding:14px 18px;margin-bottom:14px"><div style="font-size:13px;font-weight:700">Mercado de temas</div><div style="font-size:11px;color:var(--w-muted);margin-top:4px;line-height:1.7">Instala y activa la apariencia de tu sitio. Los temas <b>GRATIS</b> se activan con un clic. Los <b>PREMIUM</b> requieren un <b>código de instalación</b> de 16 caracteres que solo el equipo de Wontia entrega.</div></div>';
    html+='<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px">';
    themes.forEach(function(m){
        html+='<div class="w-card" style="padding:16px;display:flex;flex-direction:column;gap:8px'+(m.active?';border-color:rgba(34,211,238,.5)':'')+'">';
        html+='<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px"><strong style="font-size:14px">'+W.esc(m.name)+'</strong>'+tierChip(m)+'</div>';
        html+='<div style="font-size:11px;color:var(--w-muted)">v'+W.esc(m.version||'1.0.0')+' · '+W.esc(m.author||'Wontia')+' · '+W.esc(m.category||'general')+'</div>';
        html+='<div style="font-size:12px;color:var(--w-muted);line-height:1.6;flex:1">'+W.esc(m.description||'')+'</div>';
        html+='<div class="w-flex w-gap-sm" style="flex-wrap:wrap;margin-top:4px">';
        if(m.active){ html+='<span class="w-btn w-btn-secondary" style="pointer-events:none">✓ Activo</span><button class="w-btn w-btn-primary" onclick="wontia.themeConfig(\''+W.esc(m.slug)+'\')">⚙ Configurar</button>'; }
        else if(m.locked){ html+='<button class="w-btn w-btn-primary" onclick="wontia.themeRedeem(\''+W.esc(m.slug)+'\')">🔒 Desbloquear</button>'; }
        else { html+='<button class="w-btn w-btn-primary" onclick="wontia.themeActivate(\''+W.esc(m.slug)+'\')">Activar</button>'; }
        html+='<a class="w-btn w-btn-secondary" href="/" target="_blank">Vista ↗</a>';
        html+='</div></div>';
    });
    html+='</div>';

    if(d.can_generate){
        html+='<div class="w-card" style="margin-top:16px"><h3>Códigos de instalación (equipo Wontia)</h3>';
        html+='<div style="font-size:11px;color:var(--w-muted);margin-bottom:10px">Genera un serial de 16 caracteres para un theme premium. Se muestra una sola vez; entrégalo al cliente.</div>';
        var prem=themes.filter(function(m){return m.tier==='premium'});
        html+='<div class="w-flex w-gap-sm" style="flex-wrap:wrap;align-items:flex-end"><div class="w-form-group" style="margin:0"><label class="w-label">Theme</label><select class="w-select" id="sk-serial-theme">'+prem.map(function(m){return '<option value="'+W.esc(m.slug)+'">'+W.esc(m.name)+'</option>'}).join('')+'</select></div><div class="w-form-group" style="margin:0"><label class="w-label">Etiqueta / cliente (opcional)</label><input class="w-input" id="sk-serial-label" placeholder="Ej: sonika.com.co"/></div><button class="w-btn w-btn-primary" onclick="wontia.themeGen()">Generar código</button></div>';
        html+='<div id="sk-serial-out" style="margin-top:12px"></div>';
        var serials=d.serials||[];
        if(serials.length){
            html+='<div style="margin-top:16px;font-size:12px"><b>Seriales recientes</b></div><table class="w-table" style="margin-top:6px"><thead><tr><th>Código (id)</th><th>Theme</th><th>Etiqueta</th><th>Estado</th><th></th></tr></thead><tbody>';
            serials.forEach(function(s){
                var st=s.revoked_at?'revocado':(s.bound_site_id?('sitio '+s.bound_site_id):'disponible');
                html+='<tr><td>#'+s.id+'</td><td>'+W.esc(s.theme_slug)+'</td><td>'+W.esc(s.label||'—')+'</td><td>'+W.esc(st)+'</td><td>'+(s.revoked_at?'':'<button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.themeRevoke('+s.id+')">Revocar</button>')+'</td></tr>';
            });
            html+='</tbody></table>';
        }
        html+='</div>';
    }
    panel.innerHTML=html+'<div id="sk-cfg-box" style="margin-top:14px"></div>';
};

W.themeActivate=async function(slug){
    var r=await W.api('/api/v1/admin/themes/activate',{method:'POST',body:{theme:slug}});
    if(r.ok){W.notify(r.message||'Tema activado','success');W.renderThemesPanel();}
};

W.themeRedeem=function(slug){
    var body='<div style="font-size:12px;color:var(--w-muted);margin-bottom:10px">Introduce el código de instalación de 16 caracteres para el theme <b>'+W.esc(slug)+'</b>.</div>'+
        '<div class="w-form-group"><label class="w-label">Código de instalación</label><input class="w-input" id="sk-redeem-code" placeholder="A1b2!..." autocomplete="off" style="font-family:\'JetBrains Mono\',monospace"/></div>';
    W.modal('Desbloquear theme premium',body,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancelar</button><button class="w-btn w-btn-primary" id="sk-redeem-go">Activar licencia</button>');
    var go=document.getElementById('sk-redeem-go'); go.addEventListener('click',async function(){
        var code=(document.getElementById('sk-redeem-code').value||'').trim();
        if(!code){W.notify('Introduce el código','error');return}
        var r=await W.api('/api/v1/admin/themes/redeem',{method:'POST',body:{code:code}});
        if(r.ok){ W.closeModal(); W.notify(r.message||'Licencia activada','success'); var a=await W.api('/api/v1/admin/themes/activate',{method:'POST',body:{theme:r.theme||slug}}); if(a.ok)W.notify(a.message,'success'); W.renderThemesPanel(); }
    });
};

W.themeGen=async function(){
    var theme=(document.getElementById('sk-serial-theme')||{}).value||'';
    var label=(document.getElementById('sk-serial-label')||{}).value||'';
    var r=await W.api('/api/v1/admin/themes/serials',{method:'POST',body:{theme:theme,label:label}});
    if(!r.ok)return;
    var out=document.getElementById('sk-serial-out');
    if(out){
        out.innerHTML='<div class="w-card" style="border-color:rgba(52,211,153,.5);padding:14px"><div style="font-size:11px;color:var(--w-muted);margin-bottom:6px">Código para «'+W.esc(theme)+'» (cópialo ahora, no se vuelve a mostrar):</div>'
            +'<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><code id="sk-code" style="font-family:\'JetBrains Mono\',monospace;font-size:16px;letter-spacing:1px;background:var(--w-surface);padding:10px 12px;border-radius:8px">'+W.esc(r.code)+'</code>'
            +'<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.themeCopy()">Copiar</button></div></div>';
        W._lastSerial=r.code;
    }
    W.notify('Código generado','success');
};

W.themeCopy=function(){ try{navigator.clipboard.writeText(W._lastSerial||'');W.notify('Código copiado','success')}catch(e){W.notify('No se pudo copiar','error')} };

W.themeRevoke=async function(id){
    if(!window.confirm('¿Revocar este serial? Se desactivará la licencia del sitio que lo usó.'))return;
    var r=await W.api('/api/v1/admin/themes/serials/'+id,{method:'DELETE'});
    if(r.ok){W.notify('Serial revocado','success');W.renderThemesPanel();}
};

W.renderNavPanel=async function(){
    var panel=document.getElementById('settings-panel'); if(!panel)return;
    panel.innerHTML='<div style="padding:20px;color:var(--w-muted)">Cargando menús…</div>';
    var r=await W.api('/api/v1/admin/nav'); var d=r.data||{};
    var st=await W.api('/api/v1/admin/nav/style'); var s=(st&&st.data)||{};
    var locs=d.locations||{}, menus=d.menus||{};
    var html=W.navAppearance(s);
    html+='<div class="w-card" style="padding:14px 18px;margin-bottom:14px"><div style="font-size:13px;font-weight:700">Navegación administrable</div><div style="font-size:11px;color:var(--w-muted);margin-top:4px;line-height:1.6">Edita los menús del sitio. El tema los consume automáticamente, sin tocar código.</div></div>';
    Object.keys(locs).forEach(function(loc){
        var items=(menus[loc]&&menus[loc].items)||[];
        var tops=items.filter(function(x){return x.parent_id===null});
        html+='<div class="w-card" style="margin-bottom:14px"><h3>'+W.esc(locs[loc])+'</h3>';
        if(items.length){
            html+='<table class="w-table"><thead><tr><th>Etiqueta</th><th>URL</th><th>Visible</th><th>Padre</th><th></th></tr></thead><tbody>';
            items.forEach(function(it){
                var opts='<option value="0">— Raíz —</option>'+tops.filter(function(t){return t.id!==it.id}).map(function(t){return '<option value="'+t.id+'"'+(it.parent_id===t.id?' selected':'')+'>'+W.esc(t.label)+'</option>'}).join('');
                html+='<tr id="navrow-'+it.id+'"><td><input class="w-input nav-label" value="'+W.esc(it.label)+'" style="min-width:130px"/></td>'
                    +'<td><input class="w-input nav-url" value="'+W.esc(it.url)+'" style="min-width:150px"/></td>'
                    +'<td style="text-align:center"><input type="checkbox" class="nav-visible" '+(it.is_visible?'checked':'')+'/></td>'
                    +'<td><select class="w-select nav-parent">'+opts+'</select></td>'
                    +'<td style="white-space:nowrap"><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.navSaveRow('+it.id+',\''+loc+'\')">Guardar</button> <button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.navDel('+it.id+')">✕</button></td></tr>';
            });
            html+='</tbody></table>';
        } else html+='<div style="font-size:12px;color:var(--w-muted);padding:8px 0">Sin elementos.</div>';
        html+='<div class="w-flex w-gap-sm" style="flex-wrap:wrap;margin-top:10px"><input class="w-input" id="nav2l-'+loc+'" placeholder="Etiqueta" style="max-width:180px"/><input class="w-input" id="nav2u-'+loc+'" placeholder="/ruta o https://" style="max-width:220px"/><select class="w-select" id="nav2p-'+loc+'"><option value="0">— Raíz —</option>'+tops.map(function(t){return '<option value="'+t.id+'">'+W.esc(t.label)+'</option>'}).join('')+'</select><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.navAdd(\''+loc+'\')">+ Añadir</button></div>';
        html+='</div>';
    });
    panel.innerHTML=html;
};
W.navAdd=async function(loc){
    var label=(document.getElementById('nav2l-'+loc)||{}).value||'';
    var url=(document.getElementById('nav2u-'+loc)||{}).value||'#';
    var pid=parseInt((document.getElementById('nav2p-'+loc)||{}).value||'0',10);
    if(!label.trim()){W.notify('Falta la etiqueta','error');return}
    var r=await W.api('/api/v1/admin/nav/items',{method:'POST',body:{location:loc,label:label,url:url,parent_id:pid||null,is_visible:true}});
    if(r.ok){W.notify('Añadido','success');W.renderNavPanel();}
};
W.navSaveRow=async function(id,loc){
    var row=document.getElementById('navrow-'+id); if(!row)return;
    var body={id:id,location:loc,label:row.querySelector('.nav-label').value,url:row.querySelector('.nav-url').value,is_visible:row.querySelector('.nav-visible').checked,parent_id:(parseInt(row.querySelector('.nav-parent').value,10)||null)};
    var r=await W.api('/api/v1/admin/nav/items',{method:'POST',body:body});
    if(r.ok){W.notify('Guardado','success');W.renderNavPanel();}
};
W.navDel=async function(id){
    if(!window.confirm('¿Eliminar este elemento del menú?'))return;
    var r=await W.api('/api/v1/admin/nav/items/'+id,{method:'DELETE'});
    if(r.ok){W.notify('Eliminado','success');W.renderNavPanel();}
};
W.loadGf=function(f){f=(f||'').trim();if(!f)return;var id='gf-'+f.replace(/\s+/g,'-');if(document.getElementById(id))return;var l=document.createElement('link');l.id=id;l.rel='stylesheet';l.href='https://fonts.googleapis.com/css2?family='+encodeURIComponent(f)+':wght@400;500;600;700;800&display=swap';document.head.appendChild(l);};
W.navAppearance=function(s){
    s=s||{};
    var align=s.align||'left', pos=s.position||'below', bg=s.bg||'#111111', color=s.color||'#ffffff', hover=s.hover_bg||'#1f1f1f', accent=s.accent||'#F20D16', font=s.font||'Sora', size=s.size||14;
    W._navStyle={align:align,position:pos,bg:bg,color:color,hover_bg:hover,accent:accent,font:font,size:size};
    var fonts=['Sora','Inter','Manrope','Plus Jakarta Sans','Outfit','Poppins','Montserrat','Figtree','DM Sans','Space Grotesk','Work Sans','Rubik','Nunito Sans','Lexend','Public Sans'];
    var sel=fonts.map(function(f){return '<option value="'+W.esc(f)+'"'+(f===font?' selected':'')+'>'+W.esc(f)+'</option>'}).join('');
    var h='<div class="w-card" style="margin-bottom:14px"><div class="w-flex-between" style="flex-wrap:wrap;gap:8px"><h3 style="margin:0">🎨 Apariencia de la barra</h3><div class="w-flex w-gap-sm"><button class="w-btn w-btn-secondary" onclick="wontia.navPreset(\'dark\')">Oscura</button><button class="w-btn w-btn-secondary" onclick="wontia.navPreset(\'light\')">Clara</button><button class="w-btn w-btn-secondary" onclick="wontia.navPreset(\'brand\')">Marca</button><button class="w-btn w-btn-primary" onclick="wontia.navStyleSave()">💾 Guardar</button></div></div>';
    h+='<div style="font-size:11px;color:var(--w-muted);margin:6px 0 12px">Estilo del menú principal (Inicio · Tienda · Ofertas · Marcas · Contacto · Soporte).</div>';
    h+='<div class="w-form-group"><label class="w-label">Alineación del menú</label><div class="w-flex w-gap-sm" id="nav-align">'
      +['left','center','right'].map(function(a){return '<button type="button" class="w-btn '+(a===align?'w-btn-primary':'w-btn-secondary')+'" data-align="'+a+'" onclick="wontia.navAlign(\''+a+'\')">'+({left:'⬅ Izquierda',center:'↔ Centro',right:'Derecha ➡'})[a]+'</button>'}).join('')
      +'</div></div>';
    h+='<div class="w-form-group"><label class="w-label">Posición de la barra</label><div class="w-flex w-gap-sm" id="nav-pos">'
      +['above','below'].map(function(p){return '<button type="button" class="w-btn '+(p===pos?'w-btn-primary':'w-btn-secondary')+'" data-pos="'+p+'" onclick="wontia.navPos(\''+p+'\')">'+({above:'⬆ Arriba (sobre logo, buscador y cuenta/carrito)',below:'⬇ Abajo (actual)'})[p]+'</button>'}).join('')
      +'</div></div>';
    h+='<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px;margin-top:6px">'
      +W.colorPick('navbg','Color de fondo',bg)
      +W.colorPick('navfg','Color de letras',color)
      +W.colorPick('navhover','Color al pasar el mouse',hover)
      +W.colorPick('navaccent','Color de acento (activo/oferta)',accent)
      +'<div class="w-form-group"><label class="w-label">Fuente</label><select class="w-select" id="nav-font">'+sel+'</select></div>'
      +'<div class="w-form-group"><label class="w-label">Tamaño de letra</label><input type="range" min="11" max="22" value="'+size+'" id="nav-size" oninput="document.getElementById(\'nav-size-v\').textContent=this.value+\'px\';wontia.navPreview()" style="width:100%"/><div id="nav-size-v" style="font-size:11px;color:var(--w-muted)">'+size+'px</div></div>'
      +'</div>';
    h+='<div style="margin-top:14px"><label class="w-label">Vista previa</label><div id="nav-preview"></div></div></div>';
    setTimeout(function(){W.colorPickInit();var f=document.getElementById('nav-font');if(f)f.addEventListener('change',W.navPreview);W.navPreview();},0);
    return h;
};
W.colorPick=function(id,label,val){
    var sw=['#111111','#ffffff','#F20D16','#C70A12','#2b2b2b','#F5F5F5','#0E9F6E','#1E40AF','#B45309','#7C3AED'];
    return '<div class="w-form-group"><label class="w-label">'+W.esc(label)+'</label>'
      +'<div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap"><input type="color" id="cp-'+id+'" value="'+W.esc(val)+'" oninput="wontia.cpSync(\''+id+'\',\'c\')" style="width:40px;height:34px;border:1px solid var(--w-border);border-radius:8px;background:none;cursor:pointer"/>'
      +'<input class="w-input" id="cph-'+id+'" value="'+W.esc(val)+'" oninput="wontia.cpSync(\''+id+'\',\'h\')" style="max-width:110px;font-family:monospace"/>'
      +'<button type="button" class="w-btn w-btn-secondary w-btn-sm" id="cpe-'+id+'" title="Cuentagotas" onclick="wontia.cpEye(\''+id+'\')">🎧</button></div>'
      +'<div id="cpr-'+id+'" style="font-size:10px;color:var(--w-muted);font-family:monospace;margin-top:2px"></div>'
      +'<div style="display:flex;gap:4px;flex-wrap:wrap;margin-top:5px">'+sw.map(function(c){return '<button type="button" onclick="wontia.cpSet(\''+id+'\',\''+c+'\')" title="'+c+'" style="width:18px;height:18px;border-radius:5px;border:1px solid var(--w-border);background:'+c+';cursor:pointer"></button>'}).join('')+'</div></div>';
};
W.cpSync=function(id,src){
    var c=document.getElementById('cp-'+id), hx=document.getElementById('cph-'+id), rgb=document.getElementById('cpr-'+id);
    if(src==='c'&&c&&hx)hx.value=c.value;
    if(src==='h'&&c&&hx&&/^#[0-9a-fA-F]{6}$/.test(hx.value))c.value=hx.value;
    var v=(c&&c.value)||'#000000'; if(rgb){rgb.textContent=W.hexToRgb(v)+'  '+v}
    W.navPreview();
};
W.cpSet=function(id,val){var c=document.getElementById('cp-'+id),hx=document.getElementById('cph-'+id);if(c)c.value=val;if(hx)hx.value=val;W.cpSync(id,'c')};
W.cpEye=async function(id){if(!window.EyeDropper){W.notify('El cuentagotas no está disponible en este navegador','error');return}try{var r=await new EyeDropper().open();W.cpSet(id,r.sRGBHex)}catch(e){}};
W.hexToRgb=function(h){h=(h||'').replace('#','');if(h.length!==6)return '';var n=parseInt(h,16);return 'rgb('+((n>>16)&255)+', '+((n>>8)&255)+', '+(n&255)+')'};
W.colorPickInit=function(){['navbg','navfg','navhover','navaccent'].forEach(function(id){var c=document.getElementById('cp-'+id),hx=document.getElementById('cph-'+id);if(c&&hx)W.cpSync(id,'c')})};
W.navAlign=function(a){if(!W._navStyle)W._navStyle={};W._navStyle.align=a;document.querySelectorAll('#nav-align [data-align]').forEach(function(b){b.className='w-btn '+(b.getAttribute('data-align')===a?'w-btn-primary':'w-btn-secondary')});W.navPreview()};
W.navPos=function(p){if(!W._navStyle)W._navStyle={};W._navStyle.position=p;document.querySelectorAll('#nav-pos [data-pos]').forEach(function(b){b.className='w-btn '+(b.getAttribute('data-pos')===p?'w-btn-primary':'w-btn-secondary')});};
W.navPreview=function(){
    var box=document.getElementById('nav-preview'); if(!box)return;
    var st=W._navStyle||{};
    var g=function(id,d){var e=document.getElementById('cph-'+id);return (e&&e.value)||d};
    var bg=g('navbg',st.bg), fg=g('navfg',st.color), hv=g('navhover',st.hover_bg), ac=g('navaccent',st.accent);
    var fo=((document.getElementById('nav-font')||{}).value)||st.font, sz=((document.getElementById('nav-size')||{}).value)||st.size;
    W.loadGf(fo);
    var just=({left:'flex-start',center:'center',right:'flex-end'})[st.align]||'flex-start';
    var it=function(t,hot){return '<span style="color:'+fg+';font-family:\''+W.esc(fo)+'\',sans-serif;font-size:'+sz+'px;font-weight:600;padding:11px 13px;'+(hot?'border-bottom:2px solid '+ac:'')+'">'+t+'</span>'};
    box.innerHTML='<div style="background:'+bg+';display:flex;justify-content:'+just+';gap:2px;border-radius:8px;overflow:auto">'+it('Inicio')+it('Tienda')+it('Ofertas',1)+it('Marcas')+it('Contacto')+it('Soporte')+'</div>';
};
W.navPreset=function(kind){
    var p={dark:{bg:'#111111',color:'#ffffff',hover_bg:'#1f1f1f',accent:'#F20D16'},light:{bg:'#F5F5F5',color:'#111111',hover_bg:'#E6E6E6',accent:'#F20D16'},brand:{bg:'#F20D16',color:'#ffffff',hover_bg:'#C70A12',accent:'#ffffff'}}[kind];
    if(!p)return;var map={bg:'navbg',color:'navfg',hover_bg:'navhover',accent:'navaccent'};
    Object.keys(map).forEach(function(k){var id=map[k],c=document.getElementById('cp-'+id),hx=document.getElementById('cph-'+id);if(c)c.value=p[k];if(hx)hx.value=p[k];W.cpSync(id,'c')});
    W.navPreview();
};
W.navStyleSave=async function(){
    var st=W._navStyle||{};
    var g=function(id,d){var e=document.getElementById('cph-'+id);return (e&&e.value)||d};
    var body={align:st.align||'left',position:st.position||'below',bg:g('navbg','#111111'),color:g('navfg','#ffffff'),hover_bg:g('navhover','#1f1f1f'),accent:g('navaccent','#F20D16'),font:((document.getElementById('nav-font')||{}).value)||'Sora',size:parseInt(((document.getElementById('nav-size')||{}).value)||'14',10)};
    var r=await W.api('/api/v1/admin/nav/style',{method:'POST',body:body});
    if(r&&r.ok)W.notify('Apariencia de la barra guardada','success');
};
W.themeConfig=async function(slug){
    var r=await W.api('/api/v1/admin/themes/'+encodeURIComponent(slug)+'/config');
    if(!r.ok)return;
    var d=r.data||{}; W.state.themeCfgSchema=d.schema||[]; W.state.themeCfgSlug=slug;
    var box=document.getElementById('sk-cfg-box'); if(!box)return;
    if(!W.state.themeCfgSchema.length){ box.innerHTML='<div class="w-card"><h3>Configurar «'+W.esc(slug)+'»</h3><div style="font-size:12px;color:var(--w-muted)">Este tema no declara campos configurables (theme.json → config).</div></div>'; box.scrollIntoView({behavior:'smooth'}); return; }
    box.innerHTML='<div class="w-card"><h3>Configurar «'+W.esc(slug)+'»</h3><div style="font-size:11px;color:var(--w-muted);margin-bottom:8px">Estos campos se guardan como settings del sitio y el tema los usa automáticamente.</div>'+W.renderSchemaFields(W.state.themeCfgSchema, d.values||{})+'<div class="w-flex w-gap-sm" style="margin-top:12px"><button class="w-btn w-btn-primary" onclick="wontia.themeCfgSave()">Guardar</button><button class="w-btn w-btn-secondary" onclick="wontia.renderThemesPanel()">Cancelar</button></div></div>';
    if(W.repInit)W.repInit(W.state.themeCfgSchema);
    box.scrollIntoView({behavior:'smooth'});
};
W.themeCfgSave=async function(){
    var slug=W.state.themeCfgSlug; if(!slug)return;
    var values=W.collectSchemaFields(W.state.themeCfgSchema);
    var r=await W.api('/api/v1/admin/themes/'+encodeURIComponent(slug)+'/config',{method:'PUT',body:{values:values}});
    if(r.ok)W.notify(r.message||'Guardado','success');
};

W.agentBridgeOpen=function(){
    W.modal('Canal de agentes',
      '<div id="w-bridge-feed" style="max-height:52vh;overflow:auto;font-size:12px">Cargando…</div>'
      +'<div class="w-form-group" style="margin-top:10px"><label class="w-label">Responder como Head Team Leader</label><textarea class="w-textarea" id="w-bridge-reply" placeholder="Escribe una ORDEN / RESPUESTA…"></textarea></div>'
      +'<div class="w-flex w-gap-sm" style="flex-wrap:wrap"><select class="w-select" id="w-bridge-type" style="max-width:160px"><option>ORDEN</option><option>RESPUESTA</option><option>ACLARACION</option><option>NOTA</option></select><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.agentBridgePost()">Publicar</button><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.agentBridgeTasks()">Tareas</button><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.agentBridgeExport()">Exportar .md</button><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.agentBridgeAgents()">Agentes</button><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.closeModal()">Cerrar</button></div>','');
    W.agentBridgeLoad();
    if(W.agentBridgeTimer)clearInterval(W.agentBridgeTimer);
    W.agentBridgeTimer=setInterval(W.agentBridgeLoad,5000);
    var ov=document.getElementById('w-modal');
    if(ov&&!W._bridgeObs){ W._bridgeObs=new MutationObserver(function(){if(ov.style.display==='none'){clearInterval(W.agentBridgeTimer);W.agentBridgeTimer=null}}); W._bridgeObs.observe(ov,{attributes:true,attributeFilter:['style']}); }
};
W.agentBridgeLoad=async function(){
    var box=document.getElementById('w-bridge-feed'); if(!box)return;
    var r=await W.api('/api/v1/admin/agent-bridge'); var d=r.data||{};
    var msgs=d.messages||[]; var max=0;
    var cols={SOLICITUD:'#f59e0b',ORDEN:'#22d3ee',RESULTADO:'#34d399',RESPUESTA:'#8b5cf6',ACLARACION:'#94a3b8',NOTA:'#64748b'};
    var html='';
    msgs.forEach(function(m){
        if(m.id>max)max=m.id;
        var mine=(m.from_role==='head-team-leader');
        html+='<div style="margin:8px 0;padding:8px 10px;border-radius:8px;background:'+(mine?'rgba(34,211,238,.08)':'var(--w-surface)')+';border:1px solid var(--w-border)">'
          +'<div style="font-size:10px;color:var(--w-muted);display:flex;gap:8px;align-items:center;flex-wrap:wrap"><span style="color:'+(cols[m.type]||'#64748b')+';font-weight:800">'+W.esc(m.type)+'</span><b>'+W.esc(m.from_role)+'</b> → '+W.esc(m.to_role)+(m.site_slug?' · '+W.esc(m.site_slug):'')+' · '+W.esc(m.created_at)+' · '+W.esc(m.status)+'</div>'
          +(m.subject?'<div style="font-weight:600;margin-top:4px">'+W.esc(m.subject)+'</div>':'')
          +'<div style="margin-top:4px;white-space:pre-wrap;line-height:1.5">'+W.esc(m.body)+'</div>'
          +(m.status!=='resuelto'?'<div style="margin-top:6px"><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.agentBridgeResolve('+m.id+')">✓ Resolver</button></div>':'')
          +'</div>';
    });
    if(!msgs.length)html='<div style="color:var(--w-muted);padding:16px;text-align:center">Sin mensajes aún.</div>';
    box.innerHTML=html; box.scrollTop=box.scrollHeight;
    W.agentBridgeLastId=max; try{localStorage.setItem('wwi_bridge_last',String(max))}catch(e){}
    W.agentBridgePaintBadge(0);
};
W.agentBridgePost=async function(){
    var body=(document.getElementById('w-bridge-reply')||{}).value||'';
    var type=(document.getElementById('w-bridge-type')||{}).value||'ORDEN';
    if(!body.trim()){W.notify('Escribe un mensaje','error');return}
    var r=await W.api('/api/v1/admin/agent-bridge',{method:'POST',body:{type:type,body:body,from_role:'head-team-leader',to_role:'agent',status:'abierto'}});
    if(r.ok){document.getElementById('w-bridge-reply').value='';W.agentBridgeLoad();}
};
W.agentBridgeAgents=async function(){
    var r=await W.api('/api/v1/admin/agent-bridge/agents');
    var list=(r.data||[]);
    var html='<div style="font-size:12px;max-height:40vh;overflow:auto">'+(list.length?list.map(function(a){return '<div style="padding:6px 0;border-bottom:1px solid var(--w-border)"><b>'+W.esc(a.slug)+'</b> · '+W.esc(a.name||'')+' · site '+W.esc(a.site_id||'-')+' · '+(a.is_active?'activo':'inactivo')+' · visto '+W.esc(a.last_seen||'-')+'</div>'}).join(''):'<div style="color:var(--w-muted)">Sin agentes.</div>')+'</div>'
      +'<div class="w-flex w-gap-sm" style="margin-top:10px;flex-wrap:wrap"><input class="w-input" id="w-ag-slug" placeholder="slug (sonika-agent)" style="max-width:200px"/><input class="w-input" id="w-ag-name" placeholder="Nombre" style="max-width:200px"/><input class="w-input" id="w-ag-site" placeholder="site_id" type="number" style="max-width:100px"/><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.agentBridgeCreate()">Crear clave</button></div><div id="w-ag-out" style="margin-top:10px"></div>';
    W.modal('Agentes del canal', html, '<button class="w-btn w-btn-secondary" onclick="wontia.agentBridgeOpen()">Volver al canal</button>');
};
W.agentBridgeCreate=async function(){
    var slug=(document.getElementById('w-ag-slug')||{}).value||'', name=(document.getElementById('w-ag-name')||{}).value||'', sid=(document.getElementById('w-ag-site')||{}).value||'';
    var r=await W.api('/api/v1/admin/agent-bridge/agents',{method:'POST',body:{slug:slug,name:name,site_id:sid||null}});
    if(r.ok){var o=document.getElementById('w-ag-out'); if(o)o.innerHTML='<div class="w-card" style="border-color:rgba(52,211,153,.5);padding:12px"><div style="font-size:11px;color:var(--w-muted)">Clave de «'+W.esc(r.slug)+'» (cópiala ahora):</div><code style="font-family:monospace;font-size:14px;word-break:break-all">'+W.esc(r.key)+'</code></div>';}
};
W.agentBridgePaintBadge=function(n){var b=document.getElementById('w-bridge-badge'); if(!b)return; if(n>0){b.textContent=n;b.style.display='block'}else{b.style.display='none'}};
W.agentBridgeResolve=async function(id){
    var r=await W.api('/api/v1/admin/agent-bridge/'+id,{method:'PATCH',body:{status:'resuelto'}});
    if(r.ok){W.notify('Resuelto','success');W.agentBridgeLoad();}
};
W.agentBridgeExport=async function(){
    try{
        var r=await fetch('/api/v1/admin/agent-bridge/export',{headers:{'Authorization':'Bearer '+(W.token||'')}});
        var t=await r.text();
        W.modal('Puente (markdown)', '<pre style="max-height:60vh;overflow:auto;white-space:pre-wrap;font-size:11px">'+W.esc(t)+'</pre>', '<button class="w-btn w-btn-primary" onclick="wontia.agentBridgeOpen()">Volver al canal</button>');
    }catch(e){W.notify('No se pudo exportar','error')}
};
W.agentBridgeTasks=async function(){
    var r=await W.api('/api/v1/admin/agent-bridge/tasks'); var list=(r.data||[]);
    var pend=list.filter(function(t){return t.status!=='done'}).length;
    var html='<div style="font-size:12px;max-height:52vh;overflow:auto">'+(list.length?list.map(function(t){
        var col=t.status==='done'?'#34d399':(t.status==='in_progress'?'#22d3ee':'#f59e0b');
        return '<div style="padding:8px 0;border-bottom:1px solid var(--w-border)"><div style="display:flex;gap:8px;align-items:center"><span style="color:'+col+';font-weight:800">'+W.esc(t.status)+'</span><b>'+W.esc(t.title||'')+'</b>'+(t.site_slug?' · '+W.esc(t.site_slug):'')+'</div><div style="color:var(--w-muted);white-space:pre-wrap;margin-top:4px">'+W.esc((t.detail||'').slice(0,240))+'</div>'+(t.status!=='done'?'<button class="w-btn w-btn-secondary w-btn-sm" style="margin-top:6px" onclick="wontia.agentBridgeTaskDone('+t.id+')">✓ Marcar hecha</button>':'')+'</div>';
    }).join(''):'<div style="color:var(--w-muted)">Sin tareas de plataforma.</div>')+'</div>';
    W.modal('Tareas de plataforma ('+pend+' pendientes)', html, '<button class="w-btn w-btn-primary" onclick="wontia.agentBridgeOpen()">Volver al canal</button>');
};
W.agentBridgeTaskDone=async function(id){
    var r=await W.api('/api/v1/admin/agent-bridge/tasks/'+id,{method:'PATCH',body:{status:'done'}});
    if(r.ok){W.notify('Tarea cerrada','success');W.agentBridgeTasks();}
};
W.agentBridgePoll=function(){
    if(!document.getElementById('w-bridge-bell'))return;
    var check=async function(){ try{ var r=await W.api('/api/v1/admin/agent-bridge'); var d=r.data||{}; var max=d.max_id||0; var last=parseInt(localStorage.getItem('wwi_bridge_last')||'0',10); if(max>last && !document.getElementById('w-bridge-feed')) W.agentBridgePaintBadge(max-last); }catch(e){} };
    check(); setInterval(check, 60000);
};

W.factoryWizard=async function(){
    var plansR=await W.api('/api/v1/admin/factory/plans'); var plans=(plansR.data||[]);
    var opts=plans.map(function(p){return '<option value="'+W.esc(p.slug)+'">'+W.esc(p.name||p.slug)+'</option>'}).join('');
    var themesR=await W.api('/api/v1/admin/themes/marketplace'); var themes=((themesR.data||{}).themes||[]);
    var topts=themes.filter(function(t){return !t.locked}).map(function(t){return '<option value="'+W.esc(t.slug)+'"'+(t.slug==='default'?' selected':'')+'>'+W.esc(t.name)+'</option>'}).join('');
    var body='<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">'
      +'<div class="w-form-group"><label class="w-label">Cliente *</label><input class="w-input" id="pw-client"/></div>'
      +'<div class="w-form-group"><label class="w-label">Email *</label><input class="w-input" id="pw-email" type="email"/></div>'
      +'<div class="w-form-group"><label class="w-label">Dominio (vacío = subdominio .wontia.com)</label><input class="w-input" id="pw-domain" placeholder="cliente.com"/></div>'
      +'<div class="w-form-group"><label class="w-label">Plan</label><select class="w-select" id="pw-plan">'+opts+'</select></div>'
      +'<div class="w-form-group"><label class="w-label">Tema</label><select class="w-select" id="pw-theme">'+topts+'</select></div>'
      +'<div class="w-form-group"><label class="w-label">Sector (opcional)</label><input class="w-input" id="pw-sector"/></div>'
      +'</div><div style="font-size:11px;color:var(--w-muted);margin-top:6px">Crea site + usuario + deploy automático (contenedor/SSL) en &lt;1 min.</div><div id="pw-out" style="margin-top:10px"></div>';
    W.modal('Nuevo sitio (onboarding)', body, '<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancelar</button><button class="w-btn w-btn-primary" id="pw-go">Provisionar</button>');
    var go=document.getElementById('pw-go'); if(go)go.addEventListener('click',W.factoryProvision);
};
W.factoryProvision=async function(){
    var g=function(id){return (document.getElementById(id)||{}).value||''};
    var body={client:g('pw-client'),email:g('pw-email'),domain:g('pw-domain'),plan:g('pw-plan'),theme:g('pw-theme'),sector:g('pw-sector')};
    if(!body.client||!body.email){W.notify('Cliente y email son obligatorios','error');return}
    W.notify('Provisionando… (puede tardar unos segundos)','info');
    var r=await W.api('/api/v1/admin/factory/provision',{method:'POST',body:body});
    var o=document.getElementById('pw-out');
    if(r.ok && o){ o.innerHTML='<div class="w-card" style="border-color:rgba(52,211,153,.5);padding:12px;font-size:12px;line-height:1.8"><b>¡Sitio creado!</b> · tenant #'+r.tenant_id+'<br>Admin: <a href="'+W.esc(r.admin_url||'')+'" target="_blank">'+W.esc(r.admin_url||'')+'</a><br>Usuario: <b>'+W.esc(r.username||'')+'</b><br>Contraseña: <code>'+W.esc(r.password||'')+'</code><br><span style="color:var(--w-muted)">El contenedor y SSL se crean por el auto-deployer en &lt;1 min.</span></div>'; W.notify('Sitio creado','success'); }
};

W.loadQrLib=function(){
    return new Promise(function(res){
        if(window.QRCode)return res(true);
        var s=document.createElement('script');
        s.src='https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js';
        s.onload=function(){res(!!window.QRCode)};
        s.onerror=function(){res(false)};
        document.head.appendChild(s);
    });
};

W.renderSecurityPanel=async function(){
    var panel=document.getElementById('settings-panel'); if(!panel)return;
    panel.innerHTML='<div style="padding:20px;color:var(--w-muted)">Cargando…</div>';
    var d=await W.api('/api/v1/admin/auth/2fa/status'); var o=d.data||{};
    var html='<div class="w-card"><h3>Acceso y seguridad</h3><div style="font-size:11px;color:var(--w-muted);line-height:1.7;margin-bottom:4px">Protege tu cuenta con autenticación en dos pasos (Google Authenticator) y recuperación por correo.</div></div>';
    html+='<div class="w-card"><h3>Autenticación en dos pasos (2FA)</h3>';
    html+='<div style="font-size:11px;color:var(--w-muted);line-height:1.7;margin-bottom:12px">Además de tu contraseña, al iniciar sesión pediremos un código temporal de 6 dígitos generado por tu app.</div>';
    if(o.enabled){
        html+='<div style="font-size:12px;margin-bottom:4px">Estado: <b style="color:#34d399">ACTIVO</b></div>';
        html+='<div style="font-size:11px;color:var(--w-muted)">Activado: '+W.esc(o.confirmed_at||'—')+' · Códigos de recuperación restantes: <b>'+o.recovery_remaining+'</b></div>';
        html+='<div class="w-flex w-gap-sm" style="margin-top:12px;flex-wrap:wrap"><button class="w-btn w-btn-secondary" onclick="wontia.secRegenRecovery()">Regenerar códigos de recuperación</button><button class="w-btn w-btn-danger" onclick="wontia.secDisable()">Desactivar 2FA</button></div>';
    }else{
        html+='<div style="font-size:12px;margin-bottom:10px">Estado: <b style="color:#fbbf24">INACTIVO</b></div>';
        if(!o.mail_configured){ html+='<div style="font-size:11px;color:#fbbf24;margin-bottom:10px">Aviso: el correo SMTP no está configurado; la recuperación por email no podrá enviar códigos.</div>'; }
        html+='<button class="w-btn w-btn-primary" onclick="wontia.secSetup()">Activar 2FA</button>';
    }
    html+='</div>';
    html+='<div class="w-card"><h3>Recuperación de contraseña</h3><div style="font-size:11px;color:var(--w-muted);line-height:1.7">En la pantalla de login, el enlace <b>¿Olvidaste tu contraseña?</b> envía un código OTP de un solo uso al correo registrado del usuario. Correo SMTP: <b>'+(o.mail_configured?'configurado':'no configurado')+'</b>.</div></div>';
    html+='<div class="w-card"><div class="w-flex-between" style="flex-wrap:wrap;gap:8px"><h3 style="margin:0">🛡️ Escaneo de seguridad</h3><button class="w-btn w-btn-primary" onclick="wontia.secScan()">↻ Escanear ahora</button></div><div id="sec-scan" style="margin-top:10px"></div></div>';
    html+='<div id="sec-setup"></div>';
    panel.innerHTML=html;
    W.secScanLoad();
};
W.secScanLoad=async function(){
    var box=document.getElementById('sec-scan'); if(!box)return;
    box.innerHTML='<div style="color:var(--w-muted);font-size:12px">Cargando informe…</div>';
    var r=await W.api('/api/v1/admin/security/scan'); W._secRender(r&&r.data);
};
W.secScan=async function(){
    var box=document.getElementById('sec-scan'); if(box)box.innerHTML='<div style="color:var(--w-muted);font-size:12px">Escaneando…</div>';
    var r=await W.api('/api/v1/admin/security/scan',{method:'POST',body:{}});
    if(r&&r.ok)W.notify('Escaneo completado','success');
    W._secRender(r&&r.data);
};
W._secRender=function(d){
    var box=document.getElementById('sec-scan'); if(!box)return;
    if(!d){box.innerHTML='<div style="font-size:12px;color:var(--w-muted)">Sin informe. Pulsa “Escanear ahora”.</div>';return}
    var sc={high:'#DC2626',medium:'#b45309',low:'#6b7280'};
    var h='<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px"><div class="w-card" style="flex:1;min-width:120px;text-align:center;margin:0;padding:10px"><div style="font-size:22px;font-weight:800;color:'+(d.score>=80?'#15803d':(d.score>=60?'#b45309':'#DC2626'))+'">'+W.num(d.score)+'/100</div><div style="font-size:11px;color:var(--w-muted)">Score</div></div><div class="w-card" style="flex:1;min-width:120px;text-align:center;margin:0;padding:10px"><div style="font-size:22px;font-weight:800;color:#DC2626">'+W.num(d.critical||0)+'</div><div style="font-size:11px;color:var(--w-muted)">Críticos</div></div></div>';
    h+='<div style="font-size:11px;color:var(--w-muted);margin-bottom:8px">Último: '+W.esc(d.generated_at||'-')+' · v'+W.esc(d.version||'-')+'</div>';
    h+='<div style="display:flex;flex-direction:column;gap:3px;max-height:320px;overflow:auto">';
    (d.findings||[]).forEach(function(f){h+='<div style="display:flex;gap:8px;align-items:center;font-size:12px;padding:3px 0;border-bottom:1px dashed var(--w-border)"><span>'+(f.ok?'✅':'❌')+'</span><span style="flex:1">'+W.esc(f.label)+'</span><span class="w-badge" style="background:'+(sc[f.severity]||'#666')+'22;color:'+(sc[f.severity]||'#666')+'">'+W.esc(String(f.severity||'').toUpperCase())+'</span><span style="color:var(--w-muted);font-family:monospace;font-size:10px">'+W.esc(f.detail||'')+'</span></div>'});
    h+='</div>';
    box.innerHTML=h;
};

W.secSetup=async function(){
    var r=await W.api('/api/v1/admin/auth/2fa/setup',{method:'POST'});
    if(!r.ok)return;
    var d=r.data||{};
    var box=document.getElementById('sec-setup');
    if(!box)return;
    box.innerHTML='<div class="w-card"><h3>Escanea el código QR</h3>'+
        '<div style="font-size:11px;color:var(--w-muted);margin-bottom:12px">Abre Google Authenticator → “+” → Escanear código QR. Si no puedes escanear, añade la clave manual.</div>'+
        '<div id="sec-qr" style="background:#fff;width:220px;height:220px;border-radius:12px;display:flex;align-items:center;justify-content:center;margin-bottom:12px"></div>'+
        '<div style="font-size:11px;color:var(--w-muted);margin-bottom:6px">Clave manual:</div>'+
        '<code style="font-family:\'JetBrains Mono\',monospace;font-size:13px;letter-spacing:1px;background:var(--w-surface);padding:8px 10px;border-radius:8px;display:inline-block;word-break:break-all">'+W.esc(d.secret)+'</code>'+
        '<div class="w-form-group" style="margin-top:14px;max-width:220px"><label class="w-label">Código de 6 dígitos de la app</label><input class="w-input" id="sec-enable-code" inputmode="numeric" maxlength="6" placeholder="000000"/></div>'+
        '<div class="w-flex w-gap-sm"><button class="w-btn w-btn-primary" onclick="wontia.secEnable()">Verificar y activar</button><button class="w-btn w-btn-secondary" onclick="wontia.renderSecurityPanel()">Cancelar</button></div></div>';
    var ok=await W.loadQrLib();
    var qr=document.getElementById('sec-qr');
    if(ok&&window.QRCode){
        var canvas=document.createElement('canvas');
        qr.appendChild(canvas);
        try{
            window.QRCode.toCanvas(canvas,d.otpauth_uri,{width:200,margin:1},function(e){
                if(e)qr.innerHTML='<span style="color:#111;font-size:11px;padding:10px;text-align:center">No se pudo dibujar el QR. Usa la clave manual.</span>';
            });
        }catch(e){ qr.innerHTML='<span style="color:#111;font-size:11px;padding:10px;text-align:center">Usa la clave manual.</span>'; }
    }else{
        qr.innerHTML='<span style="color:#111;font-size:11px;padding:10px;text-align:center">QR no disponible sin conexión. Usa la clave manual.</span>';
    }
};

W.secEnable=async function(){
    var code=(document.getElementById('sec-enable-code')||{}).value||'';
    var r=await W.api('/api/v1/admin/auth/2fa/enable',{method:'POST',body:{code:code}});
    if(!r.ok)return;
    var codes=(r.data&&r.data.recovery_codes)||[];
    W._secCodes=codes;
    var box=document.getElementById('sec-setup');
    box.innerHTML='<div class="w-card" style="border-color:rgba(52,211,153,.5)"><h3 style="color:#34d399">2FA activado</h3>'+
        '<div style="font-size:11px;color:var(--w-muted);margin-bottom:10px">Guarda estos códigos de recuperación en un lugar seguro. Cada uno sirve una sola vez si pierdes tu dispositivo.</div>'+
        '<div style="font-family:\'JetBrains Mono\',monospace;font-size:13px;line-height:2;background:var(--w-surface);padding:12px;border-radius:10px">'+codes.map(function(c){return W.esc(c)}).join('<br>')+'</div>'+
        '<div class="w-flex w-gap-sm" style="margin-top:12px"><button class="w-btn w-btn-secondary" onclick="wontia.secCopyCodes()">Copiar códigos</button><button class="w-btn w-btn-primary" onclick="wontia.renderSecurityPanel()">Listo</button></div></div>';
    W.notify('2FA activado','success');
};

W.secCopyCodes=function(){
    var txt=(W._secCodes||[]).join('\n');
    try{navigator.clipboard.writeText(txt);W.notify('Códigos copiados','success')}catch(e){W.notify('No se pudo copiar','error')}
};

W.secDisable=function(){
    var body='<div class="w-form-group"><label class="w-label">Contraseña actual</label><input class="w-input" type="password" id="sec-dis-pass" placeholder="Tu contraseña"/></div>'+
        '<div class="w-form-group"><label class="w-label">o un código 2FA / de recuperación</label><input class="w-input" id="sec-dis-code" placeholder="000000" maxlength="9"/></div>';
    W.modal('Desactivar 2FA',body,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancelar</button><button class="w-btn w-btn-danger" id="sec-dis-go">Desactivar</button>');
    document.getElementById('sec-dis-go').addEventListener('click',async function(){
        var r=await W.api('/api/v1/admin/auth/2fa/disable',{method:'POST',body:{password:(document.getElementById('sec-dis-pass').value||''),code:(document.getElementById('sec-dis-code').value||'')}});
        if(r.ok){W.closeModal();W.notify('2FA desactivado','success');W.renderSecurityPanel();}
    });
};

W.secRegenRecovery=function(){
    var body='<div class="w-form-group"><label class="w-label">Código 2FA actual</label><input class="w-input" id="sec-reg-code" inputmode="numeric" maxlength="6" placeholder="000000"/></div>';
    W.modal('Regenerar códigos de recuperación',body,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancelar</button><button class="w-btn w-btn-primary" id="sec-reg-go">Generar</button>');
    document.getElementById('sec-reg-go').addEventListener('click',async function(){
        var r=await W.api('/api/v1/admin/auth/2fa/recovery/regenerate',{method:'POST',body:{code:(document.getElementById('sec-reg-code').value||'')}});
        if(!r.ok)return;
        var codes=(r.data&&r.data.recovery_codes)||[];W._secCodes=codes;
        W.closeModal();
        W.modal('Nuevos códigos', '<div style="font-size:11px;color:var(--w-muted);margin-bottom:10px">Guárdalos ahora; no se volverán a mostrar.</div><div style="font-family:\'JetBrains Mono\',monospace;font-size:13px;line-height:2">'+codes.map(function(c){return W.esc(c)}).join('<br>')+'</div>','<button class="w-btn w-btn-primary" onclick="wontia.closeModal();wontia.renderSecurityPanel()">Entendido</button>');
    });
};

W.renderCachePanel=async function(){
    var panel=document.getElementById('settings-panel'); if(!panel)return;
    panel.innerHTML='<div style="padding:20px;color:var(--w-muted)">Cargando…</div>';
    var d=await W.api('/api/v1/admin/cache'); var o=d.data||{};
    var fmt=function(b){ b=b||0; if(b<1024)return b+' B'; if(b<1048576)return (b/1024).toFixed(1)+' KB'; return (b/1048576).toFixed(2)+' MB'; };
    var dirs=o.dirs||{};
    var rows=Object.keys(dirs).map(function(k){var x=dirs[k];return '<tr><td>'+W.esc(k)+'</td><td style="font-size:11px">'+W.esc(x.path||'')+'</td><td>'+(x.exists?'sí':'no')+'</td><td>'+(x.files||0)+'</td><td>'+fmt(x.size)+'</td></tr>';}).join('');
    var html='<div class="w-card"><h3>Administración de caché</h3><div style="font-size:11px;color:var(--w-muted);margin-bottom:12px">Purga caché, elimina archivos temporales y fuerza la recarga de contenido y multimedia en el sitio.</div>';
    html+='<table class="w-table"><thead><tr><th>Caché</th><th>Ruta</th><th>Existe</th><th>Archivos</th><th>Tamaño</th></tr></thead><tbody>'+rows+'<tr><td><b>uploads</b></td><td style="font-size:11px">/assets/uploads</td><td>sí</td><td>'+(o.uploads_files||0)+'</td><td>'+fmt(o.uploads_size)+'</td></tr></tbody></table>';
    html+='<div style="font-size:12px;margin-top:10px;color:var(--w-muted)">OPcache: <b>'+(o.opcache?'activo':'no disponible')+'</b> · Última purga: <b>'+W.esc(o.last_purge||'—')+'</b> · Versión de assets: <b>'+W.esc(o.asset_version||'—')+'</b></div></div>';
    html+='<div class="w-card"><h3>Purgar</h3>';
    html+='<label style="font-size:13px;display:flex;gap:8px;align-items:center;margin:6px 0"><input type="checkbox" id="cg-app" checked style="width:auto"/> Caché de la aplicación</label>';
    html+='<label style="font-size:13px;display:flex;gap:8px;align-items:center;margin:6px 0"><input type="checkbox" id="cg-tmp" checked style="width:auto"/> Archivos temporales</label>';
    html+='<label style="font-size:13px;display:flex;gap:8px;align-items:center;margin:6px 0"><input type="checkbox" id="cg-opcache" style="width:auto"/> OPcache (recarga el código PHP)</label>';
    html+='<label style="font-size:13px;display:flex;gap:8px;align-items:center;margin:6px 0"><input type="checkbox" id="cg-assets" checked style="width:auto"/> Forzar recarga de assets/multimedia (sube la versión)</label>';
    html+='<div class="w-flex w-gap-sm" style="margin-top:12px"><button class="w-btn w-btn-primary" onclick="wontia.doPurge()">Purgar ahora</button><button class="w-btn w-btn-secondary" onclick="wontia.renderCachePanel()">Recargar</button></div></div>';
    panel.innerHTML=html;
};

W.doPurge=async function(){
    var chk=function(id){var e=document.getElementById(id);return e&&e.checked?1:0;};
    var opts={ app_cache:chk('cg-app'), tmp:chk('cg-tmp'), opcache:chk('cg-opcache'), assets:chk('cg-assets') };
    if(!opts.app_cache&&!opts.tmp&&!opts.opcache&&!opts.assets){ W.notify('Selecciona al menos una acción','error'); return; }
    if(!window.confirm('¿Purgar la caché seleccionada?'))return;
    var r=await W.api('/api/v1/admin/cache/purge',{method:'POST',body:{options:opts}});
    if(r.ok){ W.notify('Caché purgada: '+((r.done||[]).join(', ')||'ok'),'success'); W.renderCachePanel(); }
    else W.notify((r&&r.message)||'Error','error');
};

W.renderUpdatePanel=async function(){
    var panel=document.getElementById('settings-panel'); if(!panel)return;
    panel.innerHTML='<div style="padding:20px;color:var(--w-muted)">Cargando actualizaciones…</div>';
    var ov={};try{ov=(await W.api('/api/v1/admin/system/update/overview')).data||{}}catch(e){}
    var loc=ov.local||{},st=ov.settings||{},chans=ov.channels||['stable','beta'],can=!!ov.can_apply;
    var card=function(v,l,sub){return '<div class="w-stat-card"><div class="w-stat-value">'+v+'</div><div class="w-stat-label">'+W.esc(l)+'</div>'+(sub?'<div style="font-size:10px;color:var(--w-muted);margin-top:2px">'+W.esc(sub)+'</div>':'')+'</div>'};
    var html='<div class="w-card"><h3 style="margin:0 0 4px">Actualizaciones del sistema</h3><div style="font-size:11px;color:var(--w-muted);line-height:1.7">Consulta al <strong>servidor central WWI</strong> y actualiza el sistema. '+(loc.is_central?'Esta instancia es el <strong style="color:var(--w-primary)">servidor central</strong> y aplica la actualización a todos los sitios WWI.':'Esta instancia se actualiza desde el servidor central: <b>'+W.esc(loc.central_url||'')+'</b>')+'</div>'
      +'<div class="w-stats" style="margin-top:12px">'
      +card(W.esc(loc.version||'—'),'Versión local',(loc.build_at||'').slice(0,16))
      +card(W.esc(String(loc.site_id!=null?loc.site_id:'—')),'SITE_ID',loc.is_central?'servidor central':'cliente')
      +card(W.esc(loc.php||'—'),'PHP','')
      +card(loc.has_secret?'✔':'—','Secreto update',loc.has_secret?'configurado':'no configurado')
      +'</div>'
      +'<div class="w-flex w-gap-sm" style="margin-top:12px;flex-wrap:wrap">'
      +'<button class="w-btn w-btn-primary" onclick="wontia.updCheck()">🔍 Buscar actualizaciones</button>'
      +(can?'<button class="w-btn w-btn-secondary" onclick="wontia.updApply(1)">⬆️ Aplicar actualización</button>':'')
      +'<button class="w-btn w-btn-secondary" onclick="wontia.renderUpdatePanel()">↻ Recargar</button>'
      +'</div></div>';
    html+='<div id="upd-result"></div><div id="upd-progress"></div>';
    html+='<div class="w-card"><h3>Configuración del actualizador</h3>'
      +'<div class="w-form-group"><label class="w-label">Canal</label><select class="w-select" id="upd-channel">'+chans.map(function(c){return '<option value="'+W.esc(c)+'"'+(st.channel===c?' selected':'')+'>'+W.esc(c)+'</option>'}).join('')+'</select></div>'
      +'<div class="w-form-group"><label class="w-label">Servidor central (URL)</label><input class="w-input" id="upd-central" value="'+W.esc(st.central_url||'')+'"/></div>'
      +'<div class="w-form-group"><label class="w-label">Repositorio (GitHub)</label><input class="w-input" id="upd-repo" value="'+W.esc(st.repo||'')+'"/></div>'
      +'<div class="w-form-group"><label class="w-label">Ventana de mantenimiento (HH:MM-HH:MM)</label><input class="w-input" id="upd-window" placeholder="02:00-05:00" value="'+W.esc(st.window||'')+'"/></div>'
      +'<label style="font-size:13px;display:flex;gap:8px;align-items:center;margin:6px 0"><input type="checkbox" id="upd-auto" style="width:auto"'+(st.auto_update?' checked':'')+'/> Auto-actualizar desde el servidor central (según ventana)</label>'
      +'<div class="w-flex w-gap-sm" style="margin-top:10px;flex-wrap:wrap"><button class="w-btn w-btn-primary" onclick="wontia.updSaveSettings()">Guardar configuración</button>'
      +(can?'<button class="w-btn w-btn-secondary" onclick="wontia.updRegenSecret()">🔑 Generar secreto de update</button>':'')+'</div>'
      +'<div id="upd-secret" style="font-size:11px;color:var(--w-muted);margin-top:8px"></div></div>';
    html+='<div id="upd-history"></div>';
    panel.innerHTML=html;
    W.updAutoPoll();
    W.updHistory();
};

W.updCheck=async function(){
    var el=document.getElementById('upd-result');
    if(el)el.innerHTML='<div class="w-card" style="color:var(--w-muted);font-size:12px">Consultando servidor central y repositorio…</div>';
    var r={};try{r=await W.api('/api/v1/admin/system/update/check',{method:'POST'})}catch(e){}
    if(!r.ok){if(el)el.innerHTML='<div class="w-card" style="color:#f87171">No se pudo verificar: '+W.esc((r&&r.message)||'error')+'</div>';return}
    var d=r.data||{},loc=d.local||{},lat=d.latest||{};
    var badge=function(t,col){return '<span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;background:'+col[0]+';color:'+col[1]+';border:1px solid '+col[2]+'">'+W.esc(t)+'</span>'};
    var h='<div class="w-card"><div class="w-flex-between"><h3 style="margin:0">Resultado</h3>'+(d.update_available?badge('ACTUALIZACIÓN DISPONIBLE',['rgba(34,211,238,.14)','#22d3ee','rgba(34,211,238,.4)']):badge('AL DÍA',['rgba(52,211,153,.14)','#34d399','rgba(52,211,153,.4)']))+'</div>';
    h+='<div style="font-size:12px;margin-top:8px;line-height:1.9">Local: <b>'+W.esc(loc.version||'—')+'</b> · Última: <b>'+W.esc(lat.v||'—')+'</b> ('+W.esc(lat.src||'')+')<br>Servidor central: '+(d.central?W.esc(d.central.version||'')+' <span style="color:var(--w-muted)">'+W.esc((d.central.build_at||'').slice(0,16))+'</span>':'<span style="color:#f87171">'+W.esc(d.central_error||'no disponible')+'</span>')+'<br>GitHub: '+(d.github?W.esc(d.github.version||'')+(d.github.url?' <a href="'+W.esc(d.github.url)+'" target="_blank" rel="noopener">ver release</a>':''):'<span style="color:var(--w-muted)">sin releases</span>')+'</div>';
    var plan=d.plan||{};
    if(plan.steps)h+='<div style="margin-top:10px;font-size:11px;color:var(--w-muted);border-top:1px dashed var(--w-border);padding-top:8px"><b style="color:var(--w-text)">Plan (dry-run)</b><br>'+plan.steps.map(W.esc).join('<br>')+'<br>Aplica a: <b>'+W.esc(plan.applies_to||'')+'</b> · Downtime: '+W.esc(plan.downtime||'')+'</div>';
    if(lat.notes)h+='<div style="margin-top:10px"><div style="font-size:11px;color:var(--w-muted);margin-bottom:4px">Cambios (release notes)</div><pre style="white-space:pre-wrap;font-size:11px;background:var(--w-bg2);border:1px solid var(--w-border);border-radius:8px;padding:10px;max-height:200px;overflow:auto">'+W.esc(lat.notes)+'</pre></div>';
    if(d.update_available)h+='<div style="margin-top:10px"><label style="font-size:12px;display:flex;gap:8px;align-items:center"><input type="checkbox" id="upd-backup" checked style="width:auto"/> Hacer backup antes de actualizar</label><div class="w-flex w-gap-sm" style="margin-top:8px"><button class="w-btn w-btn-primary" onclick="wontia.updApply(1)">⬆️ Actualizar ahora</button></div></div>';
    h+='<div style="font-size:10px;color:var(--w-muted);margin-top:8px">Verificado: '+W.esc((d.checked_at||'').slice(0,19))+'</div></div>';
    el.innerHTML=h;
};

W.updApply=async function(confirmar){
    var ch=document.getElementById('upd-channel'),channel=ch?ch.value:'';
    var bk=document.getElementById('upd-backup'),backup=bk?!!bk.checked:true;
    if(confirmar&&!window.confirm('¿Aplicar la actualización ahora? El sistema se pondrá al día en ~1 minuto.'))return;
    var r={};try{r=await W.api('/api/v1/admin/system/update/apply',{method:'POST',body:{channel:channel,backup:backup}})}catch(e){}
    if(r.ok){W.notify(r.message||'Actualización iniciada','success');W.updAutoPoll()}else W.notify((r&&r.message)||'No se pudo iniciar','error');
};

W.updAutoPoll=function(){
    if(W._updTimer){clearInterval(W._updTimer);W._updTimer=null;}
    var step=async function(){
        var el=document.getElementById('upd-progress');
        if(!el){if(W._updTimer){clearInterval(W._updTimer);W._updTimer=null;}return}
        var st={};try{st=(await W.api('/api/v1/admin/system/update/status')).data||{}}catch(e){}
        var stat=st.status||'idle';
        if(stat==='idle'){el.innerHTML='';return}
        var pct=st.pct||0;
        var label=({running:'En curso',build:'Construyendo imagen',recreate:'Recreando contenedores',health:'Health-check',done:'Completado',failed:'Falló',rolled_back:'Rollback aplicado',stale:'Sin actividad'})[stat]||stat;
        var col=stat==='failed'?'#f87171':((stat==='done')?'#34d399':'#22d3ee');
        el.innerHTML='<div class="w-card"><div class="w-flex-between"><h3 style="margin:0">Actualizando — '+W.esc(label)+'</h3><span style="font-size:11px;color:var(--w-muted)">'+(st.containers_done!=null?st.containers_done+'/'+st.containers_total:'')+' &#183; ETA '+(st.eta_s!=null?st.eta_s+'s':'—')+'</span></div><div class="w-brick-bar" style="height:10px;margin-top:10px"><i style="width:'+pct+'%;background:'+col+'"></i></div><div style="font-size:11px;color:var(--w-muted);margin-top:6px">'+W.esc(st.message||'')+(st.commit?' &#183; commit '+W.esc(String(st.commit).slice(0,8)):'')+'</div></div>';
        if(stat==='done'||stat==='rolled_back'||stat==='failed'){clearInterval(W._updTimer);W._updTimer=null;W.updHistory();setTimeout(function(){W.renderUpdatePanel()},2500)}
    };
    step();
    W._updTimer=setInterval(step,4000);
};

W.updSaveSettings=async function(){
    var g=function(id){var e=document.getElementById(id);return e?e.value:''};
    var auto=document.getElementById('upd-auto');
    var settings={channel:g('upd-channel'),central_url:g('upd-central'),repo:g('upd-repo'),window:g('upd-window'),auto_update:auto?!!auto.checked:false};
    var r=await W.api('/api/v1/admin/system/update/settings',{method:'POST',body:{settings:settings}});
    if(r.ok)W.notify('Configuración guardada','success');else W.notify((r&&r.message)||'Error','error');
};

W.updRegenSecret=async function(){
    var r=await W.api('/api/v1/admin/system/update/secret',{method:'POST'});
    if(r.ok){var el=document.getElementById('upd-secret');if(el)el.innerHTML='Secreto: <code style="user-select:all">'+W.esc(r.secret)+'</code> — cópialo en los demás sitios WWI.';W.notify('Secreto regenerado','success')}
    else W.notify((r&&r.message)||'Error','error');
};

W.updHistory=async function(){
    var el=document.getElementById('upd-history');if(!el)return;
    var h=[];try{h=(await W.api('/api/v1/admin/system/update/history')).data||[]}catch(e){}
    var rows=h.map(function(x){var d=x.data||{};var s=x.status==='done'?(d.status||'ok'):'encolada';var col=(s==='success'||s==='done')?'#34d399':((s==='failed'||s==='rolled_back')?'#f87171':'#fbbf24');var when=d.ts?new Date(d.ts*1000).toLocaleString():(d.started_at||'');return '<tr><td style="font-family:var(--w-font-mono);font-size:10px">'+W.esc(when)+'</td><td>'+W.esc(d.requested_by||'—')+'</td><td>'+W.esc(d.channel||'—')+'</td><td><b style="color:'+col+'">'+W.esc(s)+'</b></td><td style="font-size:10px">'+W.esc(String(d.commit||'').slice(0,8))+'</td></tr>'}).join('');
    el.innerHTML='<div class="w-card"><h3>Historial de actualizaciones</h3><table class="w-table"><thead><tr><th>Cuándo</th><th>Solicitó</th><th>Canal</th><th>Estado</th><th>Commit</th></tr></thead><tbody>'+(rows||'<tr><td colspan="5" style="color:var(--w-muted)">Sin actualizaciones registradas</td></tr>')+'</tbody></table></div>';
};

W.renderSettingsGeneral=async function(){
    var panel=document.getElementById('settings-panel'); if(!panel)return;
    var d=await W.api('/api/v1/admin/settings'); var s=d.data||{};
    var th=await W.api('/api/v1/admin/themes'); var td=th.data||{themes:[],active:''};
    var keys=['site_name','site_description','ga_measurement_id','cookie_consent_enabled','primary_color','logo_text'];
    var html='<div class="w-card"><h3>Site Settings</h3>';
    keys.forEach(function(k){
        html+='<div class="w-form-group"><label class="w-label">'+W.esc(k)+'</label><input class="w-input" id="set-'+k+'" value="'+W.esc(s[k]||'')+'"/></div>';
    });
    html+='<button class="w-btn w-btn-primary" onclick="wontia.saveSettings()">Guardar ajustes</button></div>';
    html+='<div class="w-card"><h3>Tema del sitio</h3><div style="font-size:11px;color:var(--w-muted);margin-bottom:10px">Activa o desactiva la apariencia del sitio en cualquier momento. El cambio es reversible y no afecta el contenido.</div><div class="w-flex w-gap-sm" style="flex-wrap:wrap"><select class="w-select" id="set-theme" style="max-width:300px">'+td.themes.map(function(t){return '<option value="'+W.esc(t)+'"'+(t===td.active?' selected':'')+'>'+W.esc(t)+'</option>'}).join('')+'</select><button class="w-btn w-btn-primary" onclick="wontia.saveTheme()">Activar tema</button></div><div style="font-size:11px;color:var(--w-muted);margin-top:8px">Tema activo: <strong>'+W.esc(td.active||'—')+'</strong></div></div>';
    panel.innerHTML=html;
};

W.renderLogoPanel=async function(){
    var panel=document.getElementById('settings-panel'); if(!panel)return;
    panel.innerHTML='<div style="padding:20px;color:var(--w-muted)">Cargando…</div>';
    var d=await W.api('/api/v1/admin/settings'); var s=d.data||{};
    var logo=s.logo_image||'', fav=s.favicon||'', touch=s.favicon_touch||'', tc=s.theme_color||'';
    var h=parseInt(s.logo_height||'40',10); if(!h||h<16)h=40;
    var show=(s.logo_show_text!=='0'), alt=s.logo_alt||'', txt=s.logo_text||'WONTIA';
    var html='<div class="w-card"><h3>Logo</h3><div style="font-size:11px;color:var(--w-muted);margin-bottom:12px">Logo del sitio actual. Formatos: PNG, JPG, WebP, GIF, ICO (SVG no permitido).</div>';
    html+='<div style="display:flex;gap:16px;flex-wrap:wrap"><div style="flex:1;min-width:260px">';
    html+='<div id="logo-preview" style="border:1px dashed var(--w-border);border-radius:10px;min-height:96px;display:flex;align-items:center;justify-content:center;background:var(--w-bg);padding:10px">'+(logo?'<img src="'+W.esc(logo)+'" style="height:'+h+'px;width:auto;max-width:100%"/>':'<span style="font-size:12px;color:var(--w-muted)">Sin logo (se usa el texto/letra del tema)</span>')+'</div>';
    html+='<div class="w-flex w-gap-sm" style="margin-top:10px;flex-wrap:wrap"><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.brandPick(\'logo\')">Elegir del Media</button><label class="w-btn w-btn-secondary w-btn-sm" style="cursor:pointer">Subir<input type="file" accept="image/png,image/jpeg,image/webp,image/gif" style="display:none" onchange="wontia.brandUpload(this,\'logo\')"/></label><button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.brandClear(\'logo\')">Quitar</button></div>';
    html+='<input type="hidden" id="logo-image" value="'+W.esc(logo)+'"/>';
    html+='<div style="margin-top:14px"><label class="w-label">Tamaño — alto del logo: <span id="logo-h-lbl">'+h+'</span>px</label><input type="range" id="logo-height" min="16" max="160" step="1" value="'+h+'" style="width:100%" oninput="wontia.brandSlider(this.value)"/></div>';
    html+='<div class="w-form-group" style="margin-top:10px"><label class="w-label">Texto junto al logo</label><input class="w-input" id="logo-text" value="'+W.esc(txt)+'"/></div>';
    html+='<label style="font-size:12px;display:flex;align-items:center;gap:8px"><input type="checkbox" id="logo-show-text" '+(show?'checked':'')+' style="width:auto"/> Mostrar el texto junto al logo</label>';
    html+='<div class="w-form-group" style="margin-top:10px"><label class="w-label">Texto alternativo (alt) — obligatorio si hay logo</label><input class="w-input" id="logo-alt" value="'+W.esc(alt)+'" placeholder="Ej: Logo de INTSOLCOM"/></div>';
    html+='</div></div><div id="logo-lib" style="display:none;margin-top:12px"></div></div>';
    html+='<div class="w-card"><h3>Favicon</h3><div style="font-size:11px;color:var(--w-muted);margin-bottom:12px">.ico o PNG (32×32; 180×180 para apple-touch).</div>';
    html+='<div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap"><div id="fav-preview" style="width:48px;height:48px;border:1px solid var(--w-border);border-radius:10px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:var(--w-bg)">'+(fav?'<img src="'+W.esc(fav)+'" style="width:32px;height:32px;object-fit:contain"/>':'<span style="font-size:10px;color:var(--w-muted)">—</span>')+'</div>';
    html+='<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.brandPick(\'favicon\')">Elegir del Media</button><label class="w-btn w-btn-secondary w-btn-sm" style="cursor:pointer">Subir<input type="file" accept=".ico,image/x-icon,image/png,image/vnd.microsoft.icon" style="display:none" onchange="wontia.brandUpload(this,\'favicon\')"/></label><button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.brandClear(\'favicon\')">Quitar</button></div>';
    html+='<input type="hidden" id="logo-favicon" value="'+W.esc(fav)+'"/>';
    html+='<div class="w-form-group" style="margin-top:10px"><label class="w-label">Apple touch icon (opcional)</label><input class="w-input" id="logo-favicon-touch" value="'+W.esc(touch)+'" placeholder="/assets/uploads/..."/></div>';
    html+='<div class="w-form-group"><label class="w-label">Color de tema (opcional)</label><input class="w-input" id="logo-theme-color" value="'+W.esc(tc)+'" placeholder="#7c3cff"/></div>';
    html+='</div>';
    html+='<div class="w-flex w-gap-sm"><button class="w-btn w-btn-primary" onclick="wontia.saveBrand()">Guardar logo y favicon</button><button class="w-btn w-btn-secondary" onclick="wontia.renderSettings(\'logo\')">Recargar</button></div>';
    panel.innerHTML=html;
};

W.brandSlider=function(v){ var l=document.getElementById('logo-h-lbl'); if(l)l.textContent=v; var img=document.querySelector('#logo-preview img'); if(img)img.style.height=parseInt(v,10)+'px'; };
W.brandClear=function(which){
    var id=which==='logo'?'logo-image':'logo-favicon'; var el=document.getElementById(id); if(el)el.value='';
    var box=document.getElementById(which==='logo'?'logo-preview':'fav-preview');
    if(box)box.innerHTML='<span style="font-size:'+(which==='logo'?'12':'10')+'px;color:var(--w-muted)">Sin '+(which==='logo'?'logo':'favicon')+'</span>';
};
W.brandSet=function(which,url){
    var id=which==='logo'?'logo-image':'logo-favicon'; var el=document.getElementById(id); if(el)el.value=url;
    if(which==='logo'){ var h=(document.getElementById('logo-height')||{}).value||40; var p=document.getElementById('logo-preview'); if(p)p.innerHTML='<img src="'+url+'" style="height:'+parseInt(h,10)+'px;width:auto;max-width:100%"/>'; }
    else { var f=document.getElementById('fav-preview'); if(f)f.innerHTML='<img src="'+url+'" style="width:32px;height:32px;object-fit:contain"/>'; }
    var box=document.getElementById('logo-lib'); if(box)box.style.display='none';
};
W.brandPick=function(which){
    var box=document.getElementById('logo-lib');if(box)box.style.display='none';
    W.openMediaExplorer({type:'image',title:'Elegir imagen de marca',onSelect:function(m){W.brandSet(which,m.url)}});
};
W.brandUpload=async function(input,which){
    var f=input.files&&input.files[0]; if(!f)return;
    var fd=new FormData(); fd.append('file',f);
    try{
        var r=await fetch('/api/v1/admin/media/upload',{method:'POST',headers:W.token?{Authorization:'Bearer '+W.token}:{},body:fd});
        var d=await r.json();
        if(d.ok&&d.data&&d.data.url){ W.brandSet(which,d.data.url); W.notify('Imagen subida','success'); }
        else W.notify((d&&d.message)||'No se pudo subir','error');
    }catch(e){ W.notify('Error de subida','error'); }
    input.value='';
};
W.saveBrand=async function(){
    var data={ logo_image:W.val('logo-image'), logo_height:(document.getElementById('logo-height')||{}).value||'40', logo_show_text:(document.getElementById('logo-show-text')&&document.getElementById('logo-show-text').checked)?1:0, logo_text:W.val('logo-text'), logo_alt:W.val('logo-alt'), favicon:W.val('logo-favicon'), favicon_touch:W.val('logo-favicon-touch'), theme_color:W.val('logo-theme-color') };
    if(data.logo_image && !data.logo_alt){ W.notify('Añade el texto alternativo (alt) del logo','error'); return; }
    var r=await W.api('/api/v1/admin/settings',{method:'PUT',body:data});
    if(r.ok)W.notify('Logo y favicon guardados','success'); else W.notify((r&&r.message)||'Error','error');
};

W.saveTheme=async function(){
    var t=document.getElementById('set-theme')?document.getElementById('set-theme').value:'';
    if(!t)return;
    var r=await W.api('/api/v1/admin/themes/active',{method:'PUT',body:{theme:t}});
    if(r.ok){W.notify(r.message||'Tema activado','success');W.renderSettings()}
    else W.notify(r.message||'Error','error');
};

W.saveSettings=async function(){
    var data={};
    ['site_name','site_description','ga_measurement_id','cookie_consent_enabled','primary_color','logo_text'].forEach(function(k){data[k]=document.getElementById('set-'+k).value});
    var r=await W.api('/api/v1/admin/settings',{method:'PUT',body:data});
    if(r.ok)W.notify('Settings saved','success');
};

W.renderUsers=async function(){
    var app=document.getElementById('wontia-app');
    var d=await W.api('/api/v1/admin/users');
    var users=d.data||[];
    var html='<div class="w-flex-between w-mb-lg"><h3>Users</h3><button class="w-btn w-btn-primary" onclick="wontia.showUserEditor()">+ Add User</button></div>';
    html+='<table class="w-table"><thead><tr><th>Username</th><th>Email</th><th>Role</th><th>Active</th><th>Last Login</th><th></th></tr></thead><tbody>';
    users.forEach(function(u){
        html+='<tr><td>'+W.esc(u.username)+'</td><td>'+W.esc(u.email)+'</td><td>'+W.esc(u.role)+'</td><td>'+(u.is_active?'Yes':'No')+'</td><td>'+W.esc(u.last_login||'')+'</td><td><div class="w-flex w-gap-sm"><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.showUserEditor('+u.id+')">Edit</button><button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.deleteUser('+u.id+')">Del</button></div></td></tr>';
    });
    html+='</tbody></table>';
    app.innerHTML=html;
};

W.showUserEditor=function(id){
    var html='<div class="w-form-group"><label class="w-label">Username</label><input class="w-input" id="ue-user"/></div><div class="w-form-group"><label class="w-label">Email</label><input class="w-input" id="ue-email"/></div><div class="w-form-group"><label class="w-label">Password (leave blank to keep)</label><input class="w-input" type="password" id="ue-pass"/></div><div class="w-form-group"><label class="w-label">Role</label><select class="w-select" id="ue-role"><option value="admin">Admin</option><option value="editor">Editor</option><option value="superadmin">Superadmin</option></select></div>';
    W.modal(id?'Edit User':'Add User',html,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancel</button><button class="w-btn w-btn-primary" onclick="wontia.saveUser('+(id||0)+')">Save</button>');
    if(id){
        W.api('/api/v1/admin/users').then(function(d){
            var u=(d.data||[]).find(function(x){return x.id==id});
            if(u){
                document.getElementById('ue-user').value=u.username||'';
                document.getElementById('ue-email').value=u.email||'';
                document.getElementById('ue-role').value=u.role||'admin';
            }
        });
    }
};

W.saveUser=async function(id){
    var data={username:document.getElementById('ue-user').value,email:document.getElementById('ue-email').value,role:document.getElementById('ue-role').value};
    var pw=document.getElementById('ue-pass').value;
    if(pw)data.password=pw;
    if(!data.username||!data.email){W.notify('Fill required fields','error');return}
    var method=id?'PUT':'POST';
    var url=id?'/api/v1/admin/users/'+id:'/api/v1/admin/users';
    var r=await W.api(url,{method:method,body:data});
    if(r.ok){W.closeModal();W.renderUsers();W.notify('Saved','success')}
};

W.deleteUser=function(id){
    W.confirm('Delete this user?',async function(){await W.api('/api/v1/admin/users/'+id,{method:'DELETE'});W.renderUsers();W.notify('Deleted','success')});
};

W.renderDashboard=async function(){
    var app=document.getElementById('wontia-app');
    var d=await W.api('/api/v1/admin/dashboard');
    var s=d.data?.['stats']||{};
    app.innerHTML='<div class="w-stats">'+
        '<div class="w-stat-card"><div class="w-stat-value">'+W.num(s.total_pages||0)+'</div><div class="w-stat-label">Pages</div></div>'+
        '<div class="w-stat-card"><div class="w-stat-value">'+W.num(s.total_posts||0)+'</div><div class="w-stat-label">Blog Posts</div></div>'+
        '<div class="w-stat-card"><div class="w-stat-value">'+W.num(s.published_posts||0)+'</div><div class="w-stat-label">Published</div></div>'+
        '<div class="w-stat-card"><div class="w-stat-value">'+W.num(s.draft_posts||0)+'</div><div class="w-stat-label">Drafts</div></div>'+
        '<div class="w-stat-card"><div class="w-stat-value">'+W.num(s.total_media||0)+'</div><div class="w-stat-label">Media</div></div>'+
        '<div class="w-stat-card"><div class="w-stat-value">'+W.num(s.total_views||0)+'</div><div class="w-stat-label">Views</div></div>'+
        '</div>'+
        '<div class="w-card"><h3>Recent Pages</h3>'+(d.data?.['recent_pages']||[]).map(function(p){return '<div style="font-size:12px;padding:6px 0;border-bottom:1px solid var(--w-border)">'+W.esc(p.title)+' <span class="w-badge w-badge-'+(p.status==='published'?'published':'draft')+'">'+p.status+'</span></div>'}).join('')+'</div>';
    W.autoCheckBrickHub();
};

W.renderBricks=async function(tab,action){
    if(tab==='biblioteca')tab='marketplace';
    if(tab==='extensiones')tab='instalados';
    tab=tab||'marketplace';
    W.state.bricksTab=tab;
    var app=document.getElementById('wontia-app');
    var tabs=[
        {id:'marketplace',label:'Brick Marketplace'},
        {id:'instalados',label:'Bricks Acoplados'},
        {id:'ia',label:'IA (BRICK)'},
        {id:'repos',label:'Repos & Sync'},
        {id:'incubadora',label:'Incubadora'}
    ];
    var bar='<div class="w-card" style="padding:14px 18px;margin-bottom:14px"><div style="font-size:13px;font-weight:700">Bricks — centro de bloques</div><div style="font-size:11px;color:var(--w-muted);margin-top:4px;line-height:1.7"><strong>Brick Marketplace</strong> muestra solo los bricks <strong style="color:var(--w-primary)">lanzados</strong> (terminados) con su etiqueta <span class="w-bm-new">NUEVO</span>. La <strong>Incubadora</strong> reúne lo que está en desarrollo con su <strong>cuenta regresiva</strong> hasta el lanzamiento. <strong>Bricks Acoplados</strong> gestiona lo que ya está en el sitio, <strong>IA (BRICK)</strong> la capa de inteligencia y <strong>Repos & Sync</strong> las fuentes GitHub.</div></div>';
    bar+='<div class="w-toolbar w-mb-lg" style="border-bottom:1px solid var(--w-border);padding-bottom:12px">';
    tabs.forEach(function(t){
        bar+='<button class="w-btn '+(tab===t.id?'w-btn-primary':'w-btn-secondary')+'" onclick="wontia.bricksGo(\''+t.id+'\')">'+t.label+'</button>';
    });
    bar+='</div><div id="bs-content"></div>';
    app.innerHTML=bar;
    if(tab==='ia'){W.state.brickHost=document.getElementById('bs-content');W.renderBrickInto(W.state.brickHost,true,action||W.state.brick.tab||'overview');return}
    if(tab==='repos'){W.state.bhHost=document.getElementById('bs-content');W.renderBrickHubInto(W.state.bhHost,true);return}
    if(tab==='incubadora'){W.bsIncubator();return}
    if(tab==='instalados'){W.bsInstalled();return}
    W.bsMarketplace();
};

W.bricksGo=function(tab){
    window.location.hash=tab==='marketplace'?'#bricks':'#bricks/'+tab;
};

W.bhRefresh=function(){
    W._bm.loaded=false;
    if(W.state.bricksTab==='instalados'){W.bsInstalled();return}
    if(W.state.bricksTab==='marketplace'){W.bsMarketplace();return}
    if(W.state.bhHost)W.renderBrickHubInto(W.state.bhHost,true);
    else W.renderBrickHub();
};

W._bm={items:null,metrics:null,loaded:false,lifecycle:{},lcRecent:[],isAdmin:false,view:'marketplace',cat:'',sort:'recientes',q:''};

W.bmLevels=[
    {n:1,emoji:'🤔',label:'Nada popular'},
    {n:2,emoji:'🙈',label:'Empieza su aventura'},
    {n:3,emoji:'🏍️💨',label:'Tomando velocidad'},
    {n:4,emoji:'🏎️🔥😄',label:'¡Vamos funcional! A los usuarios les gusta'},
    {n:5,emoji:'🚀😄👏🎉',label:'¡Fuera de órbita! Muy popular'}
];

W.bmLevel=function(n){return W.bmLevels[Math.max(1,Math.min(5,Math.round(n||1)))-1]};

W.bmMetrics=function(slug){
    return (W._bm.metrics&&W._bm.metrics.summary&&W._bm.metrics.summary[slug])||{rating_avg:0,rating_count:0,dist:[0,0,0,0,0],installs:0,views:0,previews:0,uninstalls:0,add_to_page:0,updates:0};
};

W.bmLoad=async function(force){
    if(W._bm.loaded&&!force)return;
    var res=await Promise.all([
        W.api('/api/v1/admin/bricks').catch(function(){return{}}),
        W.api('/api/v1/admin/brickhub').catch(function(){return{}}),
        W.api('/api/v1/admin/seo-global-launch').catch(function(){return{}}),
        W.api('/api/v1/admin/bricks/metrics').catch(function(){return{}}),
        W.api('/api/v1/admin/bricklifecycle').catch(function(){return{}})
    ]);
    var core=res[0].data||{};
    var bhList=res[1].data||[];
    var sgl=(res[2].data&&res[2].data.brick)||null;
    W._bm.metrics=res[3].data||{summary:{},my_ratings:{},insights:{}};
    var lcd=(res[4].data||{});
    W._bm.lifecycle=lcd.map||{};
    W._bm.lcRecent=lcd.recent||[];
    W._bm.isAdmin=!!lcd.is_admin;W._bm.isCentral=!!lcd.is_central;
    W._bm.lcConfig=lcd.config||{new_days:30,maturities:['alpha','beta','rc','stable']};
    var lcFor=function(slug){return W._bm.lifecycle[slug]||null};
    var items=[];
    for(var id in core){
        var b=core[id];
        items.push({slug:id,name:b.name,category:b.category||'general',version:b.version||'1.0.0',launched_at:b.launched_at||'',origin:'core',core:true,installed:(b.usage_count||0)>0,functional:b.functional!==false,installed_version:'',installed_id:null,source_id:0,source_name:'Core',desc:(b.configSchema&&b.configSchema.length?b.configSchema.length+' campos configurables':'Bloque del sistema'),uses_ai:!!b.uses_ai,usage_count:b.usage_count||0,update_available:false,lifecycle:b.lifecycle||lcFor(id),readiness:b.readiness||null});
    }
    (bhList||[]).forEach(function(b){
        if(sgl&&b.slug===sgl.slug)return;
        items.push({slug:b.slug,name:b.name,category:b.category||'general',version:b.version||'1.0.0',launched_at:b.created_at||b.installed_at||'',origin:'brickhub',installed:!!b.installed,installed_version:b.installed_version||'',installed_id:b.installed_id||null,source_id:b.source_id||0,source_name:b.source_name||'Repo',desc:b.description||'Brick instalable desde repositorio',uses_ai:false,usage_count:0,update_available:false,lifecycle:b.lifecycle||lcFor(b.slug),readiness:null});
    });
    if(sgl)items.push({slug:'seo-global-launch',name:'SEO Global Launch',category:'system',version:sgl.version||'1.0.0',launched_at:'',origin:'incubator',installed:!!sgl.installed,installed_version:sgl.version||'1.0.0',installed_id:null,source_id:0,source_name:'Incubadora',desc:'Motor SEO full-site: scan, IA de metadatos, autofix, JSON-LD, auditoría y tracker de bots.',uses_ai:true,usage_count:0,update_available:false,lifecycle:lcFor('seo-global-launch')||{state:'incubator',brick_type:'incubator',maturity:'beta',countdown:null},readiness:null});
    var seen={},uniq=[];
    items.forEach(function(it){if(seen[it.slug])return;seen[it.slug]=1;uniq.push(it)});
    W._bm.items=uniq;
    W._bm.loaded=true;
};

W.bmSortItems=function(items){
    var s=W._bm.sort,arr=items.slice();
    if(s==='instalados')arr.sort(function(a,b){return W.bmMetrics(b.slug).installs-W.bmMetrics(a.slug).installs});
    else if(s==='rating')arr.sort(function(a,b){return W.bmMetrics(b.slug).rating_avg-W.bmMetrics(a.slug).rating_avg});
    else if(s==='usados')arr.sort(function(a,b){return (b.usage_count||0)-(a.usage_count||0)});
    else if(s==='version')arr.sort(function(a,b){return String(b.version).localeCompare(String(a.version),undefined,{numeric:true})});
    else if(s==='categoria')arr.sort(function(a,b){return (a.category||'').localeCompare(b.category||'')||a.name.localeCompare(b.name)});
    else arr.sort(function(a,b){return String(b.launched_at||'').localeCompare(String(a.launched_at||''))||a.name.localeCompare(b.name)});
    return arr;
};

W.bmCard=function(it){
    var mm=W.bmMetrics(it.slug);
    var lc=it.lifecycle||{};
    var my=(W._bm.metrics&&W._bm.metrics.my_ratings&&W._bm.metrics.my_ratings[it.slug])||0;
    var avg=mm.rating_avg||0;
    var shown=my||Math.round(avg);
    var stars='';
    for(var i=1;i<=5;i++){
        var lv=W.bmLevels[i-1];
        stars+='<button class="w-bm-star'+(i<=shown?' on':'')+'" title="'+lv.emoji+' '+W.esc(lv.label)+'" onclick="event.stopPropagation();wontia.bmRate(\''+W.esc(it.slug)+'\','+i+')">'+(i<=shown?'★':'☆')+'</button>';
    }
    var lvl=avg>0?W.bmLevel(avg):null;
    var origin=it.origin==='core'?'Core':(it.origin==='brickhub'?'Repo':(it.origin==='incubator'?'Incubadora':''));
    var metrics='<span title="Instalaciones">⬇ '+W.num(mm.installs)+'</span>';
    if(it.origin==='core')metrics+='<span title="Usos en páginas">📄 '+W.num(it.usage_count||0)+'</span>';
    metrics+='<span title="Previews">👁 '+W.num(mm.previews)+'</span>';
    var actions='<button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.bmPreview(\''+W.esc(it.slug)+'\')">Preview</button>';
    if(window.__WWI_IS_CENTRAL)actions+='<button class="w-btn w-btn-secondary w-btn-sm" title="Showcase (capturas del brick) — solo WWI central" onclick="wontia.showBrickShowcase(\''+W.esc(it.slug)+'\')">⚙</button>';
    if(it.origin==='core'){
        if(it.functional!==false&&lc.state!=='incubator')actions+='<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.brickAddToPage(\''+W.esc(it.slug)+'\',\''+W.esc(it.name)+'\')">Añadir a página</button>';
        actions+='<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.showBrickConfig(\''+W.esc(it.slug)+'\')">Configurar</button>';
    }else if(it.origin==='brickhub'){
        if(it.installed){
            if(it.update_available)actions+='<button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.bmUpdate('+it.installed_id+',\''+W.esc(it.slug)+'\')">🔄 Actualizar</button>';
            else actions+='<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.bhCheckUpdate('+it.installed_id+')">Check Update</button>';
            actions+='<button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.bmUninstall(this,'+it.installed_id+',\''+W.esc(it.name)+'\',\''+W.esc(it.slug)+'\')">Desacoplar</button>';
        }else if(lc.state!=='incubator'){
            actions+='<button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.bmInstall(this,'+it.source_id+',\''+W.esc(it.slug)+'\',\''+W.esc(it.name)+'\')">Acoplar</button>';
        }
    }else if(it.origin==='incubator'){
        if(it.installed){
            actions+='<button class="w-btn w-btn-secondary w-btn-sm" onclick="location.href=\'/admin.php?panel=seo-global-launch#seo-global-launch/resumen\'">Abrir panel</button>';
            actions+='<button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.sglDeactivate(this,\''+W.esc(it.name)+'\')">Desacoplar</button>';
        }else actions+='<button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.bmActivate(this)">Acoplar</button>';
    }
    var updateBadge=it.update_available?'<span class="w-bm-update" title="Actualización disponible">🔄</span>':'';
    var active=it.installed||it.core;
    var stateLabel=it.core?'<span class="w-bm-installed-label" title="Este es un brick de sistema, no necesita acople.">◆ Core · siempre disponible</span>':(it.installed?'<span class="w-bm-installed-label">✓ Acoplado</span>':'<span class="w-bm-decoupled-label">○ Disponible</span>');
    var themechip=it.theme_used?'<span class="w-bm-installed-label" title="Renderizado directamente por el tema">Usado por el tema</span>':'';
    var devChip=(lc.state==='incubator')?'<span class="w-bm-dev" title="En desarrollo — aún no se lanza al Marketplace">🔥 En el horno</span>':'';
    var newBadge=lc.is_new?'<span class="w-bm-new" title="Lanzado recientemente al Marketplace">NUEVO</span>':'';
    var maturityChip=(lc.maturity&&lc.maturity!=='stable')?'<span class="w-bm-mat w-bm-mat-'+W.esc(lc.maturity)+'">'+W.esc(String(lc.maturity).toUpperCase())+'</span>':'';
    var interestChip=(lc.state==='incubator'&&lc.interest)?'<span class="w-bm-interest" title="Usuarios interesados">🔔 '+W.num(lc.interest)+'</span>':'';
    var hiddenChip=lc.hidden?'<span class="w-bm-hidden" title="Oculto del catálogo">🙈 Oculto</span>':'';
    if(W._bm.isCentral){
        if(lc.state==='incubator'){
            actions+='<button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.blLaunch(\''+W.esc(it.slug)+'\',\''+W.esc(it.name)+'\')" title="Lanzar al Marketplace">🚀 Lanzar</button>';
            actions+='<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.blSchedule(\''+W.esc(it.slug)+'\',\''+W.esc(it.name)+'\')" title="Programar fecha">🗓</button>';
        }else{
            actions+='<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.blIncubate(\''+W.esc(it.slug)+'\',\''+W.esc(it.name)+'\')" title="Devolver a la incubadora">↩ Reinculbar</button>';
        }
        actions+='<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.blHide(\''+W.esc(it.slug)+'\','+(lc.hidden?0:1)+')" title="'+(lc.hidden?'Mostrar en el catálogo':'Ocultar del catálogo')+'">'+(lc.hidden?'👁 Mostrar':'🙈 Ocultar')+'</button>';
    }else if(lc.state==='incubator'){
        actions+='<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.blInterest(\''+W.esc(it.slug)+'\')">🔔 Avísame</button>';
    }
    var cdHtml=(lc.state==='incubator')?W.cdHtml(it.slug,lc.target_launch_at,lc.target_estimated):'';
    var check='<label class="w-bm-check" title="Seleccionar"><input type="checkbox" class="wb-sel" data-slug="'+W.esc(it.slug)+'" onchange="wontia.blSel()"/></label>';
    return '<div class="w-bm-card'+(active?' w-bm-installed':'')+(lc.state==='incubator'?' w-bm-incubating':'')+(lc.is_new?' w-bm-isnew':'')+'" data-slug="'+W.esc(it.slug)+'">'
        +'<span class="w-bm-blocks'+(active?' on':' off')+'" aria-hidden="true"></span>'
        +(active?'<span class="w-bm-live" role="img" aria-label="'+(it.core?'Brick de sistema activo':'Acoplado')+'" title="'+(it.core?'Brick de sistema activo':'Funcionando')+'"></span>':'')
        +check
        +'<div class="w-bm-top"><span class="w-bm-chip">'+W.esc(origin)+'</span><span class="w-bm-chip">'+W.esc(it.category)+'</span>'+newBadge+maturityChip+stateLabel+themechip+devChip+interestChip+hiddenChip+updateBadge+'</div>'
        +'<div class="w-bm-name">'+W.esc(it.name)+'</div>'
        +'<div class="w-bm-desc">'+W.esc(it.desc)+'</div>'
        +'<div class="w-bm-meta"><span>v'+W.esc(it.version)+'</span>'+(it.launched_at?'<span>'+W.esc(String(it.launched_at).slice(0,10))+'</span>':'')+(it.uses_ai?'<span class="w-bm-ia">✦ IA</span>':'')+'</div>'
        +cdHtml
        +'<div class="w-bm-metrics">'+metrics+'</div>'
        +'<div class="w-bm-rating"><div class="w-bm-stars">'+stars+'</div><div class="w-bm-emoji">'+(lvl?lvl.emoji+' '+W.esc(lvl.label)+' · '+avg.toFixed(1)+' ('+mm.rating_count+')':'Sin valoraciones — sé el primero')+'</div></div>'
        +'<div class="w-bm-actions">'+actions+'</div></div>';
};

W.bmSetState=function(slug,installed){
    (W._bm.items||[]).forEach(function(it){if(it.slug===slug)it.installed=installed});
};

W.bmRender=function(){
    var wrap=document.getElementById('bs-grid');
    if(!wrap||!W._bm.items)return;
    var q=W._bm.q||'',cat=W._bm.cat||'';
    var items=W._bm.items.filter(function(it){
        var lc=it.lifecycle||{};
        if(W._bm.view==='marketplace'&&lc.state!=='launched')return false;
        if(lc.hidden&&!W._bm.showHidden)return false;
        if(cat&&it.category!==cat)return false;
        var sf=W._bm.stateFilter||'';
        if(sf==='core'&&!it.core)return false;
        if(sf==='coupled'&&!it.installed)return false;
        if(sf==='incubator'&&lc.state!=='incubator')return false;
        if(q){
            var hay=(it.slug+' '+it.name+' '+(it.category||'')+' '+(it.desc||'')+' '+(it.core?'core sistema disponible':'')+' '+(it.installed?'acoplado':(it.core?'':'disponible'))+' '+(lc.state==='incubator'?'en desarrollo incubadora':'')+' '+(it.theme_used?'usado por el tema':'')).toLowerCase();
            if(hay.indexOf(q)===-1)return false;
        }
        return true;
    });
    items=W.bmSortItems(items);
    var coreItems=items.filter(function(it){return it.core});
    var coupled=items.filter(function(it){return it.installed&&!it.core});
    var decoupled=items.filter(function(it){return !it.installed&&!it.core});
    var html='';
    if(W._bm.isCentral)html+=W.blBatchBar();
    html+=W.bmStateBar();
    var sep='';
    if(coreItems.length){html+='<div class="w-bm-section'+sep+'"><div class="w-bm-section-head"><span class="w-bm-section-title">◆ Bricks Core (siempre disponibles)</span><span class="w-bm-section-count">'+coreItems.length+'</span></div><div class="w-bm-grid">'+coreItems.map(W.bmCard).join('')+'</div></div>';sep=' w-bm-section-sep'}
    if(coupled.length){html+='<div class="w-bm-section'+sep+'"><div class="w-bm-section-head"><span class="w-bm-section-title">✓ Acoplados</span><span class="w-bm-section-count">'+coupled.length+'</span></div><div class="w-bm-grid">'+coupled.map(W.bmCard).join('')+'</div></div>';sep=' w-bm-section-sep'}
    if(decoupled.length){html+='<div class="w-bm-section'+sep+'"><div class="w-bm-section-head"><span class="w-bm-section-title off">◻ Lanzados para acoplar</span><span class="w-bm-section-count">'+decoupled.length+'</span></div><div class="w-bm-grid">'+decoupled.map(W.bmCard).join('')+'</div></div>'}
    if(items.length)html+='<div style="margin-top:18px;font-size:11px;color:var(--w-muted);display:flex;gap:14px;flex-wrap:wrap"><span>◆ Core · siempre disponible (sistema)</span><span>✓ Acoplado (repositorio)</span><span>○ Disponible (sin acoplar)</span><span>🔥 En desarrollo</span><span>🙈 Oculto</span></div>';
    if(!items.length)html='<div class="w-empty-state"><h3>Sin resultados</h3><p>Prueba otra categoría u otra búsqueda.</p></div>';
    wrap.innerHTML=html;
};

W.bmStateBar=function(){
    var f=W._bm.stateFilter||'';
    var b=function(v,l){return '<button class="w-btn '+(f===v?'w-btn-primary':'w-btn-secondary')+' w-btn-sm" onclick="wontia.bmState(\''+v+'\')">'+l+'</button>'};
    return '<div class="w-flex w-gap-sm" style="flex-wrap:wrap;margin-bottom:12px">'+b('','Todos')+b('core','◆ Core')+b('coupled','✓ Acoplados')+b('incubator','🔥 En desarrollo')+'</div>';
};
W.bmState=function(v){W._bm.stateFilter=v||'';W.bmRender()};

W.bmRefreshMetrics=async function(){
    var d=await W.api('/api/v1/admin/bricks/metrics');
    if(d.ok)W._bm.metrics=d.data;
};

W.bmRate=async function(slug,n){
    var r=await W.api('/api/v1/admin/bricks/'+encodeURIComponent(slug)+'/rate',{method:'POST',body:{rating:n}});
    if(r.ok){
        var lv=W.bmLevels[n-1];
        W.notify('Valoración: '+lv.emoji+' '+lv.label,'success');
        if(n>=4)W.confetti();
        await W.bmRefreshMetrics();
        W.bmRender();
    }
};

W.bmPreview=function(slug){
    W.api('/api/v1/admin/bricks/'+encodeURIComponent(slug)+'/event',{method:'POST',body:{event:'preview'}}).catch(function(){});
    W.brickPreview(slug);
};

W.bmBlocks=function(rect,color,mode){
    try{if(window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches)return}catch(e){}
    var n=12,cx=rect.left+rect.width/2,cy=rect.top+rect.height/2;
    for(var i=0;i<n;i++){
        var b=document.createElement('div');
        b.className='w-bm-fly';
        b.style.background=color;
        var sx,sy,ex,ey,rot;
        if(mode==='crumble'){
            sx=rect.left+Math.random()*Math.max(rect.width,60);
            sy=rect.top+Math.random()*Math.max(rect.height,60)*0.7;
            ex=sx+(Math.random()*180-90);
            ey=sy+140+Math.random()*180;
            rot=Math.random()*420-210;
        }else{
            sx=cx+(Math.random()*260-130);
            sy=cy+(Math.random()*260-130);
            ex=cx-6+((i%4)-1.5)*16;
            ey=cy-6+(Math.floor(i/4)-1)*16;
            rot=360;
        }
        b.style.left=sx+'px';
        b.style.top=sy+'px';
        document.body.appendChild(b);
        var a=b.animate([
            {transform:'translate(0,0) rotate(0deg) scale(1)',opacity:1},
            {transform:'translate('+(ex-sx)+'px,'+(ey-sy)+'px) rotate('+rot+'deg) scale('+(mode==='crumble'?0.55:1)+')',opacity:mode==='crumble'?0:1}
        ],{duration:mode==='crumble'?720:620,delay:i*22,easing:mode==='crumble'?'cubic-bezier(.45,0,.9,1)':'cubic-bezier(.22,1,.36,1)',fill:'forwards'});
        a.onfinish=(function(el){return function(){el.remove()}})(b);
    }
};

W.bmRefreshViews=function(){
    W._bm.loaded=false;
    if(W.state.bricksTab==='instalados'){W.bsInstalled();return}
    if(W.state.bricksTab==='marketplace'){W.bsMarketplace();return}
    W.bhRefresh();
};

W.bmInstall=function(btn,sourceId,slug,name){
    var card=btn?btn.closest('.w-bm-card'):null;
    var rect=card?card.getBoundingClientRect():{left:innerWidth/2-60,top:innerHeight/2-60,width:120,height:120};
    W.bmBlocks(rect,'#34d399','assemble');
    setTimeout(async function(){
        try{
            var r=await W.api('/api/v1/admin/brickhub/install',{method:'POST',body:{source_id:sourceId,slug:slug,name:name}});
            if(r.ok){
                W.api('/api/v1/admin/bricks/'+encodeURIComponent(slug)+'/event',{method:'POST',body:{event:'install'}}).catch(function(){});
                W.notify('"'+name+'" acoplado ✓','success');
                W.confetti();
                W.state.bmHighlight=slug;
                W._bm.loaded=false;
                window.location.hash='#bricks/instalados';
            }else W.notify(r.message||'No se pudo acoplar','error');
        }catch(e){W.notify('Error de conexión al acoplar','error')}
    },660);
};

W.bmUninstall=function(btn,id,name,slug){
    var card=btn?btn.closest('.w-bm-card'):null;
    var rect=card?card.getBoundingClientRect():{left:innerWidth/2-60,top:innerHeight/2-60,width:120,height:120};
    W.confirm('¿Desacoplar "'+name+'"? Volverá al Brick Marketplace.',function(){
        W.bmBlocks(rect,'#f87171','crumble');
        setTimeout(function(){W.bmSetState(slug,false);W.bmRender()},420);
        setTimeout(async function(){
            try{
                var r=await W.api('/api/v1/admin/brickhub/uninstall/'+id,{method:'DELETE'});
                if(r.ok){
                    W.api('/api/v1/admin/bricks/'+encodeURIComponent(slug)+'/event',{method:'POST',body:{event:'uninstall'}}).catch(function(){});
                    W.notify('"'+name+'" desacoplado — devuelto al Marketplace','success');
                    W.bmRefreshViews();
                }else{
                    W.bmSetState(slug,true);W.bmRender();
                    W.notify(r.message||'No se pudo desacoplar — restaurado','error');
                }
            }catch(e){
                W.bmSetState(slug,true);W.bmRender();
                W.notify('Error de conexión — brick restaurado','error');
            }
        },780);
    });
};

W.sglDeactivate=function(btn,name){
    var card=btn?btn.closest('.w-bm-card'):null;
    var rect=card?card.getBoundingClientRect():{left:innerWidth/2-60,top:innerHeight/2-60,width:120,height:120};
    W.confirm('¿Desacoplar "'+name+'"? Volverá al Brick Marketplace.',function(){
        W.bmBlocks(rect,'#f87171','crumble');
        setTimeout(function(){W.bmSetState('seo-global-launch',false);W.bmRender()},420);
        setTimeout(async function(){
            try{
                var r=await W.api('/api/v1/admin/seo-global-launch/deactivate',{method:'POST'});
                if(r.ok){
                    W.api('/api/v1/admin/bricks/seo-global-launch/event',{method:'POST',body:{event:'uninstall'}}).catch(function(){});
                    W.notify('"'+name+'" desacoplado — devuelto al Marketplace','success');
                    W.bmRefreshViews();
                }else{
                    W.bmSetState('seo-global-launch',true);W.bmRender();
                    W.notify(r.message||'No se pudo desacoplar — restaurado','error');
                }
            }catch(e){
                W.bmSetState('seo-global-launch',true);W.bmRender();
                W.notify('Error de conexión — brick restaurado','error');
            }
        },780);
    });
};

W.bmActivate=function(btn){
    var card=btn?btn.closest('.w-bm-card'):null;
    var rect=card?card.getBoundingClientRect():{left:innerWidth/2-60,top:innerHeight/2-60,width:120,height:120};
    W.bmBlocks(rect,'#34d399','assemble');
    setTimeout(async function(){
        try{
            var r=await W.api('/api/v1/admin/seo-global-launch/activate',{method:'POST'});
            if(r.ok){
                W.notify('SEO Global Launch acoplado ✓','success');
                W.confetti();
                W.state.bmHighlight='seo-global-launch';
                W._bm.loaded=false;
                window.location.hash='#bricks/instalados';
            }else W.notify(r.message||'No se pudo acoplar','error');
        }catch(e){W.notify('Error de conexión al acoplar','error')}
    },620);
};

W.bmUpdate=function(id,slug){
    W.api('/api/v1/admin/bricks/'+encodeURIComponent(slug)+'/event',{method:'POST',body:{event:'update'}}).catch(function(){});
    W.bhApplyUpdate(id);
};

W.bmInsights=async function(){
    await W.bmLoad();
    var ins=(W._bm.metrics&&W._bm.metrics.insights)||{};
    var tot=ins.totals||{};
    var ev=tot.events||{};
    var dist=tot.dist||[0,0,0,0,0];
    var max=Math.max.apply(null,dist.concat([1]));
    var bars='';
    for(var i=5;i>=1;i--){
        var lv=W.bmLevels[i-1];
        bars+='<div style="display:flex;align-items:center;gap:8px;margin-bottom:6px"><span style="width:150px;font-size:11px">'+lv.emoji+' '+W.esc(lv.label)+'</span><div class="w-brick-bar" style="flex:1"><i style="width:'+Math.round(dist[i-1]/max*100)+'%"></i></div><span style="width:40px;text-align:right;font-size:11px">'+dist[i-1]+'</span></div>';
    }
    var rank=function(list,key){
        return (list||[]).map(function(x){return '<div style="display:flex;justify-content:space-between;font-size:11px;padding:4px 0;border-bottom:1px solid var(--w-border)"><span>'+W.esc(x.slug||x.name||'')+'</span><span style="color:var(--w-muted)">'+x[key]+'</span></div>'}).join('')||'<div style="font-size:11px;color:var(--w-muted)">Sin datos</div>';
    };
    var html='<div class="w-stats">'
        +'<div class="w-stat-card"><div class="w-stat-value">'+W.num(tot.ratings||0)+'</div><div class="w-stat-label">Valoraciones</div></div>'
        +'<div class="w-stat-card"><div class="w-stat-value">'+(tot.avg||0)+' ★</div><div class="w-stat-label">Promedio</div></div>'
        +'<div class="w-stat-card"><div class="w-stat-value">'+W.num(tot.five||0)+'</div><div class="w-stat-label">5 estrellas 🚀</div></div>'
        +'<div class="w-stat-card"><div class="w-stat-value">'+W.num(tot.low||0)+'</div><div class="w-stat-label">≤2 estrellas</div></div>'
        +'</div>';
    html+='<div class="w-brick-grid2" style="display:grid;grid-template-columns:1fr 1fr;gap:14px">'
        +'<div class="w-card"><h3>Distribución de estrellas</h3>'+bars+'</div>'
        +'<div class="w-card"><h3>Embudo medible</h3>'
        +'<div style="font-size:12px;line-height:2">👁 Previews: <strong>'+W.num(ev.preview||0)+'</strong></div>'
        +'<div style="font-size:12px;line-height:2">⬇ Instalaciones: <strong>'+W.num(ev.install||0)+'</strong></div>'
        +'<div style="font-size:12px;line-height:2">📄 Añadidos a página: <strong>'+W.num(ev.add_to_page||0)+'</strong></div>'
        +'<div style="font-size:12px;line-height:2">🔄 Actualizaciones: <strong>'+W.num(ev.update||0)+'</strong></div>'
        +'<div style="font-size:12px;line-height:2">🗑 Desinstalaciones: <strong>'+W.num(ev.uninstall||0)+'</strong></div>'
        +'</div></div>';
    html+='<div class="w-brick-grid2" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-top:14px">'
        +'<div class="w-card"><h3>🏆 Mejor valorados</h3>'+rank(ins.top_rated,'rating_avg')+'</div>'
        +'<div class="w-card"><h3>⚠ Menos gustados</h3>'+rank(ins.worst_rated,'rating_avg')+'</div>'
        +'<div class="w-card"><h3>⬇ Más instalados</h3>'+rank(ins.most_installed,'installs')+'</div>'
        +'</div>';
    var rec=(ins.recent||[]).map(function(r){var lv=W.bmLevels[(r.rating||1)-1];return '<div style="font-size:11px;padding:4px 0;border-bottom:1px solid var(--w-border)">'+lv.emoji+' <strong>'+W.esc(r.brick_slug)+'</strong> — '+W.esc(r.comment||'')+' <span style="color:var(--w-muted)">'+W.esc(r.username||'')+'</span></div>'}).join('')||'<div style="font-size:11px;color:var(--w-muted)">Sin comentarios aún</div>';
    html+='<div class="w-card" style="margin-top:14px"><h3>Últimas opiniones</h3>'+rec+'</div>';
    W.modal('Métricas de gusto — Brick Marketplace',html,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cerrar</button>');
};

W.bsMarketplace=async function(){
    var el=document.getElementById('bs-content');
    if(!el)return;
    el.innerHTML='<div style="text-align:center;padding:40px;color:var(--w-muted)">Cargando Brick Marketplace...</div>';
    await W.bmLoad();
    W._bm.view='marketplace';
    var items=(W._bm.items||[]).filter(function(it){var lc=it.lifecycle||{};return lc.state==='launched'&&(!lc.hidden||W._bm.showHidden)});
    var cats={};
    items.forEach(function(it){cats[it.category]=1});
    var ins=(W._bm.metrics&&W._bm.metrics.insights)||{};
    var tot=ins.totals||{};
    var top=(ins.top_rated&&ins.top_rated[0])||null;
    var worst=(ins.worst_rated&&ins.worst_rated[0])||null;
    var ev=tot.events||{};
    var html='<div class="w-card" style="padding:14px 18px;margin-bottom:14px"><div style="font-size:13px;font-weight:700">Brick Marketplace</div><div style="font-size:11px;color:var(--w-muted);margin-top:4px;line-height:1.7">Solo se muestran los bricks <strong style="color:var(--w-primary)">lanzados</strong> (terminados). Lo que sigue en desarrollo vive en la <strong>Incubadora</strong> con su cuenta regresiva. La etiqueta <span class="w-bm-new">NUEVO</span> marca los lanzamientos recientes.</div></div>';
    html+='<div class="w-bm-insights">'
        +'<div class="w-bm-ins"><b>'+W.num(tot.ratings||0)+'</b><span>Valoraciones</span></div>'
        +'<div class="w-bm-ins"><b>'+(tot.avg||0)+' ★ '+(tot.ratings?W.bmLevel(tot.avg).emoji:'')+'</b><span>Promedio global</span></div>'
        +'<div class="w-bm-ins"><b>'+(top?W.esc(top.slug):'—')+'</b><span>Mejor valorado'+(top?' · '+top.rating_avg+'★':'')+'</span></div>'
        +'<div class="w-bm-ins"><b>'+(worst?W.esc(worst.slug):'—')+'</b><span>Menos gustado'+(worst?' · '+worst.rating_avg+'★':'')+'</span></div>'
        +'<div class="w-bm-ins"><b>'+(ev.preview||0)+' → '+(ev.install||0)+'</b><span>Previews → Instalaciones</span></div>'
        +'<div class="w-bm-ins" style="display:flex;align-items:center"><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.bmInsights()">Ver métricas de gusto</button></div>'
        +'</div>';
    html+='<div class="w-flex-between w-mb" style="gap:12px;flex-wrap:wrap">'
        +'<input class="w-input" id="bs-search" placeholder="Buscar brick…" style="max-width:240px"/>'
        +'<div class="w-toolbar" id="bs-cats"><button class="w-btn w-btn-primary w-btn-sm" data-cat="">Todas ('+items.length+')</button>';
    Object.keys(cats).sort().forEach(function(c){html+='<button class="w-btn w-btn-secondary w-btn-sm" data-cat="'+W.esc(c)+'">'+W.esc(c)+'</button>'});
    html+='</div>'
        +(W._bm.isCentral?'<label class="w-flex" style="gap:6px;font-size:11px;align-items:center;color:var(--w-muted);margin-right:10px"><input type="checkbox" id="bs-showhidden"'+(W._bm.showHidden?' checked':'')+' onchange="wontia.blShowHidden(this.checked)"/> Ver ocultos</label>':'')
        +'<select class="w-select" id="bs-sort" style="max-width:220px">'
        +'<option value="recientes">Más recientes</option>'
        +'<option value="instalados">Más instalados</option>'
        +'<option value="rating">Mejor valorados</option>'
        +'<option value="usados">Más usados en páginas</option>'
        +'<option value="version">Versión (mayor primero)</option>'
        +'<option value="categoria">Por categoría</option>'
        +'</select></div>';
    html+='<div id="bs-grid"></div>';
    el.innerHTML=html;
    document.getElementById('bs-sort').value=W._bm.sort;
    document.getElementById('bs-sort').addEventListener('change',function(){W._bm.sort=this.value;W.bmRender()});
    document.getElementById('bs-search').addEventListener('input',function(){W._bm.q=this.value.toLowerCase();W.bmRender()});
    document.querySelectorAll('#bs-cats button').forEach(function(btn){
        btn.addEventListener('click',function(){
            document.querySelectorAll('#bs-cats button').forEach(function(x){x.classList.remove('w-btn-primary');x.classList.add('w-btn-secondary')});
            btn.classList.remove('w-btn-secondary');btn.classList.add('w-btn-primary');
            W._bm.cat=btn.dataset.cat||'';
            W.bmRender();
        });
    });
    W.bmRender();
};

W.bsInstalled=async function(){
    var el=document.getElementById('bs-content');
    if(!el)return;
    el.innerHTML='<div style="text-align:center;padding:40px;color:var(--w-muted)">Cargando bricks instalados...</div>';
    await W.bmLoad();
    W._bm.view='installed';
    var upd={};
    try{
        var u=await W.api('/api/v1/admin/brickhub/updates');
        (u.updates||[]).forEach(function(x){upd[x.slug||x.brick_slug||'']=x});
    }catch(e){}
    var items=(W._bm.items||[]).filter(function(it){var lc=it.lifecycle||{};return (it.installed||(it.origin==='core'&&(it.usage_count||0)>0))&&lc.state!=='incubator'&&(!lc.hidden||W._bm.showHidden)});
    items.forEach(function(it){it.update_available=!!upd[it.slug]});
    var html='<div class="w-card" style="padding:14px 18px;margin-bottom:14px"><div style="font-size:13px;font-weight:700">Bricks Acoplados</div><div style="font-size:11px;color:var(--w-muted);margin-top:4px;line-height:1.7">Bricks acoplados a este sitio: los instalados desde repositorios/incubadora y los bricks <strong style="color:var(--w-text)">Core</strong> que ya están en uso en las páginas del sitio. El <span style="color:var(--w-primary)">punto verde palpitante</span> indica funcionamiento correcto; el resplandor verde marca los acoplados y 🔄 avisa actualizaciones.</div></div>';
    html+='<div class="w-bm-grid" id="bs-grid"></div>';
    el.innerHTML=html;
    var grid=document.getElementById('bs-grid');
    grid.innerHTML=items.map(W.bmCard).join('')||'<div class="w-empty-state" style="grid-column:1/-1"><h3>Sin bricks acoplados</h3><p>Explora el Brick Marketplace para acoplar.</p></div>';
    if(W.state.bmHighlight){
        var hi=grid.querySelector('.w-bm-card[data-slug="'+W.state.bmHighlight+'"]');
        W.state.bmHighlight=null;
        if(hi){
            hi.classList.add('w-bm-flash');
            try{hi.scrollIntoView({block:'center',behavior:'smooth'})}catch(e){}
            setTimeout(function(){hi.classList.remove('w-bm-flash')},2600);
        }
    }
};

W.bsIncubator=async function(){
    var el=document.getElementById('bs-content');
    if(!el)return;
    el.innerHTML='<div style="text-align:center;padding:40px;color:var(--w-muted)">Cargando incubadora...</div>';
    await W.bmLoad();
    W._bm.view='incubator';
    var items=(W._bm.items||[]).filter(function(it){var lc=it.lifecycle||{};return lc.state==='incubator'&&(!lc.hidden||W._bm.showHidden)});
    items.sort(function(a,b){var ta=(a.lifecycle&&a.lifecycle.target_launch_at)||'',tb=(b.lifecycle&&b.lifecycle.target_launch_at)||'';return String(ta).localeCompare(String(tb))});
    var admin=W._bm.isCentral;
    var html='<div class="w-card" style="padding:14px 18px;margin-bottom:14px"><div style="font-size:13px;font-weight:700">🧪 Incubadora de Bricks</div><div style="font-size:11px;color:var(--w-muted);margin-top:4px;line-height:1.7">Bricks en desarrollo (no terminados). Aquí NO aparecen en el Marketplace: solo pasan al lanzarlos. La <strong style="color:var(--w-primary)">cuenta regresiva</strong> indica cuándo se lanzará cada uno.'+(admin?' <strong>Solo admin</strong> ve el botón 🚀 Lanzar.':'')+'</div></div>';
    if(admin)html+=W.blBatchBar();
    html+='<div id="bs-grid"></div>';
    var roadmapInner=(items.length?items.map(function(it){var lc=it.lifecycle||{};return '<div class="w-bl-rl-item"><span class="w-bm-chip">'+W.esc(it.category||'general')+'</span><span class="w-bl-rl-name">'+W.esc(it.name)+'</span>'+(it.functional===false?'<span class="w-bm-dev">🔥</span>':'')+'<span class="w-bl-rl-date">'+(lc.target_launch_at?W.esc(String(lc.target_launch_at).slice(0,16)):'por definir')+'</span></div>'}).join(''):'<div style="font-size:11px;color:var(--w-muted)">Nada en la incubadora todavía.</div>');
    var recent=(W._bm.lcRecent||[]).slice(0,8);
    html+='<div class="w-cascade">';
    html+=W.accBlock('roadmap','🗺️','Roadmap de lanzamientos',items.length,roadmapInner);
    if(recent.length){
        html+=W.accBlock('activity','📜','Actividad reciente',recent.length,recent.map(function(e){return '<div class="w-bl-rl-item"><span class="w-bm-chip">'+W.esc(e.event||'')+'</span><span class="w-bl-rl-name">'+W.esc(e.slug||'')+'</span><span class="w-bl-rl-date">'+W.esc(String(e.created_at||'').slice(0,16))+' · '+W.esc(e.actor||'')+'</span></div>'}).join(''));
    }
    html+='</div>';
    el.innerHTML=html;
    W.accInit(el);
    var grid=document.getElementById('bs-grid');
    if(!items.length){grid.innerHTML='<div class="w-empty-state"><h3>Incubadora vacía</h3><p>No hay bricks en desarrollo. Todo lo terminado está en el Brick Marketplace.</p></div>';return}
    var groups={};
    items.forEach(function(it){var c=it.category||'general';(groups[c]=groups[c]||[]).push(it)});
    var out='';
    Object.keys(groups).sort().forEach(function(c){
        out+=W.blCatHead(c,groups[c],admin)+'<div class="w-bm-grid">'+groups[c].map(W.bmCard).join('')+'</div></div>';
    });
    grid.innerHTML=out;
    W.startCdTick();
};

W.accBlock=function(key,icon,title,count,innerHtml){
    return '<details class="w-acc" data-acc="'+W.esc(key)+'"><summary class="w-acc__sum">'
        +'<span class="w-acc__ic" aria-hidden="true">'+icon+'</span>'
        +'<span class="w-acc__t">'+W.esc(title)+'</span>'
        +(count!=null?'<span class="w-acc__c">'+W.num(count)+'</span>':'')
        +'<span class="w-acc__chev" aria-hidden="true">&#9662;</span></summary>'
        +'<div class="w-acc__wrap"><div class="w-acc__body"><div class="w-acc__inner">'+innerHtml+'</div></div></div></details>';
};

W.accInit=function(scope){
    (scope||document).querySelectorAll('details.w-acc[data-acc]').forEach(function(d){
        var k='wwi_acc_'+d.getAttribute('data-acc');
        try{ if(localStorage.getItem(k)==='1')d.open=true; }catch(e){}
        if(!d.__accBound){d.__accBound=1; d.addEventListener('toggle',function(){try{localStorage.setItem(k,d.open?'1':'0')}catch(e){}});}
    });
};

W.blCatHead=function(cat,items,admin){
    var slugs=items.map(function(it){return W.esc(it.slug)}).join(',');
    return '<div class="w-bm-section" data-cat="'+W.esc(cat)+'"><div class="w-bm-section-head">'
        +(admin?'<label class="w-bm-check" title="Seleccionar categoría"><input type="checkbox" onchange="wontia.blCatSel(\''+W.esc(cat)+'\',this.checked)"/></label>':'')
        +'<span class="w-bm-section-title">'+W.esc(cat)+'</span><span class="w-bm-section-count">'+items.length+'</span>'
        +(admin?'<span style="flex:1"></span><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.blCatConfig(\''+W.esc(cat)+'\')" title="Configuración de la categoría">⚙ Config</button><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.blCatHide(\''+slugs+'\',1)" title="Ocultar categoría">🙈 Ocultar</button>':'')
        +'</div>';
};

W.blCatSel=function(cat,checked){
    (W._bm.items||[]).forEach(function(it){
        if((it.category||'general')===cat){
            var cb=document.querySelector('.wb-sel[data-slug="'+it.slug+'"]');
            if(cb)cb.checked=checked;
        }
    });
    W.blSel();
};

W.blCatHide=async function(slugs,hidden){
    var list=(slugs||'').split(',').filter(Boolean);
    for(var i=0;i<list.length;i++){try{await W.api('/api/v1/admin/bricklifecycle/'+encodeURIComponent(list[i])+'/hide',{method:'POST',body:{hidden:hidden}})}catch(e){}}
    W.notify(hidden?'Categoría oculta del catálogo':'Categoría visible','success');
    W.blReload();
};

W.blCatConfig=function(cat){
    var items=(W._bm.items||[]).filter(function(it){return (it.category||'general')===cat&&it.origin==='core'});
    var html='<div style="font-size:11px;color:var(--w-muted);margin-bottom:10px">Bricks de <strong>'+W.esc(cat)+'</strong>. Abre la configuración (esquema) de cada uno.</div><div style="display:flex;flex-direction:column;gap:6px">';
    if(!items.length)html+='<div style="font-size:11px;color:var(--w-muted)">Esta categoría no tiene bricks Core configurables.</div>';
    items.forEach(function(it){html+='<button class="w-btn w-btn-secondary" style="justify-content:space-between" onclick="wontia.showBrickConfig(\''+W.esc(it.slug)+'\')"><span>'+W.esc(it.name)+'</span><span style="font-size:10px;color:var(--w-muted)">⚙ Esquema</span></button>'});
    html+='</div>';
    W.modal('Configuración — '+cat,html,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cerrar</button>');
};

W.blBatchBar=function(){
    return '<div class="w-card w-bl-batch" id="wb-batch" style="padding:10px 14px;margin-bottom:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">'
        +'<span style="font-size:11px;color:var(--w-muted)">Seleccionados: <b id="wb-batch-n">0</b></span>'
        +'<button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.blBatch(\'launch\')">🚀 Lanzar</button>'
        +'<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.blBatch(\'incubate\')">↩ Reinculbar</button>'
        +'<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.blBatch(\'hide\')">🙈 Ocultar</button>'
        +'<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.blBatch(\'show\')">👁 Mostrar</button>'
        +'</div>';
};

W.blSel=function(){
    var n=document.querySelectorAll('.wb-sel:checked').length;
    var el=document.getElementById('wb-batch-n');
    if(el)el.textContent=n;
};

W.blBatch=async function(action){
    var sel=Array.prototype.slice.call(document.querySelectorAll('.wb-sel:checked')).map(function(cb){return cb.dataset.slug});
    if(!sel.length){W.notify('Selecciona al menos un brick','error');return}
    var body={};
    if(action==='hide')body={hidden:1};
    if(action==='show')body={hidden:0};
    if(action==='launch')body={release_notes:'Lanzamiento en lote desde la incubadora',maturity:'stable'};
    for(var i=0;i<sel.length;i++){
        try{
            var url=action==='hide'||action==='show'?'/hide':(action==='launch'?'/launch':'/incubate');
            await W.api('/api/v1/admin/bricklifecycle/'+encodeURIComponent(sel[i])+url,{method:'POST',body:body});
        }catch(e){}
    }
    W.notify('Acción "'+action+'" aplicada a '+sel.length+' bricks','success');
    if(action==='launch')W.confetti();
    W.blReload();
};

W.cdHtml=function(slug,target,estimated){
    if(!target)return '';
    var p=W.cdParts(target);
    if(!p)return '<div class="w-bm-cd expired"><span class="w-cd-cap">⏳ Lanzamiento inminente</span></div>';
    return '<div class="w-bm-cd" data-cd-target="'+W.esc(target)+'"><span class="w-cd-cap">⏳ Lanzamiento en'+(estimated?' (estimado)':'')+'</span><div class="w-cd-grid" aria-label="cuenta regresiva" aria-live="off">'+W.cdSegs(p)+'</div></div>';
};

W.cdSegs=function(p){
    var u=[['años',p.years],['meses',p.months],['sem',p.weeks],['días',p.days],['h',p.hours],['min',p.minutes],['seg',p.seconds]];
    return u.map(function(x){return '<span class="w-cd-seg"><b>'+x[1]+'</b><i>'+x[0]+'</i></span>'}).join('');
};

W.cdParts=function(target){
    var now=new Date(),t=new Date(target);
    var total=Math.floor((t-now)/1000);
    if(isNaN(total)||total<=0)return null;
    var y=t.getFullYear()-now.getFullYear(),mo=t.getMonth()-now.getMonth(),d=t.getDate()-now.getDate(),h=t.getHours()-now.getHours(),mi=t.getMinutes()-now.getMinutes(),se=t.getSeconds()-now.getSeconds();
    if(se<0){se+=60;mi--}
    if(mi<0){mi+=60;h--}
    if(h<0){h+=24;d--}
    if(d<0){var pm=new Date(t.getFullYear(),t.getMonth(),0).getDate();d+=pm;mo--}
    if(mo<0){mo+=12;y--}
    return {years:Math.max(0,y),months:Math.max(0,mo),weeks:Math.floor(d/7),days:d%7,hours:h,minutes:mi,seconds:se,total:total};
};

W.startCdTick=function(){
    if(W._cdTimer)clearInterval(W._cdTimer);
    var reduce=false;try{reduce=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches}catch(e){}
    if(reduce)return;
    W._cdTimer=setInterval(function(){
        var nodes=document.querySelectorAll('.w-bm-cd[data-cd-target]');
        if(!nodes.length){clearInterval(W._cdTimer);W._cdTimer=null;return}
        nodes.forEach(function(n){
            var p=W.cdParts(n.getAttribute('data-cd-target'));
            var grid=n.querySelector('.w-cd-grid');
            if(!p){n.innerHTML='<span class="w-cd-cap">⏳ Lanzamiento inminente</span>';return}
            if(!grid)return;
            grid.innerHTML=W.cdSegs(p);
        });
    },1000);
};

W.blReload=function(){
    W._bm.loaded=false;
    var t=W.state.bricksTab||'marketplace';
    if(t==='incubadora')W.bsIncubator();
    else if(t==='instalados')W.bsInstalled();
    else W.bsMarketplace();
};

W.blShowHidden=function(v){W._bm.showHidden=!!v;W.blReload()};

W.blLaunch=async function(slug,name){
    var r=null;try{r=await W.api('/api/v1/admin/bricklifecycle/'+encodeURIComponent(slug)+'/readiness')}catch(e){}
    var rd=(r&&r.data)||{score:0,checks:[]};
    var checks=(rd.checks||[]).map(function(c){return '<div style="display:flex;justify-content:space-between;font-size:11px;padding:4px 0;border-bottom:1px solid var(--w-border)"><span>'+(c.ok?'✅':'⬜')+' '+W.esc(c.label)+'</span><span style="color:var(--w-muted)">'+c.weight+'%</span></div>'}).join('');
    var html='<div style="font-size:11px;color:var(--w-muted);margin-bottom:8px">Vas a lanzar <strong>'+W.esc(name)+'</strong> al Brick Marketplace.</div>';
    html+='<div style="margin-bottom:10px"><div class="w-flex-between" style="font-size:11px"><span>Preparación</span><b>'+rd.score+'%</b></div><div class="w-brick-bar" style="height:8px"><i style="width:'+rd.score+'%"></i></div></div>'+checks;
    html+='<div class="w-form-group" style="margin-top:10px"><label class="w-label">Notas de versión (obligatorio)</label><textarea class="w-textarea" id="bl-notes" placeholder="Qué incluye este lanzamiento…"></textarea></div>';
    html+='<div class="w-form-group"><label class="w-label">Madurez</label><select class="w-select" id="bl-maturity"><option value="stable">STABLE</option><option value="rc">RC</option><option value="beta">BETA</option><option value="alpha">ALPHA</option></select></div>';
    W.modal('🚀 Lanzar al Marketplace',html,'<button class="w-btn w-btn-primary" onclick="wontia.blLaunchDo(\''+W.esc(slug)+'\')">Lanzar</button> <button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancelar</button>');
};

W.blLaunchDo=async function(slug){
    var notes=(document.getElementById('bl-notes')||{}).value||'';
    var maturity=(document.getElementById('bl-maturity')||{}).value||'stable';
    if(notes.trim().length<3){W.notify('Escribe las notas de versión','error');return}
    var r=await W.api('/api/v1/admin/bricklifecycle/'+encodeURIComponent(slug)+'/launch',{method:'POST',body:{release_notes:notes,maturity:maturity}});
    if(r.ok){W.closeModal();W.confetti();W.notify('Brick lanzado al Marketplace ✓','success');W.blReload()}
    else W.notify(r.message||'No se pudo lanzar','error');
};

W.blSchedule=async function(slug,name){
    var base=new Date(Date.now()+90*86400000);
    var val=base.toISOString().slice(0,16);
    function preset(days){var d=new Date(Date.now()+days*86400000);return d.toISOString().slice(0,16)}
    var html='<div style="font-size:11px;color:var(--w-muted);margin-bottom:8px">Programa el lanzamiento de <strong>'+W.esc(name)+'</strong>.</div>';
    html+='<div class="w-toolbar" style="margin-bottom:8px"><button class="w-btn w-btn-secondary w-btn-sm" onclick="document.getElementById(\'bl-date\').value=\''+preset(30)+'\'">+1 mes</button><button class="w-btn w-btn-secondary w-btn-sm" onclick="document.getElementById(\'bl-date\').value=\''+preset(90)+'\'">+3 meses</button><button class="w-btn w-btn-secondary w-btn-sm" onclick="document.getElementById(\'bl-date\').value=\''+preset(365)+'\'">+1 año</button></div>';
    html+='<div class="w-form-group"><label class="w-label">Fecha y hora</label><input class="w-input" type="datetime-local" id="bl-date" value="'+val+'"/></div>';
    W.modal('🗓 Programar lanzamiento',html,'<button class="w-btn w-btn-primary" onclick="wontia.blScheduleDo(\''+W.esc(slug)+'\')">Programar</button> <button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancelar</button>');
};

W.blScheduleDo=async function(slug){
    var v=(document.getElementById('bl-date')||{}).value||'';
    if(!v){W.notify('Elige una fecha','error');return}
    var r=await W.api('/api/v1/admin/bricklifecycle/'+encodeURIComponent(slug)+'/schedule',{method:'POST',body:{target_launch_at:v}});
    if(r.ok){W.closeModal();W.notify('Lanzamiento programado ✓','success');W.blReload()}
    else W.notify(r.message||'No se pudo programar','error');
};

W.blIncubate=function(slug,name){
    W.confirm('¿Reinculbar "'+W.esc(name)+'"? Volverá a la Incubadora y saldrá del Marketplace.',async function(){
        var r=await W.api('/api/v1/admin/bricklifecycle/'+encodeURIComponent(slug)+'/incubate',{method:'POST',body:{reason:'manual'}});
        if(r.ok){W.notify('Brick reinculbado','success');W.blReload()}else W.notify(r.message||'No se pudo','error');
    });
};

W.blInterest=async function(slug){
    var r=await W.api('/api/v1/admin/bricklifecycle/'+encodeURIComponent(slug)+'/interest',{method:'POST'});
    if(r.ok){W.notify('¡Gracias! Te avisaremos cuando se lance 🔔','success')}
};

W.blHide=async function(slug,hidden){
    var r=await W.api('/api/v1/admin/bricklifecycle/'+encodeURIComponent(slug)+'/hide',{method:'POST',body:{hidden:hidden?1:0}});
    if(r.ok){W.notify(hidden?'Brick oculto del catálogo':'Brick visible en el catálogo','success');W.blReload()}
    else W.notify(r.message||'No se pudo','error');
};

W.brickPreview=async function(type){
    var existing=document.getElementById('w-pv-overlay');
    if(existing)existing.remove();
    var ov=document.createElement('div');
    ov.className='w-pv-overlay';
    ov.id='w-pv-overlay';
    ov.innerHTML='<div class="w-pv-box" id="w-pv-box"><div class="w-pv-head"><div style="font-size:12px;font-weight:700">Preview — '+W.esc(type)+'</div><div class="w-flex" style="gap:4px"><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.pvDevice(900)">Desktop</button><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.pvDevice(700)">Tablet</button><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.pvDevice(390)">Mobile</button><button class="w-btn w-btn-secondary w-btn-sm" onclick="document.getElementById(\'w-pv-overlay\').remove()">&#x2715;</button></div></div><iframe id="w-pv-frame" sandbox="allow-scripts allow-same-origin" style="width:100%;flex:1;border:none;background:#06080f"></iframe></div>';
    document.body.appendChild(ov);
    ov.addEventListener('mousedown',function(e){if(e.target===ov)ov.remove()});
    var frame=document.getElementById('w-pv-frame');
    frame.srcdoc='<div style="padding:40px;color:#8593ab;font-family:Inter,sans-serif">Cargando preview…</div>';
    try{
        var r=await fetch('/api/v1/admin/bricks/'+encodeURIComponent(type)+'/preview',{headers:{Authorization:'Bearer '+W.token}});
        var html=await r.text();
        frame.srcdoc=html;
    }catch(e){
        frame.srcdoc='<div style="padding:40px;color:#f87171;font-family:Inter,sans-serif">No se pudo cargar el preview.</div>';
    }
};

W.pvDevice=function(w){
    var b=document.getElementById('w-pv-box');
    if(b)b.style.maxWidth=w+'px';
};

W.showBrickShowcase=async function(slug){
    if(!window.__WWI_IS_CENTRAL){W.notify('El showcase de bricks solo lo gestiona el administrador de WWI.','error');return}
    var r=await W.api('/api/v1/admin/bricks/'+encodeURIComponent(slug)+'/showcase');
    W._sh={slug:slug,items:(r.data||[])};
    W.shModal();
};
W.shModal=function(){
    var s=W._sh;var html='';
    html+='<div style="font-size:12px;color:var(--w-muted);margin-bottom:10px">Capturas del brick en funcionamiento. La ⭐ es la portada. Solo administradores.</div>';
    if(s.items.length){
        html+='<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px">';
        s.items.forEach(function(it,i){
            var thumb=it.media_type==='video'?'<div style="display:grid;place-items:center;height:88px;background:#0d1220;color:#9fb0c9;font-size:11px">▶ video</div>':'<img src="'+W.esc(it.media)+'" style="width:100%;height:88px;object-fit:cover;display:block"/>';
            html+='<div style="border:1px solid var(--w-border);border-radius:10px;overflow:hidden">'+thumb
              +'<div style="padding:6px">'
              +'<div style="font-size:11px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+W.esc(it.caption||('#'+(i+1)))+(Number(it.is_cover)?' ⭐':'')+'</div>'
              +'<div style="font-size:10px;color:var(--w-muted)">'+W.esc(it.device)+(it.availability?(' · '+W.esc(it.availability)):'')+(it.version?(' · v'+W.esc(it.version)):'')+'</div>'
              +'<div style="display:flex;gap:4px;margin-top:5px;flex-wrap:wrap">'
              +'<button class="w-btn w-btn-secondary w-btn-sm" title="Subir" onclick="wontia.shMove('+it.id+',-1)">↑</button>'
              +'<button class="w-btn w-btn-secondary w-btn-sm" title="Bajar" onclick="wontia.shMove('+it.id+',1)">↓</button>'
              +(Number(it.is_cover)?'':'<button class="w-btn w-btn-secondary w-btn-sm" title="Portada" onclick="wontia.shCover('+it.id+')">⭐</button>')
              +'<button class="w-btn w-btn-secondary w-btn-sm" title="Editar" onclick="wontia.shEdit('+it.id+')">✎</button>'
              +'<button class="w-btn w-btn-danger w-btn-sm" title="Eliminar" onclick="wontia.shDel('+it.id+')">🗑</button>'
              +'</div></div></div>';
        });
        html+='</div>';
    } else { html+='<div style="padding:14px;color:var(--w-muted);border:1px dashed var(--w-border);border-radius:10px">Sin capturas. Añade la primera.</div>'; }
    html+='<div style="margin-top:16px;border-top:1px solid var(--w-border);padding-top:12px"><b style="font-size:12px">Añadir captura</b>'
      +'<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;align-items:center">'
      +'<button class="w-btn w-btn-secondary" onclick="wontia.shUpload()">⬆ Subir</button><button class="w-btn w-btn-secondary" onclick="wontia.shPick()">📁 Media</button><input type="file" id="sh-file" accept="image/*,video/*" style="display:none" onchange="wontia.shDoUpload(this.files)"/>'
      +'<input class="w-input" id="sh-media" placeholder="URL de la imagen/video" style="flex:1;min-width:200px"/>'
      +'<select class="w-input" id="sh-device" style="width:120px"><option value="all">Todo</option><option value="desktop">Desktop</option><option value="tablet">Tablet</option><option value="mobile">Mobile</option></select>'
      +'<select class="w-input" id="sh-type" style="width:110px"><option value="image">Imagen</option><option value="video">Video</option></select>'
      +'</div><div id="sh-media-grid" style="margin-top:8px"></div>'
      +'<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">'
      +'<input class="w-input" id="sh-caption" placeholder="Leyenda" style="flex:1;min-width:150px"/>'
      +'<input class="w-input" id="sh-alt" placeholder="Texto alternativo" style="flex:1;min-width:150px"/>'
      +'<input class="w-input" id="sh-demo" placeholder="URL demo (opcional)" style="flex:1;min-width:150px"/>'
      +'<select class="w-input" id="sh-avail" style="width:160px"><option value="">— Disponibilidad —</option><option value="available">Disponible</option><option value="in-development">En desarrollo</option><option value="future">Futuro</option><option value="concept">Concepto</option><option value="prototype">Prototipo</option></select>'
      +'<input class="w-input" id="sh-version" placeholder="Versión" style="width:100px"/>'
      +'<button class="w-btn w-btn-primary" onclick="wontia.shAdd()">Añadir</button>'
      +'</div></div>';
    W.modal('⚙ Showcase — '+W.esc(s.slug),html,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cerrar</button>');
};
W.shRefresh=async function(){var r=await W.api('/api/v1/admin/bricks/'+encodeURIComponent(W._sh.slug)+'/showcase');W._sh.items=(r.data||[]);W.shModal();};
W.shAdd=async function(){
    var body={media:document.getElementById('sh-media').value,device:document.getElementById('sh-device').value,media_type:document.getElementById('sh-type').value,caption:document.getElementById('sh-caption').value,alt:document.getElementById('sh-alt').value,demo_url:document.getElementById('sh-demo').value,availability:document.getElementById('sh-avail').value,version:document.getElementById('sh-version').value};
    var r=await W.api('/api/v1/admin/bricks/'+encodeURIComponent(W._sh.slug)+'/showcase',{method:'POST',body:body});
    if(r.ok){W.notify('Captura añadida','success');W.shRefresh()}else W.notify((r.data&&r.data.message)||'Error','error');
};
W.shDel=async function(id){if(!confirm('¿Eliminar esta captura?'))return;var r=await W.api('/api/v1/admin/bricks/'+encodeURIComponent(W._sh.slug)+'/showcase/'+id,{method:'DELETE'});if(r.ok){W.notify('Eliminada','success');W.shRefresh()}};
W.shCover=async function(id){var r=await W.api('/api/v1/admin/bricks/'+encodeURIComponent(W._sh.slug)+'/showcase/'+id+'/cover',{method:'POST',body:{}});if(r.ok)W.shRefresh()};
W.shMove=async function(id,dir){var ids=W._sh.items.map(function(x){return x.id});var i=ids.indexOf(id);var j=i+dir;if(i<0||j<0||j>=ids.length)return;var t=ids[i];ids[i]=ids[j];ids[j]=t;var r=await W.api('/api/v1/admin/bricks/'+encodeURIComponent(W._sh.slug)+'/showcase/reorder',{method:'PUT',body:{ids:ids}});if(r.ok)W.shRefresh()};
W.shEdit=function(id){var it=null;W._sh.items.forEach(function(x){if(Number(x.id)===Number(id))it=x});if(!it)return;
    var html='<div style="display:flex;gap:8px;flex-wrap:wrap">'
      +'<input class="w-input" id="she-caption" value="'+W.esc(it.caption||'')+'" placeholder="Leyenda" style="flex:1;min-width:180px"/>'
      +'<input class="w-input" id="she-alt" value="'+W.esc(it.alt||'')+'" placeholder="Alt" style="flex:1;min-width:180px"/></div>'
      +'<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">'
      +'<input class="w-input" id="she-demo" value="'+W.esc(it.demo_url||'')+'" placeholder="URL demo" style="flex:1;min-width:180px"/>'
      +'<select class="w-input" id="she-device" style="width:130px">'+['all','desktop','tablet','mobile'].map(function(d){return '<option value="'+d+'"'+(it.device===d?' selected':'')+'>'+d+'</option>'}).join('')+'</select>'
      +'<select class="w-input" id="she-type" style="width:110px"><option value="image"'+(it.media_type==='image'?' selected':'')+'>Imagen</option><option value="video"'+(it.media_type==='video'?' selected':'')+'>Video</option></select>'
      +'<select class="w-input" id="she-avail" style="width:170px"><option value="">— Disponibilidad —</option>'+['available','in-development','future','concept','prototype'].map(function(a){return '<option value="'+a+'"'+(it.availability===a?' selected':'')+'>'+a+'</option>'}).join('')+'</select>'
      +'<input class="w-input" id="she-version" value="'+W.esc(it.version||'')+'" placeholder="Versión" style="width:110px"/>'
      +'</div>';
    W.modal('Editar captura',html,'<button class="w-btn w-btn-secondary" onclick="wontia.shModal()">Volver</button><button class="w-btn w-btn-primary" onclick="wontia.shSave('+it.id+')">Guardar</button>');
};
W.shSave=async function(id){var body={caption:document.getElementById('she-caption').value,alt:document.getElementById('she-alt').value,demo_url:document.getElementById('she-demo').value,device:document.getElementById('she-device').value,media_type:document.getElementById('she-type').value,availability:document.getElementById('she-avail').value,version:document.getElementById('she-version').value};var r=await W.api('/api/v1/admin/bricks/'+encodeURIComponent(W._sh.slug)+'/showcase/'+id,{method:'PUT',body:body});if(r.ok){W.notify('Guardado','success');W.shRefresh()}else W.notify('Error','error')};
W.shSetMedia=function(u){var i=document.getElementById('sh-media');if(i)i.value=u;var g=document.getElementById('sh-media-grid');if(g)g.innerHTML=u?('<img src="'+W.esc(u)+'" style="max-width:160px;border-radius:8px;border:1px solid var(--w-border)"/>'):''};
W.shPick=async function(){var g=document.getElementById('sh-media-grid');if(!g)return;g.innerHTML='<div style="color:var(--w-muted);font-size:12px">Cargando…</div>';try{var d=await W.api('/api/v1/admin/media?type=image');var items=(d&&d.data)||[];if(!items.length){g.innerHTML='<div style="color:var(--w-muted);font-size:12px">No hay imágenes en la Biblioteca.</div>';return}g.innerHTML='<div class="w-lib-grid">'+items.map(function(m){return '<button type="button" class="w-lib-item" title="'+W.esc(m.filename||'')+'" onclick="wontia.shSetMedia(\''+W.esc(m.url)+'\')"><img src="'+W.esc(m.url)+'" alt=""/></button>'}).join('')+'</div>'}catch(e){g.innerHTML='<div style="color:#ef4444;font-size:12px">Error cargando la biblioteca.</div>'}};
W.shUpload=function(){var f=document.getElementById('sh-file');if(f)f.click()};
W.shDoUpload=async function(files){
    for(var i=0;i<files.length;i++){
        if(files[i].size>12*1024*1024){W.notify('“'+files[i].name+'” supera 12MB.','error');continue}
        var fd=new FormData();fd.append('file',files[i]);
        W.notify('Subiendo '+files[i].name+'…','info');
        var r=await W.api('/api/v1/admin/media/upload',{method:'POST',body:fd});
        if(r&&r.ok){
            var u=(r.data&&(r.data.url||(r.data.data&&r.data.data.url)))||'';
            W.shSetMedia(u);
            W.notify('Subida lista: '+files[i].name,'success');
        }else W.notify((r&&r.data&&r.data.message)||'Error subiendo','error');
    }
};

W.brickAddToPage=async function(type,name){
    var d=await W.api('/api/v1/admin/pages');
    var pages=d.data||[];
    if(!pages.length){W.notify('No hay páginas todavía','error');return}
    var html='<div style="font-size:11px;color:var(--w-muted);margin-bottom:10px">Elige la página donde añadir <strong>'+W.esc(name)+'</strong> como nueva sección (al final).</div><div style="display:flex;flex-direction:column;gap:6px">';
    pages.forEach(function(p){
        html+='<button class="w-btn w-btn-secondary" style="justify-content:space-between" onclick="wontia.brickAddConfirm('+p.id+',\''+W.esc(type)+'\',\''+W.esc(name)+'\')"><span>'+W.esc(p.title)+'</span><span style="font-size:10px;color:var(--w-muted)">/'+W.esc(p.slug)+'</span></button>';
    });
    html+='</div>';
    W.modal('Añadir brick a página',html,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancelar</button>');
};

W.brickAddConfirm=async function(pageId,type,name){
    var r=await W.api('/api/v1/admin/pages/'+pageId+'/sections',{method:'POST',body:{type:'widget',widget_type:type,title:name,config:{}}});
    if(r.ok){
        W.closeModal();
        W.confetti();
        W.notify('"'+name+'" añadido a la página','success');
        setTimeout(function(){window.location.hash='#sections/'+pageId},450);
    }else if(r.message)W.notify(r.message,'error');
};

W.confetti=function(){
    try{if(window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches)return}catch(e){}
    var colors=['#22d3ee','#8b5cf6','#ec4899','#fbbf24','#34d399'];
    for(var i=0;i<24;i++){
        var d=document.createElement('div');
        d.className='w-confetti';
        d.style.background=colors[i%colors.length];
        d.style.left=(window.innerWidth/2)+'px';
        d.style.top=(window.innerHeight/2)+'px';
        d.style.setProperty('--dx',(Math.random()*520-260)+'px');
        d.style.setProperty('--dy',(Math.random()*-380-60)+'px');
        document.body.appendChild(d);
        setTimeout((function(el){return function(){el.remove()}})(d),1700);
    }
};

W.renderMegaMenu=async function(){
    var app=document.getElementById('wontia-app');
    app.innerHTML='<div style="padding:40px;text-align:center;color:var(--w-muted)">Cargando Mega Menú…</div>';
    var d=await W.api('/api/v1/admin/bricks/mega-menu');
    var b=(d&&d.data)||{};
    W._mmSchema=b.configSchema||[];
    var cfg={};
    try{var c=await W.api('/api/v1/admin/bricks/mega-menu/config');cfg=(c.data&&c.data.config)||{};}catch(e){}
    var h='<div class="w-flex-between w-mb-lg" style="flex-wrap:wrap;gap:8px"><div><div style="font-size:11px;color:var(--w-muted)">Bricks › Mega Menú</div><h3 style="margin:0">🧭 Mega Menú</h3></div>'
      +'<div class="w-flex w-gap-sm"><button class="w-btn w-btn-secondary" onclick="window.location.hash=\'#bricks\'">← Volver a Bricks</button><button class="w-btn w-btn-primary" onclick="wontia.mmSave()">💾 Guardar</button></div></div>';
    h+='<div style="font-size:12px;color:var(--w-muted);margin-bottom:12px">Panel lateral + megamenú del <b>header</b> del sitio (categorías hasta 5 niveles). Los datos salen del catálogo real. Actívalo con el interruptor de abajo.</div>';
    h+='<div class="w-card"><div class="schema-fields" id="mm-editor">'+W.renderSchemaFields(W._mmSchema,cfg)+'</div>';
    h+='<div class="sec-foot" style="position:sticky;bottom:0;background:var(--w-bg);padding:12px 0;border-top:1px solid var(--w-border);display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap"><span id="mm-status" class="sec-status">Listo</span><span class="w-flex w-gap-sm"><button class="w-btn w-btn-secondary" onclick="window.location.hash=\'#bricks\'">← Volver</button><button class="w-btn w-btn-primary" onclick="wontia.mmSave()">💾 Guardar cambios</button></span></div></div>';
    app.innerHTML=h;
    app.insertAdjacentHTML('beforeend','<div class="w-card" style="margin-top:14px"><div class="w-flex-between" style="flex-wrap:wrap;gap:8px"><h3 style="margin:0">🌳 Estructura del menú</h3><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.mmTreeLoad()">↻ Recargar</button></div><div style="font-size:12px;color:var(--w-muted);margin:6px 0 10px">Cada elemento con su <b>texto</b> y su <b>enlace</b>. Origen: <b>Categoría</b> = definida en el tema; <b>Tipo</b> = <code>product_type</code>, <b>Marca</b> = <code>vendor</code>, <b>Modelo</b> = tags del catálogo. Los enlaces usan los filtros reales de <code>/buscar</code>.</div><div id="mm-tree"></div></div>');
    if(W.repInit)W.repInit(W._mmSchema); if(W.secLinkInit)W.secLinkInit();
    W.mmTreeLoad();
    W._mmDirty=false;
    var el=document.getElementById('mm-editor');
    var st=function(s,c){var e=document.getElementById('mm-status');if(e){e.textContent=s;e.className='sec-status'+(c?' '+c:'')}};
    st('Listo');
    ['input','change'].forEach(function(ev){el.addEventListener(ev,function(){W._mmDirty=true;st('Sin guardar','dirty')})});
};
W.mmSave=async function(){
    var cfg=W.collectSchemaFields(W._mmSchema||[]);
    var r=await W.api('/api/v1/admin/bricks/mega-menu/config',{method:'POST',body:{config:cfg}});
    var e=document.getElementById('mm-status');
    if(r&&r.ok){W._mmDirty=false;if(e){e.textContent='Guardado';e.className='sec-status saved'}W.notify('Mega Menú guardado','success');W.mmTreeLoad()}
    else{if(e){e.textContent='Error';e.className='sec-status err'}W.notify('No se pudo guardar','error')}
};
W.mmTreeLoad=async function(){
    var box=document.getElementById('mm-tree'); if(!box)return;
    box.innerHTML='<div style="padding:16px;color:var(--w-muted);font-size:12px">Cargando estructura…</div>';
    var r=await W.api('/api/v1/admin/bricks/mega-menu/tree');
    var cats=(r&&r.data&&r.data.categories)||[];
    if(!cats.length){box.innerHTML='<div class="w-empty-state"><p>Sin categorías (revisa el catálogo).</p></div>';return}
    var h='';
    cats.forEach(function(c){
        var ints=W.num(c.count)+' producto(s)';
        h+='<details class="w-acc" data-acc="mmt_'+W.esc(c.slug)+'"><summary class="w-acc__sum">'
          +'<span class="w-acc__ic">'+(c.icon||'📁')+'</span>'
          +'<span class="w-acc__t" style="text-transform:none;color:var(--w-text)">'+W.esc(c.name)+'</span>'
          +'<span class="w-acc__c">'+ints+'</span>'
          +'<a class="w-acc__c" href="'+W.esc(c.url)+'" target="_blank" rel="noopener" onclick="event.stopPropagation()" style="text-decoration:none" title="Abrir enlace">'+W.esc(c.url)+' ↗</a>'
          +'<span class="w-acc__chev">&#9662;</span></summary>'
          +'<div class="w-acc__wrap"><div class="w-acc__body"><div class="w-acc__inner">'
          +W.mmTreeLevel('Tipos','product_type',c.types)
          +W.mmTreeLevel('Marcas','vendor',c.brands)
          +W.mmTreeLevel('Modelos','tags',c.models)
          +W.mmTreeLevel('Precio','rango &min/&max',c.prices)
          +'</div></div></div></details>';
    });
    box.innerHTML=h;
    if(W.accInit)W.accInit(box);
};
W.mmTreeLevel=function(title,src,items){
    if(!items||!items.length)return '<div style="font-size:11px;color:var(--w-muted);margin-bottom:8px">'+W.esc(title)+': <i>sin elementos</i></div>';
    var h='<div style="margin-bottom:12px"><div style="font-size:11px;font-weight:800;color:var(--w-primary);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">'+W.esc(title)+' <span style="color:var(--w-muted);font-weight:600;text-transform:none">· origen: '+W.esc(src)+'</span></div>';
    h+='<div style="display:flex;flex-direction:column;gap:2px">';
    items.forEach(function(x){h+='<div style="display:flex;justify-content:space-between;gap:10px;font-size:12px;padding:3px 0;border-bottom:1px dashed var(--w-border)"><span>'+W.esc(x.name)+(x.count!=null?' <span style="color:var(--w-muted)">('+W.num(x.count)+')</span>':'')+'</span><a href="'+W.esc(x.url)+'" target="_blank" rel="noopener" style="color:var(--w-muted);text-decoration:none;font-family:var(--w-font-mono);font-size:11px">'+W.esc(x.url)+' ↗</a></div>'});
    return h+'</div></div>';
};
if(!window.__wwiMmUnload){window.__wwiMmUnload=1;window.addEventListener('beforeunload',function(e){if(W._mmDirty){e.preventDefault();e.returnValue=''}});}

W.showBrickConfig=async function(type){
    if(type==='banner'){ window.location.hash='#banners'; return; }
    if(type==='mega-menu'){ window.location.hash='#mega-menu'; return; }
    if(type==='sonika-hero'){ window.location.hash='#hero'; return; }
    var d=await W.api('/api/v1/admin/bricks/'+type);
    var b=d.data||{};
    var schema=b.configSchema||[];
    W._bcType=type; W._bcSchema=schema;
    var cfg={};
    try{var c=await W.api('/api/v1/admin/bricks/'+encodeURIComponent(type)+'/config'); cfg=(c.data&&c.data.config)||{};}catch(e){}
    var body=schema.length
        ?'<div class="schema-fields" id="bc-fields">'+W.renderSchemaFields(schema,cfg)+'</div>'
        :'<div style="font-size:12px;color:var(--w-muted)">Este brick no tiene opciones configurables.</div>';
    var footer='<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cerrar</button>';
    if(schema.length)footer+='<button class="w-btn w-btn-primary" onclick="wontia.saveBrickConfig()">Guardar</button>';
    W.modal(((b.meta&&b.meta.name)||type)+' <span style="font-size:10px;color:var(--w-muted)">Configuración del brick</span>',body,footer);
    if(W.repInit)W.repInit(schema);
    if(W.secLinkInit)W.secLinkInit();
};
W.saveBrickConfig=async function(){
    var type=W._bcType, schema=W._bcSchema||[];
    if(!type)return;
    var cfg=W.collectSchemaFields(schema);
    var r=await W.api('/api/v1/admin/bricks/'+encodeURIComponent(type)+'/config',{method:'POST',body:{config:cfg}});
    if(r&&r.ok){W.notify('Configuración guardada','success');W.closeModal();}
    else{W.notify((r&&r.message)||'No se pudo guardar la configuración','error');}
};

W.codeEditorField=function(f){
    var uid='ce-'+f.key;
    var html='<div class="w-form-group"><div class="w-flex-between" style="margin-bottom:6px"><label class="w-label" style="margin-bottom:0">'+W.esc(f.label)+'</label>';
    html+='<div class="w-toolbar" style="gap:2px">';
    html+='<button class="w-btn w-btn-secondary" style="padding:2px 6px;font-size:9px" onclick="wontia.insSnippet(\''+uid+'\',\'<div>...</div>\')" title="Insert div">&lt;div&gt;</button>';
    html+='<button class="w-btn w-btn-secondary" style="padding:2px 6px;font-size:9px" onclick="wontia.insSnippet(\''+uid+'\',\'<script>\\n// your code\\n</script>\')" title="Insert script">&lt;script&gt;</button>';
    html+='<button class="w-btn w-btn-secondary" style="padding:2px 6px;font-size:9px" onclick="wontia.insSnippet(\''+uid+'\',\'<style>\\n/* your styles */\\n</style>\')" title="Insert style">&lt;style&gt;</button>';
    html+='<button class="w-btn w-btn-secondary" style="padding:2px 6px;font-size:9px" onclick="wontia.insSnippet(\''+uid+'\',\'<iframe src=\\"\\" width=\\"100%\\" height=\\"400\\" frameborder=\\"0\\"></iframe>\')" title="Insert iframe">iframe</button>';
    html+='<button class="w-btn w-btn-secondary" style="padding:2px 6px;font-size:9px" onclick="wontia.openFullEditor(\''+uid+'\')" title="Fullscreen editor">&#x26F6;</button>';
    html+='</div></div>';
    html+='<textarea class="w-textarea w-code-editor" id="'+uid+'" style="min-height:200px;font-family:\'SF Mono\',\'Fira Code\',\'Consolas\',monospace;font-size:12px;line-height:1.6;background:#0d1117;color:#c9d1d9;border:1px solid #30363d;padding:16px;tab-size:2;white-space:pre;overflow:auto" spellcheck="false" onkeydown="wontia.handleCodeTab(event,this)">'+W.esc(f.default||'')+'</textarea>';
    if(f.help)html+='<div style="font-size:9px;color:var(--w-muted);margin-top:4px">'+W.esc(f.help)+'</div>';
    html+='</div>';
    return html;
};

W.insSnippet=function(id,snippet){
    var ta=document.getElementById(id);
    if(!ta)return;
    var start=ta.selectionStart;
    var end=ta.selectionEnd;
    var text=ta.value;
    ta.value=text.substring(0,start)+snippet+text.substring(end);
    ta.selectionStart=ta.selectionEnd=start+snippet.length;
    ta.focus();
};

W.openFullEditor=function(id){
    var ta=document.getElementById(id);
    if(!ta)return;
    var content=ta.value;
    W.modal('Fullscreen Code Editor',
        '<textarea id="fe-full" style="width:100%;min-height:60vh;font-family:\'SF Mono\',\'Fira Code\',\'Consolas\',monospace;font-size:13px;line-height:1.6;background:#0d1117;color:#c9d1d9;border:1px solid #30363d;padding:20px;tab-size:2;white-space:pre;overflow:auto;resize:none" spellcheck="false" onkeydown="wontia.handleCodeTab(event,this)">'+W.esc(content)+'</textarea>',
        '<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancel</button><button class="w-btn w-btn-primary" onclick="document.getElementById(\''+id+'\').value=document.getElementById(\'fe-full\').value;wontia.closeModal()">Apply & Close</button>'
    );
};

W.handleCodeTab=function(e,ta){
    if(e.key==='Tab'){e.preventDefault();var s=ta.selectionStart;ta.value=ta.value.substring(0,s)+'  '+ta.value.substring(ta.selectionEnd);ta.selectionStart=ta.selectionEnd=s+2}
};

W.renderBrickHub=function(){W.state.bhHost=null;W.renderBrickHubInto(document.getElementById('wontia-app'),false)};

W.renderBrickHubInto=function(app,compact){
    var tab=W.state.bhTab||'marketplace';
    W.state.bhTab=tab;
    var tabs=[
        {id:'marketplace',label:'Marketplace'},
        {id:'sources',label:'Sources'},
        {id:'installed',label:'Installed'},
        {id:'updates',label:'Updates'},
        {id:'sites',label:'Sites'},
        {id:'history',label:'History'}
    ];
    var tabBar='';
    if(!compact)tabBar+='<div class="w-card" style="padding:14px 18px;margin-bottom:14px"><div style="font-size:13px;font-weight:700">BrickHub — tienda y actualizador de extensiones</div><div style="font-size:11px;color:var(--w-muted);margin-top:4px;line-height:1.7">Conecta repositorios de GitHub (<strong>Sources</strong>) que publican bricks. Desde aquí puedes <strong>descubrir</strong> nuevos bricks, <strong>instalarlos</strong> en este sitio (<strong>Marketplace/Installed</strong>), y recibir <strong>Updates</strong> cuando los repos publican versiones nuevas. Cuando haya una actualización pendiente verás un aviso en el sidebar. Todo se instala con validación de seguridad y registro de historial.</div></div>';
    tabBar+='<div class="w-toolbar w-mb-lg" style="border-bottom:1px solid var(--w-border);padding-bottom:12px">';
    tabs.forEach(function(t){
        tabBar+='<button class="w-btn '+(tab===t.id?'w-btn-primary':'w-btn-secondary')+'" onclick="wontia.switchBHTab(\''+t.id+'\')">'+t.label+'</button>';
    });
    tabBar+='</div>';
    app.innerHTML='<div>'+tabBar+'<div id="bh-content"></div></div>';
    var fns={marketplace:W.bhMarketplace,sources:W.bhSources,installed:W.bhInstalled,updates:W.bhUpdates,sites:W.bhRegisteredSites,history:W.bhHistory};
    (fns[tab]||W.bhMarketplace)();
};

W.switchBHTab=function(t){W.state.bhTab=t;W.renderBrickHubInto(W.state.bhHost||document.getElementById('wontia-app'),!!W.state.bhHost)};

W.bhMarketplace=async function(){
    var el=document.getElementById('bh-content');
    el.innerHTML='<div style="text-align:center;padding:40px;color:var(--w-muted)">Loading marketplace...</div>';
    var d=await W.api('/api/v1/admin/brickhub');
    var bricks=d.data||[];
    if(!bricks.length){el.innerHTML='<div class="w-empty-state"><h3>No bricks in marketplace</h3><p>Add a GitHub source to discover bricks to install.</p><button class="w-btn w-btn-primary" onclick="wontia.switchBHTab(\'sources\')">Add Source</button></div>';return}
    var html='<div class="w-flex-between w-mb"><div><strong style="font-size:13px">'+bricks.length+' bricks available</strong></div><div class="w-flex w-gap-sm"><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.bhScanLocal()">Scan Local</button><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.bhSetupWizard()">Setup Wizard</button><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.renderBrickHub()">Refresh</button><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.bhSyncAll()">Sync All</button></div></div>';
    html+='<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px">';
    bricks.forEach(function(b){
        var installed=!!b.installed;
        html+='<div class="w-card" style="padding:18px">';
        html+='<div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:12px">';
        html+='<div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,'+(installed?'rgba(0,184,125,.2)':'rgba(155,140,222,.2)')+');display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">'+(b.category==='system'?'\u2699':b.category==='integration'?'\uD83D\uDD17':'\uD83E\uDDE9')+'</div>';
        html+='<div style="flex:1;min-width:0"><div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+W.esc(b.name)+'</div>';
        html+='<div style="font-size:10px;color:var(--w-muted);margin-top:2px">'+W.esc(b.source_name||'')+'</div>';
        if(installed)html+='<span style="font-size:9px;background:rgba(0,184,125,.15);color:var(--w-primary);padding:1px 8px;border-radius:10px;margin-top:4px;display:inline-block">v'+W.esc(b.installed_version)+' installed</span>';
        else html+='<span style="font-size:9px;background:rgba(190,19,65,.12);color:var(--w-accent);padding:1px 8px;border-radius:10px;margin-top:4px;display:inline-block">Not installed</span>';
        html+='</div></div>';
        html+='<div style="display:flex;gap:6px;justify-content:flex-end">';
        if(installed){
            html+='<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.bhCheckUpdate('+b.installed_id+')">Check Update</button>';
            html+='<button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.bhUninstall('+b.installed_id+',\''+W.esc(b.name)+'\')">Uninstall</button>';
        }else{
            html+='<button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.bhInstall('+b.source_id+',\''+W.esc(b.slug)+'\',\''+W.esc(b.name)+'\')">Install</button>';
        }
        html+='</div></div>';
    });
    html+='</div>';
    el.innerHTML=html;
};

W.bhSources=async function(){
    var el=document.getElementById('bh-content');
    el.innerHTML='<div style="text-align:center;padding:40px;color:var(--w-muted)">Loading sources...</div>';
    var d=await W.api('/api/v1/admin/brickhub/sources');
    var sources=d.data||[];
    var html='<div class="w-flex-between w-mb-lg"><div><strong style="font-size:13px">'+sources.length+' sources</strong></div><button class="w-btn w-btn-primary" onclick="wontia.bhShowAddSource()">+ Add Source</button></div>';
    if(!sources.length){html+='<div class="w-empty-state"><h3>No GitHub sources</h3><p>Add your first source to start discovering bricks</p></div>'}
    sources.forEach(function(s){
        html+='<div class="w-card" style="padding:16px"><div class="w-flex-between w-mb"><div><div style="font-size:13px;font-weight:600">'+W.esc(s.name)+'</div><div style="font-size:11px;color:var(--w-muted)">'+W.esc(s.repo_url)+' <span class="w-badge w-badge-'+(s.is_active?'published':'draft')+'">'+(s.is_active?'active':'inactive')+'</span></div></div><div class="w-flex w-gap-sm">';
        if(s.last_version)html+='<span style="font-size:10px;color:var(--w-muted)">Latest: v'+W.esc(s.last_version)+'</span>';
        html+='<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.bhSyncSource('+s.id+')">Sync</button>';
        html+='<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.bhDiscoverSource('+s.id+')">Discover</button>';
        html+='<button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.bhRemoveSource('+s.id+',\''+W.esc(s.name)+'\')">Remove</button>';
        html+='</div></div>';
        if(s.brick_count>0)html+='<div style="font-size:11px;color:var(--w-muted);margin-top:6px">'+s.brick_count+' brick(s) installed from this source</div>';
        html+='</div>';
    });
    el.innerHTML=html;
};

W.bhInstalled=async function(){
    var el=document.getElementById('bh-content');
    el.innerHTML='<div style="text-align:center;padding:40px;color:var(--w-muted)">Loading...</div>';
    var d=await W.api('/api/v1/admin/brickhub/installed');
    var bricks=d.data||[];
    if(!bricks.length){el.innerHTML='<div class="w-empty-state"><h3>No bricks installed</h3><p>Visit the Marketplace to install bricks</p><button class="w-btn w-btn-primary" onclick="wontia.switchBHTab(\'marketplace\')">Go to Marketplace</button></div>';return}
    var html='<div class="w-flex-between w-mb-lg"><strong style="font-size:13px">'+bricks.length+' installed</strong></div>';
    bricks.forEach(function(b){
        html+='<div class="w-card" style="padding:14px"><div class="w-flex-between"><div><div style="font-size:13px;font-weight:600">'+W.esc(b.name)+' <span style="font-size:10px;color:var(--w-muted)">v'+W.esc(b.version)+'</span></div><div style="font-size:11px;color:var(--w-muted)">'+W.esc(b.category||'')+' &middot; '+W.esc(b.source_name||'manual')+' &middot; <span class="w-badge w-badge-'+(b.status==='active'?'published':'draft')+'">'+b.status+'</span></div></div><div class="w-flex w-gap-sm"><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.bhCheckUpdate('+b.id+')">Check</button><button class="w-btn w-btn-primary w-btn-sm" style="font-size:10px" onclick="wontia.bhPushToSites('+b.id+',\''+W.esc(b.name)+'\')">Push All</button><button class="w-btn w-btn-secondary w-btn-sm" style="color:#d2991d" onclick="wontia.bhBroadcast(\''+W.esc(b.slug)+'\',\''+W.esc(b.name)+'\')">Notify Sites</button><button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.bhUninstall('+b.id+',\''+W.esc(b.name)+'\')">Uninstall</button></div></div></div>';
    });
    el.innerHTML=html;
};

W.bhUpdates=async function(){
    var el=document.getElementById('bh-content');
    el.innerHTML='<div style="text-align:center;padding:40px;color:var(--w-muted)">Checking for updates...</div>';
    var d=await W.api('/api/v1/admin/brickhub/updates');
    var updates=d.updates||[];
    var html='<div class="w-flex-between w-mb-lg"><strong style="font-size:13px">'+updates.length+' update(s) available</strong><div class="w-flex w-gap-sm"><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.renderBrickHub()">Refresh</button>';
    if(updates.length>0)html+='<button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.bhApplyAllUpdates()">Apply All Updates</button>';
    html+='</div></div>';
    if(!updates.length){html+='<div class="w-empty-state"><h3>All bricks are up to date</h3><p>No updates available. Check back later.</p></div>'}
    updates.forEach(function(u){
        html+='<div class="w-card" style="padding:14px"><div class="w-flex-between"><div><div style="font-size:13px;font-weight:600">'+W.esc(u.name)+'</div><div style="font-size:11px;color:var(--w-muted)">'+W.esc(u.current)+' \u2192 <span style="color:var(--w-primary)">'+W.esc(u.latest)+'</span></div></div><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.bhApplyUpdate('+u.brick_id+')">Update Now</button></div></div>';
    });
    el.innerHTML=html;
};

W.bhHistory=async function(){
    var el=document.getElementById('bh-content');
    el.innerHTML='<div style="text-align:center;padding:40px;color:var(--w-muted)">Loading history...</div>';
    var d=await W.api('/api/v1/admin/brickhub/history');
    var history=d.data||[];
    if(!history.length){el.innerHTML='<div class="w-empty-state"><h3>No update history</h3><p>Updates applied will appear here</p></div>';return}
    var html='<div class="w-flex-between w-mb-lg"><strong style="font-size:13px">'+history.length+' record(s)</strong></div>';
    history.forEach(function(h){
        var statusColor=h.status==='applied'?'var(--w-primary)':h.status==='failed'?'var(--w-accent)':h.status==='pending'?'var(--w-muted)':'#fbbf24';
        html+='<div class="w-card" style="padding:12px"><div class="w-flex-between"><div><div style="font-size:12px;font-weight:600">'+W.esc(h.brick_name)+'</div><div style="font-size:10px;color:var(--w-muted)">'+W.esc(h.from_version)+' \u2192 '+W.esc(h.to_version)+'</div></div><div style="text-align:right"><span style="font-size:10px;color:'+statusColor+';font-weight:600">'+h.status+'</span><div style="font-size:9px;color:var(--w-muted)">'+W.esc(h.created_at||'')+'</div></div></div>';
        if(h.release_notes)html+='<div style="font-size:10px;color:var(--w-muted);margin-top:6px;white-space:pre-wrap;max-height:60px;overflow:hidden">'+W.esc(h.release_notes.substring(0,200))+'</div>';
        html+='</div>';
    });
    el.innerHTML=html;
};

W.bhRegisteredSites=async function(){
    var el=document.getElementById('bh-content');
    el.innerHTML='<div style="text-align:center;padding:40px;color:var(--w-muted)">Loading registered sites...</div>';
    var d=await W.api('/api/v1/admin/brickhub/registry');
    var sites=d.data||[];
    var html='<div class="w-flex-between w-mb-lg"><div><strong style="font-size:13px">'+sites.length+' registered site(s)</strong></div><div class="w-flex w-gap-sm"><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.bhRegisteredSites()">Refresh</button></div></div>';
    if(!sites.length){
        html+='<div class="w-empty-state"><h3>No registered sites</h3><p>Child WWI installations will appear here when they register with this mother installation.</p><div style="margin-top:16px;font-size:11px;color:var(--w-muted);background:var(--w-bg);padding:12px;border-radius:8px;text-align:left"><strong>How to connect a child site:</strong><br><br>1. On the child site admin, go to Settings<br>2. Set <code>BRICKHUB_MOTHER_URL</code> to <code id="bh-current-url" style="color:#22d3ee">'+window.location.origin+'</code><br>3. The child will auto-register on next admin login</div></div>'}
    else{
        html+='<table class="w-table"><thead><tr><th>Site</th><th>URL</th><th>Key</th><th>Last Seen</th><th>Status</th></tr></thead><tbody>';
        sites.forEach(function(s){
            html+='<tr><td style="font-weight:600">'+W.esc(s.child_name||'Unnamed')+'</td><td style="font-size:11px">'+W.esc(s.child_url)+'</td><td style="font-size:10px;font-family:monospace">'+W.esc(s.site_key.substring(0,16))+'...</td><td style="font-size:11px">'+W.esc(s.last_seen_at||'Never')+'</td><td><span class="w-badge w-badge-'+(s.is_active?'published':'draft')+'">'+(s.is_active?'active':'inactive')+'</span></td></tr>';
        });
        html+='</tbody></table>';
    }
    el.innerHTML=html;
};

W.bhShowAddSource=function(){
    var html='<div class="w-form-group"><label class="w-label">Source Name</label><input class="w-input" id="bs-name" placeholder="WWI Core"/></div>';
    html+='<div class="w-form-group"><label class="w-label">GitHub Repo URL</label><input class="w-input" id="bs-repo" placeholder="https://github.com/user/repo"/></div>';
    html+='<div class="w-form-group"><label class="w-label">Branch</label><input class="w-input" id="bs-branch" value="main"/></div>';
    html+='<div class="w-form-group"><label class="w-label">Install Path</label><input class="w-input" id="bs-path" value="/src/Bricks/"/></div>';
    html+='<div class="w-form-group"><label class="w-label">GitHub Token (optional, for private repos)</label><input class="w-input" id="bs-token" type="password" placeholder="ghp_..."/></div>';
    W.modal('Add GitHub Source',html,
        '<button class="w-btn w-btn-secondary" onclick="wontia.bhVerifyRepo()">Verify Repo</button>'+
        '<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancel</button>'+
        '<button class="w-btn w-btn-primary" id="bs-save">Add Source</button>'
    );
    document.getElementById('bs-save').addEventListener('click',async function(){
        var data={name:document.getElementById('bs-name').value,repo_url:document.getElementById('bs-repo').value,branch:document.getElementById('bs-branch').value,install_path:document.getElementById('bs-path').value,auth_token:document.getElementById('bs-token').value||null};
        if(!data.name||!data.repo_url){W.notify('Name and repo URL required','error');return}
        var r=await W.api('/api/v1/admin/brickhub/sources',{method:'POST',body:data});
        if(r.ok){W.closeModal();W.bhSources();W.notify('Source added','success')}
    });
};

W.bhVerifyRepo=async function(){
    var url=document.getElementById('bs-repo').value;
    var token=document.getElementById('bs-token').value;
    if(!url){W.notify('Enter a repo URL','error');return}
    W.notify('Verifying...','info');
    var r=await W.api('/api/v1/admin/brickhub/sources/verify',{method:'POST',body:{repo_url:url,auth_token:token||null}});
    if(r.ok)W.notify('Repo verified: '+r.name+' ('+r.language+', '+r.stars+' stars)','success');
};

W.bhRemoveSource=function(id,name){
    W.confirm('Remove source "'+name+'"? (Installed bricks will remain)',async function(){
        var r=await W.api('/api/v1/admin/brickhub/sources/'+id,{method:'DELETE'});
        if(r.ok){W.bhSources();W.notify('Source removed','success')}
    });
};

W.bhSyncSource=async function(id){
    W.notify('Syncing...','info');
    var r=await W.api('/api/v1/admin/brickhub/sync',{method:'POST',body:{source_id:id}});
    if(r.ok&&r.data){
        var d=r.data;
        W.notify(d.source+': v'+d.latest_version+' ('+d.updates_available+' updates)','success');
        W.bhSources();
    }
};

W.bhSyncAll=async function(){
    W.notify('Syncing all sources...','info');
    var r=await W.api('/api/v1/admin/brickhub/sync',{method:'POST',body:{}});
    if(r.ok){W.notify('Sync complete','success');W.bhRefresh()}
};

W.bhDiscoverSource=async function(id){
    W.notify('Discovering bricks...','info');
    var r=await W.api('/api/v1/admin/brickhub/sources/'+id+'/discover');
    if(r.ok&&r.data){
        var bricks=r.data;
        var list=bricks.map(function(b){return '<div style="padding:4px 0;font-size:12px">'+W.esc(b.name)+' <span style="color:var(--w-muted);font-size:10px">('+W.esc(b.slug)+')</span></div>'}).join('');
        W.modal('Discovered Bricks in '+W.esc(r.source),list||'<p style="color:var(--w-muted)">No bricks found in this repo</p>','<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Close</button>');
    }
};

W.bhInstall=async function(sourceId,slug,name){
    var r=await W.api('/api/v1/admin/brickhub/install',{method:'POST',body:{source_id:sourceId,slug:slug,name:name}});
    if(r.ok){W.notify('Installed: '+name,'success');W.bhRefresh()}
};

W.bhUninstall=function(id,name){
    W.confirm('Uninstall "'+name+'"?',async function(){
        var r=await W.api('/api/v1/admin/brickhub/uninstall/'+id,{method:'DELETE'});
        if(r.ok){W.notify('Uninstalled: '+name,'success');W.bhRefresh()}
    });
};

W.bhBroadcast=function(slug,name){
    W.confirm('Send update notification for "'+name+'" to all installed sites?',async function(){
        W.notify('Broadcasting...','info');
        var r=await W.api('/api/v1/admin/brickhub/broadcast/'+slug,{method:'POST'});
        if(r.ok)W.notify('Notified '+r.sites_notified+' site(s) about '+r.brick+' v'+r.version,'success');
    });
};

W.bhPushToSites=function(brickId,name){
    W.confirm('Push "'+name+'" update to ALL registered child sites? This sends HTTP requests to each site.',async function(){
        W.notify('Pushing to all sites...','info');
        var r=await W.api('/api/v1/admin/brickhub/push/'+brickId,{method:'POST'});
        if(r.ok)W.notify('Pushed to '+r.remote_pushed+'/'+r.remote_total+' remote sites, '+r.local_notified+' local','success');
    });
};

W.bhScanLocal=async function(){
    W.notify('Scanning local bricks...','info');
    var r=await W.api('/api/v1/admin/brickhub/scan-local',{method:'POST'});
    if(r.ok){
        var msg=r.registered+' new brick(s) registered, '+r.already_installed+' already present';
        W.notify(msg,'success');
        W.renderBrickHub();
    }
};

W.bhSetupWizard=async function(){
    W.modal('BrickHub Setup Wizard',
        '<div style="text-align:center;padding:20px">'+
        '<div style="font-size:48px;margin-bottom:16px">&#x1F9F1;</div>'+
        '<h3 style="margin-bottom:8px">BrickHub Initial Setup</h3>'+
        '<p style="font-size:12px;color:var(--w-muted);margin-bottom:20px">This will: create BrickHub tables, scan local bricks, and prepare the marketplace.</p>'+
        '<div id="bh-setup-status" style="font-size:11px;color:var(--w-muted);margin-bottom:16px">Ready to start...</div>'+
        '</div>',
        '<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancel</button>'+
        '<button class="w-btn w-btn-primary" id="bh-setup-run">Run Setup</button>'
    );
    document.getElementById('bh-setup-run').addEventListener('click',async function(){
        document.getElementById('bh-setup-status').innerHTML='<span style="color:#d2991d">Running setup...</span>';
        var r=await W.api('/api/v1/admin/brickhub/setup',{method:'POST'});
        if(r.ok){
            var tablesOk=!r.tables.errors||!r.tables.errors.length;
            var bricksMsg=r.bricks.message||'';
            document.getElementById('bh-setup-status').innerHTML=
                '<div style="color:var(--w-primary);margin-bottom:8px">Setup complete!</div>'+
                '<div style="font-size:10px">Tables: '+(tablesOk?'OK':'Issues')+' | '+bricksMsg+'</div>'+
                '<div style="font-size:10px;color:var(--w-muted);margin-top:8px">'+W.esc(r.source_note||'')+'</div>';
            setTimeout(function(){W.closeModal();W.renderBrickHub();W.notify('BrickHub ready','success')},2000);
        }
    });
};

W.updateBHBadge=async function(){
    try{
        var r=await W.api('/api/v1/admin/brickhub/notifications');
        var count=r.pending||0;
        var badge=document.getElementById('bh-badge');
        if(badge){
            if(count>0){badge.style.display='inline-block';badge.textContent=count}
            else{badge.style.display='none'}
        }
    }catch(e){}
};

W.autoCheckBrickHub=function(){
    W.updateBHBadge();
    W.api('/api/v1/admin/brickhub/auto-check',{method:'POST'}).then(function(r){
        if(r.ok&&r.data&&r.data.updates_found>0){
            W.notify(r.data.updates_found+' new BrickHub update(s) found','info');
            W.updateBHBadge();
        }
    }).catch(function(){});
    W.registerWithMother();
};

W.registerWithMother=function(){
    var mother=W.state.motherUrl||null;
    if(!mother)return;
    var siteKey=W.state.siteKey||('wwi_'+Math.random().toString(36).substring(2,18));
    W.state.siteKey=siteKey;
    var payload={child_url:window.location.origin,site_key:siteKey,child_name:document.title||'WWI Site'};
    fetch(mother+'/api/v1/brickhub/child/register',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}).catch(function(){});
};

W.bhCheckUpdate=async function(brickId){
    W.notify('Checking...','info');
    var r=await W.api('/api/v1/admin/brickhub/check/'+brickId);
    if(r.ok&&r.data&&r.data.available)W.notify('Update available: v'+r.data.latest_version,'success');
    else W.notify('Already up to date','info');
};

W.bhApplyUpdate=async function(brickId){
    W.notify('Applying update...','info');
    var r=await W.api('/api/v1/admin/brickhub/updates/apply/'+brickId,{method:'POST'});
    if(r.ok){W.notify('Updated!','success');W.bhRefresh()}
};

W.bhApplyAllUpdates=function(){
    W.confirm('Apply all available updates? This may take a few minutes.',async function(){
        W.notify('Applying all updates...','info');
        var r=await W.api('/api/v1/admin/brickhub/updates/apply-all',{method:'POST'});
        if(r.ok)W.notify(r.applied+' updated, '+r.failed+' failed','success');
        W.bhRefresh();
    });
};

W.state.brick={tab:'overview',providers:[],models:[],policies:[],instances:[],meta:null,range:'30d',group:'model'};

W.brickLoad=async function(){
    var m=W.state.brick;
    var jobs=[W.api('/api/v1/admin/brick/capabilities'),W.api('/api/v1/admin/brick/providers'),W.api('/api/v1/admin/brick/models'),W.api('/api/v1/admin/brick/policies'),W.api('/api/v1/admin/brick/instances')];
    var all=await Promise.all(jobs);
    if(all[0].data)m.meta=all[0];
    if(all[1].data)m.providers=all[1].data;
    if(all[2].data)m.models=all[2].data;
    if(all[3].data)m.policies=all[3].data;
    if(all[4].data)m.instances=all[4].data;
};

W.renderBrick=async function(tab){W.state.brickHost=null;await W.renderBrickInto(document.getElementById('wontia-app'),false,tab)};

W.renderBrickInto=async function(app,compact,tab){
    if(!app)return;
    var m=W.state.brick;
    tab=tab||m.tab||'overview';
    m.tab=tab;
    var tabs=[['overview','Overview'],['providers','Providers'],['models','Models'],['policies','Policies'],['systems','Systems'],['usage','Usage & Cost'],['test','Test Model']];
    var bar='';
    if(!compact)bar+='<div class="w-card" style="padding:14px 18px;margin-bottom:14px"><div style="font-size:13px;font-weight:700">AI BRICK — capa de inteligencia</div><div style="font-size:11px;color:var(--w-muted);margin-top:4px;line-height:1.7">Proveedores, modelos, políticas, presupuesto, uso y salud de la IA del ecosistema. BRICK decide <strong>qué modelo, dónde y a qué costo</strong> para cada tarea (TIA, previews, agentes…).</div></div>';
    bar+='<div class="w-brick-tabs">';
    tabs.forEach(function(t){
        bar+='<button class="w-brick-tab'+(tab===t[0]?' active':'')+'" onclick="wontia.brickGo(\''+t[0]+'\')">'+t[1]+'</button>';
    });
    bar+='<div style="flex:1"></div><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.brickEnsure()">Setup Tables</button></div>';
    app.innerHTML='<div>'+bar+'<div id="brick-content"></div></div>';
    try{await W.brickLoad()}catch(e){}
    var fns={overview:W.brickOverview,providers:W.brickProviders,models:W.brickModels,policies:W.brickPolicies,systems:W.brickSystems,usage:W.brickUsage,test:W.brickTest};
    (fns[tab]||W.brickOverview)();
};

W.brickGo=function(t){
    if(W.state.brickHost){W.renderBrickInto(W.state.brickHost,true,t);return}
    location.hash='#brick/'+t;
};

W.brickEnsure=async function(){
    W.notify('Ensuring BRICK tables...','info');
    var r=await W.api('/api/v1/admin/brick/ensure-tables',{method:'POST'});
    if(r.ok)W.notify(r.message,'success');
    if(W.state.brickHost)W.renderBrickInto(W.state.brickHost,true,W.state.brick.tab);
    else W.renderBrick(W.state.brick.tab);
};

W.bMoney=function(n){return '$'+Number(n||0).toFixed(4)};

W.bCap=function(list){
    var meta=(W.state.brick.meta&&W.state.brick.meta.data&&W.state.brick.meta.data.capabilities)||{};
    var out='';
    (list||[]).forEach(function(c){out+='<span class="w-brick-chip">'+W.esc(meta[c]||c)+'</span>'});
    return out;
};

W.bStatus=function(s){
    var map={success:['#34d399','SUCCESS'],error:['#f87171','ERROR'],fallback:['#22d3ee','FALLBACK'],budget_blocked:['#fbbf24','BUDGET'],enabled:['#34d399','ENABLED'],draft:['#8593ab','DISABLED'],active:['#34d399','ACTIVE']};
    var c=map[s]||['#8593ab',(s||'')];
    return '<span class="w-badge" style="background:'+c[0]+'22;color:'+c[0]+'">'+c[1]+'</span>';
};

W.bDot=function(s){return '<span class="w-dot w-dot-'+(s||'disabled')+'"></span>'};

W.brickBars=function(rows){
    if(!rows||!rows.length)return '<div class="w-empty-state" style="padding:24px"><p>No data yet</p></div>';
    var max=Math.max.apply(null,rows.map(function(r){return r.cost}))||1;
    var html='';
    rows.forEach(function(r){
        var pct=Math.max(1,Math.round(r.cost/max*100));
        html+='<div style="margin-bottom:10px"><div class="w-flex-between" style="font-size:11px"><span><span class="w-dot" style="background:'+W.esc(r.color||'#22d3ee')+'"></span>'+W.esc(r.label)+'</span><span style="color:var(--w-muted)">'+W.bMoney(r.cost)+' &middot; '+W.num(r.tokens)+' tokens &middot; '+r.requests+' req</span></div><div class="w-brick-bar"><i style="width:'+pct+'%;background:'+W.esc(r.color||'#22d3ee')+'"></i></div></div>';
    });
    return html;
};

W.brickRecentTable=function(rows){
    if(!rows||!rows.length)return '<div class="w-empty-state" style="padding:24px"><p>No requests yet</p></div>';
    var html='<table class="w-table"><tr><th>When</th><th>System</th><th>Provider</th><th>Model</th><th>Tokens</th><th>Cost</th><th>Latency</th><th>Status</th></tr>';
    rows.forEach(function(r){
        html+='<tr><td style="font-size:10px">'+W.esc((r.created_at||'').replace('T',' '))+'</td><td>'+W.esc(r.system_id||'')+'</td><td>'+W.esc(r.provider_name||'-')+'</td><td>'+W.esc(r.model_name||r.model_identifier||'-')+'</td><td>'+W.num(r.total_tokens)+'</td><td>'+W.bMoney(r.estimated_cost)+'</td><td>'+r.latency_ms+' ms</td><td>'+W.bStatus(r.status)+'</td></tr>';
    });
    html+='</table>';
    return html;
};

W.brickSuggHtml=function(list){
    if(!list||!list.length)return '<div class="w-empty-state" style="padding:24px"><p>All systems nominal. No suggestions needed.</p></div>';
    var html='';
    list.forEach(function(s){
        var color=s.severity==='critical'?'#f87171':s.severity==='warning'?'#fbbf24':'#22d3ee';
        html+='<div class="w-brick-sugg" style="border-color:'+color+'"><div class="w-flex-between"><strong style="font-size:12px">'+W.esc(s.title)+'</strong><span style="font-size:9px;color:'+color+';font-weight:700">'+W.esc((s.severity||'').toUpperCase())+'</span></div><div style="font-size:11px;color:var(--w-muted);margin-top:4px">'+W.esc(s.detail)+'</div><button class="w-btn w-btn-secondary w-btn-sm" style="margin-top:8px" onclick="wontia.brickSuggAction(\''+W.esc(s.type)+'\')">'+W.esc(s.action)+'</button></div>';
    });
    return html;
};

W.brickSuggAction=function(type){
        if(type==='budget'||type==='fallback'||type==='cost')location.hash='#bricks/ia/policies';
        else if(type==='error')location.hash='#bricks/ia/test';
        else location.hash='#bricks/ia/models';
};

W.brickOverview=async function(){
    var el=document.getElementById('brick-content');
    el.innerHTML='<div style="text-align:center;padding:40px;color:var(--w-muted)">Loading AI infrastructure...</div>';
    var r=await W.api('/api/v1/admin/brick/overview?range=30d');
    if(!r.ok||!r.data){el.innerHTML='<div class="w-empty-state"><h3>BRICK is not initialized</h3><p>Create the AI infrastructure tables and seed data.</p><button class="w-btn w-btn-primary" onclick="wontia.brickEnsure()">Setup BRICK Tables</button></div>';return}
    var d=r.data,s=d.stats,b=d.budget;
    var budgetColor=b.status==='limit'?'#f87171':b.status==='warning'?'#fbbf24':'#34d399';
    var kpis=[['Active Providers',s.active_providers],['Active Models',s.active_models+' / '+s.total_models],['Requests (30d)',W.num(s.requests_range)],['Tokens (30d)',W.num(s.tokens_range)],['Cost (30d)',W.bMoney(s.cost_range)],['Cost Today',W.bMoney(s.cost_today)],['Error Rate',s.error_rate+'%'],['Avg Latency',s.avg_latency_ms+' ms']];
    var html='<div class="w-stats">';
    kpis.forEach(function(k){html+='<div class="w-stat-card"><div class="w-stat-value">'+k[1]+'</div><div class="w-stat-label">'+W.esc(k[0])+'</div></div>'});
    html+='</div>';
    html+='<div class="w-card" style="padding:16px 20px"><div class="w-flex-between"><div><strong style="font-size:13px">Monthly AI Budget</strong><div style="font-size:11px;color:var(--w-muted);margin-top:2px">'+W.bMoney(b.monthly_spent)+' of '+W.bMoney(b.monthly_budget)+' &middot; '+b.pct_used+'% used &middot; '+s.failover_count+' failovers &middot; '+s.requests_today+' requests today</div></div><span class="w-badge" style="background:'+budgetColor+'22;color:'+budgetColor+'">'+(b.status==='limit'?'LIMIT':b.status==='warning'?'WARNING':'OK')+'</span></div><div class="w-brick-bar" style="margin-top:10px"><i style="width:'+Math.min(100,b.pct_used)+'%;background:'+budgetColor+'"></i></div></div>';
    html+='<div class="w-brick-grid2" style="margin-top:16px"><div class="w-card"><h3>Suggestions</h3>'+W.brickSuggHtml(d.suggestions)+'</div><div class="w-card"><h3>Cost by Provider (30d)</h3>'+W.brickBars(d.cost_by_provider)+'</div></div>';
    html+='<div class="w-brick-grid2" style="margin-top:16px"><div class="w-card"><h3>Cost by Model (30d)</h3>'+W.brickBars(d.cost_by_model)+'</div><div class="w-card"><h3>System → Model → Requests → Cost</h3>'+W.brickSystemTable(d.usage_by_system)+'</div></div>';
    html+='<div class="w-brick-grid2" style="margin-top:16px"><div class="w-card"><h3>Model Health</h3>'+W.brickHealthHtml(d.health)+'</div><div class="w-card"><h3>Recent Requests</h3>'+W.brickRecentTable(d.recent)+'</div></div>';
    el.innerHTML=html;
};

W.brickSystemTable=function(rows){
    if(!rows||!rows.length)return '<div class="w-empty-state" style="padding:24px"><p>No data yet</p></div>';
    var html='<table class="w-table"><tr><th>System</th><th>Requests</th><th>Tokens</th><th>Cost</th><th>Avg Latency</th><th>Errors</th></tr>';
    rows.forEach(function(r){html+='<tr><td>'+W.esc(r.label)+'</td><td>'+W.num(r.requests)+'</td><td>'+W.num(r.tokens)+'</td><td>'+W.bMoney(r.cost)+'</td><td>'+Math.round(r.avg_latency)+' ms</td><td>'+r.errors+'</td></tr>'});
    html+='</table>';
    return html;
};

W.brickHealthHtml=function(rows){
    if(!rows||!rows.length)return '<div class="w-empty-state" style="padding:24px"><p>No health data yet. Run a test or send a request.</p></div>';
    var html='';
    rows.forEach(function(h){
        html+='<div class="w-flex-between" style="padding:6px 0;border-bottom:1px solid var(--w-border);font-size:11px"><span>'+W.bDot(h.status)+W.esc(h.model_name||'')+' <span style="color:var(--w-muted)">('+W.esc(h.provider_name||'')+')</span></span><span style="color:var(--w-muted)">'+h.latency_ms+' ms &middot; '+h.success_count+' ok / '+h.error_count+' err</span></div>';
    });
    return html;
};

W.brickProviders=function(){
    var el=document.getElementById('brick-content');
    var rows=W.state.brick.providers;
    var html='<div class="w-flex-between w-mb"><div style="font-size:12px;color:var(--w-muted)">'+rows.length+' providers &middot; API keys live in environment variables, never in the database</div><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.brickProviderForm()">+ New Provider</button></div>';
    if(!rows.length){el.innerHTML=html+'<div class="w-empty-state"><p>No providers</p></div>';return}
    html+='<table class="w-table"><tr><th>Provider</th><th>Adapter</th><th>Key</th><th>Models</th><th>Status</th><th>Actions</th></tr>';
    rows.forEach(function(p){
        html+='<tr><td><span class="w-brick-chip" style="background:'+W.esc(p.color)+';color:#fff">'+W.esc(p.badge||'AI')+'</span> <strong>'+W.esc(p.name)+'</strong> <span style="font-size:10px;color:var(--w-muted)">'+W.esc(p.slug)+'</span></td><td style="font-size:10px">'+W.esc(p.adapter)+'</td><td>'+(p.has_key?'<span style="color:#34d399">Configured</span>':'<span style="color:#fbbf24">Missing</span>')+'</td><td>'+p.enabled_models+'</td><td>'+W.bStatus(p.status)+'</td><td><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.brickProviderForm('+p.id+')">Edit</button> <button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.brickToggleProvider('+p.id+')">Toggle</button> <button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.brickDeleteProvider('+p.id+')">Del</button></td></tr>';
    });
    html+='</table>';
    html+='<div style="font-size:11px;color:var(--w-muted);margin-top:12px">Set credentials with the referenced env variable (e.g. BRICK_OPENAI_API_KEY) in .env or the container environment. DeepSeek falls back to DEEPSEEK_API_KEY.</div>';
    el.innerHTML=html;
};

W.brickProviderForm=function(id){
    var p=null;
    if(id)W.state.brick.providers.forEach(function(x){if(x.id===id)p=x});
    var meta=(W.state.brick.meta&&W.state.brick.meta.data)||{};
    var body='<div class="w-form-group"><label class="w-label">Name</label><input class="w-input" id="bp-name" value="'+W.esc(p?p.name:'')+'"/></div>';
    body+='<div class="w-form-group"><label class="w-label">Slug</label><input class="w-input" id="bp-slug" value="'+W.esc(p?p.slug:'')+'"/></div>';
    body+='<div class="w-form-group"><label class="w-label">Description</label><input class="w-input" id="bp-desc" value="'+W.esc(p?p.description:'')+'"/></div>';
    body+='<div class="w-form-group"><label class="w-label">Adapter</label><select class="w-select" id="bp-adapter">';
    for(var a in (meta.adapters||{}))body+='<option value="'+W.esc(a)+'"'+((p&&p.adapter===a)?' selected':'')+'>'+W.esc(meta.adapters[a])+'</option>';
    body+='</select></div>';
    body+='<div class="w-form-group"><label class="w-label">API Base URL</label><input class="w-input" id="bp-base" value="'+W.esc(p?p.api_base_url:'')+'"/></div>';
    body+='<div class="w-form-group"><label class="w-label">Auth Method</label><select class="w-select" id="bp-auth"><option value="bearer"'+(p&&p.auth_method==='bearer'?' selected':'')+'>Bearer Token</option><option value="api-key"'+(p&&p.auth_method==='api-key'?' selected':'')+'>API Key Header</option><option value="azure"'+(p&&p.auth_method==='azure'?' selected':'')+'>Azure</option></select></div>';
    body+='<div class="w-form-group"><label class="w-label">API Key Env Var (reference only — never the key itself)</label><input class="w-input" id="bp-env" value="'+W.esc(p?p.api_key_env:'')+'" placeholder="BRICK_PROVIDER_API_KEY"/></div>';
    body+='<div class="w-flex" style="gap:12px"><div class="w-form-group" style="flex:1"><label class="w-label">Badge (2 letters)</label><input class="w-input" id="bp-badge" value="'+W.esc(p?p.badge:'AI')+'"/></div><div class="w-form-group" style="flex:1"><label class="w-label">Color</label><input class="w-input" id="bp-color" value="'+W.esc(p?p.color:'#8b5cf6')+'"/></div></div>';
    body+='<div class="w-form-group"><label class="w-label">Status</label><select class="w-select" id="bp-status"><option value="enabled"'+(p&&p.status==='enabled'?' selected':'')+'>Enabled</option><option value="disabled"'+(p&&p.status!=='enabled'?' selected':'')+'>Disabled</option></select></div>';
    W.modal(p?'Edit Provider':'New Provider',body,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancel</button><button class="w-btn w-btn-primary" onclick="wontia.brickSaveProvider('+(p?p.id:'null')+')">Save</button>');
};

W.brickSaveProvider=async function(id){
    var payload={name:W.val('bp-name'),slug:W.val('bp-slug'),description:W.val('bp-desc'),adapter:W.val('bp-adapter'),api_base_url:W.val('bp-base'),auth_method:W.val('bp-auth'),api_key_env:W.val('bp-env'),badge:W.val('bp-badge'),color:W.val('bp-color'),status:W.val('bp-status')};
    var r=id?await W.api('/api/v1/admin/brick/providers/'+id,{method:'PUT',body:payload}):await W.api('/api/v1/admin/brick/providers',{method:'POST',body:payload});
    if(r.ok){W.closeModal();W.notify(r.message,'success');W.renderBrick('providers')}
};

W.brickToggleProvider=async function(id){
    var p=null;W.state.brick.providers.forEach(function(x){if(x.id===id)p=x});
    if(!p)return;
    var payload={name:p.name,slug:p.slug,description:p.description,adapter:p.adapter,api_base_url:p.api_base_url,auth_method:p.auth_method,api_key_env:p.api_key_env,badge:p.badge,color:p.color,docs_url:p.docs_url,status:p.status==='enabled'?'disabled':'enabled',sort_order:p.sort_order};
    var r=await W.api('/api/v1/admin/brick/providers/'+id,{method:'PUT',body:payload});
    if(r.ok)W.renderBrick('providers');
};

W.brickDeleteProvider=function(id){
    W.confirm('Delete this provider and all its models?',async function(){
        var r=await W.api('/api/v1/admin/brick/providers/'+id,{method:'DELETE'});
        if(r.ok)W.renderBrick('providers');
    });
};

W.brickModels=function(){
    var el=document.getElementById('brick-content');
    var rows=W.state.brick.models;
    var provs=W.state.brick.providers;
    var html='<div class="w-flex-between w-mb"><div class="w-toolbar"><select class="w-select" style="width:180px" id="bm-filter" onchange="wontia.brickModelsFilter(this.value)"><option value="">All providers</option>';
    provs.forEach(function(p){html+='<option value="'+p.id+'">'+W.esc(p.name)+'</option>'});
    html+='</select></div><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.brickModelForm()">+ New Model</button></div>';
    html+='<table class="w-table"><tr><th>Model</th><th>Identifier</th><th>Context</th><th>Input $/M</th><th>Output $/M</th><th>Capabilities</th><th>Priority</th><th>Enabled</th><th>Actions</th></tr>';
    rows.forEach(function(m){
        if(W.state.brick.modelFilter&&m.provider_id!==W.state.brick.modelFilter)return;
        html+='<tr><td><span class="w-brick-chip" style="background:'+W.esc(m.color)+';color:#fff">'+W.esc(m.badge||'AI')+'</span> '+W.esc(m.display_name||m.name)+'</td><td style="font-size:10px">'+W.esc(m.model_identifier)+'</td><td>'+W.num(m.context_window)+'</td><td>'+m.input_cost+'</td><td>'+m.output_cost+'</td><td style="max-width:260px">'+W.bCap(m.capabilities)+'</td><td>'+m.priority+'</td><td>'+(m.enabled===1||m.enabled==='1'?'<span style="color:#34d399">ON</span>':'<span style="color:var(--w-muted)">OFF</span>')+'</td><td><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.brickModelForm('+m.id+')">Edit</button> <button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.brickToggleModel('+m.id+')">Toggle</button> <button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.brickDeleteModel('+m.id+')">Del</button></td></tr>';
    });
    html+='</table>';
    html+='<div style="font-size:11px;color:var(--w-muted);margin-top:12px">Costs are USD per 1M tokens. Only enabled models with an enabled provider participate in routing.</div>';
    el.innerHTML=html;
};

W.brickModelsFilter=function(v){W.state.brick.modelFilter=v?parseInt(v):null;W.brickModels()};

W.brickModelPayload=function(m){
    return {provider_id:m.provider_id,name:m.name,display_name:m.display_name,version:m.version,model_identifier:m.model_identifier,description:m.description,context_window:m.context_window,max_output_tokens:m.max_output_tokens,input_cost:m.input_cost,cached_input_cost:m.cached_input_cost,output_cost:m.output_cost,capabilities:m.capabilities||[],priority:m.priority,status:m.status,enabled:m.enabled?1:0};
};

W.brickToggleModel=async function(id){
    var m=null;W.state.brick.models.forEach(function(x){if(x.id===id)m=x});
    if(!m)return;
    var payload=W.brickModelPayload(m);
    payload.enabled=(m.enabled===1||m.enabled==='1')?0:1;
    var r=await W.api('/api/v1/admin/brick/models/'+id,{method:'PUT',body:payload});
    if(r.ok)W.renderBrick('models');
};

W.brickDeleteModel=function(id){
    W.confirm('Delete this model?',async function(){
        var r=await W.api('/api/v1/admin/brick/models/'+id,{method:'DELETE'});
        if(r.ok)W.renderBrick('models');
    });
};

W.brickModelForm=function(id){
    var m=null;
    if(id)W.state.brick.models.forEach(function(x){if(x.id===id)m=x});
    var caps=(W.state.brick.meta&&W.state.brick.meta.data&&W.state.brick.meta.data.capabilities)||{};
    var body='<div class="w-form-group"><label class="w-label">Provider</label><select class="w-select" id="bmf-provider">';
    W.state.brick.providers.forEach(function(p){body+='<option value="'+p.id+'"'+((m&&m.provider_id===p.id)?' selected':'')+'>'+W.esc(p.name)+'</option>'});
    body+='</select></div>';
    body+='<div class="w-form-group"><label class="w-label">Name</label><input class="w-input" id="bmf-name" value="'+W.esc(m?m.name:'')+'"/></div>';
    body+='<div class="w-form-group"><label class="w-label">Display Name</label><input class="w-input" id="bmf-display" value="'+W.esc(m?m.display_name:'')+'"/></div>';
    body+='<div class="w-form-group"><label class="w-label">Model Identifier (API)</label><input class="w-input" id="bmf-ident" value="'+W.esc(m?m.model_identifier:'')+'" placeholder="e.g. gpt-4o-mini"/></div>';
    body+='<div class="w-flex" style="gap:12px"><div class="w-form-group" style="flex:1"><label class="w-label">Version</label><input class="w-input" id="bmf-version" value="'+W.esc(m?m.version:'1.0')+'"/></div><div class="w-form-group" style="flex:1"><label class="w-label">Priority</label><input class="w-input" id="bmf-priority" value="'+(m?m.priority:0)+'" type="number"/></div></div>';
    body+='<div class="w-form-group"><label class="w-label">Description</label><input class="w-input" id="bmf-desc" value="'+W.esc(m?m.description:'')+'"/></div>';
    body+='<div class="w-flex" style="gap:12px"><div class="w-form-group" style="flex:1"><label class="w-label">Context Window</label><input class="w-input" id="bmf-ctx" value="'+(m?m.context_window:0)+'" type="number"/></div><div class="w-form-group" style="flex:1"><label class="w-label">Max Output Tokens</label><input class="w-input" id="bmf-maxtok" value="'+(m?m.max_output_tokens:4096)+'" type="number"/></div></div>';
    body+='<div class="w-flex" style="gap:12px"><div class="w-form-group" style="flex:1"><label class="w-label">Input $/1M</label><input class="w-input" id="bmf-in" value="'+(m?m.input_cost:0)+'" type="number" step="0.0001"/></div><div class="w-form-group" style="flex:1"><label class="w-label">Cached In $/1M</label><input class="w-input" id="bmf-cin" value="'+(m?m.cached_input_cost:0)+'" type="number" step="0.0001"/></div><div class="w-form-group" style="flex:1"><label class="w-label">Output $/1M</label><input class="w-input" id="bmf-out" value="'+(m?m.output_cost:0)+'" type="number" step="0.0001"/></div></div>';
    body+='<div class="w-form-group"><label class="w-label">Capabilities</label><div style="display:flex;flex-wrap:wrap;gap:4px">';
    var sel=m?(m.capabilities||[]):[];
    for(var c in caps){
        var on=sel.indexOf(c)>-1;
        body+='<label style="font-size:11px;padding:4px 10px;border-radius:6px;border:1px solid '+(on?'#22d3ee':'var(--w-border)')+';cursor:pointer;background:'+(on?'rgba(155,140,222,.12)':'transparent')+'"><input type="checkbox" class="bmf-cap" value="'+W.esc(c)+'" '+(on?'checked':'')+' style="margin-right:4px;width:auto"/>'+W.esc(caps[c])+'</label>';
    }
    body+='</div></div>';
    body+='<div class="w-form-group"><label class="w-label">Status</label><select class="w-select" id="bmf-status"><option value="active"'+(m&&m.status==='active'?' selected':'')+'>Active</option><option value="deprecated"'+(m&&m.status==='deprecated'?' selected':'')+'>Deprecated</option><option value="retired"'+(m&&m.status==='retired'?' selected':'')+'>Retired</option></select></div>';
    body+='<label style="font-size:12px;display:flex;align-items:center;gap:8px"><input type="checkbox" id="bmf-enabled" '+((m?(m.enabled===1||m.enabled==='1'):true)?'checked':'')+' style="width:auto"/> Enabled</label>';
    W.modal(m?'Edit Model':'New Model',body,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancel</button><button class="w-btn w-btn-primary" onclick="wontia.brickSaveModel('+(m?m.id:'null')+')">Save</button>');
};

W.brickSaveModel=async function(id){
    var caps=[];
    document.querySelectorAll('#w-modal-content .bmf-cap:checked').forEach(function(c){caps.push(c.value)});
    var payload={provider_id:parseInt(W.val('bmf-provider')),name:W.val('bmf-name'),display_name:W.val('bmf-display'),version:W.val('bmf-version'),model_identifier:W.val('bmf-ident'),description:W.val('bmf-desc'),context_window:parseInt(W.val('bmf-ctx'))||0,max_output_tokens:parseInt(W.val('bmf-maxtok'))||4096,input_cost:parseFloat(W.val('bmf-in'))||0,cached_input_cost:parseFloat(W.val('bmf-cin'))||0,output_cost:parseFloat(W.val('bmf-out'))||0,capabilities:caps,priority:parseInt(W.val('bmf-priority'))||0,status:W.val('bmf-status'),enabled:document.getElementById('bmf-enabled').checked?1:0};
    var r=id?await W.api('/api/v1/admin/brick/models/'+id,{method:'PUT',body:payload}):await W.api('/api/v1/admin/brick/models',{method:'POST',body:payload});
    if(r.ok){W.closeModal();W.notify(r.message,'success');W.renderBrick('models')}
};

W.brickPolicies=function(){
    var el=document.getElementById('brick-content');
    var rows=W.state.brick.policies;
    var html='<div class="w-flex-between w-mb"><div style="font-size:12px;color:var(--w-muted)">'+rows.length+' policies &middot; system → module → function → model chain with failover</div><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.brickPolicyForm()">+ New Policy</button></div>';
    if(!rows.length){el.innerHTML=html+'<div class="w-empty-state"><p>No policies</p></div>';return}
    html+='<table class="w-table"><tr><th>System / Module / Function</th><th>Strategy</th><th>Primary → Fallback</th><th>Capabilities</th><th>Budget</th><th>Active</th><th>Actions</th></tr>';
    rows.forEach(function(p){
        html+='<tr><td><strong>'+W.esc(p.system_id)+'</strong><span style="color:var(--w-muted)"> / '+W.esc(p.module)+' / '+W.esc(p.function_key)+'</span></td><td><span class="w-brick-chip">'+W.esc(p.strategy)+'</span></td><td style="font-size:10px">'+W.esc(p.primary_name||'-')+' → '+W.esc(p.fallback_name||'-')+(p.fallback2_name?' → '+W.esc(p.fallback2_name):'')+'</td><td>'+W.bCap(p.required_capabilities)+'</td><td>$'+p.monthly_budget+'</td><td>'+(p.is_active===1||p.is_active==='1'?'<span style="color:#34d399">ON</span>':'<span style="color:var(--w-muted)">OFF</span>')+'</td><td><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.brickPolicyForm('+p.id+')">Edit</button> <button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.brickDeletePolicy('+p.id+')">Del</button></td></tr>';
    });
    html+='</table>';
    el.innerHTML=html;
};

W.brickDeletePolicy=function(id){
    W.confirm('Delete this policy?',async function(){
        var r=await W.api('/api/v1/admin/brick/policies/'+id,{method:'DELETE'});
        if(r.ok)W.renderBrick('policies');
    });
};

W.brickPolicyForm=function(id){
    var p=null;
    if(id)W.state.brick.policies.forEach(function(x){if(x.id===id)p=x});
    var models=W.state.brick.models;
    var caps=(W.state.brick.meta&&W.state.brick.meta.data&&W.state.brick.meta.data.capabilities)||{};
    var strat=(W.state.brick.meta&&W.state.brick.meta.data&&W.state.brick.meta.data.strategies)||{};
    var msel=function(val){
        var s='<option value="">— none —</option>';
        models.forEach(function(m){s+='<option value="'+m.id+'"'+((val&&val===m.id)?' selected':'')+'>'+W.esc(m.provider_name+' — '+(m.display_name||m.name))+'</option>'});
        return s;
    };
    var body='<div class="w-form-group"><label class="w-label">System</label><input class="w-input" id="bpf-system" list="bpf-systems" value="'+W.esc(p?p.system_id:'wontia')+'"/><datalist id="bpf-systems">';
    W.state.brick.instances.forEach(function(i){body+='<option value="'+W.esc(i.system_id)+'">'+W.esc(i.name)+'</option>'});
    body+='</datalist></div>';
    body+='<div class="w-flex" style="gap:12px"><div class="w-form-group" style="flex:1"><label class="w-label">Module</label><input class="w-input" id="bpf-module" value="'+W.esc(p?p.module:'general')+'"/></div><div class="w-form-group" style="flex:1"><label class="w-label">Function</label><input class="w-input" id="bpf-function" value="'+W.esc(p?p.function_key:'default')+'"/></div></div>';
    body+='<div class="w-form-group"><label class="w-label">Strategy</label><select class="w-select" id="bpf-strategy">';
    for(var s2 in strat)body+='<option value="'+W.esc(s2)+'"'+((p&&p.strategy===s2)?' selected':'')+'>'+W.esc(strat[s2])+'</option>';
    body+='</select></div>';
    body+='<div class="w-form-group"><label class="w-label">Primary Model</label><select class="w-select" id="bpf-primary">'+msel(p?p.primary_model_id:null)+'</select></div>';
    body+='<div class="w-flex" style="gap:12px"><div class="w-form-group" style="flex:1"><label class="w-label">Fallback 1</label><select class="w-select" id="bpf-fallback">'+msel(p?p.fallback_model_id:null)+'</select></div><div class="w-form-group" style="flex:1"><label class="w-label">Fallback 2</label><select class="w-select" id="bpf-fallback2">'+msel(p?p.fallback2_model_id:null)+'</select></div></div>';
    body+='<div class="w-form-group"><label class="w-label">Required Capabilities</label><div style="display:flex;flex-wrap:wrap;gap:4px">';
    var sel=p?(p.required_capabilities||[]):[];
    for(var c in caps){
        var on=sel.indexOf(c)>-1;
        body+='<label style="font-size:11px;padding:4px 10px;border-radius:6px;border:1px solid '+(on?'#22d3ee':'var(--w-border)')+';cursor:pointer;background:'+(on?'rgba(155,140,222,.12)':'transparent')+'"><input type="checkbox" class="bpf-cap" value="'+W.esc(c)+'" '+(on?'checked':'')+' style="margin-right:4px;width:auto"/>'+W.esc(caps[c])+'</label>';
    }
    body+='</div></div>';
    body+='<div class="w-flex" style="gap:12px"><div class="w-form-group" style="flex:1"><label class="w-label">Monthly Budget $</label><input class="w-input" id="bpf-budget" value="'+(p?p.monthly_budget:0)+'" type="number" step="1"/></div><div class="w-form-group" style="flex:1"><label class="w-label">Warning %</label><input class="w-input" id="bpf-warn" value="'+(p?p.budget_warning_pct:80)+'" type="number"/></div><div class="w-form-group" style="flex:1"><label class="w-label">Hard Limit %</label><input class="w-input" id="bpf-hard" value="'+(p?p.budget_hard_limit_pct:100)+'" type="number"/></div></div>';
    body+='<label style="font-size:12px;display:flex;align-items:center;gap:8px"><input type="checkbox" id="bpf-fbenabled" '+((p?(p.fallback_enabled===1||p.fallback_enabled==='1'):true)?'checked':'')+' style="width:auto"/> Fallback enabled</label>';
    body+='<label style="font-size:12px;display:flex;align-items:center;gap:8px;margin-top:8px"><input type="checkbox" id="bpf-active" '+((p?(p.is_active===1||p.is_active==='1'):true)?'checked':'')+' style="width:auto"/> Active</label>';
    W.modal(p?'Edit Policy':'New Policy',body,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancel</button><button class="w-btn w-btn-primary" onclick="wontia.brickSavePolicy('+(p?p.id:'null')+')">Save</button>');
};

W.brickSavePolicy=async function(id){
    var caps=[];
    document.querySelectorAll('#w-modal-content .bpf-cap:checked').forEach(function(c){caps.push(c.value)});
    var payload={system_id:W.val('bpf-system'),module:W.val('bpf-module'),function:W.val('bpf-function'),strategy:W.val('bpf-strategy'),primary_model_id:parseInt(W.val('bpf-primary'))||0,fallback_model_id:parseInt(W.val('bpf-fallback'))||0,fallback2_model_id:parseInt(W.val('bpf-fallback2'))||0,required_capabilities:caps,preferred_providers:[],excluded_providers:[],max_cost_per_request:0,fallback_enabled:document.getElementById('bpf-fbenabled').checked?1:0,monthly_budget:parseFloat(W.val('bpf-budget'))||0,budget_warning_pct:parseInt(W.val('bpf-warn'))||80,budget_hard_limit_pct:parseInt(W.val('bpf-hard'))||100,is_active:document.getElementById('bpf-active').checked?1:0};
    var r=id?await W.api('/api/v1/admin/brick/policies/'+id,{method:'PUT',body:payload}):await W.api('/api/v1/admin/brick/policies',{method:'POST',body:payload});
    if(r.ok){W.closeModal();W.notify(r.message,'success');W.renderBrick('policies')}
};

W.brickSystems=function(){
    var el=document.getElementById('brick-content');
    var rows=W.state.brick.instances;
    var html='<div class="w-flex-between w-mb"><div style="font-size:12px;color:var(--w-muted)">BRICK instances across the WONTIA ecosystem. Each system configures its own policies (module → function → model chain).</div></div>';
    if(!rows.length){el.innerHTML=html+'<div class="w-empty-state"><p>No systems registered</p></div>';return}
    html+='<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px">';
    rows.forEach(function(i){
        html+='<div class="w-card" style="padding:16px"><div class="w-flex-between"><strong style="font-size:13px">'+W.esc(i.name)+'</strong><span class="w-brick-chip">'+W.esc(i.system_id)+'</span></div><div style="font-size:11px;color:var(--w-muted);margin-top:6px">'+W.esc(i.description||'')+'</div><div style="font-size:11px;margin-top:10px;color:#22d3ee">'+i.policy_count+' policies</div></div>';
    });
    html+='</div>';
    html+='<div style="font-size:11px;color:var(--w-muted);margin-top:14px">Reusable across WONTIA, TIA System, IA Annotation, Websites, Landing Pages, Agents, Automations and future products. Ecosystem apps call <code>POST /api/v1/brick/request</code> with header <code>X-Brick-Key</code> (env BRICK_API_KEY); TIA controls BRICK via <code>POST /api/v1/brick/command</code>. Health: <code>GET /api/v1/brick/health</code>.</div>';
    el.innerHTML=html;
};

W.brickUsage=async function(){
    var el=document.getElementById('brick-content');
    el.innerHTML='<div style="text-align:center;padding:40px;color:var(--w-muted)">Loading usage...</div>';
    var range=W.state.brick.range||'30d';
    var group=W.state.brick.group||'model';
    var r=await W.api('/api/v1/admin/brick/usage?range='+range+'&group='+group);
    var d=r.data||{rows:[],recent:[]};
    var ranges=[['today','Today'],['7d','7 Days'],['30d','30 Days'],['all','All Time']];
    var groups=[['model','By Model'],['provider','By Provider'],['system','By System'],['function','By Function']];
    var html='<div class="w-toolbar w-mb">';
    ranges.forEach(function(x){html+='<button class="w-btn '+(range===x[0]?'w-btn-primary':'w-btn-secondary')+'" onclick="wontia.brickRange(\''+x[0]+'\')">'+x[1]+'</button>'});
    html+='<span style="flex:1"></span><select class="w-select" style="width:150px" onchange="wontia.brickGroup(this.value)">';
    groups.forEach(function(g){html+='<option value="'+g[0]+'"'+(group===g[0]?' selected':'')+'>'+g[1]+'</option>'});
    html+='</select></div>';
    html+='<div class="w-card"><h3>Cost & Consumption — '+W.esc(range)+'</h3>'+W.brickBars(d.rows)+'</div>';
    html+='<div class="w-card"><h3>Aggregated</h3><table class="w-table"><tr><th>Label</th><th>Requests</th><th>Tokens</th><th>Cost</th><th>Avg Latency</th><th>Errors</th></tr>';
    (d.rows||[]).forEach(function(x){html+='<tr><td>'+W.esc(x.label)+'</td><td>'+W.num(x.requests)+'</td><td>'+W.num(x.tokens)+'</td><td>'+W.bMoney(x.cost)+'</td><td>'+Math.round(x.avg_latency)+' ms</td><td>'+x.errors+'</td></tr>'});
    html+='</table></div>';
    html+='<div class="w-card"><h3>Recent Requests</h3>'+W.brickRecentTable(d.recent)+'</div>';
    el.innerHTML=html;
};

W.brickRange=function(r){W.state.brick.range=r;W.brickUsage()};
W.brickGroup=function(g){W.state.brick.group=g;W.brickUsage()};

W.brickTest=function(){
    var el=document.getElementById('brick-content');
    var models=W.state.brick.models.filter(function(m){return (m.enabled===1||m.enabled==='1')&&m.provider_name});
    var body='<div class="w-card"><h3>Test Model</h3>';
    body+='<div class="w-form-group"><label class="w-label">Model</label><select class="w-select" id="bt-model">';
    models.forEach(function(m){body+='<option value="'+m.id+'">'+W.esc(m.provider_name+' — '+(m.display_name||m.name))+'</option>'});
    body+='</select></div>';
    body+='<div class="w-form-group"><label class="w-label">System Prompt</label><textarea class="w-textarea" id="bt-system" style="min-height:60px"></textarea></div>';
    body+='<div class="w-form-group"><label class="w-label">Prompt</label><textarea class="w-textarea" id="bt-prompt" style="min-height:100px" placeholder="Type a test prompt..."></textarea></div>';
    body+='<div class="w-flex" style="gap:12px"><div class="w-form-group" style="flex:1"><label class="w-label">Temperature</label><input class="w-input" id="bt-temp" value="0.7" type="number" step="0.1"/></div><div class="w-form-group" style="flex:1"><label class="w-label">Max Tokens</label><input class="w-input" id="bt-tokens" value="500" type="number"/></div><div class="w-form-group" style="flex:1"><label class="w-label">System</label><input class="w-input" id="bt-sys" value="wontia"/></div></div>';
    body+='<button class="w-btn w-btn-primary" onclick="wontia.brickRunTest()">Run Test</button>';
    body+='<div id="bt-result" style="margin-top:16px"></div>';
    body+='</div>';
    el.innerHTML=body;
};

W.brickRunTest=async function(){
    var res=document.getElementById('bt-result');
    res.innerHTML='<div style="padding:20px;color:var(--w-muted);text-align:center">Running...</div>';
    var payload={model_id:parseInt(W.val('bt-model')),system_prompt:W.val('bt-system'),prompt:W.val('bt-prompt'),temperature:parseFloat(W.val('bt-temp'))||0.7,max_tokens:parseInt(W.val('bt-tokens'))||500,system_id:W.val('bt-sys')||'wontia'};
    var r=await W.api('/api/v1/admin/brick/test',{method:'POST',body:payload});
    var d=r.data||{};
    if(!d.ok){res.innerHTML='<div class="w-card" style="border-color:var(--w-accent)"><strong style="color:var(--w-accent)">Error</strong><p style="font-size:12px;margin-top:6px">'+W.esc(d.error||r.message||'Request failed')+'</p></div>';return}
    var u=d.usage||{};
    var html='<div class="w-card" style="border-color:rgba(0,184,125,.4)">';
    html+='<div class="w-flex" style="flex-wrap:wrap;gap:8px"><span class="w-brick-chip">'+W.esc(d.provider||'')+'</span><span class="w-brick-chip">'+W.esc(d.model||'')+'</span><span class="w-brick-chip">'+d.latency_ms+' ms</span><span class="w-brick-chip">'+W.num(u.input_tokens)+' in</span><span class="w-brick-chip">'+W.num(u.output_tokens)+' out</span><span class="w-brick-chip">'+W.bMoney(d.cost)+'</span></div>';
    html+='<pre style="margin-top:14px;font-size:12px;line-height:1.7;white-space:pre-wrap;font-family:inherit">'+W.esc(d.content||'')+'</pre></div>';
    res.innerHTML=html;
};

W.val=function(id){var e=document.getElementById(id);return e?e.value:''};

W.state.factory={tab:'plans',plans:[],config:{}};

W.factoryGo=function(t){location.hash='#wwi/'+t};

W.factoryEnsure=async function(){
    W.notify('Ensuring factory tables...','info');
    var r=await W.api('/api/v1/admin/factory/ensure-tables',{method:'POST'});
    if(r.ok)W.notify(r.message,'success');
    W.renderWWI(W.state.factory.tab||'inicio');
};

W.factoryPlans=function(){
    var el=document.getElementById('factory-content');
    var rows=W.state.factory.plans;
    var html='<div class="w-flex-between w-mb"><div style="font-size:12px;color:var(--w-muted)">Plans & pricing engine — nothing hardcoded. Bilingual ES/EN.</div><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.factoryPlanForm()">+ New Plan</button></div>';
    if(!rows.length){el.innerHTML=html+'<div class="w-empty-state"><p>No plans. Run Setup Tables or create one.</p></div>';return}
    html+='<table class="w-table"><tr><th>Plan</th><th>Price COP</th><th>Price USD</th><th>Billing</th><th>Margin</th><th>Active</th><th>Actions</th></tr>';
    rows.forEach(function(p){
        var m=p.margin||{};
        var mc=m.status==='critical'?'#f87171':m.status==='warning'?'#fbbf24':'#34d399';
        html+='<tr><td><strong>'+W.esc(p.name_es)+'</strong> <span style="color:var(--w-muted)">/ '+W.esc(p.name_en)+'</span><div style="font-size:10px;color:var(--w-muted)">'+W.esc(p.slug)+'</div></td><td>$'+W.num(p.price_cop)+'</td><td>$'+p.price_usd+'</td><td><span class="w-brick-chip">'+W.esc(p.billing_type)+'</span></td><td style="color:'+mc+'">'+(m.margin_pct!=null?m.margin_pct+'%':'—')+'</td><td>'+(p.is_active===1?'<span style="color:#34d399">ON</span>':'<span style="color:var(--w-muted)">OFF</span>')+'</td><td><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.factoryPlanForm('+p.id+')">Edit</button> <button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.factoryDeletePlan('+p.id+')">Del</button></td></tr>';
    });
    html+='</table>';
    html+='<div style="font-size:11px;color:var(--w-muted);margin-top:12px">Margin is computed live: price − internal costs (USD items × wwi.usd_cop_rate + % items) vs min_margin_pct.</div>';
    el.innerHTML=html;
};

W.factoryDeletePlan=function(id){
    W.confirm('Delete this plan?',async function(){
        var r=await W.api('/api/v1/admin/factory/plans/'+id,{method:'DELETE'});
        if(r.ok)W.renderWWI('planes');
    });
};

W.factoryPlanForm=function(id){
    var p=null;
    if(id)W.state.factory.plans.forEach(function(x){if(x.id===id)p=x});
    var body='<div class="w-flex" style="gap:12px"><div class="w-form-group" style="flex:1"><label class="w-label">Slug</label><input class="w-input" id="fp-slug" value="'+W.esc(p?p.slug:'')+'"/></div><div class="w-form-group" style="flex:1"><label class="w-label">Billing</label><select class="w-select" id="fp-billing"><option value="one_time"'+(p&&p.billing_type==='one_time'?' selected':'')+'>One time</option><option value="monthly"'+(p&&p.billing_type==='monthly'?' selected':'')+'>Monthly</option><option value="annual"'+(p&&p.billing_type==='annual'?' selected':'')+'>Annual</option></select></div></div>';
    body+='<div class="w-flex" style="gap:12px"><div class="w-form-group" style="flex:1"><label class="w-label">Name ES</label><input class="w-input" id="fp-nes" value="'+W.esc(p?p.name_es:'')+'"/></div><div class="w-form-group" style="flex:1"><label class="w-label">Name EN</label><input class="w-input" id="fp-nen" value="'+W.esc(p?p.name_en:'')+'"/></div></div>';
    body+='<div class="w-flex" style="gap:12px"><div class="w-form-group" style="flex:1"><label class="w-label">Price COP</label><input class="w-input" id="fp-cop" type="number" value="'+(p?p.price_cop:0)+'"/></div><div class="w-form-group" style="flex:1"><label class="w-label">Price USD</label><input class="w-input" id="fp-usd" type="number" step="0.01" value="'+(p?p.price_usd:0)+'"/></div><div class="w-form-group" style="flex:1"><label class="w-label">Duration (months)</label><input class="w-input" id="fp-dur" type="number" value="'+(p?p.duration_months:12)+'"/></div></div>';
    body+='<div class="w-form-group"><label class="w-label">Description ES</label><input class="w-input" id="fp-des" value="'+W.esc(p?p.description_es:'')+'"/></div>';
    body+='<div class="w-form-group"><label class="w-label">Description EN</label><input class="w-input" id="fp-den" value="'+W.esc(p?p.description_en:'')+'"/></div>';
    body+='<div class="w-form-group"><label class="w-label">Features (JSON array)</label><textarea class="w-textarea" id="fp-feat" style="min-height:70px;font-family:monospace;font-size:11px">'+W.esc(JSON.stringify(p?p.features:[]))+'</textarea></div>';
    body+='<div class="w-form-group"><label class="w-label">Limits (JSON object)</label><textarea class="w-textarea" id="fp-lim" style="min-height:70px;font-family:monospace;font-size:11px">'+W.esc(JSON.stringify(p?p.limits:{}))+'</textarea></div>';
    body+='<div class="w-form-group"><label class="w-label">Margin cost items (JSON: usd or pct)</label><textarea class="w-textarea" id="fp-costs" style="min-height:90px;font-family:monospace;font-size:11px">'+W.esc(JSON.stringify(p?p.margin_cost_items:[]))+'</textarea></div>';
    body+='<div class="w-flex" style="gap:12px"><div class="w-form-group" style="flex:1"><label class="w-label">Min margin %</label><input class="w-input" id="fp-minm" type="number" value="'+(p?p.min_margin_pct:25)+'"/></div><div class="w-form-group" style="flex:1"><label class="w-label">Sort</label><input class="w-input" id="fp-sort" type="number" value="'+(p?p.sort_order:0)+'"/></div></div>';
    body+='<label style="font-size:12px;display:flex;align-items:center;gap:8px"><input type="checkbox" id="fp-active" '+((p?(p.is_active===1):true)?'checked':'')+' style="width:auto"/> Active</label>';
    W.modal(p?'Edit Plan':'New Plan',body,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancel</button><button class="w-btn w-btn-primary" onclick="wontia.factorySavePlan('+(p?p.id:'null')+')">Save</button>');
};

W.factorySavePlan=async function(id){
    var payload={
        slug:W.val('fp-slug'),name_es:W.val('fp-nes'),name_en:W.val('fp-nen'),
        description_es:W.val('fp-des'),description_en:W.val('fp-den'),
        price_cop:parseFloat(W.val('fp-cop'))||0,price_usd:parseFloat(W.val('fp-usd'))||0,
        billing_type:W.val('fp-billing'),duration_months:parseInt(W.val('fp-dur'))||0,
        features:W.safeJsonField('fp-feat'),limits:W.safeJsonField('fp-lim'),
        margin_cost_items:W.safeJsonField('fp-costs'),
        min_margin_pct:parseFloat(W.val('fp-minm'))||25,sort_order:parseInt(W.val('fp-sort'))||0,
        is_active:document.getElementById('fp-active').checked?1:0
    };
    var r=id?await W.api('/api/v1/admin/factory/plans/'+id,{method:'PUT',body:payload}):await W.api('/api/v1/admin/factory/plans',{method:'POST',body:payload});
        if(r.ok){W.closeModal();W.notify(r.message,'success');W.renderWWI('planes')}else if(r.message){W.notify(r.message,'error')}
};

W.safeJsonField=function(id){
    var v=W.val(id);
    try{var parsed=JSON.parse(v||'[]');return parsed}catch(e){W.notify('Invalid JSON in field '+id,'error');throw e}
};

W.factoryConfig=function(){
    var el=document.getElementById('factory-content');
    var cfg=W.state.factory.config||{};
    var html='<div class="w-flex-between w-mb"><div style="font-size:12px;color:var(--w-muted)">Configuration engine — prices, limits and rules. Nothing hardcoded.</div></div>';
    html+='<table class="w-table"><tr><th>Key</th><th>Value</th></tr>';
    for(var k in cfg){
        html+='<tr><td style="font-size:11px;color:var(--w-muted)"><code>'+W.esc(k)+'</code></td><td><input class="w-input fc-val" data-key="'+W.esc(k)+'" value="'+W.esc(cfg[k])+'" style="max-width:400px"/></td></tr>';
    }
    html+='</table>';
      html+='<button class="w-btn w-btn-primary w-mt" onclick="wontia.factorySaveConfig()">Save Configuration</button>';
      html+=' <button class="w-btn w-btn-secondary w-mt" onclick="wontia.factorySyncMail()">Sincronizar correo a todos los sitios</button>';
      el.innerHTML=html;
  };
  
  W.factorySyncMail=async function(){
      var r=await W.api('/api/v1/admin/factory/mail/sync',{method:'POST',body:{}});
      W.notify(r.message||(r.ok?'OK':'Error'),r.ok?'success':'error');
  };

W.factorySaveConfig=async function(){
    var payload={};
    document.querySelectorAll('.fc-val').forEach(function(i){payload[i.dataset.key]=i.value});
    var r=await W.api('/api/v1/admin/factory/config',{method:'PUT',body:payload});
    if(r.ok)W.notify(r.message,'success');
};

W.factoryMargin=async function(){
    var el=document.getElementById('factory-content');
    el.innerHTML='<div style="text-align:center;padding:40px;color:var(--w-muted)">Computing margins...</div>';
    var r=await W.api('/api/v1/admin/factory/margin');
    var rows=r.data||[];
    if(!rows.length){el.innerHTML='<div class="w-empty-state"><p>No plans with margin data.</p></div>';return}
    var html='<div style="font-size:12px;color:var(--w-muted);margin-bottom:16px">Margin Guard — computed live at USD rate '+rows[0].usd_rate+' COP (editable: wwi.usd_cop_rate). Alerts when margin &lt; min_margin_pct.</div>';
    html+='<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px">';
    rows.forEach(function(m){
        var color=m.status==='critical'?'#f87171':m.status==='warning'?'#fbbf24':'#34d399';
        html+='<div class="w-card" style="padding:16px;border-left:3px solid '+color+'"><div class="w-flex-between"><strong style="font-size:13px">'+W.esc(m.plan)+'</strong><span class="w-badge" style="background:'+color+'22;color:'+color+'">'+(m.status==='critical'?'CRITICAL':m.status==='warning'?'WARNING':'OK')+'</span></div>';
        html+='<div style="font-size:11px;color:var(--w-muted);margin-top:4px">'+W.esc(m.billing)+' &middot; '+(m.slug||'')+'</div>';
        html+='<div style="margin-top:10px;font-size:12px"><div class="w-flex-between" style="padding:3px 0"><span>Price</span><strong>$'+W.num(m.price_cop)+'</strong></div>';
        (m.items||[]).forEach(function(i){
            html+='<div class="w-flex-between" style="padding:3px 0;color:var(--w-muted)"><span>− '+W.esc(i.item)+' '+(i.usd!=null?'($'+i.usd+')':i.pct!=null?'('+i.pct+'%)':'')+'</span><span>−$'+W.num(i.cop)+'</span></div>';
        });
        html+='<div class="w-flex-between" style="padding:3px 0"><span>Total cost</span><span>−$'+W.num(m.total_cost_cop)+'</span></div>';
        html+='<div class="w-flex-between" style="padding:6px 0;border-top:1px solid var(--w-border)"><span style="font-weight:600">Margin</span><strong style="color:'+color+'">$'+W.num(m.margin_cop)+' ('+m.margin_pct+'%)</strong></div></div>';
        html+='</div>';
    });
    html+='</div>';
    el.innerHTML=html;
};

W.fStatus=function(s){
    var map={PUBLISHED:['#34d399'],ACTIVE:['#34d399'],PAID:['#34d399'],READY:['#34d399'],DRAFT:['#8593ab'],CREATED:['#8593ab'],PENDING_PAYMENT:['#fbbf24'],PROVISIONING:['#22d3ee'],REGISTERED:['#22d3ee'],DNS_PENDING:['#22d3ee'],GENERATING:['#22d3ee'],REQUESTED:['#fbbf24'],EXPIRING:['#fbbf24'],AVAILABLE:['#22d3ee'],SUSPENDED:['#f87171'],FAILED:['#f87171'],EXPIRED:['#f87171'],CANCELLED:['#f87171'],REFUNDED:['#f87171'],ARCHIVED:['#8593ab'],DELETED:['#8593ab']};
    var c=map[s]||['#8593ab'];
    return '<span class="w-badge" style="background:'+c[0]+'22;color:'+c[0]+'">'+W.esc(s||'')+'</span>';
};

W.fStatusSelect=function(id,current,options){
    var s='<select class="w-select" id="'+id+'" onchange="wontia.'+options.cb+'(this.dataset.id,this.value)">';
    options.list.forEach(function(o){s+='<option value="'+o+'"'+((current===o)?' selected':'')+'>'+o+'</option>'});
    s+='</select>';
    return s;
};

W.factoryInicio=async function(){
    var el=document.getElementById('factory-content');
    el.innerHTML='<div style="text-align:center;padding:40px;color:var(--w-muted)">Loading...</div>';
    var r=await W.api('/api/v1/admin/factory/dashboard');
    if(!r.ok){el.innerHTML='<div class="w-empty-state"><h3>Factory not initialized</h3><button class="w-btn w-btn-primary" onclick="wontia.factoryEnsure()">Setup Tables</button></div>';return}
    var d=r.data;
    var kpis=[['Sitios',d.sites_total],['Publicados',d.sites_published],['Dominios',d.domains_total+' ('+d.domains_active+' activos)'],['Emails',d.emails_total],['Clientes',d.clients],['Pedidos',d.orders_total+' ('+d.orders_paid+' pagados)'],['Ingresos',W.bMoney(d.revenue_total)],['IA del mes',W.bMoney(d.ai_cost_month)],['Jobs pendientes',d.pending_jobs]];
    var html='<div class="w-stats">';
    kpis.forEach(function(k){html+='<div class="w-stat-card"><div class="w-stat-value">'+k[1]+'</div><div class="w-stat-label">'+W.esc(k[0])+'</div></div>'});
    html+='</div>';
    html+='<div class="w-card"><h3>Servicios del ecosistema</h3><div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px">';
    [['Sitios web','#sitios','Gestionar sitios de clientes','fabrik'],['Dominios','#dominios','Registro y ciclo de vida','dom'],['Emails','#emails','Buzones corporativos','mail'],['Pedidos y Pagos','#pedidos','Órdenes y transiciones','ord'],['Saldos','#saldos','Créditos y consumos','sal'],['Consumo IA','#ia','Tokens y costos por cliente','ia'],['Actualizaciones','#system','Actualizar el sistema desde Git','sys']].forEach(function(s){
        html+='<div class="w-card" style="padding:16px;cursor:pointer" onclick="wontia.factoryGo(\''+s[1].slice(1)+'\')"><div style="font-size:13px;font-weight:600">'+s[0]+'</div><div style="font-size:11px;color:var(--w-muted);margin-top:4px">'+s[2]+'</div></div>';
    });
    html+='</div></div>';
    html+='<div class="w-card"><h3>Margin Guard</h3><div id="fg-margin-mini" style="font-size:11px;color:var(--w-muted)">Loading...</div></div>';
    el.innerHTML=html;
    var m=await W.api('/api/v1/admin/factory/margin');
    var mini='';
    (m.data||[]).forEach(function(x){
        var color=x.status==='critical'?'#f87171':x.status==='warning'?'#fbbf24':'#34d399';
        mini+='<div class="w-flex-between" style="padding:6px 0;border-bottom:1px solid var(--w-border)"><span>'+W.esc(x.plan)+' ('+W.esc(x.billing)+')</span><strong style="color:'+color+'">'+x.margin_pct+'%</strong></div>';
    });
    document.getElementById('fg-margin-mini').innerHTML=mini||'No plans';
};

W.factorySites=async function(){
    var el=document.getElementById('factory-content');
    el.innerHTML='<div style="text-align:center;padding:40px;color:var(--w-muted)">Loading...</div>';
    var r=await W.api('/api/v1/admin/factory/sites');
    var rows=r.data||[];
    var html='<div class="w-flex-between w-mb"><div style="font-size:12px;color:var(--w-muted)">Client sites (tenants) with lifecycle and plan.</div><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.factorySiteForm()">+ New Site</button></div>';
    html+='<table class="w-table"><tr><th>Site</th><th>Domain</th><th>Plan</th><th>Active Domain</th><th>Status</th><th>Creds</th><th>Created</th></tr>';
    rows.forEach(function(s){
        html+='<tr><td><strong>'+W.esc(s.name)+'</strong> <span style="font-size:10px;color:var(--w-muted)">#'+s.id+'</span></td><td style="font-size:11px">'+W.esc(s.domain||'-')+'</td><td>'+W.esc(s.plan_name||'-')+'</td><td style="font-size:11px">'+W.esc(s.active_domain||'-')+'</td><td>'+W.fStatusSelect('fs-'+s.id,s.status||'DRAFT',{list:['DRAFT','GENERATING','READY','PUBLISHED','SUSPENDED','ARCHIVED'],cb:'factorySiteStatus'})+'</td><td><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.factoryCreds('+s.id+')">Creds</button></td><td style="font-size:10px">'+W.esc((s.created_at||'').slice(0,10))+'</td></tr>';
    });
    html+='</table>';
    el.innerHTML=html;
};

W.factorySiteStatus=async function(id,status){
    var r=await W.api('/api/v1/admin/factory/sites/'+id+'/status',{method:'PUT',body:{status:status}});
    if(r.ok)W.notify(r.message,'success');
};

W.factoryCreds=async function(id){
    W.notify('Generando credenciales del cliente...','info');
    var r=await W.api('/api/v1/admin/factory/sites/'+id+'/credentials',{method:'POST'});
    if(r.ok&&r.data){
        W.modal('Credenciales del cliente', '<div class="w-form-group"><label class="w-label">Usuario</label><input class="w-input" readonly value="'+W.esc(r.data.username)+'"/></div><div class="w-form-group"><label class="w-label">Contraseña (se muestra una sola vez)</label><input class="w-input" readonly value="'+W.esc(r.data.password)+'"/></div><div style="font-size:11px;color:var(--w-muted)">Comparte estas credenciales con el cliente de forma segura. El acceso al panel de su sitio estará disponible cuando su dominio esté publicado.</div>', '<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cerrar</button>');
    }
};

W.factorySiteForm=function(){
    var plans=W.state.factory.plans;
    var opts='<option value="">— none —</option>';
    plans.forEach(function(p){opts+='<option value="'+p.id+'">'+W.esc(p.name_es)+'</option>'});
    var body='<div class="w-form-group"><label class="w-label">Site Name</label><input class="w-input" id="fsite-name" placeholder="Cliente - Negocio"/></div>';
    body+='<div class="w-form-group"><label class="w-label">Domain (para publicar)</label><input class="w-input" id="fsite-domain" placeholder="cliente.com"/></div>';
    body+='<div class="w-flex" style="gap:12px"><div class="w-form-group" style="flex:1"><label class="w-label">Locale</label><select class="w-select" id="fsite-locale"><option value="es">es</option><option value="en">en</option><option value="pt">pt</option></select></div><div class="w-form-group" style="flex:1"><label class="w-label">Plan</label><select class="w-select" id="fsite-plan">'+opts+'</select></div></div>';
    W.modal('New Client Site',body,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancel</button><button class="w-btn w-btn-primary" onclick="wontia.factorySaveSite()">Create</button>');
};

W.factorySaveSite=async function(){
    var r=await W.api('/api/v1/admin/factory/sites',{method:'POST',body:{name:W.val('fsite-name'),domain:W.val('fsite-domain'),locale:W.val('fsite-locale'),plan_id:parseInt(W.val('fsite-plan'))||0,status:'DRAFT'}});
    if(r.ok){W.closeModal();W.notify(r.message,'success');W.factorySites()}
};

W.factoryDominios=async function(){
    var el=document.getElementById('factory-content');
    el.innerHTML='<div style="text-align:center;padding:40px;color:var(--w-muted)">Loading...</div>';
    var r=await W.api('/api/v1/admin/factory/domains');
    var rows=r.data||[];
    var html='<div class="w-flex-between w-mb"><div style="font-size:12px;color:var(--w-muted)">Domain registry & lifecycle.</div><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.factoryDomainForm()">+ New Domain</button></div>';
    html+='<table class="w-table"><tr><th>Domain</th><th>Site</th><th>Provider</th><th>Status</th><th>Renewal USD</th><th>Expires</th></tr>';
    rows.forEach(function(d){
        html+='<tr><td><strong>'+W.esc(d.name)+'</strong></td><td style="font-size:11px">'+W.esc(d.site_name||'-')+'</td><td>'+W.esc(d.provider||'-')+'</td><td>'+W.fStatusSelect('fd-'+d.id,d.status,{list:['SEARCH','AVAILABLE','REGISTERING','REGISTERED','DNS_PENDING','ACTIVE','EXPIRING','EXPIRED'],cb:'factoryDomainStatus'})+'</td><td>'+d.renewal_cost+'</td><td style="font-size:10px">'+W.esc((d.expires_at||'').slice(0,10)||'-')+'</td></tr>';
    });
    html+='</table>';
    el.innerHTML=html;
};

W.factoryDomainStatus=async function(id,status){
    var r=await W.api('/api/v1/admin/factory/domains/'+id+'/status',{method:'PUT',body:{status:status}});
    if(r.ok)W.notify(r.message,'success');
};

W.factoryDomainForm=function(){
    var sites=W.state.factory.sites||[];
    var opts='<option value="0">— none —</option>';
    sites.forEach(function(s){opts+='<option value="'+s.id+'">'+W.esc(s.name)+'</option>'});
    var body='<div class="w-form-group"><label class="w-label">Domain</label><input class="w-input" id="fdom-name" placeholder="cliente.com"/></div>';
    body+='<div class="w-flex" style="gap:12px"><div class="w-form-group" style="flex:1"><label class="w-label">Site</label><select class="w-select" id="fdom-site">'+opts+'</select></div><div class="w-form-group" style="flex:1"><label class="w-label">Provider</label><input class="w-input" id="fdom-prov" placeholder="registrar"/></div></div>';
    body+='<div class="w-flex" style="gap:12px"><div class="w-form-group" style="flex:1"><label class="w-label">Registration USD</label><input class="w-input" id="fdom-reg" type="number" step="0.01" value="10.97"/></div><div class="w-form-group" style="flex:1"><label class="w-label">Renewal USD</label><input class="w-input" id="fdom-ren" type="number" step="0.01" value="10.97"/></div></div>';
    W.modal('New Domain',body,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancel</button><button class="w-btn w-btn-primary" onclick="wontia.factorySaveDomain()">Create</button>');
};

W.factorySaveDomain=async function(){
    var r=await W.api('/api/v1/admin/factory/domains',{method:'POST',body:{name:W.val('fdom-name'),site_id:parseInt(W.val('fdom-site'))||0,provider:W.val('fdom-prov'),registration_cost:parseFloat(W.val('fdom-reg'))||0,renewal_cost:parseFloat(W.val('fdom-ren'))||0,status:'AVAILABLE'}});
    if(r.ok){W.closeModal();W.notify(r.message,'success');W.factoryDominios()}
};

W.factoryEmails=async function(){
    var el=document.getElementById('factory-content');
    el.innerHTML='<div style="text-align:center;padding:40px;color:var(--w-muted)">Loading...</div>';
    var r=await W.api('/api/v1/admin/factory/emails');
    var rows=r.data||[];
    var html='<div class="w-flex-between w-mb"><div style="font-size:12px;color:var(--w-muted)">Corporate mailboxes. Passwords are never stored in plain text.</div><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.factoryEmailForm()">+ New Mailbox</button></div>';
    html+='<table class="w-table"><tr><th>Mailbox</th><th>Domain</th><th>Provider</th><th>Status</th></tr>';
    rows.forEach(function(e){
        html+='<tr><td><strong>'+W.esc(e.mailbox)+'</strong></td><td style="font-size:11px">'+W.esc(e.domain_name||'-')+'</td><td>'+W.esc(e.provider||'-')+'</td><td>'+W.fStatusSelect('fe-'+e.id,e.status,{list:['REQUESTED','PROVISIONING','ACTIVE','SUSPENDED','DELETED'],cb:'factoryEmailStatus'})+'</td></tr>';
    });
    html+='</table>';
    el.innerHTML=html;
};

W.factoryEmailStatus=async function(id,status){
    var r=await W.api('/api/v1/admin/factory/emails/'+id+'/status',{method:'PUT',body:{status:status}});
    if(r.ok)W.notify(r.message,'success');
};

W.factoryEmailForm=function(){
    var sites=W.state.factory.sites||[];
    var domains=W.state.factory.domains||[];
    var sopts='<option value="0">— none —</option>';
    sites.forEach(function(s){sopts+='<option value="'+s.id+'">'+W.esc(s.name)+'</option>'});
    var dopts='<option value="0">— none —</option>';
    domains.forEach(function(d){dopts+='<option value="'+d.id+'">'+W.esc(d.name)+'</option>'});
    var body='<div class="w-form-group"><label class="w-label">Mailbox (usuario@dominio.com)</label><input class="w-input" id="fmail-box" placeholder="contacto"/></div>';
    body+='<div class="w-flex" style="gap:12px"><div class="w-form-group" style="flex:1"><label class="w-label">Site</label><select class="w-select" id="fmail-site">'+sopts+'</select></div><div class="w-form-group" style="flex:1"><label class="w-label">Domain</label><select class="w-select" id="fmail-dom">'+dopts+'</select></div></div>';
    body+='<div class="w-form-group"><label class="w-label">Provider</label><input class="w-input" id="fmail-prov" placeholder="mail provider"/></div>';
    W.modal('New Mailbox',body,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancel</button><button class="w-btn w-btn-primary" onclick="wontia.factorySaveEmail()">Create</button>');
};

W.factorySaveEmail=async function(){
    var r=await W.api('/api/v1/admin/factory/emails',{method:'POST',body:{mailbox:W.val('fmail-box'),site_id:parseInt(W.val('fmail-site'))||0,domain_id:parseInt(W.val('fmail-dom'))||0,provider:W.val('fmail-prov'),status:'REQUESTED'}});
    if(r.ok){W.closeModal();W.notify(r.message,'success');W.factoryEmails()}
};

W.factoryPedidos=async function(){
    var el=document.getElementById('factory-content');
    el.innerHTML='<div style="text-align:center;padding:40px;color:var(--w-muted)">Loading...</div>';
    var r=await W.api('/api/v1/admin/factory/orders');
    var rows=r.data||[];
    var html='<div class="w-flex-between w-mb"><div style="font-size:12px;color:var(--w-muted)">Orders — payment webhook will drive these states automatically (Fase 1).</div><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.factoryOrderForm()">+ New Order</button></div>';
    html+='<table class="w-table"><tr><th>Order</th><th>Client</th><th>Plan</th><th>Domain</th><th>Total</th><th>Status</th><th>Created</th></tr>';
    rows.forEach(function(o){
        html+='<tr><td style="font-size:10px">'+W.esc((o.uuid||'').slice(0,8))+'</td><td style="font-size:11px">'+W.esc(o.customer_name||o.site_name||'-')+'</td><td>'+W.esc(o.plan_slug||'-')+'</td><td style="font-size:11px">'+W.esc(o.domain_name||'-')+'</td><td>$'+W.num(o.total)+' '+W.esc(o.currency||'')+'</td><td>'+W.fStatusSelect('fo-'+o.id,o.status,{list:['CREATED','PENDING_PAYMENT','PAID','PROVISIONING','READY','FAILED','CANCELLED','REFUNDED'],cb:'factoryOrderStatus'})+'</td><td style="font-size:10px">'+W.esc((o.created_at||'').slice(0,10))+'</td></tr>';
    });
    html+='</table>';
    el.innerHTML=html;
};

W.factoryOrderStatus=async function(id,status){
    var r=await W.api('/api/v1/admin/factory/orders/'+id+'/status',{method:'PUT',body:{status:status}});
    if(r.ok){W.notify(r.message,'success');if(status==='PAID')W.notify('Balance credited to tenant','success')}
};

W.factoryOrderForm=function(){
    var plans=W.state.factory.plans;
    var sites=W.state.factory.sites||[];
    var opts='<option value="">— none —</option>';
    plans.forEach(function(p){opts+='<option value="'+p.id+'">'+W.esc(p.name_es)+' ($'+W.num(p.price_cop)+')</option>'});
    var sopts='<option value="0">— none —</option>';
    sites.forEach(function(s){sopts+='<option value="'+s.id+'">'+W.esc(s.name)+'</option>'});
    var body='<div class="w-flex" style="gap:12px"><div class="w-form-group" style="flex:1"><label class="w-label">Client Name</label><input class="w-input" id="fo-name"/></div><div class="w-form-group" style="flex:1"><label class="w-label">Client Email</label><input class="w-input" id="fo-email" type="email"/></div></div>';
    body+='<div class="w-flex" style="gap:12px"><div class="w-form-group" style="flex:1"><label class="w-label">Plan</label><select class="w-select" id="fo-plan">'+opts+'</select></div><div class="w-form-group" style="flex:1"><label class="w-label">Tenant Site</label><select class="w-select" id="fo-tenant">'+sopts+'</select></div></div>';
    body+='<div class="w-form-group"><label class="w-label">Domain (opcional)</label><input class="w-input" id="fo-domain" placeholder="cliente.com"/></div>';
    W.modal('New Order',body,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancel</button><button class="w-btn w-btn-primary" onclick="wontia.factorySaveOrder()">Create</button>');
};

W.factorySaveOrder=async function(){
    var r=await W.api('/api/v1/admin/factory/orders',{method:'POST',body:{customer_name:W.val('fo-name'),customer_email:W.val('fo-email'),plan_id:parseInt(W.val('fo-plan'))||0,tenant_id:parseInt(W.val('fo-tenant'))||0,domain_name:W.val('fo-domain')}});
    if(r.ok){W.closeModal();W.notify(r.message,'success');W.factoryPedidos()}
};

W.factorySaldos=async function(){
    var el=document.getElementById('factory-content');
    el.innerHTML='<div style="text-align:center;padding:40px;color:var(--w-muted)">Loading...</div>';
    var r=await W.api('/api/v1/admin/factory/ledger');
    var rows=r.data||[];
    var html='<div class="w-flex-between w-mb"><div style="font-size:12px;color:var(--w-muted)">Balance ledger — credits and debits per tenant. Auto-credited when orders are PAID.</div><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.factoryLedgerForm()">+ Entry</button></div>';
    html+='<table class="w-table"><tr><th>Site</th><th>Type</th><th>Amount</th><th>Reason</th><th>Ref</th><th>When</th></tr>';
    rows.forEach(function(l){
        html+='<tr><td style="font-size:11px">'+W.esc(l.site_name||('#'+l.site_id))+'</td><td>'+(l.direction==='credit'?'<span style="color:#34d399">CREDIT</span>':'<span style="color:#f87171">DEBIT</span>')+'</td><td><strong>$'+W.num(l.amount)+'</strong></td><td style="font-size:11px">'+W.esc(l.reason||'')+'</td><td style="font-size:10px">'+W.esc(l.ref||'')+'</td><td style="font-size:10px">'+W.esc((l.created_at||'').slice(0,16))+'</td></tr>';
    });
    html+='</table>';
    el.innerHTML=html;
};

W.factoryLedgerForm=function(){
    var sites=W.state.factory.sites||[];
    var sopts='<option value="0">— none —</option>';
    sites.forEach(function(s){sopts+='<option value="'+s.id+'">'+W.esc(s.name)+'</option>'});
    var body='<div class="w-form-group"><label class="w-label">Site</label><select class="w-select" id="fl-site">'+sopts+'</select></div>';
    body+='<div class="w-flex" style="gap:12px"><div class="w-form-group" style="flex:1"><label class="w-label">Type</label><select class="w-select" id="fl-dir"><option value="credit">Credit (+)</option><option value="debit">Debit (−)</option></select></div><div class="w-form-group" style="flex:1"><label class="w-label">Amount</label><input class="w-input" id="fl-amt" type="number"/></div></div>';
    body+='<div class="w-form-group"><label class="w-label">Reason</label><input class="w-input" id="fl-reason" placeholder="Pago, consumo, ajuste..."/></div>';
    W.modal('New Ledger Entry',body,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancel</button><button class="w-btn w-btn-primary" onclick="wontia.factorySaveLedger()">Create</button>');
};

W.factorySaveLedger=async function(){
    var r=await W.api('/api/v1/admin/factory/ledger',{method:'POST',body:{site_id:parseInt(W.val('fl-site'))||0,direction:W.val('fl-dir'),amount:parseFloat(W.val('fl-amt'))||0,reason:W.val('fl-reason')}});
    if(r.ok){W.closeModal();W.notify(r.message,'success');W.factorySaldos()}
};

W.factoryIA=async function(){
    var el=document.getElementById('factory-content');
    el.innerHTML='<div style="text-align:center;padding:40px;color:var(--w-muted)">Loading...</div>';
    var r=await W.api('/api/v1/admin/factory/ai-usage');
    var rows=r.data||[];
    var html='<div class="w-flex-between w-mb"><div style="font-size:12px;color:var(--w-muted)">AI consumption per client this month (from BRICK usage records).</div><button class="w-btn w-btn-secondary w-btn-sm" onclick="location.hash=\'#brick\'">Open AI BRICK</button></div>';
    html+='<table class="w-table"><tr><th>Site</th><th>Requests</th><th>Tokens</th><th>Cost</th></tr>';
    rows.forEach(function(x){
        html+='<tr><td style="font-size:11px">'+W.esc(x.site_name||('#'+x.site_id))+'</td><td>'+W.num(x.requests)+'</td><td>'+W.num(x.tokens)+'</td><td>'+W.bMoney(x.cost)+'</td></tr>';
    });
    html+='</table>';
    if(!rows.length)html+='<div class="w-empty-state"><p>No AI usage yet this month.</p></div>';
    el.innerHTML=html;
};

W.factoryJobs=async function(){
    var el=document.getElementById('factory-content');
    el.innerHTML='<div style="text-align:center;padding:40px;color:var(--w-muted)">Loading...</div>';
    var r=await W.api('/api/v1/admin/factory/jobs');
    var rows=r.data||[];
    var html='<div class="w-flex-between w-mb"><div style="font-size:12px;color:var(--w-muted)">Job queue — async, idempotent and retryable. Also runs via CLI: <code>php /app/public/job-runner.php</code> (cron).</div><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.factoryRunJobs()">Run Now</button></div>';
    html+='<table class="w-table"><tr><th>ID</th><th>Type</th><th>Status</th><th>Attempts</th><th>Error</th><th>Created</th></tr>';
    rows.forEach(function(j){
        html+='<tr><td>'+j.id+'</td><td><code style="font-size:11px">'+W.esc(j.type)+'</code></td><td>'+W.fStatus(String(j.status||'').toUpperCase())+'</td><td>'+j.attempts+'/'+j.max_attempts+'</td><td style="font-size:10px;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+W.esc(j.error||'')+'</td><td style="font-size:10px">'+W.esc((j.created_at||'').slice(0,16))+'</td></tr>';
    });
    html+='</table>';
    if(!rows.length)html+='<div class="w-empty-state"><p>No jobs yet. Jobs are enqueued when orders are paid.</p></div>';
    el.innerHTML=html;
};

W.factoryRunJobs=async function(){
    W.notify('Running due jobs...','info');
    var r=await W.api('/api/v1/admin/factory/jobs/run',{method:'POST'});
    if(r.ok)W.notify(r.message,'success');
    W.factoryJobs();
};

W.updateLabels=function(files){
    if(!files||!files.length)return 'Sistema';
    var labels={};
    files.forEach(function(f){
        var m;
        if((m=f.match(/^src\/Widgets\/(.+)Widget\.php$/))){labels['Widget '+m[1].replace(/([a-z])([A-Z])/g,'$1 $2')]=1;}
        else if((m=f.match(/^src\/Bricks\/([^\/]+)/))){labels['Brick '+m[1]]=1;}
        else if((m=f.match(/^templates\/themes\/([^\/]+)/))){labels['Tema '+m[1]]=1;}
        else if((m=f.match(/^src\/Controllers\/Admin\/(.+)Controller\.php$/))){labels['Panel '+m[1]]=1;}
        else if(f.indexOf('public/assets/js/admin.js')===0){labels['Panel admin (UI)']=1;}
        else if(f.indexOf('public/assets/css')===0){labels['Estilos admin']=1;}
        else if(f.indexOf('src/Core/AiBrick')===0){labels['BRICK (IA)']=1;}
        else if(f.indexOf('src/Core/')===0){labels['Núcleo']=1;}
        else if(f.indexOf('install/')===0){labels['Base de datos']=1;}
        else if(f.indexOf('deploy/')===0){labels['Auto-actualización']=1;}
        else if(f.indexOf('templates/')===0){labels['Temas']=1;}
        else if((m=f.match(/\.md$/))){labels['Documentación']=1;}
        else {labels['Sistema']=1;}
    });
    var keys=Object.keys(labels);
    var shown=keys.slice(0,4).join(' · ');
    if(keys.length>4)shown+=' · +'+(keys.length-4);
    return shown;
};

W.factorySystem=async function(){
    var el=document.getElementById('factory-content');
    if(!el)return;
    var r=await W.api('/api/v1/admin/system/updates');
    var rows=r.data||[];
    var s=await W.api('/api/v1/admin/system/status');
    var st=(s.data)||{status:'idle'};
    var running=st.status==='running';
    var bh=await W.api('/api/v1/admin/brickhub/notifications');
    var pendingBricks=(bh.data&&bh.data.pending)||0;
    var pendingSystem=rows.filter(function(u){return u.status==='pending'}).length;
    var available=pendingBricks+pendingSystem;
    var titleColor=available>0?'#22d3ee':'#34d399';
    var html='<div style="margin-bottom:16px;padding:16px 18px;border:1px solid var(--w-border);border-left:3px solid '+titleColor+';border-radius:10px;background:var(--w-surface)"><div style="font-size:20px;font-weight:800;letter-spacing:-.01em">'+available+' actualizaci&oacute;n'+(available===1?'':'es')+' disponible'+(available===1?'':'s')+'</div><div style="font-size:12px;color:var(--w-muted);margin-top:4px">'+(available>0?'Hay mejoras listas. Los sitios reciben aviso por correo y se actualizan autom&aacute;ticamente.':'Sistema al d&iacute;a — no hay actualizaciones pendientes.')+'</div></div>';
    html+='<div class="w-card"><h3>Actualización desde Git</h3>';

    if(running){
        var pct=Math.max(0,Math.min(100,parseInt(st.pct)||0));
        var steps=[['clone','Descarga'],['sync','Sincronización'],['build','Build'],['recreate','Contenedores'],['health','Salud']];
        var stepIdx=0;steps.forEach(function(x,i){if(x[0]===st.step)stepIdx=i});
        var stepsHtml='';
        steps.forEach(function(x,i){
            var on=i<stepIdx, cur=i===stepIdx;
            stepsHtml+='<span class="mono" style="font-size:10px;padding:3px 8px;border-radius:6px;'+(cur?'background:rgba(34,211,238,.15);color:#22d3ee;font-weight:700':'color:var(--w-muted)')+'">'+(on?'✓ ':'')+W.esc(x[1])+'</span>';
        });
        html+='<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px"><span style="font-size:11px;font-weight:700;letter-spacing:.06em;color:#22d3ee">ACTUALIZANDO SISTEMA</span><span class="mono" style="font-size:18px;font-weight:700">'+pct+'%</span></div>';
        html+='<div style="height:8px;background:var(--w-border);border-radius:4px;overflow:hidden"><div style="height:100%;width:'+pct+'%;background:linear-gradient(90deg,#22d3ee,#8b5cf6);transition:width .5s ease"></div></div>';
        html+='<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px">'+stepsHtml+'</div>';
        html+='<div class="mono" style="font-size:11px;color:var(--w-muted);margin-top:10px">'+W.esc(st.message||'')+' &middot; '+W.num(st.elapsed_s||0)+'s transcurridos'+(st.eta_s?' &middot; ~'+W.num(st.eta_s)+'s restantes':'')+(st.containers_total?' &middot; contenedores '+W.num(st.containers_done||0)+'/'+W.num(st.containers_total):'')+'</div>';
        if(st.commit)html+='<div class="mono" style="font-size:10px;color:var(--w-muted);margin-top:4px">commit '+W.esc(st.commit)+'</div>';
        html+='</div>';
    }else if(st.status==='success'){
        html+='<div style="background:rgba(0,184,125,.08);border:1px solid rgba(0,184,125,.4);border-radius:10px;padding:18px;display:flex;gap:14px;align-items:center;margin-bottom:14px">'
            +'<div style="width:38px;height:38px;border-radius:10px;background:#34d399;color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700">✓</div>'
            +'<div><div style="font-size:14px;font-weight:800;color:#34d399;letter-spacing:.05em">SISTEMA ACTUALIZADO</div>'
            +'<div class="mono" style="font-size:11px;color:var(--w-muted);margin-top:2px">commit '+W.esc(st.commit||'-')+(st.containers_total?' · '+W.num(st.containers_total)+' contenedores':'')+(st.elapsed_s?' · '+W.num(st.elapsed_s)+'s':'')+'</div></div></div>';
        html+='<div style="font-size:12px;color:var(--w-muted);line-height:1.7;margin-bottom:14px">Todos los componentes están en la última versión del repositorio. Los sitios ya fueron notificados por correo.</div>';
        html+='<button class="w-btn w-btn-primary" onclick="wontia.factoryRunUpdate()">Actualizar de nuevo desde Git</button>';
        html+=' <button class="w-btn w-btn-secondary" onclick="wontia.factoryNotifySites()">Notificar a los sitios</button>';
    }else if(st.status==='rolled_back'){
        html+='<div style="background:rgba(190,19,65,.08);border:1px solid rgba(190,19,65,.4);border-radius:10px;padding:18px;margin-bottom:14px"><div style="font-size:13px;font-weight:800;color:var(--w-accent);letter-spacing:.05em">ROLLBACK APLICADO</div><div class="mono" style="font-size:11px;color:var(--w-muted);margin-top:4px">La actualización falló la verificación y se restauró la versión anterior. Revisa el log del servidor.</div></div>';
        html+='<button class="w-btn w-btn-primary" onclick="wontia.factoryRunUpdate()">Reintentar actualización</button>';
    }else{
        html+='<div style="font-size:12px;color:var(--w-muted);line-height:1.7;margin-bottom:14px">Actualiza TODOS los componentes del sistema (sitios, factory, temas, módulos) desde el repositorio oficial <code>intsolcom/wontia-web-intelligence</code>. El agente del servidor descarga el último commit, reconstruye la imagen, recrea los contenedores y verifica salud; si algo falla, hace rollback automático. Tarda ~1-2 minutos.</div>';
        html+='<button class="w-btn w-btn-primary" onclick="wontia.factoryRunUpdate()">Actualizar sistema desde Git</button>';
        html+=' <button class="w-btn w-btn-secondary" onclick="wontia.factoryNotifySites()">Notificar a los sitios</button>';
        html+='<div style="font-size:11px;color:var(--w-muted);margin-top:10px">Se ejecuta vía cron del servidor en menos de 1 minuto. Al actualizar se envía correo automático a todos los sitios.</div>';
    }
    html+='</div>';
    html+='<div class="w-card"><h3>Historial de actualizaciones</h3>';
    if(!rows.length){html+='<div class="w-empty-state" style="padding:16px"><p>Sin actualizaciones aún</p></div>';}
    else{
        html+='<table class="w-table"><tr><th>Cuándo</th><th>Actualiza</th><th>Estado</th><th>Commit</th><th>Contenedores</th><th>Duración</th></tr>';
        rows.forEach(function(u){
            var d=u.data||{};
            var status=u.status==='pending'?'pending':(d.status||'done');
            var what=W.updateLabels(d.changed_files);
            var subject=d.commit_subject?'<div style="font-size:10px;color:var(--w-muted);max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+W.esc(d.commit_subject)+'</div>':'';
            html+='<tr><td style="font-size:10px">'+W.esc(u.file)+'</td><td style="font-size:11px"><strong>'+W.esc(what)+'</strong>'+subject+'</td><td>'+W.fStatus(String(status).toUpperCase())+'</td><td class="mono" style="font-size:11px">'+W.esc(d.commit||'-')+'</td><td style="font-size:11px">'+((d.containers||[]).length||'-')+'</td><td style="font-size:11px">'+(d.duration_s?d.duration_s+'s':'-')+'</td></tr>';
        });
        html+='</table>';
    }
    html+='</div>';
    el.innerHTML=html;
    if(running){
        clearTimeout(W.state.updatePoll);
        W.state.updatePoll=setTimeout(function(){if(W.state.factory.tab==='system')W.factorySystem()},2500);
    }
};

W.factoryRunUpdate=function(){
    W.confirm('¿Actualizar TODO el sistema desde Git? Los sitios se reinician brevemente (~1-2 min) y recibirán aviso por correo.',async function(){
        W.notify('Encolando actualización...','info');
        var r=await W.api('/api/v1/admin/system/update',{method:'POST'});
        if(r.ok){W.notify(r.message,'success');setTimeout(W.factorySystem,2500)}
        else if(r.message)W.notify(r.message,'error');
    });
};

W.factoryNotifySites=function(){
    W.confirm('¿Enviar correo a todos los sitios notificando la actualización disponible?',async function(){
        W.notify('Encolando notificaciones...','info');
        var r=await W.api('/api/v1/admin/system/notify-sites',{method:'POST',body:{}});
        if(r.ok)W.notify(r.message,'success');
    });
};

W.renderPortal=async function(){
    var app=document.getElementById('wontia-app');
    app.innerHTML='<div style="text-align:center;padding:60px;color:var(--w-muted)">Loading your services...</div>';
    var r=await W.api('/api/v1/admin/factory/my-portal');
    if(!r.ok){app.innerHTML='<div class="w-empty-state"><p>'+W.esc(r.message||'Error')+'</p></div>';return}
    var d=r.data;
    var site=d.site||{};
    var plan=d.plan||{};
    var ai=d.ai_month||{};
    var html='<div class="w-stats">';
    html+='<div class="w-stat-card"><div class="w-stat-value">'+W.esc(site.status||'-')+'</div><div class="w-stat-label">Mi sitio</div></div>';
    html+='<div class="w-stat-card"><div class="w-stat-value">'+W.esc(plan?plan.name_es:'-')+'</div><div class="w-stat-label">Plan</div></div>';
    html+='<div class="w-stat-card"><div class="w-stat-value">'+W.bMoney(d.balance)+'</div><div class="w-stat-label">Saldo</div></div>';
    html+='<div class="w-stat-card"><div class="w-stat-value">'+W.bMoney(ai.cost||0)+'</div><div class="w-stat-label">IA del mes</div></div>';
    html+='</div>';
    html+='<div class="w-brick-grid2"><div class="w-card"><h3>Mi sitio</h3><table class="w-table"><tr><th>Nombre</th><td>'+W.esc(site.name||'-')+'</td></tr><tr><th>Dominio</th><td>'+W.esc(site.domain||'-')+'</td></tr><tr><th>Estado</th><td>'+W.fStatus(site.status||'DRAFT')+'</td></tr></table></div>';
    html+='<div class="w-card"><h3>Dominios</h3>'+(d.domains&&d.domains.length?'<table class="w-table"><tr><th>Dominio</th><th>Estado</th><th>Expira</th></tr>'+d.domains.map(function(x){return '<tr><td>'+W.esc(x.name)+'</td><td>'+W.fStatus(x.status)+'</td><td>'+(x.expires_at||'-')+'</td></tr>'}).join('')+'</table>':'<div class="w-empty-state" style="padding:20px"><p>Sin dominios</p></div>')+'</div></div>';
    html+='<div class="w-brick-grid2"><div class="w-card"><h3>Correos</h3>'+(d.emails&&d.emails.length?'<table class="w-table"><tr><th>Buzón</th><th>Estado</th></tr>'+d.emails.map(function(x){return '<tr><td>'+W.esc(x.mailbox)+'</td><td>'+W.fStatus(x.status)+'</td></tr>'}).join('')+'</table>':'<div class="w-empty-state" style="padding:20px"><p>Sin buzones</p></div>')+'</div>';
    html+='<div class="w-card"><h3>Pedidos</h3>'+(d.orders&&d.orders.length?'<table class="w-table"><tr><th>#</th><th>Plan</th><th>Total</th><th>Estado</th></tr>'+d.orders.map(function(o){return '<tr><td>'+W.esc((o.uuid||'').slice(0,8))+'</td><td>'+(o.plan_id||'-')+'</td><td>$'+W.num(o.total)+'</td><td>'+W.fStatus(o.status)+'</td></tr>'}).join('')+'</table>':'<div class="w-empty-state" style="padding:20px"><p>Sin pedidos</p></div>')+'</div></div>';
    html+='<div class="w-card"><h3>TIA — Tu asistente de sitio</h3><div class="w-flex w-gap-sm"><input class="w-input" id="tia-input" placeholder="Ej: cambia el color a #22d3ee · agrega testimonios · muéstrame el estado" style="flex:1" onkeydown="if(event.key===\'Enter\')wontia.tiaSend()"/><button class="w-btn w-btn-primary" onclick="wontia.tiaSend()">Enviar</button></div><div id="tia-result" style="margin-top:12px"></div><div style="font-size:11px;color:var(--w-muted);margin-top:8px">Las acciones destructivas piden confirmación. Todo queda en el AI Audit Log.</div></div>';
    html+='<div class="w-card"><h3>Brief de negocio — TIA lo analiza</h3><div class="w-form-group" style="margin-bottom:10px"><label class="w-label">Nombre del negocio</label><input class="w-input" id="brief-name" placeholder="Panadería La Esquina"/></div><div class="w-form-group" style="margin-bottom:10px"><label class="w-label">Email</label><input class="w-input" id="brief-email" type="email" placeholder="tu@negocio.com"/></div><div class="w-form-group" style="margin-bottom:10px"><label class="w-label">Cuéntale a TIA sobre tu negocio</label><textarea class="w-textarea" id="brief-story" style="min-height:90px" placeholder="Somos una panadería familiar en Barranquilla. Vendemos pan artesanal, tortas y desayunos. Atendemos de 6am a 7pm..."></textarea></div><button class="w-btn w-btn-primary" onclick="wontia.briefSubmit()">Enviar a TIA</button><div id="brief-result" style="margin-top:10px"></div><div id="brief-list" style="margin-top:14px"></div></div>';
    html+='<div class="w-brick-grid2"><div class="w-card"><h3>Secciones del sitio</h3><div id="tia-sections">Cargando…</div></div><div class="w-card"><h3>AI Audit Log</h3><div id="tia-history">Cargando…</div></div></div>';
    app.innerHTML=html;
    W.tiaLoadSections();
    W.tiaLoadHistory();
    W.briefLoad();
};

W.briefSubmit=async function(){
    var res=document.getElementById('brief-result');
    if(!res)return;
    var payload={business_name:document.getElementById('brief-name').value,customer_email:document.getElementById('brief-email').value,story:document.getElementById('brief-story').value};
    if(!payload.customer_email||!payload.story){res.innerHTML='<div style="color:var(--w-accent);font-size:12px">Completa email y descripción.</div>';return}
    res.innerHTML='<div style="color:var(--w-muted);font-size:12px">Enviando a TIA…</div>';
    var r=await W.api('/api/v1/public/briefs',{method:'POST',body:payload});
    if(r.ok){res.innerHTML='<div style="color:#34d399;font-size:12px">✓ '+W.esc(r.message)+'</div>';document.getElementById('brief-story').value='';W.briefLoad();}
    else res.innerHTML='<div style="color:var(--w-accent);font-size:12px">'+W.esc(r.message||'Error')+'</div>';
};

W.briefLoad=async function(){
    var el=document.getElementById('brief-list');if(!el)return;
    var r=await W.api('/api/v1/admin/factory/my-portal');
    var briefs=(r.data&&r.data.briefs)||[];
    el.innerHTML=briefs.map(function(b){
        var p=b.profile?JSON.parse(b.profile):null;
        var lines=p?('<div style="margin-top:6px;font-size:11px;color:var(--w-muted)">Servicios: '+(p.servicios||[]).join(', ')+'</div><div style="font-size:11px;color:var(--w-muted)">Ubicación: '+W.esc(p.ubicacion||'—')+'</div>'+(p.pendientes&&p.pendientes.length?'<div style="font-size:11px;color:#fbbf24;margin-top:4px">Pendiente: '+p.pendientes.join(', ')+'</div>':'')):'';
        return '<div class="w-card" style="padding:12px;margin-top:8px"><div class="w-flex-between"><strong style="font-size:12px">'+W.esc(b.business_name||b.customer_email)+'</strong>'+W.fStatus(b.status)+'</div>'+lines+'</div>';
    }).join('')||'<div style="color:var(--w-muted);font-size:11px">Sin briefs aún</div>';
};

W.tiaSend=async function(){
    var input=document.getElementById('tia-input');var res=document.getElementById('tia-result');
    if(!input||!res)return;
    var cmd=input.value.trim();if(!cmd)return;
    res.innerHTML='<div style="color:var(--w-muted);font-size:12px">TIA está pensando…</div>';
    var r=await W.api('/api/v1/admin/tia/command',{method:'POST',body:{command:cmd}});
    var d=r.data||{};
    if(d.preview){
        W.state.tiaToken=d.token;
        res.innerHTML='<div class="w-card" style="border-color:#fbbf24;padding:14px"><div style="font-size:12px;color:#fbbf24">⚠ '+W.esc(r.message||'')+'</div><div class="w-flex w-gap-sm" style="margin-top:10px"><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.tiaConfirm()">Confirmar</button><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.tiaCancel()">Cancelar</button></div></div>';
    }else if(r.ok){
        res.innerHTML='<div class="w-card" style="border-color:rgba(52,211,153,.5);padding:14px"><div style="font-size:12px;color:#34d399">✓ '+W.esc(r.message||'Hecho')+'</div></div>';
        W.tiaLoadSections();W.tiaLoadHistory();
    }else{
        res.innerHTML='<div class="w-card" style="border-color:var(--w-accent);padding:14px"><div style="font-size:12px;color:var(--w-accent)">'+W.esc(r.message||'Error')+'</div></div>';
    }
    input.value='';
};

W.tiaConfirm=async function(){
    var res=document.getElementById('tia-result');
    var r=await W.api('/api/v1/admin/tia/confirm',{method:'POST',body:{token:W.state.tiaToken}});
    if(r.ok){res.innerHTML='<div class="w-card" style="border-color:rgba(52,211,153,.5);padding:14px"><div style="font-size:12px;color:#34d399">✓ '+W.esc(r.message||'Ejecutado')+'</div></div>';W.tiaLoadSections();W.tiaLoadHistory();}
    else res.innerHTML='<div style="color:var(--w-accent);font-size:12px">'+W.esc(r.message||'Error')+'</div>';
};

W.tiaCancel=function(){document.getElementById('tia-result').innerHTML='<div style="color:var(--w-muted);font-size:12px">Acción cancelada.</div>'};

W.tiaLoadSections=async function(){
    var el=document.getElementById('tia-sections');if(!el)return;
    var r=await W.api('/api/v1/admin/tia/sections');
    var rows=r.data||[];
    el.innerHTML=rows.map(function(s){return '<div class="w-flex-between" style="padding:5px 0;border-bottom:1px solid var(--w-border);font-size:11px"><span><span class="mono" style="color:var(--w-muted)">#'+s.id+'</span> '+W.esc(s.title||s.widget_type||'sección')+' <span class="w-brick-chip">'+W.esc(s.widget_type||'custom')+'</span></span><span style="color:var(--w-muted)">'+(s.is_active==='1'||s.is_active===1?'visible':'oculta')+'</span></div>'}).join('')||'<div style="color:var(--w-muted);font-size:11px">Sin secciones</div>';
};

W.tiaLoadHistory=async function(){
    var el=document.getElementById('tia-history');if(!el)return;
    var r=await W.api('/api/v1/admin/tia/history');
    var rows=r.data||[];
    el.innerHTML=rows.map(function(a){return '<div style="padding:5px 0;border-bottom:1px solid var(--w-border);font-size:11px"><span class="w-brick-chip">'+W.esc(a.status)+'</span> <strong>'+W.esc(a.action)+'</strong><div style="color:var(--w-muted);margin-top:2px">'+W.esc((a.command||'').slice(0,80))+'</div></div>'}).join('')||'<div style="color:var(--w-muted);font-size:11px">Sin acciones aún</div>';
};

W.renderWWI=async function(tab,action){
    tab=tab||'resumen';
    W.state.wwiTab=tab;
    var app=document.getElementById('wontia-app');
    var tabs=[['resumen','Resumen'],['inicio','Inicio'],['sitios','Sitios'],['dominios','Dominios'],['emails','Emails'],['pedidos','Pedidos'],['saldos','Saldos'],['ia','Consumo IA'],['jobs','Jobs'],['planes','Planes'],['config','Config'],['margin','Margin Guard'],['system','Actualizaciones']];
    var bar='<div class="w-brick-tabs">';
    tabs.forEach(function(t){
        bar+='<button class="w-brick-tab'+(tab===t[0]?' active':'')+'" onclick="wontia.wwiGo(\''+t[0]+'\')">'+t[1]+'</button>';
    });
    bar+='<div style="flex:1"></div><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.factoryEnsure()">Setup Tables</button></div>';
    if(tab!=='resumen'){
        W.state.factory.tab=tab;
        app.innerHTML='<div>'+bar+'<div id="factory-content"></div></div>';
        try{
            var pl=await W.api('/api/v1/admin/factory/plans');
            if(pl.data)W.state.factory.plans=pl.data;
            var cf=await W.api('/api/v1/admin/factory/config');
            if(cf.data)W.state.factory.config=cf.data;
            var stt=await W.api('/api/v1/admin/factory/sites');
            if(stt.data)W.state.factory.sites=stt.data;
            var dm=await W.api('/api/v1/admin/factory/domains');
            if(dm.data)W.state.factory.domains=dm.data;
        }catch(e){}
        var fns={inicio:W.factoryInicio,sitios:W.factorySites,dominios:W.factoryDominios,emails:W.factoryEmails,pedidos:W.factoryPedidos,saldos:W.factorySaldos,ia:W.factoryIA,jobs:W.factoryJobs,planes:W.factoryPlans,config:W.factoryConfig,margin:W.factoryMargin,system:W.factorySystem};
        (fns[tab]||W.factoryInicio)();
        return;
    }
    app.innerHTML=bar+'<div style="text-align:center;padding:40px;color:var(--w-muted)">Cargando sistema WWI...</div>';
    var st=(await W.api('/api/v1/admin/system/status')).data||{};
    var br=(await W.api('/api/v1/brick/overview')).data||{};
    var fd=(await W.api('/api/v1/admin/factory/dashboard')).data||{};
    var bh=(await W.api('/api/v1/admin/brickhub/notifications')).data||{};
    var s=br.stats||{};
    var color=st.status==='success'?'#34d399':(st.status==='running'?'#22d3ee':(st.status==='rolled_back'?'#f87171':'#8593ab'));
    var statusTxt=st.status==='running'?('Actualizando — '+(st.pct||0)+'%'):(st.status==='success'?'Sistema al día':(st.status==='rolled_back'?'Rollback aplicado':'Listo'));
    var html='<div style="margin-bottom:18px"><div style="font-size:20px;font-weight:800;letter-spacing:-.01em">WWI — Centro de Administración</div><div style="font-size:12px;color:var(--w-muted);margin-top:4px">Motor CMS + negocio (sitios, pedidos, dominios, saldos) + bricks + IA, todo en un solo lugar.</div></div>';
    html+=tabsHtml;
    html+='<div class="w-stats">'
        +'<div class="w-stat-card"><div class="w-stat-value" style="color:'+color+'">'+W.esc(statusTxt)+'</div><div class="w-stat-label">Estado del sistema</div></div>'
        +'<div class="w-stat-card"><div class="w-stat-value">'+W.esc(st.commit||'—')+'</div><div class="w-stat-label">Versión desplegada</div></div>'
        +'<div class="w-stat-card"><div class="w-stat-value">'+(s.active_providers||0)+'</div><div class="w-stat-label">Proveedores IA activos</div></div>'
        +'<div class="w-stat-card"><div class="w-stat-value">'+(fd.sites_total||0)+'</div><div class="w-stat-label">Sitios en el ecosistema</div></div>'
        +'<div class="w-stat-card"><div class="w-stat-value">'+(bh.pending||0)+'</div><div class="w-stat-label">Actualizaciones de bricks</div></div>'
        +'</div>';
    html+='<div class="w-brick-grid2" style="display:grid;grid-template-columns:1fr 1fr;gap:14px">';
    html+='<div class="w-card"><h3>Acciones del sistema</h3>'
        +'<div style="display:flex;flex-direction:column;gap:8px">'
        +'<button class="w-btn w-btn-primary" onclick="location.hash=\'#wwi/system\'">Actualizar sistema desde Git</button>'
        +'<button class="w-btn w-btn-secondary" onclick="wontia.wwiSetup(\'brick\')">Preparar tablas de IA (BRICK)</button>'
        +'<button class="w-btn w-btn-secondary" onclick="wontia.wwiSetup(\'brickhub\')">Preparar tablas de BrickHub</button>'
        +'<button class="w-btn w-btn-secondary" onclick="wontia.wwiRunJobs()">Ejecutar trabajos pendientes (jobs)</button>'
        +'<button class="w-btn w-btn-secondary" onclick="location.hash=\'#wwi/jobs\'">Ver cola de trabajos</button>'
        +'</div><div id="wwi-action-result" style="margin-top:10px"></div></div>';
    html+='<div class="w-card"><h3>¿Qué es cada menú?</h3><div style="font-size:12px;color:var(--w-muted);line-height:1.9">'
        +'<div><strong style="color:var(--w-text)">Pages</strong> — las páginas del sitio (ej. Home) y su contenido.</div>'
        +'<div><strong style="color:var(--w-text)">Sections</strong> — las secciones dentro de una página (hero, planes, FAQ…).</div>'
        +'<div><strong style="color:var(--w-text)">Bricks</strong> — hub con pestañas: Marketplace, Acoplados, IA (BRICK), Repos & Sync e Incubadora.</div>'
        +'<div><strong style="color:var(--w-text)">WWI — Sistema</strong> — el motor y el negocio: Resumen, Sitios, Dominios, Emails, Pedidos, Saldos, Consumo IA, Jobs, Planes, Config, Margin y Actualizaciones.</div>'
        +'<div><strong style="color:var(--w-text)">Blog / Media / SEO / Analytics</strong> — contenido, imágenes, posicionamiento y métricas.</div>'
        +'<div><strong style="color:var(--w-text)">Settings / Users</strong> — configuración del sitio y cuentas con acceso.</div>'
        +'</div></div>';
    html+='</div>';
    html+='<div class="w-card" style="margin-top:14px"><h3>🧱 Incubadora de Bricks</h3>'
        +'<div style="font-size:11px;color:var(--w-muted);margin-bottom:10px">Bricks instalables del ecosistema. Actívalos y adminístralos desde aquí.</div>'
        +'<div id="wwi-bricks-slot" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:12px"><div style="font-size:12px;color:var(--w-muted)">Cargando bricks...</div></div></div>';
    app.innerHTML=bar+html;
    W.wwiLoadBricks();
};

W.wwiGo=function(t){window.location.hash=t==='resumen'?'#wwi':'#wwi/'+t};

W.wwiLoadBricks=async function(slotId){
    var el=document.getElementById(slotId||'wwi-bricks-slot'); if(!el)return;
    try{
        var r=await W.api('/api/v1/admin/seo-global-launch');
        var b=(r.data&&r.data.brick)||{};
        var html='<div style="background:var(--w-surface);border:1px solid var(--w-border);border-radius:10px;padding:14px">'
            +'<div style="display:flex;justify-content:space-between;align-items:center;gap:8px"><div><strong style="font-size:13px">🚀 SEO Global Launch</strong> <span style="font-size:10px;color:var(--w-muted);background:var(--w-bg);padding:2px 8px;border-radius:10px">v'+W.esc(b.version||'1.0.0')+'</span></div>'
            +'<span style="font-size:10px;padding:2px 8px;border-radius:10px;background:'+(b.installed?'rgba(52,211,153,.12)':'rgba(245,158,11,.12)')+';color:'+(b.installed?'#34d399':'#fbbf24')+'">'+(b.installed?'ACTIVO':'NO INSTALADO')+'</span></div>'
            +'<div style="font-size:11px;color:var(--w-muted);margin-top:6px;line-height:1.6">Motor SEO full-site: scan, generación IA de metadatos, autofix, deepfix, JSON-LD, auditoría y tracker de bots. Multi-tenant.</div>'
            +'<div style="display:flex;gap:8px;margin-top:10px">'
            +'<button class="w-btn '+(b.installed?'w-btn-secondary':'w-btn-primary')+' w-btn-sm" onclick="wontia.sglActivate()">'+(b.installed?'Reinstalar tablas':'Activar en este sitio')+'</button>'
            +'<button class="w-btn w-btn-secondary w-btn-sm" onclick="location.href=\'/admin.php?panel=seo-global-launch#seo-global-launch/resumen\'">Abrir panel</button>'
            +'</div></div>';
        el.innerHTML=html;
    }catch(e){el.innerHTML='<div style="font-size:12px;color:#f87171">No se pudo cargar la incubadora.</div>'}
};

W.wwiSetup=async function(kind){
    var el=document.getElementById('wwi-action-result');
    if(el)el.innerHTML='<div style="font-size:11px;color:var(--w-muted)">Preparando…</div>';
    var url=kind==='brick'?'/api/v1/admin/brick/ensure-tables':'/api/v1/admin/brickhub/ensure-tables';
    var r=await W.api(url,{method:'POST'});
    if(el)el.innerHTML='<div style="font-size:11px;color:'+(r.ok?'#34d399':'#f87171')+'">'+W.esc(r.message||(r.ok?'Listo':'Error'))+'</div>';
};

W.wwiRunJobs=async function(){
    var el=document.getElementById('wwi-action-result');
    if(el)el.innerHTML='<div style="font-size:11px;color:var(--w-muted)">Ejecutando…</div>';
    var r=await W.api('/api/v1/admin/factory/jobs/run',{method:'POST'});
    if(el)el.innerHTML='<div style="font-size:11px;color:'+(r.ok?'#34d399':'#f87171')+'">'+W.esc(r.message||(r.ok?'Listo':'Error'))+'</div>';
};

// ════════════════════════════════════════════════
// SEO GLOBAL LAUNCH — brick panel
// ════════════════════════════════════════════════
W.renderSeoGlobalLaunch=async function(tab){
    tab=(tab||'resumen');
    W.sglLiveStop();
    var app=document.getElementById('wontia-app');
    app.innerHTML='<div style="text-align:center;padding:50px;color:var(--w-muted)">Cargando SEO Global Launch...</div>';
    var ov=(await W.api('/api/v1/admin/seo-global-launch')).data||{};
    var b=ov.brick||{}; var st=ov.stats||{};
    var tabs=[['resumen','Resumen'],['monitoreo','Monitoreo en vivo'],['analitica','Analítica'],['mi-trafico','Mi tráfico'],['meta-ads','Pauta Meta'],['keywords','Keywords'],['tecnico','Técnico'],['integraciones','Integraciones'],['seguridad','Seguridad IP'],['redirecciones','Redirecciones'],['404s','404s'],['enlaces-rotos','Enlaces rotos']];
    var tabsHtml='<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">'+tabs.map(function(x){return '<button class="w-btn '+(tab===x[0]?'w-btn-primary':'w-btn-secondary')+'" onclick="location.hash=\'#seo-global-launch/'+x[0]+'\'">'+x[1]+'</button>'}).join('')+'</div>';
    if(tab==='monitoreo'){
        app.innerHTML='<div style="margin-bottom:18px"><div style="font-size:20px;font-weight:800;letter-spacing:-.01em">SEO Global Launch</div><div style="font-size:12px;color:var(--w-muted);margin-top:4px">Monitoreo en vivo · peticiones, respuestas y comportamiento del sitio</div></div>'+tabsHtml
            +'<div id="sgl-live"><div class="sgl-live"><div class="sgl-live__top"><div class="sgl-live__brand"><span class="sgl-live__dot"></span><b>LIVE</b><span id="sgl-live-clock" class="sgl-live__clock">--:--:--</span></div><div class="sgl-live__controls"><button class="w-btn w-btn-secondary w-btn-sm" onclick="W.sglLiveFetch()">Actualizar</button><button class="w-btn w-btn-secondary w-btn-sm" id="sgl-live-pause" onclick="W.sglLiveToggle()">Pausar</button><button class="w-btn w-btn-secondary w-btn-sm" onclick="W.sglLiveClear()">Limpiar</button></div></div>'
            +'<div class="sgl-live__stats" id="sgl-live-stats"><div class="sgl-live__stat"><b>—</b><span>Conectando</span></div></div>'
            +'<div class="sgl-live__toolbar"><input id="sgl-live-filter" class="w-input" placeholder="Filtrar por ruta, IP, ciudad o dispositivo" oninput="W.sglLiveFilterRows()"/><select id="sgl-live-audience" class="w-select" onchange="W.sglLiveAudience()"><option value="client">Clientes</option><option value="internal">Yo</option><option value="bot">Bots</option><option value="all">Todos</option></select><select id="sgl-live-method" class="w-select" onchange="W.sglLiveFilterRows()"><option value="">Todos los métodos</option><option>GET</option><option>POST</option><option>PUT</option><option>PATCH</option><option>DELETE</option></select><select id="sgl-live-status" class="w-select" onchange="W.sglLiveFilterRows()"><option value="">Todos los estados</option><option value="2">2xx</option><option value="3">3xx</option><option value="4">4xx</option><option value="5">5xx</option></select><label class="sgl-live__auto"><input type="checkbox" id="sgl-live-autoscroll" checked/> Auto-scroll</label></div>'
            +'<div id="sgl-term" class="sgl-term"><div class="sgl-term__empty">Conectando al flujo del servidor…</div></div>'
            +'<div class="sgl-live__note">Por defecto se muestran <b>clientes</b>. El tráfico propio se marca con <b>YO</b> y se administra en <b>Mi tráfico</b>; no se suma a las métricas de clientes. Los bots se muestran aparte. Retención de 90 días. Las peticiones a estáticos, admin y beacons no se registran.</div></div></div>';
        W.sglLiveStart();
        return;
    }
    if(tab==='seguridad'){
        app.innerHTML='<div style="margin-bottom:18px"><div style="font-size:20px;font-weight:800;letter-spacing:-.01em">SEO Global Launch</div><div style="font-size:12px;color:var(--w-muted);margin-top:4px">Inteligencia de IPs, bots y detección de ataques</div></div>'+tabsHtml+'<div id="sgl-security"><div class="w-card" style="text-align:center;padding:30px;color:var(--w-muted)">Analizando peticiones...</div></div>';
        W.sglSecurityLoad();
        return;
    }
    if(tab==='integraciones'){
        app.innerHTML='<div style="margin-bottom:18px"><div style="font-size:20px;font-weight:800;letter-spacing:-.01em">SEO Global Launch</div><div style="font-size:12px;color:var(--w-muted);margin-top:4px">Integraciones con buscadores y medición externa</div></div>'+tabsHtml+'<div id="sgl-integrations"><div class="w-card" style="text-align:center;padding:30px;color:var(--w-muted)">Cargando integraciones...</div></div>';
        W.sglIntegrationsLoad();
        return;
    }
    if(tab==='analitica'){
        app.innerHTML='<div style="margin-bottom:18px"><div style="font-size:20px;font-weight:800;letter-spacing:-.01em">SEO Global Launch</div><div style="font-size:12px;color:var(--w-muted);margin-top:4px">Analítica, embudo y oportunidades · excluye tráfico propio y bots</div></div>'+tabsHtml+'<div id="sgl-analytics"><div class="w-card" style="text-align:center;padding:30px;color:var(--w-muted)">Cargando analítica...</div></div>';
        W.sglAnalyticsLoad();
        return;
    }
    if(tab==='mi-trafico'){
        app.innerHTML='<div style="margin-bottom:18px"><div style="font-size:20px;font-weight:800;letter-spacing:-.01em">Mi tráfico</div><div style="font-size:12px;color:var(--w-muted);margin-top:4px">Las visitas de este equipo se separan de los clientes y no contaminan la pauta</div></div>'+tabsHtml+'<div id="sgl-internal"><div class="w-card" style="text-align:center;padding:30px;color:var(--w-muted)">Analizando tráfico propio...</div></div>';
        W.sglInternalLoad();
        return;
    }
    if(tab==='meta-ads'){
        app.innerHTML='<div style="margin-bottom:18px"><div style="font-size:20px;font-weight:800;letter-spacing:-.01em">Pauta Meta</div><div style="font-size:12px;color:var(--w-muted);margin-top:4px">Tráfico real de anuncios: fbclid, UTM, landings y conversiones</div></div>'+tabsHtml+'<div id="sgl-meta"><div class="w-card" style="text-align:center;padding:30px;color:var(--w-muted)">Cargando resultados de Meta...</div></div>';
        W.sglMetaLoad();
        return;
    }
    if(tab==='redirecciones'||tab==='404s'||tab==='enlaces-rotos'){
        app.innerHTML='<div style="margin-bottom:18px"><div style="font-size:20px;font-weight:800;letter-spacing:-.01em">SEO Global Launch</div><div style="font-size:12px;color:var(--w-muted);margin-top:4px">Redirecciones, 404 y enlaces rotos</div></div>'+tabsHtml+'<div id="sgl-phase2"><div class="w-card" style="text-align:center;padding:30px;color:var(--w-muted)">Cargando...</div></div>';
        W.sglPhase2Load(tab);
        return;
    }
    if(tab==='keywords'){
        app.innerHTML='<div style="margin-bottom:18px"><div style="font-size:20px;font-weight:800;letter-spacing:-.01em">SEO Global Launch</div><div style="font-size:12px;color:var(--w-muted);margin-top:4px">Keywords por producto y página · borradores, aprobación y control de saturación</div></div>'+tabsHtml+'<div id="sgl-keywords"><div class="w-card" style="text-align:center;padding:30px;color:var(--w-muted)">Cargando keywords...</div></div>';
        W.sglKeywordsLoad();
        return;
    }
    if(tab==='tecnico'){
        app.innerHTML='<div style="margin-bottom:18px"><div style="font-size:20px;font-weight:800;letter-spacing:-.01em">SEO Global Launch</div><div style="font-size:12px;color:var(--w-muted);margin-top:4px">Brick v'+W.esc(b.version||'2.0.0')+' · Técnico y rastreo</div></div>'+tabsHtml+'<div id="sgl-tech"><div class="w-card" style="text-align:center;padding:30px;color:var(--w-muted)">Cargando configuración técnica...</div></div>';
        W.sglTechLoad();
        return;
    }
    var html='<div style="margin-bottom:18px"><div style="font-size:20px;font-weight:800;letter-spacing:-.01em">🚀 SEO Global Launch</div>'
        +'<div style="font-size:12px;color:var(--w-muted);margin-top:4px">Motor SEO full-site de un clic: meta tags, JSON-LD, Open Graph, auditoría y auto-fix con IA. Brick v'+W.esc(b.version||'1.0.0')+' · <strong style="color:var(--w-text)">'+W.esc(st.site_url||'')+'</strong></div></div>';
    html+='<div class="w-stats">'
        +'<div class="w-stat-card"><div class="w-stat-value" style="color:'+(st.last_score>=80?'#34d399':(st.last_score>=50?'#fbbf24':'#f87171'))+'">'+(st.last_score||'—')+'</div><div class="w-stat-label">Último score</div></div>'
        +'<div class="w-stat-card"><div class="w-stat-value">'+W.num(st.seo_pages||0)+'</div><div class="w-stat-label">Páginas optimizadas</div></div>'
        +'<div class="w-stat-card"><div class="w-stat-value">'+W.num(st.posts_total||0)+'</div><div class="w-stat-label">Posts publicados</div></div>'
        +'<div class="w-stat-card"><div class="w-stat-value" style="color:'+((st.posts_without_meta||0)>0?'#fbbf24':'#34d399')+'">'+W.num(st.posts_without_meta||0)+'</div><div class="w-stat-label">Posts sin metadata</div></div>'
        +'</div>';
    if(!b.installed){
        html+='<div class="w-card" style="margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap"><div><strong style="font-size:13px">Brick no instalado en este sitio</strong><div style="font-size:11px;color:var(--w-muted);margin-top:4px">Actívalo para crear las tablas y comenzar a optimizar este sitio.</div></div><button class="w-btn w-btn-primary" onclick="wontia.sglActivate()">Activar Brick</button></div>';
    }
    html+='<div class="w-card" style="margin-bottom:14px"><h3>Acciones</h3>'
        +'<div style="display:flex;gap:8px;flex-wrap:wrap">'
        +'<button class="w-btn w-btn-primary" onclick="wontia.sglAction(\'scan\',this)">🔍 Scan completo</button>'
        +'<button class="w-btn w-btn-secondary" onclick="wontia.sglAction(\'generate\',this)">✨ Generar metadatos IA</button>'
        +'<button class="w-btn w-btn-secondary" onclick="wontia.sglAction(\'autofix\',this)">🩹 Auto-fix posts sin meta</button>'
        +'<button class="w-btn w-btn-secondary" onclick="wontia.sglAction(\'deepfix\',this)">🧠 Deep-fix contenido</button>'
        +'</div><div id="sgl-result" style="margin-top:12px"></div></div>';
    html+='<div class="w-brick-grid2" style="display:grid;grid-template-columns:1fr 1fr;gap:14px">'
        +'<div class="w-card"><h3>Problemas detectados</h3><div id="sgl-issues">Cargando...</div></div>'
        +'<div class="w-card"><h3>Historial de scores</h3><div id="sgl-scores">Cargando...</div></div>'
        +'</div>'
        +'<div class="w-card" style="margin-top:14px"><h3>🤖 Bots que visitan el sitio</h3><div id="sgl-bots">Cargando...</div></div>';
    app.innerHTML=html;
    W.sglLoad('issues','sgl-issues');
    W.sglLoad('scores','sgl-scores');
    W.sglLoad('bots','sgl-bots');
};

W.sglActivate=async function(){
    var r=await W.api('/api/v1/admin/seo-global-launch/activate',{method:'POST'});
    W.notify(r.message||(r.ok?'Brick activado':'Error'),r.ok?'success':'error');
    W.renderSeoGlobalLaunch();
};

W.sglAction=async function(what,btn){
    if(btn){btn.disabled=true;btn.style.opacity=.5}
    var el=document.getElementById('sgl-result');
    if(el)el.innerHTML='<div style="font-size:11px;color:var(--w-muted)">Ejecutando '+W.esc(what)+'... (puede tardar con IA)</div>';
    var r=await W.api('/api/v1/admin/seo-global-launch/'+what,{method:'POST'});
    if(btn){btn.disabled=false;btn.style.opacity=1}
    var msg='';
    if(what==='scan'&&r.ok)msg='Score total: <strong>'+r.total_score+'</strong> · '+r.pages_count+' páginas · '+r.issues_count+' issues · posts: '+r.posts_count;
    else if(what==='generate'&&r.ok)msg='Páginas optimizadas: <strong>'+r.pages_optimized+'</strong> · tokens: '+(r.tokens_used||0)+(r.ai_model?' · modelo: '+W.esc(r.ai_model)+' via '+W.esc(r.ai_provider||''):'');
    else if(what==='autofix'&&r.ok)msg='Posts corregidos: <strong>'+r.fixed+'</strong> de '+r.total+(r.ai_model?' · modelo: '+W.esc(r.ai_model):'');
    else if(what==='deepfix'&&r.ok)msg='Correcciones aplicadas: <strong>'+r.fixed+'</strong> · tokens: '+(r.tokens||0);
    else msg=(r.message||r.error||'Error desconocido');
    var color=r.ok?'#34d399':'#f87171';
    if(el)el.innerHTML='<div style="font-size:12px;color:'+color+'">'+msg+'</div>';
    if(r.ok){W.sglLoad('issues','sgl-issues');W.sglLoad('scores','sgl-scores');W.renderSeoGlobalLaunchStatsOnly&&setTimeout(function(){location.hash='#seo-global-launch'},800)}
};

W.sglLoad=async function(kind,elId){
    var el=document.getElementById(elId); if(!el)return;
    try{
        var r=await W.api('/api/v1/admin/seo-global-launch/'+kind);
        var d=r.data||r;
        if(kind==='issues'){
            var issues=d.issues||[];
            if(!issues.length){el.innerHTML='<div class="w-empty-state" style="padding:12px"><p>Sin problemas detectados 🎉</p></div>';return}
            var html='<div style="font-size:11px;color:var(--w-muted);margin-bottom:8px">'+issues.length+' problemas · '+d.high+' altos · '+d.medium+' medios</div>';
            issues.slice(0,12).forEach(function(i){
                var sev=i.severity==='high'?'#f87171':(i.severity==='medium'?'#fbbf24':'#8593ab');
                html+='<div style="display:flex;gap:8px;align-items:flex-start;padding:6px 0;border-bottom:1px solid var(--w-border)"><span style="width:8px;height:8px;border-radius:50%;background:'+sev+';margin-top:4px;flex-shrink:0"></span><div style="font-size:12px"><div>'+W.esc(i.issue)+'</div><div style="font-size:11px;color:var(--w-muted)">'+W.esc(i.title||'')+'</div></div></div>';
            });
            el.innerHTML=html;
        }else if(kind==='scores'){
            var scores=d.scores||[];
            if(!scores.length){el.innerHTML='<div class="w-empty-state" style="padding:12px"><p>Aún no hay scans.</p></div>';return}
            var html='';
            scores.slice(0,10).forEach(function(s){
                var c=s.total_score>=80?'#34d399':(s.total_score>=50?'#fbbf24':'#f87171');
                html+='<div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--w-border);font-size:12px"><span>'+W.esc(s.scan_date||'')+'</span><strong style="color:'+c+'">'+s.total_score+'</strong></div>';
            });
            el.innerHTML=html;
        }else if(kind==='bots'){
            var bots=d.bots||[];
            if(!bots.length){el.innerHTML='<div style="font-size:12px;color:var(--w-muted)">'+(d.note||'Sin visitas de bots recientes.')+'</div>';return}
            var html='<div style="display:flex;gap:8px;flex-wrap:wrap">';
            bots.forEach(function(bt){
                html+='<div style="background:var(--w-surface);border:1px solid var(--w-border);border-radius:8px;padding:8px 12px;font-size:12px"><span>'+bt.icon+'</span> <strong>'+W.esc(bt.name)+'</strong> <span style="color:var(--w-muted)">· '+bt.visits+' visitas'+(bt.last?' · '+W.esc(bt.last):'')+'</span></div>';
            });
            el.innerHTML=html+'</div>';
        }
    }catch(e){el.innerHTML='<div style="font-size:12px;color:#f87171">Error cargando datos</div>'}
};


W.sglTechLoad=async function(){
    var box=document.getElementById('sgl-tech'); if(!box)return;
    var r=await W.api('/api/v1/admin/seo-global-launch/technical');
    if(!r||!r.ok||!r.data){box.innerHTML='<div class="w-empty-state"><h3>No disponible</h3><p>'+W.esc((r&&r.message)||'No se pudo cargar el panel técnico.')+'</p></div>';return}
    var d=r.data||{},c=d.config||{},pages=d.pages||[];
    W._sglTech=d;
    var h='<div class="w-card" style="margin-bottom:14px"><div class="w-flex-between" style="gap:10px;flex-wrap:wrap"><div><h3 style="margin:0">SEO técnico</h3><p style="font-size:12px;color:var(--w-muted);margin:5px 0 0">Sitemap, robots, canonical y hreflang por sitio. Las integraciones externas quedan fuera de esta fase.</p></div><button class="w-btn w-btn-secondary w-btn-sm" onclick="W.sglTechClearCache()">Limpiar cache</button></div>'
        +'<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px"><a class="w-btn w-btn-secondary w-btn-sm" href="'+W.esc(d.sitemap_url||'#')+'" target="_blank" rel="noopener">Abrir sitemap</a><a class="w-btn w-btn-secondary w-btn-sm" href="'+W.esc(d.robots_url||'#')+'" target="_blank" rel="noopener">Abrir robots.txt</a></div>'
        +'<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:14px">'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Sitemap activo</label><select class="w-input" id="sgl-enable-sitemap" style="width:100%"><option value="1"'+(c.enable_sitemap?' selected':'')+'>Sí</option><option value="0"'+(c.enable_sitemap?'':' selected')+'>No</option></select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">robots.txt activo</label><select class="w-input" id="sgl-enable-robots" style="width:100%"><option value="1"'+(c.enable_robots?' selected':'')+'>Sí</option><option value="0"'+(c.enable_robots?'':' selected')+'>No</option></select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Canonical administrable</label><select class="w-input" id="sgl-enable-canonical" style="width:100%"><option value="1"'+(c.enable_canonical?' selected':'')+'>Sí</option><option value="0"'+(c.enable_canonical?'':' selected')+'>No</option></select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Hreflang activo</label><select class="w-input" id="sgl-enable-hreflang" style="width:100%"><option value="1"'+(c.enable_hreflang?' selected':'')+'>Sí</option><option value="0"'+(c.enable_hreflang?'':' selected')+'>No</option></select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Cache SEO</label><select class="w-input" id="sgl-enable-cache" style="width:100%"><option value="1"'+(c.enable_cache?' selected':'')+'>Sí</option><option value="0"'+(c.enable_cache?'':' selected')+'>No</option></select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">TTL cache (segundos)</label><input class="w-input" type="number" min="60" max="900" id="sgl-cache-ttl" value="'+(parseInt(c.sitemap_cache_ttl)||600)+'" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Crawl-delay</label><input class="w-input" type="number" min="0" max="120" id="sgl-crawl" value="'+(parseInt(c.robots_crawl_delay)||0)+'" style="width:100%"/></div>'
        +'</div><div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px"><div><label style="font-size:11px;color:var(--w-muted)">Robots: Allow (una ruta por línea)</label><textarea class="w-input" id="sgl-robots-allow" rows="3" style="width:100%">'+W.esc(c.robots_allow||'')+'</textarea></div><div><label style="font-size:11px;color:var(--w-muted)">Robots: Disallow (una ruta por línea)</label><textarea class="w-input" id="sgl-robots-disallow" rows="3" style="width:100%">'+W.esc(c.robots_disallow||'')+'</textarea></div></div>'
        +'<div style="margin-top:12px"><button class="w-btn w-btn-primary" onclick="W.sglTechSaveConfig()">Guardar configuración técnica</button><span id="sgl-tech-msg" role="status" aria-live="polite" style="font-size:11px;margin-left:8px;color:var(--w-muted)"></span></div></div>';
    h+='<div class="w-card"><h3 style="margin:0 0 8px">Canonical · robots · hreflang</h3><p style="font-size:12px;color:var(--w-muted);margin:0 0 12px">Edita la configuración por URL. Hreflang: una línea por idioma con formato <code>es|https://...</code>.</p>';
    if(!pages.length){h+='<div class="w-empty-state"><h3>Sin URLs</h3><p>Publica páginas o posts para administrar su SEO técnico.</p></div>';}
    else{
        h+='<div style="overflow:auto"><table class="w-table"><thead><tr><th>URL</th><th>Canonical</th><th>Robots</th><th>Hreflang</th><th></th></tr></thead><tbody>';
        pages.forEach(function(p,i){
            var langs=Object.keys(p.hreflang||{}).map(function(k){return k+'|'+(p.hreflang[k]||'')}).join('\n');
            h+='<tr><td style="max-width:260px;word-break:break-all"><b>'+W.esc(p.title||'')+'</b><div style="font-size:10px;color:var(--w-muted)">'+W.esc(p.url)+'</div></td>'
              +'<td><input class="w-input" id="sgl-canonical-'+i+'" value="'+W.esc(p.canonical_url||'')+'" placeholder="Automática" style="min-width:180px;font-size:11px"/></td>'
              +'<td><select class="w-input" id="sgl-robots-'+i+'" style="font-size:11px"><option value=""'+(p.robots?'':' selected')+'>Automático</option><option value="index,follow"'+(p.robots==='index,follow'?' selected':'')+'>index,follow</option><option value="noindex,nofollow"'+(p.robots==='noindex,nofollow'?' selected':'')+'>noindex,nofollow</option><option value="index,nofollow"'+(p.robots==='index,nofollow'?' selected':'')+'>index,nofollow</option><option value="noindex,follow"'+(p.robots==='noindex,follow'?' selected':'')+'>noindex,follow</option></select></td>'
              +'<td><textarea class="w-input" id="sgl-lang-'+i+'" rows="2" style="min-width:190px;font-size:11px">'+W.esc(langs)+'</textarea></td>'
              +'<td><button class="w-btn w-btn-secondary w-btn-sm" onclick="W.sglTechSavePage('+i+')">Guardar</button></td></tr>';
        });
        h+='</tbody></table></div>';
    }
    h+='</div>';
    box.innerHTML=h;
};
W.sglTechSaveConfig=async function(){
    var v=function(id){var el=document.getElementById(id);return el?el.value:''};
    var payload={enable_sitemap:v('sgl-enable-sitemap')==='1',enable_robots:v('sgl-enable-robots')==='1',enable_canonical:v('sgl-enable-canonical')==='1',enable_hreflang:v('sgl-enable-hreflang')==='1',enable_cache:v('sgl-enable-cache')==='1',sitemap_cache_ttl:parseInt(v('sgl-cache-ttl'))||600,robots_crawl_delay:parseInt(v('sgl-crawl'))||0,robots_allow:v('sgl-robots-allow'),robots_disallow:v('sgl-robots-disallow')};
    var r=await W.api('/api/v1/admin/seo-global-launch/config',{method:'POST',body:payload});
    var msg=document.getElementById('sgl-tech-msg');
    if(r&&r.ok){if(msg){msg.textContent='Configuración guardada';msg.style.color='#0d7a58'}W.notify('Configuración técnica guardada','success');W.sglTechLoad()}else if(msg){msg.textContent=(r&&r.message)||'No se pudo guardar';msg.style.color='#b42318'}
};
W.sglTechSavePage=async function(i){
    var d=W._sglTech||{},p=(d.pages||[])[i];if(!p)return;
    var r=await W.api('/api/v1/admin/seo-global-launch/technical',{method:'POST',body:{url:p.url,page_type:p.type,canonical_url:document.getElementById('sgl-canonical-'+i).value,robots:document.getElementById('sgl-robots-'+i).value,hreflang:document.getElementById('sgl-lang-'+i).value}});
    if(r&&r.ok){W.notify('Configuración guardada','success');W.sglTechLoad()}else W.notify((r&&r.message)||'No se pudo guardar','error');
};
W.sglTechClearCache=async function(){
    var r=await W.api('/api/v1/admin/seo-global-launch/cache/clear',{method:'POST',body:{}});
    if(r&&r.ok){W.notify(r.message||'Cache eliminada','success');W.sglTechLoad()}else W.notify((r&&r.message)||'No se pudo limpiar','error');
};

W.sglAnalyticsDays=30;
W._sglKwFilters={q:'',status:'',type:''};W._sglKwAfter=0;W._sglKwPage=1;
W.sglKeywordsLoad=async function(){
    var box=document.getElementById('sgl-keywords');if(!box)return;
    var f=W._sglKwFilters||{q:'',status:'',type:''};
    var qs='?q='+encodeURIComponent(f.q||'')+'&status='+encodeURIComponent(f.status||'')+'&type='+encodeURIComponent(f.type||'')+'&page='+(W._sglKwPage||1);
    var r=await W.api('/api/v1/admin/seo-global-launch/keywords'+qs);
    if(!r||!r.ok){box.innerHTML='<div class="w-empty-state"><h3>Keywords no disponible</h3><p>'+W.esc((r&&r.message)||'No se pudo cargar.')+'</p></div>';return}
    var d=r.data||{},s=d.stats||{},items=d.items||[];
    var h='<div class="w-card" style="margin-bottom:14px"><div class="w-flex-between" style="gap:10px;flex-wrap:wrap"><div><h3 style="margin:0">Keywords y palabras clave</h3><p style="font-size:11px;color:var(--w-muted);margin:4px 0 0">Los keywords no son un factor de ranking de Google. Se publican solo tras aprobación y nunca modifican el contenido visible.</p></div><div style="display:flex;gap:6px;flex-wrap:wrap"><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglKeywordsPreview(this)">Previsualizar 10</button><button class="w-btn w-btn-primary w-btn-sm" type="button" onclick="W.sglKeywordsGenerate(this)">Generar 50 borradores</button></div></div>';
    h+='<div class="sgl-live__stats" style="margin-top:12px">'+W.sglLiveStat(d.catalog_products||0,'Productos catálogo')+W.sglLiveStat(s.products||0,'Con keywords')+W.sglLiveStat(s.approved||0,'Aprobadas','#34d399')+W.sglLiveStat(s.draft||0,'Borradores','#fbbf24')+W.sglLiveStat((d.coverage||0)+'%','Cobertura')+'</div>';
    h+='<div style="font-size:10px;color:var(--w-muted);margin-top:8px">Máximo '+W.esc(d.limit||8)+' términos por entidad. La generación automática solo crea borradores; no los publica.</div></div>';
    h+='<div class="w-card" style="margin-bottom:14px"><h3 style="margin:0 0 8px">Añadir o editar manualmente</h3><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:8px">'
        +'<div><label style="font-size:10px;color:var(--w-muted)">Tipo</label><select class="w-select" id="sgl-kw-type-new"><option value="product">Producto</option><option value="page">Página</option></select></div>'
        +'<div><label style="font-size:10px;color:var(--w-muted)">Ruta o URL</label><input class="w-input" id="sgl-kw-path-new" placeholder="/producto/mi-producto o home"/></div>'
        +'<div><label style="font-size:10px;color:var(--w-muted)">Título</label><input class="w-input" id="sgl-kw-title-new" placeholder="Nombre visible"/></div>'
        +'<div style="grid-column:1/-1"><label style="font-size:10px;color:var(--w-muted)">Keywords (separadas por coma; máximo 8)</label><textarea class="w-input" id="sgl-kw-list-new" rows="2" placeholder="guitarra electrica, guitarra electrica fender"></textarea></div>'
        +'<div><label style="font-size:10px;color:var(--w-muted)">Guardar como</label><select class="w-select" id="sgl-kw-status-new"><option value="draft">Borrador</option><option value="approved">Aprobado y visible</option></select></div>'
        +'<div style="display:flex;align-items:end"><button class="w-btn w-btn-primary" type="button" onclick="W.sglKeywordsSave(this)">Guardar</button></div></div><div id="sgl-kw-msg" style="font-size:11px;color:var(--w-muted);margin-top:8px"></div></div>';
    h+='<div class="w-card"><div style="display:flex;gap:6px;flex-wrap:wrap;align-items:end;margin-bottom:10px"><div><label style="font-size:10px;color:var(--w-muted)">Buscar</label><input class="w-input" id="sgl-kw-q" value="'+W.esc(f.q||'')+'" placeholder="producto, ruta o keyword" onkeydown="if(event.key===\'Enter\')W.sglKeywordsFilter()"/></div><div><label style="font-size:10px;color:var(--w-muted)">Estado</label><select class="w-select" id="sgl-kw-status" onchange="W.sglKeywordsFilter()"><option value="">Todos</option><option value="draft"'+(f.status==='draft'?' selected':'')+'>Borrador</option><option value="approved"'+(f.status==='approved'?' selected':'')+'>Aprobado</option><option value="rejected"'+(f.status==='rejected'?' selected':'')+'>Rechazado</option></select></div><div><label style="font-size:10px;color:var(--w-muted)">Tipo</label><select class="w-select" id="sgl-kw-type" onchange="W.sglKeywordsFilter()"><option value="">Todos</option><option value="product"'+(f.type==='product'?' selected':'')+'>Producto</option><option value="page"'+(f.type==='page'?' selected':'')+'>Página</option></select></div><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglKeywordsFilter()">Aplicar</button><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglKeywordsBulk(\'approved\',this)">Aprobar seleccionadas</button><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglKeywordsBulk(\'rejected\',this)">Rechazar seleccionadas</button></div>';
    h+='<div style="font-size:11px;color:var(--w-muted);margin-bottom:8px">'+W.esc(d.total||0)+' registros · página '+W.esc(d.page||1)+'</div>';
    if(!items.length){h+='<div class="w-empty-state"><h3>Sin keywords</h3><p>Genera borradores desde el catálogo o añade una página manualmente.</p></div>'}
    else{h+='<div style="overflow:auto"><table style="width:100%;border-collapse:collapse;font-size:11px"><thead><tr style="text-align:left;color:var(--w-muted)"><th style="padding:6px"><input type="checkbox" onclick="W.sglKeywordsSelectAll(this)"/></th><th style="padding:6px">Entidad</th><th style="padding:6px">Keywords</th><th style="padding:6px">Origen</th><th style="padding:6px">Estado</th><th style="padding:6px">Acciones</th></tr></thead><tbody>';
        items.forEach(function(x){var kws=Array.isArray(x.keywords)?x.keywords:[];var badge=x.status==='approved'?'#34d399':(x.status==='draft'?'#fbbf24':'#fb7185');h+='<tr style="border-top:1px solid var(--w-border);vertical-align:top"><td style="padding:6px"><input type="checkbox" class="sgl-kw-check" data-kw-id="'+W.esc(x.id)+'"/></td><td style="padding:6px"><b>'+W.esc(x.title||x.entity_key)+'</b><div style="color:var(--w-muted);font-size:10px;margin-top:2px">'+W.esc(x.entity_type)+' · /'+W.esc(x.entity_key)+'</div></td><td style="padding:6px;max-width:360px">'+kws.map(function(k){return '<span style="display:inline-block;background:var(--w-bg);border-radius:6px;padding:2px 5px;margin:1px">'+W.esc(k)+'</span>'}).join(' ')+'</td><td style="padding:6px">'+W.esc(x.source)+'</td><td style="padding:6px;color:'+badge+';font-weight:700">'+W.esc(x.status)+'</td><td style="padding:6px;white-space:nowrap"><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglKeywordsEdit('+W.esc(x.id)+')">Editar</button> <button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglKeywordsSetStatus('+W.esc(x.id)+',\'approved\')">Aprobar</button> <button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglKeywordsSetStatus('+W.esc(x.id)+',\'rejected\')">Rechazar</button></td></tr>'});h+='</tbody></table></div>'}
    var pages=Math.max(1,Math.ceil((d.total||0)/(d.per_page||50)));var current=d.page||1;
    h+='<div style="display:flex;gap:6px;align-items:center;margin-top:10px"><button class="w-btn w-btn-secondary w-btn-sm" type="button" '+(current<=1?'disabled':'')+' onclick="W.sglKeywordsPage('+(current-1)+')">Anterior</button><span style="font-size:11px;color:var(--w-muted)">Página '+current+' de '+pages+'</span><button class="w-btn w-btn-secondary w-btn-sm" type="button" '+(current>=pages?'disabled':'')+' onclick="W.sglKeywordsPage('+(current+1)+')">Siguiente</button></div>';
    h+='<div id="sgl-kw-samples" style="font-size:11px;color:var(--w-muted);margin-top:10px"></div></div>';
    box.innerHTML=h;W._sglKwItems=items;
};
W.sglKeywordsEdit=function(id){
    var x=(W._sglKwItems||[]).filter(function(v){return String(v.id)===String(id)})[0];if(!x)return;
    var set=function(field,value){var el=document.getElementById(field);if(el)el.value=value||''};
    set('sgl-kw-type-new',x.entity_type);set('sgl-kw-path-new',x.entity_key);set('sgl-kw-title-new',x.title);set('sgl-kw-list-new',(x.keywords||[]).join(', '));set('sgl-kw-status-new',x.status==='approved'?'approved':'draft');
    var path=document.getElementById('sgl-kw-path-new');if(path)path.scrollIntoView({behavior:'smooth',block:'center'});
};
W.sglKeywordsFilter=function(){var v=function(id){var e=document.getElementById(id);return e?e.value:''};W._sglKwFilters={q:v('sgl-kw-q'),status:v('sgl-kw-status'),type:v('sgl-kw-type')};W._sglKwPage=1;W.sglKeywordsLoad()};
W.sglKeywordsPage=function(page){W._sglKwPage=Math.max(1,parseInt(page)||1);W.sglKeywordsLoad()};
W.sglKeywordsSave=async function(btn){
    var v=function(id){var e=document.getElementById(id);return e?e.value:''};
    var payload={entity_type:v('sgl-kw-type-new'),path:v('sgl-kw-path-new'),title:v('sgl-kw-title-new'),keywords:v('sgl-kw-list-new'),status:v('sgl-kw-status-new')};
    if(btn)btn.disabled=true;var r=await W.api('/api/v1/admin/seo-global-launch/keywords',{method:'POST',body:payload});if(btn)btn.disabled=false;
    var msg=document.getElementById('sgl-kw-msg');if(msg){msg.textContent=(r&&r.message)||(r&&r.ok?'Guardado':'No se pudo guardar');msg.style.color=r&&r.ok?'#0d7a58':'#b42318'}
    if(r&&r.ok){W.notify(r.message||'Keywords guardadas','success');W.sglKeywordsLoad()}
};
W.sglKeywordsGenerate=async function(btn){
    if(btn)btn.disabled=true;var r=await W.api('/api/v1/admin/seo-global-launch/keywords/generate',{method:'POST',body:{after_id:W._sglKwAfter||0,limit:50}});if(btn)btn.disabled=false;
    if(!r||!r.ok){W.notify((r&&r.message)||'No se pudo generar','error');return}
    W._sglKwAfter=r.last_id||W._sglKwAfter;W.notify('Borradores: '+(r.created||0)+' · revisados: '+(r.processed||0),'success');
    if(r.samples&&r.samples.length){var box=document.getElementById('sgl-kw-samples');if(box)box.innerHTML='<b>Muestra generada:</b><pre style="white-space:pre-wrap;margin:6px 0">'+W.esc(JSON.stringify(r.samples,null,2))+'</pre>'}
    W.sglKeywordsLoad();
};
W.sglKeywordsPreview=async function(btn){
    if(btn)btn.disabled=true;var r=await W.api('/api/v1/admin/seo-global-launch/keywords/generate',{method:'POST',body:{preview:true,limit:10}});if(btn)btn.disabled=false;
    var box=document.getElementById('sgl-kw-samples');if(box){box.innerHTML='<b>Previsualización:</b><pre style="white-space:pre-wrap;margin:6px 0">'+W.esc(JSON.stringify((r&&r.data&&r.data.items)||[],null,2))+'</pre>'}
};
W.sglKeywordsSetStatus=async function(id,status){
    var r=await W.api('/api/v1/admin/seo-global-launch/keywords/'+(status==='approved'?'approve':'reject'),{method:'POST',body:{ids:[id]}});
    if(r&&r.ok){W.notify(status==='approved'?'Keyword aprobada':'Keyword rechazada','success');W.sglKeywordsLoad()}else W.notify((r&&r.message)||'No se pudo actualizar','error')
};
W.sglKeywordsBulk=async function(status,btn){
    var ids=Array.prototype.slice.call(document.querySelectorAll('.sgl-kw-check:checked')).map(function(e){return parseInt(e.getAttribute('data-kw-id'))}).filter(function(x){return x>0});
    if(!ids.length){W.notify('Selecciona al menos una keyword','info');return}
    if(!window.confirm('¿'+ (status==='approved'?'Aprobar':'Rechazar') +' '+ids.length+' keywords? Solo cambia el metadato, no el contenido.')) return;
    if(btn)btn.disabled=true;var r=await W.api('/api/v1/admin/seo-global-launch/keywords/'+(status==='approved'?'approve':'reject'),{method:'POST',body:{ids:ids}});if(btn)btn.disabled=false;
    if(r&&r.ok){W.notify('Actualizadas: '+(r.updated||0),'success');W.sglKeywordsLoad()}else W.notify((r&&r.message)||'No se pudo actualizar','error')
};
W.sglKeywordsSelectAll=function(el){document.querySelectorAll('.sgl-kw-check').forEach(function(x){x.checked=el.checked})};
W.sglAnalyticsLoad=async function(){
    var box=document.getElementById('sgl-analytics'); if(!box)return;
    var days=W.sglAnalyticsDays||30;
    var a=await W.api('/api/v1/admin/seo-global-launch/analytics?days='+days);
    var o=await W.api('/api/v1/admin/seo-global-launch/opportunities?days='+days);
    if(!a||!a.ok){box.innerHTML='<div class="w-empty-state"><h3>Sin datos</h3><p>'+W.esc((a&&a.message)||'No se pudo cargar la analítica.')+'</p></div>';return}
    W._sglAnalytics=a; W._sglOpp=(o&&o.items)||[];
    var t=a.traffic||{},ord=a.orders||{},cart=a.cart||{},seo=a.seo||{},fn=a.funnel||{};
    var kpi=function(v,l,color){return '<div class="w-card" style="flex:1;min-width:130px;text-align:center;margin:0"><div style="font-size:22px;font-weight:800;'+(color?'color:'+color:'')+'">'+W.esc(v)+'</div><div style="font-size:10px;color:var(--w-muted);margin-top:4px">'+W.esc(l)+'</div></div>'};
    var h='<div class="w-card" style="margin-bottom:14px"><div class="w-flex-between" style="gap:10px;flex-wrap:wrap"><div><h3 style="margin:0">Analítica del sitio</h3><p style="font-size:12px;color:var(--w-muted);margin:5px 0 0">Datos de los últimos '+days+' días. Excluye tráfico propio y bots. Visitantes únicos y páginas se calculan con el monitor de peticiones (90 días); los eventos y carritos de invitados solo se registran con consentimiento.</p></div><div style="display:flex;gap:6px;flex-wrap:wrap">'+(W._sglOpp.length?'<span class="w-badge" style="background:#fff7ed;color:#b45309">'+W._sglOpp.length+' oportunidades</span>':'')+'<select class="w-input" style="width:100px" onchange="W.sglAnalyticsDays=parseInt(this.value)||30;W.sglAnalyticsLoad()"><option value="7"'+(days===7?' selected':'')+'>7 días</option><option value="30"'+(days===30?' selected':'')+'>30 días</option><option value="90"'+(days===90?' selected':'')+'>90 días</option></select><button class="w-btn w-btn-secondary w-btn-sm" onclick="W.sglAnalyticsLoad()">Actualizar</button></div></div>'
        +'<div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px">'+kpi(W.num(t.sessions||0),'Sesiones')+kpi(W.num(t.views||0),'Visitas')+kpi(W.num(t.pages_per_session||0),'Páginas/sesión')+kpi(W.num(ord.paid||0),'Pedidos pagados','#0d7a58')+kpi(W.ocMoney(ord.revenue_cents||0,'COP'),'Ingresos')+kpi((cart.abandonment_rate||0)+'%','Abandono de pedidos','#b45309')+'</div>'
        +'<div style="display:flex;gap:8px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-top:10px;padding-top:10px;border-top:1px solid var(--w-border)"><div style="font-size:11px;color:var(--w-muted)"><b style="color:var(--w-text)">Tráfico propio excluido:</b> '+W.num(t.internal_page_requests||0)+' vistas de servidor y '+W.num(t.internal_sessions||0)+' sesiones de evento marcadas como “yo”. No se suman a clientes ni a la pauta.</div><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="location.hash=\'#seo-global-launch/mi-trafico\'">Abrir Mi tráfico</button></div></div>';
    var table=function(title,rows,cols){
        var x='<div class="w-card"><h3 style="margin:0 0 8px">'+title+'</h3>';
        if(!rows||!rows.length)return x+'<div style="font-size:12px;color:var(--w-muted)">Sin datos en el período.</div></div>';
        x+='<div style="overflow:auto"><table class="w-table"><thead><tr>'+cols.map(function(c){return '<th>'+W.esc(c[0])+'</th>'}).join('')+'</tr></thead><tbody>';
        rows.forEach(function(r){x+='<tr>'+cols.map(function(c){return '<td style="max-width:300px;word-break:break-all">'+W.esc(c[1](r))+'</td>'}).join('')+'</tr>'});
        return x+'</tbody></table></div></div>';
    };
    h+='<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">'
        +table('Dispositivos',a.devices||[],[['Dispositivo',function(r){return r.device||'—'}],['Visitas',function(r){return W.num(r.visits||0)}]])
        +table('Páginas más visitadas',a.top_pages||[],[['Página',function(r){return r.page_url||'—'}],['Visitas',function(r){return W.num(r.views||0)}],['Únicos',function(r){return W.num(r.visitors||0)}]])
        +'</div>';
    h+='<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">'
        +table('Productos vendidos',a.products||[],[['Producto',function(r){return r.name||'—'}],['Unidades',function(r){return W.num(r.qty||0)}],['Monto',function(r){return W.ocMoney(r.amount_cents||0,'COP')}]])
        +table('Categorías vendidas',a.categories||[],[['Categoría',function(r){return r.category||'—'}],['Unidades',function(r){return W.num(r.qty||0)}],['Monto',function(r){return W.ocMoney(r.amount_cents||0,'COP')}]])
        +'</div>';
    h+='<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">';
    h+='<div class="w-card"><h3 style="margin:0 0 8px">Embudo</h3><div style="display:flex;gap:8px;flex-wrap:wrap">'+kpi(W.num(fn.product_view||0),'Producto visto')+kpi(W.num(fn.cart_add||0),'Al carrito')+kpi(W.num(fn.checkout_start||0),'Checkout')+kpi(W.num(fn.purchase||0),'Compra')+'</div><div style="font-size:11px;color:var(--w-muted);margin-top:8px">Solo sesiones con consentimiento y eventos instrumentados.</div></div>';
    h+='<div class="w-card"><h3 style="margin:0 0 8px">Carrito y checkout</h3><div style="font-size:12px;line-height:1.8"><div><b>Carritos guardados:</b> '+W.num(cart.tracked_carts||0)+'</div><div><b>Valor guardado:</b> '+W.ocMoney(cart.value_cents||0,'COP')+'</div><div><b>Carritos de invitados:</b> '+W.num(cart.guest_carts||0)+' · '+W.num(cart.guest_items||0)+' ítems</div><div><b>Valor invitados:</b> '+W.ocMoney(cart.guest_value_cents||0,'COP')+'</div><div><b>Abandonos en checkout:</b> '+W.num(cart.checkout_abandons||0)+'</div><div><b>Abandonados +1h:</b> '+W.num(cart.abandoned_after_1h||0)+'</div><div><b>Pedidos pendientes:</b> '+W.num(ord.pending||0)+'</div><div><b>Pedidos fallidos:</b> '+W.num(ord.failed||0)+'</div><div style="font-size:11px;color:var(--w-muted);margin-top:5px">'+W.esc(cart.note||'')+'</div></div></div></div>';
    h+='<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">'
        +table('Enlaces más clicados',a.top_links||[],[['Destino',function(r){return r.target||'—'}],['Clics',function(r){return W.num(r.clicks||0)}]])
        +table('Actividad por hora',a.hourly||[],[['Hora',function(r){return String(r.hour).padStart(2,'0')+':00'}],['Visitas',function(r){return W.num(r.views||0)}]])
        +'</div>';
    h+='<div class="w-card" style="margin-bottom:14px"><h3 style="margin:0 0 8px">Oportunidades y acciones</h3>';
    if(!W._sglOpp.length)h+='<div style="font-size:12px;color:var(--w-muted)">No hay problemas críticos detectados en este período.</div>';
    else{W._sglOpp.forEach(function(x){h+='<div style="display:flex;gap:10px;align-items:flex-start;justify-content:space-between;border-bottom:1px solid var(--w-border);padding:9px 0"><div><b style="font-size:12px">'+W.esc(x.title)+'</b><div style="font-size:11px;color:var(--w-muted);margin-top:3px">'+W.esc(x.detail)+'</div></div><button class="w-btn w-btn-secondary w-btn-sm" onclick="W.sglOppGo(\''+W.esc(x.action)+'\')">Resolver</button></div>'})}
    h+='</div>';
    var sen=a.sentiment||{}; h+='<div class="w-card" style="border-left:3px solid #cbd5e1"><h3 style="margin:0 0 6px">Sentimiento y causas de abandono</h3>'+(sen.available?'<div style="font-size:12px;line-height:1.8"><div><b>Reseñas analizadas:</b> '+W.num(sen.count||0)+'</div><div><b>Positivas:</b> '+W.num(sen.positive||0)+' ('+W.num(sen.positive_pct||0)+'%)</div><div><b>Neutrales:</b> '+W.num(sen.neutral||0)+'</div><div><b>Negativas:</b> '+W.num(sen.negative||0)+' ('+W.num(sen.negative_pct||0)+'%)</div><div style="font-size:11px;color:var(--w-muted);margin-top:5px">'+W.esc(sen.reason||'')+'</div></div>':'<p style="font-size:12px;color:var(--w-muted);margin:0">'+W.esc(sen.reason||'No hay fuente conectada.')+'</p>')+'<p style="font-size:11px;color:var(--w-muted);margin:8px 0 0">Las causas de abandono requieren encuesta o motivos declarados; no se infieren como hechos.</p></div>';
    box.innerHTML=h;
};
// ── Mi tráfico (yo) ─────────────────────────────
W.sglInternalDays=7;
W.sglShortDate=function(v){if(!v)return '—';return String(v).replace('T',' ').slice(0,16)};
W.sglKpi=function(v,l,color){return '<div class="w-card" style="flex:1;min-width:130px;text-align:center;margin:0"><div style="font-size:22px;font-weight:800;'+(color?'color:'+color:'')+'">'+W.esc(String(v))+'</div><div style="font-size:10px;color:var(--w-muted);margin-top:4px">'+W.esc(l)+'</div></div>'};
W.sglInternalLoad=async function(){
    var box=document.getElementById('sgl-internal'); if(!box)return;
    var days=W.sglInternalDays||7;
    var r=await W.api('/api/v1/admin/seo-global-launch/internal?days='+days);
    if(!r||!r.ok||!r.data){box.innerHTML='<div class="w-empty-state"><h3>Mi tráfico no disponible</h3><p>'+W.esc((r&&r.message)||'No se pudo cargar el aislamiento de tráfico.')+'</p></div>';return}
    var d=r.data,cur=d.current||{},cl=d.client||{},it=d.internal||{},sh=d.share||{};
    var statusColor=cur.internal?'#0d7a58':'#b45309';
    var statusText=cur.internal?'Este equipo está aislado como tráfico propio':'Este equipo aún no está marcado';
    var h='<div class="w-card" style="margin-bottom:14px;border-left:4px solid '+statusColor+'"><div class="w-flex-between" style="gap:10px;flex-wrap:wrap"><div><div style="font-size:15px;font-weight:800;color:'+statusColor+'">'+W.esc(statusText)+'</div><div style="font-size:12px;color:var(--w-muted);margin-top:5px">'+(cur.internal?W.esc(cur.reason_label||cur.reason||'detectado'):'Marca este dispositivo para que tus visitas no cuenten como clientes, incluso si cierras la sesión de administrador.')+'</div><div style="font-size:11px;color:var(--w-muted);margin-top:5px">IP actual: <b>'+W.esc(cur.ip||'—')+'</b> · Sesión admin: <b>'+(cur.admin_session?'Sí':'No')+'</b> · Cookie de equipo: <b>'+(cur.cookie_marked?'Sí':'No')+'</b></div></div><div style="display:flex;gap:6px;flex-wrap:wrap;align-items:flex-start"><select class="w-input" style="width:104px" onchange="W.sglInternalDays=parseInt(this.value)||7;W.sglInternalLoad()"><option value="1"'+(days===1?' selected':'')+'>24 h</option><option value="7"'+(days===7?' selected':'')+'>7 días</option><option value="30"'+(days===30?' selected':'')+'>30 días</option><option value="90"'+(days===90?' selected':'')+'>90 días</option></select><button class="w-btn w-btn-primary" type="button" onclick="W.sglInternalMark()">Marcar este dispositivo</button>'+(cur.cookie_marked?'<button class="w-btn w-btn-secondary" type="button" onclick="W.sglInternalUnmark()">Desmarcar</button>':'')+'<button class="w-btn w-btn-secondary" type="button" onclick="W.sglInternalAddIp()">Añadir mi IP actual</button></div></div></div>';
    h+='<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px">'+W.sglKpi(W.num(cl.requests||0),'Solicitudes clientes')+W.sglKpi(W.num(cl.page_views||0),'Vistas clientes')+W.sglKpi(W.num(cl.visitors||0),'Visitantes clientes')+W.sglKpi(W.num(it.requests||0),'Solicitudes mías','#b45309')+W.sglKpi(W.num(it.page_views||0),'Vistas mías','#b45309')+W.sglKpi((sh.page_views_pct||0)+'%','% de mis vistas','#b45309')+W.sglKpi(W.num(d.bots||0),'Bots (aparte)')+'</div>';
    h+='<div class="w-card" style="margin-bottom:14px"><h3 style="margin:0 0 8px">Cómo se detecta “yo”</h3><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;font-size:12px"><div style="background:var(--w-surface);border:1px solid var(--w-border);border-radius:8px;padding:10px"><b>1 · Sesión de administrador</b><div style="color:var(--w-muted);margin-top:3px">Si estás logueado en el admin, tus visitas se marcan automáticamente.</div></div><div style="background:var(--w-surface);border:1px solid var(--w-border);border-radius:8px;padding:10px"><b>2 · Dispositivo firmado</b><div style="color:var(--w-muted);margin-top:3px">Cookie HMAC de larga duración. Es el sustituto real de la MAC, que no viaja en HTTP.</div></div><div style="background:var(--w-surface);border:1px solid var(--w-border);border-radius:8px;padding:10px"><b>3 · IP / CIDR</b><div style="color:var(--w-muted);margin-top:3px">Allowlist para tu red. Evita rangos amplios porque podrías ocultar clientes reales.</div></div></div><div style="font-size:11px;color:var(--w-muted);margin-top:8px">'+W.esc(d.mac_note||'')+'</div></div>';
    h+='<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">';
    h+='<div class="w-card"><h3 style="margin:0 0 8px">Motivos de detección</h3>';
    if(!(d.reasons||[]).length)h+='<div style="font-size:12px;color:var(--w-muted)">Aún no hay tráfico propio en este período.</div>';
    else{h+='<table class="w-table"><thead><tr><th>Motivo</th><th>Solicitudes</th><th>Vistas</th></tr></thead><tbody>';d.reasons.forEach(function(x){h+='<tr><td>'+W.esc(x.label||x.reason||'—')+'</td><td>'+W.num(x.requests||0)+'</td><td>'+W.num(x.page_views||0)+'</td></tr>'});h+='</tbody></table>'}
    h+='</div><div class="w-card"><h3 style="margin:0 0 8px">Eventos propios</h3><div class="sgl-live__stats">'+W.sglLiveStat(W.num(it.events||0),'Eventos')+W.sglLiveStat(W.num(it.sessions||0),'Sesiones')+W.sglLiveStat(W.num(it.event_views||0),'Vistas')+W.sglLiveStat(W.num(it.interactions||0),'Clics')+W.sglLiveStat(W.num(it.submits||0),'Formularios')+W.sglLiveStat(W.num(it.analytics_views||0),'Vistas analytics')+'</div><div style="font-size:11px;color:var(--w-muted);margin-top:8px">Los eventos requieren consentimiento de analítica; las solicitudes del servidor se miden siempre.</div><div style="font-size:12px;margin-top:8px"><b>Pedidos internos:</b> '+W.num(it.orders||0)+'</div></div></div>';
    h+='<div class="w-card" style="margin-bottom:14px"><div class="w-flex-between" style="gap:10px;flex-wrap:wrap"><div><h3 style="margin:0 0 6px">Allowlist de IP / CIDR</h3><div style="font-size:11px;color:var(--w-muted)">Solo se excluyen las IPs de esta lista. La IP actual se añade como /32 o /128.</div></div><div style="display:flex;gap:6px;flex-wrap:wrap"><input class="w-input" id="sgl-net-cidr" placeholder="190.84.116.204 o 190.84.0.0/16" style="min-width:220px"/><input class="w-input" id="sgl-net-label" placeholder="Etiqueta (casa, oficina)" style="min-width:150px"/><button class="w-btn w-btn-primary" type="button" onclick="W.sglInternalAddNetwork()">Añadir red</button></div></div>';
    if(!(d.networks||[]).length)h+='<div style="font-size:12px;color:var(--w-muted);margin-top:10px">Sin IPs permitidas. Marca el dispositivo o añade tu IP actual.</div>';
    else{h+='<div style="overflow:auto;margin-top:10px"><table class="w-table"><thead><tr><th>CIDR</th><th>Etiqueta</th><th>Creada</th><th></th></tr></thead><tbody>';d.networks.forEach(function(n){h+='<tr><td><b>'+W.esc(n.cidr)+'</b></td><td>'+W.esc(n.label||'—')+'</td><td>'+W.esc(W.sglShortDate(n.created_at))+'</td><td><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglInternalDeleteNetwork('+n.id+')">Quitar</button></td></tr>'});h+='</tbody></table></div>'}
    h+='<div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-top:10px"><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglInternalBackfill(true)">Previsualizar históricos</button><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglInternalBackfill(false)">Reclasificar históricos</button><span id="sgl-backfill-msg" style="font-size:11px;color:var(--w-muted)"></span></div></div>';
    h+='<div class="w-card" style="margin-bottom:14px"><h3 style="margin:0 0 8px">Dispositivos marcados</h3>';
    if(!(d.devices||[]).length)h+='<div style="font-size:12px;color:var(--w-muted)">Aún no hay dispositivos marcados.</div>';
    else{h+='<div style="overflow:auto"><table class="w-table"><thead><tr><th>Etiqueta</th><th>Estado</th><th>Último uso</th><th></th></tr></thead><tbody>';d.devices.forEach(function(x){h+='<tr><td><b>'+W.esc(x.label||'Este equipo')+'</b><div style="font-size:10px;color:var(--w-muted)">'+W.esc((x.user_agent||'').slice(0,90))+'</div></td><td style="color:'+(x.active?'#0d7a58':'#b42318')+';font-weight:700">'+(x.active?'Activo':'Revocado')+'</td><td>'+W.esc(W.sglShortDate(x.last_seen_at||x.created_at))+'</td><td>'+(x.active?'<button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglInternalRevokeDevice('+x.id+')">Revocar</button>':'')+'</td></tr>'});h+='</tbody></table></div>'}
    h+='</div>';
    h+='<div class="w-card" style="margin-bottom:14px"><h3 style="margin:0 0 8px">Últimas solicitudes propias</h3>';
    if(!(d.latest||[]).length)h+='<div style="font-size:12px;color:var(--w-muted)">Sin solicitudes propias en el período.</div>';
    else{h+='<div style="overflow:auto"><table class="w-table"><thead><tr><th>Hora</th><th>Ruta</th><th>Estado</th><th>Tipo</th><th>Motivo</th><th>IP</th></tr></thead><tbody>';d.latest.forEach(function(x){h+='<tr><td>'+W.esc(W.sglShortDate(x.created_at))+'</td><td style="max-width:300px;word-break:break-all">'+W.esc(x.path)+'</td><td>'+W.esc(String(x.status||0))+'</td><td>'+(x.is_page_view?'Vista':'Recurso')+'</td><td>'+W.esc(x.reason_label||x.reason||'—')+'</td><td>'+W.esc(x.ip||'—')+'</td></tr>'});h+='</tbody></table></div>'}
    h+='</div>';
    h+='<div class="w-card"><h3 style="margin:0 0 8px">Últimos eventos propios</h3>';
    if(!(d.latest_events||[]).length)h+='<div style="font-size:12px;color:var(--w-muted)">Sin eventos propios. Si no aceptaste analítica, las visitas igual aparecen en las solicitudes del servidor.</div>';
    else{h+='<div style="overflow:auto"><table class="w-table"><thead><tr><th>Hora</th><th>Tipo</th><th>Página</th><th>Motivo</th></tr></thead><tbody>';d.latest_events.forEach(function(x){h+='<tr><td>'+W.esc(W.sglShortDate(x.created_at))+'</td><td>'+W.esc(x.type)+'</td><td style="max-width:320px;word-break:break-all">'+W.esc(x.page_url||'—')+'</td><td>'+W.esc(x.reason_label||x.reason||'—')+'</td></tr>'});h+='</tbody></table></div>'}
    h+='</div>';
    box.innerHTML=h;
};
W.sglInternalMark=async function(){
    var r=await W.api('/api/v1/admin/seo-global-launch/internal/device/mark',{method:'POST',body:{label:'Este equipo'}});
    W.notify((r&&r.message)||(r&&r.ok?'Equipo marcado':'No se pudo marcar'),r&&r.ok?'success':'error');
    if(r&&r.ok)W.sglInternalLoad();
};
W.sglInternalUnmark=async function(){
    if(!window.confirm('¿Desmarcar este equipo? Volverá a contar como cliente hasta que lo marques de nuevo.'))return;
    var r=await W.api('/api/v1/admin/seo-global-launch/internal/device/unmark',{method:'POST'});
    W.notify((r&&r.message)||(r&&r.ok?'Equipo desmarcado':'No se pudo desmarcar'),r&&r.ok?'success':'error');
    if(r&&r.ok)W.sglInternalLoad();
};
W.sglInternalAddIp=async function(){
    var r=await W.api('/api/v1/admin/seo-global-launch/internal/networks',{method:'POST',body:{current:true,label:'Mi IP actual'}});
    W.notify((r&&r.message)||(r&&r.ok?'IP añadida':'No se pudo añadir la IP'),r&&r.ok?'success':'error');
    if(r&&r.ok)W.sglInternalLoad();
};
W.sglInternalAddNetwork=async function(){
    var cidr=(document.getElementById('sgl-net-cidr')||{}).value||'';
    var label=(document.getElementById('sgl-net-label')||{}).value||'';
    if(!cidr.trim()){W.notify('Escribe una IP o un CIDR','info');return}
    var r=await W.api('/api/v1/admin/seo-global-launch/internal/networks',{method:'POST',body:{cidr:cidr,label:label}});
    W.notify((r&&r.message)||(r&&r.ok?'Red añadida':'No se pudo añadir la red'),r&&r.ok?'success':'error');
    if(r&&r.ok)W.sglInternalLoad();
};
W.sglInternalDeleteNetwork=async function(id){
    if(!window.confirm('¿Quitar esta red de la allowlist? Sus visitas volverán a contar como clientes hasta que la añadas de nuevo.'))return;
    var r=await W.api('/api/v1/admin/seo-global-launch/internal/networks/'+id,{method:'DELETE'});
    W.notify((r&&r.message)||(r&&r.ok?'Red eliminada':'No se pudo eliminar'),r&&r.ok?'success':'error');
    if(r&&r.ok)W.sglInternalLoad();
};
W.sglInternalRevokeDevice=async function(id){
    if(!window.confirm('¿Revocar este dispositivo? Dejará de estar aislado como tráfico propio.'))return;
    var r=await W.api('/api/v1/admin/seo-global-launch/internal/devices/'+id+'/revoke',{method:'POST'});
    W.notify((r&&r.message)||(r&&r.ok?'Dispositivo revocado':'No se pudo revocar'),r&&r.ok?'success':'error');
    if(r&&r.ok)W.sglInternalLoad();
};
W.sglInternalBackfill=async function(dry){
    if(!dry&&!window.confirm('Esto marcará como internos los históricos de las IPs permitidas. No se borra nada. ¿Continuar?'))return;
    var msg=document.getElementById('sgl-backfill-msg');
    if(msg)msg.textContent='Procesando...';
    var r=await W.api('/api/v1/admin/seo-global-launch/internal/backfill',{method:'POST',body:{dry_run:!!dry}});
    if(!r||!r.ok){if(msg)msg.textContent=(r&&r.message)||'No se pudo reclasificar';W.notify((r&&r.message)||'No se pudo reclasificar','error');return}
    var c=r.counts||{};
    var txt=(dry?'Potencial':'Reclasificado')+': '+W.num(c.live_requests||0)+' solicitudes, '+W.num(c.events||0)+' eventos, '+W.num(c.analytics_views||0)+' vistas analytics, '+W.num(c.orders||0)+' pedidos.';
    if(msg)msg.textContent=txt;
    W.notify(txt,dry?'info':'success');
    if(!dry)W.sglInternalLoad();
};

// ── Pauta Meta ──────────────────────────────────
W.sglMetaDays=30;
W.sglMetaLoad=async function(){
    var box=document.getElementById('sgl-meta'); if(!box)return;
    var days=W.sglMetaDays||30;
    var r=await W.api('/api/v1/admin/seo-global-launch/meta?days='+days);
    if(!r||!r.ok){box.innerHTML='<div class="w-empty-state"><h3>Pauta Meta no disponible</h3><p>'+W.esc((r&&r.message)||'No se pudo cargar el reporte.')+'</p></div>';return}
    W._sglMeta=r;
    var s=r.summary||{},fn=r.funnel||{},oc=r.orders_context||{},notes=r.notes||[];
    var h='<div class="w-card" style="margin-bottom:14px"><div class="w-flex-between" style="gap:10px;flex-wrap:wrap"><div><h3 style="margin:0">Resultados reales de Meta</h3><p style="font-size:12px;color:var(--w-muted);margin:5px 0 0">Pauta reconocida por <b>fbclid</b> o UTM de Meta con medio pagado. El tráfico propio y los bots están excluidos.</p></div><div style="display:flex;gap:6px;flex-wrap:wrap"><select class="w-input" style="width:110px" onchange="W.sglMetaDays=parseInt(this.value)||30;W.sglMetaLoad()"><option value="7"'+(days===7?' selected':'')+'>7 días</option><option value="14"'+(days===14?' selected':'')+'>14 días</option><option value="30"'+(days===30?' selected':'')+'>30 días</option><option value="90"'+(days===90?' selected':'')+'>90 días</option></select><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglMetaBackfill()">Reconstruir atribución histórica</button><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglMetaLoad()">Actualizar</button><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglMetaExport()">Exportar CSV</button></div></div>';
    h+='<div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px">'+W.sglKpi(W.num(s.visits||0),'Visitas de pauta')+W.sglKpi(W.num(s.visitors||0),'Visitantes / sesiones')+W.sglKpi(W.num(s.sessions||0),'Sesiones')+W.sglKpi((s.share_pct||0)+'%','% del tráfico real','#0d7a58')+W.sglKpi(W.num(s.cart_adds||0),'Al carrito')+W.sglKpi(W.num(s.checkout_starts||0),'Checkout')+W.sglKpi(W.num(s.purchases||0),'Compras atribuidas','#0d7a58')+W.sglKpi(W.ocMoney(s.revenue_cents||0,'COP'),'Ingresos atribuidos')+'</div>';
    h+='<div style="font-size:11px;color:var(--w-muted);margin-top:8px">Vistas clientes del período: '+W.num(s.total_client_views||0)+' · Landings de servidor en los últimos 90 días: '+W.num(s.landing_requests||0)+' solicitudes / '+W.num(s.landing_visitors||0)+' visitantes · Conversión atribuida: '+(s.conversion_pct||0)+'%</div></div>';
    if(!r.has_data)h+='<div class="w-card" style="margin-bottom:14px;border-left:4px solid #b45309"><h3 style="margin:0 0 6px">Todavía no hay datos de Meta en este período</h3><p style="font-size:12px;color:var(--w-muted);margin:0">Abre un anuncio con <b>fbclid</b> o etiqueta la URL con <b>utm_source=facebook|ig|meta</b> y <b>utm_medium=paid_social|cpc</b>. Si la pauta ya corrió, pulsa <b>Reconstruir atribución histórica</b> para extraer UTM/fbclid de las URLs guardadas.</p></div>';
    h+='<div class="w-card" style="margin-bottom:14px"><h3 style="margin:0 0 8px">Comparación por ventana</h3><div style="display:flex;gap:10px;flex-wrap:wrap">'+(r.ranges||[]).map(function(x){return W.sglKpi(W.num(x.visits||0),x.label+' · '+W.num(x.visitors||0)+' visitantes/sesiones · '+W.num(x.purchases||0)+' compras')}).join('')+'</div></div>';
    var table=function(title,rows,cols){
        var x='<div class="w-card" style="margin-bottom:14px"><h3 style="margin:0 0 8px">'+title+'</h3>';
        if(!rows||!rows.length)return x+'<div style="font-size:12px;color:var(--w-muted)">Sin datos en el período.</div></div>';
        x+='<div style="overflow:auto"><table class="w-table"><thead><tr>'+cols.map(function(c){return '<th>'+W.esc(c[0])+'</th>'}).join('')+'</tr></thead><tbody>';
        rows.forEach(function(row){x+='<tr>'+cols.map(function(c){return '<td style="max-width:340px;word-break:break-all">'+W.esc(c[1](row))+'</td>'}).join('')+'</tr>'});
        return x+'</tbody></table></div></div>';
    };
    h+=table('Campañas y anuncios',r.campaigns||[],[['Campaña',function(x){return x.campaign||'—'}],['Fuente / medio',function(x){return (x.source||'—')+' / '+(x.medium||'—')}],['Contenido',function(x){return x.content||'—'}],['Término',function(x){return x.term||'—'}],['Visitas',function(x){return W.num(x.visits||0)}],['Únicos',function(x){return W.num(x.visitors||0)}],['Sesiones',function(x){return W.num(x.sessions||0)}]]);
    h+=table('Landings de la pauta',r.landing||[],[['Landing',function(x){return x.landing||'—'}],['Visitas',function(x){return W.num(x.visits||0)}],['Únicos',function(x){return W.num(x.visitors||0)}]]);
    h+=table('Fuentes y medios',r.sources||[],[['Fuente',function(x){return x.source||'—'}],['Medio',function(x){return x.medium||'—'}],['Visitas',function(x){return W.num(x.visits||0)}],['Únicos',function(x){return W.num(x.visitors||0)}]]);
    h+=table('Embudo por campaña (consentimiento)',r.campaign_funnel||[],[['Campaña',function(x){return x.campaign||'—'}],['Carrito',function(x){return W.num(x.cart_adds||0)}],['Checkout',function(x){return W.num(x.checkout_starts||0)}],['Compras',function(x){return W.num(x.purchases||0)}],['Ingresos',function(x){return W.ocMoney(x.revenue_cents||0,'COP')}]]);
    h+=table('Evolución diaria',r.daily||[],[['Día',function(x){return x.day||'—'}],['Visitas',function(x){return W.num(x.visits||0)}],['Únicos',function(x){return W.num(x.visitors||0)}]]);
    h+='<div class="w-card" style="margin-bottom:14px"><h3 style="margin:0 0 8px">Contexto del negocio (no atribuido)</h3><div class="sgl-live__stats">'+W.sglLiveStat(W.num(oc.paid||0),'Pedidos pagados')+W.sglLiveStat(W.ocMoney(oc.revenue_cents||0,'COP'),'Ingresos totales')+W.sglLiveStat(W.num(fn.product_views||0),'Productos vistos (pauta)')+W.sglLiveStat(W.num(fn.purchases||0),'Compras atribuidas')+'</div><div style="font-size:11px;color:var(--w-muted);margin-top:8px">Los pedidos pagados son contexto del período; no se atribuyen a Meta sin una señal de campaña.</div></div>';
    h+='<div class="w-card"><h3 style="margin:0 0 6px">Cómo leer este reporte</h3><ul style="font-size:12px;color:var(--w-muted);line-height:1.8;margin:0;padding-left:18px">'+notes.map(function(n){return '<li>'+W.esc(n)+'</li>'}).join('')+'</ul></div>';
    box.innerHTML=h;
};
W.sglMetaExport=function(){
    var d=W._sglMeta||{},s=d.summary||{};
    var lines=['Pauta Meta · '+ (d.days||W.sglMetaDays) +' días',''];
    lines.push('Visitas,'+ (s.visits||0));
    lines.push('Visitantes únicos,'+ (s.visitors||0));
    lines.push('Sesiones,'+ (s.sessions||0));
    lines.push('% del tráfico real,'+ (s.share_pct||0));
    lines.push('Carrito,'+ (s.cart_adds||0));
    lines.push('Checkout,'+ (s.checkout_starts||0));
    lines.push('Compras atribuidas,'+ (s.purchases||0));
    lines.push('Ingresos atribuidos (cents),'+ (s.revenue_cents||0));
    lines.push('');
    lines.push('Campaña,Fuente,Medio,Contenido,Término,Visitas,Únicos,Sesiones');
    (d.campaigns||[]).forEach(function(x){
        var row=[x.campaign,x.source,x.medium,x.content,x.term,x.visits,x.visitors,x.sessions];
        lines.push(row.map(function(v){return '"'+String(v==null?'':v).replace(/"/g,'""')+'"'}).join(','));
    });
    var blob=new Blob(['\uFEFF'+lines.join('\n')],{type:'text/csv;charset=utf-8;'});
    var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='pauta-meta-'+(d.days||W.sglMetaDays)+'d.csv';document.body.appendChild(a);a.click();setTimeout(function(){URL.revokeObjectURL(a.href);a.remove()},500);
};
W.sglMetaBackfill=async function(){
    if(!window.confirm('Reconstruir UTM/fbclid desde las URLs históricas ya guardadas. No inventa datos. ¿Continuar?'))return;
    var r=await W.api('/api/v1/admin/seo-global-launch/meta/backfill',{method:'POST',body:{limit:5000}});
    if(r&&r.ok){var u=r.updated||{};W.notify('Atribución reconstruida: '+(u.analytics_views||0)+' vistas y '+(u.seo_tracking_events||0)+' eventos','success');W.sglMetaLoad()}else W.notify((r&&r.message)||'No se pudo reconstruir','error');
};

W.sglOppGo=function(action){
    if(action==='redirecciones'||action==='404s'||action==='enlaces-rotos'){location.hash='#seo-global-launch/'+action;return}
    if(action==='generate'){W.sglAction('generate',null);return}
    if(action==='pedidos'){location.hash='#order-center/pedidos';return}
    if(action==='carrito'){W.notify('Abre la recuperación de carritos configurada en Tienda.','info');return}
    W.notify('Instrumenta los eventos del funnel para resolver esta oportunidad.','info');
};

W._sglLive={timer:null,paused:false,lastId:0,items:[],busy:false};
W.sglLiveStop=function(){
    if(W._sglLive&&W._sglLive.timer){clearTimeout(W._sglLive.timer);W._sglLive.timer=null}
};
W.sglLiveStart=function(){
    W._sglLive={timer:null,paused:false,lastId:0,items:[],busy:false};
    var p=document.getElementById('sgl-live-pause'); if(p)p.textContent='Pausar';
    W.sglLiveFetch(true);
};
W.sglLiveSchedule=function(){
    if(!document.getElementById('sgl-live')||W._sglLive.paused)return;
    if(W._sglLive.timer)clearTimeout(W._sglLive.timer);
    W._sglLive.timer=setTimeout(function(){W.sglLiveFetch(false)},2000);
};
W.sglLiveToggle=function(){
    W._sglLive.paused=!W._sglLive.paused;
    var b=document.getElementById('sgl-live-pause'); if(b)b.textContent=W._sglLive.paused?'Reanudar':'Pausar';
    if(!W._sglLive.paused)W.sglLiveFetch(false);
};
W.sglLiveClear=function(){
    var t=document.getElementById('sgl-term'); if(t)t.innerHTML='';
    W._sglLive.items=[];
};
W.sglLiveFilterRows=function(){
    var q=(document.getElementById('sgl-live-filter')||{}).value||'';
    var method=(document.getElementById('sgl-live-method')||{}).value||'';
    var status=(document.getElementById('sgl-live-status')||{}).value||'';
    q=q.toLowerCase().trim();
    [].slice.call(document.querySelectorAll('#sgl-term .sgl-term__line')).forEach(function(row){
        var text=(row.textContent||'').toLowerCase();
        var ok=(!q||text.indexOf(q)>=0)&&(!method||row.getAttribute('data-method')===method)&&(!status||String(row.getAttribute('data-status')).charAt(0)===status);
        row.style.display=ok?'':'none';
    });
};
W.sglLiveStat=function(v,l,color){
    return '<div class="sgl-live__stat"><b'+(color?' style="color:'+color+'"':'')+'>'+W.esc(String(v))+'</b><span>'+W.esc(l)+'</span></div>';
};
W.sglLiveAppend=function(items){
    var term=document.getElementById('sgl-term'); if(!term)return;
    var empty=term.querySelector('.sgl-term__empty'); if(empty)empty.remove();
    items.forEach(function(x){
        W._sglLive.items.push(x);
        var line=document.createElement('div');
        line.className='sgl-term__line';
        line.setAttribute('data-method',x.method||'');
        line.setAttribute('data-status',String(x.status||0));
        var raw=(x.created_at||'').replace(' ','T');
        if(raw&&!/[zZ]|[+-]\d\d:\d\d$/.test(raw))raw+='Z';
        var dt=new Date(raw);
        var hhmmss=isNaN(dt.getTime())?new Date().toLocaleTimeString('es-CO',{hour12:false}):dt.toLocaleTimeString('es-CO',{hour12:false});
        var code=x.status||0;
        var color=code>=500?'#fb7185':(code>=400?'#fbbf24':(code>=300?'#a5b4fc':'#6ee7b7'));
        var bits=[x.ip||'IP —'];
        if(x.city)bits.push(x.city);
        else if(x.country)bits.push(x.country);
        if(x.device)bits.push(x.device);
        if(x.browser)bits.push(x.browser);
        if(x.is_internal)bits.push('YO · '+(x.internal_reason||'interno'));
        if(x.is_bot)bits.push('bot');
        var lineText='['+hhmmss+'] '+(x.protocol||'HTTP/1.1')+' '+code+' '+W.esc(x.method||'GET')+' '+W.esc(x.path||'/')+'  '+(x.duration_ms||0)+'ms  '+W.esc(bits.join(' · '));
        line.innerHTML='<span style="color:'+color+'">'+lineText+'</span>';
        if(W._sglLive.items.length>250){W._sglLive.items.shift();if(term.firstChild)term.removeChild(term.firstChild)}
        term.appendChild(line);
    });
    var auto=document.getElementById('sgl-live-autoscroll');
    if(auto&&auto.checked)term.scrollTop=term.scrollHeight;
    W.sglLiveFilterRows();
};
W.sglLiveAudience=function(){
    W._sglLive.lastId=0;W._sglLive.items=[];
    var term=document.getElementById('sgl-term');
    if(term)term.innerHTML='<div class="sgl-term__empty">Cambiando de audiencia…</div>';
    W.sglLiveFetch(true);
};
W.sglLiveFetch=async function(first){
    if(W._sglLive.busy)return;
    var box=document.getElementById('sgl-live'); if(!box)return;
    W._sglLive.busy=true;
    try{
        var audience=(document.getElementById('sgl-live-audience')||{}).value||'client';
        var r=await W.api('/api/v1/admin/seo-global-launch/live?after_id='+(first?0:W._sglLive.lastId)+'&limit=100&audience='+encodeURIComponent(audience));
        var d=(r&&r.data)||{};
        var items=d.items||[];
        if(items.length){
            W._sglLive.lastId=Math.max.apply(null,items.map(function(x){return x.id||0}));
            W.sglLiveAppend(items);
        }else if(first){
            var term=document.getElementById('sgl-term');
            if(term)term.innerHTML='<div class="sgl-term__empty">Sin peticiones para esta audiencia. Cambia el filtro o espera tráfico.</div>';
        }
        var s=d.stats||{},cs=d.client_stats||{},is=d.internal_stats||{},bs=d.bot_stats||{};
        var stats=document.getElementById('sgl-live-stats');
        if(stats){
            stats.innerHTML=W.sglLiveStat(s.requests||0,'Solicitudes')+W.sglLiveStat(s.page_views||0,'Vistas de página')+W.sglLiveStat(s.unique_visitors||0,'Visitantes')+W.sglLiveStat(s.ok||0,'2xx','#6ee7b7')+W.sglLiveStat((s.client_errors||0)+(s.server_errors||0),'4xx/5xx','#fb7185')+W.sglLiveStat((s.avg_ms||0)+' ms','Latencia media')+W.sglLiveStat((s.mobile||0)+'M · '+(s.desktop||0)+'D · '+(s.tablet||0)+'T · '+(s.tv||0)+'TV','Dispositivos')+W.sglLiveStat(cs.requests||0,'Clientes 24h')+W.sglLiveStat(is.requests||0,'Yo 24h','#b45309')+W.sglLiveStat(bs.requests||0,'Bots 24h','#fbbf24');
        }
        var clock=document.getElementById('sgl-live-clock'); if(clock)clock.textContent=new Date().toLocaleTimeString('es-CO',{hour12:false});
    }catch(e){
        var term=document.getElementById('sgl-term');
        if(first&&term)term.innerHTML='<div class="sgl-term__empty">No se pudo conectar con el monitor. Revisa la sesión o las tablas del brick.</div>';
    }finally{
        W._sglLive.busy=false;
        W.sglLiveSchedule();
    }
};
W._sglSec={data:null};
W.sglSecurityLoad=async function(){
    var box=document.getElementById('sgl-security'); if(!box)return;
    var r=await W.api('/api/v1/admin/seo-global-launch/security?window=3600');
    if(!r||!r.ok){box.innerHTML='<div class="w-empty-state"><h3>No disponible</h3><p>'+W.esc((r&&r.message)||'No se pudo cargar seguridad IP.')+'</p></div>';return}
    W._sglSec.data=r;
    var ind=r.individual||[],camps=r.campaigns||[],blocks=r.blocked||[];
    var color=function(v){return v>=70?'#fb7185':(v>=40?'#fbbf24':'#34d399')};
    var h='<div class="w-card" style="margin-bottom:14px"><div class="w-flex-between" style="gap:10px;flex-wrap:wrap"><div><h3 style="margin:0">Seguridad IP</h3><p style="font-size:12px;color:var(--w-muted);margin:5px 0 0">Analiza IPs, verifica bots, detecta campañas distribuidas y bloquea el tráfico ofensivo. El bloqueo es de aplicación.</p></div><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglSecurityLoad()">Actualizar</button></div>'
        +'<div class="sgl-live__stats" style="margin-top:12px">'+W.sglLiveStat(r.events||0,'Peticiones analizadas')+W.sglLiveStat(ind.length,'IPs con señales')+W.sglLiveStat(camps.length,'Campañas')+W.sglLiveStat(blocks.length,'IPs bloqueadas','#fb7185')+'</div>'
        +'<div style="display:flex;gap:7px;align-items:center;flex-wrap:wrap;margin-top:12px"><input class="w-input" id="sgl-sec-ip" placeholder="IP a analizar, por ejemplo 74.7.227.157" style="flex:1;min-width:220px"/><button class="w-btn w-btn-primary" type="button" onclick="W.sglSecScan()">Escanear IP</button>'+(ind[0]?'<button class="w-btn w-btn-secondary" type="button" onclick="W.sglSecScan(\''+W.esc(ind[0].ip)+'\')">Escanear la principal</button>':'')+'</div><div id="sgl-sec-scan-result" style="margin-top:12px"></div></div>';
    h+='<div class="w-card" style="margin-bottom:14px"><h3 style="margin:0 0 8px">IPs con señales en la última hora</h3>';
    if(!ind.length)h+='<div style="font-size:12px;color:var(--w-muted)">Sin señales suficientes. El monitor seguirá acumulando datos.</div>';
    else{
        h+='<div style="overflow:auto"><table class="w-table"><thead><tr><th>IP</th><th>Ubicación</th><th>Peticiones</th><th>Error</th><th>Riesgo</th><th></th></tr></thead><tbody>';
        ind.slice(0,20).forEach(function(x){
            h+='<tr><td style="font-family:var(--w-font-mono);font-size:11px">'+W.esc(x.ip)+(x.bot>0?' <span style="font-size:9px;color:#fbbf24">bot</span>':'')+'</td><td style="font-size:11px">'+W.esc([x.city,x.country].filter(Boolean).join(', ')||'—')+(x.asn?'<div style="font-size:9px;color:var(--w-muted)">'+W.esc(x.asn)+'</div>':'')+'</td><td>'+W.num(x.requests||0)+'</td><td>'+W.num(x.errors||0)+'</td><td><b style="color:'+color(x.score||0)+'">'+W.num(x.score||0)+'</b><div style="font-size:9px;color:var(--w-muted)">'+W.esc(x.verdict||'')+'</div></td><td style="white-space:nowrap"><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglSecScan(\''+W.esc(x.ip)+'\')">Escanear</button> <button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglSecBlock(\''+W.esc(x.ip)+'\')">Bloquear</button></td></tr>';
        });
        h+='</tbody></table></div>';
    }
    h+='</div>';
    h+='<div class="w-card" style="margin-bottom:14px"><h3 style="margin:0 0 8px">Campañas distribuidas</h3><p style="font-size:11px;color:var(--w-muted);margin:0 0 10px">Agrupa la misma técnica (User-Agent + ruta) desde varias IPs, incluyendo VPN/proxy cuando el proveedor lo reporta.</p>';
    if(!camps.length)h+='<div style="font-size:12px;color:var(--w-muted)">No hay campañas con los umbrales actuales.</div>';
    else{
        camps.forEach(function(x,i){
            h+='<div style="border:1px solid var(--w-border);border-radius:10px;padding:10px;margin-bottom:8px"><div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap"><div><b style="font-size:12px">'+W.esc(x.reason||'Firma repetida')+'</b><div style="font-size:11px;color:var(--w-muted);margin-top:3px">'+W.esc(x.signature||'')+' · '+W.num(x.ip_count||0)+' IPs · '+W.num(x.requests||0)+' peticiones · '+W.num(x.errors||0)+' errores</div><div style="font-size:10px;color:var(--w-muted);margin-top:4px;word-break:break-all">'+W.esc((x.ips||[]).slice(0,8).join(', '))+((x.ips||[]).length>8?'…':'')+'</div></div><div style="text-align:right"><div style="font-size:18px;font-weight:800;color:'+color(x.risk||0)+'">'+W.num(x.risk||0)+'</div>'+(x.kind==='bot_distribuido'?'<span style="font-size:10px;color:#34d399">Bot conocido · no bloquear automáticamente</span>':'<button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglSecBlockCampaign('+i+')">Bloquear IPs</button>')+'</div></div></div>';
        });
    }
    h+='</div>';
    h+='<div class="w-card"><h3 style="margin:0 0 8px">IPs bloqueadas</h3>';
    if(!blocks.length)h+='<div style="font-size:12px;color:var(--w-muted)">No hay bloqueos activos.</div>';
    else{
        h+='<div style="overflow:auto"><table class="w-table"><thead><tr><th>IP</th><th>Motivo</th><th>Creado</th><th>Expira</th><th></th></tr></thead><tbody>';
        blocks.forEach(function(x){h+='<tr><td style="font-family:var(--w-font-mono);font-size:11px">'+W.esc(x.ip)+'</td><td style="font-size:11px">'+W.esc(x.reason||'')+'</td><td style="font-size:10px">'+W.esc(x.created_at||'')+'</td><td style="font-size:10px">'+W.esc(x.expires_at||'Permanente')+'</td><td><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglSecUnblock(\''+W.esc(x.ip)+'\')">Desbloquear</button></td></tr>'});
        h+='</tbody></table></div>';
    }
    h+='<p style="font-size:10px;color:var(--w-muted);line-height:1.5;margin:10px 0 0">Los datos de IP son internos y visibles solo para administradores. La geolocalización/proxy es una estimación del proveedor; el bloqueo de aplicación no reemplaza un firewall perimetral.</p></div>';
    box.innerHTML=h;
};
W.sglSecScan=async function(ip){
    ip=(ip||((document.getElementById('sgl-sec-ip')||{}).value)||'').trim();
    var out=document.getElementById('sgl-sec-scan-result'); if(!out)return;
    if(!ip){W.notify('Escribe una IP válida','error');return}
    out.innerHTML='<div class="w-card" style="margin:0;padding:12px;color:var(--w-muted);font-size:12px">Consultando '+W.esc(ip)+'…</div>';
    var r=await W.api('/api/v1/admin/seo-global-launch/ip?ip='+encodeURIComponent(ip));
    if(!r||!r.ok){out.innerHTML='<div class="w-card" style="margin:0;padding:12px;color:#fb7185">'+W.esc((r&&r.message)||'No se pudo analizar la IP.')+'</div>';return}
    var i=r.intel||{},a=r.activity||{},b=r.bot||{},ev=(r.evidence||[]).map(function(x){return '<li>'+W.esc(x)+'</li>'}).join('');
    var col=r.score>=70?'#fb7185':(r.score>=40?'#fbbf24':'#34d399');
    var where=[i.city,i.region,i.country].filter(Boolean).join(', ')||'No disponible';
    var botTxt=b.verified?'Verificado por DNS inverso':(b.claimed?'Declarado, no verificado':'No identificado');
    var h='<div class="w-card" style="margin:0;border-left:3px solid '+col+'"><div class="w-flex-between" style="gap:8px;flex-wrap:wrap"><div><h3 style="margin:0;font-family:var(--w-font-mono)">'+W.esc(r.ip)+'</h3><div style="font-size:11px;color:var(--w-muted);margin-top:4px">'+W.esc(where)+' · '+W.esc(i.isp||i.org||'ISP no disponible')+' · '+W.esc(i.asn||'ASN no disponible')+'</div></div><div style="text-align:right"><div style="font-size:22px;font-weight:800;color:'+col+'">'+W.num(r.score||0)+'</div><div style="font-size:10px;color:var(--w-muted)">'+W.esc(r.verdict||'')+'</div></div></div>'
        +'<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;margin-top:10px;font-size:11px"><div><b>ISP:</b> '+W.esc(i.isp||'—')+'</div><div><b>Org:</b> '+W.esc(i.org||'—')+'</div><div><b>Proxy/VPN:</b> '+(i.proxy?'Sí (indicio)':'No reportado')+'</div><div><b>Hosting:</b> '+(i.hosting?'Sí':'No')+'</div><div><b>Bot:</b> '+W.esc(botTxt)+'</div><div><b>DNS inverso:</b> '+W.esc(b.reverse_host||'—')+'</div><div><b>Peticiones:</b> '+W.num(a.requests||0)+'</div><div><b>Errores:</b> '+W.num(a.errors||0)+'</div><div><b>Rutas sensibles:</b> '+W.num(a.sensitive||0)+'</div><div><b>Rutas únicas:</b> '+W.num(a.unique_paths||0)+'</div></div>'
        +(ev?'<ul style="font-size:11px;color:var(--w-muted);margin:9px 0">'+ev+'</ul>':'')
        +'<p style="font-size:11px;color:var(--w-muted);margin:0">'+W.esc(r.recommendation||'')+'</p>'
        +(a.paths&&a.paths.length?'<div style="font-size:10px;color:var(--w-muted);margin-top:6px">Rutas: '+W.esc(a.paths.join(', '))+'</div>':'')
        +'<div style="margin-top:10px">'+(r.blocked?'<button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglSecUnblock(\''+W.esc(r.ip)+'\')">Desbloquear IP</button>':'<button class="w-btn w-btn-primary w-btn-sm" type="button" onclick="W.sglSecBlock(\''+W.esc(r.ip)+'\')">Bloquear IP</button>')+'</div></div>';
    out.innerHTML=h;
};
W.sglSecBlock=function(ip){
    var reason=window.prompt('Motivo del bloqueo para '+ip, 'Ataque o abuso detectado desde el monitor SEO');
    if(reason===null)return;
    var hours=window.prompt('Duración en horas (0 = permanente)', '24');
    if(hours===null)return;
    W.api('/api/v1/admin/seo-global-launch/ip/block',{method:'POST',body:{ip:ip,reason:reason,hours:parseInt(hours,10)||0}}).then(function(r){
        if(r&&r.ok){W.notify('IP bloqueada','success');W.sglSecurityLoad()}else W.notify((r&&r.message)||'No se pudo bloquear','error');
    });
};
W.sglSecUnblock=function(ip){
    W.confirm('¿Desbloquear '+ip+'?',function(){
        W.api('/api/v1/admin/seo-global-launch/ip/unblock',{method:'POST',body:{ip:ip}}).then(function(r){
            if(r&&r.ok){W.notify('IP desbloqueada','success');W.sglSecurityLoad()}else W.notify((r&&r.message)||'No se pudo desbloquear','error');
        });
    });
};
W.sglSecBlockCampaign=function(index){
    var d=W._sglSec&&W._sglSec.data||{},c=(d.campaigns||[])[index]; if(!c)return;
    W.confirm('¿Bloquear '+c.ip_count+' IPs de esta campaña?',function(){
        W.api('/api/v1/admin/seo-global-launch/ip/block-bulk',{method:'POST',body:{ips:c.ips||[],reason:'Campaña distribuida: '+c.reason+' ('+c.signature+')',hours:24}}).then(function(r){
            if(r&&r.ok){W.notify('IPs bloqueadas: '+(r.blocked||0),'success');W.sglSecurityLoad()}else W.notify((r&&r.message)||'No se pudo bloquear','error');
        });
    });
};
W._sglInt={};
W.sglIntegrationsLoad=async function(){
    var box=document.getElementById('sgl-integrations'); if(!box)return;
    var r=await W.api('/api/v1/admin/seo-global-launch/integrations');
    if(!r||!r.ok||!r.data){box.innerHTML='<div class="w-empty-state"><h3>No disponible</h3><p>'+W.esc((r&&r.message)||'No se pudieron cargar las integraciones.')+'</p></div>';return}
    var d=r.data||{}; W._sglInt=d;
    var badge=function(on){return '<span class="w-badge" style="background:'+(on?'rgba(52,211,153,.12)':'rgba(148,163,184,.12)')+';color:'+(on?'#34d399':'#94a3b8')+'">'+(on?'CONFIGURADA':'SIN CONFIGURAR')+'</span>'};
    var h='<div class="w-card" style="margin-bottom:14px"><div class="w-flex-between" style="gap:10px;flex-wrap:wrap"><div><h3 style="margin:0">Integraciones</h3><p style="font-size:12px;color:var(--w-muted);margin:5px 0 0">Conecta el sitio con buscadores y medición externa. Los secretos se guardan cifrados y no se vuelven a mostrar.</p></div><span style="font-size:10px;color:var(--w-muted)">Sitio: '+W.esc(d.site_url||'')+'</span></div>'
        +'<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;margin-top:14px">'
        +'<div class="w-card" style="margin:0;padding:13px"><div style="display:flex;justify-content:space-between;gap:8px;align-items:center"><b>IndexNow</b>'+badge(d.indexnow&&d.indexnow.configured)+'</div><p style="font-size:11px;color:var(--w-muted);line-height:1.5;margin:6px 0 8px">Avisa a Bing, Yandex y otros motores compatibles cuando publiques URLs.</p><label class="w-label">Clave</label><div style="display:flex;gap:6px"><input class="w-input" id="sgl-int-indexnow" value="'+W.esc((d.indexnow&&d.indexnow.key)||'')+'" placeholder="Se genera al guardar" style="flex:1;min-width:0"/><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglIntGenerate()">Generar</button></div><button class="w-btn w-btn-secondary w-btn-sm" style="margin-top:8px" type="button" onclick="W.sglIntRun(\'indexnow\',this)">Enviar sitemap</button></div>'
        +'<div class="w-card" style="margin:0;padding:13px"><div style="display:flex;justify-content:space-between;gap:8px;align-items:center"><b>PageSpeed Insights</b>'+badge(d.pagespeed&&d.pagespeed.configured)+'</div><p style="font-size:11px;color:var(--w-muted);line-height:1.5;margin:6px 0 8px">Mide rendimiento, SEO, accesibilidad y buenas prácticas del sitio.</p><label class="w-label">API key de Google</label><input class="w-input" id="sgl-int-pagespeed" type="password" autocomplete="new-password" placeholder="'+(d.pagespeed&&d.pagespeed.configured?W.esc(d.pagespeed.masked) : 'Pega la API key')+'" style="width:100%"/><div style="display:flex;gap:6px;margin-top:8px"><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglIntRun(\'pagespeed\',this,\'mobile\')">Probar móvil</button><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglIntRun(\'pagespeed\',this,\'desktop\')">Probar escritorio</button></div></div>'
        +'<div class="w-card" style="margin:0;padding:13px"><div style="display:flex;justify-content:space-between;gap:8px;align-items:center"><b>Google Search Console</b>'+badge(d.gsc&&d.gsc.configured)+'</div><p style="font-size:11px;color:var(--w-muted);line-height:1.5;margin:6px 0 8px">Cuenta de servicio con acceso a la propiedad. Debe añadirse como usuario en Search Console.</p><label class="w-label">Propiedad</label><input class="w-input" id="sgl-int-gsc-property" value="'+W.esc((d.gsc&&d.gsc.property)||'')+'" placeholder="https://sonika.com.co/" style="width:100%"/><label class="w-label" style="margin-top:8px;display:block">JSON de cuenta de servicio</label><textarea class="w-input" id="sgl-int-gsc-json" rows="3" placeholder="'+(d.gsc&&d.gsc.configured?'Configurada · pega un JSON nuevo para reemplazar':'Pega el JSON de la cuenta de servicio')+'" style="width:100%;font-family:var(--w-font-mono);font-size:10px"></textarea><div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap"><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglIntRun(\'gsc_test\',this)">Probar</button><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglIntRun(\'gsc_sitemap\',this)">Enviar sitemap</button></div></div>'
        +'<div class="w-card" style="margin:0;padding:13px"><div style="display:flex;justify-content:space-between;gap:8px;align-items:center"><b>Bing Webmaster</b>'+badge(d.bing&&d.bing.configured)+'</div><p style="font-size:11px;color:var(--w-muted);line-height:1.5;margin:6px 0 8px">Envía el sitemap y consulta las propiedades verificadas en Bing.</p><label class="w-label">API key</label><input class="w-input" id="sgl-int-bing" type="password" autocomplete="new-password" placeholder="'+(d.bing&&d.bing.configured?W.esc(d.bing.masked) : 'Pega la API key de Bing')+'" style="width:100%"/><div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap"><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglIntRun(\'bing_test\',this)">Probar</button><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.sglIntRun(\'bing_sitemap\',this)">Enviar sitemap</button></div></div>'
        +'</div><div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:12px"><button class="w-btn w-btn-primary" type="button" onclick="W.sglIntSave()">Guardar integraciones</button><span id="sgl-int-msg" role="status" aria-live="polite" style="font-size:11px;color:var(--w-muted)">'+(d.last_saved_at?'Guardado: '+W.esc(d.last_saved_at):'')+'</span></div><div id="sgl-int-result" style="margin-top:10px"></div></div>';
    box.innerHTML=h;
};
W.sglIntGenerate=function(){
    var a=new Uint8Array(16); (window.crypto&&crypto.getRandomValues)?crypto.getRandomValues(a):a.fill(Math.floor(Math.random()*256)); var s=''; a.forEach(function(b){s+=('0'+b.toString(16)).slice(-2)}); var el=document.getElementById('sgl-int-indexnow'); if(el)el.value=s;
};
W.sglIntSave=async function(){
    var v=function(id){var e=document.getElementById(id);return e?e.value:''};
    var msg=document.getElementById('sgl-int-msg');
    if(msg){msg.textContent='Guardando…';msg.style.color='var(--w-muted)'}
    var r=await W.api('/api/v1/admin/seo-global-launch/integrations',{method:'POST',body:{indexnow_key:v('sgl-int-indexnow'),pagespeed_key:v('sgl-int-pagespeed'),gsc_property:v('sgl-int-gsc-property'),gsc_service_json:v('sgl-int-gsc-json'),bing_key:v('sgl-int-bing')}});
    if(r&&r.ok){if(msg){msg.textContent='Guardado';msg.style.color='#0d7a58'}W.notify('Integraciones guardadas','success');W.sglIntegrationsLoad()}else if(msg){msg.textContent=(r&&r.message)||'No se pudo guardar';msg.style.color='#b42318'}
};
W.sglIntRun=async function(provider,btn,strategy){
    var out=document.getElementById('sgl-int-result'); if(out)out.innerHTML='<div style="font-size:12px;color:var(--w-muted)">Ejecutando '+W.esc(provider)+'…</div>';
    if(btn){btn.disabled=true;btn.style.opacity=.5}
    var r=await W.api('/api/v1/admin/seo-global-launch/integrations/run',{method:'POST',body:{provider:provider,strategy:strategy||'mobile'}});
    if(btn){btn.disabled=false;btn.style.opacity=1}
    if(!out)return;
    var color=r&&r.ok?'#34d399':((r&&r.status==='skipped')?'#fbbf24':'#fb7185');
    var detail='';
    if(r){
        if(r.message)detail+='<div>'+W.esc(r.message)+'</div>';
        if(r.http)detail+='<div style="font-size:11px;color:var(--w-muted)">HTTP '+W.esc(r.http)+'</div>';
        if(r.submitted)detail+='<div style="font-size:11px;color:var(--w-muted)">URLs enviadas: '+W.num(r.submitted)+'</div>';
        if(r.performance!==undefined)detail+='<div style="font-size:11px">Rendimiento: <b>'+W.esc(r.performance)+'</b> · SEO: <b>'+W.esc(r.seo)+'</b> · Accesibilidad: <b>'+W.esc(r.accessibility)+'</b></div>';
        if(r.sites&&r.sites.length)detail+='<div style="font-size:11px;color:var(--w-muted)">Propiedades: '+W.esc(r.sites.slice(0,5).join(', '))+'</div>';
    }
    out.innerHTML='<div class="w-card" style="margin:0;padding:11px;border-left:3px solid '+color+'">'+detail+'</div>';
};
W.sglPhase2Load=async function(tab){
    var box=document.getElementById('sgl-phase2'); if(!box)return;
    if(tab==='redirecciones'){
        var r=await W.api('/api/v1/admin/seo-global-launch/redirects');
        var list=(r&&r.data&&r.data.redirects)||[];
        var h='<div class="w-card" style="margin-bottom:14px"><h3 style="margin:0 0 10px">Redirecciones</h3><p style="font-size:12px;color:var(--w-muted);margin:0 0 12px">Redirecciones 301/302 con detección de bucles. Los cambios se aplican inmediatamente en el sitio.</p>'
          +'<div style="display:grid;grid-template-columns:1fr 1.3fr 120px auto;gap:8px;align-items:end"><div><label style="font-size:11px;color:var(--w-muted)">Desde (ruta)</label><input class="w-input" id="sgl-redir-from" placeholder="/ruta-antigua" style="width:100%"/></div><div><label style="font-size:11px;color:var(--w-muted)">Hacia (URL o ruta)</label><input class="w-input" id="sgl-redir-to" placeholder="/ruta-nueva" style="width:100%"/></div><div><label style="font-size:11px;color:var(--w-muted)">Código</label><select class="w-input" id="sgl-redir-status" style="width:100%"><option value="301">301</option><option value="302">302</option></select></div><button class="w-btn w-btn-primary" onclick="wontia.sglRedirSave()">Agregar</button></div>'
          +'<div style="margin-top:12px"><label style="font-size:11px;color:var(--w-muted)">Importar CSV (from,to[,301|302])</label><textarea class="w-input" id="sgl-redir-csv" rows="3" style="width:100%;font-family:monospace" placeholder="/viejo,/nuevo,301"></textarea><button class="w-btn w-btn-secondary w-btn-sm" style="margin-top:7px" onclick="wontia.sglRedirImport()">Importar CSV</button></div></div>';
        if(!list.length) h+='<div class="w-empty-state"><h3>Sin redirecciones</h3><p>Crea la primera para evitar enlaces rotos.</p></div>';
        else{
            h+='<div class="w-card" style="padding:0;overflow:auto"><table class="w-table"><thead><tr><th>Desde</th><th>Hacia</th><th>Código</th><th>Hits</th><th>Último hit</th><th></th></tr></thead><tbody>';
            list.forEach(function(x){h+='<tr><td style="font-family:monospace;font-size:11px;max-width:260px;word-break:break-all">'+W.esc(x.from_path)+'</td><td style="max-width:280px;word-break:break-all">'+W.esc(x.to_url)+'</td><td>'+W.esc(x.status_code)+'</td><td>'+W.num(x.hits||0)+'</td><td style="white-space:nowrap">'+W.esc(String(x.last_hit_at||'—').slice(0,16))+'</td><td><button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.sglRedirDelete('+x.id+')">Eliminar</button></td></tr>'});
            h+='</tbody></table></div>';
        }
        box.innerHTML=h;
        return;
    }
    if(tab==='404s'){
        var r=await W.api('/api/v1/admin/seo-global-launch/404s');
        var list=(r&&r.data&&r.data.items)||[];
        var h='<div class="w-card" style="margin-bottom:14px"><div class="w-flex-between" style="gap:10px;flex-wrap:wrap"><div><h3 style="margin:0">Monitor de 404s</h3><p style="font-size:12px;color:var(--w-muted);margin:5px 0 0">URLs no encontradas que pueden convertirse en redirecciones.</p></div><button class="w-btn w-btn-secondary w-btn-sm" onclick="W.sglPhase2Load(\'404s\')">Recargar</button></div></div>';
        if(!list.length) h+='<div class="w-empty-state"><h3>Sin 404s registrados</h3><p>El monitor empezará a registrar URLs no encontradas.</p></div>';
        else{
            h+='<div class="w-card" style="padding:0;overflow:auto"><table class="w-table"><thead><tr><th>URL</th><th>Hits</th><th>Última visita</th><th>Referrer</th><th></th></tr></thead><tbody>';
            list.forEach(function(x){h+='<tr><td style="font-family:monospace;font-size:11px;max-width:300px;word-break:break-all">'+W.esc(x.url)+'</td><td>'+W.num(x.hits||0)+'</td><td style="white-space:nowrap">'+W.esc(String(x.last_seen||'—').slice(0,16))+'</td><td style="max-width:200px;word-break:break-all;font-size:10px">'+W.esc(x.referrer||'—')+'</td><td style="white-space:nowrap"><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.sgl404Promote(\''+W.esc(x.url)+'\')">Crear redirect</button> <button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.sgl404Delete('+x.id+')">Quitar</button></td></tr>'});
            h+='</tbody></table></div>';
        }
        box.innerHTML=h;
        return;
    }
    var r=await W.api('/api/v1/admin/seo-global-launch/broken-links');
    var list=(r&&r.data&&r.data.items)||[];
    var h='<div class="w-card" style="margin-bottom:14px"><div class="w-flex-between" style="gap:10px;flex-wrap:wrap"><div><h3 style="margin:0">Enlaces rotos</h3><p style="font-size:12px;color:var(--w-muted);margin:5px 0 0">Rastreo interno por lotes con timeout de 5 s. Solo enlaces del dominio del sitio.</p></div><button class="w-btn w-btn-primary w-btn-sm" onclick="W.sglBrokenScan(this)">Escanear lote</button></div><div id="sgl-broken-msg" style="font-size:11px;color:var(--w-muted);margin-top:8px"></div></div>';
    if(!list.length) h+='<div class="w-empty-state"><h3>Sin enlaces rotos</h3><p>Ejecuta un escaneo para comprobar los enlaces internos.</p></div>';
    else{
        h+='<div class="w-card" style="padding:0;overflow:auto"><table class="w-table"><thead><tr><th>Origen</th><th>Destino</th><th>Estado</th><th>Error</th><th>Comprobado</th></tr></thead><tbody>';
        list.forEach(function(x){h+='<tr><td style="max-width:250px;word-break:break-all;font-size:11px">'+W.esc(x.source_url)+'</td><td style="max-width:250px;word-break:break-all;font-size:11px">'+W.esc(x.target_url)+'</td><td>'+W.esc(x.status_code||'0')+'</td><td>'+W.esc(x.error||'—')+'</td><td style="white-space:nowrap">'+W.esc(String(x.checked_at||'—').slice(0,16))+'</td></tr>'});
        h+='</tbody></table></div>';
    }
    box.innerHTML=h;
};
W.sglRedirSave=async function(){
    var v=function(id){var el=document.getElementById(id);return el?el.value:''};
    var r=await W.api('/api/v1/admin/seo-global-launch/redirects',{method:'POST',body:{from_path:v('sgl-redir-from'),to_url:v('sgl-redir-to'),status_code:parseInt(v('sgl-redir-status'))||301,is_active:1}});
    if(r&&r.ok){W.notify('Redirección guardada','success');W.sglPhase2Load('redirecciones')}else W.notify((r&&r.message)||'No se pudo guardar','error');
};
W.sglRedirDelete=function(id){W.confirm('¿Eliminar esta redirección?',async function(){var r=await W.api('/api/v1/admin/seo-global-launch/redirects/'+id,{method:'DELETE'});if(r&&r.ok){W.notify('Redirección eliminada','success');W.sglPhase2Load('redirecciones')}else W.notify((r&&r.message)||'No se pudo eliminar','error')})};
W.sglRedirImport=async function(){
    var el=document.getElementById('sgl-redir-csv');var csv=el?el.value:'';
    var r=await W.api('/api/v1/admin/seo-global-launch/redirects/import',{method:'POST',body:{csv:csv}});
    if(r&&r.ok){W.notify('Importadas: '+(r.created||0),'success');W.sglPhase2Load('redirecciones')}else W.notify((r&&r.message)||'No se pudo importar','error');
};
W.sgl404Delete=async function(id){var r=await W.api('/api/v1/admin/seo-global-launch/404s/'+id,{method:'DELETE'});if(r&&r.ok){W.notify('Registro eliminado','success');W.sglPhase2Load('404s')}else W.notify((r&&r.message)||'No se pudo eliminar','error')};
W.sgl404Promote=function(url){W._sgl404Url=url;location.hash='#seo-global-launch/redirecciones';setTimeout(function(){var f=document.getElementById('sgl-redir-from');if(f)f.value=url},500)};
W.sglBrokenScan=async function(btn){if(btn){btn.disabled=true;btn.textContent='Escaneando...'}var r=await W.api('/api/v1/admin/seo-global-launch/broken-links/scan',{method:'POST',body:{}});if(btn){btn.disabled=false;btn.textContent='Escanear lote'}var m=document.getElementById('sgl-broken-msg');if(m)m.textContent=r&&r.ok?('Revisados: '+(r.checked||0)+' · rotos: '+(r.broken||0)):(r&&r.message||'Error');if(r&&r.ok)W.sglPhase2Load('enlaces-rotos')};

W.refreshJobsBadge=async function(){
    var b=document.getElementById('w-jobs-badge');
    if(!b)return;
    try{
        var r=await fetch('/api/v1/admin/factory/jobs',{headers:{Authorization:'Bearer '+W.token}});
        if(!r.ok)return;
        var d=await r.json();
        var rows=d.data||[];
        var n=rows.filter(function(j){return ['queued','retrying','processing','running'].indexOf(String(j.status))>-1}).length;
        if(n>0){b.style.display='';b.textContent=n+' job'+(n===1?'':'s')}
        else{b.style.display='none'}
    }catch(e){}
};

W.toggleDensity=function(){
    var on=document.body.classList.toggle('w-compact');
    try{localStorage.setItem('wwi_density',on?'compact':'comfort')}catch(e){}
    W.notify(on?'Modo compacto activado':'Modo cómodo activado','info');
};

W.mountTopbar=function(){
    var bar=document.querySelector('.w-topbar');
    if(!bar)return;
    if(document.querySelector('.w-nav-item[data-panel="wwi"]')&&!document.getElementById('w-jobs-badge')){
        var b=document.createElement('a');
        b.id='w-jobs-badge';
        b.className='w-jobs-badge';
        b.href='#wwi/jobs';
        b.title='Trabajos pendientes';
        b.style.display='none';
        bar.insertBefore(b,bar.lastElementChild);
        W.refreshJobsBadge();
        setInterval(W.refreshJobsBadge,60000);
    }
    if(!document.getElementById('w-build-badge')&&window.__WWI_VER){
        var v=document.createElement('span');
        v.id='w-build-badge';
        v.title='Build del admin cargado. Si no ves las mejoras, recarga la página (el SPA no recarga el JS).';
        v.style.cssText='font-family:var(--w-font-mono,monospace);font-size:10px;color:var(--w-muted);border:1px solid var(--w-border2,#2a3346);border-radius:8px;padding:4px 8px;margin-right:12px';
        v.textContent='build '+String(window.__WWI_VER).slice(-6);
        bar.insertBefore(v,bar.lastElementChild);
    }
    if(!document.getElementById('w-density')){
        var d=document.createElement('button');
        d.id='w-density';
        d.className='w-density-btn';
        d.title='Densidad compacta';
        d.textContent='⇕';
        d.addEventListener('click',W.toggleDensity);
        bar.insertBefore(d,bar.lastElementChild);
    }
    try{if(localStorage.getItem('wwi_density')==='compact')document.body.classList.add('w-compact')}catch(e){}
};

(function(){
    var app=document.getElementById('wontia-app');
    if(!app||!window.MutationObserver)return;
    var t=null;
    var obs=new MutationObserver(function(){
        if(W.calm())return;
        if(t)clearTimeout(t);
        t=setTimeout(function(){
            t=null;
            app.classList.remove('w-panel-enter');
            void app.offsetWidth;
            app.classList.add('w-panel-enter');
        },40);
    });
    obs.observe(app,{childList:true});
})();

W.palette=function(){
    var existing=document.getElementById('w-palette-overlay');
    if(existing){existing.remove();return}
    var actions=[
        {label:'Pages · Páginas',hash:'#pages',sub:'Páginas del sitio y su contenido',need:'pages'},
        {label:'Pages · Secciones',hash:'#pages/secciones',sub:'Reordenar secciones (arrastrar y soltar)',need:'pages'},
        {label:'WWI · Sitios',hash:'#wwi/sitios',sub:'Lifecycle de sitios',need:'wwi'},
        {label:'WWI · Pedidos',hash:'#wwi/pedidos',sub:'Pedidos, pagos y provisioning',need:'wwi'},
        {label:'WWI · Dominios',hash:'#wwi/dominios',sub:'Estados de dominio',need:'wwi'},
        {label:'WWI · Actualizaciones',hash:'#wwi/system',sub:'Actualizar sistema desde Git',need:'wwi'},
        {label:'WWI · Jobs',hash:'#wwi/jobs',sub:'Cola de trabajos y provisioning',need:'wwi'},
        {label:'WWI · Planes',hash:'#wwi/planes',sub:'Planes, precios y márgenes',need:'wwi'},
        {label:'WWI · Config',hash:'#wwi/config',sub:'Config del negocio y pagos',need:'wwi'},
        {label:'IA (BRICK) · Overview',hash:'#bricks/ia/overview',sub:'KPIs y presupuesto de IA',need:'bricks'},
        {label:'IA (BRICK) · Policies',hash:'#bricks/ia/policies',sub:'Estrategias y fallback',need:'bricks'},
        {label:'IA (BRICK) · Test',hash:'#bricks/ia/test',sub:'Probar un modelo',need:'bricks'},
        {label:'Brick Marketplace',hash:'#bricks',sub:'Todos los bricks, valoraciones y métricas',need:'bricks'},
        {label:'Bricks Acoplados',hash:'#bricks/instalados',sub:'Gestionar bricks acoplados',need:'bricks'},
        {label:'Bricks · Repos & Sync',hash:'#bricks/repos',sub:'Fuentes GitHub, sync y updates',need:'bricks'},
        {label:'Bricks · Incubadora',hash:'#bricks/incubadora',sub:'Bricks del ecosistema',need:'bricks'},
        {label:'WWI · Sistema',hash:'#wwi',sub:'Estado y configuración',need:'wwi'}
    ].filter(function(a){return document.querySelector('.w-nav-item[data-panel="'+a.need+'"]')});
    var items=[];
    document.querySelectorAll('.w-nav-item').forEach(function(a){
        var t=a.cloneNode(true);
        var b=t.querySelector('.w-badge');
        if(b)b.remove();
        items.push({label:t.textContent.trim(),hash:a.getAttribute('href'),sub:'Panel'});
    });
    items=items.concat(actions);
    if(W._bm&&W._bm.items){
        W._bm.items.forEach(function(it){
            if(it.origin!=='core')return;
            items.push({label:'Brick · '+it.name,sub:'Añadir a página…',action:function(){W.brickAddToPage(it.slug,it.name)}});
        });
    }
    var ov=document.createElement('div');
    ov.className='w-palette-overlay';
    ov.id='w-palette-overlay';
    ov.innerHTML='<div class="w-palette"><input id="w-palette-input" placeholder="Buscar panel o acción…" autocomplete="off"/><div class="w-palette-list" id="w-palette-list"></div><div class="w-palette-foot"><span><kbd>↑↓</kbd>navegar</span><span><kbd>Enter</kbd>abrir</span><span><kbd>Esc</kbd>cerrar</span></div></div>';
    document.body.appendChild(ov);
    var input=ov.querySelector('#w-palette-input');
    var list=ov.querySelector('#w-palette-list');
    var sel=0,cur=items;
    function fuzzy(q,s){
        q=q.toLowerCase();s=s.toLowerCase();
        if(s.indexOf(q)>-1)return true;
        var i=0;
        for(var j=0;j<s.length&&i<q.length;j++){if(s[j]===q[i])i++}
        return i===q.length;
    }
    function render(){
        if(!cur.length){list.innerHTML='<div class="w-palette-empty">Sin resultados</div>';return}
        list.innerHTML=cur.map(function(it,i){
            return '<div class="w-palette-item'+(i===sel?' sel':'')+'" data-i="'+i+'"><span>'+W.esc(it.label)+'</span><span class="sub">'+W.esc(it.sub||'')+'</span></div>';
        }).join('');
        var el=list.querySelector('.w-palette-item.sel');
        if(el)el.scrollIntoView({block:'nearest'});
    }
    function go(i){
        var it=cur[i];
        if(!it)return;
        ov.remove();
        if(it.action){it.action();return}
        window.location.hash=it.hash;
    }
    function filter(){
        var q=input.value.trim();
        cur=q?items.filter(function(it){return fuzzy(q,it.label+' '+(it.sub||''))}):items;
        sel=0;
        render();
    }
    input.addEventListener('input',filter);
    input.addEventListener('keydown',function(e){
        if(e.key==='ArrowDown'){e.preventDefault();sel=Math.min(sel+1,cur.length-1);render()}
        else if(e.key==='ArrowUp'){e.preventDefault();sel=Math.max(sel-1,0);render()}
        else if(e.key==='Enter'){e.preventDefault();go(sel)}
        else if(e.key==='Escape'){ov.remove()}
    });
    list.addEventListener('click',function(e){
        var item=e.target.closest('.w-palette-item');
        if(item)go(parseInt(item.dataset.i)||0);
    });
    ov.addEventListener('mousedown',function(e){if(e.target===ov)ov.remove()});
    render();
    input.focus();
};
document.addEventListener('keydown',function(e){
    if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();W.palette()}
    else if(e.key==='Escape'&&document.getElementById('w-palette-overlay')){document.getElementById('w-palette-overlay').remove()}
});

W.storeGo=function(t){window.location.hash='#store/'+t};

W.renderStore=async function(tab){
    tab=tab||'productos';
    W.state.storeTab=tab;
    var app=document.getElementById('wontia-app');
    var tabs=[['productos','Productos'],['contenido','Contenido'],['categorias','Categorías'],['pedidos','Pedidos'],['envios','Envíos'],['resenas','Reseñas'],['devoluciones','Devoluciones'],['cupones','Cupones'],['addi','Addi'],['config','Configuración']];
    var bar='<div class="w-brick-tabs">';
    tabs.forEach(function(t){bar+='<button class="w-brick-tab'+(tab===t[0]?' active':'')+'" onclick="wontia.storeGo(\''+t[0]+'\')">'+t[1]+'</button>'});
    bar+='<div style="flex:1"></div><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.storeEnsure()">Setup Tables</button></div>';
    app.innerHTML=bar+'<div id="store-content"><div style="text-align:center;padding:40px;color:var(--w-muted)">Cargando tienda...</div></div>';
    var ov=await W.api('/api/v1/admin/store/overview');
    if(ov.ok&&ov.data&&ov.data.ready===false){
        document.getElementById('store-content').innerHTML='<div class="w-empty-state"><h3>Tienda no inicializada</h3><p>Crea las tablas de la tienda para empezar a vender.</p><button class="w-btn w-btn-primary" style="margin-top:12px" onclick="wontia.storeEnsure()">Crear tablas de la tienda</button></div>';
        return;
    }
    var fns={productos:W.storeProducts,contenido:W.storeContentAudit,categorias:W.storeCategories,pedidos:W.storeOrders,envios:W.storeShipping,resenas:W.storeReviews,devoluciones:W.storeReturns,cupones:W.storeCoupons,addi:W.storeAddi,config:W.storeSettings};
    (fns[tab]||W.storeProducts)();
};

W.storeReviews=async function(){
    var el=document.getElementById('store-content');
    el.innerHTML='<div style="color:var(--w-muted);padding:20px">Cargando reseñas...</div>';
    var r=await W.api('/api/v1/admin/store/reviews');
    if(!r.ok){el.innerHTML='<div class="w-empty-state"><h3>No se pudieron cargar las reseñas</h3></div>';return}
    var items=(r.data&&r.data.items)||[];
    var h='<div class="w-flex-between" style="margin-bottom:14px"><h3>Reseñas '+(r.data.pending?('<span class="w-badge">'+r.data.pending+' pendientes</span>'):'')+'</h3><select class="w-input" style="width:160px" onchange="wontia.storeReviews()"><option value="">Todas</option><option value="pending">Pendientes</option><option value="approved">Aprobadas</option><option value="rejected">Rechazadas</option></select></div>';
    if(!items.length){el.innerHTML=h+'<div class="w-empty-state"><h3>Sin reseñas</h3><p>Aún no hay reseñas de productos.</p></div>';return}
    h+='<table class="w-table" style="width:100%"><thead><tr><th>Producto</th><th>Autor</th><th>Nota</th><th>Reseña</th><th>Estado</th><th></th></tr></thead><tbody>';
    items.forEach(function(x){var st=x.status;var color=st==='approved'?'#1E7A4B':(st==='rejected'?'#DC2626':'#B45309');var stars='';for(var i=1;i<=5;i++)stars+=(i<=(x.rating|0)?'★':'☆');
        h+='<tr><td>'+W.esc(x.product_ref)+'</td><td>'+W.esc(x.author_name)+(x.verified_purchase?' ✓':'')+'</td><td>'+stars+'</td><td style="max-width:320px">'+W.esc(((x.title?x.title+' — ':'')+(x.body||'')).slice(0,180))+'</td><td style="color:'+color+'">'+st+'</td><td style="white-space:nowrap">'
            +(st!=='approved'?'<button class="w-btn w-btn-sm" onclick="wontia.storeReviewMod(\''+x.id+'\',\'approved\')">Aprobar</button> ':'')
            +(st!=='rejected'?'<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.storeReviewMod(\''+x.id+'\',\'rejected\')">Rechazar</button> ':'')
            +'<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.storeReviewDel(\''+x.id+'\')">🗑</button></td></tr>';});
    h+='</tbody></table>';
    el.innerHTML=h;
};
W.storeReviewMod=async function(id,status){var r=await W.api('/api/v1/admin/store/reviews/'+id,{method:'PATCH',body:{status:status}});if(r.ok){W.notify('Reseña actualizada','success');W.storeReviews()}else W.notify((r.data&&r.data.message)||'Error','error')};
W.storeReviewDel=async function(id){if(!confirm('¿Eliminar esta reseña?'))return;var r=await W.api('/api/v1/admin/store/reviews/'+id,{method:'DELETE'});if(r.ok){W.notify('Reseña eliminada','success');W.storeReviews()}};

W.storeReturns=async function(){
    var el=document.getElementById('store-content');
    el.innerHTML='<div style="color:var(--w-muted);padding:20px">Cargando devoluciones...</div>';
    var r=await W.api('/api/v1/admin/store/returns');
    if(!r.ok){el.innerHTML='<div class="w-empty-state"><h3>No se pudieron cargar las devoluciones</h3></div>';return}
    var items=(r.data&&r.data.items)||[];
    var h='<div class="w-flex-between" style="margin-bottom:14px"><h3>Devoluciones '+(r.data.requested?('<span class="w-badge">'+r.data.requested+' por revisar</span>'):'')+'</h3></div>';
    if(!items.length){el.innerHTML=h+'<div class="w-empty-state"><h3>Sin devoluciones</h3><p>No hay solicitudes de devolución.</p></div>';return}
    h+='<table class="w-table" style="width:100%"><thead><tr><th>Pedido</th><th>Cliente</th><th>Motivo</th><th>Estado</th><th>Reembolso</th><th></th></tr></thead><tbody>';
    items.forEach(function(x){var st=x.status;var color={requested:'#B45309',approved:'#2563EB',rejected:'#DC2626',refunded:'#1E7A4B'}[st]||'#555';
        h+='<tr><td>'+W.esc(x.order_number||x.uuid||('#'+x.order_id))+'</td><td>'+W.esc(x.customer_email||'—')+'</td><td style="max-width:260px">'+W.esc((x.reason||'').slice(0,140))+'</td><td style="color:'+color+'">'+st+'</td><td>'+W.esc(x.refund_cents?('$'+(x.refund_cents/100).toLocaleString('es-CO')):'—')+'</td><td style="white-space:nowrap">'
            +(st!=='refunded'?'<button class="w-btn w-btn-sm" onclick="wontia.storeReturnSet(\''+x.id+'\',\'refunded\')">Reembolsar</button> ':'')
            +(st==='requested'?'<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.storeReturnSet(\''+x.id+'\',\'approved\')">Aprobar</button> ':'')
            +(st==='requested'?'<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.storeReturnSet(\''+x.id+'\',\'rejected\')">Rechazar</button>':'')
            +'</td></tr>';});
    h+='</tbody></table>';
    el.innerHTML=h;
};
W.storeReturnSet=async function(id,status){var restock=(status==='refunded')?confirm('¿Devolver el stock al inventario?'):false;var r=await W.api('/api/v1/admin/store/returns/'+id,{method:'PATCH',body:{status:status,restock:restock}});if(r.ok){W.notify('Devolución actualizada','success');W.storeReturns()}else W.notify((r.data&&r.data.message)||'Error','error')};

W.storeCoupons=async function(){
    var el=document.getElementById('store-content');
    el.innerHTML='<div style="color:var(--w-muted);padding:20px">Cargando cupones…</div>';
    var r=await W.api('/api/v1/admin/store/coupons');
    var items=(r&&r.data)||[];
    var h='<div class="w-flex-between" style="margin-bottom:14px"><h3 style="margin:0">Cupones</h3><button class="w-btn w-btn-primary" onclick="wontia.storeCouponNew()">＋ Nuevo cupón</button></div>';
    if(!items.length){h+='<div class="w-empty-state"><h3>Sin cupones</h3><p>Crea tu primer cupón (porcentaje o monto fijo).</p></div>';el.innerHTML=h;return}
    h+='<table class="w-table"><thead><tr><th>Código</th><th>Tipo</th><th>Valor</th><th>Mín.</th><th>Usos</th><th>Vence</th><th>Activo</th><th></th></tr></thead><tbody>';
    items.forEach(function(c){h+='<tr><td><b>'+W.esc(c.code)+'</b></td><td>'+W.esc(c.type)+'</td><td>'+(c.type==='percent'?(c.value+'%'):('$'+(c.value/100).toLocaleString('es-CO')))+'</td><td>'+(+c.min_order_cents?('$'+(c.min_order_cents/100).toLocaleString('es-CO')):'—')+'</td><td>'+c.used+'/'+(+c.max_uses||'∞')+'</td><td>'+W.esc(c.expires_at?String(c.expires_at).slice(0,10):'—')+'</td><td>'+(+c.active?'Sí':'No')+'</td><td><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.storeCouponEdit('+c.id+')">✎</button> <button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.storeCouponDel('+c.id+')">🗑</button></td></tr>'});
    h+='</tbody></table>';
    el.innerHTML=h;
};
W.storeCouponNew=function(){W.storeCouponEditor({})};
W.storeCouponEdit=async function(id){var r=await W.api('/api/v1/admin/store/coupons');var c=((r&&r.data)||[]).find(function(x){return +x.id===+id})||{};W.storeCouponEditor(c)};
W.storeCouponEditor=function(c){
    var html='<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Código</label><input class="w-input" id="cp-code" value="'+W.esc(c.code||'')+'" placeholder="VERANO10"/></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Tipo</label><select class="w-input" id="cp-type"><option value="percent"'+(c.type==='percent'?' selected':'')+'>Porcentaje (%)</option><option value="fixed"'+(c.type==='fixed'?' selected':'')+'>Fijo (centavos)</option></select></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Valor</label><input class="w-input" type="number" id="cp-val" value="'+(c.value||'')+'"/></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Mínimo (centavos)</label><input class="w-input" type="number" id="cp-min" value="'+(c.min_order_cents||0)+'"/></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Máx. usos (0 = ∞)</label><input class="w-input" type="number" id="cp-uses" value="'+(c.max_uses||0)+'"/></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Vence (YYYY-MM-DD)</label><input class="w-input" id="cp-exp" value="'+W.esc(c.expires_at?String(c.expires_at).slice(0,10):'')+'"/></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Activo</label><select class="w-input" id="cp-active"><option value="1"'+((c.active===undefined||+c.active)?' selected':'')+'>Sí</option><option value="0"'+((c.active!==undefined&&!+c.active)?' selected':'')+'>No</option></select></div>'
      +'</div>';
    W.modal((c.id?'Editar':'Nuevo')+' cupón',html,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancelar</button><button class="w-btn w-btn-primary" onclick="wontia.storeCouponSave('+(c.id||0)+')">Guardar</button>');
};
W.storeCouponSave=async function(id){
    var body={code:document.getElementById('cp-code').value,type:document.getElementById('cp-type').value,value:parseInt(document.getElementById('cp-val').value)||0,min_order_cents:parseInt(document.getElementById('cp-min').value)||0,max_uses:parseInt(document.getElementById('cp-uses').value)||0,expires_at:document.getElementById('cp-exp').value,active:document.getElementById('cp-active').value==='1'};
    var r=id?await W.api('/api/v1/admin/store/coupons/'+id,{method:'PUT',body:body}):await W.api('/api/v1/admin/store/coupons',{method:'POST',body:body});
    if(r.ok){W.closeModal();W.notify('Cupón guardado','success');W.storeCoupons()}else W.notify((r.data&&r.data.message)||'Error','error');
};
W.storeCouponDel=async function(id){if(!confirm('¿Eliminar el cupón?'))return;var r=await W.api('/api/v1/admin/store/coupons/'+id,{method:'DELETE'});if(r.ok)W.storeCoupons()};

W.storeEnsure=async function(){
    var r=await W.api('/api/v1/admin/store/ensure-tables',{method:'POST'});
    if(r.ok){W.notify('Tablas de tienda listas','success');W.renderStore(W.state.storeTab||'productos')}
    else W.notify((r.data&&r.data.message)||'Error al crear tablas','error');
};

W.storeContentAudit=async function(page){
    page=Math.max(0,parseInt(page||0,10)||0);
    var el=document.getElementById('store-content');
    if(!el)return;
    var qEl=document.getElementById('store-content-q');
    var iEl=document.getElementById('store-content-issue');
    var fEl=document.getElementById('store-content-format');
    var sEl=document.getElementById('store-content-sort');
    var q=qEl?qEl.value:'';
    var issue=iEl?iEl.value:'';
    var format=fEl?fEl.value:'';
    var sort=sEl?sEl.value:'score';
    var limit=50;
    W._clExportFilters={q:q,filter:(issue==='empty'?'empty':'all'),status:'',vendor:''};
    var yta=new URLSearchParams(location.search).get('yta');
    if(yta){W.notify(yta==='ok'?'Canal de YouTube conectado':'No se pudo conectar YouTube: '+new URLSearchParams(location.search).get('msg'),yta==='ok'?'success':'error');try{history.replaceState(null,'',location.pathname+'?panel=store'+location.hash)}catch(e){}}
    el.innerHTML='<div style="padding:34px;text-align:center;color:var(--w-muted)">Analizando el catálogo editorial…</div>';
    var r=await W.api('/api/v1/admin/store/content-audit?limit='+limit+'&offset='+(page*limit)+'&q='+encodeURIComponent(q)+'&issue='+encodeURIComponent(issue)+'&format='+encodeURIComponent(format)+'&sort='+encodeURIComponent(sort));
    if(!r.ok||!r.data){
        el.innerHTML='<div class="w-empty-state"><h3>No se pudo cargar la auditoría</h3><p>'+W.esc((r.data&&r.data.message)||'Revisa los logs del servidor.')+'</p></div>';
        return;
    }
    var d=r.data,s=d.summary||{},rows=d.rows||[],total=+d.total||0;
    function fmt(n){return (+n||0).toLocaleString('es-CO')}
    function metric(label,value,color){
        return '<div style="padding:12px 14px;border:1px solid var(--w-border);border-radius:12px;background:var(--w-surface)"><div style="font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--w-muted)">'+label+'</div><div style="font-size:22px;font-weight:800;line-height:1.1;margin-top:4px;'+(color?'color:'+color:'')+'">'+value+'</div></div>';
    }
    function options(list,current){
        var out='';
        list.forEach(function(item){
            out+='<option value="'+W.esc(item[0])+'"'+(item[0]===current?' selected':'')+'>'+W.esc(item[1])+'</option>';
        });
        return out;
    }
    var issueOptions=[['','Todas las alertas'],['empty','Sin descripción'],['only_text','Solo texto'],['no_specs','Sin ficha técnica'],['no_video','Sin video'],['inline_styles','Estilos inline'],['too_long','Muy larga'],['too_short','Muy corta'],['long_text','Texto extenso']];
    var formatOptions=[['','Todos los formatos'],['video','Con video'],['image','Con imagen'],['table','Con tabla'],['list','Con lista'],['styled','Con estilos inline'],['only_text','Solo texto']];
    var sortOptions=[['score','Peor score primero'],['chars_desc','Más largas primero'],['chars_asc','Más cortas primero'],['title','Título A-Z'],['recent','Handle A-Z']];
    var buckets=['0-39','40-59','60-79','80-100'];
    var h='<div class="w-card" style="padding:18px;margin-bottom:14px">'
        +'<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap">'
        +'<div><h2 style="margin:0;font-size:20px">Contenido editorial</h2><p style="margin:6px 0 0;color:var(--w-muted);font-size:12.5px;max-width:700px;line-height:1.55">Auditoría de las '+fmt(s.total)+' descripciones del catálogo. Detecta vacíos, solo texto, falta de ficha o video, estilos heredados y textos demasiado largos antes de que lleguen al cliente.</p></div>'
        +'<div style="display:flex;gap:8px;flex-wrap:wrap">'
        +'<button class="w-btn w-btn-primary" onclick="wontia.storeContentLab()">🧪 Abrir Content Fix Lab</button>'
        +'<button class="w-btn w-btn-secondary" onclick="wontia.storeContentLabExport()">⬇ Descargar .xls</button>'
        +'<button class="w-btn w-btn-secondary" onclick="wontia.storeContentLabMicheScan()">🔄 Escanear Miche</button>'
        +'<button class="w-btn '+(d.enabled?'w-btn-primary':'w-btn-secondary')+'" onclick="wontia.storeContentToggle('+(d.enabled?1:0)+')">'+(d.enabled?'● Normalizador activo':'○ Normalizador desactivado')+'</button>'
        +'<button class="w-btn w-btn-secondary" onclick="wontia.storeContentAudit('+page+')">↻ Actualizar</button>'
        +'</div></div>'
        +'<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(132px,1fr));gap:10px;margin-top:16px">'
        +metric('Productos',fmt(s.total))
        +metric('Con descripción',fmt(s.with_description))
        +metric('Sin descripción',fmt(s.empty),s.empty?'#B45309':'')
        +metric('Solo texto',fmt(s.only_text))
        +metric('Con video',fmt(s.video))
        +metric('Score promedio',fmt(s.avg_score)+'/100')
        +'</div>'
        +'<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:12px;align-items:center">'
        +'<span style="font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--w-muted);margin-right:4px">Salud editorial</span>';
    buckets.forEach(function(b){
        var count=(s.score_buckets&&s.score_buckets[b])||0;
        var color=b==='0-39'?'#B42318':(b==='40-59'?'#B45309':(b==='60-79'?'#2563EB':'#067647'));
        h+='<span style="display:inline-flex;align-items:center;gap:6px;border:1px solid var(--w-border);border-radius:999px;padding:4px 9px;font-size:11px;font-weight:700;color:var(--w-text)"><i style="width:7px;height:7px;border-radius:50%;background:'+color+';display:inline-block"></i>'+b+' · '+fmt(count)+'</span>';
    });
    h+='</div></div>'
        +'<div class="w-card" style="padding:12px;margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">'
        +'<input class="w-input" id="store-content-q" style="width:270px" placeholder="Buscar producto, handle o marca…" value="'+W.esc(q)+'" onkeydown="if(event.key===\'Enter\'){wontia.storeContentAudit(0)}"/>'
        +'<select class="w-input" id="store-content-issue" style="width:185px" onchange="wontia.storeContentAudit(0)">'+options(issueOptions,issue)+'</select>'
        +'<select class="w-input" id="store-content-format" style="width:175px" onchange="wontia.storeContentAudit(0)">'+options(formatOptions,format)+'</select>'
        +'<select class="w-input" id="store-content-sort" style="width:185px" onchange="wontia.storeContentAudit(0)">'+options(sortOptions,sort)+'</select>'
        +'<button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.storeContentAudit(0)">Filtrar</button>'
        +'<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.storeContentReset()">Limpiar</button>'
        +'<span style="margin-left:auto;font-size:12px;color:var(--w-muted)">'+fmt(total)+' resultados</span></div>';
    if(!rows.length){
        h+='<div class="w-empty-state"><h3>Sin resultados</h3><p>Prueba otro filtro o limpia la búsqueda.</p></div>';
        el.innerHTML=h;
        return;
    }
    h+='<div class="w-card" style="padding:0;overflow:auto"><table class="w-table" style="width:100%;min-width:880px"><thead><tr style="text-align:left;color:var(--w-muted);font-size:10.5px;text-transform:uppercase;letter-spacing:.05em"><th style="padding:11px 12px">Producto</th><th style="padding:11px 12px">Formato</th><th style="padding:11px 12px">Caracteres</th><th style="padding:11px 12px">Score</th><th style="padding:11px 12px">Alertas</th><th style="padding:11px 12px"></th></tr></thead><tbody>';
    rows.forEach(function(x){
        var score=+x.score||0;
        var color=score<40?'#B42318':(score<70?'#B45309':'#067647');
        var formats=[];
        if(x.has_video)formats.push('Video');
        if(x.has_table)formats.push('Tabla');
        else if(x.has_list)formats.push('Lista');
        if(x.has_image)formats.push('Imagen');
        if(x.only_text)formats.push('Solo texto');
        if(x.inline_styles)formats.push('Estilos');
        var formatHtml=formats.length?formats.map(function(f){return '<span style="display:inline-block;border:1px solid var(--w-border);border-radius:6px;padding:2px 6px;font-size:10px;font-weight:700;margin:1px 2px 1px 0;color:var(--w-text)">'+W.esc(f)+'</span>'}).join(''):'<span style="color:var(--w-muted)">—</span>';
        var issueHtml=(x.issues||[]).slice(0,3).map(function(t){return '<span style="display:inline-block;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;border-radius:6px;padding:2px 6px;font-size:10px;font-weight:700;margin:1px 2px 1px 0">'+W.esc(t)+'</span>'}).join('')||'<span style="color:#067647;font-size:11px;font-weight:700">Sin alertas</span>';
        h+='<tr>'
            +'<td style="padding:10px 12px;max-width:330px"><a href="'+W.esc(x.url)+'" target="_blank" rel="noopener" style="font-weight:800;color:var(--w-text);text-decoration:none">'+W.esc(x.title||x.handle)+'</a><div style="font-size:10.5px;color:var(--w-muted);margin-top:2px">'+W.esc(x.handle)+(x.vendor?' · '+W.esc(x.vendor):'')+'</div></td>'
            +'<td style="padding:10px 12px">'+formatHtml+'</td>'
            +'<td style="padding:10px 12px;white-space:nowrap"><b style="font-size:13px">'+fmt(x.chars)+'</b><div style="font-size:10px;color:var(--w-muted)">'+fmt(x.words)+' palabras</div></td>'
            +'<td style="padding:10px 12px;min-width:90px"><b style="font-size:13px;color:'+color+'">'+score+'</b><span style="display:block;height:4px;background:var(--w-border);border-radius:99px;margin-top:4px;overflow:hidden"><i style="display:block;height:100%;width:'+score+'%;background:'+color+'"></i></span></td>'
            +'<td style="padding:10px 12px;max-width:260px">'+issueHtml+'</td>'
            +'<td style="padding:10px 12px;white-space:nowrap"><a class="w-btn w-btn-secondary w-btn-sm" href="'+W.esc(x.url)+'" target="_blank" rel="noopener">Ver ficha</a></td>'
            +'</tr>';
    });
    h+='</tbody></table></div>';
    var pages=Math.ceil(total/limit);
    if(pages>1){
        h+='<div style="display:flex;justify-content:center;gap:8px;align-items:center;margin-top:14px;flex-wrap:wrap">'
            +'<button class="w-btn w-btn-secondary w-btn-sm" '+(page<=0?'disabled':'')+' onclick="wontia.storeContentAudit('+(page-1)+')">‹ Anterior</button>'
            +'<span style="font-size:12px;color:var(--w-muted)">Página '+(page+1)+' de '+pages+'</span>'
            +'<button class="w-btn w-btn-secondary w-btn-sm" '+(page>=pages-1?'disabled':'')+' onclick="wontia.storeContentAudit('+(page+1)+')">Siguiente ›</button></div>';
    }
    el.innerHTML=h;
};

W.storeContentReset=function(){
    var q=document.getElementById('store-content-q');
    var issue=document.getElementById('store-content-issue');
    var format=document.getElementById('store-content-format');
    var sort=document.getElementById('store-content-sort');
    if(q)q.value='';
    if(issue)issue.value='';
    if(format)format.value='';
    if(sort)sort.value='score';
    W.storeContentAudit(0);
};

W.storeContentToggle=async function(current){
    var next=current?'0':'1';
    var r=await W.api('/api/v1/admin/store/settings',{method:'PUT',body:{product_content_v2:next}});
    if(r.ok){
        W.notify(next==='1'?'Normalizador editorial activado':'Normalizador editorial desactivado','success');
        W.storeContentAudit(0);
    } else {
        W.notify((r.data&&r.data.message)||'No se pudo cambiar el normalizador','error');
    }
};

// ── Content Fix Lab ─────────────────────────────
W._clState={page:0,filter:'empty',status:'',q:'',vendor:''};
W._clJob=null;
W.clKpi=function(v,l,c){return '<div class="w-card" style="flex:1;min-width:125px;text-align:center;margin:0"><div style="font-size:22px;font-weight:800;'+(c?'color:'+c:'')+'">'+W.esc(String(v))+'</div><div style="font-size:10px;color:var(--w-muted);margin-top:4px">'+W.esc(l)+'</div></div>'};
W.clBadge=function(text,color){return '<span style="display:inline-block;border:1px solid '+color+'33;background:'+color+'11;color:'+color+';border-radius:999px;padding:2px 7px;font-size:10px;font-weight:800">'+W.esc(text)+'</span>'};
W.storeContentLab=async function(page,filter,status,q,vendor){
    page=Math.max(0,parseInt(page||0,10)||0);
    if(filter!==undefined)W._clState.filter=filter;
    if(status!==undefined)W._clState.status=status;
    if(q!==undefined)W._clState.q=q;
    if(vendor!==undefined)W._clState.vendor=vendor;
    W._clState.page=page;
    W._clExportFilters={q:W._clState.q,filter:W._clState.filter,status:W._clState.status,vendor:W._clState.vendor};
    var el=document.getElementById('store-content'); if(!el)return;
    el.innerHTML='<div style="padding:34px;text-align:center;color:var(--w-muted)">Cargando Content Fix Lab…</div>';
    var qs='?limit=50&offset='+(page*50)+'&filter='+encodeURIComponent(W._clState.filter)+'&status='+encodeURIComponent(W._clState.status)+'&q='+encodeURIComponent(W._clState.q)+'&vendor='+encodeURIComponent(W._clState.vendor);
    var res=await Promise.all([
        W.api('/api/v1/admin/store/content-lab/summary'),
        W.api('/api/v1/admin/store/content-lab/items'+qs),
        W.api('/api/v1/admin/store/content-lab/video/status').catch(function(){return{ok:false}})
    ]);
    var sum=(res[0]&&res[0].data)||{}, d=(res[1]&&res[1].data)||{}, video=(res[2]&&res[2].data)||{};
    var rows=d.items||[],total=+d.total||0;
    var yt=video.youtube||{};
    var h='<div class="w-card" style="padding:18px;margin-bottom:14px"><div class="w-flex-between" style="gap:12px;flex-wrap:wrap"><div><div style="font-size:11px;color:var(--w-muted)">Tienda › Contenido › Content Fix Lab</div><h2 style="margin:2px 0 0;font-size:20px">🧪 Content Fix Lab</h2><p style="margin:6px 0 0;color:var(--w-muted);font-size:12.5px;max-width:760px;line-height:1.55">Investigación en dominios oficiales, verificación exacta del producto, generación con TIA y publicación con rollback. Nada se aplica sin confirmación humana.</p></div><div style="display:flex;gap:7px;flex-wrap:wrap"><button class="w-btn w-btn-secondary" onclick="wontia.storeContentAudit(0)">← Auditoría</button><button class="w-btn w-btn-secondary" onclick="wontia.storeContentLabExport()">⬇ .xls</button><button class="w-btn w-btn-secondary" onclick="wontia.storeContentLabMicheScan()">🔄 Miche</button><button class="w-btn '+(yt.connected?'w-btn-primary':'w-btn-secondary')+'" onclick="wontia.storeContentLabYoutube()">'+(yt.connected?'● YouTube conectado':'Conectar YouTube')+'</button></div></div>';
    h+='<div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px">'+W.clKpi(W.num(sum.products||0),'Productos')+W.clKpi(W.num(sum.empty||0),'Sin descripción','#B45309')+W.clKpi(W.num(sum.needs_review||0),'Por confirmar','#B45309')+W.clKpi(W.num(sum.generated||0),'Generadas','#2563EB')+W.clKpi(W.num(sum.applied||0),'Aplicadas','#067647')+W.clKpi(W.num(sum.miche_updates||0),'Miche actualizó','#7C3AED')+W.clKpi((video.yt_dlp?'✓':'✗')+' / '+(video.ffmpeg?'✓':'✗'),'yt-dlp / ffmpeg',video.yt_dlp&&video.ffmpeg?'#067647':'#B42318')+'</div></div>';
    h+='<div class="w-card" style="padding:12px;margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">'
        +'<input class="w-input" id="cl-q" style="width:280px" placeholder="Buscar producto, handle, marca o SKU…" value="'+W.esc(W._clState.q)+'" onkeydown="if(event.key===\'Enter\'){wontia.storeContentLab(0,undefined,undefined,this.value)}"/>'
        +'<select class="w-input" id="cl-filter" style="width:150px" onchange="wontia.storeContentLab(0,this.value)"><option value="empty"'+(W._clState.filter==='empty'?' selected':'')+'>Sin descripción</option><option value="all"'+(W._clState.filter==='all'?' selected':'')+'>Todo el catálogo</option></select>'
        +'<select class="w-input" id="cl-status" style="width:185px" onchange="wontia.storeContentLab(0,undefined,this.value)"><option value="">Todos los estados</option><option value="no_job"'+(W._clState.status==='no_job'?' selected':'')+'>Sin iniciar</option><option value="needs_review"'+(W._clState.status==='needs_review'?' selected':'')+'>Por confirmar</option><option value="generated"'+(W._clState.status==='generated'?' selected':'')+'>Generadas</option><option value="applied"'+(W._clState.status==='applied'?' selected':'')+'>Aplicadas</option><option value="miche_update"'+(W._clState.status==='miche_update'?' selected':'')+'>Miche actualizó</option><option value="failed"'+(W._clState.status==='failed'?' selected':'')+'>Sin coincidencia</option></select>'
        +'<button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.storeContentLab(0,undefined,undefined,document.getElementById(\'cl-q\').value)">Filtrar</button>'
        +'<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.storeContentLab(0,\'empty\',\'\',\'\',\'\')">Limpiar</button>'
        +'<span style="margin-left:auto;font-size:12px;color:var(--w-muted)">'+W.num(total)+' productos</span></div>';
    if(!rows.length){h+='<div class="w-empty-state"><h3>Sin productos en este filtro</h3><p>Prueba “Todo el catálogo” o cambia el estado.</p></div>';el.innerHTML=h;return}
    h+='<div class="w-card" style="padding:0;overflow:auto"><table class="w-table" style="width:100%;min-width:980px"><thead><tr style="text-align:left;color:var(--w-muted);font-size:10.5px;text-transform:uppercase;letter-spacing:.05em"><th style="padding:10px 12px">Producto</th><th style="padding:10px 12px">Score / alertas</th><th style="padding:10px 12px">Laboratorio</th><th style="padding:10px 12px">Confianza</th><th style="padding:10px 12px">Fuente</th><th style="padding:10px 12px">Video</th><th style="padding:10px 12px"></th></tr></thead><tbody>';
    rows.forEach(function(x){
        var score=+x.score||0,color=score<40?'#B42318':(score<70?'#B45309':'#067647');
        var issues=(x.issues||[]).slice(0,3).map(function(t){return '<span style="display:inline-block;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;border-radius:6px;padding:1px 5px;font-size:9.5px;font-weight:700;margin:1px 2px 1px 0">'+W.esc(t)+'</span>'}).join('')||'<span style="color:#067647;font-size:10.5px;font-weight:700">Sin alertas</span>';
        var st=x.job_status,stColor=st==='applied'?'#067647':(st==='generated'?'#2563EB':(st==='needs_review'?'#B45309':(st==='failed'?'#B42318':'#667085')));
        var actions='';
        if(x.job_id)actions+='<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.storeContentLabOpen('+x.job_id+')">Abrir</button> ';
        else actions+='<button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.storeContentLabResearch('+x.id+',this)">Investigar</button> ';
        if(st==='generated')actions+='<button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.storeContentLabApply('+x.job_id+')">Aplicar</button> ';
        if(st==='applied')actions+='<a class="w-btn w-btn-secondary w-btn-sm" href="'+W.esc(x.live_url)+'" target="_blank" rel="noopener">Ver vivo</a>';
        h+='<tr><td style="padding:10px 12px;max-width:300px"><a href="'+W.esc(x.live_url)+'" target="_blank" rel="noopener" style="font-weight:800;color:var(--w-text);text-decoration:none">'+W.esc(x.name)+'</a><div style="font-size:10.5px;color:var(--w-muted);margin-top:2px">'+W.esc(x.handle)+(x.vendor?' · '+W.esc(x.vendor):'')+'</div></td>'
            +'<td style="padding:10px 12px;min-width:150px"><b style="color:'+color+'">'+score+'</b> <span style="font-size:10px;color:var(--w-muted)">/100 · '+W.num(x.description_chars)+' car.</span><div style="margin-top:3px">'+issues+'</div></td>'
            +'<td style="padding:10px 12px">'+W.clBadge(x.job_status_label||'Sin iniciar',stColor)+'</td>'
            +'<td style="padding:10px 12px;min-width:90px">'+(x.confidence?('<b>'+x.confidence+'%</b><span style="display:block;height:4px;background:var(--w-border);border-radius:99px;margin-top:3px;overflow:hidden"><i style="display:block;height:100%;width:'+x.confidence+'%;background:'+(x.confidence>=85?'#067647':'#B45309')+'"></i></span>'):'<span style="color:var(--w-muted)">—</span>')+'</td>'
            +'<td style="padding:10px 12px;max-width:220px;word-break:break-all">'+(x.source_url?'<a href="'+W.esc(x.source_url)+'" target="_blank" rel="noopener" style="font-size:10.5px">'+W.esc(x.source_domain||'fuente')+'</a>':'<span style="color:var(--w-muted);font-size:10.5px">—</span>')+'</td>'
            +'<td style="padding:10px 12px">'+(x.youtube_url?'<a href="'+W.esc(x.youtube_url)+'" target="_blank" rel="noopener" style="font-size:10.5px">YouTube</a>':'<span style="font-size:10.5px;color:var(--w-muted)">'+W.esc(x.video_status||'none')+'</span>')+'</td>'
            +'<td style="padding:10px 12px;white-space:nowrap">'+actions+'</td></tr>';
    });
    h+='</tbody></table></div>';
    var pages=Math.ceil(total/50);
    if(pages>1){h+='<div style="display:flex;justify-content:center;gap:8px;align-items:center;margin-top:14px"><button class="w-btn w-btn-secondary w-btn-sm" '+(page<=0?'disabled':'')+' onclick="wontia.storeContentLab('+(page-1)+')">‹ Anterior</button><span style="font-size:12px;color:var(--w-muted)">Página '+(page+1)+' de '+pages+'</span><button class="w-btn w-btn-secondary w-btn-sm" '+(page>=pages-1?'disabled':'')+' onclick="wontia.storeContentLab('+(page+1)+')">Siguiente ›</button></div>'}
    el.innerHTML=h;
};
W.storeContentLabResearch=async function(productId,btn){
    if(btn){btn.disabled=true;btn.textContent='Investigando…'}
    W.notify('Buscando en dominios oficiales y verificando identidad…','info');
    var r=await W.api('/api/v1/admin/store/content-lab/research',{method:'POST',body:{product_id:productId}});
    if(btn){btn.disabled=false;btn.textContent='Investigar'}
    if(r&&r.job){W.notify(r.message||'Investigación completada',r.ok?'success':'error');W.storeContentLabOpen(r.job.id)}
    else W.notify((r&&r.message)||'No se pudo investigar','error');
    W.storeContentLab(W._clState.page);
};
W.storeContentLabOpen=async function(jobId){
    var r=await W.api('/api/v1/admin/store/content-lab/job/'+jobId);
    if(!r||!r.ok||!r.data){W.notify((r&&r.message)||'No se pudo abrir el trabajo','error');return}
    var j=r.data.job,acts=r.data.activity||[],video=r.data.video||{};
    W._clJob=j;
    var yt=video.youtube||{};
    var stageDone=function(k){return (k==='sources'&&(j.source_url||(j.candidates||[]).length))||(k==='source_review'&&['verified','generated','applied'].indexOf(j.status)>-1)||(k==='extract'&&j.source_text)||(k==='generate'&&j.generated_html)||(k==='review'&&j.generated_html)||(k==='apply'&&j.status==='applied')||(k==='video'&&j.video_status!=='none')};
    var stageHtml=Object.keys(j.stages||{}).map(function(k){var done=stageDone(k);return '<span style="display:inline-flex;align-items:center;gap:5px;border:1px solid var(--w-border);border-radius:999px;padding:3px 8px;font-size:10.5px;font-weight:700;background:'+(done?'#ecfdf3':'#fff')+';color:'+(done?'#067647':'#667085')+'">'+(done?'●':'○')+' '+W.esc(j.stages[k])+'</span>'}).join(' ');
    var body='<div style="max-height:70vh;overflow:auto;padding-right:4px">';
    body+='<div class="w-card" style="margin-bottom:12px;padding:12px"><div style="font-size:13px;font-weight:800">'+W.esc(j.title||j.handle)+'</div><div style="font-size:11px;color:var(--w-muted);margin-top:3px">'+W.esc(j.vendor||'')+' · '+W.esc(j.product_type||'')+' · Handle: '+W.esc(j.handle)+'</div><div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:8px">'+stageHtml+'</div></div>';
    if(j.source_url){body+='<div class="w-card" style="margin-bottom:12px;padding:12px;border-left:3px solid #067647"><div style="font-size:11px;color:var(--w-muted);text-transform:uppercase;font-weight:800;letter-spacing:.06em">Fuente confirmada</div><div style="font-size:12px;margin-top:4px"><a href="'+W.esc(j.source_url)+'" target="_blank" rel="noopener">'+W.esc(j.source_title||j.source_domain)+'</a></div><div style="font-size:10.5px;color:var(--w-muted);margin-top:3px">Confianza '+W.num(j.confidence||0)+'% · '+W.esc(j.source_domain||'')+'</div></div>'}
    var cands=j.candidates||[];
    if(cands.length){
        body+='<div class="w-card" style="margin-bottom:12px;padding:12px"><h3 style="margin:0 0 6px;font-size:13px">Fuentes candidatas</h3><div style="font-size:11px;color:var(--w-muted);margin-bottom:8px">Solo se puede confirmar una fuente que haya pasado las reglas de identidad. Las rechazadas no se pueden aplicar.</div>';
        cands.forEach(function(c,i){
            var conf=+c.confidence||0,color=c.status==='verified'?'#067647':(c.status==='needs_review'?'#B45309':'#B42318');
            var ev=c.evidence||{},missing=(ev.missing||[]).slice(0,4),conflicts=(ev.conflicts||[]).slice(0,3);
            body+='<div style="border:1px solid var(--w-border);border-radius:10px;padding:10px;margin-bottom:8px;background:#fff"><div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap"><div><b style="font-size:12px">'+W.esc(c.title||c.domain||c.url)+'</b><div style="font-size:10.5px;color:var(--w-muted);word-break:break-all"><a href="'+W.esc(c.url)+'" target="_blank" rel="noopener">'+W.esc(c.url)+'</a></div></div><div style="text-align:right">'+W.clBadge(c.status==='verified'?'Verificada':(c.status==='needs_review'?'Revisar':'Rechazada'),color)+'<div style="font-size:10.5px;color:var(--w-muted);margin-top:3px">'+conf+'% · '+W.esc(c.page_type||'')+'</div></div></div>';
            if(missing.length)body+='<div style="font-size:10px;color:#B42318;margin-top:5px">Faltan tokens: '+W.esc(missing.join(', '))+'</div>';
            if(conflicts.length)body+='<div style="font-size:10px;color:#B42318;margin-top:5px">Conflictos: '+W.esc(conflicts.join(', '))+'</div>';
            if(c.error)body+='<div style="font-size:10px;color:#B42318;margin-top:5px">'+W.esc(c.error)+'</div>';
            if(c.status!=='rejected'&&['verified','generated','applied'].indexOf(j.status)===-1)body+='<div style="margin-top:7px"><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.storeContentLabConfirm('+j.id+','+i+')">Confirmar esta fuente</button></div>';
            body+='</div>';
        });
        body+='</div>';
    }
    body+='<div class="w-card" style="margin-bottom:12px;padding:12px"><h3 style="margin:0 0 6px;font-size:13px">URL oficial manual</h3><div style="font-size:11px;color:var(--w-muted);margin-bottom:6px">Si la marca bloquea bots o no está en el mapa, pega la URL exacta de la ficha oficial. El sistema volverá a verificar identidad.</div><div style="display:flex;gap:6px;flex-wrap:wrap"><input class="w-input" id="cl-manual-url" placeholder="https://www.fabricante.com/producto/…" style="flex:1;min-width:260px"/><button class="w-btn w-btn-secondary" onclick="wontia.storeContentLabManual('+j.id+')">Analizar URL</button></div></div>';
    if(['verified','generated','applied'].indexOf(j.status)>-1){
        body+='<div class="w-card" style="margin-bottom:12px;padding:12px"><div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap"><div><h3 style="margin:0;font-size:13px">Descripción generada</h3><div style="font-size:11px;color:var(--w-muted);margin-top:3px">Score editorial: '+W.num(j.generated_score||0)+'/100 · '+(j.ai_model?W.esc(j.ai_model):'sin modelo')+'</div></div><div style="display:flex;gap:6px;flex-wrap:wrap"><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.storeContentLabGenerate('+j.id+')">✨ '+(j.generated_html?'Regenerar con TIA':'Generar con TIA')+'</button>'+(j.generated_html?'<button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.storeContentLabApply('+j.id+')">Aplicar en el sitio</button>':'')+'</div></div>';
        if(j.generated_preview)body+='<div style="margin-top:10px;border:1px solid var(--w-border);border-radius:10px;padding:12px;max-height:380px;overflow:auto;background:#fff;font-size:13px;line-height:1.65">'+j.generated_preview+'</div>';
        body+='</div>';
    }
    var md=j.miche_data||{};
    body+='<div class="w-card" style="margin-bottom:12px;padding:12px"><div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap"><div><h3 style="margin:0;font-size:13px">Comparación con Miche</h3><div style="font-size:11px;color:var(--w-muted);margin-top:3px">'+(md.miche_quality?('Nuestra: '+W.num(md.ours&&md.ours.score||0)+'/100 · Miche: '+W.num(md.miche_quality.score||0)+'/100'):'Sin comparar todavía')+'</div></div><div style="display:flex;gap:6px;flex-wrap:wrap"><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.storeContentLabMiche('+j.id+')">Comparar ahora</button>'+(md.recommendation&&md.recommendation.action==='replace'?'<button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.storeContentLabMicheApply('+j.id+')">Usar Miche</button>':'')+'</div></div>';
    if(md.recommendation)body+='<div style="font-size:11px;margin-top:7px;color:'+(md.recommendation.action==='replace'?'#B45309':'#067647')+'">'+W.esc(md.recommendation.label||'')+'</div>';
    body+='</div>';
    var vids=j.source_videos||[];
    body+='<div class="w-card" style="margin-bottom:12px;padding:12px"><h3 style="margin:0 0 6px;font-size:13px">Video oficial</h3><div style="font-size:11px;color:var(--w-muted);margin-bottom:6px">No se usan hotlinks. El video se descarga a un sandbox privado y solo se publica si SONIKA tiene derechos. '+(yt.connected?'YouTube conectado.':'YouTube no conectado.')+'</div>';
    if(vids.length){vids.forEach(function(v,i){body+='<div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;border-top:1px dashed var(--w-border);padding:6px 0"><div style="font-size:11px"><b>'+W.esc(v.provider||'video')+'</b> · '+W.esc(v.title||v.id||'')+'<div style="word-break:break-all;color:var(--w-muted);font-size:10px">'+W.esc(v.url||'')+'</div></div><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.storeContentLabVideoPrepare('+j.id+','+i+')">Descargar a sandbox</button></div>'})}
    else body+='<div style="font-size:11px;color:var(--w-muted)">No se detectaron videos en la ficha oficial.</div>';
    if(j.video_file)body+='<div style="font-size:11px;margin-top:7px;color:#067647;word-break:break-all">Sandbox: '+W.esc(j.video_file)+' · '+W.esc(j.video_status||'')+'</div>';
    if(j.youtube_url)body+='<div style="font-size:11px;margin-top:7px"><a href="'+W.esc(j.youtube_url)+'" target="_blank" rel="noopener">Ver en YouTube</a></div>';
    if(j.video_file&&!j.youtube_url&&yt.connected)body+='<div style="margin-top:7px"><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.storeContentLabVideoUpload('+j.id+')">Subir a YouTube @sonikaCO</button></div>';
    if(!yt.connected)body+='<div style="margin-top:7px"><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.storeContentLabYoutube()">Conectar YouTube</button></div>';
    body+='<label style="display:flex;gap:6px;align-items:flex-start;font-size:10.5px;color:var(--w-muted);margin-top:7px"><input type="checkbox" id="cl-rights" style="width:auto;margin-top:2px"/> Confirmo que SONIKA tiene autorización para reutilizar y subir este video.</label></div>';
    if(acts.length){body+='<div class="w-card" style="padding:12px"><h3 style="margin:0 0 6px;font-size:13px">Actividad</h3><div style="max-height:180px;overflow:auto">';acts.forEach(function(a){body+='<div style="font-size:10.5px;border-bottom:1px dashed var(--w-border);padding:4px 0"><b>'+W.esc(a.action)+'</b> · '+W.esc(a.status)+' · <span style="color:var(--w-muted)">'+W.esc(String(a.created_at||'').replace('T',' ').slice(0,16))+' · '+W.esc(a.actor||'')+'</span><div style="color:var(--w-muted)">'+W.esc(a.detail||'')+'</div></div>'});body+='</div></div>'}
    body+='</div>';
    var actions='<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cerrar</button>';
    if(j.status==='applied')actions+='<a class="w-btn w-btn-primary" href="'+W.esc(j.live_url||'#')+'" target="_blank" rel="noopener">Ver en vivo</a><button class="w-btn w-btn-danger" onclick="wontia.storeContentLabRollback('+j.id+')">Revertir</button>';
    W.modal('Content Fix Lab · '+W.esc(String(j.title||j.handle||'')),body,actions);
};
W.storeContentLabConfirm=async function(jobId,index){
    if(!window.confirm('¿Confirmas que esta fuente oficial es EXACTAMENTE el mismo producto? Se guardará esta decisión en la evidencia.'))return;
    var r=await W.api('/api/v1/admin/store/content-lab/source',{method:'POST',body:{job_id:jobId,index:index,confirm:true}});
    W.notify((r&&r.message)||'Fuente confirmada',r&&r.ok?'success':'error');if(r&&r.ok)W.storeContentLabOpen(jobId);
};
W.storeContentLabManual=async function(jobId){
    var url=(document.getElementById('cl-manual-url')||{}).value||'';
    if(!url.trim()){W.notify('Pega una URL oficial','info');return}
    W.notify('Analizando URL oficial…','info');
    var r=await W.api('/api/v1/admin/store/content-lab/source',{method:'POST',body:{job_id:jobId,manual_url:url,confirm:true}});
    W.notify((r&&r.message)||'URL analizada',r&&r.ok?'success':'error');if(r&&r.job)W.storeContentLabOpen(r.job.id);
};
W.storeContentLabGenerate=async function(jobId){
    W.notify('TIA está redactando la descripción con la fuente verificada…','info');
    var r=await W.api('/api/v1/admin/store/content-lab/generate',{method:'POST',body:{job_id:jobId}});
    W.notify((r&&r.message)||'Generación completada',r&&r.ok?'success':'error');if(r&&r.job)W.storeContentLabOpen(jobId);
};
W.storeContentLabApply=async function(jobId){
    if(!window.confirm('¿Aplicar esta descripción al producto y publicarla en el sitio? Se guardará una revisión para poder revertir.'))return;
    var r=await W.api('/api/v1/admin/store/content-lab/apply',{method:'POST',body:{job_id:jobId}});
    W.notify((r&&r.message)||'Aplicada',r&&r.ok?'success':'error');if(r&&r.ok&&r.live_url){window.open(r.live_url,'_blank');W.storeContentLabOpen(jobId)}
};
W.storeContentLabRollback=async function(jobId){
    if(!window.confirm('¿Revertir a la descripción anterior?'))return;
    var r=await W.api('/api/v1/admin/store/content-lab/rollback',{method:'POST',body:{job_id:jobId}});
    W.notify((r&&r.message)||'Revertida',r&&r.ok?'success':'error');if(r&&r.ok)W.storeContentLabOpen(jobId);
};
W.storeContentLabMiche=async function(jobId){
    W.notify('Consultando la ficha de Miche…','info');
    var r=await W.api('/api/v1/admin/store/content-lab/miche',{method:'POST',body:{job_id:jobId}});
    W.notify((r&&r.message)||'Comparación lista',r&&r.ok?'success':'error');if(r&&r.data)W.storeContentLabOpen(jobId);
};
W.storeContentLabMicheApply=async function(jobId){
    if(!window.confirm('¿Reemplazar nuestra descripción por la de Miche? Solo se hace si Miche es más completa.'))return;
    var r=await W.api('/api/v1/admin/store/content-lab/miche/apply',{method:'POST',body:{job_id:jobId}});
    W.notify((r&&r.message)||'Miche aplicada',r&&r.ok?'success':'error');if(r&&r.ok)W.storeContentLabOpen(jobId);
};
W.storeContentLabMicheScan=async function(){
    W.notify('Escaneando actualizaciones de contenido en Miche…','info');
    var r=await W.api('/api/v1/admin/store/content-lab/miche/scan',{method:'POST',body:{limit:250}});
    if(!r||!r.ok){W.notify((r&&r.message)||'No se pudo escanear Miche','error');return}
    var items=r.items||[];
    var body='<div style="max-height:60vh;overflow:auto">'+(items.length?'<table class="w-table" style="width:100%"><thead><tr><th>Producto</th><th>Miche</th><th>Nuestra</th><th>Recomendación</th><th></th></tr></thead><tbody>'+items.map(function(x){return '<tr><td>'+W.esc(x.name)+'</td><td>'+W.num(x.miche_score)+'/100</td><td>'+W.num(x.ours_score)+'/100</td><td>'+(x.recommendation==='replace'?'Reemplazar por Miche':'Mantener la nuestra')+'</td><td><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.closeModal();wontia.storeContentLabOpen('+x.job_id+')">Abrir</button></td></tr>'}).join('')+'</tbody></table>':'<div style="font-size:12px;color:var(--w-muted)">No se detectaron actualizaciones de contenido en esta página de Miche.</div>')+'</div>';
    W.modal('Actualizaciones de Miche',body,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cerrar</button>');
    W.storeContentLab(W._clState.page);
};
W.storeContentLabVideoPrepare=async function(jobId,index){
    if(!window.confirm('Vas a descargar el video oficial a un sandbox privado. Confirma que SONIKA tiene derechos para reutilizarlo. ¿Continuar?'))return;
    W.notify('Descargando video al sandbox… puede tardar unos minutos','info');
    var r=await W.api('/api/v1/admin/store/content-lab/video/prepare',{method:'POST',body:{job_id:jobId,index:index,rights:true}});
    W.notify((r&&r.message)||'Video preparado',r&&r.ok?'success':'error');if(r&&r.ok)W.storeContentLabOpen(jobId);
};
W.storeContentLabVideoUpload=async function(jobId){
    var rights=!!(document.getElementById('cl-rights')&&document.getElementById('cl-rights').checked);
    if(!rights){W.notify('Marca la confirmación de derechos antes de subir','info');return}
    if(!window.confirm('¿Subir este video a la cuenta de YouTube de SONIKA como público?'))return;
    W.notify('Subiendo video a YouTube…','info');
    var r=await W.api('/api/v1/admin/store/content-lab/video/upload',{method:'POST',body:{job_id:jobId,rights:true}});
    W.notify((r&&r.message)||'Video subido',r&&r.ok?'success':'error');if(r&&r.ok)W.storeContentLabOpen(jobId);
};
W.storeContentLabYoutube=async function(){
    var r=await W.api('/api/v1/admin/store/content-lab/youtube');
    var d=(r&&r.data)||{};
    var body='<div style="font-size:12px;line-height:1.7">'
        +'<div><b>Estado:</b> '+(d.connected?'Conectado':'No conectado')+'</div>'
        +(d.channel_title?'<div><b>Canal:</b> '+W.esc(d.channel_title)+'</div>':'')
        +'<div style="font-size:11px;color:var(--w-muted);margin-top:6px">Callback OAuth: <code>'+W.esc(d.redirect_uri||'')+'</code></div>'
        +'<div style="margin-top:10px;padding:10px;border:1px dashed var(--w-border);border-radius:8px"><b>Credenciales de Google Cloud</b><div style="font-size:11px;color:var(--w-muted);margin:3px 0 7px">Crea un OAuth Client (Web) con el callback de arriba y pega Client ID/Secret. Luego autoriza el canal @sonikaCO.</div><input class="w-input" id="yt-client-id" placeholder="Client ID" value="'+W.esc(d.client_id||'')+'" style="width:100%;margin-bottom:6px"/><input class="w-input" id="yt-client-secret" placeholder="Client Secret (se guarda cifrado)" type="password" style="width:100%;margin-bottom:6px"/><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.storeContentLabYouTubeSave()">Guardar credenciales</button></div>'
        +'<div style="margin-top:10px;padding:10px;border:1px dashed var(--w-border);border-radius:8px"><b>Cookies para descargar de YouTube</b><div style="font-size:11px;color:var(--w-muted);margin:3px 0 7px">Google bloquea las descargas anónimas. Exporta un <code>cookies.txt</code> (formato Netscape) desde una sesión de YouTube y súbelo. Se guarda fuera del webroot. Úsalo solo con videos sobre los que SONIKA tenga derechos.</div><input type="file" id="yt-cookies" accept=".txt" style="font-size:11px;margin-bottom:6px"/><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.storeContentLabYouTubeCookies()">Subir cookies</button><span style="font-size:11px;color:'+(d.cookies_configured?'#067647':'#B45309')+';margin-left:8px">'+(d.cookies_configured?'● Cookies configuradas':'○ Sin cookies')+'</span></div>'
        +'<div style="margin-top:10px;display:flex;gap:6px;flex-wrap:wrap"><button class="w-btn w-btn-primary" onclick="wontia.storeContentLabYouTubeConnect()">Conectar YouTube</button>'+(d.connected?'<button class="w-btn w-btn-danger" onclick="wontia.storeContentLabYouTubeDisconnect()">Desconectar</button>':'')+'</div>'
        +'</div>';
    W.modal('YouTube · cuenta SONIKA',body,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cerrar</button>');
};
W.storeContentLabYouTubeSave=async function(){
    var id=(document.getElementById('yt-client-id')||{}).value||'';
    var secret=(document.getElementById('yt-client-secret')||{}).value||'';
    var r=await W.api('/api/v1/admin/store/content-lab/youtube/credentials',{method:'POST',body:{client_id:id,client_secret:secret}});
    W.notify((r&&r.message)||'Credenciales guardadas',r&&r.ok?'success':'error');
};
W.storeContentLabYouTubeCookies=async function(){
    var input=document.getElementById('yt-cookies');
    if(!input||!input.files||!input.files[0]){W.notify('Selecciona el archivo cookies.txt','info');return}
    var fd=new FormData();fd.append('cookies',input.files[0]);
    try{
        var res=await fetch('/api/v1/admin/store/content-lab/youtube/cookies',{method:'POST',body:fd,credentials:'same-origin',headers:W.token?{Authorization:'Bearer '+W.token}:{}});
        var d=await res.json();
        W.notify((d&&d.message)||(res.ok?'Cookies guardadas':'No se pudieron guardar'),res.ok?'success':'error');
        if(res.ok)W.storeContentLabYoutube();
    }catch(e){W.notify('No se pudieron subir las cookies: '+e.message,'error')}
};
W.storeContentLabYouTubeConnect=async function(){
    var r=await W.api('/api/v1/admin/store/content-lab/youtube/start',{method:'POST'});
    if(r&&r.ok&&r.url){window.location.href=r.url}else W.notify((r&&r.message)||'No se pudo iniciar OAuth','error');
};
W.storeContentLabYouTubeDisconnect=async function(){
    if(!window.confirm('¿Desconectar la cuenta de YouTube?'))return;
    var r=await W.api('/api/v1/admin/store/content-lab/youtube/disconnect',{method:'POST'});
    W.notify((r&&r.message)||'Desconectado',r&&r.ok?'success':'error');W.closeModal();
};
W.storeContentLabExport=async function(){
    var f=W._clExportFilters||{filter:'empty'};
    var qs=new URLSearchParams({filter:f.filter||'empty',q:f.q||'',status:f.status||'',vendor:f.vendor||''}).toString();
    try{
        var res=await fetch('/api/v1/admin/store/content-lab/export.xls?'+qs,{credentials:'same-origin',headers:W.token?{Authorization:'Bearer '+W.token}:{}});
        if(!res.ok)throw new Error('HTTP '+res.status);
        var blob=await res.blob();
        var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='sonika-contenido-'+new Date().toISOString().slice(0,10)+'.xls';document.body.appendChild(a);a.click();setTimeout(function(){URL.revokeObjectURL(a.href);a.remove()},600);
        W.notify('Lista .xls descargada','success');
    }catch(e){W.notify('No se pudo descargar el .xls: '+e.message,'error')}
};

W.storeProducts=function(){
    var el=document.getElementById('store-content');
    el.innerHTML='<div class="w-flex-between w-mb-lg" style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:16px">'
        +'<div style="display:flex;gap:8px;flex-wrap:wrap"><input class="w-input" style="width:220px" id="store-p-search" placeholder="Buscar producto o SKU..."/>'
        +'<select class="w-input" id="store-p-status" style="width:150px"><option value="">Todos</option><option value="active">Activos</option><option value="draft">Borrador</option><option value="archived">Archivados</option></select></div>'
        +'<button class="w-btn w-btn-primary" onclick="wontia.storeProductEditor()">+ Nuevo producto</button></div>'
        +'<div id="store-p-list"></div>';
    W.storeProductsLoad();
    document.getElementById('store-p-search').addEventListener('input',W.storeProductsLoad);
    document.getElementById('store-p-status').addEventListener('change',W.storeProductsLoad);
};

W.storeProductsLoad=async function(){
    var q=document.getElementById('store-p-search')?document.getElementById('store-p-search').value:'';
    var st=document.getElementById('store-p-status')?document.getElementById('store-p-status').value:'';
    var list=document.getElementById('store-p-list');
    if(!list)return;
    var r=await W.api('/api/v1/admin/store/products?search='+encodeURIComponent(q)+'&status='+encodeURIComponent(st)+'&limit=5000');
    var rows=r.data||[];
    if(!rows.length){list.innerHTML='<div class="w-empty-state"><h3>Sin productos</h3><p>Crea tu primer producto para empezar a vender.</p></div>';return}
    var html='<div class="w-card" style="padding:0;overflow:auto"><table style="width:100%;border-collapse:collapse;font-size:13px"><thead><tr style="text-align:left;color:var(--w-muted);font-size:11px;text-transform:uppercase;letter-spacing:.05em">'
        +'<th style="padding:12px 14px">Producto</th><th style="padding:12px 14px">Categoría</th><th style="padding:12px 14px">Precio</th><th style="padding:12px 14px">Stock</th><th style="padding:12px 14px">Estado</th><th style="padding:12px 14px"></th></tr></thead><tbody>';
    rows.forEach(function(p){
        var stockTxt=p.track_stock==1?String(p.stock):'∞';
        var badge=p.status==='active'?'published':(p.status==='draft'?'draft':'archived');
        html+='<tr style="border-top:1px solid var(--w-border)">'
            +'<td style="padding:12px 14px"><div style="font-weight:600">'+W.esc(p.name)+(p.is_featured==1?' ⭐':'')+'</div><div style="font-size:11px;color:var(--w-muted)">/'+W.esc(p.slug)+(p.sku?' · '+W.esc(p.sku):'')+'</div></td>'
            +'<td style="padding:12px 14px;color:var(--w-muted)">'+W.esc(p.category_name||'—')+'</td>'
            +'<td style="padding:12px 14px;font-weight:600">$'+W.num((p.price_cents/100))+'</td>'
            +'<td style="padding:12px 14px;'+(p.track_stock==1&&p.stock<=3?'color:#f87171;font-weight:700':'')+'">'+stockTxt+'</td>'
            +'<td style="padding:12px 14px"><span class="w-badge w-badge-'+badge+'">'+W.esc(p.status)+'</span></td>'
            +'<td style="padding:12px 14px;text-align:right;white-space:nowrap"><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.storeProductEditor('+p.id+')">Editar</button> '
            +'<button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.storeProductDelete('+p.id+')">Eliminar</button></td></tr>';
    });
    html+='</tbody></table></div>';
    list.innerHTML=html;
};

W.storeProductEditor=async function(id){
    var p={id:0,name:'',slug:'',category_id:'',description:'',short_description:'',price_cents:0,compare_price_cents:0,sku:'',stock:0,track_stock:1,status:'draft',is_featured:0,images:[],variants:[]};
    if(id){var r=await W.api('/api/v1/admin/store/products/'+id);if(r.ok&&r.data)p=r.data}
    var cats=(await W.api('/api/v1/admin/store/categories')).data||[];
    var catOpts='<option value="">Sin categoría</option>';
    cats.forEach(function(c){catOpts+='<option value="'+c.id+'"'+(String(p.category_id)===String(c.id)?' selected':'')+'>'+W.esc(c.name)+'</option>'});
    var body='<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Nombre *</label><input class="w-input" id="sp-name" value="'+W.esc(p.name)+'" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Slug</label><input class="w-input" id="sp-slug" value="'+W.esc(p.slug)+'" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Categoría</label><select class="w-input" id="sp-cat" style="width:100%">'+catOpts+'</select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">SKU</label><input class="w-input" id="sp-sku" value="'+W.esc(p.sku)+'" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Precio (en centavos) *</label><input class="w-input" type="number" id="sp-price" value="'+p.price_cents+'" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Precio comparación (centavos)</label><input class="w-input" type="number" id="sp-compare" value="'+p.compare_price_cents+'" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Stock</label><input class="w-input" type="number" id="sp-stock" value="'+p.stock+'" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Estado</label><select class="w-input" id="sp-status" style="width:100%">'
            +['draft','active','archived'].map(function(s){return '<option value="'+s+'"'+(p.status===s?' selected':'')+'>'+s+'</option>'}).join('')+'</select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Controlar stock</label><select class="w-input" id="sp-track" style="width:100%"><option value="1"'+(p.track_stock==1?' selected':'')+'>Sí</option><option value="0"'+(p.track_stock!=1?' selected':'')+'>No (ilimitado)</option></select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Destacado</label><select class="w-input" id="sp-featured" style="width:100%"><option value="0"'+(p.is_featured!=1?' selected':'')+'>No</option><option value="1"'+(p.is_featured==1?' selected':'')+'>Sí</option></select></div>'
        +'</div>'
        +'<div style="margin-top:12px"><label style="font-size:11px;color:var(--w-muted)">Descripción corta</label><input class="w-input" id="sp-short" value="'+W.esc(p.short_description||'')+'" style="width:100%"/></div>'
        +'<div style="margin-top:12px"><label style="font-size:11px;color:var(--w-muted)">Descripción</label><textarea class="w-input" id="sp-desc" rows="4" style="width:100%">'+W.esc(p.description||'')+'</textarea></div>'
        +'<div style="margin-top:12px"><label style="font-size:11px;color:var(--w-muted)">Imágenes</label><div id="sp-images-list" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px"></div>'
        +'<div style="display:flex;gap:6px"><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.storeMediaPicker()">+ Elegir del Media</button>'
        +'<input class="w-input" id="sp-img-url" placeholder="https://... o /assets/uploads/..." style="flex:1"/>'
        +'<button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.storeAddImageUrl()">+ URL</button></div></div>'
        +'<div style="margin-top:12px"><label style="font-size:11px;color:var(--w-muted)">Variantes (nombre, precio en centavos, stock)</label><div id="sp-variants"></div><button class="w-btn w-btn-secondary w-btn-sm" style="margin-top:6px" onclick="wontia.storeVariantRow()">+ Variante</button></div>';
    W.modal(id?('Editar producto #'+id):'Nuevo producto',body,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancelar</button><button class="w-btn w-btn-primary" onclick="wontia.storeProductSave('+(id||0)+')">Guardar</button>');
    W.state.storeImages=(p.images||[]).slice();
    W.storeRenderImages();
    (p.variants||[]).forEach(function(v){W.storeVariantRow(v)});
};

W.storeRenderImages=function(){
    var el=document.getElementById('sp-images-list');
    if(!el)return;
    var imgs=W.state.storeImages||[];
    if(!imgs.length){el.innerHTML='<div style="font-size:11px;color:var(--w-muted)">Sin imágenes todavía.</div>';return}
    el.innerHTML=imgs.map(function(u,i){
        return '<div style="position:relative;width:74px;height:74px;border-radius:10px;overflow:hidden;border:1px solid var(--w-border)">'
            +'<img src="'+W.esc(u)+'" style="width:100%;height:100%;object-fit:cover" onerror="this.style.opacity=.25"/>'
            +'<button class="w-btn w-btn-danger w-btn-sm" style="position:absolute;top:2px;right:2px;padding:1px 6px;font-size:11px" onclick="wontia.storeRemoveImage('+i+')">×</button></div>';
    }).join('');
};

W.storeRemoveImage=function(i){
    (W.state.storeImages||[]).splice(i,1);
    W.storeRenderImages();
};

W.storeAddImageUrl=function(){
    var el=document.getElementById('sp-img-url');
    var u=el?el.value.trim():'';
    if(!u)return;
    W.state.storeImages=W.state.storeImages||[];
    W.state.storeImages.push(u);
    if(el)el.value='';
    W.storeRenderImages();
};

W.storeMediaPicker=async function(){
    var r=await W.api('/api/v1/admin/media?page=1');
    var rows=r.data||[];
    if(!rows.length){W.notify('No hay archivos en Media. Sube imágenes primero.','error');return}
    var grid='<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:10px;max-height:420px;overflow:auto">';
    rows.forEach(function(m){
        var isImg=(m.mime||'').indexOf('image/')===0;
        grid+='<div style="cursor:pointer;border:1px solid var(--w-border);border-radius:10px;overflow:hidden" onclick="wontia.storePickMedia(\''+W.esc(m.url).replace(/'/g,'')+'\')">'
            +(isImg?'<img src="'+W.esc(m.url)+'" style="width:100%;height:84px;object-fit:cover;display:block"/>':'<div style="height:84px;display:flex;align-items:center;justify-content:center;font-size:11px;color:var(--w-muted)">'+W.esc(m.mime||'file')+'</div>')
            +'<div style="font-size:10px;color:var(--w-muted);padding:4px 6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+W.esc(m.filename||'')+'</div></div>';
    });
    grid+='</div>';
    W.modal('Elegir imagen del Media',grid,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cerrar</button>');
};

W.storePickMedia=function(url){
    W.state.storeImages=W.state.storeImages||[];
    W.state.storeImages.push(url);
    W.closeModal();
    W.storeRenderImages();
};

W.storeVariantRow=function(v){
    v=v||{name:'',price_cents:0,stock:0};
    var c=document.getElementById('sp-variants');
    var d=document.createElement('div');
    d.style.cssText='display:grid;grid-template-columns:1fr 110px 90px 34px;gap:6px;margin-top:6px';
    d.innerHTML='<input class="w-input sp-v-name" value="'+W.esc(v.name)+'" placeholder="Ej: Talla M"/>'
        +'<input class="w-input sp-v-price" type="number" value="'+(v.price_cents||0)+'" placeholder="Precio"/>'
        +'<input class="w-input sp-v-stock" type="number" value="'+(v.stock||0)+'" placeholder="Stock"/>'
        +'<button class="w-btn w-btn-danger w-btn-sm" onclick="this.parentNode.remove()">×</button>';
    c.appendChild(d);
};

W.storeProductSave=async function(id){
    var v=function(s){var el=document.querySelector(s);return el?el.value:''};
    var images=(W.state.storeImages||[]).slice();
    var variants=[];
    document.querySelectorAll('#sp-variants > div').forEach(function(row){
        var nm=row.querySelector('.sp-v-name').value.trim();
        if(!nm)return;
        variants.push({name:nm,price_cents:parseInt(row.querySelector('.sp-v-price').value)||0,stock:parseInt(row.querySelector('.sp-v-stock').value)||0});
    });
    var payload={id:id||undefined,name:v('#sp-name'),slug:v('#sp-slug'),category_id:v('#sp-cat')||null,sku:v('#sp-sku'),
        price_cents:parseInt(v('#sp-price'))||0,compare_price_cents:parseInt(v('#sp-compare'))||0,stock:parseInt(v('#sp-stock'))||0,
        track_stock:parseInt(v('#sp-track')),status:v('#sp-status'),is_featured:parseInt(v('#sp-featured')),
        short_description:v('#sp-short'),description:v('#sp-desc'),images:images,variants:variants};
    var r=await W.api(id?('/api/v1/admin/store/products/'+id):'/api/v1/admin/store/products',{method:id?'PUT':'POST',body:payload});
    if(r.ok){W.notify('Producto guardado','success');W.closeModal();W.storeProductsLoad()}
};

W.storeProductDelete=function(id){
    W.confirm('¿Eliminar este producto? Esta acción no se puede deshacer.',async function(){
        var r=await W.api('/api/v1/admin/store/products/'+id,{method:'DELETE'});
        if(r.ok){W.notify('Producto eliminado','success');W.storeProductsLoad()}
    });
};

W.storeCategories=async function(){
    var el=document.getElementById('store-content');
    var r=await W.api('/api/v1/admin/store/categories');
    var rows=r.data||[];
    var html='<div class="w-flex-between" style="display:flex;justify-content:space-between;margin-bottom:16px"><h3 style="margin:0">Categorías</h3><button class="w-btn w-btn-primary" onclick="wontia.storeCategoryEditor()">+ Nueva categoría</button></div>';
    if(!rows.length){html+='<div class="w-empty-state"><h3>Sin categorías</h3><p>Agrupa tus productos por categorías.</p></div>'}
    else{
        html+='<div class="w-card" style="padding:0"><table style="width:100%;border-collapse:collapse;font-size:13px"><thead><tr style="text-align:left;color:var(--w-muted);font-size:11px;text-transform:uppercase"><th style="padding:12px 14px">Nombre</th><th style="padding:12px 14px">Slug</th><th style="padding:12px 14px">Productos</th><th style="padding:12px 14px">Activa</th><th style="padding:12px 14px"></th></tr></thead><tbody>';
        rows.forEach(function(c){
            html+='<tr style="border-top:1px solid var(--w-border)"><td style="padding:12px 14px;font-weight:600">'+W.esc(c.name)+'</td><td style="padding:12px 14px;color:var(--w-muted)">'+W.esc(c.slug)+'</td>'
                +'<td style="padding:12px 14px">'+(c.product_count||0)+'</td><td style="padding:12px 14px">'+(c.is_active==1?'Sí':'No')+'</td>'
                +'<td style="padding:12px 14px;text-align:right;white-space:nowrap"><button class="w-btn w-btn-secondary w-btn-sm" onclick=\'wontia.storeCategoryEditor('+JSON.stringify(c)+')\'>Editar</button> '
                +'<button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.storeCategoryDelete('+c.id+')">Eliminar</button></td></tr>';
        });
        html+='</tbody></table></div>';
    }
    el.innerHTML=html;
};

W.storeCategoryEditor=function(c){
    c=c||{id:0,name:'',slug:'',description:'',is_active:1};
    var body='<div><label style="font-size:11px;color:var(--w-muted)">Nombre *</label><input class="w-input" id="sc-name" value="'+W.esc(c.name)+'" style="width:100%"/></div>'
        +'<div style="margin-top:12px"><label style="font-size:11px;color:var(--w-muted)">Slug</label><input class="w-input" id="sc-slug" value="'+W.esc(c.slug)+'" style="width:100%"/></div>'
        +'<div style="margin-top:12px"><label style="font-size:11px;color:var(--w-muted)">Descripción</label><input class="w-input" id="sc-desc" value="'+W.esc(c.description||'')+'" style="width:100%"/></div>'
        +'<div style="margin-top:12px"><label style="font-size:11px;color:var(--w-muted)">Activa</label><select class="w-input" id="sc-active" style="width:100%"><option value="1"'+(c.is_active==1?' selected':'')+'>Sí</option><option value="0"'+(c.is_active!=1?' selected':'')+'>No</option></select></div>';
    W.modal(c.id?('Editar categoría #'+c.id):'Nueva categoría',body,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancelar</button><button class="w-btn w-btn-primary" onclick="wontia.storeCategorySave('+(c.id||0)+')">Guardar</button>');
};

W.storeCategorySave=async function(id){
    var v=function(s){return document.querySelector(s).value};
    var payload={id:id||undefined,name:v('#sc-name'),slug:v('#sc-slug'),description:v('#sc-desc'),is_active:parseInt(v('#sc-active'))};
    var r=await W.api(id?('/api/v1/admin/store/categories/'+id):'/api/v1/admin/store/categories',{method:id?'PUT':'POST',body:payload});
    if(r.ok){W.notify('Categoría guardada','success');W.closeModal();W.storeCategories()}
};

W.storeCategoryDelete=function(id){
    W.confirm('¿Eliminar esta categoría? Los productos quedarán sin categoría.',async function(){
        var r=await W.api('/api/v1/admin/store/categories/'+id,{method:'DELETE'});
        if(r.ok){W.notify('Categoría eliminada','success');W.storeCategories()}
    });
};

W.storeOrders=async function(){
    var el=document.getElementById('store-content');
    el.innerHTML='<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px"><input class="w-input" style="width:240px" id="store-o-search" placeholder="Buscar pedido, cliente..."/>'
        +'<select class="w-input" id="store-o-pay" style="width:150px"><option value="">Pago: todos</option><option value="pending">Pendiente</option><option value="paid">Pagado</option><option value="failed">Fallido</option></select>'
        +'<select class="w-input" id="store-o-ful" style="width:170px"><option value="">Estado: todos</option><option value="new">Nuevo</option><option value="confirmed">Confirmado</option><option value="preparing">Preparando</option><option value="shipped">Enviado</option><option value="delivered">Entregado</option><option value="cancelled">Cancelado</option></select></div>'
        +'<div id="store-o-list"></div>';
    W.storeOrdersLoad();
    document.getElementById('store-o-search').addEventListener('input',W.storeOrdersLoad);
    document.getElementById('store-o-pay').addEventListener('change',W.storeOrdersLoad);
    document.getElementById('store-o-ful').addEventListener('change',W.storeOrdersLoad);
};

W.storeOrdersLoad=async function(){
    var list=document.getElementById('store-o-list');
    if(!list)return;
    var q=document.getElementById('store-o-search').value,ps=document.getElementById('store-o-pay').value,fs=document.getElementById('store-o-ful').value;
    var r=await W.api('/api/v1/admin/store/orders?search='+encodeURIComponent(q)+'&payment_status='+ps+'&fulfillment_status='+fs+'&limit=200');
    var rows=r.data||[];
    if(!rows.length){list.innerHTML='<div class="w-empty-state"><h3>Sin pedidos</h3><p>Los pedidos de la tienda aparecerán aquí.</p></div>';return}
    var payColor={paid:'#34d399',pending:'#fbbf24',failed:'#f87171',refunded:'#8593ab'};
    var html='<div class="w-card" style="padding:0;overflow:auto"><table style="width:100%;border-collapse:collapse;font-size:13px"><thead><tr style="text-align:left;color:var(--w-muted);font-size:11px;text-transform:uppercase"><th style="padding:12px 14px">Pedido</th><th style="padding:12px 14px">Cliente</th><th style="padding:12px 14px">Total</th><th style="padding:12px 14px">Pago</th><th style="padding:12px 14px">Estado</th><th style="padding:12px 14px">Fecha</th></tr></thead><tbody>';
    rows.forEach(function(o){
        html+='<tr style="border-top:1px solid var(--w-border);cursor:pointer" onclick="wontia.storeOrderDetail('+o.id+')">'
            +'<td style="padding:12px 14px;font-weight:600">'+W.esc(o.order_number||o.uuid.slice(0,8))+'</td>'
            +'<td style="padding:12px 14px">'+W.esc(o.customer_name||'')+'<div style="font-size:11px;color:var(--w-muted)">'+W.esc(o.customer_email||'')+'</div></td>'
            +'<td style="padding:12px 14px;font-weight:600">$'+W.num((o.total_cents/100))+'</td>'
            +'<td style="padding:12px 14px"><span style="color:'+(payColor[o.payment_status]||'inherit')+';font-weight:600">'+W.esc(o.payment_status)+'</span></td>'
            +'<td style="padding:12px 14px">'+W.esc(o.fulfillment_status)+'</td>'
            +'<td style="padding:12px 14px;color:var(--w-muted)">'+W.esc((o.created_at||'').slice(0,16))+'</td></tr>';
    });
    html+='</tbody></table></div>';
    list.innerHTML=html;
};

W.storeOrderDetail=async function(id){
    var r=await W.api('/api/v1/admin/store/orders/'+id);
    if(!r.ok||!r.data)return;
    var o=r.data;
    var items=(o.items||[]).map(function(it){return '<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--w-border);font-size:13px"><span>'+(it.qty)+' × '+W.esc(it.name)+(it.variant_name?' ('+W.esc(it.variant_name)+')':'')+'</span><span>$'+W.num((it.subtotal_cents/100))+'</span></div>'}).join('');
    var addr=o.shipping_address||{};
    var body='<div style="font-size:13px;line-height:1.9">'
        +'<div><strong>Cliente:</strong> '+W.esc(o.customer_name||'')+' · '+W.esc(o.customer_email||'')+' · '+W.esc(o.customer_phone||'')+'</div>'
        +'<div><strong>Entrega:</strong> '+W.esc([addr.line1,addr.city,addr.region].filter(function(x){return x}).join(', '))+'</div>'
        +(addr.department_code?'<div style="font-size:11px;color:var(--w-muted)">DANE '+W.esc(addr.department_code)+' / '+W.esc(addr.municipality_code||'')+(o.shipping_carrier?' · '+W.esc(o.shipping_carrier):'')+'</div>':'')
        +(o.shipping_eta_min_days&&o.shipping_eta_max_days?'<div style="font-size:11px;color:var(--w-muted)">Entrega estimada: '+W.esc(o.shipping_eta_min_days)+'-'+W.esc(o.shipping_eta_max_days)+' días</div>':'')
        +(o.notes?'<div><strong>Notas:</strong> '+W.esc(o.notes)+'</div>':'')
        +'<div style="margin-top:12px">'+items+'</div>'
        +'<div style="display:flex;justify-content:space-between;padding:6px 0"><span>Subtotal</span><span>$'+W.num((o.subtotal_cents/100))+'</span></div>'
        +'<div style="display:flex;justify-content:space-between;padding:6px 0"><span>Envío</span><span>'+(o.shipping_cents>0?'$'+W.num((o.shipping_cents/100)):'Gratis')+'</span></div>'
        +'<div style="display:flex;justify-content:space-between;padding:6px 0;font-weight:800;font-size:15px"><span>Total</span><span>$'+W.num((o.total_cents/100))+'</span></div>'
        +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px">'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Estado del pago</label><select class="w-input" id="so-pay" style="width:100%">'+['pending','paid','failed','refunded'].map(function(s){return '<option value="'+s+'"'+(o.payment_status===s?' selected':'')+'>'+s+'</option>'}).join('')+'</select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Estado del pedido</label><select class="w-input" id="so-ful" style="width:100%">'+['new','confirmed','preparing','shipped','delivered','cancelled'].map(function(s){return '<option value="'+s+'"'+(o.fulfillment_status===s?' selected':'')+'>'+s+'</option>'}).join('')+'</select></div>'
        +'</div>'
        +'<div style="margin-top:12px"><label style="font-size:11px;color:var(--w-muted)">Notas internas</label><textarea class="w-input" id="so-notes" rows="2" style="width:100%">'+W.esc(o.admin_notes||'')+'</textarea></div></div>';
    W.modal('Pedido '+(o.order_number||o.uuid.slice(0,8)),body,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cerrar</button><button class="w-btn w-btn-primary" onclick="wontia.storeOrderSave('+id+')">Guardar cambios</button>');
};

W.storeOrderSave=async function(id){
    var payload={payment_status:document.getElementById('so-pay').value,fulfillment_status:document.getElementById('so-ful').value,admin_notes:document.getElementById('so-notes').value};
    var r=await W.api('/api/v1/admin/store/orders/'+id,{method:'PUT',body:payload});
    if(r.ok){W.notify('Pedido actualizado','success');W.closeModal();W.storeOrdersLoad()}
};

W.storeShipping=async function(tab){
    W._shippingTab=tab||W._shippingTab||'cobertura';
    var el=document.getElementById('store-content');
    el.innerHTML='<div class="w-empty-state"><h3>Cargando envíos</h3><p>Consultando cobertura y tarifas.</p></div>';
    var r=await W.api('/api/v1/admin/store/shipping/overview');
    if(!r.ok||!r.data){el.innerHTML='<div class="w-empty-state"><h3>No se pudo cargar Envíos</h3><p>'+W.esc(r.message||'Intenta de nuevo.')+'</p></div>';return}
    W._shipping=r.data;
    if(!W._shippingDept&&W._shipping.departments&&W._shipping.departments.length)W._shippingDept=W._shipping.departments[0].code;
    W.shippingRender();
};

W.shippingTab=function(tab){W._shippingTab=tab||'cobertura';W.shippingRender()};

W.shippingRender=function(){
    var el=document.getElementById('store-content');if(!el)return;
    var d=W._shipping||{},s=d.stats||{},tab=W._shippingTab||'cobertura';
    var h='<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:16px">'
        +'<div><h3 style="margin:0">Envíos</h3><p style="margin:5px 0 0;color:var(--w-muted);font-size:12px">Cobertura nacional, tarifas y tiempos de entrega.</p></div>'
        +'<div style="display:flex;gap:8px;flex-wrap:wrap"><button class="w-btn w-btn-secondary" onclick="wontia.storeShipping()">Actualizar</button><button class="w-btn w-btn-primary" onclick="wontia.shippingRateEditor()">+ Nueva tarifa</button></div></div>';
    h+='<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:16px">'
        +W.shippingStat('Cobertura',(s.covered_departments||0)+'/'+(s.departments||0),'departamentos')
        +W.shippingStat('Municipios',W.num(s.municipalities||0),'en la base DANE')
        +W.shippingStat('Tarifas activas',W.num(s.active_rates||0),(s.municipality_rates||0)+' excepciones municipales')
        +W.shippingStat('Cobertura nacional',(s.coverage_pct||0)+'%',(s.covered_municipalities||0)+' municipios')
        +'</div>';
    h+='<div style="display:flex;gap:8px;border-bottom:1px solid var(--w-border);margin-bottom:14px">'
        +W.shippingTabBtn('cobertura','Cobertura',tab)
        +W.shippingTabBtn('tarifas','Tarifas',tab)
        +'</div>';
    h+=tab==='tarifas'?W.shippingRatesHtml():W.shippingCoverageHtml();
    el.innerHTML=h;
};

W.shippingStat=function(label,value,note){
    return '<div class="w-card" style="padding:13px 14px"><div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--w-muted);font-weight:700">'+W.esc(label)+'</div><div style="font-size:22px;font-weight:800;margin-top:5px">'+W.esc(value)+'</div><div style="font-size:11px;color:var(--w-muted);margin-top:2px">'+W.esc(note||'')+'</div></div>';
};

W.shippingTabBtn=function(id,label,current){
    return '<button class="w-btn '+(current===id?'w-btn-primary':'w-btn-secondary')+'" style="margin-bottom:-1px;border-radius:8px 8px 0 0" onclick="wontia.shippingTab(\''+id+'\')">'+W.esc(label)+'</button>';
};

W.shippingCoverageHtml=function(){
    var d=W._shipping||{},depts=d.departments||[],current=W._shippingDept||'',detail=W._shippingDetail;
    var h='<div style="display:grid;grid-template-columns:minmax(260px,.8fr) minmax(0,1.7fr);gap:14px;align-items:start">';
    h+='<div class="w-card" style="padding:0;overflow:hidden"><div style="padding:11px 12px;border-bottom:1px solid var(--w-border)"><input class="w-input" id="ship-search" placeholder="Buscar departamento..." oninput="wontia.shippingFilter(this.value)" style="width:100%"/></div><div id="ship-depts" style="max-height:620px;overflow:auto">';
    depts.forEach(function(dept){
        var active=String(dept.code)===String(current);
        h+='<button type="button" data-ship-dept="'+W.esc((dept.name||'').toLowerCase())+'" onclick="wontia.shippingDept(\''+dept.code+'\')" style="width:100%;text-align:left;border:0;border-bottom:1px solid var(--w-border);background:'+(active?'var(--w-soft,#f5f5f7)':'transparent')+';padding:10px 12px;cursor:pointer;font:inherit"><div style="display:flex;justify-content:space-between;gap:8px;align-items:center"><b style="font-size:12px">'+W.esc(dept.name)+'</b><span style="font-size:10px;color:var(--w-muted)">'+W.esc(String(dept.municipalities||0))+'</span></div><div style="margin-top:3px;font-size:10px;color:'+(dept.covered?'#15803d':'#b91c1c')+'">'+(dept.covered?'● '+(dept.rate_name||'Cubierto'):'● Sin cobertura')+(dept.exceptions?' · '+dept.exceptions+' excepción(es)':'')+'</div></button>';
    });
    h+='</div></div><div class="w-card" style="padding:0;overflow:hidden">';
    if(!detail){h+='<div class="w-empty-state" style="padding:46px 18px"><h3>Selecciona un departamento</h3><p>Consulta sus municipios y tarifas.</p></div>'}
    else{
        var dep=detail.department||{},municipalities=detail.municipalities||[];
        h+='<div style="padding:13px 14px;border-bottom:1px solid var(--w-border);display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap"><div><b style="font-size:14px">'+W.esc(dep.name||'')+'</b><div style="font-size:11px;color:var(--w-muted);margin-top:2px">'+municipalities.length+' municipios · tarifa '+(detail.department_rate?'departamental':(detail.national?'nacional':'sin cobertura'))+'</div></div><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.shippingRateEditor(0,{department_code:\''+W.esc(dep.code||'')+'\'})">+ Tarifa departamento</button></div>';
        h+='<div style="padding:8px 12px;border-bottom:1px solid var(--w-border)"><input class="w-input" id="ship-mun-search" placeholder="Buscar municipio o código DANE..." oninput="wontia.shippingMunFilter(this.value)" style="width:100%"/></div>';
        h+='<div style="max-height:560px;overflow:auto"><table style="width:100%;border-collapse:collapse;font-size:12px"><thead><tr style="text-align:left;color:var(--w-muted);font-size:10px;text-transform:uppercase"><th style="padding:9px 12px">Municipio</th><th style="padding:9px 12px">DANE</th><th style="padding:9px 12px">Tarifa</th><th style="padding:9px 12px">Entrega</th><th style="padding:9px 12px">Origen</th><th style="padding:9px 12px"></th></tr></thead><tbody>';
        municipalities.forEach(function(m){
            var origin={municipio:'Municipio',departamento:'Departamento',nacional:'Nacional'}[m.rate_source]||'Sin cobertura';
            h+='<tr data-ship-mun="'+W.esc(((m.name||'')+' '+(m.code||'')).toLowerCase())+'" style="border-top:1px solid var(--w-border)"><td style="padding:8px 12px"><b>'+W.esc(m.name)+'</b>'+(m.is_capital==1?' <span style="font-size:9px;color:var(--w-muted)">capital</span>':'')+'</td><td style="padding:8px 12px;color:var(--w-muted)">'+W.esc(m.code)+'</td><td style="padding:8px 12px">'+(m.cost_cents!=null?'$'+W.num(m.cost_cents/100):'—')+'</td><td style="padding:8px 12px;color:var(--w-muted)">'+(m.eta_min_days&&m.eta_max_days?m.eta_min_days+'-'+m.eta_max_days+' días':'—')+'</td><td style="padding:8px 12px"><span style="font-size:10px;padding:3px 7px;border-radius:999px;background:'+(m.covered?'#dcfce7':'#fee2e2')+';color:'+(m.covered?'#166534':'#991b1b')+'">'+W.esc(origin)+'</span></td><td style="padding:8px 12px;text-align:right"><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.shippingRateEditor('+(m.has_exception?m.rate_id:0)+',{department_code:\''+W.esc(dep.code||'')+'\',municipality_code:\''+W.esc(m.code)+'\'})">'+(m.has_exception?'Editar':'Excepción')+'</button></td></tr>';
        });
        h+='</tbody></table></div>';
    }
    h+='</div></div>';
    return h;
};

W.shippingRatesHtml=function(){
    var rates=(W._shipping&&W._shipping.rates)||[];
    if(!rates.length)return '<div class="w-empty-state"><h3>Sin tarifas</h3><p>Crea una tarifa nacional o por departamento.</p></div>';
    var h='<div class="w-card" style="padding:0;overflow:hidden"><table style="width:100%;border-collapse:collapse;font-size:12px"><thead><tr style="text-align:left;color:var(--w-muted);font-size:10px;text-transform:uppercase"><th style="padding:11px 13px">Tarifa</th><th style="padding:11px 13px">Cobertura</th><th style="padding:11px 13px">Transportadora</th><th style="padding:11px 13px">Costo</th><th style="padding:11px 13px">Gratis desde</th><th style="padding:11px 13px">Entrega</th><th style="padding:11px 13px">Estado</th><th style="padding:11px 13px"></th></tr></thead><tbody>';
    rates.forEach(function(r){
        var scope={nacional:'Nacional',departamento:'Departamento',municipio:'Municipio'}[r.scope]||r.scope;
        var where=r.scope==='municipio'?((r.municipality_name||'')+', '+(r.department_name||'')):(r.scope==='departamento'?(r.department_name||''):'Todo Colombia');
        h+='<tr style="border-top:1px solid var(--w-border)"><td style="padding:10px 13px;font-weight:700">'+W.esc(r.name)+'</td><td style="padding:10px 13px"><span style="font-size:10px;padding:2px 6px;border-radius:999px;background:#eef2ff;color:#3730a3">'+W.esc(scope)+'</span><div style="font-size:10px;color:var(--w-muted);margin-top:3px">'+W.esc(where)+'</div></td><td style="padding:10px 13px;color:var(--w-muted)">'+W.esc(r.carrier||'—')+'</td><td style="padding:10px 13px">$'+W.num((r.cost_cents||0)/100)+'</td><td style="padding:10px 13px">'+(r.free_over_cents!=null?'$'+W.num(r.free_over_cents/100):'Umbral global')+'</td><td style="padding:10px 13px;color:var(--w-muted)">'+(r.eta_min_days&&r.eta_max_days?r.eta_min_days+'-'+r.eta_max_days+' días':'—')+'</td><td style="padding:10px 13px">'+(r.is_active==1?'<span style="color:#15803d">Activa</span>':'<span style="color:#b91c1c">Inactiva</span>')+'</td><td style="padding:10px 13px;text-align:right;white-space:nowrap"><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.shippingRateEditor('+r.id+')">Editar</button> <button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.shippingRateDelete('+r.id+')">Eliminar</button></td></tr>';
    });
    return h+'</tbody></table></div>';
};

W.shippingFilter=function(value){
    value=String(value||'').toLowerCase();
    document.querySelectorAll('[data-ship-dept]').forEach(function(el){el.style.display=el.getAttribute('data-ship-dept').indexOf(value)>-1?'':'none'});
};

W.shippingMunFilter=function(value){
    value=String(value||'').toLowerCase();
    document.querySelectorAll('[data-ship-mun]').forEach(function(el){el.style.display=el.getAttribute('data-ship-mun').indexOf(value)>-1?'':'none'});
};

W.shippingDept=async function(code){
    W._shippingDept=code;W._shippingDetail=null;W.shippingRender();
    var r=await W.api('/api/v1/admin/store/shipping/municipalities?department='+encodeURIComponent(code));
    if(r.ok&&r.data){W._shippingDetail=r.data;W.shippingRender()}
};

W.shippingRateEditor=function(id,prefill){
    prefill=prefill||{};
    var rate=null,rates=(W._shipping&&W._shipping.rates)||[];
    if(id)rates.forEach(function(r){if(String(r.id)===String(id))rate=r});
    var isEdit=!!rate;
    var scope=rate?({nacional:'national',departamento:'department',municipio:'municipality'}[rate.scope]||'national'):'national';
    if(prefill.municipality_code)scope='municipality';else if(prefill.department_code)scope='department';
    var dept=rate&&rate.department_code?String(rate.department_code):(prefill.department_code||'');
    var mun=rate&&rate.municipality_code?String(rate.municipality_code):(prefill.municipality_code||'');
    var depts=(W._shipping&&W._shipping.departments)||[];
    var options='<option value="">Selecciona</option>'+depts.map(function(d){return '<option value="'+W.esc(d.code)+'"'+(String(d.code)===String(dept)?' selected':'')+'>'+W.esc(d.name)+'</option>'}).join('');
    var body='<div><label style="font-size:11px;color:var(--w-muted)">Nombre de la tarifa *</label><input class="w-input" id="sr-name" value="'+W.esc(rate?rate.name:(prefill.name||''))+'" placeholder="Ej: Nacional / Eje Cafetero / Medellín" style="width:100%"/></div>'
        +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px"><div><label style="font-size:11px;color:var(--w-muted)">Cobertura</label><select class="w-input" id="sr-scope" onchange="wontia.shippingScope()" style="width:100%"><option value="national"'+(scope==='national'?' selected':'')+'>Nacional</option><option value="department"'+(scope==='department'?' selected':'')+'>Departamento</option><option value="municipality"'+(scope==='municipality'?' selected':'')+'>Municipio</option></select></div><div><label style="font-size:11px;color:var(--w-muted)">Transportadora</label><input class="w-input" id="sr-carrier" value="'+W.esc(rate?rate.carrier:'')+'" placeholder="Ej: Servientrega" style="width:100%"/></div></div>'
        +'<div id="sr-dept-wrap" style="margin-top:12px;display:none"><label style="font-size:11px;color:var(--w-muted)">Departamento</label><select class="w-input" id="sr-dept" onchange="wontia.shippingScope()" style="width:100%">'+options+'</select></div>'
        +'<div id="sr-mun-wrap" style="margin-top:12px;display:none"><label style="font-size:11px;color:var(--w-muted)">Municipio</label><select class="w-input" id="sr-mun" style="width:100%"><option value="'+W.esc(mun)+'">Cargando...</option></select></div>'
        +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px"><div><label style="font-size:11px;color:var(--w-muted)">Costo COP *</label><input class="w-input" type="number" min="0" step="100" id="sr-cost" value="'+W.esc(String(rate?Math.round((rate.cost_cents||0)/100):''))+'" style="width:100%"/></div><div><label style="font-size:11px;color:var(--w-muted)">Gratis desde COP</label><input class="w-input" type="number" min="0" step="1000" id="sr-free" value="'+W.esc(rate&&rate.free_over_cents!=null?String(Math.round(rate.free_over_cents/100)):'')+'" placeholder="Vacío = umbral global" style="width:100%"/><small style="color:var(--w-muted);font-size:10px">Vacío usa el umbral global. 0 = nunca gratis.</small></div></div>'
        +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px"><div><label style="font-size:11px;color:var(--w-muted)">Entrega mínima (días)</label><input class="w-input" type="number" min="1" max="60" id="sr-eta-min" value="'+W.esc(String(rate&&rate.eta_min_days?rate.eta_min_days:2))+'" style="width:100%"/></div><div><label style="font-size:11px;color:var(--w-muted)">Entrega máxima (días)</label><input class="w-input" type="number" min="1" max="60" id="sr-eta-max" value="'+W.esc(String(rate&&rate.eta_max_days?rate.eta_max_days:5))+'" style="width:100%"/></div></div>'
        +'<div style="display:flex;gap:18px;flex-wrap:wrap;margin-top:12px"><label style="font-size:12px;display:flex;gap:7px;align-items:center"><input type="checkbox" id="sr-cod" '+(rate&&rate.cod_enabled==0?'':'checked')+'/> Contraentrega disponible</label><label style="font-size:12px;display:flex;gap:7px;align-items:center"><input type="checkbox" id="sr-active" '+((!rate||rate.is_active==1)?'checked':'')+'/> Tarifa activa</label></div>';
    W.modal(isEdit?'Editar tarifa':'Nueva tarifa de envío',body,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancelar</button><button class="w-btn w-btn-primary" onclick="wontia.shippingRateSave('+(rate?rate.id:0)+')">Guardar tarifa</button>');
    W._shippingEditor={dept:dept,mun:mun,scope:scope};
    setTimeout(function(){W.shippingScope()},0);
};

W.shippingScope=async function(){
    var scopeEl=document.getElementById('sr-scope');if(!scopeEl)return;
    var scope=scopeEl.value,deptWrap=document.getElementById('sr-dept-wrap'),munWrap=document.getElementById('sr-mun-wrap'),deptEl=document.getElementById('sr-dept');
    deptWrap.style.display=scope==='national'?'none':'block';
    munWrap.style.display=scope==='municipality'?'block':'none';
    if(scope==='national')return;
    var dept=deptEl.value||(W._shippingEditor&&W._shippingEditor.dept)||'';
    if(!dept)return;
    var munEl=document.getElementById('sr-mun');if(!munEl)return;
    var selected=(W._shippingEditor&&W._shippingEditor.mun)||munEl.value||'';
    munEl.innerHTML='<option value="">Cargando municipios...</option>';
    if(scope!=='municipality')return;
    var r=await W.api('/api/v1/public/shipping/municipalities?department='+encodeURIComponent(dept));
    if(!r.ok||!Array.isArray(r.data)){munEl.innerHTML='<option value="">No se pudieron cargar</option>';return}
    munEl.innerHTML='<option value="">Selecciona el municipio</option>'+r.data.map(function(m){return '<option value="'+W.esc(m.code)+'"'+(String(m.code)===String(selected)?' selected':'')+'>'+W.esc(m.name)+'</option>'}).join('');
    W._shippingEditor.mun='';
};

W.shippingRateSave=async function(id){
    var val=function(x){var el=document.getElementById(x);return el?el.value:''};
    var payload={scope:val('sr-scope'),name:val('sr-name').trim(),department_code:val('sr-dept'),municipality_code:val('sr-mun'),carrier:val('sr-carrier').trim(),cost_cents:Math.round((parseFloat(val('sr-cost'))||0)*100),eta_min_days:parseInt(val('sr-eta-min'))||2,eta_max_days:parseInt(val('sr-eta-max'))||5,cod_enabled:document.getElementById('sr-cod')&&document.getElementById('sr-cod').checked?1:0,is_active:document.getElementById('sr-active')&&document.getElementById('sr-active').checked?1:0};
    var free=String(val('sr-free')).trim();payload.free_over_cents=free===''?null:Math.round((parseFloat(free)||0)*100);
    if(id)payload.id=id;
    if(!payload.name){W.notify('Escribe un nombre para la tarifa','error');return}
    if(payload.scope!=='national'&&!payload.department_code){W.notify('Selecciona un departamento','error');return}
    if(payload.scope==='municipality'&&!payload.municipality_code){W.notify('Selecciona un municipio','error');return}
    var r=await W.api(id?('/api/v1/admin/store/shipping/rates/'+id):'/api/v1/admin/store/shipping/rates',{method:id?'PUT':'POST',body:payload});
    if(r.ok){W.notify('Tarifa guardada','success');W.closeModal();W._shippingDetail=null;W.storeShipping(W._shippingTab)}
};

W.shippingRateDelete=function(id){
    W.confirm('¿Eliminar esta tarifa de envío?',async function(){
        var r=await W.api('/api/v1/admin/store/shipping/rates/'+id,{method:'DELETE'});
        if(r.ok){W.notify('Tarifa eliminada','success');W._shippingDetail=null;W.storeShipping(W._shippingTab)}
    });
};

W.storeAddi=async function(){
    var el=document.getElementById('store-content');
    el.innerHTML='<div class="w-empty-state"><h3>Cargando Addi</h3><p>Consultando configuración y transacciones.</p></div>';
    var r=await W.api('/api/v1/admin/addi/overview');
    if(!r.ok||!r.data){el.innerHTML='<div class="w-empty-state"><h3>No se pudo cargar Addi</h3><p>'+W.esc(r.message||'Intenta de nuevo.')+'</p></div>';return}
    W._addi=r.data;
    var s=W._addi.settings||{},st=W._addi.stats||{},lc=s.last_config||{};
    var h='<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:16px"><div><h3 style="margin:0">Addi · pago a cuotas</h3><p style="margin:5px 0 0;color:var(--w-muted);font-size:12px">Brick de integración con Addi para originar créditos desde el checkout.</p></div><div style="display:flex;gap:8px"><button class="w-btn w-btn-secondary" onclick="wontia.storeAddiTest()">Probar conexión</button><button class="w-btn w-btn-primary" onclick="wontia.storeAddiSave()">Guardar configuración</button></div></div>';
    h+='<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:16px">'
        +W.addiStat('Estado',s.configured?(s.enabled?'Activo':'Desactivado'):'Sin configurar',s.environment_label||'')
        +W.addiStat('Transacciones',W.num(st.total||0),(st.approved||0)+' aprobadas')
        +W.addiStat('Monto aprobado','$'+W.num((st.amount_cents||0)/100),'crédito Addi')
        +W.addiStat('Compra permitida',lc.minAmount!=null?('$'+W.num(lc.minAmount)+' - $'+W.num(lc.maxAmount||0)):'Consultar',lc.isActiveAlly?'Ally activo':'Ally inactivo')
        +'</div>';
    if(!s.configured)h+='<div class="w-card" style="border-left:3px solid #d97706;background:#fffbeb;margin-bottom:14px"><b>Configura las credenciales de Addi</b><p style="font-size:12px;color:#92400e;margin:5px 0 0">Sin ally slug, client ID y client secret el método no aparecerá en el checkout.</p></div>';
    else if(lc&&lc.isActiveAlly===false)h+='<div class="w-card" style="border-left:3px solid #dc2626;background:#fef2f2;margin-bottom:14px"><b>Addi reporta el ally como inactivo</b><p style="font-size:12px;color:#991b1b;margin:5px 0 0">El método se ocultará en el checkout hasta que Addi active el comercio o marques “Forzar visible”. Última consulta: '+W.esc(s.last_config_at||'—')+'</p></div>';
    h+='<div class="w-card" style="padding:16px;margin-bottom:14px"><h3 style="margin:0 0 12px">Credenciales y conexión</h3><div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Integración</label><select class="w-input" id="ad-enabled" style="width:100%"><option value="1"'+(s.enabled?' selected':'')+'>Activa</option><option value="0"'+(s.enabled?'':' selected')+'>Inactiva</option></select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Ambiente</label><select class="w-input" id="ad-env" style="width:100%"><option value="staging"'+(s.environment==='staging'?' selected':'')+'>Staging</option><option value="production"'+(s.environment==='production'?' selected':'')+'>Producción</option></select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Ally slug *</label><input class="w-input" id="ad-ally" value="'+W.esc(s.ally_slug||'')+'" placeholder="slug-del-comercio" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Client ID *</label><input class="w-input" id="ad-client" value="'+W.esc(s.client_id||'')+'" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Client secret '+(s.client_secret_set?'(guardado)':'(sin configurar)')+'</label><input class="w-input" type="password" id="ad-secret" value="" placeholder="'+(s.client_secret_set?'••••••••':'Pega el secreto')+'" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Usuario callback</label><input class="w-input" id="ad-cb-user" value="'+W.esc(s.callback_user||'')+'" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Contraseña callback '+(s.callback_password_set?'(guardada)':'(sin configurar)')+'</label><div style="display:flex;gap:6px"><input class="w-input" type="password" id="ad-cb-pass" value="" placeholder="'+(s.callback_password_set?'••••••••':'Configura una contraseña')+'" style="width:100%"/><button type="button" class="w-btn w-btn-secondary" onclick="wontia.addiGenPass()">Generar</button></div></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">URL callback (Basic Auth)</label><input class="w-input" id="ad-callback" value="'+W.esc(s.callback_url||'')+'" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">URL redirección</label><input class="w-input" id="ad-redirect" value="'+W.esc(s.redirection_url||'')+'" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Logo (URL)</label><input class="w-input" id="ad-logo" value="'+W.esc(s.logo_url||'')+'" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Etiqueta checkout</label><input class="w-input" id="ad-label" value="'+W.esc(s.label||'Paga con Addi')+'" style="width:100%"/></div>'
        +'</div><div style="display:flex;gap:18px;flex-wrap:wrap;margin-top:12px"><label style="font-size:12px;display:flex;gap:7px;align-items:center"><input type="checkbox" id="ad-force" '+(s.force_visible?'checked':'')+'/> Forzar visible si Addi está inactivo</label><label style="font-size:12px;display:flex;gap:7px;align-items:center"><input type="checkbox" id="ad-widget" '+(s.widget_enabled?'checked':'')+'/> Mostrar widget en productos</label></div></div>';
    h+='<div class="w-card" style="padding:16px;margin-bottom:14px"><h3 style="margin:0 0 10px">Widget de producto</h3><div style="display:grid;grid-template-columns:1fr 1fr;gap:12px"><div><label style="font-size:11px;color:var(--w-muted)">Título</label><input class="w-input" id="ad-widget-title" value="'+W.esc(s.widget_title||'')+'" style="width:100%"/></div><div><label style="font-size:11px;color:var(--w-muted)">Estilos JSON (opcional)</label><input class="w-input" id="ad-widget-styles" value="'+W.esc(s.widget_styles||'')+'" placeholder="{\"widget\":{\"fontFamily\":\"system-ui\"}}" style="width:100%"/></div></div><p style="font-size:11px;color:var(--w-muted);margin:8px 0 0">Se inserta como &lt;addi-widget&gt; en la ficha de producto. Script: widgets.addi.com/bundle.min.js</p></div>';
    h+='<div class="w-card" style="border-left:3px solid '+(s.enabled?'#dc2626':'#6b7280')+';background:'+(s.enabled?'#fef2f2':'#f3f4f6')+';margin-bottom:14px"><b>'+(s.enabled?'¿Addi no fue aprobado?':'Addi deshabilitado')+'</b><p style="font-size:12px;color:'+(s.enabled?'#991b1b':'#374151')+';margin:5px 0 0">'+(s.enabled?'Desactívalo para ocultarlo por completo del checkout y de las fichas de producto.':'No se mostrará en el checkout ni en las fichas de producto. Puedes reactivarlo cuando Addi apruebe el comercio.')+'</p>'+(s.enabled?'<button class="w-btn w-btn-danger w-btn-sm" style="margin-top:10px" onclick="wontia.storeAddiDisable()">Desactivar Addi</button>':'')+'</div>';
    var rows=W._addi.transactions||[];
    h+='<div class="w-card" style="padding:0;overflow:hidden"><div style="padding:14px 16px;border-bottom:1px solid var(--w-border);display:flex;justify-content:space-between;align-items:center"><h3 style="margin:0">Transacciones Addi</h3><span style="font-size:11px;color:var(--w-muted)">'+rows.length+' registradas</span></div>';
    if(!rows.length)h+='<div class="w-empty-state" style="padding:30px"><h3>Sin transacciones</h3><p>Las solicitudes de crédito aparecerán aquí.</p></div>';
    else{h+='<div style="overflow:auto"><table style="width:100%;border-collapse:collapse;font-size:12px"><thead><tr style="text-align:left;color:var(--w-muted);font-size:10px;text-transform:uppercase"><th style="padding:10px 14px">Pedido</th><th style="padding:10px 14px">Cliente</th><th style="padding:10px 14px">Monto</th><th style="padding:10px 14px">Estado</th><th style="padding:10px 14px">Application</th><th style="padding:10px 14px">Fecha</th><th style="padding:10px 14px"></th></tr></thead><tbody>';
      rows.forEach(function(t){var col=W.addiStatusClass(t.status);h+='<tr style="border-top:1px solid var(--w-border)"><td style="padding:9px 14px"><b>'+W.esc(t.order_number||'—')+'</b></td><td style="padding:9px 14px;color:var(--w-muted)">'+W.esc(t.customer_name||t.customer_email||'—')+'</td><td style="padding:9px 14px">$'+W.num((t.amount_cents||0)/100)+'</td><td style="padding:9px 14px"><span style="padding:2px 7px;border-radius:999px;font-size:10px;font-weight:700;background:'+col.bg+';color:'+col.fg+'">'+W.esc(t.status||'—')+'</span></td><td style="padding:9px 14px;color:var(--w-muted);font-size:10px">'+W.esc(t.application_id||'—')+'</td><td style="padding:9px 14px;color:var(--w-muted);font-size:10px">'+W.esc((t.created_at||'').slice(0,16))+'</td><td style="padding:9px 14px;text-align:right">'+(t.status==='APPROVED'?'<button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.addiCancel(\''+W.esc(t.order_uuid)+'\')">Cancelar</button>':'')+'</td></tr>'});
      h+='</tbody></table></div>';}
    h+='</div>';
    el.innerHTML=h;
};
W.addiStat=function(label,value,note){return '<div class="w-card" style="padding:12px 14px"><div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--w-muted);font-weight:700">'+W.esc(label)+'</div><div style="font-size:19px;font-weight:800;margin-top:4px">'+W.esc(value)+'</div><div style="font-size:10px;color:var(--w-muted);margin-top:2px">'+W.esc(note||'')+'</div></div>'};
W.addiStatusClass=function(status){if(status==='APPROVED')return {bg:'#dcfce7',fg:'#166534'};if(status==='ERROR'||status==='REJECTED'||status==='DECLINED'||status==='ABANDONED'||status==='INTERNAL_ERROR')return {bg:'#fee2e2',fg:'#991b1b'};return {bg:'#fef3c7',fg:'#92400e'}};
W.storeAddiSave=async function(){
    var v=function(id){var el=document.getElementById(id);return el?el.value:''};
    var c=function(id){var el=document.getElementById(id);return !!(el&&el.checked)};
    var payload={enabled:v('ad-enabled')==='1',environment:v('ad-env'),ally_slug:v('ad-ally'),client_id:v('ad-client'),callback_user:v('ad-cb-user'),callback_url:v('ad-callback'),redirection_url:v('ad-redirect'),logo_url:v('ad-logo'),label:v('ad-label'),force_visible:c('ad-force'),widget_enabled:c('ad-widget'),widget_title:v('ad-widget-title'),widget_styles:v('ad-widget-styles')};
    var secret=v('ad-secret'),cbpass=v('ad-cb-pass');if(secret)payload.client_secret=secret;if(cbpass)payload.callback_password=cbpass;
    var r=await W.api('/api/v1/admin/addi/settings',{method:'PUT',body:payload});
    if(r.ok){W.notify('Configuración de Addi guardada','success');W.storeAddi()}else W.notify((r.message||'No se pudo guardar Addi'),'error');
};
W.storeAddiDisable=async function(){
    var sel=document.getElementById('ad-enabled');
    if(sel)sel.value='0';
    await W.storeAddiSave();
};
W.storeAddiTest=async function(){
    var r=await W.api('/api/v1/admin/addi/test',{method:'POST',body:{}});
    var d=r.data||{};var cfg=d.config&&d.config.data?d.config.data:{};var h='<div style="font-size:13px;line-height:1.8"><div><b>Ambiente:</b> '+W.esc(d.environment||'—')+'</div><div><b>Configurado:</b> '+(d.configured?'Sí':'No')+'</div><div><b>Ally activo:</b> '+(cfg.isActiveAlly?'Sí':'No')+'</div><div><b>Mínimo:</b> '+(cfg.minAmount!=null?'$'+W.num(cfg.minAmount):'—')+'</div><div><b>Máximo:</b> '+(cfg.maxAmount!=null?'$'+W.num(cfg.maxAmount):'—')+'</div><div><b>Addi Pay Now:</b> '+(cfg.isActivePayNow?'Sí':'No')+'</div><div><b>Auth:</b> '+(d.auth&&d.auth.ok?'OK':(d.auth&&d.auth.message?W.esc(d.auth.message):'No probado'))+'</div><div><b>Callback:</b> '+W.esc(d.callback_url||'—')+'</div></div>';
    if(!d.ok)h='<div class="w-empty-state" style="padding:18px"><b>La prueba encontró un problema</b><p>'+W.esc((r.message||(d.config&&d.config.message)||'Revisa las credenciales.'))+'</p></div>'+h;
    W.modal('Prueba de conexión Addi',h,'<button class="w-btn w-btn-primary" onclick="wontia.closeModal()">Cerrar</button>');
};
W.addiGenPass=function(){var chars='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%*?-_';var a=new Uint32Array(32);if(window.crypto&&crypto.getRandomValues)crypto.getRandomValues(a);var s='';for(var i=0;i<32;i++)s+=chars[(a[i]||Math.floor(Math.random()*chars.length))%chars.length];var el=document.getElementById('ad-cb-pass');if(el)el.value=s;};
W.addiCancel=function(uuid){W.confirm('¿Solicitar cancelación total de este crédito Addi? Solo se permite una cancelación por transacción.',async function(){var r=await W.api('/api/v1/admin/addi/cancel/'+encodeURIComponent(uuid),{method:'POST',body:{}});if(r.ok){W.notify(r.message||'Cancelación solicitada','success');W.storeAddi()}else W.notify(r.message||'No se pudo cancelar','error')})};

W.storeLogoMethods=[['breb','Bre-B'],['addi','Addi'],['request','WhatsApp / pago asistido'],['mercadopago','Mercado Pago'],['epayco','ePayco']];
W.storeLogoCard=function(id,label,url,custom){
    url=url||'';
    return '<div class="w-card" style="padding:12px;margin:0"><div style="display:flex;gap:10px;align-items:center;margin-bottom:8px"><div style="width:92px;height:48px;border:1px solid var(--w-border);border-radius:9px;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden"><img id="ss-logo-prev-'+id+'" src="'+W.esc(url)+'" alt="" style="max-width:100%;max-height:100%;object-fit:contain;display:'+(url?'block':'none')+'"/><span id="ss-logo-empty-'+id+'" style="font-size:10px;color:var(--w-muted);display:'+(url?'none':'block')+'">Sin logo</span></div><div style="flex:1;min-width:0"><b style="font-size:12px">'+W.esc(label)+'</b><input class="w-input" id="ss-logo-'+id+'" value="'+W.esc(url)+'" placeholder="https://... o /assets/uploads/..." oninput="wontia.storeLogoPreview(\''+id+'\')" style="width:100%;font-size:11px;margin-top:5px"/></div></div><div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap"><input type="file" accept="image/*" onchange="wontia.storeLogoUpload(\''+id+'\',this)" style="font-size:10px;max-width:150px"/><button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.storeLogoRemove(\''+id+'\')">Quitar</button>'+(custom?'<button type="button" class="w-btn w-btn-danger w-btn-sm" onclick="wontia.storeLogoRemoveMethod('+id+')">Eliminar método</button>':'')+'</div></div>';
};
W.storeLogoPreview=function(id){var v=document.getElementById('ss-logo-'+id),img=document.getElementById('ss-logo-prev-'+id),empty=document.getElementById('ss-logo-empty-'+id);if(!v||!img)return;var u=v.value.trim();img.src=u;img.style.display=u?'block':'none';if(empty)empty.style.display=u?'none':'block';};
W.storeLogoRemove=function(id){var v=document.getElementById('ss-logo-'+id);if(v)v.value='';W.storeLogoPreview(id);W.storeLogoSave()};
W.storeLogoSave=async function(){
    var logos={};W.storeLogoMethods.forEach(function(pair){var el=document.getElementById('ss-logo-'+pair[0]);var u=el?el.value.trim():'';if(u)logos[pair[0]]=u});
    var r=await W.api('/api/v1/admin/store/settings',{method:'PUT',body:{store_payment_logos:JSON.stringify(logos),store_payment_logo_methods:JSON.stringify(W.storeLogoCustom||[]),store_payment_logo_methods:JSON.stringify(W.storeLogoCustom||[])}});
    if(r.ok)W.notify('Logos de pago guardados','success');else W.notify((r&&r.message)||'No se pudieron guardar los logos','error');
};
W.storeLogoUpload=async function(id,input){
    var file=input&&input.files&&input.files[0];if(!file)return;
    if(file.size>12*1024*1024){W.notify('La imagen supera 12 MB','error');input.value='';return}
    var fd=new FormData();fd.append('file',file);fd.append('alt_text','Logo de pago '+id);
    var r=await W.api('/api/v1/admin/media/upload',{method:'POST',body:fd});
    var u=r&&r.ok&&r.data?(r.data.url||(r.data.data&&r.data.data.url)||''):'';
    if(!u){W.notify((r&&(r.message||(r.data&&r.data.message)))||'No se pudo subir el logo','error');input.value='';return}
    var v=document.getElementById('ss-logo-'+id);if(v){v.value=u;W.storeLogoPreview(id)}
    await W.storeLogoSave();input.value='';
};

W.storeLogoCustom=[];
W.storeLogoSelect=function(){
    var sel=document.getElementById('ss-logo-method-select');
    var name=document.getElementById('ss-logo-attach-name');
    var url=document.getElementById('ss-logo-attach-url');
    var id=sel?sel.value:'';
    if(!id){if(name)name.textContent='Selecciona un método para vincular su logo';if(url)url.value='';W.storeLogoAttachPreview();return}
    var pair=(W.storeLogoMethods||[]).filter(function(x){return String(x[0])===String(id)})[0]||['',''];
    var card=document.getElementById('ss-logo-'+id);
    if(name)name.textContent='Logo para '+(pair[1]||id);
    if(url)url.value=card?card.value:'';
    W.storeLogoAttachPreview();
};
W.storeLogoAttachPreview=function(){
    var url=document.getElementById('ss-logo-attach-url');
    var img=document.getElementById('ss-logo-attach-prev');
    if(!url||!img)return;
    var u=url.value.trim();
    img.src=u;
    img.style.display=u?'block':'none';
};
W.storeLogoAttach=async function(){
    var sel=document.getElementById('ss-logo-method-select');
    var id=sel?sel.value:'';
    if(!id){W.notify('Selecciona un método de pago','error');return}
    var card=document.getElementById('ss-logo-'+id);
    var fileInput=document.getElementById('ss-logo-attach-file');
    if(fileInput&&fileInput.files&&fileInput.files[0]){
        if(card)card.value='';
        await W.storeLogoUpload(id,fileInput);
        W.storeLogoSelect();
        return;
    }
    var url=document.getElementById('ss-logo-attach-url');
    if(!url||!card){W.notify('No se encontró el método seleccionado','error');return}
    card.value=url.value.trim();
    W.storeLogoPreview(id);
    await W.storeLogoSave();
    W.storeLogoSelect();
};
W.storeLogoAddMethod=async function(){
    var input=document.getElementById('ss-logo-method-label');
    var label=input?input.value.trim():'';
    if(label.length<2){W.notify('Escribe un nombre de al menos 2 caracteres','error');return}
    var base='custom-'+String(label).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,40);
    if(base==='custom-')base='custom-'+Date.now();
    var id=base,n=2,used=function(v){return (W.storeLogoMethods||[]).some(function(x){return String(x[0])===v})};
    while(used(id)){id=base+'-'+(n++)}
    var custom=(W.storeLogoCustom||[]).concat([{id:id,label:label}]);
    var r=await W.api('/api/v1/admin/store/settings',{method:'PUT',body:{store_payment_logo_methods:JSON.stringify(custom)}});
    if(r&&r.ok){W.notify('Método manual agregado','success');W.storeSettings()}else W.notify((r&&r.message)||'No se pudo agregar el método','error');
};
W.storeLogoRemoveMethod=function(id){
    W.confirm('¿Eliminar este método manual del administrador de logos?',async function(){
        var custom=(W.storeLogoCustom||[]).filter(function(x){return String(x.id)!==String(id)});
        var r=await W.api('/api/v1/admin/store/settings',{method:'PUT',body:{store_payment_logo_methods:JSON.stringify(custom)}});
        if(r&&r.ok){W.notify('Método eliminado','success');W.storeSettings()}else W.notify((r&&r.message)||'No se pudo eliminar el método','error');
    });
};

W.catalogBrands=[];
W.salesCategories=[];
W.salesSelected=[];
W.catalogSelected=[];
W.catalogBrandCount=function(name){var n=String(name||'').trim().toLowerCase();for(var i=0;i<W.catalogBrands.length;i++){if(String(W.catalogBrands[i].name||'').trim().toLowerCase()===n)return W.catalogBrands[i].count||0}return 0};
W.catalogRenderChips=function(){
    var box=document.getElementById('ss-cat-chips'),count=document.getElementById('ss-cat-count');if(!box)return;
    if(!W.catalogSelected.length){box.innerHTML='<span style="font-size:11px;color:var(--w-muted)">Sin marcas seleccionadas. La tienda mostrará todo el catálogo.</span>'}
    else{box.innerHTML=W.catalogSelected.map(function(name,i){return '<span draggable="true" ondragstart="wontia.catalogDragStart(event,'+i+')" ondragover="event.preventDefault()" ondrop="wontia.catalogDrop(event,'+i+')" style="display:inline-flex;align-items:center;gap:6px;padding:6px 8px;border:1px solid var(--w-border);border-radius:9px;background:#fff;color:#333333;font-size:11px;font-weight:700;cursor:grab"><span style="color:#9ca3af;letter-spacing:-2px">::</span><span>'+W.esc(name)+'</span><small style="color:#333333;font-weight:600">'+W.catalogBrandCount(name)+'</small><button type="button" class="w-btn w-btn-secondary w-btn-sm" style="padding:1px 5px;min-height:auto" onclick="wontia.catalogMove('+i+',-1)" title="Subir">↑</button><button type="button" class="w-btn w-btn-secondary w-btn-sm" style="padding:1px 5px;min-height:auto" onclick="wontia.catalogMove('+i+',1)" title="Bajar">↓</button><button type="button" class="w-btn w-btn-danger w-btn-sm" style="padding:1px 6px;min-height:auto" onclick="wontia.catalogRemove('+i+')" title="Quitar">×</button></span>'}).join('')}
    if(count)count.textContent=W.catalogSelected.length+'/10 marcas';
};
W.catalogAdd=function(name){
    name=String(name||'').trim();if(!name)return;
    var match=null;for(var i=0;i<W.catalogBrands.length;i++){if(String(W.catalogBrands[i].name||'').trim().toLowerCase()===name.toLowerCase()){match=W.catalogBrands[i];break}}
    var canonical=match?match.name:name;
    if(W.catalogSelected.some(function(x){return String(x).toLowerCase()===canonical.toLowerCase()})){W.notify('Esa marca ya está seleccionada','info');return}
    if(W.catalogSelected.length>=10){W.notify('Máximo 10 marcas destacadas','error');return}
    W.catalogSelected.push(canonical);
    var input=document.getElementById('ss-cat-brand-input');if(input)input.value='';
    W.catalogRenderChips();
};
W.catalogRemove=function(i){W.catalogSelected.splice(i,1);W.catalogRenderChips()};
W.catalogMove=function(i,delta){var j=i+delta;if(j<0||j>=W.catalogSelected.length)return;var t=W.catalogSelected[i];W.catalogSelected[i]=W.catalogSelected[j];W.catalogSelected[j]=t;W.catalogRenderChips()};
W.catalogDragStart=function(e,i){W._catalogDrag=i;try{e.dataTransfer.effectAllowed='move';e.dataTransfer.setData('text/plain',String(i))}catch(x){}};
W.catalogDrop=function(e,i){e.preventDefault();var from=W._catalogDrag;if(from==null||from===i)return;var t=W.catalogSelected.splice(from,1)[0];W.catalogSelected.splice(i,0,t);W._catalogDrag=null;W.catalogRenderChips()};
W.catalogTop=function(){var sorted=W.catalogBrands.slice().sort(function(a,b){return (b.count||0)-(a.count||0)});W.catalogSelected=sorted.slice(0,10).map(function(x){return x.name});W.catalogRenderChips()};
W.catalogClear=function(){W.catalogSelected=[];W.catalogRenderChips()};
W.catalogSortOptions=[
    ['catalog','Orden original del catálogo'],
    ['name_asc','Nombre A–Z'],
    ['name_desc','Nombre Z–A'],
    ['price_desc','Precio: mayor a menor'],
    ['price_asc','Precio: menor a mayor'],
    ['brand','Marca A–Z'],
    ['guitars_amps','Guitarras y amplificadores'],
    ['pianos_amps','Pianos y amplificadores'],
    ['basses_amps','Bajos y amplificadores'],
    ['gpb_interleaved','Guitarras, pianos y bajos intercalados'],
    ['guitars_basses','Guitarras y bajos'],
    ['guitars_basses_amps','Guitarras, bajos y amplificadores'],
    ['guitars_pianos_basses','Guitarras, pianos y bajos'],
    ['guitars_pianos_basses_amps','Guitarras, pianos, bajos y amplificadores'],
    ['guitars_amps_interleaved','Guitarras y amplificadores intercalados'],
    ['band','Banda completa: guitarras, bajos, pianos, batería y audio'],
    ['stage','Sonido para escenario'],
    ['studio','Estudio y grabación'],
    ['acoustic','Acústicos y clásicos'],
    ['percussion_audio','Percusión y audio'],
    ['winds_accordions','Vientos y acordeones'],
    ['dj_production','DJ y producción']
];
W.catalogPanel=function(s){
    var sort=s.store_catalog_sort||'catalog',mode=s.store_catalog_mode||'all';
    var showSales=String(s.store_show_sales===undefined?'1':s.store_show_sales)!=='0';
    var salesSelected=Array.isArray(W.salesSelected)?W.salesSelected:[];
    var salesChecks=(W.salesCategories||[]).map(function(c){return '<label style="font-size:11px;display:inline-flex;gap:6px;align-items:center;border:1px solid var(--w-border);border-radius:8px;padding:6px 8px;background:#fff;color:#333333"><input type="checkbox" class="ss-sales-cat" value="'+W.esc(c.slug)+'"'+(salesSelected.indexOf(String(c.slug))>=0?' checked':'')+'/> '+W.esc(c.name)+'</label>'}).join('');
    return '<div class="w-card" id="store-catalog-panel" style="margin-bottom:14px"><div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap"><div><h3 style="margin:0">Menú TIENDA · organización del catálogo</h3><p style="font-size:12px;color:var(--w-muted);margin:5px 0 0">Define el orden, la selección de marcas y el escaparate de la tienda. Los cambios se aplican en la pestaña <b>TODOS</b> de <b>/tienda</b>.</p></div><a class="w-btn w-btn-secondary w-btn-sm" href="/tienda" target="_blank" rel="noopener">Ver tienda</a></div>'
        +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px">'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Ordenar productos</label><select class="w-input" id="ss-cat-sort" style="width:100%">'+W.catalogSortOptions.map(function(o){return '<option value="'+W.esc(o[0])+'"'+(sort===o[0]?' selected':'')+'>'+W.esc(o[1])+'</option>'}).join('')+'</select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Escaparate</label><select class="w-input" id="ss-cat-mode" style="width:100%"><option value="all"'+(mode==='all'?' selected':'')+'>Todo el catálogo</option><option value="offers"'+(mode==='offers'?' selected':'')+'>Solo ofertas y promociones</option><option value="brands"'+(mode==='brands'?' selected':'')+'>Solo marcas seleccionadas</option><option value="mix"'+(mode==='mix'?' selected':'')+'>Mix: marcas seleccionadas + ofertas</option></select></div>'
        +'</div>'
        +'<p style="font-size:10.5px;color:var(--w-muted);margin:9px 0 0">Las colecciones de varios grupos se mezclan de forma equilibrada para que la primera página no quede dominada por una sola categoría.</p>'
        +'<div style="display:flex;gap:18px;flex-wrap:wrap;margin-top:12px"><label style="font-size:12px;display:flex;gap:7px;align-items:center"><input type="checkbox" id="ss-cat-hide" '+((s.store_catalog_hide_out_of_stock==='1')?'checked':'')+'/> Ocultar agotados</label><label style="font-size:12px;display:flex;gap:7px;align-items:center"><input type="checkbox" id="ss-cat-tags" '+((s.store_catalog_show_tags!=='0')?'checked':'')+'/> Mostrar etiquetas de marcas en la tienda</label><label style="font-size:12px;display:flex;gap:7px;align-items:center"><input type="checkbox" id="ss-cat-oos-add" '+((s.store_oos_disable_add!=='0')?'checked':'')+'/> Deshabilitar Agregar cuando esté agotado</label></div>'
        +'<div style="margin-top:14px;padding:12px;border:1px solid var(--w-border);border-radius:10px;background:#fff;color:#333333"><div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap"><div><b style="font-size:12px;color:#333333">Vendidos el mes pasado</b><p style="font-size:11px;color:#333333;margin:4px 0 0">Un clic para mostrar u ocultar la etiqueta en las tarjetas y fichas. Si no seleccionas categorías, se muestra en todos los productos.</p></div><label style="font-size:12px;display:flex;gap:7px;align-items:center;white-space:nowrap;color:#333333"><input type="checkbox" id="ss-sales-enabled" onchange="wontia.salesToggle(this.checked)" '+(showSales?'checked':'')+'/> Mostrar etiqueta</label></div><div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px">'+salesChecks+'</div><div style="font-size:10.5px;color:#333333;margin-top:7px">Opcional: marca categorías para limitar la etiqueta. Sin categorías marcadas = todos los productos.</div></div>'
        +'<div style="margin-top:14px"><div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap"><label style="font-size:11px;color:var(--w-muted)">Marcas destacadas para TIENDA <span id="ss-cat-count" style="font-weight:800;color:var(--w-text)"></span></label><div style="display:flex;gap:6px;flex-wrap:wrap"><button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.catalogTop()">Top 10 por inventario</button><button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.catalogClear()">Limpiar</button></div></div>'
        +'<div style="display:flex;gap:7px;margin-top:8px;flex-wrap:wrap"><input class="w-input" id="ss-cat-brand-input" list="ss-cat-brand-list" placeholder="Escribe una marca y pulsa Enter" style="flex:1;min-width:220px" onkeydown="if(event.key===\'Enter\'){event.preventDefault();wontia.catalogAdd(this.value)}"/><datalist id="ss-cat-brand-list">'+W.catalogBrands.map(function(b){return '<option value="'+W.esc(b.name)+'">'+W.esc(b.count+' productos')+'</option>'}).join('')+'</datalist><button type="button" class="w-btn w-btn-primary w-btn-sm" onclick="wontia.catalogAdd(document.getElementById(\'ss-cat-brand-input\').value)">+ Agregar</button></div>'
        +'<div id="ss-cat-chips" style="display:flex;gap:7px;flex-wrap:wrap;margin-top:9px"></div><p style="font-size:10.5px;color:var(--w-muted);margin:7px 0 0">Máximo 10. Arrastra las etiquetas o usa ↑ ↓ para cambiar su orden. Se guardan como parte de la configuración de la tienda.</p></div>'
        +'<div style="margin-top:14px"><button type="button" class="w-btn w-btn-primary" onclick="wontia.storeSettingsSave()">Guardar organización</button></div></div>';
};
W.recSlots=[
    ['strings','Cuerdas'],['strap','Correas'],['picks','Púas'],['cable','Cables'],['tuner','Afinadores'],
    ['stand','Bases y soportes'],['amp','Amplificadores'],['pedal','Pedales'],['mic','Micrófonos'],
    ['case','Fundas y estuches'],['power','Fuentes'],['accessory','Accesorios generales'],
    ['bench','Bancos'],['headphones','Audífonos'],['capo','Capos'],['sticks','Baquetas'],
    ['practice_pad','Practice pads'],['ear_protection','Protección auditiva'],['xlr','Cables XLR'],
    ['mic_stand','Pies de micrófono'],['interface','Interfaces'],['metronome','Metrónomos']
];
W.recData=function(s){
    var out={enabled:true,title:'Completa tu sonido',subtitle:'Productos que combinan con tu compra',max:3,max_more:6,mode:'smart',show_reasons:true,hide_out_of_stock:true,no_repeat:true,fill_fallback:true,disabled_slots:[],discount_enabled:true,rounding:100,stackable:true};
    try{var r=JSON.parse(s.store_recommendations||'{}')||{};Object.keys(r).forEach(function(k){out[k]=r[k]})}catch(e){}
    return out;
};
W.recommendationPanel=function(s){
    var r=W.recData(s), disabled=Array.isArray(r.disabled_slots)?r.disabled_slots:[];
    var slotChecks=W.recSlots.map(function(x){return '<label style="font-size:11px;display:inline-flex;gap:6px;align-items:center;border:1px solid var(--w-border);border-radius:8px;padding:6px 8px;background:#fff;color:#333"><input type="checkbox" class="ss-rec-slot" value="'+x[0]+'"'+(disabled.indexOf(x[0])<0?' checked':'')+'/> '+W.esc(x[1])+'</label>'}).join('');
    return '<div class="w-card" id="store-recommendations-panel" style="margin-bottom:14px">'
        +'<div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap"><div><h3 style="margin:0">Productos recomendados en el carrito</h3><p style="font-size:12px;color:var(--w-muted);margin:5px 0 0">Sugerencias de venta cruzada con descuento progresivo. Se muestran debajo de los productos del carrito.</p></div><span style="font-size:10px;font-weight:800;padding:4px 8px;border-radius:999px;background:#f1f5e8;color:#556B2F">Brick ecommerce</span></div>'
        +'<div style="display:grid;grid-template-columns:2fr 1fr;gap:12px;margin-top:14px">'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Título</label><input class="w-input" id="ss-rec-title" value="'+W.esc(r.title)+'" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Activar módulo</label><select class="w-input" id="ss-rec-enabled" style="width:100%"><option value="1"'+(r.enabled?' selected':'')+'>Sí, mostrar recomendados</option><option value="0"'+(r.enabled?'':' selected')+'>No, ocultar</option></select></div>'
        +'<div style="grid-column:1/-1"><label style="font-size:11px;color:var(--w-muted)">Subtítulo</label><input class="w-input" id="ss-rec-subtitle" value="'+W.esc(r.subtitle)+'" style="width:100%"/></div>'
        +'</div>'
        +'<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:12px">'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Tarjetas visibles</label><select class="w-input" id="ss-rec-max" style="width:100%">'+[1,2,3].map(function(n){return '<option value="'+n+'"'+(Number(r.max)===n?' selected':'')+'>'+n+'</option>'}).join('')+'</select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Máximo con Ver más</label><select class="w-input" id="ss-rec-more" style="width:100%">'+[3,4,5,6,8,9,10,12].map(function(n){return '<option value="'+n+'"'+(Number(r.max_more)===n?' selected':'')+'>'+n+'</option>'}).join('')+'</select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Modo</label><select class="w-input" id="ss-rec-mode" style="width:100%"><option value="smart"'+(r.mode==='smart'?' selected':'')+'>Equilibrado</option><option value="essentials"'+(r.mode==='essentials'?' selected':'')+'>Solo esenciales</option><option value="mix"'+(r.mode==='mix'?' selected':'')+'>Mix completo</option></select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Redondeo del descuento</label><select class="w-input" id="ss-rec-round" style="width:100%">'+[10,50,100].map(function(n){return '<option value="'+n+'"'+(Number(r.rounding)===n?' selected':'')+'>$'+n+'</option>'}).join('')+'</select></div>'
        +'</div>'
        +'<div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:12px">'
        +'<label style="font-size:12px;display:flex;gap:7px;align-items:center"><input type="checkbox" id="ss-rec-reasons" '+(r.show_reasons?'checked':'')+'/> Mostrar motivo de la sugerencia</label>'
        +'<label style="font-size:12px;display:flex;gap:7px;align-items:center"><input type="checkbox" id="ss-rec-oos" '+(r.hide_out_of_stock?'checked':'')+'/> Ocultar agotados</label>'
        +'<label style="font-size:12px;display:flex;gap:7px;align-items:center"><input type="checkbox" id="ss-rec-norepeat" '+(r.no_repeat?'checked':'')+'/> No repetir productos del carrito</label>'
        +'<label style="font-size:12px;display:flex;gap:7px;align-items:center"><input type="checkbox" id="ss-rec-fill" '+(r.fill_fallback?'checked':'')+'/> Completar con accesorios populares</label>'
        +'</div>'
        +'<div style="margin-top:14px;padding:11px 12px;border:1px solid #dfe8cc;border-radius:9px;background:#f7faf1"><div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center"><div style="font-size:12px;color:#46552c"><b>Descuento de venta cruzada</b><br><span style="font-size:10.5px">0,5% al artículo más caro · 1% al intermedio · 1,5% al más barato. Solo los 3 primeros recomendados agregados desde este módulo.</span></div><label style="font-size:12px;display:flex;gap:7px;align-items:center;color:#46552c"><input type="checkbox" id="ss-rec-discount" '+(r.discount_enabled?'checked':'')+'/> Activo</label></div>'
        +'<div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:7px;font-size:10.5px;color:#556B2F"><span>Mínimo $100</span><span>Acumulable con cupón y Bre-B</span><span>Se recalcula en el servidor</span></div></div>'
        +'<div style="margin-top:14px"><label style="font-size:11px;color:var(--w-muted)">Tipos de sugerencia desactivados</label><p style="font-size:10.5px;color:var(--w-muted);margin:3px 0 7px">Desmarca una categoría para dejar de sugerirla en todas las combinaciones.</p><div style="display:flex;gap:6px;flex-wrap:wrap">'+slotChecks+'</div></div>'
        +'<div style="margin-top:14px"><button type="button" class="w-btn w-btn-primary" onclick="wontia.storeSettingsSave()">Guardar configuración</button></div>'
        +'</div>';
};
W.recPayload=function(){
    var v=function(id){var el=document.getElementById(id);return el?el.value:''};
    var c=function(id){var el=document.getElementById(id);return !!(el&&el.checked)};
    var disabled=[];document.querySelectorAll('.ss-rec-slot').forEach(function(x){if(!x.checked)disabled.push(x.value)});
    return {enabled:v('#ss-rec-enabled')==='1',title:v('#ss-rec-title'),subtitle:v('#ss-rec-subtitle'),max:parseInt(v('#ss-rec-max'))||3,max_more:parseInt(v('#ss-rec-more'))||12,mode:v('#ss-rec-mode')||'smart',show_reasons:c('ss-rec-reasons'),hide_out_of_stock:c('ss-rec-oos'),no_repeat:c('ss-rec-norepeat'),fill_fallback:c('ss-rec-fill'),disabled_slots:disabled,discount_enabled:c('ss-rec-discount'),rounding:parseInt(v('#ss-rec-round'))||100,stackable:true};
};
W.socialProofData=function(s){
    var out={enabled:true,position:'bottom-left',animation_in:'slide-up',animation_out:'fade',interval_min:300,interval_max:300,initial_delay:12,visible_seconds:8,age_min:1,age_max:60,theme:'light',accent:'#F20D16',dismissible:true,product_link:true,names:[],cities:[],products:[],categories:[]};
    try{var r=JSON.parse(s.store_social_proof||'{}')||{};Object.keys(r).forEach(function(k){out[k]=r[k]})}catch(e){}
    ['names','cities','products','categories'].forEach(function(k){out[k]=Array.isArray(out[k])?out[k]:[]});
    return out;
};
W.socialProofPanel=function(s){
    var d=W.socialProofData(s);
    W.spData=d;
    var st=s.social_proof_stats||{};
    var combos=parseInt(st.combinations)||0;
    var opt=function(value,label,current){return '<option value="'+W.esc(value)+'"'+(String(current)===String(value)?' selected':'')+'>'+W.esc(label)+'</option>'};
    var text=function(id,value,rows,placeholder){return '<textarea class="w-input" id="'+id+'" rows="'+(rows||5)+'" placeholder="'+W.esc(placeholder||'')+'" style="width:100%;font-family:inherit;resize:vertical">'+W.esc((value||[]).join('\n'))+'</textarea>'};
    var statusColor=st.minimum_met?'#0d7a58':'#b45309';
    var sourceLabel=st.source==='manual'?'lista editable':(st.source==='catalog'?'catálogo de productos':'lista de respaldo');
    var citySourceLabel=st.city_source==='shipping'?'módulo Envíos':(st.city_source==='manual'?'lista editable':'respaldo');
    return '<div class="w-card" id="store-social-proof-panel" style="margin-bottom:14px">'
        +'<div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap"><div><h3 style="margin:0">Prueba social flotante</h3><p style="font-size:12px;color:var(--w-muted);margin:5px 0 0">Una etiqueta discreta que aparece desde abajo, muestra una compra reciente y se desvanece. Usa el catálogo real y nombres de ejemplo editables.</p></div><span style="font-size:10px;font-weight:800;padding:4px 8px;border-radius:999px;background:#fff3cd;color:#8a6d00">Experimental</span></div>'
        +'<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:14px">'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Activo</label><select class="w-input" id="ss-sp-enabled" style="width:100%">'+opt('1','Sí',d.enabled?'1':'0')+opt('0','No',d.enabled?'1':'0')+'</select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Posición</label><select class="w-input" id="ss-sp-position" style="width:100%">'+opt('bottom-left','Abajo izquierda',d.position)+opt('bottom-right','Abajo derecha',d.position)+opt('bottom-center','Abajo centro',d.position)+'</select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Tema</label><select class="w-input" id="ss-sp-theme" style="width:100%">'+opt('light','Claro',d.theme)+opt('dark','Oscuro',d.theme)+'</select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Color de acento</label><input class="w-input" type="color" id="ss-sp-accent" value="'+W.esc(d.accent)+'" style="width:100%;height:38px;padding:3px"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Entrada</label><select class="w-input" id="ss-sp-in" style="width:100%">'+opt('slide-up','Subir desde abajo',d.animation_in)+opt('fade','Desvanecer',d.animation_in)+opt('scale','Escalar',d.animation_in)+'</select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Salida</label><select class="w-input" id="ss-sp-out" style="width:100%">'+opt('fade','Desvanecer',d.animation_out)+opt('slide-down','Bajar',d.animation_out)+opt('scale','Escalar',d.animation_out)+'</select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Cada (segundos)</label><div style="display:flex;gap:6px"><input class="w-input" type="number" min="300" id="ss-sp-min" value="'+(parseInt(d.interval_min)||300)+'" style="width:50%"/><input class="w-input" type="number" min="300" id="ss-sp-max" value="'+(parseInt(d.interval_max)||300)+'" style="width:50%"/></div></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Visible (segundos)</label><input class="w-input" type="number" min="3" max="60" id="ss-sp-visible" value="'+(parseInt(d.visible_seconds)||8)+'" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Primer aviso (segundos)</label><input class="w-input" type="number" min="5" max="120" id="ss-sp-initial" value="'+(parseInt(d.initial_delay)||12)+'" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Antigüedad (segundos)</label><div style="display:flex;gap:6px"><input class="w-input" type="number" min="1" max="60" id="ss-sp-age-min" value="'+(parseInt(d.age_min)||1)+'" style="width:50%"/><input class="w-input" type="number" min="1" max="60" id="ss-sp-age-max" value="'+(parseInt(d.age_max)||60)+'" style="width:50%"/></div></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Enlace al producto</label><select class="w-input" id="ss-sp-link" style="width:100%">'+opt('1','Sí',d.product_link?'1':'0')+opt('0','No',d.product_link?'1':'0')+'</select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Botón cerrar</label><select class="w-input" id="ss-sp-dismiss" style="width:100%">'+opt('1','Sí',d.dismissible?'1':'0')+opt('0','No',d.dismissible?'1':'0')+'</select></div>'
        +'</div>'
        +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px">'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Nombres (uno por línea)</label>'+text('ss-sp-names',d.names,5,'María Fernanda Gutiérrez')+'</div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Ciudades y municipios (vacío = todos los del módulo Envíos)</label>'+text('ss-sp-cities',d.cities,5,'Vacío = todos los municipios del módulo Envíos')+'<p style="font-size:10.5px;color:var(--w-muted);margin:4px 0 0">Si lo dejas vacío se usan todos los municipios de Colombia cargados en Envíos.</p></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Productos (vacío = catálogo completo)</label>'+text('ss-sp-products',d.products,5,'Nombre del producto | Categoría | /producto/slug')+'<p style="font-size:10.5px;color:var(--w-muted);margin:4px 0 0">Si lo dejas vacío se usan los productos reales del catálogo. Para editarlos usa una línea por producto: nombre | categoría | URL.</p></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Categorías permitidas (vacío = todas)</label>'+text('ss-sp-categories',d.categories,5,'Guitarras y amplificadores')+'<p style="font-size:10.5px;color:var(--w-muted);margin:4px 0 0">Filtra los productos del catálogo por categoría. Una categoría por línea.</p></div>'
        +'</div>'
        +'<div style="margin-top:12px;padding:10px 12px;border:1px solid var(--w-border);border-radius:9px;background:#fbfcff"><div style="display:flex;gap:16px;flex-wrap:wrap;font-size:11.5px;color:#334155"><span><b style="color:'+statusColor+'">'+W.num(combos)+'</b> combinaciones</span><span>'+W.num(st.names||d.names.length)+' nombres</span><span>'+W.num(st.products||0)+' productos ('+W.esc(sourceLabel)+')</span><span>'+W.num(st.cities||d.cities.length)+' ciudades ('+W.esc(citySourceLabel)+')</span></div><div style="font-size:10.5px;color:'+statusColor+';margin-top:5px">'+(st.minimum_met?'Cumple el mínimo de 500 combinaciones.':'Ajusta nombres, productos o categorías para superar 500 combinaciones.')+'</div></div>'
        +'<div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center"><button type="button" class="w-btn w-btn-secondary" onclick="wontia.socialProofPreview()">Probar banner guardado</button><button type="button" class="w-btn w-btn-primary" onclick="wontia.storeSettingsSave()">Guardar configuración</button><span style="font-size:10.5px;color:var(--w-muted)">El primer aviso aparece a los '+(parseInt(d.initial_delay)||12)+' s; después respeta el intervalo mínimo de 5 minutos.</span></div>'
        +'<div id="ss-sp-preview" style="margin-top:10px;font-size:12px;color:var(--w-muted)"></div>'
        +'</div>';
};
W.socialProofPayload=function(){
    var v=function(id){var el=document.getElementById(id);return el?el.value:''};
    var lines=function(id){return v(id).split(/\r?\n/).map(function(x){return x.trim()}).filter(Boolean)};
    return {enabled:v('#ss-sp-enabled')==='1',position:v('#ss-sp-position'),animation_in:v('#ss-sp-in'),animation_out:v('#ss-sp-out'),interval_min:parseInt(v('#ss-sp-min'))||300,interval_max:parseInt(v('#ss-sp-max'))||300,initial_delay:parseInt(v('#ss-sp-initial'))||12,visible_seconds:parseInt(v('#ss-sp-visible'))||8,age_min:parseInt(v('#ss-sp-age-min'))||1,age_max:parseInt(v('#ss-sp-age-max'))||60,theme:v('#ss-sp-theme'),accent:v('#ss-sp-accent'),dismissible:v('#ss-sp-dismiss')==='1',product_link:v('#ss-sp-link')==='1',names:lines('#ss-sp-names'),cities:lines('#ss-sp-cities'),products:lines('#ss-sp-products'),categories:lines('#ss-sp-categories')};
};
W.socialProofPreview=async function(){
    var box=document.getElementById('ss-sp-preview');
    if(!box)return;
    box.innerHTML='<span style="color:#64748b">Consultando una combinación...</span>';
    var r=await W.api('/api/v1/public/store/social-proof?preview=1');
    var d=(r&&r.data)||null;
    if(!r||!r.ok||!d){box.innerHTML='<span style="color:#b42318">No se pudo generar la vista previa. Guarda y activa el banner primero.</span>';return}
    box.innerHTML='<div style="display:inline-flex;gap:8px;align-items:flex-start;background:#fff;border:1px solid #e7e7e7;border-radius:12px;padding:10px 12px;box-shadow:0 6px 18px rgba(16,29,58,.08);max-width:420px"><span style="width:8px;height:8px;border-radius:99px;background:'+W.esc(d.accent||'#F20D16')+';margin-top:5px"></span><div><div style="font-size:9px;letter-spacing:.12em;font-weight:800;color:#7a8494">COMPRA RECIENTE</div><div style="font-size:12.5px;color:#171717;line-height:1.35"><b>'+W.esc(d.name)+'</b> de '+W.esc(d.city)+' ha comprado: <b>'+W.esc(d.product)+'</b></div><div style="font-size:10.5px;color:#8791a0;margin-top:3px">hace '+W.num(d.age_seconds)+' segundos</div></div></div>';
};

W.impactNoteData=function(s){
    var out={enabled:true,label:'Impacto SONIKA',interval:9,phrases:[]};
    try{var r=JSON.parse(s.store_impact_note||'{}')||{};Object.keys(r).forEach(function(k){out[k]=r[k]})}catch(e){}
    out.phrases=Array.isArray(out.phrases)?out.phrases:[];
    return out;
};
W.impactNotePanel=function(s){
    var d=W.impactNoteData(s);W.impactData=d;
    return '<div class="w-card" id="store-impact-note-panel" style="margin-bottom:14px">'
        +'<div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap"><div><h3 style="margin:0">Impacto SONIKA en ficha y carrito</h3><p style="font-size:12px;color:var(--w-muted);margin:5px 0 0">Aviso informativo de la donación del 10% de utilidades. Rota automáticamente entre las frases que definas, sin interrumpir la compra.</p></div><span style="font-size:10px;font-weight:800;padding:4px 8px;border-radius:999px;background:#fff1f2;color:#9a4a51">Impacto</span></div>'
        +'<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-top:14px">'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Activo</label><select class="w-input" id="ss-impact-enabled" style="width:100%"><option value="1"'+(d.enabled?' selected':'')+'>Sí</option><option value="0"'+(d.enabled?'':' selected')+'>No</option></select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Etiqueta</label><input class="w-input" id="ss-impact-label" value="'+W.esc(d.label||'Impacto SONIKA')+'" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Rotar cada (segundos)</label><input class="w-input" type="number" min="4" max="60" id="ss-impact-interval" value="'+(parseInt(d.interval)||9)+'" style="width:100%"/></div>'
        +'</div>'
        +'<div style="margin-top:12px"><label style="font-size:11px;color:var(--w-muted)">Frases (una por línea, máximo 8)</label><textarea class="w-input" id="ss-impact-phrases" rows="7" placeholder="Cada vez que compras donaremos el 10%..." style="width:100%;font-family:inherit;resize:vertical">'+W.esc((d.phrases||[]).join('\n'))+'</textarea><p style="font-size:10.5px;color:var(--w-muted);margin:4px 0 0">La primera frase se muestra al cargar; las demás rotan automáticamente. El aviso aparece en la ficha de producto y en el carrito.</p></div>'
        +'<div style="margin-top:10px;padding:10px 12px;border:1px solid #f1dcdf;border-left:2px solid #f3c4c7;border-radius:9px;background:#fcfcfc;color:#66717f;font-size:11.5px;line-height:1.45;max-width:620px"><div style="font-size:8.5px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#9a4a51;margin-bottom:3px">'+W.esc(d.label||'Impacto SONIKA')+'</div><div id="ss-impact-preview">'+W.esc((d.phrases||[])[0]||'Escribe una frase para verla aquí.')+'</div></div>'
        +'<div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center"><button type="button" class="w-btn w-btn-secondary" onclick="wontia.impactNotePreview()">Probar rotación</button><button type="button" class="w-btn w-btn-primary" onclick="wontia.storeSettingsSave()">Guardar configuración</button><span style="font-size:10.5px;color:var(--w-muted)">También puedes desactivarlo cuando quieras.</span></div>'
        +'</div>';
};
W.impactNotePayload=function(){
    var v=function(id){var el=document.getElementById(id);return el?el.value:''};
    var phrases=v('#ss-impact-phrases').split(/\r?\n/).map(function(x){return x.trim()}).filter(Boolean);
    return {enabled:v('#ss-impact-enabled')==='1',label:v('#ss-impact-label'),interval:parseInt(v('#ss-impact-interval'))||9,phrases:phrases};
};
W.impactNotePreview=function(){
    var box=document.getElementById('ss-impact-preview');
    if(!box)return;
    var d=W.impactNotePayload(),phrases=(d.phrases||[]);
    if(!phrases.length){box.textContent='Escribe al menos una frase.';return}
    var i=0;box.textContent=phrases[0];
    if(W._impactPrevTimer)clearInterval(W._impactPrevTimer);
    W._impactPrevTimer=setInterval(function(){i=(i+1)%phrases.length;box.textContent=phrases[i]},2000);
};

W.storeSettings=async function(){
    var el=document.getElementById('store-content');
    var r=await W.api('/api/v1/admin/store/settings');
    var cb=await W.api('/api/v1/admin/store/catalog-brands');
    var cc=await W.api('/api/v1/admin/store/catalog-categories');
    var lm=await W.api('/api/v1/admin/store/payment-logo-methods');
    var s=r.data||{},methods=r.methods||[];
    var lmData=(lm&&lm.ok&&lm.data)||{};
    if(Array.isArray(lmData.methods)&&lmData.methods.length)W.storeLogoMethods=lmData.methods.map(function(x){return [String(x.id),String(x.label||x.id)]});
    W.storeLogoCustom=Array.isArray(lmData.custom)?lmData.custom:[];
    W.catalogBrands=(cb&&cb.data)||[];
    W.salesCategories=(cc&&cc.data)||[];
    W.salesSelected=[];try{W.salesSelected=JSON.parse(s.store_show_sales_categories||'[]')||[]}catch(e){}
    W.salesSelected=Array.isArray(W.salesSelected)?W.salesSelected:[];
    W.catalogSelected=[];try{W.catalogSelected=JSON.parse(s.store_catalog_brands||'[]')||[]}catch(e){}
    W.catalogSelected=Array.isArray(W.catalogSelected)?W.catalogSelected.slice(0,10):[];
    var logos={};try{logos=JSON.parse(s.store_payment_logos||'{}')||{}}catch(e){}
    var pm=['wompi','cod','manual'];
    var body='<div class="w-card"><h3 style="margin-top:0">Tienda</h3>'
        +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Tienda habilitada</label><select class="w-input" id="ss-enabled" style="width:100%"><option value="1"'+((s.store_enabled==='1'||s.store_enabled==='')?' selected':'')+'>Sí</option><option value="0"'+(s.store_enabled==='0'?' selected':'')+'>No</option></select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Moneda</label><select class="w-input" id="ss-currency" style="width:100%"><option value="COP"'+(s.store_currency==='COP'?' selected':'')+'>COP</option><option value="USD"'+(s.store_currency==='USD'?' selected':'')+'>USD</option></select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">WhatsApp de la tienda</label><input class="w-input" id="ss-whatsapp" value="'+W.esc(s.store_whatsapp||'')+'" placeholder="573001234567" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Email de notificaciones</label><input class="w-input" id="ss-notify" value="'+W.esc(s.store_notify_email||'')+'" placeholder="ventas@tutienda.com" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Pedido mínimo (centavos)</label><input class="w-input" type="number" id="ss-min" value="'+(parseInt(s.store_min_order_cents)||0)+'" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Máx. pedidos por IP/hora</label><input class="w-input" type="number" id="ss-max-ip" value="'+(parseInt(s.store_max_orders_hour_ip)||8)+'" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Máx. pedidos por correo/hora</label><input class="w-input" type="number" id="ss-max-email" value="'+(parseInt(s.store_max_orders_hour_email)||4)+'" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">URL de términos y condiciones</label><input class="w-input" id="ss-terms" value="'+W.esc(s.store_terms_url||'')+'" style="width:100%"/></div>'
        +'</div>'
        +'<div style="margin-top:12px"><label style="font-size:11px;color:var(--w-muted)">Nota de envío</label><input class="w-input" id="ss-shipnote" value="'+W.esc(s.store_shipping_note||'')+'" style="width:100%"/></div>'
        +'</div>'
        +'<div class="w-card" style="margin-top:14px"><h3 style="margin-top:0">Métodos de pago</h3><div style="display:flex;gap:18px;flex-wrap:wrap">'
        +pm.map(function(m){var label={wompi:'Wompi (en línea)',cod:'Contra entrega',manual:'Pago asistido (WhatsApp)'}[m];return '<label style="font-size:13px;display:flex;gap:8px;align-items:center"><input type="checkbox" class="ss-pm" value="'+m+'"'+(methods.indexOf(m)>-1?' checked':'')+'/> '+label+'</label>'}).join('')
        +'</div></div>'
        +'<div class="w-card" style="margin-top:14px"><h3 style="margin-top:0">Wompi</h3><div style="font-size:12px;color:var(--w-muted);margin-bottom:12px">Llaves del comercio (cada tienda usa sus propias llaves).</div>'
        +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Public key</label><input class="w-input" id="ss-wompi-pub" value="'+W.esc(s.store_wompi_public_key||'')+'" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Integrity key '+(s.store_wompi_integrity_key_set?'(guardada)':'(sin configurar)')+'</label><input class="w-input" type="password" id="ss-wompi-int" value="" placeholder="••••" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Events key '+(s.store_wompi_events_key_set?'(guardada)':'(sin configurar)')+'</label><input class="w-input" type="password" id="ss-wompi-evt" value="" placeholder="••••" style="width:100%"/></div>'
        +'</div></div>'
        +'<div class="w-card" style="margin-top:14px"><h3 style="margin-top:0">Reseñas y fidelidad</h3><div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Publicar reseñas automáticamente</label><select class="w-input" id="ss-rev-auto" style="width:100%"><option value="1"'+((s.store_reviews_auto_approve==='1'||s.store_reviews_auto_approve==='')?' selected':'')+'>Sí</option><option value="0"'+(s.store_reviews_auto_approve==='0'?' selected':'')+'>No (moderar)</option></select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Exigir compra para reseñar</label><select class="w-input" id="ss-rev-buy" style="width:100%"><option value="0"'+(s.store_reviews_require_purchase==='1'?'':' selected')+'>No</option><option value="1"'+(s.store_reviews_require_purchase==='1'?' selected':'')+'>Sí</option></select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Programa de puntos activo</label><select class="w-input" id="ss-loy-on" style="width:100%"><option value="0"'+(s.store_loyalty_enabled==='1'?'':' selected')+'>No</option><option value="1"'+(s.store_loyalty_enabled==='1'?' selected':'')+'>Sí</option></select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Puntos por cada 1.000 (moneda)</label><input class="w-input" type="number" id="ss-loy-rate" value="'+(parseInt(s.store_loyalty_points_per_1000)||1)+'" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Valor de 1 punto (centavos)</label><input class="w-input" type="number" id="ss-loy-val" value="'+(parseInt(s.store_loyalty_redeem_cents)||100)+'" style="width:100%"/></div>'
        +'</div></div>'
        +'<div class="w-card" style="margin-top:14px"><h3 style="margin-top:0">Carrito</h3><div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Recuperar carritos abandonados</label><select class="w-input" id="ss-rec-on" style="width:100%"><option value="0"'+((s.store_recovery_enabled==='1')?'':' selected')+'>No</option><option value="1"'+((s.store_recovery_enabled==='1')?' selected':'')+'>Sí</option></select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Horas sin comprar antes de recordar</label><input class="w-input" type="number" id="ss-rec-h" value="'+(parseInt(s.store_recovery_hours)||24)+'" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Reserva de inventario (minutos)</label><input class="w-input" type="number" id="ss-res-min" value="'+(parseInt(s.store_reservation_minutes)||20)+'" style="width:100%"/></div>'
        +'</div></div>'
        +'<div class="w-card" style="margin-top:14px"><h3 style="margin-top:0">Monedas</h3><div style="font-size:12px;color:var(--w-muted);margin-bottom:8px">La moneda base se cobra; las demás son referenciales (conversión por tasa). JSON: [{"code":"USD","label":"USD","symbol":"US$","rate":0.00025}]</div><textarea class="w-input" id="ss-currencies" rows="3" style="width:100%;font-family:monospace">'+W.esc(s.store_currencies||'')+'</textarea></div>'
        +'<div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap"><button class="w-btn w-btn-primary" onclick="wontia.storeSettingsSave()">Guardar configuración</button>'
        +'<button class="w-btn w-btn-secondary" onclick="wontia.storeMaintenance()">Liberar stock de pedidos vencidos</button></div>';
    var logoOptions=(W.storeLogoMethods||[]).map(function(pair){return '<option value="'+W.esc(pair[0])+'">'+W.esc(pair[1])+'</option>'}).join('');
    var logosSection='<div class="w-card" id="store-payment-logos" style="margin-bottom:14px"><div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap"><div><h3 style="margin:0">Logos de métodos de pago</h3><p style="font-size:12px;color:var(--w-muted);margin:5px 0 0">Detecta los métodos instalados, permite crear métodos manuales ilimitados y relacionar cada logo con su método.</p></div><button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.storeLogoSave()">Guardar logos</button></div>'
        +'<div style="margin-top:14px;padding:12px;border:1px solid var(--w-border);border-radius:10px;background:#fbfcff">'
        +'<div style="display:grid;grid-template-columns:1.3fr 1fr auto;gap:8px;align-items:end">'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Método instalado</label><select class="w-input" id="ss-logo-method-select" onchange="wontia.storeLogoSelect()" style="width:100%"><option value="">Selecciona un método...</option>'+logoOptions+'</select></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Crear método manual</label><input class="w-input" id="ss-logo-method-label" placeholder="Nombre del método" style="width:100%"/></div>'
        +'<button type="button" class="w-btn w-btn-primary w-btn-sm" onclick="wontia.storeLogoAddMethod()">+ Crear método</button>'
        +'</div>'
        +'<div style="display:grid;grid-template-columns:1.3fr 1fr auto;gap:8px;align-items:end;margin-top:9px">'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Logo del método</label><input class="w-input" id="ss-logo-attach-url" placeholder="https://... o /assets/uploads/..." oninput="wontia.storeLogoAttachPreview()" style="width:100%"/></div>'
        +'<div><label style="font-size:11px;color:var(--w-muted)">Subir imagen</label><input type="file" id="ss-logo-attach-file" accept="image/*" style="font-size:10px;width:100%"/></div>'
        +'<button type="button" class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.storeLogoAttach()">Vincular logo</button>'
        +'</div>'
        +'<div style="display:flex;gap:10px;align-items:center;margin-top:9px"><span style="font-size:11px;color:var(--w-muted)" id="ss-logo-attach-name">Selecciona un método para vincular su logo</span><img id="ss-logo-attach-prev" alt="" style="max-height:34px;max-width:120px;display:none;object-fit:contain"/></div>'
        +'</div>'
        +'<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(245px,1fr));gap:10px;margin-top:12px">'+W.storeLogoMethods.map(function(pair){var isCustom=(W.storeLogoCustom||[]).some(function(x){return String(x.id)===String(pair[0])});return W.storeLogoCard(pair[0],pair[1],logos[pair[0]]||'',isCustom)}).join('')+'</div></div>';
    el.innerHTML=logosSection+W.catalogPanel(s)+W.recommendationPanel(s)+W.socialProofPanel(s)+W.impactNotePanel(s)+body;
    W.catalogRenderChips();
};

W.salesToggle=async function(on){
    var r=await W.api('/api/v1/admin/store/settings',{method:'PUT',body:{store_show_sales:on?1:0}});
    if(r&&r.ok)W.notify(on?'Etiqueta de vendidos visible':'Etiqueta de vendidos oculta','success');
    else W.notify('No se pudo cambiar la visibilidad','error');
};
W.storeSettingsSave=async function(){
    var v=function(s){return document.querySelector(s).value};
    var methods=[];document.querySelectorAll('.ss-pm').forEach(function(c){if(c.checked)methods.push(c.value)});
    var salesCats=[];document.querySelectorAll('.ss-sales-cat').forEach(function(c){if(c.checked)salesCats.push(c.value)});
    var logos={};W.storeLogoMethods.forEach(function(pair){var el=document.getElementById('ss-logo-'+pair[0]);var u=el?el.value.trim():'';if(u)logos[pair[0]]=u});
    var payload={store_enabled:v('#ss-enabled'),store_currency:v('#ss-currency'),store_whatsapp:v('#ss-whatsapp'),store_notify_email:v('#ss-notify'),store_payment_logos:JSON.stringify(logos),
        store_catalog_sort:v('#ss-cat-sort'),store_catalog_mode:v('#ss-cat-mode'),store_catalog_brands:JSON.stringify(W.catalogSelected||[]),
        store_catalog_hide_out_of_stock:(document.getElementById('ss-cat-hide')&&document.getElementById('ss-cat-hide').checked)?1:0,
        store_catalog_show_tags:(document.getElementById('ss-cat-tags')&&document.getElementById('ss-cat-tags').checked)?1:0,
        store_oos_disable_add:(document.getElementById('ss-cat-oos-add')&&document.getElementById('ss-cat-oos-add').checked)?1:0,
        store_show_sales:(document.getElementById('ss-sales-enabled')&&document.getElementById('ss-sales-enabled').checked)?1:0,
        store_show_sales_categories:JSON.stringify(salesCats),
        store_recommendations:JSON.stringify(W.recPayload()),store_social_proof:JSON.stringify(W.socialProofPayload()),store_impact_note:JSON.stringify(W.impactNotePayload()),
        store_min_order_cents:parseInt(v('#ss-min'))||0,store_terms_url:v('#ss-terms'),store_shipping_note:v('#ss-shipnote'),
        store_max_orders_hour_ip:parseInt(v('#ss-max-ip'))||8,store_max_orders_hour_email:parseInt(v('#ss-max-email'))||4,
        store_reviews_auto_approve:v('#ss-rev-auto'),store_reviews_require_purchase:v('#ss-rev-buy'),
        store_loyalty_enabled:v('#ss-loy-on'),store_loyalty_points_per_1000:parseInt(v('#ss-loy-rate'))||1,store_loyalty_redeem_cents:parseInt(v('#ss-loy-val'))||100,
        store_currencies:v('#ss-currencies'),
        store_recovery_enabled:v('#ss-rec-on'),store_recovery_hours:parseInt(v('#ss-rec-h'))||24,store_reservation_minutes:parseInt(v('#ss-res-min'))||20,
        store_payment_methods:methods,store_wompi_public_key:v('#ss-wompi-pub'),store_wompi_integrity_key:v('#ss-wompi-int'),store_wompi_events_key:v('#ss-wompi-evt')};
    var r=await W.api('/api/v1/admin/store/settings',{method:'PUT',body:payload});
    if(r.ok){W.notify('Configuración guardada','success');W.storeSettings()}
};

W.storeMaintenance=async function(){
    var r=await W.api('/api/v1/admin/store/maintenance',{method:'POST',body:{}});
    if(r.ok)W.notify('Pedidos vencidos liberados: '+((r.data&&r.data.released)||0),'success');
};

W.ocMoney=function(cents,cur){var n=(Number(cents||0)/100);return '$'+n.toLocaleString('es-CO')+(cur&&cur!=='COP'?' '+cur:'')};
W.ocFulfillLabel=function(s){return ({new:'Recibido',confirmed:'Confirmado',preparing:'En preparación',shipped:'Despachado',delivered:'Entregado',cancelled:'Cancelado'})[s]||s||''};
W.ocPayLabel=function(s){return ({pending:'Pago pendiente',paid:'Pagado',failed:'Pago rechazado',refunded:'Reembolsado'})[s]||s||''};
W.ocKpi=function(label,val,color){
    return '<div class="w-card" style="flex:1;min-width:150px;text-align:center;margin:0">'
      +'<div style="font-size:26px;font-weight:800;'+(color?'color:'+color:'')+'">'+W.num(val)+'</div>'
      +'<div style="font-size:11px;color:var(--w-muted);margin-top:4px">'+label+'</div></div>';
};
W.ocTable=function(rows){
    if(!rows||!rows.length)return '<div style="padding:18px;color:var(--w-muted);font-size:13px">Sin pedidos.</div>';
    var h='<div style="overflow:auto"><table class="w-table"><thead><tr>'
      +'<th>Pedido</th><th>Cliente</th><th>Total</th><th>Pago</th><th>Estado</th><th>Fecha</th><th></th></tr></thead><tbody>';
    rows.forEach(function(o){
        h+='<tr><td><b>'+W.esc(o.order_number||('#'+o.id))+'</b></td>'
          +'<td>'+W.esc(o.customer_name||'')+'</td>'
          +'<td>'+W.esc(W.ocMoney(o.total_cents,o.currency))+'</td>'
          +'<td>'+W.esc(W.ocPayLabel(o.payment_status))+'</td>'
          +'<td>'+W.esc(W.ocFulfillLabel(o.fulfillment_status))+'</td>'
          +'<td style="white-space:nowrap">'+W.esc(o.created_at||'')+'</td>'
          +'<td><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.ocVerPedido('+o.id+')">Ver</button></td></tr>';
    });
    return h+'</tbody></table></div>';
};
W.renderOrderCenter=async function(tab){
    tab=(tab||'resumen');
    var app=document.getElementById('wontia-app');
    app.innerHTML='<div class="w-card">'
      +'<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:14px">'
      +'<button class="w-btn '+(tab!=='pedidos'?'w-btn-primary':'w-btn-secondary')+'" onclick="location.hash=\'#order-center\'">Resumen</button>'
      +'<button class="w-btn '+(tab==='pedidos'?'w-btn-primary':'w-btn-secondary')+'" onclick="location.hash=\'#order-center/pedidos\'">Todos los pedidos</button>'
      +'<span style="margin-left:auto;font-size:11px;color:var(--w-muted)">Central de pedidos</span>'
      +'</div><div id="oc-body"><div style="text-align:center;padding:40px;color:var(--w-muted)">Cargando...</div></div></div>';
    if(tab==='pedidos')return W.ocPedidos();
    return W.ocResumen();
};
W.ocResumen=async function(){
    var d=await W.api('/api/v1/admin/orders-center/overview');
    var b=document.getElementById('oc-body'); if(!b)return;
    if(!d||!d.ok){b.innerHTML='<div class="w-empty-state"><h3>No disponible</h3><p>'+W.esc((d&&d.message)||'No se pudo cargar la Central de pedidos.')+'</p></div>';return}
    var s=d.data||{};
    var h='<div style="display:flex;gap:12px;flex-wrap:wrap">'
      +W.ocKpi('Pedidos activos',s.active)
      +W.ocKpi('Pendientes de acción',s.pending,'#b45309')
      +W.ocKpi('Despachados',s.shipped,'#0369a1')
      +W.ocKpi('Entregados',s.delivered,'#15803d')
      +W.ocKpi('Total de pedidos',s.total)
      +'</div>'
      +'<div class="w-card" style="margin-top:14px"><h3>Pedidos recientes</h3>'+W.ocTable(s.recent||[])+'</div>'
      +'<div style="margin-top:14px;font-size:12px;color:var(--w-muted)">Secciones <b>Mis tareas</b>, <b>Operación Miche</b>, <b>Evidencias</b>, <b>Incidencias</b> y <b>SLA</b> se habilitarán en las siguientes fases con funcionalidad verificada.</div>';
    b.innerHTML=h;
};
W.ocPedidos=async function(q,status){
    var params=new URLSearchParams();
    if(q)params.set('search',q);
    if(status)params.set('status',status);
    var d=await W.api('/api/v1/admin/orders-center/orders?'+params.toString());
    var b=document.getElementById('oc-body'); if(!b)return;
    var sts=(d&&d.ok&&d.data&&d.data.statuses)||['new','confirmed','preparing','shipped','delivered','cancelled'];
    var opts='<option value="">Todos los estados</option>';
    sts.forEach(function(s){opts+='<option value="'+W.esc(s)+'"'+(s===status?' selected':'')+'>'+W.esc(W.ocFulfillLabel(s))+'</option>'});
    b.innerHTML='<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">'
      +'<input id="oc-q" class="w-input" placeholder="Buscar por cliente, número o UUID..." style="max-width:340px" value="'+W.esc(q||'')+'"/>'
      +'<select id="oc-st" class="w-select" style="max-width:190px">'+opts+'</select>'
      +'<button class="w-btn w-btn-primary" onclick="wontia.ocPedidos(document.getElementById(\'oc-q\').value,document.getElementById(\'oc-st\').value)">Buscar</button></div>'
      +'<div id="oc-list"></div>';
    var list=document.getElementById('oc-list');
    var items=(d&&d.ok&&d.data&&d.data.items)||[];
    list.innerHTML=W.ocTable(items)+'<div style="font-size:11px;color:var(--w-muted);margin-top:8px">'+W.num((d&&d.data&&d.data.total)||0)+' pedido(s)</div>';
    var inp=document.getElementById('oc-q');
    if(inp)inp.addEventListener('keydown',function(e){if(e.key==='Enter')W.ocPedidos(inp.value,(document.getElementById('oc-st')||{}).value)});
};
W.ocVerPedido=async function(id){
    var d=await W.api('/api/v1/admin/orders-center/orders/'+id);
    if(!d||!d.ok){W.notify((d&&d.message)||'No disponible','error');return}
    var o=d.data||{};
    var h='<div style="font-size:13px;line-height:1.7;max-height:65vh;overflow:auto">'
      +'<div><b>Pedido:</b> '+W.esc(o.order_number||('#'+o.id))+'</div>'
      +'<div><b>Cliente:</b> '+W.esc(o.customer_name||'')+' — '+W.esc(o.customer_email||'')+' '+W.esc(o.customer_phone||'')+'</div>'
      +'<div><b>Estado:</b> '+W.esc(W.ocFulfillLabel(o.fulfillment_status))+' · '+W.esc(W.ocPayLabel(o.payment_status))+'</div>'
      +'<div style="margin:10px 0 6px"><b>Productos</b></div>';
    (o.items||[]).forEach(function(it){
        h+='<div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--w-border);padding:4px 0"><span>'+W.esc(it.name||'')+(it.variant_name?(' ('+W.esc(it.variant_name)+')'):'')+' x'+(it.qty||1)+'</span><span>'+W.ocMoney(it.subtotal_cents||0,o.currency)+'</span></div>';
    });
    h+='<div style="display:flex;justify-content:space-between;margin-top:8px;font-weight:800"><span>Total</span><span>'+W.ocMoney(o.total_cents||0,o.currency)+'</span></div></div>';
    W.modal('Pedido '+(o.order_number||('#'+o.id)),h,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cerrar</button>');
};

W.renderDirectory=async function(){
    var app=document.getElementById('wontia-app'); if(!app)return;
    app.innerHTML='<div style="padding:30px;text-align:center;color:var(--w-muted)">Cargando reseñas...</div>';
    var r=await W.api('/api/v1/admin/directory/reviews');
    var d=(r&&r.data)||{}; var pending=d.pending||[]; var reviews=d.reviews||[];
    var pr=await W.api('/api/v1/admin/directory/profiles');
    var pd=(pr&&pr.data)||{}; var pendingProfiles=pd.pending||[];
    var h='<div class="w-flex-between" style="flex-wrap:wrap;gap:8px;margin-bottom:12px"><div><div style="font-size:11px;color:var(--w-muted)">Directorio Sonika</div><h3 style="margin:0">Perfiles y reseñas</h3><div style="font-size:12px;color:var(--w-muted);margin-top:3px">Aprueba perfiles para publicarlos y verificar su identidad. Aprueba reseñas para publicarlas; el Top 10 se calcula con reseñas aprobadas.</div></div><button class="w-btn w-btn-secondary" onclick="W.renderDirectory()">Actualizar</button></div>';
    h+='<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:14px"><div class="w-card" style="padding:14px"><div style="font-size:11px;color:var(--w-muted)">Perfiles pendientes</div><div style="font:800 26px Sora,sans-serif">'+pendingProfiles.length+'</div></div><div class="w-card" style="padding:14px"><div style="font-size:11px;color:var(--w-muted)">Reseñas pendientes</div><div style="font:800 26px Sora,sans-serif">'+pending.length+'</div></div><div class="w-card" style="padding:14px"><div style="font-size:11px;color:var(--w-muted)">Regla</div><div style="font-size:12px;color:var(--w-muted);line-height:1.5">Aprobar perfil = activo, verificado y puntos. Aprobar reseña = visible y verificada.</div></div></div>';
    h+='<div style="margin-top:4px"><h3 style="margin:0 0 8px">Perfiles pendientes</h3>';
    if(!pendingProfiles.length){h+='<div style="font-size:12px;color:var(--w-muted);margin-bottom:14px">Sin perfiles pendientes.</div>';}
    else{
        h+='<div style="display:flex;flex-direction:column;gap:8px;margin-bottom:18px">';
        pendingProfiles.forEach(function(x){h+='<div class="w-card" style="padding:13px"><div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap"><div style="flex:1;min-width:220px"><div style="font-weight:800">'+W.esc(x.display_name)+' <span style="font-size:10px;color:var(--w-muted);font-weight:600">'+W.esc(x.status)+'</span></div><div style="font-size:12px;color:var(--w-muted);margin-top:2px">'+W.esc(x.category||'')+' · '+W.esc(x.city||'')+' · '+W.esc(x.owner_email||x.email||'')+'</div><div style="font-size:12px;line-height:1.55;margin-top:6px;color:#555">'+W.esc(String(x.bio||'').slice(0,260))+'</div></div><div class="w-flex w-gap-sm" style="flex-wrap:wrap"><button class="w-btn w-btn-primary w-btn-sm" onclick="W.dirProfileModerate('+x.id+',\'approved\')">Aprobar y publicar</button><button class="w-btn w-btn-danger w-btn-sm" onclick="W.dirProfileModerate('+x.id+',\'rejected\')">Rechazar</button></div></div></div>';});
        h+='</div>';
    }
    h+='</div>';
    h+='<div style="margin-top:12px"><h3 style="margin:0 0 8px">Reseñas pendientes</h3>';
    if(!pending.length){h+='<div class="w-empty-state"><h3>Sin reseñas pendientes</h3><p>Cuando un cliente envíe una reseña, aparecerá aquí.</p></div>';}
    else{
        h+='<div style="display:flex;flex-direction:column;gap:10px">';
        pending.forEach(function(x){
            h+='<div class="w-card" style="padding:14px"><div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap"><div style="flex:1;min-width:220px"><div style="font-weight:800">'+W.esc(x.profile_ref)+'</div><div style="font-size:12px;color:var(--w-muted);margin-top:2px">'+W.esc(x.reviewer_name)+' · '+W.esc(x.reviewer_email)+' · '+(x.service_date?W.esc(x.service_date):'sin fecha')+'</div><div style="color:#f5a623;margin-top:5px">'+'★'.repeat(Math.max(1,Math.min(5,parseInt(x.rating,10)||1)))+'</div><div style="font-size:13px;line-height:1.6;margin-top:7px;white-space:pre-line">'+W.esc(x.body||'')+'</div><div style="font-size:11px;color:var(--w-muted);margin-top:6px">Puntualidad '+W.esc(x.punctuality)+'/5 · Calidad '+W.esc(x.quality)+'/5 · Profesionalismo '+W.esc(x.professionalism)+'/5 · Comunicación '+W.esc(x.communication)+'/5</div></div><div class="w-flex w-gap-sm" style="flex-wrap:wrap"><button class="w-btn w-btn-primary w-btn-sm" onclick="W.dirModerate('+x.id+',\'approved\')">Aprobar y verificar</button><button class="w-btn w-btn-danger w-btn-sm" onclick="W.dirModerate('+x.id+',\'rejected\')">Rechazar</button></div></div></div>';
        });
        h+='</div>';
    }
    h+='<div style="margin-top:22px"><h3 style="margin:0 0 8px">Últimas reseñas</h3>';
    if(!reviews.length)h+='<div style="font-size:12px;color:var(--w-muted)">Sin reseñas registradas.</div>';
    else{
        h+='<div style="display:flex;flex-direction:column;gap:6px">';
        reviews.slice(0,30).forEach(function(x){var color=x.status==='approved'?'#0d7a58':(x.status==='rejected'?'#b42318':'#8a6d00');h+='<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;background:var(--w-card);border:1px solid var(--w-border);border-radius:9px;padding:9px 11px;font-size:12px"><b>'+W.esc(x.profile_ref)+'</b><span>'+W.esc(x.reviewer_name)+'</span><span style="color:#f5a623">'+'★'.repeat(Math.max(1,Math.min(5,parseInt(x.rating,10)||1)))+'</span><span style="color:'+color+';font-weight:800;text-transform:uppercase;font-size:10px">'+W.esc(x.status)+'</span><span style="flex:1"></span><span style="color:var(--w-muted)">'+W.esc(String(x.created_at||'').slice(0,16))+'</span></div>'});
        h+='</div>';
    }
    h+='</div>';
    app.innerHTML=h;
};
W.dirModerate=async function(id,status){
    var r=await W.api('/api/v1/admin/directory/reviews/'+id+'/moderate',{method:'POST',body:{status:status}});
    if(r&&r.ok){W.notify(status==='approved'?'Reseña aprobada y verificada':'Reseña rechazada','success');W.renderDirectory();}
    else W.notify((r&&r.message)||'No se pudo moderar','error');
};
W.dirProfileModerate=async function(id,status){
    var r=await W.api('/api/v1/admin/directory/profiles/'+id+'/moderate',{method:'POST',body:{status:status}});
    if(r&&r.ok){W.notify(status==='approved'?'Perfil publicado y verificado':'Perfil rechazado','success');W.renderDirectory();}
    else W.notify((r&&r.message)||'No se pudo moderar el perfil','error');
};
W.renderToques=async function(){
    var app=document.getElementById('wontia-app'); if(!app)return;
    app.innerHTML='<div style="padding:30px;text-align:center;color:var(--w-muted)">Cargando toques...</div>';
    var r=await W.api('/api/v1/admin/directory/toques'); var d=(r&&r.data)||{}; var list=d.toques||[]; var profiles=(d.profiles||[]).filter(function(p){return p.verified&&p.source==='directory'});
    var h='<div class="w-flex-between" style="flex-wrap:wrap;gap:8px;margin-bottom:12px"><div><div style="font-size:11px;color:var(--w-muted)">Directorio Sonika</div><h3 style="margin:0">Toques</h3><div style="font-size:12px;color:var(--w-muted);margin-top:3px">Oportunidades para músicos. Los toques curados solo se invitan a perfiles con rango Escenario o superior.</div></div><div class="w-flex w-gap-sm"><a class="w-btn w-btn-secondary" href="/directorio/toques" target="_blank">Ver público</a><button class="w-btn w-btn-secondary" onclick="W.renderToques()">Actualizar</button></div></div>';
    h+='<div class="w-card" style="padding:14px;margin-bottom:14px"><h3 style="margin:0 0 8px">Nuevo toque</h3><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px"><input class="w-input" id="tq-title" placeholder="Título del toque"/><input class="w-input" id="tq-city" placeholder="Ciudad"/><input class="w-input" id="tq-date" type="date"/><input class="w-input" id="tq-instrument" placeholder="Instrumento"/><input class="w-input" id="tq-genre" placeholder="Género"/><input class="w-input" id="tq-budget" placeholder="Presupuesto (opcional)"/><select class="w-select" id="tq-type"><option value="open">Toque abierto</option><option value="curated">Toque curado</option></select></div><textarea class="w-textarea" id="tq-desc" placeholder="Descripción del toque" style="margin-top:8px;min-height:70px"></textarea><div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px"><input class="w-input" id="tq-name" placeholder="Organizador" style="flex:1;min-width:160px"/><input class="w-input" id="tq-email" placeholder="Correo del organizador" style="flex:1;min-width:200px"/><button class="w-btn w-btn-primary" onclick="W.toqueCreate()">Publicar toque</button></div></div>';
    if(!list.length){h+='<div class="w-empty-state"><h3>Sin toques</h3><p>Crea el primero.</p></div>';}
    else{
        h+='<div style="display:flex;flex-direction:column;gap:8px">';
        list.forEach(function(x){
            var opts=profiles.map(function(p){return '<option value="'+p.id+'">'+W.esc(p.name)+' · '+W.esc(p.rank_key||'')+'</option>'}).join('');
            h+='<div class="w-card" style="padding:13px"><div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap"><div style="flex:1;min-width:220px"><div style="font-weight:800">'+W.esc(x.title)+' <span class="w-badge" style="font-size:9px">'+W.esc(String(x.status).toUpperCase())+'</span></div><div style="font-size:12px;color:var(--w-muted);margin-top:2px">'+W.esc(x.city||'')+' · '+(x.event_date?W.esc(x.event_date):'sin fecha')+' · '+W.esc(x.instrument||'')+' · '+W.esc(x.toque_type)+'</div><div style="font-size:12px;color:#555;margin-top:5px">'+W.esc(String(x.description||'').slice(0,220))+'</div></div><div class="w-flex w-gap-sm" style="flex-wrap:wrap;align-items:center"><select class="w-select" id="tq-prof-'+x.id+'" style="min-width:190px"><option value="">Invitar perfil...</option>'+opts+'</select><button class="w-btn w-btn-secondary w-btn-sm" onclick="W.toqueInvite('+x.id+')">Invitar</button>'+(x.status==='open'?'<button class="w-btn w-btn-danger w-btn-sm" onclick="W.toqueStatus('+x.id+',\'closed\')">Cerrar</button>':'')+'</div></div></div>';
        });
        h+='</div>';
    }
    app.innerHTML=h;
};
W.toqueCreate=async function(){
    var v=function(id){return (document.getElementById(id)||{}).value||''};
    var body={title:v('tq-title'),city:v('tq-city'),event_date:v('tq-date'),instrument:v('tq-instrument'),genre:v('tq-genre'),budget:v('tq-budget'),toque_type:v('tq-type'),description:v('tq-desc'),organizer_name:v('tq-name'),organizer_email:v('tq-email')};
    var r=await W.api('/api/v1/admin/directory/toques',{method:'POST',body:body});
    if(r&&r.ok){W.notify('Toque publicado','success');W.renderToques();}else W.notify((r&&r.message)||'No se pudo crear el toque','error');
};
W.toqueInvite=async function(id){
    var el=document.getElementById('tq-prof-'+id);var pid=el?el.value:'';
    if(!pid){W.notify('Selecciona un perfil','error');return;}
    var r=await W.api('/api/v1/admin/directory/toques/'+id+'/invite',{method:'POST',body:{profile_id:pid}});
    if(r&&r.ok)W.notify(r.message||'Invitación registrada','success');else W.notify((r&&r.message)||'No se pudo invitar','error');
};
W.toqueStatus=async function(id,status){
    var r=await W.api('/api/v1/admin/directory/toques/'+id+'/moderate',{method:'POST',body:{status:status}});
    if(r&&r.ok){W.notify('Toque actualizado','success');W.renderToques();}else W.notify((r&&r.message)||'No se pudo actualizar','error');
};
W.renderMonyas=async function(){
    var app=document.getElementById('wontia-app'); if(!app)return;
    app.innerHTML='<div style="padding:30px;text-align:center;color:var(--w-muted)">Cargando moñas...</div>';
    var r=await W.api('/api/v1/admin/directory/monyas'); var d=(r&&r.data)||{}; var list=d.monyas||[]; var profiles=(d.profiles||[]).filter(function(p){return p.source==='directory'});
    var leaders=profiles.filter(function(p){return ['referente','legado'].indexOf(String(p.rank_key))>=0});
    var invite=profiles.filter(function(p){return ['escenario','referente','legado'].indexOf(String(p.rank_key))>=0});
    var h='<div class="w-flex-between" style="flex-wrap:wrap;gap:8px;margin-bottom:12px"><div><div style="font-size:11px;color:var(--w-muted)">Directorio Sonika</div><h3 style="margin:0">Moñas</h3><div style="font-size:12px;color:var(--w-muted);margin-top:3px">Crear una moña requiere un líder Referente o Legado. Invitar requiere rango Escenario o superior.</div></div><div class="w-flex w-gap-sm"><a class="w-btn w-btn-secondary" href="/directorio/monas" target="_blank">Ver público</a><button class="w-btn w-btn-secondary" onclick="W.renderMonyas()">Actualizar</button></div></div>';
    h+='<div class="w-card" style="padding:14px;margin-bottom:14px"><h3 style="margin:0 0 8px">Nueva moña</h3>'+(leaders.length?'<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px"><input class="w-input" id="mn-name" placeholder="Nombre de la moña"/><input class="w-input" id="mn-city" placeholder="Ciudad"/><input class="w-input" id="mn-genre" placeholder="Género"/><select class="w-select" id="mn-leader"><option value="">Líder (Referente/Legado)</option>'+leaders.map(function(p){return '<option value="'+p.id+'">'+W.esc(p.name)+' · '+W.esc(p.rank_key)+'</option>'}).join('')+'</select></div><textarea class="w-textarea" id="mn-desc" placeholder="Descripción" style="margin-top:8px;min-height:65px"></textarea><button class="w-btn w-btn-primary" style="margin-top:8px" onclick="W.monyaCreate()">Crear moña</button>':'<div style="font-size:12px;color:var(--w-muted)">Todavía no hay perfiles con rango Referente o Legado. Primero deben acumular puntos y verificaciones.</div>')+'</div>';
    if(!list.length){h+='<div class="w-empty-state"><h3>Sin moñas</h3><p>Cuando exista una, aparecerá aquí.</p></div>';}
    else{
        h+='<div style="display:flex;flex-direction:column;gap:8px">';
        list.forEach(function(x){
            var opts=invite.map(function(p){return '<option value="'+p.id+'">'+W.esc(p.name)+' · '+W.esc(p.rank_key)+'</option>'}).join('');
            h+='<div class="w-card" style="padding:13px"><div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap"><div style="flex:1;min-width:220px"><div style="font-weight:800">'+W.esc(x.name)+' <span class="w-badge" style="font-size:9px">'+W.esc(String(x.status).toUpperCase())+'</span></div><div style="font-size:12px;color:var(--w-muted);margin-top:2px">'+W.esc(x.city||'')+' · '+W.esc(x.genre||'')+' · Líder: '+W.esc(x.leader_name||'—')+'</div><div style="font-size:12px;color:#555;margin-top:5px">'+W.esc(String(x.description||'').slice(0,220))+'</div></div><div class="w-flex w-gap-sm" style="flex-wrap:wrap;align-items:center"><select class="w-select" id="mn-prof-'+x.id+'" style="min-width:190px"><option value="">Invitar perfil...</option>'+opts+'</select><button class="w-btn w-btn-secondary w-btn-sm" onclick="W.monyaInvite('+x.id+')">Invitar</button></div></div></div>';
        });
        h+='</div>';
    }
    app.innerHTML=h;
};
W.monyaCreate=async function(){
    var v=function(id){return (document.getElementById(id)||{}).value||''};
    var body={name:v('mn-name'),city:v('mn-city'),genre:v('mn-genre'),leader_profile_id:v('mn-leader'),description:v('mn-desc')};
    var r=await W.api('/api/v1/admin/directory/monyas',{method:'POST',body:body});
    if(r&&r.ok){W.notify('Moña creada','success');W.renderMonyas();}else W.notify((r&&r.message)||'No se pudo crear la moña','error');
};
W.monyaInvite=async function(id){
    var el=document.getElementById('mn-prof-'+id);var pid=el?el.value:'';
    if(!pid){W.notify('Selecciona un perfil','error');return;}
    var r=await W.api('/api/v1/admin/directory/monyas/'+id+'/invite',{method:'POST',body:{profile_id:pid,role:'Músico'}});
    if(r&&r.ok)W.notify('Invitación registrada','success');else W.notify((r&&r.message)||'No se pudo invitar','error');
};
W.renderSistecredito=async function(){
    var app=document.getElementById('wontia-app'); if(!app)return;
    app.innerHTML='<div class="w-card"><div style="text-align:center;padding:40px;color:var(--w-muted)">Cargando Sistecrédito...</div></div>';
    var r=await W.api('/api/v1/admin/sistecredito/overview');
    if(!r||!r.ok||!r.data){app.innerHTML='<div class="w-empty-state"><h3>No disponible</h3><p>'+W.esc((r&&r.message)||'No se pudo cargar el panel de Sistecrédito.')+'</p></div>';return}
    var d=r.data||{},s=d.settings||{},st=d.stats||{},items=d.intents||[];
    var badge=s.configured?'<span style="font-size:10px;font-weight:800;padding:4px 8px;border-radius:999px;background:#ecfdf5;color:#047857">Configurado</span>':'<span style="font-size:10px;font-weight:800;padding:4px 8px;border-radius:999px;background:#fff7ed;color:#b45309">Sin configurar</span>';
    var env=String(s.environment||'staging');
    var flash=W._scFlash||null;W._scFlash=null;
    var connColor=s.configured?'#0d9f72':'#b45309';
    var connLabel=s.configured?'Sitio configurado':'Configuración pendiente';
    var h='<div class="w-card"><div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap"><div><div style="font-size:11px;color:var(--w-muted)">Pagos</div><h3 style="margin:0">Sistecrédito</h3><p style="font-size:12px;color:var(--w-muted);margin:5px 0 0">Panel administrativo de la integración. Se muestra en el checkout cuando el método está activo y las credenciales están completas.</p></div><div class="w-flex w-gap-sm">'+badge+'<span style="font-size:10px;font-weight:800;padding:4px 8px;border-radius:999px;background:#f1f5f9;color:#475569">'+W.esc(env)+'</span></div></div></div>';
    h+=(flash?'<div class="w-card" style="margin-top:12px;border-left:3px solid #0d9f72;background:#f0fdf4;color:#065f46"><b>'+W.esc(flash.text)+'</b> <span style="font-size:11px;color:#047857">'+W.esc(flash.at)+'</span></div>':'');
    h+='<div class="w-card" style="margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap"><span style="width:10px;height:10px;border-radius:99px;background:'+connColor+';box-shadow:0 0 0 4px rgba(100,116,139,.12)"></span><div><b style="font-size:12px">'+connLabel+'</b><div style="font-size:11px;color:var(--w-muted)">'+(s.configured?'Vendor ID, Store ID y Subscription Key completos. La verificación real se hará al habilitar el checkout.':'Completa Vendor ID, Store ID y Subscription Key para vincular el sitio.')+'</div></div>'+(s.last_saved_at?'<span style="margin-left:auto;font-size:10.5px;color:var(--w-muted)">Último guardado: '+W.esc(s.last_saved_at)+'</span>':'')+'</div>';
    h+='<div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px">'+W.ocKpi('Intenciones',st.intents||0)+W.ocKpi('Pendientes',st.pending||0,'#b45309')+W.ocKpi('Aprobadas',st.approved||0,'#0d7a58')+W.ocKpi('Fallidas / expiradas',st.failed||0,'#b42318')+W.ocKpi('Eventos',st.events||0)+'</div>';
    h+='<div class="w-card" style="margin-top:14px"><div class="w-flex-between" style="gap:10px;flex-wrap:wrap"><div><h3 style="margin:0">Configuración</h3><p style="font-size:12px;color:var(--w-muted);margin:5px 0 0">Credenciales enmascaradas y almacenadas cifradas. No se exponen al frontend.</p></div><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.renderSistecredito()">Actualizar</button></div>';
    h+='<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-top:14px">'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Habilitación</label><select class="w-input" id="sc-enabled" style="width:100%"><option value="0"'+(s.enabled==='1'?'':' selected')+'>Deshabilitado</option><option value="1"'+(s.enabled==='1'?' selected':'')+'>Habilitado en panel</option></select></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Ambiente</label><select class="w-input" id="sc-env" style="width:100%"><option value="staging"'+(env==='staging'?' selected':'')+'>Staging</option><option value="production"'+(env==='production'?' selected':'')+'>Producción</option></select></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">SCOrigen</label><input class="w-input" id="sc-origen" value="'+W.esc(s.sc_origen||'Staging')+'" style="width:100%"/></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">PaymentMethodId (fijo)</label><input class="w-input" value="2" disabled style="width:100%;background:#f8fafc;color:#333333;opacity:1;-webkit-text-fill-color:#333333"/></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Moneda (fija)</label><input class="w-input" value="COP" disabled style="width:100%;background:#f8fafc;color:#333333;opacity:1;-webkit-text-fill-color:#333333"/></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Vendor ID</label><input class="w-input" id="sc-vendor-id" value="'+W.esc(s.vendor_id||'')+'" style="width:100%" autocomplete="off"/></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Subscription Key '+(s.subscription_key_set?'(guardado)':'(sin configurar)')+'</label><input class="w-input" type="password" id="sc-subscription-key" placeholder="'+(s.subscription_key_set?'Nueva Subscription Key (opcional)':'Escribe la Subscription Key')+'" style="width:100%" autocomplete="new-password"/></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Store ID</label><input class="w-input" id="sc-store-id" value="'+W.esc(s.store_id||'')+'" style="width:100%"/></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">URL de retorno</label><input class="w-input" id="sc-redirect" value="'+W.esc(s.redirect_url||'')+'" placeholder="https://sonika.com.co/pedido/{uuid}" style="width:100%"/></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">URL de notificación</label><input class="w-input" id="sc-notify" value="'+W.esc(s.notification_url||'')+'" placeholder="https://sonika.com.co/api/..." style="width:100%"/></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Timeout (segundos)</label><input class="w-input" type="number" min="5" max="120" id="sc-timeout" value="'+(parseInt(s.timeout_seconds)||25)+'" style="width:100%"/></div>'
      +'</div><div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center"><button class="w-btn w-btn-primary" id="sc-save" onclick="wontia.sistecreditoSave()">Guardar configuración</button><span id="sc-save-status" role="status" aria-live="polite" style="font-size:11px;font-weight:700;color:var(--w-muted)"></span><span style="font-size:10.5px;color:var(--w-muted)">El checkout se habilita solo con el método activo y las credenciales completas.</span></div></div>';
    h+='<div class="w-card" style="margin-top:14px"><div class="w-flex-between" style="gap:10px;flex-wrap:wrap"><div><h3 style="margin:0">Intenciones de pago</h3><p style="font-size:12px;color:var(--w-muted);margin:5px 0 0">Trazabilidad de `_id`, redirect y verificación de estado.</p></div><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.renderSistecredito()">Recargar</button></div>';
    if(!items.length){h+='<div class="w-empty-state" style="margin-top:12px"><h3>Sin intenciones registradas</h3><p>Aquí aparecerán las intenciones cuando se habilite el checkout Sistecrédito.</p></div>';}
    else{
        h+='<div style="overflow:auto;margin-top:12px"><table class="w-table"><thead><tr><th>Pedido</th><th>_id</th><th>Monto</th><th>Estado</th><th>Verificado</th><th>Fecha</th><th></th></tr></thead><tbody>';
        items.forEach(function(x){h+='<tr><td><b>'+W.esc(x.order_number||('#'+x.order_id))+'</b></td><td style="font-family:monospace;font-size:11px">'+W.esc(x.provider_payment_id||'—')+'</td><td>'+W.ocMoney(x.amount_cents,x.currency)+'</td><td>'+W.esc(x.status||'')+'</td><td>'+W.esc(x.last_checked_at||'—')+'</td><td style="white-space:nowrap">'+W.esc(String(x.created_at||'').slice(0,16))+'</td><td><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.sistecreditoIntent('+x.id+')">Ver</button></td></tr>'});
        h+='</tbody></table></div>';
    }
    h+='</div>';
    app.innerHTML=h;
};
W.sistecreditoSave=async function(){
    var v=function(id){var el=document.getElementById(id);return el?el.value:''};
    var btn=document.getElementById('sc-save');
    var status=document.getElementById('sc-save-status');
    var say=function(text,color){if(status){status.textContent=text;status.style.color=color||'var(--w-muted)'}};
    if(btn){btn.disabled=true;btn.textContent='Guardando...'}
    say('Guardando configuración...','#64748b');
    var payload={enabled:v('#sc-enabled')==='1',environment:v('#sc-env'),sc_origen:v('#sc-origen'),vendor_id:v('#sc-vendor-id'),subscription_key:v('#sc-subscription-key'),store_id:v('#sc-store-id'),redirect_url:v('#sc-redirect'),notification_url:v('#sc-notify'),timeout_seconds:parseInt(v('#sc-timeout'))||25};
    try{
        var r=await W.api('/api/v1/admin/sistecredito/settings',{method:'PUT',body:payload});
        if(r&&r.ok){W._scFlash={text:'Configuración guardada correctamente',at:new Date().toLocaleString()};W.renderSistecredito();return}
        say((r&&r.message)||'No se pudo guardar la configuración.', '#b42318');
    }catch(e){say('No se pudo conectar con el servidor. Intenta de nuevo.', '#b42318');}
    if(btn){btn.disabled=false;btn.textContent='Guardar configuración'}
};
W.sistecreditoIntent=async function(id){
    var r=await W.api('/api/v1/admin/sistecredito/intents/'+id);
    if(!r||!r.ok||!r.data){W.notify((r&&r.message)||'No disponible','error');return}
    var x=r.data||{},h='<div style="font-size:12.5px;line-height:1.65;max-height:65vh;overflow:auto"><div><b>Pedido:</b> '+W.esc(x.order_number||('#'+x.order_id))+'</div><div><b>_id:</b> '+W.esc(x.provider_payment_id||'—')+'</div><div><b>Estado:</b> '+W.esc(x.status||'')+'</div><div><b>Monto:</b> '+W.ocMoney(x.amount_cents,x.currency)+'</div><div><b>Última consulta:</b> '+W.esc(x.last_checked_at||'—')+'</div><div style="margin-top:10px"><b>Eventos</b></div>';
    if(!(x.events||[]).length)h+='<p style="color:var(--w-muted)">Sin eventos.</p>';
    else (x.events||[]).forEach(function(e){h+='<div style="border-bottom:1px solid var(--w-border);padding:5px 0"><b>'+W.esc(e.verified_status||e.provider_status||'')+'</b> · '+W.esc(e.validation_result||'')+'<div style="font-size:11px;color:var(--w-muted)">'+W.esc(e.received_at||'')+'</div></div>'});
    h+='</div>';
    W.modal('Intención de pago',h,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cerrar</button>');
};

W._ciuTab='ciu';
W.renderTiaCommandCenter=async function(tab){
    tab=tab||'ciu';
    W._ciuTab=tab;
    W.sglLiveStop();
    var app=document.getElementById('wontia-app');
    var tabs=[['ciu','Centro de Información Universal'],['training','Training Room'],['learning','Aprendizaje'],['simulation','Sales Lab'],['profit','Rentabilidad'],['interacciones','Interacciones vs resultados'],['fuentes','Fuentes'],['sin-respuesta','Preguntas sin respuesta']];
    var bar='<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">'+tabs.map(function(t){return '<button class="w-btn '+(tab===t[0]?'w-btn-primary':'w-btn-secondary')+'" onclick="location.hash=\'#tia-command-center/'+t[0]+'\'">'+W.esc(t[1])+'</button>'}).join('')+'</div>';
    app.innerHTML='<div style="margin-bottom:18px"><div style="font-size:20px;font-weight:800;letter-spacing:-.01em">TIA Command Center</div><div style="font-size:12px;color:var(--w-muted);margin-top:4px">Centro de Información Universal (CIU) · conocimiento exacto de SONIKA</div></div>'+bar+'<div id="ciu-body"><div class="w-card" style="text-align:center;padding:30px;color:var(--w-muted)">Cargando CIU...</div></div>';
    W.ciuLoad(tab);
};
W.ciuLoad=async function(tab){
    tab=tab||W._ciuTab||'ciu';
    W._ciuTab=tab;
    var box=document.getElementById('ciu-body'); if(!box)return;
    if(tab==='training'){W.trainingLoad();return}
    if(tab==='learning'){W.tiaLearningLoad();return}
    if(tab==='simulation'){W.tiaSimulationLoad();return}
    if(tab==='profit'){W.tiaProfitLoad();return}
    if(tab==='interacciones'){W.tiaInteractionsLoad();return}
    if(tab==='sin-respuesta'){W.ciuQuestions();return}
    var r=await W.api('/api/v1/admin/ciu/overview');
    if(!r||!r.ok){box.innerHTML='<div class="w-empty-state"><h3>CIU no disponible</h3><p>'+W.esc((r&&r.message)||'No se pudo cargar el Centro de Información Universal.')+'</p></div>';return}
    var d=r, by=d.by_type||{}, run=d.last_run||{};
    var h='<div class="sgl-live__stats" style="margin-bottom:14px">'
        +W.sglLiveStat(d.documents||0,'Documentos activos')
        +W.sglLiveStat(by.product||0,'Productos')
        +W.sglLiveStat(by.category||0,'Categorías')
        +W.sglLiveStat((by.page||0)+(by.policy||0),'Páginas y políticas')
        +W.sglLiveStat((by.section||0)+(by.block||0),'Secciones')
        +W.sglLiveStat(d.stale||0,'Obsoletos',(d.stale||0)>0?'#fbbf24':'#34d399')
        +W.sglLiveStat(d.unanswered||0,'Sin respuesta','#fbbf24')
        +W.sglLiveStat(run.finished_at?String(run.finished_at).slice(0,19):'—','Última sync')
        +'</div>';
    h+='<div class="w-card" style="margin-bottom:14px"><div class="w-flex-between" style="gap:10px;flex-wrap:wrap"><div><h3 style="margin:0">Buscar en el CIU</h3><p style="font-size:12px;color:var(--w-muted);margin:5px 0 0">Prueba una pregunta como “¿Qué garantía tiene un acordeón Hohner?”.</p></div><button class="w-btn w-btn-primary" type="button" onclick="W.ciuSync(this)">Sincronizar CIU</button></div><div style="display:flex;gap:7px;flex-wrap:wrap;margin-top:12px"><input class="w-input" id="ciu-search" placeholder="Buscar producto, categoría, política o sección" style="flex:1;min-width:220px" onkeydown="if(event.key===\'Enter\')W.ciuSearch()"/><button class="w-btn w-btn-secondary" type="button" onclick="W.ciuSearch()">Buscar</button></div><div id="ciu-search-results" style="margin-top:12px"></div></div>';
    h+='<div class="w-card" style="margin-bottom:14px"><h3 style="margin:0 0 8px">Fuentes</h3><div style="overflow:auto"><table class="w-table"><thead><tr><th>Fuente</th><th>Documentos</th><th>Estado</th><th>Última sync</th></tr></thead><tbody>';
    (d.sources||[]).forEach(function(s){h+='<tr><td>'+W.esc(s.label||s.source_key)+'</td><td>'+W.num(s.doc_count||0)+'</td><td><span style="font-size:10px;color:'+(s.status==='ok'?'#34d399':(s.status==='empty'?'#fbbf24':'#fb7185'))+'">'+W.esc(s.status||'')+'</span></td><td style="font-size:10px">'+W.esc(s.last_sync_at||'—')+'</td></tr>'});
    h+='</tbody></table></div>'+(run.summary?'<div style="font-size:10px;color:var(--w-muted);margin-top:8px">'+W.esc(JSON.stringify(run.summary))+'</div>':'')+'</div>';
    h+='<div class="w-card"><h3 style="margin:0 0 8px">Documentos recientes</h3><p style="font-size:11px;color:var(--w-muted);margin:0 0 10px">El CIU se construye desde el catálogo y el CMS. No modifica productos ni precios.</p><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.ciuSearch(\'producto\')">Ver productos</button></div>';
    box.innerHTML=h;
};
W.ciuSync=async function(btn){
    if(btn){btn.disabled=true;btn.textContent='Sincronizando...'}
    var r=await W.api('/api/v1/admin/ciu/sync',{method:'POST'});
    if(btn){btn.disabled=false;btn.textContent='Sincronizar CIU'}
    if(r&&r.ok){W.notify('CIU sincronizado: '+(r.documents||0)+' documentos','success')}else W.notify((r&&r.message)||(r&&r.errors&&r.errors[0])||'No se pudo sincronizar','error');
    W.ciuLoad(W._ciuTab);
};
W.ciuSearch=async function(q){
    var input=document.getElementById('ciu-search');
    q=typeof q==='string'?q:((input&&input.value)||'');
    q=(q||'').trim();
    if(!q){W.notify('Escribe qué quieres buscar','error');return}
    var out=document.getElementById('ciu-search-results'); if(!out)return;
    out.innerHTML='<div style="font-size:12px;color:var(--w-muted)">Buscando...</div>';
    var r=await W.api('/api/v1/admin/ciu/search?q='+encodeURIComponent(q)+'&limit=8');
    var rows=(r&&r.data)||[];
    if(!rows.length){out.innerHTML='<div style="font-size:12px;color:var(--w-muted)">Sin coincidencias en el CIU.</div>';return}
    out.innerHTML=rows.map(function(x){return '<div style="border-bottom:1px solid var(--w-border);padding:8px 0"><div style="display:flex;gap:8px;justify-content:space-between;align-items:flex-start"><div><b style="font-size:12px">'+W.esc(x.title)+'</b> <span class="w-badge" style="font-size:9px">'+W.esc(x.type)+'</span><div style="font-size:11px;color:var(--w-muted);margin-top:3px">'+W.esc(x.snippet||'')+'</div></div><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.ciuDoc('+x.id+')">Ver</button></div></div>'}).join('');
};
W.ciuDoc=async function(id){
    var r=await W.api('/api/v1/admin/ciu/documents/'+id);
    if(!r||!r.ok){W.notify('No se pudo abrir el documento','error');return}
    var d=r.data||{};
    var title='<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><b>'+W.esc(d.title)+'</b><span class="w-badge">'+W.esc(d.type)+'</span></div><div style="font-size:11px;color:var(--w-muted);margin:5px 0 10px">'+W.esc(d.url||'')+'</div>';
    W.modal('CIU · documento', title+'<pre style="white-space:pre-wrap;max-height:55vh;overflow:auto;font-size:11px;line-height:1.6;background:var(--w-bg2);border:1px solid var(--w-border);border-radius:8px;padding:10px">'+W.esc(d.content||'')+'</pre>', '<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cerrar</button>'+(d.url?' <a class="w-btn w-btn-primary" href="'+W.esc(d.url)+'" target="_blank" rel="noopener">Abrir en el sitio</a>':''));
};
W.ciuQuestions=async function(){
    var box=document.getElementById('ciu-body'); if(!box)return;
    var r=await W.api('/api/v1/admin/ciu/questions?unmatched=1&limit=100');
    var rows=(r&&r.data)||[];
    var h='<div class="w-card"><div class="w-flex-between" style="gap:10px;flex-wrap:wrap"><div><h3 style="margin:0">Preguntas sin respuesta</h3><p style="font-size:12px;color:var(--w-muted);margin:5px 0 0">Sirven para detectar productos, políticas o secciones que faltan en el CIU.</p></div><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.ciuQuestions()">Actualizar</button></div>';
    if(!rows.length)h+='<div class="w-empty-state" style="padding:18px"><p>Sin preguntas pendientes.</p></div>';
    else{h+='<div style="overflow:auto;margin-top:12px"><table class="w-table"><thead><tr><th>Pregunta</th><th>Fecha</th></tr></thead><tbody>';rows.forEach(function(x){h+='<tr><td style="font-size:12px">'+W.esc(x.question)+'</td><td style="font-size:10px">'+W.esc(x.created_at||'')+'</td></tr>'});h+='</tbody></table></div>'}
    h+='</div>';box.innerHTML=h;
};
W._training=null;
W.attRows=function(rows){
    rows=rows||[];
    if(!rows.length)return '<div style="font-size:11px;color:var(--w-muted)">Sin escenarios para esa búsqueda.</div>';
    return rows.map(function(x){return '<div style="border-left:3px solid #7c3aed;padding:6px 9px;margin-bottom:6px"><div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap"><b style="font-size:11px">'+W.esc(x.question||'')+'</b><span style="font-size:9px;color:var(--w-muted)">'+W.esc(x.category||'')+' · P'+W.esc(String(x.priority||0))+'</span></div><div style="font-size:10px;color:var(--w-muted);margin-top:2px">Objetivo: '+W.esc(x.goal||'')+'</div><div style="font-size:10px;margin-top:3px">Pregunta: '+W.esc(x.ask||'-')+'</div><div style="font-size:10px">Siguiente: '+W.esc(x.next_step||'-')+' · Cierre: '+W.esc(x.close_type||'-')+'</div></div>'}).join('');
};
W.attentionSearch=async function(){
    var q=(document.getElementById('tr-att-q')||{}).value||'';
    var out=document.getElementById('tr-att-list');if(!out)return;
    out.innerHTML='<div style="font-size:11px;color:var(--w-muted)">Buscando...</div>';
    var r=await W.api('/api/v1/admin/ciu/attention?limit=50&q='+encodeURIComponent(q));
    out.innerHTML=(r&&r.ok)?W.attRows(r.data||[]):'<div style="font-size:11px;color:#fb7185">'+W.esc((r&&r.message)||'No se pudo buscar')+'</div>';
};
W.trainingLoad=async function(){
    var box=document.getElementById('ciu-body'); if(!box)return;
    var r=await W.api('/api/v1/admin/ciu/training');
    if(!r||!r.ok){box.innerHTML='<div class="w-empty-state"><h3>Training Room no disponible</h3><p>'+W.esc((r&&r.message)||'No se pudo cargar el comportamiento de TIA.')+'</p></div>';return}
    W._training=r;
    var roles=r.roles||[],intents=r.intents||[],sents=r.sentiments||[],rules=r.rules||[],cases=r.cases||[],attStats=r.attention_stats||{},attPreview=r.attention_preview||[];
    var h='<div class="w-card" style="margin-bottom:14px"><div class="w-flex-between" style="gap:10px;flex-wrap:wrap"><div><h3 style="margin:0">Training Room</h3><p style="font-size:12px;color:var(--w-muted);margin:5px 0 0">Define cómo piensa, siente y actúa TIA antes de responder. Los cambios se aplican al siguiente mensaje del chat.</p></div><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.trainingLoad()">Actualizar</button></div>'
        +'<div class="sgl-live__stats" style="margin-top:12px">'+W.sglLiveStat(roles.length,'Roles')+W.sglLiveStat(intents.length,'Intenciones')+W.sglLiveStat(sents.length,'Emociones')+W.sglLiveStat(roles.filter(function(x){return x.is_active}).length,'Roles activos')+W.sglLiveStat(rules.length,'Reglas activas')+W.sglLiveStat(cases.length,'Casos por revisar')+W.sglLiveStat(attStats.total||0,'Escenarios de atención')+'</div></div>';
    h+='<div class="w-card" style="margin-bottom:14px"><h3 style="margin:0 0 8px">Simulador de comportamiento</h3><p style="font-size:12px;color:var(--w-muted);margin:0 0 10px">Escribe un mensaje real de cliente. Verás intención, emoción, rol, confianza y si TIA debe derivar a humano.</p><div style="display:flex;gap:7px;flex-wrap:wrap"><input class="w-input" id="tr-sim-msg" placeholder="Ej.: estoy molesto, mi pedido no llegó y necesito una persona" style="flex:1;min-width:240px" onkeydown="if(event.key===\'Enter\')W.trainingSimulate()"/><button class="w-btn w-btn-primary" type="button" onclick="W.trainingSimulate()">Simular</button></div><div id="tr-sim-result" style="margin-top:12px"></div></div>';
    h+='<div class="w-card" style="margin-bottom:14px"><div class="w-flex-between" style="gap:8px;flex-wrap:wrap"><div><h3 style="margin:0 0 4px">Roles de TIA</h3><p style="font-size:11px;color:var(--w-muted);margin:0">Cada intención elige un rol; una emoción fuerte puede cambiar el rol a conciliador.</p></div><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.trainingNewRole()">+ Nuevo rol</button></div><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin-top:10px">';
    roles.forEach(function(x){h+='<div class="w-card" style="margin:0;padding:12px"><div style="display:flex;justify-content:space-between;gap:6px"><b>'+W.esc(x.name)+'</b><span class="w-badge" style="font-size:9px;background:'+(x.is_active?'rgba(52,211,153,.12)':'rgba(148,163,184,.12)')+';color:'+(x.is_active?'#34d399':'#94a3b8')+'">'+(x.is_active?'ACTIVO':'OFF')+'</span></div><div style="font-size:11px;color:var(--w-muted);margin-top:5px">'+W.esc(x.mission||'Sin misión')+'</div><div style="font-size:10px;color:var(--w-muted);margin-top:5px">Tono: '+W.esc(x.tone||'—')+'</div><button class="w-btn w-btn-secondary w-btn-sm" style="margin-top:8px" type="button" onclick="W.trainingEditRole(\''+W.esc(x.slug)+'\')">Editar rol</button></div>'});
    h+='</div></div>';
    h+='<div class="w-card" style="margin-bottom:14px"><div class="w-flex-between" style="gap:8px;flex-wrap:wrap"><div><h3 style="margin:0 0 4px">Intenciones y playbooks</h3><p style="font-size:11px;color:var(--w-muted);margin:0">La intención decide el rol, las reglas de respuesta y el nivel de derivación.</p></div><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.trainingNewIntent()">+ Nueva intención</button></div><div style="overflow:auto;margin-top:10px"><table class="w-table"><thead><tr><th>Intención</th><th>Rol</th><th>Derivación</th><th>Prioridad</th><th></th></tr></thead><tbody>';
    intents.forEach(function(x){h+='<tr><td><b style="font-size:11px">'+W.esc(x.name)+'</b><div style="font-size:9px;color:var(--w-muted)">'+W.esc(x.slug)+'</div></td><td style="font-size:11px">'+W.esc(x.role_slug)+'</td><td style="font-size:10px">'+W.esc(x.handoff)+'</td><td style="font-size:10px">'+W.num(x.priority||0)+'</td><td><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.trainingEditIntent(\''+W.esc(x.slug)+'\')">Editar</button></td></tr>'});
    h+='</tbody></table></div></div>';
    h+='<div class="w-card" style="margin-bottom:14px"><div class="w-flex-between" style="gap:8px;flex-wrap:wrap"><div><h3 style="margin:0 0 4px">Emociones y detección</h3><p style="font-size:11px;color:var(--w-muted);margin:0">Palabras, frases y ajuste de respuesta. Las emociones marcadas como escalables pueden forzar derivación.</p></div><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.trainingNewSentiment()">+ Nueva emoción</button></div><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin-top:10px">';
    sents.forEach(function(x){h+='<div class="w-card" style="margin:0;padding:12px;border-left:3px solid '+(x.color||'#94a3b8')+'"><div style="display:flex;justify-content:space-between;gap:6px"><b>'+W.esc(x.name)+'</b>'+(x.escalate?'<span class="w-badge" style="font-size:9px;background:#fb718522;color:#fb7185">ESCALA</span>':'')+'</div><div style="font-size:10px;color:var(--w-muted);margin-top:5px">'+W.esc((x.keywords||[]).slice(0,7).join(', '))+'</div><div style="font-size:11px;margin-top:5px">'+W.esc(x.adjustment||'')+'</div><button class="w-btn w-btn-secondary w-btn-sm" style="margin-top:8px" type="button" onclick="W.trainingEditSentiment(\''+W.esc(x.slug)+'\')">Editar emoción</button></div>'});
    h+='</div></div>';
    var objs=r.objections||[];
    h+='<div class="w-card" style="margin-bottom:14px"><div class="w-flex-between" style="gap:8px;flex-wrap:wrap"><div><h3 style="margin:0 0 4px">Objeciones y respuestas</h3><p style="font-size:11px;color:var(--w-muted);margin:0">Detecta la objeción real, responde con empatía y propone el siguiente paso.</p></div><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.trainingNewObjection()">+ Nueva objeción</button></div><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin-top:10px">';
    if(!objs.length)h+='<div style="font-size:11px;color:var(--w-muted)">Sin objeciones registradas.</div>';
    objs.forEach(function(x){h+='<div class="w-card" style="margin:0;padding:12px;border-left:3px solid '+(x.escalate?'#fb7185':'#fbbf24')+'"><div style="display:flex;justify-content:space-between;gap:6px"><b>'+W.esc(x.name)+'</b>'+(x.escalate?'<span class="w-badge" style="font-size:9px;background:#fb718522;color:#fb7185">ESCALA</span>':'')+'</div><div style="font-size:10px;color:var(--w-muted);margin-top:5px">'+W.esc((x.phrases||[]).slice(0,4).join(', '))+'</div><div style="font-size:11px;margin-top:5px">'+W.esc(x.empathy||'')+'</div><button class="w-btn w-btn-secondary w-btn-sm" style="margin-top:8px" type="button" onclick="W.trainingEditObjection(\''+W.esc(x.slug)+'\')">Editar objeción</button></div>'});
    h+='</div></div>';

    h+='<div class="w-card" style="margin-bottom:14px"><h3 style="margin:0 0 4px">Reglas activas que TIA consulta antes de responder</h3><p style="font-size:11px;color:var(--w-muted);margin:0 0 9px">Se guardan en tia_playbook_rules y se incluyen en el comportamiento; los casos reales pendientes se guardan en tia_learning_insights y requieren aprobación humana.</p>';
    if(!rules.length)h+='<div style="font-size:11px;color:var(--w-muted)">Sin reglas activas.</div>';
    rules.forEach(function(x){h+='<div style="display:flex;gap:8px;align-items:flex-start;padding:7px 0;border-bottom:1px solid var(--w-border)"><span style="font-size:9px;font-weight:800;color:#059669;background:#ecfdf5;border-radius:9px;padding:3px 6px">ACTIVA</span><span style="font-size:11px;line-height:1.5">'+W.esc(x.rule_text||'')+'</span></div>'});
    if(cases.length){h+='<div style="margin-top:14px"><h4 style="margin:0 0 4px">Casos reales pendientes</h4>';cases.forEach(function(x){var recs=(x.recommendations||[]).map(function(v){return v.training_rule||v.next_play||''}).filter(Boolean);h+='<div style="border-left:3px solid #f59e0b;padding:7px 9px;margin-top:7px"><b style="font-size:11px">'+W.esc(x.title)+'</b><div style="font-size:10px;color:var(--w-muted);margin-top:2px">'+W.esc(x.diagnosis||'')+'</div><div style="font-size:10px;margin-top:4px">'+W.esc(recs.join(' · '))+'</div><button class="w-btn w-btn-primary w-btn-sm" style="margin-top:6px" type="button" onclick="W.tiaApplyTrainingCase('+x.id+',this)">Aplicar como regla</button></div>'});h+='</div>'}
    h+='</div>';
    h+='<div class="w-card" style="margin-bottom:14px"><div class="w-flex-between" style="gap:8px;flex-wrap:wrap"><div><h3 style="margin:0 0 4px">Catálogo de atención</h3><p style="font-size:11px;color:var(--w-muted);margin:0">Preguntas, objeciones, emociones y planes de respuesta. TIA usa el escenario más cercano antes de responder.</p></div><span style="font-size:10px;font-weight:800;color:#7c3aed">'+(attStats.total||0)+' escenarios</span></div>';
    var cats=attStats.categories||{};h+='<div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:9px">'+Object.keys(cats).map(function(k){return '<span style="font-size:9.5px;padding:3px 7px;border:1px solid var(--w-border);border-radius:999px;color:var(--w-muted)">'+W.esc(k)+' '+cats[k]+'</span>'}).join('')+'</div>';
    h+='<div style="display:flex;gap:7px;margin-top:10px;flex-wrap:wrap"><input class="w-input" id="tr-att-q" placeholder="Buscar pregunta, objeción o categoría" style="flex:1;min-width:220px" onkeydown="if(event.key===\'Enter\')W.attentionSearch()"/><button class="w-btn w-btn-secondary" type="button" onclick="W.attentionSearch()">Buscar</button></div><div id="tr-att-list" style="margin-top:9px">'+W.attRows(attPreview)+'</div></div>';
    h+='<div class="w-card"><h3 style="margin:0 0 8px">Últimos cambios de entrenamiento</h3><div style="font-size:11px;color:var(--w-muted)">'+((r.events||[]).length?r.events.map(function(e){return W.esc(e.entity+' · '+e.action+' · '+(e.username||'')+' · '+(e.created_at||''))}).join('<br>'):'Sin cambios registrados.')+'</div></div>';
    box.innerHTML=h;
};
W.trainingSimulate=async function(){
    var msg=(document.getElementById('tr-sim-msg')||{}).value||'';
    var out=document.getElementById('tr-sim-result'); if(!out)return;
    if(!msg.trim()){W.notify('Escribe un mensaje para simular','error');return}
    out.innerHTML='<div style="font-size:12px;color:var(--w-muted)">Analizando...</div>';
    var r=await W.api('/api/v1/admin/ciu/training/simulate',{method:'POST',body:{message:msg,page:'/tienda'}});
    if(!r||!r.ok){out.innerHTML='<div style="font-size:12px;color:#fb7185">No se pudo simular.</div>';return}
    var s=r.sentiment||{},i=r.intent||{},role=r.role||{};
    out.innerHTML='<div class="w-card" style="margin:0;padding:12px;border-left:3px solid '+(s.color||'#94a3b8')+'"><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;font-size:11px"><div><b>Intención:</b> '+W.esc(i.name||'')+'</div><div><b>Emoción:</b> '+W.esc(s.name||'')+'</div><div><b>Rol:</b> '+W.esc(role.name||'')+'</div><div><b>Etapa:</b> '+W.esc(r.stage||'—')+'</div><div><b>Objeción:</b> '+W.esc((r.objection&&r.objection.name)||'—')+'</div><div><b>Siguiente:</b> '+W.esc(r.next_action||'—')+'</div><div><b>Confianza:</b> '+W.esc(String(r.confidence||0))+'%</div><div><b>Derivación:</b> '+W.esc(r.handoff||'none')+'</div><div><b>¿Aclarar?:</b> '+(r.clarify?'Sí':'No')+'</div></div><pre style="white-space:pre-wrap;font-size:11px;line-height:1.5;background:var(--w-bg2);border:1px solid var(--w-border);border-radius:8px;padding:9px;margin-top:9px">'+W.esc(r.directive||'')+'</pre></div>';
};
W.trainingEditRole=function(slug){
    var x=(W._training.roles||[]).filter(function(v){return v.slug===slug})[0]||{slug:slug};
    W.modal('Training Room · '+W.esc(x.name||'Rol'),'<div class="w-form-group"><label class="w-label">Slug</label><input class="w-input" id="tr-role-slug" value="'+W.esc(x.slug||'')+'" '+(x.slug?'readonly':'')+'/></div><div class="w-form-group"><label class="w-label">Nombre</label><input class="w-input" id="tr-role-name" value="'+W.esc(x.name||'')+'"/></div><div class="w-form-group"><label class="w-label">Misión</label><textarea class="w-input" id="tr-role-mission" rows="2">'+W.esc(x.mission||'')+'</textarea></div><div class="w-form-group"><label class="w-label">Tono</label><textarea class="w-input" id="tr-role-tone" rows="2">'+W.esc(x.tone||'')+'</textarea></div><div class="w-form-group"><label class="w-label">Restricciones</label><textarea class="w-input" id="tr-role-restrictions" rows="3">'+W.esc(x.restrictions||'')+'</textarea></div><div class="w-form-group"><label class="w-label">Prompt de comportamiento</label><textarea class="w-input" id="tr-role-prompt" rows="3">'+W.esc(x.system_prompt||'')+'</textarea></div><div class="w-form-group"><label class="w-label">Objetivo de negocio</label><textarea class="w-input" id="tr-role-business" rows="2">'+W.esc(x.business_goal||'')+'</textarea></div><div class="w-form-group"><label class="w-label">Responsabilidades</label><textarea class="w-input" id="tr-role-responsibilities" rows="4">'+W.esc(x.responsibilities||'')+'</textarea></div><div class="w-form-group"><label class="w-label">Flujo de conversación</label><textarea class="w-input" id="tr-role-flow" rows="4">'+W.esc(x.conversation_flow||'')+'</textarea></div><div class="w-form-group"><label class="w-label">Acciones permitidas</label><textarea class="w-input" id="tr-role-actions" rows="3">'+W.esc(x.allowed_actions||'')+'</textarea></div><div class="w-form-group"><label class="w-label">Reglas de escalamiento</label><textarea class="w-input" id="tr-role-escalation" rows="3">'+W.esc(x.escalation_rules||'')+'</textarea></div><div class="w-form-group"><label class="w-label">Disparadores de handoff</label><textarea class="w-input" id="tr-role-handoff" rows="2">'+W.esc(x.handoff_triggers||'')+'</textarea></div><div class="w-form-group"><label class="w-label">Fuentes de conocimiento</label><textarea class="w-input" id="tr-role-sources" rows="2">'+W.esc(x.knowledge_sources||'')+'</textarea></div><div class="w-form-group"><label class="w-label">Frases modelo</label><textarea class="w-input" id="tr-role-samples" rows="3">'+W.esc(x.sample_phrases||'')+'</textarea></div><div class="w-form-group"><label class="w-label">KPIs</label><textarea class="w-input" id="tr-role-kpis" rows="2">'+W.esc(x.kpis||'')+'</textarea></div><label style="font-size:11px"><input type="checkbox" id="tr-role-active" '+(x.is_active!==false?'checked':'')+'/> Activo</label>','<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancelar</button><button class="w-btn w-btn-primary" onclick="W.trainingSaveRole()">Guardar rol</button>');
};
W.trainingSaveRole=function(){
    var v=function(id){return (document.getElementById(id)||{}).value||''};
    W.api('/api/v1/admin/ciu/training/role',{method:'POST',body:{slug:v('tr-role-slug'),name:v('tr-role-name'),mission:v('tr-role-mission'),tone:v('tr-role-tone'),restrictions:v('tr-role-restrictions'),system_prompt:v('tr-role-prompt'),business_goal:v('tr-role-business'),responsibilities:v('tr-role-responsibilities'),conversation_flow:v('tr-role-flow'),allowed_actions:v('tr-role-actions'),escalation_rules:v('tr-role-escalation'),handoff_triggers:v('tr-role-handoff'),knowledge_sources:v('tr-role-sources'),sample_phrases:v('tr-role-samples'),kpis:v('tr-role-kpis'),is_active:!!(document.getElementById('tr-role-active')||{}).checked}}).then(function(r){if(r&&r.ok){W.closeModal();W.notify('Rol guardado','success');W.trainingLoad()}else W.notify((r&&r.message)||'No se pudo guardar','error')});
};
W.trainingNewRole=function(){W.trainingEditRole('')};
W.trainingEditIntent=function(slug){
    var x=(W._training.intents||[]).filter(function(v){return v.slug===slug})[0]||{slug:slug};
    var roles=(W._training.roles||[]).map(function(v){return '<option value="'+W.esc(v.slug)+'"'+(x.role_slug===v.slug?' selected':'')+'>'+W.esc(v.name)+'</option>'}).join('');
    W.modal('Training Room · '+W.esc(x.name||'Intención'),'<div class="w-form-group"><label class="w-label">Slug</label><input class="w-input" id="tr-intent-slug" value="'+W.esc(x.slug||'')+'" '+(x.slug?'readonly':'')+'/></div><div class="w-form-group"><label class="w-label">Nombre</label><input class="w-input" id="tr-intent-name" value="'+W.esc(x.name||'')+'"/></div><div class="w-form-group"><label class="w-label">Rol</label><select class="w-input" id="tr-intent-role">'+roles+'</select></div><div class="w-form-group"><label class="w-label">Ejemplos (una frase por línea)</label><textarea class="w-input" id="tr-intent-examples" rows="3">'+W.esc((x.examples||[]).join('\n'))+'</textarea></div><div class="w-form-group"><label class="w-label">Palabras clave (una por línea)</label><textarea class="w-input" id="tr-intent-keywords" rows="3">'+W.esc((x.keywords||[]).join('\n'))+'</textarea></div><div class="w-form-group"><label class="w-label">Playbook</label><textarea class="w-input" id="tr-intent-playbook" rows="3">'+W.esc(x.playbook||'')+'</textarea></div><div class="w-form-group"><label class="w-label">Derivación</label><select class="w-input" id="tr-intent-handoff"><option value="none"'+(x.handoff==='none'?' selected':'')+'>No derivar</option><option value="suggest"'+(x.handoff==='suggest'?' selected':'')+'>Sugerir humano</option><option value="required"'+(x.handoff==='required'?' selected':'')+'>Obligatorio humano</option></select></div><div class="w-form-group"><label class="w-label">Prioridad</label><input class="w-input" id="tr-intent-priority" type="number" value="'+(x.priority||0)+'"/></div><label style="font-size:11px"><input type="checkbox" id="tr-intent-active" '+(x.is_active!==false?'checked':'')+'/> Activa</label>','<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancelar</button><button class="w-btn w-btn-primary" onclick="W.trainingSaveIntent()">Guardar intención</button>');
};
W.trainingSaveIntent=function(){
    var v=function(id){return (document.getElementById(id)||{}).value||''};
    W.api('/api/v1/admin/ciu/training/intent',{method:'POST',body:{slug:v('tr-intent-slug'),name:v('tr-intent-name'),role_slug:v('tr-intent-role'),examples:v('tr-intent-examples'),keywords:v('tr-intent-keywords'),playbook:v('tr-intent-playbook'),handoff:v('tr-intent-handoff'),priority:parseInt(v('tr-intent-priority'))||0,is_active:!!(document.getElementById('tr-intent-active')||{}).checked}}).then(function(r){if(r&&r.ok){W.closeModal();W.notify('Intención guardada','success');W.trainingLoad()}else W.notify((r&&r.message)||'No se pudo guardar','error')});
};
W.trainingNewIntent=function(){W.trainingEditIntent('')};
W.trainingEditSentiment=function(slug){
    var x=(W._training.sentiments||[]).filter(function(v){return v.slug===slug})[0]||{slug:slug};
    W.modal('Training Room · '+W.esc(x.name||'Emoción'),'<div class="w-form-group"><label class="w-label">Slug</label><input class="w-input" id="tr-sent-slug" value="'+W.esc(x.slug||'')+'" '+(x.slug?'readonly':'')+'/></div><div class="w-form-group"><label class="w-label">Nombre</label><input class="w-input" id="tr-sent-name" value="'+W.esc(x.name||'')+'"/></div><div class="w-form-group"><label class="w-label">Palabras clave (una por línea)</label><textarea class="w-input" id="tr-sent-keywords" rows="3">'+W.esc((x.keywords||[]).join('\n'))+'</textarea></div><div class="w-form-group"><label class="w-label">Frases (una por línea)</label><textarea class="w-input" id="tr-sent-phrases" rows="2">'+W.esc((x.phrases||[]).join('\n'))+'</textarea></div><div class="w-form-group"><label class="w-label">Ajuste de respuesta</label><textarea class="w-input" id="tr-sent-adjustment" rows="3">'+W.esc(x.adjustment||'')+'</textarea></div><div class="w-form-group"><label class="w-label">Color</label><input class="w-input" id="tr-sent-color" value="'+W.esc(x.color||'#94a3b8')+'"/></div><label style="font-size:11px"><input type="checkbox" id="tr-sent-escalate" '+(x.escalate?'checked':'')+'/> Escalar a humano</label> <label style="font-size:11px;margin-left:10px"><input type="checkbox" id="tr-sent-active" '+(x.is_active!==false?'checked':'')+'/> Activa</label>','<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancelar</button><button class="w-btn w-btn-primary" onclick="W.trainingSaveSentiment()">Guardar emoción</button>');
};
W.trainingSaveSentiment=function(){
    var v=function(id){return (document.getElementById(id)||{}).value||''};
    W.api('/api/v1/admin/ciu/training/sentiment',{method:'POST',body:{slug:v('tr-sent-slug'),name:v('tr-sent-name'),keywords:v('tr-sent-keywords'),phrases:v('tr-sent-phrases'),adjustment:v('tr-sent-adjustment'),color:v('tr-sent-color'),escalate:!!(document.getElementById('tr-sent-escalate')||{}).checked,is_active:!!(document.getElementById('tr-sent-active')||{}).checked}}).then(function(r){if(r&&r.ok){W.closeModal();W.notify('Emoción guardada','success');W.trainingLoad()}else W.notify((r&&r.message)||'No se pudo guardar','error')});
};
W.trainingNewSentiment=function(){W.trainingEditSentiment('')};
W.trainingEditObjection=function(slug){
    var x=(W._training.objections||[]).filter(function(v){return v.slug===slug})[0]||{slug:slug};
    W.modal('Training Room · '+W.esc(x.name||'Objeción'),'<div class="w-form-group"><label class="w-label">Slug</label><input class="w-input" id="tr-obj-slug" value="'+W.esc(x.slug||'')+'" '+(x.slug?'readonly':'')+'/></div><div class="w-form-group"><label class="w-label">Nombre</label><input class="w-input" id="tr-obj-name" value="'+W.esc(x.name||'')+'"/></div><div class="w-form-group"><label class="w-label">Frases que la activan (una por línea)</label><textarea class="w-input" id="tr-obj-phrases" rows="3">'+W.esc((x.phrases||[]).join('\n'))+'</textarea></div><div class="w-form-group"><label class="w-label">Empatía</label><textarea class="w-input" id="tr-obj-empathy" rows="2">'+W.esc(x.empathy||'')+'</textarea></div><div class="w-form-group"><label class="w-label">Respuesta</label><textarea class="w-input" id="tr-obj-response" rows="3">'+W.esc(x.response||'')+'</textarea></div><div class="w-form-group"><label class="w-label">Siguiente paso</label><textarea class="w-input" id="tr-obj-next" rows="2">'+W.esc(x.next_step||'')+'</textarea></div><label style="font-size:11px"><input type="checkbox" id="tr-obj-escalate" '+(x.escalate?'checked':'')+'/> Escalar a humano</label> <label style="font-size:11px;margin-left:10px"><input type="checkbox" id="tr-obj-active" '+(x.is_active!==false?'checked':'')+'/> Activa</label>','<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancelar</button><button class="w-btn w-btn-primary" onclick="W.trainingSaveObjection()">Guardar objeción</button>');
};
W.trainingSaveObjection=function(){
    var v=function(id){return (document.getElementById(id)||{}).value||''};
    W.api('/api/v1/admin/ciu/training/objection',{method:'POST',body:{slug:v('tr-obj-slug'),name:v('tr-obj-name'),phrases:v('tr-obj-phrases'),empathy:v('tr-obj-empathy'),response:v('tr-obj-response'),next_step:v('tr-obj-next'),escalate:!!(document.getElementById('tr-obj-escalate')||{}).checked,is_active:!!(document.getElementById('tr-obj-active')||{}).checked}}).then(function(r){if(r&&r.ok){W.closeModal();W.notify('Objeción guardada','success');W.trainingLoad()}else W.notify((r&&r.message)||'No se pudo guardar','error')});
};
W.trainingNewObjection=function(){W.trainingEditObjection('')};
W._simRun=null;W._simRunning=false;
W.tiaSimulationLoad=async function(){
    var box=document.getElementById('ciu-body');if(!box)return;
    var r=await W.api('/api/v1/admin/ciu/simulation');
    if(!r||!r.ok){box.innerHTML='<div class="w-empty-state"><h3>Sales Lab no disponible</h3><p>'+W.esc((r&&r.message)||'No se pudo cargar.')+'</p></div>';return}
    var l=r.latest;var sc=l?parseFloat(l.score)||0:0;
    var h='<div class="w-card" style="margin-bottom:14px"><div class="w-flex-between" style="gap:10px;flex-wrap:wrap"><div><h3 style="margin:0">Sales Lab · 100 conversaciones</h3><p style="font-size:11px;color:var(--w-muted);margin:4px 0 0">Simulación interna con clientes sintéticos: agradables, difíciles, enojados y crisis. No afecta clientes reales.</p></div><div style="display:flex;gap:6px;flex-wrap:wrap"><button class="w-btn w-btn-primary w-btn-sm" id="sim-start" type="button" onclick="W.simStart(this)">Ejecutar 100 conversaciones</button><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.tiaSimulationLoad()">Actualizar</button></div></div>';
    if(l){h+='<div class="sgl-live__stats" style="margin-top:12px">'+W.sglLiveStat(sc.toFixed(1)+'%','Score global')+W.sglLiveStat(l.passed||0,'Aprobadas','#34d399')+W.sglLiveStat(l.failed||0,'Por revisar','#fb7185')+W.sglLiveStat(l.progress||0+'%','Progreso')+'</div><div style="height:6px;background:#eee;border-radius:99px;overflow:hidden;margin-top:10px"><div id="sim-progress" style="height:100%;width:'+(l.progress||0)+'%;background:#8b5cf6"></div></div>'}
    h+='<div style="margin-top:10px"><pre id="sim-terminal" style="max-height:250px;overflow:auto;background:#111827;color:#d1fae5;border-radius:8px;padding:10px;font:10.5px/1.45 ui-monospace,Menlo,monospace;white-space:pre-wrap">'+(l?(l.logs||[]).map(function(x){return '['+(x.t||'')+'] ['+(x.level||'info')+'] '+(x.message||'')}).join('\n'):'Sales Lab listo. Ejecuta las 100 conversaciones.')+'</pre></div></div>';
    h+='<div class="w-card" style="margin-bottom:14px"><div class="w-flex-between" style="gap:8px;flex-wrap:wrap"><div><h3 style="margin:0 0 4px">Prompt maestro</h3><p style="font-size:11px;color:var(--w-muted);margin:0">Aplícalo después de revisar la simulación para reforzar el comportamiento de TIA.</p></div><button class="w-btn '+(r.prompt_applied?'w-btn-secondary':'w-btn-primary')+' w-btn-sm" type="button" onclick="W.simApplyPrompt(this)">'+(r.prompt_applied?'Prompt aplicado · reaplicar':'Aplicar prompt maestro')+'</button></div></div>';
    if(l&&(l.results||[]).length){var fails=(l.results||[]).filter(function(x){return !x.passed});h+='<div class="w-card"><h3 style="margin:0 0 8px">Fallos que debe revisar</h3>'+(fails.length?fails.slice(0,20).map(function(x){return '<div style="font-size:11px;padding:6px 0;border-bottom:1px solid var(--w-border)"><b>'+W.esc(x.id)+' · '+W.esc(x.persona)+' · '+W.esc(x.mood)+'</b> · '+x.score+'%<div style="color:var(--w-muted);margin-top:2px">'+W.esc((x.checks||[]).join(' · '))+'</div></div>'}).join(''):'<div style="font-size:11px;color:#059669">Sin fallos en esta simulación.</div>')+'</div>'}
    box.innerHTML=h;if(l){W._simRun=l.id;W.simRender(l)}
};
W.simRender=function(run){
    if(!run)return;var pre=document.getElementById('sim-terminal');if(pre){pre.textContent=(run.logs||[]).map(function(x){return '['+(x.t||'')+'] ['+(x.level||'info')+'] '+(x.message||'')}).join('\n');pre.scrollTop=pre.scrollHeight}var bar=document.getElementById('sim-progress');if(bar)bar.style.width=(run.progress||0)+'%';
};
W.simStart=async function(btn){
    if(W._simRunning)return;W._simRunning=true;if(btn)btn.disabled=true;
    var r=await W.api('/api/v1/admin/ciu/simulation/run',{method:'POST',body:{}});
    if(!r||!r.ok){W._simRunning=false;if(btn)btn.disabled=false;W.notify((r&&r.message)||'No se pudo iniciar','error');return}
    W._simRun=r.run_id;W._simRunTotal=r.total_steps||100;W.simStep(btn);
};
W.simStep=async function(btn){
    if(!W._simRun)return;
    var r=await W.api('/api/v1/admin/ciu/simulation/run/'+W._simRun+'/step',{method:'POST',body:{}});
    if(!r||!r.ok){W._simRunning=false;if(btn)btn.disabled=false;W.notify((r&&r.message)||'La simulación se detuvo','error');return}
    W.simRender(r.run);
    if(r.run&&r.run.status==='running'){setTimeout(function(){W.simStep(btn)},180);return}
    W._simRunning=false;if(btn)btn.disabled=false;W.notify(r.run&&r.run.status==='completed'?'Simulación completada':'Simulación finalizada','success');
};
W.simApplyPrompt=async function(btn){
    if(btn)btn.disabled=true;var r=await W.api('/api/v1/admin/ciu/simulation/prompt',{method:'POST',body:{}});if(btn)btn.disabled=false;if(r&&r.ok){W.notify('Prompt maestro aplicado','success');W.tiaSimulationLoad()}else W.notify((r&&r.message)||'No se pudo aplicar','error');
};
W.tiaLearningLoad=async function(){
    var box=document.getElementById('ciu-body');if(!box)return;
    var r=await W.api('/api/v1/admin/ciu/learning');
    if(!r||!r.ok){box.innerHTML='<div class="w-empty-state"><h3>Learning Center no disponible</h3><p>'+W.esc((r&&r.message)||'No se pudo cargar.')+'</p></div>';return}
    var score=Math.max(0,Math.min(100,parseFloat(r.score)||0));
    var h='<div class="w-card" style="margin-bottom:14px"><div class="w-flex-between" style="gap:12px;flex-wrap:wrap"><div><h3 style="margin:0">Aprendizaje de TIA</h3><p style="font-size:11px;color:var(--w-muted);margin:4px 0 0">Qué sabe, qué le falta y qué debe entrenar. Medición interna 0–100.</p></div><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.tiaLearningLoad()">Actualizar</button><button class="w-btn w-btn-primary w-btn-sm" id="learning-start" type="button" onclick="W.learningStart(this)">Iniciar aprendizaje faltante</button></div><div style="margin-top:10px"><div style="height:6px;background:#eee;border-radius:99px;overflow:hidden"><div id="learning-progress" style="height:100%;width:0;background:#8b5cf6;transition:width .2s"></div></div><pre id="learning-terminal" style="margin-top:8px;max-height:220px;overflow:auto;background:#111827;color:#d1fae5;border-radius:8px;padding:10px;font:10.5px/1.45 ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap">Listo para iniciar el aprendizaje faltante.</pre></div>';
    h+='<div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap;margin-top:14px"><div style="width:132px;height:132px;border-radius:50%;background:conic-gradient(#8b5cf6 0 '+score+'%,#e9e5f5 '+score+'% 100%);display:grid;place-items:center;flex:0 0 132px"><div style="width:100px;height:100px;border-radius:50%;background:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center"><b style="font-size:28px;line-height:1;color:#333333">'+score.toFixed(1)+'%</b><span style="font-size:10px;color:var(--w-muted)">'+W.esc(r.level||'')+'</span></div></div><div style="flex:1;min-width:230px">';
    (r.components||[]).forEach(function(c){h+='<div style="margin-bottom:7px"><div style="display:flex;justify-content:space-between;font-size:10.5px"><span>'+W.esc(c.label)+' <span style="color:var(--w-muted)">('+W.esc(c.detail)+')</span></span><b style="color:#333333">'+(c.no_data?'Sin datos':c.score+'%')+'</b></div><div style="height:5px;background:#eee;border-radius:99px;overflow:hidden"><div style="height:100%;width:'+c.score+'%;background:'+(c.no_data?'#cbd5e1':(c.score>=80?'#34d399':(c.score>=50?'#8b5cf6':'#fb7185')))+'"></div></div></div>'});
    h+='</div></div></div>';
    if((r.history||[]).length){h+='<div class="w-card" style="margin-bottom:14px"><h3 style="margin:0 0 8px">Evolución</h3><div style="display:flex;gap:5px;align-items:end;height:72px;overflow-x:auto;padding-bottom:2px">'+r.history.slice().reverse().map(function(x){return '<div title="'+W.esc(x.date)+' · '+x.score+'%" style="min-width:20px;height:'+Math.max(4,x.score)+'%;background:#8b5cf6;border-radius:3px 3px 0 0"></div>'}).join('')+'</div><div style="font-size:10px;color:var(--w-muted);margin-top:4px">'+W.esc(r.history[0]&&r.history[0].date||'')+' · últimos 30 días</div></div>'}
    h+='<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px"><div class="w-card"><h3 style="margin:0 0 6px">Qué le falta</h3>'+((r.gaps||[]).length?(r.gaps||[]).map(function(x){return '<div style="font-size:11px;padding:5px 0;border-bottom:1px solid var(--w-border)"><b>'+W.esc(x.label)+'</b> · '+x.score+'%<div style="color:var(--w-muted)">'+W.esc(x.detail)+'</div></div>'}).join(''):'<div style="font-size:11px;color:#059669">Sin brechas críticas.</div>')+'</div><div class="w-card"><h3 style="margin:0 0 6px">Qué entrenar</h3>'+((r.recommendations||[]).length?(r.recommendations||[]).map(function(x){return '<div style="font-size:11px;padding:5px 0;border-bottom:1px solid var(--w-border)"><b style="color:#8b5cf6">'+W.esc(x.priority)+'</b> '+W.esc(x.action)+'</div>'}).join(''):'<div style="font-size:11px;color:#059669">Sin recomendaciones pendientes.</div>')+'</div></div>';
    h+='<div class="w-card" style="margin-bottom:14px"><h3 style="margin:0 0 4px">Manuales y fuentes de atención</h3><p style="font-size:11px;color:var(--w-muted);margin:0 0 9px">TIA usará estos manuales como contexto de tono, objeciones y protocolos. No reemplazan precios, stock ni políticas del catálogo.</p><div style="display:grid;grid-template-columns:1.8fr 1fr;gap:8px"><input class="w-input" id="learning-url" placeholder="https://... manual de atención"/><input class="w-input" id="learning-title" placeholder="Título opcional"/></div><div style="display:flex;gap:7px;margin-top:7px;flex-wrap:wrap"><button class="w-btn w-btn-primary w-btn-sm" type="button" onclick="W.learningIngestUrl(this)">Aprender URL</button><input type="file" id="learning-file" accept=".txt,.md,.csv,.json,.html,.htm" style="font-size:11px"/><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.learningUpload(this)">Subir archivo</button><span id="learning-status" style="font-size:11px;color:var(--w-muted)"></span></div>';
    h+='<div style="overflow:auto;margin-top:10px"><table class="w-table"><thead><tr><th>Título</th><th>Tipo</th><th>Origen</th><th></th></tr></thead><tbody>'+((r.sources||[]).length?(r.sources||[]).map(function(x){return '<tr><td>'+W.esc(x.title)+'</td><td>'+W.esc(x.source_type)+'</td><td style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+W.esc(x.url||'Archivo')+'</td><td><button class="w-btn w-btn-danger w-btn-sm" type="button" onclick="W.learningDeleteSource('+x.id+')">Quitar</button></td></tr>'}).join(''):'<tr><td colspan="4" style="color:var(--w-muted)">Sin manuales cargados.</td></tr>')+'</tbody></table></div></div>';
    h+='<div class="w-card"><h3 style="margin:0 0 4px">Corregir comportamiento</h3><p style="font-size:11px;color:var(--w-muted);margin:0 0 9px">Describe qué hizo mal y qué debe hacer en su lugar. Se convierte en una regla activa.</p><textarea class="w-input" id="learning-bad" rows="2" placeholder="Comportamiento inadecuado: por ejemplo, repite el mismo listado y no explica la diferencia." style="width:100%"></textarea><textarea class="w-input" id="learning-good" rows="2" placeholder="Comportamiento deseado: por ejemplo, reconocer una vez, comparar con datos reales y ofrecer ficha o especialista." style="width:100%;margin-top:7px"></textarea><button class="w-btn w-btn-primary w-btn-sm" style="margin-top:7px" type="button" onclick="W.learningCommand(this)">Aplicar corrección</button></div>';
    box.innerHTML=h;
};
W._learningJob=null;
W._learningRunning=false;
W.learningRenderJob=function(job){
    if(!job)return;
    var pre=document.getElementById('learning-terminal');
    if(pre){pre.textContent=(job.logs||[]).map(function(x){return '['+(x.t||'')+'] ['+(x.level||'info')+'] '+(x.message||'')}).join('\n');pre.scrollTop=pre.scrollHeight}
    var bar=document.getElementById('learning-progress');if(bar)bar.style.width=(job.progress||0)+'%';
};
W.learningStart=async function(btn){
    if(W._learningRunning)return;
    W._learningRunning=true;if(btn)btn.disabled=true;
    var pre=document.getElementById('learning-terminal');if(pre)pre.textContent='Creando proceso de aprendizaje...';
    var r=await W.api('/api/v1/admin/ciu/learning/run',{method:'POST',body:{}});
    if(!r||!r.ok){W._learningRunning=false;if(btn)btn.disabled=false;W.notify((r&&r.message)||'No se pudo iniciar','error');return}
    W._learningJob=r.job_id;
    W.learningStep(btn);
};
W.learningStep=async function(btn){
    if(!W._learningJob)return;
    var r=await W.api('/api/v1/admin/ciu/learning/run/'+W._learningJob+'/step',{method:'POST',body:{}});
    if(!r||!r.ok){W._learningRunning=false;if(btn)btn.disabled=false;W.notify((r&&r.message)||'El proceso se detuvo','error');return}
    W.learningRenderJob(r.job);
    if(r.job&&r.job.status==='running'){setTimeout(function(){W.learningStep(btn)},280);return}
    W._learningRunning=false;if(btn)btn.disabled=false;
    if(r.job&&r.job.status==='completed')W.notify('Aprendizaje instalado y probado','success');
    else W.notify('El aprendizaje no pasó la prueba. Revisa la terminal.','error');
};
W._learningStatus=function(text){var el=document.getElementById('learning-status');if(el)el.textContent=text||''};
W.learningIngestUrl=async function(btn){
    var v=function(id){return (document.getElementById(id)||{}).value||''};
    if(btn)btn.disabled=true;W._learningStatus('Leyendo URL...');
    var r=await W.api('/api/v1/admin/ciu/learning/url',{method:'POST',body:{url:v('learning-url'),title:v('learning-title')}});
    if(btn)btn.disabled=false;W._learningStatus((r&&r.message)||'');
    if(r&&r.ok)W.tiaLearningLoad();
};
W.learningUpload=async function(btn){
    var f=document.getElementById('learning-file');if(!f||!f.files||!f.files[0]){W.notify('Selecciona un archivo','error');return}
    var fd=new FormData();fd.append('file',f.files[0]);
    if(btn)btn.disabled=true;W._learningStatus('Procesando archivo...');
    var r=await W.api('/api/v1/admin/ciu/learning/upload',{method:'POST',body:fd});
    if(btn)btn.disabled=false;W._learningStatus((r&&r.message)||'');
    if(r&&r.ok)W.tiaLearningLoad();
};
W.learningCommand=async function(btn){
    var v=function(id){return (document.getElementById(id)||{}).value||''};
    if(btn)btn.disabled=true;
    var r=await W.api('/api/v1/admin/ciu/learning/command',{method:'POST',body:{bad:v('learning-bad'),good:v('learning-good')}});
    if(btn)btn.disabled=false;W.notify((r&&r.message)||(r&&r.ok?'Corrección aplicada':'No se pudo aplicar'),r&&r.ok?'success':'error');
    if(r&&r.ok)W.tiaLearningLoad();
};
W.learningDeleteSource=async function(id){if(!confirm('¿Quitar este manual del aprendizaje?'))return;var r=await W.api('/api/v1/admin/ciu/learning/sources/'+id,{method:'DELETE'});if(r&&r.ok)W.tiaLearningLoad()};
W.tiaProfitLoad=async function(){
    var box=document.getElementById('ciu-body');if(!box)return;
    var period=(document.getElementById('pr-period')||{}).value||new Date().toISOString().slice(0,7);
    var r=await W.api('/api/v1/admin/ciu/profit?period='+encodeURIComponent(period));
    if(!r||!r.ok){box.innerHTML='<div class="w-empty-state"><h3>Rentabilidad no disponible</h3><p>'+W.esc((r&&r.message)||'No se pudo cargar.')+'</p></div>';return}
    var p=r.policy||{},rev=r.revenue||{};
    var cats=[['financial','Financiero'],['operational','Operativo'],['tax','Fiscal'],['administrative','Administrativo'],['marketing','Marketing'],['returns','Devoluciones'],['logistics','Logística'],['other','Otro']];
    var h='<div class="w-card" style="margin-bottom:14px"><div class="w-flex-between" style="gap:8px;flex-wrap:wrap"><div><h3 style="margin:0">Rentabilidad manual</h3><p style="font-size:11px;color:var(--w-muted);margin:4px 0 0">Datos internos. El cliente nunca ve costos, márgenes ni campañas.</p></div><div style="display:flex;gap:6px;align-items:end"><div><label class="w-label">Período</label><input class="w-input" id="pr-period" type="month" value="'+W.esc(period)+'" style="width:150px"/></div><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.tiaProfitLoad()">Ver</button></div></div>';
    h+='<div class="sgl-live__stats" style="margin-top:12px">'+W.sglLiveStat(W.ocMoney(rev.total_cents||0,'COP'),'Ingreso atribuido')+W.sglLiveStat(W.ocMoney(r.gross_margin_cents||0,'COP'),'Margen bruto','#34d399')+W.sglLiveStat(W.ocMoney(r.operating_costs_cents||0,'COP'),'Costos manuales')+W.sglLiveStat(W.ocMoney(r.campaign_spend_cents||0,'COP'),'Campañas')+W.sglLiveStat(W.ocMoney(r.net_profit_cents||0,'COP'),'Utilidad neta estimada',(r.net_profit_cents||0)>=0?'#34d399':'#fb7185')+W.sglLiveStat((r.net_margin_pct||0)+'%','Margen neto')+W.sglLiveStat(W.ocMoney(r.net_target_cents||0,'COP'),'Meta neta')+'</div>';
    h+='<div style="font-size:11px;color:var(--w-muted);margin-top:8px">Gap contra meta: <b style="color:'+((r.net_gap_cents||0)>=0?'#059669':'#b42318')+'">'+W.ocMoney(r.net_gap_cents||0,'COP')+'</b> · Acordeones excluidos: '+W.ocMoney(rev.excluded_accordion_cents||0,'COP')+' · Sin producto: '+W.ocMoney(rev.unknown_cents||0,'COP')+'</div></div>';
    h+='<div class="w-card" style="margin-bottom:14px"><h3 style="margin:0 0 8px">Política</h3><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px"><div><label class="w-label">Margen bruto %</label><input class="w-input" id="pr-gross" type="number" step="0.1" value="'+(p.gross_margin_pct||20)+'"/></div><div><label class="w-label">Meta neta %</label><input class="w-input" id="pr-net" type="number" step="0.1" value="'+(p.net_target_pct||5)+'"/></div><div><label style="font-size:11px;display:block;margin-top:22px"><input type="checkbox" id="pr-breb" '+(p.breb_included?'checked':'')+'/> Bre-B incluido</label><label style="font-size:11px;display:block;margin-top:6px"><input type="checkbox" id="pr-acc" '+(p.accordion_excluded?'checked':'')+'/> Excluir acordeones</label></div></div><button class="w-btn w-btn-primary w-btn-sm" style="margin-top:9px" type="button" onclick="W.profitSavePolicy()">Guardar política</button></div>';
    h+='<div class="w-card" style="margin-bottom:14px"><h3 style="margin:0 0 4px">Costos del período</h3><p style="font-size:11px;color:var(--w-muted);margin:0 0 9px">Financieros, operativos, fiscales, administrativos, devoluciones, logística u otros. No dupliques marketing aquí si lo cargas en campañas.</p><div style="display:grid;grid-template-columns:1.3fr 1fr 1fr;gap:8px"><select class="w-input" id="pc-cat">'+cats.map(function(c){return '<option value="'+c[0]+'">'+c[1]+'</option>'}).join('')+'</select><input class="w-input" id="pc-label" placeholder="Descripción"/><input class="w-input" id="pc-amount" type="number" step="1" placeholder="Monto COP"/></div><div style="display:flex;gap:8px;margin-top:7px"><input class="w-input" id="pc-notes" placeholder="Nota opcional" style="flex:1"/><button class="w-btn w-btn-primary w-btn-sm" type="button" onclick="W.profitAddCost(\''+W.esc(period)+'\')">Agregar costo</button></div>';
    h+='<div style="overflow:auto;margin-top:10px"><table class="w-table"><thead><tr><th>Categoría</th><th>Descripción</th><th>Monto</th><th></th></tr></thead><tbody>'+((r.costs||[]).length?(r.costs||[]).map(function(x){return '<tr><td>'+W.esc(x.category)+'</td><td>'+W.esc(x.label)+'</td><td>'+W.ocMoney(x.amount_cents,'COP')+'</td><td><button class="w-btn w-btn-danger w-btn-sm" type="button" onclick="W.profitDelCost('+x.id+')">Eliminar</button></td></tr>'}).join(''):'<tr><td colspan="4" style="color:var(--w-muted)">Sin costos cargados.</td></tr>')+'</tbody></table></div></div>';
    h+='<div class="w-card" style="margin-bottom:14px"><h3 style="margin:0 0 4px">Costos de producto y acordeones</h3><p style="font-size:11px;color:var(--w-muted);margin:0 0 9px">Carga SKU/referencia y costo real. Se usa para acordeones y futuros cálculos por producto.</p><div style="display:grid;grid-template-columns:1fr 1.4fr 1fr;gap:8px"><input class="w-input" id="pp-ref" placeholder="SKU / referencia"/><input class="w-input" id="pp-title" placeholder="Nombre del producto"/><input class="w-input" id="pp-cost" type="number" step="1" placeholder="Costo COP"/></div><div style="display:flex;gap:8px;margin-top:7px"><input class="w-input" id="pp-notes" placeholder="Nota opcional" style="flex:1"/><button class="w-btn w-btn-primary w-btn-sm" type="button" onclick="W.profitAddProductCost()">Guardar costo</button></div>';
    h+='<div style="overflow:auto;margin-top:10px"><table class="w-table"><thead><tr><th>Referencia</th><th>Producto</th><th>Costo</th><th></th></tr></thead><tbody>'+((r.product_costs||[]).length?(r.product_costs||[]).map(function(x){return '<tr><td>'+W.esc(x.product_ref)+'</td><td>'+W.esc(x.product_title||'-')+'</td><td>'+W.ocMoney(x.cost_cents,'COP')+'</td><td><button class="w-btn w-btn-danger w-btn-sm" type="button" onclick="W.profitDelProductCost('+x.id+')">Eliminar</button></td></tr>'}).join(''):'<tr><td colspan="4" style="color:var(--w-muted)">Sin costos de producto.</td></tr>')+'</tbody></table></div></div>';
    h+='<div class="w-card"><h3 style="margin:0 0 4px">Campañas de marketing</h3><p style="font-size:11px;color:var(--w-muted);margin:0 0 9px">Inversión real del período. Se resta de la utilidad neta.</p><div style="display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:8px"><input class="w-input" id="pm-name" placeholder="Campaña"/><input class="w-input" id="pm-channel" placeholder="Canal"/><input class="w-input" id="pm-spend" type="number" step="1" placeholder="Gasto COP"/></div><div style="display:flex;gap:8px;margin-top:7px"><input class="w-input" id="pm-notes" placeholder="Nota opcional" style="flex:1"/><button class="w-btn w-btn-primary w-btn-sm" type="button" onclick="W.profitAddCampaign(\''+W.esc(period)+'\')">Agregar campaña</button></div>';
    h+='<div style="overflow:auto;margin-top:10px"><table class="w-table"><thead><tr><th>Campaña</th><th>Canal</th><th>Gasto</th><th></th></tr></thead><tbody>'+((r.campaigns||[]).length?(r.campaigns||[]).map(function(x){return '<tr><td>'+W.esc(x.name)+'</td><td>'+W.esc(x.channel||'-')+'</td><td>'+W.ocMoney(x.spend_cents,'COP')+'</td><td><button class="w-btn w-btn-danger w-btn-sm" type="button" onclick="W.profitDelCampaign('+x.id+')">Eliminar</button></td></tr>'}).join(''):'<tr><td colspan="4" style="color:var(--w-muted)">Sin campañas cargadas.</td></tr>')+'</tbody></table></div></div>';
    box.innerHTML=h;
};
W._profitCents=function(v){return Math.round((parseFloat(v)||0)*100)};
W.profitSavePolicy=async function(){
    var r=await W.api('/api/v1/admin/ciu/profit/policy',{method:'POST',body:{gross_margin_pct:parseFloat((document.getElementById('pr-gross')||{}).value)||20,net_target_pct:parseFloat((document.getElementById('pr-net')||{}).value)||5,breb_included:!!(document.getElementById('pr-breb')||{}).checked,accordion_excluded:!!(document.getElementById('pr-acc')||{}).checked}});
    if(r&&r.ok){W.notify('Política guardada','success');W.tiaProfitLoad()}else W.notify((r&&r.message)||'No se pudo guardar','error');
};
W.profitAddCost=async function(period){
    var v=function(id){return (document.getElementById(id)||{}).value||''};
    var r=await W.api('/api/v1/admin/ciu/profit/costs',{method:'POST',body:{period:period,category:v('pc-cat'),label:v('pc-label'),amount_cents:W._profitCents(v('pc-amount')),notes:v('pc-notes')}});
    if(r&&r.ok){W.tiaProfitLoad()}else W.notify((r&&r.message)||'No se pudo guardar','error');
};
W.profitDelCost=async function(id){if(!confirm('¿Eliminar este costo?'))return;var r=await W.api('/api/v1/admin/ciu/profit/costs/'+id,{method:'DELETE'});if(r&&r.ok)W.tiaProfitLoad()};
W.profitAddProductCost=async function(){
    var v=function(id){return (document.getElementById(id)||{}).value||''};
    var r=await W.api('/api/v1/admin/ciu/profit/product-costs',{method:'POST',body:{product_ref:v('pp-ref'),product_title:v('pp-title'),cost_cents:W._profitCents(v('pp-cost')),notes:v('pp-notes')}});
    if(r&&r.ok){W.tiaProfitLoad()}else W.notify((r&&r.message)||'No se pudo guardar','error');
};
W.profitDelProductCost=async function(id){if(!confirm('¿Eliminar este costo?'))return;var r=await W.api('/api/v1/admin/ciu/profit/product-costs/'+id,{method:'DELETE'});if(r&&r.ok)W.tiaProfitLoad()};
W.profitAddCampaign=async function(period){
    var v=function(id){return (document.getElementById(id)||{}).value||''};
    var r=await W.api('/api/v1/admin/ciu/profit/campaigns',{method:'POST',body:{period:period,name:v('pm-name'),channel:v('pm-channel'),spend_cents:W._profitCents(v('pm-spend')),notes:v('pm-notes')}});
    if(r&&r.ok){W.tiaProfitLoad()}else W.notify((r&&r.message)||'No se pudo guardar','error');
};
W.profitDelCampaign=async function(id){if(!confirm('¿Eliminar esta campaña?'))return;var r=await W.api('/api/v1/admin/ciu/profit/campaigns/'+id,{method:'DELETE'});if(r&&r.ok)W.tiaProfitLoad()};
W.tiaInteractionsLoad=async function(){
    var box=document.getElementById('ciu-body'); if(!box)return;
    var r=await W.api('/api/v1/admin/ciu/interactions?days=30');
    if(!r||!r.ok){box.innerHTML='<div class="w-empty-state"><h3>Sin datos</h3><p>'+W.esc((r&&r.message)||'No se pudieron cargar las interacciones.')+'</p></div>';return}
    var s=r.stats||{};
    var labels={started:'Sin resultado',product_click:'Clic en producto',cart:'Agregó al carrito',checkout:'Inició checkout',paid:'Pedido pagado'};
    var color=function(v){return v==='paid'?'#34d399':(v==='checkout'?'#22d3ee':(v==='cart'?'#fbbf24':(v==='product_click'?'#a5b4fc':'#94a3b8')))};
    var h='<div class="w-card" style="margin-bottom:14px"><div class="w-flex-between" style="gap:10px;flex-wrap:wrap"><div><h3 style="margin:0">Interacciones vs resultados</h3><p style="font-size:12px;color:var(--w-muted);margin:5px 0 0">Cada conversación se conecta con clics, carrito, checkout y compra. Sin datos inventados.</p></div><div style="display:flex;gap:6px;flex-wrap:wrap"><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="W.tiaInteractionsLoad()">Actualizar</button><button class="w-btn w-btn-primary w-btn-sm" type="button" onclick="W.tiaAnalyzeInteractions(this)">Analizar con IA</button></div></div>'
        +'<div class="sgl-live__stats" style="margin-top:12px">'+W.sglLiveStat(s.interactions||0,'Interacciones')+W.sglLiveStat(s.product_click||0,'Clic en producto')+W.sglLiveStat(s.cart||0,'Carritos')+W.sglLiveStat(s.checkout||0,'Checkouts')+W.sglLiveStat(s.paid||0,'Pedidos pagados','#34d399')+W.sglLiveStat((s.conversion_rate||0)+'%','Conversión')+W.sglLiveStat(W.ocMoney(s.revenue_cents||0,'COP'),'Ingresos')+W.sglLiveStat(s.no_conversion||0,'Sin resultado')+'</div>'
                 +'<div id="tia-analysis-result" style="margin-top:12px"></div></div>';
    var e=r.economics||{};
    h+='<div class="w-card" style="margin-bottom:14px"><h3 style="margin:0 0 8px">Rentabilidad atribuida a TIA</h3><div class="sgl-live__stats">'+W.sglLiveStat(W.ocMoney(e.attributed_revenue_cents||0,'COP'),'Ingreso atribuido','#34d399')+W.sglLiveStat(W.ocMoney(e.gross_margin_cents||0,'COP'),'Margen bruto estimado','#34d399')+W.sglLiveStat(W.ocMoney(e.net_target_cents||0,'COP'),'Meta neta 5%')+W.sglLiveStat(W.ocMoney(e.available_cost_cents||0,'COP'),'Disponible para costos')+W.sglLiveStat(W.ocMoney(e.avg_ticket_cents||0,'COP'),'Ticket promedio')+W.sglLiveStat(e.attributed_orders||0,'Pedidos atribuidos')+'</div><p style="font-size:11px;color:var(--w-muted);margin:8px 0 0">'+W.esc(e.margin_note||'')+'</p>'+((e.excluded_revenue_cents||e.unknown_revenue_cents)?'<p style="font-size:10px;color:var(--w-muted);margin:4px 0 0">Excluido: acordeones '+W.ocMoney(e.excluded_revenue_cents||0,'COP')+' · sin producto identificado '+W.ocMoney(e.unknown_revenue_cents||0,'COP')+'</p>':'')+'</div>';
    var fb=r.feedback||{};
    h+='<div class="w-card" style="margin-bottom:14px"><h3 style="margin:0 0 8px">Satisfacción del chat</h3><div class="sgl-live__stats">'+W.sglLiveStat((fb.positive_pct||0)+'%','Respuestas útiles','#34d399')+W.sglLiveStat(fb.positive||0,'Útiles')+W.sglLiveStat(fb.negative||0,'No útiles')+W.sglLiveStat(fb.total||0,'Valoraciones')+'</div>'+((fb.reasons||[]).length?'<div style="font-size:11px;color:var(--w-muted);margin-top:8px">Motivos de insatisfacción: '+W.esc((fb.reasons||[]).map(function(x){return (x.reason||'')+' ('+x.count+')'}).join(' · '))+'</div>':'<div style="font-size:11px;color:var(--w-muted);margin-top:8px">Aún no hay motivos de insatisfacción registrados.</div>')+'</div>';
    if((r.experiments||[]).length){
        h+='<div class="w-card" style="margin-bottom:14px"><div class="w-flex-between" style="gap:8px;flex-wrap:wrap"><div><h3 style="margin:0">Experimentos A/B</h3><p style="font-size:11px;color:var(--w-muted);margin:4px 0 0">Prueba el CTA del aviso proactivo y mide clics, chats, pedidos e ingresos por variante.</p></div></div>';
        r.experiments.forEach(function(x){
            var running=x.status==='running';
            h+='<div style="border:1px solid var(--w-border);border-radius:10px;padding:10px;margin-top:10px"><div class="w-flex-between" style="gap:8px;flex-wrap:wrap"><b style="font-size:12px">'+W.esc(x.name)+' <span style="font-size:9px;font-weight:800;color:'+(running?'#059669':'#94a3b8')+'">'+W.esc(String(x.status||'').toUpperCase())+'</span></b><button class="w-btn '+(running?'w-btn-secondary':'w-btn-primary')+' w-btn-sm" type="button" onclick="W.tiaToggleExperiment('+x.id+',\''+(running?'paused':'running')+'\')">'+(running?'Pausar':'Reanudar')+'</button></div><div style="overflow:auto;margin-top:7px"><table class="w-table"><thead><tr><th>Variante</th><th>Impresiones</th><th>Clics CTA</th><th>Chats</th><th>Pagados</th><th>Ingresos</th><th>Conv.</th></tr></thead><tbody>';
            ['A','B'].forEach(function(k){var v=(x.variants&&x.variants[k])||{};h+='<tr><td><b>'+k+'</b> · '+W.esc(v.label||'')+'</td><td>'+W.num(v.impressions||0)+'</td><td>'+W.num(v.clicks||0)+' · '+(v.click_rate_pct||0)+'%</td><td>'+W.num(v.interactions||0)+'</td><td>'+W.num(v.paid||0)+'</td><td>'+W.ocMoney(v.revenue_cents||0,'COP')+'</td><td>'+(v.conversion_pct||0)+'%</td></tr>'});
            h+='</tbody></table></div></div>';
        });
        h+='</div>';
    }
    h+='<div class="w-card" style="margin-bottom:14px"><h3 style="margin:0 0 8px">Resultado por intención</h3><div style="overflow:auto"><table class="w-table"><thead><tr><th>Intención</th><th>Interacciones</th><th>Pagadas</th><th>Ingresos</th></tr></thead><tbody>';
    (r.intents||[]).forEach(function(x){h+='<tr><td>'+W.esc(x.intent)+'</td><td>'+W.num(x.count||0)+'</td><td>'+W.num(x.paid||0)+'</td><td>'+W.ocMoney(x.revenue_cents||0,'COP')+'</td></tr>'});
    h+='</tbody></table></div></div>';
    h+='<div class="w-card"><h3 style="margin:0 0 8px">Interacciones recientes</h3>';
    if(!(r.recent||[]).length)h+='<div style="font-size:12px;color:var(--w-muted)">Todavía no hay interacciones registradas. Habla con TIA en la tienda para comenzar a medir.</div>';
    else{
        h+='<div style="overflow:auto"><table class="w-table"><thead><tr><th>Pregunta</th><th>Intención / etapa</th><th>Resultado</th><th>Fecha</th></tr></thead><tbody>';
        (r.recent||[]).forEach(function(x){h+='<tr><td style="max-width:320px;font-size:11px">'+W.esc((x.question||'').slice(0,160))+'</td><td style="font-size:10px">'+W.esc(x.intent||'—')+'<div style="color:var(--w-muted)">'+W.esc(x.stage||'')+'</div>'+(x.attention?'<div style="color:#7c3aed;font-size:9px">'+W.esc(x.attention)+'</div>':'')+'</td><td><span style="font-size:10px;color:'+color(x.outcome)+'">'+W.esc(labels[x.outcome]||x.outcome)+'</span>'+(x.value_cents?'<div style="font-size:10px">'+W.ocMoney(x.value_cents,'COP')+'</div>':'')+'</td><td style="font-size:10px">'+W.esc(x.created_at||'')+'</td></tr>'});
        h+='</tbody></table></div>';
    }
    h+='</div>';
    if((r.insights||[]).length){
        h+='<div class="w-card" style="margin-top:14px"><h3 style="margin:0 0 8px">Aprendizajes del centro de análisis</h3>';
        r.insights.forEach(function(x){var applied=(x.status==='applied');h+='<div style="border-left:3px solid '+(applied?'#34d399':'#8b5cf6')+';padding:7px 10px;margin-bottom:8px"><b style="font-size:12px">'+W.esc(x.title)+'</b>'+(applied?' <span style="font-size:9px;font-weight:800;color:#059669">APLICADO</span>':'')+'<div style="font-size:11px;color:var(--w-muted);margin-top:3px">'+W.esc(x.diagnosis||'')+'</div><div style="font-size:10px;margin-top:4px">'+W.esc((x.recommendations||[]).map(function(v){return v.next_play||v.technique||''}).filter(Boolean).join(' · '))+'</div>'+(applied?'':'<div style="margin-top:6px"><button class="w-btn w-btn-primary w-btn-sm" type="button" onclick="W.tiaApplyInsight('+x.id+',this)">Enseñar a TIA</button></div>')+'</div>'});
        h+='</div>';
    }
    box.innerHTML=h;
};
W.tiaAnalyzeInteractions=async function(btn){
    if(btn){btn.disabled=true;btn.textContent='Analizando...'}
    var out=document.getElementById('tia-analysis-result');
    if(out)out.innerHTML='<div style="font-size:12px;color:var(--w-muted)">Analizando patrones de conversación…</div>';
    var r=await W.api('/api/v1/admin/ciu/interactions/analyze',{method:'POST',body:{days:30}});
    if(btn){btn.disabled=false;btn.textContent='Analizar con IA'}
    if(!r||!r.ok){if(out)out.innerHTML='<div style="font-size:12px;color:#fb7185">'+W.esc((r&&r.message)||'No se pudo analizar.')+'</div>';return}
    var recs=(r.recommendations||[]).map(function(x){return '<li style="margin-bottom:5px"><b>'+W.esc(x.problem||'')+'</b> → '+W.esc(x.next_play||x.technique||'')+'</li>'}).join('');
    if(out)out.innerHTML='<div style="border-left:3px solid #8b5cf6;padding:9px 12px"><b style="font-size:12px">'+W.esc(r.title||'Aprendizaje')+'</b><div style="font-size:11px;color:var(--w-muted);margin-top:4px">'+W.esc(r.diagnosis||'')+'</div>'+(recs?'<ul style="font-size:11px;margin:7px 0 0;padding-left:17px">'+recs+'</ul>':'')+(r.id?'<div style="margin-top:8px"><button class="w-btn w-btn-primary w-btn-sm" type="button" onclick="W.tiaApplyInsight('+r.id+',this)">Aplicar al Training Room</button></div>':'')+'</div>';
};
W.tiaApplyInsight=async function(id,btn){
    if(!confirm('¿Aplicar estas reglas al comportamiento de TIA? Solo se usarán en próximas conversaciones.'))return;
    if(btn){btn.disabled=true;btn.textContent='Aplicando...'}
    var r=await W.api('/api/v1/admin/ciu/interactions/insights/'+id+'/apply',{method:'POST',body:{}});
    if(btn){btn.disabled=false;btn.textContent='Aplicar al Training Room'}
    if(r&&r.ok){W.notify((r.rules&&r.rules.length?r.rules.length+' reglas aplicadas':'Aprendizaje aplicado'),'success');W.tiaInteractionsLoad()}else W.notify((r&&r.message)||'No se pudo aplicar','error');
};
W.tiaApplyTrainingCase=async function(id,btn){
    if(!confirm('¿Convertir este caso real en una regla activa? TIA la aplicará en próximas conversaciones.'))return;
    if(btn){btn.disabled=true;btn.textContent='Aplicando...'}
    var r=await W.api('/api/v1/admin/ciu/interactions/insights/'+id+'/apply',{method:'POST',body:{}});
    if(btn){btn.disabled=false;btn.textContent='Aplicar como regla'}
    if(r&&r.ok){W.notify('Caso convertido en regla','success');W.trainingLoad()}else W.notify((r&&r.message)||'No se pudo aplicar','error');
};
W.tiaToggleExperiment=async function(id,status){
    if(!confirm(status==='running'?'¿Reanudar este experimento?':'¿Pausar este experimento?'))return;
    var r=await W.api('/api/v1/admin/ciu/experiments/'+id+'/toggle',{method:'POST',body:{status:status}});
    if(r&&r.ok){W.notify('Experimento '+(status==='running'?'activo':'pausado'),'success');W.tiaInteractionsLoad()}else W.notify((r&&r.message)||'No se pudo actualizar','error');
};
W._mpFlash=null;
W.mpCopy=function(text,label){
    try{navigator.clipboard.writeText(String(text||''));W.notify((label||'Valor')+' copiado','success')}catch(e){W.notify('No se pudo copiar','error')}
};
W.renderMercadoPago=async function(){
    var app=document.getElementById('wontia-app'); if(!app)return;
    app.innerHTML='<div class="w-card"><div style="text-align:center;padding:40px;color:var(--w-muted)">Cargando Mercado Pago...</div></div>';
    var r=await W.api('/api/v1/admin/mercadopago/overview');
    if(!r||!r.ok||!r.data){app.innerHTML='<div class="w-empty-state"><h3>No disponible</h3><p>'+W.esc((r&&r.message)||'No se pudo cargar el panel de Mercado Pago.')+'</p></div>';return}
    var d=r.data||{},s=d.settings||{},st=d.stats||{},payments=d.payments||[],hooks=d.webhooks||[],ep=d.endpoints||{};
    var flash=W._mpFlash||null;W._mpFlash=null;
    try{
        var q=new URLSearchParams(location.search);
        if(q.get('mp_oauth')==='ok'){flash={text:'Cuenta conectada con Mercado Pago',at:''};q.delete('mp_oauth');q.delete('mp_msg');history.replaceState(null,'',location.pathname+(q.toString()?'?'+q.toString():'')+location.hash)}
        else if(q.get('mp_oauth')==='error'){flash={text:(q.get('mp_msg')||'No se pudo conectar la cuenta'),at:'',error:true};q.delete('mp_oauth');q.delete('mp_msg');history.replaceState(null,'',location.pathname+(q.toString()?'?'+q.toString():'')+location.hash)}
    }catch(e){}
    var ready=!!s.configured, live=!!s.available;
    var badge=ready?'<span style="font-size:10px;font-weight:800;padding:4px 8px;border-radius:999px;background:#ecfdf5;color:#047857">Configurado</span>':'<span style="font-size:10px;font-weight:800;padding:4px 8px;border-radius:999px;background:#fff7ed;color:#b45309">Sin configurar</span>';
    var liveBadge=live?'<span style="font-size:10px;font-weight:800;padding:4px 8px;border-radius:999px;background:#eef2ff;color:#3730a3">Visible en checkout</span>':'<span style="font-size:10px;font-weight:800;padding:4px 8px;border-radius:999px;background:#f1f5f9;color:#475569">Oculto en checkout</span>';
    var modeLabel=s.mode==='checkout_api'?'Checkout API (tarjeta en el sitio)':'Checkout Pro (redirección)';
    var h='<div class="w-card"><div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap"><div><div style="font-size:11px;color:var(--w-muted)">Pagos</div><h3 style="margin:0">Mercado Pago</h3><p style="font-size:12px;color:var(--w-muted);margin:5px 0 0">Pasarela completa: credenciales manuales, Checkout Pro, Checkout API con tokenización, webhooks firmados, conciliación y reembolsos.</p></div><div class="w-flex w-gap-sm">'+badge+liveBadge+'<span style="font-size:10px;font-weight:800;padding:4px 8px;border-radius:999px;background:#f1f5f9;color:#475569">'+W.esc(s.environment||'sandbox')+'</span></div></div></div>';
    h+=(flash?'<div class="w-card" style="margin-top:12px;border-left:3px solid '+(flash.error?'#b42318':'#0d9f72')+';background:'+(flash.error?'#fef3f2':'#f0fdf4')+';color:'+(flash.error?'#7a271a':'#065f46')+'"><b>'+W.esc(flash.text)+'</b>'+(flash.at?' <span style="font-size:11px">'+W.esc(flash.at)+'</span>':'')+'</div>':'');
    h+='<div class="w-card" style="margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap"><span style="width:10px;height:10px;border-radius:99px;background:'+(live?'#0d9f72':(ready?'#f59e0b':'#94a3b8'))+';box-shadow:0 0 0 4px rgba(100,116,139,.12)"></span><div><b style="font-size:12px">'+(live?'Listo para cobrar':(ready?'Configurado, pero deshabilitado':'Configuración pendiente'))+'</b><div style="font-size:11px;color:var(--w-muted)">'+W.esc(modeLabel)+' · Token: '+W.esc((s.token&&s.token.label)||'—')+(s.token&&s.token.expires_at?' · vence '+W.esc(s.token.expires_at):'')+'</div></div>'+(s.last_test_at?'<span style="margin-left:auto;font-size:10.5px;color:var(--w-muted)">Última prueba: '+W.esc(s.last_test_at)+'</span>':'')+'</div>';
    h+='<div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px">'+W.ocKpi('Pagos',st.payments||0)+W.ocKpi('Aprobados',st.paid||0,'#0d7a58')+W.ocKpi('Pendientes',st.pending||0,'#b45309')+W.ocKpi('Fallidos',st.failed||0,'#b42318')+W.ocKpi('Reembolsados',st.refunded||0)+W.ocKpi('Recaudado',W.ocMoney(st.amount_paid_cents||0,s.currency||'COP'))+W.ocKpi('Webhooks',st.webhooks||0)+W.ocKpi('Firma inválida',st.webhooks_invalid||0,(st.webhooks_invalid||0)>0?'#b42318':undefined)+'</div>';

    h+='<div class="w-card" style="margin-top:14px"><div class="w-flex-between" style="gap:10px;flex-wrap:wrap"><div><h3 style="margin:0">Conexión y credenciales</h3><p style="font-size:12px;color:var(--w-muted);margin:5px 0 0">Los secretos se guardan cifrados (AES-256-GCM) y nunca se envían al frontend. Deja un campo secreto vacío para conservar el valor actual.</p></div><div class="w-flex w-gap-sm"><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="wontia.mpTest(this)">Probar conexión</button><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="wontia.renderMercadoPago()">Actualizar</button></div></div>';
    (d.settings&&d.settings.health||[]).length?h+='<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:7px;margin-top:12px">'+d.settings.health.map(function(x){var col=x.severity==='error'?'#b42318':(x.severity==='warn'?'#b45309':'#0d7a58');return '<div style="display:flex;gap:7px;align-items:flex-start;border:1px solid var(--w-border);border-radius:8px;padding:7px 9px"><span style="width:7px;height:7px;border-radius:99px;background:'+col+';margin-top:5px;flex:0 0 auto"></span><div><b style="font-size:11px">'+W.esc(x.label||x.key)+'</b><div style="font-size:10px;color:var(--w-muted);line-height:1.4">'+W.esc(x.detail||'')+'</div></div></div>'}).join('')+'</div>':'';
    var secretNote=function(set,key){return set?'<span style="font-size:10px;color:#047857;font-weight:700">Guardado</span>':'<span style="font-size:10px;color:#b45309;font-weight:700">Sin configurar</span>'}
    h+='<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px;margin-top:14px">'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Estado</label><select class="w-input" id="mp-enabled" style="width:100%"><option value="0"'+(s.enabled?'':' selected')+'>Deshabilitado</option><option value="1"'+(s.enabled?' selected':'')+'>Habilitado</option></select></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Ambiente</label><select class="w-input" id="mp-env" style="width:100%"><option value="sandbox"'+((s.environment||'sandbox')==='sandbox'?' selected':'')+'>Sandbox / Pruebas</option><option value="production"'+(s.environment==='production'?' selected':'')+'>Producción</option></select></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Modalidad</label><select class="w-input" id="mp-mode" style="width:100%"><option value="checkout_pro"'+((s.mode||'checkout_pro')==='checkout_pro'?' selected':'')+'>Checkout Pro</option><option value="checkout_api"'+(s.mode==='checkout_api'?' selected':'')+'>Checkout API (brick)</option></select></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Client ID</label><input class="w-input" id="mp-client-id" value="'+W.esc(s.client_id||'')+'" placeholder="Número de la aplicación" style="width:100%" autocomplete="off"/></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Client Secret '+secretNote(s.client_secret_set)+'</label><input class="w-input" type="password" id="mp-client-secret" placeholder="'+(s.client_secret_set?'Nuevo Client Secret (opcional)':'Escribe el Client Secret')+'" style="width:100%" autocomplete="new-password"/></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Public Key</label><input class="w-input" id="mp-public-key" value="'+W.esc(s.public_key||'')+'" placeholder="APP_USR-..." style="width:100%" autocomplete="off"/></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Access Token '+secretNote(s.access_token_set)+'</label><input class="w-input" type="password" id="mp-access-token" placeholder="'+(s.access_token_set?'Nuevo Access Token (opcional)':'Opcional: se genera con Client ID + Secret')+'" style="width:100%" autocomplete="new-password"/></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Clave secreta de webhooks '+secretNote(s.webhook_secret_set)+'</label><input class="w-input" type="password" id="mp-webhook-secret" placeholder="'+(s.webhook_secret_set?'Nueva clave (opcional)':'Clave secreta de la aplicación')+'" style="width:100%" autocomplete="new-password"/></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Etiqueta en el checkout</label><input class="w-input" id="mp-label" value="'+W.esc(s.label||'Mercado Pago')+'" style="width:100%"/></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Descripción</label><input class="w-input" id="mp-description" value="'+W.esc(s.description||'')+'" style="width:100%"/></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Descriptor del estado de cuenta</label><input class="w-input" id="mp-statement" value="'+W.esc(s.statement_descriptor||'SONIKA')+'" maxlength="22" style="width:100%"/></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Cuotas máximas</label><input class="w-input" type="number" min="1" max="36" id="mp-installments" value="'+(parseInt(s.max_installments)||12)+'" style="width:100%"/></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">Modo binario</label><select class="w-input" id="mp-binary" style="width:100%"><option value="1"'+(s.binary_mode?' selected':'')+'>Sí (aprobado o rechazado)</option><option value="0"'+(s.binary_mode?'':' selected')+'>No (permitir pendientes)</option></select></div>'
      +'<div><label style="font-size:11px;color:var(--w-muted)">URL de éxito personalizada</label><input class="w-input" id="mp-success-url" value="'+W.esc(s.success_url||'')+'" placeholder="Opcional" style="width:100%"/></div>'
      +'</div>';
    h+='<div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:10px;font-size:11px">'
      +'<label><input type="checkbox" id="mp-clear-secret"/> Borrar Client Secret</label>'
      +'<label><input type="checkbox" id="mp-clear-token"/> Borrar Access Token</label>'
      +'<label><input type="checkbox" id="mp-clear-webhook"/> Borrar clave de webhooks</label></div>';
    h+='<div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center"><button class="w-btn w-btn-primary" id="mp-save" type="button" onclick="wontia.mpSave()">Guardar configuración</button><button class="w-btn w-btn-secondary" type="button" onclick="wontia.mpOAuth()">'+(s.oauth_connected?'Reconectar cuenta':'Conectar cuenta por OAuth')+'</button>'+(s.oauth_connected?'<button class="w-btn w-btn-secondary" type="button" onclick="wontia.mpDisconnect()">Desconectar OAuth</button>':'')+'<span id="mp-save-status" role="status" aria-live="polite" style="font-size:11px;font-weight:700;color:var(--w-muted)"></span></div>';
    h+='</div>';

    h+='<div class="w-card" style="margin-top:14px"><h3 style="margin:0 0 4px">URLs de integración</h3><p style="font-size:12px;color:var(--w-muted);margin:0 0 10px">Copia estas URLs en el panel de Mercado Pago → Tus integraciones → Webhooks y en la configuración OAuth de la aplicación.</p>';
    var urls=[['Webhook',ep.webhook||''],['Redirect OAuth',ep.redirect_uri||'']];
    urls.forEach(function(u){h+='<div style="display:flex;gap:8px;align-items:center;margin-top:7px;flex-wrap:wrap"><span style="font-size:11px;font-weight:700;min-width:110px">'+W.esc(u[0])+'</span><code style="font-size:10.5px;background:var(--w-bg2);border:1px solid var(--w-border);border-radius:6px;padding:5px 7px;flex:1;min-width:220px;word-break:break-all">'+W.esc(u[1])+'</code><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="wontia.mpCopy(\''+W.esc(u[1])+'\',\''+W.esc(u[0])+'\')">Copiar</button></div>'});
    h+='<div style="font-size:11px;color:var(--w-muted);margin-top:10px">Documentación oficial de credenciales: <a href="'+W.esc(ep.docs||'https://www.mercadopago.com.co/developers/es/docs/your-integrations/credentials')+'" target="_blank" rel="noopener" style="color:#2563eb">Mercado Pago Developers</a></div></div>';

    h+='<div class="w-card" style="margin-top:14px"><div class="w-flex-between" style="gap:10px;flex-wrap:wrap"><div><h3 style="margin:0">Pagos recientes</h3><p style="font-size:12px;color:var(--w-muted);margin:5px 0 0">Conciliación local con el estado real de Mercado Pago.</p></div><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="wontia.renderMercadoPago()">Recargar</button></div>';
    if(!payments.length){h+='<div class="w-empty-state" style="margin-top:12px"><h3>Sin pagos registrados</h3><p>Aquí aparecerán los pagos cuando se habilite el checkout con Mercado Pago.</p></div>'}
    else{h+='<div style="overflow:auto;margin-top:12px"><table class="w-table"><thead><tr><th>Pedido</th><th>Pago MP</th><th>Monto</th><th>Medio</th><th>Estado</th><th>Modo</th><th>Fecha</th><th></th></tr></thead><tbody>';
        payments.forEach(function(x){var stc=x.status==='paid'?'#0d7a58':(x.status==='failed'?'#b42318':'#b45309');h+='<tr><td><b>'+W.esc(x.order_number_live||x.order_number||('#'+x.order_id))+'</b><div style="font-size:9.5px;color:var(--w-muted)">'+W.esc(x.order_payment_status||'')+'</div></td><td style="font-family:monospace;font-size:10.5px">'+W.esc(x.payment_id||x.preference_id||'—')+'</td><td>'+W.ocMoney(x.amount_cents,x.currency)+'</td><td style="font-size:10.5px">'+W.esc(x.payment_method_id||'—')+(parseInt(x.installments)>1?' · '+parseInt(x.installments)+'x':'')+'</td><td><span style="font-size:10px;font-weight:800;color:'+stc+'">'+W.esc(x.status||'')+'</span></td><td style="font-size:10px">'+W.esc(x.mode||'')+'</td><td style="white-space:nowrap;font-size:10px">'+W.esc(String(x.created_at||'').slice(0,16))+'</td><td><button class="w-btn w-btn-secondary w-btn-sm" type="button" onclick="wontia.mpPayment('+x.id+')">Ver</button></td></tr>'});
        h+='</tbody></table></div>'}
    h+='</div>';

    h+='<div class="w-card" style="margin-top:14px"><div class="w-flex-between" style="gap:10px;flex-wrap:wrap"><div><h3 style="margin:0">Webhooks recibidos</h3><p style="font-size:12px;color:var(--w-muted);margin:5px 0 0">Cada notificación se registra una sola vez y se valida con HMAC x-signature.</p></div></div>';
    if(!hooks.length){h+='<div class="w-empty-state" style="margin-top:12px"><h3>Sin notificaciones</h3><p>Verifica que la URL de webhook esté registrada en Mercado Pago.</p></div>'}
    else{h+='<div style="overflow:auto;margin-top:12px"><table class="w-table"><thead><tr><th>Evento</th><th>Recurso</th><th>Firma</th><th>Resultado</th><th>Recibido</th></tr></thead><tbody>';
        hooks.forEach(function(x){h+='<tr><td style="font-size:10.5px">'+W.esc(x.topic||'—')+'</td><td style="font-family:monospace;font-size:10.5px">'+W.esc(x.resource_id||'—')+'</td><td style="font-size:10px;color:'+(x.signature_valid===1?'#0d7a58':(x.signature_valid===0?'#b42318':'#64748b'))+'">'+(x.signature_valid===1?'Válida':(x.signature_valid===0?'Inválida':'Sin clave'))+'</td><td style="font-size:10px">'+W.esc(x.result||'')+'</td><td style="font-size:10px;white-space:nowrap">'+W.esc(String(x.received_at||'').slice(0,16))+'</td></tr>'});
        h+='</tbody></table></div>'}
    h+='</div>';

    h+='<div class="w-card" style="margin-top:14px"><h3 style="margin:0 0 8px">Guía rápida</h3><ol style="font-size:12px;color:var(--w-muted);line-height:1.75;margin:0;padding-left:20px"><li>Entra a <b>Mercado Pago → Tus integraciones → Credenciales</b>.</li><li>Copia <b>Client ID</b>, <b>Client Secret</b> y <b>Public Key</b>. En producción usa las credenciales APP_USR.</li><li>Pega la <b>Clave secreta de webhooks</b> y registra la URL de webhook de arriba.</li><li>Guarda, pulsa <b>Probar conexión</b> y luego activa el estado.</li><li>Si usas Checkout API, la Public Key debe ser la misma aplicación.</li></ol></div>';
    app.innerHTML=h;
};
W.mpSave=async function(){
    var v=function(id){var el=document.getElementById(id);return el?el.value:''};
    var btn=document.getElementById('mp-save'),status=document.getElementById('mp-save-status');
    var say=function(t,c){if(status){status.textContent=t;status.style.color=c||'var(--w-muted)'}};
    if(btn){btn.disabled=true;btn.textContent='Guardando...'}
    say('Guardando configuración...','#64748b');
    var payload={
        enabled:v('#mp-enabled')==='1',environment:v('#mp-env'),mode:v('#mp-mode'),
        client_id:v('#mp-client-id').trim(),client_secret:v('#mp-client-secret'),public_key:v('#mp-public-key').trim(),
        access_token:v('#mp-access-token'),webhook_secret:v('#mp-webhook-secret'),
        label:v('#mp-label'),description:v('#mp-description'),statement_descriptor:v('#mp-statement'),
        max_installments:parseInt(v('#mp-installments'))||12,binary_mode:v('#mp-binary')==='1',success_url:v('#mp-success-url'),
        clear_client_secret:!!(document.getElementById('mp-clear-secret')||{}).checked,
        clear_access_token:!!(document.getElementById('mp-clear-token')||{}).checked,
        clear_webhook_secret:!!(document.getElementById('mp-clear-webhook')||{}).checked
    };
    try{
        var r=await W.api('/api/v1/admin/mercadopago/settings',{method:'PUT',body:payload});
        if(r&&r.ok){W._mpFlash={text:'Configuración guardada correctamente',at:new Date().toLocaleString()};W.renderMercadoPago();return}
        say((r&&r.message)||'No se pudo guardar la configuración.','#b42318');
    }catch(e){say('No se pudo conectar con el servidor. Intenta de nuevo.','#b42318')}
    if(btn){btn.disabled=false;btn.textContent='Guardar configuración'}
};
W.mpTest=async function(btn){
    if(btn){btn.disabled=true;btn.textContent='Probando...'}
    var r=await W.api('/api/v1/admin/mercadopago/test',{method:'POST'});
    if(btn){btn.disabled=false;btn.textContent='Probar conexión'}
    if(!r||!r.ok){W.notify((r&&r.message)||'No se pudo probar la conexión','error');return}
    var d=r.data||{},acc=d.account||{},warn=d.warnings||[];
    var h='<div style="font-size:12.5px;line-height:1.7;max-height:65vh;overflow:auto">'
      +'<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:8px">'
      +'<div><b>Resultado:</b> '+(d.ok?'<span style="color:#047857;font-weight:800">Conexión correcta</span>':'<span style="color:#b42318;font-weight:800">Con problemas</span>')+'</div>'
      +'<div><b>Ambiente:</b> '+W.esc(d.environment||'')+'</div>'
      +'<div><b>Modalidad:</b> '+W.esc(d.mode||'')+'</div>'
      +'<div><b>Token:</b> '+W.esc(d.token_source||'none')+'</div>'
      +'<div><b>Cuenta:</b> '+W.esc(acc.nickname||acc.email||acc.id||'—')+'</div>'
      +'<div><b>País:</b> '+W.esc(acc.country_id||acc.site_id||'—')+'</div>'
      +'<div><b>Medios de pago:</b> '+W.num(d.payment_methods||0)+'</div>'
      +'<div><b>Webhook firmado:</b> '+(d.webhook_signature?'Sí':'No')+'</div>'
      +'</div>'
      +(warn.length?'<div style="margin-top:12px"><b>Recomendaciones</b>'+warn.map(function(x){return '<div style="border-left:3px solid #f59e0b;padding:5px 8px;margin-top:6px;background:#fffbeb;color:#92400e">'+W.esc(x)+'</div>'}).join('')+'</div>':'')
      +(d.error?'<div style="margin-top:12px;border-left:3px solid #b42318;padding:6px 9px;background:#fef3f2;color:#7a271a">'+W.esc(d.error)+'</div>':'')
      +'<div style="font-size:10.5px;color:var(--w-muted);margin-top:12px">Comprobado: '+W.esc(d.checked_at||'')+'</div></div>';
    W.modal('Diagnóstico de Mercado Pago',h,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cerrar</button>');
};
W.mpOAuth=async function(){
    var r=await W.api('/api/v1/admin/mercadopago/oauth/start',{method:'POST'});
    if(!r||!r.ok||!r.url){W.notify((r&&r.message)||'No se pudo iniciar OAuth','error');return}
    window.open(r.url,'_blank','noopener');
    W.notify('Completa la autorización en la pestaña de Mercado Pago','info');
};
W.mpDisconnect=async function(){
    if(!confirm('¿Desconectar la cuenta de Mercado Pago? Se borrarán el token OAuth y el refresh token.'))return;
    var r=await W.api('/api/v1/admin/mercadopago/oauth/disconnect',{method:'POST'});
    if(r&&r.ok){W._mpFlash={text:'Cuenta desconectada',at:new Date().toLocaleString()};W.renderMercadoPago()}else W.notify((r&&r.message)||'No se pudo desconectar','error');
};
W.mpPayment=async function(id){
    var r=await W.api('/api/v1/admin/mercadopago/payments/'+id);
    if(!r||!r.ok||!r.data){W.notify((r&&r.message)||'No disponible','error');return}
    var x=r.data||{},h='<div style="font-size:12.5px;line-height:1.65;max-height:62vh;overflow:auto">'
      +'<div><b>Pedido:</b> '+W.esc(x.order_number_live||x.order_number||('#'+x.order_id))+'</div>'
      +'<div><b>Pago MP:</b> '+W.esc(x.payment_id||'—')+'</div>'
      +'<div><b>Preferencia:</b> '+W.esc(x.preference_id||'—')+'</div>'
      +'<div><b>Estado:</b> '+W.esc(x.status||'')+' '+W.esc(x.status_detail||'')+'</div>'
      +'<div><b>Monto:</b> '+W.ocMoney(x.amount_cents,x.currency)+'</div>'
      +'<div><b>Medio:</b> '+W.esc(x.payment_method_id||'—')+' · '+(parseInt(x.installments)||1)+' cuota(s)</div>'
      +'<div><b>Pagador:</b> '+W.esc(x.payer_email||'—')+'</div>'
      +'<div><b>Referencia:</b> '+W.esc(x.external_reference||'—')+'</div>'
      +'<div><b>Modo:</b> '+W.esc(x.mode||'')+' · '+W.esc(x.environment||'')+'</div>'
      +'<div><b>Creado:</b> '+W.esc(x.created_at||'')+' · <b>Sincronizado:</b> '+W.esc(x.last_synced_at||'—')+'</div>';
    if((x.webhooks||[]).length){h+='<div style="margin-top:10px"><b>Webhooks</b>';x.webhooks.forEach(function(w){h+='<div style="border-bottom:1px solid var(--w-border);padding:4px 0;font-size:11px">'+W.esc(w.topic||'')+' · '+W.esc(w.result||'')+' · firma '+(w.signature_valid===1?'válida':(w.signature_valid===0?'inválida':'n/d'))+' <span style="color:var(--w-muted)">'+W.esc(w.received_at||'')+'</span></div>'});h+='</div>'}
    h+='</div>';
    var footer='<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cerrar</button>';
    if(x.payment_id){footer+=' <button class="w-btn w-btn-secondary" onclick="wontia.mpSync('+x.id+')">Sincronizar</button> <button class="w-btn w-btn-primary" onclick="wontia.mpRefund('+x.id+')">Reembolsar</button>'}
    W.modal('Pago de Mercado Pago',h,footer);
};
W.mpSync=async function(id){
    var r=await W.api('/api/v1/admin/mercadopago/payments/'+id+'/sync',{method:'POST'});
    if(r&&r.ok){W.notify('Estado sincronizado: '+(r.status||''),'success');W.closeModal();W.renderMercadoPago()}else W.notify((r&&r.message)||'No se pudo sincronizar','error');
};
W.mpRefund=async function(id){
    var input=prompt('Monto a reembolsar en centavos (vacío = reembolso total):','');
    if(input===null)return;
    var body={};if(String(input).trim()!=='')body.amount_cents=parseInt(input,10)||0;
    if(!confirm('¿Confirmas el reembolso en Mercado Pago?'))return;
    var r=await W.api('/api/v1/admin/mercadopago/payments/'+id+'/refund',{method:'POST',body:body});
    if(r&&r.ok){W.notify(r.message||'Reembolso solicitado','success');W.closeModal();W.renderMercadoPago()}else W.notify((r&&r.message)||'No se pudo reembolsar','error');
};
W.panels={
    dashboard:W.renderDashboard,
    'order-center':W.renderOrderCenter,
    wwi:W.renderWWI,
    store:W.renderStore,
    sistecredito:W.renderSistecredito,
    mercadopago:W.renderMercadoPago,
    pages:W.renderPages,
    pageEditor:W.renderPageEditor,
    bricks:W.renderBricks,
    brickhub:W.renderBrickHub,
    brick:W.renderBrick,
    portal:W.renderPortal,
    blog:W.renderBlogList,
    blogEditor:W.renderBlogEditor,
    media:W.renderMediaManager,
    banners:W.renderBanners,
    'mega-menu':W.renderMegaMenu,
    hero:W.renderHero,
    directory:W.renderDirectory,
    toques:W.renderToques,
    monas:W.renderMonyas,
    seo:W.renderSeo,
    'seo-global-launch':W.renderSeoGlobalLaunch,
    'tia-command-center':W.renderTiaCommandCenter,
    analytics:W.renderAnalytics,
    settings:W.renderSettings,
    users:W.renderUsers,
    sectionEditor:W.renderSectionEditor
};

(function(){
    var required=['dashboard','wwi','pages','bricks','brickhub','brick','blog','media','seo','analytics','settings','users'];
    var missing=required.filter(function(k){return !W.panels[k]});
    if(missing.length)console.error('WWI ADMIN ERROR - paneles faltantes:',missing);
})();

W.esc=function(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')};
W.num=function(n){return n!=null?n.toLocaleString():'0'};
W.slugify=function(t){return t.toLowerCase().replace(/[^a-z0-9\s-]/g,'').replace(/[\s_]+/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'')};

W.watchVersion=function(){
    if(W._verTimer)return;
    W._ver=window.__WWI_VER!=null?String(window.__WWI_VER):null;
    if(!W._ver)return;
    var check=async function(){
        try{
            var r=await fetch('/api/v1/public/asset-version',{cache:'no-store'});
            var d=await r.json();
            var v=d&&d.data&&d.data.js!=null?String(d.data.js):null;
            if(v&&v!==W._ver){location.reload();return}
        }catch(e){}
    };
    W._verTimer=setInterval(check,300000);
    document.addEventListener('visibilitychange',function(){if(!document.hidden)check()});
};

window.addEventListener('hashchange',function(){W.router()});
window.addEventListener('load',function(){W.mountTopbar();W.router();W.updateBHBadge();setInterval(W.updateBHBadge,300000);W.watchVersion();W.agentBridgePoll()});
window.wontia=W;
window.W=W;
})();
