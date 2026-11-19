"""Gráficos de calidad del E4: cobertura de líneas por capa y complejidad ciclomática de los métodos.

Lee el reporte Clover de PHPUnit (pcov) y la salida --json de medir-codigo.php, y escribe dos SVG que después se
pasan a PNG (por ejemplo con Playwright, a 2x). Un solo color de serie, rejilla suave y el valor escrito en cada
barra, como los gráficos de avance de agil/graficos-avance.py.

Uso, desde la raíz del repositorio:
  php docs/entregables/CE02-ingenieria-de-software/E4-gestion-y-calidad/calidad/medir-codigo.php --json > metricas.json
  cd backend && php artisan test --coverage-clover=../clover.xml && cd ..
  python3 docs/entregables/CE02-ingenieria-de-software/E4-gestion-y-calidad/calidad/graficos-calidad.py clover.xml metricas.json salida/
"""

import json
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

INK, INK2, MUTED, GRID, BLUE = '#0b0b0b', '#52514e', '#8a8984', '#e6e5e0', '#2a78d6'
FONT = 'font-family:Arial,Helvetica,sans-serif'

# Capa del backend según la carpeta de app/ (la primera que coincide).
CAPAS = [
    ('API y web (Http)', ('app/Http/',)),
    ('Panel (Filament y Livewire)', ('app/Filament/', 'app/Livewire/', 'app/Providers/Filament/')),
    ('Servicios de dominio', ('app/Services/',)),
    ('Modelos', ('app/Models/',)),
    ('Consola (tareas programadas)', ('app/Console/',)),
    ('Apoyo y excepciones', ('app/Support/', 'app/Exceptions/', 'app/Providers/')),
    ('Enums (estados y tipos)', ('app/Enums/',)),
    ('Políticas de permisos', ('app/Policies/',)),
]


def miles(numero: int) -> str:
    """1 234 con espacio de no separación, como se escriben los números en el resto del documento."""
    return f'{numero:,}'.replace(',', '\u00a0')


def cobertura_por_capa(clover: Path) -> list[tuple[str, int, int]]:
    totales = {nombre: [0, 0] for nombre, _ in CAPAS}
    for archivo in ET.parse(clover).iter('file'):
        ruta = archivo.get('name', '')
        ruta = ruta[ruta.find('app/'):] if 'app/' in ruta else ruta
        metricas = archivo.find('metrics')
        for nombre, prefijos in CAPAS:
            if ruta.startswith(prefijos):
                totales[nombre][0] += int(metricas.get('statements'))
                totales[nombre][1] += int(metricas.get('coveredstatements'))
                break
    filas = [(nombre, total, cubiertas) for nombre, (total, cubiertas) in totales.items() if total]
    return sorted(filas, key=lambda fila: -fila[2] / fila[1])


def barras_horizontales(filas: list[tuple[str, int, int]], meta: float) -> str:
    ancho, izquierda, derecha, alto_fila = 1200, 330, 150, 34
    arriba = 46
    alto = arriba + alto_fila * len(filas) + 40
    plot = ancho - izquierda - derecha
    x = lambda porcentaje: izquierda + plot * porcentaje / 100  # noqa: E731
    svg = [f'<svg xmlns="http://www.w3.org/2000/svg" width="{ancho}" height="{alto}" viewBox="0 0 {ancho} {alto}">',
           '<rect width="100%" height="100%" fill="#fff"/>']
    for porcentaje in range(0, 101, 20):
        svg.append(f'<line x1="{x(porcentaje)}" x2="{x(porcentaje)}" y1="{arriba - 6}" y2="{alto - 34}" stroke="{GRID}"/>')
        svg.append(f'<text x="{x(porcentaje)}" y="{alto - 14}" text-anchor="middle" style="{FONT};font-size:12px" '
                   f'fill="{INK2}">{porcentaje} %</text>')
    svg.append(f'<line x1="{x(meta)}" x2="{x(meta)}" y1="{arriba - 14}" y2="{alto - 34}" stroke="{INK}" '
               'stroke-width="1.5" stroke-dasharray="5 4"/>')
    svg.append(f'<text x="{x(meta)}" y="{arriba - 20}" text-anchor="middle" style="{FONT};font-size:12px;font-weight:700" '
               f'fill="{INK}">mínimo de la CI: {meta:.0f} %</text>')
    for i, (nombre, total, cubiertas) in enumerate(filas):
        porcentaje = 100 * cubiertas / total
        y = arriba + i * alto_fila
        alto_barra = alto_fila - 12
        largo = x(porcentaje) - izquierda
        svg.append(f'<text x="{izquierda - 12}" y="{y + alto_barra / 2 + 5}" text-anchor="end" '
                   f'style="{FONT};font-size:14px" fill="{INK}">{nombre}</text>')
        svg.append(f'<path d="M{izquierda},{y} H{izquierda + largo - 4} Q{izquierda + largo},{y} {izquierda + largo},{y + 4} '
                   f'V{y + alto_barra - 4} Q{izquierda + largo},{y + alto_barra} {izquierda + largo - 4},{y + alto_barra} '
                   f'H{izquierda} Z" fill="{BLUE}"/>')
        texto = f'{porcentaje:.1f} %'.replace('.', ',')
        svg.append(f'<text x="{izquierda + largo + 8}" y="{y + alto_barra / 2 + 5}" style="{FONT};font-size:14px;'
                   f'font-weight:700" fill="{INK}" stroke="#fff" stroke-width="5" paint-order="stroke">{texto}</text>')
        svg.append(f'<text x="{ancho - 8}" y="{y + alto_barra / 2 + 5}" text-anchor="end" style="{FONT};font-size:12px" '
                   f'fill="{INK2}">{miles(cubiertas)} de {miles(total)}</text>')
    svg.append(f'<line x1="{izquierda}" x2="{izquierda}" y1="{arriba - 6}" y2="{alto - 34}" stroke="{MUTED}"/>')
    svg.append('</svg>')
    return '\n'.join(svg)


def histograma_complejidad(metricas: dict) -> str:
    rangos = [('1', 1, 1), ('2', 2, 2), ('3 a 5', 3, 5), ('6 a 10', 6, 10), ('11 a 20', 11, 20), ('más de 20', 21, 10**6)]
    complejidades = [metodo['complejidad'] for metodo in metricas['metodos']]
    cuentas = [sum(1 for c in complejidades if desde <= c <= hasta) for _, desde, hasta in rangos]
    total = len(complejidades)
    ancho, alto, izquierda, derecha, arriba, abajo = 1200, 380, 40, 40, 40, 70
    plot_ancho, plot_alto = ancho - izquierda - derecha, alto - arriba - abajo
    maximo = max(cuentas) * 1.15
    ancho_barra = plot_ancho / len(rangos) * 0.56
    base = arriba + plot_alto
    svg = [f'<svg xmlns="http://www.w3.org/2000/svg" width="{ancho}" height="{alto}" viewBox="0 0 {ancho} {alto}">',
           '<rect width="100%" height="100%" fill="#fff"/>']
    for k in range(1, 5):
        y = base - plot_alto * k / 4
        svg.append(f'<line x1="{izquierda}" x2="{ancho - derecha}" y1="{y}" y2="{y}" stroke="{GRID}"/>')
    for i, ((nombre, _, _), cuenta) in enumerate(zip(rangos, cuentas)):
        cx = izquierda + plot_ancho * (i + 0.5) / len(rangos)
        alto_barra = plot_alto * cuenta / maximo
        x, y = cx - ancho_barra / 2, base - alto_barra
        radio = min(4, alto_barra)
        svg.append(f'<path d="M{x},{base} V{y + radio} Q{x},{y} {x + radio},{y} H{x + ancho_barra - radio} '
                   f'Q{x + ancho_barra},{y} {x + ancho_barra},{y + radio} V{base} Z" fill="{BLUE}"/>')
        porcentaje = f'{100 * cuenta / total:.1f} %'.replace('.', ',')
        svg.append(f'<text x="{cx}" y="{y - 26}" text-anchor="middle" style="{FONT};font-size:15px;font-weight:700" '
                   f'fill="{INK}">{miles(cuenta)}</text>')
        svg.append(f'<text x="{cx}" y="{y - 9}" text-anchor="middle" style="{FONT};font-size:12px" fill="{INK2}">'
                   f'{porcentaje}</text>')
        svg.append(f'<text x="{cx}" y="{base + 22}" text-anchor="middle" style="{FONT};font-size:14px;font-weight:700" '
                   f'fill="{INK}">{nombre}</text>')
    # Separa los rangos que el registro de code smells marca como "método complejo" (más de 10).
    limite = izquierda + plot_ancho * 4 / len(rangos)
    svg.append(f'<line x1="{limite}" x2="{limite}" y1="{arriba - 10}" y2="{base}" stroke="{INK}" stroke-width="1.5" '
               'stroke-dasharray="5 4"/>')
    svg.append(f'<text x="{limite + 8}" y="{arriba + 4}" style="{FONT};font-size:12px;font-weight:700" fill="{INK}">'
               'code smell: más de 10</text>')
    svg.append(f'<line x1="{izquierda}" x2="{ancho - derecha}" y1="{base}" y2="{base}" stroke="{MUTED}"/>')
    svg.append(f'<text x="{izquierda}" y="{alto - 12}" style="{FONT};font-size:12px" fill="{INK2}">Complejidad '
               f'ciclomática de cada método de backend/app ({miles(total)} métodos).</text>')
    svg.append('</svg>')
    return '\n'.join(svg)


if __name__ == '__main__':
    clover, metricas, salida = Path(sys.argv[1]), Path(sys.argv[2]), Path(sys.argv[3])
    salida.mkdir(parents=True, exist_ok=True)
    (salida / 'cobertura-por-capa.svg').write_text(barras_horizontales(cobertura_por_capa(clover), 90), encoding='utf-8')
    (salida / 'complejidad-metodos.svg').write_text(histograma_complejidad(json.loads(metricas.read_text())), encoding='utf-8')
