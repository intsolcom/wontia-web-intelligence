# Slogan de marca — administración

## Dónde está

**Administración → Settings → Logo, favicon y slogan**

El slogan se administra en la misma tarjeta del logo. La vista previa usa el logo y el alto actuales.

## Controles

| Control | Uso | Rango / valores |
|---|---|---|
| Mostrar slogan | Activa o desactiva el slogan | Sí / No |
| Texto | Texto visible | Máx. 80 caracteres |
| Tipo de letra | Tipografía tipo firma | Great Vibes, Dancing Script, Allura, Parisienne, Sacramento, Caveat |
| Tamaño | Tamaño en píxeles | 10–48 px |
| Separación vertical | Espacio superior respecto al logo | −20 a 40 px |
| Separación horizontal | Desplazamiento horizontal respecto al logo | −20 a 80 px |
| Color | Color del texto | Selector de color |
| Escala móvil | Reducción por debajo de 900 px | 50–100 % |
| Vista previa | Fondo claro u oscuro | Claro / Oscuro |

## Dónde se aplica

- **Header del sitio:** debajo del logo, dentro de `.logo-stack`.
- **Footer:** debajo del logo del footer.
- **PDF del pedido:** en el encabezado de “Resumen de pedido”, usando la misma tipografía, tamaño y color.

## Settings

| Key | Default |
|---|---|
| `brand_slogan_enabled` | `1` |
| `brand_slogan_text` | `El Sonido que te mueve` |
| `brand_slogan_font` | `Great Vibes` |
| `brand_slogan_size` | `18` |
| `brand_slogan_gap_y` | `4` |
| `brand_slogan_gap_x` | `0` |
| `brand_slogan_color` | `#F20D16` |
| `brand_slogan_mobile_scale` | `72` |

Los valores se sanean al leerlos: fuente permitida, tamaños dentro de rango y color hexadecimal válido.

## Tipografías

El sitio carga solo la tipografía seleccionada. El admin carga las seis para la vista previa. El PDF carga la seleccionada desde Google Fonts y espera a que esté lista antes de imprimir.

## Notas

- El color debe contrastar con el fondo del footer; la vista previa permite revisar fondo claro y oscuro.
- Si el slogan se desactiva, el sitio no carga la tipografía de firma.
- Si se borran los settings, se aplican los valores por defecto del código.
- La configuración no modifica el logo ni las tablas de catálogo.
