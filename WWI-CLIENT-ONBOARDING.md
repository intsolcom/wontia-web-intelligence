# WWI — Onboarding de cliente nuevo (instalar WWI en dominio nuevo)

Método repetible para vender/publicar un sitio WWI a un cliente nuevo. **Universal-first**: primero la
plataforma, luego el sitio.

## Requisitos previos (una vez)
- Central `wwi.wontia.com` (SITE_ID=5) con `/app/deploy-queue` y **auto-deployer** activo (cron `* * * * *`).
- Wildcard DNS `*.wontia.com → <VPS_IP>` (para el subdominio temporal) y cert Let's Encrypt wildcard.
- Al menos un **plan** en Factory → Planes.
- SMTP global en Factory → Config (`wwi.mail_*`) para correos transaccionales.

## Alta de cliente (CLI, recomendado)
```bash
docker exec wontia-wwi php /app/public/provision.php \
  --client="Panadería La Esquina" --email=dueno@dominio.com \
  --domain=panaderialaesquina.com --plan=starter --theme=default --sector=restaurant
```
Devuelve JSON con `tenant_id`, `admin_url`, `username`, `password`.

Qué hace (idempotente por dominio):
1. Crea el pedido y **provisiona el tenant** (`FactoryService::provisionSite`): site, usuario cliente,
   dominio (DNS_PENDING), 3 buzones, página home + secciones, (tienda si el plan es ecommerce), saldo.
2. Fija **tema**, `site_name` y `logo_text`.
3. Genera **credenciales** del cliente.
4. Dispara los jobs (email de bienvenida + **deploy**).
El **auto-deployer** crea en <1 min: contenedor (puerto auto 4011+), vhost nginx, **cert SSL** → marca el
site PUBLISHED y el dominio ACTIVE. El **menú y la config del tema se autosiembran** al primer render.

## Alta desde el panel (alternativa)
Factory → **Sitios → Añadir** (o Pedidos → crear + marcar pagado) → el provisioning y el deploy corren solos.

## Checklist de puesta en producción
- [ ] DNS del dominio del cliente → A → `<VPS_IP>` (o subdominio `*.wontia.com`).
- [ ] SSL emitido (certbot) y 443 sirviendo.
- [ ] Health del contenedor: `docker ps | grep wontia` + `GET https://<dominio>/api/v1/health`.
- [ ] `site_name`, **logo/favicon** (Settings → Logo & Favicon), **Menús** (Settings → Menús),
      **config de tema** (Settings → Themes → ⚙ Configurar).
- [ ] Contenido (Pages/Builder/Tienda) editado por el cliente desde su admin.
- [ ] SMTP del sitio (Factory → Config + *Sincronizar correo*) o heredado por provisioning.
- [ ] Backups (cron del host) y monitoreo del badge de jobs.
- [ ] Credenciales entregadas y **2FA** sugerido (Settings → Seguridad).
- [ ] Si el cliente quiere un **theme premium**: generar serial en el central (Settings → Themes) y el
      cliente lo canjea en **Settings → Themes → 🔒 Desbloquear**.

## Regla de oro (contrato CMS)
El cliente administra **contenido** desde su admin; **nunca** se hardcodea contenido en el tema. La
estructura del CMS la mantiene el **Head Team Leader** (ver `AGENT-BRIDGE.md`).
