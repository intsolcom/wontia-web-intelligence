# Banner con Exit-Intent y Carritos Abandonados

**Versión:** 1.0.0
**Sitio de validación:** SONIKA (`https://sonika.com.co`)
**Estado:** implementado y desplegado.

---

## 1. Qué se agregó

### En el Brick Banner / Aviso

- Detección real de intención de salida del navegador:
  - Movimiento del mouse hacia la parte superior/cierre de la ventana.
  - `mouseout` cuando el cursor sale del documento.
  - Sensibilidad configurable en píxeles.
- Reglas antes de mostrar:
  - Tiempo mínimo en página.
  - Scroll mínimo.
  - Solo si el carrito tiene productos.
  - No mostrar en checkout ni página de pedido.
  - Activar o no en móvil.
  - Fallback configurable por tiempo.
  - Frecuencia existente (`una vez`, `3 veces`, `sesión`, `siempre`).
- Otros disparadores de comportamiento:
  - Inactividad del usuario.
  - Profundidad de scroll.
  - Tiempo en página.
- Resumen visual del carrito dentro del modal:
  - Productos, cantidades, SKU y subtotales.
  - Total.
  - Botón directo a checkout.
  - Cupón de recuperación opcional.
- Métricas nuevas:
  - Intentos de salida.
  - Disparos por comportamiento.
  - Cierres.
  - Clics de recuperación.
  - Tasa de recuperación.

### En Tienda

Nueva pestaña:

```text
Tienda → Carritos abandonados
```

Incluye:

- Tráfico del sitio: visitantes únicos, sesiones, páginas vistas, páginas por sesión y tiempo promedio.
- Embudo: producto visto, agregado al carrito, checkout y compra.
- Métricas de carritos:
  - Activos.
  - Abandonados después de 30 minutos.
  - Valor en riesgo.
  - Recuperados/convertidos.
  - Tasa de recuperación.
  - Ingresos recuperados.
- Listado de carritos con:
  - Cliente o invitado.
  - Productos y unidades.
  - Valor.
  - Origen/UTM.
  - Dispositivo y última página.
  - Tiempo desde la última actividad.
  - Estado.
- Acciones:
  - Ver detalle.
  - Recordar por email (abre el cliente de correo y marca contacto).
  - Marcar recuperado.
  - Eliminar.
  - Exportar CSV.
  - Ejecutar recuperación por email existente.
  - Crear aviso visual de recuperación.
- Histórico de carritos invitados del tracking anterior (agregado, sin detalle de productos).

---

## 2. Cómo configurar el Banner de recuperación

1. Ir a **Tienda → Carritos abandonados**.
2. Pulsar **Crear aviso de recuperación**.
3. Se crea un banner modal en **Banners** con:
   - `mode = modal`
   - `exit_intent = true`
   - `exit_intent_cart_only = true`
   - `exit_intent_exclude_checkout = true`
   - `show_cart_summary = true`
   - `frequency = session`
   - CTA a `/checkout`
4. Abrir **Banners → Recuperación de carrito** para editar:
   - Texto.
   - Colores.
   - Tiempo mínimo.
   - Scroll mínimo.
   - Cupón de recuperación.
   - Texto del botón.
5. Guardar y activar.

El aviso se renderiza globalmente en el footer de SONIKA cuando está marcado como recuperación y habilitado.

### Campos nuevos del Banner

| Campo | Uso |
|---|---|
| `exit_intent` | Activa detección de salida |
| `exit_intent_min_time` | Segundos mínimos antes de mostrar |
| `exit_intent_min_scroll` | Scroll mínimo (%) |
| `exit_intent_sensitivity` | Píxeles desde el borde superior |
| `exit_intent_fallback_ms` | Mostrar después de X ms si no se detecta salida |
| `exit_intent_cart_only` | Solo mostrar con carrito |
| `exit_intent_exclude_checkout` | Excluir checkout/pedido |
| `exit_intent_mobile` | Activar salida en móvil |
| `inactivity_seconds` | Mostrar tras inactividad |
| `scroll_trigger_pct` | Mostrar al llegar a X% de scroll |
| `time_trigger_seconds` | Mostrar tras X segundos |
| `behavior_tracking` | Medir salida, cierre y recuperación |
| `show_cart_summary` | Mostrar productos del carrito |
| `cart_storage_key` | Clave localStorage del carrito |
| `cart_cta_label` | Texto del botón de checkout |
| `cart_cta_url` | URL del botón |
| `recovery_coupon` | Cupón opcional añadido al checkout |

---

## 3. Captura de carritos abandonados

El tracking existente `seo-track.js` ahora envía `cart_items` en:

- `cart_add`
- `cart_snapshot`
- `checkout_start`
- `checkout_abandon`

`SeoTrackingController` valida el consentimiento de analítica y llama a `CartAbandonmentService::capture()`.

### Datos guardados

Tabla `store_abandoned_carts`:

- Sesión y visitor hash.
- Cliente, nombre y email si hay sesión iniciada.
- Productos, cantidades y valor.
- UTM, `fbclid`, `gclid` y landing.
- Última página y dispositivo.
- Estado:
  - `active`
  - `abandoned`
  - `contacted`
  - `recovered`
  - `converted`
  - `dismissed`
- Pedido recuperado y valor recuperado.

### Regla de abandono

Un carrito activo pasa a `abandoned` después de **30 minutos** sin actividad.

### Recuperación

- Al crear un pedido, el carrito se marca `recovered`.
- Al confirmar el pago, se marca `converted`.
- La recuperación por email reutiliza `CartRecoveryService` y la configuración existente:
  - Tienda → Configuración → Carrito → Recuperar carritos abandonados.
  - Horas antes de recordar.

---

## 4. Privacidad y consentimiento

- La captura de detalle requiere consentimiento `analytics`.
- Si no hay consentimiento, no se guarda el carrito ni se envía el beacon.
- No se guardan datos de pago.
- El email y nombre solo se guardan si el cliente tiene sesión iniciada.
- Los invitados se muestran como “Invitado”.
- El aviso visual respeta la segmentación y las reglas del Banner.
- El cupón de recuperación es opcional y se configura manualmente.

---

## 5. Métricas del Banner

`wwi_banner_stats` ahora incluye:

- `exit_intents`
- `behavior_triggers`
- `dismissals`
- `recovery_clicks`

El panel **Banners** muestra:

- Impresiones.
- Clics.
- CTR.
- Intentos de salida.
- Cierres.
- Clics de recuperación.
- Tasa de recuperación.

---

## 6. Pruebas realizadas

- Captura de carrito desde beacon público con consentimiento.
- Valor y cantidades correctos.
- UTM, `fbclid` y campaña asociados.
- Métricas de carritos activos, valor en riesgo, top productos.
- Transiciones `contacted → recovered → converted`.
- Eliminación de registros de prueba.
- Tabla `wwi_banner_stats` con nuevas columnas.
- Render del Banner con:
  - `data-exit="1"`
  - `data-bn-cart`
  - resumen del carrito
- Prueba E2E en producción:
  - Banner temporal de recuperación.
  - Carrito con 2 productos.
  - Simulación de salida.
  - Modal abierto.
  - Resumen con 2 productos y total `$608.800`.
  - CTA a `/checkout`.
  - Limpieza del banner y datos de prueba.
- Panel admin:
  - Pestaña visible.
  - Tráfico y embudo renderizan.
  - Tabla de carritos renderiza.
  - Detalle del carrito renderiza.
  - Botón de estrategia crea el banner y navega a su editor.

---

## 7. Limitaciones

- El exit-intent con mouse no es fiable en móvil; para móvil se recomienda inactividad o scroll.
- El navegador no permite mostrar un modal cuando el usuario cierra la pestaña; se detecta la intención antes de salir.
- Los carritos históricos de invitados solo tienen agregado (cantidad/valor), no detalle de productos.
- La recuperación por email funciona para clientes con sesión y carrito guardado en `store_carts`.
- El envío de email es manual desde el panel o por el cron existente; no se envía automáticamente al mostrar el modal.
- No se crean cupones automáticamente; el cupón debe existir y configurarse en el Banner.
- El aviso de recuperación se renderiza en SONIKA; para otros temas hay que llamar `BannerService::recoveryConfigs()` en su footer.

---

## 8. Rollback

- Desactivar el banner de recuperación en **Banners**.
- Desactivar `behavior_tracking` o `exit_intent` en el Banner.
- Si se requiere revertir código, restaurar:

```text
/tmp/cart-banner-backup-20261112.tgz
```

- La tabla `store_abandoned_carts` puede conservarse para auditoría o eliminarse con:

```sql
DROP TABLE IF EXISTS store_abandoned_carts;
```

---

## 9. Archivos

### Nuevos

- `src/Services/CartAbandonmentService.php`
- `src/Controllers/Admin/AbandonedCartController.php`
- `install/abandoned_carts.sql`
- `docs/BANNER-EXIT-INTENT-ABANDONED-CARTS.md`

### Modificados

- `src/Widgets/BannerWidget.php`
- `src/Services/BannerService.php`
- `src/Services/BannerStatsService.php`
- `src/Controllers/SeoTrackingController.php`
- `src/Services/StoreService.php`
- `public/api.php`
- `public/assets/js/admin.js`
- `public/assets/js/seo-track.js`
- `templates/themes/sonika/lib.php`
- `install/banner_stats.sql`
