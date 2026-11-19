#!/usr/bin/env bash
# Crea o actualiza los 4 usuarios de MySQL de backend/database/sql/seguridad.sql. Lo ejecuta "desplegar.sh basedatos"
# dentro de vm-app con "az vm run-command": vm-app lee las claves de Key Vault con su identidad y llega a MySQL por
# la red privada. Se corre antes de la primera publicación y otra vez después (para dar a reportes las vistas).
#
#   remoto-seguridad.sh <key-vault> <servidor-mysql> <seguridad.sql en base64>
set -euo pipefail

KV="${1:?Falta el Key Vault}"
HOST="${2:?Falta el servidor de MySQL}"
SQL_B64="${3:?Falta seguridad.sql en base64}"

# Llega sin el relleno "=" (ver b64 en desplegar.sh): se repone antes de decodificar.
desde_b64() {
  local s="$1"
  while [ $(( ${#s} % 4 )) -ne 0 ]; do s="${s}="; done
  printf '%s' "$s" | base64 -d
}

token=$(curl -sf -H Metadata:true \
  "http://169.254.169.254/metadata/identity/oauth2/token?api-version=2018-02-01&resource=https%3A%2F%2Fvault.azure.net" \
  | jq -r .access_token)

secreto() {
  curl -sf -H "Authorization: Bearer $token" "https://${KV}.vault.azure.net/secrets/$1?api-version=7.4" | jq -r .value
}

# Las claves van en un archivo temporal y no en la línea de comandos.
CNF=$(mktemp)
trap 'rm -f "$CNF"' EXIT
chmod 600 "$CNF"
printf '[client]\nhost=%s\nuser=vegaadmin\npassword=%s\nssl-ca=/etc/ssl/certs/ca-certificates.crt\n' \
  "$HOST" "$(secreto mysql-admin-password)" > "$CNF"

{
  printf "SET @clave_app='%s', @clave_despliegue='%s', @clave_reportes='%s', @clave_respaldo='%s';\n" \
    "$(secreto db-password)" "$(secreto db-password-despliegue)" "$(secreto db-password-reportes)" "$(secreto db-password-respaldo)"
  desde_b64 "$SQL_B64"
} | mysql --defaults-extra-file="$CNF" consultorio

echo "Usuarios de MySQL listos en $HOST."
