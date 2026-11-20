# Resumen ejecutivo

**Problema.** Clínica Vega Implant (Juliaca) atiende con un sistema web propio: la página donde los pacientes reservan y el panel donde el personal lleva la agenda, la historia clínica, la caja y las copias de seguridad. Ese sistema guarda datos de salud, que la Ley N.º 29733 trata como datos sensibles. Necesita una red que lo publique en internet sin exponer la base de datos, que una la sede de la clínica con el centro de datos por un canal cifrado y que detecte y registre los intentos de ataque.

**Diseño.** Dos redes unidas por una VPN IPsec: **producción** (`vnet-vega`, 10.20.0.0/16), donde viven el proxy, la aplicación y la base de datos, y la **sede de la clínica** (`vnet-clinica`, 10.10.0.0/16), con sus segmentos de administración, personal, servidores y DMZ. Cada red tiene un firewall propio con dos interfaces (WAN y LAN) que concentra el filtrado entre segmentos, el NAT, la VPN y el IDS. Desde internet solo se llega al firewall de producción, y este solo deja pasar la web (80 y 443) hacia el proxy de la DMZ. El proxy es el único que habla con la aplicación, y la aplicación es la única que habla con la base de datos.

**Seguridad y detección.** Defensa en capas: grupos de seguridad de red (NSG) en cada subred, reglas de nftables en cada firewall con política por defecto *drop*, un WAF (ModSecurity con OWASP CRS) en el proxy, el IDS Suricata en la WAN de los dos firewalls, un honeypot en la DMZ de la sede y el SIEM Wazuh, que recibe los registros de todas las máquinas por la VPN.

**Equivalencia tecnológica.** La guía de la línea propone VMware ESXi para la parte local y AWS para la nube, y admite cambiar la tecnología si se mantiene la equivalencia técnica. El equipo no tiene un servidor ESXi ni crédito de AWS, pero sí Azure for Students. Por eso la sede de la clínica se modela como una segunda red virtual de Azure en otra región, con el mismo direccionamiento, segmentos y firewall que tendría en el local; y la parte de nube usa los servicios equivalentes de Azure (tabla 2).

**Resultado esperado.** Una red que se crea entera desde el repositorio con un comando (`infra/desplegar.sh todo`), con 12 pruebas de conectividad planeadas (sección 8) que en el Semestre 2 se ejecutan y comparan con este diseño.

# Contexto y análisis de necesidades

## Organización y procesos críticos

La clínica tiene un doctor que también es el administrador (el Dr. Vega), una recepcionista y pacientes que reservan desde el celular. Los procesos que dependen de la red son estos:

*Tabla 1. Procesos críticos y lo que piden a la red.*

| Proceso | Quién lo usa | Qué necesita de la red | Impacto si falla |
| --- | --- | --- | --- |
| Reserva en línea y confirmación por WhatsApp | Pacientes, desde internet | Web pública por HTTPS, disponible todo el día | Se pierden citas nuevas |
| Agenda, recepción y caja | Recepción, en la sede o fuera | Panel por HTTPS, con autenticación fuerte | La clínica vuelve al cuaderno |
| Historia clínica, odontograma y radiografías | Doctor | Panel, archivos de hasta 25 MB | No se puede atender con historia |
| Copias de seguridad | Sistema, automático | Canal privado de producción a la sede | Riesgo de perder datos de salud |
| Administración de servidores | Equipo técnico | Acceso restringido, sin exponer SSH a internet | No se puede corregir un incidente |
| Monitoreo y alertas | Equipo técnico | Registros de todas las máquinas en un SIEM | Ataques sin detectar |

## Partes interesadas

| Interesado | Interés en la red |
| --- | --- |
| Dr. Vega (dueño y administrador) | Que el sistema esté disponible y que los datos de sus pacientes no se filtren |
| Recepcionista | Entrar al panel desde la clínica o desde casa sin pasos complicados |
| Pacientes | Reservar desde el celular; que su historia sea confidencial |
| Equipo de infraestructura (CE03) | Una red reproducible, segmentada y observable |
| Equipo de software (CE02) | Un entorno de producción donde publicar sin pasos manuales |
| Docentes evaluadores | Evidencia verificable y sin datos personales reales |

## Alcance

**Dentro del alcance:** la red de producción, la red de la sede, la VPN entre ambas, la seguridad perimetral (firewalls, NSG, DMZ, WAF), la detección (IDS, honeypot, SIEM) y el plan de pruebas de conectividad.

**Fuera del alcance:** el cableado físico del local, la red Wi-Fi de pacientes y los equipos de rayos X, que no se conectan al sistema. La sede se modela en Azure (sección 1); en un local real, su firewall sería un equipo físico con la misma configuración.

## Objetivos medibles

| N.º | Objetivo | Indicador | Meta |
| --: | --- | --- | --- |
| O1 | Exponer a internet solo lo necesario | Puertos abiertos en la IP pública de producción | Solo 80, 443 y la VPN (500/4500 UDP); SSH solo desde la IP de administración |
| O2 | Aislar la base de datos | Subredes que llegan a MySQL | Solo `snet-app`; MySQL sin IP pública |
| O3 | Cifrar todo lo que cruza internet | Tráfico en claro entre sede y producción | 0: todo por IPsec con AES-256-GCM |
| O4 | Segmentar la sede | Pruebas negativas entre segmentos que se bloquean | 100 % de las planeadas |
| O5 | Detectar | Fuentes que envían registros al SIEM | Todas las VM y los dos firewalls |
| O6 | Recuperar la VPN sola | Tiempo de reconexión tras un corte | Menos de 60 s |

# Equivalencia tecnológica

*Tabla 2. Equivalencia entre la tecnología de la guía y la usada en el proyecto.*

| Guía de la línea | En este proyecto | Por qué es equivalente |
| --- | --- | --- |
| Red local en VMware ESXi | `vnet-clinica` en Azure North Central US | Mismos segmentos, direccionamiento y firewall; cambia el hipervisor, no el diseño |
| VLAN y *port groups* | Subredes de la VNet con su NSG y su ruta | Cada subred es un dominio aislado que solo se comunica a través del firewall |
| AWS VPC | Azure VNet `vnet-vega` | Red privada con subredes y tablas de rutas |
| Security Groups | NSG por subred | Filtro con estado en cada subred |
| NACL | nftables en el firewall (política *drop*) | Segunda capa de filtrado entre segmentos |
| Internet Gateway y NAT Gateway | IP pública y NAT del firewall (`masquerade`) | Salida y entrada controladas por un solo punto |
| VPN Gateway | strongSwan en cada firewall (IKEv2) | VPN IPsec sitio a sitio |
| Firewall perimetral (OPNsense en el diseño original) | Firewall Linux: nftables, strongSwan, Suricata y Unbound | Mismas funciones, configuradas por código (cloud-init) |
| CloudWatch | Azure Monitor y Log Analytics | Métricas y alertas de la nube |

# Topología

## Topología general

![](diagramas/topologia-general.png)

*Figura 1. Topología general: producción y sede unidas por la VPN IPsec.*

El tráfico de internet entra solo por el firewall de producción. La sede se une a producción por la VPN, y desde ella el personal llega al panel, el SIEM recibe los registros y el servidor de respaldo recibe la copia horaria de la base de datos.

## Topología lógica: segmentos y direccionamiento

![](diagramas/segmentacion.png)

*Figura 2. Segmentos de cada red y flujos permitidos entre ellos.*

*Tabla 3. Plan de direccionamiento.*

| Red | Segmento (equivale a VLAN) | Prefijo | Puerta de enlace | Equipos |
| --- | --- | --- | --- | --- |
| Producción | `snet-wan` | 10.20.0.0/24 | Azure | vm-fw WAN 10.20.0.4 + IP pública |
| Producción | `snet-lan` | 10.20.1.0/24 | Azure | vm-fw LAN 10.20.1.4 |
| Producción | `snet-dmz` | 10.20.3.0/24 | 10.20.1.4 (firewall) | vm-proxy 10.20.3.4 |
| Producción | `snet-app` | 10.20.4.0/24 | 10.20.1.4 (firewall) | vm-app 10.20.4.4 |
| Producción | `snet-mysql` (delegada) | 10.20.5.0/24 | Local a la VNet | MySQL Flexible |
| Sede | `snet-wan` | 10.10.0.0/24 | Azure | vm-fw WAN 10.10.0.4 + IP pública |
| Sede | `snet-lan` | 10.10.1.0/24 | Azure | vm-fw LAN 10.10.1.4 |
| Sede | `snet-mgmt` (VLAN 10) | 10.10.10.0/24 | 10.10.1.4 | Administración |
| Sede | `snet-personal` (VLAN 30) | 10.10.30.0/24 | 10.10.1.4 | vm-recepcion 10.10.30.10 |
| Sede | `snet-servidores` (VLAN 50) | 10.10.50.0/24 | 10.10.1.4 | vm-wazuh 10.10.50.10 |
| Sede | `snet-dmz` (VLAN 60) | 10.10.60.0/24 | 10.10.1.4 | vm-honeypot 10.10.60.10 |

Los prefijos /16 dejan espacio para crecer sin cambiar el plan, y los dos rangos no se solapan, condición necesaria para la VPN.

## Ruteo

Cada subred interna tiene una tabla de rutas (`rt-via-firewall`) cuyo siguiente salto es la LAN del firewall (tipo *VirtualAppliance*):

| Destino | Siguiente salto | Para qué |
| --- | --- | --- |
| 0.0.0.0/0 | LAN del firewall | Toda salida a internet pasa por el firewall y su NAT |
| La propia red (/16) | LAN del firewall | Obliga a que el tráfico entre subredes pase por nftables |
| La otra red (/16) | LAN del firewall | El tráfico hacia la otra sede entra a la VPN |
| 10.20.5.0/24 (MySQL) | Local a la VNet | La base de datos responde directo a la aplicación |

Las interfaces del firewall tienen activado el reenvío IP en Azure y en el sistema (`net.ipv4.ip_forward=1`).

# Seguridad perimetral

## Firewall de cada red

El firewall es una VM Ubuntu 24.04 con dos interfaces. Sus reglas de nftables se guardan en el repositorio (`infra/firewall/produccion.nft` y `sede.nft`), tienen política *drop* en las cadenas de entrada y reenvío, y registran cada bloqueo con un prefijo (`fw-entrada-bloqueo`, `fw-reenvio-bloqueo`, `fw-dmz-bloqueo`, `fw-personal-bloqueo`) para que el SIEM los cuente.

*Tabla 4. Reglas de reenvío planeadas en el firewall de producción.*

| Origen | Destino | Puertos | Acción |
| --- | --- | --- | --- |
| Internet (WAN) | Proxy 10.20.3.4 (tras DNAT) | TCP 80, 443 | Permitir |
| Proxy | App 10.20.4.4 | TCP 80 | Permitir |
| Sede 10.10.0.0/16 (VPN) | Proxy | TCP 80, 443 | Permitir |
| Administración de la sede 10.10.0.0/23 | Proxy y app | TCP 22 | Permitir |
| App y proxy | Wazuh 10.10.50.10 | TCP 1514, 1515 | Permitir |
| App | Respaldo 10.10.50.10 | TCP 22 | Permitir |
| DMZ y app | Internet | TCP 80, 443, 587; UDP 123 | Permitir |
| Cualquier otro | Cualquier otro | Todos | Bloquear y registrar |

*Tabla 5. Reglas de reenvío planeadas en el firewall de la sede.*

| Origen | Destino | Puertos | Acción |
| --- | --- | --- | --- |
| Internet (WAN) | Honeypot 10.10.60.10 (tras DNAT) | TCP 22, 3306 | Permitir (señuelo) |
| DMZ | Wazuh | TCP 1514, 1515 | Permitir |
| DMZ | Sede o producción | Todos | Bloquear y registrar (`fw-dmz-bloqueo`) |
| Personal | Proxy de producción | TCP 80, 443 | Permitir |
| Personal | Administración o servidores | Todos | Bloquear y registrar (`fw-personal-bloqueo`) |
| Administración | Todo | Todos | Permitir |
| Producción (VPN) | Wazuh | TCP 1514, 1515, 22; UDP 514 | Permitir |
| Cualquier otro | Cualquier otro | Todos | Bloquear y registrar |

## DMZ y proxy inverso

La DMZ de producción tiene un solo equipo, el proxy inverso (nginx). Termina el HTTPS con un certificado de Let's Encrypt, filtra con ModSecurity y el OWASP Core Rule Set, limita las peticiones por IP al panel y reenvía a la aplicación. Si el proxy fuera comprometido, el firewall solo le deja llegar al puerto 80 de la aplicación y a los puertos de registro del SIEM.

## Segunda capa: grupos de seguridad de red

Cada subred tiene un NSG que repite lo esencial de las reglas del firewall: aunque una regla de nftables fallara, el NSG seguiría bloqueando. Por ejemplo, `nsg-app` solo admite el puerto 80 desde el proxy y el 22 desde la administración de la sede, y niega cualquier otro 80 o 443; `nsg-wan` solo deja pasar la web, la VPN y el SSH del firewall desde la IP de administración.

## Administración sin exponer servidores

Ninguna VM interna tiene SSH desde internet. El firewall de la sede hace de *jump host*: se entra a él por el puerto 2200 (solo desde la IP de administración) y desde él a las demás VM, también a las de producción por la VPN. Como alternativa sin abrir puertos, Azure permite ejecutar comandos con `az vm run-command`.

# Conectividad híbrida: VPN IPsec

*Tabla 6. Parámetros de la VPN entre los dos firewalls.*

| Parámetro | Valor |
| --- | --- |
| Tipo | Sitio a sitio, IPsec en modo túnel |
| Software | strongSwan (`swanctl`) en cada firewall |
| Negociación | IKEv2, autenticación con clave compartida (guardada en Key Vault como `vpn-psk`) |
| Cifrado | AES-256-GCM para IKE y ESP, PRF HMAC-SHA2-256, grupo DH MODP-2048 |
| Encapsulado | NAT-T (UDP 4500), porque Azure hace NAT a la IP pública |
| Redes protegidas | 10.20.0.0/16 ↔ 10.10.0.0/16 |
| Recuperación | `dpd_delay = 30s`, `dpd_action = restart`, `close_action = start`, `start_action = start` |

El firewall de producción aplica el NAT de destino solo a lo que llega a su IP de WAN (`ip daddr $WAN_IP`), no a lo que llega por la VPN. Así, una conexión de la sede al puerto 22 de producción no termina en otro equipo por error, y lo mismo pasa en la sede con el honeypot.

# Flujo de información planeado

![](diagramas/flujo-informacion.png)

*Figura 3. Flujo de una petición web y de los registros hacia el SIEM.*

1. El paciente o el personal abre `https://vegaimplantes.me`. El DNS público apunta a la IP del firewall de producción.
2. El firewall traduce el destino al proxy (DNAT) y deja pasar solo los puertos 80 y 443.
3. El proxy termina el TLS, aplica el WAF y el límite de peticiones, y reenvía a la aplicación con la IP real del cliente en `X-Forwarded-For`.
4. La aplicación consulta MySQL por TLS con un usuario de mínimo privilegio y lee sus secretos de Key Vault con su identidad administrada.
5. Los agentes envían los registros a Wazuh por la VPN, y la aplicación envía cada hora una copia de la base de datos a la sede.

En la sede, el DNS interno (Unbound en el firewall) resuelve el dominio a la IP privada del proxy, de modo que el personal de la clínica entra al panel por la VPN sin salir a internet.

# Matriz de componentes de red

*Tabla 7. Componentes de red, función y configuración.*

| Componente | Red | Función | Configuración | Dónde está el código |
| --- | --- | --- | --- | --- |
| vm-fw producción | Producción | Firewall, NAT, VPN, IDS | 2 NIC, nftables, strongSwan, Suricata | `infra/cloud-init/firewall.yaml`, `infra/firewall/produccion.nft` |
| vm-fw sede | Sede | Firewall, NAT, VPN, IDS, DNS, jump host | 2 NIC, nftables, strongSwan, Suricata, Unbound | `infra/firewall/sede.nft` |
| vm-proxy | Producción, DMZ | Proxy inverso, WAF, TLS | nginx, ModSecurity CRS, certbot | `infra/nginx/proxy.conf` |
| vm-app | Producción, app | Servidor web de la aplicación | nginx, PHP-FPM 8.3 | `infra/nginx/vegaimplant.conf` |
| MySQL Flexible | Producción, delegada | Base de datos | Sin acceso público, TLS obligatorio | `infra/azure/prod/main.bicep` |
| vm-wazuh | Sede, servidores | SIEM y respaldo | Wazuh 4.12 todo en uno | `infra/cloud-init/wazuh.yaml` |
| vm-honeypot | Sede, DMZ | Señuelo SSH y MySQL | OpenCanary | `infra/cloud-init/honeypot.yaml` |
| vm-recepcion | Sede, personal | Puesto de la recepción | Ubuntu, navegador | `infra/cloud-init/base.yaml` |
| NSG (7) | Ambas | Filtro por subred | Reglas mínimas | `infra/azure/*/main.bicep` |
| Tablas de rutas (2) | Ambas | Forzar el paso por el firewall | Siguiente salto *VirtualAppliance* | `infra/azure/*/main.bicep` |

# Diseño de la detección

| Capa | Herramienta | Qué detecta | Adónde envía |
| --- | --- | --- | --- |
| Perímetro | Suricata (reglas ET Open) en la WAN de cada firewall | Escaneos, exploits conocidos, protocolos anómalos | `eve.json` → Wazuh |
| Filtrado | nftables con `log prefix` | Conexiones bloqueadas entre segmentos | Registro del kernel → Wazuh |
| Aplicación web | ModSecurity + OWASP CRS en el proxy | Inyección SQL, XSS, rutas sospechosas | `modsec_audit.log` → Wazuh |
| Engaño | Honeypot OpenCanary en la DMZ de la sede, con los puertos 22 y 3306 publicados | Cualquier contacto (nadie legítimo lo usa) | `opencanary.log` → Wazuh |
| Correlación | Wazuh (manager, indexer, dashboard) | Reglas de nivel alto, varios errores desde una IP, inicios de sesión | Panel de Wazuh |
| Nube | Azure Monitor | VM caída, CPU alta | Correo al equipo |

Suricata empieza en modo IDS (detecta y registra). El paso a IPS (modo NFQUEUE) se planea para la fase de pruebas, una vez medidos los falsos positivos.

# Plan de pruebas de conectividad

*Tabla 8. Pruebas planeadas: se ejecutan en el Semestre 2 (S2-E2).*

| ID | Desde | Hacia | Esperado |
| --- | --- | --- | --- |
| PC-01 | Internet | `https://vegaimplantes.me/` | 200 |
| PC-02 | Internet | IP pública de producción, puertos 22, 3306, 8080 | Filtrados |
| PC-03 | Internet | IP pública de la sede, 22 y 3306 | Responden (honeypot) y generan alerta |
| PC-04 | Recepción | Panel por la VPN (DNS interno) | 200 |
| PC-05 | Recepción | Wazuh 10.10.50.10:22 | Bloqueado (`fw-personal-bloqueo`) |
| PC-06 | Recepción | App 10.20.4.4:80 directo | Bloqueado |
| PC-07 | Honeypot | Recepción 10.10.30.10:22 | Bloqueado (`fw-dmz-bloqueo`) |
| PC-08 | Proxy | App 10.20.4.4:22 | Bloqueado |
| PC-09 | Proxy | App 10.20.4.4:80 | Permitido |
| PC-10 | App | MySQL 3306 | Permitido, con TLS |
| PC-11 | App | Sede 10.10.50.10:22 (copia) | Permitido por la VPN |
| PC-12 | Firewall de la sede | Corte de la VPN | Se restablece sola en menos de 60 s |

# Escalabilidad y hoja de ruta

**Escalabilidad.** Las redes /16 admiten 256 subredes /24. Una segunda aplicación se agrega en `snet-app` detrás del mismo proxy (un `upstream` con varios servidores). Si el firewall Linux se queda corto, se cambia el tamaño de la VM (de 1 a 4 GiB) sin cambiar las reglas; si el tráfico crece mucho, el diseño admite reemplazarlo por Azure Firewall con las mismas reglas. Una segunda sede se suma con otro prefijo (por ejemplo, 10.11.0.0/16) y otro túnel desde el firewall de producción.

*Tabla 9. Hoja de ruta.*

| Fase | Qué | Cuándo |
| --- | --- | --- |
| 1 | Diseño de red y de centro de datos (este documento y S1-E2) | Semestre 1 |
| 2 | Infraestructura como código: Bicep, cloud-init y reglas de nftables | Inicio del Semestre 2 |
| 3 | Despliegue de producción, sede y VPN; pruebas de conectividad | Semestre 2 |
| 4 | SIEM, agentes, honeypot y pruebas negativas | Semestre 2 |
| 5 | Dominio con HTTPS y panel con MFA | Semestre 2 |
| 6 | Suricata en modo IPS y WAF en modo bloqueo | Tras medir falsos positivos |

# Referencias

[1] IEEE, "IEEE Standard for Ethernet — Amendment: Media Access Control (MAC) Bridges and Virtual Bridged LANs," IEEE Std 802.1Q-2022, 2022.

[2] C. Kaufman, P. Hoffman, Y. Nir, P. Eronen y T. Kivinen, "Internet Key Exchange Protocol Version 2 (IKEv2)," RFC 7296, IETF, oct. 2014.

[3] S. Kent y K. Seo, "Security Architecture for the Internet Protocol," RFC 4301, IETF, dic. 2005.

[4] Microsoft, "Azure Virtual Network documentation," Microsoft Learn, 2026. [En línea]. Disponible: https://learn.microsoft.com/azure/virtual-network/

[5] OISF, "Suricata User Guide," 2026. [En línea]. Disponible: https://docs.suricata.io/

[6] OWASP, "OWASP ModSecurity Core Rule Set," 2026. [En línea]. Disponible: https://coreruleset.org/

[7] Wazuh Inc., "Wazuh documentation 4.12," 2025. [En línea]. Disponible: https://documentation.wazuh.com/4.12/

[8] Congreso de la República del Perú, "Ley N.º 29733, Ley de Protección de Datos Personales," 2011.
