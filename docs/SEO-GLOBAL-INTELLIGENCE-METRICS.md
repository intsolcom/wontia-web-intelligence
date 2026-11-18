# SEO Global Intelligence — Diccionario de métricas

**Versión:** Fase 2 (medición canónica)
**Fecha:** 17 de noviembre de 2026
**Regla:** una métrica = una definición + una fuente + una ventana. No se suman fuentes distintas.

---

## 1. Fuentes de datos y su papel

| Fuente | Tabla | Papel | Consentimiento |
|---|---|---|---|
| Monitor de peticiones | `seo_live_requests` | Tráfico canónico de servidor, latencia, 404, seguridad | No (log técnico) |
| Analítica de servidor | `analytics_views` | Respaldo de page views y atribución UTM/fbclid | Sí |
| Eventos de navegador | `seo_tracking_events` | Comportamiento y embudo (page_view, view_item, add_to_cart, begin_checkout, purchase) | Sí |
| Ledger externo | `tracking_events` | Estado de despacho a Meta/GA4/CAPI | Sí |
| Pedidos | `store_orders` / `store_order_items` | Compras pagadas, ingresos y ticket | No aplica |
| Búsqueda interna | `store_search_log` | Consultas y búsquedas sin resultado | No PII (hash) |
| Agregados diarios | `seo_daily_metrics` | Dashboard rápido y comparación de períodos | Derivado |

---

## 2. Métricas canónicas

### 2.1 Tráfico

| Métrica | Definición | Fuente | Filtros |
|---|---|---|---|
| `client_views` | Vistas de página de clientes | `seo_live_requests` | `is_page_view=1`, `is_internal=0`, `is_bot=0` |
| `client_visitors` | Visitantes únicos | `seo_live_requests` | `COUNT(DISTINCT visitor_hash)` |
| `client_sessions` | Sesiones | `seo_live_requests` | `COUNT(DISTINCT session_id)` |
| `analytics_views` | Vistas de respaldo server-side | `analytics_views` | `is_internal=0`, `is_bot=0` |
| `consent_page_views` | Page views de eventos | `seo_tracking_events` | `type='page_view'`, `is_internal=0`, `is_bot=0` |
| `consent_sessions` | Sesiones con consentimiento | `seo_tracking_events` | `COUNT(DISTINCT session_id)` |
| `internal_views` | Vistas propias | `seo_live_requests` / `seo_tracking_events` | `is_internal=1` |
| `bot_views` | Vistas de bots | `seo_live_requests` | `is_bot=1` |

**Regla:** `views` y `visitors` del dashboard salen de `seo_live_requests`. `sessions` y `pages_per_session` salen de `seo_tracking_events` con consentimiento; `server_sessions` queda solo como referencia. No se dividen fuentes distintas.

### 2.2 Comportamiento y embudo

| Métrica | Definición | Fuente |
|---|---|---|
| `product_views` | Vistas de ficha de producto | `seo_tracking_events.type='product_view'` |
| `view_category` | Vistas de categoría | `seo_tracking_events.type='view_category'` |
| `search` | Búsquedas internas | `seo_tracking_events.type='search'` o `store_search_log` |
| `add_to_cart` | Añadidos al carrito | `seo_tracking_events.type='cart_add'` |
| `remove_from_cart` | Retiros del carrito | `seo_tracking_events.type='remove_from_cart'` |
| `view_cart` | Vistas del carrito | `seo_tracking_events.type='view_cart'` |
| `begin_checkout` | Inicios de checkout | `seo_tracking_events.type='checkout_start'` |
| `add_shipping_info` | Datos de envío | `seo_tracking_events.type='add_shipping_info'` |
| `add_payment_info` | Datos de pago | `seo_tracking_events.type='add_payment_info'` |
| `purchase` | Compras atribuidas | `seo_tracking_events.type='purchase'` |
| `whatsapp_click` | Clics a WhatsApp | `seo_tracking_events.type='whatsapp_click'` |
| `contact_click` | Clics a contacto | `seo_tracking_events.type='contact_click'` |

**Embudo:** `client_views` → `product_views` → `add_to_cart` → `begin_checkout` → `purchase`.

### 2.3 Compras y negocio

| Métrica | Definición | Fuente |
|---|---|---|
| `orders_paid` | Pedidos pagados | `store_orders.payment_status='paid'` |
| `orders_revenue_cents` | Ingresos pagados | `SUM(total_cents)` de pedidos pagados |
| `avg_ticket_cents` | Ticket promedio | Ingresos / pedidos pagados |
| `abandoned_orders` | Pedidos pendientes > 1 h | `payment_status='pending'` |
| `purchase` (atribuido) | Compra con señal de campaña | `seo_tracking_events` con UTM/fbclid |

**Regla:** pedidos pagados ≠ compras atribuidas. No se atribuye un pedido a Meta sin `fbclid` o UTM de campaña.

### 2.4 Pauta Meta

| Métrica | Definición | Fuente |
|---|---|---|
| `meta_visits` | Visitas con señal de Meta | `analytics_views.is_meta_paid=1` |
| `meta_visitors` | Visitantes de Meta | `COUNT(DISTINCT visitor_hash)` |
| `meta_sessions` | Sesiones de Meta | `COUNT(DISTINCT session_id)` |
| `meta_cart_adds` | Carritos de Meta | `seo_tracking_events` + `is_meta_paid` |
| `meta_purchases` | Compras atribuidas | `seo_tracking_events` + `is_meta_paid` |
| `meta_revenue_cents` | Ingresos atribuidos | `value_cents` de compras atribuidas |
| `spend` | Inversión | Meta Ads API / CSV (Fase 3) |
| `ctr`, `cpc`, `cpm` | Métricas de Meta | Meta Ads API / CSV (Fase 3) |
| `cpa`, `roas` | Coste por compra y retorno | spend + compras/ingresos (Fase 3) |

**Regla:** datos reportados por Meta ≠ datos medidos por SONIKA. Se muestran separados.

### 2.5 SEO técnico

| Métrica | Definición | Fuente |
|---|---|---|
| `requests_404` | Peticiones 404 de clientes | `seo_live_requests.status=404` |
| `broken_links` | Enlaces rotos detectados | `seo_broken_links.status_code>=400` |
| `pages_without_meta` | Páginas sin title | `seo_pages` |
| `sitemap_urls` | URLs en sitemap | `sitemap.xml` |
| `indexed_pages` | Páginas indexadas | Search Console (Fase 3) |
| `lcp_ms`, `inp_ms`, `cls` | Core Web Vitals | CrUX/PageSpeed/RUM (Fase 4) |

---

## 3. Deduplicación

Cada evento de `seo_tracking_events` lleva `event_id` único por sitio. El navegador lo genera y el servidor lo conserva. La tabla tiene índice único `(site_id, event_id)`; un reintento del mismo evento no se duplica.

- `purchase`: `purchase_<order_uuid>`.
- `page_view`: `evt_<timestamp>_<random>` generado una vez por evento.
- `cart_add`: `evt_...` generado en el clic.
- Los eventos históricos sin `event_id` conservan `NULL` y no colisionan.

---

## 4. Retención

| Tabla | Retención propuesta | Estado |
|---|---|---|
| `seo_live_requests` | 90 días | Ya implementada |
| `seo_tracking_events` | 365 días | Pendiente de activar |
| `analytics_views` | 365 días | Pendiente de activar |
| `tracking_events` | 365 días | Pendiente de activar |
| `store_search_log` | 365 días | Pendiente de activar |
| `seo_404` | 180 días | Pendiente de activar |
| `seo_daily_metrics` | 730 días | Agregados, no PII |

La retención se implementará con un servicio `SeoRetentionService` y ejecución programada. No se borrarán datos sin aprobación explícita de los períodos.

---

## 5. Calidad de datos

- `seo_live_requests` guarda IP cruda para seguridad; no se usa en métricas comerciales.
- `analytics_views` y `seo_tracking_events` guardan hash de IP.
- Los bots se excluyen de clientes y se muestran aparte.
- El tráfico interno se detecta por sesión admin, dispositivo firmado e IP/CIDR.
- Si una métrica no tiene fuente, se muestra “No disponible”, no “0”.

---

## 6. Comparación de períodos

Toda tarjeta del Resumen debe mostrar:

1. Valor actual.
2. Valor del período anterior equivalente.
3. Variación porcentual.
4. Fuente.
5. Última actualización.
6. Limitación, si existe.

La tabla `seo_daily_metrics` permite comparar 7/30/90 días sin escanear las tablas grandes.

---

## 7. Estado de implementación — Fase 2

Implementado y verificado:

- `seo_tracking_events` ampliada con `event_id`, `event_name`, `items_json`, `value_cents`, `currency`, `consent_snapshot`, `is_test`.
- Índice único `(site_id, event_id)` para deduplicar eventos del navegador.
- `SeoTrackingService` mapea eventos internos a nombres GA4 (`view_item`, `add_to_cart`, `begin_checkout`, `purchase`, etc.).
- `seo-track.js` envía `event_id` y registra `view_category`, `search`, `view_cart`, `whatsapp_click` y `contact_click`, además de los eventos existentes.
- `SeoInternalTrafficService::ensureSessionCookie()` crea `wwi_seo_sid` en la primera visita; el monitor de peticiones y los eventos comparten el mismo `session_id`.
- `SeoDailyMetricsService` crea y reconstruye `seo_daily_metrics`; `job-runner.php` lo ejecuta cada 10 minutos.
- `SeoAnalyticsService` separa vistas/visitantes de servidor de sesiones/embudo con consentimiento y evita dividir fuentes distintas.
- El prompt de IA del Brick se corrigió a **español (Colombia)**.
- Bing quedó desactivado por clave inválida.

Pendiente de Fase 2:

- Los datos históricos sin `session_id` no se usan para sesiones; la métrica canónica de sesiones es el consentimiento. No se reescribe historial.
- Instrumentar `add_shipping_info`, `add_payment_info` y `remove_from_cart` en el checkout/carrito.
- Retención programada de `analytics_views`, `seo_tracking_events`, `tracking_events` y `store_search_log`.
- Medir Core Web Vitals reales con RUM propio; PageSpeed está bloqueado por cuota 0/día del proyecto Google.
- Revisar los 7 pedidos fallidos y la atribución nula de los 11 pedidos.

---

## 8. Fase 4 — SEO técnico, datos estructurados y sitemap

Implementado y verificado:

### 8.1 URL canónica

- Setting `seo_site_url` = `https://sonika.com.co`.
- `SeoGlobalLaunchBrick::siteUrl()` y `SeoIntegrationService::siteUrl()` lo priorizan, así que CLI, cron, sitemap y canonical usan el dominio público y no `APP_URL` interno.
- El panel permite editarlo en **Integraciones → URL canónica del sitio**.

### 8.2 Sitemap

- Incluye el catálogo público: **3.436 URLs** (3.318 productos + 110 categorías + 8 páginas) frente a 118 antes.
- Excluye productos de prueba mediante `sitemap_exclude` (hoy `punto-test`).
- Normaliza `/home` a `/` y deduplica URLs.
- Verificado en vivo: `/sitemap.xml` con 3.436 `<loc>` y 3.318 productos.

### 8.3 Datos estructurados

- Home: `Organization` + `WebSite` + `SearchAction`.
- Categoría: `CollectionPage` + `ItemList` + `BreadcrumbList`.
- Producto: `Product` + `Offer` + `Brand` + `BreadcrumbList`, con SKU, precio, moneda, disponibilidad e imagen propia.
- Verificado con HTML en vivo: 2 bloques JSON-LD en home, categoría y producto.

### 8.4 Metadatos de producto

- Título: `seo_title` o nombre; sin sufijo de marca si supera 55 caracteres.
- Descripción: cuerpo (≥60 caracteres) → `seo_description` truncado a 155 → fallback nombre/marca + CTA.
- OG image: primera imagen del producto (antes era el logo genérico del sitio).

### 8.5 Auditoría real

- `scan()` audita 3.437 URLs (8 páginas, 110 categorías, 3.319 productos, 0 posts) y guarda hallazgos en `seo_audit_issues`.
- Scores ponderados: meta 30 %, contenido 20 %, schema 20 %, OG 10 %, técnico 20 %. Ya no hay `technical_score = 90` fijo ni `total = 100 − issues × 3`.
- Técnico incluye: muestra HTTP real (home/categoría/producto), canonical, cobertura del sitemap, robots, tasa de 404 de 7 días y latencia promedio.
- Resultado actual: **score 89** · meta 75 · contenido 88 · schema 100 · OG 100 · técnico 93 · 3.437 URLs · **0,71 % de 404** · **1.792 ms** de latencia.

### 8.6 Redirecciones gestionadas

- `/home`, `/inicio`, `/index.php` → `/`.
- `/envios` → `/politica-de-envios`; `/privacy`, `/privacidad` → `/politica-de-privacidad`.
- `/login`, `/register`, `/forgot-password`, `/reset-password` → `/cuenta`.
- `/contact` → `/soporte`; `/blog/`, `/news/` → `/`; `/products.json`, `/collections/all` → `/tienda`.
- `/favicon.ico`, `/apple-touch-icon.png` → isotipo PNG.
- Las sondas de seguridad (`wp-`, `.env`, `phpinfo`, credenciales, terraform, etc.) se clasifican como bots y no contaminan la tasa de 404.

### 8.7 PageSpeed

- El key configurado responde **HTTP 429**: cuota `0/día` en el proyecto Google `583797351490`. No es un problema de red ni de la clave.
- Para activarlo: habilitar PageSpeed Insights API en ese proyecto y ampliar la cuota, o crear una clave en un proyecto con cuota disponible.
- Mientras tanto, Core Web Vitals se medirá con RUM propio (pendiente).

### 8.8 Calidad de catálogo corregida

- **311 productos** con slug `-copia` se migraron a un slug derivado de su nombre. El nombre, SKU, imagen y descripción eran coherentes entre sí; el handle del catálogo de origen era el campo desalineado.
- Respaldo completo en `seo_slug_migrations` (`product_id`, `old_slug`, `new_slug`). `catalog_handle` se conserva como clave de sincronización con la fuente.
- **311 redirecciones 301** `/producto/{old}` → `/producto/{new}`, conservando `?ref=` y parámetros UTM.
- El carrito resuelve handles antiguos mediante `catalog_handle`; la sincronización de catálogo indexa por `slug` y `catalog_handle` para no duplicar productos.
- **21 guitarras eléctricas** que estaban en `Guitarras Electroacústicas` se reasignaron a `Guitarras Eléctricas` (categoría 34, tags y mapa de taxonomía) y se bloquearon para que una reconstrucción no las revierta. Electroacústicas queda con 41 productos y Eléctricas con 233.
- El mapa de taxonomía se remapeó (310 entradas) y se respaldó en `taxonomy_map.json.bak-20261117`. Los cambios de categoría quedaron registrados en `seo_catalog_fixes`.
- Rollback: `seo_slug_migrations` permite revertir slugs; `seo_catalog_fixes` conserva el antes/después de la reclasificación.

### 8.9 Accesos externos

- **Search Console:** propiedad corregida a `https://sonika.com.co/`; cuenta de servicio `sonika-search-console@titanium-web-468409-d8.iam.gserviceaccount.com`. Falta autorizarla como usuario en Google para que `gscTest` devuelva propiedades.
- **Bing:** desactivado; clave inválida.
- **Meta Ads / CAPI:** pendiente de `act_...`, token `ads_read`, token CAPI y confirmación del Pixel.
- **GA4:** al final, sin Measurement ID.

---

## 9. RUM — Core Web Vitals reales

Implementado:

- Endpoint público `POST /api/v1/public/seo/vitals`.
- Tabla `seo_vitals` sin IP ni user-agent: `page_url`, `metric`, `value`, `rating`, `device`, `connection`, `is_test`, `created_at`.
- Solo se recoge con consentimiento de analítica (`sonika_cookie_consent`); los bots se descartan.
- El navegador envía al `pagehide`/`visibilitychange` con `sendBeacon`: LCP, INP (p98 de event timing), CLS acumulado, TTFB y FCP.
- El servidor calcula p75 por métrica, dispositivo y página con los umbrales oficiales: LCP 2,5/4 s; INP 200/500 ms; CLS 0,1/0,25; TTFB 0,8/1,8 s; FCP 1,8/3 s.
- Panel: **Técnico → Core Web Vitals reales (RUM)** con p75, muestras y páginas con peor LCP.
- Las pruebas usan `?rum_test=1` y se guardan con `is_test=1`; no entran en el resumen.
- Limitación: INP y CLS son aproximaciones del navegador; no sustituyen CrUX ni PageSpeed. PageSpeed sigue bloqueado por cuota 0/día del proyecto Google.

---

## 10. Google Search Console

- Pestaña **Google** en el panel.
- Endpoint admin `GET /api/v1/admin/seo-global-launch/gsc?days=28&dimension=query`.
- `SeoIntegrationService::gscSearchAnalytics()` consulta Search Analytics: clics, impresiones, CTR y posición por consulta, página, país, dispositivo o fecha.
- Requiere autorizar `sonika-search-console@titanium-web-468409-d8.iam.gserviceaccount.com` en Search Console → Configuración → Usuarios y permisos.
- Si la propiedad es de tipo Dominio, configurar `sc-domain:sonika.com.co` en Integraciones; si es prefijo de URL, `https://sonika.com.co/`.
- Sin acceso, la pestaña muestra el correo, el botón para abrir Search Console y un botón para reintentar; el resto del panel sigue funcionando.

---

## 11. Consolidación de host e indexación

Diagnóstico (18 de noviembre de 2026):

- Googlebot visitó el sitio más de 1.500 veces en 90 días y rastreó home, categorías y productos con HTTP 200. **El sitio sí está indexado**; el problema es consolidación y ranking, no ausencia.
- Google muestra mezcladas `www.sonika.com.co` y `sonika.com.co`, y también `/buscar` y `/pqrf`, porque cada host generaba su propio canonical y las páginas internas eran indexables.
- `sonika-com-co.wontia.com` también respondía 200 con canonical propio.

Correcciones implementadas:

- **301 de `www.sonika.com.co` a `https://sonika.com.co`** para GET/HEAD, conservando ruta y query. Las peticiones `/api/` y los POST no se redirigen para no romper webhooks.
- **Canonical HTML único** a `https://sonika.com.co`; `www` y los previews `*.wontia.com` se normalizan al host público.
- **`X-Robots-Tag: noindex, nofollow`** en previews `*.wontia.com`, sin bloquear su acceso.
- **`/buscar` y `/pqrf` con `noindex`**; se retiraron del `Disallow` para que Google pueda rastrearlos y procesar el noindex. Solo `/buscar/live` y `/buscar/sugerencias` (JSON) quedan bloqueados.
- `siteUrl()` con caché estática para no consultar la base en cada URL del sitemap.

Pendiente después de esto:

- Autorizar la cuenta de servicio en Search Console para inspeccionar cobertura, consultas y posición.
- Enviar el sitemap y solicitar indexación de home y categorías principales.
- Mejorar contenido único de productos/categorías: es el siguiente factor para rankear consultas comerciales.

---

## 12. Merchant Center e inspección de indexación

- **Feed para Google Merchant Center:** `https://sonika.com.co/google-merchant.xml`.
  - Generado desde `store_products` activos y públicos.
  - Campos: id, título, descripción, link, imagen principal y adicionales, disponibilidad, precio, precio de oferta, condición, marca, MPN/SKU, tipo de producto, categoría amplia de Google y etiquetas de categoría/marca.
  - Cache de 1 hora en `/app/cache/google-merchant.xml`.
  - Sin envío ni devoluciones en el feed: se configuran a nivel de cuenta en Merchant Center porque no hay pesos ni envío gratis por producto.
- **Inspector de URL** en la pestaña Google (`POST /api/v1/admin/seo-global-launch/gsc/inspect`):
  - Muestra cobertura, veredicto, canonical de Google, canonical del usuario, último rastreo, robots y estado de fetch.
- **Hallazgo del 18 de noviembre de 2026:** la home sin `www` estaba marcada como duplicada y Google había elegido `sonika-com-co.wontia.com` como canónica; la home `www` sí estaba indexada y los productos también apuntaban al host interno. Los 301/canonical/noindex ya corrigen la causa; falta el recrawl.
- **Datos de Search Console (28 días):** 10 clics / 40 impresiones en consultas; 23 clics / 97 impresiones en páginas; la consulta “sonika” concentra 10 clics en posición 2,3 y `/tienda` 14 clics en posición 3,8.

---

## 13. Contenido editorial de categorías — primer lote

- 10 categorías con introducción editorial de 300–437 palabras, almacenada en `store_categories.description`.
- La guía se muestra **debajo del grid de productos** para no afectar la conversión; el meta description usa el primer fragmento (máx. 155 caracteres) y el `CollectionPage` JSON-LD usa la misma descripción.
- Categorías: Guitarras Eléctricas, Guitarras Acústicas, Guitarras Electroacústicas, Baterías Acústicas, Acordeones, Pianos, Cabinas, Micrófonos Inalámbricos, Congas y Tumbadoras, Platillos.
- Revisión humana: se eliminaron marcas inventadas por la IA (`JINBAO`, `KOHLT`) y una mención incorrecta a `SABIAN` en Baterías; se suavizaron afirmaciones sobre especificaciones.
- `store_categories.description` pasó de `VARCHAR(500)` a `TEXT`; `install/store.sql` actualizado.
- Rollback: `seo_catalog_fixes` guarda el antes/después de las 10 categorías (antes estaban vacías).

---

## 14. Guías y FAQ en 50 productos top

- 50 productos seleccionados por vistas de página de los últimos 90 días; se excluyeron productos de prueba.
- Tabla `product_seo_content`: `intro`, `use_cases_json`, `faq_json`, `meta_description`, `status`, `source`. **No reemplaza** la descripción original del fabricante.
- La ficha muestra una **Guía rápida** debajo de la descripción, con casos de uso y preguntas frecuentes; el `meta description` usa el texto generado y se añade `FAQPage` JSON-LD. El `Product` JSON-LD usa la introducción completa.
- Generación con DeepSeek desde datos reales del producto; validaciones: introducción ≥90 palabras, 4 casos de uso, 3 FAQ y meta ≥60 caracteres. Revisión por muestreo (5 fichas) y escaneo de afirmaciones sensibles.
- Corrección detectada: el producto 3029 tenía nombre “NEGRA” y slug “azul”; se eliminó el color del texto generado.
- Rollback: cambiar `status` a `draft` o borrar la fila; la descripción original nunca se modifica.
- Estado: **50 publicados**. El bloque es complementario: si se elimina, la ficha sigue funcionando con su contenido original.
- Enlaces internos en las 10 guías de categoría: bloque **Explora también** con categorías hermanas y **Productos recomendados** con 3 fichas (primero las que tienen guía publicada; si faltan, las más vistas en 90 días, con caché de 6 h en `/app/cache`).
- Imagen desplegada: `wontia-web-intelligence:products-20261118c`; rollback `pre-products-20261118c` (`products-20261118b`) y `pre-products-20261118` (`categories-20261118`).

