# WWI — BUILDER TEXT FORMATTING MASTER PROMPT

> **Versión:** 1.0 · **Fecha:** septiembre 2026 · **Modo:** PEDS (Inspeccionar → Proponer → Entregar → Verificar)
> **Requerimiento:** el editor de texto debe permitir **formateo rico**: tamaño, color, color de fondo, estilo (negrita/cursiva/subrayado/tachado), encabezados, listas, alineación, enlaces y limpieza de formato — sin pedir HTML al usuario.

## 1. INSPECCIÓN (estado actual)
- **Bloques `text`** (builder): barra `wb-rte-tools` + editor `contenteditable` (`#wb-rte`).
- **Bricks**: el panel renderiza el `configSchema` del widget (`renderBrickFields`). Los campos `richtext` ahora usan barra + contenteditable (antes textarea).
- **Saneado**: `BuilderService::sanitizeProps()`/`sanitizeRich()` (quita `<script>/<style>` y su contenido, `on*`, `javascript:`, `position`, y limita `style` a `color|background-color|font-size|text-align|font-weight|font-style|text-decoration`).

## 2. ENTREGADO (núcleo)
- Barra: **negrita, cursiva, subrayado, tachado**, **tamaño** (Pequeño/Normal/Grande/Muy grande), **H1/H2/H3/P**, **cita**, **listas** (viñetas/numerada), **alineación** (izq/centro/der), **color de texto**, **color de fondo**, **enlace**, **limpiar formato**.
- `document.execCommand` + `styleWithCSS` (estilos inline controlados) y **saneado server-side** (verificado E2E: se aplica color y se persiste sin `<script>`/`onerror`/`position`).

## 3.b IMPLEMENTADAS (sep 2026)
- **#2 Sangría** (`indent`/`outdent`), **#3 Paleta de marca** (swatches desde `settings.builder_tokens`), **#4 Pegar sin formato** (paste → texto plano), **#8 Snippets** (guardar selección en `settings.builder_snippets` + insertar desde la barra), **#9 IA sobre la selección** (TIA reescribe solo lo seleccionado vía BRICK). Verificado E2E.
- **#6 Undo local**: ya funciona (Ctrl+Z dentro del `contenteditable` usa el undo nativo, no lo intercepta el undo global del builder).

## 3. LAS 10 MEJORAS ADICIONALES
1. **Escala tipográfica del design system**: en vez de tamaños sueltos, tokens `display | h1…h6 | body | caption` aplicables a la selección (consistencia de marca).
2. **Listas con sangría**: aumentar/disminuir sangría (`indent`/`outdent`) y listas anidadas.
3. **Paleta de marca como swatches**: mostrar los tokens de color del sitio junto al selector, con historial de colores recientes.
4. **Pegar sin formato** por defecto (limpiar al pegar) + **pegar con formato** opcional (Ctrl+Shift+V).
5. **Modo "usar estilo del tema"**: opción para no fijar color/tamaño y heredar del tema (evita romper el design system).
6. **Undo/redo dentro del editor de texto** (pila local del contenteditable) además del undo global del builder.
7. **Accesibilidad en vivo**: aviso de **contraste** texto/fondo bajo, **jerarquía de encabezados** (no saltar H1→H3) y "no usar solo color" para significados.
8. **Guardar selección como snippet/patrón** reutilizable (texto con formato reutilizable en cualquier bloque).
9. **IA sobre la selección**: aplicar «mejorar / reescribir / traducir» **solo al texto seleccionado** (no a todo el bloque).
10. **Formato por dispositivo (responsive)**: definir tamaño/alineación distintos por breakpoint (escritorio/tablet/móvil) en el mismo editor.

## 4. GOBERNANZA (PEDS — Monitor de Código, 6 puntos)
1. **Sintaxis**: `php -l` / `node --check`.
2. **Seguridad**: saneado server-side obligatorio de HTML de usuario (whitelist de etiquetas + `style` limitado + bloqueo `on*`/`javascript:`); sin secretos.
3. **Buenas prácticas**: reutilizar `richToolbar`/`rtApply`/`sanitizeProps`; sin duplicación.
4. **Idempotencia**: N/A (contenido); historial por bloque ya disponible.
5. **Accesibilidad/UX**: botones con `title`, foco, `reduced-motion`, estados.
6. **Verificación**: prueba en vivo (aplicar formato → guardar → comprobar saneado persistido).

## 5. CRITERIO DE ACEPTACIÓN
- Se puede dar formato (tamaño/color/fondo/estilo/alineación/listas/enlaces) sin escribir HTML.
- Todo HTML guardado pasa el saneado server-side y conserva solo formato permitido.
- El Monitor de Código (6 puntos) pasa en cada cambio.
