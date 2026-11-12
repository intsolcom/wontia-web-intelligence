# Mercado Pago — estado y activación

**Sitio:** SONIKA (`https://sonika.com.co`)
**Estado:** backend desplegado, tablas creadas y pagos **desactivados** hasta configurar credenciales.

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
2. Ambiente: **Pruebas (sandbox)**.
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
9. Activar **Mercado Pago** y guardar.
10. Probar un pedido de bajo valor en sandbox.
11. Cuando esté validado, cambiar a **Producción** y repetir la prueba.

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

- `mercadopago_enabled = 0`.
- Sin credenciales.
- `publicMethod()` devuelve `null`.
- El checkout mantiene sus métodos actuales.
- No hay cobros ni pedidos de Mercado Pago.
- El panel de administración ya responde correctamente y muestra el diagnóstico de credenciales.

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
