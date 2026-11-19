# Firewall Linux (vm-fw)

Cada red tiene un `vm-fw`: una VM Ubuntu 24.04 con dos tarjetas, configurada sola por cloud-init
(`infra/cloud-init/firewall.yaml`). Hace lo que en el diseño hacía OPNsense, con las mismas herramientas de base:

| Función | Herramienta | Dónde está |
| --- | --- | --- |
| Firewall entre redes y NAT | nftables | `produccion.nft` y `sede.nft` (este directorio) |
| VPN IPsec entre producción y la sede | strongSwan (IKEv2, AES-256-GCM, NAT-T) | `infra/scripts/configurar-vpn.sh` |
| IDS en la WAN | Suricata con reglas ET Open | `/var/log/suricata/eve.json` |
| DNS interno de la sede | Unbound | El dominio de la web se resuelve al proxy de producción |

| | Producción | Sede |
| --- | --- | --- |
| WAN (`eth0`) | 10.20.0.4 + IP pública | 10.10.0.4 + IP pública |
| LAN (`eth1`) | 10.20.1.4 | 10.10.1.4 |
| Entrada desde internet | 80 y 443 → proxy; 2200 SSH del firewall; 2222/2223 SSH de instalación | 2200 SSH del firewall (también jump host); 22 y 3306 → honeypot |

## Cambiar una regla

Las reglas son código: se cambian en el repositorio, con revisión, y se aplican con un comando.

1. Edita `produccion.nft` o `sede.nft` en una rama y abre un pull request.
2. Después del merge, en Cloud Shell: `git pull && bash infra/desplegar.sh reglas`.

`aplicar-reglas.sh` revisa la sintaxis con `nft -c` antes de aplicar y guarda una copia de las reglas anteriores
en el mismo firewall: un error no deja la red sin reglas.

## Ver qué está pasando

Desde Cloud Shell, por SSH (`ssh -p 2200 -i ~/.ssh/vega vegaadmin@IP_PUBLICA_DEL_FIREWALL`):

| Para ver | Comando |
| --- | --- |
| Reglas cargadas | `sudo nft list ruleset` |
| Bloqueos en vivo (evidencia de pruebas negativas) | `sudo journalctl -k -f \| grep fw-` |
| Estado de la VPN | `sudo swanctl --list-sas` |
| Alertas de Suricata | `sudo tail -f /var/log/suricata/eve.json \| jq 'select(.event_type=="alert")'` |
| Conexiones que pasan | `sudo conntrack -L` (instala `conntrack` si hace falta) |

Con el agente de Wazuh instalado (`infra/scripts/instalar-agentes.sh`), los bloqueos de nftables y las alertas de
Suricata llegan al SIEM.

## Pasar Suricata a IPS

Por defecto Suricata trabaja como IDS (detecta y registra). Para que bloquee, se cambia a modo NFQUEUE y se manda
el tráfico de reenvío a la cola con una regla `queue` en la cadena `reenvio`. Háganlo en la fase de pruebas, en
una rama, y comprueben que la web sigue respondiendo antes del merge. Si la VM queda corta de memoria, cambien el
tamaño a `Standard_B2als_v2` (4 GiB) en `infra/azure/modules/firewall-vm.bicep`.

## Equivalencia con el E1

El E1 (figura 13) nombra a OPNsense como firewall. Este firewall cumple los mismos papeles (entrada única, NAT al
proxy de la DMZ, VPN IPsec, Suricata) y se crea sin pasos manuales. En el E1 basta cambiar "OPNsense" por
"firewall Linux (nftables, strongSwan, Suricata)".
