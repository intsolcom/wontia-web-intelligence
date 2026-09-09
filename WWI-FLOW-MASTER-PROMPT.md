# WWI — PROMPT MAESTRO: FLUJO GUIADO DE CREACIÓN (Slide Wizard)

> Especificación para agentes IA (DeepSeek/OpenCode). Reutilizar el stack existente (PEDS): landing tema `wwi`, TIA via BRICK, pricing engine, domain checker RDAP, checkout público, factory. NO crear un segundo sistema de páginas.

## OBJETIVO
El cliente entra a la landing y se queda EN EL HERO con instrucciones grandes y claras, sin ser invasivo:
"¿Estás listo para implementar tu sitio web en pocos minutos?" → CTA **Empezar**.

Al hacer clic, un **slide** (efecto slide-left) lo lleva a una **caja de chat con TIA** que le dice:
> "Escribe cómo quieres tu sitio web. Por ejemplo: *soy arquitecto y quiero que mi sitio muestre mi trayectoria y los proyectos que he ejecutado*."

## FLUJO (máquina de estados de slides)
1. **HERO** (instrucciones grandes + CTA Empezar).
2. **SLIDE PROMPT** — chat con TIA (ejemplo precargado como placeholder). El usuario escribe su prompt.
3. **GENERANDO** — spinner + pasos visibles (analizando → estructura → contenido). TIA genera la estructura del sitio.
4. **SLIDE PREVIEW** — botón **"Ver preview"** abre un MODAL con el sitio funcional (menús + contenido), pero NO es un sitio activo: es una **muestra temporal** (`wwi_previews`, TTL 1h) para no gastar espacio ni crear tenants sin propósito.
   - Botón **"Editar el prompt"** (vuelve al chat con el prompt anterior precargado).
   - Botón **"Descartar, probar un prompt diferente"** (vuelve al chat con prompt vacío).
   - **Límite: 2 prompts por IP** (no agotar tokens). Superado el límite → catálogo.
5. **SLIDE CATÁLOGO (fallback)** — si el cliente no se decide, ve las plantillas predefinidas y elige manualmente.
6. **SLIDE PLANES** — máximo **3 planes** (SIN Web Master). Web Master aparece al final como **venta cruzada** (agregar al carrito) al elegir plan.
7. **SLIDE DOMINIO** — chat con TIA para escoger dominio (sugerencias por sector + disponibilidad RDAP en vivo).
8. Al confirmar dominio → se crea el pedido (checkout existente) con plan + addons → pago (dummy hoy, Wompi después).

## REGLAS
- Nada invasivo: sin popups automáticos; el flujo solo avanza cuando el usuario hace clic.
- Preview 100% efímero: URL única, expiración visible, limpieza automática.
- Límites por IP en backend (no solo frontend).
- No alucinar: TIA no inventa datos del negocio; faltantes → pendientes.
- Todo queda en AI Audit Log.

## 30 INNOVACIONES PROPUESTAS (para hacerlo el más potente del planeta)
1. **Voz**: dictar el prompt con SpeechRecognition (webkitSpeechRecognition).
2. **Preguntas de seguimiento inteligentes** de TIA (máx. 3) si el prompt es ambiguo.
3. **Detección automática de sector** (arquitecto→construcción) para pre-elegir plantilla base y paleta.
4. **Device switcher en el preview** (desktop/tablet/mobile dentro del modal).
5. **"TIA explica"**: por qué eligió esas secciones (justificación visible).
6. **Comparación A/B**: 2 variantes de preview y elegir una (consume 1 intento).
7. **Generación en 2 fases**: estructura primero (rápido), contenido después (streaming progresivo).
8. **Score de completitud** del preview vs el prompt.
9. **Chips de mejora** en editar prompt: "más formal", "más premium", "más juvenil".
10. **Tone selector** previo al prompt (Professional/Friendly/Premium…).
11. **Renovación progresiva de intentos** (1 intento nuevo cada 24h) con aviso claro.
12. **Autoguardado del prompt** en localStorage para reanudar la sesión.
13. **Historial de versiones del prompt** con undo/redo por sesión.
14. **Catálogo con filtros por sector + búsqueda + "sorpréndeme"** (plantilla aleatoria).
15. **Mini-preview al hover** de cada plantilla (thumbnail estático).
16. **Comparador de planes deslizable** con "qué incluye" expandible.
17. **Cross-sell inteligente**: ofrecer Web Master solo si el sitio tiene e-commerce o >N secciones.
18. **Carrito real multi-item** (plan + addons) con totales en vivo.
19. **Sugerencias de dominio por sector con score de marca** (5 opciones + disponibilidad RDAP en vivo).
20. **Si el dominio está TAKEN, TIA sugiere alternativas al instante** (variaciones).
21. **Detección del nombre del negocio en el prompt** para prellenar el dominio.
22. **Autoguardado de cada paso** → recuperar el flujo al cerrar la pestaña.
23. **Barra de progreso del wizard** (5 pasos, animación sutil).
24. **Demo guiada opcional** para el primer visitante (ejemplo precargado).
25. **Timeout de generación con fallback automático al catálogo** de plantillas.
26. **Analytics del funnel por paso** (eventos) para ver dónde abandonan.
27. **Countdown visible** de expiración del preview.
28. **Preview en dark/light** según el tone elegido.
29. **Compartir preview** (enlace temporal) para pedir opinión antes de comprar.
30. **Botón "¡Me gusta! Comprar este"** que salta directo a planes con el prompt fijado.

## ENTIDADES NUEVAS (mínimas, PEDS)
- `wwi_previews` (uuid, ip_hash, prompt, structure JSON, status generating|ready|failed, created_at) — TTL 1h.
- `wwi_prompt_attempts` (ip_hash, day, attempts) — límite 2 por IP/día.
- `wwi_orders.addons` JSON — carrito (plan + addons).

## API NUEVA (pública)
- `POST /api/v1/public/previews` {prompt} → uuid + intentos restantes.
- `GET /api/v1/public/previews/{uuid}` → estado + structure.
- `GET /api/v1/public/preview/{uuid}` → HTML efímero renderizado (modal iframe).
- `GET /api/v1/public/previews/attempts` → intentos usados/restantes (por IP).
- `POST /api/v1/public/previews/suggest-domains` {business} → 5 nombres sugeridos (TIA).
- Worker: job `generate_preview` + limpieza de previews expirados en el cron.
