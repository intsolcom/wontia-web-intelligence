#!/usr/bin/env python3
import json, os, hmac, hashlib, subprocess, time, glob, shutil

QUEUE = "/var/lib/dokploy/wontia-deploy"
DONE = QUEUE + "/done"
LOG = "/var/log/wwi-update.log"
STATUS = QUEUE + "/update-status.json"
REPO = "https://github.com/intsolcom/wontia-web-intelligence.git"
SRC = "/tmp/wwi-src"
APP = "/tmp/wontia-build/app"
IMAGE = "wontia-web-intelligence:latest"
PREV = "wontia-web-intelligence:previous"

_STATE = {"started_at": 0}

def status(step, pct, message="", **extra):
    data = {
        "status": "running",
        "step": step,
        "pct": pct,
        "message": message,
        "started_at": _STATE["started_at"],
        "updated_at": int(time.time()),
    }
    data.update(extra)
    try:
        with open(STATUS, "w") as f:
            json.dump(data, f)
    except Exception:
        pass

def log(msg):
    line = time.strftime("%Y-%m-%d %H:%M:%S") + " " + msg
    with open(LOG, "a") as f:
        f.write(line + "\n")
    print(line)

def run(args, timeout=900):
    if isinstance(args, str):
        args = args.split()
    return subprocess.run(args, capture_output=True, text=True, timeout=timeout)

def sh(cmd, timeout=900):
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
    canonical = "system_update|" + str(req.get("ts", ""))
    expect = hmac.new(s.encode(), canonical.encode(), hashlib.sha256).hexdigest()
    return hmac.compare_digest(expect, req.get("sign", ""))

def container_configs():
    out = sh("docker ps --format '{{.Names}}'")
    names = [l.strip() for l in out.stdout.splitlines() if l.strip().startswith("wontia-")]
    configs = []
    for n in names:
        img = sh(f"docker inspect -f '{{{{.Config.Image}}}}' {n}").stdout.strip()
        if not img.startswith("wontia-web-intelligence"):
            continue
        env = json.loads(sh(f"docker inspect -f '{{{{json .Config.Env}}}}' {n}").stdout or "[]")
        binds = json.loads(sh(f"docker inspect -f '{{{{json .HostConfig.Binds}}}}' {n}").stdout or "[]") or []
        ports = json.loads(sh(f"docker inspect -f '{{{{json .HostConfig.PortBindings}}}}' {n}").stdout or "{{}}") or {}
        net = sh(f"docker inspect -f '{{{{.HostConfig.NetworkMode}}}}' {n}").stdout.strip() or "intsolcom"
        restart = sh(f"docker inspect -f '{{{{.HostConfig.RestartPolicy.Name}}}}' {n}").stdout.strip() or "unless-stopped"
        host_port = ""
        for k, v in ports.items():
            if k.startswith("80/") and v:
                host_port = v[0].get("HostPort", "")
        configs.append({"name": n, "env": env, "binds": binds, "port": host_port, "net": net, "restart": restart})
    return configs

def recreate(c, image):
    run(["docker", "rm", "-f", c["name"]])
    args = ["docker", "run", "-d", "--name", c["name"], "--network", c["net"]]
    if c["port"]:
        args += ["-p", f"{c['port']}:80"]
    for e in c["env"]:
        args += ["-e", e]
    for b in c["binds"]:
        args += ["-v", b]
    args += ["--restart", c["restart"], image]
    r = run(args, timeout=120)
    if r.returncode != 0:
        log("RECREATE FAIL " + c["name"] + ": " + r.stderr[-300:])
    return r.returncode == 0

def finish(path, result):
    os.makedirs(DONE, exist_ok=True)
    dest = DONE + "/" + os.path.basename(path)
    with open(dest, "w") as f:
        json.dump(result, f, indent=2)
    if os.path.exists(path):
        os.remove(path)

def process(path):
    req = json.load(open(path))
    if not verify(req):
        log("BAD SIGNATURE " + path)
        os.rename(path, path + ".bad")
        return
    started = time.time()
    _STATE["started_at"] = int(started)
    status("init", 3, "Preparando actualización")
    log("UPDATE START requested_by=" + str(req.get("requested_by", "?")))
    sh(f"docker tag {IMAGE} {PREV}")
    status("clone", 10, "Descargando el último commit desde Git")
    sh(f"rm -rf {SRC}")
    clone = sh(f"git clone --depth 1 {REPO} {SRC}", timeout=300)
    if clone.returncode != 0:
        log("CLONE FAIL: " + clone.stderr[-300:])
        status("clone", 10, "Error descargando el repositorio", status="failed", error=clone.stderr[-300:])
        finish(path, {"ts": req.get("ts"), "status": "failed", "step": "clone", "error": clone.stderr[-500:]})
        return
    commit = sh(f"git -C {SRC} rev-parse --short HEAD").stdout.strip()
    status("sync", 20, "Sincronizando archivos (preserva configuración)", commit=commit)
    rsync = sh(f"rsync -a --delete --exclude '.env' --exclude 'vendor/' --exclude 'cache/' --exclude 'public/assets/uploads/' {SRC}/ {APP}/", timeout=300)
    if rsync.returncode != 0:
        log("RSYNC FAIL: " + rsync.stderr[-300:])
        status("sync", 20, "Error sincronizando archivos", status="failed", error=rsync.stderr[-300:], commit=commit)
        finish(path, {"ts": req.get("ts"), "status": "failed", "step": "sync", "commit": commit})
        return
    status("build", 40, "Construyendo la nueva imagen Docker", commit=commit)
    build = sh(f"cd {APP} && docker build -t {IMAGE} .", timeout=900)
    if build.returncode != 0:
        log("BUILD FAIL: " + build.stderr[-300:])
        status("build", 40, "Error construyendo la imagen", status="failed", error=build.stderr[-300:], commit=commit)
        finish(path, {"ts": req.get("ts"), "status": "failed", "step": "build", "commit": commit})
        return
    configs = container_configs()
    total = len(configs)
    status("recreate", 60, f"Recreando contenedores (0/{total})", commit=commit, containers_total=total, containers_done=0)
    for i, c in enumerate(configs):
        recreate(c, IMAGE)
        pct = 60 + int(30 * (i + 1) / max(1, total))
        status("recreate", pct, f"Recreando contenedores ({i + 1}/{total})", commit=commit, containers_total=total, containers_done=i + 1)
    time.sleep(5)
    status("health", 92, "Verificando salud de los sitios", commit=commit, containers_total=total, containers_done=total)
    failed = []
    for c in configs:
        if c["port"]:
            code = sh(f"curl -s -o /dev/null -w '%{{http_code}}' http://localhost:{c['port']}/api/v1/health", timeout=30).stdout.strip()
            if code != "200":
                failed.append(c["name"])
    if failed:
        log("HEALTH FAIL " + ",".join(failed) + " -> ROLLBACK")
        status("rollback", 95, "Fallo de salud — restaurando versión anterior", commit=commit, failed=failed)
        for c in configs:
            recreate(c, PREV)
        status("done", 100, "Actualización revertida (rollback)", status="rolled_back", commit=commit, failed=failed)
        final_status = "rolled_back"
    else:
        status("done", 100, "Sistema actualizado correctamente", status="success", commit=commit)
        final_status = "success"
    duration = int(time.time() - started)
    log(f"UPDATE {final_status.upper()} commit={commit} containers={len(configs)} duration={duration}s")
    finish(path, {"ts": req.get("ts"), "status": final_status, "commit": commit, "containers": [c["name"] for c in configs], "duration_s": duration, "failed": failed})

def main():
    if not secret():
        return
    for path in sorted(glob.glob(QUEUE + "/update-*.json")):
        try:
            process(path)
        except Exception as e:
            log("ERROR " + path + ": " + str(e))

if __name__ == "__main__":
    main()
