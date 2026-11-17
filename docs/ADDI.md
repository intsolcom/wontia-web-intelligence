# Addi · pago a cuotas

Integración de Addi como método de pago en el checkout de SONIKA. Addi origina un crédito a nombre del cliente y redirige a su flujo de originación; SONIKA recibe el resultado por webhook y por la página de retorno.

## 1. Flujo

1. El checkout consulta `GET /api/v1/public/addi/config?amount_cents=...`.
2. Si el monto está entre `minAmount` y `maxAmount`, y `isActiveAlly=true`, Addi se habilita.
3. Al confirmar el pedido, `StoreService` valida de nuevo el monto/estado contra Addi y llama a `POST https://api.addi.com/v1/online-applications`.
4. Addi responde `301` con `Location`; SONIKA guarda el intento y redirige al cliente a esa URL.
5. Addi envía el resultado a `POST /api/v1/public/addi/callback` con Basic Auth.
6. El pedido se marca pagado si `status=APPROVED` y `approvedAmount` coincide con el total.
7. El cliente vuelve a `/pedido/{uuid}`; la página consulta `/api/v1/public/addi/status` y se actualiza.

## 2. Configuración

### Settings

| Clave | Descripción |
|---|---|
| `addi_enabled` | `1` muestra el método (si está configurado). |
| `addi_environment` | `production` o `staging`. |
| `addi_ally_slug` | Slug del aliado. En producción SONIKA es **`sonikacomco-ecommerce`**. |
| `addi_client_id` | Client ID de Addi. |
| `addi_client_secret` | Client secret cifrado con AES-256-GCM (`TwoFactorService`). |
| `addi_callback_user` | Usuario de Basic Auth del webhook. |
| `addi_callback_password` | Contraseña de Basic Auth del webhook, cifrada. |
| `addi_callback_url` | URL pública del webhook. |
| `addi_redirection_url` | URL de retorno; acepta `{order_id}`. |
| `addi_force_visible` | Muestra Addi aunque el aliado esté inactivo (para pruebas). |
| `addi_widget_enabled` | Muestra el widget `<addi-widget>` en fichas de producto. |
| `addi_last_config` | Última respuesta del endpoint de configuración. |

### Entornos

| Entorno | Auth | API | Audience |
|---|---|---|---|
| Producción | `https://auth.addi.com` | `https://api.addi.com` | `https://api.addi.com` |
| Staging | `https://auth.addi-staging.com` | `https://api.addi-staging.com` | `https://api.staging.addi.com` |

Las credenciales de staging se solicitan a `soporte_aliados@addi.com`. Las de producción son únicas y se entregan tras la certificación.

## 3. Endpoints

### Configuración del aliado

```http
GET https://channels-public-api.addi.com/allies/{ally_slug}/config?requestedamount={monto_en_pesos}
```

Devuelve `minAmount`, `maxAmount`, `policy`, `widgetConfig`, `isActiveAlly` e `isActivePayNow`. El descuento de `policy.discount` es **visual**: no se resta del total antes de crear la solicitud; Addi lo aplica internamente.

### Autenticación

`POST {auth}/oauth/token` con `grant_type=client_credentials`, `client_id`, `client_secret` y `audience`. El token se genera **por cada intento** de transacción.

### Crear solicitud

```http
POST https://api.addi.com/v1/online-applications
Authorization: Bearer {token}
Content-Type: application/json
```

Campos principales: `orderId`, `totalAmount`, `shippingAmount`, `totalTaxesAmount`, `currency`, `client`, `shippingAddress`, `billingAddress`, `items` y `allyUrlRedirection` (`callbackUrl`, `redirectionUrl`, `logoUrl`). Addi responde `301` con `Location`; no se debe seguir automáticamente.

### Webhook

```http
POST https://sonika.com.co/api/v1/public/addi/callback
Authorization: Basic {user}:{password}
```

Estados: `APPROVED`, `PENDING`, `REJECTED`, `DECLINED`, `ABANDONED`, `INTERNAL_ERROR`. El endpoint debe responder `200` y **devolver exactamente el mismo JSON** recibido. Si falla, Addi reintenta cada 30 minutos durante 24 horas.

### Cancelación

```http
POST https://api.addi.com/v1/online-applications/cancellations
Authorization: Bearer {token}
```

Solo para transacciones aprobadas. Una sola cancelación por transacción (total o parcial). Para una segunda cancelación parcial hay que escribir a `soporte_aliados@addi.com`.

### Estado para la página de pedido

```http
GET /api/v1/public/addi/status?uuid={order_uuid}
```

Devuelve `payment_status`, `addi_status`, `redirect_url`, `addi_order_id` y `application_id`.

## 4. Modelo de intentos

Addi exige un `orderId` **único por intento**. No se reutiliza el UUID del pedido: si el cliente abandona y vuelve a intentar, se genera un `addi_order_id` nuevo y se conserva el `order_uuid` para asociarlo al pedido.

Tabla `addi_transactions`:

| Campo | Uso |
|---|---|
| `order_uuid` | Pedido de SONIKA. |
| `addi_order_id` | `orderId` enviado a Addi; único por intento. |
| `application_id` | ID que devuelve Addi en el callback. |
| `status` | `CREATED`, `REDIRECT`, `PENDING`, `APPROVED`, etc. |
| `amount_cents` | Total del pedido. |
| `approved_amount_cents` | Monto aprobado por Addi. |
| `redirect_url` | URL de originación de Addi. |
| `callback_json` | Payload exacto del webhook. |

El callback busca por `addi_order_id`, no por UUID del pedido.

## 5. Reglas de negocio

- **Monto:** se envía en pesos colombianos, como número/decimal. El total es el del pedido (incluye IVA y envío); no se aplica el descuento de Addi antes de enviar.
- **Aprobación:** `approvedAmount` debe ser igual al total. Si no coincide, el intento queda `AMOUNT_MISMATCH` y **no** se marca pagado.
- **Aprobación parcial:** Addi no hace aprobaciones parciales.
- **Expiración:** la solicitud expira en 2 horas.
- **Moneda/documento:** COP y persona natural con cédula (CC).
- **Idempotencia:** el `orderId` único y `X-Idempotency-Key` permiten reintentos seguros.
- **Reintentos:** un nuevo intento debe generar un nuevo `orderId`; no reutilizar el anterior.

## 6. Activación

Antes de mostrar Addi a clientes:

1. Addi debe reportar `isActiveAlly=true` para `sonikacomco-ecommerce`.
2. Configurar el usuario y la contraseña de notificación en **Admin → Tienda → Addi**.
3. Activar `addi_enabled`.
4. Activar Addi en **Admin → Tienda → Configuración → Métodos de pago en el checkout**.
5. Verificar `GET /api/v1/admin/addi/overview`: ally activo, mínimo/máximo correctos y Auth OK.
6. Hacer una compra de prueba entre `minAmount` y `maxAmount` y confirmar el callback.

Si el aliado está inactivo, el checkout muestra Addi deshabilitado con el aviso “Addi no está activo para este comercio todavía”. Para pruebas internas se puede activar `Forzar visible`, pero el pedido será rechazado por la validación del servidor mientras `isActiveAlly=false`.

## 7. Pruebas

```bash
# Config pública del aliado
curl -s 'https://channels-public-api.addi.com/allies/sonikacomco-ecommerce/config?requestedamount=100000'

# Webhook (Basic Auth) — debe devolver el mismo JSON
curl -i -u 'usuario:password' \
  -H 'Content-Type: application/json' \
  -d '{"orderId":"SONIKA-...","status":"PENDING","approvedAmount":0}' \
  https://sonika.com.co/api/v1/public/addi/callback
```

En el admin:

- **Probar conexión** valida config + Auth.
- **Transacciones Addi** muestra cada intento y permite cancelar los aprobados.

## 8. Archivos

- `src/Services/AddiService.php`
- `src/Controllers/AddiController.php`
- `src/Controllers/Admin/AddiController.php`
- `src/Services/StoreService.php`
- `src/Services/BrebService.php`
- `public/api.php`
- `public/assets/js/sonika-payments.js`
- `public/assets/js/admin.js`
- `templates/themes/sonika/pedido.php`
- `templates/themes/sonika/router.php`
- `install/addi.sql`

## 9. Seguridad

- El client secret y la contraseña de callback se guardan cifrados; nunca se envían al frontend.
- El webhook valida Basic Auth con `hash_equals`.
- El callback solo procesa `orderId` registrados en `addi_transactions`.
- El monto aprobado se compara contra el total del pedido.
- Las credenciales no deben incluirse en commits ni en documentación pública.
