# WWI Tracking & Conversion Brick

**Versión:** 1.0.0
**Slug:** `tracking-conversion`
**Categoría:** marketing
**Entorno de validación inicial:** SONIKA (`https://sonika.com.co`)
**Estado:** implementado y desplegado; IDs y pruebas externas pendientes.

---

## 1. Objetivo

Brick universal de medición de marketing y conversión para sitios WWI. Provee:

- Meta Pixel: `PageView`, `ViewContent`, `AddToCart`, `InitiateCheckout`, `Purchase`.
- GA4 opcional: `page_view`, `view_item`, `add_to_cart`, `begin_checkout`, `purchase`.
- UTM first-touch y last-touch, `fbclid` y `gclid`.
- Atribución por pedido.
- Ledger interno de eventos y errores por plataforma.
- Debugger administrativo y health monitor.
- Consentimiento granular `analytics` / `marketing` y retiro de consentimiento.
- Arquitectura de adaptadores preparada para nuevas plataformas.

No crea una aplicación separada, no reemplaza el checkout y no modifica las tablas SEO existentes.

---

## 2. Arquitectura

```text
Página pública
  └─ CookieConsentService::render()
       ├─ banner de consentimiento granular
       └─ TrackingConversionService::footerHtml()
            ├─ window.WWI_TRACKING_CONFIG
            └─ /assets/js/tracking-conversion.js (defer)

Navegador
  ├─ PageView / ViewContent / AddToCart / InitiateCheckout
  ├─ valida consentimiento
  ├─ ejecuta fbq() y/o gtag()
  └─ envía beacon a /api/v1/public/tracking/event

Pedido
  ├─ StoreService::createOrder() / createCatalogOrder()
  │    ├─ guarda atribución first/last
  │    └─ crea borrador Purchase (pending_payment)
  ├─ StoreService::markPaid() / updateOrder(paid)
  │    └─ marca Purchase como ready
  └─ /pedido/{uuid}
       ├─ GET /api/v1/public/tracking/purchase/{uuid}
       ├─ dispara Purchase una sola vez
       └─ confirma dispatch al ledger
```

### Componentes

| Componente | Archivo |
|---|---|
| Definición del Brick | `src/Bricks/TrackingConversion/brick.json` |
| Ciclo de vida del Brick | `src/Bricks/TrackingConversion/TrackingConversionBrick.php` |
| Configuración, salud y orquestación | `src/Services/TrackingConversionService.php` |
| Ledger de eventos | `src/Services/TrackingEventService.php` |
| Atribución first/last | `src/Services/TrackingAttributionService.php` |
| Consentimiento | `src/Services/TrackingConsentService.php` |
| API pública | `src/Controllers/TrackingConversionController.php` |
| API admin | `src/Controllers/Admin/TrackingConversionController.php` |
| Motor de navegador | `public/assets/js/tracking-conversion.js` |
| Migración | `install/tracking_conversion.sql` |
| Panel admin | `public/admin.php` + `public/assets/js/admin.js` + `public/assets/css/tracking-conversion.css` |

### Persistencia

- Configuración: `settings` → `brick_config_tracking-conversion` (JSON).
- Ledger: `tracking_events`.
- Atribución por pedido: `tracking_order_attribution`.
- No se modifican `seo_tracking_events`, `analytics_views`, `seo_live_requests` ni las tablas de checkout.

---

## 3. Instalación

1. Desplegar los archivos del Brick.
2. Ejecutar la migración idempotente:

```bash
mysql ... < install/tracking_conversion.sql
```

También se crean automáticamente al abrir el panel o al activar el Brick.

3. Entrar al admin:

```text
/admin.php#tracking-conversion
```

4. Pulsar **Activar**.
5. Configurar Meta Pixel y/o GA4.
6. Guardar la configuración.

El Brick queda **desactivado por defecto**. No se envía ningún evento a Meta o Google hasta que se configure un ID y se active la plataforma.

### Instalación en BrickHub

El Brick se registra con:

- `slug`: `tracking-conversion`
- `brick_class`: `App\Bricks\TrackingConversion\TrackingConversionBrick`
- `installed_path`: `src/Bricks/TrackingConversion/`

La instalación en la tabla `bricks` no carga código automáticamente: la integración se invoca desde `CookieConsentService`, `StoreService` y la API.

---

## 4. Configuración

Panel: `#tracking-conversion`.

| Sección | Campos |
|---|---|
| Resumen | Salud de Meta, GA4 y UTM; eventos 24 h; compras 7 días; últimos eventos |
| Meta Pixel | Activar, Pixel ID, ambiente, test event code |
| GA4 | Activar, Measurement ID, debug |
| UTM | Activar, retención, tipo de `content_id`, moneda |
| Eventos | Activar/desactivar PageView, ViewContent, AddToCart, InitiateCheckout, Purchase |
| Debugger | Últimos eventos, plataforma, destino, estado, pedido, valor, params y error |
| Salud | Score interno, errores, avisos y acciones recomendadas |
| Privacidad | Consentimiento obligatorio, atribución por pedido, reapertura del banner |
| Avanzado | Debug, retención, dedup, reintento de Purchase, modo test, export/import |

### Valores por defecto

```json
{
  "enabled": false,
  "meta_enabled": false,
  "meta_pixel_id": "",
  "ga4_enabled": false,
  "ga4_measurement_id": "",
  "utm_enabled": true,
  "utm_retention_days": 90,
  "content_id_type": "sku",
  "currency": "COP",
  "event_page_view": true,
  "event_view_content": true,
  "event_add_to_cart": true,
  "event_initiate_checkout": true,
  "event_purchase": true,
  "consent_required": true
}
```

### Validación

- Meta Pixel ID: `8–20` dígitos numéricos.
- GA4 Measurement ID: `G-XXXXXXXXXX`.
- Moneda: ISO de 3 letras.
- `content_id_type`: `sku`, `product_id` o `handle`.
- Retención UTM: 1–365 días.
- No se guardan tokens ni secretos.

---

## 5. Meta Pixel

### Activación

1. Ingresar el Pixel ID en el panel.
2. Activar Meta Pixel.
3. Activar el interruptor maestro.
4. Verificar con Meta Pixel Helper y Events Manager → Test Events.

### Eventos

| Evento WWI | Evento Meta | Disparador |
|---|---|---|
| `page_view` | `PageView` | Carga de página |
| `view_content` | `ViewContent` | `/producto/{handle}` |
| `add_to_cart` | `AddToCart` | `sonika:cart-add`, después de agregar correctamente |
| `initiate_checkout` | `InitiateCheckout` | Entrada a `/checkout`, una vez por carrito |
| `purchase` | `Purchase` | Pedido con `payment_status = paid` |

### Parámetros

- `content_ids`
- `content_type` (`product`)
- `content_name`
- `value`
- `currency` (`COP` en SONIKA)
- `contents` (`id`, `quantity`, `item_price`)
- `num_items`

El identificador por defecto es **SKU**, con fallback a `product_id` y `handle`.

### Deduplicación

- Cada evento lleva `eventID` en Meta Pixel.
- `Purchase` usa `purchase_<order_uuid>` como ID determinístico.
- El servidor conserva una fila por `event_id` y plataforma.
- Un pedido ya despachado no vuelve a recibir `Purchase`.

### Limitaciones de Meta en v1.0

- No hay Conversions API ni server-side tracking.
- `test_event_code` se conserva para una futura integración CAPI; no sustituye Meta Pixel Helper.
- No se puede afirmar que Meta “aceptó” un evento solo porque `fbq()` se ejecutó.
- Un adblocker puede bloquear el script; el evento queda como `dispatched` o `failed` en el ledger según el caso.

---

## 6. GA4

### Activación

1. Ingresar el Measurement ID.
2. Activar GA4.
3. Activar el interruptor maestro.
4. Verificar en GA4 → Administrar → DebugView o Realtime.

### Eventos

| Evento WWI | Evento GA4 |
|---|---|
| `page_view` | `page_view` |
| `view_content` | `view_item` |
| `add_to_cart` | `add_to_cart` |
| `initiate_checkout` | `begin_checkout` |
| `purchase` | `purchase` |

### Parámetros

- `items` con `item_id`, `item_name`, `price`, `quantity`.
- `currency`
- `value`
- `transaction_id` en Purchase.
- `event_id` para trazabilidad interna.

### Migración desde GA4 legado

Si existe `ga_measurement_id` en `.env` o `settings` y el Brick está activo, `CookieConsentService` no inyecta el GA4 legado para evitar duplicados. El ID debe migrarse al panel del Brick.

---

## 7. UTM y atribución

### Cookie

`wwi_attr_v1`, primera parte, JSON:

```json
{
  "v": 1,
  "first": { "source": "...", "medium": "...", "campaign": "...", "fbclid": "...", "landing": "...", "ts": 0 },
  "last": { "source": "...", "medium": "...", "campaign": "...", "fbclid": "...", "gclid": "...", "landing": "...", "ts": 0 }
}
```

- `first` no se sobrescribe.
- `last` se actualiza con cada nuevo toque de campaña.
- Si el nuevo toque es de otra red, los click IDs anteriores no se conservan en `last`.
- No se guardan emails, teléfonos, direcciones ni datos de pago.
- La cookie solo se escribe con consentimiento `analytics`.
- Retención configurable; por defecto 90 días.

### Asociación al pedido

Al crear el pedido, `TrackingAttributionService` lee la cookie y guarda:

- first source/medium/campaign/content/term, `fbclid`, `gclid`, landing y fecha.
- last source/medium/campaign/content/term, `fbclid`, `gclid`, landing y fecha.
- snapshot de consentimiento.

El panel muestra los pedidos con atribución en **Privacidad**.

### Convención SONIKA

```text
utm_source=facebook
utm_medium=paid_social
utm_campaign=sonika_noviembre_2026
```

No está hardcodeada en el Brick; se configura en cada URL de campaña.

---

## 8. Eventos y ledger

### `tracking_events`

Una fila por evento y plataforma:

- `event_id`, `event_name`, `platform`, `destination_event`.
- `session_id`, `visitor_hash`, `order_uuid`, `order_number`.
- `consent_snapshot`.
- `params` JSON.
- `value_cents`, `currency`.
- `status`: `generated`, `dispatched`, `failed`, `skipped_no_consent`, `pending_payment`, `ready`, `cancelled`.
- `error`, `occurred_at`, `dispatched_at`.

### Flujo de Purchase

1. `createOrder` / `createCatalogOrder` crea un borrador con `status = pending_payment`.
2. `markPaid` o `updateOrder(paid)` lo pasa a `ready`.
3. La página `/pedido/{uuid}` consulta el payload.
4. El navegador dispara `Purchase` con `event_id = purchase_<uuid>`.
5. El navegador confirma `dispatched` o `failed` al ledger.

Si el usuario no vuelve a la página del pedido después de pagar, el `Purchase` de navegador no se envía. Esta es una limitación conocida de v1.0; se resolvería con CAPI/server-side en una fase futura.

---

## 9. Consentimiento y privacidad

### Categorías

- `analytics`: GA4 y UTM.
- `marketing`: Meta Pixel.
- `necessary`: siempre activo.

### Cookie

`sonika_cookie_consent`:

```json
{"necessary":true,"analytics":true,"marketing":false}
```

También se aceptan los valores legados `all` y `necessary`.

### UI

- Aceptar todo.
- Solo necesarias.
- Personalizar con checkboxes de analítica y marketing.
- Enlace **Preferencias de cookies** en el footer de SONIKA.
- Botón flotante **Cookies** después de otorgar consentimiento.
- `window.wontiaCookieOpen()` para reabrir.
- `window.WWITracking.revoke()` para detener tracking y borrar `wwi_attr_v1`.

### Reglas

- Sin consentimiento no se cargan scripts de Meta/GA.
- Sin consentimiento `analytics` no se escribe la cookie de atribución.
- Al retirar consentimiento se borra `wwi_attr_v1`.
- No se envían datos personales a Meta o Google.
- El `Purchase` se construye desde la base de datos, no desde precios del cliente.

---

## 10. Permisos y seguridad

- Rutas admin bajo `AuthMiddleware`.
- Lectura: `superadmin`, `admin`, `editor`.
- Escritura: `superadmin`, `admin`.
- Endpoints públicos con rate limit por IP.
- IDs de Meta/GA4 son públicos por diseño; no se guardan secretos.
- `Purchase` solo se despacha si el pedido está `paid`; el ack público no puede marcar un pedido pendiente.
- El ledger no almacena emails, teléfonos, direcciones, documentos, tokens ni datos de pago.
- `public/api.php` ya exponía todos los settings; no guardar secretos en `settings`.

---

## 11. Debugger y salud

### Debugger

Muestra:

- Evento.
- Plataforma.
- Destino.
- Estado.
- Pedido.
- Valor y moneda.
- Fecha.
- Params y error.

No muestra datos personales.

### Health Monitor

Estados:

- `connected`: configuración válida y evento despachado reciente.
- `configured_unverified`: configuración válida sin eventos recientes.
- `warning`: configuración incompleta o sin actividad.
- `error`: ID inválido o tabla faltante.
- `disabled`: plataforma apagada.

Diagnóstico incluido:

- Tablas.
- Interruptor maestro.
- Pixel ID.
- Measurement ID.
- Consentimiento.
- GA4 legado.
- Identificadores de producto.
- Eventos 24 h.
- Flujo de compra.

---

## 12. Pruebas realizadas

### Sintaxis

- `php -l` en todos los PHP nuevos y modificados.
- `node --check` en `tracking-conversion.js` y `admin.js`.

### Servicio

- Creación idempotente de tablas.
- Configuración por defecto desactivada.
- Guardado y lectura de configuración.
- Validación de Pixel ID y GA4 ID.
- Consentimiento `all`, `necessary` y JSON granular.
- Creación de borradores `Purchase` para Meta y GA4.
- Transición `pending_payment → ready`.
- Persistencia de `params`, SKU, `product_id`, precio y moneda.
- Registro de eventos de navegador.
- Diagnóstico y health.
- Limpieza de datos de prueba y restauración a desactivado.

### Navegador (harness local, sin red externa)

- Sin consentimiento: cero eventos Meta/GA.
- Con consentimiento: `PageView` en ambas plataformas.
- `ViewContent` con SKU correcto.
- `AddToCart` con cantidad y valor.
- `InitiateCheckout` una vez por carrito.
- `Purchase` con `event_id` determinístico.
- Deduplicación al recargar checkout.
- UTM first-touch y last-touch.
- Retiro de consentimiento detiene eventos y borra cookie.
- Panel admin: 9 secciones renderizan y `tcSave` recolecta la configuración.

### Producción

- Home/producto/checkout/admin: HTTP 200.
- Admin API sin sesión: HTTP 401.
- Brick desactivado: no se inyecta `tracking-conversion.js`.
- Banner con checkboxes y reapertura funciona.
- Producto expone `window.SONIKA_PRODUCT`, `data-sku`, `data-product-id`.
- Sin errores PHP en logs.

### Pendiente de verificación externa

- Pixel real con Meta Pixel Helper y Events Manager.
- GA4 real con DebugView/Realtime.
- Pedido pagado real E2E.
- Confirmación de recepción por Meta/Google (requiere CAPI/server-side o revisión manual en sus consolas).

---

## 13. Troubleshooting

| Problema | Causa probable | Acción |
|---|---|---|
| No se inyecta el script | Interruptor maestro apagado | Activar en `#tracking-conversion` |
| Meta no recibe eventos | Pixel ID inválido, adblocker o consentimiento marketing ausente | Revisar Health, Pixel Helper y consentimiento |
| GA4 no recibe eventos | Measurement ID inválido o consentimiento analytics ausente | Revisar DebugView y Health |
| `Purchase` no aparece | Pedido no pagado o el usuario no volvió a `/pedido/{uuid}` | Verificar `payment_status`, ledger y limitación v1 |
| `content_ids` vacío | SKU faltante y sin fallback | Configurar `product_id`/`handle` o completar SKU |
| Purchase duplicado | Reintento manual o ack fallido | Revisar `event_id` y estado en el ledger |
| Cookie de atribución ausente | Sin consentimiento analytics | Aceptar analítica y verificar `wwi_attr_v1` |
| GA4 duplicado | GA4 legado y Brick activos | Migrar el ID al Brick; no usar ambos |
| Eventos de prueba en producción | Se activó con consentimiento durante pruebas | Limpiar `tracking_events` del sitio |

---

## 14. Rollback

1. Panel → **Desactivar**. Deja de inyectar el script y de despachar eventos.
2. Si se requiere retirar el código, restaurar los archivos desde `/tmp/tc-backup-20261111.tgz` o el commit correspondiente.
3. Las tablas nuevas pueden conservarse para auditoría.
4. Para eliminarlas por completo:

```sql
DROP TABLE IF EXISTS tracking_events;
DROP TABLE IF EXISTS tracking_order_attribution;
```

5. Si se elimina el Brick y se desea volver al GA4 legado, restaurar `ga_measurement_id` en `.env` o `settings`.

---

## 15. Extensiones futuras

- Meta Conversions API server-side con el mismo `event_id`.
- TikTok Pixel y LinkedIn Insight Tag mediante adaptadores.
- Confirmación de recepción por plataforma.
- Reporte de atribución first/last con filtros y exportación.
- Reintento controlado de Purchase con ventana de deduplicación.
- Integración con catálogo de Meta para validar `content_ids`.
- Consent Mode v2 para GA4.
- Envío de Purchase aunque el usuario no vuelva a la página del pedido (server-side).

---

## 16. Archivos

### Nuevos

- `src/Bricks/TrackingConversion/brick.json`
- `src/Bricks/TrackingConversion/TrackingConversionBrick.php`
- `src/Services/TrackingConsentService.php`
- `src/Services/TrackingAttributionService.php`
- `src/Services/TrackingEventService.php`
- `src/Services/TrackingConversionService.php`
- `src/Controllers/TrackingConversionController.php`
- `src/Controllers/Admin/TrackingConversionController.php`
- `public/assets/js/tracking-conversion.js`
- `public/assets/css/tracking-conversion.css`
- `install/tracking_conversion.sql`
- `docs/TRACKING-CONVERSION-BRICK.md`

### Modificados

- `src/Services/CookieConsentService.php`
- `src/Services/StoreService.php`
- `src/Widgets/SonikaFooterWidget.php`
- `templates/themes/sonika/router.php`
- `templates/themes/sonika/lib.php`
- `public/api.php`
- `public/admin.php`
- `public/assets/js/admin.js`

### No modificados

- Checkout, pagos, autenticación, carrito, SEO tracking existente, partners, Hero, TIA.
