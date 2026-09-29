# CODEX-HANDOFF — Guía de acceso y trabajo sobre Wontia Web Intelligence

> Documento para agentes de IA (p. ej. ChatGPT Codex) que necesiten **leer y modificar** el proyecto y desplegarlo por SSH. **No contiene credenciales.**

## 1. Ruta local del proyecto
- **Raíz:** `D:\INTSOLCOM\IA DEVELOPMENT\WONTIA\Wontia-web-intelligence`
- Backend: `src/` (`Core/`, `Controllers/Admin/`, `Services/`, `Widgets/`, `Bricks/`)
- Entrypoints: `public/index.php` (front), `public/admin.php` (SPA), `public/api.php` (REST `/api/v1`), `public/job-runner.php`
- Assets: `public/assets/js/admin.js`, `public/assets/js/builder.js`, `public/assets/css/...`
- Temas: `templates/themes/{wwi,wwi-intelligence,default}` + `_shared/{live-editor.php,builder-only.php}`
- SQL/seeds/DDL: `install/` (`schema.sql`, `brick_ai.sql`, `builder.sql`, `wwi_*.sql`)
- **Leer primero:** `AGENTS.md` y los `WWI-*MASTER-PROMPT.md` (contexto y convenciones).

## 2. VPS / SSH
- **Host:** `root@169.58.12.55`
- **Clave SSH:** `C:\Users\sergi\.ssh\contabo_vps`
- Conexión: `ssh -i "C:\Users\sergi\.ssh\contabo_vps" root@169.58.12.55`

## 3. Contenedores Docker (imagen única `wontia-web-intelligence:latest`)
| Contenedor | Puerto | Tenant | App |
|---|---|---|---|
| `wontia-wwi` | 4009 | **WWI / site 5** (`wwi.wontia.com`) — el que se edita normalmente | `/app` |
| `wontia-demo6` | 4010 | demo site 6 | `/app` |
| `wontia-web-intelligence` | 4003 | site 1 | `/app` |
| `mysql-prod` | — | DB `wontia` | — |

Dentro del contenedor la estructura es igual al repo bajo `/app` (p. ej. `/app/public/assets/js/builder.js`, `/app/src/Services/...`, `/app/templates/themes/...`).

## 4. Flujo de despliegue (patrón usado)
```powershell
scp -i "C:\Users\sergi\.ssh\contabo_vps" <archivo> root@169.58.12.55:/tmp/check/<archivo>
ssh -i "C:\Users\sergi\.ssh\contabo_vps" root@169.58.12.55 `
  "docker cp /tmp/check/<archivo> wontia-wwi:/app/<ruta>"
```
Persistencia: `git add -A; git commit -m "..."; git push origin main` → el agente de auto-update del host reconstruye la imagen. **Aviso:** un *system update* revierte lo que esté solo por `docker cp`, así que hay que **commitear/pushear**.

Rutas VPS: build `/tmp/wontia-build/app` · uploads `/var/lib/dokploy/uploads/wontia` · backups `/var/lib/dokploy/backups/wontia`.

## 5. Verificación obligatoria
- **PHP:** `docker exec wontia-wwi php -l /tmp/<archivo>.php` (copiar antes con `docker cp`).
- **JS:** `node --check public/assets/js/<archivo>.js` (local).
- **ENCODING:** NO editar con `Get-Content`/`Set-Content` de PowerShell (doble-codifica UTF-8 → mojibake). Usar el editor o `[System.IO.File]::ReadAllText/WriteAllText(..., UTF8Encoding($false))`.
- **Prueba en vivo:** `Invoke-RestMethod` (PowerShell) o `fetch` (Node) contra `https://wwi.wontia.com/api/v1/...` con `Authorization: Bearer <JWT>`.

## 6. API y autenticación
- **Login:** `POST https://wwi.wontia.com/api/v1/admin/auth/login` con `{username,password}` → `{token}` (JWT). Ese login también crea la **sesión de servidor** (cookie) que exige `admin.php`.
- CRUD: `/api/v1/admin/sections/*`, `/pages`, `/bricks`. Builder: `/api/v1/admin/builder/*` (`tree`, `rows`, `blocks`, `reorder/{type}`, `render`, `revisions`, `components`, `comments`…). IA: `POST /api/v1/admin/brick/request` (BRICK).
- **Credenciales admin:** NO en el repo. El operador las guarda fuera (archivo local del owner). Pedirlas al owner. **No commitear secretos.**
- **DB:** usuario de la app; contraseñas solo en el VPS (`.env` del build y `-e` en `docker run`). Nada de credenciales en el código.

## 7. Reglas de código (obligatorias)
- PHP 8.3, namespace `App\...`. **PDO prepared** siempre (`prepare → execute → fetch`); **toda query nueva filtra por `@site_id`** (multi-site).
- **No añadir comentarios** al código. Escapar salida (`esc` / `htmlspecialchars`). Config JSON con `safeJson()`.
- Sin dependencias externas (composer solo autoload).
- Idempotencia: DDL `IF NOT EXISTS`, seeds `INSERT IGNORE`.
- Estados UX: loading/empty/error. Accesibilidad (focus-visible, reduced-motion).
- **Monitor de Código (PEDS):** sintaxis + seguridad + buenas prácticas + idempotencia + UX + verificación en vivo. No romper features existentes.

## 8. Quirks de entorno (Windows / sesión)
- PowerShell 5.1: **no usar `&&`** → `; if ($?) { ... }`.
- `git commit/push` a veces se cuelga en esta máquina: reintentar y comprobar `git rev-parse HEAD` vs `origin/main`.

## 9. Estado actual relevante
- Editor visual = **builder por bloques** (único módulo live; el `live-editor` por secciones solo se carga en páginas **sin** filas del builder).
- La **home (page id 14)** tuvo un incidente (se restauró una versión vacía) y se **recuperó** desde la revisión 5 *"Antes de restaurar"* (7 filas / 8 bloques). *Restaurar versión* ahora **pide confirmación**.
- Documentación de contexto: `AGENTS.md`, `WWI-CMS-MASTER-PROMPT.md`, `WWI-BUILDER-INTERACTION-MASTER-PROMPT.md`, `WWI-BUILDER-TEXT-FORMAT-MASTER-PROMPT.md`, `WWI-DESIGN-SYSTEM-MASTER-PROMPT.md`, etc.
- Repo: `https://github.com/intsolcom/wontia-web-intelligence` (branch `main`).
