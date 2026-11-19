"""Ajusta el .docx que arma pandoc antes de llenar el índice.

Uso: python3 plantilla/ajustar-docx.py word/archivo.docx metadatos.yaml [--secciones-seguidas]

1. Las celdas de las tablas toman el estilo "Texto de tabla" (9 puntos) y su código en línea baja a 8 puntos:
   pandoc les pone "Compact", el mismo estilo de las listas. Ese código puede partirse después de "_", "/" y "::"
   (con un espacio de ancho cero), para que un nombre largo de prueba o de archivo no se corte a la mitad de una
   palabra ni se salga de la celda. Ninguna fila se parte entre dos páginas.
2. Un párrafo que solo dice [[HORIZONTAL]] cierra la sección anterior (vertical) y empieza una horizontal; uno que
   solo dice [[VERTICAL]] cierra la horizontal. Sirve para una tabla ancha, como la matriz de trazabilidad.
   La portada sigue sin encabezado ni número y las secciones siguientes los conservan.
3. Un párrafo que solo dice [[PAGINA]] hace empezar en una página nueva lo que sigue (por ejemplo, un acta), y la
   fila de una tabla de firmas (con una columna "Firma") deja espacio para firmar a mano.
4. El título "Anexos" del documento principal (que solo dice dónde están los anexos) no empieza página nueva.
5. El párrafo que sigue a una tabla lleva 6 puntos de aire antes, y un bloque de código no se separa del párrafo
   que lo presenta. Uno corto (hasta 35 líneas, como un escenario Gherkin) tampoco se parte; uno largo (un script
   completo de un anexo) empieza en la misma página y sigue en las siguientes, sin dejar una hoja en blanco.
6. Con --secciones-seguidas, después de la primera, cada sección principal sigue en la misma página, separada
   por espacio, para que un entregable con límite de páginas no pierda medias hojas en blanco.
7. Una tabla corta (hasta 8 filas) no se parte: pasa entera a la página siguiente junto con su título. Una larga
   puede partirse, pero no deja el encabezado solo al pie de una hoja ni una última fila (como "Total") sola en
   la siguiente: sus dos primeras y sus dos últimas filas van juntas.
8. Las tablas llevan el encabezado en azul oscuro con letra blanca (se repite si la tabla sigue en otra hoja) y
   las filas pares del cuerpo en gris azulado muy claro. Van como formato de cada celda, no solo en el estilo de
   tabla, para que Word y LibreOffice las muestren igual.
9. La portada: el título, subtítulo, autores y fecha que arma pandoc se cambian por una portada con la
   universidad, el entregable, el sistema, los integrantes y la fecha. Lo común a los nueve Word está en
   plantilla/portada.yaml y lo de cada uno en el bloque "portada" de su -metadatos.yaml. Si existe
   plantilla/logo-upeu.png, va arriba. El encabezado de las páginas suma el nombre corto del entregable.
"""

import copy
import sys
from pathlib import Path

import yaml
from docx import Document
from docx.enum.table import WD_CELL_VERTICAL_ALIGNMENT, WD_ROW_HEIGHT_RULE, WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Pt, RGBColor

PLANTILLA = Path(__file__).resolve().parent
AZUL, ACENTO, GRIS = RGBColor(0x1F, 0x38, 0x64), RGBColor(0x2F, 0x54, 0x96), RGBColor(0x59, 0x59, 0x59)
NEGRO, BLANCO = RGBColor(0x1A, 0x1A, 0x1A), RGBColor(0xFF, 0xFF, 0xFF)
AZUL_HEX, LINEA_HEX, FILA_PAR_HEX, FONDO_SISTEMA_HEX = '1F3864', 'C9D3E3', 'F3F6FA', 'E8EEF8'
MESES = ('enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre',
         'noviembre', 'diciembre')

# Orden de los hijos que pide el esquema de Word; uno fuera de orden puede hacer que Word no abra el archivo.
ORDEN = {
    'tcPr': ('cnfStyle', 'tcW', 'gridSpan', 'hMerge', 'vMerge', 'tcBorders', 'shd', 'noWrap', 'tcMar',
             'textDirection', 'tcFitText', 'vAlign', 'hideMark'),
    'rPr': ('rStyle', 'rFonts', 'b', 'bCs', 'i', 'iCs', 'caps', 'smallCaps', 'strike', 'dstrike', 'outline',
            'shadow', 'emboss', 'imprint', 'noProof', 'snapToGrid', 'vanish', 'webHidden', 'color', 'spacing', 'w',
            'kern', 'position', 'sz', 'szCs', 'highlight', 'u', 'effect', 'bdr', 'shd', 'fitText', 'vertAlign',
            'rtl', 'cs', 'em', 'lang', 'eastAsianLayout', 'specVanish', 'oMath'),
    'pPr': ('pStyle', 'keepNext', 'keepLines', 'pageBreakBefore', 'framePr', 'widowControl', 'numPr',
            'suppressLineNumbers', 'pBdr', 'shd', 'tabs', 'suppressAutoHyphens', 'kinsoku', 'wordWrap',
            'overflowPunct', 'topLinePunct', 'autoSpaceDE', 'autoSpaceDN', 'bidi', 'adjustRightInd', 'snapToGrid',
            'spacing', 'ind', 'contextualSpacing', 'mirrorIndents', 'suppressOverlap', 'jc', 'textDirection',
            'textAlignment', 'textboxTightWrap', 'outlineLvl', 'divId', 'cnfStyle', 'rPr', 'sectPr', 'pPrChange'),
    'tblPr': ('tblStyle', 'tblpPr', 'tblOverlap', 'bidiVisual', 'tblStyleRowBandSize', 'tblStyleColBandSize', 'tblW',
              'jc', 'tblCellSpacing', 'tblInd', 'tblBorders', 'shd', 'tblLayout', 'tblCellMar', 'tblLook'),
}


def poner(padre, nombre, **atributos):
    """Cambia (o crea en su lugar del esquema) el hijo w:nombre de padre, con esos atributos."""
    for viejo in padre.findall(qn(f'w:{nombre}')):
        padre.remove(viejo)
    nuevo = OxmlElement(f'w:{nombre}')
    for clave, valor in atributos.items():
        nuevo.set(qn(f'w:{clave}'), str(valor))
    orden = ORDEN[padre.tag.split('}')[1]]
    despues = {qn(f'w:{n}') for n in orden[orden.index(nombre) + 1:]}
    for hijo in padre:
        if hijo.tag in despues:
            hijo.addprevious(nuevo)
            return nuevo
    padre.append(nuevo)
    return nuevo


MARCAS = ('[[HORIZONTAL]]', '[[VERTICAL]]')
PAGINA = '[[PAGINA]]'
FIRMA = 'Firma'
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


def colores_de_tablas(doc):
    """Encabezado azul oscuro con letra blanca y filas pares del cuerpo en gris azulado muy claro."""
    for tabla in doc.element.body.iter(qn('w:tbl')):
        cuerpo = 0
        for fila in tabla.findall(qn('w:tr')):
            tr_pr = fila.find(qn('w:trPr'))
            encabezado = tr_pr is not None and tr_pr.find(qn('w:tblHeader')) is not None
            if not encabezado:
                cuerpo += 1
                if cuerpo % 2 == 1:
                    continue
            for celda in fila.findall(qn('w:tc')):
                tc_pr = celda.find(qn('w:tcPr'))
                if tc_pr is None:
                    tc_pr = celda.makeelement(qn('w:tcPr'), {})
                    celda.insert(0, tc_pr)
                poner(tc_pr, 'shd', val='clear', color='auto', fill=AZUL_HEX if encabezado else FILA_PAR_HEX)
                if encabezado:
                    for run in celda.iter(qn('w:r')):
                        r_pr = run.find(qn('w:rPr'))
                        if r_pr is None:
                            r_pr = run.makeelement(qn('w:rPr'), {})
                            run.insert(0, r_pr)
                        estilo = r_pr.find(qn('w:rStyle'))
                        if estilo is not None and estilo.get(qn('w:val')) == 'Pendiente':
                            continue
                        poner(r_pr, 'b')
                        poner(r_pr, 'color', val='FFFFFF')


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


FILAS_DE_UNA_TABLA_ENTERA = 8
FILAS_JUNTAS_EN_LOS_BORDES = 2


def tablas_sin_filas_sueltas(doc):
    """Word mantiene juntas dos filas cuando los párrafos de la primera llevan "conservar con el siguiente"."""
    for tabla in doc.element.body.iter(qn('w:tbl')):
        filas = tabla.findall(qn('w:tr'))
        if len(filas) <= FILAS_DE_UNA_TABLA_ENTERA:
            juntas = filas[:-1]
        else:
            juntas = filas[:FILAS_JUNTAS_EN_LOS_BORDES - 1] + filas[-FILAS_JUNTAS_EN_LOS_BORDES:-1]
        for fila in juntas:
            for parrafo in fila.iter(qn('w:p')):
                ppr = parrafo.get_or_add_pPr()
                if ppr.find(qn('w:keepNext')) is None:
                    # w:keepNext va antes que el resto de las propiedades del párrafo, salvo el estilo.
                    estilo = ppr.find(qn('w:pStyle'))
                    posicion = 1 if estilo is not None else 0
                    ppr.insert(posicion, ppr.makeelement(qn('w:keepNext'), {}))


LINEAS_DE_UN_BLOQUE_ENTERO = 35


def bloques_de_codigo_enteros(doc):
    """Un bloque de código no queda separado del título que lo presenta; si es corto, tampoco se parte."""
    parrafos = doc.paragraphs
    for anterior, parrafo in zip(parrafos, parrafos[1:]):
        if parrafo.style.name == 'Source Code':
            # pandoc deja cada bloque en un solo párrafo, con un salto de línea (w:br) entre línea y línea.
            lineas = len(parrafo._element.findall('.//' + qn('w:br'))) + 1
            if lineas <= LINEAS_DE_UN_BLOQUE_ENTERO:
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
    """Cada fila de una tabla de firmas (con una columna "Firma") deja 1,5 cm para firmar a mano."""
    for tabla in doc.tables:
        if not tabla.rows or FIRMA not in [celda.text.strip() for celda in tabla.rows[0].cells]:
            continue
        for fila in list(tabla.rows)[1:]:
            fila.height = Cm(1.5)
            fila.height_rule = WD_ROW_HEIGHT_RULE.AT_LEAST
            for celda in fila.cells:
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


def fecha_larga(fecha):
    """19/11/2026 → 19 de noviembre de 2026."""
    dia, mes, anio = (int(parte) for parte in str(fecha).split('/'))
    return f'{dia} de {MESES[mes - 1]} de {anio}'


class Portada:
    """Arma la portada justo antes del índice, con párrafos y tablas que Word y LibreOffice muestran igual."""

    def __init__(self, doc, antes_de):
        self.doc, self.antes_de = doc, antes_de

    def _mover(self, elemento):
        self.antes_de.addprevious(elemento)

    def _formato(self, parrafo, antes=0, despues=0, alinear=WD_ALIGN_PARAGRAPH.CENTER, interlineado=1.1):
        pf = parrafo.paragraph_format
        pf.space_before, pf.space_after, pf.line_spacing, pf.alignment = Pt(antes), Pt(despues), interlineado, alinear
        pf.first_line_indent = Cm(0)

    def _texto(self, parrafo, texto, tamano, color, negrita=False, espaciado=0):
        run = parrafo.add_run(texto)
        run.font.name, run.font.size, run.font.color.rgb, run.font.bold = 'Arial', Pt(tamano), color, negrita
        if texto.startswith('[PENDIENTE'):
            run.style = self.doc.styles['Pendiente']
        if espaciado:
            poner(run._r.get_or_add_rPr(), 'spacing', val=int(espaciado * 20))
        return run

    def linea(self, texto, tamano, color, negrita=False, espaciado=0, antes=0, despues=0):
        parrafo = self.doc.add_paragraph(style='Normal')
        self._formato(parrafo, antes, despues)
        if texto:
            self._texto(parrafo, texto, tamano, color, negrita, espaciado)
        self._mover(parrafo._p)
        return parrafo

    def raya(self, antes=4, despues=10):
        """Raya corta y centrada, de 3 cm: un párrafo vacío con borde inferior y sangría a los dos lados."""
        parrafo = self.linea('', 4, NEGRO, antes=antes, despues=despues)
        parrafo.paragraph_format.left_indent = parrafo.paragraph_format.right_indent = Cm(6.5)
        bordes = poner(parrafo._p.get_or_add_pPr(), 'pBdr')
        abajo = OxmlElement('w:bottom')
        for clave, valor in (('val', 'single'), ('sz', '18'), ('space', '1'), ('color', '2F5496')):
            abajo.set(qn(f'w:{clave}'), valor)
        bordes.append(abajo)

    def tabla(self, filas, anchos, bordes):
        tabla = self.doc.add_table(rows=filas, cols=len(anchos))
        tabla.alignment = WD_TABLE_ALIGNMENT.CENTER
        tbl_pr = tabla._tbl.tblPr
        poner(tbl_pr, 'tblW', w=int(sum(anchos) * 567), type='dxa')
        caja = poner(tbl_pr, 'tblBorders')
        for lado in ('top', 'left', 'bottom', 'right', 'insideH', 'insideV'):
            grosor, color = bordes.get(lado, (0, 'auto'))
            borde = OxmlElement(f'w:{lado}')
            for clave, valor in (('val', 'single' if grosor else 'nil'), ('sz', grosor), ('space', 0), ('color', color)):
                borde.set(qn(f'w:{clave}'), str(valor))
            caja.append(borde)
        margenes = poner(tbl_pr, 'tblCellMar')
        for lado, valor in (('top', 90), ('left', 160), ('bottom', 90), ('right', 160)):
            margen = OxmlElement(f'w:{lado}')
            margen.set(qn('w:w'), str(valor))
            margen.set(qn('w:type'), 'dxa')
            margenes.append(margen)
        poner(tbl_pr, 'tblLayout', type='fixed')
        for columna, ancho in zip(tabla.columns, anchos):
            columna.width = Cm(ancho)
        for fila in tabla.rows:
            for celda, ancho in zip(fila.cells, anchos):
                celda.width = Cm(ancho)
        self._mover(tabla._tbl)
        return tabla

    @staticmethod
    def fondo(celda, color):
        poner(celda._tc.get_or_add_tcPr(), 'shd', val='clear', color='auto', fill=color)

    def en_celda(self, celda, texto, tamano, color, negrita=False, espaciado=0, primero=True, antes=0, despues=0,
                 alinear=WD_ALIGN_PARAGRAPH.CENTER):
        parrafo = celda.paragraphs[0] if primero else celda.add_paragraph()
        parrafo.style = self.doc.styles['Normal']
        self._formato(parrafo, antes, despues, alinear, interlineado=1.15)
        self._texto(parrafo, texto, tamano, color, negrita, espaciado)
        return parrafo


def portada(doc, ruta_metadatos):
    meta = yaml.safe_load(Path(ruta_metadatos).read_text(encoding='utf-8')) or {}
    propia = meta.get('portada')
    if not propia:
        return
    comun = yaml.safe_load((PLANTILLA / 'portada.yaml').read_text(encoding='utf-8'))

    viejos = [p for p in doc.paragraphs if p.style.name in ('Title', 'Subtitle', 'Author', 'Date')]
    if not viejos:
        return
    antes_de = viejos[-1]._p.getnext()
    for parrafo in viejos:
        parrafo._p.getparent().remove(parrafo._p)
    hoja = Portada(doc, antes_de)

    logo = PLANTILLA / 'logo-upeu.png'
    if logo.exists():
        parrafo = hoja.linea('', 10, NEGRO, despues=8)
        parrafo.add_run().add_picture(str(logo), height=Cm(2.4))

    franja = hoja.tabla(1, [16], {})
    celda = franja.cell(0, 0)
    Portada.fondo(celda, AZUL_HEX)
    hoja.en_celda(celda, comun['universidad'].upper(), 15, BLANCO, negrita=True, espaciado=1.5, antes=4)
    hoja.en_celda(celda, f"{comun['escuela']} · {comun['campus']}", 10.5, BLANCO, primero=False, antes=2, despues=4)

    hoja.linea(comun['evaluacion'].upper(), 9, GRIS, espaciado=0.8, antes=30)
    hoja.linea(propia['tipo'].upper(), 12, ACENTO, negrita=True, espaciado=2, antes=66, despues=6)
    hoja.linea(propia['titulo'], 26, AZUL, negrita=True, despues=2)
    hoja.raya()
    hoja.linea(propia['evidencias'], 11, GRIS, despues=0)

    sistema = hoja.tabla(1, [13], {'left': (36, AZUL_HEX)})
    celda = sistema.cell(0, 0)
    Portada.fondo(celda, FONDO_SISTEMA_HEX)
    hoja.en_celda(celda, 'SISTEMA', 8.5, GRIS, espaciado=1.5, antes=2)
    hoja.en_celda(celda, comun['sistema'], 16, AZUL, negrita=True, primero=False, antes=2, despues=2)
    sistema._tbl.addprevious(hoja.linea('', 4, NEGRO, antes=40)._p)

    filas = [('Integrantes', comun['integrantes']), ('Ciclo y semestre', [comun['ciclo']]),
             ('Repositorio', [comun['repositorio']])]
    if propia.get('despliegue'):
        filas.append(('Despliegue', [comun['despliegue']]))
    filas.append(('Fecha', [fecha_larga(meta['date'])]))
    datos = hoja.tabla(len(filas), [4, 12], {'top': (12, AZUL_HEX), 'bottom': (12, AZUL_HEX), 'insideH': (4, LINEA_HEX)})
    datos._tbl.addprevious(hoja.linea('', 4, NEGRO, antes=76)._p)
    for fila, (rotulo, valores) in zip(datos.rows, filas):
        hoja.en_celda(fila.cells[0], rotulo, 9.5, AZUL, negrita=True, alinear=WD_ALIGN_PARAGRAPH.LEFT)
        for orden, valor in enumerate(valores):
            hoja.en_celda(fila.cells[1], valor, 10, NEGRO, primero=orden == 0, alinear=WD_ALIGN_PARAGRAPH.LEFT)
        fila.cells[0].vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
        Portada.fondo(fila.cells[0], FILA_PAR_HEX)

    hoja.linea(f"{comun['lugar']} · {str(meta['date'])[-4:]}", 10, GRIS, antes=60)


def encabezado(doc, ruta_metadatos):
    """Suma al encabezado de las páginas el nombre corto del entregable, en azul."""
    meta = yaml.safe_load(Path(ruta_metadatos).read_text(encoding='utf-8')) or {}
    corto = (meta.get('portada') or {}).get('encabezado')
    if not corto:
        return
    parrafo = doc.sections[0].header.paragraphs[0]
    for texto, color, negrita in (('   |   ', GRIS, False), (corto, AZUL, True)):
        run = parrafo.add_run(texto)
        run.font.name, run.font.size, run.font.color.rgb, run.font.bold = 'Arial', Pt(8.5), color, negrita


def main(ruta, metadatos=None, seguidas=False):
    doc = Document(ruta)
    estilo_de_tablas(doc)
    colores_de_tablas(doc)
    tablas_sin_filas_sueltas(doc)
    aire_tras_tablas(doc)
    bloques_de_codigo_enteros(doc)
    saltos_de_pagina(doc)
    espacio_para_firmar(doc)
    secciones(doc)
    anexos_sin_salto(doc)
    if seguidas:
        secciones_seguidas(doc)
    if metadatos:
        portada(doc, metadatos)
        encabezado(doc, metadatos)
    doc.save(ruta)


if __name__ == '__main__':
    argumentos = [a for a in sys.argv[2:] if not a.startswith('--')]
    main(sys.argv[1], argumentos[0] if argumentos else None, seguidas='--secciones-seguidas' in sys.argv[2:])
