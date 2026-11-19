# Uso: python3 proxy-gzip.py 8001 http://127.0.0.1:8000   (y se mide http://127.0.0.1:8001)
"""Proxy local que comprime con gzip (nivel 5, como el gzip de Nginx) lo que el servidor de desarrollo de PHP envía
sin comprimir: HTML, CSS, JS, JSON y SVG. Solo para medir el efecto de la compresión."""
import gzip, sys, urllib.request
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

UPSTREAM = sys.argv[2] if len(sys.argv) > 2 else 'http://127.0.0.1:8000'
TYPES = ('text/', 'application/javascript', 'application/json', 'image/svg+xml')

class Handler(BaseHTTPRequestHandler):
    protocol_version = 'HTTP/1.1'
    def log_message(self, *a): pass
    def do_GET(self):
        req = urllib.request.Request(UPSTREAM + self.path, headers={k: v for k, v in self.headers.items() if k.lower() != 'accept-encoding'})
        try:
            resp = urllib.request.urlopen(req)
        except urllib.error.HTTPError as e:
            resp = e
        body = resp.read()
        ctype = resp.headers.get('Content-Type', '')
        gz = 'gzip' in self.headers.get('Accept-Encoding', '') and ctype.startswith(TYPES) and len(body) > 1024
        if gz:
            body = gzip.compress(body, 5)
        self.send_response(resp.status)
        for k, v in resp.headers.items():
            if k.lower() not in ('content-length', 'transfer-encoding', 'connection', 'content-encoding'):
                self.send_header(k, v)
        if gz:
            self.send_header('Content-Encoding', 'gzip')
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        self.wfile.write(body)

ThreadingHTTPServer(('127.0.0.1', int(sys.argv[1])), Handler).serve_forever()
