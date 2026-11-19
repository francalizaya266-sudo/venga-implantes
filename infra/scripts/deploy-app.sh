#!/usr/bin/env bash
# Publica una versión del sistema en vm-app.
#
# Uso (en vm-app):  sudo bash deploy-app.sh consultorio-para-hosting.zip
#
# El .zip es el paquete que deja GitHub Actions en cada cambio de main (Actions → Pruebas → Artifacts).
# Cada versión queda en /var/www/vega/releases/<fecha>; /var/www/vega/current apunta a la activa.
# El .env, storage/ (copias, radiografías, firmas) y public/media viven en /var/www/vega/shared y se conservan.
#
# Necesita:
#   /etc/vega/vega.env  KEY_VAULT=kv-vega-xxxxxxxx  y  STORAGE_ACCOUNT=stvegaxxxxxxxx
#   /etc/vega/app.env   la configuración sin secretos (infra/env/produccion.env, completada)
# Los secretos se leen de Key Vault con la identidad administrada de la VM.
set -euo pipefail

ZIP="${1:?Indica el .zip del paquete: sudo bash deploy-app.sh consultorio-para-hosting.zip}"
BASE=/var/www/vega
SHARED="$BASE/shared"
RELEASE="$BASE/releases/$(date +%Y%m%d%H%M%S)"
INFRA_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PHP_FPM=php8.3-fpm

# shellcheck disable=SC1091
source "$INFRA_DIR/scripts/kv.sh"

echo "==> Descomprimiendo en $RELEASE"
mkdir -p "$RELEASE" "$SHARED/storage" "$SHARED/media"
unzip -q "$ZIP" -d "$RELEASE"
if [ -d "$RELEASE/backend" ]; then
  shopt -s dotglob
  mv "$RELEASE/backend"/* "$RELEASE"/
  rmdir "$RELEASE/backend"
  shopt -u dotglob
fi

echo "==> Carpetas compartidas"
if [ ! -d "$SHARED/storage/framework" ]; then
  cp -a "$RELEASE/storage/." "$SHARED/storage/"
fi
rm -rf "$RELEASE/storage"
ln -s "$SHARED/storage" "$RELEASE/storage"

if [ -d "$RELEASE/public/media" ] && [ -z "$(ls -A "$SHARED/media")" ]; then
  cp -a "$RELEASE/public/media/." "$SHARED/media/"
fi
rm -rf "$RELEASE/public/media"
ln -s "$SHARED/media" "$RELEASE/public/media"

echo "==> .env (configuración + secretos de Key Vault)"
[ -f /etc/vega/app.env ] || { echo "Falta /etc/vega/app.env (copia infra/env/produccion.env y complétalo)." >&2; exit 1; }
{
  cat /etc/vega/app.env
  echo
  echo "# Secretos leídos de Key Vault $KEY_VAULT el $(date -Is)"
  echo "APP_KEY=$(kv_secret app-key)"
  echo "DB_PASSWORD=$(kv_secret db-password)"
  echo "MAIL_PASSWORD=$(kv_secret mail-password || true)"
  echo "WHATSAPP_CLOUD_TOKEN=$(kv_secret whatsapp-cloud-token || true)"
  echo "DNI_LOOKUP_TOKEN=$(kv_secret dni-lookup-token || true)"
  echo "SENTRY_LARAVEL_DSN=$(kv_secret sentry-dsn || true)"
} > "$SHARED/.env.nuevo"
mv "$SHARED/.env.nuevo" "$SHARED/.env"
chown root:www-data "$SHARED/.env"
chmod 640 "$SHARED/.env"
ln -sf "$SHARED/.env" "$RELEASE/.env"

echo "==> Dependencias de PHP"
cd "$RELEASE"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --no-progress

chown -R www-data:www-data "$SHARED/storage" "$SHARED/media" "$RELEASE/bootstrap/cache"

echo "==> Migraciones con consultorio_despliegue (el usuario de la app no puede cambiar la estructura)"
sudo -u www-data php artisan config:clear
DEPLOY_PASSWORD="$(kv_secret db-password-despliegue)"
sudo -u www-data env DB_USERNAME=consultorio_despliegue DB_PASSWORD="$DEPLOY_PASSWORD" \
  php artisan migrate --force
unset DEPLOY_PASSWORD

echo "==> Cachés"
sudo -u www-data php artisan optimize

echo "==> Activando la versión"
ln -sfn "$RELEASE" "$BASE/current"

# nginx: solo la primera vez.
if [ ! -f /etc/nginx/sites-available/vegaimplant.conf ]; then
  install -m 644 "$INFRA_DIR/nginx/vegaimplant.conf" /etc/nginx/sites-available/vegaimplant.conf
  ln -sf /etc/nginx/sites-available/vegaimplant.conf /etc/nginx/sites-enabled/vegaimplant.conf
fi

# Scripts y tareas programadas.
install -m 644 "$INFRA_DIR/scripts/kv.sh" /opt/vega/kv.sh
install -m 755 "$INFRA_DIR/scripts/backup-to-blob.sh" /opt/vega/backup-to-blob.sh
install -m 755 "$INFRA_DIR/scripts/copy-to-dr.sh" /opt/vega/copy-to-dr.sh
cat > /etc/cron.d/vega <<'CRON'
# El único cron que necesita Laravel: recordatorios, cola, copias diarias a las 3:00.
* * * * * www-data php /var/www/vega/current/artisan schedule:run >> /dev/null 2>&1
# Copia a Blob después de la copia diaria del sistema.
30 3 * * * root /opt/vega/backup-to-blob.sh >> /var/log/vega-backup.log 2>&1
# Copia horaria de la base de datos a la sede de la clínica (solo funciona con la VPN y vm-respaldo encendidas).
15 * * * * root /opt/vega/copy-to-dr.sh >> /var/log/vega-dr.log 2>&1
CRON
chmod 644 /etc/cron.d/vega

nginx -t
systemctl reload "$PHP_FPM" nginx

echo "==> Dejando las últimas 5 versiones"
ls -1dt "$BASE"/releases/* | tail -n +6 | xargs -r rm -rf

echo "Listo: $(readlink "$BASE/current")"
