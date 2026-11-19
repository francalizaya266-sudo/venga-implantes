#!/usr/bin/env bash
# Prueba infra/scripts/volver-version.sh sin Azure: arma una carpeta como /var/www/vega con tres versiones y
# reemplaza sudo y systemctl por registros, para ver a qué versión apunta "current" después de cada paso.
#
#   bash docs/entregables/CE02-ingenieria-de-software/E4-gestion-y-calidad/evidencias/probar-volver-version.sh
set -euo pipefail

RAIZ="$(cd "$(dirname "$0")/../../../../.." && pwd)"
SCRIPT="$RAIZ/infra/scripts/volver-version.sh"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

export VEGA_BASE="$TMP/vega"
mkdir -p "$TMP/bin" "$VEGA_BASE/releases"/{20261117090000,20261118090000,20261119090000}
ln -s "$VEGA_BASE/releases/20261119090000" "$VEGA_BASE/current"

cat > "$TMP/bin/sudo" <<'STUB'
#!/usr/bin/env bash
shift 2
echo "sudo: $* (en $(basename "$PWD"))" >> "$VEGA_BASE/registro.txt"
STUB
cat > "$TMP/bin/systemctl" <<'STUB'
#!/usr/bin/env bash
echo "systemctl $*" >> "$VEGA_BASE/registro.txt"
STUB
chmod +x "$TMP/bin/sudo" "$TMP/bin/systemctl"
export PATH="$TMP/bin:$PATH"

activa() { basename "$(readlink "$VEGA_BASE/current")"; }
comprobar() {
  if [ "$1" = "$2" ]; then echo "  ok: $3"; else echo "  FALLA: $3 (se esperaba $2, quedó $1)"; exit 1; fi
}

echo "1. Lista de versiones"
bash "$SCRIPT" --lista | sed 's/^/   /'
comprobar "$(bash "$SCRIPT" --lista | grep -c '(activa)')" 1 "una sola versión marcada como activa"

echo "2. Volver sin indicar versión"
bash "$SCRIPT" | sed 's/^/   /'
comprobar "$(activa)" 20261118090000 "pasa a la anterior"
comprobar "$(grep -c 'artisan optimize (en 20261118090000)' "$VEGA_BASE/registro.txt")" 1 "rearma las cachés en la versión a la que vuelve"
comprobar "$(grep -c 'systemctl reload php8.3-fpm nginx' "$VEGA_BASE/registro.txt")" 1 "recarga PHP-FPM y Nginx"

echo "3. Volver otra vez"
bash "$SCRIPT" | sed 's/^/   /'
comprobar "$(activa)" 20261117090000 "pasa a la que sigue hacia atrás"

echo "4. Sin versión más vieja"
if bash "$SCRIPT" 2> "$TMP/error.txt"; then comprobar si no "termina con error"; fi
sed 's/^/   /' "$TMP/error.txt"
comprobar "$(activa)" 20261117090000 "deja la activa como estaba"

echo "5. Una versión que no existe"
if bash "$SCRIPT" 20200101000000 2> "$TMP/error.txt"; then comprobar si no "termina con error"; fi
sed 's/^/   /' "$TMP/error.txt"
comprobar "$(activa)" 20261117090000 "deja la activa como estaba"

echo "6. Ir a una versión por su nombre (también hacia adelante)"
bash "$SCRIPT" 20261119090000 | sed 's/^/   /'
comprobar "$(activa)" 20261119090000 "queda la indicada"

echo "Todo en orden."
