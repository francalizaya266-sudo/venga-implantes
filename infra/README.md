# Infraestructura en Azure (línea CE03)

Es la vista de despliegue del E1 (figura 13) llevada a plantillas: desde internet solo se llega a OPNsense; OPNsense
pasa el tráfico al proxy de la DMZ (nginx + ModSecurity + Let's Encrypt) y el proxy a `vm-app` (nginx + PHP-FPM 8.3).
MySQL Flexible Server 8.0 es privado y exige TLS. Los secretos del `.env` van en Key Vault y los registros en
Azure Monitor. Una segunda red hace de sede de la clínica y se une a producción por una VPN IPsec entre los dos
OPNsense.

| Red | Grupo de recursos | Qué tiene |
| --- | --- | --- |
| `vnet-vega` 10.20.0.0/16 | `rg-vega-prod` | OPNsense, `vm-proxy` (DMZ), `vm-app`, MySQL, Key Vault, Blob, alertas |
| `vnet-clinica` 10.10.0.0/16 | `rg-vega-clinica` | OPNsense, Wazuh, respaldo, honeypot, jump host, PC de recepción |

### Dos suscripciones

Un crédito de 100 USD no alcanza para las dos redes. Producción va en la suscripción de Azure for Students de un
integrante y la sede en la de otro. La VPN entre los dos OPNsense va por internet, así que funciona igual entre
suscripciones distintas: además, parece más una sede de verdad. Cada dueño invita a los demás como
**Colaborador** de su grupo de recursos.

## Qué hay en esta carpeta

| Ruta | Qué es |
| --- | --- |
| `azure/prod/` | Bicep de producción (dos pasadas: red y servicios, luego las VM) |
| `azure/clinica/` | Bicep de la sede de la clínica (dos pasadas) |
| `azure/modules/` | VM Ubuntu reutilizable y asignación de roles |
| `azure/presupuesto.bicep` | Alerta de presupuesto al 50 % y 80 % |
| `cloud-init/` | Lo que cada VM instala sola al nacer |
| `nginx/proxy.conf` | vm-proxy: WAF, HTTPS, caché de `/app/assets`, `/panel` cerrado a internet |
| `nginx/vegaimplant.conf` | vm-app: Laravel solo para el proxy |
| `env/produccion.env` | Configuración de producción sin secretos |
| `scripts/` | Publicar la app, el proxy, las copias a Blob y a la sede, y los agentes de Wazuh y Datadog |
| `opnsense/README.md` | Los dos OPNsense: NAT, reglas, VPN, DNS, Suricata |
| `desplegar.sh` | Un solo comando para crear, encender, apagar y revisar todo |
| `azure/entorno.env.example` | Región, suscripciones y correo (se copia a `entorno.env`, que no se sube) |
| `../.github/workflows/desplegar-azure.yml` | Publica la app en vm-app desde GitHub, sin contraseñas |

**Regla del equipo:** ningún secreto entra al repositorio. Las claves de las personas van en 1Password; los secretos
de la aplicación, en Key Vault; los parámetros sensibles de Bicep se leen de variables de entorno.

## Ruta rápida: todo desde Cloud Shell

No se instala nada en la PC. **Cloud Shell** (shell.azure.com, o el ícono `>_` del portal) es una terminal de Azure
en el navegador que ya trae `az`, Bicep y git. Allí se clona el repositorio y todo se maneja con un solo script,
`infra/desplegar.sh`. La app se publica desde GitHub Actions, sin contraseñas.

### Una sola vez

```bash
git clone https://github.com/francalizaya266-sudo/venga-implantes.git
cd venga-implantes
cp infra/azure/entorno.env.example infra/azure/entorno.env
nano infra/azure/entorno.env        # región, suscripciones, correo, tu IP
bash infra/desplegar.sh preparar    # compila las plantillas, crea la llave SSH y los grupos
```

### Producción

| Paso | Comando o acción | Tiempo |
| --- | --- | --- |
| 1 | `bash infra/desplegar.sh produccion red` | 10 min |
| 2 | Instalar OPNsense de producción (`infra/opnsense/README.md`, puntos 1 y 2) | 30 min |
| 3 | `bash infra/desplegar.sh secretos` | 1 min |
| 4 | `bash infra/desplegar.sh produccion vms` | 10 min |
| 5 | Usuarios de MySQL con `seguridad.sql` (punto 2.5) | 5 min |
| 6 | En GitHub, las variables del workflow (abajo) y **Actions → Desplegar en Azure → Run workflow** | 10 min |
| 7 | Certificado en el proxy (punto 2.7) | 5 min |

**Variables de GitHub** (Settings → Secrets and variables → Actions → Variables). Salen de la salida del paso 1:

| Variable | Valor |
| --- | --- |
| `AZURE_CLIENT_ID` | `githubClientId` |
| `AZURE_TENANT_ID` | `tenantId` |
| `AZURE_SUBSCRIPTION_ID` | `subscriptionId` |
| `AZURE_RG` | `rg-vega-prod` |
| `AZURE_STORAGE` | `storageAccount` |
| `SITE_URL` | `https://vegaimplant.tech` (opcional: el workflow comprueba `/up` al terminar) |

No son secretos: GitHub entra a Azure con un token firmado que solo acepta la identidad `id-github-despliegue`,
y solo desde la rama `main`. Cada publicación queda registrada en Actions: es evidencia de control de cambios.

### Sede de la clínica

| Paso | Comando o acción | Tiempo |
| --- | --- | --- |
| 1 | `bash infra/desplegar.sh sede red` | 5 min |
| 2 | Instalar OPNsense de la sede (`infra/opnsense/README.md`, puntos 1 y 3) | 30 min |
| 3 | `bash infra/desplegar.sh sede vms` | 20 min |
| 4 | VPN y DNS entre los dos OPNsense (`infra/opnsense/README.md`, puntos 3 y 4) | 30 min |

### Cada sesión de trabajo

```bash
bash infra/desplegar.sh encender    # los OPNsense primero
bash infra/desplegar.sh acceso      # la IP de Cloud Shell cambia en cada sesión
bash infra/desplegar.sh estado
# ... trabajar ...
bash infra/desplegar.sh apagar      # desasigna todo: deja de cobrar cómputo y conserva la configuración
```

Apagar en lugar de borrar es lo que hace barato el proyecto: una VM desasignada solo cobra su disco, y al
encenderla vuelve tal cual, con OPNsense configurado. Así el gasto total queda en unos 10 a 25 USD del crédito.

### Configurar OPNsense una sola vez

La configuración manual de cada OPNsense se hace una vez. Después, **Sistema → Configuración → Copias de
seguridad → Descargar** y guarda el `config.xml` en 1Password (tiene claves: no va al repositorio). Si un OPNsense
se pierde, se vuelve a crear con opnazure y se restaura ese archivo en minutos.

Las fases de abajo explican qué hace cada orden por dentro, para el informe y para cuando algo falle.

---

## Fase 0 · Cuentas y accesos (1 a 2 días)

1. Dos integrantes activan **Azure for Students** con el correo de la universidad (azure.microsoft.com/free/students).
2. Revisen qué regiones permite cada suscripción (`az account list-locations -o table`). Si `brazilsouth` no está,
   usen `eastus2`. Las dos redes pueden estar en regiones distintas.
3. **MFA** en todas las cuentas. Cada dueño invita a los demás como Colaborador de su grupo de recursos.
4. Bóveda del equipo en **1Password**.
5. Dominio **.TECH** del Student Pack.
6. Cuentas de **Datadog, Sentry, Termius, Requestly, Testmail y UptimeRobot**.
7. En GitHub, rama `main` protegida (revisión obligatoria en cada pull request).
8. Llave SSH del equipo (la privada va a 1Password):

   ```bash
   ssh-keygen -t ed25519 -C "vega-implant" -f ~/.ssh/vega
   ```

9. Alerta de presupuesto en cada suscripción:

   ```bash
   az deployment sub create -l brazilsouth -f infra/azure/presupuesto.bicep \
     -p alertEmail='correo-del-equipo@...' startDate="$(date +%Y-%m-01)"
   ```

## Fase 1 · Diseño (1 semana)

Diagramas en Draw.io de las dos redes (y de la clínica real con ESXi, solo como diseño para CE0331),
direccionamiento, reglas, tamaño de las VM, presupuesto y RTO/RPO. Las plantillas de esta carpeta son la fuente de
verdad: si cambia una IP, cámbienla aquí, en los diagramas y en el E1.

## Fase 2 · Producción (1 semana) · suscripción de producción

### 2.1 Red, MySQL, Key Vault y Blob (primera pasada)

```bash
az account set --subscription "SUSCRIPCION-PRODUCCION"
az group create -n rg-vega-prod -l brazilsouth

export SSH_PUBLIC_KEY="$(cat ~/.ssh/vega.pub)"
export ADMIN_SOURCE_IPS="TU_IP/32,$(curl -s https://ifconfig.me)/32"
export OPERATOR_OBJECT_ID="$(az ad signed-in-user show --query id -o tsv)"
export MYSQL_ADMIN_PASSWORD='...'                 # nueva, a 1Password
export ALERT_EMAIL='correo-del-equipo@...'
export DEPLOY_VMS=false

az deployment group create -g rg-vega-prod \
  -f infra/azure/prod/main.bicep -p infra/azure/prod/prod.bicepparam \
  --query properties.outputs
```

Anota `mysqlHost`, `keyVault` y `storageAccount`.

### 2.2 OPNsense de producción

`infra/opnsense/README.md`, puntos 1 y 2 (sin las reglas IPsec todavía). Anota su IP pública WAN y apunta el
dominio: registros **A** de `vegaimplant.tech` y `www` hacia esa IP.

### 2.3 Secretos en Key Vault

```bash
KV=kv-vega-xxxxxxxx
az keyvault secret set --vault-name $KV -n app-key                --value "base64:$(openssl rand -base64 32)"
az keyvault secret set --vault-name $KV -n db-password            --value "$(openssl rand -base64 24)"
az keyvault secret set --vault-name $KV -n db-password-despliegue --value "$(openssl rand -base64 24)"
az keyvault secret set --vault-name $KV -n db-password-reportes   --value "$(openssl rand -base64 24)"
az keyvault secret set --vault-name $KV -n db-password-respaldo   --value "$(openssl rand -base64 24)"
az keyvault secret set --vault-name $KV -n mail-password          --value '...'   # clave SMTP de Brevo
# Cuando los tengan: whatsapp-cloud-token, dni-lookup-token, sentry-dsn
```

### 2.4 Las VM (segunda pasada)

```bash
export DEPLOY_VMS=true
az deployment group create -g rg-vega-prod \
  -f infra/azure/prod/main.bicep -p infra/azure/prod/prod.bicepparam
```

Salen a internet por OPNsense para instalarse (unos 10 minutos).

### 2.5 Usuarios de MySQL (`backend/database/sql/seguridad.sql`)

Hasta que exista la VPN, entra por los reenvíos temporales de OPNsense:

```bash
scp -P 2222 -i ~/.ssh/vega -r infra backend/database/sql vegaadmin@IP_WAN_PROD:~
ssh -p 2222 -i ~/.ssh/vega vegaadmin@IP_WAN_PROD          # llega a vm-app
```

En vm-app, la primera vez se corre **antes** de migrar (crea `consultorio_despliegue`) y **después** de la
primera publicación (da a reportes las vistas):

```bash
sudo mkdir -p /etc/vega && echo "KEY_VAULT=kv-vega-xxxxxxxx" | sudo tee /etc/vega/vega.env
source ~/infra/scripts/kv.sh
( echo "SET @clave_app='$(kv_secret db-password)', @clave_despliegue='$(kv_secret db-password-despliegue)', @clave_reportes='$(kv_secret db-password-reportes)', @clave_respaldo='$(kv_secret db-password-respaldo)';"
  cat ~/sql/seguridad.sql ) | mysql -h MYSQL_HOST -u vegaadmin -p --ssl-ca=/etc/ssl/certs/ca-certificates.crt consultorio
```

(`kv.sh` usa la identidad administrada de vm-app: no necesitas iniciar sesión en Azure dentro de la VM.)

### 2.6 Publicar la aplicación

1. `/etc/vega/vega.env` en vm-app:

   ```bash
   sudo tee /etc/vega/vega.env <<EOF
   KEY_VAULT=kv-vega-xxxxxxxx
   STORAGE_ACCOUNT=stvegaxxxxxxxx
   DB_HOST=MYSQL_HOST
   EOF
   ```

2. `/etc/vega/app.env`: copia `infra/env/produccion.env` y completa lo marcado con `<...>`.
3. Descarga el paquete `consultorio-para-hosting` de la última ejecución de **Pruebas** en GitHub Actions,
   súbelo y publícalo:

   ```bash
   scp -P 2222 -i ~/.ssh/vega consultorio-para-hosting.zip vegaadmin@IP_WAN_PROD:~
   sudo bash ~/infra/scripts/deploy-app.sh ~/consultorio-para-hosting.zip
   ```

4. Vuelve a correr `seguridad.sql` (punto 2.5) para que reportes reciba las vistas.
5. Datos de prueba (la rúbrica no admite datos reales) y una clave propia para el administrador:

   ```bash
   cd /var/www/vega/current
   sudo -u www-data php artisan db:seed --class=DemoSeeder --force
   sudo -u www-data php artisan usuario:clave admin@consultorio.test
   ```

   `db:seed` escribe datos: si `consultorio_app` no alcanza (por ejemplo para crear objetos), córrelo con
   `env DB_USERNAME=consultorio_despliegue DB_PASSWORD=...` como hace `deploy-app.sh` con las migraciones.

### 2.7 El proxy

```bash
scp -P 2223 -i ~/.ssh/vega -r infra vegaadmin@IP_WAN_PROD:~
ssh -p 2223 -i ~/.ssh/vega vegaadmin@IP_WAN_PROD          # llega a vm-proxy
sudo DOMINIO=vegaimplant.tech CORREO=correo-del-equipo@... bash ~/infra/scripts/configurar-proxy.sh
```

### 2.8 Copias a Blob

En vm-app: `sudo /opt/vega/backup-to-blob.sh`.

**Evidencia:** la web con candado en el dominio; `https://vegaimplant.tech/panel` responde **403** desde internet;
`curl -sI https://vegaimplant.tech/up` muestra que pasó por el proxy.

## Fase 3 · Sede de la clínica (1 semana) · suscripción de la sede

```bash
az account set --subscription "SUSCRIPCION-SEDE"
az group create -n rg-vega-clinica -l brazilsouth
export DEPLOY_VMS=false
az deployment group create -g rg-vega-clinica \
  -f infra/azure/clinica/main.bicep -p infra/azure/clinica/clinica.bicepparam
```

OPNsense de la sede: `infra/opnsense/README.md`, puntos 1 y 3. Luego las VM:

```bash
export DEPLOY_VMS=true
az deployment group create -g rg-vega-clinica \
  -f infra/azure/clinica/main.bicep -p infra/azure/clinica/clinica.bicepparam
```

Todas se apagan solas a las 20:00. Para encenderlas en una sesión de laboratorio (OPNsense primero):

```bash
az vm start --ids $(az vm list -g rg-vega-clinica --query "[].id" -o tsv)
```

Si Azure avisa que superaste la cuota de vCPU, enciéndelas por turnos.

### Acceso desde tu PC

Agrega a `~/.ssh/config` (o importa lo mismo en Termius):

```sshconfig
Host vega-jump
  HostName IP_WAN_SEDE
  Port 2222
  User vegaadmin
  IdentityFile ~/.ssh/vega

Host vega-app vega-proxy vega-wazuh vega-respaldo vega-recepcion vega-honeypot
  User vegaadmin
  IdentityFile ~/.ssh/vega
  ProxyJump vega-jump

Host vega-app
  HostName 10.20.4.4
Host vega-proxy
  HostName 10.20.3.4
Host vega-wazuh
  HostName 10.10.50.10
Host vega-respaldo
  HostName 10.10.50.20
Host vega-recepcion
  HostName 10.10.30.10
Host vega-honeypot
  HostName 10.10.60.10
  Port 2222
```

`vega-app` y `vega-proxy` funcionan cuando la VPN de la fase 4 está arriba.

## Fase 4 · VPN entre los dos OPNsense (2 a 3 días)

`infra/opnsense/README.md`, punto 4, más las reglas IPsec de los puntos 2 y 3, y el DNS del punto 3.
Cuando el túnel funcione:

1. Borra en el OPNsense de producción los reenvíos 2222 y 2223.
2. Cierra el SSH de instalación: `SETUP_SSH=false` en `entorno.env` y `bash infra/desplegar.sh produccion vms`.

**Evidencia:** el túnel arriba en los dos OPNsense; desde la PC de recepción el panel abre:

```bash
ssh -D 1080 vega-recepcion
```

Configura tu navegador con el proxy SOCKS5 `127.0.0.1:1080`, con la opción de resolver el DNS por el proxy, y abre
`https://vegaimplant.tech/panel`: entras como si estuvieras en la clínica.

## Fase 5 · Seguridad y monitoreo (1 semana)

1. Suricata y logs remotos a Wazuh en los dos OPNsense (`infra/opnsense/README.md`, puntos 5 y 6).
2. Contraseñas de Wazuh a 1Password:
   `ssh vega-wazuh "sudo tar -O -xf /root/wazuh-install-files.tar wazuh-install-files/wazuh-passwords.txt"`.
3. Agentes en cada VM Linux (vm-app, vm-proxy, wazuh, respaldo, honeypot, jump, recepción):

   ```bash
   scp infra/scripts/instalar-agentes.sh vega-app:~
   ssh vega-app "sudo WAZUH_MANAGER=10.10.50.10 AGENT_NAME=vm-app DD_API_KEY=... DD_SITE=... bash ~/instalar-agentes.sh"
   ```

   En vm-proxy, agrega además el log del WAF a Wazuh: `/var/log/modsec_audit.log`.
4. Tablero de Wazuh desde tu PC: `ssh -L 8443:10.10.50.10:443 vega-jump` y abre `https://localhost:8443`.
5. Sentry en Laravel (en una rama del repositorio, con pull request): `composer require sentry/sentry-laravel`,
   `\Sentry\Laravel\Integration::handles($exceptions);` en `bootstrap/app.php`, el secreto `sentry-dsn` en Key Vault
   y `SENTRY_SEND_DEFAULT_PII=false`.
6. UptimeRobot: monitores HTTP a `https://vegaimplant.tech/up` y `https://vegaimplant.tech/api/v1/site`.

## Fase 6 · Pruebas (3 a 4 días)

Cada fila de la tabla de reglas del plan es una prueba con resultado esperado y obtenido. Algunas:

| Desde | Comando | Esperado |
| --- | --- | --- |
| Tu PC | `curl -I https://vegaimplant.tech/` | 200 |
| Tu PC | `curl -I https://vegaimplant.tech/panel` | 403 |
| Tu PC | `curl -I "https://vegaimplant.tech/api/v1/booking/slots?date=2026-12-01&treatment=1%27%20OR%201=1"` | Alerta en `/var/log/modsec_audit.log` (403 cuando el WAF está en `On`) |
| Tu PC | `nmap -Pn -p 22,3306,8443 IP_WAN_PROD` | Cerrados o filtrados |
| Tu PC | `nmap -Pn -p 22,3306 IP_WAN_SEDE` | Abiertos; alerta del honeypot en Wazuh |
| vega-recepcion | `curl -I https://vegaimplant.tech/panel/login` | 200 (por la VPN) |
| vega-recepcion | `nc -zv 10.10.50.10 22` | Bloqueado y registrado en OPNsense |
| vega-recepcion | `nc -zv 10.20.4.4 80` | Bloqueado: solo el proxy llega a vm-app |
| vega-honeypot | `nc -zv 10.10.30.10 22` | Bloqueado (DMZ hacia adentro) |
| vega-proxy | `nc -zv 10.20.4.4 22` | Bloqueado (la DMZ solo llega al 80 de vm-app) |
| Tu PC | 300 peticiones seguidas a `/api/v1/account` | Respuestas 429 con la IP de tu PC, no la del proxy |

Cuando el WAF lleve unos días en detección sin falsos positivos, pásalo a bloqueo en vm-proxy:
`sudo sed -i 's/^SecRuleEngine .*/SecRuleEngine On/' /etc/modsecurity/modsecurity.conf && sudo systemctl reload nginx`.

## Fase 7 · Continuidad (2 a 3 días)

1. Copia horaria a la sede: en vm-app, la llave `/etc/vega/dr_key` (su `.pub` va a
   `/home/respaldo/.ssh/authorized_keys` en vm-respaldo). Prueba `sudo /opt/vega/copy-to-dr.sh`.
2. MySQL Flexible ya guarda copias automáticas 7 días con restauración a un punto en el tiempo: es el RPO de
   minutos en producción. La copia horaria cubre la sede.
3. **Ejercicio de recuperación** (anota la hora de inicio y de fin):
   1. Detén `vm-app`. UptimeRobot, Datadog y Azure Monitor deben avisar.
   2. Bórrala y vuelve a crearla con la segunda pasada de producción.
   3. Publica la app con `deploy-app.sh` (los secretos vuelven desde Key Vault).
   4. Baja la última copia: `azcopy sync https://STORAGE.blob.core.windows.net/respaldos/base-de-datos /var/www/vega/shared/storage/app/private/respaldos`
      y los archivos de `archivos/clinica` y `archivos/web`.
   5. `sudo -u www-data env DB_USERNAME=consultorio_despliegue DB_PASSWORD=... php artisan respaldo:restaurar`.
   6. Revisa la agenda de hoy, un paciente con radiografías y la caja. La diferencia de horas es el **RTO real**.

## Fase 8 · SGSI y cierre

Inventario de activos, matriz de riesgos, Declaración de Aplicabilidad, políticas y ética. Después de la demo,
en la suscripción de la sede: `az group delete -n rg-vega-clinica --yes`.

## Costos: qué apagar

| Recurso | Cobra apagado | Qué hacer |
| --- | --- | --- |
| VM de la sede | Solo el disco | Se apagan solas a las 20:00 |
| OPNsense, vm-proxy y vm-app | Sí, si están encendidas | Son la producción: encendidas. En pausas largas, `az vm deallocate` |
| MySQL | Sí | `az mysql flexible-server stop` en pausas largas (Azure lo vuelve a encender a los 30 días) |
| IP públicas | Sí | Poco costo; se van al borrar cada grupo |

Revisa el saldo de cada suscripción en https://www.microsoftazuresponsorships.com/balance cada semana.
