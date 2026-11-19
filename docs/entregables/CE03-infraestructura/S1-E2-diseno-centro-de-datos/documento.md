# Resumen ejecutivo

**Propósito.** Diseñar el centro de datos híbrido que aloja el sistema de Clínica Vega Implant: una parte **en la nube**, donde corren la web, el panel y la base de datos, y una parte **en la sede de la clínica**, donde están el puesto de recepción, el SIEM y una copia de los datos que no depende de la nube.

**Arquitectura.** La nube es Microsoft Azure en la región Chile Central (la más cercana a Juliaca), con tres máquinas virtuales (firewall, proxy y aplicación), una base de datos MySQL administrada sin acceso público, un almacén de secretos (Key Vault), un almacenamiento de objetos para las copias (Blob) y Azure Monitor. La sede se diseña como un pequeño centro de datos local virtualizado: un firewall, un conmutador con cuatro VLAN y un host de virtualización con el SIEM, el servidor de respaldo y el honeypot. En este proyecto la sede se implementa como una segunda red de Azure en North Central US que replica ese diseño (tabla 2 del S1-E1), y la sección 4 deja el diseño físico de referencia para cuando la clínica lo instale en su local.

**Integración.** Las dos partes se unen con una VPN IPsec sitio a sitio entre sus firewalls (AES-256-GCM, IKEv2), que lleva los registros al SIEM, la copia horaria de la base de datos y el acceso del personal y del equipo técnico.

**Capacidad y continuidad.** Seis máquinas de 2 vCPU caben en la cuota de Azure for Students (6 vCPU por región) con un margen de crecimiento medido. La continuidad se apoya en tres copias independientes: las automáticas de MySQL (7 días, restauración a un punto en el tiempo), la diaria en Blob Storage y la horaria en la sede. Objetivos preliminares: **RPO de 1 hora y RTO de 2 horas** para perder la aplicación entera, y **5 minutos** para volver a la versión anterior.

# Objetivos y alcance

## Objetivos medibles

*Tabla 1. Objetivos del centro de datos.*

| N.º | Objetivo | Indicador | Meta preliminar |
| --: | --- | --- | --- |
| OC1 | Recuperar los datos tras una pérdida | RPO | 1 h (copia horaria a la sede); minutos con la restauración de MySQL |
| OC2 | Volver a dar servicio tras perder la aplicación | RTO | 2 h, reconstruyendo desde el código |
| OC3 | Volver atrás un cambio que falla | Tiempo para dejar activa la versión anterior | Menos de 5 min |
| OC4 | Crear el centro de datos sin pasos manuales | Recursos creados por código | 100 % (Bicep y cloud-init) |
| OC5 | Proteger los secretos | Secretos en archivos del repositorio | 0: todos en Key Vault |
| OC6 | Gastar dentro del crédito estudiantil | Gasto mensual | Menos de 40 USD, con aviso al 50 % y 80 % |
| OC7 | Ver el estado de la plataforma | Máquinas con agente de monitoreo | Todas |

## Alcance

**Dentro:** la red, el cómputo, el almacenamiento, la base de datos, los secretos, las identidades, la VPN, el monitoreo y las copias, en la nube y en la sede.

**Fuera:** los equipos de rayos X y su software, que no se integran; las estaciones de trabajo físicas, salvo el puesto de recepción como referencia; y la red Wi-Fi de pacientes.

# Arquitectura en la nube

![](diagramas/centro-de-datos.png)

*Figura 1. Recursos de Azure por grupo de recursos, identidades y permisos.*

## Región, red y subredes

| Elemento | Diseño | Equivalente en AWS |
| --- | --- | --- |
| Región | Chile Central (`chilecentral`) por latencia a Perú | Región |
| Red virtual | `vnet-vega`, 10.20.0.0/16 | VPC con su CIDR |
| Subredes | WAN, LAN, DMZ, app y MySQL (delegada), cada una /24 | Subredes públicas y privadas |
| Tabla de rutas | `rt-via-firewall`: 0.0.0.0/0 y las dos /16 hacia la LAN del firewall | Route table |
| Entrada y salida a internet | IP pública estática en la WAN del firewall, con DNAT y NAT | Internet Gateway y NAT Gateway |
| VPN | strongSwan en el firewall | VPN Gateway |
| Filtro por subred | 4 NSG: `nsg-wan`, `nsg-lan`, `nsg-dmz`, `nsg-app` | Security Groups |
| Filtro entre subredes | nftables en el firewall, política *drop* | NACL |

## Cómputo

*Tabla 2. Máquinas virtuales de producción.*

| VM | Subred e IP | Tamaño | vCPU | RAM | Disco | Software |
| --- | --- | --- | --: | --: | --: | --- |
| vm-fw | WAN 10.20.0.4, LAN 10.20.1.4 | Standard_B2ats_v2 | 2 | 1 GiB | 30 GB | nftables, strongSwan, Suricata |
| vm-proxy | DMZ 10.20.3.4 | Standard_B2ats_v2 | 2 | 1 GiB + 2 GiB de swap | 30 GB | nginx, ModSecurity, certbot |
| vm-app | App 10.20.4.4 | Standard_B2ats_v2 | 2 | 1 GiB + 2 GiB de swap | 30 GB | nginx, PHP-FPM 8.3, Composer, azcopy |

Todas usan Ubuntu Server 24.04 LTS, se crean con un archivo de cloud-init que instala su software al nacer y solo aceptan llaves SSH (sin contraseña). La serie B (con créditos de CPU) se elige porque la carga de una clínica es baja y con picos cortos.

## Datos, secretos y almacenamiento

| Servicio | Diseño | Por qué |
| --- | --- | --- |
| Azure Database for MySQL Flexible | 8.0, Burstable B1ms, 32 GB, copias automáticas 7 días, solo acceso privado (subred delegada y DNS privado), TLS obligatorio | La base de datos queda fuera de internet y Azure se encarga de parches y copias |
| Key Vault | Autorización por RBAC, borrado suave de 7 días | Los secretos no viven en archivos; la VM los lee con su identidad |
| Blob Storage | Solo HTTPS y TLS 1.2+, sin acceso público, versiones y borrado suave de 14 días | Copias diarias y paquetes de despliegue, a prueba de borrados accidentales |
| Log Analytics y Azure Monitor | Alerta de VM caída y de CPU sobre 80 %, aviso por correo | Visibilidad desde fuera de las VM |
| Presupuesto | 40 USD con avisos al 50 % y 80 % | Control del crédito estudiantil |

## Identidades

- **vm-app** tiene una identidad administrada con dos permisos: leer secretos de Key Vault (*Key Vault Secrets User*) y escribir copias en Blob (*Storage Blob Data Contributor*). No guarda claves de la nube en disco.
- **GitHub Actions** entra con una identidad administrada (`id-github-despliegue`) federada por OIDC: Azure solo acepta tokens firmados por GitHub para la rama `main` del repositorio. Tiene *Virtual Machine Contributor* en el grupo de producción y escritura en Blob para subir los paquetes.
- **El operador** que despliega recibe *Key Vault Secrets Officer* para cargar los secretos.
- **MySQL** tiene cuatro usuarios de mínimo privilegio: la aplicación (lectura y escritura de datos), el despliegue (cambios de esquema), los reportes (solo vistas) y el respaldo (solo lectura).

# Arquitectura de la sede (parte local)

## Diseño físico de referencia

Para cuando la clínica lleve la sede a su local, el diseño físico es este:

*Tabla 3. Equipamiento físico de referencia para la sede.*

| Elemento | Especificación | Función |
| --- | --- | --- |
| Gabinete | Rack de pared de 12 U, con bandeja y organizador de cables | Alojar y ventilar los equipos |
| Firewall | Equipo con 2 puertos de red (o mini PC con dos NIC) con la misma configuración Linux | WAN hacia el proveedor de internet y LAN troncal hacia el conmutador |
| Conmutador | Gestionable de 24 puertos con 802.1Q (VLAN) | VLAN 10 administración, 30 personal, 50 servidores y 60 DMZ |
| Host de virtualización | 1 servidor con 8 núcleos, 32 GB de RAM y 2 discos de 1 TB en espejo (RAID 1) | Correr el SIEM, el respaldo y el honeypot |
| Almacenamiento | Los discos del host en RAID 1; copia de las copias en un disco externo cifrado, rotado semanalmente | Guardar las copias horarias |
| Energía | UPS de 1 500 VA en línea interactiva, con apagado ordenado por USB | Resistir cortes cortos y apagar sin dañar datos |
| Enlace | Internet de fibra con IP pública (o NAT-T si el proveedor no la da) | La VPN con producción |

## Diseño del hipervisor

| Elemento de ESXi | Diseño | En la implementación en Azure |
| --- | --- | --- |
| vSwitch0 (enlace de subida) | NIC física hacia el puerto troncal del conmutador; NIC teaming activo-pasivo con una segunda NIC | Red de Azure (redundancia de la plataforma) |
| *Port group* VLAN 10 | Administración | `snet-mgmt` 10.10.10.0/24 |
| *Port group* VLAN 30 | Personal | `snet-personal` 10.10.30.0/24 |
| *Port group* VLAN 50 | Servidores | `snet-servidores` 10.10.50.0/24 |
| *Port group* VLAN 60 | DMZ | `snet-dmz` 10.10.60.0/24 |
| VM firewall | 2 vCPU, 1 GB, interfaces en WAN y troncal | `vm-fw` con 2 NIC (10.10.0.4 y 10.10.1.4) |
| VM SIEM y respaldo | 2 vCPU, 4 GB, 64 GB | `vm-wazuh` (Standard_B2als_v2, 64 GB) |
| VM honeypot | 2 vCPU, 1 GB, 30 GB | `vm-honeypot` en la DMZ |
| VM recepción | 2 vCPU, 1 GB, 30 GB | `vm-recepcion` en Personal |

## Por qué la sede se implementa en Azure

El equipo no dispone de un servidor ESXi. Una segunda red de Azure en otra región reproduce lo que importa evaluar del diseño local: los segmentos, el firewall con sus reglas, la VPN por internet con NAT-T (como pasaría con un proveedor de internet real) y los servicios de la sede, y se crea entera con `infra/desplegar.sh sede`. La región es distinta de la de producción (North Central US) porque la cuota estudiantil es de 6 vCPU por región; así la sede también queda físicamente lejos de producción, como en un esquema real de recuperación ante desastres.

# Integración híbrida

| Flujo | De | A | Por | Puerto |
| --- | --- | --- | --- | --- |
| Panel del personal | Recepción (sede) | Proxy (producción) | VPN | 443 |
| Registros al SIEM | Todas las VM de producción | Wazuh (sede) | VPN | 1514, 1515 |
| Copia horaria | vm-app | Wazuh, usuario `respaldo` | VPN, SSH con llave dedicada | 22 |
| Administración | Firewall de la sede (jump host) | Proxy y app | VPN | 22 |
| Resolución del dominio | Recepción | Unbound en el firewall de la sede | Local | 53 |

La VPN se describe en el S1-E1 (sección 5). El DNS interno de la sede responde el dominio de la web con la IP privada del proxy, así el personal de la clínica entra por la VPN aunque la web también sea pública.

# Dimensionamiento y capacidad

## Cómputo y cuota

*Tabla 4. Consumo de cuota por región.*

| Región | VM | vCPU usadas | Cuota | Margen |
| --- | --- | --: | --: | --: |
| Chile Central (producción) | vm-fw, vm-proxy, vm-app | 6 | 6 | 0 vCPU; crecer pide aumento de cuota o cambiar a tamaños de 4 vCPU |
| North Central US (sede) | vm-fw, vm-wazuh y una VM de laboratorio | 6 | 6 | 0 vCPU; la VM de laboratorio se alterna entre recepción y honeypot |

## Memoria, disco y crecimiento

Los usos de esta tabla se midieron en el entorno desplegado el 19/11/2026, con los datos de prueba cargados (268 pacientes, 874 citas, 733 pagos).

| Recurso | Uso medido | Capacidad | Margen | Criterio para crecer |
| --- | --- | --- | --- | --- |
| RAM de vm-app | 300 MB y 49 MB de swap | 887 MB + 2 GiB de swap | 66 % | Pasar a B2als_v2 (4 GiB) si el swap pasa de 500 MB de forma sostenida |
| RAM de los firewalls | 330-420 MB y 450-570 MB de swap (Suricata) | 887 MB + 2 GiB de swap | 50 % de la RAM | Pasar a B2als_v2 si Suricata pasa a modo IPS |
| RAM de vm-wazuh | 2,7 GB (indexer, manager y dashboard) | 3,9 GB | 31 % | Pasar a B2as_v2 (8 GiB) si el indexer se reinicia por memoria |
| Disco de vm-wazuh | 41 GB (índices de alertas) | 61 GB | 33 % | Retención de índices de 30 días o ampliar el disco en línea |
| Base de datos | 6,6 MB | 32 GB | > 99 % | A 100 pacientes nuevos por mes alcanza por décadas |
| Archivos clínicos | Radiografías de hasta 25 MB | Blob, sin límite práctico | — | Pasar los antiguos a nivel de acceso esporádico |
| Copias en la sede | Copias horarias de unos 230 KB, se guardan las últimas 48 | El disco de vm-wazuh | > 99 % de lo que ocupan | La rotación ya está automatizada |
| Disco de las demás VM | 4,3-4,8 GB | 29 GB | 83 % | Ampliar el disco en línea |

## Costo estimado

| Recurso | Costo aproximado encendido | Apagado |
| --- | --- | --- |
| 6 VM de la serie B v2 | 1,5-2 USD por día en total | Solo el disco |
| MySQL B1ms | 0,5 USD por día | Se puede detener hasta 30 días |
| Key Vault, Blob, IP públicas, Log Analytics | Céntimos por día | Igual |

Con las VM apagadas fuera de las sesiones de trabajo (`desplegar.sh apagar`), el proyecto completo cabe en 10 a 25 USD del crédito.

# Disponibilidad y continuidad

![](diagramas/continuidad.png)

*Figura 2. Tres niveles de copia, cada uno independiente de los otros.*

| Amenaza | Medida | RPO | RTO |
| --- | --- | --- | --- |
| Error en una versión de la aplicación | Versiones guardadas en `releases/` y acción "volver" del workflow | 0 | < 5 min |
| Borrado o corrupción de datos | Restauración a un punto en el tiempo de MySQL | Minutos | < 1 h |
| Pérdida de vm-app | Recrearla con Bicep y cloud-init, publicar y restaurar | 0 (los datos están en MySQL) | < 2 h |
| Pérdida de la base de datos o de la región | Copia horaria en la sede y diaria en Blob | 1 h | < 2 h |
| Caída de la VPN | Reconexión automática (DPD y `close_action = start`) | 0 | < 1 min |
| Pérdida de la sede | La producción sigue; la sede se recrea con `desplegar.sh sede` | Los registros del SIEM | < 1 h |

**Limitaciones aceptadas.** Cada servicio tiene una sola VM: Azure garantiza 99,5 % a una VM sola con disco SSD estándar (los discos de este diseño son HDD estándar, sin compromiso de disponibilidad de la plataforma). Se acepta porque la clínica puede atender unas horas con agenda en papel y porque el presupuesto no alcanza para conjuntos de disponibilidad. El paso siguiente sería un disco SSD y, después, una segunda vm-app detrás del proxy.

# Matriz de componentes y trazabilidad

*Tabla 5. Componentes, requisito que cubren y dónde se implementan.*

| Componente | Requisito u objetivo | Implementación | Evidencia en el Semestre 2 |
| --- | --- | --- | --- |
| VNet y subredes | Segmentación (S1-E1, O2 y O4) | `infra/azure/*/main.bicep` | S2-E2, pruebas negativas |
| Firewalls Linux | Perímetro, NAT, VPN, IDS | `infra/firewall/*.nft`, `infra/cloud-init/firewall.yaml` | S2-E2 |
| VM de producción | Servicio web | `infra/azure/modules/linux-vm.bicep` | S2-E3, inventario |
| MySQL Flexible | Datos privados con TLS (O2) | `infra/azure/prod/main.bicep` | S2-E3, prueba TLS |
| Key Vault | OC5 | `infra/scripts/kv.sh` | S2-E3, lista de secretos |
| Blob | OC1 | `infra/scripts/backup-to-blob.sh` | S2-E3, simulacro de restauración |
| Copia horaria a la sede | OC1 | `infra/scripts/copy-to-dr.sh` | S2-E3, copias en la sede |
| Identidades administradas y OIDC | Sin claves en las VM ni en GitHub | `main.bicep`, `.github/workflows/desplegar-azure.yml` | S2-E3 |
| Azure Monitor y presupuesto | OC6, OC7 | `main.bicep`, `presupuesto.bicep` | S2-E3 |
| Wazuh | OC7 | `infra/cloud-init/wazuh.yaml`, `instalar-agentes.sh` | S2-E2, agentes activos |

# Referencias

[1] Microsoft, "Azure Well-Architected Framework," Microsoft Learn, 2026. [En línea]. Disponible: https://learn.microsoft.com/azure/well-architected/

[2] Microsoft, "Azure Database for MySQL — Flexible Server: backup and restore," Microsoft Learn, 2026. [En línea]. Disponible: https://learn.microsoft.com/azure/mysql/flexible-server/concepts-backup-restore

[3] Microsoft, "SLA for Online Services," 2026. [En línea]. Disponible: https://www.microsoft.com/licensing/docs/view/Service-Level-Agreements-SLA-for-Online-Services

[4] VMware, "vSphere Networking," VMware ESXi 8.0 documentation, 2024.

[5] ISO/IEC, "ISO/IEC 22301:2019 Security and resilience — Business continuity management systems — Requirements," 2019.

[6] TIA, "ANSI/TIA-942-B Telecommunications Infrastructure Standard for Data Centers," 2017.

[7] GitHub, "Configuring OpenID Connect in Azure," GitHub Docs, 2026. [En línea]. Disponible: https://docs.github.com/actions/deployment/security-hardening-your-deployments/configuring-openid-connect-in-azure
