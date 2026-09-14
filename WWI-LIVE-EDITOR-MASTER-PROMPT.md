# WWI — LIVE INLINE EDITOR MASTER PROMPT (Editor en vivo con barra lateral)

> Especificación del editor inline de WWI: barra lateral derecha que edita con el mouse cualquier sección/módulo/componente mientras navegas el sitio. Incluye sistema de inserción (secciones, bricks, imágenes, texto, bloques, filas, columnas, herramientas, código) y 32 innovaciones. **Estado: spec aprobada pendiente de implementación por fases.**

## 1. COMPRENSIÓN DEL REQUERIMIENTO (confirmación)
1. Al activar **"Editar sitio"**, se abre una **barra lateral derecha fija** con el editor completo.
2. **Selección con el mouse**: hover + clic sobre cualquier sección, módulo o componente del sitio → la barra carga su editor.
3. **Navegación sin salir del editor**: scroll vertical, cambio de menú y de páginas internas; el panel se re-vincula a los elementos de la nueva página.
4. **Sistema de inserción** dentro de la barra: secciones, bricks, imágenes, texto, bloques, filas, columnas, herramientas y código (como Webflow/Elementor/Gutenberg).
5. Todo token-gated (JWT), same-origin, tenant-checked, con guardado en vivo y re-render.

## 2. REQUERIMIENTOS FUNCIONALES

### R1. Barra lateral "Editor en vivo"
- Fija a la derecha, **redimensionable** (drag del borde) y colapsable; recuerda ancho y estado.
- Cabecera: breadcrumb **Página → Sección → Elemento** + botones Guardar/Publicar/Cerrar + estado de autosave.
- Pestañas: **Contenido | Diseño | Avanzado | IA** (contextuales al tipo de selección).
- Estados UX: sin selección (herramientas de página), selección, loading, error, vacío.
- Responsive: en móvil se convierte en bottom-sheet.

### R2. Selección inteligente de elementos
- Hover resalta el nodo bajo el cursor con chip de tipo/nombre; clic selecciona.
- **Alt+clic** selecciona el contenedor padre; **doble clic** entra en edición de texto inline.
- Esc deselecciona; clic fuera deselecciona; navegación por teclado entre padre/hijos/hermanos.
- Protecciones: nav del sitio, barra del editor y nodos del sistema no editables.
- Multi-selección (Ctrl/⌘+clic) para operaciones en lote (fase B).

### R3. Edición contextual
- **Secciones**: título, subtítulo, visibilidad + campos del schema del brick (ya existente).
- **Elementos internos** (texto, imagen, botón, enlace): binding por `data-editable` en los widgets → clave del config.
- **Autosave** con debounce + guardado explícito; indicador Guardando/Guardado/Error.
- **Undo/redo** (Ctrl+Z/Y) con pila de acciones; snapshots automáticos.

### R4. Sistema de inserción (Add)
- Insertar: **Secciones** (Brick Marketplace), **Bricks**, **Bloques/Patrones**, **Filas y Columnas** (layout), **Texto**, **Títulos**, **Imágenes** (biblioteca), **Botones**, **Separadores/Espaciadores**, **Embeds**, **Formularios**, **Mapas**, **Iconos**, **Galerías**, **Tablas** y **Código** (HTML/CSS/JS).
- Punto de inserción: antes/después de la selección o **drag & drop** con drop zones y snapping.
- Buscador con categorías, recientes y favoritos; previews en vivo.

### R5. Navegación durante la edición
- Scroll vertical manteniendo selección y panel.
- Cambio de **página interna / menú**: el panel detecta la nueva página y re-vincula selección/secciones.
- Selector de página y breadcrumb del sitio dentro de la barra.

### R6. Persistencia y seguridad
- Endpoints existentes (`GET /sections/{id}`, `PUT /sections/{id}`, `PUT /sections/reorder`, `GET /sections/{id}/render`) + nuevos para layout/elementos.
- Validación de payload, `@site_id` en toda query, escape de salida, token-gated.
- Optimistic UI con rollback y toasts de error (ya implementado en acople/desacople).

### R7. Accesibilidad y UX
- Focus visible, atajos documentados, reduced-motion, contraste AA.
- La barra no debe alterar el layout del sitio (overlay real).

## 3. MODELO DE DATOS (extensión propuesta)
- `sections.type`: `widget` | `custom` | `html` | **`layout`** (nuevo).
- Para `layout`: `config` guarda un **árbol JSON** `{rows:[{cols:[{span, elements:[{type,props}]}]}]}`.
- Nuevo widget `layout` (LayoutWidget) que renderiza filas/columnas/elementos con el design system.
- Elementos básicos como tipos del árbol: `text`, `heading`, `image`, `button`, `divider`, `spacer`, `html`, `icon`, `video`, `gallery`.
- Los widgets existentes exponen `data-editable="clave"` para mapear nodos DOM → config.

## 4. API NUEVA PROPUESTA
- `POST /api/v1/admin/pages/{pageId}/sections` (ya existe) → insertar secciones/bricks.
- `POST /api/v1/admin/sections/{id}/duplicate` → duplicar sección.
- `PUT /api/v1/admin/sections/{id}` → guardar config (existe).
- `GET /api/v1/admin/sections/{id}/render` → re-render en vivo (existe).
- `GET /api/v1/admin/elements/catalog` → catálogo de elementos insertables (tipos, iconos, schemas).
- `POST /api/v1/admin/media` (existente) para imágenes.
- `PUT /api/v1/admin/sections/reorder` (existe) para mover.

## 5. LAS 32 INNOVACIONES
1. **Inspector anclado al scroll**: la selección sigue visible mientras navegas (highlight sticky).
2. **Smart Select**: clic selecciona el elemento; Alt+clic el contenedor; doble clic edita texto in situ.
3. **Edición inline real** (contenteditable) sincronizada con el panel.
4. **Drop zones fantasma** entre secciones con snapping y preview del hueco.
5. **Árbol de estructura** (navigator) con jerarquía Página→Sección→Elemento y búsqueda.
6. **Time-travel visual**: historial con miniaturas y diff por sección.
7. **Undo/redo global** con pila visual y "replay" de cambios.
8. **Autosave inteligente** con estado y versiones cada N minutos.
9. **Borrador vs publicado por sección** (badge en el sitio).
10. **Multi-selección y lote** (mover, duplicar, ocultar, eliminar).
11. **Duplicar con un clic** (sección o elemento) con offset inteligente.
12. **Eyedropper de estilos**: copiar/pegar estilos entre elementos.
13. **Tokens en vivo**: cambiar color/fuente global y ver todo el sitio actualizarse.
14. **Biblioteca de patrones** (bloques compuestos) con previews reales.
15. **IA de copy**: mejorar, cambiar tono, generar variantes desde el panel.
16. **IA de estructura**: "agrega testimonios de 3 clientes" → sección completa (TIA integrada).
17. **IA de conversión**: sugerencias por sección con heatmap y copy.
18. **Responsive por breakpoint** con overrides Desktop/Tablet/Mobile y preview simultánea.
19. **Editor de código por elemento** (HTML/CSS/JS) con validación y sandbox.
20. **Editor de imagen inline**: crop, focal point, alt, compresión y WebP automático.
21. **Media library integrada** con drag & drop y búsqueda.
22. **SEO en vivo**: preview SERP, schema, headings y alt por página/sección.
23. **Accesibilidad en vivo**: checker de contraste/roles/foco con score.
24. **Performance en vivo**: peso por sección, LCP estimado, alertas de imágenes pesadas.
25. **Comentarios anclados** a elementos para feedback del cliente.
26. **Modo cliente**: solo texto/imágenes, estructura bloqueada, con aprobaciones.
27. **Colaboración en tiempo real**: cursores, presencia y bloqueo por sección.
28. **A/B testing visual** por sección con métricas de conversión.
29. **Programación de publicación** por sección (fecha/hora).
30. **Command palette (Ctrl+K)** para insertar/mover/seleccionar/editar por nombre.
31. **Snippets/símbolos reutilizables** que se actualizan en todo el sitio.
32. **Modo presentación** (oculta el editor) + snapshots automáticos antes de cambios masivos y rollback total.

## 6. FASES DE IMPLEMENTACIÓN
- **Fase A (MVP de este pedido)**: barra lateral fija redimensionable con pestaña Contenido (sección seleccionada + navegación entre elementos de la sección), selección con hover/clic de secciones y elementos con `data-editable`, navegación entre páginas sin perder el panel, Add básico (sección desde marketplace, texto, imagen, botón, código, fila/columna), autosave y undo básico.
- **Fase B**: edición inline contenteditable, árbol de estructura, drag & drop desde el panel, breakpoints, tokens, media editor.
- **Fase C**: IA (copy/estructura/conversión), colaboración, A/B, SEO/a11y/perf en vivo, comentarios, versiones visuales, modo cliente.

## 7. GOBERNANZA (PEDS)
- Toda query nueva con `@site_id`; payloads validados; escape de salida; token-gated.
- Estados UX completos (loading/empty/error/success) y reduced-motion.
- Prueba en vivo obligatoria de cada endpoint/flujo antes de cerrar.
- Sin código muerto ni duplicado; verificación `php -l` / `node --check` / CSS balanceado.
