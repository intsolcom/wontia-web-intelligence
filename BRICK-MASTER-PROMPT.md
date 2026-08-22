# BRICK — CONCEPTO & MASTER PROMPT

> Copia este archivo completo en DeepSeek (o cualquier agente IA) para que entienda BRICK y sepa cómo integrar cualquier sitio web o landing page del ecosistema WONTIA.

---

## 1. QUÉ ES BRICK

**BRICK = AI Provider & Model Management.** Es la **capa de infraestructura de inteligencia artificial** del ecosistema WONTIA. Un "ladrillo" modular y reutilizable que resuelve siempre la misma función: responder a la pregunta

> "Necesito inteligencia para esta tarea."

BRICK determina:

- **WHAT** — qué capacidad se necesita (texto, razonamiento, visión, tools...)
- **WHICH** — qué modelo es el adecuado
- **WHERE** — qué proveedor lo ejecuta (DeepSeek, OpenAI, Anthropic, Gemini, xAI, Mistral, OpenRouter...)
- **HOW** — qué política de selección aplicar (manual, cost, performance, balanced, auto)
- **FALLBACK** — qué hacer si el modelo falla (cadena de hasta 3 modelos)
- **COST** — cuánto cuesta (registro por request en USD)
- **HEALTH** — si el modelo está disponible (healthy/degraded/offline)

**BRICK no es de una sola app.** Es transversal: WONTIA, TIA System, IA Annotation, Websites, Landing Pages, Agents, Automations y cualquier producto futuro.

Filosofía WONTIA/TIA: **UNDERSTAND → DECIDE → ACT**. BRICK es la pieza que permite a TIA DECIDE y EXECUTE sobre la infraestructura de IA.

---

## 2. ARQUITECTURA

```
APLICACIÓN (cualquier sitio, app o landing)
        │
        ▼
   TIA / AI SERVICE
        │
        ▼
      BRICK            ← registro de providers, modelos, políticas, costos
        │
        ▼
    AI ROUTER          ← decide qué modelo usar según policy + failover
        │
        ▼
 PROVIDER ADAPTER      ← OpenAiCompatibleAdapter (DeepSeek, OpenAI, Gemini, xAI,
        │                Mistral, OpenRouter, Azure) · AnthropicAdapter (Claude)
        ▼
   MODELO (API)
```

La aplicación **nunca** depende del proveedor. Cambiar de DeepSeek a OpenAI no toca una línea de la app: solo cambia una policy en el panel BRICK.

---

## 3. CÓMO SE CONECTA UN NUEVO SITIO WEB / LANDING PAGE

Hay 3 modos, ordenados de preferencia:

### Modo A — Sitio en WWI (multi-site automático) ★
Si el sitio se despliega como tenant de Wontia Web Intelligence:
1. Levantar el contenedor con `SITE_ID` nuevo (ej. `SITE_ID=3`).
2. **Nada más.** BRICK se auto-provisiona la primera vez que se usa: al abrir el panel `#brick` o al recibir el primer request, `AiBrickService::provision()` detecta que el site no tiene proveedores y siembra automáticamente (7 providers, 13 modelos, 6 instancias, 4 policies) con `site_id = @site_id`.
3. El sitio ya tiene panel AI BRICK en su admin: `https://<dominio>/admin.php#brick`.

En instalaciones nuevas, el wizard `install.php` ejecuta `install/brick_ai.sql` solo.

### Modo B — Sitio externo vía HTTP (sin instalar nada)
Cualquier app, sitio o landing del ecosistema (Node, Python, PHP, agentes) consume BRICK por API:
- `POST https://<host-brick>/api/v1/brick/request` con header `X-Brick-Key: <BRICK_API_KEY>` → AIRequest normalizado → AIResponse.
- `GET /api/v1/brick/health` — sin key, para monitoreo.
- `POST /api/v1/brick/command` — capa de comandos para TIA.

Desde la red interna del VPS: `http://wontia-web-intelligence:80/api/v1/brick/request`.

### Modo C — Copiar el componente (PHP)
La carpeta `src/Core/AiBrick/` es autocontenida (PSR-4 `App\Core\AiBrick\`). Copiarla a otro proyecto PHP y apuntar a la misma base de datos (o ejecutar `install/brick_ai.sql`) la deja operativa.

---

## 4. CONTRATO NORMALIZADO

Todos los proveedores hablan el MISMO formato de entrada y salida.

### AIRequest (entrada)

```json
{
  "system_id": "tia",
  "module": "orchestration",
  "function": "default",
  "system_prompt": "Eres el núcleo de inteligencia de WONTIA.",
  "messages": [
    {"role": "user", "content": "Analiza estos datos y recomienda una acción."}
  ],
  "temperature": 0.7,
  "max_tokens": 1500,
  "tools": [],
  "response_format": null,
  "capabilities": ["reasoning", "tools"],
  "user_id": 123
}
```

- `system_id` / `module` / `function` localizan la **policy** que decide modelo y estrategia.
- `capabilities` = capacidades requeridas (text, reasoning, vision, audio, image, video, coding, tools, structured_output, streaming, embeddings, long_context, realtime, agents).

### AIResponse (salida)

```json
{
  "ok": true,
  "content": "Respuesta del modelo...",
  "model": "DeepSeek V4 Chat",
  "provider": "DeepSeek",
  "usage": {"input_tokens": 11, "output_tokens": 3, "total_tokens": 14},
  "latency_ms": 708,
  "cost": 0.000006,
  "finish_reason": "stop",
  "used_fallback": false,
  "attempt": 0,
  "system_id": "tia",
  "module": "orchestration",
  "function": "default",
  "strategy": "balanced"
}
```

`used_fallback`/`attempt` indican si hubo failover. Cada intento queda registrado en `ai_usage` (tokens, costo, latencia, status success|error|fallback|budget_blocked).

### Ejemplo curl

```bash
curl -X POST https://wontia.intsolcom.com/api/v1/brick/request \
  -H 'Content-Type: application/json' \
  -H 'X-Brick-Key: <BRICK_API_KEY>' \
  -d '{"system_id":"website","module":"seo","function":"metadata","messages":[{"role":"user","content":"Genera meta description para: Tienda de café artesanal"}],"max_tokens":200}'
```

---

## 5. POLICIES Y ESTRATEGIAS

Una policy = `system → module → function → cadena de modelos`.

| Strategy | Comportamiento |
|---|---|
| `manual` | Usa primary → fallback1 → fallback2 tal cual están configurados |
| `cost` | El modelo más barato que cumpla las capacidades requeridas |
| `performance` | El de mayor prioridad/rendimiento |
| `balanced` | Balance: prioridad + costo + capacidad + disponibilidad |
| `auto` | El sistema elige automáticamente |

Cada policy además tiene: `required_capabilities`, `preferred_providers`, `excluded_providers`, `max_cost_per_request`, `monthly_budget` (con `budget_warning_pct` y `budget_hard_limit_pct` — al llegar al límite, BRICK bloquea y devuelve `budget_blocked`), `fallback_enabled`.

Polícies sembradas por defecto:

| System | Module | Function | Strategy | Primary → Fallback |
|---|---|---|---|---|
| wontia | general | default | balanced | deepseek-chat → gpt-4o-mini |
| wontia | content | generation | cost | deepseek-chat → gpt-4o-mini |
| tia | orchestration | default | balanced | deepseek-reasoner → deepseek-chat → gpt-4o-mini |
| website | seo | metadata | cost | deepseek-chat → gpt-4o-mini |

---

## 6. CAPA DE COMANDOS (TIA)

`POST /api/v1/brick/command` con `{"command": "...", "args": {...}}`:

- `show costs` → costos del mes por provider/model
- `use cheapest` → cambia la policy a estrategia cost
- `best reasoning model` → estrategia performance
- `set primary` (args: `model`) → define modelo primario
- `set fallback` (args: `model`) → define fallback
- `health` → estado de providers/modelos

Esto permite que TIA controle BRICK por voz o texto: "usa el modelo más barato para esta tarea" → UNDERSTAND → DECIDE → EXECUTE sobre la infraestructura.

---

## 7. SEGURIDAD

- **Las API keys de los proveedores NUNCA están en la base de datos ni en el frontend.** La DB guarda solo `api_key_env` (nombre de la variable de entorno: `BRICK_DEEPSEEK_API_KEY`, `BRICK_OPENAI_API_KEY`...). DeepSeek además cae a `DEEPSEEK_API_KEY` legacy.
- El frontend solo ve `has_key: true|false`.
- La API pública exige header `X-Brick-Key` (env `BRICK_API_KEY`). Sin key configurada → 403; key inválida → 401.
- El panel admin exige JWT de WWI.

---

## 8. CHECKLIST — DESPLEGAR UN SITIO NUEVO CON IA

1. **WWI multi-site**: nuevo contenedor con `SITE_ID` nuevo → BRICK se auto-siembra (Modo A). Nada más.
2. **Sitio externo**: elegir Modo B (HTTP con `X-Brick-Key`) o Modo C (copiar `src/Core/AiBrick/`).
3. Registrar la app en `ai_instances` si es un sistema nuevo (ej. `system_id: "shop_xyz"`).
4. Crear sus policies (`system → module → function`) desde el panel o vía `ai_policies`.
5. Configurar API keys de proveedores en el entorno del host BRICK.
6. Probar con `POST /api/v1/brick/request` y verificar en el panel Overview: KPIs, costos por modelo, salud y sugerencias.

## 9. PRINCIPIO FINAL

**BRICK no es un selector de modelos: es la AI INFRASTRUCTURE LAYER del ecosistema.** Cada request deja huella medible (tokens, costo, latencia, estado), cada fallo activa failover, cada presupuesto se respeta, y TIA puede gobernar todo con comandos. La app solo pide inteligencia; BRICK resuelve el resto.
