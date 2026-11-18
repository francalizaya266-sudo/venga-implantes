#!/usr/bin/env bash
# Convierte las fuentes .md de cada entregable en Word (.docx) con la plantilla de la UPeU y deja su PDF de
# verificación en word/. Uso, desde cualquier carpeta:  bash docs/entregables/generar-word.sh
# Necesita pandoc, LibreOffice (con su módulo de Python) y pdfinfo (poppler-utils).
set -euo pipefail

cd "$(dirname "$0")"
mkdir -p word

if [ ! -f plantilla/plantilla-upeu.docx ]; then
  pandoc -o plantilla/base.docx --print-default-data-file reference.docx
  python3 plantilla/crear-plantilla.py plantilla/base.docx plantilla/plantilla-upeu.docx
  rm plantilla/base.docx
fi

# generar <archivo de salida> <metadatos .yaml> <fuentes .md…>
generar() {
  local salida="word/$1" metadatos="$2"
  shift 2
  pandoc "$@" \
    --metadata-file="$metadatos" \
    --reference-doc=plantilla/plantilla-upeu.docx \
    --lua-filter=plantilla/word.lua \
    --toc --toc-depth=3 --number-sections \
    --resource-path=. \
    -o "$salida"
  python3 plantilla/actualizar-indice.py "$salida"
  local pdf="${salida%.docx}.pdf"
  local paginas
  paginas=$(pdfinfo "$pdf" | awk '/^Pages:/ {print $2}')
  local pendientes
  pendientes=$(pdftotext "$pdf" - | grep -o 'PENDIENTE' | wc -l)
  printf '%s: %s páginas, %s [PENDIENTE]\n' "$salida" "$paginas" "$pendientes"
}

# FASE 0: análisis previo y diagnóstico frente a la rúbrica.
generar CE02-00-Analisis-y-Diagnostico.docx 00-metadatos.yaml \
  00-analisis-del-proyecto.md 00-estado-actual.md
