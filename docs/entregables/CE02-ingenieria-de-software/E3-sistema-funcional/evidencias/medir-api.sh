#!/usr/bin/env bash
# Mide el tiempo de respuesta de las rutas de lectura de la API /api/v1 (RNF-REN-02: bajo 500 ms con los datos del
# seeder). Hace 3 pedidos de calentamiento y luego N pedidos seguidos por ruta, y escribe una tabla en Markdown con
# la mediana, el percentil 95 y el máximo. Solo pide rutas GET: no crea citas ni envía mensajes.
#
# Uso: bash medir-api.sh [URL base] [pedidos por ruta]
#      bash medir-api.sh http://127.0.0.1:8000 30
set -euo pipefail

BASE="${1:-http://127.0.0.1:8000}"
N="${2:-30}"
API="$BASE/api/v1"

# El primer tratamiento que se reserva en la web y su primer día con horarios libres, tal como los da la API.
OPTIONS=$(curl -fsS "$API/booking/options")
TREATMENT=$(python3 -c 'import json,sys; t=json.load(sys.stdin)["data"]["treatments"]; print(t[-1]["id"])' <<<"$OPTIONS")
SLUG=$(python3 -c 'import json,sys; t=json.load(sys.stdin)["data"]["treatments"]; print(t[-1]["slug"])' <<<"$OPTIONS")
DAY=$(curl -fsS "$API/booking/days?treatment=$TREATMENT" | python3 -c 'import json,sys; print(json.load(sys.stdin)["data"]["date"] or "")')
PROMO=$(curl -fsS "$API/promotions" | python3 -c 'import json,sys; c=json.load(sys.stdin)["data"]["campaigns"]; print(c[0]["slug"] if c else "")')

ROUTES=(
  "site"
  "home"
  "treatments"
  "treatments/$SLUG"
  "promotions"
  ${PROMO:+"promotions/$PROMO"}
  "team"
  "cases"
  "reviews"
  "faqs"
  "pages/contact"
  "booking/options"
  "booking/days?treatment=$TREATMENT"
  ${DAY:+"booking/slots?treatment=$TREATMENT&date=$DAY"}
  "account"
  "links/00000000000000000000000000000000"
)

echo "| Ruta (GET /api/v1/…) | Estado | Mediana (ms) | P95 (ms) | Máximo (ms) | Tamaño (KB) |"
echo "|---|---|---|---|---|---|"

for route in "${ROUTES[@]}"; do
  for _ in 1 2 3; do curl -fsS -o /dev/null "$API/$route" || true; done

  samples=$(for _ in $(seq "$N"); do
    curl -sS -o /dev/null -H 'Accept: application/json' -w '%{http_code} %{time_total} %{size_download}\n' "$API/$route"
  done)

  python3 - "$route" <<PY
import statistics, sys
rows = [line.split() for line in """$samples""".strip().splitlines()]
times = sorted(float(t) * 1000 for _, t, _ in rows)
p95 = times[max(0, round(0.95 * len(times)) - 1)]
status = ",".join(sorted({code for code, _, _ in rows}))
size = int(rows[-1][2]) / 1024
print(f"| \`{sys.argv[1]}\` | {status} | {statistics.median(times):.0f} | {p95:.0f} | {times[-1]:.0f} | {size:.1f} |")
PY
done
