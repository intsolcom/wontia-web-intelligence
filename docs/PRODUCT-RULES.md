# Reglas de producto — envío gratis y visibilidad por IP

## 1. Envío gratis por producto

### Configuración

En **Tienda → Productos → Editar producto** existe el checkbox:

- **Cobrar flete o transporte a este producto** (marcado por defecto).

Si se **desmarca**, el producto no paga flete y no suma al umbral de envío gratis.

Campo en base de datos: `store_products.free_shipping` (`0` = cobra flete normal, `1` = no paga flete).

### Lógica

1. Si todos los productos del carrito son de envío gratis → envío `$0`.
2. Si hay mezcla:
   - Los productos con envío gratis no suman al umbral de $300.000 antes de IVA.
   - El flete se calcula con el subtotal y el peso de los productos que sí pagan.
   - Si el subtotal que paga alcanza el umbral, todo el pedido queda con envío gratis.
3. El peso total se conserva para la promesa de entrega (por ejemplo, Barranquilla con artículo pesado).

### Dónde se aplica

- `CartService::validate` propaga `free_shipping` al carrito.
- `BrebService::quote` separa subtotal/peso exento y no exento.
- `StoreService::createOrder` (flujo legacy por zonas) respeta la exención.
- `checkout.php` y `sonika-payments.js` muestran la nota y el total correctos.
- La ficha y la tarjeta del producto muestran el badge **Envío gratis**.

### Prueba rápida

```text
Producto A: free_shipping = 1, precio $50.000
Producto B: free_shipping = 0, precio $100.000
Destino: Bogotá
Resultado esperado: flete sobre B; A no suma al umbral.
```

Si solo está A, el envío debe ser `$0`.

## 2. Visibilidad por IP

### Configuración

En el editor de producto:

- **Visible solo para IPs autorizadas**
- Campo **IPs autorizadas**: acepta IP exacta, CIDR o comodín.
  - `190.1.2.3`
  - `10.0.0.0/8`
  - `190.1.2.*`
- Botón **Usar mi IP** para agregar la IP pública actual del administrador.

Campos en base de datos:

- `store_products.visibility` (`public` o `private`)
- `store_products.allowed_ips` (texto separado por comas)

### Comportamiento

Un producto privado:

- No aparece en catálogo, búsqueda, categorías, marcas, recomendados ni sitemap para IPs no autorizadas.
- Devuelve 404 en la ficha si la IP no está autorizada.
- No se puede agregar al carrito ni comprar desde una IP no autorizada (validación server-side en `CartService` y `StoreService`).
- No se indexa: la ficha privada envía `X-Robots-Tag: noindex, nofollow, noarchive`.
- Los administradores con sesión (`admin`, `superadmin`, `owner`) pueden verlo siempre, incluso si su IP cambió.
- Para una IP autorizada se comporta como un producto normal: precio, stock, carrito, checkout y envío.

### Seguridad

- La IP se resuelve con `IpAccessService::clientIp()`, que usa la lógica de proxy de `SeoInternalTrafficService` (Cloudflare, `X-Forwarded-For`, `X-Real-IP`, `REMOTE_ADDR`).
- La validación se hace en servidor. No depende de CSS ni de JavaScript.
- La lista de IPs se guarda en el producto; no se expone en el HTML público.
- El endpoint `GET /api/v1/admin/store/current-ip` requiere autenticación de administrador.

### Ejemplo: producto de prueba

El producto `producto de prueba` (SKU `000111xxx`) quedó:

- `slug = producto-de-prueba`
- `status = active`
- `stock = 100`
- `free_shipping = 0`
- `visibility = private`
- `allowed_ips = 190.84.116.204` (IP del administrador en la fecha de configuración)

Si la IP del administrador cambia, entrar al producto y pulsar **Usar mi IP** para actualizarla.

## 3. Archivos

- `src/Services/IpAccessService.php`
- `src/Services/StoreService.php`
- `src/Services/CartService.php`
- `src/Services/BrebService.php`
- `src/Controllers/Admin/StoreController.php`
- `public/api.php`
- `public/assets/js/admin.js`
- `templates/themes/sonika/lib.php`
- `templates/themes/sonika/router.php`
- `templates/themes/sonika/checkout.php`
- `public/assets/js/sonika-payments.js`
- `install/store.sql`

## 4. Migración

Las columnas se crean automáticamente con `StoreService::ensureTables()`:

```sql
ALTER TABLE store_products ADD COLUMN IF NOT EXISTS free_shipping TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE store_products ADD COLUMN IF NOT EXISTS visibility VARCHAR(20) NOT NULL DEFAULT 'public';
ALTER TABLE store_products ADD COLUMN IF NOT EXISTS allowed_ips TEXT NULL;
```

También quedaron en `install/store.sql` para instalaciones nuevas.
