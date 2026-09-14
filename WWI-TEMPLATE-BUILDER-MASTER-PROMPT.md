# WWI — TEMPLATE BUILDER MASTER PROMPT (Contrato para crear temas nuevos)

> Este documento es TODO lo que ChatGPT (o cualquier IA/desarrollador) debe conocer para proponer y construir una **plantilla/tema nuevo** para un sitio WWI, sin romper nada de lo existente. Entrégalo completo como contexto antes de pedir el prompt maestro del tema. Si algo de aquí se ignora, el tema se romperá (editor en vivo, wizard, checkout, widgets).

## 1. QUÉ ES WWI (contexto mínimo)
- CMS **basado en BRICKs** (bloques) para sitios multi-tenant. PHP 8.3 + Nginx (Docker) + MariaDB. **Sin frameworks** (composer solo autoload).
- **Multi-site**: cada sitio es una fila en `sites` (con `id`, `theme`, `domain`). La conexión PDO fija la variable de sesión `@site_id`; **toda query filtra por `@site_id`**.
- Un **tema** solo controla la **apariencia y el layout del frontend** (HTML/CSS/JS de presentación). NO controla datos, endpoints, widgets ni el admin.
- El **contenido** vive en DB: `pages` (páginas) y `sections` (secciones por página; cada sección es un widget BRICK o HTML). El tema **renderiza lo que le llega**, nunca consulta contenido por su cuenta (salvo settings públicos).

## 2. CÓMO SE CARGA UN TEMA (flujo real)
1. `public/index.php` (front controller) resuelve la URL:
   - `$slug = uri === '/' ? 'home' : trim(uri,'/')`.
   - `$page` = fila de `pages` por `slug` + `site_id = @site_id` + `status='published'` (si no existe → 404).
   - `$sections` = filas de `sections` con `page_id = $page['id'] AND is_active = 1 ORDER BY sort_order ASC`.
   - `$theme = SELECT theme FROM sites WHERE id=@site_id` (fallback `Config::get('theme','default')`).
   - `require ROOT_DIR . '/templates/themes/' . $theme . '/index.php';`
2. **Variables disponibles en el tema**: `$page` (array: id, title, slug, meta_title, meta_description, meta_keywords, og_image, canonical_url, no_index…), `$sections` (array de filas de sections). También puedes usar `App\Core\Config`, `App\Core\Database`, `App\Widgets\WidgetRegistry`, `App\Services\CookieConsentService`.
3. Cambiar el tema de un sitio: `UPDATE sites SET theme='mi-tema' WHERE id=<site_id>;` (desde el admin o SQL). **No** se toca nada más.

## 3. ESTRUCTURA DE UNA SECCIÓN (formato exacto)
Cada fila de `sections`:
- `id`, `page_id`, `type` (`widget` | `html` | `custom`), `widget_type` (id del BRICK cuando type=widget), `title`, `subtitle`, `content` (HTML para html/custom), `config` (JSON string), `sort_order`, `is_active`.
- El tema debe renderizar así (contrato obligatorio):
```php
<?php
$wwiAbIds = [];
foreach ($sections as $s) {
    if (!empty($s['widget_type']) && WidgetRegistry::get($s['widget_type'])) $wwiAbIds[] = (int)$s['id'];
}
$wwiAb = [];
if ($wwiAbIds) {
    try {
        $wwiVisitor = sha1(($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $wwiAb = (new \App\Services\LiveEditorService())->pickVariants($wwiAbIds, $wwiVisitor);
    } catch (\Throwable $e) { $wwiAb = []; }
}
foreach ($sections as $section):
    $wwiSid = (int)($section['id'] ?? 0);
    $config = json_decode($section['config'] ?? '{}', true) ?: [];
    $wwiVariant = isset($wwiAb[$wwiSid]) ? (int)$wwiAb[$wwiSid]['variant_id'] : 0;
    if ($wwiVariant) $config = array_merge($config, $wwiAb[$wwiSid]['config']);
    $wwiHide = (!empty($config['_hide_mobile']) ? ' wwi-hide-mobile' : '') . (!empty($config['_hide_tablet']) ? ' wwi-hide-tablet' : '');
    echo '<div class="wwi-section' . $wwiHide . '" data-sid="' . $wwiSid . '" data-widget="' . htmlspecialchars((string)($section['widget_type'] ?? '')) . '"' . ($wwiVariant ? ' data-variant="' . $wwiVariant . '"' : '') . '>';
    if (!empty($section['widget_type']) && WidgetRegistry::get($section['widget_type'])):
        echo WidgetRegistry::render($section['widget_type'], $config);
    elseif ($section['type'] === 'custom' || $section['type'] === 'html'):
        echo '<section>' . ($section['content'] ?? '') . '</section>';
    else:
        if ($section['content']) echo '<section>' . $section['content'] . '</section>';
    endif;
    echo '</div>';
endforeach; ?>
```
**Esto es intocable**: los atributos `data-sid`, `data-widget`, `data-variant` y las clases `wwi-section`, `wwi-hide-mobile`, `wwi-hide-tablet` son el contrato con el **editor en vivo**, el A/B testing y la visibilidad responsive. Un tema que no los emita pierde el editor.

## 4. EL TEMA DEBE INCLUIR EL EDITOR EN VIVO (obligatorio)
Al final del `<body>`, todo tema debe incluir el parcial compartido:
```php
<?php require ROOT_DIR . '/templates/themes/_shared/live-editor.php'; ?>
```
Ese parcial (fuente única de verdad) contiene:
- `window.__WWI_EDIT_CTX__` (page id/title/slug).
- CSS y JS del editor: barra flotante "Editar sitio", barra lateral (Contenido/Añadir/Página/Comentarios/Calidad), selección por clic, edición inline (doble clic), repeaters, rutas anidadas (`data-editable="items.0.title"`), fuentes (`data-source="plan:ID:campo"`, `template:ID:campo`, `settings:nav:campo`), drag & drop, versiones, comentarios, A/B, presencia/cursores, IA.
- Solo se activa si hay `wwi_token` válido en localStorage y NO está en modo preview.

## 5. QUÉ EMITEN LOS WIDGETS (el tema DEBE estilizar estas clases)
El tema **no elige** el HTML de las secciones: lo emiten los widgets. El tema debe proveer el CSS para todas estas clases (design system):
- Layout: `.wrap`, `.wwi-grid-3`, `.wwi-grid-2`, `.panel`, `.card`, `.card .ic`, `.h-sec`, `.step`, `.step-num`, `.stat`, `.stat .v`, `.stat .l`, `.marquee`, `.marquee .track`, `.trust-row`, `.w-footer`, `.w-footer .cols`, `.w-footer .legal`, `.legal`, `.domain-box`, `.domain-result`, `.img-swap`.
- Tipografía/acento: `.mono`, `.num`, `.metric`, `.gradient-text`, `.badge`, `.h-sec h2/p`.
- Botones: `.btn`, `.btn-primary`, `.btn-ghost`, `.btn-outline`, `.btn-pulse`.
- Planes: `.plan-card`, `.plan-card.featured`, `.plan-name`, `.plan-price`, `.plan-feats`, `.plan-mini`, `.tpl-card`, `.dom-chip`, `.dom-row`.
- FAQ: `.faq-item`, `.faq-q`, `.faq-a`, `.faq-item.open`.
- Wizard/TIA (flujo de la landing): `.flow-overlay`, `.flow-shell`, `.flow-head`, `.flow-close`, `.flow-track`, `.flow-slide`, `.flow-h1`, `.flow-sub`, `.flow-actions`, `.flow-grid`, `.flow-modal`, `.chat-box`, `.chat-row`, `.chat-ava`, `.chat-msg`, `.typing`, `.chips`, `.chip`, `.chat-in`, `.spin`, `.confetti`.
- Fondos/efectos (opcionales): `.aurora`, `.orbs`, `.grid-bg`, `.cursor-glow`, `.scroll-progress`.
- Nav: `.w-nav`, `.w-nav-brand`, `.w-nav-logo`, `.w-nav-links`.
- Editor en vivo: NO las estiliza el tema; vienen en el parcial compartido (`.wwi-ed-*`, `.wwi-rep-*`, `.wwi-sel`, `.wwi-dropzone`, `.wwi-ed-cursor`, `.wwi-hide-*`, `.wwi-show-editables`, `.wwi-client`).
- Tokens CSS obligatorios (los widgets los usan): `--bg`, `--bg2`, `--panel`, `--panel2`, `--border`, `--border2`, `--text`, `--muted`, `--accent`, `--accent2`, `--ok`, `--warn`, `--bad`, `--glow`, `--radius`, `--nav-bg`, `--soft`, `--overlay` en `:root[data-theme='dark']` y `:root[data-theme='light']`, más `--cx/--cy` (cursor) y `--dx/--dy` (confetti).

## 6. JS GLOBALES QUE EL TEMA DEBE DEFINIR (contrato con los widgets)
Los HTML de widgets/checkout/wizard invocan estas funciones por `onclick`/`oninput`. El tema DEBE definirlas (copiar del tema `wwi` o reimplementarlas con la misma firma):
- Utilidades: `wwiEsc(s)`, `wwiConfetti()`, `wwiCoMsg(k,def)`.
- Datos: `wwiLoadPlans()`, `wwiLoadTemplates()`, `wwiLoadPayMode()`, `wwiUpdateTotal()`, `wwiCheckDomain()`.
- Checkout: `wwiCheckoutSubmit()`, `wwiDummyPay(uuid)`.
- Wizard (flujo "Empezar"): `wwiFlowOpen()`, `wwiFlowClose()`, `wwiFlowGo(i)`, `wwiFlowChip(el)`, `wwiFlowSend()`, `wwiFlowShowPreview(uuid)`, `wwiFlowDiscard()`, `wwiFlowLoadTpls()`, `wwiFlowPickTpl(slug)`, `wwiFlowLoadPlans()`, `wwiFlowPickPlan(id)`, `wwiXsConfirm(add)`, `wwiFlowCheckDomain()`, `wwiFlowSuggestDomains()`, `wwiFlowPickDom(name)`, `wwiFlowConfirmDomain()`, `wwiFlowCreateOrder()`, `wwiPvDevice(w)`.
- Opcional (mejora): `wwiHeroGpu()` (WebGPU del hero).
Si un tema nuevo no las define, el sitio **falla silenciosamente** en checkout/wizard.

## 7. ENDPOINTS PÚBLICOS QUE EL TEMA CONSUME (solo lectura)
- `GET /api/v1/public/plans` (planes del pricing engine), `GET /api/v1/public/plans/{slug}`.
- `GET /api/v1/public/templates` (catálogo), `GET /api/v1/public/settings`.
- `POST /api/v1/public/orders`, `GET /api/v1/public/orders/{uuid}`.
- `POST /api/v1/public/payments/dummy/{uuid}`, `GET /api/v1/public/payment-mode`.
- `GET /api/v1/public/domain/check?name=…`, `POST /api/v1/public/previews/suggest-domains`.
- `POST /api/v1/public/previews`, `GET /api/v1/public/previews/{uuid}`, `GET /api/v1/public/preview/{uuid}`, `POST /api/v1/public/previews/from-template`, `GET /api/v1/public/previews/attempts`.
- `POST /api/v1/public/briefs`.
- `POST /api/v1/public/variants/{id}/track` (beacons de A/B; se dispara solo si `data-variant` está presente y el visitante no es editor).
- **Nunca** inventar endpoints nuevos desde el tema; el backend es fijo.

## 8. SETTINGS Y CONFIGURACIÓN QUE EL TEMA LEE
- `sites.theme` → carpeta del tema.
- `settings` (por `site_id`): `wwi_nav` (JSON: brand, logo_letter, cta, cta_url, links[]), `site_name`, `wwi_brand_primary` (color primario), claves de consentimiento de cookies (`CookieConsentService::render()`).
- `Config::get('APP_URL'|'SITE_ID'|'site_name'|…)`.
- El tema debe leer `wwi_nav` con defaults (ver tema wwi) y renderizar el nav con `data-source="settings:nav:…"` para que sea editable en vivo.

## 9. PROHIBICIONES (si se rompe esto, se daña el sistema)
1. NO modificar `src/`, `public/api.php`, `public/index.php`, `public/admin.php`, esquema DB ni otros temas.
2. NO cambiar nombres de clases/ids/atributos del contrato (§3), ni firmas de funciones JS (§6), ni nombres de tokens CSS (§5).
3. NO consultar tablas directamente desde el tema (salvo `settings` para nav/brand); el contenido llega en `$page`/`$sections`.
4. NO añadir librerías JS pesadas (>30KB) ni frameworks; vanilla JS + CSS. Sin jQuery.
5. NO hardcodear textos de negocio en el tema (marca, precios, links): todo desde `$sections`/settings. El CI `hardcode-test.php` solo cubre widgets, pero la regla aplica al tema.
6. NO romper `data-editable`, `data-source`, `data-sid`, `data-variant`, `wwi-hide-*`.
7. NO asumir un solo idioma (hay ES/EN), ni un solo tenant (multi-site), ni un solo dispositivo (mobile-first).
8. NO usar `http://` ni recursos externos bloqueables; fuentes por Google Fonts (Inter + JetBrains Mono) o system.
9. NO inline `<script>` con datos sensibles; escape de salida SIEMPRE (`htmlspecialchars`).
10. NO tocar `X-Frame-Options` (el preview del wizard usa iframe mismo-origen: SAMEORIGIN).

## 10. REQUISITOS DE CALIDAD DEL TEMA (aceptación)
- **Performance**: LCP < 1.5s, CLS < 0.02, JS propio < 50KB gzip, animaciones solo `transform/opacity`, `content-visibility` donde aplique.
- **Accesibilidad WCAG 2.2 AA**: foco visible, contraste AA, `prefers-reduced-motion` respetado, targets ≥44px, HTML semántico.
- **Responsive**: mobile-first; `wwi-hide-mobile/tablet` deben funcionar; grids colapsan.
- **Modo claro/oscuro**: `:root[data-theme='dark'|'light']` con los tokens; el toggle del tema wwi (`#wwi-theme-toggle`) es opcional pero recomendado; si se incluye, persistir en `localStorage.wwi_theme` y respetar `__WWI_PREVIEW__`.
- **Editor en vivo funcional**: clic selecciona secciones, doble clic edita inline, sidebar guarda, re-render en vivo, drag&drop, fuentes, versiones, comentarios, A/B, cursores.
- **Wizard/Checkout funcionales**: todas las funciones JS de §6 definidas y el HTML de los widgets funciona sin cambios.
- **Preview del wizard**: si el tema tiene modal de preview, el iframe debe apuntar a `/api/v1/public/preview/{uuid}` (mismo origen).

## 11. ENTREGABLE DE UN TEMA NUEVO
```
templates/themes/<slug>/index.php     ← único archivo obligatorio (puede incluir assets propios)
templates/themes/<slug>/assets/…      ← opcional (css/js/img del tema)
```
- El `index.php` debe: (1) definir tokens y CSS del design system; (2) renderizar el nav (settings `wwi_nav` + `data-source`); (3) renderizar `$sections` con el contrato §3; (4) incluir `_shared/live-editor.php`; (5) renderizar `CookieConsentService::render()`; (6) definir todas las funciones JS de §6; (7) ser seguro (escape) y accesible.
- Activación: `UPDATE sites SET theme='<slug>' WHERE id=<site_id>;`
- **Prueba obligatoria** antes de activar: abrir el sitio con el tema, verificar editor en vivo, wizard, checkout dummy, tema claro/oscuro, móvil, y que los tests CI sigan verdes (`coverage-test.php`, `hardcode-test.php`).

## 12. CÓMO DEBE CHATGPT CONSTRUIR EL PROMPT MAESTRO DEL TEMA
Pídele que genere un prompt que incluya, en este orden:
1. **Rol**: "Eres un diseñador frontend senior + arquitecto de temas para WWI (CMS multi-site basado en BRICKs, PHP 8.3, sin frameworks)".
2. **Contexto**: pegar §1–§8 de este documento (o referenciarlo íntegro).
3. **Objetivo**: crear el tema `<slug>` con personalidad visual X (colores, tipografía, layout), manteniendo el contrato.
4. **Restricciones**: pegar §9 (prohibiciones) y §10 (calidad).
5. **Estructura a generar**: el `index.php` completo (con tokens, nav, loop de secciones, parcial del editor, cookie consent) + CSS + JS de las funciones de §6 (puede reutilizar la implementación del tema `wwi` como referencia exacta, adaptando estilos).
6. **Checklist de verificación** (§10) y prueba de activación (§11).
7. **Formato de entrega**: un solo archivo `templates/themes/<slug>/index.php` autocontenido (CSS y JS inline) + instrucciones de activación.

## 13. ANTI-CONFLICTOS (errores típicos que ChatGPT comete y hay que prohibir explícitamente)
- Inventar un `theme.json` o un sistema de "layouts" paralelo → **prohibido**: el tema es un solo `index.php`.
- Consultar `pages`/`sections` por su cuenta → **prohibido**: llegan en variables.
- Renombrar `.wwi-section` a `.section` o quitar `data-sid` → **rompe el editor**.
- Reimplementar el wizard/checkout con otros nombres de funciones → **rompe el HTML de los widgets**.
- Usar React/Vue/Tailwind CDN → **prohibido** (sin dependencias).
- Hardcodear "WWI", precios o links → **prohibido** (multi-tenant).
- Omitir `CookieConsentService::render()` → **incumple privacidad**.
- No respetar `data-theme` claro/oscuro → **rompe tokens**.
- Animaciones pesadas en scroll sin `reduced-motion` → **incumple accesibilidad**.
- Tocar `X-Frame-Options` o cargar el preview fuera del origen → **rompe el wizard**.

## 14. PEDS (gobernanza al implementar el tema)
- `php -l` al archivo del tema; JS extraído con `node --check`; CSS balanceado.
- Sin queries nuevas (el tema no consulta DB salvo settings); escape de salida.
- Idempotencia: activar el tema es un `UPDATE` reversible (`UPDATE sites SET theme='wwi' …`).
- Prueba en vivo: editor, wizard, checkout, temas, responsive, tests CI verdes.
- Sin código muerto; el tema viejo permanece intacto para rollback.
