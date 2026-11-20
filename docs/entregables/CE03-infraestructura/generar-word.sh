#!/usr/bin/env bash
# Convierte las fuentes .md de cada entregable de CE03 en Word (.docx) con la plantilla de la UPeU, deja su PDF de
# verificación en word/ y arma los anexos en Excel. Uso, desde cualquier carpeta:
#   bash docs/entregables/CE03-infraestructura/generar-word.sh
#
# Necesita pandoc (o "pip install pypandoc_binary"), python-docx, PyYAML y openpyxl. El índice y el PDF los hace
# LibreOffice (Linux) o Microsoft Word (Windows, si no hay LibreOffice). Los diagramas: diagramas/exportar.sh.
set -euo pipefail

cd "$(dirname "$0")"
mkdir -p word

PANDOC="${PANDOC:-$(command -v pandoc || python -c 'import pypandoc; print(pypandoc.get_pandoc_path())')}"
PY="$(command -v python || command -v python3)"
# pandoc separa las rutas de --resource-path con ";" en Windows y con ":" en Linux.
case "$(uname -s)" in MINGW*|MSYS*|CYGWIN*) SEP=';' ;; *) SEP=':' ;; esac

if [ ! -f plantilla/plantilla-upeu.docx ]; then
  "$PANDOC" -o plantilla/base.docx --print-default-data-file reference.docx
  "$PY" plantilla/crear-plantilla.py plantilla/base.docx plantilla/plantilla-upeu.docx
  rm plantilla/base.docx
fi

indice() {
  if command -v soffice >/dev/null 2>&1; then
    "$PY" plantilla/actualizar-indice.py "$1"
  else
    powershell.exe -NoProfile -ExecutionPolicy Bypass -File plantilla/actualizar-indice-word.ps1 "$1"
  fi
}

# generar <archivo de salida> <metadatos .yaml> <fuentes .md…>
# INDICE=2 deja el índice en dos niveles y SEGUIDAS=1 no empieza página en cada sección desde la 2.
generar() {
  # SOLO=CE0311 bash generar-word.sh arma solo los Word cuyo nombre contiene ese texto.
  case "$1" in *"${SOLO:-}"*) ;; *) return 0 ;; esac
  local salida="word/$1" metadatos="$2"
  shift 2
  local carpeta
  carpeta=$(dirname "$1")
  "$PANDOC" "$@" \
    --metadata-file="$metadatos" \
    --reference-doc=plantilla/plantilla-upeu.docx \
    --lua-filter=plantilla/word.lua \
    --toc --toc-depth="${INDICE:-3}" --number-sections \
    --resource-path=".${SEP}$carpeta" \
    -o "$salida"
  "$PY" plantilla/ajustar-docx.py "$salida" "$metadatos" ${SEGUIDAS:+--secciones-seguidas}
  indice "$salida"
  local pdf="${salida%.docx}.pdf"
  if command -v pdfinfo >/dev/null 2>&1; then
    printf '%s: %s páginas\n' "$salida" "$(pdfinfo "$pdf" | awk '/^Pages:/ {print $2}')"
  else
    printf '%s: listo\n' "$salida"
  fi
}

# Primero los anexos en Excel (inventario de activos, matriz de riesgos, SoA y pruebas de red): también escriben
# esas tablas dentro de los .md del SGSI y de la red, así el Word y el Excel salen de los mismos datos (datos.py).
"$PY" generar-anexos.py

# SEMESTRE 1 · E1: Análisis y Diseño de Red (CE0311).
INDICE=2 generar CE0311-S1-E1-Analisis-y-Diseno-de-Red.docx S1-E1-diseno-de-red/metadatos.yaml \
  S1-E1-diseno-de-red/documento.md

# SEMESTRE 1 · E2: Análisis y Diseño de Centro de Datos Híbrido (CE0331).
INDICE=2 generar CE0331-S1-E2-Analisis-y-Diseno-de-Centro-de-Datos-Hibrido.docx \
  S1-E2-diseno-centro-de-datos/metadatos.yaml S1-E2-diseno-centro-de-datos/documento.md

# SEMESTRE 2 · E1: Planificación e Implementación del SGSI (CE0321–CE0324).
INDICE=2 generar CE0321-CE0324-S2-E1-Planificacion-e-Implementacion-del-SGSI.docx S2-E1-sgsi/metadatos.yaml \
  S2-E1-sgsi/documento.md

# SEMESTRE 2 · E2: Implementación, Testing y Monitoreo de Red (CE0312–CE0313).
INDICE=2 generar CE0312-CE0313-S2-E2-Implementacion-Testing-y-Monitoreo-de-Red.docx \
  S2-E2-red-implementacion/metadatos.yaml S2-E2-red-implementacion/documento.md

# SEMESTRE 2 · E3: Implementación, Gestión y Control de Centro de Datos (CE0332–CE0333).
INDICE=2 generar CE0332-CE0333-S2-E3-Implementacion-Gestion-y-Control-de-Centro-de-Datos.docx \
  S2-E3-centro-de-datos-implementacion/metadatos.yaml S2-E3-centro-de-datos-implementacion/documento.md
