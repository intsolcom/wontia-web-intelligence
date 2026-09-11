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
| `32a409b` | **BRICK — AI Provider & Model Management**: capa de IA del ecosistema (ver §13) + rebrand "WWI" en admin |

**Sin commitear:** nada pendiente en este momento.

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

- VPS `root@<VPS_IP>`, SSH key `~/.ssh/contabo_vps`, contenedor `wontia-web-intelligence` (puerto `4003`, red `intsolcom`), DB en contenedor `mysql-prod`.
- Flujo: `scp` archivos a `/tmp/wontia-build/app/...` → `docker build -t wontia-web-intelligence:latest .` → `docker rm -f` + `docker run -d ... -v /var/lib/dokploy/uploads/wontia:/app/public/assets/uploads`.
- SQL siempre vía archivo: `docker exec -i mysql-prod mysql -uwontia -p<DB_APP_PASS_EN_VPS> wontia < archivo.sql` (usar el usuario de la app; el pass de root de mysql-prod fue cambiado en ago-2026 y ya no es el documentado).
- PowerShell 5.1: **NO usar `&&`** — usar `; if ($?) { ... }`.

## 10. Convenciones de código

- PHP: namespace `App\...`, PDO, sin dependencias externas (composer solo para autoload básico).
- **No añadir comentarios** al código salvo que se pidan.
- Escapar salida de usuario con `$this->esc()`; config JSON siempre con `$this->safeJson()`.
- Admin SPA: paneles en `wontia.panels`, llamadas con `wontia.api()` (auto-adjunta JWT).
- Verificar con `php -l` cada archivo tocado antes de cerrar.
- **MONITOR DE CÓDIGO (obligatorio, sep 2026)**: ningún cambio se cierra sin pasar: (1) `php -l`/`node --check`; (2) seguridad — PDO prepared siempre (jamás `->execute()->fetch()`, usar `prepare→execute→fetch`), escape de salida, sin secretos en código, `@site_id` en toda query nueva; (3) idempotencia (DDL `IF NOT EXISTS`, seeds `INSERT IGNORE`); (4) estados UX loading/empty/error; (5) prueba en vivo del endpoint/flujo; (6) sin código muerto/duplicado. Ver `WWI-CMS-MASTER-PROMPT.md` §4.

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

## 12b. Seguridad — controles aplicados (ago 2026)

- **Sesiones**: cookie `HttpOnly + SameSite=Lax + Secure`, `use_strict_mode`, ID regenerado en login (`Session.php`).
- **Login**: throttle por IP+usuario (5 fallos → bloqueo 15 min, HTTP 429) + delay anti-brute-force (`AuthController`). Mensaje genérico siempre.
- **JWT**: fail-closed si `JWT_SECRET` falta o es placeholder. **Rotado en prod** (agregado al `.env` del build, última línea manda). No exponer el valor.
- **Uploads**: whitelist de extensiones + validación de contenido real con `getimagesize` + **SVG bloqueado** (era XSS). `site_id = @site_id` corregido. Ejecución de PHP en `/assets/uploads/` denegada en nginx.
- **Users**: un usuario NO-superadmin ya no puede escalarse cambiando su propio `role`; roles restringidos a `superadmin|admin|editor`.
- **install.php**: devuelve 403 si ya existe `.env` (no se puede re-ejecutar la instalación en prod).
- **CORS**: `*` solo en endpoints públicos; `/api/v1/admin/*` y `/api/v1/brick/*` restringen origen a `APP_URL`. API y admin envían `Cache-Control: no-store`.
- **Headers nginx**: X-Frame-Options DENY, nosniff, Referrer-Policy, Permissions-Policy + **HSTS en host nginx** (`wontia-cms.conf`). Error handler devuelve 500 real (antes devolvía 200).
- **Credenciales redactadas** de AGENTS.md / MASTER-PROMPT.md / IMPLEMENTATION-PROMPT.md (estaban con passwords reales commiteadas). Valores reales viven solo en el VPS.
- **INFRA (crítico)**: si se recrea `mysql-prod` hay que reconectarlo a la red: `docker network connect intsolcom mysql-prod` — si no, todas las apps fallan con `db:false`.
- **WWI FACTORY (ago 2026)**: iniciada la transformación en "Autonomous AI Website Factory". Ver `WWI-AUDIT.md` (auditoría + matriz) y `WWI-MASTER-IMPLEMENTATION-PLAN.md` (fases). Fase 0 hecha: schema `install/wwi_factory.sql` (planes/órdenes/pagos/provisioning/dominios/email/plantillas/ai_actions/audit/versions) + `FactoryService`/`FactoryController` + panel admin "Factory" (Plans/Config/Margin Guard) + API pública `/api/v1/public/plans`, `/plans/{slug}`, `/domain/check`. Margin Guard activo: Web Starter sembrado da margen 13.6% (CRITICAL <25%) — revisar precios/costos en el panel.
- **WWI FACTORY — dominio propio**: tenant dedicado en contenedor `wontia-wwi` (puerto 4009, **`SITE_ID=5`** — el 3 es de iannma, ¡no colisionar!, `APP_URL=https://wwi.wontia.com`, BRICK_API_KEY propia). Vhost host nginx `/etc/nginx/sites-enabled/wwi.conf` (80→301, 443 con cert Let's Encrypt propio + HSTS → proxy 4009). DNS en Hostinger: `wwi.wontia.com A 169.58.12.55`. El operador de la Factory usa `https://wwi.wontia.com/admin.php#factory`. Cert renovable con el cron de certbot estándar.
- **WWI FACTORY — Portal de operación (Fase 0.5)**: panel Factory con sub-nav Inicio (KPIs: sitios/dominios/emails/pedidos/ingresos/IA/jobs/clientes) · Sitios (lifecycle DRAFT→PUBLISHED) · Dominios (SEARCH→EXPIRED) · Emails · Pedidos (CREATED→PAID→... auto-acredita saldo al pagar) · Saldos (wwi_balance_ledger) · Consumo IA por cliente · Planes/Config/Margin. Panel **#portal** para rol `client` (mi sitio, plan, dominios, correos, pedidos, saldo, consumo IA del mes). Vistas de operador = endpoints superadmin sin filtro `@site_id`; vistas de cliente = `@site_id` propio.
- **WWI FACTORY — Checkout (Fase 1 parcial)**: widget `wwi-checkout` en la landing (form → `POST /api/v1/public/orders` crea pedido PENDING_PAYMENT; `GET /api/v1/public/orders/{uuid}` estado). Webhook `POST /api/v1/public/payments/webhook` con **firma Wompi** (SHA256 reference|cents|currency|integrity_key; config `wwi.payment_provider` + `wwi.wompi_integrity_key` en Factory→Config). Sin provider configurado → 503 fail-closed. Flujo E2E verificado: firma válida → payment signature_verified → order PAID → saldo acreditado (si tenant) → job `provision_site` encolado en `wwi_jobs`.
- **WWI FACTORY — Pasarela DUMMY (activa)**: `wwi.payment_provider=dummy` → el checkout muestra "Pagar (modo demo)" → `POST /api/v1/public/payments/dummy/{uuid}` simula pago verificado (payment 'dummy', simulated:true) y encola provisioning. Si luego se pone `wompi`, el endpoint dummy se auto-desactiva (503).
- **WWI FACTORY — Provisioning Worker (Fase 1, HECHO)**: `FactoryService::provisionSite()` crea tenant (sites), usuario cliente, dominio (DNS_PENDING), 3 buzones (contacto/info/ventas), página home + 4 secciones, saldo inicial y orden→READY. Idempotente (si ya tiene tenant_id, no repite). `runDueJobs()` procesa `wwi_jobs` con retries (max 3, +5min). Disparadores: panel Factory→Jobs→"Run Now" (superadmin) + **cron del VPS**: `* * * * * docker exec wontia-wwi php /app/public/job-runner.php`. E2E verificado: pedido→pago dummy→tenant "Panaderia Demo" READY con usuario/dominio/correos/página/saldo.
- **TIA WEBSITE AGENT (Fase 2, HECHO)**: `TiaAgentService` interpreta comandos en español vía BRICK (system_id wontia/module agent/function command, JSON estricto) y ejecuta acciones REALES sobre el tenant (`@site_id`): set_color (settings wwi_brand_primary → override CSS en theme default), add_section (testimonios/servicios/contacto/precios/faq → widgets), remove_section (**Action Preview + token HMAC + confirmación**), hide/show_section, update_text, create_page, status. AI Audit Log en `wwi_ai_actions` (command, action, payload, status executed/preview/failed). UI: chat de TIA en el panel #portal (sections + history). Endpoints `/api/v1/admin/tia/command|confirm|sections|history` (cualquier rol autenticado, opera su tenant).
- **AI BUSINESS BRIEF (Fase 2, HECHO)**: `POST /api/v1/public/briefs` (nombre/email/historia ≥30 chars) → job `process_brief` → TIA vía BRICK extrae perfil estructurado JSON (historia, propuesta_valor, servicios, productos, clientes, ubicacion, contacto, horarios, redes, diferenciadores, **pendientes** — nunca inventa datos). Tabla `wwi_briefs` + UI en #portal. Verificado: brief de Panadería procesado con pendientes correctos.
- **Emails transaccionales (estructura)**: `EmailService` (SMTP SSL 465 con AUTH LOGIN vía stream, sin librerías; si MAIL_* no configurado → job `send_email` termina como skipped). `provisionSite` encola email de bienvenida al terminar el tenant.
- **Tenant demo**: contenedor `wontia-demo6` (puerto 4010, SITE_ID=6, "Panaderia Demo", APP_URL=https://panaderiademo.wontia.com, BRICK key propia en `brick_key_site6.txt`). Vhost host nginx `/etc/nginx/sites-enabled/panaderiademo.conf` (80 listo; falta DNS `panaderiademo.wontia.com → <VPS_IP>` + cert para activar 443).
- **WWI FLOW — Slide Wizard (Fase 2, HECHO)**: ver `WWI-FLOW-MASTER-PROMPT.md` (spec + 30 innovaciones). Flujo guiado en la landing: Hero CTA "Empezar" → slide chat con TIA (prompt del sitio) → preview efímero (tabla `wwi_previews`, TTL 1h + limpieza en job-runner, render en `GET /api/v1/public/preview/{uuid}` con banner "VISTA PREVIA TEMPORAL") → botones Editar prompt / Descartar → **límite 2 prompts por IP/día** (`wwi_prompt_attempts`, 429 → catálogo de plantillas) → planes (máx 3, sin web-master) → **venta cruzada Web Master** (addons en `wwi_orders.addons` JSON) → chat de dominio (checker RDAP + `suggest-domains` por TIA) → pedido + pago dummy. E2E verificado: preview de arquitecto generado con placeholders (sin alucinar), límite 429 OK, sugerencias de dominio OK.
- **Preview RICO (sep 2026)**: el generador sigue el spec `WWI-UNIVERSAL-WEBSITE-MASTER-PROMPT.md` — estructura FIJA de plantilla básica (header·hero·valor·servicios·about·beneficios·testimonios·CTA·contacto·footer) con contrato JSON estricto (colors primary/secondary, placeholders {{CAMPO}}, copy conversión). El render del preview usa design system profesional con tokens del sector. X-Frame-Options = SAMEORIGIN (el modal del preview es iframe). **Permisos de prompts**: límite 2/día SOLO para usuarios finales anónimos; **constructor/admin/editor con sesión = prompts ilimitados** (`isBuilder()` en FactoryService, el wizard muestra "Modo constructor: prompts ilimitados").
- **Wizard UX/UI (sep 2026)**: ver `WWI-FLOW-UX-MASTER-PROMPT.md` (roles + 20 mejoras CTA). Implementado en el tema wwi: chips de prompt sugerido, avatar de TIA + typing dots, labels de paso + dots de progreso, barra de confianza (gratis/sin tarjeta/minutos), botón con micro-pulso, device switcher en el modal de preview (Desktop/Tablet/Mobile), dictado por voz (Web Speech API, es-CO), focus-visible + reduced-motion (WCAG 2.2 AA). **Previews de plantilla** (`POST /api/v1/public/previews/from-template`, no consume intentos) renderizan el preset con el design system rico. **Credenciales de cliente**: Factory → Sitios → botón "Creds" regenera y muestra una sola vez usuario/contraseña del cliente (`POST /api/v1/admin/factory/sites/{id}/credentials`, superadmin).
- **Backups**: script `/root/wwi-backup.sh` (mysqldump gz diario) + cron host `30 2 * * *` + retención 7 días en `/var/lib/dokploy/backups/wontia`. Verificado (dump 70KB creado).
- **AUTO-DEPLOYER (Fase 2, HECHO)**: wildcard DNS `*.wontia.com → <VPS_IP>` en Hostinger (un solo registro cubre TODOS los subdominios). `provisionSite` escribe una solicitud firmada (HMAC canónico por campos con JWT_SECRET, `deploy/wwi-deploy.py` la verifica) en `/var/lib/dokploy/wontia-deploy/` (volume montado en wontia-wwi como `/app/deploy-queue`, 777). El agente host (`/root/wwi-deploy.py`, cron `* * * * *`) crea el contenedor (puerto auto desde 4011), vhost nginx auto-<dominio>.conf, cert Let's Encrypt por subdominio (HTTP-01 vía wildcard), y marca site PUBLISHED + dominio ACTIVE. BRICK key por tenant en settings `wwi_brick_key`. **E2E verificado: pedido → pago dummy → provision → deploy → https://cafeteria-quindio.wontia.com VIVO desde internet, cero intervención humana.** Dominios propios del cliente: el cliente apunta su A record (instrucciones de TIA); automatización total requiere API de registrador.
- **GIT SELF-UPDATE (sep 2026)**: `POST /api/v1/admin/system/update` (superadmin, panel Factory → System) escribe solicitud firmada (HMAC `system_update|ts`) en la cola; el agente host `/root/wwi-update.py` (cron `* * * * *`, `deploy/wwi-update.py`) hace git clone del repo público → rsync (preserva .env/vendor/cache/uploads) → docker build → recrea TODOS los contenedores `wontia-web-intelligence` capturando su config vía docker inspect → health-check de cada uno → **rollback automático** a `:previous` si falla. E2E verificado 2 veces (6 contenedores, ~20s). El deploy agent ignora `update-*.json` y viceversa.
- **CMS — AUDITORÍA 13 MENÚS (sep 2026)**: ver `WWI-CMS-MASTER-PROMPT.md`. Fallas encontradas y corregidas: (1) BrickHub 5 endpoints con "table not found" → schema splitter roto por comentarios SQL en `AutoDiscoveryService::ensureBrickHubTables` (ahora limpia comentarios antes de split; tablas creadas vía `POST /brickhub/ensure-tables`); (2) `->execute()->fetchColumn()` inválido en Media/Blog/Analytics (patrón corregido a `prepare→execute→fetch`); (3) GA4 site_id hardcodeado; (4) Users vacío para superadmin (ahora muestra todos los sitios); (5) `BrickHubNotificationService` con `site_id=1` hardcodeado → `@site_id`. **Todos los 13 menús verificados OK.**
- **OJO credenciales (sep 2026)**: el admin cambió su contraseña (admin/admin ya NO funciona) — la nueva está en el archivo local del operador `C:\Users\sergi\AppData\Local\Temp\opencode\admin_pass.txt`. No exponerla.
- **WWI LANDING (ago 2026)**: tema por sitio vía `sites.theme` (index.php/App.php leen `theme` de la tabla). Tema `templates/themes/wwi/index.php` (design system dark cian→violeta, referencia Hostinger sin copia) + 8 widgets `Wwi*Widget` (hero con domain checker, planes desde `/api/v1/public/plans`, benefits, steps, templates desde `/api/v1/public/templates`, faq, cta, footer). Contenido 100% en DB del tenant 5: página `home` (seed `install/wwi_landing_seed.sql`), editable en admin **Pages → home → Sections**. Los precios NO están en la landing: vienen del Pricing Engine.
- **Domain Checker REAL (RDAP)**: `DomainCheckerService` consulta RDAP (Verisign .com/.net, IANA bootstrap para otros TLD, caché 5 min, ERROR 60s con backoff) — sin API key, sin inventar. Estados: AVAILABLE/TAKEN (con registrar + expiración reales)/INVALID/CHECKING/ERROR. OJO: Verisign devuelve 404 + EOF de TLS en dominios libres → el código mira el HTTP code antes que curl_error. **.co/.com.co → CHECKING**: el RDAP de GoDaddy miente (404 hasta en registrados) y el WHOIS bloquea datacenters — se confirman al registrar; integrar API de registrador (Porkbun/Cloudflare) para hacerlos autoritativos.
- **Domain Cost Engine**: `wwi.domain_costs` (settings JSON por TLD: reg/ren USD) — valores editables en Factory → Config; la landing muestra precio/año de cada sugerencia de dominio (com $10.97, net $12.98, org $12.50, co $28, com.co $25, site $2.50, info $15). Verificar contra el registrador real (Porkbun/Cloudflare) antes de fijar márgenes — Margin Guard ya usa `wwi.domain_cost_usd`.
- **Config env-priority (ago 2026)**: `Config::get` ahora respeta el entorno real del contenedor (`-e`) por encima del `.env` del archivo (12-factor). Permite APP_URL/BRICK_API_KEY por tenant. BRICK_API_KEY del site 1 rotada (la anterior quedó expuesta en chat); JWT_SECRET rotado (128 chars).
- **Tenants activos**: 4003 site1 (wontia.com/intsolcom.com) · 4004 site2 (marcasbpo.com) · 4006 catastro (wontia-catastro-core) · 4008 iannma (IA Annotation) · 4009 site3 (WWI Factory). Todos comparten la misma imagen.
- **Pendiente usuario**: cambiar la contraseña `admin/admin` (aún activa).

## 13. BRICK — AI Infrastructure Layer (agosto 2026)

Componente transversal reutilizable por todo el ecosistema (Wontia, TIA System, IA Annotation, Websites, Agents, Automations). La app pregunta "necesito inteligencia para esta tarea"; BRICK decide WHAT→WHICH→WHERE→HOW→FALLBACK→COST→HEALTH.

### Archivo de esquema
- `install/brick_ai.sql` — DDL + seed (providers, modelos, instancias, policies). También ejecutable desde el panel (Setup Tables) o `POST /api/v1/admin/brick/ensure-tables` (autocrea + autoseed si vacío, multi-site con `@site_id`).
- **Auto-provision por sitio**: `AiBrickService::provision()` siembra automáticamente al primer uso si el site no tiene proveedores (se llama desde providers/models/policies/instances/overview/findPolicy). Un tenant nuevo (SITE_ID) queda con BRICK disponible sin pasos manuales. El wizard `install.php` también ejecuta `brick_ai.sql` en instalaciones nuevas.
- **Concepto para IA**: `BRICK-MASTER-PROMPT.md` — documento completo (en español) para pegar en DeepSeek/TIA: qué es BRICK, contrato AIRequest/AIResponse con ejemplos, 3 modos de conexión de un sitio nuevo (multi-site auto / HTTP X-Brick-Key / copiar `src/Core/AiBrick`), policies, comandos TIA, seguridad y checklist de despliegue. `WWI-INTEGRATION-PROMPT.md` — prompt de integración para apps externas (todas las rutas + credenciales como referencias a archivos).

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
4. ~~Commit de todo el BRICK~~ — **HECHO**: commit `32a409b`. Falta `git push origin main` (confirmar con usuario).
