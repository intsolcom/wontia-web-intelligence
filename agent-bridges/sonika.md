# Puente de agente — SONIKA
> Reglas: `AGENT-BRIDGE.md`. Este archivo es SOLO para SONIKA.

- **Sitio:** SONIKA (sonika.com.co)  ·  **SITE_ID:** 12  ·  **Contenedor:** `wontia-sonika-com-co` (4013)
- **Agente:** `sonika-agent`  ·  **Rol:** contenido (vía CMS WWI)
- **Plataforma (estructura CMS / theme engine / menús / settings):** Head Team Leader WWI

## Bitácora de conversación
<!-- Formato de AGENT-BRIDGE.md · más reciente arriba -->

## [ACLARACIÓN] — De: Head Team Leader → Para: sonika-agent
- Fecha (UTC): 2026-10-10
- Estado: abierto
- Asunto: Alcance y propiedad del theme SONIKA
- Contexto: se detectó contenido hardcodeado en el theme (`lib.php`, `index.php`): menú, hero, beneficios,
  footer, contacto/legal y meta.
- Instrucción: El theme es **plataforma** (Head Team Leader). Tu trabajo es **contenido**: catálogo, textos,
  imágenes, SEO y pedidos, **siempre desde el admin de WWI** (Settings, Menús, Pages/Sections/Builder, Tienda).
  **No edites** `src/**`, `templates/themes/**`, `public/api.php`, `public/assets/js/admin.js`.
- Acción requerida: si necesitas un campo editable que hoy no existe (menú, hero, footer), abre una
  `SOLICITUD` aquí. El Head Team Leader lo habilitará como **config de theme/settings** y lo entregará listo.
- Criterios de aceptación: el contenido editable de SONIKA se administra sin tocar código.

## [RESPUESTA] — De: Head Team Leader → Para: sonika-agent
- Fecha (UTC): 2026-10-10
- Estado: resuelto
- Asunto: Verificación del Test A (contenido por CMS)
- Verificación (hecha por el Head Team Leader):
  - **git**: el agente NO creó commits ni tocó código (último commit = `7f88eb6`, del Head Team Leader). ✔
  - **BD site 12**: `logo_image` subido · `theme.sonika.hero_title` = "TODO PARA TU *MÚSICA*." · `theme.sonika.topbar_text` actualizado · `nav.primary` con "Soporte → /soporte" añadido y "Blog" oculto. ✔
  - **Conclusión**: operó EXCLUSIVAMENTE contenido vía CMS, sin tocar la plataforma. ✔
- Instrucción (trazabilidad): a partir de ahora, tras CADA tarea (contenido o código), publica una entrada
  `[RESULTADO]` aquí (qué cambió + dónde). Así el Head Team Leader lo revisa y responde en el chat.
