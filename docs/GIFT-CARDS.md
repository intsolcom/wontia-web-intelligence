# Tarjetas de regalo SONIKA

Estado: **Fase 1 implementada** (compra digital, código + PIN, canje total/parcial, entrega inmediata o programada y administración).

## 1. Experiencia del cliente

### Compra
1. El cliente entra a `/tarjetas-de-regalo`.
2. Elige una ocasión:
   - Cumpleaños.
   - Grado / Graduación.
   - Navidad.
   - Día de la Madre.
   - Día del Padre.
   - Amor y Amistad.
   - Día del Músico.
   - Patrocinio al talento.
   - Regalo libre.
   - Quinceañera.
   - Aniversario.
   - Baby shower.
   - Agradecimiento.
   - Condolencias.
   - Regalo corporativo.
   - Día del Niño.
   - Día de las Velitas.
   - Año Nuevo.
3. Define monto, remitente, destinatario, mensaje y fecha de entrega.
4. Paga con Mercado Pago, Bre-B, ePayco (si está activo) o pago asistido.
5. Al confirmarse el pago, el sistema emite la tarjeta y envía código + PIN al destinatario. También envía copia al comprador.

### Canje
- En `/carrito` y `/checkout` hay un bloque **Tarjeta de regalo**.
- El cliente ingresa código + PIN y pulsa **Aplicar**.
- El saldo se aplica al total, total o parcialmente.
- Si el saldo cubre todo, el pedido queda pagado sin cobro adicional.
- Si no cubre todo, paga la diferencia con el método elegido.
- Si el pedido falla, se cancela o vence la reserva, el saldo se devuelve automáticamente.

### Consulta de saldo
- `/tarjetas-de-regalo#consultar` o `/tarjetas-de-regalo/consultar?code=...`.
- Muestra saldo, vencimiento, ocasión y últimos movimientos.
- Requiere código + PIN. Tiene límite de intentos por IP y por tarjeta.

## 2. Modelo de datos

| Tabla | Uso |
|---|---|
| `gift_card_occasions` | Ocasiones, colores, íconos, montos sugeridos y mensaje por defecto. |
| `gift_cards` | Tarjeta: código, PIN cifrado/hash, saldo, estado, destinatario, entrega y vencimiento. |
| `gift_card_transactions` | Historial de emisión, uso, liberación, anulación y ajustes. |
| `gift_card_redemptions` | Reserva del saldo por pedido. Estados: `reserved`, `completed`, `released`. |
| `gift_card_purchases` | Pedido de compra de tarjeta y datos de personalización. |
| `gift_card_attempts` | Control de intentos para prevenir fuerza bruta. |

`store_orders` agrega:
- `gift_card_cents`: monto cubierto con tarjeta.
- `gift_card_code`: código usado (para trazabilidad).

## 3. Flujo técnico

### Compra
- `POST /api/v1/public/gift-cards/purchase`.
- Crea un pedido digital sin envío y un registro en `gift_card_purchases`.
- El pago se procesa con el método elegido.
- `StoreService::markPaid()` llama a `GiftCardService::onOrderPaid()`.
- Si el pedido es una compra de tarjeta, se emite la tarjeta y se programa/entrega el correo.

### Canje
- `POST /api/v1/public/checkout/quote` acepta `gift_card: {code, pin}`.
- `BrebService::quote()` valida y descuenta el saldo del total.
- `StoreService::createCatalogOrder()` reserva el saldo dentro de la transacción del pedido.
- `markPaid()` completa la reserva.
- `updateOrder()` con `failed`/`refunded`/`cancelled`, `releaseExpiredOrders()` y el job de reservas vencidas liberan el saldo.

### Entrega
- `public/job-runner.php` ejecuta:
  - `GiftCardService::deliverScheduled()`: envía tarjetas cuya fecha de entrega ya llegó.
  - `GiftCardService::expireReservations()`: libera reservas sin pago después de las horas configuradas.

## 4. Configuración

En **Admin → Tienda → Configuración → Tarjetas de regalo**:

- Activar/desactivar la venta.
- Monto mínimo y máximo (COP).
- Vigencia en días.
- Horas de reserva sin pago.
- URL de términos y condiciones.
- Emisión manual de tarjetas.
- Reenvío y anulación.
- Ocasiones: activar/desactivar, nombre, ícono y orden.

La compra de tarjetas usa los métodos habilitados en **Tienda → Configuración → Métodos de pago en el checkout** (Mercado Pago, Bre-B, ePayco y pago asistido). Ver `docs/PAYMENT-METHODS.md`.

Valores por defecto:
- Mínimo: **$10.000 COP**.
- Máximo: **$2.000.000 COP**.
- Vigencia: **365 días**.
- Reserva sin pago: **24 horas**.

## 5. Seguridad

- Código aleatorio `SNK-XXXX-XXXX-XXXX`.
- PIN de 6 dígitos.
- `pin_hash` con `password_hash`.
- `pin_enc` con AES-256-GCM y secreto propio (`gift_card_secret`).
- Límite de intentos: 12 por IP / 15 min y 5 por tarjeta / 15 min.
- El PIN nunca se muestra en listados administrativos.
- El código completo solo se muestra al comprador/destinatario en el pedido pagado y en el correo.
- No se puede usar una tarjeta anulada o vencida.
- El saldo se reserva con `SELECT ... FOR UPDATE` para evitar doble gasto.

## 6. Límites de la Fase 1

- Una tarjeta por pedido (cantidad = 1).
- Entrega digital por correo.
- Sin tarjeta física.
- Sin recarga de saldo.
- Sin tarjeta colaborativa/grupal.
- Sin mensaje de voz o video.
- Sin diseño visual subido por el cliente (se usan gradientes e íconos por ocasión).

Estas funciones quedan para Fase 2/3.

## 7. Pruebas recomendadas

1. Crear una tarjeta de prueba por $10.000 con Mercado Pago en modo producción/prueba.
2. Confirmar el pago y verificar:
   - Pedido en `store_orders` con `payment_status = paid`.
   - Tarjeta en `gift_cards` con saldo.
   - Correo al destinatario y copia al comprador.
3. Aplicar la tarjeta en `/checkout`:
   - Cobertura parcial.
   - Cobertura total.
4. Cancelar un pedido con saldo reservado y verificar la devolución.
5. Consultar saldo en `/tarjetas-de-regalo#consultar`.
6. Anular una tarjeta desde el admin y verificar que no se pueda canjear.
