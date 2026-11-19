# CE03 · Infraestructura Tecnológica

Informes de la línea de Infraestructura para el sistema de Clínica Vega Implant, con la infraestructura real de
`infra/` (Azure) como evidencia. Las fuentes son Markdown; los Word, sus PDF y los Excel se arman con un comando.

| Entregable | Evidencias | Fuente | Word y PDF (en `word/`) |
| --- | --- | --- | --- |
| Semestre 1 · E1: Análisis y Diseño de Red | CE0311 | `S1-E1-diseno-de-red/` | `CE0311-S1-E1-Analisis-y-Diseno-de-Red` |
| Semestre 1 · E2: Análisis y Diseño de Centro de Datos Híbrido | CE0331 | `S1-E2-diseno-centro-de-datos/` | `CE0331-S1-E2-Analisis-y-Diseno-de-Centro-de-Datos-Hibrido` |
| Semestre 2 · E1: Planificación e Implementación del SGSI | CE0321–CE0324 | `S2-E1-sgsi/` | `CE0321-CE0324-S2-E1-Planificacion-e-Implementacion-del-SGSI` |
| Semestre 2 · E2: Implementación, Testing y Monitoreo de Red | CE0312–CE0313 | `S2-E2-red-implementacion/` | `CE0312-CE0313-S2-E2-Implementacion-Testing-y-Monitoreo-de-Red` |
| Semestre 2 · E3: Implementación, Gestión y Control de Centro de Datos | CE0332–CE0333 | `S2-E3-centro-de-datos-implementacion/` | `CE0332-CE0333-S2-E3-Implementacion-Gestion-y-Control-de-Centro-de-Datos` |

Anexos en Excel (`word/`): inventario de activos, matriz de riesgos y Declaración de Aplicabilidad (S2-E1), y plan
y resultados de pruebas de red (S2-E2). Salen de `datos.py`, igual que las tablas que los informes llevan dentro.

## Presentaciones

`presentaciones/CE03-Infraestructura-Tecnologica` (PowerPoint y PDF) presenta lo hecho en los cinco entregables en una
sola exposición de 26 diapositivas (unos 26 minutos), con un diagrama de la arquitectura completa: cada entregable abre con una portada (número, nombre y quién lo
expone) y sigue con tres diapositivas de contenido con cifras reales. Está en azul y amarillo UPeU y repartida entre los
tres integrantes (Jhoel la red, Fran el centro de datos, Joselyn el SGSI): el círculo amarillo dice quién expone, la
etiqueta del pie de qué entregable es, y las notas del orador lo que se dice. Se arma con
`node presentaciones/generar-ppt.js` (dependencias al inicio del script) y el PDF con `presentaciones/exportar-pdf.ps1`.

## Equivalencia tecnológica

La guía de la línea propone VMware ESXi y AWS. El equipo usa Azure for Students: la sede de la clínica es una
segunda red de Azure (North Central US) con los mismos segmentos y firewall que tendría el local, y cada servicio de
AWS tiene su equivalente de Azure (VPC → VNet, Security Group → NSG, NACL → nftables, VPN Gateway → strongSwan,
CloudWatch → Azure Monitor). El S1-E1 (tabla 2) y el S1-E2 (sección 4) lo explican.

## Carpetas

| Carpeta | Qué tiene |
| --- | --- |
| `diagramas/` | Diagramas en Mermaid (`.mmd`) y su imagen (`.png`); `exportar.sh` las vuelve a generar |
| `evidencias/` | Salidas de los comandos en cada VM del 19/11/2026 (IP públicas reemplazadas) y los scripts de los simulacros |
| `plantilla/` | Plantilla de la UPeU y scripts de formato, los mismos de CE02 con el encabezado de CE03 |
| `word/` | Los Word, sus PDF y los Excel |

## Volver a generar

```bash
pip install pypandoc_binary python-docx pyyaml openpyxl   # si no hay pandoc
bash docs/entregables/CE03-infraestructura/diagramas/exportar.sh   # solo si cambió un diagrama
bash docs/entregables/CE03-infraestructura/generar-word.sh
SOLO=CE0311 bash docs/entregables/CE03-infraestructura/generar-word.sh   # un solo informe
```

El índice y el PDF los hace LibreOffice en Linux o Microsoft Word en Windows (`plantilla/actualizar-indice-word.ps1`).
Ningún archivo de esta carpeta contiene credenciales ni datos personales reales.
