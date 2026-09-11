# WWI — CMS MASTER PROMPT (Auditoría, Robustez y Actualizaciones)

> Prompt maestro para llevar Wontia Web Intelligence al 100% funcional como CMS de autogestión. Incluye el listado ordenado del requerimiento, roles, el monitor de código obligatorio y 50 innovaciones de robustez.

## 1. REQUERIMIENTO (listado ordenado, semántica y lógica)
1. **Auditoría total del CMS**: escanear y mapear los 13 menús del admin (Dashboard, Pages, Sections, Bricks, BrickHub, AI BRICK, Factory, Blog, Media, SEO, Analytics, Settings, Users). Cero links rotos, cero menús vacíos, cero endpoints con error.
2. **Garantía de funcionalidad 100%**: cada módulo que exista debe operar de verdad — la promesa comercial (sitios auto-gestionables) depende del CMS central.
3. **Transmisión de actualizaciones**: cuando se publique un brick/versión nueva, TODOS los sitios con WWI instalado deben recibir la notificación y poder instalarla/actualizarla desde BrickHub, sin comprometer la seguridad (ni del sistema universal ni de los tenants).
4. **PEDS + Monitor de Código obligatorio**: todo código escrito pasa por un monitor que garantiza normas, técnicas, buenas prácticas y ciberseguridad.
5. **50 innovaciones** para que WWI sea el CMS más robusto del planeta.

## 2. ROLES (simular simultáneamente)
- **QA Lead / Auditor**: escanea cada menú, endpoint y flujo; reporta fallas con severidad.
- **Backend Engineer (PHP/PDO)**: corrige queries, multi-site (@site_id), transacciones.
- **Frontend Engineer (Vanilla JS SPA)**: menús, estados vacíos, toasts, accesibilidad.
- **Database Architect**: esquemas idempotentes, índices, integridad referencial.
- **DevOps Engineer**: pipeline de actualización Git (agente host, rollback, health checks).
- **Security Engineer**: firmas HMAC, aislamiento por tenant, superficie de ataque del update system.
- **Release Manager**: versionado semántico de bricks, changelogs, notificaciones de actualización.
- **Product Owner**: prioriza por impacto en la promesa comercial (autogestión garantizada).
- **Code Monitor (rol nuevo, obligatorio)**: ver §4.

## 3. SISTEMA DE ACTUALIZACIONES (implementado)
- **Fuentes**: `brick_sources` (repos GitHub) + `bricks` (instalados) + `brick_updates` (pendientes).
- **Detección**: `autoCheck` compara versión instalada vs última del repo (GitHub API) y crea `brick_updates` con estado `pending` por site.
- **Notificación**: `GET /api/v1/admin/brickhub/notifications` → contador de pendientes; el admin muestra badge en el sidebar (poll cada 5 min) + pestaña Updates con "Apply".
- **Instalación**: `POST /brickhub/updates/apply/{id}` (superadmin) descarga y aplica; `apply-all` para lote.
- **Multi-sitio**: `broadcast/{slug}` (madre → hijos), `mother/pending` + `child/notify` (poll firmado), `brickhub_registry` (sitios hijos registrados).
- **Seguridad**: endpoints admin-only (JWT + rol); webhooks con `webhook_secret`; actualizaciones del sistema vía cola firmada HMAC + agente host con health-check y rollback automático.
- **Actualización del CORE (WWI)**: `POST /api/v1/admin/system/update` → cola firmada → `wwi-update.py` (git pull + rsync + rebuild + recreate 6 contenedores + health + rollback).

## 4. MONITOR DE CÓDIGO (PEDS — obligatorio desde ahora)
Todo código nuevo/modificado debe pasar el monitor:
1. **Sintaxis**: `php -l` / `node --check` sin errores.
2. **Seguridad**: sin SQL concatenado con input (solo PDO prepared), escape de salida (`esc`/`htmlspecialchars`), sin secretos en código/repo, validación de input, multi-site (`@site_id`) en toda query nueva.
3. **Buenas prácticas**: patrón correcto PDO (`prepare → execute → fetch`, nunca `->execute()->fetch()`), sin funciones obsoletas, sin duplicación, nombres consistentes, sin código muerto.
4. **Idempotencia**: DDL con `IF NOT EXISTS`, seeds con `INSERT IGNORE`, updates repetibles.
5. **Accesibilidad/UX**: estados loading/empty/error definidos; focus-visible; reduced-motion.
6. **Verificación**: lint + prueba en vivo del endpoint/flujo antes de cerrar la tarea.
El monitor es un paso de PEDS: **ningún cambio se considera terminado sin pasar los 6 puntos.**

## 5. 50 INNOVACIONES DE ROBUSTEZ
1. Health-check automático de cada menú (smoke test diario de los 13 endpoints).
2. Auto-heal de tablas faltantes al primer uso (ensure idempotente en cada módulo).
3. Versionado semántico de bricks + changelog por release.
4. Canary de actualizaciones: aplicar a 1 sitio primero, luego broadcast.
5. Rollback por sitio además del rollback global.
6. Firmas HMAC en TODA comunicación madre↔hijo.
7. Rate-limit en endpoints públicos del hub.
8. Auditoría de cambios del sistema (quién actualizó qué y cuándo).
9. Backups automáticos pre-actualización.
10. Verificación de integridad post-update (checksums).
11. Migraciones versionadas por tabla (no DDL disperso).
12. Feature flags por brick (activar/desactivar sin desinstalar).
13. Dependencias entre bricks declaradas y validadas.
14. Matriz de compatibilidad brick ↔ versión WWI.
15. Instalación de bricks en sandbox + promoción.
16. Logs estructurados JSON por request.
17. Alertas al operador (email/Telegram) ante fallos de update.
18. Dashboard de salud del ecosistema (todos los sitios, versión, estado).
19. Auto-descubrimiento de bricks locales y remotos unificado.
20. Firma de autor en bricks (verificación de procedencia).
21. Límite de recursos por tenant (storage/IA) con avisos.
22. Modo mantenimiento por sitio durante updates.
23. Reintentos exponenciales en jobs fallidos.
24. Dead-letter queue para jobs irrecuperables.
25. Idempotencia total en provisioning (ya aplicada) extendida a updates.
26. Sincronización de configuración entre sitios (plantillas de settings).
27. Multi-idioma del admin (ES/EN).
28. Búsqueda global en el admin (Ctrl+K).
29. Atajos de teclado en el admin.
30. Export/import de configuración de un sitio (JSON).
31. Clonado de sitio completo (sitio → sitio) con assets.
32. Preview de bricks antes de instalar (sandbox iframe).
33. Tests automáticos de widgets (render sin error con config vacía).
34. Contract testing de la API pública.
35. Métricas de uso por brick.
36. Deprecación controlada de bricks con avisos.
37. Migración automática de datos al actualizar un brick.
38. Firma de releases con hash verificable.
39. Rate-limit de login por tenant además de por IP.
40. 2FA para superadmin.
41. Rotación automática de secrets (JWT/BRICK keys) con grace period.
42. Escaneo de dependencias (bricks) contra CVEs conocidos.
43. Modo solo-lectura durante mantenimiento de DB.
44. Verificación de espacio en disco antes de builds.
45. Cola de deploys con concurrencia limitada.
46. Estado de actualización visible en el sitio (banner admin).
47. Changelog público de bricks en el BrickHub.
48. Puntuación de calidad por brick (tests, docs, seguridad).
49. Auto-documentación de bricks (meta → docs).
50. Panel de operador unificado: sitios + bricks + updates + salud + costos.

## 6. 30 INNOVACIONES — MENÚ Y PROCESO DE ACTUALIZACIÓN
1. Barra de progreso con porcentaje en tiempo real (polling 2.5s).
2. Tiempo transcurrido y estimado (ETA calculado por ritmo real).
3. Indicador de pasos: Descarga → Sincronización → Build → Contenedores → Salud.
4. Contador de contenedores recreados (x/6) en vivo.
5. Aviso verde flat "SISTEMA ACTUALIZADO" con commit, contenedores y duración.
6. Banner de rollback en rojo cuando falla la verificación.
7. Botón deshabilitado mientras corre (evita doble enqueue).
8. Historial con estado, commit, contenedores y duración por corrida.
9. Auto-refresh del historial al terminar (sin recargar página).
10. Polling auto-cancelable al cambiar de pestaña.
11. Detección de actualización estancada (stale > 5 min) con aviso.
12. Notificación toast al encolar y al completar.
13. Changelog visible: lista de commits incluidos en la actualización.
14. Verificación de versión actual vs última de GitHub antes de actualizar.
15. Badge "Actualización disponible" en el sidebar cuando el remoto tiene commits nuevos.
16. Modo mantenimiento automático por sitio durante la recreación.
17. Canary: recrear 1 contenedor primero y esperar salud antes del resto.
18. Health-check con reintentos (3 intentos, backoff 5s).
19. Rollback selectivo (solo contenedores fallidos).
20. Snapshot pre-update (tag + dump de DB opcional).
21. Firma de integridad del commit (SHA verificado contra GitHub API).
22. Log en vivo del agente visible en el panel (últimas N líneas).
23. Botón "Ver detalles" por actualización (errores, contenedores, timing por paso).
24. Programación de ventana de mantenimiento (hora preferida).
25. Notificación por email/Telegram al operador cuando termina o falla.
26. Modo dry-run: simular sin recrear (valida clone+build).
27. Rate-limit de updates (mínimo 5 min entre corridas).
28. Firma de quién solicitó cada actualización (auditoría).
29. Estado del agente (online/offline) visible en el panel.
30. Export del reporte de actualización (JSON/PDF) para auditoría.

## 7. 20 INNOVACIONES — ROBUSTEZ Y SEGURIDAD DE COMPONENTES
1. **Sesión JWT persistente**: el token se guarda en localStorage; sobrevive a reinicios de contenedores (las sesiones PHP no).
2. **Auto-redirección a login** ante 401 con aviso claro (sin pantallas vacías).
3. **Menú WWI de sistema**: consola superadmin separada del contenido (estado, versión, setup, salud, guía).
4. **Auto-setup de tablas** por módulo al primer uso (BRICK, BrickHub) sin botones manuales.
5. **Smoke test automático de los 13 menús** tras cada actualización; si un endpoint falla, alerta.
6. **Guía integrada en cada menú** (qué es y para qué sirve) — cero confusión.
7. **Refresh token rotativo** (access 24h + refresh 7d) con revocación por usuario.
8. **2FA opcional para superadmin** (TOTP).
9. **Registro de sesiones activas** por usuario con cierre remoto.
10. **Rate-limit por usuario** además de por IP (evita abuso autenticado).
11. **CSRF token** para operaciones mutantes del admin (doble protección con SameSite).
12. **Cifrado en reposo** de secretos de integraciones (llaves de registrador, SMTP).
13. **Verificación de integridad de bricks** (hash SHA256 del paquete descargado antes de instalar).
14. **Sandbox de evaluación de bricks** (lint + smoke antes de activar).
15. **Auditoría completa de acciones admin** (quién, qué, cuándo, desde dónde).
16. **Alertas de salud al operador** (email/Telegram) cuando un sitio cae o un update falla.
17. **Panel de salud del ecosistema** (todos los sitios: versión, estado, último update).
18. **Backup automático pre-actualización** (DB + snapshot de configuración).
19. **Modo mantenimiento** por sitio durante operaciones críticas.
20. **Bloqueo de actualizaciones concurrentes** con lock distribuido en la cola.
