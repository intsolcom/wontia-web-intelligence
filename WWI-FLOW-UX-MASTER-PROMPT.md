# WWI — FLOW UX/UI MASTER PROMPT (Slide Wizard)

> Spec de experiencia para el flujo guiado de creación de sitios de WWI. Objetivo: que el cliente se mantenga conectado al proceso, entienda cada paso sin fricción y actúe. Minimalista, moderno, humano. Aplicar junto a `WWI-FLOW-MASTER-PROMPT.md` y `WWI-UNIVERSAL-WEBSITE-MASTER-PROMPT.md`.

## ROLES QUE EJECUTAN ESTA SPEC (simular simultáneamente)
- **UX Designer**: flujo claro de 5 pasos, cero ambigüedad, progreso visible.
- **UI Designer**: jerarquía tipográfica, espaciado 8pt, color con propósito.
- **Conversation Designer**: copy de TIA cálido, breve, siempre con siguiente acción.
- **CRO Specialist**: cada pantalla tiene UN objetivo y UNA acción primaria.
- **Copywriter**: mensajes en español claro, verbos de acción, sin tecnicismos.
- **Motion Designer**: micro-animaciones ≤ 300ms, con `prefers-reduced-motion`.
- **Accessibility Specialist**: WCAG 2.2 AA — foco visible, labels, contraste, teclado.
- **Frontend Engineer**: CSS tokens, sin dependencias, performance first.
- **Product Designer**: reduce ansiedad (precio, tiempo, compromiso) en cada paso.
- **QA**: estados loading/empty/error/success definidos SIEMPRE.

## PRINCIPIOS UX
1. **Una pantalla = un objetivo.** Nunca dos decisiones en el mismo paso.
2. **Mensajes escaneables**: título corto + 1 línea de apoyo + acción.
3. **El texto se lee por cómo se presenta**: jerarquía clara, máximo 62 caracteres por línea, contraste AA.
4. **Cero fricción**: sugerencias clicables antes de exigir escribir.
5. **Progreso visible**: el usuario siempre sabe en qué paso está y cuántos faltan.
6. **Recompensa inmediata**: preview en < 60s con feedback animado.
7. **Salidas siempre disponibles**: "prefiero ver plantillas" nunca escondido.
8. **Confianza**: sin tarjeta, gratis, temporal, claro.

## 20 MEJORAS GRÁFICAS Y TÉCNICAS (CTA / RETENCIÓN)
1. **Chips de prompt sugerido** clicables ("Soy arquitecto…", "Tengo un restaurante…", "Soy abogado…") — eliminan la página en blanco.
2. **Avatar de TIA** en cada mensaje + indicador de escritura animado (typing dots).
3. **Labels de paso** junto a los dots ("Cuéntanos → Vista previa → Plan → Dominio").
4. **Barra de confianza** bajo el input: "Vista previa gratis · Sin tarjeta · Listo en minutos".
5. **Botón primario con micro-pulso** (respeta reduced-motion) para dirigir la mirada.
6. **Mensaje de TIA con siguiente acción explícita** ("Escribe o toca un ejemplo 👇").
7. **Transición de slides con easing suave** (cubic-bezier, 380ms) y sin saltos.
8. **Estado "generando" con pasos visibles** (analizando → diseñando → contenido) en lugar de spinner solo.
9. **Preview en modal con device switcher** (Desktop/Tablet/Mobile) — confianza de que es responsive.
10. **Banner de temporalidad** en el preview ("expira en 60 min") — reduce ansiedad y crea urgencia sana.
11. **Botones de decisión claros**: "Ver preview" (primario), "Editar prompt" / "Descartar" (secundarios).
12. **Contador de intentos amable**: "Te quedan X vistas previas hoy" (nunca castigar, siempre ofrecer alternativa).
13. **Fallback al catálogo sin callejón sin salida**: al agotar intentos, transición directa a plantillas.
14. **Plantillas con preview rico inmediato** al seleccionar (sin gastar intentos).
15. **Planes en tarjetas comparables** con precio mono, beneficio clave y "Elegir este plan" repetido.
16. **Venta cruzada no invasiva**: modal único de Web Master con "No, gracias" igual de visible.
17. **Chat de dominio con sugerencias en chips** + estado RDAP en color (verde libre / rojo tomado).
18. **Confirmación final con resumen** (plan + dominio + total) antes de crear el pedido.
19. **Accesibilidad completa**: focus-visible, aria-labels en mic/chips, contraste AA, teclado (Enter/Espacio).
20. **Analytics de funnel por paso** (evento `wwi_flow_step`) listo para medir abandono y optimizar.

## APLICACIÓN TÉCNICA (PEDS)
- Todo en el tema `wwi` (CSS tokens + JS del wizard). Sin dependencias nuevas.
- Reutilizar: `/api/v1/public/previews*`, `/api/v1/public/plans`, `/templates`, `/domain/check`, `/orders`.
- Estados: loading (skeleton/dots), empty (mensaje + acción), error (reintentar), success (check + siguiente paso).
- Límite de intentos: 2/día anónimos; ilimitado para builders (ya implementado).
