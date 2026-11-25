"""Crea plantilla-upeu.docx: los estilos que pandoc usa para armar cada entregable en Word.

Uso (desde docs/entregables/CE03-infraestructura):
    pandoc -o plantilla/base.docx --print-default-data-file reference.docx
    python3 plantilla/crear-plantilla.py plantilla/base.docx plantilla/plantilla-upeu.docx

A4, márgenes de 2,5 cm, Arial 11 con interlineado 1,5, títulos numerados por pandoc (1, 1.1, 1.1.1) en azul,
con una línea bajo cada sección principal, salto de página antes de cada una, encabezado con el nombre del
sistema y una línea, "Página N de M" en el pie (la portada no lleva), tablas con bordes suaves, encabezado azul
oscuro y texto de 9 puntos, leyendas de figuras y tablas con el rótulo en negrita, código sobre fondo gris claro,
y el estilo "Pendiente" en amarillo para los [PENDIENTE]. La portada la arma plantilla/ajustar-docx.py.
"""

import sys

from docx import Document
from docx.enum.section import WD_SECTION
from docx.enum.style import WD_STYLE_TYPE
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_LINE_SPACING
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Pt, RGBColor

SISTEMA = 'Sistema web de Clínica Vega Implant'
FUENTE = 'Arial'
AZUL = RGBColor(0x1F, 0x38, 0x64)
ACENTO = RGBColor(0x2F, 0x54, 0x96)
GRIS = RGBColor(0x59, 0x59, 0x59)
# Los mismos colores en hexadecimal, para los bordes y fondos que se escriben en el XML.
AZUL_HEX, ACENTO_HEX, LINEA_HEX, FONDO_CODIGO_HEX = '1F3864', '2F5496', 'C9D3E3', 'F4F6FA'


# Orden de los hijos de w:pPr que pide el esquema de Word; uno fuera de orden puede hacer que Word no abra el archivo.
ORDEN_PPR = ('pStyle', 'keepNext', 'keepLines', 'pageBreakBefore', 'framePr', 'widowControl', 'numPr',
             'suppressLineNumbers', 'pBdr', 'shd', 'tabs', 'suppressAutoHyphens', 'kinsoku', 'wordWrap',
             'overflowPunct', 'topLinePunct', 'autoSpaceDE', 'autoSpaceDN', 'bidi', 'adjustRightInd', 'snapToGrid',
             'spacing', 'ind', 'contextualSpacing', 'mirrorIndents', 'suppressOverlap', 'jc', 'textDirection',
             'textAlignment', 'textboxTightWrap', 'outlineLvl', 'divId', 'cnfStyle', 'rPr', 'sectPr', 'pPrChange')


def insertar_en_orden(ppr, elemento):
    """Pone el elemento en w:pPr antes del primer hijo que el esquema pide después de él."""
    nombre = elemento.tag.split('}')[1]
    despues = {qn(f'w:{n}') for n in ORDEN_PPR[ORDEN_PPR.index(nombre) + 1:]}
    for hijo in ppr:
        if hijo.tag in despues:
            hijo.addprevious(elemento)
            return elemento
    ppr.append(elemento)
    return elemento


def borde_de_parrafo(style, lado, grosor, color, espacio=4):
    """Línea en un lado del párrafo (grosor en octavos de punto), como la que va bajo cada sección principal."""
    ppr = style.element.get_or_add_pPr() if hasattr(style, 'element') else style
    bordes = ppr.find(qn('w:pBdr'))
    if bordes is None:
        bordes = insertar_en_orden(ppr, OxmlElement('w:pBdr'))
    for viejo in bordes.findall(qn(f'w:{lado}')):
        bordes.remove(viejo)
    b = OxmlElement(f'w:{lado}')
    b.set(qn('w:val'), 'single')
    b.set(qn('w:sz'), str(grosor))
    b.set(qn('w:space'), str(espacio))
    b.set(qn('w:color'), color)
    bordes.append(b)


def fondo_de_parrafo(style, color):
    ppr = style.element.get_or_add_pPr()
    for viejo in ppr.findall(qn('w:shd')):
        ppr.remove(viejo)
    shd = OxmlElement('w:shd')
    shd.set(qn('w:val'), 'clear')
    shd.set(qn('w:color'), 'auto')
    shd.set(qn('w:fill'), color)
    insertar_en_orden(ppr, shd)


def fuente(style, size=None, bold=None, italic=None, color=None, name=FUENTE):
    style.font.name = name
    rpr = style.element.get_or_add_rPr()
    fonts = rpr.find(qn('w:rFonts'))
    if fonts is None:
        fonts = OxmlElement('w:rFonts')
        rpr.append(fonts)
    for attr in ('w:ascii', 'w:hAnsi', 'w:cs', 'w:eastAsia'):
        fonts.set(qn(attr), name)
    for attr in ('w:asciiTheme', 'w:hAnsiTheme', 'w:cstheme', 'w:eastAsiaTheme'):
        if fonts.get(qn(attr)) is not None:
            del fonts.attrib[qn(attr)]
    if size is not None:
        style.font.size = Pt(size)
    if bold is not None:
        style.font.bold = bold
    if italic is not None:
        style.font.italic = italic
    if color is not None:
        style.font.color.rgb = color


def parrafo(style, before=0, after=6, line=1.5, align=None, keep_next=None, page_break=None):
    pf = style.paragraph_format
    pf.space_before = Pt(before)
    pf.space_after = Pt(after)
    pf.line_spacing_rule = WD_LINE_SPACING.MULTIPLE
    pf.line_spacing = line
    if align is not None:
        pf.alignment = align
    if keep_next is not None:
        pf.keep_with_next = keep_next
    if page_break is not None:
        pf.page_break_before = page_break


def estilo(doc, nombre, tipo=WD_STYLE_TYPE.PARAGRAPH, base=None):
    for existente in doc.styles:
        if existente.name == nombre:
            return existente
    nuevo = doc.styles.add_style(nombre, tipo)
    if base is not None:
        nuevo.base_style = estilo(doc, base)
    return nuevo


def nivel_de_esquema(style, nivel):
    """Nivel de esquema del título: lo usan el índice de Word y el de LibreOffice."""
    ppr = style.element.get_or_add_pPr()
    for viejo in ppr.findall(qn('w:outlineLvl')):
        ppr.remove(viejo)
    lvl = OxmlElement('w:outlineLvl')
    lvl.set(qn('w:val'), str(nivel))
    insertar_en_orden(ppr, lvl)


def idioma_por_defecto(doc, codigo='es-PE'):
    rpr_default = doc.styles.element.find(qn('w:docDefaults')).find(qn('w:rPrDefault')).find(qn('w:rPr'))
    lang = rpr_default.find(qn('w:lang'))
    if lang is None:
        lang = OxmlElement('w:lang')
        rpr_default.append(lang)
    for attr in ('w:val', 'w:eastAsia', 'w:bidi'):
        lang.set(qn(attr), codigo)


def bordes_de_tabla(style):
    tblpr = style.element.find(qn('w:tblPr'))
    if tblpr is None:
        tblpr = OxmlElement('w:tblPr')
        style.element.append(tblpr)
    for viejo in tblpr.findall(qn('w:tblBorders')):
        tblpr.remove(viejo)
    bordes = OxmlElement('w:tblBorders')
    for lado, grosor, color in (('top', 8, AZUL_HEX), ('left', 4, LINEA_HEX), ('bottom', 8, AZUL_HEX),
                                ('right', 4, LINEA_HEX), ('insideH', 4, LINEA_HEX), ('insideV', 4, LINEA_HEX)):
        b = OxmlElement(f'w:{lado}')
        b.set(qn('w:val'), 'single')
        b.set(qn('w:sz'), str(grosor))
        b.set(qn('w:space'), '0')
        b.set(qn('w:color'), color)
        bordes.append(b)
    tblpr.append(bordes)
    margenes = OxmlElement('w:tblCellMar')
    for lado, valor in (('left', '80'), ('right', '80'), ('top', '30'), ('bottom', '30')):
        m = OxmlElement(f'w:{lado}')
        m.set(qn('w:w'), valor)
        m.set(qn('w:type'), 'dxa')
        margenes.append(m)
    tblpr.append(margenes)
    # Primera fila: fondo azul oscuro, texto blanco en negrita. Las filas pares del cuerpo, gris azulado muy claro.
    # ajustar-docx.py pone los mismos colores directo en cada celda, porque no todos los programas leen estas reglas.
    for viejo in style.element.findall(qn('w:tblStylePr')):
        style.element.remove(viejo)
    for tipo, relleno, negrita, color in (('firstRow', AZUL_HEX, True, 'FFFFFF'), ('band2Horz', 'F3F6FA', False, None)):
        regla = OxmlElement('w:tblStylePr')
        regla.set(qn('w:type'), tipo)
        rpr = OxmlElement('w:rPr')
        if negrita:
            rpr.append(OxmlElement('w:b'))
        if color:
            c = OxmlElement('w:color')
            c.set(qn('w:val'), color)
            rpr.append(c)
        regla.append(rpr)
        tcpr = OxmlElement('w:tcPr')
        shd = OxmlElement('w:shd')
        shd.set(qn('w:val'), 'clear')
        shd.set(qn('w:color'), 'auto')
        shd.set(qn('w:fill'), relleno)
        tcpr.append(shd)
        regla.append(tcpr)
        style.element.append(regla)


def texto_de_pie(parrafo_pie, texto):
    run = parrafo_pie.add_run(texto)
    run.font.size, run.font.color.rgb = Pt(9), GRIS


def campo(parrafo_pie, codigo):
    for tipo, texto in (('begin', None), (None, f' {codigo} '), ('separate', None), (None, '1'), ('end', None)):
        r = parrafo_pie.add_run()
        r.font.size, r.font.color.rgb = Pt(9), GRIS
        if tipo:
            fld = OxmlElement('w:fldChar')
            fld.set(qn('w:fldCharType'), tipo)
            r._r.append(fld)
        elif texto.strip() == codigo:
            instr = OxmlElement('w:instrText')
            instr.set(qn('xml:space'), 'preserve')
            instr.text = texto
            r._r.append(instr)
        else:
            r.text = texto


def campo_pagina(parrafo_pie):
    texto_de_pie(parrafo_pie, 'Página ')
    campo(parrafo_pie, 'PAGE')
    texto_de_pie(parrafo_pie, ' de ')
    campo(parrafo_pie, 'NUMPAGES')


def main(entrada, salida):
    doc = Document(entrada)
    idioma_por_defecto(doc)

    for nombre in ('Normal', 'Body Text', 'First Paragraph', 'Block Text', 'Definition', 'Definition Term'):
        s = estilo(doc, nombre)
        fuente(s, size=11)
        parrafo(s, after=6, line=1.5, align=WD_ALIGN_PARAGRAPH.JUSTIFY if nombre != 'Normal' else None)

    compacto = estilo(doc, 'Compact')
    fuente(compacto, size=10)
    parrafo(compacto, after=2, line=1.15)
    # Texto de las celdas: plantilla/ajustar-docx.py lo aplica después de pandoc, que usa "Compact" en las tablas.
    texto_tabla = estilo(doc, 'Texto de tabla', base='Compact')
    fuente(texto_tabla, size=9)
    parrafo(texto_tabla, after=1, line=1.0)

    colores = {1: AZUL, 2: ACENTO, 3: AZUL, 4: GRIS}
    for nivel, (size, before, after) in {1: (16, 0, 8), 2: (13, 12, 6), 3: (12, 10, 4), 4: (11, 8, 4)}.items():
        s = estilo(doc, f'Heading {nivel}')
        fuente(s, size=size, bold=True, italic=False, color=colores[nivel])
        parrafo(s, before=before, after=after, line=1.15, keep_next=True, page_break=(nivel == 1))
        nivel_de_esquema(s, nivel - 1)
    borde_de_parrafo(estilo(doc, 'Heading 1'), 'bottom', 12, ACENTO_HEX, espacio=2)

    titulo = estilo(doc, 'Title')
    fuente(titulo, size=24, bold=True, color=AZUL)
    parrafo(titulo, before=150, after=18, line=1.15, align=WD_ALIGN_PARAGRAPH.CENTER)
    subtitulo = estilo(doc, 'Subtitle')
    fuente(subtitulo, size=14, italic=False, color=GRIS)
    parrafo(subtitulo, before=0, after=60, line=1.15, align=WD_ALIGN_PARAGRAPH.CENTER)
    for nombre in ('Author', 'Date'):
        s = estilo(doc, nombre)
        fuente(s, size=12)
        parrafo(s, before=0, after=6, line=1.15, align=WD_ALIGN_PARAGRAPH.CENTER)
    resumen = estilo(doc, 'Abstract')
    fuente(resumen, size=11)
    parrafo(resumen, after=6, line=1.5, align=WD_ALIGN_PARAGRAPH.JUSTIFY)

    indice = estilo(doc, 'TOC Heading')
    fuente(indice, size=16, bold=True, color=AZUL)
    parrafo(indice, before=0, after=12, line=1.15, page_break=True)
    borde_de_parrafo(indice, 'bottom', 12, ACENTO_HEX, espacio=4)
    # Entradas del índice: las secciones principales en negrita y azul, las demás con sangría.
    for nivel, (negrita, color, antes, sangria) in {1: (True, AZUL, 4, 0), 2: (False, None, 0, 0.5), 3: (False, None, 0, 1.0)}.items():
        entrada = estilo(doc, f'TOC {nivel}', base='Normal')
        fuente(entrada, size=10, bold=negrita, color=color)
        parrafo(entrada, before=antes, after=1, line=1.1)
        entrada.paragraph_format.left_indent = Cm(sangria)

    for nombre in ('Caption', 'Table Caption', 'Image Caption'):
        s = estilo(doc, nombre)
        fuente(s, size=9, italic=True, bold=False, color=GRIS)
        parrafo(s, before=4, after=10, line=1.15, align=WD_ALIGN_PARAGRAPH.CENTER, keep_next=(nombre == 'Table Caption'))
    # "Tabla 3." o "Figura 2." al inicio de la leyenda: negrita, azul y sin cursiva (plantilla/word.lua lo marca).
    rotulo = estilo(doc, 'Rotulo de leyenda', tipo=WD_STYLE_TYPE.CHARACTER)
    fuente(rotulo, bold=True, italic=False, color=AZUL)
    figura = estilo(doc, 'Figure')
    parrafo(figura, before=6, after=0, line=1.0, align=WD_ALIGN_PARAGRAPH.CENTER, keep_next=True)
    captioned = estilo(doc, 'Captioned Figure')
    parrafo(captioned, before=6, after=0, line=1.0, align=WD_ALIGN_PARAGRAPH.CENTER, keep_next=True)

    codigo = estilo(doc, 'Source Code', base='Normal')
    fuente(codigo, size=9, name='Consolas')
    parrafo(codigo, before=4, after=4, line=1.0)
    fondo_de_parrafo(codigo, FONDO_CODIGO_HEX)
    borde_de_parrafo(codigo, 'left', 18, ACENTO_HEX, espacio=6)
    codigo.paragraph_format.left_indent = Cm(0.25)
    verbatim = estilo(doc, 'Verbatim Char', tipo=WD_STYLE_TYPE.CHARACTER)
    fuente(verbatim, size=9.5, name='Consolas')

    pendiente = estilo(doc, 'Pendiente', tipo=WD_STYLE_TYPE.CHARACTER)
    fuente(pendiente, bold=True)
    rpr = pendiente.element.get_or_add_rPr()
    resaltado = OxmlElement('w:highlight')
    resaltado.set(qn('w:val'), 'yellow')
    rpr.append(resaltado)
    sombra = OxmlElement('w:shd')
    sombra.set(qn('w:val'), 'clear')
    sombra.set(qn('w:color'), 'auto')
    sombra.set(qn('w:fill'), 'FFFF00')
    rpr.append(sombra)

    bordes_de_tabla(estilo(doc, 'Table', tipo=WD_STYLE_TYPE.TABLE))

    seccion = doc.sections[0]
    seccion.start_type = WD_SECTION.NEW_PAGE
    seccion.page_width, seccion.page_height = Cm(21), Cm(29.7)
    for lado in ('left_margin', 'right_margin', 'top_margin', 'bottom_margin'):
        setattr(seccion, lado, Cm(2.5))
    seccion.header_distance = seccion.footer_distance = Cm(1.25)
    seccion.different_first_page_header_footer = True

    # ajustar-docx.py agrega al encabezado el nombre de cada entregable.
    encabezado = seccion.header.paragraphs[0]
    encabezado.text = ''
    run = encabezado.add_run(f'{SISTEMA} · CE03 Infraestructura Tecnológica')
    run.font.size, run.font.color.rgb, run.font.name = Pt(8.5), GRIS, FUENTE
    encabezado.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    borde_de_parrafo(encabezado._p.get_or_add_pPr(), 'bottom', 4, LINEA_HEX, espacio=4)

    pie = seccion.footer.paragraphs[0]
    pie.text = ''
    campo_pagina(pie)
    pie.alignment = WD_ALIGN_PARAGRAPH.CENTER
    borde_de_parrafo(pie._p.get_or_add_pPr(), 'top', 4, LINEA_HEX, espacio=4)

    # Portada sin encabezado ni número.
    seccion.first_page_header.paragraphs[0].text = ''
    seccion.first_page_footer.paragraphs[0].text = ''

    doc.save(salida)


if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2])
