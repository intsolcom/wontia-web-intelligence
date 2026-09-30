# WWI — LOGO & FAVICON MANAGER MASTER PROMPT

> **Versión:** 1.0 · **Fecha:** septiembre 2026 · **Modo:** PEDS (Inspeccionar → Proponer → Entregar → Verificar)
> **Requerimiento:** en **Settings** (menú izquierdo del admin) añadir una pestaña **“Logo”** con un sistema completo para administrar la marca del sitio: subir/elegir logo (PNG, JPG, WebP, GIF, ICO), **slider horizontal para redimensionar** el logo **proporcionalmente** dentro de su contenedor, y un apartado para **subir/definir el favicon**. La función debe estar **disponible en TODOS los sitios WWI** existentes (multi-site).

## 1. INSPECCIÓN (estado actual)
- **Settings** (`W.renderSettings`, `admin.js`): solo una tarjeta de claves sueltas (`site_name`, `site_description`, `ga_measurement_id`, `cookie_consent_enabled`, `primary_color`, `logo_text`) + tarjeta de **Tema**. **No** hay gestión de logo ni favicon.
- **Media** (`/api/v1/admin/media/upload`, campo `file`): acepta `jpg/png/webp/gif/ico` (máx 5MB), valida `getimagesize`, **bloquea SVG**. Biblioteca con `GET /media`.
- **Settings** (`settings` por `site_id`): `GET/PUT /api/v1/admin/settings` (clave/valor arbitrario). **Sin tabla nueva necesaria**.
- **Temas** (`default`, `wwi`, `wwi-intelligence`): el header usa marca fija (letra “W” + texto) y el favicon es un SVG inline fijo. No leen settings.

## 2. ARQUITECTURA
- Claves de settings (por sitio, `@site_id`): `logo_image` (URL), `logo_height` (px), `logo_show_text` (1/0), `logo_text` (texto), `logo_alt` (alt), `favicon` (URL), `favicon_touch` (apple-touch-icon), `logo_dark` (versión para fondo oscuro).
- **Parcial compartido** `templates/themes/_shared/brand.php` que lee settings del sitio y expone helpers: `wwi_favicon_links()` (tags de favicon) y `wwi_logo_img($max)` (markup del logo). Los 3 temas lo usan → **disponible en todos los sitios**.
- El **slider** no recorta la imagen: controla la **altura** del logo (`logo_height`) manteniendo proporción (`height:auto`), dentro de un contenedor con ancho máximo.

## 3. LAS 10 INNOVACIONES
1. **Pestaña “Marca/Logo”** en Settings con sub-pestañas (Logo · Favicon · Avanzado).
2. **Slider de tamaño proporcional** (altura px 16–160) con **preview en vivo** y límites de contenedor (max-width).
3. **Zona de carga drag&drop** + selector de **Biblioteca de Media** + URL manual.
4. **Versión clara/oscura** del logo (logo para fondo claro y otro para oscuro) según el tema.
5. **Favicon multi-formato** (.ico, PNG 32/180, SVG futuro) + `apple-touch-icon` + `theme-color`.
6. **Preview en contexto**: muestra el logo sobre el color real del header del tema (con el texto opcional al lado).
7. **Validación y seguridad**: whitelist de formatos, `getimagesize`, bloqueo SVG, límite de peso, alt obligatorio (a11y).
8. **Aplicación global multi-site**: el parcial es compartido; cada sitio guarda su propio logo/favicon (`@site_id`).
9. **“Usar logo del sitio X”**: copiar logo/favicon de otro sitio (marca madre) con un clic.
10. **Historial/versión**: conservar el logo anterior y permitir “restaurar” (rollback de marca).

## 4. GOBERNANZA (PEDS — Monitor de Código, 6 puntos)
1. **Sintaxis**: `php -l` / `node --check`.
2. **Seguridad**: subida validada (whitelist + `getimagesize`, sin SVG), escape de salida, `@site_id` en toda query, sin secretos.
3. **Buenas prácticas**: reutilizar `/media/upload` y `/settings`; parcial compartido (sin duplicar por tema).
4. **Idempotencia**: `PUT settings` con `ON DUPLICATE KEY`; el parcial no rompe sin settings (defaults).
5. **Accesibilidad/UX**: `alt` obligatorio, slider con teclado/foco, preview, estados loading/empty/error.
6. **Verificación**: prueba en vivo (subir logo → slider → guardar → ver en el sitio y en favicon) en al menos un tenants.

## 5. CRITERIO DE ACEPTACIÓN
- En **Settings → Logo** se puede **subir/elegir** el logo, **redimensionarlo** con el slider (proporcional) y **guardar**.
- Se puede **subir/definir el favicon**.
- El cambio se refleja en el **sitio publicado** y en el **favicon**.
- Disponible en **todos los sitios**; cada uno con su propia marca. Monitor de Código (6 puntos) pasa.
