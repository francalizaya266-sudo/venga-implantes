#!/usr/bin/env bash
# Un solo comando para toda la infraestructura en Azure. Pensado para Azure Cloud Shell (shell.azure.com):
# ya trae az, Bicep y git, así que no hace falta instalar nada en tu PC.
#
#   bash infra/desplegar.sh <orden>
#
#   preparar      Revisa la sesión, compila las plantillas, registra los servicios de Azure, muestra la cuota de
#                 vCPU y crea la llave SSH y los grupos de recursos.
#   todo          produccion + secretos + sede + vpn + basedatos, en ese orden (unos 40 minutos).
#   produccion    Producción completa: red, firewall, proxy, app, MySQL, Key Vault, Blob, alertas.
#   secretos      Crea en Key Vault las claves que falten (app, los 4 usuarios de MySQL y la VPN).
#   sede [vm]     Sede de la clínica: red, firewall, Wazuh y una VM de laboratorio: recepcion (por defecto) u
#                 honeypot. La cuota de estudiante (6 vCPU por región) da para 3 VM a la vez: cambiar de una a
#                 otra borra la anterior y crea la nueva (unos 10 minutos).
#   vpn           Configura la VPN IPsec entre los dos firewalls.
#   basedatos     Crea los usuarios de MySQL de database/sql/seguridad.sql (se repite después de migrar).
#   reglas        Aplica en los dos firewalls las reglas de infra/firewall/*.nft.
#   acceso        Pone tu IP y la de esta Cloud Shell en los NSG (la IP de Cloud Shell cambia por sesión).
#   encender      Enciende todas las VM (los firewalls primero).
#   apagar        Desasigna todas las VM: dejan de cobrar cómputo y conservan su configuración.
#   estado        Qué está encendido, con qué IP, y el estado de la VPN.
#   borrar-sede   Borra todo el grupo de la sede (al terminar el proyecto).
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
# La sede va en otra región: cada región tiene su propia cuota de 6 vCPU.
REGION_SEDE="${REGION_SEDE:-$REGION}"
LLAVE="$HOME/.ssh/vega"
RED_PROD=10.20.0.0/16
RED_SEDE=10.10.0.0/16
PROVEEDORES=(Microsoft.CloudShell Microsoft.Compute Microsoft.Network Microsoft.Storage Microsoft.KeyVault
  Microsoft.DBforMySQL Microsoft.OperationalInsights Microsoft.Insights Microsoft.ManagedIdentity Microsoft.DevTestLab)

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
  export SSH_PUBLIC_KEY ADMIN_SOURCE_IPS ALERT_EMAIL GITHUB_REPO AUTO_SHUTDOWN SETUP_SSH SITE_DOMAIN DEPLOY_VMS
  SSH_PUBLIC_KEY="$(cat "$LLAVE.pub")"
  ADMIN_SOURCE_IPS="$(ips_admin)"
  AUTO_SHUTDOWN="${AUTO_SHUTDOWN:-}"
  GITHUB_REPO="${GITHUB_REPO:-}"
  SETUP_SSH="${SETUP_SSH:-true}"
  SITE_DOMAIN="${DOMINIO:-}"
  DEPLOY_VMS=true
}

boveda() {
  usar "$SUB_PROD"
  az keyvault list -g "$RG_PROD" --query "[0].name" -o tsv 2>/dev/null || true
}

secreto() {
  az keyvault secret show --vault-name "$1" -n "$2" --query value -o tsv
}

# Guarda un secreto con reintentos: el permiso sobre un Key Vault recién creado tarda un par de minutos en valer.
guardar_secreto() {
  local kv="$1" nombre="$2" valor="$3" intento
  for intento in 1 2 3 4 5 6 7 8; do
    if az keyvault secret set --vault-name "$kv" -n "$nombre" --value "$valor" -o none 2>/dev/null; then
      return 0
    fi
    echo "  Esperando el permiso sobre $kv (intento $intento)..."
    sleep 20
  done
  echo "No se pudo guardar $nombre en $kv." >&2
  return 1
}

secreto_si_falta() {
  local kv="$1" nombre="$2" valor="$3"
  if az keyvault secret show --vault-name "$kv" -n "$nombre" -o none 2>/dev/null; then
    echo "  $nombre: ya existe"
  else
    guardar_secreto "$kv" "$nombre" "$valor"
    echo "  $nombre: creado"
  fi
}

# La clave del administrador de MySQL se pide una vez y queda en Key Vault: los despliegues siguientes la reusan
# (si cambiara entre despliegues, Azure la cambiaría en el servidor).
clave_mysql() {
  local kv
  kv="$(boveda)"
  if [ -n "$kv" ] && secreto "$kv" mysql-admin-password >/dev/null 2>&1; then
    secreto "$kv" mysql-admin-password
    return
  fi
  if [ -z "${MYSQL_ADMIN_PASSWORD:-}" ]; then
    read -r -s -p "Clave nueva del administrador de MySQL (guárdala también en 1Password): " MYSQL_ADMIN_PASSWORD
    echo >&2
  fi
  printf '%s' "$MYSQL_ADMIN_PASSWORD"
}

ip_firewall() {
  usar "$1"
  az network public-ip show -g "$2" -n pip-fw --query ipAddress -o tsv 2>/dev/null || true
}

# Ejecuta un script del repositorio dentro de una VM, sin SSH (az vm run-command).
remoto() {
  local rg="$1" vm="$2" script="$3"
  shift 3
  az vm run-command invoke -g "$rg" -n "$vm" --command-id RunShellScript \
    --scripts @"$RAIZ/infra/scripts/$script" --parameters "$@" \
    --query 'value[0].message' -o tsv
}

orden_produccion() {
  usar "$SUB_PROD"
  comunes
  export OPERATOR_OBJECT_ID MYSQL_ADMIN_PASSWORD
  OPERATOR_OBJECT_ID="$(az ad signed-in-user show --query id -o tsv)"
  MYSQL_ADMIN_PASSWORD="$(clave_mysql)"
  usar "$SUB_PROD"
  paso "Producción en $RG_PROD (unos 15 minutos)"
  az deployment group create -g "$RG_PROD" -n "vega-prod-$(date +%Y%m%d%H%M%S)" \
    -f "$AZ_DIR/prod/main.bicep" -p "$AZ_DIR/prod/prod.bicepparam" \
    --query properties.outputs -o jsonc
  guardar_secreto "$(boveda)" mysql-admin-password "$MYSQL_ADMIN_PASSWORD"
  echo
  echo "  Apunta el dominio (registros A de @ y www) a la IP pública del firewall: $(ip_firewall "$SUB_PROD" "$RG_PROD")"
}

orden_sede() {
  local lab="${1:-recepcion}" otra
  case "$lab" in
    recepcion) otra=vm-honeypot ;;
    honeypot) otra=vm-recepcion ;;
    ninguna) otra="" ;;
    *) echo "Uso: desplegar.sh sede [recepcion|honeypot|ninguna]" >&2; exit 1 ;;
  esac
  usar "$SUB_SEDE"
  comunes
  export LAB_VM="$lab"
  [ -n "$SITE_DOMAIN" ] || { echo "Falta DOMINIO en $ENTORNO." >&2; exit 1; }
  # Solo caben 3 VM: la VM de laboratorio anterior se borra antes de crear la nueva (su disco y su tarjeta también).
  for vm in vm-honeypot vm-recepcion; do
    if { [ "$vm" = "$otra" ] || [ "$lab" = ninguna ]; } && az vm show -g "$RG_SEDE" -n "$vm" -o none 2>/dev/null; then
      paso "Borrando $vm para dejar lugar en la cuota"
      az vm delete -g "$RG_SEDE" -n "$vm" --yes -o none
    fi
  done
  paso "Sede de la clínica en $RG_SEDE con la VM de laboratorio: $lab (unos 15 minutos)"
  az deployment group create -g "$RG_SEDE" -n "vega-sede-$(date +%Y%m%d%H%M%S)" \
    -f "$AZ_DIR/clinica/main.bicep" -p "$AZ_DIR/clinica/clinica.bicepparam" -o none
  echo "  Listo. Al terminar la sesión: bash infra/desplegar.sh apagar"
}

orden_secretos() {
  local kv nombre
  kv="$(boveda)"
  [ -n "$kv" ] || { echo "Primero: desplegar.sh produccion" >&2; exit 1; }
  paso "Secretos en $kv"
  secreto_si_falta "$kv" app-key "base64:$(openssl rand -base64 32)"
  for nombre in db-password db-password-despliegue db-password-reportes db-password-respaldo vpn-psk; do
    secreto_si_falta "$kv" "$nombre" "$(openssl rand -base64 33 | tr -d '/+=')"
  done
  echo "  Faltan a mano, cuando los tengan: mail-password, whatsapp-cloud-token, dni-lookup-token, sentry-dsn"
  echo "  az keyvault secret set --vault-name $kv -n mail-password --value '...'"
}

orden_vpn() {
  local ip_prod ip_sede kv psk
  ip_prod="$(ip_firewall "$SUB_PROD" "$RG_PROD")"
  ip_sede="$(ip_firewall "$SUB_SEDE" "$RG_SEDE")"
  [ -n "$ip_prod" ] && [ -n "$ip_sede" ] || { echo "Faltan los firewalls: corre 'produccion' y 'sede' primero." >&2; exit 1; }
  kv="$(boveda)"
  psk="$(secreto "$kv" vpn-psk)"
  paso "VPN: producción $ip_prod ⇄ sede $ip_sede"
  usar "$SUB_PROD"
  remoto "$RG_PROD" vm-fw configurar-vpn.sh "$ip_prod" "$ip_sede" "$RED_PROD" "$RED_SEDE" "$psk"
  usar "$SUB_SEDE"
  remoto "$RG_SEDE" vm-fw configurar-vpn.sh "$ip_sede" "$ip_prod" "$RED_SEDE" "$RED_PROD" "$psk"
}

orden_basedatos() {
  local kv host
  kv="$(boveda)"
  usar "$SUB_PROD"
  host="$(az mysql flexible-server list -g "$RG_PROD" --query "[0].fullyQualifiedDomainName" -o tsv)"
  paso "Usuarios de MySQL en $host (seguridad.sql, desde vm-app)"
  remoto "$RG_PROD" vm-app remoto-seguridad.sh "$kv" "$host" \
    "$(base64 -w0 "$RAIZ/backend/database/sql/seguridad.sql")"
}

orden_reglas() {
  paso "Reglas del firewall de producción"
  usar "$SUB_PROD"
  remoto "$RG_PROD" vm-fw aplicar-reglas.sh "$(base64 -w0 "$RAIZ/infra/firewall/produccion.nft")"
  paso "Reglas del firewall de la sede"
  usar "$SUB_SEDE"
  remoto "$RG_SEDE" vm-fw aplicar-reglas.sh "$(base64 -w0 "$RAIZ/infra/firewall/sede.nft")"
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
    for sub in "$SUB_PROD" "$SUB_SEDE"; do
      usar "$sub"
      paso "Servicios de Azure en \"$sub\" (las suscripciones de estudiante no los traen registrados)"
      for proveedor in "${PROVEEDORES[@]}"; do
        az provider register -n "$proveedor" -o none
      done
      for proveedor in "${PROVEEDORES[@]}"; do
        until [ "$(az provider show -n "$proveedor" --query registrationState -o tsv)" = "Registered" ]; do
          echo "  esperando $proveedor..."
          sleep 15
        done
      done
      echo "  Todos registrados."
    done
    paso "Cuota de vCPU (cada VM de la serie B v2 usa 2; producción usa 6 y la sede 6)"
    usar "$SUB_PROD"
    echo "  Producción, $REGION: $(az vm list-usage -l "$REGION" --query "[?name.value=='cores'] | [0].[currentValue, limit]" -o tsv | paste -sd/) vCPU en uso"
    usar "$SUB_SEDE"
    echo "  Sede, $REGION_SEDE: $(az vm list-usage -l "$REGION_SEDE" --query "[?name.value=='cores'] | [0].[currentValue, limit]" -o tsv | paste -sd/) vCPU en uso"
    paso "Grupos de recursos"
    usar "$SUB_PROD"; az group create -n "$RG_PROD" -l "$REGION" -o none
    usar "$SUB_SEDE"
    actual="$(az group show -n "$RG_SEDE" --query location -o tsv 2>/dev/null || true)"
    if [ -n "$actual" ] && [ "$actual" != "$REGION_SEDE" ]; then
      if [ -z "$(az resource list -g "$RG_SEDE" --query "[0].id" -o tsv)" ]; then
        echo "  $RG_SEDE estaba vacío en $actual: se vuelve a crear en $REGION_SEDE"
        az group delete -n "$RG_SEDE" --yes
      else
        echo "  $RG_SEDE ya tiene recursos en $actual: bórralo o cambia REGION_SEDE." >&2
        exit 1
      fi
    fi
    az group create -n "$RG_SEDE" -l "$REGION_SEDE" -o none
    echo "  $RG_PROD ($REGION) y $RG_SEDE ($REGION_SEDE) listos."
    ;;

  todo)
    orden_produccion
    orden_secretos
    orden_sede
    paso "Esperando 3 minutos a que los firewalls terminen de instalarse"
    sleep 180
    orden_vpn
    orden_basedatos
    paso "Listo. Sigue: variables de GitHub, 'Desplegar en Azure' en Actions y el certificado del proxy (infra/README.md)."
    ;;

  produccion) orden_produccion ;;
  secretos) orden_secretos ;;
  sede) orden_sede "${2:-recepcion}" ;;
  vpn) orden_vpn ;;
  basedatos) orden_basedatos ;;
  reglas) orden_reglas ;;

  acceso)
    ips="$(ips_admin)"
    paso "Acceso de administración desde: $ips"
    IFS=',' read -r -a lista <<< "$ips"
    usar "$SUB_PROD"
    az network nsg rule update -g "$RG_PROD" --nsg-name nsg-wan -n Permitir-Admin --source-address-prefixes "${lista[@]}" -o none 2>/dev/null \
      && echo "  producción: SSH del firewall y de instalación" \
      || echo "  producción: sin regla de administración (se crea con MI_IP al desplegar)"
    for nsg in nsg-dmz nsg-app; do
      az network nsg rule update -g "$RG_PROD" --nsg-name "$nsg" -n Permitir-SSH-Instalacion --source-address-prefixes "${lista[@]}" -o none 2>/dev/null || true
    done
    usar "$SUB_SEDE"
    az network nsg rule update -g "$RG_SEDE" --nsg-name nsg-wan -n Permitir-Admin --source-address-prefixes "${lista[@]}" -o none 2>/dev/null \
      && echo "  sede: SSH del firewall y del jump host" || echo "  sede: todavía no existe"
    ;;

  encender|apagar)
    for par in "$SUB_PROD|$RG_PROD" "$SUB_SEDE|$RG_SEDE"; do
      sub="${par%%|*}"; rg="${par##*|}"
      usar "$sub"
      if [ "$orden" = encender ]; then
        # El firewall primero: las demás salen a internet a través de él.
        if az vm show -g "$rg" -n vm-fw -o none 2>/dev/null; then
          paso "Encendiendo vm-fw ($rg)"
          az vm start -g "$rg" -n vm-fw -o none
        fi
        ids="$(az vm list -g "$rg" --query "[?name!='vm-fw'].id" -o tsv 2>/dev/null || true)"
        if [ -n "$ids" ]; then
          paso "Encendiendo las demás VM de $rg"
          # shellcheck disable=SC2086
          az vm start --ids $ids -o none
        fi
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
    paso "VPN vista desde producción"
    usar "$SUB_PROD"
    az vm run-command invoke -g "$RG_PROD" -n vm-fw --command-id RunShellScript \
      --scripts "swanctl --list-sas | head -12 || echo 'sin túnel'" --query 'value[0].message' -o tsv 2>/dev/null \
      || echo "  (el firewall está apagado)"
    ;;

  borrar-sede)
    usar "$SUB_SEDE"
    read -r -p "Esto borra $RG_SEDE con todas sus VM y discos. Escribe el nombre del grupo para confirmar: " confirmacion
    [ "$confirmacion" = "$RG_SEDE" ] || { echo "Cancelado."; exit 1; }
    az group delete -n "$RG_SEDE" --yes --no-wait
    echo "Borrándose en segundo plano."
    ;;

  *)
    sed -n '2,22p' "$0" | sed 's/^# \{0,1\}//'
    exit 1
    ;;
esac
