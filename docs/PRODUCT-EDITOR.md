# Editor de productos — modal seguro

## Problema corregido

- El modal se cerraba al tocar el fondo o cualquier zona fuera del contenido.
- Al guardar, el modal se cerraba sin confirmar que el producto se hubiera creado.
- El selector de imágenes reemplazaba el modal y perdía el formulario.
- No había protección para cambios sin guardar.

## Comportamiento actual

- El modal **solo se cierra** con:
  - La **X** del encabezado.
  - El botón **Cancelar**.
  - El botón **Guardar**, únicamente cuando la API confirma la creación/edición.
- Tocar el fondo oscuro ya no cierra el modal.
- Si hay cambios sin guardar, se pide confirmación antes de cerrar.
- Si es un producto nuevo, se guarda un **borrador automático** en `sessionStorage`.
- Al volver a abrir **+ Nuevo producto**, el borrador se restaura con un aviso y la opción **Descartar borrador**.
- El borrador se elimina al guardar el producto correctamente.
- Los errores de la API se muestran dentro del modal, sin cerrarlo.
- El botón **Guardar** se bloquea mientras guarda para evitar dobles envíos.
- El selector de imágenes del Media ahora se abre **dentro del modal**, sin reemplazarlo.
- Se agregó el campo **Peso (gramos)** para la promesa de entrega de Barranquilla.
- Se agregó el checkbox **Cobrar flete o transporte a este producto** (desmarcado = envío gratis).
- Se agregó el checkbox **Visible solo para IPs autorizadas** con lista de IPs/CIDR/comodines y botón **Usar mi IP**.
- La lista de productos muestra los badges **Envío gratis** y **Privado IP**.
- Al crear un producto, la lista vuelve a **Todos** y limpia la búsqueda para que el producto nuevo sea visible.

## Flujo

1. **Tienda → Productos → + Nuevo producto**.
2. Completar nombre, precio, stock, peso, imágenes y variantes.
3. Pulsar **Guardar**.
4. Si falta el nombre o el servidor rechaza la operación, el modal permanece abierto con el error.
5. Si todo está correcto, el producto se crea y el modal se cierra.

## Persistencia del borrador

- Clave: `sonika_product_draft_v1`.
- Ámbito: `sessionStorage` de la pestaña del administrador.
- Incluye: nombre, slug, categoría, precios, stock, peso, estado, envío gratis, visibilidad, IPs autorizadas, imágenes y variantes.
- No se guarda para edición de productos existentes.

## Archivos

- `public/assets/js/admin.js`

> `public/admin.php` conserva su estructura. El cierre por fondo se desactiva desde `admin.js` al cargar el panel, para no mezclar cambios ajenos en ese archivo.
