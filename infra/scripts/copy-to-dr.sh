#!/usr/bin/env bash
# Cada hora: copia de la base de datos de producción hacia vm-respaldo, en la sede de la clínica, por la VPN.
# Así la sede nunca pierde más de una hora de datos (RPO de 1 h). En vm-respaldo se guardan las últimas 48.
# Usa consultorio_respaldo, el usuario de solo lectura de database/sql/seguridad.sql.
#
# Necesita:
#   /etc/vega/vega.env   KEY_VAULT=...  y  DB_HOST=vega-mysql-xxxx.mysql.database.azure.com
#   /etc/vega/dr_key     (chmod 600) llave privada cuyo par está en vm-respaldo, usuario "respaldo".
set -euo pipefail

# shellcheck disable=SC1091
source /opt/vega/kv.sh
: "${DB_HOST:?Falta DB_HOST en /etc/vega/vega.env}"

DR_HOST="${DR_HOST:-10.10.50.20}"
NAME="hora_$(date +%Y-%m-%d_%H%M).sql.gz"

if ! nc -z -w 5 "$DR_HOST" 22; then
  echo "$(date -Is) vm-respaldo no responde (¿VPN o VM apagada?): se omite esta hora"
  exit 0
fi

CNF=$(mktemp)
trap 'rm -f "$CNF"' EXIT
chmod 600 "$CNF"
printf '[client]\nhost=%s\nuser=consultorio_respaldo\npassword=%s\nssl-ca=/etc/ssl/certs/ca-certificates.crt\n' \
  "$DB_HOST" "$(kv_secret db-password-respaldo)" > "$CNF"

mysqldump --defaults-extra-file="$CNF" \
  --single-transaction --set-gtid-purged=OFF --no-tablespaces --routines --triggers consultorio \
  | gzip \
  | ssh -i /etc/vega/dr_key -o StrictHostKeyChecking=accept-new "respaldo@$DR_HOST" \
      "cat > /srv/respaldos/$NAME && ls -1t /srv/respaldos/hora_*.sql.gz | tail -n +49 | xargs -r rm -f"

echo "$(date -Is) copiado $NAME"
