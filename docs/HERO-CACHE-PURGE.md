# Hero — caché, propagación y purga

## Diagnóstico

El Hero no guarda una copia de la configuración en caché: `HeroService` lee
`theme.sonika.hero_theme_config` de `settings` en cada request. El problema real
era otro:

1. **El iframe del estudio móvil** cargaba `/` una sola vez. Al guardar o cambiar
   el zoom, seguía mostrando el documento anterior.
2. **El navegador móvil** podía conservar el HTML de la home por caché
   heurística, ya que no había `Cache-Control`.
3. Las imágenes del Hero se servían con la misma URL aunque se reemplazara el
   archivo, por lo que el móvil podía seguir mostrando la versión vieja.

## Solución implementada

### 1. Versión de assets del Hero

Se agregó el setting `theme.sonika.hero_cache_version`.

- Se actualiza al guardar el theme, guardar un slide, publicar, borrar, reordenar
  o purgar.
- `SonikaHeroWidget` agrega `?_hv={version}` a las imágenes del Hero.
- El Hero expone `data-hero-v="{version}"` para diagnóstico.
- No se usa `?v=` para no chocar con el `v` de Shopify. El parámetro es `_hv`.

### 2. Home sin caché heurística

`public/index.php` envía para `/`:

```http
Cache-Control: no-cache, must-revalidate, max-age=0
Pragma: no-cache
```

Esto obliga al navegador móvil a revalidar el HTML. No usa `no-store`, por lo que
el navegador puede conservar una copia y revalidarla, que es más eficiente.

### 3. Estudio móvil en vivo

- Al cambiar zoom, fit o focal en **Composición avanzada**, el iframe del
  estudio móvil aplica el cambio al instante, sin guardar.
- Al guardar el theme, el iframe se recarga con `?hero_v={timestamp}&nc={timestamp}`.
- El botón **Recargar preview** fuerza la recarga manual.
- El selector de dispositivo permite 390×844, 360×800, 412×915 y 430×932.

### 4. Purga del brick

Botón **🧹 Purgar caché** en:

- Cabecera de **SONIKA Hero Builder**.
- Cabecera de **Configurar contenido**.

También existe el endpoint:

```http
POST /api/v1/admin/hero/purge-cache
```

La purga:

- incrementa `theme.sonika.hero_cache_version`;
- invalida OPcache de `HeroService` y `SonikaHeroWidget` con
  `opcache_invalidate(..., true)`;
- limpia archivos `hero*` de `/cache` y `/storage/cache`;
- recarga el estudio móvil si está abierto;
- no borra configuración, slides, versiones ni imágenes.

### 5. Por qué no se hace `opcache_reset()` global

`opcache_reset()` borra la caché de **todos** los scripts PHP del sitio. Es
tentador, pero castiga a todos los usuarios y no es necesario: la configuración
del Hero es data, no código. Se invalida solo lo que cambió. Si se despliega
código nuevo, el flujo normal de deploy ya invalida OPcache por timestamp.

## Método recomendado

1. Guardar el theme. La versión de assets cambia y el preview móvil se recarga.
2. Si se reemplazó una imagen con la misma URL, guardar o pulsar **Purgar caché**.
3. En el teléfono, recargar la home. Con `no-cache`, el navegador revalida.
4. Si más adelante se agrega Cloudflare u otro CDN, purgar `/` y las URLs de
   imágenes del Hero desde el panel del CDN. El botón del brick no puede purgar
   un CDN externo sin credenciales/API.

## Por qué el primer slide no guardaba

Había un bug adicional en `admin.js`: el editor de composición escribía el índice
del slide con `W.esc(idx)`. Como `W.esc(0)` devolvía cadena vacía, el primer slide
quedaba con `data-i=""` y `W.repCollect()` lo ignoraba al guardar. El resultado era
una petición 200 con la composición vieja: el zoom del primer slide nunca cambiaba.

Se corrigió a `W.esc(String(idx))` y se agregó un respaldo en `W.repCollect()` para
usar la posición de la tarjeta cuando `data-i` falte.

## Actualizar el panel después de un deploy

El admin es una SPA: **navegar entre paneles no vuelve a cargar `admin.js`**. Si la
pestaña estaba abierta antes del deploy, sigue con el JS viejo.

Para forzar la actualización:

1. Abrir:
   ```
   https://sonika.com.co/admin.php?panel=seo-global-launch&fresh=1
   ```
   `admin.php` responde con `Clear-Site-Data: "cache"` y recarga con la versión nueva.
2. O cerrar la pestaña del admin y abrirla de nuevo.
3. O `Ctrl+F5` en el admin.
4. En la versión nueva, usar **⟳ Recargar panel** o hacer clic en el badge `build`.

## Auto-guardado y resumen

- Los cambios del theme se guardan automáticamente **1.6 s** después de la última
  edición. El botón **Guardar cambios** sigue disponible.
- La tarjeta **Último guardado** muestra, por slide:
  `PC {zoom}% · Móvil {zoom}%`.
- Si el resumen sigue mostrando el valor viejo, el problema no es caché: el payload
  no incluía el cambio.

