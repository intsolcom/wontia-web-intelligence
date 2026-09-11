# WWI — Deploy & Update desde Git

## Flujo de actualización (una vez configurado)

```
1. Trabajas y haces push a GitHub (rama main)
2. En el VPS (o desde tu PC):  bash /opt/wwi-src/deploy/wwi-update.sh
3. El script hace: git pull → copia .env de secretos → docker build →
   recrea TODOS los contenedores del manifiesto → smoke test
```

## Setup inicial en el VPS (ya ejecutado)

```bash
# 1. Secretos (NO en el repo)
mkdir -p /root/wwi-secrets /root/wwi-keys
cp /tmp/wontia-build/app/.env /root/wwi-secrets/.env   # credenciales de producción
chmod 600 /root/wwi-secrets/.env

# 2. BRICK keys por tenant (una por contenedor que la use)
echo "<key-site1>"   > /root/wwi-keys/site1.key
echo "<key-factory>" > /root/wwi-keys/factory.key
echo "<key-demo6>"   > /root/wwi-keys/demo6.key
chmod 600 /root/wwi-keys/*.key

# 3. Clonar el repo
git clone https://github.com/intsolcom/wontia-web-intelligence.git /opt/wwi-src
```

## Desde Windows (tu PC)

```powershell
# Actualizar el sistema en el VPS con lo último de Git:
ssh -i $env:USERPROFILE\.ssh\contabo_vps root@<VPS_IP> "bash /opt/wwi-src/deploy/wwi-update.sh"
```

## Reglas

- **Nunca** poner API keys ni `.env` en el repo: van en `/root/wwi-secrets/` y `/root/wwi-keys/`.
- El manifiesto `deploy/containers.conf` define cada tenant (puerto, SITE_ID, APP_URL, volumen).
- Agregar un tenant nuevo = agregar una línea al manifiesto + su key en `/root/wwi-keys/` + `wwi-update.sh`.
- El auto-deployer (`/root/wwi-deploy.py`) sigue creando contenedores de clientes automáticamente; este script es para actualizar el CÓDIGO de los contenedores administrados.
