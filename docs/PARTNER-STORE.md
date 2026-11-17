# SONIKA Partners — Mi Tienda dentro del panel

## Qué cambia

El Partner ya no tiene que salir a `/tienda` para armar su catálogo. El panel incluye una pestaña **Mi Tienda** que usa el mismo catálogo, carrito y checkout de SONIKA.

## Flujo

1. Entrar a `/mi-antro`.
2. Abrir el panel: `/artista/{slug}/panel`.
3. Pestaña **Mi Tienda**.
4. Buscar productos por nombre, marca o categoría.
5. Pulsar **+ Agregar** para sumarlos a la tienda del Partner.
6. Pulsar **Copiar link** para copiar el enlace con atribución.
7. Pulsar **Ver mi tienda** para abrir la página pública.

## Enlaces

- Enlace de tienda: `/r/{codigo}`.
- Enlace de producto: `/r/{codigo}/p/{handle}`.
- Código: `nombre-apellido-####`, con número aleatorio para no exponer el ID interno.
- Cookie `sonika_aff`: 180 días.
- Ventana de comisión: 30 días desde el último clic válido.

## Persistencia

- Colección principal: `affiliate_collections`, slug `mi-tienda`.
- Productos: JSON de handles en `affiliate_collections.products`.
- Límite: 60 productos.
- La colección se crea automáticamente al agregar el primer producto.
- Alias de códigos antiguos: `affiliate_link_aliases`.
- “Recordarme”: `affiliate_remember_tokens` (selector + hash del validador).

## Sesión

- Sesión normal: 8 horas.
- Renovación deslizante mientras el Partner usa el panel.
- “Mantener sesión iniciada”: 30 días en el dispositivo.
- El panel muestra el estado de la atribución en el navegador y permite probar el enlace.

## Exportación

`Mi Tienda → Descargar catálogo CSV` genera un CSV con producto, SKU, precio, stock y enlace de atribución.

## Seguridad

- El endpoint de agregar/quitar producto exige sesión de Partner.
- El handle se valida contra el catálogo real.
- El código de atribución se valida en servidor al registrar el clic y al crear el pedido.
- Los enlaces `/r/` tienen `X-Robots-Tag: noindex`.
- Los destinos de `/r/{code}?to=` están limitados a rutas públicas seguras.

## Archivos

- `src/Services/AffiliateService.php`
- `src/Controllers/AffiliateController.php`
- `templates/themes/sonika/router.php`
- `templates/themes/sonika/lib.php`
- `public/assets/js/sonika-attribution.js`
- `public/assets/js/sonika-share.js`
- `install/affiliates_partner_store.sql`
