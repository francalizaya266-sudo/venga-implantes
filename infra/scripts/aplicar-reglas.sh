#!/usr/bin/env bash
# Aplica en un firewall las reglas de nftables del repositorio. Lo ejecuta "desplegar.sh reglas" dentro de vm-fw
# con "az vm run-command".
#
#   aplicar-reglas.sh <reglas-en-base64>
set -euo pipefail

REGLAS="${1:?Faltan las reglas en base64}"

# Llega sin el relleno "=" (ver b64 en desplegar.sh): se repone antes de decodificar.
desde_b64() {
  local s="$1"
  while [ $(( ${#s} % 4 )) -ne 0 ]; do s="${s}="; done
  printf '%s' "$s" | base64 -d
}
NUEVAS=$(mktemp)
trap 'rm -f "$NUEVAS"' EXIT

desde_b64 "$REGLAS" > "$NUEVAS"

# Se comprueban antes de aplicar: un error de sintaxis no deja al firewall sin reglas.
nft -c -f "$NUEVAS"
cp /etc/nftables.conf "/etc/nftables.conf.$(date +%Y%m%d%H%M%S)"
install -m 644 "$NUEVAS" /etc/nftables.conf
nft -f /etc/nftables.conf
echo "Reglas aplicadas en $(hostname): $(nft list ruleset | grep -c ' accept\| drop') reglas de aceptar o bloquear."
