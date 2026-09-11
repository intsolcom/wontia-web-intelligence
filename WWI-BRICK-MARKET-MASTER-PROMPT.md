# WWI — BRICK MARKETPLACE MASTER PROMPT

> Especificación + 55 innovaciones para el Brick Marketplace y Bricks Instalados. Objetivo: el marketplace de bricks más robusto, medible y adictivo del planeta. Aplica a `#bricks` (pestañas Brick Marketplace / Bricks Instalados / IA (BRICK) / Repos & Sync / Incubadora).

## 1. REQUERIMIENTOS BASE (implementados ✅)
- **Brick Marketplace**: todos los bricks disponibles (Core + repos GitHub + Incubadora), 5 por fila, diseño flat tecnológico, hover con elevación y glow, orden por categoría / fecha / versión / más instalados / más usados, buscador difuso y chips de categoría.
- **Etiqueta Instalado verde** en las tarjetas instaladas (marketplace e instalados).
- **Valoración 1-5 estrellas** con emojis: 1 🤔 Nada popular · 2 🙈 Empieza su aventura · 3 🏍️💨 Tomando velocidad · 4 🏎️🔥😄 ¡Vamos funcional! · 5 🚀😄👏🎉 ¡Fuera de órbita! Muy popular.
- **Contador de instalaciones** por brick (global) + usos en páginas + previews.
- **Bricks Instalados**: solo activos, resplandor verde sutil, **punto verde palpitante** de funcionamiento, emoji 🔄 + botón Actualizar si hay update, gestión (check/uninstall/abrir panel).
- **Métricas de gusto medibles**: distribución de estrellas, embudo preview→instalación→uso, mejor/peor valorados, más instalados, últimas opiniones.
- **Tablas**: `brick_ratings`, `brick_metrics`, `brick_events` (auto-creadas, idempotentes, multi-tenant con `site_id` de trazabilidad; agregación global de producto).

## 2. LAS 55 INNOVACIONES

### A. Marketplace visual y UX (1-14)
1. ✅ Grid 5×N con responsive 4/3/2/1 y hover elevación + glow cyan.
2. ✅ Tarjeta flat con chips de origen (Core/Repo/Incubadora), categoría, versión, fecha y chip IA.
3. ✅ Etiqueta verde "✓ Instalado" siempre visible en ambas pestañas.
4. ✅ Resplandor verde interior sutil en tarjetas instaladas.
5. ✅ Punto verde palpitante lento (funcionando) con halo expansivo.
6. ✅ Emoji 🔄 palpitante cuando hay actualización + botón directo.
7. Preview en vivo dentro de la tarjeta (miniatura renderizada en canvas/iframe lazy al hover).
8. Comparador side-by-side de 2-3 bricks (métricas y valoraciones en columnas).
9. Vista "Sala de trofeos": top 5 por categoría con medallas 🥇🥈🥉.
10. Modo "vitrina" a pantalla completa (solo tarjetas, sin chrome) para presentar a clientes.
11. Filtros combinables (categoría + origen + solo instalados + solo IA + con update).
12. Chips de "tendencias" (🔥 más valorado esta semana, 🚀 subiendo).
13. Skeleton cards con shimmer durante la carga (cero layout shift).
14. Micro-animación de entrada escalonada (stagger) al renderizar el grid.

### B. Valoración y dopamina ética (15-28)
15. ✅ Estrellas interactivas con tooltip/emoji por nivel.
16. ✅ Recompensa visual (confetti) al valorar con 4-5 estrellas.
17. ✅ Distribución de estrellas por brick (1-5) visible en el modal de métricas.
18. Reseñas con comentario (input opcional tras valorar; ya soportado en DB).
19. "¿Te resultó útil?" (👍/👎) sobre cada reseña para moderación comunitaria.
20. Ranking semanal "Top movers" (mayor subida de rating en 7 días).
21. Insignias automáticas: "Favorito de la comunidad", "Recién llegado prometedor", "En racha".
22. Barra de progreso "Tu opinión ayuda: N usuarios ya valoraron".
23. Streak de valoraciones (gamificación ética, sin obligación).
24. Recordatorio amable 7 días después de instalar: "¿Cómo te fue con este brick?".
25. Comparativa personal: "Valoraste este brick 5★, la media es 4.2★".
26. Histórico de tu valoración (cambios en el tiempo) auditable.
27. Emojis animados al hover de cada estrella (scale + color).
28. Modo "anonimato" de reseña (solo métrica, sin username público).

### C. Bricks Instalados y operación (29-40)
29. ✅ Punto de salud por brick (pulso lento = OK; rápido/rojo = degradado/offline).
30. ✅ Update disponible con emoji + botón y contador global en el badge del sidebar.
31. Auto-update configurable por brick (canal estable/beta).
32. Changelog inline al pulsar Actualizar (diff de versiones + archivos).
33. Rollback por brick a la versión anterior (usa `:previous`).
34. Programación de updates (ventana horaria / fin de semana).
35. Dependencias entre bricks: instalar A sugiere/requiere B (grafo visible).
36. "Salud del ecosistema": semáforo por brick (health check + último error).
37. Sandbox de prueba: activar brick en preview sin publicar.
38. Modo mantenimiento por brick (pausa sin desinstalar).
39. Auditoría de instalación/desinstalación (quién, cuándo, desde qué sitio).
40. Export/Import de configuración de un brick entre sitios (JSON firmado).

### D. Métricas y aprendizaje (41-48)
41. ✅ Embudo medible: previews → instalaciones → añadidos a página → updates.
42. ✅ Insights de gusto: mejor/peor valorados, más instalados, distribución global.
43. ✅ Eventos granulares en `brick_events` (view, preview, install, uninstall, add_to_page, update, rate).
44. Cohortes: retención por brick (¿sigue instalado a los 30/90 días?).
45. Correlación rating vs retención ("los 5★ se desinstalan 3× menos").
46. Alertas automáticas: caída de rating > 0.5 en 7 días, picos de desinstalación.
47. Dashboard temporal con series (rating/instalaciones por semana) en SVG nativo.
48. Export CSV/JSON de métricas para BI externo.

### E. Robustez, seguridad y escala (49-55)
49. ✅ Validación estricta server-side: rating 1-5, slug regex, comentario ≤500, whitelist de eventos.
50. ✅ Un voto por usuario y brick (UNIQUE user+brick) con upsert; sin spam.
51. ✅ Tablas idempotentes auto-creadas; sin migración manual; multi-site con trazabilidad.
52. Rate-limit de eventos por usuario/minuto (anti-abuso del contador).
53. Moderación de comentarios (cola de revisión + blacklist de términos).
54. Réplica/edge: métricas agregadas en caché (TTL 60s) para no golpear DB en picos.
55. Versionado semántico estricto + verificación de firma de bricks (supply-chain).

## 3. ACOPLE / DESACOPLE — 10 INNOVACIONES
1. ✅ **Semántica acoplado/desacoplado**: verde "✓ Acoplado" / rojo "○ Desacoplado", botones "Acoplar"/"Desacoplar" (los Core no se desacoplan: vienen integrados al motor).
2. ✅ **Motivo de bloques** en cada ficha (mask SVG de 8 bloques, esquina inferior derecha, opacidad 12-18%, `pointer-events:none`) — identidad visual sin invadir.
3. ✅ **Desmoronamiento**: al desacoplar, 12 bloques con gravedad, rotación y dispersión caen desde la ficha (Web Animations API) y el brick "vuelve" al Marketplace.
4. ✅ **Acoplamiento inverso**: al acoplar, los bloques convergen y encajan en la ficha (snap magnético con easing), seguido de confetti blocky.
5. ✅ **Traslado a la categoría**: tras acoplar, navegación automática a Bricks Acoplados con highlight verde + scroll centrado de la ficha.
6. ✅ **Reduced-motion**: con `prefers-reduced-motion` las animaciones se omiten y solo cambia el estado (accesibilidad WCAG).
7. Haptics móvil (`navigator.vibrate`) al acoplar/desacoplar en dispositivos táctiles.
8. Sonido de encaje de bloques opcional (WebAudio, off por defecto, opt-in).
9. **Undo de desacople**: toast accionable 8s con "Deshacer" que re-acopla sin pasar por el Marketplace.
10. **Motivo de desacople**: selector (no lo uso / lento / faltan funciones / otro) que alimenta el ranking de causas y alertas de producto.
- Bonus: modo "obra" (barra de progreso con bloques apilándose durante la instalación) y confetti 100% cuadrado para coherencia con el concepto de bloques.

## 4. GOBERNANZA (PEDS)
- Toda query nueva con `@site_id` cuando aplique; las agregaciones globales del marketplace son intencionales (feedback de producto cross-tenant) y guardan `site_id` de trazabilidad.
- Escape de salida en frontend (`W.esc`); slugs validados con regex; eventos con whitelist.
- Estados UX: loading, empty, error y success en cada vista; reduced-motion respetado.
- Prueba en vivo obligatoria de endpoints nuevos antes de cerrar.
