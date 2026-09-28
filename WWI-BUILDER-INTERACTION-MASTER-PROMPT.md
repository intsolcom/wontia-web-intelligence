# WWI — BUILDER INTERACTION MASTER PROMPT (Selección · Mover · Eliminar en modo edición)

> **Versión:** 1.0 · **Fecha:** septiembre 2026 · **Modo:** PEDS v1.0 (Inspeccionar → Proponer → Entregar → Verificar)
> **Requerimiento:** al activar **Editar sitio** (toggle del builder), TODOS los elementos deben mostrarse **seleccionables** para **editar, mover/cambiar de sitio o eliminar**, con affordances claras y a prueba de errores. Objetivo: que la experiencia de edición visual sea la más potente del mercado.

## 1. INSPECCIÓN (estado actual, evidencia en código)

- **Entrada**: `#wb-toggle` ("🧱 Bloques") en `builder.js` activa `body.wb-on` (modo edición). El builder se inyecta desde `_shared/live-editor.php` con `?v=filemtime` (anti-caché).
- **Ya implementado** (`builder.js` + `builder.css`):
  - Hover/outline en fila (`.wwi-b-row`), columna (`.wwi-b-col`) y bloque (`.wwi-b-block`).
  - **Selección de bloque** (clic, fase de captura; los botones/enlaces NO navegan en modo edición).
  - **Toolbar de bloque** (`.wb-tools`): handle de arrastre, etiqueta, subir/bajar, duplicar, **eliminar**.
  - **Fila**: etiqueta arrastrable "Fila N", acciones (subir/bajar/duplicar/eliminar).
  - **Columna**: borrar y **resize** (drag handle).
  - **Drag & drop**: bloques entre slots con **línea de inserción**; filas completas.
  - **Papelera + Deshacer** al eliminar (toast con "Deshacer").
  - **Placeholder wireframe** en slot vacío ("＋ Espacio disponible · haz clic para añadir").
  - **Panel inspector** derecho (contenido/diseño/avanzado), **paleta** con buscador, **revisiones**, **visibilidad por dispositivo**, **alineación**.

### Brechas detectadas
1. Falta una **barra de selección flotante** unificada (editar/mover/duplicar/ocultar/eliminar) anclada al elemento.
2. **Sin selección múltiple** (Shift/marquee) ni acciones en lote.
3. **Sin árbol lateral de estructura** (Navigator) sincronizado.
4. **Sin atajos de teclado** completos (mover con flechas, duplicar, copiar/pegar, deshacer global).
5. **Sin breadcrumb de jerarquía** ni "seleccionar contenedor".
6. **Sin bloqueo** de elementos ni **copiar/pegar estilos**.
7. Accesibilidad/descubrimiento mejorables (foco, ARIA, hint de primer uso).

## 2. INVESTIGACIÓN (cómo debe ser, referentes)

Patrón estándar de los editores visuales líderes (**Elementor** —Navigator/Structure, inline editing, "move sections", multi-select, nested elements—, **Webflow** —Navigator, selection/hierarchy, style panel—, **Wix**, **Framer**, **GrapesJS** —Selection/Drag/Components manager—, **Builder.io**, **WordPress Gutenberg**):

- **Selección**: hover con outline + chip de etiqueta; clic selecciona; Alt/Esc sube a contenedor; Shift añade a multi-selección; clic en zona vacía deselecciona.
- **Mover**: drag con línea de inserción y fantasma; drag de contenedores; auto-scroll; soltar dentro/fuera de contenedores; snap.
- **Eliminar**: tecla Supr + botón; **deshacer** inmediato; confirmación configurable.
- **Estructura**: árbol lateral (Navigator) con iconos por tipo, drag para reordenar, visibilidad por dispositivo.
- **Inspector**: panel contextual por elemento (contenido/diseño/avanzado), tokens/variables, responsive por breakpoint.
- **Historial**: deshacer/rehacer global; versiones; retención.

## 3. ARQUITECTURA DE INTERACCIÓN (propuesta)

- **Un solo modelo de selección** `S.sel = {kind:'row|col|slot|block', id}` con overlay global que dibuja el outline + chip + barra flotante.
- **Barra de selección flotante** (FAB) anclada al elemento: ✎ Editar · ⣿ Mover · ⧉ Duplicar · 👁 Ocultar · 🗑 Eliminar (nunca navega; en modo edición).
- **Árbol de estructura** (panel "Estructura") que replica el árbol de filas/columnas/slots/bloques; clic sincroniza selección y scroll; drag reordena.
- **Teclado**: ↑↓ mover, Supr/Backspace eliminar, Ctrl/⌘+D duplicar, Ctrl/⌘+C/V copiar-pegar, Esc deseleccionar/subir, Ctrl/⌘+Z / Shift+Z deshacer/rehacer.
- **Todo pasa por API builder** (`/api/v1/admin/builder/*`) con `@site_id`, preparado, idempotente y con **revisiones** automáticas antes de operaciones destructivas.

## 4. LAS 30 INNOVACIONES

### A. Selección y affordance
1. **Overlay de selección unificado** (fila/columna/slot/bloque): outline + esquinas + **chip con tipo y nº**.
2. **Barra de selección flotante** anclada al elemento con editar/mover/duplicar/ocultar/eliminar.
3. **Selección múltiple** (Shift+clic y marquee) con **acciones en lote** (mover/eliminar/duplicar).
4. **Breadcrumb de jerarquía** (Fila 3 › Columna 1 › Bloque 2) y **"seleccionar contenedor"** (Alt/clic en chip / Esc sube).
5. **Ir al elemento**: clic en la página ↔ clic en el árbol, con **scroll sincronizado**.
6. **Teclado completo** (flechas para mover, Supr, Ctrl+D, Ctrl+C/V, Ctrl+Z/Y, Esc).
7. **Modo estructura/wireframe global**: muestra todos los contenedores y sus límites.

### B. Mover / reordenar
8. **Drag & drop con fantasma** y línea de inserción entre bloques y contenedores.
9. Arrastrar **filas**, **columnas** y **bloques entre columnas** con reglas de contención.
10. **Auto-scroll** durante el arrastre + **snap** a slots/columnas/tamaños.
11. **Redimensionar columnas** y **presets** de rejilla (50/50, 33/66, 25/25/25/25…).
12. **Duplicar arrastrando** (Alt+drag) y **mover a otra página/patrón**.
13. **Árbol lateral con drag** para reordenar cualquier nivel (Navigator).
14. **Deshacer/rehacer global** de todas las operaciones (mover, borrar, duplicar, estilo).

### C. Eliminar y estados vacíos
15. **Eliminar** con Supr, botón y barra flotante; **Deshacer** inmediato; confirmación configurable.
16. **Contenedor vacío = wireframe** con "espacio disponible", dimensiones y CTA de añadir.
17. **Ocultar/mostrar** por dispositivo desde la barra (sin borrar).
18. **Bloqueo (lock)** de elementos para evitar mover/borrar por accidente.

### D. Contexto y propiedades
19. **Inspector contextual** por tipo con pestañas Contenido/Diseño/Avanzado.
20. **Edición inline** (doble clic) con barra de formato y guardado por bloque.
21. **Copiar/pegar estilos** (eyedropper) entre elementos.
22. **Tokens/variables** de diseño reutilizables (color, espaciado, tipografía).
23. **Responsive por breakpoint** con overrides y "copiar de escritorio".
24. **Componentes/símbolos** reutilizables enlazados (cambiar uno = cambia todos).

### E. Precisión, colaboración e IA
25. **Guías y espaciado en vivo** (arrastrar padding/margin con medidores).
26. **Alineación inteligente** con snap a otros elementos y distribución uniforme.
27. **Multi-idioma** y **variantes A/B por elemento**.
28. **Comentarios anclados** al elemento y **presencia** en vivo (cursores).
29. **Historial por elemento** con comparación (diff) de cambios.
30. **Asistente TIA contextual**: con un elemento seleccionado, comandos naturales ("mejora este texto", "muévelo arriba", "hazlo responsive", "cambia el color").

## 5. FASES
- **Fase 1 (núcleo, HECHA)**: barra flotante del bloque (handle/label/↑↓/⧉/🗑) + **breadcrumb de jerarquía** (`#wb-crumb`: Fila › Col › Bloque; segmentos hacen scroll+flash; ✕ deselecciona) + **atajos**: ↑/↓ mover, `Ctrl/⌘+D` duplicar, `Ctrl/⌘+C/V` copiar/pegar, `Supr/Backspace` eliminar, `Esc` deseleccionar, `Ctrl/⌘+Z` / `Ctrl+Shift+Z`/`Ctrl+Y` **deshacer/rehacer global** (pila local de operaciones con inversas vía API) + hint de primer uso. Verificado E2E.
- **Fase 2 (HECHA)**: **Navigator** lateral (`🌳 Estructura` → `#wb-tree`: filas › columnas › bloques; clic sincroniza selección+scroll; **drag** para reordenar bloques y filas), **selección múltiple** (Shift+clic y **marquee**) con **barra de lote** (`#wb-batch`: duplicar/eliminar/ocultar) y **refresco real del canvas** (`refreshCanvas()` re-renderiza vía `/builder/render` en vez de placeholders). Deshacer/rehacer global ya en Fase 1.
- **Fase 3 (HECHA)**: **copiar/pegar estilos** (`🎨/🖌` en el panel), **tokens de color** (swatches + guardar; persistidos en `settings.builder_tokens`, aplican `styles.background`), **responsive preview** (switcher 🖥/▭/▯ en la barra → `body.wb-dev-*` con ancho de canvas) + ocultar/mostrar por dispositivo (existente) y **bloqueo (lock)** por bloque (`props._locked`; bloquea arrastre, ↑↓ y Supr; badge 🔒).
- **Fase 4 (parcial)**: **asistente TIA contextual** HECHO (en el panel, bloques de texto: `🤖 TIA` con Mejorar/Acortar/Ampliar/Traducir EN-ES y prompt libre; reescribe `props.html` vía `POST /api/v1/admin/brick/request`, verificado con DeepSeek). **Pendiente (Fase 4b, requiere esquema/modelo):** componentes/símbolos enlazados, comentarios anclados + presencia/cursores, historial por elemento con diff.

## 6. GOBERNANZA (PEDS — Monitor de Código, 6 puntos)
1. **Sintaxis**: `php -l` / `node --check`.
2. **Seguridad**: PDO prepared (`prepare → execute → fetch`), `@site_id` en toda query, escape, sin secretos.
3. **Buenas prácticas**: sin duplicación ni código muerto; reutilizar el modelo de selección existente.
4. **Idempotencia**: DDL `IF NOT EXISTS`; revisiones antes de operaciones destructivas.
5. **Accesibilidad/UX**: focus-visible, ARIA, atajos documentados, reduced-motion, hints.
6. **Verificación**: lint + prueba en vivo (E2E) de seleccionar, mover, cambiar de sitio y eliminar.

## 7. CRITERIO DE ACEPTACIÓN
- Al activar **Editar sitio**, todo elemento es **seleccionable** con affordance clara y **no navega**.
- Se puede **mover/cambiar de sitio** (drag, teclado y árbol) y **eliminar** (con deshacer), en **fila/columna/bloque**.
- Cero elementos visibles sin forma de editar/mover/eliminar. El **Monitor de Código (6 puntos)** pasa en cada cambio.
