(function(){
var W={};
W.token=null;
try{W.token=localStorage.getItem('wwi_token')||null}catch(e){}

W.api=async function(url,options){
    options=options||{};
    var headers=options.headers||{};
    headers['Content-Type']=headers['Content-Type']||'application/json';
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
    document.querySelectorAll('.w-nav-item').forEach(function(a){a.classList.toggle('active',a.dataset.panel===panel)});
    var title=document.getElementById('panel-title');
    var titles={dashboard:'Dashboard',wwi:'WWI — Sistema',pages:'Pages',sections:'Sections',bricks:'Bricks',brickhub:'BrickHub',brick:'AI BRICK',factory:'Factory',portal:'Mi Portal',blog:'Blog',media:'Media',seo:'SEO',analytics:'Analytics',settings:'Settings',users:'Users'};
    if(title)title.textContent=titles[panel]||panel;
    var app=document.getElementById('wontia-app');
    if(!app)return;
    W.vt(function(){
        app.innerHTML='<div style="text-align:center;padding:60px;color:var(--w-muted)">Loading...</div>';
        try{
            var fn=W.panels[panel];
            if(fn)fn(id,action,parts);
            else if(panel==='pages'&&id&&!action)W.panels.pageEditor(id);
            else if(panel==='blog'&&id&&!action)W.panels.blogEditor(id);
            else if(panel==='sections'&&id)W.panels.pageSections(id);
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

W.renderPageList=function(){
    var app=document.getElementById('wontia-app');
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
        html+='<a href="#sections/'+p.id+'" class="w-btn w-btn-secondary w-btn-sm" onclick="event.stopPropagation()">Sections</a>';
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
        if(r.ok){W.notify('Saved','success');W.state.currentPage=r.data;if(!id){window.location.hash='#sections/'+r.data.id;window.location.hash='#pages'}}
    });
};

W.renderSectionManager=function(pageId){
    var app=document.getElementById('wontia-app');
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
        el.innerHTML='<div class="w-empty-state"><h3>Selecciona una página</h3><p>Entra a Pages y elige la página cuyas secciones quieres editar.</p><a href="#pages" class="w-btn w-btn-primary">Ir a Pages</a></div>';
        return;
    }
    var d=await W.api('/api/v1/admin/pages/'+pageId);
    if(!d.ok||!d.data){
        el.innerHTML='<div class="w-empty-state"><h3>Page not found</h3><p>La página no existe en este sitio. Entra a Pages para ver las disponibles.</p><a href="#pages" class="w-btn w-btn-primary">Ir a Pages</a></div>';
        return;
    }
    W.state.currentPage=d.data;
    if(!W.state.currentPage.sections)W.state.currentPage.sections=[];
    var html='<div class="w-flex-between w-mb-lg"><div><h3 style="font-size:15px">Secciones: '+W.esc(W.state.currentPage.title)+'</h3><span style="font-size:11px;color:var(--w-muted)">'+W.state.currentPage.sections.length+' secciones</span></div><div class="w-flex w-gap-sm"><button class="w-btn w-btn-primary" onclick="wontia.showSectionTypePicker('+pageId+')">+ Añadir sección</button><a href="#pages" class="w-btn w-btn-secondary">Volver</a></div></div>';
    if(!W.state.currentPage.sections.length){
        html+='<div class="w-empty-state"><h3>Sin secciones</h3><p>Añade la primera sección a esta página</p></div>';
    }else{
        W.state.currentPage.sections.forEach(function(s,i){
            html+='<div class="w-card w-mb" draggable="true" data-sid="'+s.id+'" style="cursor:grab">';
            html+='<div class="w-flex-between w-mb"><div class="w-flex w-gap-sm"><span style="font-size:10px;color:var(--w-muted);background:var(--w-bg);padding:2px 8px;border-radius:4px">'+W.esc(W.sectionLabel(s))+'</span><strong style="font-size:13px">'+W.esc(s.title||'Sin título')+'</strong>'+(s.is_active==1||s.is_active==='1'?'':'<span class="w-brick-chip" style="color:#F5A623">oculta</span>')+'</div><div class="w-flex w-gap-sm"><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.editSection('+s.id+')">Editar</button><button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.deleteSection('+s.id+','+pageId+')">Eliminar</button></div></div>';
            if(s.subtitle)html+='<p style="font-size:12px;color:var(--w-muted);margin-bottom:6px">'+W.esc(s.subtitle)+'</p>';
            html+='</div>';
        });
    }
    el.innerHTML=html;
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

W.renderSchemaFields=function(schema,values){
    values=values||{};
    var html='';
    (schema||[]).forEach(function(f){
        var v=(values[f.key]!==undefined&&values[f.key]!==null)?values[f.key]:(f.default!==undefined?f.default:'');
        var vid='secf-'+f.key;
        if(f.type==='text')html+='<div class="w-form-group"><label class="w-label">'+W.esc(f.label)+'</label><input class="w-input" id="'+vid+'" value="'+W.esc(v)+'"/></div>';
        else if(f.type==='textarea')html+='<div class="w-form-group"><label class="w-label">'+W.esc(f.label)+'</label><textarea class="w-textarea" id="'+vid+'">'+W.esc(v)+'</textarea></div>';
        else if(f.type==='toggle')html+='<div class="w-form-group"><label class="w-label" style="display:flex;align-items:center;gap:8px"><input type="checkbox" id="'+vid+'" '+(v?'checked':'')+' style="width:auto"/> '+W.esc(f.label)+'</label></div>';
        else if(f.type==='select'){var o='';for(var k in (f.options||{}))o+='<option value="'+W.esc(k)+'"'+(String(v)===String(k)?' selected':'')+'>'+W.esc(f.options[k])+'</option>';html+='<div class="w-form-group"><label class="w-label">'+W.esc(f.label)+'</label><select class="w-select" id="'+vid+'">'+o+'</select></div>';}
        else html+='<div class="w-form-group"><label class="w-label">'+W.esc(f.label)+' <span style="font-size:9px;color:var(--w-muted)">JSON</span></label><textarea class="w-textarea" id="'+vid+'" style="min-height:80px;font-family:monospace;font-size:11px">'+W.esc(typeof v==='object'?JSON.stringify(v):v)+'</textarea></div>';
    });
    return html;
};

W.collectSchemaFields=function(schema){
    var cfg={};
    (schema||[]).forEach(function(f){
        var el=document.getElementById('secf-'+f.key);
        if(!el)return;
        if(f.type==='toggle')cfg[f.key]=el.checked?1:0;
        else if(f.type==='code'){try{cfg[f.key]=JSON.parse(el.value||'[]')}catch(e){cfg[f.key]=el.value}}
        else cfg[f.key]=el.value;
    });
    return cfg;
};

W.editSection=async function(sectionId){
    var s=null;
    ((W.state.currentPage&&W.state.currentPage.sections)||[]).forEach(function(x){if(x.id===sectionId)s=x});
    if(!s){
        var d=await W.api('/api/v1/admin/sections/'+sectionId);
        if(!d.ok||!d.data){W.notify(d.message||'Section not found','error');return}
        s=d.data;
    }
    var pageId=s.page_id;
    var body='';
    body+='<div class="w-form-group"><label class="w-label">Título de la sección</label><input class="w-input" id="es-title" value="'+W.esc(s.title||'')+'"/></div>';
    body+='<div class="w-form-group"><label class="w-label">Subtítulo / descripción</label><textarea class="w-textarea" id="es-subtitle">'+W.esc(s.subtitle||'')+'</textarea></div>';
    if(s.widget_type){
        var d2={};
        try{d2=await W.api('/api/v1/admin/bricks/'+s.widget_type)}catch(e){d2={}}
        var b=d2.data||{};
        var schema=b.configSchema||[];
        var values={};
        try{values=JSON.parse(s.config||'{}')||{}}catch(e){}
        W.state.editSchema=schema;
        if(schema.length){body+='<div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--w-muted);margin:14px 0 8px">Contenido — '+W.esc(W.sectionLabel(s))+'</div>'+W.renderSchemaFields(schema,values);}
    }else{
        W.state.editSchema=null;
        body+='<div class="w-form-group"><label class="w-label">Contenido (HTML)</label><textarea class="w-textarea" id="es-content" style="min-height:150px;font-family:monospace">'+W.esc(s.content||'')+'</textarea></div>';
    }
    body+='<label style="font-size:12px;display:flex;align-items:center;gap:8px;margin-top:8px"><input type="checkbox" id="es-active" '+(s.is_active==1||s.is_active==='1'?'checked':'')+' style="width:auto"/> Visible en el sitio</label>';
    W.modal('Editar sección — '+W.sectionLabel(s),body,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancelar</button><button class="w-btn w-btn-primary" onclick="wontia.saveSection('+sectionId+','+pageId+')">Guardar</button>');
};

W.saveSection=async function(id,pageId){
    var activeEl=document.getElementById('es-active');
    var data={title:W.val('es-title'),subtitle:W.val('es-subtitle'),is_active:activeEl&&activeEl.checked?1:0};
    if(W.state.editSchema)data.config=W.collectSchemaFields(W.state.editSchema);
    else data.content=W.val('es-content');
    var r=await W.api('/api/v1/admin/sections/'+id,{method:'PUT',body:data});
    if(r.ok){W.closeModal();W.loadSections(pageId);W.notify('Sección guardada','success')}
    else if(r.message)W.notify(r.message,'error');
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

W.renderMediaManager=function(){
    var app=document.getElementById('wontia-app');
    app.innerHTML='<div class="w-flex-between w-mb-lg"><h3>Media Library</h3><button class="w-btn w-btn-primary" id="media-upload-btn">Upload</button></div><div class="w-drop-zone w-mb-lg" id="media-drop"><span>Drag & drop images here or click to upload</span></div><input type="file" id="media-file-input" accept="image/*" style="display:none" multiple/><div id="media-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px"></div>';
    document.getElementById('media-drop').addEventListener('click',function(){document.getElementById('media-file-input').click()});
    document.getElementById('media-upload-btn').addEventListener('click',function(){document.getElementById('media-file-input').click()});
    document.getElementById('media-file-input').addEventListener('change',W.handleMediaUpload);
    document.getElementById('media-drop').addEventListener('dragover',function(e){e.preventDefault();this.classList.add('drag-over')});
    document.getElementById('media-drop').addEventListener('dragleave',function(){this.classList.remove('drag-over')});
    document.getElementById('media-drop').addEventListener('drop',function(e){e.preventDefault();this.classList.remove('drag-over');W.uploadFiles(e.dataTransfer.files)});
    W.refreshMedia();
};

W.refreshMedia=async function(){
    var d=await W.api('/api/v1/admin/media');
    var items=d.data||[];
    var grid=document.getElementById('media-grid');
    if(!grid)return;
    if(!items.length){grid.innerHTML='<div class="w-empty-state" style="grid-column:1/-1"><p>No media uploaded</p></div>';return;}
    grid.innerHTML=items.map(function(m){
        return '<div style="background:var(--w-bg);border-radius:6px;overflow:hidden;text-align:center;position:relative">'+
            '<img src="'+W.esc(m.url)+'" alt="" style="width:100%;height:120px;object-fit:cover" onerror="this.src=\'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22150%22 height=%22120%22><rect fill=%22%23333%22 width=%22150%22 height=%22120%22/><text fill=%22%23888%22 x=%2275%22 y=%2265%22 text-anchor=%22middle%22 font-size=%2211%22>No img</text></svg>\'"/>'+
            '<div style="padding:6px;font-size:10px;color:var(--w-muted)">'+W.esc(m.filename||'')+'</div>'+
            '<button class="w-btn w-btn-danger w-btn-sm" style="position:absolute;top:4px;right:4px" onclick="wontia.deleteMedia('+m.id+')">X</button>'+
            '<button class="w-btn w-btn-secondary w-btn-sm" style="position:absolute;bottom:30px;right:4px" onclick="navigator.clipboard.writeText(\''+m.url+'\');wontia.notify(\'Copied\',\'success\')">Copy URL</button>'+
            '</div>';
    }).join('');
};

W.handleMediaUpload=function(){W.uploadFiles(this.files)};

W.uploadFiles=async function(files){
    for(var i=0;i<files.length;i++){
        var fd=new FormData();
        fd.append('file',files[i]);
        W.notify('Uploading '+files[i].name+'...','info');
        var r=await W.api('/api/v1/admin/media/upload',{method:'POST',body:fd});
        if(r.ok)W.notify('Uploaded '+files[i].name,'success');
    }
    W.refreshMedia();
};

W.deleteMedia=function(id){
    W.confirm('Delete this file?',async function(){await W.api('/api/v1/admin/media/'+id,{method:'DELETE'});W.refreshMedia();W.notify('Deleted','success')});
};

W.renderAnalytics=async function(){
    var app=document.getElementById('wontia-app');
    var d=await W.api('/api/v1/admin/analytics');
    var a=d.data||{};
    app.innerHTML='<div class="w-stats">'+
        '<div class="w-stat-card"><div class="w-stat-value">'+W.num(a.totals?.['views']||0)+'</div><div class="w-stat-label">Page Views</div></div>'+
        '<div class="w-stat-card"><div class="w-stat-value">'+W.num(a.totals?.['unique_visitors']||0)+'</div><div class="w-stat-label">Unique Visitors</div></div>'+
        '</div>'+
        '<div class="w-card"><h3>Top Pages</h3><table class="w-table"><thead><tr><th>URL</th><th>Views</th></tr></thead><tbody>'+(a.top_pages||[]).map(function(p){return '<tr><td>'+W.esc(p.page_url)+'</td><td>'+p.views+'</td></tr>'}).join('')+'</tbody></table></div>'+
        '<div class="w-card"><h3>GA4</h3><div class="w-flex w-gap-sm"><input class="w-input" id="ga4-id" value="'+W.esc(a.ga4_id||'')+'" placeholder="G-XXXXXXXXXX"/><button class="w-btn w-btn-primary" onclick="wontia.saveGa4()">Save</button></div></div>';
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

W.renderSettings=async function(){
    var app=document.getElementById('wontia-app');
    var d=await W.api('/api/v1/admin/settings');
    var s=d.data||{};
    var keys=['site_name','site_description','ga_measurement_id','cookie_consent_enabled','primary_color','logo_text'];
    var html='<div class="w-card"><h3>Site Settings</h3>';
    keys.forEach(function(k){
        html+='<div class="w-form-group"><label class="w-label">'+W.esc(k)+'</label><input class="w-input" id="set-'+k+'" value="'+W.esc(s[k]||'')+'"/></div>';
    });
    html+='<button class="w-btn w-btn-primary" onclick="wontia.saveSettings()">Save Settings</button></div>';
    app.innerHTML=html;
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

W.renderBricks=async function(){
    var app=document.getElementById('wontia-app');
    app.innerHTML='<div class="w-card" style="padding:14px 18px;margin-bottom:16px"><div style="font-size:13px;font-weight:700">Bricks — bloques funcionales de página</div><div style="font-size:11px;color:var(--w-muted);margin-top:4px;line-height:1.7">Cada brick es un módulo reutilizable (hero, planes, FAQ, testimonios…) que se coloca dentro de una página como <strong>Section</strong>. Aquí ves todos los bricks disponibles en el sistema. Para usar uno: <strong>Pages → página → Sections → + Add Section</strong>. Los bricks de código abierto/instalables se gestionan en <strong>BrickHub</strong>.</div></div><div id="brick-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px"></div>';
    var d=await W.api('/api/v1/admin/bricks');
    var bricks=d.data||{};
    var grid=document.getElementById('brick-grid');
    var html='';
    for(var id in bricks){
        var b=bricks[id];
        html+='<div class="w-card" style="padding:20px"><div style="display:flex;align-items:center;gap:10px;margin-bottom:12px"><span style="font-size:20px">&#x1F9F1;</span><div><div style="font-size:14px;font-weight:600">'+W.esc(b.name)+'</div><div style="font-size:10px;color:var(--w-muted);text-transform:uppercase">'+W.esc(b.category||'general')+' v'+W.esc(b.version||'1.0')+'</div></div></div><div style="margin-bottom:12px;min-height:60px">'+b.adminPreview+'</div><div style="display:flex;gap:6px"><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.showBrickConfig(\''+W.esc(id)+'\')">Config</button><span style="font-size:10px;color:var(--w-muted);align-self:center">ID: '+W.esc(id)+'</span></div></div>';
    }
    if(!html)html='<div class="w-empty-state" style="grid-column:1/-1"><h3>No BRICKs found</h3><p>Install widgets in src/Widgets/</p></div>';
    grid.innerHTML=html;
};

W.showBrickConfig=async function(type){
    var d=await W.api('/api/v1/admin/bricks/'+type);
    var b=d.data||{};
    var fields='';
    (b.configSchema||[]).forEach(function(f){
        if(f.type==='text')fields+='<div class="w-form-group"><label class="w-label">'+W.esc(f.label)+'</label><input class="w-input" id="bc-'+W.esc(f.key)+'" value="'+W.esc(f.default||'')+'"/></div>';
        else if(f.type==='textarea')fields+='<div class="w-form-group"><label class="w-label">'+W.esc(f.label)+'</label><textarea class="w-textarea" id="bc-'+W.esc(f.key)+'">'+W.esc(f.default||'')+'</textarea></div>';
        else if(f.type==='html')fields+='<div class="w-form-group"><label class="w-label">'+W.esc(f.label)+' <span style="font-size:9px;color:var(--w-muted)">HTML</span></label><textarea class="w-textarea" id="bc-'+W.esc(f.key)+'" style="min-height:80px">'+W.esc(f.default||'')+'</textarea></div>';
        else if(f.type==='code')fields+=W.codeEditorField(f);
        else if(f.type==='select'){
            fields+='<div class="w-form-group"><label class="w-label">'+W.esc(f.label)+'</label><select class="w-select" id="bc-'+W.esc(f.key)+'">';
            var opts=f.options||{};
            for(var ok in opts)fields+='<option value="'+W.esc(ok)+'">'+W.esc(opts[ok])+'</option>';
            fields+='</select></div>';
        }
        else if(f.type==='toggle'){
            fields+='<div class="w-form-group"><label class="w-label" style="display:flex;align-items:center;gap:10px;cursor:pointer"><input type="checkbox" id="bc-'+W.esc(f.key)+'" style="width:auto"/> <span>'+W.esc(f.label)+'</span></label></div>';
        }
    });
    W.modal(b.meta.name+' <span style="font-size:10px;color:var(--w-muted)">BRICK Configuration Schema</span>',fields,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Close</button>');
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

W.renderBrickHub=function(){
    var tab=W.state.bhTab||'marketplace';
    W.state.bhTab=tab;
    var app=document.getElementById('wontia-app');
    var tabs=[
        {id:'marketplace',label:'Marketplace'},
        {id:'sources',label:'Sources'},
        {id:'installed',label:'Installed'},
        {id:'updates',label:'Updates'},
        {id:'sites',label:'Sites'},
        {id:'history',label:'History'}
    ];
    var tabBar='<div class="w-card" style="padding:14px 18px;margin-bottom:14px"><div style="font-size:13px;font-weight:700">BrickHub — tienda y actualizador de extensiones</div><div style="font-size:11px;color:var(--w-muted);margin-top:4px;line-height:1.7">Conecta repositorios de GitHub (<strong>Sources</strong>) que publican bricks. Desde aquí puedes <strong>descubrir</strong> nuevos bricks, <strong>instalarlos</strong> en este sitio (<strong>Marketplace/Installed</strong>), y recibir <strong>Updates</strong> cuando los repos publican versiones nuevas. Cuando haya una actualización pendiente verás un aviso en el sidebar. Todo se instala con validación de seguridad y registro de historial.</div></div>';
    tabBar+='<div class="w-toolbar w-mb-lg" style="border-bottom:1px solid var(--w-border);padding-bottom:12px">';
    tabs.forEach(function(t){
        tabBar+='<button class="w-btn '+(tab===t.id?'w-btn-primary':'w-btn-secondary')+'" onclick="wontia.switchBHTab(\''+t.id+'\')">'+t.label+'</button>';
    });
    tabBar+='</div>';
    app.innerHTML='<div>'+tabBar+'<div id="bh-content"></div></div>';
    var fns={marketplace:W.bhMarketplace,sources:W.bhSources,installed:W.bhInstalled,updates:W.bhUpdates,sites:W.bhRegisteredSites,history:W.bhHistory};
    (fns[tab]||W.bhMarketplace)();
};

W.switchBHTab=function(t){W.state.bhTab=t;W.renderBrickHub()};

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
        var statusColor=h.status==='applied'?'var(--w-primary)':h.status==='failed'?'var(--w-accent)':h.status==='pending'?'var(--w-muted)':'#f59e0b';
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
        html+='<div class="w-empty-state"><h3>No registered sites</h3><p>Child WWI installations will appear here when they register with this mother installation.</p><div style="margin-top:16px;font-size:11px;color:var(--w-muted);background:var(--w-bg);padding:12px;border-radius:8px;text-align:left"><strong>How to connect a child site:</strong><br><br>1. On the child site admin, go to Settings<br>2. Set <code>BRICKHUB_MOTHER_URL</code> to <code id="bh-current-url" style="color:#B89EFF">'+window.location.origin+'</code><br>3. The child will auto-register on next admin login</div></div>'}
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
    if(r.ok){W.notify('Sync complete','success');W.renderBrickHub()}
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
    if(r.ok){W.notify('Installed: '+name,'success');W.renderBrickHub()}
};

W.bhUninstall=function(id,name){
    W.confirm('Uninstall "'+name+'"?',async function(){
        var r=await W.api('/api/v1/admin/brickhub/uninstall/'+id,{method:'DELETE'});
        if(r.ok){W.notify('Uninstalled: '+name,'success');W.renderBrickHub()}
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
    if(r.ok){W.notify('Updated!','success');W.renderBrickHub()}
};

W.bhApplyAllUpdates=function(){
    W.confirm('Apply all available updates? This may take a few minutes.',async function(){
        W.notify('Applying all updates...','info');
        var r=await W.api('/api/v1/admin/brickhub/updates/apply-all',{method:'POST'});
        if(r.ok)W.notify(r.applied+' updated, '+r.failed+' failed','success');
        W.renderBrickHub();
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

W.renderBrick=async function(tab){
    var m=W.state.brick;
    tab=tab||m.tab||'overview';
    m.tab=tab;
    var app=document.getElementById('wontia-app');
    var tabs=[['overview','Overview'],['providers','Providers'],['models','Models'],['policies','Policies'],['systems','Systems'],['usage','Usage & Cost'],['test','Test Model']];
    var bar='<div class="w-brick-tabs">';
    tabs.forEach(function(t){
        bar+='<button class="w-brick-tab'+(tab===t[0]?' active':'')+'" onclick="wontia.brickGo(\''+t[0]+'\')">'+t[1]+'</button>';
    });
    bar+='<div style="flex:1"></div><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.brickEnsure()">Setup Tables</button></div>';
    app.innerHTML='<div>'+bar+'<div id="brick-content"></div></div>';
    try{await W.brickLoad()}catch(e){}
    var fns={overview:W.brickOverview,providers:W.brickProviders,models:W.brickModels,policies:W.brickPolicies,systems:W.brickSystems,usage:W.brickUsage,test:W.brickTest};
    (fns[tab]||W.brickOverview)();
};

W.brickGo=function(t){location.hash='#brick/'+t};

W.brickEnsure=async function(){
    W.notify('Ensuring BRICK tables...','info');
    var r=await W.api('/api/v1/admin/brick/ensure-tables',{method:'POST'});
    if(r.ok)W.notify(r.message,'success');
    W.renderBrick();
};

W.bMoney=function(n){return '$'+Number(n||0).toFixed(4)};

W.bCap=function(list){
    var meta=(W.state.brick.meta&&W.state.brick.meta.data&&W.state.brick.meta.data.capabilities)||{};
    var out='';
    (list||[]).forEach(function(c){out+='<span class="w-brick-chip">'+W.esc(meta[c]||c)+'</span>'});
    return out;
};

W.bStatus=function(s){
    var map={success:['#00B87D','SUCCESS'],error:['#BE1341','ERROR'],fallback:['#B89EFF','FALLBACK'],budget_blocked:['#F5A623','BUDGET'],enabled:['#00B87D','ENABLED'],draft:['#8b8fa3','DISABLED'],active:['#00B87D','ACTIVE']};
    var c=map[s]||['#8b8fa3',(s||'')];
    return '<span class="w-badge" style="background:'+c[0]+'22;color:'+c[0]+'">'+c[1]+'</span>';
};

W.bDot=function(s){return '<span class="w-dot w-dot-'+(s||'disabled')+'"></span>'};

W.brickBars=function(rows){
    if(!rows||!rows.length)return '<div class="w-empty-state" style="padding:24px"><p>No data yet</p></div>';
    var max=Math.max.apply(null,rows.map(function(r){return r.cost}))||1;
    var html='';
    rows.forEach(function(r){
        var pct=Math.max(1,Math.round(r.cost/max*100));
        html+='<div style="margin-bottom:10px"><div class="w-flex-between" style="font-size:11px"><span><span class="w-dot" style="background:'+W.esc(r.color||'#B89EFF')+'"></span>'+W.esc(r.label)+'</span><span style="color:var(--w-muted)">'+W.bMoney(r.cost)+' &middot; '+W.num(r.tokens)+' tokens &middot; '+r.requests+' req</span></div><div class="w-brick-bar"><i style="width:'+pct+'%;background:'+W.esc(r.color||'#B89EFF')+'"></i></div></div>';
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
        var color=s.severity==='critical'?'#BE1341':s.severity==='warning'?'#F5A623':'#B89EFF';
        html+='<div class="w-brick-sugg" style="border-color:'+color+'"><div class="w-flex-between"><strong style="font-size:12px">'+W.esc(s.title)+'</strong><span style="font-size:9px;color:'+color+';font-weight:700">'+W.esc((s.severity||'').toUpperCase())+'</span></div><div style="font-size:11px;color:var(--w-muted);margin-top:4px">'+W.esc(s.detail)+'</div><button class="w-btn w-btn-secondary w-btn-sm" style="margin-top:8px" onclick="wontia.brickSuggAction(\''+W.esc(s.type)+'\')">'+W.esc(s.action)+'</button></div>';
    });
    return html;
};

W.brickSuggAction=function(type){
    if(type==='budget'||type==='fallback'||type==='cost')location.hash='#brick/policies';
    else if(type==='error')location.hash='#brick/test';
    else location.hash='#brick/models';
};

W.brickOverview=async function(){
    var el=document.getElementById('brick-content');
    el.innerHTML='<div style="text-align:center;padding:40px;color:var(--w-muted)">Loading AI infrastructure...</div>';
    var r=await W.api('/api/v1/admin/brick/overview?range=30d');
    if(!r.ok||!r.data){el.innerHTML='<div class="w-empty-state"><h3>BRICK is not initialized</h3><p>Create the AI infrastructure tables and seed data.</p><button class="w-btn w-btn-primary" onclick="wontia.brickEnsure()">Setup BRICK Tables</button></div>';return}
    var d=r.data,s=d.stats,b=d.budget;
    var budgetColor=b.status==='limit'?'#BE1341':b.status==='warning'?'#F5A623':'#00B87D';
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
        html+='<tr><td><span class="w-brick-chip" style="background:'+W.esc(p.color)+';color:#fff">'+W.esc(p.badge||'AI')+'</span> <strong>'+W.esc(p.name)+'</strong> <span style="font-size:10px;color:var(--w-muted)">'+W.esc(p.slug)+'</span></td><td style="font-size:10px">'+W.esc(p.adapter)+'</td><td>'+(p.has_key?'<span style="color:#00B87D">Configured</span>':'<span style="color:#F5A623">Missing</span>')+'</td><td>'+p.enabled_models+'</td><td>'+W.bStatus(p.status)+'</td><td><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.brickProviderForm('+p.id+')">Edit</button> <button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.brickToggleProvider('+p.id+')">Toggle</button> <button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.brickDeleteProvider('+p.id+')">Del</button></td></tr>';
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
    body+='<div class="w-flex" style="gap:12px"><div class="w-form-group" style="flex:1"><label class="w-label">Badge (2 letters)</label><input class="w-input" id="bp-badge" value="'+W.esc(p?p.badge:'AI')+'"/></div><div class="w-form-group" style="flex:1"><label class="w-label">Color</label><input class="w-input" id="bp-color" value="'+W.esc(p?p.color:'#9B8CDE')+'"/></div></div>';
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
        html+='<tr><td><span class="w-brick-chip" style="background:'+W.esc(m.color)+';color:#fff">'+W.esc(m.badge||'AI')+'</span> '+W.esc(m.display_name||m.name)+'</td><td style="font-size:10px">'+W.esc(m.model_identifier)+'</td><td>'+W.num(m.context_window)+'</td><td>'+m.input_cost+'</td><td>'+m.output_cost+'</td><td style="max-width:260px">'+W.bCap(m.capabilities)+'</td><td>'+m.priority+'</td><td>'+(m.enabled===1||m.enabled==='1'?'<span style="color:#00B87D">ON</span>':'<span style="color:var(--w-muted)">OFF</span>')+'</td><td><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.brickModelForm('+m.id+')">Edit</button> <button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.brickToggleModel('+m.id+')">Toggle</button> <button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.brickDeleteModel('+m.id+')">Del</button></td></tr>';
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
        body+='<label style="font-size:11px;padding:4px 10px;border-radius:6px;border:1px solid '+(on?'#B89EFF':'var(--w-border)')+';cursor:pointer;background:'+(on?'rgba(155,140,222,.12)':'transparent')+'"><input type="checkbox" class="bmf-cap" value="'+W.esc(c)+'" '+(on?'checked':'')+' style="margin-right:4px;width:auto"/>'+W.esc(caps[c])+'</label>';
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
        html+='<tr><td><strong>'+W.esc(p.system_id)+'</strong><span style="color:var(--w-muted)"> / '+W.esc(p.module)+' / '+W.esc(p.function_key)+'</span></td><td><span class="w-brick-chip">'+W.esc(p.strategy)+'</span></td><td style="font-size:10px">'+W.esc(p.primary_name||'-')+' → '+W.esc(p.fallback_name||'-')+(p.fallback2_name?' → '+W.esc(p.fallback2_name):'')+'</td><td>'+W.bCap(p.required_capabilities)+'</td><td>$'+p.monthly_budget+'</td><td>'+(p.is_active===1||p.is_active==='1'?'<span style="color:#00B87D">ON</span>':'<span style="color:var(--w-muted)">OFF</span>')+'</td><td><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.brickPolicyForm('+p.id+')">Edit</button> <button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.brickDeletePolicy('+p.id+')">Del</button></td></tr>';
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
        body+='<label style="font-size:11px;padding:4px 10px;border-radius:6px;border:1px solid '+(on?'#B89EFF':'var(--w-border)')+';cursor:pointer;background:'+(on?'rgba(155,140,222,.12)':'transparent')+'"><input type="checkbox" class="bpf-cap" value="'+W.esc(c)+'" '+(on?'checked':'')+' style="margin-right:4px;width:auto"/>'+W.esc(caps[c])+'</label>';
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
        html+='<div class="w-card" style="padding:16px"><div class="w-flex-between"><strong style="font-size:13px">'+W.esc(i.name)+'</strong><span class="w-brick-chip">'+W.esc(i.system_id)+'</span></div><div style="font-size:11px;color:var(--w-muted);margin-top:6px">'+W.esc(i.description||'')+'</div><div style="font-size:11px;margin-top:10px;color:#B89EFF">'+i.policy_count+' policies</div></div>';
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

W.renderFactory=async function(tab){
    var m=W.state.factory;
    tab=tab||m.tab||'inicio';
    m.tab=tab;
    var app=document.getElementById('wontia-app');
    var tabs=[['inicio','Inicio'],['sitios','Sitios'],['dominios','Dominios'],['emails','Emails'],['pedidos','Pedidos'],['saldos','Saldos'],['ia','Consumo IA'],['jobs','Jobs'],['planes','Planes'],['config','Config'],['margin','Margin Guard'],['system','Actualizaciones']];
    var bar='<div class="w-brick-tabs">';
    tabs.forEach(function(t){
        bar+='<button class="w-brick-tab'+(tab===t[0]?' active':'')+'" onclick="wontia.factoryGo(\''+t[0]+'\')">'+t[1]+'</button>';
    });
    bar+='<div style="flex:1"></div><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.factoryEnsure()">Setup Tables</button></div>';
    app.innerHTML='<div>'+bar+'<div id="factory-content"></div></div>';
    try{
        var pl=await W.api('/api/v1/admin/factory/plans');
        if(pl.data)m.plans=pl.data;
        var cf=await W.api('/api/v1/admin/factory/config');
        if(cf.data)m.config=cf.data;
        var st=await W.api('/api/v1/admin/factory/sites');
        if(st.data)m.sites=st.data;
        var dm=await W.api('/api/v1/admin/factory/domains');
        if(dm.data)m.domains=dm.data;
    }catch(e){}
    var fns={inicio:W.factoryInicio,sitios:W.factorySites,dominios:W.factoryDominios,emails:W.factoryEmails,pedidos:W.factoryPedidos,saldos:W.factorySaldos,ia:W.factoryIA,jobs:W.factoryJobs,plans:W.factoryPlans,config:W.factoryConfig,margin:W.factoryMargin,system:W.factorySystem};
    (fns[tab]||W.factoryInicio)();
};

W.factoryGo=function(t){location.hash='#factory/'+t};

W.factoryEnsure=async function(){
    W.notify('Ensuring factory tables...','info');
    var r=await W.api('/api/v1/admin/factory/ensure-tables',{method:'POST'});
    if(r.ok)W.notify(r.message,'success');
    W.renderFactory();
};

W.factoryPlans=function(){
    var el=document.getElementById('factory-content');
    var rows=W.state.factory.plans;
    var html='<div class="w-flex-between w-mb"><div style="font-size:12px;color:var(--w-muted)">Plans & pricing engine — nothing hardcoded. Bilingual ES/EN.</div><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.factoryPlanForm()">+ New Plan</button></div>';
    if(!rows.length){el.innerHTML=html+'<div class="w-empty-state"><p>No plans. Run Setup Tables or create one.</p></div>';return}
    html+='<table class="w-table"><tr><th>Plan</th><th>Price COP</th><th>Price USD</th><th>Billing</th><th>Margin</th><th>Active</th><th>Actions</th></tr>';
    rows.forEach(function(p){
        var m=p.margin||{};
        var mc=m.status==='critical'?'#BE1341':m.status==='warning'?'#F5A623':'#00B87D';
        html+='<tr><td><strong>'+W.esc(p.name_es)+'</strong> <span style="color:var(--w-muted)">/ '+W.esc(p.name_en)+'</span><div style="font-size:10px;color:var(--w-muted)">'+W.esc(p.slug)+'</div></td><td>$'+W.num(p.price_cop)+'</td><td>$'+p.price_usd+'</td><td><span class="w-brick-chip">'+W.esc(p.billing_type)+'</span></td><td style="color:'+mc+'">'+(m.margin_pct!=null?m.margin_pct+'%':'—')+'</td><td>'+(p.is_active===1?'<span style="color:#00B87D">ON</span>':'<span style="color:var(--w-muted)">OFF</span>')+'</td><td><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.factoryPlanForm('+p.id+')">Edit</button> <button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.factoryDeletePlan('+p.id+')">Del</button></td></tr>';
    });
    html+='</table>';
    html+='<div style="font-size:11px;color:var(--w-muted);margin-top:12px">Margin is computed live: price − internal costs (USD items × wwi.usd_cop_rate + % items) vs min_margin_pct.</div>';
    el.innerHTML=html;
};

W.factoryDeletePlan=function(id){
    W.confirm('Delete this plan?',async function(){
        var r=await W.api('/api/v1/admin/factory/plans/'+id,{method:'DELETE'});
        if(r.ok)W.renderFactory('plans');
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
    if(r.ok){W.closeModal();W.notify(r.message,'success');W.renderFactory('plans')}else if(r.message){W.notify(r.message,'error')}
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
    el.innerHTML=html;
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
        var color=m.status==='critical'?'#BE1341':m.status==='warning'?'#F5A623':'#00B87D';
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
    var map={PUBLISHED:['#00B87D'],ACTIVE:['#00B87D'],PAID:['#00B87D'],READY:['#00B87D'],DRAFT:['#8b8fa3'],CREATED:['#8b8fa3'],PENDING_PAYMENT:['#F5A623'],PROVISIONING:['#B89EFF'],REGISTERED:['#B89EFF'],DNS_PENDING:['#B89EFF'],GENERATING:['#B89EFF'],REQUESTED:['#F5A623'],EXPIRING:['#F5A623'],AVAILABLE:['#B89EFF'],SUSPENDED:['#BE1341'],FAILED:['#BE1341'],EXPIRED:['#BE1341'],CANCELLED:['#BE1341'],REFUNDED:['#BE1341'],ARCHIVED:['#8b8fa3'],DELETED:['#8b8fa3']};
    var c=map[s]||['#8b8fa3'];
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
        var color=x.status==='critical'?'#BE1341':x.status==='warning'?'#F5A623':'#00B87D';
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
        html+='<tr><td style="font-size:11px">'+W.esc(l.site_name||('#'+l.site_id))+'</td><td>'+(l.direction==='credit'?'<span style="color:#00B87D">CREDIT</span>':'<span style="color:#BE1341">DEBIT</span>')+'</td><td><strong>$'+W.num(l.amount)+'</strong></td><td style="font-size:11px">'+W.esc(l.reason||'')+'</td><td style="font-size:10px">'+W.esc(l.ref||'')+'</td><td style="font-size:10px">'+W.esc((l.created_at||'').slice(0,16))+'</td></tr>';
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
    var titleColor=available>0?'#B89EFF':'#00B87D';
    var html='<div style="margin-bottom:16px;padding:16px 18px;border:1px solid var(--w-border);border-left:3px solid '+titleColor+';border-radius:10px;background:var(--w-surface)"><div style="font-size:20px;font-weight:800;letter-spacing:-.01em">'+available+' actualizaci&oacute;n'+(available===1?'':'es')+' disponible'+(available===1?'':'s')+'</div><div style="font-size:12px;color:var(--w-muted);margin-top:4px">'+(available>0?'Hay mejoras listas. Los sitios reciben aviso por correo y se actualizan autom&aacute;ticamente.':'Sistema al d&iacute;a — no hay actualizaciones pendientes.')+'</div></div>';
    html+='<div class="w-card"><h3>Actualización desde Git</h3>';

    if(running){
        var pct=Math.max(0,Math.min(100,parseInt(st.pct)||0));
        var steps=[['clone','Descarga'],['sync','Sincronización'],['build','Build'],['recreate','Contenedores'],['health','Salud']];
        var stepIdx=0;steps.forEach(function(x,i){if(x[0]===st.step)stepIdx=i});
        var stepsHtml='';
        steps.forEach(function(x,i){
            var on=i<stepIdx, cur=i===stepIdx;
            stepsHtml+='<span class="mono" style="font-size:10px;padding:3px 8px;border-radius:6px;'+(cur?'background:rgba(34,211,238,.15);color:#B89EFF;font-weight:700':'color:var(--w-muted)')+'">'+(on?'✓ ':'')+W.esc(x[1])+'</span>';
        });
        html+='<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px"><span style="font-size:11px;font-weight:700;letter-spacing:.06em;color:#B89EFF">ACTUALIZANDO SISTEMA</span><span class="mono" style="font-size:18px;font-weight:700">'+pct+'%</span></div>';
        html+='<div style="height:8px;background:var(--w-border);border-radius:4px;overflow:hidden"><div style="height:100%;width:'+pct+'%;background:linear-gradient(90deg,#22d3ee,#8b5cf6);transition:width .5s ease"></div></div>';
        html+='<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px">'+stepsHtml+'</div>';
        html+='<div class="mono" style="font-size:11px;color:var(--w-muted);margin-top:10px">'+W.esc(st.message||'')+' &middot; '+W.num(st.elapsed_s||0)+'s transcurridos'+(st.eta_s?' &middot; ~'+W.num(st.eta_s)+'s restantes':'')+(st.containers_total?' &middot; contenedores '+W.num(st.containers_done||0)+'/'+W.num(st.containers_total):'')+'</div>';
        if(st.commit)html+='<div class="mono" style="font-size:10px;color:var(--w-muted);margin-top:4px">commit '+W.esc(st.commit)+'</div>';
        html+='</div>';
    }else if(st.status==='success'){
        html+='<div style="background:rgba(0,184,125,.08);border:1px solid rgba(0,184,125,.4);border-radius:10px;padding:18px;display:flex;gap:14px;align-items:center;margin-bottom:14px">'
            +'<div style="width:38px;height:38px;border-radius:10px;background:#00B87D;color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700">✓</div>'
            +'<div><div style="font-size:14px;font-weight:800;color:#00B87D;letter-spacing:.05em">SISTEMA ACTUALIZADO</div>'
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
    if(r.ok){res.innerHTML='<div style="color:#00B87D;font-size:12px">✓ '+W.esc(r.message)+'</div>';document.getElementById('brief-story').value='';W.briefLoad();}
    else res.innerHTML='<div style="color:var(--w-accent);font-size:12px">'+W.esc(r.message||'Error')+'</div>';
};

W.briefLoad=async function(){
    var el=document.getElementById('brief-list');if(!el)return;
    var r=await W.api('/api/v1/admin/factory/my-portal');
    var briefs=(r.data&&r.data.briefs)||[];
    el.innerHTML=briefs.map(function(b){
        var p=b.profile?JSON.parse(b.profile):null;
        var lines=p?('<div style="margin-top:6px;font-size:11px;color:var(--w-muted)">Servicios: '+(p.servicios||[]).join(', ')+'</div><div style="font-size:11px;color:var(--w-muted)">Ubicación: '+W.esc(p.ubicacion||'—')+'</div>'+(p.pendientes&&p.pendientes.length?'<div style="font-size:11px;color:#F5A623;margin-top:4px">Pendiente: '+p.pendientes.join(', ')+'</div>':'')):'';
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
        res.innerHTML='<div class="w-card" style="border-color:#F5A623;padding:14px"><div style="font-size:12px;color:#F5A623">⚠ '+W.esc(r.message||'')+'</div><div class="w-flex w-gap-sm" style="margin-top:10px"><button class="w-btn w-btn-primary w-btn-sm" onclick="wontia.tiaConfirm()">Confirmar</button><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.tiaCancel()">Cancelar</button></div></div>';
    }else if(r.ok){
        res.innerHTML='<div class="w-card" style="border-color:rgba(0,184,125,.5);padding:14px"><div style="font-size:12px;color:#00B87D">✓ '+W.esc(r.message||'Hecho')+'</div></div>';
        W.tiaLoadSections();W.tiaLoadHistory();
    }else{
        res.innerHTML='<div class="w-card" style="border-color:var(--w-accent);padding:14px"><div style="font-size:12px;color:var(--w-accent)">'+W.esc(r.message||'Error')+'</div></div>';
    }
    input.value='';
};

W.tiaConfirm=async function(){
    var res=document.getElementById('tia-result');
    var r=await W.api('/api/v1/admin/tia/confirm',{method:'POST',body:{token:W.state.tiaToken}});
    if(r.ok){res.innerHTML='<div class="w-card" style="border-color:rgba(0,184,125,.5);padding:14px"><div style="font-size:12px;color:#00B87D">✓ '+W.esc(r.message||'Ejecutado')+'</div></div>';W.tiaLoadSections();W.tiaLoadHistory();}
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

W.renderWWI=async function(){
    var app=document.getElementById('wontia-app');
    app.innerHTML='<div style="text-align:center;padding:50px;color:var(--w-muted)">Cargando sistema WWI...</div>';
    var st=(await W.api('/api/v1/admin/system/status')).data||{};
    var br=(await W.api('/api/v1/brick/overview')).data||{};
    var fd=(await W.api('/api/v1/admin/factory/dashboard')).data||{};
    var bh=(await W.api('/api/v1/admin/brickhub/notifications')).data||{};
    var s=br.stats||{};
    var color=st.status==='success'?'#00B87D':(st.status==='running'?'#B89EFF':(st.status==='rolled_back'?'#BE1341':'#8b8fa3'));
    var statusTxt=st.status==='running'?('Actualizando — '+(st.pct||0)+'%'):(st.status==='success'?'Sistema al día':(st.status==='rolled_back'?'Rollback aplicado':'Listo'));
    var html='<div style="margin-bottom:18px"><div style="font-size:20px;font-weight:800;letter-spacing:-.01em">WWI — Centro de Administración</div><div style="font-size:12px;color:var(--w-muted);margin-top:4px">Administra el SISTEMA completo (motor CMS, bricks, IA, sitios). Esto es distinto del contenido de tu landing.</div></div>';
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
        +'<button class="w-btn w-btn-primary" onclick="location.hash=\'#factory/system\'">Actualizar sistema desde Git</button>'
        +'<button class="w-btn w-btn-secondary" onclick="wontia.wwiSetup(\'brick\')">Preparar tablas de IA (BRICK)</button>'
        +'<button class="w-btn w-btn-secondary" onclick="wontia.wwiSetup(\'brickhub\')">Preparar tablas de BrickHub</button>'
        +'<button class="w-btn w-btn-secondary" onclick="wontia.wwiRunJobs()">Ejecutar trabajos pendientes (jobs)</button>'
        +'<button class="w-btn w-btn-secondary" onclick="location.hash=\'#factory/jobs\'">Ver cola de trabajos</button>'
        +'</div><div id="wwi-action-result" style="margin-top:10px"></div></div>';
    html+='<div class="w-card"><h3>¿Qué es cada menú?</h3><div style="font-size:12px;color:var(--w-muted);line-height:1.9">'
        +'<div><strong style="color:var(--w-text)">Pages</strong> — las páginas del sitio (ej. Home) y su contenido.</div>'
        +'<div><strong style="color:var(--w-text)">Sections</strong> — las secciones dentro de una página (hero, planes, FAQ…).</div>'
        +'<div><strong style="color:var(--w-text)">Bricks</strong> — bloques funcionales reutilizables para las páginas (widgets).</div>'
        +'<div><strong style="color:var(--w-text)">BrickHub</strong> — tienda de extensiones: conecta repos de GitHub y actualiza bricks instalados.</div>'
        +'<div><strong style="color:var(--w-text)">AI BRICK</strong> — la capa de IA del ecosistema: proveedores, modelos, políticas y costos.</div>'
        +'<div><strong style="color:var(--w-text)">Factory</strong> — el negocio: planes, pedidos, sitios de clientes, dominios, saldos.</div>'
        +'<div><strong style="color:var(--w-text)">Blog / Media / SEO / Analytics</strong> — contenido, imágenes, posicionamiento y métricas.</div>'
        +'<div><strong style="color:var(--w-text)">Settings / Users</strong> — configuración del sitio y cuentas con acceso.</div>'
        +'</div></div>';
    html+='</div>';
    app.innerHTML=html;
};

W.wwiSetup=async function(kind){
    var el=document.getElementById('wwi-action-result');
    if(el)el.innerHTML='<div style="font-size:11px;color:var(--w-muted)">Preparando…</div>';
    var url=kind==='brick'?'/api/v1/admin/brick/ensure-tables':'/api/v1/admin/brickhub/ensure-tables';
    var r=await W.api(url,{method:'POST'});
    if(el)el.innerHTML='<div style="font-size:11px;color:'+(r.ok?'#00B87D':'#BE1341')+'">'+W.esc(r.message||(r.ok?'Listo':'Error'))+'</div>';
};

W.wwiRunJobs=async function(){
    var el=document.getElementById('wwi-action-result');
    if(el)el.innerHTML='<div style="font-size:11px;color:var(--w-muted)">Ejecutando…</div>';
    var r=await W.api('/api/v1/admin/factory/jobs/run',{method:'POST'});
    if(el)el.innerHTML='<div style="font-size:11px;color:'+(r.ok?'#00B87D':'#BE1341')+'">'+W.esc(r.message||(r.ok?'Listo':'Error'))+'</div>';
};

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
    if(document.querySelector('.w-nav-item[data-panel="factory"]')&&!document.getElementById('w-jobs-badge')){
        var b=document.createElement('a');
        b.id='w-jobs-badge';
        b.className='w-jobs-badge';
        b.href='#factory/jobs';
        b.title='Trabajos pendientes';
        b.style.display='none';
        bar.insertBefore(b,bar.lastElementChild);
        W.refreshJobsBadge();
        setInterval(W.refreshJobsBadge,60000);
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
        {label:'Factory · Actualizaciones',hash:'#factory/updates',sub:'Actualizar sistema desde Git',need:'factory'},
        {label:'Factory · Jobs',hash:'#factory/jobs',sub:'Cola de trabajos y provisioning',need:'factory'},
        {label:'Factory · Sitios',hash:'#factory/sites',sub:'Lifecycle de sitios',need:'factory'},
        {label:'Factory · Dominios',hash:'#factory/domains',sub:'Estados de dominio',need:'factory'},
        {label:'Factory · Config',hash:'#factory/config',sub:'Planes, márgenes y pagos',need:'factory'},
        {label:'AI BRICK · Overview',hash:'#brick/overview',sub:'KPIs y presupuesto de IA',need:'brick'},
        {label:'AI BRICK · Policies',hash:'#brick/policies',sub:'Estrategias y fallback',need:'brick'},
        {label:'AI BRICK · Test',hash:'#brick/test',sub:'Probar un modelo',need:'brick'},
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

W.panels={
    dashboard:W.renderDashboard,
    wwi:W.renderWWI,
    pages:W.renderPageList,
    pageEditor:W.renderPageEditor,
    sections:W.renderSectionManager,
    pageSections:W.renderSectionManager,
    bricks:W.renderBricks,
    brickhub:W.renderBrickHub,
    brick:W.renderBrick,
    factory:W.renderFactory,
    portal:W.renderPortal,
    blog:W.renderBlogList,
    blogEditor:W.renderBlogEditor,
    media:W.renderMediaManager,
    seo:W.renderSeo,
    analytics:W.renderAnalytics,
    settings:W.renderSettings,
    users:W.renderUsers
};

(function(){
    var required=['dashboard','wwi','pages','sections','bricks','brickhub','brick','factory','blog','media','seo','analytics','settings','users'];
    var missing=required.filter(function(k){return !W.panels[k]});
    if(missing.length)console.error('WWI ADMIN ERROR - paneles faltantes:',missing);
})();

W.esc=function(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')};
W.num=function(n){return n!=null?n.toLocaleString():'0'};
W.slugify=function(t){return t.toLowerCase().replace(/[^a-z0-9\s-]/g,'').replace(/[\s_]+/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'')};

window.addEventListener('hashchange',function(){W.router()});
window.addEventListener('load',function(){W.mountTopbar();W.router();W.updateBHBadge();setInterval(W.updateBHBadge,300000)});
window.wontia=W;
})();
