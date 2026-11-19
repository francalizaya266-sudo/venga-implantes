#!/usr/bin/env bash
# Aplica en un firewall las reglas de nftables del repositorio. Lo ejecuta "desplegar.sh reglas" dentro de vm-fw
# con "az vm run-command".
#
#   aplicar-reglas.sh <reglas-en-base64>
set -euo pipefail

REGLAS="${1:?Faltan las reglas en base64}"
NUEVAS=$(mktemp)
trap 'rm -f "$NUEVAS"' EXIT

printf '%s' "$REGLAS" | base64 -d > "$NUEVAS"

# Se comprueban antes de aplicar: un error de sintaxis no deja al firewall sin reglas.
nft -c -f "$NUEVAS"
cp /etc/nftables.conf "/etc/nftables.conf.$(date +%Y%m%d%H%M%S)"
install -m 644 "$NUEVAS" /etc/nftables.conf
nft -f /etc/nftables.conf
echo "Reglas aplicadas en $(hostname): $(nft list ruleset | grep -c ' accept\| drop') reglas de aceptar o bloquear."
