#!/usr/bin/env bash
# Instala el sitio en vm-proxy y pide el certificado de Let's Encrypt.
#
# Uso (en vm-proxy):  sudo DOMINIO=vegaimplantes.me CORREO=equipo@... bash configurar-proxy.sh
#
# Antes: el registro A del dominio debe apuntar a la IP pública del firewall de producción (vm-fw), que ya
# reenvía el 80 y el 443 a 10.20.3.4 (infra/firewall/produccion.nft).
set -euo pipefail

: "${DOMINIO:?Indica DOMINIO, por ejemplo vegaimplantes.me}"
: "${CORREO:?Indica CORREO para los avisos del certificado}"
INFRA_DIR="$(cd "$(dirname "$0")/.." && pwd)"
SITE=/etc/nginx/sites-available/vegaimplant.conf

if [ ! -f "$SITE" ]; then
  sed "s/vegaimplantes\.me/${DOMINIO}/g" "$INFRA_DIR/nginx/proxy.conf" > "$SITE"
  ln -sf "$SITE" /etc/nginx/sites-enabled/vegaimplant.conf
fi

nginx -t
systemctl reload nginx

certbot --nginx -d "$DOMINIO" -d "www.$DOMINIO" --redirect --non-interactive --agree-tos -m "$CORREO"

echo "Listo. Prueba: curl -I https://$DOMINIO/up"
