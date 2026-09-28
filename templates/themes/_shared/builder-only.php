<?php
/* WWI Builder-only - carga SOLO el editor de bloques (builder.js).
   Se usa en páginas renderizadas por el Builder para evitar el doble módulo
   de edición (live-editor por secciones + builder). Requiere $page en scope. */
?>
<script>window.__WWI_EDIT_CTX__=<?= json_encode(['pageId' => (int)($page['id'] ?? 0), 'pageTitle' => (string)($page['title'] ?? ''), 'pageSlug' => (string)($page['slug'] ?? '')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<script>
(function(){
    var token = null;
    try { token = localStorage.getItem('wwi_token') || null; } catch (e) {}
    if (!token || window.__WWI_PREVIEW__) return;
    if (!document.getElementById('wb-css')) {
        var l = document.createElement('link'); l.id = 'wb-css'; l.rel = 'stylesheet';
        l.href = '/assets/css/builder.css?v=<?= @filemtime(ROOT_DIR . '/public/assets/css/builder.css') ?: time() ?>';
        document.head.appendChild(l);
    }
    if (!window.__WWI_BUILDER_LOADING) {
        window.__WWI_BUILDER_LOADING = 1;
        var s = document.createElement('script');
        s.src = '/assets/js/builder.js?v=<?= @filemtime(ROOT_DIR . '/public/assets/js/builder.js') ?: time() ?>';
        s.defer = true; document.body.appendChild(s);
    }
})();
</script>
