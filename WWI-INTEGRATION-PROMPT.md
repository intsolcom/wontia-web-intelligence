# WWI — PROMPT DE INTEGRACIÓN PARA OTRAS APPS

> Copia este archivo completo en un agente IA (DeepSeek/OpenCode) para integrar WWI en otra aplicación.
> Regla de oro: usa SOLO los endpoints listados. Las credenciales sensibles van como referencias a archivos, nunca en texto plano.

## 1. QUÉ ES WWI

- **WWI** (Wontia Web Intelligence): CMS multi-tenant (PHP 8.3 + MariaDB) que sirve sitios web por tenant (`SITE_ID`).
- **BRICK**: capa de IA transversal (proveedores/modelos/políticas/failover/costos). Cualquier app del ecosistema obtiene IA mediante una API normalizada, sin saber qué proveedor responde.
- **Factory**: motor de venta/provisioning de sitios (planes, órdenes, dominios, correos, saldos).

## 2. RUTAS PÚBLICAS (sin auth)

| Método | Ruta | Uso |
|---|---|---|
| GET | `https://wwi.wontia.com/api/v1/health` | Estado de la app |
| GET | `https://wwi.wontia.com/api/v1/public/plans` | Planes activos (bilingüe, precios COP/USD) |
| GET | `https://wwi.wontia.com/api/v1/public/plans/{slug}` | Plan individual (ej. `web-starter`) |
| GET | `https://wwi.wontia.com/api/v1/public/templates` | Categorías de plantillas |
| GET | `https://wwi.wontia.com/api/v1/public/domain/check?name=dominio.com` | Disponibilidad REAL vía RDAP + sugerencias de TLDs con precios |
| GET | `https://wwi.wontia.com/api/v1/brick/health` | Estado del componente BRICK (sin key) |

## 3. RUTAS BRICK PARA APPS DEL ECOSISTEMA (header `X-Brick-Key`)

| Método | Ruta | Uso |
|---|---|---|
| POST | `/api/v1/brick/request` | Inferencia normalizada: AIRequest → AIResponse |
| POST | `/api/v1/brick/command` | Comandos TIA: "show costs", "use cheapest", "set primary", "health" |

### AIRequest (entrada)

```json
{
  "system_id": "mi_app",
  "module": "general",
  "function": "default",
  "system_prompt": "Eres el asistente de mi app.",
  "messages": [{"role": "user", "content": "Haz una tarea"}],
  "temperature": 0.7,
  "max_tokens": 500,
  "capabilities": ["text"]
}
```

### AIResponse (salida — SIEMPRE este formato)

```json
{"ok": true, "content": "...", "model": "DeepSeek V4 Chat", "provider": "DeepSeek",
 "usage": {"input_tokens": 11, "output_tokens": 3, "total_tokens": 14},
 "latency_ms": 700, "cost": 0.000006, "used_fallback": false}
```

### Ejemplo curl

```bash
curl -X POST https://wwi.wontia.com/api/v1/brick/request \
  -H 'Content-Type: application/json' -H 'X-Brick-Key: <BRICK_KEY>' \
  -d '{"system_id":"mi_app","module":"general","function":"default",
       "messages":[{"role":"user","content":"Hola"}]}'
```

## 4. RUTAS DE ADMIN (JWT `Authorization: Bearer <token>`)

- Login: `POST /api/v1/admin/auth/login` con `{"username":"admin","password":"<ADMIN_PASS>"}` → devuelve `token`.
- Factory: `/api/v1/admin/factory/*` — plans, sites, domains, emails, orders, ledger, dashboard, margin, config, my-portal.
- BRICK: `/api/v1/admin/brick/*` — overview, providers, models, policies, instances, usage, test, command.

## 5. CREDENCIALES (ubicación — no imprimirlas en respuestas)

- **Panel admin**: `https://wwi.wontia.com/admin.php` · usuario `admin` · contraseña por defecto `admin` (cambiar al primer uso).
- **BRICK keys** (archivos locales del operador):
  - `C:\Users\sergi\AppData\Local\Temp\opencode\brick_key_new.txt` → key del site 1 (wontia.com)
  - `C:\Users\sergi\AppData\Local\Temp\opencode\brick_key_site3.txt` → key de la Factory (wwi.wontia.com)
- **BD**: MariaDB contenedor `mysql-prod` (red interna `intsolcom`), DB `wontia`, usuario `wontia` (pass vive en el VPS, no en chat). Acceso externo: túnel SSH `ssh -L 3306:127.0.0.1:3306 root@<VPS_IP>`.
- **VPS**: SSH `root@<VPS_IP>` con key `~/.ssh/contabo_vps`.

## 6. REGLAS DE SEGURIDAD (obligatorias)

1. La BRICK key SOLO en header `X-Brick-Key` — nunca en URLs, logs ni respuestas.
2. Sin key configurada → 403; key inválida → 401. Manejar ambos códigos.
3. CORS de rutas protegidas restringido a `APP_URL`; respuestas sensibles con `Cache-Control: no-store`.
4. El webhook de pago es la única fuente de verdad de un pago (nunca el frontend).

## 7. CHECKLIST DE INTEGRACIÓN PARA UNA APP NUEVA

1. Definir `system_id` (ej. `mi_app`) y registrar políticas en BRICK (`system_id/module/function`).
2. Configurar la BRICK key como variable de entorno de tu app (nunca en el repo).
3. Llamar `POST /api/v1/brick/request` con tu `system_id/module/function`; el enrutador elige modelo, hace failover y registra costo.
4. Dominios: `GET /api/v1/public/domain/check?name=...` — estados: AVAILABLE, TAKEN, INVALID, CHECKING, ERROR.
5. Prueba de humo obligatoria: `/api/v1/health` (ok:true) + un `/brick/request` con contenido no vacío.

## 8. NOTAS

- Precios de planes y costos de dominios son configurables en Factory → Config (no hardcodear).
- Referencias en el repo: `BRICK-MASTER-PROMPT.md` (concepto BRICK), `WWI-AUDIT.md`, `WWI-MASTER-IMPLEMENTATION-PLAN.md`, `AGENTS.md`.
- Si un endpoint no responde: verificar DNS/TLS y que el contenedor `wontia-wwi` esté arriba (red `intsolcom`, puerto 4009).
