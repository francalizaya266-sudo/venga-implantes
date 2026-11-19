# Rendimiento y accesibilidad de la web del paciente

Medido el 19/11/2026 con `node medir-web.cjs <URL> / /tratamientos/limpieza-dental /reservar /promociones/blanqueamiento-20 /mi-cuenta` (Chromium de Playwright, caché vacía, mediana de 3 cargas), sobre el commit `67ba171` y los mismos datos de `medicion-api.md`.

No es Lighthouse (no estaba disponible en la máquina): son las mismas métricas de laboratorio, pero sin su puntaje. El perfil **celular** imita al de Lighthouse para móvil: pantalla de 412 × 823, red 4G lenta (150 ms de latencia y 1,6 Mbit/s de bajada) y el procesador 4 veces más lento.

## Sin compresión (servidor de desarrollo de PHP)

El servidor de desarrollo no comprime: el JavaScript viaja con sus 459 KB.

| Página | Perfil | TTFB (ms) | FCP (ms) | LCP (ms) | CLS | TBT (ms) | Carga (ms) | Pedidos | Descarga (KB) |
|---|---|---|---|---|---|---|---|---|---|
| `/` | escritorio | 72 | 216 | 252 | 0.004 | 0 | 116 | 23 | 833 |
| `/` | celular | 151 | 3636 | 3760 | 0.000 | 0 | 3373 | 13 | 724 |
| `/tratamientos/limpieza-dental` | escritorio | 55 | 148 | 676 | 0.000 | 0 | 96 | 13 | 684 |
| `/tratamientos/limpieza-dental` | celular | 151 | 3556 | 3684 | 0.000 | 0 | 3382 | 6 | 634 |
| `/reservar` | escritorio | 46 | 152 | 152 | 0.000 | 0 | 89 | 10 | 690 |
| `/reservar` | celular | 151 | 3504 | 3504 | 0.000 | 0 | 3370 | 10 | 690 |
| `/promociones/blanqueamiento-20` | escritorio | 49 | 144 | 144 | 0.000 | 0 | 92 | 6 | 631 |
| `/promociones/blanqueamiento-20` | celular | 151 | 3504 | 3504 | 0.000 | 0 | 3368 | 6 | 631 |
| `/mi-cuenta` | escritorio | 46 | 136 | 140 | 0.000 | 0 | 88 | 7 | 671 |
| `/mi-cuenta` | celular | 151 | 3512 | 3512 | 0.000 | 0 | 3365 | 7 | 671 |

## Con compresión gzip, como la que hace Nginx

Igual que arriba, pero a través de un proxy local (`proxy-gzip.py`) que comprime con gzip (nivel 5) el HTML, el CSS, el JavaScript, el JSON y el SVG: el JavaScript baja a 139 KB y el CSS a 18 KB (`npm run build`).

| Página | Perfil | TTFB (ms) | FCP (ms) | LCP (ms) | CLS | TBT (ms) | Carga (ms) | Pedidos | Descarga (KB) |
|---|---|---|---|---|---|---|---|---|---|
| `/` | escritorio | 64 | 256 | 264 | 0.004 | 0 | 124 | 23 | 442 |
| `/` | celular | 151 | 1796 | 1944 | 0.000 | 0 | 1515 | 13 | 333 |
| `/tratamientos/limpieza-dental` | escritorio | 56 | 176 | 720 | 0.000 | 0 | 114 | 13 | 294 |
| `/tratamientos/limpieza-dental` | celular | 151 | 1700 | 1848 | 0.000 | 0 | 1531 | 6 | 245 |
| `/reservar` | escritorio | 67 | 224 | 228 | 0.000 | 0 | 146 | 10 | 303 |
| `/reservar` | celular | 152 | 1728 | 1728 | 0.000 | 0 | 1551 | 10 | 303 |
| `/promociones/blanqueamiento-20` | escritorio | 69 | 188 | 188 | 0.000 | 0 | 128 | 6 | 243 |
| `/promociones/blanqueamiento-20` | celular | 152 | 1680 | 1680 | 0.000 | 0 | 1549 | 6 | 243 |
| `/mi-cuenta` | escritorio | 53 | 180 | 184 | 0.000 | 0 | 116 | 7 | 284 |
| `/mi-cuenta` | celular | 151 | 1660 | 1660 | 0.000 | 0 | 1513 | 7 | 284 |

Umbrales de Google para una buena experiencia: LCP hasta 2 500 ms, CLS hasta 0,1 y TBT hasta 200 ms. Con compresión, las cinco páginas los cumplen en el perfil de celular (LCP de 1 660 a 1 944 ms, CLS 0 y TBT 0); sin compresión, el LCP sube a unos 3 500 ms por el peso del JavaScript.

## Accesibilidad (revisión automática parcial, perfil celular, con compresión)

Accesibilidad de `/` (contraste de 66 textos; 65 sobre imagen o degradado, sin revisar): sin observaciones.
Accesibilidad de `/tratamientos/limpieza-dental` (contraste de 84 textos; 22 sobre imagen o degradado, sin revisar): sin observaciones.
Accesibilidad de `/reservar` (contraste de 22 textos; 7 sobre imagen o degradado, sin revisar): sin observaciones.
Accesibilidad de `/promociones/blanqueamiento-20` (contraste de 30 textos; 22 sobre imagen o degradado, sin revisar): sin observaciones.
Accesibilidad de `/mi-cuenta` (contraste de 23 textos; 26 sobre imagen o degradado, sin revisar): sin observaciones.

Revisa: idioma del documento, título, un solo h1, saltos de nivel en los títulos, imágenes sin texto alternativo, controles sin nombre, enlace para saltar al contenido y contraste del texto sobre fondos de color sólido (WCAG 2.1 AA: 4,5:1, o 3:1 en letra grande). No revisa el texto sobre imágenes o degradados, el uso con teclado ni con lector de pantalla.

La primera corrida encontró un problema real: el texto blanco del botón de WhatsApp sobre el verde `#128c4b` tenía un contraste de 4,30:1. Se cambió a `#11824a` (4,87:1) y la segunda corrida, la de arriba, ya no lo marca.
