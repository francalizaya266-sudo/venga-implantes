#!/usr/bin/env bash
# Lo ejecuta GitHub Actions dentro de vm-app con "az vm run-command" (como root, sin SSH y sin IP pública):
# baja de Blob el paquete y la carpeta infra/ de esa versión, y corre deploy-app.sh.
#
#   remoto-publicar.sh <cuenta-de-almacenamiento> <version>
set -euo pipefail

ST="${1:?Falta la cuenta de almacenamiento}"
VERSION="${2:?Falta la versión}"

export HOME=/root
export COMPOSER_HOME=/root/.composer
export AZCOPY_AUTO_LOGIN_TYPE=MSI
export AZCOPY_LOG_LOCATION=/tmp/azcopy
export AZCOPY_JOB_PLAN_LOCATION=/tmp/azcopy

ORIGEN="https://${ST}.blob.core.windows.net/despliegues"
DIR="/opt/vega/despliegues/$VERSION"

mkdir -p "$DIR" /tmp/azcopy
cd "$DIR"
azcopy copy "$ORIGEN/paquete-$VERSION.zip" paquete.zip --log-level ERROR
azcopy copy "$ORIGEN/infra-$VERSION.tgz" infra.tgz --log-level ERROR
tar -xzf infra.tgz

bash infra/scripts/deploy-app.sh "$DIR/paquete.zip"

# Quedan las 3 últimas descargas.
ls -1dt /opt/vega/despliegues/* | tail -n +4 | xargs -r rm -rf
