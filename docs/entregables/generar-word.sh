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
# INDICE=2 deja el índice en dos niveles y SEGUIDAS=1 no empieza página en cada sección desde la 2.
generar() {
  local salida="word/$1" metadatos="$2"
  shift 2
  # Las imágenes se buscan junto al primer .md (cada entregable tiene sus diagramas en su carpeta).
  local carpeta
  carpeta=$(dirname "$1")
  pandoc "$@" \
    --metadata-file="$metadatos" \
    --reference-doc=plantilla/plantilla-upeu.docx \
    --lua-filter=plantilla/word.lua \
    --toc --toc-depth="${INDICE:-3}" --number-sections \
    --resource-path=".:$carpeta" \
    -o "$salida"
  python3 plantilla/ajustar-docx.py "$salida" ${SEGUIDAS:+--secciones-seguidas}
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

# FASE 1: E1, requerimientos y diseño del sistema (CE021: CE0211–CE0214).
INDICE=2 SEGUIDAS=1 generar CE021-S1-E1-Requerimientos-y-Diseno-del-Sistema.docx E1-requerimientos-y-diseno/E1-metadatos.yaml \
  E1-requerimientos-y-diseno/E1-documento.md

# Anexos del E1: matriz de trazabilidad (horizontal), validación con usuarios, clases detalladas y Gherkin.
INDICE=2 generar CE021-S1-E1-Requerimientos-y-Diseno-del-Sistema-Anexos.docx \
  E1-requerimientos-y-diseno/anexos/E1-anexos-metadatos.yaml E1-requerimientos-y-diseno/anexos/E1-anexos.md

# FASE 2: E2, plataforma de datos del sistema (CE022: CE0221–CE0224).
INDICE=2 SEGUIDAS=1 generar CE022-S2-E2-Plataforma-de-Datos-del-Sistema.docx E2-plataforma-de-datos/E2-metadatos.yaml \
  E2-plataforma-de-datos/E2-documento.md

# Anexos del E2: diccionario de datos, modelo lógico por dominio, scripts, evidencias y manual de administración.
INDICE=2 generar CE022-S2-E2-Plataforma-de-Datos-del-Sistema-Anexos.docx \
  E2-plataforma-de-datos/anexos/E2-anexos-metadatos.yaml E2-plataforma-de-datos/anexos/E2-diccionario.md \
  E2-plataforma-de-datos/anexos/E2-anexos.md

# FASE 3: E3, sistema de software funcional integrado (CE023: CE0233 y CE0234).
INDICE=2 SEGUIDAS=1 generar CE023-S2-E3-Sistema-de-Software-Funcional-Integrado.docx E3-sistema-funcional/E3-metadatos.yaml \
  E3-sistema-funcional/E3-documento.md

# Anexos del E3: repositorio, manual técnico, capturas, evidencia de despliegue y ejecución, y sustentación.
INDICE=2 generar CE023-S2-E3-Sistema-de-Software-Funcional-Integrado-Anexos.docx \
  E3-sistema-funcional/anexos/E3-anexos-metadatos.yaml E3-sistema-funcional/anexos/E3-anexos.md

# FASE 4: E4, gestión del proyecto y calidad de software (CE024: CE0241–CE0244).
INDICE=2 SEGUIDAS=1 generar CE024-S2-E4-Gestion-del-Proyecto-y-Calidad-de-Software.docx E4-gestion-y-calidad/E4-metadatos.yaml \
  E4-gestion-y-calidad/E4-documento.md

# Anexos del E4: reportes de pruebas, pipeline y observabilidad, artefactos ágiles, checklists y sustentación.
INDICE=2 generar CE024-S2-E4-Gestion-del-Proyecto-y-Calidad-de-Software-Anexos.docx \
  E4-gestion-y-calidad/anexos/E4-anexos-metadatos.yaml E4-gestion-y-calidad/anexos/E4-anexos.md
