# Puente de agente — Wontia Medical
> Lee `AGENT-BRIDGE.md` antes de actuar. Este archivo es SOLO para Wontia Medical.

- **Sitio:** Wontia Medical  ·  **SITE_ID:** 14  ·  **Dominio:** medical.wontia.com
- **Agente:** `wontia-medical-agent`  ·  **Rol:** contenido (vía CMS) + app externa (Node, solo por API)
- **Plataforma (estructura CMS):** Head Team Leader WWI

## Arquitectura (acordada)
- **WWI es la base.** Wontia Medical es un **tenant + plantilla** (`templates/themes/wontia-medical/`) con sus módulos como **bricks confinados al tema** (no en el core).
- **Rutas:** `medical.wontia.com/` y `/api/` → **WWI**; `medical.wontia.com/med/` → **App Wontia Medical (Node)**; `/healthz` y `/readyz` → Node.
- **TIA → BRICK** (`system_id: wontia_medical`); nunca un LLM propio. Guardarraíles clínicos antes del proveedor.
- **SSO:** WWI emite JWT (HS256); el tema inyecta `window.WWISSO_TOKEN` si hay sesión; `medical-app.js` intercambia en `POST /med/api/v1/auth/sso`.
- **Contenido** (marca, textos, hero, contacto) editable por el operador; los bricks no deben hardcodear copy sensible.

## Bitácora de conversación

## [ACLARACIÓN] — De: Head Team Leader → Para: wontia-medical-agent
- Fecha (UTC): 2026-10-15
- Estado: abierto
- Asunto: Reglas del sitio
- Instrucción: Tu alcance es **contenido** (textos, imágenes, SEO) operado desde el admin de WWI, y la **app Node** por API. No edites `src/**`, `public/{api,admin}.php`, `templates/themes/_shared/**`, `install/**`. Si necesitas una capacidad de plataforma, abre una `SOLICITUD` aquí.

## [RESULTADO] — De: wontia-medical-agent → Para: Head Team Leader
- Fecha (UTC): 2026-10-15
- Estado: resuelto
- Asunto: Integración de Wontia Medical en WWI (tema + bricks + SSO + TIA/BRICK)
- Contexto: WWI se usa como base; la app Wontia Medical (Node/TS) queda como backend API.
- Acción:
  1. Plantilla `wontia-medical` (theme.json, index.php, lib.php, router.php) + bricks: `hero`, `tia`, `agenda`, `teleconsulta`, `expediente`, `tienda`, `suscripciones`, `perfil`, `module`.
  2. Assets en `public/assets/themes/wontia-medical/` (`medical.css`, `medical-app.js`).
  3. Tenant `SITE_ID=14` (tema `wontia-medical`); contenedor `wontia-medical-wwi` (127.0.0.1:4017).
  4. nginx `medical.wontia.com`: `/` y `/api/` → WWI; `/med/` → app; `/healthz`,`/readyz` → app.
  5. SSO: JWT de WWI + intercambio en la app; TIA vía BRICK con seguridad clínica.
- Archivos implicados: `templates/themes/wontia-medical/**`, `public/assets/themes/wontia-medical/**` (core WWI **sin cambios**); app Node en repo aparte.
- Criterios de aceptación: `/` (WWI) 200; `/tia|/agenda|/teleconsulta|/expediente|/tienda|/suscripciones|/perfil` 200; `/api/v1/brick/health` 200; `/med/api/v1/meta` 200; SSO 200; TIA normal `provider=brick` y alarma `provider=guardrail`.
