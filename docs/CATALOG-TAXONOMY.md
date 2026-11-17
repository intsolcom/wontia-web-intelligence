# Taxonomía del catálogo SONIKA

## Decisión

**SONIKA es la dueña de la taxonomía. Miche solo aporta datos de producto**
(título, tipo, tags, marca, precio, imágenes). El sync de Miche nunca debe crear,
renombrar ni mover categorías del sitio.

La fuente única de verdad es `App\Services\CatalogTaxonomyService`. De ahí salen:

- El árbol de navegación público (padre → familia → tipo).
- La clasificación de cada producto (mapa persistente por `handle`).
- El espejo en `store_categories` (`parent_id`, `path`, `level`).
- La protección contra el sync (`category_locked`, `source_tags`).

## Estructura

```
/cuerdas
  /guitarras
    /acusticas
    /electroacusticas
    /electricas
    /clasicas
    /combos
    /otras
  /bajos
    /electricos
    /acusticos
    /combos
    /otros
  /ukeleles
  /violines
  /violas
  /violoncellos
  /contrabajos
  /arpas
  /cuatros-tiples
  /encordados
  /otros
/percusion
  /baterias (/acusticas, /electronicas)
  /redoblantes
  /platillos
  /congas
  /bongos
  /cajones
  /timbales
  /panderetas
  /baquetas
  /parches
  /campanas
  /shakers
  /cajas-vallenatas
  /maracas
  /djembes
  /tambores
  /metalofonos
  /otros
/vientos
  /acordeones
  /armonicas
  /melodicas
  /flautas
  /saxofones
  /trompetas
  /clarinetes
  /gaitas
  /trombones
  /boquillas-canas
  /otros
/teclados
  /pianos
  /teclados
  /sintetizadores
  /controladores
  /pads
  /otros
/audio
  /amplificadores
  /cabinas
  /mixers-consolas
  /parlantes
  /monitores
  /interfaces
  /audifonos
  /plantas
  /procesadores
  /controladores
  /tocadiscos
  /otros
/microfonos
  /dinamicos
  /condensador
  /inalambricos
  /otros
/iluminacion
  /cabezas-moviles
  /par-led
  /truss
  /maquinas-humo
  /tensores
  /luces
  /cortinas
  /otros
/accesorios
  /soportes-estructuras
  /correas
  /forros-fundas
  /cables-adaptadores
  /picks
  /capodastros
  /afinadores
  /sordinas
  /slides
  /repuestos
  /kits
  /limpiadores
  /baqueteros
  /cortavientos
  /clavijeros
  /protectores
  /clips
  /souvenirs
  /pedales
  /otros
```

Las categorías sin hijos son de dos niveles (`/microfonos/dinamicos`). Las que
tienen hijos permiten tres niveles (`/cuerdas/guitarras/electricas`).

## URLs y redirecciones 301

| URL anterior | URL nueva |
|---|---|
| `/guitarras` | `/cuerdas/guitarras` |
| `/guitarras?sub=bajos` | `/cuerdas/bajos` |
| `/guitarras?sub=electricas` | `/cuerdas/guitarras/electricas` |
| `/guitarras?sub=electroacusticas` | `/cuerdas/guitarras/electroacusticas` |
| `/acordeon` | `/vientos/acordeones` |
| `/pianos` | `/teclados/pianos` |
| `/amplificadores` | `/audio/amplificadores` |
| `/microfono-miche` | `/microfonos` |
| `/cabeza-movil` | `/iluminacion/cabezas-moviles` |
| `/cuerdas-guitarras` | `/accesorios` |
| `/cuerdas/guitarras/acusticas-amplificadas` | `/cuerdas/guitarras/electroacusticas` |

Las URLs nuevas son canónicas, aparecen en el sitemap y en los breadcrumbs.
Las antiguas responden 301 para conservar el tráfico y la indexación.

## Sincronización y protección

1. `CatalogProductService` importa productos y **actualiza `source_tags`** con
   los tags crudos de Miche, sin tocar `tags` ni `category_id` existentes.
2. `CatalogTaxonomyService::syncIfNeeded()` se ejecuta tras el import. Solo
   recalcula si cambió el `mtime` del catálogo o la versión de la taxonomía.
3. `buildMap()` clasifica los 3.313 productos y escribe
   `public/assets/uploads/_catalog/taxonomy_map.json`.
4. `seedDatabase()` crea/actualiza los 110 nodos en `store_categories` con
   `parent_id`, `path` y `level`. Las categorías aprobadas a mano conservan su
   nombre e ícono.
5. `assignProducts()` asigna la hoja canónica a los productos **no bloqueados**.
   Un producto con `category_locked = 1` conserva su categoría manual, y el
   mapa la usa como override (`status = locked`).

### Reglas de oro

- No crear categorías desde el sync. Solo `CatalogTaxonomyService::TREE`.
- No sobrescribir `store_products.category_id` si `category_locked = 1`.
- No usar `product_type` como categoría. Es un dato sucio de Miche
  (`Snowboard`, `sonido`, `Tienda`, etc.).
- Los accesorios no se mezclan automáticamente con instrumentos. El mapa guarda
  `sec` (categorías secundarias) para una curaduría futura, pero la grilla
  pública usa solo la categoría principal.
- Cualquier cambio de árbol requiere subir `CatalogTaxonomyService::VERSION`,
  ejecutar el build y purgar la caché del mega menú.

## Herramientas

```bash
# Solo genera el mapa (no toca la base)
php tools/catalog_taxonomy_build.php

# Genera mapa + store_categories + category_id
php tools/catalog_taxonomy_build.php --apply

# Resumen sin escribir nada
php tools/catalog_taxonomy_build.php --dry

# Verificación de categorías, paths duplicados y distribución
php tools/taxonomy_verify.php
```

## Criterios de clasificación

- Primero mandan los tags `1) Categoría_*` de Miche.
- Luego el título y los tags `2) Tipo_*`.
- `product_type` es el último recurso y se ignora si es un valor no musical.
- Reglas de contexto corrigen casos típicos:
  - `PEDAL BOMBO` → Percusión.
  - `PEDAL DE EFECTO` → Audio / Procesadores.
  - `PEDAL DE TECLADO` → Accesorios / Pedales.
  - `AFINADOR` → Accesorios / Afinadores.
  - `CABEZA MÓVIL` → Iluminación.
  - `MICRÓFONO ... INALÁMBRICO/BLX/SLX/RE3` → Micrófonos Inalámbricos.
  - `MICRÓFONO ... CONDENSADOR/AT2020/C-1/PGA81` → Micrófonos de Condensador.
  - `GUITARRA ... ELEC/ELECTROACUST/CLASICA` → hoja correspondiente.
- Los productos sin clasificación quedan en la cola `pending` del resumen; hoy
  hay 0. Los 2 productos de prueba (`punto-test`, `producto-de-prueba`) no
  tienen categoría y no forman parte del catálogo Miche.

## Resultado actual (16 nov 2026)

- 3.313 productos Miche clasificados: 3.047 alta confianza, 261 media, 5 baja.
- 0 pendientes, 0 conflictos.
- 110 categorías canónicas, 8 padres.
- 3.318 productos con `category_id` (3.313 Miche + 5 locales) y 3.313 con `source_tags`.
- 2 productos de prueba sin categoría.
- Conciliación: 0 categorías sin mapear, 0 categorías vacías.
