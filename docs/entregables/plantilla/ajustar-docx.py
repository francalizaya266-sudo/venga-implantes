"""Ajusta el .docx que arma pandoc antes de llenar el índice.

Uso: python3 plantilla/ajustar-docx.py word/archivo.docx [--secciones-seguidas]

1. Las celdas de las tablas toman el estilo "Texto de tabla" (9 puntos) y su código en línea baja a 8 puntos:
   pandoc les pone "Compact", el mismo estilo de las listas. Ese código puede partirse después de "_", "/" y "::"
   (con un espacio de ancho cero), para que un nombre largo de prueba o de archivo no se corte a la mitad de una
   palabra ni se salga de la celda. Ninguna fila se parte entre dos páginas.
2. Un párrafo que solo dice [[HORIZONTAL]] cierra la sección anterior (vertical) y empieza una horizontal; uno que
   solo dice [[VERTICAL]] cierra la horizontal. Sirve para una tabla ancha, como la matriz de trazabilidad.
   La portada sigue sin encabezado ni número y las secciones siguientes los conservan.
3. Un párrafo que solo dice [[PAGINA]] hace empezar en una página nueva lo que sigue (por ejemplo, un acta), y la
   fila de una tabla con [PENDIENTE – firma] deja espacio para firmar a mano.
4. El título "Anexos" del documento principal (que solo dice dónde están los anexos) no empieza página nueva.
5. El párrafo que sigue a una tabla lleva 6 puntos de aire antes, y un bloque de código no se parte ni se separa
   del párrafo que lo presenta.
6. Con --secciones-seguidas, después de la primera, cada sección principal sigue en la misma página, separada
   por espacio, para que un entregable con límite de páginas no pierda medias hojas en blanco.
"""

import copy
import sys

from docx import Document
from docx.oxml.ns import qn
from docx.enum.table import WD_CELL_VERTICAL_ALIGNMENT, WD_ROW_HEIGHT_RULE
from docx.shared import Cm, Pt

MARCAS = ('[[HORIZONTAL]]', '[[VERTICAL]]')
PAGINA = '[[PAGINA]]'
FIRMA = '[PENDIENTE – firma]'
CORTE = '\u200b'


def texto(parrafo):
    return ''.join(t.text or '' for t in parrafo.iter(qn('w:t'))).strip()


def estilo_de_tablas(doc):
    estilo = doc.styles['Texto de tabla'].style_id
    for tabla in doc.element.body.iter(qn('w:tbl')):
        for fila in tabla.iter(qn('w:tr')):
            tr_pr = fila.find(qn('w:trPr'))
            if tr_pr is None:
                tr_pr = fila.makeelement(qn('w:trPr'), {})
                fila.insert(0, tr_pr)
            if tr_pr.find(qn('w:cantSplit')) is None:
                tr_pr.append(tr_pr.makeelement(qn('w:cantSplit'), {}))
        for parrafo in tabla.iter(qn('w:p')):
            ppr = parrafo.get_or_add_pPr()
            pstyle = ppr.find(qn('w:pStyle'))
            if pstyle is None:
                pstyle = ppr.makeelement(qn('w:pStyle'), {})
                ppr.insert(0, pstyle)
            pstyle.set(qn('w:val'), estilo)
            for rstyle in parrafo.iter(qn('w:rStyle')):
                if rstyle.get(qn('w:val')) == 'VerbatimChar':
                    rpr = rstyle.getparent()
                    for viejo in rpr.findall(qn('w:sz')):
                        rpr.remove(viejo)
                    tamano = rpr.makeelement(qn('w:sz'), {qn('w:val'): str(int(Pt(8).pt * 2))})
                    rpr.append(tamano)
                    for nodo in rpr.getparent().iter(qn('w:t')):
                        nodo.text = nodo.text.replace('::', '::' + CORTE).replace('_', '_' + CORTE).replace('/', '/' + CORTE)


def aire_tras_tablas(doc):
    """El párrafo que sigue a una tabla no queda pegado a su borde."""
    for tabla in doc.element.body.iter(qn('w:tbl')):
        siguiente = tabla.getnext()
        if siguiente is not None and siguiente.tag == qn('w:p'):
            ppr = siguiente.get_or_add_pPr()
            espacio = ppr.find(qn('w:spacing'))
            if espacio is None:
                espacio = ppr.makeelement(qn('w:spacing'), {})
                ppr.append(espacio)
            espacio.set(qn('w:before'), str(int(Pt(6).pt * 20)))


def bloques_de_codigo_enteros(doc):
    """Un bloque de código (los escenarios Gherkin) no se parte y no queda separado del título que lo presenta."""
    parrafos = doc.paragraphs
    for anterior, parrafo in zip(parrafos, parrafos[1:]):
        if parrafo.style.name == 'Source Code':
            parrafo.paragraph_format.keep_together = True
            anterior.paragraph_format.keep_with_next = True


def saltos_de_pagina(doc):
    """Un párrafo que solo dice [[PAGINA]] desaparece y lo que sigue empieza en una página nueva."""
    parrafos = doc.paragraphs
    for marca, siguiente in zip(parrafos, parrafos[1:]):
        if marca.text.strip() == PAGINA:
            siguiente.paragraph_format.page_break_before = True
            marca._element.getparent().remove(marca._element)


def espacio_para_firmar(doc):
    """La fila de una tabla que pide una firma deja 1,5 cm para firmar a mano, con la marca abajo."""
    for tabla in doc.tables:
        for fila in tabla.rows:
            if any(celda.text.strip() == FIRMA for celda in fila.cells):
                fila.height = Cm(1.5)
                fila.height_rule = WD_ROW_HEIGHT_RULE.AT_LEAST
                for celda in fila.cells:
                    if celda.text.strip() == FIRMA:
                        celda.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.BOTTOM


def girar(sect_pr, horizontal):
    pg_sz = sect_pr.find(qn('w:pgSz'))
    ancho, alto = int(pg_sz.get(qn('w:w'))), int(pg_sz.get(qn('w:h')))
    if horizontal != (ancho > alto):
        pg_sz.set(qn('w:w'), str(alto))
        pg_sz.set(qn('w:h'), str(ancho))
    if horizontal:
        pg_sz.set(qn('w:orient'), 'landscape')
    elif pg_sz.get(qn('w:orient')) is not None:
        del pg_sz.attrib[qn('w:orient')]


def sin_portada(sect_pr):
    for titulo in sect_pr.findall(qn('w:titlePg')):
        sect_pr.remove(titulo)


def secciones(doc):
    cuerpo = doc.element.body
    final = cuerpo.find(qn('w:sectPr'))
    marcas = [(p, texto(p) == '[[HORIZONTAL]]') for p in cuerpo.iter(qn('w:p')) if texto(p) in MARCAS]
    for orden, (parrafo, horizontal) in enumerate(marcas):
        for hijo in list(parrafo):
            if hijo.tag != qn('w:pPr'):
                parrafo.remove(hijo)
        # La marca cierra la sección anterior: [[HORIZONTAL]] cierra una vertical y [[VERTICAL]], una horizontal.
        sect_pr = copy.deepcopy(final)
        girar(sect_pr, horizontal=not horizontal)
        if orden > 0:
            sin_portada(sect_pr)
        parrafo.get_or_add_pPr().append(sect_pr)
    if marcas:
        girar(final, horizontal=marcas[-1][1])
        sin_portada(final)


def anexos_sin_salto(doc):
    for parrafo in doc.paragraphs:
        if parrafo.style.name == 'Heading 1' and parrafo.text.strip() == 'Anexos':
            parrafo.paragraph_format.page_break_before = False


def secciones_seguidas(doc):
    titulos = [p for p in doc.paragraphs if p.style.name == 'Heading 1']
    # La primera sección empieza página (después del índice); las demás siguen.
    for parrafo in titulos[1:]:
        parrafo.paragraph_format.page_break_before = False
        parrafo.paragraph_format.space_before = Pt(24)


def main(ruta, seguidas=False):
    doc = Document(ruta)
    estilo_de_tablas(doc)
    aire_tras_tablas(doc)
    bloques_de_codigo_enteros(doc)
    saltos_de_pagina(doc)
    espacio_para_firmar(doc)
    secciones(doc)
    anexos_sin_salto(doc)
    if seguidas:
        secciones_seguidas(doc)
    doc.save(ruta)


if __name__ == '__main__':
    main(sys.argv[1], seguidas='--secciones-seguidas' in sys.argv[2:])
