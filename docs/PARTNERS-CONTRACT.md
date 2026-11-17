# Contrato SONIKA Partners — perfil dinámico y firma pendiente

**Versión actual:** `1.2`  
**Sitio:** SONIKA (`https://sonika.com.co`)

---

## 1. Un solo contrato para todos los perfiles

El contrato es el mismo para:

- Artista.
- Influencer / creador.
- Persona natural.
- Comercial / negocio.

La única diferencia es el campo dinámico `{{PERFIL}}`, que se reemplaza al mostrar y firmar el contrato con el perfil elegido por el Partner.

Ubicación de la cláusula:

```text
3. PERFIL DEL PARTNER
El Partner se registra bajo el perfil de {{PERFIL}}...
Para los perfiles distintos de Artista, no se incluyen servicios de
contratación para eventos, presentaciones, toques, moñas, booking ni
representación artística.
```

La página pública del contrato muestra una versión genérica:

```text
el perfil seleccionado al registrarse (Artista, Influencer, Persona natural o Comercial)
```

La página de firma muestra el perfil real del Partner y lo registra en la aceptación.

---

## 2. Evidencia de firma

Cada aceptación guarda:

- `contract_version`.
- `profile_category`.
- `contract_hash` calculado sobre el contrato personalizado.
- `signature_image`.
- `signature_hash`.
- Nombre y documento del firmante.
- Fecha, IP y agente de usuario.

El hash del contrato incluye el perfil elegido. Si el contrato cambia de versión, el Partner debe firmar la nueva versión.

---

## 3. Aviso en el panel

Al iniciar sesión, si el Partner no ha firmado la versión vigente:

- Se muestra un aviso destacado: **“Falta firmar tu contrato”**.
- Se indica el perfil que se registrará.
- El botón **“Firmar contrato ahora”** lleva a `/artista/contrato`.
- El enlace del encabezado cambia a **“Firmar contrato”**.
- El wizard de primeros pasos mantiene el paso de contrato pendiente.
- Cuando firma, el aviso desaparece y el enlace cambia a **“Contrato firmado ✓”**.

---

## 4. Versiones anteriores

La versión `1.1` permanece en la base para conservar las firmas históricas. La versión vigente es `1.2`.

Los Partners que firmaron `1.1` verán la alerta para firmar `1.2`, porque la nueva versión incorpora el perfil del Partner en la evidencia contractual.

---

## 5. Historial de versiones

La página de firma muestra un historial con:

- Versión.
- Vigencia.
- Estado: firmado o pendiente.
- Perfil registrado.
- Fecha de firma.

La versión vigente se resalta en verde. Las versiones anteriores se conservan como evidencia.

---

## 6. Recordatorio automático

En **Tienda → Configuración → Contrato Partner**:

- Activar/desactivar el recordatorio.
- Días después del registro para enviar el correo.

Valor por defecto: **desactivado**, 3 días. El job `public/job-runner.php` envía el recordatorio una sola vez por Partner y versión de contrato. Los envíos quedan registrados en `affiliate_contract_reminders`.

---

## 7. Archivos

- `src/Services/ArtistContractService.php`
- `templates/themes/sonika/contrato-artista.php`
- `templates/themes/sonika/contrato-partner-publico.php`
- `templates/themes/sonika/router.php`
- `public/job-runner.php`
- `src/Services/StoreService.php`
- `public/assets/js/admin.js`

**Respaldos:**
- `/tmp/contract-backup-20261112.tgz`
- `/tmp/contract-extra-backup-20261112.tgz`
