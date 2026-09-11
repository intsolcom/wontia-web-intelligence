#!/usr/bin/env python3
import json, os, hmac, hashlib, subprocess, time, glob, shutil

QUEUE = "/var/lib/dokploy/wontia-deploy"
DONE = QUEUE + "/done"
LOG = "/var/log/wwi-update.log"
REPO = "https://github.com/intsolcom/wontia-web-intelligence.git"
SRC = "/tmp/wwi-src"
APP = "/tmp/wontia-build/app"
IMAGE = "wontia-web-intelligence:latest"
PREV = "wontia-web-intelligence:previous"

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
    out = sh(f"docker ps --filter ancestor={IMAGE} --format '{{{{.Names}}}}'")
    names = [l.strip() for l in out.stdout.splitlines() if l.strip()]
    configs = []
    for n in names:
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
    log("UPDATE START requested_by=" + str(req.get("requested_by", "?")))
    sh(f"docker tag {IMAGE} {PREV}")
    sh(f"rm -rf {SRC}")
    clone = sh(f"git clone --depth 1 {REPO} {SRC}", timeout=300)
    if clone.returncode != 0:
        log("CLONE FAIL: " + clone.stderr[-300:])
        finish(path, {"ts": req.get("ts"), "status": "failed", "step": "clone", "error": clone.stderr[-500:]})
        return
    commit = sh(f"git -C {SRC} rev-parse --short HEAD").stdout.strip()
    rsync = sh(f"rsync -a --delete --exclude '.env' --exclude 'vendor/' --exclude 'cache/' --exclude 'public/assets/uploads/' {SRC}/ {APP}/", timeout=300)
    if rsync.returncode != 0:
        log("RSYNC FAIL: " + rsync.stderr[-300:])
        finish(path, {"ts": req.get("ts"), "status": "failed", "step": "sync", "commit": commit})
        return
    build = sh(f"cd {APP} && docker build -t {IMAGE} .", timeout=900)
    if build.returncode != 0:
        log("BUILD FAIL: " + build.stderr[-300:])
        finish(path, {"ts": req.get("ts"), "status": "failed", "step": "build", "commit": commit})
        return
    configs = container_configs()
    for c in configs:
        recreate(c, IMAGE)
    time.sleep(5)
    failed = []
    for c in configs:
        if c["port"]:
            code = sh(f"curl -s -o /dev/null -w '%{{http_code}}' http://localhost:{c['port']}/api/v1/health", timeout=30).stdout.strip()
            if code != "200":
                failed.append(c["name"])
    if failed:
        log("HEALTH FAIL " + ",".join(failed) + " -> ROLLBACK")
        for c in configs:
            recreate(c, PREV)
        status = "rolled_back"
    else:
        status = "success"
    duration = int(time.time() - started)
    log(f"UPDATE {status.upper()} commit={commit} containers={len(configs)} duration={duration}s")
    finish(path, {"ts": req.get("ts"), "status": status, "commit": commit, "containers": [c["name"] for c in configs], "duration_s": duration, "failed": failed})

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
