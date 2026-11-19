#!/usr/bin/env bash
# Sube a Blob Storage las copias de la base de datos que hace el sistema (respaldo:crear, 3:00),
# las radiografías, fotos y firmas, y las fotos de la web. Usa la identidad administrada de vm-app:
# no hay claves. Blob guarda versiones y conserva lo borrado 14 días.
#
# Necesita /etc/vega/vega.env con:  STORAGE_ACCOUNT=stvegaxxxxxxxx
set -euo pipefail

# shellcheck disable=SC1091
source /etc/vega/vega.env
: "${STORAGE_ACCOUNT:?Falta STORAGE_ACCOUNT en /etc/vega/vega.env}"

export AZCOPY_AUTO_LOGIN_TYPE=MSI
DEST="https://${STORAGE_ACCOUNT}.blob.core.windows.net/respaldos"
SHARED=/var/www/vega/shared

echo "$(date -Is) inicio"
azcopy sync "$SHARED/storage/app/private/respaldos" "$DEST/base-de-datos" --recursive
if [ -d "$SHARED/storage/app/private/clinica" ]; then
  azcopy sync "$SHARED/storage/app/private/clinica" "$DEST/archivos/clinica" --recursive
fi
azcopy sync "$SHARED/media" "$DEST/archivos/web" --recursive
echo "$(date -Is) fin"
