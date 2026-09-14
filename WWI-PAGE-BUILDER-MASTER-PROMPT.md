# WWI — PAGE BUILDER MASTER PROMPT (Pages · Sections · Constructor en vivo)

> Investigación de los 5 mejores sistemas de administración de páginas/secciones del mercado + arquitectura objetivo para WWI + 30 innovaciones para ser #1 en construcción de sitios web. Aplica a `#pages` (pestañas Páginas / Secciones) y al futuro constructor en vivo.

## 1. INVESTIGACIÓN — QUÉ HACEN LOS 5 MEJORES

### 1.1 WordPress — Gutenberg / Full Site Editing
- Modelo de **bloques discretos** (párrafo, heading, media, embed…), cada uno con edición y formato individual; el contenido se **serializa/parsea** (JSON en DB).
- UI de 3 zonas: **Inserter** (panel para insertar bloques), **Content canvas** (lienzo) y **Settings Panel** (ajustes del bloque seleccionado o del documento).
- **Block patterns** (combinaciones predefinidas) y **theme.json** (estilos globales por tokens).
- Extensible por APIs; ecosistema enorme de bloques propios.
- **Qué copiar**: inserter + canvas + inspector, bloques como unidad atómica, patrones reutilizables, tokens globales.

### 1.2 Webflow — Designer
- **CSS visual real** (flexbox, grid, filtros, propiedades), clases reutilizables y **global swatches/variables** (un cambio actualiza todo).
- **Symbols → Components**: elementos repetidos editables una vez, actualizados en todo el sitio.
- **CMS Collections** + **dynamic templates**: una plantilla genera cientos de páginas.
- **Edit mode**: el cliente edita textos e imágenes **directamente sobre el sitio en vivo**.
- **Interactions/animations** sin código; backups/versionado diarios; staging vs producción; código exportable limpio.
- **Qué copiar**: variables globales, componentes, edición inline en el sitio, versionado/backups, staging.

### 1.3 Elementor
- **Editor drag & drop** con precisión pixel-perfect, transforms, máscaras y motion effects.
- **Global styles** + Theme Builder; **responsive por breakpoint** (desktop/tablet/mobile con overrides).
- **Dynamic Content** y **Display Conditions** (personalización por visitante).
- **IA**: Site Planner (brief + sitemap + wireframes), AI writer, AI images, generación de código.
- **Colaboración**: notas y revisiones claras; biblioteca de plantillas/bloques guardados.
- **Qué copiar**: responsive por breakpoint, display conditions, IA de planificación, notas colaborativas.

### 1.4 Framer
- **Agentes de IA nativos al canvas**: generan y refinan **en su lugar**, cada cambio es visible, editable y reversible.
- **CMS agent** (organiza y actualiza el CMS conectado al canvas), **code agents** (efectos custom).
- Integración con IA externa (Claude Code, Cursor, terminal, PRs) para operar el sitio.
- **Branches** de colaboración, **localización** por locale, **A/B testing** y analytics nativos; foco extremo en performance (Core Web Vitals).
- **Qué copiar**: IA en el canvas con cambios editables, branches/borradores, A/B testing integrado, performance como feature.

### 1.5 Squarespace — Fluid Engine
- **Grilla libre (grid)**: arrastrar y redimensionar bloques con posición exacta; **layout móvil independiente** del desktop.
- Edición **inline** directa sobre la página; secciones como contenedores de bloques.
- **Qué copiar**: grilla libre con snap, layout móvil separado, edición inline.

## 2. SÍNTESIS — EL MEJOR SISTEMA POSIBLE (ARQUITECTURA WWI)
**Ecuación ganadora** = `Estructura (árbol) + Canvas en vivo + Inspector + Inserter + Drag&Drop + Edición inline + Responsive + Tokens + Componentes + Revisión/undo + IA + Colaboración + Performance/SEO`.

- **Modelo**: Page → Sections → (futuro) Elements dentro de cada section. `sort_order` ya persiste el orden.
- **Admin**: Pages con pestañas **Páginas | Secciones**; en Secciones: arrastrar para reordenar (✅ hecho), ▲▼ accesibles (✅), añadir desde el Brick Marketplace (✅), editar por schema (✅).
- **Siguiente capa**: edición en vivo click-to-edit sobre el sitio real + árbol de estructura + inspector lateral + inserter flotante.
- **Gobernanza**: cada cambio versionado (snapshot), publicable por sección, con IA asistida y validaciones de SEO/accesibilidad/performance en vivo.

## 3. LAS 30 INNOVACIONES (roadmap priorizado)
**Fase 1 — Admin actual (base)**
1. ✅ Pages hub con pestañas Páginas/Secciones (sin redundancia de menús).
2. ✅ Drag & drop de secciones con persistencia (`PUT /sections/reorder`) y feedback visual.
3. ✅ Botones ▲▼ accesibles (teclado/móvil) + guardado instantáneo con toast.
4. ✅ Selector de página dentro de Secciones (cero navegación redundante).
5. ✅ Inserter desde Brick Marketplace con previews y "Añadir a página".

**Fase 2 — Edición en vivo (el salto)**
6. **Click-to-edit**: overlay en el sitio real; clic en una sección abre su inspector sin salir del sitio.
7. **Árbol de estructura** (layers): jerarquía Page → Sections → Elements con drag & drop.
8. **Inspector lateral flotante**: schema-driven, edita el brick seleccionado en contexto.
9. **Inserter flotante**: botón "+" entre secciones en el sitio; abre el marketplace y suelta el brick donde lo necesitas.
10. **Drop zones inteligentes** con snapping y previsualización del hueco.
11. **Edición inline de textos**: doble clic en un heading párrafo y editar in situ.
12. **Panel de medios inline**: reemplazar imágenes arrastrando desde la biblioteca.
13. **Responsive por breakpoint**: overrides desktop/tablet/mobile con vista previa simultánea.
14. **Modo cliente**: solo texto/imágenes (estructura bloqueada), con aprobaciones.

**Fase 3 — Confianza y control**
15. **Undo/redo global** (Ctrl+Z/Y) con pila de acciones.
16. **Revisiones con diff visual** y rollback por sección o página.
17. **Snapshot automático** antes de operaciones masivas.
18. **Publicación selectiva**: borrador vs publicado por sección.
19. **Programación de publicación** por sección (fecha/hora).
20. **Colaboración**: presencia, cursores y bloqueo optimista por sección.
21. **Comentarios anclados** a secciones para feedback del cliente.

**Fase 4 — IA como copiloto**
22. **TIA en el canvas**: "hazlo más elegante", "agrega testimonios aquí" (ya existe el agente; conectar al editor).
23. **Variantes A/B de copy** generadas y testeables por sección.
24. **Auto-layout por conversión**: sugiere el orden óptimo de secciones según heatmaps.
25. **Reescritura inline** (seleccionar texto → mejorar/reescribir/traducir).

**Fase 5 — Calidad automática (superioridad medible)**
26. **SEO por sección**: headings, schema, alt, enlaces internos con score en vivo.
27. **Accesibilidad en vivo**: contraste, roles, foco, alt; semáforo por sección.
28. **Performance por sección**: peso, imágenes, LCP estimado; sugerencias automáticas.
29. **A/B testing visual integrado** con métricas de conversión por variante.
30. **Plantillas de página + export/import JSON** entre sitios del ecosistema.
- Bonus: command palette para insertar/mover secciones por nombre, atajos completos y atajos de teclado documentados.

## 4. GOBERNANZA (PEDS)
- Toda query con `@site_id`; reordenamiento validado por `page_id IN (SELECT id FROM pages WHERE site_id=@site_id)` (ya aplicado).
- Endpoints con validación de payload (`items` array con `id`/`sort_order`), escape de salida en frontend.
- Estados UX: loading/empty/error/success en cada vista; reduced-motion respetado.
- Prueba en vivo obligatoria de cada endpoint nuevo.
