# Mercado Pago — estado y activación

**Sitio:** SONIKA (`https://sonika.com.co`)
**Estado:** backend desplegado, tablas creadas y **Mercado Pago activo en producción** con Checkout Pro.
**Pendiente de seguridad:** configurar la clave secreta de webhooks para validar la firma HMAC.

---

## 1. Qué se completó

- Servicio `MercadoPagoService`.
- Controlador público:
  - Configuración.
  - Webhook.
  - OAuth callback.
  - Estado del pago.
  - Preferencia.
  - Pago transparente.
- Controlador de administración:
  - Resumen.
  - Credenciales.
  - Prueba de conexión.
  - Métodos y cuotas.
  - Pagos, sincronización y reembolsos.
  - OAuth.
- Brick `MercadoPagoBrick`.
- Tablas:
  - `mercadopago_payments`.
  - `mercadopago_webhooks`.
  - `mercadopago_oauth`.
- JS completo de pagos con:
  - Redirección de Checkout Pro.
  - Soporte preparado para Checkout API/brick.
- Estilos del brick.

---

## 2. Cómo activarlo

1. Entrar a **Admin → Mercado Pago**.
2. Ambiente: **Producción** (o **Sandbox** si vas a probar primero).
3. Modalidad recomendada: **Checkout Pro (redirección)**.
4. Credenciales:
   - Opción A: conectar la cuenta con **OAuth**.
   - Opción B: pegar **Client ID**, **Client Secret** y **Public Key**.
   - Opcional: **Access Token** y **Webhook Secret**.
5. Guardar.
6. Pulsar **Probar conexión**.
7. Copiar la **Webhook URL** que muestra el panel y configurarla en Mercado Pago:
   ```text
   https://sonika.com.co/api/v1/public/mercadopago/webhook
   ```
8. Si usas OAuth, registrar también la **Redirect URI**:
   ```text
   https://sonika.com.co/api/v1/public/mercadopago/oauth/callback
   ```
9. Copiar la **clave secreta de webhooks** desde Mercado Pago y guardarla en el panel de SONIKA.
10. Activar **Mercado Pago** y guardar.
11. Hacer una compra real de bajo valor para validar el flujo completo.
12. Confirmar que el webhook actualiza el pedido a pagado y que el panel concilia el pago.

---

## 3. Flujo Checkout Pro

1. El cliente elige Mercado Pago en el checkout.
2. SONIKA crea el pedido.
3. `MercadoPagoService::createPreference()` genera la preferencia.
4. El pedido guarda `payment_reference = MP-...` y `payment_link = redirect_url`.
5. `sonika-payments.js` redirige al cliente a Mercado Pago.
6. Mercado Pago envía el webhook.
7. El webhook actualiza el pago, el pedido y el estado local.
8. El panel de Mercado Pago permite sincronizar y reembolsar.

---

## 4. Checkout API / Brick

El código del brick está preparado, pero el checkout actual usa la tarjeta de redirección de Checkout Pro y no renderiza `#mpBrickContainer`. Por eso:

- **Usa Checkout Pro por ahora.**
- No selecciones Checkout API hasta integrar el contenedor del brick en el checkout.

---

## 5. Estado actual

- `mercadopago_enabled = 1`.
- Ambiente: **Producción**.
- Modalidad: **Checkout Pro (redirección)**.
- Cuenta conectada: **INTSOLCOM** (`site_id = MCO`, Colombia).
- Credenciales de producción cargadas y cifradas:
  - Client ID.
  - Client Secret.
  - Public Key.
  - Access Token.
- `publicMethod()` devuelve Mercado Pago y el checkout lo muestra.
- La creación de preferencias de producción fue validada con un pedido de prueba; el registro local se eliminó después.
- No se ha completado un pago real de extremo a extremo.
- La clave secreta de webhooks está pendiente: los eventos se procesan, pero todavía no se valida la firma HMAC.

---

## 6. Seguridad

- Los secretos se guardan cifrados con AES-256-GCM mediante `TwoFactorService`.
- Los secretos no se envían al frontend.
- El webhook valida firma cuando hay `Webhook Secret`.
- Los pagos se concilian localmente en `mercadopago_payments`.
- Los webhooks se registran en `mercadopago_webhooks`.

---

## 7. Archivos

### Nuevos

- `src/Services/MercadoPagoService.php`
- `src/Controllers/MercadoPagoController.php`
- `src/Controllers/Admin/MercadoPagoController.php`
- `src/Bricks/MercadoPago/MercadoPagoBrick.php`
- `src/Bricks/MercadoPago/brick.json`
- `install/mercadopago.sql`
- `docs/MERCADOPAGO.md`

### Modificados

- `public/assets/js/sonika-payments.js` — redirección Checkout Pro y brick.
- `public/assets/themes/sonika/payments.css` — estilos del brick.
- `public/api.php` — rutas públicas y de administración.
- `src/Services/StoreService.php` — creación de preferencia y estado del pedido.
- `templates/themes/sonika/lib.php` — método público en el checkout.
- `public/assets/js/admin.js` — panel de Mercado Pago y acceso en el menú.

---

## 8. Rollback

- Desactivar Mercado Pago en el panel.
- Restaurar el respaldo:
  - `/tmp/mercadopago-backup-20261112.tgz`
- Opcional: eliminar las tablas `mercadopago_*` si se quiere borrar el historial local.

---

## 9. Logo de Mercado Pago en el checkout

El logo se guarda en el setting `store_payment_logos` con la clave nativa:

```json
{"mercadopago":"/assets/uploads/logo-mercado-pago.png"}
```

El checkout lo lee en `sonika_payment_method_cards()` y lo muestra en el carrito y en el checkout. Si el logo se guarda con una clave de método manual, por ejemplo `custom-mercado-pago`, no aparece porque el método real se llama `mercadopago`. La versión actual incluye compatibilidad con esas claves antiguas.

Los métodos nativos de la tienda (`wompi`, `cod`, `manual`) solo aparecen en el administrador de logos cuando están activos en **Tienda → Configuración → Métodos de pago**. Al desmarcarlos y guardar, desaparecen del listado de logos; **Quitar logo** únicamente borra la imagen.

---

## 10. PSE y transferencia bancaria

Para que PSE y transferencia bancaria (incluida Bancolombia) funcionen en Checkout Pro:

- **No se envía `binary_mode=true`** en la preferencia. El modo binario solo acepta pagos aprobados o rechazados y rechaza automáticamente los pagos pendientes, que es el estado normal de PSE y transferencias.
- El modo binario se conserva únicamente para Checkout API, donde el pago se procesa en el sitio.
- La preferencia envía nombre, apellido, correo, documento, teléfono y dirección del comprador para completar los datos de PSE.
- La cuenta INTSOLCOM tiene PSE activo (`payment_type_id = bank_transfer`).

Las preferencias creadas antes de este ajuste conservan `binary_mode=true`. Para probar PSE/transferencia se debe **crear un pedido nuevo** desde el checkout; el pedido anterior puede seguir mostrando el botón deshabilitado.
