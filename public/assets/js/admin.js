(function(){
var W={};

W.api=async function(url,options){
    options=options||{};
    var headers=options.headers||{};
    headers['Content-Type']=headers['Content-Type']||'application/json';
    if(W.token)headers['Authorization']='Bearer '+W.token;
    if(!(options.body instanceof FormData))options.body=JSON.stringify(options.body);
    var r=await fetch(url,Object.assign({},options,{headers:headers}));
    var d=await r.json();
    if(!r.ok&&d.message)W.notify(d.message,'error');
    return d;
};

W.router=function(){
    var hash=window.location.hash.slice(1)||'dashboard';
    var parts=hash.split('/');
    var panel=parts[0];
    var id=parts[1];
    var action=parts[2];
    document.querySelectorAll('.w-nav-item').forEach(function(a){a.classList.toggle('active',a.dataset.panel===panel)});
    var title=document.getElementById('panel-title');
    var titles={dashboard:'Dashboard',pages:'Pages',sections:'Sections',blog:'Blog',media:'Media',seo:'SEO',analytics:'Analytics',settings:'Settings',users:'Users'};
    if(title)title.textContent=titles[panel]||panel;
    var app=document.getElementById('wontia-app');
    if(!app)return;
    app.innerHTML='<div style="text-align:center;padding:60px;color:var(--w-muted)">Loading...</div>';
    try{
        var fn=W.panels[panel];
        if(fn)fn(id,action,parts);
        else if(panel==='pages'&&id&&!action)W.panels.pageEditor(id);
        else if(panel==='blog'&&id&&!action)W.panels.blogEditor(id);
        else if(panel==='sections'&&id)W.panels.pageSections(id);
        else W.notify('Panel not found','error');
    }catch(e){app.innerHTML='<div class="w-empty-state"><h3>Error</h3><p>'+e.message+'</p></div>';}
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

W.loadSections=async function(pageId){
    var d=await W.api('/api/v1/admin/pages/'+pageId);
    W.state.currentPage=d.data;
    var el=document.getElementById('section-panel');
    if(!el)return;
    var html='<div class="w-flex-between w-mb-lg"><div><h3 style="font-size:15px">Sections: '+W.esc(W.state.currentPage.title)+'</h3><span style="font-size:11px;color:var(--w-muted)">'+W.state.currentPage.sections.length+' sections</span></div><div class="w-flex w-gap-sm"><button class="w-btn w-btn-primary" onclick="wontia.showSectionTypePicker('+pageId+')">+ Add Section</button><a href="#pages" class="w-btn w-btn-secondary">Back</a></div></div>';
    if(!W.state.currentPage.sections.length){
        html+='<div class="w-empty-state"><h3>No sections yet</h3><p>Add your first section to this page</p></div>';
    }else{
        W.state.currentPage.sections.forEach(function(s,i){
            html+='<div class="w-card w-mb" draggable="true" data-sid="'+s.id+'" style="cursor:grab">';
            html+='<div class="w-flex-between w-mb"><div class="w-flex w-gap-sm"><span style="font-size:10px;color:var(--w-muted);background:var(--w-bg);padding:2px 8px;border-radius:4px">'+W.esc(s.type)+'</span><strong style="font-size:13px">'+W.esc(s.title||'Untitled')+'</strong></div><div class="w-flex w-gap-sm"><button class="w-btn w-btn-secondary w-btn-sm" onclick="wontia.editSection('+s.id+')">Edit</button><button class="w-btn w-btn-danger w-btn-sm" onclick="wontia.deleteSection('+s.id+','+pageId+')">Del</button></div></div>';
            if(s.subtitle)html+='<p style="font-size:12px;color:var(--w-muted);margin-bottom:6px">'+W.esc(s.subtitle)+'</p>';
            html+='</div>';
        });
    }
    el.innerHTML=html;
};

W.showSectionTypePicker=function(pageId){
    var types=['hero','features','cta','testimonials','pricing','stats','contact','faq','custom'];
    var opts=types.map(function(t){return '<button class="w-btn w-btn-secondary" onclick="wontia.addSection('+pageId+',\''+t+'\')">'+t+'</button>'}).join('');
    W.modal('Add Section','<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">'+opts+'</div>');
};

W.addSection=async function(pageId,type){
    W.closeModal();
    var r=await W.api('/api/v1/admin/pages/'+pageId+'/sections',{method:'POST',body:{type:type,title:type.charAt(0).toUpperCase()+type.slice(1)}});
    if(r.ok){W.loadSections(pageId);W.notify('Section added','success')}
};

W.editSection=async function(sectionId){
    var d=await W.api('/api/v1/admin/sections/'+sectionId);
    var s=d.data;
    var pageId=s.page_id;
    W.modal('Edit Section',
        '<div class="w-form-group"><label class="w-label">Title</label><input class="w-input" id="es-title" value="'+W.esc(s.title||'')+'"/></div>'+
        '<div class="w-form-group"><label class="w-label">Subtitle</label><textarea class="w-textarea" id="es-subtitle">'+W.esc(s.subtitle||'')+'</textarea></div>'+
        '<div class="w-form-group"><label class="w-label">Content (HTML)</label><textarea class="w-textarea" id="es-content" style="min-height:150px;font-family:monospace">'+W.esc(s.content||'')+'</textarea></div>'+
        '<div class="w-form-group"><label class="w-label">Image URL</label><input class="w-input" id="es-image" value="'+W.esc(s.image||'')+'"/></div>',
        '<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Cancel</button><button class="w-btn w-btn-primary" onclick="wontia.saveSection('+sectionId+','+pageId+')">Save</button>'
    );
};

W.saveSection=async function(id,pageId){
    var data={title:document.getElementById('es-title').value,subtitle:document.getElementById('es-subtitle').value,content:document.getElementById('es-content').value,image:document.getElementById('es-image').value};
    var r=await W.api('/api/v1/admin/sections/'+id,{method:'PUT',body:data});
    if(r.ok){W.closeModal();W.loadSections(pageId);W.notify('Section saved','success')}
};

W.deleteSection=function(id,pageId){
    W.confirm('Delete this section?',async function(){
        await W.api('/api/v1/admin/sections/'+id,{method:'DELETE'});
        W.loadSections(pageId);
        W.notify('Section deleted','success');
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
};

W.renderBricks=async function(){
    var app=document.getElementById('wontia-app');
    app.innerHTML='<div class="w-flex-between w-mb-lg"><h3>BRICK Hub — Widget Marketplace</h3></div><div id="brick-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px"></div>';
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
        else if(f.type==='code')fields+='<div class="w-form-group"><label class="w-label">'+W.esc(f.label)+'</label><textarea class="w-textarea" id="bc-'+W.esc(f.key)+'" style="min-height:150px;font-family:monospace">'+W.esc(f.default||'')+'</textarea></div>';
    });
    W.modal(b.meta.name+' <span style="font-size:10px;color:var(--w-muted)">BRICK Configuration Schema</span>',fields,'<button class="w-btn w-btn-secondary" onclick="wontia.closeModal()">Close</button>');
};

W.panels={
    dashboard:W.renderDashboard,
    pages:W.renderPageList,
    pageEditor:W.renderPageEditor,
    sections:W.renderSectionManager,
    pageSections:W.renderSectionManager,
    bricks:W.renderBricks,
    blog:W.renderBlogList,
    blogEditor:W.renderBlogEditor,
    media:W.renderMediaManager,
    seo:W.renderSeo,
    analytics:W.renderAnalytics,
    settings:W.renderSettings,
    users:W.renderUsers
};

W.esc=function(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')};
W.num=function(n){return n!=null?n.toLocaleString():'0'};
W.slugify=function(t){return t.toLowerCase().replace(/[^a-z0-9\s-]/g,'').replace(/[\s_]+/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'')};

window.addEventListener('hashchange',function(){W.router()});
window.addEventListener('load',function(){W.router()});
window.wontia=W;
})();
