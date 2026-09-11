# WWI — DESIGN SYSTEM MASTER PROMPT (Frontend + Backend)

> Línea gráfica oficial de Wontia Web Intelligence. Aplica al **frontend** (landing WWI, sitios de clientes, temas) y al **backend** (admin SPA, Factory, paneles). Minimalista · Futurista · Flat · IA · Vivo. Movimiento y luz con propósito, nunca saturación.

---

## 1. ROLES CONVOCADOS (simular simultáneamente)
- **Creative Director**: visión y coherencia global de la marca.
- **Art Director**: dirección visual, composición, luz y color.
- **UX Designer**: flujos, jerarquía, reducción de fricción.
- **UI Designer**: componentes, tokens, consistencia.
- **Motion Designer**: coreografía de animaciones, easing, timing.
- **3D / Creative Developer**: WebGL/WebGPU, shaders, escenas generativas.
- **GPU/Graphics Engineer**: rendimiento de efectos, instancing, LOD.
- **Frontend Architect**: estructura CSS/JS sin frameworks pesados.
- **CSS Architect**: tokens, capas (@layer), container queries, Houdini.
- **Accessibility Specialist**: WCAG 2.2 AA, reduced-motion, foco, contraste.
- **Performance Engineer**: budgets, Core Web Vitals, battery-aware.
- **Behavioral/Cognitive Designer**: dopamina ética — recompensa, progreso, control.
- **CRO Specialist**: conversión sin dark patterns.
- **Data Visualization Designer**: dashboards legibles y vivos.
- **Sound Designer**: micro-audio UI opcional (WebAudio).
- **Backend UX Engineer**: admin productivo, atajos, densidad.
- **Security Engineer**: efectos no deben introducir superficies de ataque.
- **QA Engineer**: regresión visual y funcional.
- **Brand Guardian**: nunca romper la personalidad WONTIA.

## 2. FILOSOFÍA: "DOPAMINA ÉTICA"
El diseño debe producir recompensa visual sin manipular:
1. **Progreso visible**: cada acción muestra avance (barras, checks, partículas).
2. **Recompensa inmediata**: micro-celebraciones (< 1.2s) al completar tareas.
3. **Control total**: el usuario puede reducir movimiento (modo calma) y siempre entiende qué pasa.
4. **Nunca dark patterns**: sin urgencia falsa, sin culpa, sin bucles infinitos.
5. **Sorpresa medida**: 1 momento "wow" por pantalla, no diez.

## 3. PRINCIPIOS VISUALES
- **Minimalismo futurista**: espacio en blanco generoso, jerarquía clara, cero ruido.
- **Flat con profundidad**: sin skeuomorfismo; profundidad por luz, no por sombras duras.
- **Luz como material**: gradientes radiales suaves, glows contenidos, aurora animada.
- **Movimiento con propósito**: cada animación comunica estado o guía la mirada.
- **IA presente pero calmada**: TIA se siente viva (pulso, typing, avatar reactivo), nunca invasiva.
- **Consistencia front/back**: mismos tokens, distinta densidad (landing aireada, admin compacta).

## 4. DESIGN TOKENS (fuente única)
```
/* Color — marca */
--brand-cyan: #22D3EE; --brand-violet: #8B5CF6; --brand-pink: #EC4899;
--brand-lime: #34D399; --brand-amber: #F59E0B;
/* Superficies */
--bg: #06080F; --bg-2: #0A0E18; --panel: #0D1220; --panel-2: #111828;
/* Texto */
--text: #E6EDF7; --muted: #8593AB;
/* Bordes/estados */
--border: rgba(148,163,184,.14); --border-2: rgba(148,163,184,.26);
--ok: #34D399; --warn: #FBBF24; --bad: #F87171;
/* Tipografía */
--font-sans: Inter; --font-mono: 'JetBrains Mono'; base 14px, escala 1.25;
/* Espaciado: 8pt (4/8/12/16/24/32/48/64/96) */
/* Radios: 6/10/14/999 */
/* Sombras de COLOR (no negras): rgba(brand,.08-.2) */
/* Motion */
--dur-fast: .15s; --dur: .25s; --dur-slow: .45s;
--ease-out: cubic-bezier(.22,1,.36,1); --ease-spring: cubic-bezier(.34,1.56,.64,1);
```
Los tokens se exportan desde el admin (JSON/CSS) y son la ÚNICA fuente de verdad para temas de clientes.

## 5. SISTEMA DE MOVIMIENTO (Motion System)
- **Scroll-driven animations nativas** (`animation-timeline: view()/scroll()`) para reveals, parallax y barras de progreso — sin JS, sin jank.
- **View Transitions API** para navegación entre paneles del admin y cambios de vista (morphing nativo).
- **Micro-interacciones**: hover lift 2-3px, springs, shine sweep, typing físico, pulse rings.
- **Luz viva**: aurora de fondo (18s), orbes flotantes, spotlight que sigue el cursor con inercia.
- **Celebraciones**: confeti de partículas CSS al publicar/actualizar (< 1.6s, one-shot).
- **Reglas anti-saturación**: máx. 2 gradientes por viewport; animaciones ≤ 600ms; nada en bucle en primer plano; pausa automática fuera de viewport; `content-visibility: auto`.
- **Modo calma**: toggle que desactiva loops/parallax (accesibilidad + concentración).
- **`prefers-reduced-motion`** respetado SIEMPRE con alternativa estática equivalente.

## 6. TECNOLOGÍAS DE ÚLTIMA GENERACIÓN (permitidas)
- **CSS**: scroll-driven animations, view transitions, container queries, `:has()`, `@property`, `color-mix()`/`oklch()`, anchor positioning, nesting, `@layer`, `corner-shape`, `clip-path` morphing.
- **JS (vanilla)**: View Transitions API, Popover API, `<dialog>`, Web Animations API, Web Speech (voz), Battery Status API (ahorro), IntersectionObserver/ResizeObserver.
- **GPU (solo landing hero, lazy)**: WebGPU/Three.js + TSL para fondos generativos; **fallback CSS obligatorio** si no hay soporte o el dispositivo es de gama baja.
- **Fuentes**: variables (Inter) + JetBrains Mono tabular para números.
- **Prohibido**: frameworks pesados en landing, librerías de animación > 30KB, video de fondo autoplay, efectos que bloqueen el hilo principal.

## 7. PERFORMANCE BUDGET (obligatorio)
| Métrica | Objetivo |
|---|---|
| LCP | < 1.5s |
| INP | < 100ms |
| CLS | < 0.02 |
| JS landing | < 50KB gzip |
| Efectos GPU | solo above-the-fold, lazy, se apagan con batería baja/reduced-motion |
| Animaciones | transform/opacity únicamente (compositor) |

## 8. ACCESIBILIDAD (WCAG 2.2 AA)
Foco visible elegante (anillo animado), contraste AA sobre gradientes (overlay de seguridad), labels y roles correctos, navegación completa por teclado, texto escalable 200%, targets ≥ 44px, sin información solo por color.

## 9. 50+ INNOVACIONES (nunca antes vistas en un CMS/website builder)
**Movimiento y luz**
1. Aurora reactiva al scroll (gradientes que evolucionan con la posición).
2. Spotlight de cursor con inercia y suavizado (landing y admin).
3. Botones magnéticos con atracción elástica.
4. Títulos con revelado por máscara ligado al scroll (clip-path + animation-timeline).
5. Transiciones de página nativas (View Transitions) sin recarga ni flash.
6. Hero WebGPU generativo con partículas que reaccionan al mouse y al audio.
7. Glassmorphism líquido con refracción simulada por capas y noise.
8. Fondos generativos por shader con fallback CSS automático.
9. Tipografía variable que cambia peso/ancho según scroll y velocidad.
10. Texto cinético escalonado en títulos (letras con delay orgánico).

**Interacción**
11. Cursor contextual (texto/arrastrar/ver/zoom según zona).
12. Haptics móviles (vibración sutil) al confirmar acciones clave.
13. Micro-audio UI opcional (WebAudio, opt-in, apagado por defecto).
14. Confetti físico con gravedad real al publicar.
15. Drag & drop con preview fantasma y snapping magnético.
16. Reordenamiento con animaciones FLIP (sin saltos).
17. Undo/redo visual con timeline de cambios.
18. Command palette (Ctrl+K) con búsqueda difusa en el admin.
19. Atajos contextuales visibles en hover (kbd hints).
20. Foco visible animado que guía la mirada.

**Datos y feedback**
21. Contadores que animan al entrar en viewport.
22. Gráficas SVG con trazo animado ligado al scroll.
23. Barras de progreso con partículas al completar.
24. Skeletons que predicen el layout real (CLS 0).
25. Indicadores de salud con pulso orgánico (latido).
26. Jobs en vivo en el topbar del admin (progreso real).
27. Toasts apilables con swipe-to-dismiss.
28. Validación inline con micro-sacudida (sin modales).
29. Estados vacíos ilustrados con CSS art (cero imágenes).
30. Semáforo de Core Web Vitals del sitio en el admin.

**Editor y CMS**
31. Preview de sección en hover (mini-render real en el listado).
32. Snapshot visual con diff antes/después al restaurar versiones.
33. Export de design tokens (JSON/CSS) desde el admin.
34. Personalización por hora del día (tema día/noche automático).
35. Modo presentación del admin (solo datos, sin chrome).
36. Densidad adaptable (compact/comfort) por usuario.
37. Modo colaboración (cursores de otros editores) — futuro.
38. A/B testing visual integrado con toggle de variantes.
39. IA que inserta secciones desde una descripción ("agrega un menú de precios").
40. Búsqueda semántica ("¿dónde edito el hero?").

**Sistema y confianza**
41. Modo calma (reduce loops con un clic).
42. Battery-aware: apaga efectos GPU con batería baja.
43. Dark/light con transición radial desde el toggle.
44. Edge-ready: HTML estático + hidratación mínima.
45. Diseño consistente front/back con tokens compartidos.
46. Impresión/PDF elegante de reportes (estilos print).
47. Onboarding gamificado con checklist y celebración final.
48. Accesibilidad de primera clase (no como parche).
49. Motion tokens versionados (cambiar el ritmo global en 1 línea).
50. Modo "reduce data": desactiva efectos en conexiones lentas (Network Information API).
51. TIA con presencia: avatar reactivo (pensando/hablando/ejecutando).
52. Chat con typing físico natural (no bloques).
53. Notificaciones silenciosas agrupadas por tipo.
54. Preferencias de movimiento por usuario sincronizadas.
55. Micro-parallax de profundidad en tarjetas (tilt 3D leve).

## 10. GOBERNANZA (PEDS)
- **Monitor de código** obligatorio: tokens usados (cero colores hardcodeados), efectos con `transform/opacity`, `prefers-reduced-motion`, budgets, sin dependencias no aprobadas.
- **Versionado**: `design-system v1.0` en el repo; cambios de tokens se documentan y se propagan a temas de clientes.
- **QA visual**: checklist por componente (hover/focus/disabled/loading/empty/error + móvil).
- **Regla de oro**: si un efecto no comunica estado ni guía la atención, se elimina.

## 11. APLICACIÓN A WWI
1. **Landing WWI (tema `wwi`)**: aurora + orbes + spotlight + scroll-driven reveals + marquee + confeti (ya iniciado); hero GPU opcional en fase 2.
2. **Sitios de clientes**: heredan tokens del Brand Kit (TIA `set_color` → tokens); efectos moderados según plan.
3. **Admin SPA**: mismo lenguaje (dark, cyan/violeta), densidad compacta, View Transitions entre paneles, Ctrl+K, jobs en vivo, micro-feedback en cada acción.
4. **Progressive enhancement**: todo funciona sin JS/GPU; los efectos son la capa de deleite, no la base.
