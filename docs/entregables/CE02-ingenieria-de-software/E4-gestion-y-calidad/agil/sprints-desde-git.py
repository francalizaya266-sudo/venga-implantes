"""Reconstruye los sprints del E4 (sección 3) desde el historial real de los dos repositorios.

Sprint = semana calendario en hora de Lima (lunes a domingo). Para cada sprint cuenta los PR fusionados (y si vienen
de una rama claude/ o de una rama del equipo), los commits sin fusiones con su autor y las líneas agregadas y
quitadas; además escribe sprint-prs.json con cada PR (repositorio, número, fecha, autor, rama y título), que es
la base del Sprint Backlog de los anexos.

Entrada: prs-consultorio-dental.json y prs-venga-implantes.json en la carpeta indicada, con los PR tal como los
devuelve la API de GitHub (GET /repos/{dueño}/{repo}/pulls?state=closed). Los repositorios se leen de las rutas de
REPOS (clonados completos, sin --depth).

Uso: python3 sprints-desde-git.py <carpeta con los .json>
"""
import json, subprocess, datetime as dt, collections, sys
LIMA = dt.timezone(dt.timedelta(hours=-5))
SPRINTS = [("S1", "2026-10-19", "2026-10-25"), ("S2", "2026-11-02", "2026-11-08"),
           ("S3", "2026-11-09", "2026-11-15"), ("S4", "2026-11-16", "2026-11-22")]
REPOS = {"consultorio-dental": "/home/user/consultorio-dental", "venga-implantes": "/home/user/venga-implantes"}
REF = {"consultorio-dental": "origin/main", "venga-implantes": "origin/main"}
def sprint_of(d):
    for name, a, b in SPRINTS:
        if a <= d <= b: return name
    return "pausa"
out = collections.defaultdict(lambda: collections.Counter())
prs = collections.defaultdict(list)
for repo, path in REPOS.items():
    for p in json.load(open(f"{sys.argv[1]}/prs-{repo}.json")):
        if not p["merged_at"]: continue
        d = dt.datetime.fromisoformat(p["merged_at"].replace("Z", "+00:00")).astimezone(LIMA).date().isoformat()
        s = sprint_of(d)
        out[s]["prs"] += 1
        out[s]["prs_" + ("claude" if p["head"]["ref"].startswith("claude/") else "equipo")] += 1
        prs[s].append((repo, p["number"], d, p["user"]["login"], p["head"]["ref"], p["title"]))
    log = subprocess.run(["git", "-C", path, "log", REF[repo], "--no-merges", "--format=@%aI|%an", "--shortstat"], capture_output=True, text=True).stdout
    cur = None
    for line in log.splitlines():
        if line.startswith("@"):
            date, author = line[1:].split("|")
            d = dt.datetime.fromisoformat(date).astimezone(LIMA).date().isoformat()
            cur = sprint_of(d); out[cur]["commits"] += 1; out[cur]["autor:" + author] += 1
        elif "changed" in line and cur:
            parts = line.split(",")
            for part in parts:
                n = int(part.strip().split()[0])
                if "insertion" in part: out[cur]["mas"] += n
                if "deletion" in part: out[cur]["menos"] += n
for s in ["S1", "pausa", "S2", "S3", "S4"]:
    print(s, dict(out[s]))
json.dump({k: sorted(v, key=lambda x: (x[2], x[0], x[1])) for k, v in prs.items()}, open(f"{sys.argv[1]}/sprint-prs.json", "w"), ensure_ascii=False, indent=1)
