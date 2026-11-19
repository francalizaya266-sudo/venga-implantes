#!/usr/bin/env bash
# Lee secretos de Key Vault con la identidad administrada de la VM (sin az CLI ni claves guardadas).
# Se carga con "source"; luego:  kv_secret db-password
#
# Necesita /etc/vega/vega.env con:  KEY_VAULT=kv-vega-xxxxxxxx

# shellcheck disable=SC1091
source /etc/vega/vega.env
: "${KEY_VAULT:?Falta KEY_VAULT en /etc/vega/vega.env}"

_kv_token() {
  curl -sf -H Metadata:true \
    "http://169.254.169.254/metadata/identity/oauth2/token?api-version=2018-02-01&resource=https%3A%2F%2Fvault.azure.net" \
    | jq -r .access_token
}

kv_secret() {
  local name="$1" token value
  token="${_KV_TOKEN:-$(_kv_token)}"
  _KV_TOKEN="$token"
  value=$(curl -sf -H "Authorization: Bearer $token" \
    "https://${KEY_VAULT}.vault.azure.net/secrets/${name}?api-version=7.4" | jq -r .value)
  if [ -z "$value" ] || [ "$value" = "null" ]; then
    echo "No se pudo leer el secreto '$name' de $KEY_VAULT" >&2
    return 1
  fi
  printf '%s' "$value"
}
