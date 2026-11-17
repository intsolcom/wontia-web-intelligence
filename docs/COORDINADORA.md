# Coordinadora — cotizador y sincronización de tarifas

## 1. ¿Se puede conectar SONIKA con Coordinadora?

Sí. Coordinadora publica un **Web Service AGS 1.5** con la operación `Cotizador_cotizar` para calcular fletes nacionales.

- Endpoint oficial: `https://ws.coordinadora.com/ags/1.5/server.php`
- Protocolo: SOAP 1.1 document/literal.
- Autenticación: `apikey` + `clave` en el cuerpo de cada operación, más `nit`, `div` y `cuenta` de la cuenta de integrador.
- Límite recomendado: **100 solicitudes por minuto**. SONIKA usa una pausa de 0.7 s entre llamadas (~85/min).
- No se debe scrapear el cotizador web. La integración usa el Web Service oficial.

Las credenciales se solicitan a Coordinadora como integrador / plugin logístico. Sin ellas, el panel queda en modo **“Sin configurar”** y no bloquea el checkout.

## 2. Dónde está en el panel

**Tienda → Envíos → Tarifas → Cotizador Coordinadora**.

La tarjeta permite:

- Activar/desactivar la integración.
- Guardar `apikey`, `clave`, NIT, división, cuenta, código de origen, producto y nivel de servicio.
- Definir **margen de seguridad (%)** para cubrir variaciones diarias.
- Definir **redondeo** en pesos (por defecto $100).
- Definir **tolerancia** para el diagnóstico semanal (por defecto 5%).
- Probar la conexión.
- Cotizar una ciudad/peso de prueba.
- Sincronizar ahora (en cola) o ejecutar una sincronización rápida de muestra.
- Ejecutar diagnóstico semanal (en cola) o un diagnóstico rápido.
- Ver la última ejecución, sus cotizaciones, tarifas creadas/actualizadas y diferencias.

## 3. Cómo se construye la tabla estándar

Para cada destino y rango de peso se consulta Coordinadora y se crea/actualiza una tarifa en `store_shipping_rates`:

| Rango | Peso |
|---|---|
| 0-1 kg | 0 a 1.000 g |
| 1-3 kg | 1.001 a 3.000 g |
| 3-5 kg | 3.001 a 5.000 g |
| 5-10 kg | 5.001 a 10.000 g |
| 10-20 kg | 10.001 a 20.000 g |
| 20-30 kg | 20.001 a 30.000 g |
| 30-50 kg | 30.001 a 50.000 g |

Destinos:

- **Nacional**: se calcula con la mediana de los departamentos (configurable: mediana, máximo o Bogotá).
- **Departamentos**: capital de cada departamento.
- **Ciudades principales**: Bogotá, Medellín, Cali, Barranquilla, Cartagena, Bucaramanga, Cúcuta, Pereira, Ibagué, Santa Marta, Valledupar, Montería, Pasto, Neiva, Manizales, Armenia, Villavicencio, Sincelejo, Yopal, Popayán, Riohacha, Florencia y Tunja.

### Margen y redondeo

```text
costo_base = flete_total de Coordinadora
costo_con_margen = costo_base × (1 + margen / 100)
costo_final = redondear_hacia_arriba(costo_con_margen / redondeo) × redondeo
```

Ejemplo con margen 8% y redondeo $100:

```text
Flete Coordinadora: $15.000
Con margen 8%:      $16.200
Costo final:        $16.200
```

El margen cubre desfases de precio entre un día y otro, variaciones de combustible, recargos y redondeos.

### Prioridad de tarifas en el checkout

El checkout elige:

1. Tarifa municipal exacta (ej. Barranquilla 08001).
2. Tarifa departamental (ej. Atlántico 08).
3. Tarifa nacional.
4. Dentro de la ubicación, el rango de peso que contiene el pedido.
5. Si no hay rango exacto, usa la tarifa genérica o el rango inferior más cercano.

La tarifa nacional manual existente queda como respaldo para pedidos fuera de los rangos sincronizados.

## 4. Sincronización y diagnóstico semanal

- **Sincronización completa**: se encola y la ejecuta `public/job-runner.php` (cron cada minuto). Evita que el navegador quede esperando varios minutos.
- **Sincronización rápida**: consulta 12 destinos y 4 rangos para validar la conexión y la tabla.
- **Diagnóstico semanal**: vuelve a cotizar las tarifas de Coordinadora y compara el costo actual con el nuevo.
  - Diferencia menor o igual a la tolerancia: `unchanged`.
  - Diferencia mayor: `review`.
  - Si `auto_update` está activo y la diferencia es ≤ 25%, actualiza la tarifa.
  - Si la diferencia es mayor al 25%, solo alerta para revisión manual.
- El job runner ejecuta el diagnóstico si han pasado 7 días desde `last_sync_at`.
- Cada ejecución queda en:
  - `shipping_rate_sync_runs` (resumen).
  - `shipping_rate_sync_items` (detalle por destino/peso, con base, aplicado y diferencia).

## 5. Seguridad

- Las credenciales solo se guardan en `settings` y nunca se devuelven al frontend; el panel muestra `apikey` enmascarada y `clave_set`.
- Los endpoints son administrativos y requieren JWT de administrador.
- El job runner es CLI-only.
- No se exponen `apikey`/`clave` en logs ni en la URL.
- Si la API falla, el checkout sigue usando la última tabla guardada; nunca se bloquea la compra por una caída de Coordinadora.

## 6. Puesta en marcha

1. Solicitar a Coordinadora las credenciales de integrador:
   - `apikey`
   - `clave`
   - `nit`
   - `div`
   - `cuenta`
   - Código de origen (`08001000` para Barranquilla; confirmar con la cuenta).
2. En **Tienda → Envíos → Tarifas → Cotizador Coordinadora**, guardar credenciales.
3. Pulsar **Probar conexión**.
4. Pulsar **Cotizar prueba** con una ciudad y peso conocidos.
5. Ejecutar **Rápida** para validar la tabla.
6. Ejecutar **Sincronizar ahora** para la tabla completa (se ejecuta en segundo plano).
7. Revisar la última ejecución y las diferencias.
8. Dejar activo el diagnóstico semanal.

## 7. Pruebas realizadas

La integración se probó contra un servidor SOAP simulado:

- `Cotizador_departamentos` devolvió 3 departamentos.
- `Cotizador_cotizar` devolvió `flete_total = 15.000`, `dias_entrega = 2`.
- Con margen 8% y redondeo $100, el costo aplicado fue `$16.200`.
- Sincronización de 2 destinos × 2 rangos creó 4 tarifas + 2 nacionales.
- Diagnóstico de 6 tarifas devolvió 6 sin cambios.

No se ejecutó una cotización real porque la cuenta aún no tiene credenciales de integrador cargadas.

## 8. Archivos

- `src/Services/CoordinadoraService.php`
- `src/Services/ShippingService.php`
- `src/Controllers/Admin/StoreController.php`
- `public/api.php`
- `public/job-runner.php`
- `public/assets/js/admin.js`
- `install/shipping.sql`
- `docs/SHIPPING.md`

## 9. Siguientes pasos posibles

- Importar el catálogo oficial `Cotizador_ciudades` para mapear códigos de ciudad con precisión (hoy se usa DANE + `000`).
- Añadir peso volumétrico con dimensiones por producto.
- Sincronizar municipios pequeños de forma incremental.
- Implementar el gateway REST/OAuth cuando Coordinadora confirme el contrato JSON.
- Generar guías y rótulos desde el pedido usando las operaciones `Rotulos_*` y `Recogidas_*`.
