# Hero — zoom del fondo en móvil

## Síntoma

En **SONIKA Hero Builder → Theme del Hero → El Sonido Se Apodera → Composición avanzada**,
el control de zoom del fondo empezaba en 100% y no permitía bajar de 100%. En móvil no se
podía hacer zoom out para ver la imagen más pequeña.

## Causa

El límite estaba repetido en tres capas:

- `admin.js`: `<input type="range" min="100" max="250">`.
- `HeroService::normalizeComposition()`: `max(100, min(250, zoom))`.
- `SonikaHeroWidget`: `max(100, min(250, zoom))` para escritorio y móvil.

Además, el estudio móvil usaba un iframe de **390 × 430 px**, que no representa un teléfono
vertical actual.

## Solución

- Rango de zoom **50%–250%** en admin, backend y renderer.
- **Atajos**: 50%, 75%, 100%, 125%, **Llenar** (`cover`) y **Ver completa** (`contain`).
- **Fondo desenfocado**: cuando el zoom es menor a 100% (o el ajuste es `contain`), la
  imagen se reduce y el espacio libre se rellena con una copia desenfocada de la misma foto.
  A 100% el relleno queda oculto y no consume pintura.
- **Vista previa vertical** en la composición: el preview cambia a proporción **390 × 844**
  al seleccionar Móvil, con una etiqueta “Móvil · 390×844”.
- **Estudio móvil**: iframe de **390 × 844** (iPhone 14/15), selector de dispositivo
  (Android 360×800, Pixel 7 412×915, iPhone Pro Max 430×932) y botón **Alto de pantalla**
  para igualar la altura del Hero al teléfono elegido.
- **Altura móvil** ampliada de 480–720 px a **360–960 px**. En pantallas más bajas el CSS
  limita el Hero a `100dvh`.
- El theme guarda la composición por dispositivo: PC y Móvil pueden tener zoom distinto.

## Cómo usarlo

1. Ir a **SONIKA Hero Builder** y abrir el theme **El Sonido Se Apodera**.
2. **Configurar contenido** → **Slides del Hero** → abrir un slide.
3. Abrir **Composición avanzada: fondo y posición de elementos**.
4. En **Dispositivo**, elegir **Móvil**.
5. Mover **Zoom** entre 50% y 250%, o usar los atajos.
6. Guardar los cambios.

Referencias:

- **100%**: la imagen cubre el Hero.
- **Menos de 100%**: la imagen se hace más pequeña y aparece el fondo desenfocado.
- **Ver completa**: usa `contain` para mostrar toda la foto.
- **Llenar**: vuelve a `cover` al 100%.

## Verificación

Se probó con la composición móvil al **60%** en un viewport de **390 × 844**:
la imagen se redujo y el fondo desenfocado rellenó el espacio. Después se restauró el
valor original a **100%**.

> El estudio móvil es una vista previa. Los cambios reales de zoom se guardan por slide en
> **Composición avanzada**.
