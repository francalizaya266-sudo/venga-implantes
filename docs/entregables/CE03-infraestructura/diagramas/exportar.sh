#!/usr/bin/env bash
# Exporta cada diagrama .mmd a .png con el tema de la plantilla. Necesita mermaid-cli (npx @mermaid-js/mermaid-cli)
# y un Chrome: en Windows se usa el instalado (PUPPETEER_EXECUTABLE_PATH o la ruta por defecto).
set -euo pipefail
cd "$(dirname "$0")"
MMDC="${MMDC:-npx -y @mermaid-js/mermaid-cli@11.4.2}"
CHROME="${PUPPETEER_EXECUTABLE_PATH:-C:/Program Files/Google/Chrome/Application/chrome.exe}"
CONF=$(mktemp)
printf '{"executablePath":"%s","args":["--no-sandbox"]}' "$CHROME" > "$CONF"
for f in *.mmd; do
  $MMDC -i "$f" -o "${f%.mmd}.png" -c ../plantilla/tema-diagramas.json -p "$CONF" -w 1600 -s 2 -b white >/dev/null
  echo "${f%.mmd}.png"
done
rm -f "$CONF"
