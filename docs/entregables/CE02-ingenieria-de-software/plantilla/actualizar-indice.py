"""Abre un .docx en LibreOffice sin pantalla, llena el índice y guarda el .docx y su PDF de verificación.

Uso: python3 plantilla/actualizar-indice.py word/archivo.docx
Necesita LibreOffice con su módulo de Python (uno).
"""

import os
import subprocess
import sys
import time

import uno
from com.sun.star.beans import PropertyValue

PUERTO = 2083


def propiedad(nombre, valor):
    p = PropertyValue()
    p.Name, p.Value = nombre, valor
    return p


def conectar():
    contexto = uno.getComponentContext()
    resolutor = contexto.ServiceManager.createInstanceWithContext('com.sun.star.bridge.UnoUrlResolver', contexto)
    for _ in range(60):
        try:
            return resolutor.resolve(f'uno:socket,host=127.0.0.1,port={PUERTO};urp;StarOffice.ComponentContext')
        except Exception:
            time.sleep(0.5)
    raise RuntimeError('LibreOffice no respondió')


def main(ruta_docx):
    ruta = os.path.abspath(ruta_docx)
    perfil = f'file:///tmp/lo-perfil-{os.getpid()}'
    oficina = subprocess.Popen([
        'soffice', '--headless', '--invisible', '--nologo', '--norestore', f'-env:UserInstallation={perfil}',
        f'--accept=socket,host=127.0.0.1,port={PUERTO};urp;',
    ])
    try:
        contexto = conectar()
        escritorio = contexto.ServiceManager.createInstanceWithContext('com.sun.star.frame.Desktop', contexto)
        url = uno.systemPathToFileUrl(ruta)
        doc = escritorio.loadComponentFromURL(url, '_blank', 0, (propiedad('Hidden', True),))
        indices = doc.getDocumentIndexes()
        for i in range(indices.getCount()):
            indices.getByIndex(i).update()
        doc.getTextFields().refresh()
        doc.storeToURL(url, (propiedad('FilterName', 'MS Word 2007 XML'),))
        pdf = uno.systemPathToFileUrl(os.path.splitext(ruta)[0] + '.pdf')
        doc.storeToURL(pdf, (propiedad('FilterName', 'writer_pdf_Export'),))
        doc.close(True)
    finally:
        oficina.terminate()
        oficina.wait(timeout=30)


if __name__ == '__main__':
    main(sys.argv[1])
