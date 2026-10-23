# SONIKA VIBE — Manual de voz + diccionario + mapa de etiquetas + plan
> Módulo de identidad cultural y UX writing para **SONIKA Artist Partners** y **SONIKA Sound League**.
> PEDS v1.0 · SDD/FID. Los identificadores internos (código, API, DB) **no cambian**.

## 1. Auditoría del sistema actual (Fase 0)
- **Traducciones**: `App\Core\Lang` (`Lang::get('clave', 'default')`), usado en los temas (`sonika_esc`, render server-side).
- **Etiquetas UI**: `theme.json` (config del tema, en español), los bricks/widgets (textos inline), el admin (`admin.js`, español), los módulos (agenda, tienda, cuenta…).
- **Escapado**: `htmlspecialchars`/`sonika_esc` (seguro).
- **Admin de afiliados** (nuevo): `/api/v1/admin/affiliates` (JSON, sin labels culturales aún).
- **Conclusión**: no hay un “design token” de textos; los labels están **hardcodeados** en tema/bricks. => conviene un **diccionario configurable** (JSON + helper) con **fallback neutro**, sin tocar IDs.

## 2. Personalidad de la voz
Cercana y relajada · musical y creativa · alegre y respetuosa · auténtica (nunca artificial) · inclusiva (países, géneros, niveles) · **clara** en lo comercial/legal/financiero.

## 3. Diccionario inicial (versionado)
| ID interno | Expresión Vibe | Significado | Contexto | Neutro | Informalidad | Estado |
|---|---|---|---|---|---|---|
| `friend` | Mi llave | Amigo de confianza | Saludo/comunidad | Colega | Alta | propuesto |
| `peer` | Parce / Pana | Compañero de comunidad | Comunidad | Compañero | Alta | propuesto |
| `partner` | Socio | Con quien se construye | Programa | Aliado | Media | propuesto |
| `great` | Bacano / Bacanería | Algo excelente | Elogios | Excelente | Alta | propuesto |
| `space` | Mi Parche / Mi Combo | Espacio personal del artista | Panel | Mi espacio | Alta | propuesto |
| `sound` | Mi Sonido | Perfil e identidad musical | Perfil | Mi perfil musical | Media | propuesto |
| `store` | Mi Vitrina | Tienda personalizada | Tienda artista | Mi tienda | Media | propuesto |
| `selection` | Mi Setlist | Selección de productos | Colecciones | Mi selección | Media | propuesto |
| `promote` | Poner a Sonar | Compartir y promocionar | Campañas | Promocionar | Media | propuesto |
| `sales` | Mis Ventas | Ventas atribuidas | Panel comercial | Mis ventas | Baja | aprobado |
| `earnings` | Mis Ganancias | Comisiones (estados financieros exactos) | Panel financiero | Mis comisiones | Baja | aprobado |
| `help` | La Mano SONIKA | Centro de ayuda | Soporte | Centro de ayuda | Media | propuesto |
| `challenges` | Mis Retos | Objetivos y actividades | Sound League | Mis objetivos | Media | propuesto |
| `league` | Sound League | Clasificación musical | Ranking | Sound League | Baja | aprobado |
| `awards` | Logros y Premios | Reconocimientos | Premios | Logros y premios | Baja | aprobado |

> Configurable/ampliable sin tocar la lógica comercial. Meta por término: `id, expression, meaning, context, regional_variants[], neutral, example, informality(alta|media|baja), status(propuesto|aprobado)`. Ver `install/sonika_vibe_dictionary.json`.

## 4. Mapa de etiquetas (interfaz del artista)
| Función | Vibe | Neutro (si el usuario lo elige) |
|---|---|---|
| Panel del artista | Mi Parche | Mi espacio |
| Perfil musical | Mi Sonido | Mi perfil musical |
| Tienda personalizada | Mi Vitrina | Mi tienda |
| Selección de productos | Mi Setlist | Mi selección |
| Centro de contenido | Poner a Sonar | Promocionar |
| Ventas atribuidas | Mis Ventas | Mis ventas |
| Comisiones/liquidaciones | Mis Ganancias | Mis comisiones |
| Ayuda | La Mano SONIKA | Centro de ayuda |
| Objetivos | Mis Retos | Mis objetivos |
| Ranking | Sound League | Sound League |
| Premios | Logros y Premios | Logros y premios |

**Regla**: cada etiqueta mantiene descripción breve en el primer uso (tooltip/`title`) y **nunca** oculta el estado real del dinero.

## 5. Transparencia financiera (obligatorio)
Mostrar **siempre** el estado exacto: `Comisión estimada` · `Pendiente de validación` · `Aprobada` · `Programada para pago` · `Pagada` · `Reversada`.
- Prohibido presentar **estimada** como ingreso garantizado o un **premio** como aprobado antes de autorización.
- La jerga **nunca** sustituye los términos de comisión/atribución/legal.

## 6. Inclusión y regionalismos
- Usar “parce/pana/mi llave/socio/bacano” con **moderación**; no asumir que todos son colombianos, jóvenes, nativos o de un género.
- **Opción regional**: `sonika.vibe.locale = co | neutral` (configurable). Futuro: otros países.
- Evitar expresiones ofensivas/discriminatorias o de asociación regional delicada.

## 7. Gobernanza
- Diccionario **versionado** (`install/sonika_vibe_dictionary.json` + `version`), con `status` por término; cambios requieren aprobación.
- Las expresiones de marca **no** alteran contratos, políticas, fórmulas de comisión, avisos legales ni atribución.
- **IDs internos intactos** (código/API/DB).

## 8. Plan de integración progresiva
1. **Helper configurable**: `vibe('sales', 'Mis Ventas')` → lee el diccionario (setting `sonika.vibe.dictionary` / el JSON) + `neutral` si el toggle está activo, con fallback al texto actual.
2. **Auditoría de labels** (inventario por pantalla) → sustitución **solo de visibles** (no IDs) en el panel de afiliados (Fase 2/3).
3. **Toggle neutro** en la config del sitio (`sonika.vibe.locale`).
4. **QA**: navegación, accesibilidad (contraste/aria), que ninguna función comercial cambie; revisión de comprensión con 3–5 usuarios.
5. **Reversión**: desactivar el helper (fallback) sin migración de datos.

## 9. Riesgos y mitigación
| Riesgo | Mitigación |
|---|---|
| Jerga confusa para no colombianos | neutro por defecto configurable + descripción en primer uso |
| Jerga en lo financiero/legal | prohibido; estados exactos siempre |
| Regionalismos delicados | lista negra + moderación |
| Cambiar IDs por coloquial | prohibido (solo labels visibles) |
| Rendimiento | diccionario en memoria + caché |

## 10. Criterios de aceptación
1. Los labels culturales son **configurable** y reversibles. 2. IDs/API/DB intactos. 3. Estados financieros **exactos**. 4. Existe **opción neutra**. 5. Sin alterar contratos/políticas. 6. Accesibilidad y comprensión validadas. 7. Ninguna función comercial cambió.
