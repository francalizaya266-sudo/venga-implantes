"""Arma los anexos en Excel de CE03 y pone las mismas tablas dentro de los documentos .md.

Uso (desde cualquier carpeta):  python docs/entregables/CE03-infraestructura/generar-anexos.py

Excel en word/: inventario de activos, matriz de riesgos y Declaración de Aplicabilidad (S2-E1) y plan y
resultados de pruebas de red (S2-E2). En los .md, cada tabla va entre <!-- inicio: nombre --> y <!-- fin: nombre -->:
se reemplaza en cada corrida, así el Word y el Excel dicen lo mismo. Los datos están en datos.py.
"""

import collections
import re
from pathlib import Path

from openpyxl import Workbook
from openpyxl.styles import Alignment, Border, Font, PatternFill, Side
from openpyxl.utils import get_column_letter

import datos

AQUI = Path(__file__).resolve().parent
AZUL, FILA_PAR = '1F3864', 'EEF2F8'
NIVEL_COLOR = {'Bajo': 'D9EAD3', 'Medio': 'FFF2CC', 'Alto': 'F9CB9C', 'Crítico': 'EA9999'}
ESTADO_COLOR = {'Implementado': 'D9EAD3', 'Parcial': 'FFF2CC', 'Planificado': 'FCE5CD', 'No aplica': 'EEEEEE',
                'Cumple': 'D9EAD3', 'Pendiente': 'FFF2CC', 'Abierto': 'FCE5CD', 'Cerrado': 'D9EAD3'}


def nivel(valor):
    if valor >= 20:
        return 'Crítico'
    if valor >= 10:
        return 'Alto'
    if valor >= 5:
        return 'Medio'
    return 'Bajo'


def hoja(libro, titulo, encabezados, filas, anchos, color_por=None, primera=False):
    """Una hoja con encabezado azul, filas alternas, ajuste de texto y filtro."""
    ws = libro.active if primera else libro.create_sheet()
    ws.title = titulo
    linea = Side(style='thin', color='B4C2DC')
    borde = Border(left=linea, right=linea, top=linea, bottom=linea)
    ws.append(encabezados)
    for celda in ws[1]:
        celda.font = Font(bold=True, color='FFFFFF', name='Arial', size=10)
        celda.fill = PatternFill('solid', fgColor=AZUL)
        celda.alignment = Alignment(wrap_text=True, vertical='center', horizontal='center')
        celda.border = borde
    for numero, fila in enumerate(filas, start=2):
        ws.append(list(fila))
        for columna, celda in enumerate(ws[numero], start=1):
            celda.font = Font(name='Arial', size=9)
            celda.alignment = Alignment(wrap_text=True, vertical='top')
            celda.border = borde
            if numero % 2 == 1:
                celda.fill = PatternFill('solid', fgColor=FILA_PAR)
            if color_por and columna in color_por:
                color = color_por[columna](celda.value)
                if color:
                    celda.fill = PatternFill('solid', fgColor=color)
    for columna, ancho in enumerate(anchos, start=1):
        ws.column_dimensions[get_column_letter(columna)].width = ancho
    ws.freeze_panes = 'A2'
    ws.auto_filter.ref = ws.dimensions
    return ws


def tabla_md(encabezados, filas, alineacion=None):
    alineacion = alineacion or ['---'] * len(encabezados)
    limpio = lambda v: str(v).replace('|', '\\|').replace('\n', ' ')
    lineas = ['| ' + ' | '.join(encabezados) + ' |', '| ' + ' | '.join(alineacion) + ' |']
    lineas += ['| ' + ' | '.join(limpio(v) for v in fila) + ' |' for fila in filas]
    return '\n'.join(lineas)


def reemplazar(ruta_md, nombre, contenido):
    ruta = AQUI / ruta_md
    texto = ruta.read_text(encoding='utf-8')
    patron = re.compile(rf'(<!-- inicio: {nombre} -->\n)(?:.*?\n)?(<!-- fin: {nombre} -->)', re.S)
    if not patron.search(texto):
        raise SystemExit(f'Falta el bloque "{nombre}" en {ruta_md}')
    # Una línea en blanco antes y después: pandoc necesita la tabla separada de los comentarios.
    ruta.write_text(patron.sub(lambda m: m.group(1) + '\n' + contenido + '\n\n' + m.group(2), texto),
                    encoding='utf-8', newline='\n')


def main():
    salida = AQUI / 'word'
    salida.mkdir(exist_ok=True)
    sgsi = 'S2-E1-sgsi/documento.md'
    red = 'S2-E2-red-implementacion/documento.md'

    # Inventario de activos.
    activos = [(i, n, t, u, p, c, it, d, max(c, it, d), cl) for i, n, t, u, p, c, it, d, cl in datos.ACTIVOS]
    libro = Workbook()
    hoja(libro, 'Activos', ['ID', 'Activo', 'Tipo', 'Ubicación', 'Propietario', 'C', 'I', 'D', 'Valor', 'Clasificación'],
         activos, [6, 48, 14, 26, 22, 5, 5, 5, 7, 30], primera=True)
    hoja(libro, 'Escala', ['Valor', 'Confidencialidad', 'Integridad', 'Disponibilidad'], [
        (1, 'Público o sin daño si se divulga', 'Un error no afecta decisiones', 'Puede esperar días'),
        (2, 'Uso interno de la clínica', 'Un error afecta la operación', 'Puede esperar horas'),
        (3, 'Datos personales o de salud; secretos', 'Un error afecta la salud o el dinero de un paciente', 'Se necesita en minutos'),
    ], [8, 40, 45, 30])
    libro.save(salida / 'CE0321-Inventario-de-activos.xlsx')
    reemplazar(sgsi, 'activos', tabla_md(['ID', 'Activo', 'Tipo', 'C', 'I', 'D', 'Clasificación'],
                                         [(a[0], a[1], a[2], a[5], a[6], a[7], a[9]) for a in activos],
                                         ['---', '---', '---', '--:', '--:', '--:', '---']))

    # Matriz de riesgos.
    riesgos = []
    for i, act, amenaza, vuln, p, im, controles, trat, pr, ir, resp in datos.RIESGOS:
        riesgos.append((i, act, amenaza, vuln, p, im, p * im, nivel(p * im), controles, trat, pr, ir, pr * ir,
                        nivel(pr * ir), resp))
    libro = Workbook()
    color_nivel = lambda v: NIVEL_COLOR.get(v)
    hoja(libro, 'Riesgos', ['ID', 'Activos', 'Amenaza', 'Vulnerabilidad', 'P', 'I', 'Riesgo', 'Nivel',
                            'Controles (ISO/IEC 27002:2022)', 'Tratamiento', 'P residual', 'I residual',
                            'Riesgo residual', 'Nivel residual', 'Responsable'],
         riesgos, [6, 12, 34, 28, 5, 5, 8, 9, 60, 14, 9, 9, 9, 10, 12],
         color_por={8: color_nivel, 14: color_nivel}, primera=True)
    hoja(libro, 'Criterios', ['Valor', 'Probabilidad', 'Impacto'], [
        (1, 'Rara: menos de una vez en 5 años', 'Insignificante: sin daño a pacientes ni a la clínica'),
        (2, 'Improbable: una vez en 2 a 5 años', 'Menor: molestia, se corrige en horas'),
        (3, 'Posible: una vez al año', 'Moderado: la clínica deja de atender unas horas'),
        (4, 'Probable: varias veces al año', 'Mayor: filtración de datos de pocos pacientes o pérdida de un día'),
        (5, 'Casi segura: cada mes', 'Grave: filtración de historias clínicas o pérdida de datos irreparable'),
        ('Niveles', '1-4 bajo, 5-9 medio, 10-16 alto, 20-25 crítico', 'Se acepta un riesgo residual de hasta 9'),
    ], [10, 45, 65])
    libro.save(salida / 'CE0321-Matriz-de-riesgos.xlsx')
    reemplazar(sgsi, 'riesgos', tabla_md(
        ['ID', 'Amenaza', 'P×I', 'Nivel', 'Tratamiento', 'Residual', 'Nivel residual'],
        [(r[0], r[2], f'{r[4]}×{r[5]}={r[6]}', r[7], r[9], f'{r[10]}×{r[11]}={r[12]}', r[13]) for r in riesgos]))
    reemplazar(sgsi, 'riesgos-controles', tabla_md(
        ['ID', 'Controles aplicados', 'Responsable'], [(r[0], r[8], r[14]) for r in riesgos]))

    # Declaración de Aplicabilidad.
    libro = Workbook()
    hoja(libro, 'SoA', ['Control', 'Nombre', 'Aplica', 'Estado', 'Justificación o evidencia'], datos.SOA,
         [9, 44, 8, 14, 80], color_por={4: lambda v: ESTADO_COLOR.get(v)}, primera=True)
    cuenta = collections.Counter(c[3] for c in datos.SOA)
    tema = collections.defaultdict(collections.Counter)
    for c in datos.SOA:
        tema[c[0].split('.')[0]][c[3]] += 1
    nombres = {'5': 'Organizacionales', '6': 'Personas', '7': 'Físicos', '8': 'Tecnológicos'}
    estados = ['Implementado', 'Parcial', 'Planificado', 'No aplica']
    resumen = [(f'{k}. {nombres[k]}', *[tema[k][e] for e in estados], sum(tema[k].values())) for k in sorted(tema)]
    resumen.append(('Total', *[cuenta[e] for e in estados], len(datos.SOA)))
    hoja(libro, 'Resumen', ['Tema', *estados, 'Total'], resumen, [22, 14, 10, 12, 10, 8])
    libro.save(salida / 'CE0322-Declaracion-de-Aplicabilidad.xlsx')
    reemplazar(sgsi, 'soa-resumen', tabla_md(['Tema', *estados, 'Total'], resumen,
                                             ['---', '--:', '--:', '--:', '--:', '--:']))
    reemplazar(sgsi, 'soa', tabla_md(['Control', 'Nombre', 'Estado', 'Justificación o evidencia'],
                                     [(c[0], c[1], c[3], c[4]) for c in datos.SOA]))
    reemplazar(sgsi, 'incidentes', tabla_md(['ID', 'Fecha', 'Qué pasó', 'Severidad', 'Acción', 'Estado'],
                                            datos.INCIDENTES))
    reemplazar(sgsi, 'no-conformidades', tabla_md(
        ['ID', 'Control', 'Hallazgo', 'Tipo', 'Acción correctiva', 'Responsable', 'Plazo'], datos.NO_CONFORMIDADES))

    # Plan y resultados de pruebas de red.
    libro = Workbook()
    hoja(libro, 'Pruebas', ['ID', 'Tipo', 'Desde', 'Hacia', 'Cómo', 'Esperado', 'Obtenido', 'Resultado', 'Evidencia'],
         datos.PRUEBAS, [7, 12, 18, 34, 18, 26, 40, 11, 30], color_por={8: lambda v: ESTADO_COLOR.get(v)},
         primera=True)
    libro.save(salida / 'CE0312-CE0313-Plan-y-resultados-de-pruebas.xlsx')
    reemplazar(red, 'pruebas', tabla_md(['ID', 'Tipo', 'Desde', 'Hacia', 'Esperado', 'Obtenido', 'Resultado'],
                                        [(p[0], p[1], p[2], p[3], p[5], p[6], p[7]) for p in datos.PRUEBAS]))

    print('Excel en word/:', ', '.join(sorted(f.name for f in salida.glob('*.xlsx'))))


if __name__ == '__main__':
    main()
