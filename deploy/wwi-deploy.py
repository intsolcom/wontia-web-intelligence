#!/usr/bin/env python3
import json, os, hmac, hashlib, subprocess, sys, glob, time

QUEUE = "/var/lib/dokploy/wontia-deploy"
LOG = "/var/log/wwi-deploy.log"
DONE = QUEUE + "/done"
IMAGE = "wontia-web-intelligence:latest"
NETWORK = "intsolcom"
UPLOADS = "/var/lib/dokploy/uploads"
WWWROOT = "/var/www/html"

def log(msg):
    line = time.strftime("%Y-%m-%d %H:%M:%S") + " " + msg
    with open(LOG, "a") as f:
        f.write(line + "\n")
    print(line)

def run(cmd, timeout=240):
    return subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=timeout)

def secret():
    try:
        with open(QUEUE + "/.secret") as f:
            return f.read().strip()
    except Exception:
        return ""

def verify(req):
    s = secret()
    if not s:
        return False
    canonical = "|".join([
        str(req.get("site_id", "")),
        str(req.get("slug", "")),
        str(req.get("domain", "")),
        str(req.get("brick_key", "")),
        str(req.get("name", "")),
        str(req.get("user_email", "")),
        str(req.get("custom_domain", "")),
    ])
    expect = hmac.new(s.encode(), canonical.encode(), hashlib.sha256).hexdigest()
    return hmac.compare_digest(expect, req.get("sign", ""))

def free_port():
    used = set()
    out = run("ss -tln | grep -oE ':[0-9]+' | tr -d ':'")
    for line in out.stdout.splitlines():
        line = line.strip()
        if line.isdigit():
            used.add(int(line))
    port_file = QUEUE + "/.port"
    nxt = 4011
    try:
        with open(port_file) as f:
            nxt = int(f.read().strip() or 4011)
    except Exception:
        pass
    while nxt in used:
        nxt += 1
    with open(port_file, "w") as f:
        f.write(str(nxt + 1))
    return nxt

def ensure_vhost(domain, port, cert_ready):
    conf = "/etc/nginx/sites-enabled/auto-" + domain + ".conf"
    ssl = ""
    if cert_ready:
        ssl = f"""
server {{
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name {domain};

    ssl_certificate /etc/letsencrypt/live/{domain}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/{domain}/privkey.pem;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    location /assets/ {{
        proxy_pass http://127.0.0.1:{port};
        proxy_set_header Host $host;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }}
    location / {{
        proxy_pass http://127.0.0.1:{port};
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }}
}}
"""
    cfg = f"""server {{
    listen 80;
    listen [::]:80;
    server_name {domain};

    location /.well-known/acme-challenge/ {{
        root {WWWROOT};
    }}

    location / {{
        return 301 https://$host$request_uri;
    }}
}}
{ssl}
"""
    with open(conf, "w") as f:
        f.write(cfg)
    run("nginx -t")
    run("systemctl reload nginx")

def process(path):
    try:
        with open(path) as f:
            req = json.load(f)
    except Exception as e:
        log("BAD JSON " + path + ": " + str(e))
        return
    if not verify(req):
        log("BAD SIGNATURE " + path)
        os.rename(path, path + ".bad")
        return
    slug = req.get("slug", "cliente")
    domain = req.get("domain", "")
    site_id = req.get("site_id", 0)
    name = req.get("name", "")
    brick_key = req.get("brick_key", "")
    container = "wontia-" + slug

    port = free_port()
    up = UPLOADS + "/wontia-" + slug
    os.makedirs(up, exist_ok=True)

    run(f"docker rm -f {container} >/dev/null 2>&1")
    r = run(
        f"docker run -d --name {container} --network {NETWORK} -p {port}:80 "
        f"-e SITE_ID={site_id} -e APP_URL=https://{domain} -e APP_NAME='{name}' -e BRICK_API_KEY={brick_key} "
        f"-v {up}:/app/public/assets/uploads --restart unless-stopped {IMAGE}"
    )
    if r.returncode != 0:
        log("DOCKER FAIL " + slug + ": " + r.stderr[-300:])
        return

    ensure_vhost(domain, port, False)
    time.sleep(1)
    cert = run(
        f"certbot certonly --webroot -w {WWWROOT} -d {domain} "
        "--non-interactive --agree-tos --register-unsafely-without-email"
    )
    if cert.returncode == 0:
        ensure_vhost(domain, port, True)
        log("SSL OK " + domain)
    else:
        log("SSL FAIL " + domain + " (site on http pending): " + cert.stderr[-200:])

    run(
        "docker exec -i mysql-prod mysql -uwontia -pWontia2026! wontia "
        f"-e \"UPDATE sites SET status='PUBLISHED' WHERE id={site_id}; "
        f"UPDATE wwi_domains SET status='ACTIVE' WHERE site_id={site_id} AND name='{domain}';\" 2>/dev/null"
    )
    os.makedirs(DONE, exist_ok=True)
    os.rename(path, DONE + "/" + os.path.basename(path))
    log(f"DEPLOYED {domain} -> {container}:{port} (site {site_id})")

def main():
    if not secret():
        log("NO SECRET — skipping")
        return
    for path in sorted(glob.glob(QUEUE + "/*.json")):
        if os.path.basename(path).startswith("update-"):
            continue
        try:
            process(path)
        except Exception as e:
            log("ERROR " + path + ": " + str(e))

if __name__ == "__main__":
    main()
