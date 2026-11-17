# Envíos y entregas

## 1. Umbral de envío gratis

El umbral se configura en **Tienda → Configuración → Bre-B / envíos** como `breb_free_shipping_net_cents`.

Valor actual:

- **$300.000 COP antes de IVA**.
- Equivale a **$357.000 COP con IVA (19%)**.

La comparación se hace contra el subtotal **neto antes de IVA** de los productos. Por eso:

| Compra con IVA | Neto antes de IVA | ¿Envío gratis? | Costo de envío |
|---|---:|---|---:|
| $300.000 | $252.101 | No | $15.000 |
| $357.000 | $300.000 | Sí | $0 |
| $400.000 | $336.134 | Sí | $0 |

El carrito y el checkout muestran ambos valores para evitar ambigüedad:

> Envío gratis desde $300.000 antes de IVA ($357.000 con IVA).

Y cuando falta dinero:

> Te faltan $X antes de IVA ($Y con IVA) para obtener envío gratis.

### Si el umbral debe ser sobre el total con IVA

Si la promoción es "envío gratis desde $300.000 con IVA", el setting debe cambiarse a **$252.100,84 netos** (redondeado a `25210100` centavos). Es importante definir la regla antes de cambiarlo.

## 2. Barranquilla y Atlántico

Se crearon tarifas específicas:

| Tarifa | Cobertura | Costo | Entrega |
|---|---|---:|---|
| Barranquilla — 1 a 2 días | Municipio 08001 | $15.000 | 1-2 días hábiles |
| Atlántico — 1 a 2 días | Departamento 08 (excepto Barranquilla) | $15.000 | 1-2 días hábiles |
| Nacional | Resto de Colombia | $15.000 | 2-5 días hábiles |

La tarifa municipal tiene prioridad sobre la departamental y la nacional.

### Artículos de gran tamaño

El checkout calcula el peso total del carrito usando `store_products.weight_grams`. Si el destino es Barranquilla y el peso total es **igual o mayor a 20 kg**, la entrega se muestra como:

- **2 días hábiles**.
- Nota: "Artículo de gran tamaño: la entrega en Barranquilla se coordina en 2 días hábiles."

Si el peso no está configurado en el producto, se asume 0 y aplica la promesa general de 1-2 días.

## 3. Envío gratis por producto

Cada producto tiene el campo `free_shipping` (checkbox **Cobrar flete o transporte a este producto** en el editor; si se desmarca, el producto no paga flete).

Reglas:

- Si **todos** los productos del carrito tienen `free_shipping = 1`, el envío es **$0**.
- Si **algunos** productos tienen envío gratis, esos productos **no suman** al umbral de $300.000 antes de IVA. El flete se calcula sobre el subtotal y el peso de los productos que sí pagan.
- Si el subtotal de los productos que pagan alcanza el umbral, el envío es gratis para todo el pedido.
- El peso total del pedido se usa para la promesa de entrega (por ejemplo, Barranquilla con artículo pesado); el costo se calcula sobre el subtotal no exento.

El checkout muestra una nota según el caso:

- “Todos los productos de tu pedido tienen envío gratis.”
- “N productos con envío gratis. El flete se calcula sobre los demás productos.”
- “Envío gratis: los productos con envío gratis no suman al umbral y el resto de la compra supera el mínimo.”

Campos nuevos en la cotización:

- `shipping_net_before_cents`
- `shipping_weight_grams`
- `free_shipping_items`
- `free_shipping_all_items`
- `free_shipping_items_subtotal_cents`
- `shipping_note`

## 4. Diagnóstico

- **Checkout con Barranquilla:** seleccionar Atlántico → Barranquilla y verificar "Entrega estimada: 1-2 días".
- **Producto pesado:** configurar `weight_grams >= 20000` y verificar "2 días".
- **Umbral:** probar un carrito de $300.000 y otro de $357.000 con IVA.
- **Producto con envío gratis:** marcarlo, cotizar solo ese producto y verificar envío $0.
- **Mezcla:** cotizar un producto con envío gratis + uno normal y verificar que el flete se calcula sobre el normal.
- **Reversión:** desmarcar el envío gratis y verificar que vuelve a cobrarse.

## 5. Tarifas por rango de peso

`store_shipping_rates` ahora acepta:

- `weight_min_grams`
- `weight_max_grams`
- `markup_pct`
- `sync_source`
- `last_synced_at`

El checkout elige la tarifa más específica que cubra el peso del pedido:

1. Municipio exacto.
2. Departamento.
3. Nacional.
4. Rango de peso que contiene el pedido; si no existe, usa la tarifa genérica o el rango inferior más cercano.

Una tarifa con `weight_min_grams = 0` y `weight_max_grams = NULL` aplica a **todo peso** y sirve de respaldo.

## 6. Coordinadora

La integración con el cotizador de Coordinadora se configura en **Tienda → Envíos → Tarifas → Cotizador Coordinadora**. Ver `docs/COORDINADORA.md` para credenciales, sincronización, margen, redondeo y diagnóstico semanal.

## 7. Archivos

- `src/Services/ShippingService.php`
- `src/Services/BrebService.php`
- `src/Services/CartService.php`
- `templates/themes/sonika/checkout.php`
- `templates/themes/sonika/carrito.php`
- `public/assets/js/sonika-payments.js`
