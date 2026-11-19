"""Quita las claves de los usuarios del panel de un volcado de mysqldump con --complete-insert (entrada y salida
estándar). Lo usa generar-ddl-dml.sh para que dml.sql no lleve ni el hash de una clave ni un remember_token."""

import re
import sys

# bcrypt de 64 bytes al azar que no se guardaron: con este valor nadie puede entrar, pero Laravel lo lee como una
# clave equivocada y no como un error. Para entrar: php artisan usuario:clave <correo>.
SIN_CLAVE = "'$2y$12$wMR3K5/NNIW/wj/2HQy1quI.eESYesusSa7TIlY0W.MrXdEOMWYn6'"
CAMBIOS = {"users": {"password": SIN_CLAVE, "remember_token": "NULL"}}


def filas(valores: str) -> list[list[str]]:
    """Separa "(1,'a'),(2,NULL)" en filas de valores, respetando las comillas y sus escapes."""
    resultado: list[list[str]] = []
    fila: list[str] = []
    valor = ""
    comillas = dentro = False
    i = 0
    while i < len(valores):
        c = valores[i]
        if comillas:
            valor += c
            if c == "\\":
                valor += valores[i + 1]
                i += 1
            elif c == "'":
                comillas = False
        elif c == "'":
            comillas = True
            valor += c
        elif c == "(" and not dentro:
            dentro, fila, valor = True, [], ""
        elif c == "," and dentro:
            fila.append(valor)
            valor = ""
        elif c == ")" and dentro:
            fila.append(valor)
            resultado.append(fila)
            dentro, valor = False, ""
        elif dentro:
            valor += c
        i += 1
    return resultado


for linea in sys.stdin:
    m = re.match(r"INSERT INTO `(\w+)` \((.*?)\) VALUES (.*);$", linea.rstrip("\n"))
    if not m or m.group(1) not in CAMBIOS:
        sys.stdout.write(linea)
        continue
    columnas = [c.strip(" `") for c in m.group(2).split(",")]
    cambios = CAMBIOS[m.group(1)]
    nuevas = ["(" + ",".join(cambios.get(col, v) for col, v in zip(columnas, fila)) + ")" for fila in filas(m.group(3))]
    sys.stdout.write(f"INSERT INTO `{m.group(1)}` ({m.group(2)}) VALUES {','.join(nuevas)};\n")
