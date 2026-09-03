# WWI — AUDITORÍA DEL CÓDIGO EXISTENTE (v1 — ago 2026)

Auditoría previa a la transformación de WWI en *Autonomous AI Website Factory*.
Matriz: ✅ FUNCIONA · 🟡 PARCIAL · ❌ FALTA · ♻️ REUTILIZAR · 🔧 MEJORAR.

## 1. Arquitectura actual

```
public/  index.php · api.php (REST /api/v1) · admin.php (SPA vanilla JS, hash router) ·
         sitemap.php · robots.php · install.php (bloqueado si .env existe)
src/
  Core/         Config, Database (PDO + @site_id), Request, Response, Router, Session, App,
                BrickSystem, AutoDiscoveryService, BrickHubNotificationService, GitHubSyncService,
                AiBrick/ (AiRequest, AiResponse, AiProviderAdapter, OpenAiCompatibleAdapter,
                          AnthropicAdapter, AiRouter, AiBrickService)
  Middleware/   AuthMiddleware (sesión+JWT, fail-closed) · BrickKeyMiddleware (X-Brick-Key)
  Controllers/Admin/  Auth, Dashboard, Page, Section, Brick, BrickHub, Media, Blog, BlogCategory,
                      BlogTag, Seo, Analytics, Settings, User, AiBrick
  Services/     AiContentService, SeoService, CookieConsentService, AnalyticsService
  Widgets/      22 widgets BRICK + WidgetRegistry (auto-descubrimiento)
  Bricks/       GitHubSync, CodeEmbed (brick.json + clase)
templates/themes/default/index.php   Tema público (design system Wontia)
install/       schema.sql · seed*.sql · seed-wontia-ais.php · brick_ai.sql · brickhub_schema.sql
```

## 2. Stack
- PHP 8.3-FPM + Nginx (Alpine) en Docker, sin frameworks, composer solo PSR-4 (`App\` → `src/`)
- MariaDB 10.11 (`mysql-prod`, red `intsolcom`), multi-tenant por `SITE_ID` → `SET @site_id`
- Admin SPA vanilla JS (hash router, `wontia.panels`, `wontia.api()`)
- Prod: contenedores `wontia-web-intelligence` (4003, SITE 1, wontia.intsolcom.com) y `wontia-marcasbpo` (4004, SITE 2, marcasbpo.com). TLS Let's Encrypt + HSTS.

## 3. Base de datos (tablas existentes)
`sites · users · pages · sections (widget_type+config) · media · settings · blog_posts · blog_categories · blog_tags · blog_post_tags · analytics_views · bricks · brick_sources · brick_updates · ai_providers · ai_models · ai_instances · ai_policies · ai_usage · ai_health`
Todo con `site_id = @site_id` (excepto sections/page_id). Sin UUIDs hoy (INT AI).

## 4. Matriz frente al Master Prompt (120 secciones)

| # | Capa / Sección | Estado | Acción |
|---|---|---|---|
| Auth / seguridad (30) | ✅ | Auth+throttle+sesión hardening+RBAC+uploads+CORS+HSTS. FALTA: CSRF explícito, webhook signature, rate limits por API key, anti-fraud | 🔧 extender |
| Multi-tenancy (29) | 🟡 | `sites` + `@site_id` real. FALTA: tenant=cliente con dashboard propio, billing por tenant | 🔧 extender |
| BRICK widgets (19) | ✅ | 22 widgets + WidgetRegistry + admin config. Catálogo objetivo de 50+ bricks: ❌ | ♻️ base + construir |
| BrickHub/Marketplace (19-21, 61) | 🟡 | BrickHub GitHub-based funciona. FALTA: pricing por brick, estados BETA/COMING_SOON, marketplace de cliente | 🔧 extender |
| BRICK AI Layer (60) | ✅ | Providers/models/policies/router/failover/budget/usage/health + panel + API pública | ♻️ usar como AI Provider Layer |
| TIA (17, 49-52, 88) | 🟡 | Solo capa de comandos de BRICK (`/brick/command`). FALTA: TIA Website Agent (editar sitios), AI memory, brand kit, audit log de acciones TIA | construir |
| AiContentService (23) | 🟡 | Genera artículos/SEO con DeepSeek directo (no vía BRICK) | 🔧 migrar a BRICK |
| Landing/checkout (7-9) | ❌ | La landing actual es corporativa Wontia. FALTA: landing WWI one-page, planes, checkout, domain checker | construir |
| Pricing/Plans (3-4) | ❌ | No hay plans ni pricing engine. REQUISITO: nada hardcodeado + Margin Guard | construir |
| Orders/Payments (31-32, 83-84) | ❌ | No hay orders/payments/subscriptions/invoices. Provider: decidir (Wompi/PayU/Stripe/MercadoPago) | construir |
| Provisioning (33) + Job Queue (34) | ❌ | No hay jobs async ni provisioning. | construir (DB-backed queue) |
| Site Generator (35) + Versiones (36) | ❌ | FALTA: generación, snapshots, rollback | construir |
| Dominios (27, 85) + Email (28, 86) | ❌ | Solo conceptos. FALTA: domain checker/registrar adapter, email manager (no guardar passwords plano) | construir |
| Templates (13-14) | 🟡 | Design system público existe; widgets = bloques. FALTA: template engine + catálogo por sector (100 conceptuales) | construir sobre widgets |
| Brief IA (11) + Import documental (12) | ❌ | FALTA: AI Business Brief, subida PDF/DOC/TXT, extracción, "Información pendiente" (no alucinar — §55) | construir |
| Drag & Drop editor (16) | 🟡 | Admin tiene CRUD de secciones sin DnD visual. FALTA: editor visual con undo/redo/responsive | construir |
| Catálogo productos (5-6, 70-71) | ❌ | FALTA: productos, categorías, variantes, importación CSV, optimización de imágenes | construir |
| SEO (42, 75-76) | 🟡 | SeoService + sitemap + robots + audit. FALTA: AI SEO Engine con score, LocalBusiness, social previews | 🔧 extender |
| Analytics/BI (43-44) | 🟡 | Analytics nativo + GA4. FALTA: BI de TIA, conversion score, pixel Meta | 🔧 extender |
| Redes sociales (22) | ❌ | Solo links. FALTA: Social Hub | construir |
| Idiomas (74) | 🟡 | Contenido mixto ES/EN sin i18n formal. REQUISITO usuario: TODO en ES+EN, precios también | construir |
| Legal (77-78) | 🟡 | Cookies funciona. FALTA: plantillas Privacy/Terms/Data Treatment (sin asesoría legal) | construir |
| Backup/DR (79-80) | ❌ | FALTA: backups diarios, restore, retención | construir |
| Seguridad de sitios (81) + Uptime (82) | ❌ | FALTA: security scanner, uptime monitor | construir |
| Observabilidad (58, 108) | 🟡 | Error log + excepción handler (500 real). FALTA: metrics, job monitoring, system health | 🔧 extender |
| AI cost control (59) | ✅ | BRICK budgets por policy + hard limit. Extender a tenant/límites por plan | 🔧 extender |
| Rate limits (94) + Feature flags (95) + Config engine (96) | 🟡 | Throttle de login. FALTA: rate limits por API key, feature flags, config engine de precios/planes | construir |
| Referrals/Affiliate (64-65) | ❌ | FALTA | construir (Fase 4) |
| Agencies/White label (62-63) | ❌ | FALTA | construir (Fase 4) |
| Duplicate site (72-73) | ❌ | FALTA (solo copia manual de sections) | construir |
| Voz (50) | ❌ | FALTA (arquitectura preparada: comandos TIA) | preparar interfaz |
| QA automático (106-107) | ❌ | FALTA: quality gate antes de publicar | construir |
| Unit economics (92) + Margin Guard | ❌ | FALTA: calculadora de margen por plan (crítico por aumento .com 6.9% nov-2026) | construir |

## 5. Funcionalidades incompletas / bugs / deuda técnica detectados
1. `AiContentService` usa DeepSeek directo → debe enrutar por BRICK.
2. Sin i18n formal (requisito ES/EN en contenido y precios).
3. Sin cola de jobs: toda operación es síncrona (bloquea en generación IA).
4. `sites` no tiene relación con planes/pagos; el "tenant" es solo sitio web, no cliente con dashboard.
5. Admin único para operador y cliente (falta Client Dashboard + RBAC de cliente).
6. Sin snapshots de sitio (riesgo ante ediciones de TIA).
7. Sin registro de acciones de TIA (AI audit log).
8. Tests: no hay suite automatizada (PEDS pide unit/integration/E2E).
9. Imágenes: subida básica; sin WebP/AVIF/thumbnails/alt-text IA.
10. Sin domain/email/order/provisioning tables ni webhooks.

## 6. Riesgos principales
1. **Margen**: costos compuestos (dominio+correo+IA+almacenamiento+procesamiento) sin Margin Guard → planes deficitarios a escala. .com sube 6,9% el 1-nov-2026.
2. **Pagos**: provisioning debe depender SOLO del webhook verificado (firma), nunca del frontend.
3. **TIA**: alucinación de datos factuales (§55) y acciones destructivas (§18) → safety layer + preview + audit log.
4. **Seguridad multi-tenant**: aislamiento estricto por tenant en todos los módulos nuevos.
5. **Async infra**: un solo contenedor hoy; job queue DB-backed como primer paso sin migrar a Redis.

## 7. Duplicaciones detectadas
- Ninguna crítica en backend. La landing corporativa y el sitio son el mismo motor (correcto). BrickHub (bricks de sistema) y WidgetRegistry (widgets de página) son sistemas distintos pero con nombre parecido — documentar, no unificar.

## 8. Dependencias
- Solo internas: composer PSR-4 (sin librerías externas). Adaptadores HTTP propios (curl). Para pagos/dominios/correo se añadirán SDKs/APIs REST (curl) — sin frameworks.

## 9. Plan de integración
Ver `WWI-MASTER-IMPLEMENTATION-PLAN.md` (fases 0-5, mapeo a módulos existentes y decisiones críticas).
