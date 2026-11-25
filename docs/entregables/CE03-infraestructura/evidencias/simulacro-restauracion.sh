#!/bin/bash
# Simulacro de restauración (S2-E3, ejercicio 1). Se ejecuta en vm-app con:
#   az vm run-command invoke -g rg-vega-prod -n vm-app --command-id RunShellScript --scripts @simulacro-restauracion.sh
# Restaura la última copia de Blob en una base aparte, compara totales con producción y la borra.
set -e
source /etc/vega/vega.env; source /opt/vega/kv.sh
H=$(grep -h '^DB_HOST=' /etc/vega/app.env /etc/vega/vega.env | tail -1 | cut -d= -f2)
command -v mysql >/dev/null || { echo "sin cliente mysql"; exit 1; }
ADM="$(kv_secret mysql-admin-password)"
export MYSQL_PWD="$ADM"
M="mysql -h $H -u vegaadmin --ssl-mode=REQUIRED"
inicio=$(date +%s); echo "inicio: $(date -Is)"
export AZCOPY_AUTO_LOGIN_TYPE=MSI
D=$(mktemp -d)
ULT=$(azcopy list "https://${STORAGE_ACCOUNT}.blob.core.windows.net/respaldos/base-de-datos" 2>/dev/null | grep -o 'respaldo_[0-9_-]*\.sql\.gz' | sort | tail -1)
echo "copia elegida (Blob): $ULT"
azcopy copy "https://${STORAGE_ACCOUNT}.blob.core.windows.net/respaldos/base-de-datos/$ULT" "$D/$ULT" >/dev/null
echo "descargada: $(du -h $D/$ULT | cut -f1)"
$M -e "DROP DATABASE IF EXISTS consultorio_simulacro; CREATE DATABASE consultorio_simulacro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gunzip -c "$D/$ULT" | sed -E 's/`consultorio`\./`consultorio_simulacro`./g' | $M consultorio_simulacro
fin=$(date +%s); echo "fin: $(date -Is) duracion_s: $((fin-inicio))"
for t in patients appointments payments users treatments; do
  a=$($M -N -e "SELECT COUNT(*) FROM consultorio.$t" 2>/dev/null); b=$($M -N -e "SELECT COUNT(*) FROM consultorio_simulacro.$t" 2>/dev/null); echo "$t produccion=$a restaurada=$b"
done
echo "tablas restauradas: $($M -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='consultorio_simulacro'")"
$M -e "DROP DATABASE consultorio_simulacro;"; rm -rf "$D"; echo "base de simulacro borrada"
