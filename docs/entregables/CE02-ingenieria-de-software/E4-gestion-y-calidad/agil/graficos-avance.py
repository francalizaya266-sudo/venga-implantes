"""Gráficos de avance del E4 (sección 3.3): velocidad por sprint y PR fusionados acumulados.

Los valores de "velocidad" salen de sprints-desde-git.py (PR y commits por sprint) y de contar los métodos de
prueba del backend en el último commit de main de cada sprint (grep -c "public function test_" en backend/tests,
o en tests/ antes de separar el backend). burnup.json tiene una fila por día: [fecha, PR fusionados ese día,
acumulado], en hora de Lima.

Uso: python3 graficos-avance.py <carpeta con burnup.json>   (escribe <carpeta>/graf/velocidad.svg y burnup.svg)
"""
import json,sys,datetime as dt
S=sys.argv[1]
INK='#0b0b0b'; INK2='#52514e'; MUTED='#8a8984'; GRID='#e6e5e0'; BLUE='#2f5496'; BAND='#eef2f8'
FONT="font-family:Arial,Helvetica,sans-serif"
sprints=[('S1','19–25 oct'),('S2','2–8 nov'),('S3','9–15 nov'),('S4','16–19 nov')]
panels=[('PR fusionados',[25,28,44,18]),('Commits (sin fusiones)',[30,57,76,40]),('Métodos de prueba del backend al cierre',[265,453,941,986])]
def bars(x0,w,h,title,vals):
    top=60; base=top+h; mx=max(vals)*1.15; bw=w/len(vals)*0.56; out=[]
    out.append(f'<text x="{x0}" y="24" style="{FONT};font-size:19px;font-weight:700" fill="{INK}">{title}</text>')
    for k in range(0,5):
        y=base-h*k/4
        out.append(f'<line x1="{x0}" x2="{x0+w}" y1="{y}" y2="{y}" stroke="{GRID}" stroke-width="1"/>')
    for i,v in enumerate(vals):
        cx=x0+w*(i+0.5)/len(vals); bh=h*v/mx; x=cx-bw/2; y=base-bh
        out.append(f'<path d="M{x},{base} V{y+4} Q{x},{y} {x+4},{y} H{x+bw-4} Q{x+bw},{y} {x+bw},{y+4} V{base} Z" fill="{BLUE}"/>')
        out.append(f'<text x="{cx}" y="{y-8}" text-anchor="middle" style="{FONT};font-size:19px;font-weight:700" fill="{INK}">{v}</text>')
        out.append(f'<text x="{cx}" y="{base+24}" text-anchor="middle" style="{FONT};font-size:18px;font-weight:700" fill="{INK}">{sprints[i][0]}</text>')
        out.append(f'<text x="{cx}" y="{base+46}" text-anchor="middle" style="{FONT};font-size:16px" fill="{INK2}">{sprints[i][1]}</text>')
    out.append(f'<line x1="{x0}" x2="{x0+w}" y1="{base}" y2="{base}" stroke="{MUTED}" stroke-width="1"/>')
    return '\n'.join(out)
W=1200; svg1=[f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="300" viewBox="0 0 {W} 300"><rect width="100%" height="100%" fill="#fff"/>']
pw=(W-2*40)/3
for i,(t,v) in enumerate(panels): svg1.append(bars(20+i*(pw+20),pw-20,180,t,v))
svg1.append('</svg>')
rows=json.load(open(f'{S}/burnup.json'))
d0=dt.date.fromisoformat(rows[0][0]); n=len(rows)
L,R,T,B=64,30,46,66; H=360; plotw=W-L-R; ploth=H-T-B; ymax=120
X=lambda i:L+plotw*i/(n-1); Y=lambda v:T+ploth*(1-v/ymax)
svg2=[f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}"><rect width="100%" height="100%" fill="#fff"/>']
idx={r[0]:i for i,r in enumerate(rows)}
bands=[('S1','2026-10-22','2026-10-25'),('Pausa','2026-10-26','2026-11-01'),('S2','2026-11-02','2026-11-08'),('S3','2026-11-09','2026-11-15'),('S4','2026-11-16','2026-11-19')]
for name,a,b in bands:
    xa=X(idx[a])-(0 if a==rows[0][0] else plotw/(n-1)/2); xb=X(idx[b])+(0 if b==rows[-1][0] else plotw/(n-1)/2)
    if name!='Pausa': svg2.append(f'<rect x="{xa+2}" y="{T}" width="{xb-xa-4}" height="{ploth}" fill="{BAND}"/>')
    svg2.append(f'<text x="{(xa+xb)/2}" y="{T-14}" text-anchor="middle" style="{FONT};font-size:18px;font-weight:700" fill="{INK2 if name=="Pausa" else INK}">{name}</text>')
for v in range(0,ymax+1,20):
    svg2.append(f'<line x1="{L}" x2="{W-R}" y1="{Y(v)}" y2="{Y(v)}" stroke="{GRID}"/>')
    svg2.append(f'<text x="{L-10}" y="{Y(v)+6}" text-anchor="end" style="{FONT};font-size:16px" fill="{INK2}">{v}</text>')
pts=' '.join(f'{X(i)},{Y(r[2])}' for i,r in enumerate(rows))
svg2.append(f'<polyline points="{pts}" fill="none" stroke="{BLUE}" stroke-width="2.5" stroke-linejoin="round"/>')
for i,r in enumerate(rows):
    if r[1]: svg2.append(f'<circle cx="{X(i)}" cy="{Y(r[2])}" r="4.5" fill="{BLUE}" stroke="#fff" stroke-width="2"/>')
for lab in ['2026-10-24','2026-11-07','2026-11-14','2026-11-19']:
    i=idx[lab]; v=rows[i][2]
    svg2.append(f'<text x="{X(i)}" y="{Y(v)-12}" text-anchor="{"end" if lab=="2026-11-19" else "middle"}" style="{FONT};font-size:18px;font-weight:700" fill="{INK}">{v}</text>')
meses={10:'oct',11:'nov'}
for i,r in enumerate(rows):
    d=dt.date.fromisoformat(r[0])
    if d.day in (22,26) and d.month==10 or (d.month==11 and d.day in (2,9,16,19)):
        svg2.append(f'<text x="{X(i)}" y="{H-B+24}" text-anchor="middle" style="{FONT};font-size:16px" fill="{INK2}">{d.day} {meses[d.month]}</text>')
svg2.append(f'<line x1="{L}" x2="{W-R}" y1="{Y(0)}" y2="{Y(0)}" stroke="{MUTED}"/>')
svg2.append(f'<text x="{L}" y="{H-12}" style="{FONT};font-size:15px" fill="{INK2}">PR fusionados acumulados: consultorio-dental hasta el 17/11 y venga-implantes desde el 18/11, por día de fusión (hora de Lima).</text>')
svg2.append('</svg>')
open(f'{S}/graf/velocidad.svg','w').write('\n'.join(svg1)); open(f'{S}/graf/burnup.svg','w').write('\n'.join(svg2))
