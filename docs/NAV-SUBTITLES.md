# Subtítulos animados en los menús

**Versión:** 1.0.0
**Sitio de validación:** SONIKA (`https://sonika.com.co`)
**Estado:** implementado y desplegado.

---

## 1. Qué se agregó

El ítem **SONIKA Partners** del menú principal ahora se muestra en dos niveles:

```text
SONIKA Partners
Comparte, vende y gana.
```

- El nombre principal mantiene el tamaño del menú.
- El subtítulo usa una tipografía menor, más ligera y con menor opacidad, para no competir con el nombre.
- El subtítulo aparece desde abajo hacia arriba y se repite cada **10 segundos**.
- El efecto se puede cambiar por ítem.

La configuración quedó en:

```text
Settings → Menús → Subtítulo
```

---

## 2. Panel de administración

En **Settings → Menús**, cada ítem tiene un botón **Subtítulo**.

El editor permite:

- Texto del subtítulo.
- Animación:
  - Sin animación.
  - Subir desde abajo (`rise`).
  - Aparecer con fade (`fade`).
  - Deslizar suave (`slide`).
- Intervalo de repetición en segundos (3–60).
- Tamaño de letra en píxeles (8–16).
- Color del subtítulo.
- Vista previa en vivo.
- Botón **Probar animación**.
- Guardar o quitar el subtítulo.

Los campos están disponibles para todos los ítems de todos los menús. Por ahora el render de subtítulos está activo en el **menú principal**; los footers conservan los datos para una activación posterior.

---

## 3. SONIKA Partners

El ítem se sembró automáticamente con:

```text
label: SONIKA Partners
url: /sonika-partners
subtitle: Comparte, vende y gana.
subtitle_animation: rise
subtitle_interval: 10
subtitle_size: 10
subtitle_color: #c9c9c9
```

La siembra es idempotente:

- Si el ítem no existe, se crea.
- Si existía con el texto largo anterior, se corrige el nombre y se completa el subtítulo.
- Si el usuario lo elimina después de la siembra, no se vuelve a crear.
- Si una carrera dejó duplicados, se conserva uno y se eliminan los demás.

---

## 4. Render

El menú principal renderiza:

```html
<a href="/sonika-partners" class="has-mnl-sub">
  <span class="mnl-label">SONIKA Partners</span>
  <span class="mnl-sub" data-anim="rise" style="--mnl-sub-interval:10s;--mnl-sub-size:10px;--mnl-sub-color:#c9c9c9">
    <span class="mnl-sub__inner">Comparte, vende y gana.</span>
  </span>
</a>
```

La animación usa solo `transform` y `opacity`:

- `rise`: entra desde abajo, se mantiene y sale hacia arriba.
- `fade`: aparece y desaparece con opacidad.
- `slide`: entrada/salida suave.

El contenedor tiene `overflow:hidden`, por lo que el texto se revela sin mover el layout del menú.

### Accesibilidad

- `prefers-reduced-motion: reduce` desactiva la animación y deja el subtítulo visible.
- El subtítulo no reemplaza el nombre principal.
- El enlace conserva su URL y su área táctil.

---

## 5. Backend

### Columnas nuevas en `nav_items`

```text
subtitle VARCHAR(220)
subtitle_animation VARCHAR(20)
subtitle_interval INT
subtitle_size INT
subtitle_color VARCHAR(20)
```

Se agregan de forma idempotente con `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`.

La migración se ejecuta una sola vez y se marca con:

```text
settings.key = nav_items_subtitle_migrated
settings.value = 1
```

Así no se ejecutan los `ALTER TABLE` en cada visita.

### Endpoint

```text
POST /api/v1/admin/nav/items/{id}/subtitle
```

Body:

```json
{
  "subtitle": "Comparte, vende y gana.",
  "subtitle_animation": "rise",
  "subtitle_interval": 10,
  "subtitle_size": 10,
  "subtitle_color": "#c9c9c9"
}
```

El endpoint actualiza solo los campos del subtítulo, sin tocar etiqueta, URL, visibilidad ni padre.

### Bandera de siembra

```text
settings.key = sonika_partner_nav_seeded
settings.value = 1
```

Evita que el ítem reaparezca si el usuario lo elimina.

---

## 6. Pruebas realizadas

- Siembra del ítem SONIKA Partners.
- Corrección del texto largo anterior a `SONIKA Partners`.
- Subtítulo visible en el menú principal.
- Animación `mnlSubRise` con duración de 10s.
- Orden correcto: Inicio, Tienda, Directorio, SONIKA Partners, Ofertas, Contacto.
- Sin duplicados.
- Editor de subtítulo en el panel:
  - Carga de valores actuales.
  - Vista previa animada.
  - Guardado de texto, animación, intervalo, tamaño y color.
  - Quitar subtítulo.
- Persistencia y lectura de los campos.
- Sin errores PHP ni de consola.

---

## 7. Rollback

- Restaurar el respaldo:
  - `/tmp/nav-subtitle-backup-20261112.tgz`
- Revertir el commit de la feature.
- Opcional: eliminar el ítem `SONIKA Partners` de `nav_items` o volver a poner el texto largo.
- La bandera `sonika_partner_nav_seeded` puede eliminarse si se quiere volver a sembrar.
