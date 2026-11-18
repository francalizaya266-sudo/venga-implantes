"""Crea plantilla-upeu.docx: los estilos que pandoc usa para armar cada entregable en Word.

Uso (desde docs/entregables):
    pandoc -o plantilla/base.docx --print-default-data-file reference.docx
    python3 plantilla/crear-plantilla.py plantilla/base.docx plantilla/plantilla-upeu.docx

A4, márgenes de 2,5 cm, Arial 11 con interlineado 1,5, títulos numerados por pandoc (1, 1.1, 1.1.1),
salto de página antes de cada sección principal, encabezado con el nombre del sistema, número de página
en el pie (la portada no lleva), tablas con bordes y encabezado sombreado, leyendas de figuras y tablas,
y el estilo "Pendiente" en amarillo para los [PENDIENTE].
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
GRIS = RGBColor(0x59, 0x59, 0x59)


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
    ppr.append(lvl)


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
    for lado in ('top', 'left', 'bottom', 'right', 'insideH', 'insideV'):
        b = OxmlElement(f'w:{lado}')
        b.set(qn('w:val'), 'single')
        b.set(qn('w:sz'), '4')
        b.set(qn('w:space'), '0')
        b.set(qn('w:color'), '8C8C8C')
        bordes.append(b)
    tblpr.append(bordes)
    margenes = OxmlElement('w:tblCellMar')
    for lado, valor in (('left', '80'), ('right', '80'), ('top', '30'), ('bottom', '30')):
        m = OxmlElement(f'w:{lado}')
        m.set(qn('w:w'), valor)
        m.set(qn('w:type'), 'dxa')
        margenes.append(m)
    tblpr.append(margenes)
    # Primera fila: fondo azul claro y negrita.
    for viejo in style.element.findall(qn('w:tblStylePr')):
        style.element.remove(viejo)
    primera = OxmlElement('w:tblStylePr')
    primera.set(qn('w:type'), 'firstRow')
    rpr = OxmlElement('w:rPr')
    rpr.append(OxmlElement('w:b'))
    primera.append(rpr)
    tcpr = OxmlElement('w:tcPr')
    shd = OxmlElement('w:shd')
    shd.set(qn('w:val'), 'clear')
    shd.set(qn('w:color'), 'auto')
    shd.set(qn('w:fill'), 'D9E2F3')
    tcpr.append(shd)
    primera.append(tcpr)
    style.element.append(primera)


def campo_pagina(parrafo_pie):
    run = parrafo_pie.add_run('Página ')
    run.font.size = Pt(9)
    for tipo, texto in (('begin', None), (None, ' PAGE '), ('end', None)):
        r = parrafo_pie.add_run()
        r.font.size = Pt(9)
        if tipo:
            fld = OxmlElement('w:fldChar')
            fld.set(qn('w:fldCharType'), tipo)
            r._r.append(fld)
        else:
            instr = OxmlElement('w:instrText')
            instr.set(qn('xml:space'), 'preserve')
            instr.text = texto
            r._r.append(instr)


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

    for nivel, (size, before, after) in {1: (16, 0, 12), 2: (13, 14, 6), 3: (12, 10, 4), 4: (11, 8, 4)}.items():
        s = estilo(doc, f'Heading {nivel}')
        fuente(s, size=size, bold=True, italic=False, color=AZUL)
        parrafo(s, before=before, after=after, line=1.15, keep_next=True, page_break=(nivel == 1))
        nivel_de_esquema(s, nivel - 1)

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

    for nombre in ('Caption', 'Table Caption', 'Image Caption'):
        s = estilo(doc, nombre)
        fuente(s, size=9, italic=True, bold=False, color=GRIS)
        parrafo(s, before=4, after=10, line=1.15, align=WD_ALIGN_PARAGRAPH.CENTER, keep_next=(nombre == 'Table Caption'))
    figura = estilo(doc, 'Figure')
    parrafo(figura, before=6, after=0, line=1.0, align=WD_ALIGN_PARAGRAPH.CENTER, keep_next=True)
    captioned = estilo(doc, 'Captioned Figure')
    parrafo(captioned, before=6, after=0, line=1.0, align=WD_ALIGN_PARAGRAPH.CENTER, keep_next=True)

    codigo = estilo(doc, 'Source Code', base='Normal')
    fuente(codigo, size=9, name='Consolas')
    parrafo(codigo, before=4, after=4, line=1.0)
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

    encabezado = seccion.header.paragraphs[0]
    encabezado.text = ''
    run = encabezado.add_run(f'{SISTEMA} · CE02 Ingeniería de Software')
    run.font.size, run.font.color.rgb, run.font.name = Pt(9), GRIS, FUENTE
    encabezado.alignment = WD_ALIGN_PARAGRAPH.RIGHT

    pie = seccion.footer.paragraphs[0]
    pie.text = ''
    campo_pagina(pie)
    pie.alignment = WD_ALIGN_PARAGRAPH.CENTER

    # Portada sin encabezado ni número.
    seccion.first_page_header.paragraphs[0].text = ''
    seccion.first_page_footer.paragraphs[0].text = ''

    doc.save(salida)


if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2])
