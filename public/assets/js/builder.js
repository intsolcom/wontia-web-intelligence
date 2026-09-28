/* WWI INLINE BUILDER — editor visual de filas, columnas, slots y bloques (sin dependencias) */
(function () {
    'use strict';
    if (window.__WWI_BUILDER__) return;
    var token = null;
    try { token = localStorage.getItem('wwi_token') || null; } catch (e) { }
    if (!token || window.__WWI_PREVIEW__) return;
    var CTX = window.__WWI_EDIT_CTX__ || {};
    var PAGE_ID = parseInt(CTX.pageId || 0, 10);
    if (!PAGE_ID) return;

    var S = { on: false, tree: null, palette: null, sel: null, undo: [], redo: [], saving: false, ready: false, drag: null, rowDrag: null };
    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
    function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

    function api(url, opts) {
        opts = opts || {};
        opts.headers = opts.headers || {};
        opts.headers['Authorization'] = 'Bearer ' + token;
        if (opts.body && typeof opts.body !== 'string') { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(opts.body); }
        return fetch(url, opts).then(function (r) { return r.json(); });
    }

    function toast(msg, undoFn) {
        var el = $('#wb-toast');
        if (!el) { el = document.createElement('div'); el.id = 'wb-toast'; el.className = 'wb-toast'; document.body.appendChild(el); }
        el.innerHTML = esc(msg) + (undoFn ? ' <button type="button">Deshacer</button>' : '');
        el.classList.add('wb-show');
        if (undoFn) { var b = el.querySelector('button'); if (b) b.addEventListener('click', function () { undoFn(); el.classList.remove('wb-show'); }); }
        clearTimeout(el.__t); el.__t = setTimeout(function () { el.classList.remove('wb-show'); }, undoFn ? 4200 : 1800);
    }

    function setStatus(t) { var el = $('#wb-status'); if (el) el.textContent = t || ''; }

    function pushOp(op) { S.undo.push(op); if (S.undo.length > 40) S.undo.shift(); S.redo = []; }
    function undoOp() { if (!S.undo.length) { toast('Nada que deshacer'); return; } var op = S.undo.pop(); try { op.undo && op.undo(); } catch (e) { } S.redo.push(op); toast('Deshecho'); }
    function redoOp() { if (!S.redo.length) { toast('Nada que rehacer'); return; } var op = S.redo.pop(); try { op.redo && op.redo(); } catch (e) { } S.undo.push(op); toast('Rehecho'); }

    function showCrumb(block) {
        var old = $('#wb-crumb'); if (old) old.remove();
        if (!S.on || !block || !block.parentNode) return;
        var row = block.closest('.wwi-b-row');
        var col = block.closest('.wwi-b-col');
        var rows = $$('.wwi-b-row'); var ri = rows.indexOf(row) + 1;
        var cols = row ? $$(':scope > .wwi-b-row-inner > .wwi-b-col', row) : []; var ci = col ? cols.indexOf(col) + 1 : 1;
        var blocks = $$(':scope > .wwi-b-block', block.parentNode); var bi = blocks.indexOf(block) + 1;
        var label = block.getAttribute('data-brick') || block.getAttribute('data-type') || 'Bloque';
        var d = document.createElement('div');
        d.id = 'wb-crumb'; d.className = 'wb-crumb';
        d.innerHTML = '<span data-nav="row">Fila ' + ri + '</span><i>›</i><span data-nav="col">Col ' + ci + '</span><i>›</i><span data-nav="block" class="on">' + esc(label) + ' ' + bi + '</span><button type="button" title="Deseleccionar">✕</button>';
        document.body.appendChild(d);
        var r = block.getBoundingClientRect();
        d.style.top = Math.max(6, r.top - 30) + 'px';
        d.style.left = Math.max(6, r.left) + 'px';
        d.querySelectorAll('[data-nav]').forEach(function (seg) {
            seg.addEventListener('click', function () {
                var k = seg.getAttribute('data-nav');
                var el = k === 'row' ? row : (k === 'col' ? col : block);
                if (el) { try { el.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (e) { el.scrollIntoView(); } el.classList.add('wb-flash'); setTimeout(function () { el.classList.remove('wb-flash'); }, 900); }
            });
        });
        var x = d.querySelector('button'); if (x) x.addEventListener('click', function () { d.remove(); });
    }

    function copyBlock() {
        if (!S.sel) return;
        var f = findBlock(S.sel.id); if (!f) return;
        S.clip = { type: f.type, brick_slug: f.brick_slug || '', props: f.props || {}, styles: f.styles || {}, visibility: f.visibility || {} };
        toast('Bloque copiado');
    }

    function pasteBlock() {
        if (!S.clip || !S.sel) { toast('Nada que pegar'); return; }
        var cur = document.querySelector('.wwi-b-block[data-block="' + S.sel.id + '"]'); if (!cur) return;
        var slot = cur.parentNode; var sid = parseInt(slot.getAttribute('data-slot'), 10);
        var list = $$(':scope > .wwi-b-block', slot); var pos = list.indexOf(cur) + 1;
        var payload = { slot_id: sid, type: S.clip.brick_slug ? 'brick' : S.clip.type, position: pos, props: S.clip.props };
        if (S.clip.brick_slug) payload.brick_slug = S.clip.brick_slug;
        setStatus('Pegando…');
        api('/api/v1/admin/builder/blocks', { method: 'POST', body: payload }).then(function (r) {
            if (!r.ok) { toast(r.message || 'Error'); setStatus('Error'); return; }
            var newId = r.data && r.data.block_id;
            refreshCanvas(); toast('Pegado ✓'); setStatus('Guardado ✓');
            if (newId) pushOp({ undo: function () { return api('/api/v1/admin/builder/block/node/' + newId + '?page_id=' + PAGE_ID, { method: 'DELETE' }).then(function () { refreshCanvas(); }); } });
        });
    }

    function doDuplicate(block, id, slot) {
        setStatus('Duplicando…');
        api('/api/v1/admin/builder/blocks/' + id + '/duplicate', { method: 'POST' }).then(function (r) {
            if (!r.ok) { toast(r.message || 'No se pudo duplicar'); setStatus('Error'); return; }
            var newId = r.data && r.data.block_id;
            refreshCanvas(); toast('Duplicado ✓'); setStatus('Guardado ✓');
            if (newId) pushOp({
                undo: function () { return api('/api/v1/admin/builder/block/node/' + newId + '?page_id=' + PAGE_ID, { method: 'DELETE' }).then(function () { refreshCanvas(); }); },
                redo: function () { return api('/api/v1/admin/builder/blocks/' + id + '/duplicate', { method: 'POST' }).then(function (r2) { if (r2.ok) { newId = r2.data.block_id; refreshCanvas(); } }); }
            });
        });
    }

    // ── Barra del builder ──
    function bar() {
        if ($('#wb-bar')) return;
        var b = document.createElement('div');
        b.id = 'wb-bar'; b.className = 'wb-bar';
        b.innerHTML = '<button type="button" id="wb-toggle">🧱 Bloques</button>'
            + '<button type="button" id="wb-tree-btn">🌳 Estructura</button>'
            + '<span class="wb-dev" id="wb-dev"><button type="button" data-dev="desktop" class="on" title="Escritorio">🖥</button><button type="button" data-dev="tablet" title="Tablet">▭</button><button type="button" data-dev="mobile" title="Móvil">▯</button></span>'
            + '<span class="wb-status" id="wb-status"></span>'
            + '<button type="button" id="wb-comments-btn">💬 Comentarios</button>'
            + '<button type="button" id="wb-publish">Publicar</button>'
            + '<button type="button" id="wb-revs">Versiones</button>';
        document.body.appendChild(b);
        var tgl = $('#wb-toggle'); if (tgl) tgl.textContent = '✎ Editar sitio';
        $('#wb-toggle').addEventListener('click', toggle);
        $('#wb-tree-btn').addEventListener('click', function () { if (!S.on) { toast('Activa el modo edición'); return; } toggleTree(); });
        $('#wb-dev').addEventListener('click', function (e) { var t = e.target.closest('button[data-dev]'); if (!t) return; setDevice(t.getAttribute('data-dev')); });
        $('#wb-comments-btn').addEventListener('click', toggleComments);
        $('#wb-publish').addEventListener('click', function () {
            api('/api/v1/admin/builder/publish', { method: 'POST', body: { page_id: PAGE_ID, label: 'Publicacion manual' } }).then(function (r) {
                if (r.ok) toast('Publicado ✓'); else toast(r.message || 'Error al publicar');
            });
        });
        $('#wb-revs').addEventListener('click', showRevisions);
    }

    function toggle() {
        S.on = !S.on;
        document.body.classList.toggle('wb-on', S.on);
        var t = $('#wb-toggle'); if (t) t.classList.toggle('on', S.on);
        if (S.on) { load(); hint(); } else { closePanel(); }
    }

    function hint() {
        try { if (localStorage.getItem('wwi_wb_hint')) return; localStorage.setItem('wwi_wb_hint', '1'); } catch (e) { }
        if ($('#wb-hint')) return;
        var d = document.createElement('div');
        d.id = 'wb-hint'; d.className = 'wb-hint';
        d.innerHTML = '<b>Modo edición</b><span>Clic para <b>seleccionar</b> · arrastra <b>⣿</b> o usa <b>↑↓</b> para <b>mover</b> · <b>🗑</b>/<b>Supr</b> elimina · <b>Ctrl+D</b> duplica · <b>Ctrl+Z/Y</b> deshacer/rehacer · doble clic edita texto</span><button type="button" id="wb-hint-x">Entendido</button>';
        document.body.appendChild(d);
        var x = $('#wb-hint-x'); if (x) x.addEventListener('click', function () { d.remove(); });
        setTimeout(function () { if (d.parentNode) d.remove(); }, 10000);
    }

    function load() {
        setStatus('Cargando…');
        api('/api/v1/admin/builder/tree?page_id=' + PAGE_ID).then(function (r) {
            if (!r.ok) { setStatus(''); toast(r.message || 'Error al cargar'); return; }
            S.ready = r.ready !== false;
            S.tree = r.data;
            if (!S.tree.rows || !S.tree.rows.length) {
                banner();
                setStatus('');
                return;
            }
            decorate();
            setStatus((S.tree.rows.length) + ' filas');
            if ($('#wb-tree')) renderTree();
        });
        if (!S.palette) api('/api/v1/admin/builder/palette').then(function (r) { if (r.ok) S.palette = r.data; });
    }

    function refreshCanvas() {
        return Promise.all([
            api('/api/v1/admin/builder/tree?page_id=' + PAGE_ID),
            api('/api/v1/admin/builder/render?page_id=' + PAGE_ID)
        ]).then(function (res) {
            var t = res[0], r = res[1];
            if (t && t.ok) S.tree = t.data;
            var html = (r && r.ok && r.data && r.data.html) || '';
            var first = document.querySelector('main .wwi-b-row') || document.querySelector('.wwi-b-row');
            if (first && html) {
                var parent = first.parentNode;
                $$('.wwi-b-row', parent).forEach(function (n) { n.remove(); });
                var tmp = document.createElement('div'); tmp.innerHTML = html;
                Array.prototype.slice.call(tmp.children).forEach(function (n) { if (n.tagName === 'STYLE') return; parent.insertBefore(n, parent.firstChild); });
                $$('.wwi-b-slot').forEach(function (s) { ensureAdd(s); });
                decorate();
            } else { load(); }
            if ($('#wb-tree')) renderTree();
            setStatus('Actualizado ✓');
        }).catch(function () { load(); });
    }

    function setDevice(d) {
        S.device = d;
        document.body.classList.remove('wb-dev-desktop', 'wb-dev-tablet', 'wb-dev-mobile');
        document.body.classList.add('wb-dev-' + d);
        $$('#wb-dev button').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-dev') === d); });
    }
    function isLocked(id) { var f = findBlock(id); return !!(f && f.props && f.props._locked); }
    function loadTokens() {
        api('/api/v1/admin/settings').then(function (r) {
            if (r.ok && r.data && r.data.builder_tokens) { try { S.tokens = JSON.parse(r.data.builder_tokens) || []; } catch (e) { S.tokens = []; } }
        });
    }
    function toggleComments() {
        if (!S.on) { toast('Activa el modo edición'); return; }
        if (S.commentsOn) { S.commentsOn = false; removePins(); var p = $('#wb-comments-panel'); if (p) p.remove(); return; }
        S.commentsOn = true;
        loadComments();
    }
    function removePins() { $$('.wb-pin').forEach(function (p) { p.remove(); }); }
    function loadComments() {
        removePins();
        api('/api/v1/admin/builder/comments?page_id=' + PAGE_ID).then(function (r) {
            S.comments = (r && r.data) || [];
            var byBlock = {};
            S.comments.forEach(function (c) { (byBlock[c.block_id] = byBlock[c.block_id] || []).push(c); });
            Object.keys(byBlock).forEach(function (bid) {
                var b = document.querySelector('.wwi-b-block[data-block="' + bid + '"]'); if (!b) return;
                var pin = document.createElement('button'); pin.type = 'button'; pin.className = 'wb-pin';
                pin.textContent = '💬 ' + byBlock[bid].length; pin.title = 'Comentarios';
                b.appendChild(pin);
                pin.addEventListener('click', function (e) { e.stopPropagation(); openComments(bid, b); });
            });
            toast(S.comments.length + ' comentarios');
        });
    }
    function openComments(blockId, blockEl) {
        var old = $('#wb-comments-panel'); if (old) old.remove();
        var list = (S.comments || []).filter(function (c) { return String(c.block_id) === String(blockId); });
        var d = document.createElement('div'); d.id = 'wb-comments-panel'; d.className = 'wb-comments-panel';
        d.innerHTML = '<div class="wb-cp-head"><b>Comentarios</b><button type="button" id="wb-cp-x">✕</button></div>'
            + '<div class="wb-cp-list">' + (list.length ? list.map(function (c) {
                return '<div class="wb-cp-item' + (c.status === 'resolved' ? ' res' : '') + '"><div class="wb-cp-meta">' + esc(c.username || '—') + ' · ' + esc(c.created_at || '') + '</div><div>' + esc(c.body) + '</div><div class="wb-cp-acts"><button type="button" data-res="' + c.id + '">' + (c.status === 'resolved' ? 'Reabrir' : 'Resolver') + '</button><button type="button" data-del="' + c.id + '">Eliminar</button></div></div>';
            }).join('') : '<div style="font-size:11.5px;color:#9c96c4">Sin comentarios.</div>') + '</div>'
            + '<div class="wb-cp-add"><input type="text" id="wb-cp-input" placeholder="Escribe un comentario…"/><button type="button" id="wb-cp-send">Enviar</button></div>';
        document.body.appendChild(d);
        var r = blockEl.getBoundingClientRect();
        d.style.top = Math.max(10, r.bottom + 6) + 'px'; d.style.left = Math.max(10, Math.min(window.innerWidth - 300, r.left)) + 'px';
        $('#wb-cp-x').addEventListener('click', function () { d.remove(); });
        $('#wb-cp-send').addEventListener('click', function () {
            var v = $('#wb-cp-input').value; if (!v.trim()) return;
            api('/api/v1/admin/builder/comments', { method: 'POST', body: { block_id: blockId, page_id: PAGE_ID, body: v } }).then(function (x) { if (x.ok) { loadComments(); openComments(blockId, blockEl); } else toast(x.message || 'Error'); });
        });
        d.querySelectorAll('[data-res]').forEach(function (b) { b.addEventListener('click', function () { api('/api/v1/admin/builder/comments/' + b.getAttribute('data-res'), { method: 'PATCH', body: { status: 'resolved' } }).then(function () { loadComments(); openComments(blockId, blockEl); }); }); });
        d.querySelectorAll('[data-del]').forEach(function (b) { b.addEventListener('click', function () { api('/api/v1/admin/builder/comments/' + b.getAttribute('data-del'), { method: 'DELETE' }).then(function () { loadComments(); openComments(blockId, blockEl); }); }); });
    }

    function saveToken() {
        var color = prompt('Color del token (#RRGGBB):', (S.sel && S.sel.styles && S.sel.styles.background) || '#7c3cff');
        if (!color || !/^#[0-9a-fA-F]{3,8}$/.test(color)) return;
        var name = prompt('Nombre del token:', 'Color') || 'Color';
        S.tokens = S.tokens || [];
        S.tokens.push({ name: name, color: color });
        api('/api/v1/admin/settings', { method: 'PUT', body: { builder_tokens: JSON.stringify(S.tokens) } }).then(function (r) { if (r.ok) { toast('Token guardado'); renderTokens(); } else toast('Error'); });
    }
    function renderTokens() {
        var box = document.querySelector('#wb-panel #wb-tokens'); if (!box) return;
        var tk = S.tokens || [];
        box.innerHTML = tk.map(function (t, i) { return '<button type="button" class="wb-tok" title="' + esc(t.name) + ' · ' + esc(t.color) + '" data-tok="' + i + '" style="background:' + esc(t.color) + '"></button>'; }).join('')
            + '<button type="button" class="wb-tok wb-tok-add" id="wb-tok-add" title="Guardar el color actual como token">＋</button>';
        box.querySelectorAll('[data-tok]').forEach(function (b) {
            b.addEventListener('click', function () {
                var t = (S.tokens || [])[parseInt(b.getAttribute('data-tok'), 10)]; if (!t) return;
                api('/api/v1/admin/builder/blocks/' + S.sel.id, { method: 'PATCH', body: { styles: { background: t.color } } }).then(function (r) { if (r.ok) { toast('Token aplicado ✓'); closePanel(); refreshCanvas(); } });
            });
        });
        var add = box.querySelector('#wb-tok-add'); if (add) add.addEventListener('click', saveToken);
    }

    function loadBlockHistory() {
        var out = $('#wb-hist-out'); if (!out) return;
        out.innerHTML = '<div style="font-size:11px;color:#9c96c4">Cargando…</div>';
        api('/api/v1/admin/builder/blocks/' + S.sel.id + '/history').then(function (r) {
            var list = (r && r.data) || [];
            if (!list.length) { out.innerHTML = '<div style="font-size:11px;color:#9c96c4">Sin historial todavía.</div>'; return; }
            out.innerHTML = list.map(function (h) { return '<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:5px 0;border-bottom:1px solid rgba(183,140,255,.15);font-size:11.5px"><span>' + esc(h.username || '—') + ' · ' + esc(h.created_at || '') + '</span><button type="button" class="wb-btn wb-ghost" data-hist="' + h.id + '" style="padding:3px 8px;font-size:11px">Restaurar</button></div>'; }).join('');
            out.querySelectorAll('[data-hist]').forEach(function (b) {
                b.addEventListener('click', function () {
                    api('/api/v1/admin/builder/history/' + b.getAttribute('data-hist') + '/restore', { method: 'POST' }).then(function (x) {
                        if (x.ok) { toast('Versión restaurada ✓'); closePanel(); refreshCanvas(); } else toast(x.message || 'Error');
                    });
                });
            });
        });
    }

    function banner() {
        if ($('#wb-banner')) return;
        var d = document.createElement('div');
        d.id = 'wb-banner'; d.className = 'wb-banner';
        d.innerHTML = '<span>Esta página aún usa <b>secciones apiladas</b>. Conviértela a <b>filas y columnas</b> para editar por bloques (es reversible: se guarda una versión previa).</span>'
            + '<button type="button" class="wb-btn" id="wb-convert">Convertir a filas</button>'
            + '<button type="button" class="wb-btn wb-ghost" id="wb-cancel">Ahora no</button>';
        document.body.appendChild(d);
        $('#wb-cancel').addEventListener('click', function () { d.remove(); });
        $('#wb-convert').addEventListener('click', function () {
            this.disabled = true; this.textContent = 'Convirtiendo…';
            api('/api/v1/admin/builder/convert', { method: 'POST', body: { page_id: PAGE_ID } }).then(function (r) {
                if (r.ok) { toast('Convertida ✓ recargando…'); setTimeout(function () { location.reload(); }, 900); }
                else { toast(r.message || 'Error'); d.remove(); }
            });
        });
    }

    // ── Selección múltiple ──
    function clearMulti() {
        S.multi = [];
        $$('.wwi-b-block.wb-multi').forEach(function (b) { b.classList.remove('wb-multi'); });
        var bb = $('#wb-batch'); if (bb) bb.remove();
    }
    function toggleMulti(block) {
        if (!S.multi) S.multi = [];
        var id = parseInt(block.getAttribute('data-block'), 10);
        var i = S.multi.indexOf(id);
        if (i > -1) { S.multi.splice(i, 1); block.classList.remove('wb-multi'); }
        else { S.multi.push(id); block.classList.add('wb-multi'); }
        batchBar();
    }
    function batchBar() {
        var old = $('#wb-batch'); if (old) old.remove();
        if (!S.multi || S.multi.length < 2) return;
        var d = document.createElement('div'); d.id = 'wb-batch'; d.className = 'wb-batch';
        d.innerHTML = '<b>' + S.multi.length + ' seleccionados</b>'
            + '<button type="button" data-b="dup">⧉ Duplicar</button>'
            + '<button type="button" data-b="del">🗑 Eliminar</button>'
            + '<button type="button" data-b="hide">👁 Ocultar/Mostrar</button>'
            + '<button type="button" data-b="clear">✕</button>';
        document.body.appendChild(d);
        d.addEventListener('click', function (e) {
            var btn = e.target.closest('button[data-b]'); if (!btn) return;
            var a = btn.getAttribute('data-b');
            if (a === 'clear') clearMulti();
            else if (a === 'del') batchDelete();
            else if (a === 'dup') batchDuplicate();
            else if (a === 'hide') batchHide();
        });
    }
    function batchDelete() {
        var ids = S.multi.slice();
        setStatus('Eliminando…');
        Promise.all(ids.map(function (id) { return api('/api/v1/admin/builder/block/node/' + id + '?page_id=' + PAGE_ID, { method: 'DELETE' }); }))
            .then(function () { clearMulti(); toast(ids.length + ' eliminados · espacio disponible'); refreshCanvas(); });
    }
    function batchDuplicate() {
        var ids = S.multi.slice();
        setStatus('Duplicando…');
        Promise.all(ids.map(function (id) { return api('/api/v1/admin/builder/blocks/' + id + '/duplicate', { method: 'POST' }); }))
            .then(function () { clearMulti(); toast(ids.length + ' duplicados'); refreshCanvas(); });
    }
    function batchHide() {
        var ids = S.multi.slice();
        var f = findBlock(ids[0]);
        var hide = !(f && f.visibility && f.visibility.hide_desktop);
        setStatus('Actualizando…');
        Promise.all(ids.map(function (id) { return api('/api/v1/admin/builder/blocks/' + id, { method: 'PATCH', body: { visibility: { hide_desktop: hide } } }); }))
            .then(function () { clearMulti(); toast(hide ? 'Ocultos en escritorio' : 'Visibles'); refreshCanvas(); });
    }
    function marqueeInit() {
        if (S.__marq) return; S.__marq = 1;
        var box = null, sx = 0, sy = 0;
        document.addEventListener('mousedown', function (e) {
            if (!S.on || e.button !== 0) return;
            if (e.target.closest('.wwi-b-block, .wb-tools, .wb-panel, .wb-bar, .wb-tree, .wb-crumb, .wb-batch, .wb-modal')) return;
            sx = e.clientX; sy = e.clientY;
            box = document.createElement('div'); box.className = 'wb-marquee'; box.id = 'wb-marquee';
            document.body.appendChild(box);
            function move(ev) {
                if (!box) return;
                var x = Math.min(sx, ev.clientX), y = Math.min(sy, ev.clientY);
                box.style.left = x + 'px'; box.style.top = y + 'px';
                box.style.width = Math.abs(ev.clientX - sx) + 'px'; box.style.height = Math.abs(ev.clientY - sy) + 'px';
            }
            function up() {
                document.removeEventListener('mousemove', move); document.removeEventListener('mouseup', up);
                var r = box ? box.getBoundingClientRect() : null; if (box) box.remove(); box = null;
                if (!r || (r.width < 6 && r.height < 6)) return;
                clearMulti();
                $$('.wwi-b-block').forEach(function (b) {
                    var br = b.getBoundingClientRect();
                    if (!(br.right < r.left || br.left > r.right || br.bottom < r.top || br.top > r.bottom)) {
                        var id = parseInt(b.getAttribute('data-block'), 10);
                        if (S.multi.indexOf(id) < 0) { S.multi.push(id); b.classList.add('wb-multi'); }
                    }
                });
                batchBar();
            }
            document.addEventListener('mousemove', move);
            document.addEventListener('mouseup', up);
        });
    }

    // ── Navigator (árbol de estructura) ──
    function toggleTree() {
        var box = $('#wb-tree');
        if (box) { box.remove(); return; }
        box = document.createElement('div'); box.id = 'wb-tree'; box.className = 'wb-tree';
        document.body.appendChild(box);
        renderTree();
    }
    function renderTree() {
        var box = $('#wb-tree'); if (!box) return;
        var rows = (S.tree && S.tree.rows) || [];
        var h = '<div class="wb-tree-head"><b>Estructura</b><button type="button" id="wb-tree-x">✕</button></div><div class="wb-tree-body">';
        if (!rows.length) h += '<div class="wb-tree-empty">Sin filas.</div>';
        rows.forEach(function (row, ri) {
            h += '<div class="wb-tn wb-tn-row" draggable="true" data-row="' + row.id + '"><span class="wb-tn-ic">▦</span>Fila ' + (ri + 1) + '</div>';
            (row.columns || []).forEach(function (col, ci) {
                h += '<div class="wb-tn wb-tn-col"><span class="wb-tn-ic">▭</span>Columna ' + (ci + 1) + '</div>';
                (col.slots || []).forEach(function (slot) {
                    (slot.blocks || []).forEach(function (b) {
                        h += '<div class="wb-tn wb-tn-block" draggable="true" data-block="' + b.id + '" data-slot="' + slot.id + '"><span class="wb-tn-ic">▪</span>' + esc(b.brick_slug || b.type) + '</div>';
                    });
                });
            });
        });
        h += '</div>';
        box.innerHTML = h;
        var x = $('#wb-tree-x'); if (x) x.addEventListener('click', function () { box.remove(); });
        bindTree();
    }
    function treeScrollTo(el) { if (!el) return; try { el.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (e) { el.scrollIntoView(); } el.classList.add('wb-flash'); setTimeout(function () { el.classList.remove('wb-flash'); }, 900); }
    function blockIndexInSlot(slotId, blockId) {
        var pos = 0;
        ((S.tree && S.tree.rows) || []).forEach(function (row) { (row.columns || []).forEach(function (col) { (col.slots || []).forEach(function (s) { if (s.id === slotId) { (s.blocks || []).forEach(function (b, i) { if (b.id === blockId) pos = i; }); } }); }); });
        return pos;
    }
    function bindTree() {
        var box = $('#wb-tree'); if (!box) return;
        box.querySelectorAll('.wb-tn-block').forEach(function (it) {
            it.addEventListener('click', function () { var b = document.querySelector('.wwi-b-block[data-block="' + it.getAttribute('data-block') + '"]'); if (b) { select(b); treeScrollTo(b); } });
            it.addEventListener('dragstart', function () { S.treeDrag = { kind: 'block', id: parseInt(it.getAttribute('data-block'), 10) }; });
            it.addEventListener('dragover', function (e) { if (S.treeDrag && S.treeDrag.kind === 'block') { e.preventDefault(); it.classList.add('wb-tn-over'); } });
            it.addEventListener('dragleave', function () { it.classList.remove('wb-tn-over'); });
            it.addEventListener('drop', function (e) {
                it.classList.remove('wb-tn-over');
                if (!S.treeDrag || S.treeDrag.kind !== 'block') return;
                e.preventDefault();
                var dragId = S.treeDrag.id; S.treeDrag = null;
                var targetId = parseInt(it.getAttribute('data-block'), 10);
                if (dragId === targetId) return;
                var slotId = parseInt(it.getAttribute('data-slot'), 10);
                var pos = blockIndexInSlot(slotId, targetId);
                setStatus('Moviendo…');
                api('/api/v1/admin/builder/blocks/' + dragId + '/move', { method: 'POST', body: { slot_id: slotId, position: pos } }).then(function (r) {
                    if (r.ok) { toast('Movido ✓'); refreshCanvas(); } else { toast(r.message || 'Error'); setStatus('Error'); }
                });
            });
        });
        box.querySelectorAll('.wb-tn-row').forEach(function (it) {
            it.addEventListener('click', function () { var rowEl = document.querySelector('.wwi-b-row[data-row="' + it.getAttribute('data-row') + '"]'); if (rowEl) treeScrollTo(rowEl); });
            it.addEventListener('dragstart', function () { S.treeDrag = { kind: 'row', id: parseInt(it.getAttribute('data-row'), 10) }; });
            it.addEventListener('dragover', function (e) { if (S.treeDrag && S.treeDrag.kind === 'row') { e.preventDefault(); it.classList.add('wb-tn-over'); } });
            it.addEventListener('dragleave', function () { it.classList.remove('wb-tn-over'); });
            it.addEventListener('drop', function (e) {
                it.classList.remove('wb-tn-over');
                if (!S.treeDrag || S.treeDrag.kind !== 'row') return;
                e.preventDefault();
                var dragId = S.treeDrag.id; S.treeDrag = null;
                var targetId = parseInt(it.getAttribute('data-row'), 10);
                if (dragId === targetId) return;
                var ids = ((S.tree && S.tree.rows) || []).map(function (r) { return r.id; });
                var from = ids.indexOf(dragId), to = ids.indexOf(targetId);
                if (from < 0 || to < 0) return;
                ids.splice(from, 1); ids.splice(to, 0, dragId);
                setStatus('Moviendo…');
                api('/api/v1/admin/builder/reorder/row', { method: 'POST', body: { items: ids } }).then(function (r) {
                    if (r.ok) { toast('Fila movida ✓'); refreshCanvas(); } else { toast(r.message || 'Error'); setStatus('Error'); }
                });
            });
        });
    }

    // ── Decoración del canvas ──
    function decorate() {
        $$('.wwi-b-row').forEach(function (row, i) {
            if (!row.querySelector(':scope > .wb-row-tag')) {
                var t = document.createElement('span'); t.className = 'wb-row-tag'; t.textContent = 'Fila ' + (i + 1); row.appendChild(t);
                var acts = document.createElement('span'); acts.className = 'wb-row-acts';
                acts.innerHTML = '<button type="button" data-ra="add-row" title="Añadir fila debajo">+ Fila</button>'
                    + '<button type="button" data-ra="add-col" title="Añadir columna">+ Col</button>'
                    + '<button type="button" data-ra="del-row" title="Eliminar fila">🗑</button>';
                row.appendChild(acts);
                acts.addEventListener('click', function (e) {
                    var b = e.target.closest('button[data-ra]'); if (!b) return;
                    e.stopPropagation();
                    var a = b.getAttribute('data-ra');
                    if (a === 'add-row') addRow(row, i + 1);
                    else if (a === 'add-col') addColumn(row);
                    else if (a === 'del-row') deleteRow(row);
                });
            }
            $$(':scope > .wwi-b-row-inner > .wwi-b-col', row).forEach(function (col) {
                if (!col.querySelector(':scope > .wb-col-tag')) {
                    var c = document.createElement('span'); c.className = 'wb-col-tag'; c.textContent = col.getAttribute('data-span') + '/12'; col.appendChild(c);
                    var del = document.createElement('button'); del.type = 'button'; del.className = 'wb-col-del'; del.title = 'Eliminar columna'; del.textContent = '🗑';
                    col.appendChild(del);
                    del.addEventListener('click', function (e) { e.stopPropagation(); deleteColumn(col); });
                }
                if (!col.querySelector(':scope > .wb-col-resize')) {
                    var rz = document.createElement('span'); rz.className = 'wb-col-resize';
                    rz.addEventListener('pointerdown', function (e) { startColResize(e, col); });
                    col.appendChild(rz);
                }
                $$(':scope > .wwi-b-slot', col).forEach(function (slot) {
                    $$(':scope > .wwi-b-block', slot).forEach(function (block) { decorateBlock(block, slot); });
                    ensureAdd(slot);
                });
            });
        });
        // + Fila al final del árbol
        var lastRow = $$('.wwi-b-row').pop();
        if (lastRow && !$('#wb-add-row-end')) {
            var end = document.createElement('button');
            end.type = 'button'; end.id = 'wb-add-row-end'; end.className = 'wb-add wb-inline'; end.textContent = '+ Fila';
            end.title = 'Añadir una fila al final';
            end.addEventListener('click', function () { addRow(lastRow, 9999); });
            lastRow.parentNode.insertBefore(end, lastRow.nextSibling);
        }
        initRowDrag();
    }

    // Arrastre de filas completas (mover una sección de lugar)
    function initRowDrag() {
        $$('.wwi-b-row').forEach(function (row) {
            var tag = row.querySelector(':scope > .wb-row-tag');
            if (!tag || tag.__drag) return;
            tag.__drag = 1;
            tag.draggable = true;
            tag.style.cursor = 'grab';
            tag.title = 'Arrastra para mover esta sección de lugar';
            tag.addEventListener('dragstart', function (e) {
                S.rowDrag = parseInt(row.getAttribute('data-row'), 10);
                row.classList.add('wb-row-dragging');
                if (e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', 'row'); } catch (x) { } }
                e.stopPropagation();
            });
            tag.addEventListener('dragend', function () {
                row.classList.remove('wb-row-dragging');
                S.rowDrag = null;
                clearRowDrop();
            });
        });
        var rows = $$('.wwi-b-row');
        if (!rows.length) return;
        var parent = rows[0].parentNode;
        if (parent.__rowDrop) return;
        parent.__rowDrop = 1;
        parent.addEventListener('dragover', function (e) {
            if (!S.rowDrag) return;
            e.preventDefault();
            var list = $$('.wwi-b-row', parent);
            var after = null;
            for (var i = 0; i < list.length; i++) {
                var r = list[i].getBoundingClientRect();
                if (e.clientY < r.top + r.height / 2) { after = list[i]; break; }
            }
            clearRowDrop();
            var line = document.createElement('div');
            line.className = 'wb-row-drop';
            if (after) parent.insertBefore(line, after); else parent.appendChild(line);
        });
        parent.addEventListener('drop', function (e) {
            if (!S.rowDrag) return;
            e.preventDefault();
            var line = parent.querySelector('.wb-row-drop');
            var list = $$('.wwi-b-row', parent);
            var ids = list.map(function (r) { return parseInt(r.getAttribute('data-row'), 10); });
            var target = ids.length;
            if (line) {
                var next = line.nextElementSibling;
                while (next && !next.classList.contains('wwi-b-row')) next = next.nextElementSibling;
                target = next ? ids.indexOf(parseInt(next.getAttribute('data-row'), 10)) : ids.length;
            }
            clearRowDrop();
            var from = ids.indexOf(S.rowDrag);
            if (from < 0) return;
            var draggedId = S.rowDrag;
            ids.splice(from, 1);
            if (from < target) target--;
            ids.splice(target, 0, draggedId);
            S.rowDrag = null;
            var rowEl = document.querySelector('.wwi-b-row[data-row="' + draggedId + '"]');
            if (rowEl && rowEl.parentNode === parent) {
                var current = $$('.wwi-b-row', parent).filter(function (r) { return r !== rowEl; });
                parent.insertBefore(rowEl, current[target] || parent.querySelector('#wb-add-row-end') || null);
                $$('.wwi-b-row', parent).forEach(function (r, i) {
                    var t = r.querySelector(':scope > .wb-row-tag');
                    if (t) t.textContent = 'Fila ' + (i + 1);
                });
            }
            setStatus('Guardando…');
            api('/api/v1/admin/builder/reorder/row', { method: 'POST', body: { items: ids } }).then(function (r) {
                if (r.ok) { toast('Sección movida'); setStatus('Guardado ✓'); }
                else { toast(r.message || 'No se pudo mover'); setStatus('Error'); }
            });
        });
    }

    function clearRowDrop() { $$('.wb-row-drop').forEach(function (l) { l.remove(); }); }

    function addRow(refRow, position) {
        var pageRows = $$('.wwi-b-row');
        var pos = position >= 9999 ? pageRows.length : position;
        setStatus('Añadiendo fila…');
        api('/api/v1/admin/builder/rows', { method: 'POST', body: { page_id: PAGE_ID, position: pos, columns: 1 } }).then(function (r) {
            if (r.ok) { toast('Fila añadida'); setTimeout(function () { location.reload(); }, 250); }
            else { toast(r.message || 'Error al añadir fila'); setStatus('Error'); }
        });
    }

    function addColumn(row) {
        var rowId = parseInt(row.getAttribute('data-row'), 10);
        var cols = $$(':scope > .wwi-b-row-inner > .wwi-b-col', row).length;
        if (cols >= 4) { toast('Máximo 4 columnas por fila'); return; }
        var span = Math.max(2, Math.floor(12 / (cols + 1)));
        setStatus('Añadiendo columna…');
        api('/api/v1/admin/builder/columns', { method: 'POST', body: { row_id: rowId, span: span } }).then(function (r) {
            if (r.ok) { toast('Columna añadida'); setTimeout(function () { location.reload(); }, 250); }
            else { toast(r.message || 'Error'); }
        });
    }

    function deleteRow(row) {
        var rowId = parseInt(row.getAttribute('data-row'), 10);
        toast('Eliminando fila…');
        api('/api/v1/admin/builder/row/node/' + rowId + '?page_id=' + PAGE_ID, { method: 'DELETE' }).then(function (r) {
            if (r.ok) { row.remove(); toast('Fila eliminada'); setStatus('Eliminado'); }
            else toast(r.message || 'Error');
        });
    }

    function deleteColumn(col) {
        var colId = parseInt(col.getAttribute('data-col'), 10);
        var row = col.parentNode;
        if ($$(':scope > .wwi-b-col', row).length <= 1) { toast('La fila necesita al menos una columna'); return; }
        api('/api/v1/admin/builder/column/node/' + colId + '?page_id=' + PAGE_ID, { method: 'DELETE' }).then(function (r) {
            if (r.ok) { col.remove(); toast('Columna eliminada'); }
            else toast(r.message || 'Error');
        });
    }

    // Redimensionado de columnas (arrastre del borde)
    function startColResize(e, col) {
        e.preventDefault(); e.stopPropagation();
        var row = col.parentNode;
        var cols = $$(':scope > .wwi-b-col', row);
        var idx = cols.indexOf(col);
        var next = cols[idx + 1];
        if (!next) { toast('No hay columna a la derecha'); return; }
        var rowW = row.getBoundingClientRect().width || 1;
        var startX = e.clientX;
        var s1 = parseInt(col.getAttribute('data-span'), 10);
        var s2 = parseInt(next.getAttribute('data-span'), 10);
        var total = Math.min(12, s1 + s2);
        col.style.transition = 'none'; next.style.transition = 'none';
        function move(ev) {
            var dx = ev.clientX - startX;
            var delta = Math.round((dx / rowW) * 12);
            var n1 = Math.max(2, Math.min(total - 2, s1 + delta));
            var n2 = total - n1;
            col.style.flexBasis = ((n1 / 12) * 100) + '%'; col.style.maxWidth = ((n1 / 12) * 100) + '%';
            next.style.flexBasis = ((n2 / 12) * 100) + '%'; next.style.maxWidth = ((n2 / 12) * 100) + '%';
            col.__n1 = n1; col.__n2 = n2;
        }
        function up() {
            document.removeEventListener('pointermove', move);
            document.removeEventListener('pointerup', up);
            col.style.transition = ''; next.style.transition = '';
            var n1 = col.__n1, n2 = col.__n2;
            if (!n1 || n1 === s1) return;
            api('/api/v1/admin/builder/column/node/' + col.getAttribute('data-col'), { method: 'PATCH', body: { span: n1 } })
                .then(function () { return api('/api/v1/admin/builder/column/node/' + next.getAttribute('data-col'), { method: 'PATCH', body: { span: n2 } }); })
                .then(function () {
                    col.setAttribute('data-span', n1); next.setAttribute('data-span', n2);
                    var t1 = col.querySelector('.wb-col-tag'), t2 = next.querySelector('.wb-col-tag');
                    if (t1) t1.textContent = n1 + '/12';
                    if (t2) t2.textContent = n2 + '/12';
                    toast('Columnas ' + n1 + '/' + n2);
                });
        }
        document.addEventListener('pointermove', move);
        document.addEventListener('pointerup', up);
    }

    function decorateBlock(block, slot) {
        if (block.querySelector(':scope > .wb-tools')) return;
        var type = block.getAttribute('data-type');
        var brick = block.getAttribute('data-brick') || '';
        var label = brick ? brick : ({ text: 'Texto', image: 'Imagen', button: 'Botón', video: 'Video', divider: 'Separador', spacer: 'Espacio', html: 'HTML' }[type] || type);
        var tools = document.createElement('div');
        tools.className = 'wb-tools';
        tools.innerHTML = '<button type="button" class="wb-handle" draggable="true" title="Mover">⣿</button>'
            + '<span class="wb-label">' + esc(label) + '</span>'
            + '<button type="button" data-a="up" title="Subir">↑</button>'
            + '<button type="button" data-a="down" title="Bajar">↓</button>'
            + '<button type="button" data-a="dup" title="Duplicar">⧉</button>'
            + '<button type="button" data-a="del" title="Eliminar">🗑</button>';
        block.appendChild(tools);
        var handle = tools.querySelector('.wb-handle');
        if (handle) handle.setAttribute('draggable', 'true');
        if (isLocked(parseInt(block.getAttribute('data-block'), 10))) { if (handle) handle.setAttribute('draggable', 'false'); block.classList.add('wb-locked'); }
        block.addEventListener('dragstart', function (e) {
            S.drag = { id: parseInt(block.getAttribute('data-block'), 10), from: slot.getAttribute('data-slot') };
            block.classList.add('wb-dragging');
            if (e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', 'block'); } catch (x) { } }
        });
        block.addEventListener('dragend', function () { block.classList.remove('wb-dragging'); S.drag = null; clearDropLines(); });
        tools.addEventListener('click', function (e) {
            var btn = e.target.closest('button[data-a]'); if (!btn) return;
            e.stopPropagation();
            var id = parseInt(block.getAttribute('data-block'), 10);
            var act = btn.getAttribute('data-a');
            if (act === 'del') removeBlock(block, id, slot);
            else if (act === 'dup') doDuplicate(block, id, slot);
            else if (act === 'up' || act === 'down') moveSibling(block, act === 'up' ? -1 : 1);
        });
        block.addEventListener('click', function (e) {
            if (!S.on) return;
            if (e.target.closest('.wb-tools')) return;
            e.preventDefault(); e.stopPropagation();
            if (e.shiftKey) { toggleMulti(block); return; }
            clearMulti(); select(block);
        }, true);
        block.addEventListener('submit', function (e) {
            if (S.on) { e.preventDefault(); e.stopPropagation(); }
        }, true);
        block.addEventListener('mousedown', function (e) {
            if (!S.on) return;
            if (e.target.closest('.wb-tools') || e.target.closest('[contenteditable="true"]')) return;
            var inter = e.target.closest('a,button,input,select,textarea,label');
            if (inter) e.preventDefault();
        }, true);
        block.addEventListener('dblclick', function (e) {
            if (!S.on) return;
            var textEl = block.querySelector('.wwi-b-text');
            if (textEl) { e.preventDefault(); e.stopPropagation(); inlineEdit(block, textEl); return; }
            var link = block.querySelector('a.btn');
            if (link) {
                e.preventDefault(); e.stopPropagation();
                var lbl = prompt('Texto del botón:', link.textContent);
                if (lbl === null) return;
                var id = parseInt(block.getAttribute('data-block'), 10);
                api('/api/v1/admin/builder/blocks/' + id, { method: 'PATCH', body: { props: { label: lbl, href: link.getAttribute('href') || '#', style: 'primary' } } })
                    .then(function (r) { if (r.ok) { link.textContent = lbl; toast('Botón actualizado'); } });
            }
        });
    }

    // Edición de texto in situ (doble clic)
    function inlineEdit(block, el) {
        if (el.getAttribute('contenteditable') === 'true') return;
        var original = el.innerHTML;
        el.setAttribute('contenteditable', 'true');
        el.classList.add('wb-inline');
        el.focus();
        try {
            var sel = window.getSelection(); var range = document.createRange();
            range.selectNodeContents(el); range.collapse(false);
            sel.removeAllRanges(); sel.addRange(range);
        } catch (e) { }
        var mini = document.createElement('div');
        mini.className = 'wb-inline-bar';
        mini.innerHTML = '<span>Editando texto</span>'
            + '<button type="button" data-i="bold" title="Negrita"><b>B</b></button>'
            + '<button type="button" data-i="italic" title="Cursiva"><i>I</i></button>'
            + '<button type="button" data-i="h2" title="Título">H2</button>'
            + '<button type="button" data-i="p" title="Párrafo">P</button>'
            + '<button type="button" data-i="link" title="Enlace">🔗</button>'
            + '<button type="button" data-i="done">Listo</button>'
            + '<button type="button" data-i="cancel">Cancelar</button>';
        el.parentNode.insertBefore(mini, el);
        var finished = false;
        function finish(save) {
            if (finished) return; finished = true;
            el.removeAttribute('contenteditable');
            el.classList.remove('wb-inline');
            if (mini.parentNode) mini.remove();
            if (!save) { el.innerHTML = original; return; }
            if (el.innerHTML === original) return;
            var id = parseInt(block.getAttribute('data-block'), 10);
            setStatus('Guardando…');
            api('/api/v1/admin/builder/blocks/' + id, { method: 'PATCH', body: { props: { html: el.innerHTML } } }).then(function (r) {
                if (r.ok) { toast('Texto guardado ✓'); setStatus('Guardado ✓'); }
                else { toast(r.message || 'Error al guardar'); el.innerHTML = original; setStatus('Error'); }
            });
        }
        mini.addEventListener('mousedown', function (e) { e.preventDefault(); });
        mini.addEventListener('click', function (e) {
            var b = e.target.closest('button[data-i]'); if (!b) return;
            var a = b.getAttribute('data-i');
            if (a === 'done') finish(true);
            else if (a === 'cancel') finish(false);
            else if (a === 'link') { var u = prompt('URL del enlace:'); if (u) { el.focus(); document.execCommand('createLink', false, u); } }
            else if (a === 'h2') { el.focus(); document.execCommand('formatBlock', false, '<h2>'); }
            else if (a === 'p') { el.focus(); document.execCommand('formatBlock', false, '<p>'); }
            else { el.focus(); document.execCommand(a, false, null); }
        });
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { e.preventDefault(); finish(false); }
            else if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); finish(true); }
        });
        el.addEventListener('blur', function () { setTimeout(function () { if (!mini.contains(document.activeElement)) finish(true); }, 180); });
    }

    function ensureAdd(slot) {
        // El slot completo es zona de soltado con línea de inserción según la posición del cursor
        if (!slot.__wbDrop) {
            slot.__wbDrop = 1;
            slot.addEventListener('dragover', function (e) {
                if (!S.drag && !S.paletteDrag) return;
                e.preventDefault();
                var addBtn = slot.querySelector(':scope > .wb-add');
                if (addBtn) addBtn.classList.add('wb-drop');
                var line = slot.querySelector(':scope > .wb-drop-line') || document.createElement('div');
                line.className = 'wb-drop-line';
                var blocks = $$(':scope > .wwi-b-block', slot);
                var after = null;
                for (var i = 0; i < blocks.length; i++) {
                    var r = blocks[i].getBoundingClientRect();
                    if (e.clientY < r.top + r.height / 2) { after = blocks[i]; break; }
                }
                if (after) slot.insertBefore(line, after);
                else slot.insertBefore(line, addBtn || null);
                slot.__dropPos = after ? blocks.indexOf(after) : blocks.length;
            });
            slot.addEventListener('dragleave', function (e) {
                if (slot.contains(e.relatedTarget)) return;
                var addBtn = slot.querySelector(':scope > .wb-add');
                if (addBtn) addBtn.classList.remove('wb-drop');
                var l = slot.querySelector(':scope > .wb-drop-line'); if (l) l.remove();
            });
            slot.addEventListener('drop', function (e) {
                e.preventDefault();
                var addBtn = slot.querySelector(':scope > .wb-add');
                if (addBtn) addBtn.classList.remove('wb-drop');
                var l = slot.querySelector(':scope > .wb-drop-line'); if (l) l.remove();
                var slotId = parseInt(slot.getAttribute('data-slot'), 10);
                var pos = typeof slot.__dropPos === 'number' ? slot.__dropPos : 9999;
                if (S.paletteDrag) {
                    var p = S.paletteDrag; S.paletteDrag = null;
                    var payload = p.kind === 'brick'
                        ? { slot_id: slotId, type: 'brick', brick_slug: p.slug, props: {}, position: pos }
                        : { slot_id: slotId, type: p.type, props: defaultProps(p.type), position: pos };
                    setStatus('Insertando…');
                    api('/api/v1/admin/builder/blocks', { method: 'POST', body: payload }).then(function (r) {
                        if (r.ok) { toast('Bloque añadido'); location.reload(); } else { toast(r.message || 'Error'); setStatus('Error'); }
                    });
                    return;
                }
                if (!S.drag) return;
                var blockEl = document.querySelector('.wwi-b-block[data-block="' + S.drag.id + '"]');
                var pos = typeof slot.__dropPos === 'number' ? slot.__dropPos : 9999;
                if (blockEl) reparentBlock(blockEl, slot, pos);
                setStatus('Guardando…');
                api('/api/v1/admin/builder/blocks/' + S.drag.id + '/move', { method: 'POST', body: { slot_id: slotId, position: pos } })
                    .then(function (r) { if (r.ok) { toast('Bloque movido'); setStatus('Guardado ✓'); } else toast(r.message || 'Error'); });
                S.drag = null;
            });
        }
        var a = slot.querySelector(':scope > .wb-add');
        if (!a) {
            a = document.createElement('button');
            a.type = 'button'; a.className = 'wb-add';
            a.setAttribute('aria-label', 'Añadir bloque');
            a.title = 'Añadir bloque';
            a.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); openPalette(slot); });
            slot.appendChild(a);
        }
        var empty = !slot.querySelector(':scope > .wwi-b-block');
        a.classList.toggle('wb-empty', empty);
        a.innerHTML = empty ? '<span class="wb-add-ico">＋</span><em>Espacio disponible · haz clic para añadir un elemento</em>' : '+';
    }

    function clearDropLines() { $$('.wb-drop-line').forEach(function (l) { l.remove(); }); }

    // ── Selección y panel ──
    function select(block) {
        $$('.wwi-b-block.wb-sel').forEach(function (b) { b.classList.remove('wb-sel'); });
        block.classList.add('wb-sel');
        var id = parseInt(block.getAttribute('data-block'), 10);
        var found = findBlock(id);
        if (!found) return;
        S.sel = { id: id, type: block.getAttribute('data-type'), brick: block.getAttribute('data-brick') || '', props: found.props || {}, styles: found.styles || {}, block: block };
        showCrumb(block);
        openPanel();
    }

    function findBlock(id) {
        var out = null;
        (S.tree.rows || []).forEach(function (row) {
            (row.columns || []).forEach(function (col) {
                (col.slots || []).forEach(function (slot) {
                    (slot.blocks || []).forEach(function (b) { if (parseInt(b.id, 10) === id) out = b; });
                });
            });
        });
        return out;
    }

    function openPanel() {
        var p = $('#wb-panel');
        if (!p) {
            p = document.createElement('div'); p.id = 'wb-panel'; p.className = 'wb-panel';
            p.innerHTML = '<div class="wb-panel-head"><b id="wb-panel-title">Editar</b><button type="button" id="wb-panel-close">Cerrar</button></div><div class="wb-panel-body" id="wb-panel-body"></div>';
            document.body.appendChild(p);
            $('#wb-panel-close').addEventListener('click', closePanel);
        }
        p.classList.add('wb-open');
        renderPanel();
    }

    function closePanel() { var p = $('#wb-panel'); if (p) p.classList.remove('wb-open'); $$('.wwi-b-block.wb-sel').forEach(function (b) { b.classList.remove('wb-sel'); }); var c = $('#wb-crumb'); if (c) c.remove(); S.sel = null; }

    function renderPanel() {
        if (!S.sel) return;
        var body = $('#wb-panel-body'); var title = $('#wb-panel-title');
        var type = S.sel.type, props = S.sel.props || {};
        title.textContent = S.sel.brick || type;
        var curAlign = (S.sel.styles && S.sel.styles.text_align) || '';
        var h = '';
        if (type === 'text') {
            h += '<div class="wb-f"><label>Texto</label>' + richToolbar('wb-rte')
                + '<div class="wb-rte" id="wb-rte" contenteditable="true">' + (props.html || '') + '</div></div>';
            h += alignField(curAlign);
            h += tiaField();
        } else if (type === 'image') {
            h += field('URL de la imagen', '<input type="text" id="wb-p-url" value="' + esc(props.url || '') + '" placeholder="https://… o /assets/uploads/…"/>');
            h += '<div class="wb-actions"><button type="button" class="wb-btn wb-ghost" id="wb-p-media">Elegir de Media</button></div>';
            h += field('Texto alternativo (SEO)', '<input type="text" id="wb-p-alt" value="' + esc(props.alt || '') + '"/>');
            h += field('Pie de foto', '<input type="text" id="wb-p-caption" value="' + esc(props.caption || '') + '"/>');
            h += alignField(curAlign);
        } else if (type === 'button') {
            h += field('Texto', '<input type="text" id="wb-p-label" value="' + esc(props.label || '') + '"/>');
            h += field('Enlace', '<input type="text" id="wb-p-href" value="' + esc(props.href || '') + '" placeholder="https://… o #ancla"/>');
            h += field('Estilo', '<select id="wb-p-style">' + ['primary', 'secondary', 'ghost'].map(function (v) { return '<option value="' + v + '"' + (props.style === v ? ' selected' : '') + '>' + v + '</option>'; }).join('') + '</select>');
            h += alignField(curAlign);
        } else if (type === 'video') {
            h += field('URL (YouTube, Vimeo o MP4)', '<input type="text" id="wb-p-vurl" value="' + esc(props.url || '') + '"/>');
            h += field('Poster (opcional)', '<input type="text" id="wb-p-poster" value="' + esc(props.poster || '') + '"/>');
        } else if (type === 'spacer') {
            h += field('Altura (px)', '<input type="number" id="wb-p-height" value="' + esc(props.height || 40) + '" min="4" max="240"/>');
        } else if (type === 'brick') {
            h += '<div id="wb-p-brick"><div class="wb-f" style="color:#9c96c4;font-size:12px">Cargando editor de «' + esc(S.sel.brick) + '»…</div></div>';
            h += alignField(curAlign);
        } else {
            h += '<div class="wb-f"><label>Contenido HTML</label><textarea id="wb-p-html" spellcheck="false" style="font-family:JetBrains Mono,monospace;font-size:11.5px">' + esc(props.html || '') + '</textarea></div>';
        }
        var locked = !!(props._locked);
        h += '<div class="wb-f"><label>Estilo y tokens</label><div class="wb-tokens" id="wb-tokens"></div>'
            + '<div class="wb-actions" style="margin-top:8px"><button type="button" class="wb-btn wb-ghost" id="wb-style-copy">🎨 Copiar estilo</button><button type="button" class="wb-btn wb-ghost" id="wb-style-paste"' + (S.styleClip ? '' : ' disabled') + '>🖌 Pegar estilo</button></div></div>';
        h += '<div class="wb-f"><label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0"><input type="checkbox" id="wb-lock" style="width:auto" ' + (locked ? 'checked' : '') + '/> 🔒 Bloquear posición</label></div>';
        h += '<div class="wb-f"><div class="wb-actions" style="margin-top:0;gap:6px"><button type="button" class="wb-btn wb-ghost" id="wb-hist">🕘 Historial</button><button type="button" class="wb-btn wb-ghost" id="wb-comp-save">🧩 Guardar como componente</button></div><div id="wb-hist-out" style="margin-top:8px"></div></div>';
        h += '<div class="wb-f"><label>Visibilidad</label><div class="wb-vis">'
            + '<button type="button" data-v="desktop" class="on">🖥 Escritorio</button><button type="button" data-v="tablet" class="on">▭ Tablet</button><button type="button" data-v="mobile" class="on">▯ Móvil</button>'
            + '</div></div>';
        h += '<div class="wb-actions"><button type="button" class="wb-btn" id="wb-save">Guardar</button><button type="button" class="wb-btn wb-danger" id="wb-del">🗑 Eliminar</button><button type="button" class="wb-btn wb-ghost" id="wb-close2">Cerrar</button></div>';
        body.innerHTML = h;
        bindPanel();
        if (type === 'brick') loadBrickEditor(S.sel.brick, props);
    }

    function field(label, input) { return '<div class="wb-f"><label>' + label + '</label>' + input + '</div>'; }

    function richToolbar(forId) {
        return '<div class="wb-rte-tools" data-rt-for="' + forId + '">'
            + '<button type="button" data-c="bold" title="Negrita"><b>B</b></button>'
            + '<button type="button" data-c="italic" title="Cursiva"><i>I</i></button>'
            + '<button type="button" data-c="underline" title="Subrayado"><u>U</u></button>'
            + '<button type="button" data-c="strikeThrough" title="Tachado"><s>S</s></button>'
            + '<select data-size title="Tamaño de letra"><option value="">Tamaño</option><option value="2">Pequeño</option><option value="3">Normal</option><option value="5">Grande</option><option value="6">Muy grande</option></select>'
            + '<button type="button" data-c="formatBlock" data-v="h1" title="Título 1">H1</button>'
            + '<button type="button" data-c="formatBlock" data-v="h2" title="Título 2">H2</button>'
            + '<button type="button" data-c="formatBlock" data-v="h3" title="Título 3">H3</button>'
            + '<button type="button" data-c="formatBlock" data-v="p" title="Párrafo">P</button>'
            + '<button type="button" data-c="formatBlock" data-v="blockquote" title="Cita">❝</button>'
            + '<button type="button" data-c="insertUnorderedList" title="Lista con viñetas">•</button>'
            + '<button type="button" data-c="insertOrderedList" title="Lista numerada">1.</button>'
            + '<button type="button" data-c="justifyLeft" title="Alinear a la izquierda">Izq.</button>'
            + '<button type="button" data-c="justifyCenter" title="Centrar">Centro</button>'
            + '<button type="button" data-c="justifyRight" title="Alinear a la derecha">Der.</button>'
            + '<label title="Color de texto" style="display:inline-flex;align-items:center;gap:2px;font-size:10px">A<input type="color" data-color value="#7c3cff" style="width:22px;height:22px;padding:0;border:0;background:none;cursor:pointer"/></label>'
            + '<label title="Color de fondo" style="display:inline-flex;align-items:center;gap:2px;font-size:10px">▨<input type="color" data-bg value="#f3e8ff" style="width:22px;height:22px;padding:0;border:0;background:none;cursor:pointer"/></label>'
            + '<button type="button" data-c="createLink" title="Insertar enlace">🔗</button>'
            + '<button type="button" data-c="removeFormat" title="Limpiar formato">✕</button>'
            + '</div>';
    }

    function loadBrickEditor(slug, props) {
        var box = $('#wb-p-brick'); if (!box) return;
        if (S.schemas && S.schemas[slug]) { box.innerHTML = renderBrickFields(S.schemas[slug], props) + advancedJson(props); bpRepRefreshAll(); return; }
        api('/api/v1/admin/bricks/' + encodeURIComponent(slug)).then(function (r) {
            var schema = (r && r.data && r.data.configSchema) || [];
            S.schemas = S.schemas || {}; S.schemas[slug] = schema;
            var b = $('#wb-p-brick'); if (!b) return;
            b.innerHTML = renderBrickFields(schema, props) + advancedJson(props);
            bpRepRefreshAll();
        }).catch(function () { var b = $('#wb-p-brick'); if (b) b.innerHTML = advancedJson(props); });
    }

    function advancedJson(props) {
        return '<details style="margin-top:8px"><summary style="cursor:pointer;font-size:11px;color:#9c96c4">Avanzado (JSON)</summary><textarea id="wb-p-props" spellcheck="false" style="font-family:JetBrains Mono,monospace;font-size:11.5px;margin-top:6px;min-height:120px">' + esc(JSON.stringify(props, null, 2)) + '</textarea></details>';
    }

    function renderBrickFields(schema, props) {
        var h = '';
        (schema || []).forEach(function (f) {
            var t = f.type || 'text';
            var v = (props[f.key] !== undefined && props[f.key] !== null) ? props[f.key] : (f.default !== undefined ? f.default : '');
            var id = 'wbp-' + f.key;
            if (t === 'heading') { h += '<div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:#9c96c4;margin:14px 0 6px;border-top:1px solid rgba(183,140,255,.18);padding-top:8px">' + esc(f.label) + '</div>'; return; }
            if (t === 'text' || t === 'url' || t === 'link') h += field(esc(f.label), '<input type="text" id="' + id + '" value="' + esc(v) + '"/>');
            else if (t === 'textarea') h += field(esc(f.label), '<textarea id="' + id + '" style="min-height:84px">' + esc(v) + '</textarea>');
            else if (t === 'richtext') h += '<div class="wb-f"><label>' + esc(f.label) + '</label>' + richToolbar(id) + '<div class="wb-rte" id="' + id + '" contenteditable="true">' + (typeof v === 'string' ? v : '') + '</div></div>';
            else if (t === 'number') h += field(esc(f.label), '<input type="number" id="' + id + '" value="' + esc(v) + '"' + (f.min !== undefined ? ' min="' + f.min + '"' : '') + (f.max !== undefined ? ' max="' + f.max + '"' : '') + '/>');
            else if (t === 'range') h += field(esc(f.label) + ' <output id="' + id + '-out" style="float:right">' + esc(v) + '</output>', '<input type="range" id="' + id + '" min="' + (f.min !== undefined ? f.min : 0) + '" max="' + (f.max !== undefined ? f.max : 100) + '" step="' + (f.step !== undefined ? f.step : 1) + '" value="' + esc(v) + '" oninput="document.getElementById(\'' + id + '-out\').textContent=this.value"/>');
            else if (t === 'color') h += field(esc(f.label), '<input type="color" id="' + id + '" value="' + esc(/^#[0-9a-fA-F]{6}$/.test(String(v)) ? v : '#7c3cff') + '" style="width:46px;height:32px;padding:0;border:1px solid rgba(183,140,255,.25);border-radius:8px;background:none"/>');
            else if (t === 'toggle') h += '<label class="wb-f" style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0"><input type="checkbox" id="' + id + '" style="width:auto" ' + (v ? 'checked' : '') + '/> ' + esc(f.label) + '</label>';
            else if (t === 'select' || t === 'hlevel') { var o = ''; var opts = f.options || {}; for (var k in opts) o += '<option value="' + esc(k) + '"' + (String(v) === String(k) ? ' selected' : '') + '>' + esc(opts[k]) + '</option>'; h += field(esc(f.label), '<select id="' + id + '">' + o + '</select>'); }
            else if (t === 'repeater') h += renderBrickRepeater(f, Array.isArray(v) ? v : []);
            else h += field(esc(f.label) + ' (JSON)', '<textarea id="' + id + '" style="font-family:JetBrains Mono,monospace;font-size:11.5px">' + esc(typeof v === 'object' ? JSON.stringify(v) : v) + '</textarea>');
        });
        return h || '<div class="wb-f" style="color:#9c96c4;font-size:12px">Este widget no declara campos editables. Usa "Avanzado (JSON)".</div>';
    }

    function renderBrickRepeater(f, items) {
        S._brep = S._brep || {}; S._brepS = S._brepS || {};
        S._brepS[f.key] = f; S._brep[f.key] = items.map(function (it) { return (it && typeof it === 'object') ? it : {}; });
        return '<div class="wb-f" data-brept="' + esc(f.key) + '"><label>' + esc(f.label) + '</label><div id="wbp-rep-' + esc(f.key) + '"></div><button type="button" class="wb-btn wb-ghost" data-repadd="' + esc(f.key) + '" style="margin-top:6px">+ Añadir</button><textarea id="wbp-' + esc(f.key) + '" style="display:none"></textarea></div>';
    }

    function bpRepRefreshAll() { $$('#wb-panel [data-brept]').forEach(function (d) { bpRepRefresh(d.getAttribute('data-brept')); }); }

    function bpRepRefresh(key) {
        var f = (S._brepS || {})[key]; var box = document.getElementById('wbp-rep-' + key); if (!f || !box) return;
        var items = S._brep[key] || [];
        box.innerHTML = items.map(function (it, i) {
            var subs = (f.fields || []).map(function (sf) {
                var val = (it && it[sf.key] != null) ? it[sf.key] : '';
                var id = 'wbr-' + key + '-' + i + '-' + sf.key;
                var inp = (sf.type === 'textarea' || sf.type === 'richtext')
                    ? '<textarea data-i="' + i + '" data-k="' + esc(sf.key) + '" id="' + id + '" style="min-height:46px">' + esc(val) + '</textarea>'
                    : '<input type="text" data-i="' + i + '" data-k="' + esc(sf.key) + '" id="' + id + '" value="' + esc(val) + '"/>';
                return '<label style="display:block;font-size:10px;color:#9c96c4;margin-bottom:5px">' + esc(sf.label || sf.key) + inp + '</label>';
            }).join('');
            return '<div style="border:1px solid rgba(183,140,255,.2);border-radius:9px;padding:8px;margin-bottom:8px;background:#141130">'
                + '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;font-size:10px;color:#9c96c4"><b>#' + (i + 1) + '</b><span><button type="button" class="wb-btn wb-ghost" data-repup="' + key + '" data-i="' + i + '" style="padding:1px 6px">▲</button> <button type="button" class="wb-btn wb-ghost" data-repdn="' + key + '" data-i="' + i + '" style="padding:1px 6px">▼</button> <button type="button" class="wb-btn wb-danger" data-repdel="' + key + '" data-i="' + i + '" style="padding:1px 6px">✕</button></span></div>'
                + '<div>' + subs + '</div></div>';
        }).join('') || '<div style="font-size:11px;color:#9c96c4">Sin elementos.</div>';
    }

    function bpRepCollect(key) {
        var box = document.getElementById('wbp-rep-' + key); if (!box) return;
        box.querySelectorAll('[data-i][data-k]').forEach(function (inp) {
            var i = parseInt(inp.getAttribute('data-i'), 10), k = inp.getAttribute('data-k');
            S._brep[key] = S._brep[key] || [];
            if (!S._brep[key][i]) S._brep[key][i] = {};
            S._brep[key][i][k] = inp.value;
        });
    }

    function collectBrickFields(schema, base) {
        var out = {};
        (schema || []).forEach(function (f) {
            var t = f.type || 'text';
            if (t === 'heading') return;
            if (t === 'repeater') { bpRepCollect(f.key); out[f.key] = (S._brep[f.key] || []); return; }
            var el = document.getElementById('wbp-' + f.key); if (!el) return;
            if (t === 'toggle') out[f.key] = el.checked ? 1 : 0;
            else if (t === 'number') out[f.key] = el.value === '' ? '' : Number(el.value);
            else if (t === 'richtext') out[f.key] = (el.innerHTML || '');
            else out[f.key] = el.value;
        });
        for (var k in (base || {})) { if (!(k in out) && k.charAt(0) === '_') out[k] = base[k]; }
        return out;
    }
    function tiaField() {
        return '<div class="wb-f"><label>🤖 TIA · asistente contextual</label>'
            + '<input type="text" id="wb-ai-input" placeholder="Pídele algo: hazlo más breve, tono cercano…"/>'
            + '<div class="wb-actions" style="margin-top:6px;gap:5px"><button type="button" class="wb-btn wb-ghost" data-ai="improve">✨ Mejorar</button>'
            + '<button type="button" class="wb-btn wb-ghost" data-ai="shorten">✂ Acortar</button>'
            + '<button type="button" class="wb-btn wb-ghost" data-ai="expand">➕ Ampliar</button>'
            + '<button type="button" class="wb-btn wb-ghost" data-ai="en">🌐 EN</button>'
            + '<button type="button" class="wb-btn wb-ghost" data-ai="es">🇪🇸 ES</button>'
            + '<button type="button" class="wb-btn" id="wb-ai-run">Pedir ▸</button></div>'
            + '<div style="font-size:11px;color:#9c96c4;margin-top:6px">TIA reescribe este bloque y lo guarda.</div></div>';
    }

    function bindTia() {
        $$('#wb-panel [data-ai]').forEach(function (b) { b.addEventListener('click', function () { tiaRun(b.getAttribute('data-ai')); }); });
        var ar = $('#wb-ai-run'); if (ar) ar.addEventListener('click', function () { tiaRun(''); });
    }

    function tiaRun(mode) {
        var rte = $('#wb-rte');
        var cur = rte ? rte.innerHTML : ((S.sel && S.sel.props && S.sel.props.html) || '');
        var plain = String(cur).replace(/<[^>]+>/g, ' ').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim();
        if (!plain) { toast('No hay texto para la IA'); return; }
        var inp = $('#wb-ai-input');
        var instr = mode === 'improve' ? 'Mejora la redacción: más clara, natural y persuasiva; conserva el idioma y una longitud similar.'
            : mode === 'shorten' ? 'Acorta el texto conservando el mensaje clave.'
                : mode === 'expand' ? 'Amplía el texto con más detalle y valor, sin inventar datos.'
                    : mode === 'en' ? 'Traduce el texto al inglés.'
                        : mode === 'es' ? 'Traduce el texto al español.'
                            : ((inp && inp.value) || '');
        if (!instr) { toast('Escribe qué quieres o usa un botón'); return; }
        setStatus('TIA redactando…');
        api('/api/v1/admin/brick/request', {
            method: 'POST', body: {
                system_id: 'wontia', module: 'agent', function: 'builder_rewrite',
                system_prompt: 'Eres TIA, redactora de sitios web. Devuelve SOLO el texto resultante, sin comillas ni markdown.',
                messages: [{ role: 'user', content: instr + '\n\nTexto:\n' + plain }],
                max_tokens: 700, temperature: 0.6
            }
        }).then(function (r) {
            var out = String(((r && r.data && r.data.content) || '')).trim();
            if (!out) { toast('La IA no devolvió texto'); setStatus('Error'); return; }
            var pr = Object.assign({}, S.sel.props, { html: '<p>' + esc(out) + '</p>' });
            api('/api/v1/admin/builder/blocks/' + S.sel.id, { method: 'PATCH', body: { props: pr } }).then(function (x) {
                if (x.ok) { toast('Texto actualizado por TIA ✓'); closePanel(); refreshCanvas(); }
                else toast(x.message || 'Error');
            });
        }).catch(function () { toast('Error de IA'); setStatus('Error'); });
    }

    function alignField(cur) {
        cur = cur || '';
        var opts = [['', 'Heredar'], ['left', 'Izquierda'], ['center', 'Centro'], ['right', 'Derecha']];
        return '<div class="wb-f"><label>Alineación</label><select id="wb-p-align">' + opts.map(function (o) { return '<option value="' + o[0] + '"' + (cur === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select></div>';
    }

    function rtApply(tools, cmd, val) {
        if (!tools) return;
        var ed = document.getElementById(tools.getAttribute('data-rt-for')); if (!ed) return;
        ed.focus();
        try {
            document.execCommand('styleWithCSS', false, true);
            if (cmd === 'createLink') { var u = prompt('URL del enlace:', 'https://'); if (u) document.execCommand(cmd, false, u); }
            else if (cmd === 'formatBlock') document.execCommand(cmd, false, '<' + val + '>');
            else if (val !== null && val !== undefined && val !== '') document.execCommand(cmd, false, val);
            else document.execCommand(cmd, false, null);
        } catch (e) { }
    }

    function bindPanel() {
        var pn0 = $('#wb-panel');
        if (pn0 && !pn0.__rtBound) {
            pn0.__rtBound = 1;
            pn0.addEventListener('mousedown', function (e) { if (e.target.closest('.wb-rte-tools')) e.preventDefault(); }, true);
            pn0.addEventListener('click', function (e) {
                var b = e.target.closest('.wb-rte-tools [data-c]'); if (!b) return;
                rtApply(b.closest('.wb-rte-tools'), b.getAttribute('data-c'), b.getAttribute('data-v'));
            });
            pn0.addEventListener('input', function (e) {
                var inp = e.target.closest('.wb-rte-tools input[type=color]'); if (!inp) return;
                rtApply(inp.closest('.wb-rte-tools'), inp.hasAttribute('data-bg') ? 'hiliteColor' : 'foreColor', inp.value);
            });
            pn0.addEventListener('change', function (e) {
                var s = e.target.closest('.wb-rte-tools select[data-size]'); if (!s || !s.value) return;
                rtApply(s.closest('.wb-rte-tools'), 'fontSize', s.value);
            });
        }
        var media = $('#wb-p-media');
        if (media) media.addEventListener('click', function () {
            api('/api/v1/admin/media?page=1').then(function (r) {
                var rows = (r.data || []).filter(function (m) { return (m.mime || '').indexOf('image/') === 0; });
                if (!rows.length) { toast('No hay imágenes en Media'); return; }
                var box = document.createElement('div'); box.className = 'wb-modal wb-open';
                box.innerHTML = '<div class="wb-modal-box"><div class="wb-modal-head"><b>Elegir imagen</b></div><div class="wb-modal-body"><div class="wb-grid">'
                    + rows.map(function (m) { return '<button type="button" class="wb-item" data-url="' + esc(m.url) + '"><i>▣</i><span>' + esc(m.filename || '') + '</span></button>'; }).join('')
                    + '</div></div></div>';
                document.body.appendChild(box);
                box.addEventListener('click', function (e) {
                    if (e.target === box) { box.remove(); return; }
                    var it = e.target.closest('.wb-item'); if (!it) return;
                    var u = $('#wb-p-url'); if (u) u.value = it.getAttribute('data-url');
                    box.remove();
                });
            });
        });
        $$('#wb-panel .wb-vis button').forEach(function (b) {
            b.addEventListener('click', function () { b.classList.toggle('on'); });
        });
        var sc = $('#wb-style-copy');
        if (sc) sc.addEventListener('click', function () { S.styleClip = Object.assign({}, S.sel.styles || {}); toast('Estilo copiado · selecciona otro bloque y pega'); });
        var sp = $('#wb-style-paste');
        if (sp) sp.addEventListener('click', function () {
            if (!S.styleClip) return;
            api('/api/v1/admin/builder/blocks/' + S.sel.id, { method: 'PATCH', body: { styles: S.styleClip } }).then(function (r) { if (r.ok) { toast('Estilo aplicado ✓'); closePanel(); refreshCanvas(); } else toast(r.message || 'Error'); });
        });
        var lk = $('#wb-lock');
        if (lk) lk.addEventListener('change', function () {
            var pr = Object.assign({}, S.sel.props); pr._locked = this.checked;
            api('/api/v1/admin/builder/blocks/' + S.sel.id, { method: 'PATCH', body: { props: pr } }).then(function (r) { if (r.ok) { toast(lk.checked ? '🔒 Bloqueado' : 'Desbloqueado'); closePanel(); refreshCanvas(); } else toast(r.message || 'Error'); });
        });
        if (typeof renderTokens === 'function') renderTokens();
        bindTia();
        var hb = $('#wb-hist');
        if (hb) hb.addEventListener('click', function () { loadBlockHistory(); });
        var cs = $('#wb-comp-save');
        if (cs) cs.addEventListener('click', function () {
            var name = prompt('Nombre del componente:', 'Componente');
            if (name === null) return;
            api('/api/v1/admin/builder/components', { method: 'POST', body: { block_id: S.sel.id, name: name } }).then(function (r) { if (r.ok) { toast('Componente creado · ahora está enlazado ✓'); closePanel(); refreshCanvas(); } else toast(r.message || 'Error'); });
        });
        var save = $('#wb-save');
        if (save) save.addEventListener('click', savePanel);
        var delBtn = $('#wb-del');
        if (delBtn) delBtn.addEventListener('click', function () {
            if (!S.sel) return;
            var b = document.querySelector('.wwi-b-block[data-block="' + S.sel.id + '"]');
            if (b) removeBlock(b, S.sel.id, b.parentNode);
        });
        var c2 = $('#wb-close2');
        if (c2) c2.addEventListener('click', closePanel);
        var pn = $('#wb-panel');
        if (pn && !pn.__repBound) {
            pn.__repBound = 1;
            pn.addEventListener('click', function (e) {
                var t;
                if ((t = e.target.closest('[data-repadd]'))) { var k = t.getAttribute('data-repadd'); bpRepCollect(k); S._brep[k] = S._brep[k] || []; S._brep[k].push({}); bpRepRefresh(k); }
                else if ((t = e.target.closest('[data-repdel]'))) { var k2 = t.getAttribute('data-repdel'); bpRepCollect(k2); S._brep[k2].splice(parseInt(t.getAttribute('data-i'), 10), 1); bpRepRefresh(k2); }
                else if ((t = e.target.closest('[data-repup]'))) { var k3 = t.getAttribute('data-repup'); var i = parseInt(t.getAttribute('data-i'), 10); bpRepCollect(k3); var a = S._brep[k3]; if (i > 0) { var x = a[i]; a[i] = a[i - 1]; a[i - 1] = x; bpRepRefresh(k3); } }
                else if ((t = e.target.closest('[data-repdn]'))) { var k4 = t.getAttribute('data-repdn'); var i2 = parseInt(t.getAttribute('data-i'), 10); bpRepCollect(k4); var a2 = S._brep[k4]; if (i2 < a2.length - 1) { var x2 = a2[i2]; a2[i2] = a2[i2 + 1]; a2[i2 + 1] = x2; bpRepRefresh(k4); } }
            });
        }
    }

    function savePanel() {
        if (!S.sel) return;
        var type = S.sel.type, id = S.sel.id;
        var body = { props: {}, visibility: {} };
        if (type === 'text') { var rte = $('#wb-rte'); body.props.html = rte ? rte.innerHTML : ''; }
        else if (type === 'image') { body.props = { url: val('#wb-p-url'), alt: val('#wb-p-alt'), caption: val('#wb-p-caption') }; }
        else if (type === 'button') { body.props = { label: val('#wb-p-label'), href: val('#wb-p-href'), style: val('#wb-p-style') }; }
        else if (type === 'video') { body.props = { url: val('#wb-p-vurl'), poster: val('#wb-p-poster') }; }
        else if (type === 'spacer') { body.props = { height: parseInt(val('#wb-p-height'), 10) || 40 }; }
        else if (type === 'brick') {
            var bschema = (S.schemas && S.schemas[S.sel.brick]) || null;
            if (bschema && $('#wb-p-brick')) {
                body.props = collectBrickFields(bschema, S.sel.props);
            } else {
                try { body.props = JSON.parse($('#wb-p-props').value || '{}'); } catch (e) { toast('JSON inválido'); return; }
            }
        }
        else { body.props = { html: val('#wb-p-html') }; }
        if (S.sel.props && S.sel.props._component_id) { body.props = body.props || {}; body.props._component_id = S.sel.props._component_id; }
        var alignEl = $('#wb-p-align');
        if (alignEl) {
            var align = alignEl.value;
            body.styles = { text_align: align || '' };
            if (type === 'brick') {
                body.props = body.props || {};
                if (align) body.props.text_align = align; else delete body.props.text_align;
            }
        }
        $$('#wb-panel .wb-vis button').forEach(function (b) {
            body.visibility['hide_' + b.getAttribute('data-v')] = !b.classList.contains('on');
        });
        setStatus('Guardando…');
        api('/api/v1/admin/builder/blocks/' + id, { method: 'PATCH', body: body }).then(function (r) {
            if (r.ok) { toast('Guardado ✓'); setStatus('Guardado ✓');         refreshCanvas(); } else { toast(r.message || 'Error'); setStatus('Error'); }
        });
    }

    function val(sel) { var el = $(sel); return el ? el.value : ''; }

    // ── Acciones ──
    function removeBlock(block, id, slot) {
        if (isLocked(id)) { toast('🔒 Bloque bloqueado · desbloquéalo en el panel'); return; }
        if (!slot) slot = block.parentNode;
        if (S.sel && S.sel.id === id) { S.sel = null; var p = $('#wb-panel'); if (p) p.classList.remove('wb-open'); var c = $('#wb-crumb'); if (c) c.remove(); }
        block.remove();
        ensureAdd(slot);
        setStatus('Eliminando…');
        api('/api/v1/admin/builder/block/node/' + id + '?page_id=' + PAGE_ID, { method: 'DELETE' }).then(function (r) {
            if (!r.ok) { toast(r.message || 'Error al eliminar'); load(); return; }
            setStatus('Eliminado');
            api('/api/v1/admin/builder/trash?page_id=' + PAGE_ID).then(function (t) {
                var tid = (t.ok && t.data && t.data.length) ? t.data[0].id : null;
                if (tid) pushOp({ undo: function () { return api('/api/v1/admin/builder/trash/' + tid + '/restore', { method: 'POST' }).then(function () { load(); }); } });
                toast('Elemento eliminado · espacio disponible', function () { undoOp(); });
            });
        });
    }

    document.addEventListener('keydown', function (e) {
        if (!S.on) return;
        var ae = document.activeElement;
        if (ae && (ae.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].indexOf(ae.tagName) > -1)) return;
        var mod = e.ctrlKey || e.metaKey;
        var k = (e.key || '').toLowerCase();
        if (!S.sel) { if (k === 'escape') closePanel(); return; }
        var sel = document.querySelector('.wwi-b-block[data-block="' + S.sel.id + '"]');
        if (mod && k === 'd') { e.preventDefault(); if (sel) doDuplicate(sel, S.sel.id, sel.parentNode); return; }
        if (mod && k === 'c') { e.preventDefault(); copyBlock(); return; }
        if (mod && k === 'v') { e.preventDefault(); pasteBlock(); return; }
        if (mod && k === 'z' && !e.shiftKey) { e.preventDefault(); undoOp(); return; }
        if (mod && (k === 'y' || (k === 'z' && e.shiftKey))) { e.preventDefault(); redoOp(); return; }
        if (k === 'delete' || k === 'backspace') { e.preventDefault(); if (sel) removeBlock(sel, S.sel.id, sel.parentNode); return; }
        if (k === 'arrowup' || k === 'arrowdown') { if (sel) { e.preventDefault(); moveSibling(sel, k === 'arrowup' ? -1 : 1); } return; }
        if (k === 'escape') { e.preventDefault(); closePanel(); return; }
    });

    function moveSibling(block, dir) {
        var slot = block.parentNode;
        var blocks = $$(':scope > .wwi-b-block', slot);
        var ids = blocks.map(function (b) { return parseInt(b.getAttribute('data-block'), 10); });
        var id = parseInt(block.getAttribute('data-block'), 10);
        if (isLocked(id)) { toast('🔒 Bloque bloqueado'); return; }
        var i = ids.indexOf(id);
        if (i < 0) return;
        var j = i + dir;
        if (j < 0 || j >= ids.length) return;
        var before = ids.slice();
        ids.splice(i, 1);
        ids.splice(j, 0, id);
        var after = ids.slice();
        reorderDomBlocks(slot, ids);
        showCrumb(block);
        setStatus('Guardando…');
        var doReorder = function (items) { return api('/api/v1/admin/builder/reorder/block', { method: 'POST', body: { items: items } }); };
        doReorder(ids).then(function (r) {
            if (r.ok) {
                toast('Movido'); setStatus('Guardado ✓');
                pushOp({
                    undo: function () { return doReorder(before).then(function () { reorderDomBlocks(slot, before); showCrumb(block); }); },
                    redo: function () { return doReorder(after).then(function () { reorderDomBlocks(slot, after); showCrumb(block); }); }
                });
            } else { toast(r.message || 'No se pudo mover'); setStatus('Error'); }
        });
    }

    function reorderDomBlocks(slot, ids) {
        var anchor = slot.querySelector(':scope > .wb-add');
        ids.forEach(function (id) {
            var el = slot.querySelector(':scope > .wwi-b-block[data-block="' + id + '"]');
            if (el) slot.insertBefore(el, anchor || null);
        });
    }

    function reparentBlock(blockEl, newSlot, pos) {
        var oldSlot = blockEl.parentNode;
        var tools = blockEl.querySelector(':scope > .wb-tools');
        if (tools) tools.remove();
        var list = $$(':scope > .wwi-b-block', newSlot);
        var ref = list[pos] || null;
        newSlot.insertBefore(blockEl, ref || (newSlot.querySelector(':scope > .wb-add') || null));
        decorateBlock(blockEl, newSlot);
        if (oldSlot && oldSlot !== newSlot) ensureAdd(oldSlot);
        ensureAdd(newSlot);
    }

    // ── Paleta ──
    function openPalette(slot) {
        S.slot = slot;
        var box = $('#wb-palette');
        if (!box) {
            box = document.createElement('div'); box.id = 'wb-palette'; box.className = 'wb-modal';
            box.innerHTML = '<div class="wb-modal-box"><div class="wb-modal-head"><b>Añadir bloque</b><input type="text" id="wb-search" placeholder="Buscar…"/><button type="button" class="wb-btn wb-ghost" id="wb-pal-close">Cerrar</button></div><div class="wb-modal-body" id="wb-pal-body"></div></div>';
            document.body.appendChild(box);
            box.addEventListener('click', function (e) { if (e.target === box) box.classList.remove('wb-open'); });
            $('#wb-pal-close').addEventListener('click', function () { box.classList.remove('wb-open'); });
            $('#wb-search').addEventListener('input', renderPalette);
        }
        box.classList.add('wb-open');
        renderPalette();
        setTimeout(function () { var s = $('#wb-search'); if (s) s.focus(); }, 60);
    }

    function renderPalette() {
        var body = $('#wb-pal-body'); if (!body) return;
        var q = ($('#wb-search') ? $('#wb-search').value : '').toLowerCase();
        var data = S.palette || { atomic: [], bricks: [] };
        var atomic = (data.atomic || []).filter(function (a) { return !q || a.label.toLowerCase().indexOf(q) > -1; });
        var bricks = (data.bricks || []).filter(function (b) { return !q || b.label.toLowerCase().indexOf(q) > -1 || b.slug.indexOf(q) > -1; });
        var h = '';
        if (atomic.length) h += '<div class="wb-sec-t">Elementos <span style="text-transform:none;letter-spacing:0;font-weight:500">(haz clic o arrastra al sitio)</span></div><div class="wb-grid">' + atomic.map(function (a) {
            return '<button type="button" class="wb-item" draggable="true" data-kind="atomic" data-type="' + a.type + '"><i>' + esc(a.icon) + '</i><span>' + esc(a.label) + '</span></button>';
        }).join('') + '</div>';
        if (bricks.length) h += '<div class="wb-sec-t">Bricks (' + bricks.length + ')</div><div class="wb-grid">' + bricks.map(function (b) {
            return '<button type="button" class="wb-item" draggable="true" data-kind="brick" data-slug="' + esc(b.slug) + '"><i>▦</i><span>' + esc(b.label) + '</span></button>';
        }).join('') + '</div>';
        if (!h) h = '<div style="color:#9c96c4;font-size:12.5px;padding:10px">Sin resultados.</div>';
        body.innerHTML = h;
        body.addEventListener('dragstart', function (e) {
            var it = e.target.closest('.wb-item'); if (!it) return;
            S.paletteDrag = { kind: it.getAttribute('data-kind'), type: it.getAttribute('data-type'), slug: it.getAttribute('data-slug') };
            if (e.dataTransfer) { try { e.dataTransfer.setData('text/plain', 'palette'); e.dataTransfer.effectAllowed = 'copy'; } catch (x) { } }
            var box = $('#wb-palette'); if (box) box.classList.remove('wb-open');
            toast('Suelta el bloque en el lugar deseado');
        });
        body.addEventListener('dragend', function () { S.paletteDrag = null; });
        body.addEventListener('click', function (e) {
            var it = e.target.closest('.wb-item'); if (!it) return;
            var slot = S.slot; if (!slot) return;
            var slotId = parseInt(slot.getAttribute('data-slot'), 10);
            var payload = it.getAttribute('data-kind') === 'brick'
                ? { slot_id: slotId, type: 'brick', brick_slug: it.getAttribute('data-slug'), props: {} }
                : { slot_id: slotId, type: it.getAttribute('data-type'), props: defaultProps(it.getAttribute('data-type')) };
            setStatus('Insertando…');
            api('/api/v1/admin/builder/blocks', { method: 'POST', body: payload }).then(function (r) {
                if (r.ok) { toast('Bloque añadido'); location.reload(); }
                else { toast(r.message || 'Error al insertar'); setStatus('Error'); }
            });
        });
    }

    function defaultProps(type) {
        if (type === 'text') return { html: '<h2>Escribe un título</h2><p>Describe aquí tu propuesta de valor.</p>' };
        if (type === 'button') return { label: 'Empezar', href: '#', style: 'primary' };
        if (type === 'image') return { url: '', alt: '' };
        if (type === 'video') return { url: '' };
        if (type === 'spacer') return { height: 40 };
        return { html: '' };
    }

    // ── Versiones ──
    function showRevisions() {
        api('/api/v1/admin/builder/revisions?page_id=' + PAGE_ID).then(function (r) {
            var rows = r.data || [];
            var box = document.createElement('div'); box.className = 'wb-modal wb-open';
            box.innerHTML = '<div class="wb-modal-box"><div class="wb-modal-head"><b>Versiones</b><button type="button" class="wb-btn wb-ghost" id="wb-rev-close">Cerrar</button></div><div class="wb-modal-body">'
                + (rows.length ? rows.map(function (v) { return '<div class="wb-item" style="margin-bottom:8px"><i>⏱</i><span style="flex:1">' + esc(v.label || 'Versión') + ' · ' + esc(v.created_at || '') + ' · ' + esc(v.username || '') + '</span><button type="button" class="wb-btn" data-restore="' + v.id + '">Restaurar</button></div>'; }).join('') : '<div style="color:#9c96c4;padding:10px">Sin versiones todavía.</div>')
                + '</div></div>';
            document.body.appendChild(box);
            box.addEventListener('click', function (e) {
                if (e.target === box || e.target.id === 'wb-rev-close') { box.remove(); return; }
                var b = e.target.closest('[data-restore]'); if (!b) return;
                api('/api/v1/admin/builder/revisions/' + b.getAttribute('data-restore') + '/restore', { method: 'POST' }).then(function (res) {
                    if (res.ok) { toast('Restaurada ✓ recargando…'); setTimeout(function () { location.reload(); }, 800); }
                    else toast(res.message || 'Error');
                });
            });
        });
    }

    // ── Arranque ──
    function boot() { if (!document.body) return setTimeout(boot, 200); bar(); marqueeInit(); loadTokens(); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
    window.__WWI_BUILDER__ = { toggle: toggle, reload: load };
})();
