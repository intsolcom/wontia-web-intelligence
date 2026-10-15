# AGENT-BRIDGE · Canal de coordinación de agentes WWI

> Todos los agentes del ecosistema WWI leen este archivo antes de actuar.
> La conversación entre agentes se registra aquí **y se reproduce en el chat del operador**.

## Roles
- **Head Team Leader (WWI)** — dueño de la **plataforma**: core, `src/**`, motor de themes, menús, settings,
  APIs, `public/api.php`, `public/assets/js/admin.js`, `templates/themes/_shared/**`, `install/**`, `bin/**`,
  `AGENTS.md`, contratos y gobernanza. **Único autorizado** a cambiar estructura del CMS.
- **Agente de sitio** (p. ej. `sonika-agent`) — dueño **solo del contenido** de su sitio: catálogo, textos,
  imágenes, SEO, pedidos. Opera **a través del CMS/admin de WWI**, nunca editando código de plataforma/theme.
- **Operador (humano)** — orquesta; lee este canal y el chat.

## Regla de oro: **un solo escritor por path**
| Capa | Paths | Quién escribe |
|---|---|---|
| Plataforma / estructura CMS | `src/**`, `public/{api,admin}.php`, `public/assets/js/admin.js`, `templates/themes/_shared/**`, `install/**`, `bin/**`, motor `templates/themes/*/theme.json` | **Head Team Leader** |
| Contenido de un sitio | valores en el **admin/BD** (settings, menús, pages/sections, catálogo) + `public/assets/uploads/**` | **Agente de sitio** |

Si un agente de sitio necesita una capacidad nueva de plataforma → **NO la codea**; la **solicita** aquí.

## Archivos puente por sitio
| Sitio / proyecto | `SITE_ID` | Archivo puente |
|---|---|---|
| Central WWI (Factory) | 5 | `agent-bridges/_central-wwi.md` |
| SONIKA | 12 | `agent-bridges/sonika.md` |
| MarcasBPO | 2 | `agent-bridges/marcasbpo.md` |
| INTSOLCOM | 13 | `agent-bridges/intsolcom.md` |
| IA Annotation | 3 | `agent-bridges/iannma.md` |
| Wontia Medical | 14 | `agent-bridges/wontia-medical.md` |
| Panadería / Cafetería / demos | 6/9/10 | `agent-bridges/<slug>.md` |

Cada agente escribe y lee **solo el archivo de su sitio**; el Head Team Leader lee/escribe cualquiera.

## Formato de mensaje (obligatorio)
```
## [TIPO] — De: <rol> → Para: <rol>
- Fecha (UTC):
- Estado: abierto | en curso | resuelto
- Asunto:
- Contexto:
- Acción solicitada / Instrucción:
- Archivos implicados:
- Criterios de aceptación:
```
**TIPOS:** `SOLICITUD` (agente pide algo) · `ORDEN` (Head Team Leader corrige/redirige) · `ACLARACIÓN` ·
`PROPUESTA` · `RESPUESTA`.

## Protocolo
1. Un agente de sitio que pretenda tocar **estructura CMS/plataforma** lo declara aquí como `SOLICITUD`
   **antes de escribir código**.
2. El **Head Team Leader** responde con `ORDEN`: confirma propiedad del código, **reescribe el prompt**
   para encajarlo en el contrato CMS (sin hardcodear; usando settings/menús/sections/Builder) y lo entrega listo.
3. El agente de sitio **ejecuta literal** la `ORDEN` y marca `resuelto`.
4. **Cada tarea** (contenido O código) cierra con una entrada `[RESULTADO]` en el puente del sitio
   (qué cambió + dónde). El Head Team Leader la revisa y responde.
5. **Toda** la conversación (SOLICITUD/ORDEN/RESULTADO/RESPUESTA) se **reproduce en el chat del operador**.

## Contrato de contenido (recordatorio duro)
Prohibido hardcodear en themes/bricks/módulos: copy de hero/beneficios, listas de menú, enlaces de footer,
contacto/legal, `<title>`, teléfonos/emails, categorías. **Todo editable va al admin** (settings/sections/menús/catálogo).

---
_Head Team Leader — Wontia Web Intelligence._
