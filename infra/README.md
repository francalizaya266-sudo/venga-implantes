# Infraestructura en Azure (línea CE03)

Es la vista de despliegue del E1 (figura 13) llevada a código. Desde internet solo se llega al **firewall**
(`vm-fw`), que pasa el tráfico al **proxy de la DMZ** (nginx + ModSecurity + Let's Encrypt), y el proxy a **vm-app**
(nginx + PHP-FPM 8.3). **MySQL Flexible 8.0** es privado y exige TLS. Los secretos del `.env` van en **Key Vault**
y los registros en **Azure Monitor**. Una segunda red hace de **sede de la clínica** y se une a producción por una
**VPN IPsec** entre los dos firewalls.

| Red | Grupo de recursos | Qué tiene |
| --- | --- | --- |
| `vnet-vega` 10.20.0.0/16 | `rg-vega-prod` | vm-fw, vm-proxy (DMZ), vm-app, MySQL, Key Vault, Blob, alertas |
| `vnet-clinica` 10.10.0.0/16 | `rg-vega-clinica` | vm-fw (también jump host), vm-wazuh (también recibe las copias) y una VM de laboratorio: recepción u honeypot |

Todo se crea **sin pasos manuales**: el firewall es una VM Ubuntu configurada por cloud-init con las reglas de
`infra/firewall/` (nftables, strongSwan, Suricata, Unbound). Ver `infra/firewall/README.md`.

### Regiones, cuota y suscripciones

- **Cuota de Azure for Students: 6 vCPU por región.** Cada VM de la serie B v2 usa 2, así que entran 3 VM por región.
- **Producción en `chilecentral`** (la más cercana a Perú): firewall, proxy y app, 6 vCPU.
- **Sede en `northcentralus`** (otra región, con su propia cuota): firewall, Wazuh y una VM de laboratorio a la vez,
  `recepcion` para las pruebas de VPN y del panel u `honeypot` para las de seguridad (`desplegar.sh sede honeypot`
  cambia una por otra). La VPN va por internet, así que la distancia entre regiones no importa.
- Tamaños: `Standard_B2ats_v2` (2 vCPU y 1 GiB con 2 GiB de swap) y `Standard_B2als_v2` (4 GiB) para Wazuh. De las
  regiones permitidas, solo Chile Central y North Central US los ofrecen a esta suscripción.
- Chile Central no tiene el apagado automático de Azure: se apaga con `desplegar.sh apagar`.
- Producción y sede pueden ir en suscripciones de integrantes distintos (`SUB_PROD` y `SUB_SEDE`).

## Qué hay en esta carpeta

| Ruta | Qué es |
| --- | --- |
| `desplegar.sh` | Un solo comando para crear, encender, apagar y revisar todo |
| `azure/entorno.env.example` | Región, suscripciones, dominio y correo (se copia a `entorno.env`, que no se sube) |
| `azure/prod/`, `azure/clinica/` | Bicep de cada red |
| `azure/modules/` | VM Ubuntu, firewall con dos tarjetas y asignación de roles |
| `azure/presupuesto.bicep` | Alerta de presupuesto al 50 % y 80 % |
| `firewall/` | Reglas de nftables de cada firewall y su guía |
| `cloud-init/` | Lo que cada VM instala sola al nacer |
| `nginx/` | El proxy (WAF, HTTPS, caché, `/panel` cerrado a internet) y vm-app |
| `env/produccion.env` | Configuración de producción sin secretos |
| `scripts/` | Publicar la app, VPN, reglas, usuarios de MySQL, copias, agentes de Wazuh y Datadog |
| `../.github/workflows/desplegar-azure.yml` | Publica la app en vm-app desde GitHub, sin contraseñas |

**Regla del equipo:** ningún secreto entra al repositorio. Las claves de las personas van en 1Password; los
secretos de la aplicación, en Key Vault.

## Despliegue: todo desde Cloud Shell

**Cloud Shell** (shell.azure.com, o el ícono `>_` del portal) es una terminal de Azure en el navegador que ya
trae `az`, Bicep y git. Ábrela con **"Quiero crear una cuenta de almacenamiento"** en una región permitida, para que
tus archivos y la llave SSH se conserven.

### 1. Una sola vez (5 minutos)

```bash
git clone https://github.com/francalizaya266-sudo/venga-implantes.git
cd venga-implantes
cp infra/azure/entorno.env.example infra/azure/entorno.env
nano infra/azure/entorno.env        # DOMINIO, ALERT_EMAIL y MI_IP
bash infra/desplegar.sh preparar    # compila, registra los servicios, crea la llave SSH y los grupos
```

Guarda la llave en 1Password: `cat ~/.ssh/vega`.

### 2. Todo el entorno (unos 40 minutos, sin intervenir)

```bash
bash infra/desplegar.sh todo
```

Pide una sola cosa: la clave nueva del administrador de MySQL (va a Key Vault y a 1Password). Hace, en orden:

| Orden | Qué crea |
| --- | --- |
| `produccion` | Red, NSG, rutas, firewall, proxy, app, MySQL, Key Vault, Blob, alertas, identidad de GitHub |
| `secretos` | Claves de la app, de los 4 usuarios de MySQL y de la VPN, en Key Vault |
| `sede` | Red, NSG, rutas, firewall, Wazuh y la VM de recepción (`sede honeypot` la cambia por el honeypot) |
| `vpn` | El túnel IPsec entre los dos firewalls |
| `basedatos` | Los usuarios de `backend/database/sql/seguridad.sql` |

Cada orden también se puede correr sola, por ejemplo `bash infra/desplegar.sh vpn`.


### 3. Lo que queda (15 minutos)

1. **Dominio**: registros **A** de `@` y `www` hacia la IP pública del firewall de producción (la muestra `produccion`).
2. **Variables de GitHub** (Settings → Secrets and variables → Actions → Variables), con las salidas de `produccion`:

   | Variable | Valor |
   | --- | --- |
   | `AZURE_CLIENT_ID` | `githubClientId` |
   | `AZURE_TENANT_ID` | `tenantId` |
   | `AZURE_SUBSCRIPTION_ID` | `subscriptionId` |
   | `AZURE_RG` | `rg-vega-prod` |
   | `AZURE_STORAGE` | `storageAccount` |
   | `SITE_URL` | `https://tu-dominio` (opcional: comprueba `/up` al terminar) |

   No son secretos: GitHub entra a Azure con un token firmado que solo acepta la identidad `id-github-despliegue`,
   y solo desde `main`. Cada publicación queda registrada en Actions (evidencia de control de cambios).
3. **Configuración de la app en vm-app**: `/etc/vega/app.env` a partir de `infra/env/produccion.env` y
   `/etc/vega/vega.env` con `KEY_VAULT`, `STORAGE_ACCOUNT` y `DB_HOST` (ver "Entrar a las VM").
4. **Publicar**: GitHub → **Actions → Desplegar en Azure → Run workflow**.
5. **Usuarios de MySQL otra vez**, ya migrada la base (da a reportes las vistas): `bash infra/desplegar.sh basedatos`.
6. **Datos de prueba** (la rúbrica no admite datos reales), en vm-app. `DemoSeeder` necesita antes el catálogo y usa
   Faker, que es dependencia de desarrollo: se instala un momento y se vuelve a quitar.

   ```bash
   cd /var/www/vega/current
   source /opt/vega/kv.sh && CLAVE="$(kv_secret db-password-despliegue)"
   sudo COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction
   sudo -u www-data env DB_USERNAME=consultorio_despliegue DB_PASSWORD="$CLAVE" php artisan db:seed --class=CatalogSeeder --force
   sudo -u www-data php artisan db:seed --class=DemoSeeder --force
   sudo COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction
   sudo -u www-data php artisan optimize
   sudo -u www-data php artisan usuario:clave admin@consultorio.test
   ```

7. **Certificado** en vm-proxy: `sudo DOMINIO=tu-dominio CORREO=equipo@... bash ~/infra/scripts/configurar-proxy.sh`.

### Cada sesión de trabajo

```bash
bash infra/desplegar.sh encender    # los firewalls primero
bash infra/desplegar.sh acceso      # la IP de Cloud Shell cambia en cada sesión
bash infra/desplegar.sh estado      # VM encendidas, IP y estado de la VPN
# ... trabajar ...
bash infra/desplegar.sh apagar      # desasigna todo: deja de cobrar cómputo y conserva la configuración
```

Apagar en lugar de borrar es lo que hace barato el proyecto: una VM desasignada solo cobra su disco y vuelve tal
cual al encenderla. Así el gasto total queda en unos 10 a 25 USD del crédito.

## Entrar a las VM

Agrega a `~/.ssh/config` (cambia las dos IP públicas; las muestra `desplegar.sh estado`):

```sshconfig
Host vega-fw-prod
  HostName IP_FIREWALL_PRODUCCION
  Port 2200
Host vega-fw-sede
  HostName IP_FIREWALL_SEDE
  Port 2200

# El firewall de la sede hace de jump host: desde él se llega a la sede y, por la VPN, a producción.
Host vega-app vega-proxy vega-wazuh vega-recepcion vega-honeypot
  ProxyJump vega-fw-sede
Host vega-app
  HostName 10.20.4.4
Host vega-proxy
  HostName 10.20.3.4
Host vega-wazuh
  HostName 10.10.50.10
Host vega-recepcion
  HostName 10.10.30.10
Host vega-honeypot
  HostName 10.10.60.10
  Port 2222

Host vega-*
  User vegaadmin
  IdentityFile ~/.ssh/vega
```

`vega-app` y `vega-proxy` pasan por el firewall de la sede y la VPN. Antes de la VPN, se entra directo por el
firewall de producción: `ssh -p 2222 vegaadmin@IP_FIREWALL_PRODUCCION` (vm-app) y `-p 2223` (vm-proxy).
Cuando la VPN funciona, cierra esos accesos: `SETUP_SSH=false` en `entorno.env` y `bash infra/desplegar.sh produccion`.

Para usar el panel como si estuvieras en la clínica: `ssh -D 1080 vega-recepcion`, configura el navegador con el
proxy SOCKS5 `127.0.0.1:1080` (resolviendo el DNS por el proxy) y abre `https://tu-dominio/panel`.

## Seguridad y monitoreo

1. Contraseñas de Wazuh a 1Password:
   `ssh vega-wazuh "sudo tar -O -xf /root/wazuh-install-files.tar wazuh-install-files/wazuh-passwords.txt"`.
2. Agentes de Wazuh y Datadog en cada VM, firewalls incluidos (en ellos lee los bloqueos de nftables y las
   alertas de Suricata):

   ```bash
   scp infra/scripts/instalar-agentes.sh vega-app:~
   ssh vega-app "sudo WAZUH_MANAGER=10.10.50.10 AGENT_NAME=vm-app DD_API_KEY=... DD_SITE=... bash ~/instalar-agentes.sh"
   ```

3. Tablero de Wazuh: `ssh -L 8443:10.10.50.10:443 vega-fw-sede` y abre `https://localhost:8443`.
4. Sentry en Laravel (en un pull request): `composer require sentry/sentry-laravel`,
   `\Sentry\Laravel\Integration::handles($exceptions);` en `bootstrap/app.php` y el secreto `sentry-dsn`.
5. UptimeRobot: monitores a `https://tu-dominio/up` y `https://tu-dominio/api/v1/site`.

## Pruebas

Cada regla de `infra/firewall/*.nft` es una prueba con resultado esperado y obtenido. Algunas:

| Desde | Comando | Esperado |
| --- | --- | --- |
| Cloud Shell | `curl -I https://tu-dominio/` | 200 |
| Cloud Shell | `curl -I https://tu-dominio/panel` | 403 |
| Cloud Shell | `curl -I "https://tu-dominio/api/v1/booking/slots?date=2026-12-01&treatment=1%27%20OR%201=1"` | Alerta en `/var/log/modsec_audit.log` (403 con el WAF en `On`) |
| Cloud Shell | `nmap -Pn -p 22,3306,8080 IP_FIREWALL_PRODUCCION` | Cerrados o filtrados |
| Cloud Shell | `nmap -Pn -p 22,3306 IP_FIREWALL_SEDE` | Abiertos; alerta del honeypot en Wazuh |
| vega-recepcion | `curl -I https://tu-dominio/panel/login` | 200 (por la VPN) |
| vega-recepcion | `nc -zv 10.10.50.10 22` | Bloqueado; `fw-personal-bloqueo` en el firewall de la sede |
| vega-recepcion | `nc -zv 10.20.4.4 80` | Bloqueado: solo el proxy llega a vm-app |
| vega-honeypot | `nc -zv 10.10.30.10 22` | Bloqueado; `fw-dmz-bloqueo` |
| vega-proxy | `nc -zv 10.20.4.4 22` | Bloqueado (la DMZ solo llega al 80 de vm-app) |
| Cloud Shell | 300 peticiones seguidas a `/api/v1/account` | 429 con tu IP, no la del proxy |

Cuando el WAF lleve unos días en detección sin falsos positivos, pásalo a bloqueo en vm-proxy:
`sudo sed -i 's/^SecRuleEngine .*/SecRuleEngine On/' /etc/nginx/modsecurity.conf && sudo systemctl reload nginx`.

## Continuidad

1. **Copia horaria a la sede**: en vm-app, la llave `/etc/vega/dr_key` (su `.pub` va a
   `/home/respaldo/.ssh/authorized_keys` en vm-wazuh). Prueba `sudo /opt/vega/copy-to-dr.sh`.
2. MySQL Flexible guarda copias automáticas 7 días con restauración a un punto en el tiempo (RPO de minutos).
3. **Ejercicio de recuperación** (anota la hora de inicio y de fin):
   1. Detén `vm-app`. UptimeRobot, Datadog y Azure Monitor deben avisar.
   2. Bórrala (`az vm delete -g rg-vega-prod -n vm-app --yes`) y vuelve a crearla: `bash infra/desplegar.sh produccion`.
   3. Publica desde Actions (los secretos vuelven desde Key Vault).
   4. Baja la última copia: `azcopy sync https://STORAGE.blob.core.windows.net/respaldos/base-de-datos /var/www/vega/shared/storage/app/private/respaldos`
      y los archivos de `archivos/clinica` y `archivos/web`.
   5. `sudo -u www-data env DB_USERNAME=consultorio_despliegue DB_PASSWORD=... php artisan respaldo:restaurar`.
   6. Revisa la agenda, un paciente con radiografías y la caja. La diferencia de horas es el **RTO real**.

## Costos: qué apagar

| Recurso | Cobra apagado | Qué hacer |
| --- | --- | --- |
| VM (todas) | Solo el disco | `desplegar.sh apagar` al terminar cada sesión |
| MySQL | Sí | `az mysql flexible-server stop` en pausas largas (Azure lo enciende solo a los 30 días) |
| IP públicas | Sí, poco | Se van al borrar cada grupo |

Revisa el saldo de cada suscripción en https://www.microsoftazuresponsorships.com/balance cada semana. Al terminar
el proyecto: `bash infra/desplegar.sh borrar-sede`.
