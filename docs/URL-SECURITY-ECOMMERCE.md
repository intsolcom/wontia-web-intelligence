# URLs, seguridad y buenas prácticas para SONIKA

> Documento de referencia para decidir cómo se muestran las URLs del ecommerce sin sacrificar SEO, conversión ni seguridad.

## 1. Resumen ejecutivo

Las URLs amigables **no son un problema de seguridad**. Son la mejor práctica para SEO, accesibilidad, confianza y compartir en redes. Lo que sí es un problema es poner **datos sensibles o identificadores internos** en la URL y creer que “ocultar” una ruta la protege.

La regla correcta es:

- **Catálogo público:** URL legible, estable, canónica y sin datos sensibles.
- **Recursos privados:** identificador opaco (UUID/ULID) **+ autorización en servidor**; si se comparte por correo, token firmado con vencimiento.
- **Archivos privados:** URL firmada de corta duración (HMAC + expiración), nunca una carpeta “secreta”.
- **Administración:** autenticación fuerte (2FA), rate limit, `noindex` y, si se quiere, allowlist de IP o dominio separado. No depender de una ruta secreta.

Encriptar las URLs de producto no aporta seguridad real: el navegador, el historial, los logs, el `Referer`, los analytics y los buscadores siguen viendo la URL. Además rompe SEO, cache, enlaces compartidos y accesibilidad. La seguridad se logra con **control de acceso**, no con URLs ilegibles.

## 2. Qué debe ser público y qué debe ser privado

| Recurso | URL recomendada | Visible en buscadores | Seguridad |
|---|---|---|---|
| Producto | `/producto/guitarra-electrica-fender-player` | Sí | Público |
| Categoría | `/categorias/guitarras` | Sí | Público |
| Marca | `/marcas/fender` | Sí | Público |
| Tienda del Partner | `/artista/nombre-apellido` | Sí, si está aprobado | Público |
| Enlace de atribución | `/r/nombre-apellido-4821` | No | Código validado en servidor |
| Carrito | `/carrito` | No | `noindex` |
| Checkout | `/checkout` | No | `noindex`, HTTPS, CSRF |
| Pedido | `/pedido/{uuid}?t={token}` | No | UUID + token, autorización |
| Cuenta | `/cuenta` | No | Sesión autenticada |
| Panel Partner | `/artista/{slug}/panel` | No | Sesión + `noindex` |
| Contrato | `/artista/contrato` | No | Sesión + `noindex` |
| Cotización | `/cotizacion/{token}` | No | Token de alta entropía |
| Archivo privado | `/api/v1/.../download?token=...` | No | URL firmada con TTL |
| Admin | `/admin.php` | No | 2FA, rate limit, `noindex` |

## 3. Buenas prácticas de URLs para ecommerce

### 3.1. Catálogo público

1. **Slugs descriptivos y estables.** Ejemplo: `/producto/acordeon-hohner-rey-vallenato`.
2. **Minúsculas, guiones y sin acentos.** Google recomienda guiones (`-`) y no guiones bajos (`_`).
3. **Sin IDs secuenciales ni parámetros innecesarios.** Evitar `/producto/5915` o `?id=5915&cat=3`.
4. **Sin datos personales.** Nunca correo, teléfono, documento, dirección o nombre completo en la URL.
5. **Sin tokens de sesión.** Las sesiones van en cookies `HttpOnly`, no en la URL.
6. **Sin claves de API.** Nunca en query string; van en cabeceras y se rotan.
7. **Canonical correcto.** Cada producto debe tener una sola URL canónica.
8. **Redirección 301 al cambiar un slug.** Conserva el SEO y evita 404.
9. **Percent-encoding correcto.** Los caracteres no ASCII y los espacios se codifican; el servidor debe decodificar la ruta una sola vez.
10. **Sitemap solo con URLs canónicas y públicas.** Nada de borradores, pruebas, `noindex` ni rutas privadas.

### 3.2. Recursos privados y transaccionales

1. **UUID/ULID, no IDs secuenciales.** Un pedido no debe poder enumerarse cambiando `1`, `2`, `3`.
2. **Autorización en cada petición.** Tener la URL no debe ser suficiente para ver datos de otro cliente. OWASP lo llama *Broken Access Control / IDOR*.
3. **Token firmado con vencimiento** para enlaces enviados por correo:
   - `exp` (fecha de expiración),
   - `sub` (recurso),
   - `nonce` (un solo uso cuando aplique),
   - HMAC-SHA256 con secreto del servidor,
   - comparación en tiempo constante.
4. **Token de un solo uso** para acciones sensibles: descargar factura, cambiar contraseña, confirmar retiro, ver datos de tarjeta regalo.
5. **No poner el token en el `Referer`.** Usar `Referrer-Policy: strict-origin-when-cross-origin` o `no-referrer` en páginas privadas.
6. **Expiración corta** para descargas (5–15 minutos) y para enlaces de pago.
7. **Regenerar el token** si se reenvía el correo o si se detecta uso anómalo.

### 3.3. Enlaces de Partner y atribución

1. **Código legible pero no enumerable.** `nombre-apellido-4821` con número aleatorio; no usar el ID interno.
2. **Cookie first-party** `sonika_aff`, `Secure`, `SameSite=Lax`, 180 días.
3. **Ventana de comisión de 30 días** desde el último clic válido.
4. **Validación en servidor** en cada pedido: el código debe existir, el Partner debe estar aprobado y debe haber un clic dentro de la ventana.
5. **Alias históricos** para no romper enlaces viejos cuando cambie el formato.
6. **Sin PII en el enlace.** Nada de correo, teléfono o documento del Partner.

### 3.4. Administración y API

1. **2FA obligatorio** para administradores.
2. **Rate limit** en login, OTP, recuperación, checkout y endpoints públicos.
3. **CSRF** en formularios y acciones con cookies.
4. **Cookies** `HttpOnly`, `Secure`, `SameSite=Lax` o `Strict`.
5. **`noindex` y `robots.txt`** para admin, API, checkout, pedidos, cuenta y panel.
6. **Mensajes de error genéricos** al público; detalles solo en logs internos.
7. **No revelar versión de PHP, framework, rutas absolutas ni stack traces.**
8. **Webhooks con firma** (Mercado Pago, Wompi, Sistecredito) e idempotencia.
9. **Allowlist de IP** para webhooks cuando el proveedor lo permita.
10. **Logs con enmascaramiento** de UUID de pedido, tokens y códigos de atribución.

### 3.5. Cabeceras y transporte

Configuración recomendada (ya existe en `nginx.conf`):

```nginx
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=()" always;
add_header Strict-Transport-Security "max-age=15552000" always;
add_header Content-Security-Policy-Report-Only "...";
```

Siguiente fase: CSP en modo enforce con nonces/hashes, SRI en CDNs y `COOP`/`CORP` cuando el sitio no dependa de ventanas externas.

## 4. Cómo deben verse los links para el cliente

- **Producto:** `sonika.com.co/producto/acordeon-hohner-rey-vallenato`
- **Categoría:** `sonika.com.co/categorias/acordeones`
- **Marca:** `sonika.com.co/marcas/hohner`
- **Partner:** `sonika.com.co/r/daniela-perez-4821`
- **Pedido en la interfaz:** `PED-261112-68B5C` (número legible)
- **URL real del pedido:** `sonika.com.co/pedido/{uuid}` (no mostrar el UUID como si fuera el número de pedido)
- **Cotización:** `sonika.com.co/cotizacion/{token}` (botón “Ver cotización”, no mostrar el token en el título)
- **Descarga privada:** botón “Descargar factura” que apunta a una URL firmada temporal.

Lo importante visualmente:

1. El cliente ve una URL corta, legible y coherente.
2. No ve IDs internos, correos, teléfonos, tokens largos ni parámetros técnicos.
3. Los enlaces privados se muestran como acciones (“Ver pedido”, “Descargar”), no como texto con tokens.
4. Los enlaces de Partner se muestran con el nombre y el número de atribución, no con el ID de base de datos.

## 5. Qué se implementó en SONIKA

- Decodificación segura de la ruta y redirección 301 de slugs antiguos con espacios/acentos al slug canónico.
- Normalización obligatoria de slugs al guardar productos (minúsculas, sin acentos, guiones).
- Códigos de Partner `nombre-apellido-####` con número aleatorio y tabla de alias para enlaces antiguos.
- Cookie de atribución de 180 días y ventana de comisión de 30 días.
- Sesión de Partner de 8 horas con renovación deslizante y “recordarme” de 30 días.
- `X-Robots-Tag: noindex` en `/r/`, checkout, carrito, pedido, cuenta, login y panel.
- `robots.txt` con rutas sensibles obligatorias, aunque la configuración del panel no las incluya.
- Allowlist de destinos en `/r/{code}?to=...` para evitar redirecciones internas no deseadas.
- Cabeceras de seguridad en nginx, bloqueo de `_private`, bloqueo de PHP en uploads, UUID en pedidos y tokens en cotizaciones.
- Visibilidad de producto por IP (`visibility` + `allowed_ips`) con filtro en catálogo, búsqueda, ficha, carrito, API y sitemap.
- Envío gratis por producto (`free_shipping`) sin exponer reglas internas en la URL.

## 6. Brechas pendientes y prioridad

### P0 — antes de una campaña fuerte

1. **Token de acceso a pedido.** Añadir `?t=` firmado a los enlaces de correo y exigirlo para ver dirección, teléfono y códigos/PIN de tarjetas regalo. Mantener el UUID solo para estado básico.
2. **Webhook Secret de Mercado Pago.** Validar HMAC en todas las notificaciones.
3. **2FA obligatorio para administradores** y rate limit en login/OTP.
4. **Revisar logs** para confirmar que no se registran UUID, tokens ni códigos de atribución.

### P1 — siguiente iteración

5. **Tabla de alias de slugs de producto** para 301 permanentes y sin ambigüedad.
6. **URLs firmadas para descargas privadas** (facturas, comprobantes, documentos de Partner).
7. **CSP en modo enforce** con nonces/hashes.
8. **SRI** en scripts y hojas de estilo externos.
9. **Enmascarar `/pedido/` y `/r/` en logs nginx** (el `map` ya enmascara cotizaciones).
10. **Rate limit específico** por IP y por cuenta en checkout, cupones y tarjetas regalo.

### P2 — madurez

11. WAF/bot management y detección de scraping de catálogo.
12. Escaneo automático de seguridad (headers, mixed content, archivos sensibles, uploads ejecutables).
13. Firma de integridad de dependencias y SBOM.
14. Revisión periódica de accesos de administradores y rotación de secretos.

## 7. Lo que no se debe hacer

- Encriptar u ofuscar las URLs públicas del catálogo “para que no las copien”. No protege nada y destruye SEO.
- Usar links temporales para productos públicos: rompe compartir, cache y posicionamiento.
- Confiar en que una ruta secreta de admin es suficiente.
- Poner el ID de la base de datos en la URL de pedidos, facturas o clientes.
- Poner correo, teléfono, documento o dirección en la URL.
- Poner tokens largos en el texto visible de la página.
- Usar `?id=123` para recursos privados sin comprobar que el usuario autenticado sea el dueño.
- Registrar en logs la URL completa de pedidos, pagos o atribución sin enmascarar.

## 8. Referencias

- Google Search Central — URL structure best practices: https://developers.google.com/search/docs/crawling-indexing/url-structure
- Google Search Central — Link best practices: https://developers.google.com/search/docs/crawling-indexing/links-crawlable
- OWASP — Broken Access Control (A01:2021): https://owasp.org/Top10/A01_2021-Broken_Access_Control/
- OWASP — Insecure Direct Object Reference Prevention Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Insecure_Direct_Object_Reference_Prevention_Cheat_Sheet.html
- OWASP — HTTP Security Response Headers Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/HTTP_Headers_Cheat_Sheet.html
- OWASP — Session Management Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html
- PCI DSS — nunca almacenar datos de tarjeta en URLs ni logs.
