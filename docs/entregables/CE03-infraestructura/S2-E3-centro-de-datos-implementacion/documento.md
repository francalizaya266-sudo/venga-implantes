# Resumen ejecutivo

**Qué se implementó.** El centro de datos híbrido diseñado en el S1-E2 está en funcionamiento: **27 recursos** en el grupo de producción (`rg-vega-prod`, Chile Central) y **16** en el de la sede (`rg-vega-clinica`, North Central US), todos creados desde el repositorio con Bicep y cloud-init. Producción tiene tres VM (firewall, proxy y aplicación), MySQL Flexible privado con TLS, Key Vault con 10 secretos, Blob Storage con versiones y borrado suave, Log Analytics, dos alertas y una identidad federada para GitHub. La sede tiene su firewall, el SIEM con el servidor de respaldo y el puesto de recepción.

**Identidades.** Ninguna clave de la nube vive en archivos: la VM de la aplicación lee sus secretos y escribe sus copias con una identidad administrada, y GitHub publica en producción con un token OIDC que Azure solo acepta desde la rama `main`. El panel exige MFA y los servidores solo aceptan llaves SSH.

**Continuidad probada.** El 19/11/2026 se ejecutaron tres ejercicios: la copia diaria de Blob se **restauró en 12 segundos** en una base aparte, con los mismos totales que producción (268 pacientes, 874 citas, 733 pagos, 70 tablas); la VPN cortada a propósito **volvió sola en 1,4 segundos**; y una publicación completa de la aplicación tardó **65 segundos**. Además, la sede recibe una copia de la base de datos **cada hora**.

**Control.** Cada cambio entra por pull request con pruebas automáticas (7 pull requests de infraestructura el 19/11/2026), cada publicación queda registrada en GitHub Actions y en la carpeta de versiones de vm-app, y Azure Monitor avisa por correo si la aplicación se cae o se satura.

# Trazabilidad con el diseño

*Tabla 1. Del diseño del S1-E2 a lo implementado.*

| Diseño (S1-E2) | Implementado | Evidencia |
| --- | --- | --- |
| 3 VM de producción B2ats_v2 | vm-fw, vm-proxy y vm-app, B2ats_v2, Ubuntu 24.04 | `az vm list -d` (tabla 3) |
| MySQL Flexible B1ms, privado, TLS | `vega-mysql-iifqzyeo`, 8.0.21, B1ms, acceso público deshabilitado, `require_secure_transport=ON`, TLS 1.2 y 1.3 | `az mysql flexible-server` |
| Key Vault con RBAC | `kv-vega-iifqzyeo`, RBAC activado, borrado suave 7 días | `az keyvault show` |
| Blob con versiones | Versiones activadas, borrado suave de 14 días (blobs y contenedores) | `az storage account blob-service-properties show` |
| Identidad de la VM | Token de la identidad administrada obtenido desde vm-app | `rg-vega-prod-vm-app.txt` |
| OIDC para GitHub | `id-github-despliegue` con dos credenciales federadas para `refs/heads/main` | `az identity federated-credential list` |
| Sede con SIEM, respaldo y recepción | vm-fw, vm-wazuh (B2als_v2, 64 GB) y vm-recepcion | `az vm list -d` |
| RPO 1 h | Copia horaria en vm-wazuh | `/srv/respaldos` |
| RTO 2 h | Ejercicios parciales (sección 6) | Esta sección |
| Presupuesto 40 USD con avisos | `presupuesto.bicep` | Plantilla del repositorio |

# Implementación en la nube

![](diagramas/centro-de-datos.png)

*Figura 1. Recursos de Azure, identidades y permisos.*

## Red

| Elemento | Producción | Sede |
| --- | --- | --- |
| Red virtual | `vnet-vega` 10.20.0.0/16 | `vnet-clinica` 10.10.0.0/16 |
| Subredes | wan, lan, dmz, app, mysql (delegada) | wan, lan, mgmt, personal, servidores, dmz |
| NSG | `nsg-wan`, `nsg-lan`, `nsg-dmz`, `nsg-app` | `nsg-wan`, `nsg-interno`, `nsg-dmz` |
| Tabla de rutas | `rt-via-firewall` | Tabla de rutas hacia la LAN del firewall |
| IP pública | `pip-fw` (estática, en la WAN del firewall) | Una, en la WAN del firewall |
| DNS privado | `vega.private.mysql.database.azure.com` enlazada a la VNet | Unbound en el firewall |

Equivalencias con AWS: la VNet hace de VPC; las subredes con su NSG, de subredes con Security Groups; nftables en el firewall, de NACL; la IP pública con el NAT del firewall, de Internet Gateway y NAT Gateway; strongSwan, de VPN Gateway; y Azure Monitor, de CloudWatch.

## Cómputo

*Tabla 2. Máquinas virtuales y uso medido el 19/11/2026.*

| VM | Región | Tamaño | IP | RAM usada | Swap usado | Disco usado |
| --- | --- | --- | --- | --- | --- | --- |
| vm-fw | Chile Central | B2ats_v2 | 10.20.0.4 / 10.20.1.4 | 422 de 887 MB | 452 MB | 4,4 de 29 GB |
| vm-proxy | Chile Central | B2ats_v2 | 10.20.3.4 | 330 de 887 MB | 18 MB | 4,3 de 29 GB |
| vm-app | Chile Central | B2ats_v2 | 10.20.4.4 | 300 de 887 MB | 49 MB | 4,8 de 29 GB |
| vm-fw | North Central US | B2ats_v2 | 10.10.0.4 / 10.10.1.4 | 329 de 887 MB | 568 MB | 4,4 de 29 GB |
| vm-wazuh | North Central US | B2als_v2 | 10.10.50.10 | 2 697 de 3 906 MB | — | 41 de 61 GB |
| vm-recepcion | North Central US | B2ats_v2 | 10.10.30.10 | — | — | — |

Software verificado: PHP 8.3.6 y nginx 1.24 en vm-app y vm-proxy (servicios activos), Wazuh 4.12.0 (manager, indexer y dashboard activos), y nftables, strongSwan y Suricata activos en los dos firewalls.

## Datos y almacenamiento

| Recurso | Configuración verificada |
| --- | --- |
| MySQL Flexible | Estado *Ready*, versión 8.0.21, Burstable B1ms, copias de 7 días, sin alta disponibilidad, **acceso de red pública deshabilitado**, `require_secure_transport = ON`, `tls_version = TLSv1.2,TLSv1.3`; la conexión desde vm-app negocia **TLS 1.3 con TLS_AES_256_GCM_SHA384**; base de 6,6 MB |
| Usuarios de MySQL | `consultorio_app`, `consultorio_despliegue`, `consultorio_reportes` y `consultorio_respaldo`, cada uno con lo mínimo (`backend/database/sql/seguridad.sql`) |
| Blob Storage | Contenedor `respaldos` con `base-de-datos/` (copia diaria) y `archivos/clinica` y `archivos/web` (radiografías, fotos y firmas); versiones activadas; borrado suave de 14 días |
| Key Vault | 10 secretos: `app-key`, `db-password`, `db-password-despliegue`, `db-password-reportes`, `db-password-respaldo`, `mysql-admin-password`, `panel-admin-temporal`, `panel-recepcion-temporal`, `vpn-psk` y `wazuh-admin-password` (solo nombres; los valores nunca salen de Key Vault) |

# Implementación de la sede

La sede reproduce en Azure el diseño local del S1-E2 (sección 4): cada *port group* de ESXi es una subred con su NSG, el vSwitch es la red virtual, y las VM del host son VM de Azure con los mismos papeles y tamaños.

| Diseño en ESXi | Implementación | Estado |
| --- | --- | --- |
| VM firewall con WAN y troncal | vm-fw con 2 NIC: 10.10.0.4 (WAN) y 10.10.1.4 (LAN) | En ejecución; nftables, strongSwan, Suricata y Unbound activos |
| VLAN 10 administración | `snet-mgmt` | El firewall de la sede hace de jump host |
| VLAN 30 personal | `snet-personal` con vm-recepcion | En ejecución; abre el panel por la VPN |
| VLAN 50 servidores | `snet-servidores` con vm-wazuh | En ejecución; SIEM y 5 copias horarias del día |
| VLAN 60 DMZ | `snet-dmz` con vm-honeypot | Definida en código; se crea en lugar de recepción con `desplegar.sh sede honeypot` |

# Identidades y accesos

| Acceso | Mecanismo | Evidencia |
| --- | --- | --- |
| Personal al panel | Cuenta propia, contraseña elegida por el usuario y **MFA obligatorio** con app autenticadora; códigos de recuperación; reinicio del segundo factor por el administrador, con registro | `PanelMultiFactorTest` (8 pruebas), PR [#24](https://github.com/francalizaya266-sudo/venga-implantes/pull/24); `/panel/login` en producción |
| vm-app a Key Vault y Blob | Identidad administrada del sistema: *Key Vault Secrets User* y *Storage Blob Data Contributor* | Token MSI obtenido desde vm-app; azcopy con `AZCOPY_AUTO_LOGIN_TYPE=MSI` |
| GitHub Actions a Azure | Identidad `id-github-despliegue` con credenciales federadas (OIDC) para `repo:…/venga-implantes:ref:refs/heads/main`; *Virtual Machine Contributor* en `rg-vega-prod` y escritura en Blob | `az identity federated-credential list`; ejecución del 19/11 en verde |
| Operador | Cuenta personal de Azure con *Key Vault Secrets Officer*; no tiene permiso de lectura de datos de Blob (lo comprobó la propia auditoría: `az storage blob list` fue rechazado) | Salida del 19/11 |
| Administración de servidores | Solo llave SSH (`~/.ssh/vega`, guardada en 1Password); usuario `vegaadmin`; SSH del firewall en el 2200 solo desde la IP de administración; las VM internas, solo desde el jump host | NSG `Permitir-Admin`; PR-03 y PR-04 del S2-E2 |
| Sin abrir puertos | `az vm run-command` (queda en el registro de actividad de Azure) | Así se aplicaron las reglas, la VPN y estas pruebas |
| Base de datos | 4 usuarios de mínimo privilegio; administrador solo para el esquema | `seguridad.sql` |

**Rotación de credenciales.** Los secretos de Key Vault se rotan con `az keyvault secret set` y la VM los vuelve a leer al publicar; la clave de la VPN se rota cambiando `vpn-psk` y corriendo `desplegar.sh vpn`. Quedan dos rotaciones pendientes por exposición (INC-01 e INC-02 del SGSI). Las contraseñas temporales del panel (`panel-*-temporal`) dejan de servir cuando cada usuario elige la suya en su primer ingreso.

# Inventario final

*Tabla 3. Recursos de Azure el 19/11/2026 (`az resource list`).*

| Grupo | Tipo | Recursos |
| --- | --- | --- |
| rg-vega-prod | Máquinas virtuales | vm-fw, vm-proxy, vm-app |
| rg-vega-prod | Discos | osdisk-vm-fw, osdisk-vm-proxy, osdisk-vm-app |
| rg-vega-prod | Interfaces de red | nic-vm-fw-wan, nic-vm-fw-lan, nic-vm-proxy, nic-vm-app |
| rg-vega-prod | Red | vnet-vega, nsg-wan, nsg-lan, nsg-dmz, nsg-app, rt-via-firewall, pip-fw |
| rg-vega-prod | DNS privado | vega.private.mysql.database.azure.com y su enlace a la VNet |
| rg-vega-prod | Datos | vega-mysql-iifqzyeo, stvegaiifqzyeo, kv-vega-iifqzyeo |
| rg-vega-prod | Monitoreo | law-vega, ag-vega, alerta-vm-caida, alerta-cpu-vm-app |
| rg-vega-prod | Identidad | id-github-despliegue |
| rg-vega-clinica | Máquinas virtuales | vm-fw, vm-wazuh, vm-recepcion (3, con sus 3 discos) |
| rg-vega-clinica | Red | vnet-clinica, 3 NSG, 1 tabla de rutas, 1 IP pública, 4 interfaces |

# Continuidad: copias, restauración y recuperación

![](diagramas/continuidad.png)

*Figura 2. Tres niveles de copia.*

## Copias en funcionamiento

| Nivel | Qué y cuándo | Evidencia del 19/11/2026 |
| --- | --- | --- |
| MySQL Flexible | Copias automáticas de 7 días, restauración a un punto en el tiempo | `backupRetentionDays = 7` |
| Blob Storage | Copia de la base a las 3:00 (`respaldo:crear`) y subida a las 3:30 con los archivos clínicos (`backup-to-blob.sh`, cron de root) | `base-de-datos/respaldo_2026-11-19_065405.sql.gz` (223 KB), 3 archivos clínicos y la carpeta web. El entorno se puso en marcha después de las 3:30: la copia del día se creó a las 06:54 y se subió a mano a las 11:16; desde el 20/11 corre sola |
| Sede | `copy-to-dr.sh` cada hora al minuto 15, con el usuario de solo lectura, por la VPN | `hora_2026-11-19_0815`, `0915` y `1015.sql.gz`, 5 copias en el día; registro: "copiado hora_2026-11-19_1015.sql.gz" |

## Ejercicio 1: restaurar la base de datos desde Blob

Objetivo: comprobar que una copia de Blob sirve para reconstruir la base de datos, sin tocar producción. Se ejecutó en vm-app el 19/11/2026 con el script del anexo (identidad administrada para Blob y la clave de administrador leída de Key Vault):

```text
inicio: 2026-11-19T11:24:13-05:00
copia elegida (Blob): respaldo_2026-11-19_065405.sql.gz
descargada: 224K
fin: 2026-11-19T11:24:25-05:00 duracion_s: 12
patients produccion=268 restaurada=268
appointments produccion=874 restaurada=874
payments produccion=733 restaurada=733
users produccion=2 restaurada=2
treatments produccion=9 restaurada=9
tablas restauradas: 70
base de simulacro borrada
```

Resultado: la copia se descargó y restauró en **12 segundos** en la base `consultorio_simulacro`, con los mismos totales que producción en las cinco tablas comparadas y las 70 tablas. Al terminar se borró la base del simulacro.

## Ejercicio 2: interrupción de la VPN

Se cortó el túnel en el firewall de la sede (`swanctl --terminate --ike vega`) a las 11:26:34. Por `close_action = start` y `dpd_action = restart`, strongSwan lo levantó de nuevo y el ping entre las dos LAN volvió a responder a las 11:26:35: **1,4 segundos** sin intervención. Durante ese lapso, la copia horaria habría reintentado en la hora siguiente (`copy-to-dr.sh` omite la hora si la sede no responde, sin fallar).

## Ejercicio 3: publicación y vuelta atrás de la aplicación

La publicación del 19/11/2026 (PR #24, MFA del panel) se hizo con el workflow "Desplegar en Azure": compilar la web (9 s), subir el paquete a Blob (5 s), publicar en vm-app (35 s) y comprobar `/up` (1 s), **65 segundos en total**. vm-app guarda cada versión en `releases/AAAAMMDDhhmmss` y apunta `current` a la activa (`/var/www/vega/releases/20261119095558`); la acción "volver" solo cambia ese enlace a la versión anterior, sin tocar la base de datos.

## RTO y RPO: planeado contra medido

*Tabla 4. Objetivos del S1-E2 y resultados.*

| Escenario | RPO planeado | RPO real | RTO planeado | RTO medido | Cumple |
| --- | --- | --- | --- | --- | --- |
| Restaurar la base desde Blob | 24 h | Copia de las 06:54 del mismo día | 1 h | 12 s (más la publicación, si la VM es nueva) | Sí |
| Copia de la sede | 1 h | 1 h (copias de 8:15, 9:15 y 10:15) | — | — | Sí |
| Corte de la VPN | 0 | 0 | < 1 min | 1,4 s | Sí |
| Publicar o volver una versión | 0 | 0 | < 5 min | 65 s la publicación | Sí |
| Perder vm-app entera | 0 | 0 | 2 h | No medido: recrear la VM de producción se dejó para una ventana acordada | Pendiente |

**Lo que falta medir.** El ejercicio completo del `infra/README.md` (borrar vm-app, recrearla con `desplegar.sh produccion`, publicar y restaurar) da el RTO real de perder la aplicación. No se hizo sobre producción en esta ronda; se programa con la clínica.

# Monitoreo y control operativo

## Monitoreo

| Herramienta | Qué vigila | Aviso |
| --- | --- | --- |
| Azure Monitor | `alerta-vm-caida` (disponibilidad de vm-app < 1, severidad 1) y `alerta-cpu-vm-app` (CPU > 80 %, severidad 2) | Correo al grupo `ag-vega` |
| Log Analytics | `law-vega`, 30 días de retención | Consultas |
| Wazuh | 6 agentes, 3 442 alertas el 19/11 (S2-E2) | Tablero |
| GitHub Actions | Workflow "Vigilar producción": pide `/up` cada 15 minutos | Correo de GitHub; está desactivado (NC-05) |
| Presupuesto | 40 USD con avisos al 50 % y 80 % | Correo |
| Capacidad | Uso medido de RAM y disco (tabla 2) | Revisión mensual |

**Capacidad.** El punto a vigilar es el disco de vm-wazuh (41 de 61 GB, 66 %): los índices de alertas crecen cada día. La acción es una retención de índices de 30 días en el indexer o ampliar el disco en línea. Los firewalls usan 450-570 MB de swap por Suricata; si Suricata pasa a IPS, conviene subirlos a 4 GiB.

## Control de cambios

![](diagramas/despliegue.png)

*Figura 3. De un cambio a producción.*

*Tabla 5. Cambios de infraestructura por pull request.*

| PR | Fecha | Cambio |
| --- | --- | --- |
| #13 | 19/11/2026 | Despliegue en Azure alineado con el E1 (Bicep, cloud-init, scripts) |
| #15 | 19/11/2026 | Producción en Chile Central con la serie B v2 |
| #16 | 19/11/2026 | Firewall Linux automático en lugar de OPNsense |
| #17 | 19/11/2026 | Ajustes del primer despliegue real |
| #18 | 19/11/2026 | HTTPS, Wazuh conectado, copias funcionando y SSH cerrado |
| #22 | 19/11/2026 | Registro de la CI y del código de infraestructura en los entregables de CE02 |
| #24 | 19/11/2026 | Panel con MFA obligatorio y acceso desde internet |

Cada pull request pasó las cuatro comprobaciones de la CI (backend con más de 1 100 pruebas, frontend, Larastan y MySQL 8) antes del merge. Cada publicación queda en el historial del workflow "Desplegar en Azure" con quién la lanzó, qué commit y cuánto tardó. La no conformidad NC-01 pide además proteger la rama `main` para que la revisión sea obligatoria.

# Limitaciones

- Una sola VM por servicio y discos HDD estándar: se acepta por presupuesto (riesgo R08 del SGSI).
- La sede es una red de Azure que representa al local; el diseño físico queda en el S1-E2.
- El RTO de perder vm-app entera no se midió en esta ronda.
- Key Vault acepta conexiones de red pública y no tiene protección de purga (NC-03).
- La cuota de 6 vCPU por región no deja margen para crecer sin pedir aumento.

# Referencias

[1] Microsoft, "Azure Database for MySQL — Flexible Server documentation," Microsoft Learn, 2026.

[2] Microsoft, "Managed identities for Azure resources," Microsoft Learn, 2026. [En línea]. Disponible: https://learn.microsoft.com/entra/identity/managed-identities-azure-resources/

[3] Microsoft, "Workload identity federation," Microsoft Learn, 2026.

[4] Microsoft, "Azure Monitor alerts overview," Microsoft Learn, 2026.

[5] Microsoft, "Bicep documentation," Microsoft Learn, 2026. [En línea]. Disponible: https://learn.microsoft.com/azure/azure-resource-manager/bicep/

[6] ISO/IEC, "ISO/IEC 27031:2011 Guidelines for information and communication technology readiness for business continuity," 2011.

[7] NIST, "Contingency Planning Guide for Federal Information Systems," SP 800-34 Rev. 1, 2010.

# Anexo A. Script del ejercicio de restauración {-}

<!-- archivo: evidencias/simulacro-restauracion.sh bash -->

Archivo: [`evidencias/simulacro-restauracion.sh`](../evidencias/simulacro-restauracion.sh).
