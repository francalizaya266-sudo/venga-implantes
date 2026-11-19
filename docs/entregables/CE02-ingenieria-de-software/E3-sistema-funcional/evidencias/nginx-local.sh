#!/usr/bin/env bash
# Levanta en esta máquina una copia de infra/nginx/proxy.conf (el Nginx de vm-proxy en Azure) delante del
# servidor de desarrollo de Laravel, para medir la web con la misma compresión y caché que tendrá producción.
# Cambia solo lo que no aplica fuera de Azure: sin ModSecurity (el módulo no está instalado), el upstream es
# 127.0.0.1:8000, escucha en 8002 y pasa el puerto en Host para que Laravel arme bien las URL de los archivos.
#
# Uso: bash nginx-local.sh [carpeta de trabajo]    (necesita nginx: sudo apt-get install nginx-core)
#      node medir-web.cjs http://127.0.0.1:8002 / /reservar …
#      nginx -c <carpeta>/nginx.conf -s stop
set -euo pipefail

DIR="${1:-$(mktemp -d)}"
ROOT="$(cd "$(dirname "$0")/../../../../.." && pwd)"
mkdir -p "$DIR/conf.d" "$DIR/cache"

sed -e '/modsecurity/d' \
  -e 's#server 10.20.4.4:80;#server 127.0.0.1:8000;#' \
  -e 's#listen 80;#listen 8002;#' -e '/listen \[::\]:80;/d' \
  -e "s#/var/cache/nginx/vega#$DIR/cache#" -e "s#/var/log/nginx/vega#$DIR/vega#g" \
  -e 's#proxy_set_header Host \$host;#proxy_set_header Host $http_host;#' \
  "$ROOT/infra/nginx/proxy.conf" > "$DIR/conf.d/vega.conf"

# Igual que el nginx.conf de Ubuntu 24.04: gzip on (solo HTML) en el bloque http.
cat > "$DIR/nginx.conf" <<CONF
user root;
worker_processes 1;
pid $DIR/nginx.pid;
error_log $DIR/error.log;
events { worker_connections 64; }
http {
  include /etc/nginx/mime.types;
  default_type application/octet-stream;
  gzip on;
  include $DIR/conf.d/*.conf;
}
CONF

nginx -t -c "$DIR/nginx.conf"
nginx -c "$DIR/nginx.conf"
echo "Nginx de vm-proxy en http://127.0.0.1:8002 (carpeta $DIR)"
