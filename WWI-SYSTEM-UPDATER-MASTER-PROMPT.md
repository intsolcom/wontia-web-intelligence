# WWI SYSTEM UPDATER — Settings → Update (spec)

Objetivo: que cualquier instancia WWI pueda **consultar al servidor central** y **actualizar su sistema** desde la UI, sin SSH.

## Modelo
- **Servidor central** = instancia que monta la cola de despliegue (`/app/deploy-queue`), hoy `wwi.wontia.com` (`SITE_ID=5`).
- El central escribe una **solicitud firmada** y el agente host `/root/wwi-update.py` clona GitHub → build → recrea **todos** los contenedores WWI → health-check (rollback automático si falla).
- Los **tenants** (sin cola) **solicitan** al central por API firmada con un **secreto compartido**.

## API
Público (manifest/estado para comparar y para tenants):
- `GET  /api/v1/public/system/manifest` → versión, build, canal, notas, `update_endpoint`.
- `GET  /api/v1/public/system/status` → progreso del agente (`pct`, `eta_s`, contenedores…).
- `GET  /api/v1/public/system/history` → últimas actualizaciones.
- `POST /api/v1/public/system/update-request` → verifica `HMAC('system_update|ts', wwi.update_secret)` y **encola** en el central.

Admin (JWT; aplicar/configurar solo `superadmin|admin`):
- `GET  /api/v1/admin/system/update/overview`
- `POST /api/v1/admin/system/update/check`
- `POST /api/v1/admin/system/update/apply`
- `GET  /api/v1/admin/system/update/status` · `/history`
- `POST /api/v1/admin/system/update/settings` · `/secret`

## Servicio
`App\Services\SystemUpdateService`: `isCentral · version (VERSION) · localInfo · manifest · check · plan · requestUpdate · verifyCentralRequest · status · history · inWindow · autoTick · fetchCentralManifest/Status · repoLatest · secret/generateSecret` + settings `wwi.update_channel|central_url|repo|branch|auto_update|window|secret|release_notes`.

## UI (Ajustes → Update)
Tarjetas de versión local/SITE_ID/PHP/secreto · **Buscar actualizaciones** (local vs central vs GitHub) · **dry-run/plan** · changelog (release notes) · **backup antes de actualizar** · **Actualizar ahora** · **progreso en vivo** (%, ETA, contenedores) · **historial** · configuración (canal, central, repo, ventana, auto-update) · **generar secreto** para tenants.

## Innovaciones (15)
1. Manifiesto central. 2. Canales stable/beta. 3. Comparación triple (local/central/GitHub, con fallback al `VERSION` del repo). 4. Progreso en vivo con ETA. 5. Historial/auditoría. 6. Changelog. 7. Auto-check. 8. Auto-update + ventana. 9. Firma HMAC tenant→central. 10. Secreto gestionable. 11. Detección offline + fallback. 12. Dry-run/plan. 13. Archivo `VERSION`. 14. Backup flag. 15. "Actualiza todos los sitios" (central).

## Verificación
- `php -l` + `node --check`.
- Central (`wontia-wwi`): `is_central=true`, `version=3.3.0`, `manifest` OK, `status` lee `system-status.json` (pct 100, commit del último update), `history` 12, HMAC self-check `true`/bad `false`.
- Tras deploy: tenant `check()` obtiene el manifiesto del central; con el **mismo** `wwi.update_secret` el central acepta `update-request`.
