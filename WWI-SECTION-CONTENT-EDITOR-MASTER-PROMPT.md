# WWI — SECTION CONTENT EDITOR MASTER PROMPT

> **Versión:** 1.0 · **Fecha:** septiembre 2026 · **Modo:** PEDS v1.0 (Inspeccionar → Proponer → Entregar → Verificar)
> Objetivo: convertir la edición de contenido de secciones en una experiencia de nivel mundial — sin modales, sin JSON crudo para contenido, con diseño editable, validación, accesibilidad, IA y colaboración — sobre el stack existente (NO crear un segundo sistema de páginas).

## 1. INSPECCIÓN (estado actual, evidencia en código)

- **Editor de sección**: ya es una página dentro del admin SPA (`W.renderSectionEditor`, `public/assets/js/admin.js`) con navegación `#section-edit-{id}` (router en `admin.js`). Reemplazó al modal.
- **Campos**: se generan desde `configSchema()` del widget con `W.renderSchemaFields()` (`admin.js`). Tipos soportados: `text`, `textarea`, `richtext`, `number`, `range`, `color`, `select`, `hgroup`/`hlevel`, `toggle`, y default (JSON crudo).
- **Persistencia**: `PUT /api/v1/admin/sections/{id}` → `SectionController::update()`; versiones vía `LiveEditorService::addVersion()`; auditoría por campo vía `trackEdits()`.
- **Contrato de editabilidad**: `Widget::editContract()` (`src/Widgets/Widget.php`).
- **Diseño aplicado**: el hero ya genera CSS scoped desde config (`WwiHeroWidget::designCss()`).

### Brechas detectadas
1. Los campos no tienen **agrupación** (contenido vs diseño vs avanzado) → todo mezclado y campos técnicos a la vista.
2. Los `repeater` caen a **JSON crudo** (`type: 'code'` o default) → no amigable.
3. No hay **autosave** ni estado de guardado; el guardado manual **navega fuera**.
4. No hay **navegación entre secciones** ni breadcrumb.
5. Sin **validación tipada** ni errores inline.
6. Sin **responsive por breakpoint** ni overrides.
7. Sin **IA inline** (reescribir/traducir/rellenar) dentro del editor.
8. Sin **patrones reutilizables** (guardar sección como plantilla).

## 2. ROLES (simular simultáneamente)
- **UX Lead**: flujo de edición sin fricción, tabs, autosave, navegación.
- **Frontend Engineer (Vanilla JS SPA)**: `renderSchemaFields`, repeater, tabs, autosave.
- **Backend Engineer (PHP/PDO)**: update de sección, sanitización por tipo, validación.
- **Accessibility Engineer**: H1 único, alt obligatorio, contraste, focus-visible, reduced-motion.
- **Content Strategist**: presets por vertical, IA de relleno, tono de copy.
- **Security Engineer**: escape, PDO prepared, `@site_id`, límites de tamaño.
- **Code Monitor (obligatorio)**: §6.

## 3. LAS 30 INNOVACIONES

### A. Experiencia de edición (UX)
1. Editor de sección en **página** con breadcrumb (Página › Sección) y navegación **anterior/siguiente**.
2. **Edición inline real** (click en el sitio y escribir) con el panel como alternativa.
3. Pestañas **Contenido · Diseño · Avanzado** dentro del editor.
4. **Autosave** con debounce + estado (Guardado / Sin guardar / Guardando) y aviso al salir.
5. **Undo/redo** (Ctrl+Z / Ctrl+Y) apoyado en versiones.
6. **Split view** (sitio | panel) con device switcher.

### B. Tipos de campo y riqueza de contenido
7. **Rich text ligero** (negrita, cursiva, listas, enlace, alineación) saneado server-side.
8. **Jerarquía de encabezados** H1–H6 con validación de H1 único.
9. **Repeaters visuales** (icono, título, descripción, imagen, enlace) con drag&drop, duplicar, eliminar, restaurar default.
10. **Editor de imagen** con Media Library (`alt` obligatorio, focal point, reemplazar).
11. **Editor de enlace** con buscador de páginas internas + UTM + abrir en pestaña.
12. **Selector de iconos**.
13. **Color con paleta/tokens** y verificación de contraste.
14. **Campos condicionales** según otros valores.

### C. Diseño y estilos por sección
15. **Panel Diseño** (espaciado, ancho, fondo, alineación, bordes) con CSS variables scoped.
16. **Tipografía por elemento** (familia, tamaño, peso, interlineado) con escala.
17. **Responsive por breakpoint**: overrides desktop/tablet/mobile y mostrar/ocultar por dispositivo.

### D. Validación, accesibilidad y SEO
18. **Validación tipada en vivo** con errores inline y bloqueo de guardado inválido.
19. **Accesibilidad asistida**: `alt` obligatorio, enlaces con texto, contraste, H1 único, `aria`.
20. **SEO por sección**: meta título/descripción, OG, schema.org con checklist.
21. **Límites y avisos** (longitud, peso de imágenes, campos críticos).

### E. Productividad y flujo de trabajo
22. **Búsqueda global de contenido** ("dónde dice X") con salto al campo.
23. **"Ir al campo"**: click en el sitio → foco en el input.
24. **Duplicar sección** y **guardar como patrón** (reutilizables).
25. **Presets de contenido por vertical/plantilla**.
26. **Papelera + restaurar** e **historial por campo**.
27. **Bloqueo optimista** por sección/campo.

### F. IA (TIA)
28. **IA inline**: seleccionar texto → mejorar, acortar, cambiar tono, traducir (ES/EN), variantes A/B.
29. **"Generar sección desde prompt"** y **rellenar repeater**.
30. **Revisión IA de la sección**: sugiere mejoras de copy/SEO/a11y aplicables con un clic.

## 4. FASES
- **Fase A (este entregable)**: pestañas por grupo + repeater visual + navegación anterior/siguiente + autosave con estado + guard de salida.
- **Fase B (hecha)**: rich text con barra de formato (negrita/cursiva/subrayado/listas/enlace) a nivel de campo **y en subcampos de repeater** (FAQ), saneado cliente (`W.sanitizeRich`) y servidor (`LiveEditorService::sanitizeRichHtml`); campo **imagen** (URL + vista previa + Biblioteca de Media + alt opcional) y campo **enlace** (URL + selector de páginas internas); **validación tipada** en vivo (URL/color/número con mín-máx) que bloquea el guardado; **restaurar valor por defecto** por campo (`↺`).
- **Fase C (hecha)**: **campos condicionales** (`showIf` con `equals`/`not`/`in`/`notEmpty`; p. ej. `showcase_ms` solo si `show_showcase=1`), **visibilidad por dispositivo** a nivel de sección (`_hide_desktop/_hide_tablet/_hide_mobile` en la pestaña Avanzado; el builder ya la soporta por bloque con `visibility` JSON + clases `wwi-hide-*`), **accesibilidad/SEO**: `alt` obligatorio en campos de imagen y **aviso de H1 único** por página. Nota: en páginas convertidas a builder, el tema renderiza por filas (no por el loop de secciones), por lo que la visibilidad se gestiona por **bloque** en el builder.
- **Fase D (hecha)**: **IA inline** en campos de texto (`✨ IA` → mejorar/acortar/ampliar/tono formal/cercano/traducir EN-ES/variante A-B) vía `POST /api/v1/admin/brick/request` (BRICK enruta por policy; verificado con DeepSeek V4 Chat) y **"Rellenar con IA"** en repeaters (genera items por prompt → JSON → añade). **Revisión IA de la sección** (`🧭 Revisar IA` → 3-6 sugerencias de copy/SEO/a11y). **Historial y versiones** en panel inline (`🕘 Historial`: versiones con restaurar + cambios por campo `wwi_edit_events`; endpoints `GET /sections/{id}/history`, `GET /sections/{id}/versions`, `POST /versions/{id}/restore`). **Duplicar sección** (`⧉ Duplicar`, `POST /sections/{id}/duplicate`). **Patrones reutilizables** (`💾 Patrón` guarda la sección; botón **Patrones** en Secciones lista e **inserta** en la página, con eliminar; tablas/endpoints `wwi_section_patterns`, `GET /patterns`, `POST /sections/{id}/save-pattern`, `POST /patterns/{id}/insert`, `DELETE /patterns/{id}`).

## 5. CONTRATO TÉCNICO
- **Grupos de campo**: se añade `group` a cada campo del `configSchema()` (`content` | `design` | `advanced`; por defecto `content`; encabezados `type: 'heading'` agrupan visualmente).
- **Repeater visual**: `type: 'repeater'` con `fields[]`; el estado vive en un objeto JS y se serializa a un campo oculto `secf-<key>` (JSON) que el `collectSchemaFields()` lee. Nunca se pide JSON al usuario.
- **Autosave**: debounce de 1.5 s sobre el contenedor del editor; `PUT` silencioso; no navega. Botón Guardar manual siempre disponible.
- **Navegación**: anterior/siguiente sobre las secciones ordenadas de la página (`GET /api/v1/admin/pages/{pageId}/sections`).
- **Sin cambios de esquema DB**: todo vive en `sections.config` (JSON).

## 6. GOBERNANZA (PEDS — Monitor de Código, 6 puntos)
1. **Sintaxis**: `php -l` / `node --check` sin errores.
2. **Seguridad**: PDO prepared (`prepare → execute → fetch`), escape de salida, sin secretos, `@site_id` en toda query.
3. **Buenas prácticas**: sin duplicación ni código muerto, nombres consistentes.
4. **Idempotencia**: migraciones/DDL con `IF NOT EXISTS`; configs con defaults sin sobrescribir.
5. **Accesibilidad/UX**: estados loading/empty/error, focus-visible, reduced-motion, navegación por teclado.
6. **Verificación**: lint + prueba en vivo del flujo antes de cerrar.

## 7. CRITERIO DE ACEPTACIÓN
- Cero **JSON crudo** para contenido en el editor de secciones.
- Toda sección editable con **pestañas**, **guardado con estado** y **navegación** entre secciones.
- El **Monitor de Código (6 puntos)** pasa en cada cambio.
