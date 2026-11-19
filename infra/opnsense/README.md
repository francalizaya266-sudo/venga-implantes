# OPNsense: producción y sede de la clínica

Hay dos OPNsense, uno en cada red, y la VPN IPsec va entre ellos (como dice el E1, figura 13):

| | OPNsense de producción | OPNsense de la sede |
| --- | --- | --- |
| Red | `vnet-vega` 10.20.0.0/16 | `vnet-clinica` 10.10.0.0/16 |
| Grupo de recursos | `rg-vega-prod` | `rg-vega-clinica` |
| IP LAN | 10.20.1.4 | 10.10.1.4 |
| Papel | Única entrada desde internet: reenvía 80 y 443 al proxy de la DMZ; Suricata | Firewall entre las subredes de la sede; DNS interno; Suricata |
| Encendido | Siempre | Solo en las sesiones de laboratorio |

## 1. Instalar cada uno

Azure no trae una imagen oficial de OPNsense. La forma probada es la plantilla comunitaria **opnazure**
(github.com/dmauser/opnazure), que crea la VM sobre FreeBSD, instala OPNsense y le deja dos tarjetas de red.

1. Despliega primero la red de ese lado con `DEPLOY_VMS=false` (ver `infra/README.md`).
2. En el repositorio opnazure, usa **Deploy to Azure** en el escenario de **dos tarjetas de red (Two NICs) en una
   red existente**. Revisa allí sus requisitos actuales.

   | Campo | Producción | Sede |
   | --- | --- | --- |
   | Grupo de recursos | `rg-vega-prod` | `rg-vega-clinica` |
   | Red virtual existente | `vnet-vega` | `vnet-clinica` |
   | Subred no confiable (WAN) | `snet-wan` | `snet-wan` |
   | Subred confiable (LAN) | `snet-lan` | `snet-lan` |
   | Tamaño | `Standard_B2ats_v2` (sube a `Standard_B2als_v2` si Suricata en IPS se queda corto de memoria) | `Standard_B2ats_v2` |
   | Usuario y contraseña | Nuevos, en **1Password** | Nuevos, en **1Password** |

3. En el portal: **VM de OPNsense → Redes → tarjeta LAN → Configuraciones de IP** y fija la IP privada
   (10.20.1.4 o 10.10.1.4) como estática. Es el siguiente salto de las tablas de rutas.
4. Comprueba que las dos tarjetas tengan **Reenvío IP habilitado**. opnazure lo activa; si no, actívalo.
5. Si opnazure asoció sus propios NSG a `snet-wan` o `snet-lan`, el siguiente despliegue de Bicep los vuelve a
   reemplazar por los del repositorio. Es lo esperado.
6. **Sistema → Ajustes → Administración**: cambia el puerto del panel web a **8443** (en producción el 443 es
   para la web). El NSG solo deja entrar a tu IP: `https://IP-PUBLICA-WAN:8443`.
7. **Sistema → Rutas → Configuración**: rutas estáticas por la puerta de enlace de la LAN (la .1 de `snet-lan`,
   el router de Azure):

   | Producción | Sede |
   | --- | --- |
   | 10.20.3.0/24 (dmz) y 10.20.4.0/24 (app) vía 10.20.1.1 | 10.10.10.0/24, 10.10.30.0/24, 10.10.50.0/24 y 10.10.60.0/24 vía 10.10.1.1 |

## 2. OPNsense de producción

### NAT (Firewall → NAT → Reenvío de puertos, interfaz WAN)

| Puerto WAN | Destino | Para qué |
| --- | --- | --- |
| 443 | 10.20.3.4:443 (vm-proxy) | La web y la API |
| 80 | 10.20.3.4:80 (vm-proxy) | Redirige a HTTPS y renueva el certificado |
| 2222 | 10.20.4.4:22 (vm-app) | SSH solo mientras instalas, antes de la VPN (el NSG lo limita a tu IP) |
| 2223 | 10.20.3.4:22 (vm-proxy) | Igual |

Al terminar la fase de la VPN, borra los reenvíos 2222 y 2223.

### Reglas

Alias: `DMZ` 10.20.3.0/24, `APP` 10.20.4.0/24, `PROXY` 10.20.3.4, `VM_APP` 10.20.4.4, `SEDE` 10.10.0.0/16,
`WAZUH` 10.10.50.10, `RESPALDO` 10.10.50.20.

| Pestaña | Acción | Origen | Destino | Puerto | Para qué |
| --- | --- | --- | --- | --- | --- |
| WAN | Permitir | cualquiera | PROXY | 80, 443 | Las que crea el NAT |
| LAN | Permitir | PROXY | VM_APP | 80 | El proxy pasa a la aplicación |
| LAN | Permitir | VM_APP | WAZUH | 1514-1515 | Logs al SIEM de la sede |
| LAN | Permitir | VM_APP | RESPALDO | 22 | Copia horaria a la sede |
| LAN | Permitir | DMZ, APP | internet | 80, 443 | Actualizaciones, Key Vault, Blob, WhatsApp, correo (587) |
| LAN | Bloquear | DMZ | APP | todos salvo lo de arriba | Si el proxy cae, no llega a nada más |
| LAN | Bloquear | cualquiera | cualquiera | todos | Todo lo demás, con registro |
| IPsec | Permitir | SEDE | PROXY | 443 | El personal usa el panel |
| IPsec | Permitir | 10.10.10.0/24 | PROXY, VM_APP | 22 | Administración desde el jump host |
| IPsec | Bloquear | SEDE | cualquiera | todos | Todo lo demás, con registro |

Agrega también **Permitir LAN → internet 587** para el correo de Brevo.

## 3. OPNsense de la sede

### NAT (interfaz WAN)

| Puerto WAN | Destino | Para qué |
| --- | --- | --- |
| 2222 | 10.10.10.20:22 (vm-jump) | Tu entrada SSH, solo desde tu IP (lo filtra el NSG) |
| 22 | 10.10.60.10:22 (honeypot) | Señuelo |
| 3306 | 10.10.60.10:3306 (honeypot) | Señuelo |

Desactiva el SSH propio de OPNsense en la WAN para que el 22 vaya al honeypot.

### Reglas

Alias: `MGMT` 10.10.10.0/24, `PERSONAL` 10.10.30.0/24, `SERVIDORES` 10.10.50.0/24, `DMZ` 10.10.60.0/24,
`PRODUCCION` 10.20.0.0/16, `PROXY_PROD` 10.20.3.4, `VM_APP` 10.20.4.4, `WAZUH` 10.10.50.10, `RESPALDO` 10.10.50.20.

| Pestaña | Acción | Origen | Destino | Puerto | Para qué |
| --- | --- | --- | --- | --- | --- |
| LAN | Permitir | DMZ | WAZUH | 1514-1515 | Logs del honeypot al SIEM |
| LAN | Bloquear | DMZ | cualquiera interna y PRODUCCION | todos | La DMZ no entra a la red |
| LAN | Permitir | PERSONAL | PROXY_PROD | 443 | Recepción usa el panel por la VPN |
| LAN | Bloquear | PERSONAL | MGMT, SERVIDORES | todos | Recepción no administra servidores |
| LAN | Permitir | MGMT | cualquiera | todos | El jump host administra todo |
| LAN | Permitir | SERVIDORES | WAZUH | 1514-1515 | Logs de las VM al SIEM |
| LAN | Permitir | interna | internet | 80, 443 | Actualizaciones |
| LAN | Bloquear | cualquiera | cualquiera | todos | Todo lo demás, con registro |
| IPsec | Permitir | VM_APP | WAZUH | 1514-1515 | Logs de producción |
| IPsec | Permitir | VM_APP | RESPALDO | 22 | Copia horaria |
| IPsec | Bloquear | PRODUCCION | cualquiera | todos | Todo lo demás, con registro |

Activa **Registrar** en las reglas de bloqueo: son la evidencia de las pruebas negativas.

### DNS para que el personal llegue al panel

La segunda pasada de `infra/azure/clinica` (`DEPLOY_VMS=true`) hace que las VM de la sede usen a 10.10.1.4 como DNS.

1. **Servicios → Unbound DNS → General**: actívalo, escuchando en LAN.
2. **Listas de acceso**: permite 10.10.0.0/16.
3. **Consulta por reenvío**: reenvía a `168.63.129.16`, el DNS de Azure.
4. **Overrides**: `vegaimplant.tech` y `www.vegaimplant.tech` → `10.20.3.4` (el proxy, por la VPN).

Así, desde la sede, el dominio va por la VPN al proxy, que deja pasar `/panel` porque el origen es 10.10.x.x.
Desde internet, el mismo dominio llega por la IP pública y `/panel` responde 403.

## 4. VPN IPsec entre los dos

En cada OPNsense, **VPN → IPsec → Conexiones**, con la misma clave precompartida (generada con
`openssl rand -base64 32` y guardada en 1Password):

| Campo | En producción | En la sede |
| --- | --- | --- |
| Versión | IKEv2 | IKEv2 |
| Dirección remota | IP pública WAN de la sede | IP pública WAN de producción |
| Identificador local | Su propia IP pública WAN | Su propia IP pública WAN |
| Red local | 10.20.0.0/16 | 10.10.0.0/16 |
| Red remota | 10.10.0.0/16 | 10.20.0.0/16 |
| Fase 1 | AES-256-GCM, SHA-256, grupo DH 14 | Igual |
| Fase 2 | AES-256-GCM, PFS grupo 14 | Igual |

Las dos VM están detrás del NAT de Azure: deja activado **NAT Traversal** (UDP 4500). Comprueba el túnel en
**VPN → IPsec → Estado** de los dos lados. Como la sede se apaga cada noche, el túnel cae y se vuelve a levantar
solo al encenderla.

## 5. Suricata (IDS/IPS)

**Servicios → Detección de intrusos → Administración**: en producción, en WAN; en la sede, en WAN y en la DMZ.
Descarga ET Open. Empieza en modo detección (IDS) para la evidencia y pasa a IPS cuando hayas visto las alertas.

## 6. Logs hacia Wazuh

En los dos: **Sistema → Ajustes → Registro → Destinos remotos**: `10.10.50.10`, UDP 514, aplicaciones *filter* y
*suricata*. En producción esto viaja por la VPN: agrega en la pestaña IPsec de la sede **Permitir PRODUCCION →
WAZUH UDP 514**.

En vm-wazuh, agrega a `/var/ossec/etc/ossec.conf` y reinicia `wazuh-manager`:

```xml
<remote>
  <connection>syslog</connection>
  <port>514</port>
  <protocol>udp</protocol>
  <allowed-ips>10.10.1.0/24</allowed-ips>
  <allowed-ips>10.20.1.0/24</allowed-ips>
</remote>
```

## 7. Copia de la configuración

**Sistema → Configuración → Copias de seguridad → Descargar**, de cada uno. Antes de subirla al repositorio o a
un informe, quita contraseñas, claves y la clave de la VPN: la rúbrica no permite secretos en la evidencia.
