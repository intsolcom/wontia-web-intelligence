# Pedidos — archivado, pruebas y métricas

**Módulo:** Tienda → Pedidos  
**Sitio:** SONIKA (`https://sonika.com.co`)

---

## 1. Pestañas internas

La pestaña **Pedidos** tiene dos vistas:

- **Pedidos**: pedidos activos.
- **Archivados**: pedidos retirados de la bandeja principal.

El archivado es **no destructivo**. Los pedidos siguen en `store_orders` y se pueden restaurar.

---

## 2. Campos nuevos en `store_orders`

| Campo | Uso |
|---|---|
| `archived_at` | Fecha de archivado. `NULL` = activo. |
| `is_test` | `1` = pedido de prueba. |
| `archive_reason` | Motivo: `prueba`, `duplicado`, `spam`, `cancelado`, `manual`, `auto_failed`, `auto_test`. |
| `archived_by` | ID del usuario que archivó. |
| `affiliate_ref` | Código del partner que originó la venta. Se guarda aunque el pedido quede marcado como interno. |

La migración se ejecuta desde `StoreService::ensureTables()`.

---

## 3. Marcas de prueba

- Los pedidos marcados con `is_test = 1` **no se incluyen** en los totales del dashboard (`stats()`).
- Se pueden ver con el filtro **Tipo: Pruebas**.
- El botón **Métricas** compara totales con y sin pruebas.
- Si un pedido real archivado recibe un pago, `markPaid()` lo desarchiva automáticamente. Los pedidos de prueba no se desarchivan solos.

---

## 4. Acciones del panel

- **Archivar / Restaurar** por pedido.
- **Archivar seleccionados / Restaurar seleccionados**.
- **Marcar prueba / Quitar prueba**.
- **Archivar filtrados / Restaurar filtrados**.
- **Detectar pruebas**: revisa candidatos por correo, nombre, número `TEST-` o monto mínimo antes de marcarlos y archivarlos.
- **Exportar CSV** con los filtros actuales.
- **Deshacer** después de archivar seleccionados.

Filtros disponibles:

- Búsqueda por pedido, cliente, email o UUID.
- Estado de pago.
- Estado del pedido.
- Tipo: reales o pruebas.
- Método de pago.
- Fecha desde/hasta.
- Monto mínimo/máximo.

---

## 5. Archivado automático

En **Tienda → Configuración → Archivado automático**:

- Activar/desactivar.
- Días para archivar fallidos/cancelados.
- Días para archivar pedidos de prueba.

El job `public/job-runner.php` ejecuta `StoreService::autoArchiveOrders()`.

Valores por defecto:

- Fallidos/cancelados: 30 días.
- Pruebas: 7 días.

---

## 6. Exportación CSV

Endpoint:

```text
GET /api/v1/admin/store/orders/export
```

Acepta los mismos filtros que la lista. Incluye BOM UTF-8 y separador `;` para Excel en español. Las celdas que empiezan por `=`, `+`, `-` o `@` se neutralizan para evitar inyección de fórmulas.

---

## 7. Historial de eventos

La tabla `store_order_events` registra:

- Archivado y restauración.
- Marca y retiro de marca de prueba.
- Cambios de estado de pago o fulfillment.
- Pago confirmado.

El detalle del pedido muestra el historial con fecha, evento, usuario y motivo. El registro es no bloqueante: si falla, la operación principal continúa.

---

## 8. Rollback

Respaldo de los archivos modificados:

```text
/tmp/store-improve-backup-20261112.tgz
```

Para revertir el archivado de los pedidos de prueba actuales, usar la acción **Restaurar** en la pestaña Archivados.

---

## 9. Ficha 360 y resumen para Miche

La ficha del pedido está en **Administración → Central de pedidos** (`/admin.php#order-center/pedidos`). Al abrir un pedido, arriba a la derecha:

- **Resumen Miche:** abre una hoja imprimible o guardable como PDF con cliente, documento, teléfono, correo, dirección, indicaciones, transportadora, ETA, productos con SKU/cantidad/unitario/total, pago, abono, envío, total y partner.
- **Imprimir / PDF:** el documento incluye el logo de SONIKA y el slogan **“El Sonido que te mueve”** en tipografía tipo firma, texto oscuro legible, colores corporativos (azul marino, rojo SONIKA y grises de alto contraste), tabla de productos, totales, firma de recibido y pie legal. El nombre del archivo se genera como `PED-... - Nombre del cliente - Documento - Fecha`.
- **Copiar Miche:** copia el mismo resumen como texto para WhatsApp o correo.
- **Reportar pedido a proveedor (Miche):** handoff interno. Requiere los 3 checks (dinero en cuenta, cliente confirmado por AIP y reconfirmación). Crea caso, tarea y handoff en `oc_*`, pero **no envía** nada a Miche.

**No existe envío automático a Miche.** Para activarlo faltan:

1. Correo o teléfono de Miche en `oc_partners.contact_email` / `contact_phone` (hoy están vacíos).
2. Definir el disparador: al confirmar el pedido, al marcar los 3 checks o al pulsar “Reportar a proveedor”.
3. Escribir el registro en `oc_notifications` y enviar por el canal elegido. Hoy esa tabla está vacía y el código solo la lee.

**Pedidos internos:** los pedidos con `is_internal = 1` no reciben comisión de partner. Si un pedido asistido por un administrador es una venta real, hay que revisar esa marca antes de atribuirlo.

---

## 10. Atribución manual de partner

- La **Central de pedidos** abre la ficha 360 completa al pulsar **Ver** (antes abría un modal mínimo).
- La ficha muestra **Partner** con nombre, código y comisión, o **Ref. partner** si el pedido tiene el código pero no la atribución.
- Si no hay atribución, el botón **Atribuir partner** pide el código, crea la atribución y la comisión, y quita la marca de pedido interno.
- Endpoint: `POST /api/v1/admin/store/orders/{id}/attribute-partner` con `{code}`.
- El panel del partner muestra el número de pedido, cliente, productos, estado de pago/despacho, base, tasa y comisión; ya no muestra solo el UUID.
- La atribución manual exige un clic válido del partner en los últimos 30 días.
