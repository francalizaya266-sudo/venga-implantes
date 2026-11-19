# Resumen ejecutivo

**Qué se implementó.** La red diseñada en el S1-E1 está desplegada y funcionando en Azure desde el 19/11/2026: producción en Chile Central (`vnet-vega`, 10.20.0.0/16) y la sede de la clínica en North Central US (`vnet-clinica`, 10.10.0.0/16), unidas por una VPN IPsec IKEv2 con AES-256-GCM. Cada red tiene un firewall Linux (nftables, strongSwan y Suricata; Unbound en la sede) creado por código, sin pasos manuales. La web y el panel se publican en `https://vegaimplantes.me` a través del firewall y del proxy de la DMZ (nginx + ModSecurity + Let's Encrypt).

**Qué se probó.** 24 pruebas con resultado esperado y obtenido: 10 positivas, 10 negativas, 1 de recuperación y 3 de detección. **23 cumplen** y una (el honeypot) queda para la siguiente ronda porque la VM de laboratorio activa era la de recepción. La VPN se cortó a propósito y volvió sola en **1,4 segundos**.

**Qué se ve.** El SIEM Wazuh 4.12 recibe los registros de las 6 máquinas (incluidos los dos firewalls) por la VPN: el 19/11/2026 registró **3 442 alertas**, entre ellas 115 intentos de leer archivos `.env`, 82 ráfagas de errores 400 desde una misma IP y 13 escaneos al puerto 3306 de la sede, todos de internet. Suricata sumó 6 360 alertas en producción y 4 565 en la sede, y los firewalls registraron cada conexión bloqueada con su prefijo.

**Qué falta.** Suricata trabaja como IDS y el WAF en modo detección: pasarlos a bloqueo es la siguiente mejora, una vez revisados los falsos positivos (no conformidades NC-02 y NC-06 del SGSI).

# Trazabilidad con el diseño

*Tabla 1. Del diseño del S1-E1 a lo implementado.*

| Diseño (S1-E1) | Implementado | Archivo | Cambio respecto al diseño |
| --- | --- | --- | --- |
| Dos redes /16 sin solaparse | `vnet-vega` 10.20.0.0/16 y `vnet-clinica` 10.10.0.0/16 | `infra/azure/*/main.bicep` | Ninguno |
| Segmentos de la sede (VLAN 10, 30, 50 y 60) | Subredes `snet-mgmt`, `snet-personal`, `snet-servidores`, `snet-dmz` | `infra/azure/clinica/main.bicep` | Ninguno |
| Firewall perimetral (OPNsense en el diseño original) | Firewall Linux: nftables, strongSwan, Suricata y Unbound | `infra/cloud-init/firewall.yaml`, `infra/firewall/*.nft` | Mismas funciones, configurado por código y sin pasos en una interfaz web |
| Ruteo por el firewall | `rt-via-firewall` con siguiente salto en la LAN del firewall | `main.bicep` | Ninguno |
| VPN IPsec sitio a sitio | strongSwan IKEv2, AES-256-GCM, NAT-T | `infra/scripts/configurar-vpn.sh` | Ninguno |
| DMZ con proxy y WAF | vm-proxy con nginx, ModSecurity y CRS | `infra/nginx/proxy.conf` | Se agregó límite de peticiones al panel |
| Panel solo desde la sede | Panel desde internet con MFA obligatorio | `AdminPanelProvider.php` | Cambio aprobado el 19/11: el personal necesita entrar fuera de la clínica; la VPN queda para administrar servidores |
| SIEM con todos los equipos | Wazuh 4.12 con 6 agentes | `infra/cloud-init/wazuh.yaml`, `instalar-agentes.sh` | Ninguno |
| Honeypot en la DMZ de la sede | OpenCanary (SSH 22 y MySQL 3306) | `infra/cloud-init/honeypot.yaml` | Comparte cuota con la VM de recepción: se alternan (`desplegar.sh sede honeypot`) |

# Despliegue de la red

Toda la red se crea con un comando desde Azure Cloud Shell: `bash infra/desplegar.sh todo` (producción, secretos, sede, VPN y usuarios de la base de datos), unos 40 minutos sin intervenir. Las reglas se cambian por pull request y se aplican con `desplegar.sh reglas`, que revisa la sintaxis (`nft -c`) y guarda una copia de las reglas anteriores en el mismo firewall.

*Tabla 2. Estado de las máquinas el 19/11/2026 (`az vm list -d`).*

| VM | Región | Tamaño | IP privada | Estado |
| --- | --- | --- | --- | --- |
| vm-fw (producción) | chilecentral | Standard_B2ats_v2 | 10.20.0.4 y 10.20.1.4 | En ejecución |
| vm-proxy | chilecentral | Standard_B2ats_v2 | 10.20.3.4 | En ejecución |
| vm-app | chilecentral | Standard_B2ats_v2 | 10.20.4.4 | En ejecución |
| vm-fw (sede) | northcentralus | Standard_B2ats_v2 | 10.10.0.4 y 10.10.1.4 | En ejecución |
| vm-recepcion | northcentralus | Standard_B2ats_v2 | 10.10.30.10 | En ejecución |
| vm-wazuh | northcentralus | Standard_B2als_v2 | 10.10.50.10 | En ejecución |

# Conectividad híbrida: VPN

Estado del túnel visto desde el firewall de la sede (`swanctl --list-sas`, IP públicas reemplazadas):

```text
vega: #5, ESTABLISHED, IKEv2
  local  'IP_PUBLICA_SEDE' @ 10.10.0.4[4500]
  remote 'IP_PUBLICA_PROD' @ IP_PUBLICA_PROD[4500]
  redes: #8, reqid 1, INSTALLED, TUNNEL-in-UDP, ESP:AES_GCM_16-256/MODP_2048
    in  c780b3bb, 4020012 bytes,  5570 packets
    out c3019259,  317158 bytes,  4806 packets
    local  10.10.0.0/16
    remote 10.20.0.0/16
```

El túnel está establecido en los dos extremos, con cifrado AES-256-GCM, y lleva tráfico real: registros hacia Wazuh, la copia horaria y las pruebas del personal. El puerto 4500 confirma que pasa por NAT-T, como en una conexión con un proveedor de internet.

**Prueba de recuperación (PR-21).** Se cortó el túnel en el firewall de la sede con `swanctl --terminate --ike vega` a las 11:26:34 del 19/11/2026. Con `close_action = start` y `dpd_action = restart`, strongSwan lo volvió a levantar solo: el ping de la LAN de la sede a la LAN de producción respondió de nuevo a las 11:26:35, **1,4 segundos** después.

**Resolución interna.** En la sede, Unbound responde `vegaimplantes.me` con la IP privada del proxy: desde vm-recepcion, `getent hosts vegaimplantes.me` devuelve `10.20.3.4`, y el panel responde 200 por la VPN (PR-07).

# Seguridad perimetral y segmentación

## Reglas cargadas

| Firewall | Reglas de aceptar o bloquear cargadas | Servicios |
| --- | --: | --- |
| Producción | 27 | nftables, strongSwan y Suricata activos |
| Sede | 34 | nftables, strongSwan, Suricata y Unbound activos |

Las reglas son las de `infra/firewall/produccion.nft` y `sede.nft` (tablas 4 y 5 del S1-E1). Un ejemplo de bloqueo registrado en la sede, cuando la recepción intentó llegar directo a la aplicación (PR-08):

```text
fw-reenvio-bloqueo IN=eth1 OUT=eth0 SRC=10.10.30.10 DST=10.20.4.4 PROTO=TCP SPT=59476 DPT=80 SYN
```

Y en producción, cuando la aplicación intentó llegar por SSH a la recepción (PR-19):

```text
fw-reenvio-bloqueo IN=eth1 OUT=eth0 SRC=10.20.4.4 DST=10.10.30.10 PROTO=TCP SPT=60734 DPT=22 SYN
```

## Grupos de seguridad de red

*Tabla 3. Reglas de entrada de los NSG de producción (además de las reglas por defecto de Azure).*

| NSG | Prioridad | Regla | Origen | Puertos | Acción |
| --- | --: | --- | --- | --- | --- |
| nsg-wan | 100 | Permitir-Web | Internet | 80, 443 | Permitir |
| nsg-wan | 110 | Permitir-IPsec | Internet | UDP 500, 4500 | Permitir |
| nsg-wan | 120 | Permitir-Admin | IP de administración /32 | 2200 | Permitir |
| nsg-dmz | 100 | Permitir-Web | Internet | 80, 443 | Permitir |
| nsg-dmz | 110 | Permitir-Web-Sede | 10.10.0.0/16 | 80, 443 | Permitir |
| nsg-dmz | 115 | Permitir-SSH-Jump | 10.10.0.0/23 | 22 | Permitir |
| nsg-app | 100 | Permitir-Proxy | 10.20.3.4/32 | 80 | Permitir |
| nsg-app | 110 | Permitir-SSH-Jump | 10.10.0.0/23 | 22 | Permitir |
| nsg-app | 200 | Bloquear-Resto-HTTP | Cualquiera | 80, 443 | Bloquear |
| nsg-lan | 100 | Permitir-Sede | 10.10.0.0/16 | Todos | Permitir |
| nsg-lan | 110 | Permitir-Salida-Por-Firewall | 10.20.0.0/16 | Todos | Permitir |

La sede tiene `nsg-wan` (IPsec, administración y los señuelos 22 y 3306 del honeypot), `nsg-interno` (lo que llega de producción por la VPN: registros, syslog y copias) y `nsg-dmz`, que de salida solo deja enviar registros a Wazuh y bloquea todo lo demás hacia la clínica y hacia producción.

**Lección del despliegue.** El firewall es un dispositivo virtual (NVA): los NSG de su subred LAN ven el tráfico reenviado con su origen y destino originales. Hizo falta permitir en `nsg-lan` la entrada desde las subredes internas hacia internet (`Permitir-Salida-Por-Firewall`) y la salida hacia ellas (`Permitir-Reenvio-Hacia-Adentro`); sin esas dos reglas, Azure descartaba el tráfico antes de que llegara a nftables.

## DMZ y proxy

| Control | Estado | Evidencia |
| --- | --- | --- |
| HTTPS con Let's Encrypt (raíz y www) | Activo, vence el 17/02/2027, renovación automática | `certbot certificates` |
| HSTS, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy | Presentes en todas las respuestas | `curl -I` |
| ModSecurity + OWASP CRS | Modo detección; 615 eventos en el registro de auditoría | `/var/log/modsec_audit.log` |
| Límite de peticiones al panel | 10 por segundo por IP con ráfaga de 40; 80 respuestas 429 en la prueba PR-05 | Registro de accesos de nginx |
| Archivos ocultos | `/.env` responde 403 | PR-06 |

# Plan y resultados de pruebas

Las pruebas se ejecutaron el 19/11/2026 con `az vm run-command` dentro de cada VM (abrir una conexión TCP con un tiempo límite de 4 segundos) y, las de internet, desde la consola del operador. Las salidas completas están en `evidencias/` y el plan en `CE0312-CE0313-Plan-y-resultados-de-pruebas.xlsx`.

*Tabla 4. Pruebas de conectividad, segmentación, recuperación y detección.*

<!-- inicio: pruebas -->

| ID | Tipo | Desde | Hacia | Esperado | Obtenido | Resultado |
| --- | --- | --- | --- | --- | --- | --- |
| PR-01 | Positiva | Internet | https://vegaimplantes.me/ | 200 | 200 con HSTS y cabeceras de seguridad | Cumple |
| PR-02 | Positiva | Internet | /panel/login | 200 y el panel pide MFA | 200; /panel redirige a /panel/login | Cumple |
| PR-03 | Negativa | Internet | IP pública de producción: 22, 3306, 8080 | Filtrados | Filtrados | Cumple |
| PR-04 | Positiva | IP de administración | IP pública de producción: 2200 | Abierto solo para esa IP (NSG) | Abierto | Cumple |
| PR-05 | Negativa | Internet | 250 peticiones seguidas a /panel/login | Respuestas 429 | 170 × 200 y 80 × 429 | Cumple |
| PR-06 | Negativa | Internet | /.env | Bloqueado | 403 | Cumple |
| PR-07 | Positiva | vm-recepcion | Panel por la VPN (DNS interno → 10.20.3.4) | 200 | 200; el dominio resuelve a 10.20.3.4 | Cumple |
| PR-08 | Negativa | vm-recepcion | vm-app 10.20.4.4:80 | Bloqueado | Bloqueado; fw-reenvio-bloqueo en la sede | Cumple |
| PR-09 | Negativa | vm-recepcion | vm-app 10.20.4.4:22 | Bloqueado | Bloqueado | Cumple |
| PR-10 | Negativa | vm-recepcion | vm-wazuh 10.10.50.10:22 | Bloqueado (fw-personal-bloqueo) | Bloqueado; 6 registros fw-personal-bloqueo | Cumple |
| PR-11 | Negativa | vm-recepcion | Administración 10.10.10.4:22 | Bloqueado | Bloqueado | Cumple |
| PR-12 | Positiva | vm-recepcion | vm-wazuh 10.10.50.10:1514 | Permitido (agente) | Abierto | Cumple |
| PR-13 | Positiva | vm-proxy | vm-app 10.20.4.4:80 | Permitido | Abierto | Cumple |
| PR-14 | Negativa | vm-proxy | vm-app 10.20.4.4:22 | Bloqueado | Bloqueado | Cumple |
| PR-15 | Negativa | vm-proxy | vm-recepcion 10.10.30.10:22 | Bloqueado | Bloqueado | Cumple |
| PR-16 | Positiva | vm-proxy | vm-wazuh 10.10.50.10:1514 por la VPN | Permitido | Abierto | Cumple |
| PR-17 | Positiva | vm-app | MySQL 3306 | Permitido con TLS | Abierto; TLS 1.3, TLS_AES_256_GCM_SHA384 | Cumple |
| PR-18 | Positiva | vm-app | vm-wazuh 10.10.50.10:22 (copia horaria) | Permitido | Abierto; copia de las 10:15 recibida | Cumple |
| PR-19 | Negativa | vm-app | vm-recepcion 10.10.30.10:22 | Bloqueado | Bloqueado; 14 registros fw-reenvio-bloqueo en producción | Cumple |
| PR-20 | Positiva | Firewalls | Túnel IPsec | ESTABLISHED e INSTALLED | ESTABLISHED, IKEv2, AES_GCM_16-256 | Cumple |
| PR-21 | Recuperación | Firewall de la sede | Corte del túnel | Vuelve solo en < 60 s | Volvió en 1,4 s | Cumple |
| PR-22 | Detección | Internet | IP pública de la sede: 22 y 3306 | Alerta de Suricata | 13 alertas "ET SCAN Suspicious inbound to mySQL port 3306" | Cumple |
| PR-23 | Detección | Internet | /.env y rutas ocultas | Alerta en Wazuh | 115 alertas "Request to Hidden Environment File" | Cumple |
| PR-24 | Detección | Internet | Honeypot 10.10.60.10 (22 y 3306) | Evento en opencanary.log y alerta en Wazuh | No ejecutada en esta ronda: la VM de laboratorio activa era recepción | Pendiente |

<!-- fin: pruebas -->

**Cobertura de los flujos que pide la rúbrica.** Internet → DMZ (PR-01, PR-02, PR-05); Internet → LAN (PR-03); DMZ → base de datos y aplicación (PR-13, PR-14); usuarios → administración (PR-10, PR-11); administración → servidores (el jump host administra producción por la VPN; `nsg-app` y `nsg-dmz` solo admiten SSH desde 10.10.0.0/23).

# Detección: IDS, WAF y honeypot

## Suricata

*Tabla 5. Firmas con más alertas el 19/11/2026.*

| Firewall | Alertas | Firmas principales |
| --- | --: | --- |
| Producción | 6 360 | 5 123 "STREAM ESTABLISHED packet out of window"; 236 tráfico de APT; **116 "ET INFO Request to Hidden Environment File - Inbound"**; **15 "ET EXPLOIT GraphQL Introspection Query Attempt"** |
| Sede | 4 565 | 4 003 "STREAM ESTABLISHED packet out of window"; 248 tráfico de APT; **13 "ET SCAN Suspicious inbound to mySQL port 3306"**; 12 "ET HUNTING Suspicious NULL DNS Request" |

Las alertas de *stream* (paquetes fuera de ventana o con ACK inválido) suelen ser ruido del IDS en interfaces virtuales y se pueden suprimir con un umbral después de revisarlas. Las marcadas en negrita son **escaneos reales de internet**: bots que buscan archivos `.env` con claves, interfaces GraphQL y servidores MySQL expuestos. Ninguno llegó a un servicio: el firewall de producción solo publica 80 y 443 hacia el proxy, y el 3306 de la sede apunta al honeypot.

## Honeypot

El honeypot (`vm-honeypot`, 10.10.60.10) corre OpenCanary con un SSH falso en el 22 y un MySQL falso en el 3306; el SSH real de la VM pasa al 2222. El firewall de la sede reenvía a él lo que llega de internet a esos dos puertos, y la DMZ no puede iniciar conexiones hacia la clínica ni hacia producción (`fw-dmz-bloqueo`). Cada contacto queda en `/var/log/opencanary.log`, que el agente de Wazuh envía al SIEM.

Por la cuota de 6 vCPU, la sede tiene una VM de laboratorio a la vez. Durante esta ronda estaba activa la de recepción, para las pruebas del panel por la VPN, así que **PR-24 no se ejecutó**: los 13 escaneos al 3306 de la sede llegaron al firewall (Suricata los registró) pero no tenían un señuelo detrás. Para ejecutarla: `bash infra/desplegar.sh sede honeypot`, instalar el agente con `instalar-agentes.sh` y, desde fuera, `nc -v IP_PUBLICA_SEDE 3306`.

**Incidencia corregida.** En la primera versión, el NAT de destino de la sede mandaba al honeypot todo lo que llegaba al puerto 22, incluido el SSH de administración que venía por la VPN. Se corrigió limitando el DNAT a lo que llega a la IP de la WAN (`ip daddr $WAN_IP`), en los dos firewalls.

# Monitoreo y centralización de registros

![](diagramas/monitoreo.png)

*Figura 1. Fuentes de registros, SIEM y alertas.*

*Tabla 6. Agentes de Wazuh (`agent_control -l`).*

| ID | Agente | Estado | Alertas del día |
| --- | --- | --- | --: |
| 000 | vm-wazuh (servidor) | Activo, local | 302 |
| 001 | vm-proxy | Activo | 1 194 |
| 002 | vm-fw-prod | Activo | 469 |
| 003 | vm-fw-sede | Activo | 354 |
| 004 | vm-app | Activo | 827 |
| 005 | vm-recepcion | Activo | 296 |

*Tabla 7. Reglas de Wazuh con más alertas el 19/11/2026.*

| Alertas | Nivel | Regla |
| --: | --: | --- |
| 1 320 | 5 | Web server 400 error code |
| 115 | 3 | Suricata: ET INFO Request to Hidden Environment File - Inbound |
| 82 | 10 | Multiple web server 400 error codes from same source ip |
| 28 | 6 | Suspicious URL access |
| 27 | 3 | PAM: Login session closed |
| 26 | 3 | PAM: Login session opened |
| 10 | 3 | sshd: authentication success |
| 10 | 3 | Suricata: ET EXPLOIT GraphQL Introspection Query Attempt |

Lo que llega al SIEM: registros del sistema y de SSH de todas las VM; bloqueos de nftables (`kern.log`) y alertas de Suricata (`eve.json`) de los dos firewalls; accesos de nginx y la auditoría de ModSecurity del proxy; y los eventos en JSON de Laravel de la aplicación.

**Tablero.** El tablero de Wazuh no está publicado en internet: se abre con un túnel por el firewall de la sede (`ssh -L 8443:10.10.50.10:443 vega-fw-sede`, luego `https://localhost:8443`). Sus vistas de eventos de seguridad, integridad de archivos y vulnerabilidades por agente sirven de tablero de la red.

**Alertas de la nube.** Azure Monitor tiene dos alertas sobre vm-app (VM caída, severidad 1; CPU sobre 80 %, severidad 2) que avisan por correo al equipo.

# Incidencias y limitaciones

| Incidencia | Causa | Solución |
| --- | --- | --- |
| Las subredes internas no salían a internet | Los NSG de la LAN del firewall descartaban el tráfico reenviado | Reglas `Permitir-Salida-Por-Firewall` y `Permitir-Reenvio-Hacia-Adentro` |
| El SSH por la VPN terminaba en el honeypot | DNAT aplicado a todo lo que entraba | DNAT solo para la IP de la WAN |
| El agente de Wazuh instalado en vm-wazuh desinstaló el servidor | Paquetes incompatibles; el agente era de una versión más nueva | `instalar-agentes.sh` omite el servidor y fija la versión 4.12.0-1 en los agentes |
| La web cargaba recursos con `http://` | PHP veía la IP de nginx local, no la del proxy, y no confiaba en `X-Forwarded-Proto` | `fastcgi_param REMOTE_ADDR $realip_remote_addr` en vm-app |
| Puertos de configuración inicial (2222 y 2223) abiertos | DNAT de una etapa temprana | Se quitaron del firewall y del NSG |

**Limitaciones.** Suricata en modo IDS y ModSecurity en modo detección (no bloquean); el honeypot no estuvo activo en esta ronda; cada servicio tiene una sola VM; y la sede es una red de Azure que representa al local de la clínica (S1-E2).

# Referencias

[1] OISF, "Suricata User Guide," 2026. [En línea]. Disponible: https://docs.suricata.io/

[2] strongSwan, "swanctl.conf reference," 2026. [En línea]. Disponible: https://docs.strongswan.org/

[3] Netfilter Project, "nftables wiki," 2026. [En línea]. Disponible: https://wiki.nftables.org/

[4] Thinkst, "OpenCanary documentation," 2026. [En línea]. Disponible: https://opencanary.readthedocs.io/

[5] Wazuh Inc., "Wazuh documentation 4.12," 2025. [En línea]. Disponible: https://documentation.wazuh.com/4.12/

[6] Microsoft, "Network security groups — how network traffic is filtered," Microsoft Learn, 2026.

[7] OWASP, "OWASP ModSecurity Core Rule Set," 2026. [En línea]. Disponible: https://coreruleset.org/
