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
