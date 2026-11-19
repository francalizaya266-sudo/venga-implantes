#!/usr/bin/env bash
# Vuelve a una versión anterior del sistema en vm-app sin descargar nada. deploy-app.sh deja las últimas 5 en
# /var/www/vega/releases y /var/www/vega/current apunta a la activa: volver es mover ese enlace.
#
# Uso (en vm-app, como root, o desde GitHub Actions con "Desplegar en Azure" → acción "volver"):
#   bash volver-version.sh                  la versión anterior a la activa
#   bash volver-version.sh 20261119083000   esa versión (el nombre de su carpeta en releases/)
#   bash volver-version.sh --lista          las versiones que hay, con la activa marcada
#
# La base de datos no se toca. Si la versión que falló ya había migrado y la anterior no funciona con ese esquema,
# se restaura la copia de antes de publicar: "php artisan respaldo:restaurar" con el usuario de despliegue
# (E2, copia y restauración; manual técnico del E3, anexo B).
set -euo pipefail

BASE="${VEGA_BASE:-/var/www/vega}"
PHP_FPM="${PHP_FPM:-php8.3-fpm}"
RELEASES="$BASE/releases"

[ -L "$BASE/current" ] || { echo "No hay versión activa: $BASE/current no existe." >&2; exit 1; }
ACTIVA="$(basename "$(readlink "$BASE/current")")"

# De la más nueva a la más vieja.
mapfile -t VERSIONES < <(find "$RELEASES" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort -r)

if [ "${1:-}" = "--lista" ]; then
  for version in "${VERSIONES[@]}"; do
    if [ "$version" = "$ACTIVA" ]; then echo "$version  (activa)"; else echo "$version"; fi
  done
  exit 0
fi

if [ -n "${1:-}" ]; then
  DESTINO="$1"
  [ -d "$RELEASES/$DESTINO" ] || { echo "No existe la versión $DESTINO en $RELEASES." >&2; exit 1; }
else
  DESTINO=""
  for i in "${!VERSIONES[@]}"; do
    if [ "${VERSIONES[$i]}" = "$ACTIVA" ]; then
      DESTINO="${VERSIONES[$((i + 1))]:-}"
      break
    fi
  done
  [ -n "$DESTINO" ] || { echo "No hay una versión anterior a $ACTIVA." >&2; exit 1; }
fi

[ "$DESTINO" != "$ACTIVA" ] || { echo "La versión $DESTINO ya es la activa."; exit 0; }

echo "==> De $ACTIVA a $DESTINO"
cd "$RELEASES/$DESTINO"
# Las cachés de configuración y rutas se arman de nuevo con el .env de hoy (compartido entre versiones).
sudo -u www-data php artisan optimize:clear > /dev/null
sudo -u www-data php artisan optimize > /dev/null

ln -sfn "$RELEASES/$DESTINO" "$BASE/current"
systemctl reload "$PHP_FPM" nginx

echo "Listo: $(readlink "$BASE/current")"
