# WWI MASTER IMPLEMENTATION PLAN — Autonomous AI Website Factory

Plan maestro para transformar WWI en la fábrica autónoma de sitios web (master prompt de 120 secciones). Reglas: PEDS, no duplicar, no romper, seguridad > datos > pagos > provisioning > generación > TIA > UX.

## Principios rectores
- **Reutilizar**: BRICK AI Layer = AI Provider Layer (§60). WidgetRegistry = motor de bloques del Template Engine. `@site_id` = aislamiento de tenants. BrickHub = Marketplace.
- **Nada hardcodeado**: Pricing Engine + Domain Cost Engine + Margin Guard editables desde admin (§96).
- **Webhook = única fuente de verdad del pago** (§10). Provisioning: async, idempotente, retryable, observable (§33-34).
- **TIA ejecuta, no solo responde** (§17), con Safety Layer (§18) y AI Audit Log (§88). No alucinar (§55).
- **i18n ES/EN en todo el contenido y precios** (requisito del usuario).

## Decisiones críticas (documentadas; opción segura por defecto)
| Decisión | Default elegido | Alternativas |
|---|---|---|
| Gateway de pagos | **Wompi** (CO, API REST, webhooks firmados) | PayU, Stripe, MercadoPago |
| Registrador de dominios | Adapter placeholder + verificación real vía API (Porkbun/Cloudflare) — sin inventar disponibilidad | |
| Correo | Adapter de gestión (API del proveedor de hosting) — nunca passwords en texto plano | |
| Cola de jobs | **DB-backed queue** (sin Redis por ahora) | Redis cuando escale |
| Dominio WWI | wwi.wontia.com (marca del proyecto) — mismo contenedor nuevo o tenant nuevo | |
| UUIDs | Mantener INT AI + agregar `uuid` CHAR(36) para entidades públicas (orders) | migración total a UUID |

## FASE 0 — FUNDACIÓN (schema + config engine + i18n) ← ARRANQUE INMEDIATO
1. `install/wwi_factory.sql`: plans, orders, order_items, payments, subscriptions, invoices, provisioning_jobs, domains (lifecycle), email_accounts (lifecycle), templates, template_categories, sites extra (tenant_id, plan_id, lifecycle), ai_actions (audit TIA), audit_logs, jobs (queue), backups.
2. **Configuration Engine**: tablas `config` por tenant (precios, límites, features, costos internos) + `SettingsController` extendido + panel admin.
3. **Margin Guard**: calculadora de costos por plan (dominio+correo+IA+storage+payment fee) con alerta si margen < mínimo.
4. **i18n**: helper `Lang::get('key','es|en')` + archivos de idioma; precios con divisa dual COP/USD.

## FASE 1 — VENTA Y PROVISIONING
5. Landing one-page WWI (rápida: LCP<1.5s, PageSpeed 95+) + domain checker (estados reales).
6. Checkout → Wompi → webhook firmado → Order Engine (estados CREATED→PAID→PROVISIONING→READY).
7. **Provisioning Engine** sobre Job Queue: Tenant → User → Site → Template → Content (TIA vía BRICK) → Assets → SEO → Domain → SSL → Email → Analytics → Publish. Idempotente + retry.
8. Emails transaccionales (pago recibido, generando, listo, factura, renovación, recuperación).
9. Client Dashboard (separado del admin operador) + RBAC de cliente.

## FASE 2 — GENERACIÓN CON IA
10. **AI Business Brief**: conversacional + subida PDF/DOCX/TXT/imágenes/URL → TIA clasifica y estructura; faltantes → "Información pendiente".
11. **Template Engine**: catálogo por sectores (100 conceptuales) = design system + presets de widgets; agregar plantillas sin tocar core.
12. **TIA Website Agent**: comandos reales ("cambia el color", "agrega testimonios", "crea página de contacto") → interpretar→planificar→ejecutar→validar, con Action Preview destructiva y AI Audit Log.
13. **Site Generator** + snapshots/rollback (v1/v2/v3).
14. Migrar `AiContentService` → BRICK; AI SEO Engine (score), calidad QA gate previo a publicar.

## FASE 3 — COMERCIO Y ECOSISTEMA
15. Catálogo de productos (variantes, imágenes optimizadas WebP/AVIF/thumbnails/alt IA), import CSV/JSON.
16. Brick Marketplace con pricing (FREE/PAID/SUBSCRIPTION/COMING_SOON), Social Hub, Analytics BI, Local SEO CO (LocalBusiness schema).

## FASE 4 — ESCALA
17. Agency/White label, Referrals, Affiliate, Duplicate Site, voz (interfaz lista), security scanner + uptime + backups diarios + restore.

## FASE 5 — AUTONOMÍA TOTAL
18. AI Website Doctor, Conversion/Performance/Accessibility engines, AI Business Intelligence (MRR/CAC/churn/unit economics), TIA Admin.

## Criterios de terminado (resumen §118)
Comprar → verificar pago → tenant → brief → plantilla → TIA genera → QA gate → dominio+SSL+correo → publicado → cliente edita → TIA modifica → bricks → billing → analytics → backups → logs → móvil → performance. Todo sin soporte humano.

## Cómo se ejecuta
1. Cada fase: schema → backend → frontend → IA → integraciones → testing → seguridad → performance → deploy → commit lógico (regla §114).
2. Revisar `WWI-AUDIT.md` antes de tocar cada módulo para reutilizar.
3. Commits: `feat(wwi): ...` — solo cuando el usuario lo pida o al cerrar fase.
