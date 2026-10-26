# CONSTITUCIÓN DE ADAPTABILIDAD — WWI / PEDS

**Versión:** 1.0.0 · **Vigente desde:** 2026-10-26 · **Próxima revisión:** 2027-01-26
**Ámbito:** TODO el sistema Wontia (WWI), sus bricks, widgets, módulos, paneles, menús, temas, landings, apps web, PWA, WebViews y experiencias de TV. Aplica a SONIKA y a cualquier sitio/tenant.
**Estado:** NORMATIVA. Rango superior a cualquier prompt puntual. Se aplica aunque el usuario no la mencione.

> Lenguaje normativo: **DEBE** = obligatorio · **DEBERÍA** = recomendado fuerte · **PUEDE** = opcional.
> Toda excepción DEBE justificarse en el PR/entrega y quedar registrada en el changelog.

---

## 0. Preámbulo

### 0.1 Objetivo
Garantizar que **cada pantalla se vea y funcione correctamente en cualquier dispositivo** (teléfono, tablet, laptop, desktop, ultrawide, TV, foldable, WebView, PWA), con una experiencia **extremadamente simple, atractiva y adictiva**, sin degradar rendimiento, accesibilidad, SEO ni la línea gráfica establecida.

### 0.2 Investigación y fuentes oficiales (base de esta constitución)
Esta constitución se fundamenta en estándares y guías oficiales, revisados periódicamente:
- **W3C — WCAG 2.2** (ISO/IEC 40500), WAI-ARIA, ATAG/UAAG. https://www.w3.org/WAI/standards-guidelines/wcag/
- **web.dev / Chrome Team** — Responsive Web Design Basics, Core Web Vitals, Performance, Learn Design. https://web.dev/articles/responsive-web-design-basics
- **MDN Web Docs** — viewport, media queries (`hover`, `pointer`, `any-pointer`, `orientation`, `scripting`), `clamp()`, container queries, `dvh/svh`, `env(safe-area-inset-*)`.
- **Android — Adaptive layouts / Material 3** — window size classes, edge-to-edge, insets, dynamic color, density.
- **Apple — Human Interface Guidelines** — Layout, Safe Areas, Dynamic Type, touch targets (44×44 pt), Dark Mode.
- **Google TV / Android TV (10-foot UI) & Apple tvOS** — overscan, focus/D-pad navigation, tipografía a distancia, sin hover.
- **ISO 9241-11 / 9241-210** — usabilidad y diseño centrado en el usuario.
- **Core Web Vitals** — LCP, INP, CLS.

La investigación DEBE repetirse **al menos cada 3 meses** (ver §17).

### 0.3 Prompt canónico (versión corregida)
> «En la versión móvil, al pulsar el icono de **Mi cuenta** o del **carrito de compras**, el menú desplegable se abre hacia la izquierda y queda oculto fuera de la pantalla vertical.
>
> La experiencia de usuario en móvil y en las distintas pantallas es fundamental: debemos crear una **constitución** que se aplique a todo lo que hagamos —bricks, módulos, paneles, menús y, en general, a todo lo relacionado con el sistema—. Dicha constitución debe quedar incluida tanto en el sistema WWI como en el PEDS para futuras implementaciones.
>
> Antes de redactar la constitución debemos:
> a. Investigar en fuentes oficiales y confiables cómo debe desarrollarse un sitio web, una landing page, una app web y una app Android/iOS, para que todo quede en su lugar sin importar el dispositivo donde se visualice.
> b. Buscar siempre la forma de mantener la constitución actualizada con las últimas tendencias y buenas prácticas.
> c. Aplicar esta constitución siempre, aunque no se mencione en el prompt.
> d. Tener en cuenta esta constitución en cada instrucción o prompt, para obtener mejores resultados.
> e. Mejorar y potenciar los resultados: no dañar código ni desmejorar lo existente.
> f. Innovar y mejorar siempre que sea posible; la constitución permitirá cambios automáticos, anunciados o notificados, y únicamente para mejoras.
> g. Elaborar un listado completo, sin excepción ni omisión, de todas las buenas prácticas de adaptabilidad de un sitio web de PC a móvil y a otras pantallas, incluidas las TV.
> h. Antes de aplicar la constitución al código del sitio web o de la app web, inspeccionar qué está implementado y qué no, para no duplicar código que ya funciona bien.
> i. Mantener en todo cambio la línea gráfica, la estructura y el diseño establecidos; nunca cambiarlos de forma arbitraria.»

---

## 1. Principios rectores (7)
1. **Contenido primero, dispositivo después.** El layout lo decide el contenido, nunca una marca o un modelo de teléfono.
2. **Mobile-first y progresivo.** Construir desde 320 px hacia arriba; la mejora es incremental.
3. **Una sola verdad de diseño (tokens).** Colores, espaciados, radios, tipografía y sombras viven en variables.
4. **Sin desbordes ni recortes.** Nada DEBE salir del viewport ni quedar inaccesible en ninguna orientación.
5. **Accesible por defecto.** Si no cumple WCAG 2.2 AA, no está terminado.
6. **Rápido es UX.** Presupuesto de rendimiento obligatorio (§10).
7. **Consistencia e identidad.** Toda mejora respeta la línea gráfica y la estructura existentes (§18).

---

## 2. Tokens y breakpoints (DEBE)
- Breakpoints por **contenido**, no por dispositivo. Base recomendada:
  - `xs` 320–479 · `sm` 480–599 · `md` 600–767 · `lg` 768–1023 · `xl` 1024–1279 · `2xl` 1280–1535 · `3xl` ≥1536 · `uw` ≥1920 · `tv` 1920/3840 (10-foot).
- Preferir **mobile-first** (`min-width`) y **container queries** (`@container`) en componentes reutilizables (bricks/widgets).
- Usar `clamp()` para tipografía y espaciado fluido; evitar saltos bruscos.
- Todos los valores en **rem/em** (excepto bordes de 1px y radios pequeños). `html{font-size:100%}`.
- Variables CSS con prefijo del tema (`--sonika-*`, `--wwi-*`) y fallback.
- **Prohibido** `width` fijos mayores a `100vw`, `100vh` en móvil (usar `100dvh/svh`), `!important` indiscriminado y `position:fixed` sin `env(safe-area-inset-*)`.

---

## 3. Layout y composición
- **DEBE** existir `meta viewport` con `width=device-width, initial-scale=1, viewport-fit=cover` (nunca `user-scalable=no`).
- Usar **Grid/Flex**; evitar floats y posicionamiento absoluto para estructura.
- Ancho máximo de lectura: **45–75 caracteres** por línea.
- Contenedores: `max-width` + `margin-inline:auto` + padding lateral fluido (`clamp(14px, 4vw, 24px)`).
- **No ocultar contenido** por tamaño de pantalla salvo que sea redundante; si se oculta, debe existir alternativa accesible.
- Respetar **safe areas** (`env(safe-area-inset-*)`) en headers/footers/barras fijas y **bottom sheets**.
- Evitar scroll horizontal accidental: `img,video,iframe,table{max-width:100%}` y auditar `overflow-x`.
- Alturas: usar `min-height` con `dvh` para heroes; `svh` para barras; nunca `100vh` puro.
- **Foldables / split-screen**: probar 320 px de ancho y cambios de ancho en caliente; usar container queries.

---

## 4. Tipografía y legibilidad
- Tamaño base **16 px**; inputs/selects/textareas **≥16 px** en móvil (evita el zoom automático de iOS).
- Escala fluida con `clamp()`; `line-height` 1.4–1.7 en párrafos.
- No usar texto en imágenes para contenido esencial.
- Soporte de **Dynamic Type / escala de texto del sistema** (usar rem; probar 200%).
- Contraste mínimo **4.5:1** (texto normal) y **3:1** (texto grande/iconos). No depender solo del color.

---

## 5. Interacción, entrada y ergonomía
- Área táctil mínima **44×44 px** (WCAG 2.2 pide ≥24 px; usamos 44 como estándar móvil).
- Separación mínima entre objetivos: 8 px.
- Adaptar por capacidad: `@media (hover:hover) and (pointer:fine)` para hover real; `(pointer:coarse)` para touch; no depender de hover para funciones esenciales.
- **Teclado**: orden lógico de tabulación, `:focus-visible` siempre visible, sin trampas de foco, `Esc` cierra overlays.
- **TV / D-pad**: foco navegable por flechas, sin hover, con anillo de foco de 4–8 px y overscan del 5 %.
- Feedback inmediato: `:active` (escala 0.97), estados de carga, sin dobles envíos.
- Gestos: swipe solo como **atajo**, nunca como única vía; documentar alternativa visible.

---

## 6. Componentes (reglas específicas)
### 6.1 Menús y dropdowns
- **DEBE** quedar completamente dentro del viewport en cualquier ancho/orientación.
- En `(pointer:coarse)` o ancho ≤900 px: abrir como **bottom sheet** o panel full-width, no como dropdown anclado al icono.
- Cerrar al tocar fuera, con `Esc` y con botón visible. Foco atrapado mientras esté abierto (si es modal).
- Nunca depender de `:hover` en móvil.

### 6.2 Modales y drawers
- Full-screen en móvil; `max-height:90dvh`; scroll interno; `aria-modal="true"`; retorno de foco al disparador.
- Evitar scroll del body detrás; usar `overscroll-behavior:contain`.
- Franja inferior con acciones fijas y `padding-bottom: env(safe-area-inset-bottom)`.

### 6.3 Carruseles/sliders
- Swipe + flechas + **dots**; pausa al interactuar; sin autoplay agresivo (<5 s); `prefers-reduced-motion` desactiva animaciones.
- Miniaturas con `scroll-snap`; estados activos visibles; navegación por teclado.

### 6.4 Tablas
- En móvil: scroll horizontal contenido (nunca desbordar la página) o tarjetas apiladas.
- Encabezados fijos opcionales; `scope` correcto; no truncar datos críticos.

### 6.5 Formularios
- `label` visible (no solo placeholder), errores junto al campo, `aria-describedby`, autocompletado (`autocomplete`), teclados adecuados (`inputmode`, `type`), autoguardado.
- Botones de envío ≥44 px, estado `disabled` + spinner, mensajes de éxito/error accesibles (`aria-live`).
- Nunca usar placeholder como única etiqueta.

### 6.6 Cabeceras y navegación
- Header adaptable; iconos agrupados con el logo en móvil; acciones esenciales siempre visibles.
- Sticky con `backdrop-filter` y altura estable; `scroll-margin-top` en anclas.
- Menú principal tipo drawer en móvil, con búsqueda en segunda fila si aplica.

### 6.7 Notificaciones y feedback
- Toasts no bloqueantes, `aria-live="polite"`, cierre automático ≥5 s, sin tapar acciones.
- Skeletons en lugar de spinners para contenido; evitar CLS.

---

## 7. Imágenes, video y medios
- `srcset` + `sizes` + formatos modernos (AVIF/WebP), `loading="lazy"` salvo hero (usar `fetchpriority="high"`).
- **Siempre** `width`/`height` o `aspect-ratio` para reservar espacio (evita CLS).
- `decoding="async"`; `object-fit` explícito.
- Video: `poster`, `playsinline`, `muted` solo si es decorativo; controles accesibles; lazy de iframes (YouTube: cargar al clic).
- Iconos: SVG inline o sprite; `aria-hidden` en decorativos, `aria-label` en funcionales.

---

## 8. Adaptabilidad de contenido
- No truncar información esencial; si se resume, ofrecer "ver más" accesible.
- Traducciones y RTL: usar propiedades lógicas (`margin-inline`, `padding-inline`, `inset-inline`), `dir="auto"`.
- Fechas/números/moneda con `Intl`; zona horaria explícita.
- Modo oscuro: `prefers-color-scheme` + tokens; contraste validado en ambos temas.
- `prefers-contrast`, `forced-colors`, `prefers-reduced-data`, `prefers-reduced-motion` DEBEN respetarse.

---

## 9. Accesibilidad (WCAG 2.2 AA — obligatorio)
- **4 principios**: Perceivable, Operable, Understandable, Robust.
- Criterios clave adicionales de 2.2: **Focus Not Obscured**, **Target Size (24 px mín.)**, **Dragging Movements** (alternativa), **Consistent Help**, **Redundant Entry**, **Accessible Authentication**.
- HTML semántico, landmarks (`header/nav/main/footer`), un solo `h1` por página, jerarquía de encabezados.
- `alt` significativo en imágenes de contenido; vacío en decorativas.
- Contraste 4.5:1 / 3:1; texto escalable 200 % sin pérdida (reflow a 320 px / 400 % zoom).
- Estados y errores anunciados; formularios accesibles; sin trampas de foco.
- Verificación con lector de pantalla (NVDA/VoiceOver) y navegación solo teclado.

---

## 10. Rendimiento (presupuesto obligatorio)
- **LCP ≤ 2.5 s**, **INP ≤ 200 ms**, **CLS ≤ 0.1** (p75 móvil).
- JS inicial ≤ 200 KB gzip; CSS crítico inline; diferir el resto.
- Fuentes: `font-display:swap`, subset, preconnect, máximo 2 familias.
- Imágenes: dimensiones explícitas, lazy, formatos modernos, tamaños acordes al DPR.
- Evitar librerías pesadas para interacciones simples; vanilla JS cuando sea viable.
- Medir con Lighthouse/PageSpeed y en dispositivos de gama media reales.

---

## 11. Web App / PWA / WebView
- `manifest.webmanifest`: `name`, `short_name`, `start_url`, `display:standalone`, `theme_color`, `background_color`, iconos 192/512/maskable.
- `viewport-fit=cover` + safe areas; `theme-color` claro/oscuro.
- iOS: `apple-mobile-web-app-capable`, `apple-touch-icon`, `-webkit-fill-available` para alturas; evitar `100vh`.
- Android/WebView: edge-to-edge, insets, `userAgent`/`env` cuando haga falta; back físico cierra overlays.
- Offline: service worker con estrategia cache-first para estáticos y network-first para datos.
- Instalación discreta (no intrusiva), con dismiss persistente.

---

## 12. TV y pantallas grandes (10-foot UI)
- Resoluciones 1920×1080 / 3840×2160 con **overscan seguro del 5 %** (márgenes ≥48 px).
- Navegación **solo D-pad/control remoto**: foco visible, orden espacial coherente, sin hover.
- Tipografía ≥24 px para texto base; títulos ≥32 px; alto contraste.
- Sin scroll horizontal; scroll vertical con indicadores; evitar inputs de texto cuando sea posible.
- Componentes: tarjetas grandes, una acción primaria por vista, estados de foco inequívocos.
- Probar con teclado y emuladores de TV; latencia de foco < 100 ms.

---

## 13. SEO e indexabilidad
- Render en servidor del contenido principal (no depender de JS para texto clave).
- HTML semántico + encabezados correctos + `alt` + enlaces descriptivos.
- `title`, `meta description`, canonical, Open Graph/Twitter Cards, `hreflang` si aplica.
- Datos estructurados **JSON-LD** (Product, Person, Organization, BreadcrumbList, VideoObject…).
- URLs limpias y estables; sitemap; `robots.txt` correcto; evitar `noindex` accidental.
- Contenido adaptable **no oculto** a los bots; evitar texto solo en imágenes/canvas.

---

## 14. Inspección previa (obligatoria antes de escribir)
Antes de aplicar cualquier regla:
1. **Inventariar** lo existente (grep/lectura de CSS, widgets, plantillas, tokens, media queries).
2. **Detectar duplicados** y conflictos de especificidad/orden.
3. **Reutilizar** tokens, clases y patrones ya establecidos.
4. **No reescribir** lo que funciona; hacer cambios **aditivos** y de mínimo impacto.
5. Documentar qué se encontró, qué se cambió y qué se descartó por ya existir.

---

## 15. Preservación de la identidad visual
- No cambiar paleta, tipografías, radios, sombras ni estructura sin aprobación explícita.
- Las mejoras DEBEN ser **invisibles o coherentes** con el diseño vigente.
- Cualquier cambio visual perceptible DEBE anunciarse y justificarse (antes/después).
- Mantener la jerarquía y el tono de marca por sitio/tema.

---

## 16. Cambios automáticos (mejoras permitidas)
El agente PUEDE aplicar automáticamente mejoras que cumplan **todas**:
1. Sean aditivas y reversibles.
2. No alteren la identidad visual ni la estructura.
3. Mejoren accesibilidad, rendimiento, adaptabilidad o robustez.
4. No introduzcan dependencias nuevas sin justificar.
5. Queden registradas en el changelog y **notificadas en la respuesta** (qué, dónde, por qué).
6. No rompan compatibilidad (feature flag si hay riesgo).

**Nunca** automático: cambios de copy/marca, rediseños, eliminación de funcionalidad, cambios de breakpoints globales.

---

## 17. Gobernanza y actualización
- **Revisión trimestral** de fuentes (WCAG, MDN, web.dev, Material, Apple HIG, TV).
- **Registro de cambios** en este documento (tabla al final).
- **RFC** para cambios estructurales; aprobación del owner.
- **Auditoría automática** en CI (ver §19) y reporte.
- Si una nueva práctica contradice esta constitución, prevalece la más reciente **aprobada** y se documenta.

---

## 18. Matriz de pruebas mínima
| Dispositivo | Anchos | Casos |
|---|---|---|
| Móvil pequeño | 320, 360 | portrait/landscape, zoom 200 %, teclado |
| Móvil estándar | 390, 414 | iOS/Android, input zoom, safe areas |
| Tablet | 768, 1024 | split-screen, rotación |
| Laptop/Desktop | 1280, 1440, 1920 | hover, teclado, zoom 400 % |
| Ultrawide | 2560, 3440 | max-width, líneas de texto |
| TV | 1920/4K | D-pad, overscan, foco |
| PWA/WebView | standalone | instalación, offline, back |

Herramientas: Chrome DevTools (device mode), Lighthouse, axe DevTools, Playwright, lectores de pantalla, dispositivos reales.

---

## 19. Checklist “Definition of Done” (adaptabilidad)
- [ ] Sin scroll horizontal en 320–1920 px y TV.
- [ ] Menús/modales 100 % visibles en móvil (bottom sheet si aplica).
- [ ] Objetivos táctiles ≥44 px y foco visible.
- [ ] Inputs ≥16 px en móvil.
- [ ] Imágenes con dimensiones/`aspect-ratio`; sin CLS.
- [ ] CWV dentro de presupuesto.
- [ ] WCAG 2.2 AA verificado (contraste, teclado, lector, zoom 200 %).
- [ ] `prefers-reduced-motion` y `prefers-color-scheme` respetados.
- [ ] Safe areas en iOS (notch/home indicator).
- [ ] SEO: SSR, meta, canonical, JSON-LD.
- [ ] PWA/TV: manifest, foco D-pad, overscan.
- [ ] Inspección previa realizada; sin duplicar código.
- [ ] Identidad visual intacta; cambios notificados.

---

## 20. Anti-patrones (prohibido)
- `position:absolute; right:0` para dropdowns en móvil (se salen de pantalla).
- `100vh` en móvil; `overflow:hidden` global; `user-scalable=no`.
- Ocultar contenido esencial por breakpoint; hover como única interacción.
- Texto en imágenes; placeholder como label; botones <44 px.
- `!important` masivo; selectores por ID para layout; estilos inline extensos.
- Auto-play con sonido; animaciones sin `reduced-motion`.
- Duplicar CSS existente por desconocer el sistema.

---

## 21. Innovaciones (roadmap de esta característica)
1. **Adaptive Lint** propio (CLI en CI) que detecte: overflow horizontal, targets <44 px, inputs <16 px, `100vh`, dropdowns anclados, contenido oculto, faltantes de `alt`.
2. **Snapshot visual multi-viewport** (Playwright) con umbral de diff y aprobación.
3. **Tokens vivos** exportables a CSS/JSON/Figma (una sola fuente de verdad).
4. **Modo “10-foot” automático** (clase `tv-mode` por `@media (min-width:1920px) and (pointer:coarse)` + detección UA TV).
5. **Auditoría de accesibilidad en cada build** (axe-core) con presupuesto que bloquea el merge.
6. **Presupuesto de rendimiento por página** (LCP/INP/CLS) con alertas.
7. **Detección de regresiones de adaptabilidad** en PR (comentario automático).
8. **Design-review bot**: verifica que el cambio no altere la identidad visual (diff de tokens).
9. **Modo degradado por red** (`prefers-reduced-data`, `saveData`) con imágenes ligeras.
10. **Telemetría de UX** (RUM) para detectar fricción real por dispositivo (con consentimiento).
11. **Auto-fix seguro** de patrones conocidos (p. ej., dropdown → bottom sheet) con PR revisable.
12. **Documentación viva**: snippets copiables y ejemplos por componente.

---

## 22. Registro de cambios
| Versión | Fecha | Cambio |
|---|---|---|
| 1.0.0 | 2026-10-26 | Creación: principios, breakpoints, componentes, a11y, perf, TV, PWA, gobernanza, checklist. |

---

## Anexo A — Snippets base
```css
/* Tokens mínimos */
:root{
  --bp-sm:480px; --bp-md:768px; --bp-lg:1024px; --bp-xl:1280px;
  --space-1:clamp(.5rem,.4rem + .5vw,.75rem);
  --radius:12px; --tap:44px;
}
/* Scroll horizontal controlado */
html,body{max-width:100%;overflow-x:clip}
img,video,iframe,svg{max-width:100%}
/* Inputs iOS */
@media (max-width:760px){input,select,textarea{font-size:16px}}
/* Hover real */
@media (hover:hover) and (pointer:fine){ .card:hover{transform:translateY(-2px)} }
/* Bottom sheet móvil */
@media (max-width:900px){
  .dropdown{position:fixed;inset-inline:12px;bottom:12px;top:auto;max-height:70dvh;overflow:auto;
    padding-bottom:calc(12px + env(safe-area-inset-bottom))}
}
/* TV */
@media (min-width:1920px) and (pointer:coarse){
  :root{font-size:20px}
  :focus-visible{outline:4px solid #fff;outline-offset:4px}
}
```

---

**FIN DE LA CONSTITUCIÓN.**

---

## 23. Herramientas implementadas (v1.1.0)

- **`tools/adaptive-lint.php`** — linter sin dependencias. Detecta: zoom bloqueado, `alt` faltante, `100vh/100vw`, dropdowns anclados, targets <44 px, inputs <16 px, scripts bloqueantes, iframes sin lazy, `target="_blank"` sin `rel`, `outline:none`, overflow-x, exceso de `!important`.
  - Uso: `php tools/adaptive-lint.php --paths=templates,src/Widgets --fail-on=error`
- **`tools/visual-snapshot.mjs`** — Playwright + axe: capturas en 320/390/768/1024/1440/1920, detección de scroll horizontal y violaciones axe serious/critical.
- **`.github/workflows/adaptive-audit.yml`** — CI: lint + snapshots/axe + Lighthouse (requiere secret `PREVIEW_URL`).
- **`lighthouserc.json`** — presupuesto CWV (LCP ≤2500 ms, CLS ≤0.1, TBT ≤200 ms) y accesibilidad ≥0.9.
- **Tokens**: `install/adaptive-tokens.json` + variables `--wwi-*` en el CSS del tema.
- **Modo TV**: detección por UA o `?tv=1`, foco D-pad con flechas, tipografía 20 px, overscan y targets ≥52 px.

### Actualización del registro de cambios
| Versión | Fecha | Cambio |
|---|---|---|
| 1.1.0 | 2026-10-26 | Herramientas (lint, snapshots, CI, Lighthouse), tokens JSON, modo TV, corrección 100vw y bottom sheets en widgets. |
