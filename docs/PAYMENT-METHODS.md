# Métodos de pago visibles en el checkout

## Qué hace

Permite controlar desde **Admin → Tienda → Configuración → Métodos de pago en el checkout**:

- Mostrar u ocultar cada método.
- Ordenar los métodos (menor número aparece primero).
- Cambiar la etiqueta visible.
- Agregar un badge comercial (`3% OFF`, `Recomendado`, `Cuotas`, etc.).
- Elegir el método predeterminado.
- Programar una ventana de visibilidad con fecha "desde" y "hasta".

Métodos detectados:

- Mercado Pago.
- Bre-B.
- Addi.
- Sistecrédito.
- ePayco.
- Pago asistido por WhatsApp.

## Comportamiento

- Si nunca se ha guardado configuración, se muestran automáticamente todos los métodos disponibles.
- Al guardar, el sistema usa una lista explícita: solo aparecen los métodos marcados.
- Un método no configurado o no disponible se oculta automáticamente.
- Si todos los métodos configurados dejan de estar disponibles, el sistema activa un método de respaldo para no bloquear la compra.
- Se exige al menos un método disponible habilitado.
- La programación usa la zona horaria **America/Bogota**.
- El checkout y el pedido validan la misma lista: un método oculto no puede usarse aunque se envíe manualmente por API.

## Persistencia

Configuración en `settings`:

| Clave | Contenido |
|---|---|
| `store_checkout_methods` | JSON con métodos, visibilidad, etiqueta, badge, orden y fechas. |
| `store_checkout_default_method` | ID del método predeterminado. |

Ejemplo:

```json
[
  {
    "id": "mercadopago",
    "enabled": 1,
    "label": "Tarjeta o PSE",
    "badge": "Hasta 12 cuotas",
    "sort": 10,
    "from": "",
    "to": ""
  },
  {
    "id": "breb",
    "enabled": 1,
    "label": "",
    "badge": "3% OFF",
    "sort": 20,
    "from": "2026-11-20 00:00:00",
    "to": "2026-11-30 23:59:59"
  }
]
```

## Archivos

- `src/Services/CheckoutPaymentMethodService.php`
- `src/Controllers/Admin/StoreController.php`
- `public/api.php`
- `public/assets/js/admin.js`
- `templates/themes/sonika/lib.php`
- `public/assets/js/sonika-payments.js`
- `src/Services/StoreService.php`
- `src/Services/GiftCardService.php`

## Pruebas recomendadas

1. En **Tienda → Configuración → Métodos de pago en el checkout**, oculta Bre-B y guarda.
2. Abre `/checkout` en una ventana privada: Bre-B no debe aparecer.
3. Intenta crear un pedido con `payment_method=breb` por API: debe rechazarse.
4. Programa un método con fecha futura: no debe aparecer hasta la fecha.
5. Deja solo un método visible y confirma que el checkout sigue funcionando.
