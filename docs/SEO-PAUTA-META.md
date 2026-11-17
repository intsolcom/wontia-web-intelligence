# SEO Global Launch — Pauta Meta

## Síntoma

Al abrir **SEO Global Launch → Pauta Meta**, el reporte tardaba entre 8 y 15 segundos.

## Causa

`SeoMetaAdsService::report()` lanzaba **seis consultas separadas** sobre
`analytics_views` con una condición de pauta no indexable:

```sql
LOWER(COALESCE(utm_source, '')) IN (...)
OR LOWER(COALESCE(utm_medium, '')) IN (...)
OR fbclid ...
```

Cada consulta escaneaba toda la tabla y además aplicaba un `REGEXP` de bots
sobre `user_agent`. En producción eso representaba ~83.000 filas por ventana
de 30 días y ~71 MB por recorrido.

También había ~62.000 filas históricas con `is_bot = 0` y user-agent de bot,
por lo que el `REGEXP` era necesario para no inflar las visitas de clientes.

## Solución aplicada

1. **Columna generada virtual `is_meta_paid`** en:
   - `analytics_views`
   - `seo_tracking_events`
   - `seo_live_requests`

   La expresión concentra `fbclid` + UTM de Meta + medio pagado.

2. **Índice `idx_site_meta_paid (site_id, is_meta_paid, created_at)`** en las
   tres tablas. El reporte ya no escanea la tabla completa: lee solo las filas
   de pauta.

3. **Una sola lectura de vistas pagadas** a una tabla temporal
   (`tmp_seo_meta_paid`). Las campañas, landings, evolución diaria, fuentes y
   ventanas de 1/7/30 días se agregan sobre esa tabla temporal. Si el usuario
   de BD no permite tablas temporales, se usa una subconsulta derivada.

4. **Backfill único de bots históricos**: `is_bot = 1` para las vistas cuyo
   user-agent coincide con el patrón de bots. Así el reporte puede confiar en
   `is_bot = 0` sin repetir el `REGEXP` en cada consulta.

5. **Menos consultas**: se eliminaron las consultas duplicadas de las ventanas
   de 1 y 7 días y el conteo de visitantes totales que no se usaba.

## Resultado

| Ventana | Antes | Después |
|---|---:|---:|
| 7 días | ~8 s | ~0,2 s |
| 14 días | ~8 s | ~0,08 s |
| 30 días | ~8 s | ~0,06 s |
| 90 días | ~8 s | ~0,12 s |

Las cifras de clientes se mantienen: el backfill devolvió el total de vistas
de 30 días a ~21.660 (antes el `REGEXP` las filtraba, pero el costo era alto).

## Mantenimiento

- La migración vive en `SeoTrafficSchemaService::VERSION = 5`.
- El SQL documental está en `install/seo_traffic_intelligence.sql`.
- Si se agrega una nueva red o un nuevo medio pagado, actualizar:
  - `SeoMetaAdsService::META_SOURCES` / `PAID_MEDIUMS`
  - La expresión de `is_meta_paid` en `SeoTrafficSchemaService`
  - El SQL documental
- No volver a introducir `REGEXP` sobre `user_agent` en consultas de reporte:
  para eso está `is_bot`.
