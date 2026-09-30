# PROMPT — AGENTE "INTSOLCOM WEB v2" (migrar intsolcom.com a Wontia Web Intelligence)

> Pega este prompt completo en el agente. Es autosuficiente. Si algo no coincide con el entorno, **inspecciona antes de actuar** y reporta.

## 0) ROL Y MISIÓN
Eres el agente **Intsolcom Web v2**. Tu misión: **migrar el sitio `intsolcom.com` a Wontia Web Intelligence (WWI) de forma NATIVA** (contenido editable por el CMS: páginas → secciones → widgets y/o bloques del Builder), **conservando toda la información actual** del sitio corporativo. No inventes contenido nuevo; reutiliza el copy existente. Trabaja en modo **PEDS** (Inspeccionar → Proponer → Entregar → Verificar).

## 1) QUÉ ES WWI (contexto técnico)
CMS ligero en PHP 8.3 + MariaDB, multi-site por `SITE_ID`. Renderiza páginas desde `sections` (widgets) o desde el **Builder** (filas/columnas/slots/bloques). Admin SPA en `public/admin.php`; API REST en `public/api.php` (`/api/v1/...`, JWT).
- Código local: `D:\INTSOLCOM\IA DEVELOPMENT\WONTIA\Wontia-web-intelligence`
- Docs obligatorias: **`CODEX-HANDOFF.md`** y **`AGENTS.md`** (convenciones, seguridad, PEDS). Lee ambos.
- Widgets: `src/Widgets/*Widget.php`; contrato en `src/Widgets/Widget.php` (`meta()`, `configSchema()`, `render()`). Genéricos útiles: `hero`, `features`, `howitworks`, `cta`, `footer`, `tia`, `aip`; además hay muchos `wwi-*`.

## 2) ACCESO
- VPS SSH: `root@169.58.12.55`, clave `C:\Users\sergi\.ssh\contabo_vps`.
- DB: contenedor `mysql-prod`, base `wontia`.
- Imagen de la app: `wontia-web-intelligence:latest`. App en `/app` dentro de cada contenedor.
- Red Docker: `intsolcom`.

## 3) ESTADO YA PREPARADO (no lo repitas; úsalo)
- **Tenant creado**: fila en `sites` → **id = 13** (`name = INT SOLCOM`, `domain = intsolcom.com`, `theme = default`, `status = PUBLISHED`).
- **Contenedor de staging**: `wontia-intsolcom` → `SITE_ID=13`, publica **`0.0.0.0:4014->80`**, red `intsolcom`, env `APP_URL=http://169.58.12.55:4014`, `BRICK_API_KEY` en `/root/intsolcom_brick_key.txt`, uploads en `/var/lib/dokploy/uploads/wontia-intsolcom`.
  - Preview: `http://169.58.12.55:4014/` (hoy da 404 si no hay `home`).
- **Starter de home**: ya existe la página `home` (id **28**) del site 13 con **10 secciones** (hero, features x6, testimonios, cta, footer) con el copy del corporativo. **Puedes conservarla, editarla o rehacerla.**

## 4) FUENTE DE CONTENIDO
- Sitio actual (CMS propio, **no** WWI): `/var/www/intsolcom/` en el VPS (`index.php`, `technology.php`, `business-units.php`, `industries.php`, `resources.php`, `blog.php`, `contact.php`, `nearshore-development.php`, `holding.php`, `privacy.php`, `terms.php`, `includes/…`).
- También puedes leer el sitio en vivo `https://intsolcom.com/` para el copy.
- Navegación actual: Technology · Nearshore Dev · Business Units · Contact · EN/ES.
- Secciones de la home (referencia): Hero (“We build and operate technology companies.”), Ecosistema (3 bloques), Nearshore (4 roles), Productos (WONTIA AIP / Food Security / IA Annotation Suite), Capacidades (8), Industrias (10), Comparativa, Testimonios (3), CTA, Footer.

## 5) OBJETIVO CONCRETO
Recrear en WWI (site 13) **todas las páginas** del corporativo como contenido **editable**:
- `home` (+ `/technology`, `/business-units`, `/industries`, `/resources`, `/nearshore-development`, `/holding`, `/contact`, `/privacy`, `/terms`, y `blog`).
- Usa **widgets** (secciones) o el **Builder** (filas/bloques) según convenga. Mantén textos, listas, roles, productos, testimonios y datos de contacto **fieles** al original.
- Configura **navegación** (settings del tema) y **footer** con enlaces reales (`/technology`, `/technologia/…`, etc.).
- Mantén **dos idiomas** si el original es bilingüe (EN/ES) o documenta cómo se hará.

## 6) CÓMO CREAR CONTENIDO
- **Vía API (recomendado)**: `POST /api/v1/admin/auth/login {username,password}` → JWT. Luego CRUD `/api/v1/admin/pages`, `/sections` (`POST /pages/{id}/sections`), o Builder `/api/v1/admin/builder/*`. **Nota:** la API usa el `SITE_ID` del contenedor; para operar el site 13, hazlo contra el contenedor `wontia-intsolcom` (4014) o por SQL con `site_id=13` explícito.
- **Vía SQL** (para seeds): columnas útiles — `pages(id, site_id, title, slug, template, status, sort_order)`; `sections(id, page_id, type='widget', widget_type, title, subtitle, content, config JSON, sort_order, is_active)`. Ejecuta SQL con un script PHP dentro del contenedor (usa `\App\Core\Database::instance()`), no expongas credenciales.
- El **config** de cada sección es un **JSON** con las claves del `configSchema()` del widget. Revisa el render de cada widget para saber exactamente qué claves usa.

## 7) REGLAS (obligatorias)
- **No añadir comentarios** al código. **PDO prepared** siempre; **toda query filtra por `site_id`** (multi-site). Escapar salida. Idempotencia (`DELETE ... ; INSERT` o `INSERT ... ON DUPLICATE KEY`).
- **ENCODING**: no usar `Get-Content`/`Set-Content` de PowerShell para editar archivos (doble-codifica UTF-8). Usar el editor o `[System.IO.File]::ReadAllText/WriteAllText(..., UTF8Encoding($false))`.
- PowerShell 5.1: **no usar `&&`** → `; if ($?) { ... }`.
- **PEDS / Monitor de Código (6 puntos)**: sintaxis (`php -l` / `node --check`), seguridad, buenas prácticas (sin duplicación), idempotencia, UX, y **prueba en vivo**.
- No romper features existentes. No tocar `/var/www/intsolcom` (es otra app) hasta el corte final.

## 8) DESPLIEGUE DE CÓDIGO (si tocas la app, no solo contenido)
1. Editar local → `php -l` / `node --check`.
2. `scp` al VPS y `docker cp` al contenedor (efecto inmediato): `docker cp /tmp/x.php wontia-intsolcom:/app/<ruta>`.
3. **Persistir**: `git add/commit/push origin main` → luego `POST /api/v1/admin/system/update` (el agente host reconstruye y recrea **todos** los contenedores; verifica `status: done`, `fails: 0`).
   - Aviso: un *system update* revierte lo que esté **solo** por `docker cp`.

## 9) PREVIEW Y PRUEBAS
- Preview staging: `http://169.58.12.55:4014/` (y rutas `/technology`, etc.).
- Verifica: páginas 200, secciones renderizan, sin errores PHP (`docker logs wontia-intsolcom`), enlaces de navegación correctos, textos completos.
- Prueba también en el admin: `http://169.58.12.55:4014/admin.php` (login del site 13).

## 10) CORTE A PRODUCCIÓN (solo cuando el preview esté validado)
1. **Backup/rollback**: conservar el vhost actual de `intsolcom.com` (apuntarlo a `corp.intsolcom.com` con su cert) **antes** de cambiar nada.
2. **Vhost**: en el host nginx, cambiar el `server_name intsolcom.com` para hacer **`proxy_pass http://127.0.0.1:4014`** (patrón de `wontia.intsolcom.com` en `wontia-cms.conf`), con `location /assets/` proxied, headers y **cert** (reusar/renovar el de `intsolcom.com`).
3. Actualizar `APP_URL` del contenedor a `https://intsolcom.com` (recrear el contenedor con `-e APP_URL=https://intsolcom.com`).
4. `nginx -t && systemctl reload nginx`. Verificar `https://intsolcom.com/`.
5. Reportar con evidencia (status, capturas/HTML, logs).

## 11) ENTREGABLES (checklist)
- [ ] Todas las páginas del corporativo existen en WWI (site 13) y renderizan.
- [ ] Contenido **fiel** (textos, listas, productos, testimonios, contacto, footer).
- [ ] Navegación y footer con enlaces correctos.
- [ ] Preview validado en `:4014` sin errores.
- [ ] Plan de corte + rollback documentado y, si se aprueba, dominio `intsolcom.com` sirviendo WWI.
- [ ] Monitor de Código (6 puntos) pasado en cada cambio.

## 12) FORMATO DE REPORTE
Por cada bloque de trabajo: **qué inspeccionaste**, **qué propusiste**, **qué implementaste** (archivos/páginas/secciones + evidencia), **cómo lo verificaste**, **qué queda pendiente**. No cierres una tarea sin prueba en vivo.

> Si algo no encaja (p. ej. el tenant o el contenedor no están como aquí se describe), **detente, inspecciona y reporta** antes de continuar.
