# WWI — EDITABLE CONTENT MASTER PROMPT (Cobertura total de edición)

> Solución al problema detectado: hay elementos del sitio que NO son editables en el editor en vivo (planes, plantillas, pasos, beneficios) o que solo se editan como JSON crudo. Objetivo: **100% de los elementos visibles editables con UI adecuada**, cero JSON manual para contenido, y un sistema de administración de contenido el más robusto del planeta.

## 1. ANÁLISIS (evidencia en código)

### Caso 1 — Planes (`wwi-plans`): NO editable
- `WwiPlansWidget::render()` emite `<div data-wwi-plans>` vacío y el **JS del tema** (`wwiLoadPlans()`) inyecta las tarjetas desde `GET /api/v1/public/plans`.
- El schema solo expone `title`, `subtitle`, `note`. Los datos visibles (Web Starter, $299.000, features: domain 1y / hosting / ssl / email 3 / responsive) viven en el **Pricing Engine** (`wwi_plans`) — el editor en vivo no los conoce ni ofrece editarlos.
- El DOM se genera después del render: el editor no ve nodos que aún no existen.

### Caso 2 — Pasos (`wwi-steps`): editable pero como JSON crudo
- `steps` es `type: 'code'` → textarea con JSON (dificilísimo para un usuario normal).
- Igual en **Beneficios** (`items` JSON) y **FAQ** (`items` JSON).

### Caso 3 — Plantillas (`wwi-templates`): NO editable
- `WwiTemplatesWidget` emite un contenedor y `wwiLoadTemplates()` pinta 20 tarjetas (Restaurante Clasico, Bufete Profesional…) desde `GET /api/v1/public/templates`.
- Los nombres/categorías viven en `wwi_templates` (multi-tenant). El editor no los expone.

### Caso 4 — Contenido hardcodeado: NO editable
- Stats del hero ("24h", "0", "TIA"), marquee de 18 sectores, textos del checkout y del wizard están **hardcodeados en el render** del widget, no en config.

### Caso 5 — Cobertura parcial de `data-editable`
- Solo hero, CTA y FAQ tienen `data-editable`. El resto de widgets no declara nada → el mouse no los detecta.

## 2. CAUSAS RAÍZ
1. **Render diferido por JS**: los loaders (`wwiLoadPlans`, `wwiLoadTemplates`) crean DOM después de la carga; el editor no lo re-vincula ni conoce su origen de datos.
2. **Datos fuera de la sección**: planes/plantillas viven en tablas del ecosistema, no en `sections.config`; no hay editor de fuente.
3. **Schema sin tipos ricos**: no existe `repeater` (listas de objetos), `image`, `link`, `richtext`, `color`, `number`, `source`.
4. **Contenido hardcodeado**: textos fijos en los widgets en vez de config con defaults.
5. **Sin contrato de editabilidad**: ningún estándar obliga a un widget a declarar qué es editable y cómo.
6. **Sin auditoría**: nada detecta automáticamente elementos no editables.
7. **Sin validación de contenido**: JSON inválido o campos faltantes rompen el render o se guardan en silencio.

## 3. SOLUCIÓN (arquitectura)

### 3.1 Contrato de editabilidad obligatorio (`editContract`)
Cada widget declara, junto a `meta()` y `configSchema()`:
```php
public static function editContract(): array {
    return [
        'editable' => ['badge','title','subtitle','cta_primary'],      // claves del config mapeables al DOM
        'sources'  => ['plans' => ['api' => 'plans', 'editable' => ['name_es','price_cop','features']]],
        'repeaters'=> ['items' => ['fields' => ['icon','title','desc']]],
        'dynamic'  => true,                                             // el render inyecta nodos por JS
    ];
}
```
- `data-editable` se emite SIEMPRE desde el render para las claves declaradas.
- Los loaders JS (planes/plantillas) añaden `data-editable`/`data-source` a los nodos que generan.

### 3.2 Tipos de campo nuevos en `configSchema`
- `repeater`: lista de objetos con `fields[]` (title, desc, icon, image, link…) + añadir/eliminar/duplicar/reordenar.
- `image`: selector de la Media Library (con alt obligatorio).
- `link`: URL o página interna (buscador de páginas del sitio).
- `richtext`: texto con formato ligero (negrita, listas, enlaces).
- `color`, `number`, `list` (array simple), `source` (edición de datos externos).

### 3.3 Editor de repeticiones en la barra (nunca JSON crudo)
- Tarjetas por ítem con campos etiquetados, drag&drop para reordenar, botones añadir/duplicar/eliminar.
- El JSON queda como vista "Avanzado" colapsada para usuarios técnicos.
- Validación en vivo y "Restaurar default" por ítem/campo.

### 3.4 Editor de FUENTE para widgets data-driven
- Si el widget declara `sources.plans`, el sidebar muestra una sección "Contenido del motor de precios" con edición de **nombre, precio y features** por plan (vía API del Factory), con aviso claro: "Esto actualiza el Pricing Engine del ecosistema".
- Lo mismo para `sources.templates` (nombre/categoría de plantilla) y cualquier fuente futura.

### 3.5 Contenido hardcodeado → config con defaults
- Migrar stats del hero, sectores del marquee, textos del wizard/checkout a claves de config con `default` (los widgets siguen funcionando sin config).

### 3.6 Mutación observada (DOM dinámico)
- `MutationObserver` en el editor: al aparecer nodos nuevos (planes/plantillas), los decora con toolbar/`data-editable` y los vincula a su fuente.

### 3.7 Validación y fallback
- Validar tipos/estructura antes de guardar; si el config no trae un campo, el widget usa `default` (nunca rompe).
- Errores visibles en el campo, no en consola.

### 3.8 Auditoría automática de cobertura
- Botón "Auditoría de edición" (pestaña Calidad): recorre el DOM y lista nodos visibles sin mapeo editable → 0 pendientes como criterio de aceptación.
- Test en CI por widget: render + contrato + nodos `data-editable` esperados.

## 4. WIDGETS A CORREGIR (cobertura)
| Widget | Problema | Solución |
|---|---|---|
| wwi-plans | tarjetas por JS, datos en pricing | source editor (planes) + data-source en tarjetas + aviso |
| wwi-templates | tarjetas por JS | source editor (plantillas) o repetidor local con override |
| wwi-steps | JSON | repeater (title, desc) + data-editable |
| wwi-benefits | JSON | repeater (icon, title, desc) + data-editable |
| wwi-faq | JSON | repeater (q, a) + data-editable |
| wwi-hero | stats/sectores hardcodeados | config `stats` y `sectors` (repeaters) |
| wwi-checkout | textos fijos | config con defaults |
| wwi-footer | enlaces fijos | repeater de enlaces |
| wwi-cta | parcial | data-editable en botón/subtítulo (ya) + imagen opcional |
| resto de widgets core | sin contrato | `data-editable` + defaults |

## 5. LAS 30 INNOVACIONES
1. **Contrato de editabilidad obligatorio** con lint en build que falla si un widget no lo declara.
2. **Auditoría de cobertura en vivo** (0 nodos sin mapeo como criterio de aceptación).
3. **Repeaters nativos** con drag&drop, duplicar y eliminar (cero JSON para contenido).
4. **Editores de fuente** para datos del ecosistema (planes, plantillas) con aviso de alcance.
5. **Mutación observada**: el editor se re-vincula a nodos creados por JS.
6. **Escáner de hardcodes**: detecta strings visibles en widgets sin config y los reporta.
7. **Validación tipada** con errores inline y bloqueo de guardado inválido.
8. **Preview antes de guardar** (diff visual por campo).
9. **Richtext ligero** con barra mínima (negrita, listas, enlace).
10. **Editor de imagen** (media library, alt obligatorio, focal point futuro).
11. **Editor de enlaces** con buscador de páginas internas del sitio.
12. **Campos condicionales** (mostrar/ocultar según otros valores del schema).
13. **Restaurar default** por campo e ítem.
14. **Historial por campo** (quién cambió qué, cuándo) sobre las versiones ya existentes.
15. **Bloqueo optimista por campo** (evita pisar cambios de otro editor).
16. **Modo cliente sin JSON** (solo campos amigables).
17. **Presets de contenido por vertical** (copiar/pegar bloques de texto listos).
18. **IA de relleno**: "completa los 6 beneficios para un restaurante" (TIA/BRICK).
19. **Traducción asistida por campo** (ES/EN) con un clic.
20. **Búsqueda global de contenido** en el editor ("dónde dice X").
21. **Ir al campo**: clic en el sitio → foco directo en el input del sidebar.
22. **Validación SEO/a11y por campo** (alt obligatorio, H1 único, enlaces con texto).
23. **Skeleton en loaders** para que el editor espere DOM dinámico sin parpadeos.
24. **Contrato de fallback**: config incompleto → defaults, nunca render roto.
25. **Migraciones de schema por widget** (versionado de config).
26. **Tests de render por widget** (snapshot) en CI.
27. **Telemetría de edición**: campos más editados y campos ignorados (producto).
28. **Catálogo de campos autogenerado** desde el schema (documentación viva).
29. **PATCH granular por campo** para colaboración sin conflictos.
30. **Modo "solo lo visible"**: resalta en el sitio únicamente lo que es editable ahora mismo (onboarding).

## 6. FASES
- **Fase 1 (crítica)**: contrato + `data-editable` universal + repeater + migrar steps/benefits/faq + hero stats/sectores a config.
- **Fase 2**: source editors (planes, plantillas) + MutationObserver + auditoría de cobertura en Calidad.
- **Fase 3**: richtext, image/link editors, validación, restaurar default, modo cliente sin JSON.
- **Fase 4**: IA de relleno/traducción, búsqueda global, telemetría, tests CI.

## 7. GOBERNANZA (PEDS)
- Toda query con `@site_id`; validación de payload por tipo; escape de salida.
- Idempotencia en migraciones de config (defaults aplicados sin sobrescribir).
- Estados UX completos (loading/empty/error) y reduced-motion.
- Criterio de aceptación: **auditoría de edición = 0 elementos visibles sin editor** en todas las páginas del tenant.
