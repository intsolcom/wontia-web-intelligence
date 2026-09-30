# WWI BRICK LIFECYCLE — Incubadora → Marketplace (spec)

Objetivo: separar **bricks terminados** (Marketplace) de **bricks en desarrollo** (Incubadora),
con **cuenta regresiva** pública y **lanzamiento** manual (admin) o automático (fecha programada).

## Modelo (PEDS — Inspect → Propose → Deliver → Verify)

- **Marketplace** = solo bricks con `state = launched`. Etiqueta **NUEVO** (neón) durante `NEW_DAYS` (30) tras `launched_at`.
- **Incubadora** = bricks con `state = incubator`, con **cuenta regresiva** (años/meses/semanas/días/horas/min/seg) hasta `target_launch_at`.
- **Bricks Acoplados** = bricks instalados (repos/incubadora) + bricks Core **en uso** en páginas del sitio.
- La decisión de lanzar es **global** (el código de un widget es el mismo para todos los tenants) → tabla sin `site_id`.

## Datos

`install/brick_lifecycle.sql` (idempotente):
- `brick_lifecycle(slug UNIQUE, brick_type, state ENUM('incubator','launched'), maturity, hidden, target_launch_at, launched_at, launched_by, release_notes, interest)`.
- `brick_lifecycle_events(slug, event, from_state, to_state, actor, reason, meta)` → auditoría.

Backfill por defecto: un brick Core **sin registro** cuyo `render()` no produce contenido (`functional=false`) se trata como `incubator`; el resto como `launched`.

## Servicio

`App\Services\BrickLifecycleService`:
`ensureTables · all · get · resolve · assignType · launch · schedule · incubate · setMaturity · setHidden · addInterest · countdown · readiness · events · recentEvents · processDueLaunches`.

- `launch()` exige **notas de versión**; bloquea si `readiness.score < 40` salvo `force:true`. Idempotente.
- `processDueLaunches()` lanza lo vencido con `actor='system'` → invocado por `public/job-runner.php`.

## API (admin, JWT; escritura solo `superadmin|admin`)

`GET  /api/v1/admin/bricklifecycle` · `{slug}/readiness` · `{slug}/events`
`POST /api/v1/admin/bricklifecycle/{slug}/launch|schedule|incubate|maturity|hide|interest` · `ensure-tables` · `run-due`

Los ítems de `/api/v1/admin/bricks` (Core) y `/api/v1/admin/brickhub` (repos) incluyen `lifecycle` + `readiness`.

## UI (admin SPA `#bricks`)

- **Marketplace**: cabecera + insight; excluye incubando y ocultos; badge **NUEVO**; acciones admin (↩ Reinculbar, 🙈 Ocultar); toggle *Ver ocultos*.
- **Incubadora**: cabecera + **Roadmap** (orden por fecha) + **Actividad reciente** + **barra de lote**; tarjetas con **countdown en vivo**, chip de **madurez**, 🔔 interés; admin ve **🚀 Lanzar**, **🗓 Programar**, **🙈/👁**; por categoría: checkbox + **⚙ Config** + **🙈 Ocultar**.
- **Bricks Acoplados**: bricks instalados + Core en uso (no incubando).

## Innovaciones incluidas

1. Etiqueta **NUEVO** neón con caducidad configurable (30 días).
2. **Cuenta regresiva** en vivo (y/mo/sem/d/h/min/seg), tz-aware, `aria-live=off`, respeta reduced-motion.
3. **Launch Readiness** (score 0–100 con checks: render/campos/meta/preview/uso) + *gate*.
4. **Programar lanzamiento** con presets (+1 mes/+3/+1 año).
5. **Roadmap** por fecha.
6. **Audit trail** de eventos.
7. **Reinculbar** (rollback).
8. **Auto-lanzamiento** por fecha (cron `job-runner.php`).
9. **Actividad reciente** visible en la incubadora.
10. **Interés/demanda** (🔔 Avísame + contador `interest`).
11. **Release notes** obligatorias al lanzar.
12. **Semáforo de madurez** (alpha/beta/rc/stable).
13. **Gating admin** server-side + UI.
14. **Bulk/multi-selección** y **por categoría** (checkbox + config + ocultar).
15. **Ocultar/mostrar** bricks del catálogo.

## Verificación

- `php -l` en todos los archivos PHP; `node --check` en `admin.js`.
- Pruebas de servicio en contenedor: ensureTables, readiness(`hero`)=85, launch→`is_new`, schedule→countdown, interest, hide, events, `processDueLaunches()` → `launched:['…']` con `actor='system'`, cleanup.
- Rutas: `GET/POST /api/v1/admin/bricklifecycle*` → `401` sin auth (existen).
