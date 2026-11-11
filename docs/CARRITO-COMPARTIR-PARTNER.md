# Compartir producto desde el carrito (amigo + Partner SONIKA)

**Versión:** 1.0.0
**Sitio de validación:** SONIKA (`https://sonika.com.co`)
**Estado:** implementado y desplegado.

---

## 1. Qué se agregó

En cada producto del carrito (`/carrito`) aparecen dos acciones:

1. **Compartir** — comparte el producto con un amigo, familiar o conocido.
2. **Comparte y gana** — abre la experiencia de Partner SONIKA con enlace de afiliado y comisión estimada.

Los mismos dos botones están disponibles en la ficha de producto (`/producto/{handle}`), debajo de las acciones de compra y de los sellos de confianza.

Los botones no se muestran en el checkout. Tampoco aparecen si el producto está agotado o tiene un problema de disponibilidad.

---

## 2. Modo amigo

Abre un modal flat, responsive y accesible con:

- Producto, imagen y precio.
- **WhatsApp** con mensaje listo.
- **Estado de WhatsApp** con imagen vertical 1080 × 1920.
- **Historia de Instagram** con imagen vertical.
- **Facebook** para publicar el enlace.
- **Historia de Facebook** con imagen vertical.
- **Email**.
- **Copiar enlace**.
- **Más opciones** con Web Share API del sistema.
- Mensaje editable con tres plantillas:
  - Directo.
  - Curioso.
  - Cercano.
- El enlace se agrega automáticamente si el usuario lo borra del mensaje.

Las imágenes para estados/historias se generan en el navegador con Canvas. En móvil se abre el menú nativo para elegir WhatsApp o Instagram; en escritorio se descarga la imagen y se copia el mensaje.

---

## 3. Modo Partner

### Partner sin sesión

Muestra:

- Comisión por venta **3% → 7%**.
- Cómo sube con Puntos SONIKA.
- Racha semanal.
- Productos outlet al 2%.
- Productos premium con puntos extra.
- CTA **Quiero ser Partner**.
- Acceso **Ya soy Partner**.
- Opción para compartir como amigo mientras decide registrarse.

### Partner con perfil en revisión

- Aviso de solicitud en revisión.
- Sin enlace de comisión activo.
- Acceso al panel.
- Opción de compartir como amigo.

### Partner aprobado

- Saludo personalizado:
  - `Hola {Nombre}, comparte y gana con este producto.`
- Comisión estimada por unidad.
- Tasa actual y techo del mes.
- Progreso a la siguiente tasa con Puntos SONIKA.
- Enlace de Partner listo para copiar.
- Las mismas opciones de compartir, pero con el enlace de afiliado.
- Mensaje de Partner editable.
- Opción de incluir la línea:
  - `Comprando con mi enlace apoyas mi proyecto musical.`

### Cálculo de la comisión

- Producto normal: `precio × tasa actual del Partner`.
- Producto outlet: `precio × 2%`.
- La comisión es estimada por unidad.
- La tasa se calcula con `AffiliateService::pointsForMonth()`.
- El techo y la racha salen de las reglas vigentes de SONIKA Partners.

---

## 4. Enlace de Partner

Formato:

```text
https://sonika.com.co/r/{code}?to=%2Fproducto%2F{handle}
```

La ruta `/r/{code}`:

1. Registra el clic del afiliado.
2. Activa la cookie `sonika_aff` por 30 días.
3. Redirige al producto con `?ref={code}`.

Esto es importante porque la atribución de pedidos exige un clic válido en los últimos 30 días. Un enlace directo `?ref=` no registraba el clic.

El parámetro `to` solo acepta rutas internas que empiecen por `/`. Si intenta salir a otro dominio, se ignora y se usa la ruta pública del Partner.

---

## 5. Privacidad

- No se envía nada a terceros hasta que el usuario pulsa una acción de compartir.
- La imagen se genera en el navegador.
- La consulta de metadatos del producto es al propio sitio.
- No se guardan datos de pago.
- El modo Partner solo está disponible si hay sesión de Partner.

---

## 6. Accesibilidad y experiencia

- Modal con `role="dialog"`, `aria-modal` y título asociado.
- Cierre con `Escape`, botón y clic fuera.
- Foco atrapado dentro del modal y restaurado al cerrar.
- `prefers-reduced-motion` respetado.
- Textos con contraste alto.
- En móvil el modal se convierte en hoja inferior.
- El textarea usa 16px en móvil para evitar el zoom automático de iOS.

---

## 7. Pruebas realizadas

- Carrito con dos productos reales:
  - 4 disparadores (2 por producto).
  - Sin botones en checkout.
- Ficha de producto:
  - Dos disparadores con producto, precio e imagen reales.
  - Modal de amigo y modo Partner.
  - Imagen de historia con la imagen externa del producto.
- Modo amigo:
  - Mensaje, plantillas, enlace, copiar y WhatsApp.
  - Imagen 1080 × 1920 para estado/historia.
  - Vista previa y descarga.
- Modo Partner:
  - Invitado.
  - Perfil en revisión.
  - Aprobado.
  - Comisión normal y outlet.
- Enlace de Partner:
  - Redirección `302` a `/producto/ukel16?ref=...`.
  - Cookie `sonika_aff` activa por 30 días.
  - Intento de redirección externa bloqueado.
- Sin errores de consola.
- Sin errores PHP en logs.
- Limpieza de clics y carritos de prueba.

---

## 8. Archivos

### Nuevos

- `public/assets/themes/sonika/share.css`
- `public/assets/js/sonika-share.js`
- `docs/CARRITO-COMPARTIR-PARTNER.md`

### Modificados

- `public/assets/js/sonika-payments.js` — botones por producto en el carrito.
- `templates/themes/sonika/lib.php` — contexto del Partner y carga de assets.
- `templates/themes/sonika/router.php` — sesión en carrito/producto, botones en la ficha de producto y destino seguro en `/r/{code}`.
- `templates/themes/sonika/carrito.php` — carga de los assets de compartir.

---

## 9. Rollback

- Restaurar el respaldo:
  - `/tmp/share-feature-backup-20261112.tgz`
- Eliminar los archivos nuevos:
  - `public/assets/themes/sonika/share.css`
  - `public/assets/js/sonika-share.js`
- Revertir el commit de la feature.

---

## 10. Limitaciones

- Instagram y Facebook no permiten publicar una historia directamente desde una web. Se entrega la imagen y se usa el menú nativo o la descarga.
- La comisión mostrada es una estimación; la comisión final depende de la compra confirmada, descuentos, outlet y reglas del programa.
- El enlace de Partner requiere que el perfil esté `APPROVED`.
- La imagen de historia usa la imagen del producto; si el servidor de imágenes no permite CORS, se dibuja un respaldo visual.
