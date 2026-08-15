# WONTIA WEB INTELLIGENCE (WWI) — Contexto de Sesión

Contexto permanente para seguir construyendo Wontia y sus bricks. Leer completo antes de tocar código.

## 1. Qué es WWI

CMS ligero **basado en BRICKs** embebido en el ecosistema Wontia. PHP 8.3 + Nginx (Alpine) en Docker, MariaDB, sin frameworks. Admin SPA en vanilla JS (hash router).

**Posicionamiento actual (evolución v3):** WONTIA es un *Applied Intelligence System (AIS)* potenciado por **TIA** (Technology of Applied Intelligence). Ya NO es solo "web intelligence" ni un CRM. Mensaje núcleo: **UNDERSTAND → DECIDE → ACT**. **ONE PLATFORM. ONE INTELLIGENCE. MULTIPLE DOMAINS.**

## 2. Estado actual (agosto 2026)

| Commit | Qué aportó |
|---|---|
| `1eccca5` | Base v1.0.0 (Core, Auth, Admin SPA, 11 controladores, 4 servicios, Blog, Tema, Instalador, SEO, Analytics, Cookies) |
| `fbfe255` | Implementación completa v1.0.0 |
| `8d428bd` | Sistema BRICK: 8 widgets, WidgetRegistry, BrickHub, design system Wontia |
| `2417154` | MASTER-PROMPT v2.0 (reposicionamiento evolucionado) |
| `6733852` | API pública: `GET /api/v1/public/page/{slug}`, `GET /api/v1/public/settings` |
| `c6c7e15` | **Multi-site**: env `SITE_ID` → variable de sesión MySQL `@site_id` |

**Sin commitear:** `public/admin.php` — rebranding de títulos/login a "WWI Wontia Web Intelligence" (8 líneas). Pendiente de commit si se confirma el rebrand.

**NUEVO (no commiteado):** **BRICK — AI Provider & Model Management** (capa de infraestructura de IA del ecosistema). Ver §13.

**Seed disponible:** `install/seed-wontia-ais.php` es el seed más reciente (posicionamiento AIS v3 con secciones: HeroEvolved → Differentiator → TiaCommand → WontiaBusiness → DomainArch → FoodSecurity → PlatformArch → Trust → FutureVision → Pricing → CTA → Footer).

## 3. Arquitectura

```
public/  index.php (front controller) · admin.php (SPA) · api.php (REST /api/v1) · sitemap.php · robots.php · install.php
src/
  Core/         Config, Database (PDO + SET @site_id), Request, Response, Router, Session, App,
                BrickSystem (instalar/desinstalar bricks desde DB), AutoDiscoveryService,
                BrickHubNotificationService, GitHubSyncService,
                AiBrick/ (AiRequest, AiResponse, AiProviderAdapter, OpenAiCompatibleAdapter,
                         AnthropicAdapter, AiRouter, AiBrickService)
  Middleware/   AuthMiddleware (sesión + JWT)
  Controllers/Admin/  Auth, Dashboard, Page, Section, Brick, BrickHub, Media, Blog, BlogCategory,
                      BlogTag, Seo, Analytics, Settings, User, AiBrick
  Services/     AiContentService, SeoService, CookieConsentService, AnalyticsService
  Widgets/      Sistema BRICK de página (ver §5)
  Bricks/       Extensiones instalables (GitHubSync = motor del BrickHub, CodeEmbed)
templates/themes/default/index.php   Tema con design system + render vía WidgetRegistry
install/       schema.sql · seed.sql · seed-wontia-php.php · seed-wontia-evolved.php · seed-wontia-ais.php · brickhub_schema.sql
```

`.env` local: `APP_URL=https://wontia.intsolcom.com`, DB `wontia` en contenedor `mysql-prod`. `SITE_ID` (default `1`) selecciona tenant.

## 4. Los DOS sistemas de plugins (no confundir)

1. **Widgets** (`src/Widgets/`) — bricks de PÁGINA. Secciones con `widget_type` se renderizan vía `WidgetRegistry::render()`. Auto-descubrimiento por glob de `*Widget.php`.
2. **Bricks** (`src/Bricks/`) — extensiones del sistema con BrickHub: tienen `brick.json` (name, slug, version, brick_class, config), se instalan en tabla `bricks` con `site_id`, se actualizan desde repos GitHub (GitHubSyncBrick). Panel: BrickHub en admin.

## 5. Widgets existentes (22)

**v1 (landing original):** `hero`, `features`, `tia`, `aip`, `howitworks`, `pricing`, `cta`, `footer`
**v2 (evolucionados):** `hero-evolved`, `differentiator`, `tiacommand`, `wontia-business`, `domain-arch`, `food-security`, `platform-arch`, `trust`, `future-vision`
**v3 (AIS):** `ais-hero`, `ais-concept`
**Otros:** `code-embed` (CodeEmbedWidget)

### Contrato base (Widget.php)

```php
abstract public function render(array $config = []): string;
public static function meta(): array;              // id, name, icon, category, version
public static function configSchema(): array;      // campos editables en admin
public static function defaultConfig(): array;
public static function adminPreview(): string;
protected function mergeConfig(array $config): array;  // defaults + DB config
protected function esc(string $s): string;             // htmlspecialchars
protected function safeJson($value): array;            // string|array → array
```

### Crear un widget nuevo

1. `src/Widgets/MyNewWidget.php` → `class MyNewWidget extends Widget`
2. Implementar `meta()` + `render()` (+ `configSchema()`/`defaultConfig()` si aplica)
3. Auto-descubierto por WidgetRegistry — aparece en Brick Hub del admin
4. Sección nueva en DB: `INSERT INTO sections (page_id, type, widget_type, title, config, sort_order, is_active)`

## 6. Design system Wontia

Fondo `#F6F6F3`, texto `#2F2F2F`, primary lavender `linear-gradient(135deg,#9B8CDE,#B89EFF)` / `#7C3AED`, acentos `#DCCFFF` `#CFE6FF` `#D9F2E2` `#F7E8C8`. Fuente Inter. Clases en el tema: `.btn-primary`, `.badge`, `.card`, `.card-padded`, `.gradient-text`, `.grid-3`, `.reveal/.visible` (IntersectionObserver), `.img-swap`, `.hero-bg`.

## 7. Reglas de marca (CRÍTICAS)

- **Nunca sobreprometer.** Etiquetar siempre: AVAILABLE / IN DEVELOPMENT / FUTURE / CONCEPT / PROTOTYPE. Wontia Business = CURRENT/AVAILABLE. Food Security = IN DEVELOPMENT. Health, Agriculture, Industry, Logistics, Education = FUTURE.
- **No llamar a Wontia "CRM"** — es *Applied Intelligence Platform*; CRM es una capacidad dentro de un vertical.
- **TIA** = capa de inteligencia. **Wontia** = plataforma. **Dominios** = aplicaciones.
- Personalidad: INTELLIGENT · CALM · PRECISE · POWERFUL · HUMAN · FUTURE-READY. Evitar: cyberpunk, "AI magic", clichés startup, emojis excesivos.
- No romper features que funcionan salvo conflicto con el posicionamiento.

## 8. Multi-site (cómo funciona)

- `Database::instance()` lee `Config::get('SITE_ID', '1')` y ejecuta `SET @site_id = $siteId` en la conexión PDO (`PDO::MYSQL_ATTR_INIT_COMMAND`).
- **TODA query nueva debe filtrar por `site_id = @site_id`** (o `:site_id` en prepared). Revisar controladores existentes como referencia.

## 9. Deploy (VPS Contabo)

- VPS `root@169.58.12.55`, SSH key `~/.ssh/contabo_vps`, contenedor `wontia-web-intelligence` (puerto `4003`, red `intsolcom`), DB en contenedor `mysql-prod`.
- Flujo: `scp` archivos a `/tmp/wontia-build/app/...` → `docker build -t wontia-web-intelligence:latest .` → `docker rm -f` + `docker run -d ... -v /var/lib/dokploy/uploads/wontia:/app/public/assets/uploads`.
- SQL siempre vía archivo: `docker exec -i mysql-prod mysql -uroot -pAdmin2026! wontia < archivo.sql`.
- PowerShell 5.1: **NO usar `&&`** — usar `; if ($?) { ... }`.

## 10. Convenciones de código

- PHP: namespace `App\...`, PDO, sin dependencias externas (composer solo para autoload básico).
- **No añadir comentarios** al código salvo que se pidan.
- Escapar salida de usuario con `$this->esc()`; config JSON siempre con `$this->safeJson()`.
- Admin SPA: paneles en `wontia.panels`, llamadas con `wontia.api()` (auto-adjunta JWT).
- Verificar con `php -l` cada archivo tocado antes de cerrar.

## 11. Siguientes pasos posibles

1. Confirmar y commitear el rebrand "WWI Wontia Web Intelligence" de `public/admin.php` (y extenderlo al sidebar/paneles si procede).
2. Construir más widgets de dominio (nuevos verticales) siguiendo el patrón v3 AIS.
3. Nuevos bricks para BrickHub (fuentes repo, auto-actualización, notificaciones).
4. Migrar contenido vivo a `seed-wontia-ais.php` si aún no está aplicado en el VPS.
5. Multi-site: probar segundo `SITE_ID` con dominio distinto.

## 12. Cómo continuar una sesión

1. `git status` + `git log --oneline -5` para ver dónde quedó.
2. Leer el widget/controlador más parecido a lo que toca cambiar.
3. Tras cambios: `php -l` en los archivos tocados, `git diff` de revisión.
4. Commitear SOLO cuando el usuario lo pida, mensaje estilo `feat: ...` / `fix: ...` / `docs: ...`.

## 13. BRICK — AI Infrastructure Layer (agosto 2026)

Componente transversal reutilizable por todo el ecosistema (Wontia, TIA System, IA Annotation, Websites, Agents, Automations). La app pregunta "necesito inteligencia para esta tarea"; BRICK decide WHAT→WHICH→WHERE→HOW→FALLBACK→COST→HEALTH.

### Archivo de esquema
- `install/brick_ai.sql` — DDL + seed (providers, modelos, instancias, policies). También ejecutable desde el panel (Setup Tables) o `POST /api/v1/admin/brick/ensure-tables` (autocrea + autoseed si vacío, multi-site con `@site_id`).

### Tablas (todas con site_id = @site_id)
`ai_providers` (adapter, api_base_url, auth_method, api_key_env, badge/color, status) · `ai_models` (model_identifier, context, input/output cost USD por 1M, capabilities JSON, priority, enabled) · `ai_instances` (system_id: wontia, tia, ia_annotation, website, agents, automations) · `ai_policies` (system→module→function, strategy manual|auto|cost|performance|balanced, primary/fallback/fallback2, required_capabilities, monthly_budget + warning/hard %) · `ai_usage` (tokens, cost, latency, status success|error|fallback|budget_blocked) · `ai_health` (healthy|degraded|offline|disabled).

### Capa Core (`src/Core/AiBrick/`)
- `AiRequest`/`AiResponse` — contrato normalizado (mismo formato para TODOS los proveedores).
- `AiProviderAdapter` (interface) + `OpenAiCompatibleAdapter` (OpenAI, DeepSeek, xAI, Mistral, Gemini, OpenRouter, Azure) + `AnthropicAdapter`.
- `AiRouter` — estrategias, cadena de failover (max 3 intentos), control de presupuesto mensual por policy, registro de usage/health en cada intento, `test()`, `healthCheckModel()`, `command()` (capa de comandos para TIA: "use cheapest", "set primary", "show costs"...).
- `AiBrickService` — registro DB, stats, agregaciones, sugerencias permanentes.

### API (`/api/v1/admin/brick/*`, JWT)
`overview` (KPIs + budget + comparativas + suggestions + health + recent) · `providers|models|policies|instances` CRUD · `capabilities` · `usage?range&group` · `test` · `request` (AIRequest normalizado → AIResponse, el endpoint reutilizable por otros sistemas) · `command` · `health/check` · `ensure-tables`.

### API pública del ecosistema (`/api/v1/brick/*`, header `X-Brick-Key`)
- `GET /api/v1/brick/health` — sin key (estado del componente).
- `POST /api/v1/brick/request` — inferencia normalizada para TIA/IA Annotation/Agents/Automations (AIRequest → AIResponse, enruta por policy según system_id/module/function).
- `POST /api/v1/brick/command` — capa de comandos para TIA ("show costs", "use cheapest", "set primary"...).
- Clave configurada en env `BRICK_API_KEY` (Middleware `BrickKeyMiddleware`). Sin configurar → 403; key inválida → 401. Deploy en prod: `-e BRICK_API_KEY=<key>` en `docker run` (4003) + línea en `/tmp/wontia-build/app/.env` para futuros builds. marcasbpo (4004) NO tiene key → 403 (por diseño).

### Seguridad
- API keys NUNCA en DB ni en respuestas: DB guarda solo `api_key_env` (referencia a env var `BRICK_*_API_KEY`); DeepSeek cae a `DEEPSEEK_API_KEY` legacy. Frontend solo ve `has_key: bool`.

### Panel admin (hash `#brick`, "AI BRICK")
Pestañas: Overview (8 KPIs, barra de presupuesto, **Sugerencias permanentes** — alternativas más baratas, errores, presupuesto, fallback, modelos ociosos —, cost by provider/model, system→model→requests→cost, health, recent) · Providers · Models · Policies · Systems · Usage & Cost (ranges Today/7d/30d/All, agrupado por model/provider/system/function) · Test Model (prompt → respuesta, tokens, costo, latencia).

### Pendiente BRICK
1. ~~Poner API key de proveedor en prod~~ — **HECHO 14-ago-2026**: DeepSeek configurado (`BRICK_DEEPSEEK_API_KEY` + `DEEPSEEK_API_KEY` en `.env` del build VPS), verificado en vivo con respuestas reales (tia/orchestration y wontia/general OK, costos registrados).
2. Migrar `AiContentService` para enrutar por BRICK (hoy usa DeepSeek directo con `DEEPSEEK_API_KEY` — ya tiene key en prod; migrar sin romper).
3. Catálogo automático de modelos (model discovery) y cost dashboard por función.
4. Commit de todo el BRICK + deploy docs (pendiente de aprobación del usuario).
