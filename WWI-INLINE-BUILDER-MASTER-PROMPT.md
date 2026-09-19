# WWI — INLINE BUILDER MASTER PROMPT
## Constructor visual de filas y columnas con edición inline, slots «+» y paneles contextuales

> **Versión:** 1.0 · **Fecha:** septiembre 2026 · **Modo:** PEDS v1.0 (Inspeccionar → Proponer → Entregar → Verificar)
> **Ámbito:** CMS Wontia Web Intelligence (`wontia-web-intelligence`) — editor de sitio para los tenants (tema `default` y temas `wwi*`).
> **Regla de oro:** *si hay que explicarlo, está mal diseñado.* El usuario debe saber qué hacer solo con verlo.

---

# 0. CÓMO LEER ESTE PROMPT

Este documento es una **orden de construcción**. Está escrito para que un agente de IA o un desarrollador lo ejecute sin ambigüedad:

1. **§1** define el problema real (por qué el editor actual no se usa).
2. **§2** define el concepto: Fila → Columna → Slot → Bloque.
3. **§3** define la experiencia (clic, arrastre, «+», borrar, paneles).
4. **§4** lista **32 innovaciones funcionales** (obligatorias u opcionales por fase).
5. **§5** define la arquitectura, el stack exacto y el modelo de datos.
6. **§6** define la API.
7. **§7** define calidad: accesibilidad, performance, seguridad, compatibilidad.
8. **§8** define criterios de aceptación verificables (Definition of Done).
9. **§9** define el plan por fases.
10. **§10** define lo prohibido.
11. **§11** es el glosario que elimina la confusión actual.

**Instrucción al ejecutor:** implementa en el orden de las fases (§9), verificando cada criterio de §8 antes de avanzar. No inventes modelos paralelos a los existentes; extiende lo que ya funciona.

---

# 1. CONTEXTO Y PROBLEMA (INSPECCIÓN)

## 1.1 Qué existe hoy (verificado en código)

| Pieza | Estado actual |
|---|---|
| Render | El tema recorre `sections` (widgets) y las apila a ancho completo con `data-sid`, `data-widget`, `data-variant`. |
| Editor en vivo | `templates/themes/_shared/live-editor.php`: barra flotante «Editar sitio», panel lateral con pestañas, contornos `data-editable`, edición de texto con `contenteditable`, selección de elementos, re-render por sección. |
| Servicio | `LiveEditorService`: versiones por sección, comentarios anclados, presencia multiusuario, variantes A/B, planes/fuentes, telemetría de edición. |
| API | `sections` CRUD, `reorder`, `render`, `comments`, `versions`, `variants`, `presence`, `source/*`. |
| Widgets | 40+ bricks con `configSchema()` (campos editables, repeaters, fuentes dinámicas). |
| Admin | SPA con Pages → Pestañas **Páginas / Secciones** (drag & drop de secciones, ▲▼, añadir desde BrickHub). |

## 1.2 Por qué el usuario no puede editar (causa raíz)

1. **Modelo mental equivocado:** el sistema habla de «secciones» y «widgets» (vocabulario técnico), no de **filas, columnas y bloques** (vocabulario visual). El usuario no ve la estructura; no sabe dónde puede poner algo.
2. **No hay huecos visibles:** sin un lugar explícito donde soltar un bloque, el usuario no descubre que puede insertar. Un espacio vacío sin señalización no comunica nada.
3. **No hay borrado con restitución:** al no poder eliminar un elemento y recuperar su hueco, el usuario teme romper algo y abandona.
4. **Los paneles no siguen al elemento:** el panel lateral es fijo y genérico; no cambia según lo seleccionado (texto, imagen, botón…), así que hay que buscar la opción en lugar de que aparezca.
5. **La acción no es directa:** reordenar o mover requiere pasos (abrir panel, arrastrar en una lista) en vez de arrastrar sobre el propio sitio.

**Conclusión:** el problema no es de funciones, es de **modelo y señalización**. Este prompt reconstruye la capa de edición sobre el render existente, sin romperlo.

---

# 2. CONCEPTO — EL MODELO DE 4 NIVELES

Todo sitio WWI se compone de cuatro niveles. El usuario **ve los cuatro** en modo edición.

```
PÁGINA
└── FILA            (ancho completo; fondo propio; espaciado propio)
    └── COLUMNA(S)  (grilla de 12; 1–4 columnas por fila; ancho configurable)
        └── SLOT    (ranura vertical dentro de una columna; lista ordenada de bloques)
            └── BLOQUE   (unidad atómica: texto, imagen, video, botón, galería, brick completo…)
```

**Reglas del modelo**
- R1. Una fila contiene de 1 a 4 columnas (anchos que suman 12: 12 · 6+6 · 4+4+4 · 3×4 · 8+4…).
- R2. Cada columna contiene uno o más **slots apilados** verticalmente.
- R3. Un slot contiene bloques en orden vertical; puede estar **vacío** (muestra «+»).
- R4. Un bloque puede ser **atómico** (texto, imagen, botón, icono) o **compuesto** (brick completo: hero, planes, galería…), en cuyo caso ocupa el slot y su contenido interno se edita con su propio panel.
- R5. Todo nivel tiene: `id`, `site_id`, `page_id`, orden, visibilidad por breakpoint (desktop/tablet/móvil), estilos propios y estado.
- R6. **Compatibilidad total:** las páginas existentes (secciones apiladas) se migran a filas de 1 columna con 1 bloque (brick). Nada deja de funcionar.

**Terminología visible al usuario (obligatoria):** *Fila · Columna · Bloque*. Nunca «widget», «section», «config», «schema» en la UI.

---

# 3. EXPERIENCIA — REGLAS DE INTERACCIÓN (EL CORAZÓN)

## 3.1 Principio rector

| Intención del usuario | Gesto | Resultado |
|---|---|---|
| «Quiero cambiar esto» | **Clic** sobre el bloque | Se selecciona (contorno + barra de herramientas + panel contextual) |
| «Quiero añadir algo aquí» | **Clic en el «+»** del slot | Se abre el selector de bloques (paleta) y se inserta en ese slot |
| «Quiero mover esto» | **Arrastrar** el bloque (o su manija) | Se mueve con fantasma y línea de destino; soltar coloca |
| «Quiero mover esto de columna» | Arrastrar a otra columna/slot | Igual, con resaltado del slot receptor |
| «Quiero cambiar el ancho» | Arrastrar el **borde** entre columnas | Reparto 12 columnas con snap y previsualización |
| «Quiero borrar» | Botón 🗑 en la barra (o tecla `Supr`) | El bloque desaparece y **en su lugar queda un «+»** del mismo tamaño |
| «Quiero duplicar» | Botón ⧉ (o `Ctrl+D`) | Copia exacta justo debajo, seleccionada |
| «Quiero ocultarlo en móvil» | Botón 👁 con menú por breakpoint | Se atenúa en el canvas según la vista activa |
| «Quiero deshacer» | `Ctrl+Z` / `Ctrl+Y` | Reversa instantánea de cualquier acción anterior |
| «Quiero guardar» | Automático (debounce 800 ms) + indicador «Guardado ✓» | Sin botón de guardar; el usuario nunca pierde trabajo |

## 3.2 El «+» — especificación exacta (requisito del cliente)

- **Forma:** cuadrado con esquinas redondeadas (radio 16 px), borde 2 px **discontinuo**.
- **Tamaño:** 64×64 px (móvil 56×56). Nunca menor a 44×44 (área táctil WCAG).
- **Estilo:** **flat**, sin sombras duras; fondo `transparent`; color de borde y símbolo = color de acento del sitio con 55 % de opacidad.
- **Símbolo:** `+` de 28 px, trazo 2 px, centrado.
- **Estados:**
  - *reposo:* borde discontinuo tenue; tooltip «Añadir bloque».
  - *hover:* fondo del acento al 8 %, borde y `+` al 100 %, escala 1.03.
  - *foco (teclado):* anillo de foco 2 px + `outline-offset` 2 px.
  - *arrastre encima:* borde continuo, fondo 12 %, «+» → «⤓» y sombra suave.
- **Posición:** al final de cada slot (siempre visible en modo edición), y **también entre** bloques al arrastrar (línea de inserción).
- **Al borrar un bloque:** el hueco se convierte **inmediatamente** en este «+» (mismo tamaño que el bloque borrado, animación de 180 ms), de modo que el usuario puede volver a insertar sin buscar dónde.
- **Un clic en el «+»** abre la paleta de bloques en ese contexto; doble clic abre la paleta con búsqueda enfocada.

## 3.3 Señalización de la estructura (filas y columnas visibles)

En modo edición, el canvas muestra la estructura con etiquetas y contornos **finos, no invasivos**:

- **Fila:** etiqueta `Fila N` en la esquina superior izquierda (12 px, fondo del panel, radio 6 px) + contorno horizontal al hover.
- **Columna:** al hover de una fila, las columnas muestran un contorno vertical discontinuo y su ancho (`6/12`) + **manija de redimensionado** en el borde derecho.
- **Slot vacío:** «+» centrado (§3.2).
- **Bloque seleccionado:** contorno 2 px del acento + etiqueta con su tipo (`Texto`, `Imagen`, `Hero`, `Planes`…).
- **Barra de herramientas flotante** (anclada al bloque, nunca tapa el contenido):
  `[⣿ mover] [Tipo] [✎ editar] [⧉ duplicar] [👁 visibilidad] [🗔 estilos] [🗑 eliminar]`.
- **Toda la señalización desaparece** en modo «Previsualizar» y en el sitio publicado.

## 3.4 Paneles contextuales (requisito 4 del cliente)

El panel derecho **cambia según lo seleccionado**. Nunca muestra opciones que no aplican.

| Selección | Panel que se activa (contenido mínimo) |
|---|---|
| **Texto** | Editor rich-text completo: negrita/itálica/subrayado/tachado, H1–H6, listas, cita, enlace, alineación, color, resaltado, tamaño, espaciado entre líneas, fuente (tokens del tema), limpiar formato, deshacer en el campo, contador de palabras, sugerencia de TIA («mejorar redacción», «traducir», «acortar»). |
| **Imagen** | Biblioteca (Media) + subir/arrastrar, `alt` obligatorio con aviso, encuadre (cover/contain), punto focal, relación de aspecto, overlay, sombra, radio, enlace, reemplazo arrastrando desde la biblioteca. |
| **Video** | YouTube/Vimeo/MP4, poster, autoplay/silenciado/bucle, controles, proporción, radio, overlay. |
| **Botón** | Texto, enlace (interno/externo/ancla/teléfono/WhatsApp), estilo (primario/secundario/fantasma), tamaño, icono, abrir en pestaña nueva, evento de conversión. |
| **Galería/Slider** | Fuente de imágenes, estilo de miniaturas (los 6 existentes), autoplay, proporción, ancho (100/66/33 %), alineación. |
| **Icono** | Set, nombre, tamaño, color, enlace. |
| **Formulario** | Campos (añadir/quitar/reordenar), destino (correo/CRM), texto del botón, aviso legal, anti-spam. |
| **Brick compuesto** | Su `configSchema()` existente, presentado en el mismo lenguaje visual (secciones plegables, campos con etiquetas humanas). |
| **Fila** | Fondo (color/imagen/gradiente/video), altura mínima, padding vertical, alineación vertical, ancho máximo del contenido, separadores, etiqueta de ancla. |
| **Columna** | Ancho (1–12), alineación horizontal, padding, fondo, borde, radio. |
| **Slot** | Distribución (apilado / en fila / grilla), espacio entre bloques, alineación, justificación. |
| **Página** | Título, slug, SEO, tokens de marca (colores/tipografías), favicon, modo mantenimiento, idioma. |

**Regla:** el panel se abre automáticamente al seleccionar y **se actualiza en < 100 ms**. Si no hay selección, muestra el árbol de la página (estructura).

## 3.5 Atajos (obligatorios)

`Esc` deseleccionar · `Supr`/`Backspace` borrar · `Ctrl/Cmd+Z` deshacer · `Ctrl/Cmd+Shift+Z` rehacer · `Ctrl/Cmd+D` duplicar · `Ctrl/Cmd+S` guardar ahora · `Ctrl/Cmd+K` paleta de bloques · `Ctrl/Cmd+Shift+P` previsualizar · `↑/↓` navegar bloques hermanos · `Tab` entrar/salir del bloque · `Alt+↑/↓` mover bloque arriba/abajo · `[` `]` redimensionar columna.

---

# 4. LAS 32 INNOVACIONES FUNCIONALES

> Todas son **funcionales y verificables**. Las marcadas **NÚCLEO** son obligatorias para la Fase 1.

## A. Estructura y layout
1. **NÚCLEO — Filas visibles** con etiqueta y contorno; añadir fila entre dos filas con «+» horizontal.
2. **NÚCLEO — Columnas de 1 a 4** con reparto 12 columnas, snap y previsualización al arrastrar bordes.
3. **NÚCLEO — Slots por columna** con «+» al final y entre bloques.
4. **NÚCLEO — Borrar → «+»** en el mismo hueco (con `Deshacer` en toast 4 s).
5. **Plantillas de fila**: guardar una fila (columnas + bloques) como plantilla y reutilizarla; biblioteca de filas por sitio y globales.
6. **Anidamiento controlado**: un slot puede contener una «fila anidada» (1 nivel) para layouts complejos, con sangría visual.
7. **Guías y cuadrícula**: superposición opcional de la grilla 12 con `Alt+G`; reglas y distancias al arrastrar.
8. **Alinear/distribuir**: selección múltiple (Shift+clic) con alinear izquierda/centro/derecha y distribuir espacio.

## B. Interacción y control
9. **NÚCLEO — Selección por clic con barra flotante** (mover, duplicar, visibilidad, estilos, eliminar).
10. **NÚCLEO — Drag & drop con fantasma y línea de inserción** (Pointer Events; funciona con ratón, lápiz y táctil).
11. **NÚCLEO — Deshacer/Rehacer ilimitado por sesión** con pila de comandos inversos (no snapshots completos).
12. **Autoguardado con indicador** («Guardando…», «Guardado ✓», «Error — reintentar») y guardado por lotes cada 800 ms.
13. **Bloqueo optimista**: si otro usuario edita el mismo bloque, aviso y opción «ver cambios» (usa la presencia existente).
14. **Selección múltiple** y acciones en lote (borrar, duplicar, ocultar, mover a otra columna).
15. **Papelera de la página**: lo borrado va a una papelera (recuperable 30 días), no desaparece para siempre.
16. **Modo enfoque**: atenúa todo excepto el bloque seleccionado (`Ctrl+Shift+F`).

## C. Contenido y edición inline
17. **NÚCLEO — Doble clic para editar texto in situ** (contenteditable con `Selection`/`Range`), sin abrir modal.
18. **Pegado inteligente**: al pegar desde Word/web, limpia estilos y conserva estructura (listas, enlaces, negritas).
19. **Panel de texto potente** (§3.4) con contador y corrector básico en español.
20. **Reemplazo de imagen arrastrando** desde la biblioteca directamente sobre la imagen del canvas.
21. **Edición de enlaces in situ**: clic en un enlace abre un globo con URL, texto y «abrir en pestaña nueva».
22. **Biblioteca de contenido**: guardar un bloque (texto+estilo) como «favorito» reutilizable.
23. **TIA inline**: seleccionar texto → «Mejorar», «Acortar», «Traducir», «Tono profesional»; el resultado se aplica con vista previa y «Deshacer».

## D. Responsive y estilos
24. **NÚCLEO — Vista por breakpoint** (Escritorio/Tablet/Móvil) con overrides por nivel (ocultar, apilar, tamaño de fuente, padding) sin duplicar contenido.
25. **Apilado automático en móvil** con opción «mantener lado a lado» por fila.
26. **Tokens de marca aplicados en vivo** (colores, tipografías, radios) desde el panel de página; cambiar un token actualiza todo el sitio.
27. **Estilos por bloque** con herencia visible (muestra de dónde viene cada valor y botón «restablecer»).
28. **Modo alto contraste/oscuro del editor** independiente del tema del sitio.

## E. Confianza, colaboración y publicación
29. **NÚCLEO — Previsualización y publicación**: «Previsualizar» (borrador privado con URL firmada) y «Publicar» (a producción), con historial de versiones por página y rollback visual (ya existe versionado por sección: se reutiliza).
30. **Comentarios anclados a bloques** (ya existen por sección): hilo por bloque, resolver, menciones y notificación por correo.
31. **Auditoría por usuario**: quién cambió qué y cuándo, con diff legible («texto anterior → texto nuevo»).
32. **Salud del sitio en vivo** (semáforo por bloque): contraste, `alt` faltante, encabezados desordenados, enlaces rotos, peso de imágenes, LCP estimado — con «arreglar» de un clic cuando sea posible.

**Bonus (si el tiempo lo permite):** modo cliente restringido (solo texto/imágenes), programación de publicación, A/B de copy por bloque, export/import JSON de página entre sitios, y command palette para insertar cualquier bloque escribiendo su nombre.

---

# 5. ARQUITECTURA Y STACK EXACTO

## 5.1 Línea de desarrollo (obligatoria)

- **Backend:** PHP **8.3** (sin frameworks, PSR-4 con el autoload existente). Acceso a datos con **PDO** y *prepared statements* (`prepare → execute → fetch`), siempre con `site_id = @site_id`.
- **Base de datos:** **MariaDB 10.11** (contenedor `mysql-prod`, base `wontia`). DDL idempotente (`CREATE TABLE IF NOT EXISTS`), migraciones en `install/` + `ensureTables()`.
- **Frontend:** **JavaScript ES2022 nativo** (sin React/Vue/jQuery), **CSS Grid + Custom Properties**, **HTML5 semántico**, **SVG inline**. Cero dependencias externas (ni CDN).
- **Transporte:** `fetch` + JSON; `BroadcastChannel` para sincronizar pestañas; `navigator.sendBeacon` para telemetría.
- **APIs del navegador a usar:** Pointer Events (arrastre), Selection/Range (texto), MutationObserver (re-render), IntersectionObserver (perf), ResizeObserver (layout), Web Animations (micro-motion), Clipboard API, File System Access (export opcional).
- **Servidor web:** Nginx (contenedor propio por tenant). Sin build step: el editor es un archivo JS/CSS servido por el tema compartido.
- **Prohibido:** frameworks JS, bundlers, jQuery, librerías de drag&drop de terceros, `innerHTML` con datos de usuario sin sanear.

## 5.2 Modelo de datos (nuevo, aditivo)

```
wwi_page_rows      id, site_id, page_id, sort_order, layout(JSON: fondo/padding/ancho/etiqueta), is_active, created_at, updated_at
wwi_page_columns   id, site_id, row_id, sort_order, span(1..12), layout(JSON), is_active
wwi_page_slots     id, site_id, column_id, sort_order, layout(JSON: gap/align/distribucion)
wwi_page_blocks    id, site_id, slot_id, sort_order, type('text'|'image'|'video'|'button'|'icon'|'gallery'|'form'|'brick'),
                   brick_slug(NULL), props(JSON), styles(JSON), responsive(JSON desktop/tablet/mobile),
                   visibility(JSON), is_active, created_at, updated_at
wwi_page_revisions id, site_id, page_id, user_id, label, tree(JSON completo), created_at
wwi_page_trash     id, site_id, page_id, node_type, node_id, payload(JSON), deleted_by, deleted_at
```
Índices por `(site_id, page_id, sort_order)` y `(site_id, slot_id, sort_order)`. FK con `ON DELETE CASCADE` dentro del mismo sitio.

**Compatibilidad:** una página sin filas (`wwi_page_rows` vacío) se renderiza **exactamente como hoy** (sections apiladas). La migración «Convertir a filas» crea una fila/columna/slot por sección existente con un bloque `brick`.

## 5.3 Render (servidor)

- `PageRendererService::renderTree(pageId): string` construye el HTML del árbol con atributos de identidad:
  `data-row`, `data-col`, `data-slot`, `data-block`, `data-block-type`, `data-brick`.
- Los **bloques atómicos** se renderizan con plantillas PHP del tema (`text.php`, `image.php`, `button.php`…).
- Los **bloques brick** delegan en `WidgetRegistry::render($slug, $props)` (reutilización total de los 40+ bricks).
- **Cero HTML del editor en el sitio publicado**: el editor inyecta su capa solo si hay sesión de edición (token) o `?wwi_edit=1` con permisos.

## 5.4 Editor (cliente)

Módulo único `builder.js` (IIFE, sin dependencias) con submódulos internos:
- `Store` (estado + historial de comandos), `Renderer` (parcheo del DOM por bloque), `Selection`, `DragLayer`, `Palette`, `Inspector`, `Toolbar`, `TextEditor`, `MediaPicker`, `Responsive`, `Health`, `Comments`, `Presence`, `Autosave`, `Shortcuts`, `A11y`.
- Comunicación: eventos internos (`bus.emit('block:update', …)`) y `fetch` a la API.
- El canvas es el **sitio real** (no un iframe), con la capa de edición superpuesta (`position:absolute` en un contenedor `pointer-events:none` salvo controles).

---

# 6. API (REST, JWT + rol)

```
GET    /api/v1/admin/builder/tree?page_id=            → árbol completo (filas/columnas/slots/bloques)
POST   /api/v1/admin/builder/rows                     → crear fila (position)
PATCH  /api/v1/admin/builder/rows/{id}                → layout/orden
DELETE /api/v1/admin/builder/rows/{id}                → a papelera
POST   /api/v1/admin/builder/columns                  → crear columna (row_id, span, position)
PATCH  /api/v1/admin/builder/columns/{id}
DELETE /api/v1/admin/builder/columns/{id}
POST   /api/v1/admin/builder/blocks                   → crear bloque (slot_id, type|brick_slug, position)
PATCH  /api/v1/admin/builder/blocks/{id}              → props/styles/responsive/visibility
POST   /api/v1/admin/builder/blocks/{id}/duplicate
POST   /api/v1/admin/builder/blocks/{id}/move         → {slot_id, position}
DELETE /api/v1/admin/builder/blocks/{id}              → a papelera
POST   /api/v1/admin/builder/trash/{id}/restore
POST   /api/v1/admin/builder/batch                    → lote de operaciones (autosave/undo masivo)
POST   /api/v1/admin/builder/publish                  → publica la página (snapshot + invalidación de caché)
GET    /api/v1/admin/builder/revisions?page_id=
POST   /api/v1/admin/builder/revisions/{id}/restore
GET    /api/v1/admin/builder/palette                  → bloques atómicos + bricks disponibles
GET    /api/v1/admin/builder/health?page_id=          → semáforo por bloque
```
**Reglas:** todo endpoint valida `site_id`, rol (`editor|admin|superadmin`), tamaño de payload y **sanea** `props` (whitelist por tipo). Los lotes son idempotentes por `client_op_id`.

---

# 7. CALIDAD (OBLIGATORIA)

## 7.1 Accesibilidad (WCAG 2.2 AA)
- Todo control alcanzable por teclado; `Tab` ordena por fila→columna→bloque.
- `aria-label` en cada control; `role="list"/"listitem"` en slots y bloques; `aria-live="polite"` para anunciar «Bloque añadido», «Bloque eliminado», «Guardado».
- Foco visible siempre; nunca `outline:none` sin sustituto.
- Contraste ≥ 4.5:1 en controles; respeta `prefers-reduced-motion` (sin animaciones de arrastre).

## 7.2 Performance
- `builder.js` ≤ **60 KB** minificado (sin dependencias) y `builder.css` ≤ **20 KB**.
- Interacción < 16 ms por frame durante el arrastre; sin re-render completo (parcheo por bloque).
- Autosave por lotes; sin recargas de página; imágenes con `loading="lazy"` y `srcset`.
- Presupuesto del sitio publicado intacto: **LCP < 1.5 s**, **CLS < 0.02**, JS del sitio < 50 KB (el editor no se sirve al visitante).

## 7.3 Seguridad
- Sanitización de HTML en `props` (whitelist de etiquetas/atributos por tipo de bloque) — nunca confiar en el cliente.
- Escapado de salida en el render (`htmlspecialchars`).
- Permisos por rol: `client` (solo texto/imágenes de su sitio), `editor`, `admin`, `superadmin`.
- Auditoría: cada mutación registra usuario, IP, acción y diff (reutiliza `wwi_audit_logs`/telemetría existente).
- Sin secretos en el cliente; JWT en `Authorization`; CSRF mitigado por token en cabecera.

## 7.4 Compatibilidad
- Los temas `default`, `wwi` y `wwi-intelligence` siguen renderizando sus `sections` como hasta hoy.
- El editor debe funcionar en **Chrome/Edge 115+, Firefox 115+, Safari 16.4+**.
- Migración reversible: «Convertir a filas» guarda un snapshot previo en `wwi_page_revisions`.

---

# 8. CRITERIOS DE ACEPTACIÓN (DEFINITION OF DONE)

Debe cumplirse **todo** y probarse en vivo (navegador real + API):

**Estructura**
- [ ] En modo edición se ven filas y columnas con etiquetas y contornos.
- [ ] Se puede añadir una fila, dividirla en columnas (1–4) y redimensionar sus anchos con snap.
- [ ] Cada slot vacío muestra el «+» según §3.2 (flat, cuadrado redondeado, 64 px, borde discontinuo).

**Insertar / borrar**
- [ ] Clic en «+» abre la paleta y al elegir un bloque se inserta **en ese slot**, seleccionado.
- [ ] Clic en un bloque lo selecciona (contorno + barra + panel contextual correcto).
- [ ] `Supr` o 🗑 elimina el bloque y **en su lugar aparece el «+»** del mismo tamaño (con «Deshacer» 4 s).
- [ ] Duplicar, mover arriba/abajo y mover a otra columna funcionan con arrastre y con teclado.

**Edición**
- [ ] Doble clic en un texto permite editarlo in situ y guardarlo con `Esc`/clic fuera.
- [ ] El panel de texto incluye el editor rich-text completo de §3.4 y funciona con teclado.
- [ ] Los paneles cambian por tipo de bloque (texto, imagen, botón, video, galería, brick) sin recargar.
- [ ] Reemplazar una imagen arrastrando desde la biblioteca funciona.

**Confianza**
- [ ] Deshacer/Rehacer cubre todas las acciones (mover, borrar, editar, estilo).
- [ ] Autoguardado con indicador; recargar la página conserva el estado exacto.
- [ ] «Previsualizar» y «Publicar» funcionan; el historial permite volver a una versión anterior.
- [ ] Un usuario `client` no puede tocar estructura ni estilos globales.

**Calidad**
- [ ] Cero errores en consola; 0 peticiones externas.
- [ ] Todo el flujo operable solo con teclado.
- [ ] `php -l` limpio; todas las queries con `@site_id`; sanitización verificada con payload malicioso de prueba.

---

# 9. PLAN POR FASES

| Fase | Entregable | Contenido |
|---|---|---|
| **F1 — Esqueleto visible** | Filas/columnas/slots + «+» + borrar→«+» | Modelo de datos, render, árbol, «+», paleta mínima (texto, imagen, botón, brick), selección, borrar/duplicar, undo básico, autosave. |
| **F2 — Paneles contextuales** | Inspector por tipo | Editor rich-text completo, imagen con biblioteca, botón, video, galería; responsive por breakpoint; tokens de marca. |
| **F3 — Mover como un profesional** | Drag & drop total | Arrastre de bloques entre slots/columnas, redimensionado de columnas con snap, reordenar filas, selección múltiple, atajos completos. |
| **F4 — Confianza** | Versiones, papelera, comentarios, auditoría | Revisión/publish, papelera 30 días, comentarios por bloque, salud del sitio, modo cliente. |
| **F5 — Inteligencia** | TIA en el canvas | Reescribir/acortar/traducir texto, generar bloque por prompt, auto-layout sugerido, A/B de copy. |

**Regla de fase:** no se avanza sin pasar los criterios de §8 correspondientes a lo construido, con prueba en vivo.

---

# 10. LO PROHIBIDO (NO HACER)

1. **NO** introducir frameworks ni dependencias (React, Vue, jQuery, SortableJS, Quill, TinyMCE…).
2. **NO** romper el CMS actual: Pages, Secciones, Widgets, Blog, Media, Tienda y el flujo de compra siguen funcionando.
3. **NO** duplicar conceptos: el builder **usa** los bricks existentes (`WidgetRegistry`), no los reimplementa.
4. **NO** guardar HTML crudo del usuario sin sanear.
5. **NO** mostrar vocabulario técnico al usuario (widget/section/config/schema).
6. **NO** añadir botón «Guardar»: el autoguardado es la norma.
7. **NO** usar `innerHTML` con datos de usuario; usar `textContent`/`createElement`.
8. **NO** cargar el editor en el sitio publicado para visitantes anónimos.

---

# 11. GLOSARIO (elimina la confusión actual)

- **Página:** un documento del sitio (home, servicios, contacto…).
- **Fila:** banda horizontal de ancho completo; contiene columnas.
- **Columna:** división horizontal de una fila (1–4), medida en 12 avos.
- **Slot:** ranura vertical dentro de una columna donde viven los bloques.
- **Bloque:** unidad de contenido (texto, imagen, video, botón, galería, formulario o un brick completo).
- **Brick:** bloque compuesto del catálogo WWI (hero, planes, FAQ, tienda…), con su propio panel.
- **Paleta:** selector de bloques que se abre desde un «+».
- **Inspector / Panel contextual:** panel derecho que edita lo seleccionado.
- **Canvas:** el sitio real en modo edición.
- **Árbol:** vista jerárquica de la página (filas → columnas → slots → bloques).
- **Publicar:** llevar el borrador al sitio en vivo.

---

# 12. INSTRUCCIÓN FINAL AL EJECUTOR

1. **Inspecciona** los archivos citados antes de escribir una línea (`live-editor.php`, `LiveEditorService`, `SectionController`, `WidgetRegistry`, `PageRenderer` si existe, `admin.js`).
2. **Construye la Fase 1 completa** con los criterios de §8 verificados en vivo (navegador headless + API).
3. **Reporta** en el formato PEDS: qué se inspeccionó, qué se propuso, qué se implementó (archivos + commits), cómo se verificó (evidencia), qué queda pendiente.
4. **Si algo del requerimiento es ambiguo, pregunta antes de inventar.** El objetivo es que el usuario, al abrir el editor, sepa exactamente qué hacer **solo con verlo**.

> **Métrica de éxito final:** una persona sin conocimientos técnicos abre «Editar sitio», ve las filas y columnas, hace clic en un «+», elige un bloque, lo edita, lo mueve, lo borra y lo vuelve a poner — **sin que nadie se lo explique**.
