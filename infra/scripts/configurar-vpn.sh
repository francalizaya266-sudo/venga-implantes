#!/usr/bin/env bash
# Configura la VPN IPsec (strongSwan) en un firewall. Lo ejecuta "desplegar.sh vpn" dentro de cada vm-fw con
# "az vm run-command", del lado de producción y del lado de la sede.
#
#   configurar-vpn.sh <ip-publica-propia> <ip-publica-del-otro> <red-propia> <red-del-otro> <clave-compartida>
set -euo pipefail

PROPIA="${1:?Falta la IP pública propia}"
OTRO="${2:?Falta la IP pública del otro firewall}"
RED_PROPIA="${3:?Falta la red propia}"
RED_OTRO="${4:?Falta la red del otro lado}"
CLAVE="${5:?Falta la clave compartida}"

umask 077
cat > /etc/swanctl/conf.d/vega.conf <<EOF
# VPN entre producción y la sede de la clínica. Generado por infra/scripts/configurar-vpn.sh.
connections {
  vega {
    version = 2
    local_addrs = %any
    remote_addrs = ${OTRO}
    # Los dos firewalls están detrás del NAT de Azure: IPsec siempre encapsulado en UDP 4500.
    encap = yes
    proposals = aes256gcm16-prfsha256-modp2048
    dpd_delay = 30s
    local {
      auth = psk
      id = ${PROPIA}
    }
    remote {
      auth = psk
      id = ${OTRO}
    }
    children {
      redes {
        local_ts = ${RED_PROPIA}
        remote_ts = ${RED_OTRO}
        esp_proposals = aes256gcm16-modp2048
        start_action = start
        dpd_action = restart
        close_action = start
      }
    }
  }
}

secrets {
  ike-vega {
    id-1 = ${PROPIA}
    id-2 = ${OTRO}
    secret = "${CLAVE}"
  }
}
EOF

systemctl enable --now strongswan
swanctl --load-all
swanctl --initiate --child redes --timeout 20 >/dev/null 2>&1 || true
sleep 3
echo "== Estado de la VPN en $(hostname)"
swanctl --list-sas | head -20
