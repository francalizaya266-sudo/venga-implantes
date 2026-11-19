#!/usr/bin/env bash
# Corre cada consulta de consultas.sql con el usuario que indica su línea "-- Usuario:" y guarda las preguntas y sus
# resultados en ../evidencias/consultas-resultado.txt. Uso (las claves son las de seguridad.sql):
#   MYSQL_HOST=127.0.0.1 MYSQL_PORT=3306 CLAVE_APP=… CLAVE_REPORTES=… bash docs/entregables/CE02-ingenieria-de-software/E2-plataforma-de-datos/sql/correr-consultas.sh
set -euo pipefail

host=${MYSQL_HOST:-127.0.0.1}
port=${MYSQL_PORT:-3306}
base=${MYSQL_DATABASE:-consultorio}
cd "$(dirname "$0")"
salida=../evidencias/consultas-resultado.txt
cnf=$(mktemp)
trap 'rm -f "$cnf"' EXIT

# La clave va en un archivo temporal y no en la línea de comandos, donde la vería cualquiera con `ps`.
correr() {
  local clave
  case "$1" in
    consultorio_app) clave=$CLAVE_APP ;;
    consultorio_reportes) clave=$CLAVE_REPORTES ;;
    *) echo "Usuario desconocido: $1" >&2; exit 1 ;;
  esac
  printf '[client]\nuser=%s\npassword=%s\nhost=%s\nport=%s\n' "$1" "$clave" "$host" "$port" > "$cnf"
  mysql --defaults-extra-file="$cnf" --default-character-set=utf8mb4 --table "$base" -e "$2"
}

{
  echo "Resultado de consultas.sql sobre la base de demostración (DemoSeeder)."
  echo "Corrido el $(TZ=America/Lima date '+%d/%m/%Y a las %H:%M') (hora de Lima), MySQL $(correr consultorio_app 'SELECT VERSION()' | sed -n 4p | tr -d '| ')."
  echo
} > "$salida"

# Cada bloque empieza con "-- Cnn." y su consulta termina en la línea que acaba en ";".
python3 - consultas.sql <<'PY' > "$cnf.bloques"
import json, re, sys
texto = open(sys.argv[1], encoding="utf-8").read()
for bloque in re.split(r"\n(?=-- C\d\d\. )", texto)[1:]:
    lineas = bloque.strip().splitlines()
    pregunta = " ".join(l[3:] for l in lineas if l.startswith("-- ") and not l.startswith("-- Usuario:"))
    usuario = next(l.split(":", 1)[1].strip() for l in lineas if l.startswith("-- Usuario:"))
    sql = "\n".join(l for l in lineas if not l.startswith("--"))
    print(json.dumps({"pregunta": pregunta, "usuario": usuario, "sql": sql}, ensure_ascii=False))
PY

# El ciclo lee los bloques por el descriptor 3: mysql no debe consumirlos de la entrada estándar.
while IFS= read -r bloque <&3; do
  pregunta=$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["pregunta"])' "$bloque")
  usuario=$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["usuario"])' "$bloque")
  sql=$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["sql"])' "$bloque")
  {
    echo "$pregunta"
    echo "Usuario: $usuario"
    correr "$usuario" "$sql"
    echo
  } >> "$salida"
done 3< "$cnf.bloques"
rm -f "$cnf.bloques"

echo "Listo: $salida ($(grep -c '^C[0-9][0-9]\.' "$salida") consultas)"
