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

    var S = { on: false, tree: null, palette: null, sel: null, undo: [], redo: [], saving: false, ready: false, drag: null };
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

    // ── Barra del builder ──
    function bar() {
        if ($('#wb-bar')) return;
        var b = document.createElement('div');
        b.id = 'wb-bar'; b.className = 'wb-bar';
        b.innerHTML = '<button type="button" id="wb-toggle">🧱 Bloques</button>'
            + '<span class="wb-status" id="wb-status"></span>'
            + '<button type="button" id="wb-publish">Publicar</button>'
            + '<button type="button" id="wb-revs">Versiones</button>';
        document.body.appendChild(b);
        $('#wb-toggle').addEventListener('click', toggle);
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
        if (S.on) { load(); } else { closePanel(); }
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
        });
        if (!S.palette) api('/api/v1/admin/builder/palette').then(function (r) { if (r.ok) S.palette = r.data; });
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

    // ── Decoración del canvas ──
    function decorate() {
        $$('.wwi-b-row').forEach(function (row, i) {
            if (!row.querySelector(':scope > .wb-row-tag')) {
                var t = document.createElement('span'); t.className = 'wb-row-tag'; t.textContent = 'Fila ' + (i + 1); row.appendChild(t);
            }
            $$(':scope > .wwi-b-row-inner > .wwi-b-col', row).forEach(function (col) {
                if (!col.querySelector(':scope > .wb-col-tag')) {
                    var c = document.createElement('span'); c.className = 'wb-col-tag'; c.textContent = col.getAttribute('data-span') + '/12'; col.appendChild(c);
                }
                if (!col.querySelector(':scope > .wb-col-resize')) {
                    var rz = document.createElement('span'); rz.className = 'wb-col-resize'; col.appendChild(rz);
                }
                $$(':scope > .wwi-b-slot', col).forEach(function (slot) {
                    $$(':scope > .wwi-b-block', slot).forEach(function (block) { decorateBlock(block, slot); });
                    ensureAdd(slot);
                });
            });
        });
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
        block.setAttribute('draggable', 'true');
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
            else if (act === 'dup') api('/api/v1/admin/builder/blocks/' + id + '/duplicate', { method: 'POST' }).then(function (r) { if (r.ok) { reloadSlot(slot); toast('Duplicado'); } });
            else if (act === 'up' || act === 'down') moveSibling(block, act === 'up' ? -1 : 1);
        });
        block.addEventListener('click', function (e) {
            if (!S.on) return;
            if (e.target.closest('.wb-tools')) return;
            e.preventDefault(); e.stopPropagation();
            select(block);
        });
    }

    function ensureAdd(slot) {
        if (slot.querySelector(':scope > .wb-add')) return;
        var a = document.createElement('button');
        a.type = 'button'; a.className = 'wb-add'; a.innerHTML = '+';
        a.setAttribute('aria-label', 'Añadir bloque');
        a.title = 'Añadir bloque';
        a.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); openPalette(slot); });
        slot.addEventListener('dragover', function (e) {
            e.preventDefault();
            a.classList.add('wb-drop');
            var line = slot.querySelector(':scope > .wb-drop-line') || document.createElement('div');
            line.className = 'wb-drop-line';
            slot.insertBefore(line, a);
        });
        slot.addEventListener('dragleave', function (e) { if (!slot.contains(e.relatedTarget)) { a.classList.remove('wb-drop'); clearDropLines(); } });
        slot.addEventListener('drop', function (e) {
            e.preventDefault(); a.classList.remove('wb-drop'); clearDropLines();
            if (!S.drag) return;
            var pos = $$(':scope > .wwi-b-block', slot).length;
            api('/api/v1/admin/builder/blocks/' + S.drag.id + '/move', { method: 'POST', body: { slot_id: parseInt(slot.getAttribute('data-slot'), 10), position: pos } })
                .then(function (r) { if (r.ok) { toast('Movido'); location.reload(); } });
        });
        slot.appendChild(a);
    }

    function clearDropLines() { $$('.wb-drop-line').forEach(function (l) { l.remove(); }); }

    // ── Selección y panel ──
    function select(block) {
        $$('.wwi-b-block.wb-sel').forEach(function (b) { b.classList.remove('wb-sel'); });
        block.classList.add('wb-sel');
        var id = parseInt(block.getAttribute('data-block'), 10);
        var found = findBlock(id);
        if (!found) return;
        S.sel = { id: id, type: block.getAttribute('data-type'), brick: block.getAttribute('data-brick') || '', props: found.props || {}, block: block };
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

    function closePanel() { var p = $('#wb-panel'); if (p) p.classList.remove('wb-open'); $$('.wwi-b-block.wb-sel').forEach(function (b) { b.classList.remove('wb-sel'); }); S.sel = null; }

    function renderPanel() {
        if (!S.sel) return;
        var body = $('#wb-panel-body'); var title = $('#wb-panel-title');
        var type = S.sel.type, props = S.sel.props || {};
        title.textContent = S.sel.brick || type;
        var h = '';
        if (type === 'text') {
            h += '<div class="wb-f"><label>Texto</label><div class="wb-rte-tools">'
                + '<button type="button" data-c="bold"><b>B</b></button><button type="button" data-c="italic"><i>I</i></button><button type="button" data-c="underline"><u>U</u></button>'
                + '<button type="button" data-c="formatBlock" data-v="h2">H2</button><button type="button" data-c="formatBlock" data-v="h3">H3</button><button type="button" data-c="formatBlock" data-v="p">P</button>'
                + '<button type="button" data-c="insertUnorderedList">•</button><button type="button" data-c="createLink">🔗</button><button type="button" data-c="removeFormat">✕</button>'
                + '</div><div class="wb-rte" id="wb-rte" contenteditable="true">' + (props.html || '') + '</div></div>';
            h += alignField();
        } else if (type === 'image') {
            h += field('URL de la imagen', '<input type="text" id="wb-p-url" value="' + esc(props.url || '') + '" placeholder="https://… o /assets/uploads/…"/>');
            h += '<div class="wb-actions"><button type="button" class="wb-btn wb-ghost" id="wb-p-media">Elegir de Media</button></div>';
            h += field('Texto alternativo (SEO)', '<input type="text" id="wb-p-alt" value="' + esc(props.alt || '') + '"/>');
            h += field('Pie de foto', '<input type="text" id="wb-p-caption" value="' + esc(props.caption || '') + '"/>');
        } else if (type === 'button') {
            h += field('Texto', '<input type="text" id="wb-p-label" value="' + esc(props.label || '') + '"/>');
            h += field('Enlace', '<input type="text" id="wb-p-href" value="' + esc(props.href || '') + '" placeholder="https://… o #ancla"/>');
            h += field('Estilo', '<select id="wb-p-style">' + ['primary', 'secondary', 'ghost'].map(function (v) { return '<option value="' + v + '"' + (props.style === v ? ' selected' : '') + '>' + v + '</option>'; }).join('') + '</select>');
            h += alignField();
        } else if (type === 'video') {
            h += field('URL (YouTube, Vimeo o MP4)', '<input type="text" id="wb-p-vurl" value="' + esc(props.url || '') + '"/>');
            h += field('Poster (opcional)', '<input type="text" id="wb-p-poster" value="' + esc(props.poster || '') + '"/>');
        } else if (type === 'spacer') {
            h += field('Altura (px)', '<input type="number" id="wb-p-height" value="' + esc(props.height || 40) + '" min="4" max="240"/>');
        } else if (type === 'brick') {
            h += '<div class="wb-f"><label>Brick: ' + esc(S.sel.brick) + '</label><textarea id="wb-p-props" spellcheck="false" style="font-family:JetBrains Mono,monospace;font-size:11.5px">' + esc(JSON.stringify(props, null, 2)) + '</textarea></div>';
        } else {
            h += '<div class="wb-f"><label>Contenido HTML</label><textarea id="wb-p-html" spellcheck="false" style="font-family:JetBrains Mono,monospace;font-size:11.5px">' + esc(props.html || '') + '</textarea></div>';
        }
        h += '<div class="wb-f"><label>Visibilidad</label><div class="wb-vis">'
            + '<button type="button" data-v="desktop" class="on">🖥 Escritorio</button><button type="button" data-v="tablet" class="on">▭ Tablet</button><button type="button" data-v="mobile" class="on">▯ Móvil</button>'
            + '</div></div>';
        h += '<div class="wb-actions"><button type="button" class="wb-btn" id="wb-save">Guardar</button><button type="button" class="wb-btn wb-ghost" id="wb-close2">Cerrar</button></div>';
        body.innerHTML = h;
        bindPanel();
    }

    function field(label, input) { return '<div class="wb-f"><label>' + label + '</label>' + input + '</div>'; }
    function alignField() {
        return '<div class="wb-f"><label>Alineación</label><select id="wb-p-align"><option value="">Heredar</option><option value="left">Izquierda</option><option value="center">Centro</option><option value="right">Derecha</option></select></div>';
    }

    function bindPanel() {
        var rte = $('#wb-rte');
        if (rte) {
            $$('#wb-panel .wb-rte-tools button').forEach(function (b) {
                b.addEventListener('mousedown', function (e) { e.preventDefault(); });
                b.addEventListener('click', function () {
                    var c = b.getAttribute('data-c'), v = b.getAttribute('data-v');
                    rte.focus();
                    try {
                        if (c === 'createLink') { var u = prompt('URL del enlace:'); if (u) document.execCommand(c, false, u); }
                        else if (c === 'formatBlock') document.execCommand(c, false, '<' + v + '>');
                        else document.execCommand(c, false, null);
                    } catch (e) { }
                });
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
        var save = $('#wb-save');
        if (save) save.addEventListener('click', savePanel);
        var c2 = $('#wb-close2');
        if (c2) c2.addEventListener('click', closePanel);
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
        else if (type === 'brick') { try { body.props = JSON.parse($('#wb-p-props').value || '{}'); } catch (e) { toast('JSON inválido'); return; } }
        else { body.props = { html: val('#wb-p-html') }; }
        var align = val('#wb-p-align');
        if (align) body.styles = { text_align: align };
        $$('#wb-panel .wb-vis button').forEach(function (b) {
            body.visibility['hide_' + b.getAttribute('data-v')] = !b.classList.contains('on');
        });
        setStatus('Guardando…');
        api('/api/v1/admin/builder/blocks/' + id, { method: 'PATCH', body: body }).then(function (r) {
            if (r.ok) { toast('Guardado ✓'); setStatus('Guardado ✓'); setTimeout(load, 200); } else { toast(r.message || 'Error'); setStatus('Error'); }
        });
    }

    function val(sel) { var el = $(sel); return el ? el.value : ''; }

    // ── Acciones ──
    function removeBlock(block, id, slot) {
        var ph = document.createElement('div');
        ph.className = 'wb-ph-wrap';
        ph.innerHTML = '<button type="button" class="wb-add wb-ph" aria-label="Añadir bloque">+</button>';
        block.parentNode.insertBefore(ph, block);
        block.remove();
        api('/api/v1/admin/builder/block/node/' + id + '?page_id=' + PAGE_ID, { method: 'DELETE' }).then(function (r) {
            if (!r.ok) { toast(r.message || 'Error al eliminar'); load(); return; }
            toast('Bloque eliminado', function () {
                api('/api/v1/admin/builder/trash?page_id=' + PAGE_ID).then(function (t) {
                    if (t.ok && t.data && t.data.length) {
                        api('/api/v1/admin/builder/trash/' + t.data[0].id + '/restore', { method: 'POST' }).then(function () { location.reload(); });
                    }
                });
            });
            ph.querySelector('.wb-add').addEventListener('click', function () { openPalette(slot); });
            setStatus('Eliminado');
        });
    }

    function moveSibling(block, dir) {
        var slot = block.parentNode;
        var sib = dir < 0 ? block.previousElementSibling : block.nextElementSibling;
        while (sib && !sib.classList.contains('wwi-b-block')) sib = dir < 0 ? sib.previousElementSibling : sib.nextElementSibling;
        if (!sib) return;
        var ids = $$(':scope > .wwi-b-block', slot).map(function (b) { return parseInt(b.getAttribute('data-block'), 10); });
        var id = parseInt(block.getAttribute('data-block'), 10);
        var i = ids.indexOf(id), j = ids.indexOf(parseInt(sib.getAttribute('data-block'), 10));
        ids.splice(i, 1); ids.splice(j, 0, id);
        api('/api/v1/admin/builder/reorder/block', { method: 'POST', body: { items: ids } }).then(function (r) { if (r.ok) location.reload(); });
    }

    function reloadSlot(slot) {
        var sid = parseInt(slot.getAttribute('data-slot'), 10);
        api('/api/v1/admin/builder/tree?page_id=' + PAGE_ID).then(function (r) {
            if (!r.ok) return;
            S.tree = r.data;
            var blocks = [];
            (S.tree.rows || []).forEach(function (row) { (row.columns || []).forEach(function (col) { (col.slots || []).forEach(function (s) { if (parseInt(s.id, 10) === sid) blocks = s.blocks || []; }); }); });
            $$(':scope > .wwi-b-block, :scope > .wb-ph-wrap', slot).forEach(function (n) { n.remove(); });
            blocks.forEach(function (b) {
                var div = document.createElement('div');
                div.className = 'wwi-b-block'; div.setAttribute('data-block', b.id); div.setAttribute('data-type', b.type);
                if (b.brick_slug) div.setAttribute('data-brick', b.brick_slug);
                div.innerHTML = '<div style="opacity:.75">' + esc(b.brick_slug || b.type) + '</div>';
                slot.appendChild(div);
                decorateBlock(div, slot);
            });
            ensureAdd(slot);
            toast('Actualizado');
        });
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
        if (atomic.length) h += '<div class="wb-sec-t">Elementos</div><div class="wb-grid">' + atomic.map(function (a) {
            return '<button type="button" class="wb-item" data-kind="atomic" data-type="' + a.type + '"><i>' + esc(a.icon) + '</i><span>' + esc(a.label) + '</span></button>';
        }).join('') + '</div>';
        if (bricks.length) h += '<div class="wb-sec-t">Bricks (' + bricks.length + ')</div><div class="wb-grid">' + bricks.map(function (b) {
            return '<button type="button" class="wb-item" data-kind="brick" data-slug="' + esc(b.slug) + '"><i>▦</i><span>' + esc(b.label) + '</span></button>';
        }).join('') + '</div>';
        if (!h) h = '<div style="color:#9c96c4;font-size:12.5px;padding:10px">Sin resultados.</div>';
        body.innerHTML = h;
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
    function boot() { if (!document.body) return setTimeout(boot, 200); bar(); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
    window.__WWI_BUILDER__ = { toggle: toggle, reload: load };
})();
