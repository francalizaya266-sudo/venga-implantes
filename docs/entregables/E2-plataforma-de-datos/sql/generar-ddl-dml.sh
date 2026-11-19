#!/usr/bin/env bash
# Genera ddl.sql (la estructura) y dml.sql (los datos de demostración) de la base del consultorio, con el usuario
# consultorio_respaldo de seguridad.sql, que solo puede leer. Se corre sobre una base migrada y cargada con
# `php artisan db:seed` en APP_ENV=local (DemoSeeder):
#   MYSQL_HOST=127.0.0.1 MYSQL_PORT=3306 CLAVE_RESPALDO=… bash docs/entregables/E2-plataforma-de-datos/sql/generar-ddl-dml.sh
# Necesita mysqldump 8 y python3 (quitar-claves.py, en esta carpeta).
set -euo pipefail

host=${MYSQL_HOST:-127.0.0.1}
port=${MYSQL_PORT:-3306}
base=${MYSQL_DATABASE:-consultorio}
cd "$(dirname "$0")"

# La clave va en un archivo temporal y no en la línea de comandos, donde la vería cualquiera con `ps`.
cnf=$(mktemp)
trap 'rm -f "$cnf"' EXIT
printf '[client]\nuser=consultorio_respaldo\npassword=%s\nhost=%s\nport=%s\n' "$CLAVE_RESPALDO" "$host" "$port" > "$cnf"
comunes=(--defaults-extra-file="$cnf" --single-transaction --no-tablespaces --set-gtid-purged=OFF --skip-dump-date
  --default-character-set=utf8mb4)

# Lo que no es dato del consultorio (caché, sesiones, cola) o lo escribe la aplicación sola (las bitácoras).
fuera=(audit_logs payment_audit_logs cache cache_locks sessions jobs job_batches failed_jobs password_reset_tokens)
ignorar=()
for tabla in "${fuera[@]}"; do
  ignorar+=("--ignore-table=$base.$tabla")
done

{
  echo "-- Estructura de la base \`$base\`: 70 tablas, 5 vistas, 6 funciones, 2 procedimientos y 2 triggers."
  echo "-- Generado con generar-ddl-dml.sh: mysqldump --no-data --routines --triggers (sin los AUTO_INCREMENT)."
  echo "-- Las vistas, funciones, procedimientos y triggers llevan DEFINER=consultorio_despliegue: los crea ese usuario."
  echo "-- Para cargarlo, ese usuario ya debe existir (seguridad.sql) y poder leer la base donde se carga; luego dml.sql."
  echo
  mysqldump "${comunes[@]}" --no-data --routines --triggers "$base" | sed -E 's/ AUTO_INCREMENT=[0-9]+//'
} > ddl.sql

{
  echo "-- Datos de demostración de la base \`$base\` (DemoSeeder): sin datos reales de pacientes."
  echo "-- Generado con generar-ddl-dml.sh: mysqldump --no-create-info --complete-insert. Quedan fuera las tablas"
  echo "-- ${fuera[*]}."
  echo "-- La clave de cada usuario del panel se cambió por la de una clave al azar que no se guardó y remember_token"
  echo "-- va vacío: para entrar, php artisan usuario:clave <correo> da una clave temporal."
  echo
  mysqldump "${comunes[@]}" --no-create-info --skip-triggers --complete-insert "${ignorar[@]}" "$base" | python3 quitar-claves.py
} > dml.sql

printf 'ddl.sql: %s bytes; dml.sql: %s bytes, %s INSERT\n' "$(wc -c < ddl.sql)" "$(wc -c < dml.sql)" "$(grep -c '^INSERT INTO' dml.sql)"
