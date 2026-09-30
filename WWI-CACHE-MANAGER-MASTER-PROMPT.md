# WWI — CACHE MANAGER MASTER PROMPT

> **Versión:** 1.0 · **Fecha:** septiembre 2026 · **Modo:** PEDS (Inspeccionar → Proponer → Entregar → Verificar)
> **Requerimiento:** añadir en **Settings** una pestaña **“Administración de caché”** con herramientas para **purgar caché**, **eliminar caché y archivos temporales**, con el fin de **forzar la carga de contenido nuevo** (incluidos **archivos multimedia** actualizados) en el sitio. **Disponible en todos los sitios WWI** existentes.

## 1. INSPECCIÓN (estado actual)
- No existe administración de caché en el panel.
- Los assets se sirven con `?v=filemtime` (builder.js/live-editor) y nginx aplica `Cache-Control: immutable` (30 días) a `/assets/*` → un archivo reemplazado con el mismo nombre puede quedar cacheado.
- PHP con **opcache** (recomendado `opcache_reset()` tras cambios de código).
- Carpetas potenciales de caché/temporales: `ROOT_DIR/cache`, `ROOT_DIR/storage/cache`, `ROOT_DIR/tmp` (si existen).
- Settings por `@site_id` (clave/valor) disponibles para estado (`cache_last_purge`, `asset_version`).

## 2. ARQUITECTURA
- **`CacheService`**: `overview()` (tamaños/conteos de caché, temporales, uploads, opcache activo, última purga) y `purge($options)` que ejecuta acciones seleccionables:
  1. **Caché de la app** (vaciar `cache/`, `storage/cache/`).
  2. **Archivos temporales** (vaciar `tmp/`).
  3. **OPcache** (`opcache_reset()` — recarga el código PHP).
  4. **Versionar assets/media** (`asset_version = time()`), usado por los temas para añadir `?v=` a logo/favicon y multimedia → fuerza refresco en el navegador.
- **API** (admin, JWT): `GET /api/v1/admin/cache` (overview) y `POST /api/v1/admin/cache/purge` (`{options:{app_cache,tmp,opcache,assets}}`).
- **UI**: Settings → pestaña **Caché** con resumen (tamaños), casillas de acciones, botón **Purgar ahora** y **última purga**.
- **Multi-site**: `@site_id`; el estado y el `asset_version` son por sitio.

## 3. LAS 10 INNOVACIONES
1. **Purga selectiva** (caché de app / temporales / opcache / assets) con casillas.
2. **Botón “Purgar todo”** de un clic + confirmación.
3. **Versión de assets por sitio** (`asset_version`) para forzar refresco de media/logo sin renombrar archivos.
4. **Resumen de uso** (tamaño y nº de archivos de cada caché, uploads, opcache on/off).
5. **Última purga** (fecha/hora + quién) para auditoría.
6. **Purga automática programada** (limpieza periódica vía cron/`job-runner`).
7. **Purga al publicar/desplegar** (hook opcional tras un *system update*).
8. **Purga de medios y miniaturas** (borrar derivados/optimizados y regenerar).
9. **Comprobador de caché del navegador** (explicar el `?v=` y ofrecer “abrir con cache-bust”).
10. **Observabilidad**: registrar cada purga en un log (`wwi_*`/settings) y mostrar métricas (purgas del mes).

## 4. GOBERNANZA (PEDS — Monitor de Código, 6 puntos)
1. **Sintaxis**: `php -l` / `node --check`.
2. **Seguridad**: purga solo dentro de rutas permitidas (whitelist de directorios), sin borrar archivos fuera de `ROOT_DIR`, `@site_id` en queries, `requireSuper`/rol, sin secretos.
3. **Buenas prácticas**: reutilizar Settings; sin dependencias externas; sin código muerto.
4. **Idempotencia**: purga repetible; `overview` tolerante a carpetas inexistentes.
5. **UX**: estados loading/empty/error; confirmación de acciones destructivas; foco.
6. **Verificación**: prueba en vivo (purgar → verificar `asset_version` y que el sitio recarga el logo/media).

## 5. CRITERIO DE ACEPTACIÓN
- En **Settings → Administración de caché** se ve el resumen y se puede **purgar caché, eliminar caché y temporales**.
- La purga **fuerza la recarga** de contenido/multimedia (asset_version aplicado por los temas).
- Disponible en **todos los sitios**; monitor de código (6 puntos) pasado.
