"""Comprueba la matriz de trazabilidad del E1 contra el código.

Uso, desde cualquier carpeta:  python3 docs/entregables/CE02-ingenieria-de-software/verificar-trazabilidad.py

Revisa que:
1. cada requerimiento de la tabla 2 del documento principal esté una sola vez en la matriz del anexo A, y nada más;
2. cada historia y cada caso de uso citados existan (HU de la tabla 5; CU-01 a CU-20) y que la matriz asigne a cada
   historia los mismos requerimientos que la tabla 5;
3. cada archivo citado exista: `app/`, `config/` y `routes/` dentro de backend/; `src/` dentro de frontend/;
4. cada prueba citada exista: `ClaseTest::test_algo` como método de backend/tests y `Archivo.test.tsx`: “nombre”
   como it() o test() de ese archivo del frontend.
Termina con código 1 si algo no cuadra.
"""

import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parents[3]
E1 = RAIZ / 'docs/entregables/CE02-ingenieria-de-software/E1-requerimientos-y-diseno'
DOCUMENTO = E1 / 'E1-documento.md'
ANEXOS = E1 / 'anexos/E1-anexos.md'

ARCHIVO = re.compile(r'`((?:app|config|routes|src)/[^`]+)`')
PRUEBA_PHP = re.compile(r'`(\w+Test)::(test_\w+)`')
PRUEBA_TS = re.compile(r'`([\w.]+\.test\.tsx?)`: “([^”]+)”')


def filas(texto, prefijo):
    for linea in texto.splitlines():
        if linea.startswith('| ' + prefijo):
            yield [c.strip() for c in linea.strip().strip('|').split('|')]


def lista_de_rf(celda):
    """'RES-01, 02, 04' → {RES-01, RES-02, RES-04}; 'RES-01 a 03' → {RES-01 … RES-03}."""
    rf, modulo = set(), None
    for parte in re.split(r',\s*', celda):
        rango = re.fullmatch(r'(?:([A-Z]+)-)?(\d+) a (\d+)', parte)
        if rango:
            modulo = rango.group(1) or modulo
            rf |= {f'RF-{modulo}-{n:02d}' for n in range(int(rango.group(2)), int(rango.group(3)) + 1)}
            continue
        completo = re.fullmatch(r'([A-Z]+)-(\d+)', parte)
        if completo:
            modulo = completo.group(1)
            rf.add(f'RF-{modulo}-{completo.group(2)}')
        else:
            rf.add(f'RF-{modulo}-{parte}')
    return rf


def main():
    errores = []
    documento = DOCUMENTO.read_text(encoding='utf-8')
    anexos = ANEXOS.read_text(encoding='utf-8')

    requerimientos = [f[0] for f in filas(documento, 'RF-')]
    historias = {f[0]: lista_de_rf(f[5]) for f in filas(documento, 'HU-')}
    matriz = list(filas(anexos, 'RF-'))

    vistos = [f[0] for f in matriz]
    for rf in requerimientos:
        if vistos.count(rf) != 1:
            errores.append(f'{rf} aparece {vistos.count(rf)} veces en la matriz')
    for rf in set(vistos) - set(requerimientos):
        errores.append(f'{rf} está en la matriz pero no en la tabla 2')

    por_historia = {}
    casos = set()
    for rf, hu, cu, *_ in matriz:
        for h in re.findall(r'HU-\d+', hu):
            por_historia.setdefault(h, set()).add(rf)
        casos |= set(re.findall(r'CU-\d+', cu))
    for h, esperados in historias.items():
        if por_historia.get(h, set()) != esperados:
            errores.append(f'{h}: la tabla 5 dice {sorted(esperados)} y la matriz {sorted(por_historia.get(h, []))}')
    for h in set(por_historia) - set(historias):
        errores.append(f'{h} no está en la tabla 5')
    if casos != {f'CU-{n:02d}' for n in range(1, 21)}:
        errores.append(f'casos de uso citados: {sorted(casos)}')

    pruebas_php = {}
    for archivo in (RAIZ / 'backend/tests').rglob('*Test.php'):
        pruebas_php[archivo.stem] = set(re.findall(r'function (test_\w+)\s*\(', archivo.read_text(encoding='utf-8')))
    archivos_ts = {a.name: a.read_text(encoding='utf-8') for a in (RAIZ / 'frontend/src').rglob('*.test.ts*')}

    citados, pruebas = set(), set()
    for fila in matriz:
        rf, archivos, prueba = fila[0], fila[4], fila[5]
        for ruta in ARCHIVO.findall(archivos) + ARCHIVO.findall(prueba):
            base = 'frontend' if ruta.startswith('src/') else 'backend'
            citados.add(ruta)
            if not (RAIZ / base / ruta).exists():
                errores.append(f'{rf}: no existe {base}/{ruta}')
        for clase, metodo in PRUEBA_PHP.findall(prueba):
            pruebas.add(f'{clase}::{metodo}')
            if metodo not in pruebas_php.get(clase, set()):
                errores.append(f'{rf}: no existe la prueba {clase}::{metodo}')
        for archivo, nombre in PRUEBA_TS.findall(prueba):
            pruebas.add(f'{archivo}: {nombre}')
            if not re.search(r'\b(?:it|test)\(\s*[\'"`]' + re.escape(nombre) + r'[\'"`]', archivos_ts.get(archivo, '')):
                errores.append(f'{rf}: no existe la prueba “{nombre}” en {archivo}')
        if 'PENDIENTE' not in prueba and not PRUEBA_PHP.search(prueba) and not PRUEBA_TS.search(prueba):
            errores.append(f'{rf}: no cita ninguna prueba')

    clases = {p.split('::')[0].split(':')[0] for p in pruebas}
    print(f'{len(matriz)} requerimientos, {len(pruebas)} pruebas de {len(clases)} archivos de prueba, '
          f'{len(citados)} archivos del código.')
    for error in errores:
        print('  ✗', error)
    if errores:
        sys.exit(1)
    print('Todo cuadra con el código.')


if __name__ == '__main__':
    main()
