"""Agrupa los avisos de la línea base de Larastan por su causa (tabla D5 del anexo D del E4).

Uso, desde la raíz del repositorio:
  python3 docs/entregables/E4-gestion-y-calidad/calidad/clasificar-larastan.py backend/phpstan-baseline.php

La causa se deduce del texto de cada aviso; el orden de las reglas importa (gana la primera que coincide).
"""

import collections
import re
import sys
from pathlib import Path

ENTRADA = re.compile(r"'rawMessage' => '((?:[^'\\]|\\.)*)',\s*'count' => (\d+),\s*'path' => __DIR__\.'/([^']+)'")

FAMILIAS = [
    ('Relaciones sin tipo genérico', re.compile(r'Illuminate\\Database\\Eloquent\\(Model\b|Collection<int, Illuminate)')),
    ('Scopes con #[Scope]', re.compile(r'undefined method Illuminate\\Database\\Eloquent\\(Builder|Relations\\\w+)::')),
    ('Accessors y columnas calculadas', re.compile(r'undefined property App\\Models\\')),
    ('Casts leídos como texto', re.compile(
        r'on string|between string|string given|string\|null given|between null and|Offset .* on (string|array\{\}\|string)'
        r'|does not exist on string|Unreachable statement|Result of (&&|\|\|) is always|is always (true|false)'
        r'|always evaluate|Ternary operator condition|Negated boolean|returns string|nullsafe')),
]


def familia(mensaje: str) -> str:
    for nombre, patron in FAMILIAS:
        if patron.search(mensaje):
            return nombre
    return 'Otros (formas de arreglos, closures de Filament)'


def main(ruta: Path) -> None:
    texto = ruta.read_text(encoding='utf-8')
    avisos, archivos = collections.Counter(), collections.defaultdict(set)
    for mensaje, cantidad, archivo in ENTRADA.findall(texto):
        mensaje = mensaje.replace("\\'", "'").replace('\\\\', '\\')
        nombre = familia(mensaje)
        avisos[nombre] += int(cantidad)
        archivos[nombre].add(archivo)
    print('| Causa | Avisos | Archivos |')
    print('| --- | --: | --: |')
    for nombre, _ in FAMILIAS + [('Otros (formas de arreglos, closures de Filament)', None)]:
        print(f'| {nombre} | {avisos[nombre]} | {len(archivos[nombre])} |')
    todos = set().union(*archivos.values())
    print(f'| Total | {sum(avisos.values())} | {len(todos)} |')


if __name__ == '__main__':
    main(Path(sys.argv[1]))
