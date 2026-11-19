#!/usr/bin/env bash
# Un solo comando para toda la infraestructura en Azure. Pensado para Azure Cloud Shell (shell.azure.com):
# ya trae az, Bicep y git, así que no hace falta instalar nada en tu PC.
#
#   bash infra/desplegar.sh <orden>
#
#   preparar          Revisa la sesión, compila las plantillas, crea la llave SSH y los grupos de recursos.
#   produccion red    Producción, primera pasada: red, MySQL, Key Vault, Blob, alertas, identidad de GitHub.
#   secretos          Crea en Key Vault las claves que falten (app y los 4 usuarios de MySQL).
#   produccion vms    Producción, segunda pasada: vm-proxy y vm-app (con OPNsense ya instalado).
#   sede red          Sede de la clínica, primera pasada.
#   sede vms          Sede de la clínica, segunda pasada (con su OPNsense ya instalado).
#   acceso            Pone tu IP y la de esta Cloud Shell en los NSG (la IP de Cloud Shell cambia por sesión).
#   encender          Enciende todas las VM (los OPNsense primero).
#   apagar            Desasigna todas las VM: dejan de cobrar cómputo y conservan su configuración.
#   estado            Qué está encendido y con qué IP.
#   borrar-sede       Borra todo el grupo de la sede (al terminar el proyecto).
#
# La configuración sin secretos va en infra/azure/entorno.env (copia de entorno.env.example).
set -euo pipefail

RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
AZ_DIR="$RAIZ/infra/azure"
ENTORNO="$AZ_DIR/entorno.env"

[ -f "$ENTORNO" ] || { echo "Falta $ENTORNO. Copia infra/azure/entorno.env.example y complétalo." >&2; exit 1; }
# shellcheck disable=SC1090
source "$ENTORNO"

: "${REGION:?}" "${SUB_PROD:?}" "${SUB_SEDE:?}" "${RG_PROD:?}" "${RG_SEDE:?}"
LLAVE="$HOME/.ssh/vega"

paso() { printf '\n==> %s\n' "$*"; }

usar() { az account set --subscription "$1"; }

ips_admin() {
  local nube
  nube="$(curl -s https://ifconfig.me)/32"
  if [ -n "${MI_IP:-}" ]; then
    echo "${MI_IP%/32}/32,$nube"
  else
    echo "$nube"
  fi
}

comunes() {
  export SSH_PUBLIC_KEY ADMIN_SOURCE_IPS ALERT_EMAIL GITHUB_REPO AUTO_SHUTDOWN SETUP_SSH
  SSH_PUBLIC_KEY="$(cat "$LLAVE.pub")"
  ADMIN_SOURCE_IPS="$(ips_admin)"
  AUTO_SHUTDOWN="${AUTO_SHUTDOWN:-}"
  GITHUB_REPO="${GITHUB_REPO:-}"
  SETUP_SSH="${SETUP_SSH:-true}"
}

boveda() {
  az keyvault list -g "$RG_PROD" --query "[0].name" -o tsv 2>/dev/null || true
}

# La clave del administrador de MySQL se pide una vez y queda en Key Vault: los despliegues siguientes la reusan
# (si cambiara entre despliegues, Azure la cambiaría en el servidor).
clave_mysql() {
  local kv
  kv="$(boveda)"
  if [ -n "$kv" ] && az keyvault secret show --vault-name "$kv" -n mysql-admin-password --query value -o tsv >/dev/null 2>&1; then
    az keyvault secret show --vault-name "$kv" -n mysql-admin-password --query value -o tsv
    return
  fi
  if [ -z "${MYSQL_ADMIN_PASSWORD:-}" ]; then
    read -r -s -p "Clave nueva del administrador de MySQL (guárdala también en 1Password): " MYSQL_ADMIN_PASSWORD
    echo >&2
  fi
  printf '%s' "$MYSQL_ADMIN_PASSWORD"
}

desplegar_prod() {
  local pasada="$1"
  usar "$SUB_PROD"
  comunes
  export DEPLOY_VMS OPERATOR_OBJECT_ID MYSQL_ADMIN_PASSWORD
  DEPLOY_VMS="$pasada"
  OPERATOR_OBJECT_ID="$(az ad signed-in-user show --query id -o tsv)"
  MYSQL_ADMIN_PASSWORD="$(clave_mysql)"
  paso "Producción (DEPLOY_VMS=$pasada) en $RG_PROD"
  az deployment group create -g "$RG_PROD" -n "vega-prod-$(date +%Y%m%d%H%M%S)" \
    -f "$AZ_DIR/prod/main.bicep" -p "$AZ_DIR/prod/prod.bicepparam" \
    --query properties.outputs -o jsonc
  # El permiso sobre Key Vault recién creado puede tardar un par de minutos en valer.
  local kv intento
  kv="$(boveda)"
  for intento in 1 2 3 4 5 6; do
    if az keyvault secret set --vault-name "$kv" -n mysql-admin-password --value "$MYSQL_ADMIN_PASSWORD" -o none 2>/dev/null; then
      return
    fi
    echo "  Esperando el permiso sobre $kv (intento $intento)..."
    sleep 20
  done
  echo "No se pudo guardar mysql-admin-password en $kv: guárdala a mano." >&2
}

desplegar_sede() {
  local pasada="$1"
  usar "$SUB_SEDE"
  comunes
  export DEPLOY_VMS="$pasada"
  paso "Sede (DEPLOY_VMS=$pasada) en $RG_SEDE"
  az deployment group create -g "$RG_SEDE" -n "vega-sede-$(date +%Y%m%d%H%M%S)" \
    -f "$AZ_DIR/clinica/main.bicep" -p "$AZ_DIR/clinica/clinica.bicepparam" -o none
  paso "Listo. Las VM de la sede se apagan solas a las 20:00."
}

secreto_si_falta() {
  local kv="$1" nombre="$2" valor="$3"
  if az keyvault secret show --vault-name "$kv" -n "$nombre" -o none 2>/dev/null; then
    echo "  $nombre: ya existe"
  else
    az keyvault secret set --vault-name "$kv" -n "$nombre" --value "$valor" -o none
    echo "  $nombre: creado"
  fi
}

vms_de() {
  az vm list -g "$1" --query "[].name" -o tsv 2>/dev/null || true
}

# Los OPNsense primero: el resto sale a internet a través de ellos.
ordenar_firewall_primero() {
  local todas
  todas="$(cat)"
  printf '%s
' "$todas" | grep -i opn || true
  printf '%s
' "$todas" | grep -vi opn || true
}

orden="${1:-}"
case "$orden" in
  preparar)
    paso "Sesión de Azure"
    az account show --query "{usuario:user.name, suscripcion:name}" -o table
    paso "Compilando las plantillas"
    az bicep build -f "$AZ_DIR/prod/main.bicep" --stdout > /dev/null
    az bicep build -f "$AZ_DIR/clinica/main.bicep" --stdout > /dev/null
    echo "  Compilan sin errores."
    if [ ! -f "$LLAVE" ]; then
      paso "Llave SSH del equipo en $LLAVE (pásala a 1Password)"
      mkdir -p "$HOME/.ssh"
      ssh-keygen -t ed25519 -C "vega-implant" -f "$LLAVE" -N ""
    fi
    paso "Grupos de recursos"
    usar "$SUB_PROD"; az group create -n "$RG_PROD" -l "$REGION" -o none
    usar "$SUB_SEDE"; az group create -n "$RG_SEDE" -l "$REGION" -o none
    echo "  $RG_PROD y $RG_SEDE listos."
    ;;

  produccion)
    case "${2:-}" in
      red) desplegar_prod false ;;
      vms) desplegar_prod true ;;
      *) echo "Uso: desplegar.sh produccion red|vms" >&2; exit 1 ;;
    esac
    ;;

  sede)
    case "${2:-}" in
      red) desplegar_sede false ;;
      vms) desplegar_sede true ;;
      *) echo "Uso: desplegar.sh sede red|vms" >&2; exit 1 ;;
    esac
    ;;

  secretos)
    usar "$SUB_PROD"
    kv="$(boveda)"
    [ -n "$kv" ] || { echo "Primero: desplegar.sh produccion red" >&2; exit 1; }
    paso "Secretos en $kv"
    secreto_si_falta "$kv" app-key "base64:$(openssl rand -base64 32)"
    for nombre in db-password db-password-despliegue db-password-reportes db-password-respaldo; do
      secreto_si_falta "$kv" "$nombre" "$(openssl rand -base64 24 | tr -d '/+=')"
    done
    echo "  Faltan a mano, cuando los tengan: mail-password, whatsapp-cloud-token, dni-lookup-token, sentry-dsn"
    echo "  az keyvault secret set --vault-name $kv -n mail-password --value '...'"
    ;;

  acceso)
    ips="$(ips_admin)"
    paso "Acceso de administración desde: $ips"
    IFS=',' read -r -a lista <<< "$ips"
    usar "$SUB_PROD"
    az network nsg rule update -g "$RG_PROD" --nsg-name nsg-wan -n Permitir-Admin --source-address-prefixes "${lista[@]}" -o none 2>/dev/null \
      && echo "  producción: panel de OPNsense y SSH de instalación" \
      || echo "  producción: sin regla de administración (se crea con ADMIN_SOURCE_IPS al desplegar)"
    for nsg in nsg-dmz nsg-app; do
      az network nsg rule update -g "$RG_PROD" --nsg-name "$nsg" -n Permitir-SSH-Instalacion --source-address-prefixes "${lista[@]}" -o none 2>/dev/null || true
    done
    usar "$SUB_SEDE"
    az network nsg rule update -g "$RG_SEDE" --nsg-name nsg-wan -n Permitir-Admin --source-address-prefixes "${lista[@]}" -o none 2>/dev/null \
      && echo "  sede: panel de OPNsense y SSH al jump host" || echo "  sede: todavía no existe"
    ;;

  encender|apagar)
    for par in "$SUB_PROD|$RG_PROD" "$SUB_SEDE|$RG_SEDE"; do
      sub="${par%%|*}"; rg="${par##*|}"
      usar "$sub"
      if [ "$orden" = encender ]; then
        mapfile -t vms < <(vms_de "$rg" | ordenar_firewall_primero)
        for vm in "${vms[@]}"; do
          [ -n "$vm" ] || continue
          paso "Encendiendo $vm ($rg)"
          az vm start -g "$rg" -n "$vm" -o none
        done
      else
        ids="$(az vm list -g "$rg" --query "[].id" -o tsv 2>/dev/null || true)"
        if [ -n "$ids" ]; then
          paso "Apagando (desasignando) las VM de $rg"
          # shellcheck disable=SC2086
          az vm deallocate --ids $ids -o none
        fi
      fi
    done
    paso "Listo. MySQL sigue encendido; para pausarlo: az mysql flexible-server stop -g $RG_PROD -n <servidor>"
    ;;

  estado)
    for par in "$SUB_PROD|$RG_PROD" "$SUB_SEDE|$RG_SEDE"; do
      sub="${par%%|*}"; rg="${par##*|}"
      usar "$sub"
      paso "$rg"
      az vm list -g "$rg" -d --query "[].{VM:name, Estado:powerState, IP_privada:privateIps, IP_publica:publicIps}" -o table 2>/dev/null || echo "  (sin VM)"
    done
    ;;

  borrar-sede)
    usar "$SUB_SEDE"
    read -r -p "Esto borra $RG_SEDE con todas sus VM y discos. Escribe el nombre del grupo para confirmar: " confirmacion
    [ "$confirmacion" = "$RG_SEDE" ] || { echo "Cancelado."; exit 1; }
    az group delete -n "$RG_SEDE" --yes --no-wait
    echo "Borrándose en segundo plano."
    ;;

  *)
    sed -n '2,20p' "$0" | sed 's/^# \{0,1\}//'
    exit 1
    ;;
esac
