# Tiempos de respuesta de la API /api/v1

Medido el 19/11/2026 con `bash medir-api.sh http://127.0.0.1:8000 30` (30 pedidos por ruta, después de 3 de calentamiento), sobre el commit `4990c3d`.

- **Servidor:** `php artisan serve` (el servidor de desarrollo de PHP 8.3.6: un solo proceso, sin OPcache), en un contenedor Linux de 4 vCPU Intel Xeon a 2,10 GHz y 15 GB de RAM. Es más lento que producción (Nginx con PHP-FPM y OPcache): las cifras son un techo.
- **Base:** SQLite con los datos de demostración del seeder: 260 cuentas, 262 pacientes, 861 citas, 727 pagos, 9 tratamientos, 2 doctores, 1 promoción y 5 preguntas frecuentes.
- **Pedidos:** sin el celular recordado (como un visitante nuevo); `account` responde `guest: true` y el enlace es uno que no existe (`kind: expired`). Solo rutas GET: no se crearon citas ni se enviaron mensajes.

| Ruta (GET /api/v1/…) | Estado | Mediana (ms) | P95 (ms) | Máximo (ms) | Tamaño (KB) |
|---|---|---|---|---|---|
| `site` | 200 | 43 | 54 | 69 | 5.1 |
| `home` | 200 | 38 | 47 | 49 | 5.5 |
| `treatments` | 200 | 30 | 41 | 43 | 3.0 |
| `treatments/limpieza-dental` | 200 | 36 | 45 | 50 | 4.2 |
| `promotions` | 200 | 29 | 33 | 39 | 0.9 |
| `promotions/blanqueamiento-20` | 200 | 33 | 38 | 43 | 1.4 |
| `team` | 200 | 31 | 42 | 48 | 2.1 |
| `cases` | 200 | 27 | 32 | 39 | 0.6 |
| `reviews` | 200 | 38 | 39 | 40 | 0.5 |
| `faqs` | 200 | 35 | 39 | 40 | 3.0 |
| `pages/contact` | 200 | 34 | 48 | 51 | 1.4 |
| `booking/options` | 200 | 32 | 41 | 54 | 1.5 |
| `booking/days?treatment=3` | 200 | 118 | 142 | 151 | 2.6 |
| `booking/slots?treatment=3&date=2026-11-19` | 200 | 43 | 54 | 61 | 1.3 |
| `account` | 200 | 32 | 40 | 46 | 0.3 |
| `links/00000000000000000000000000000000` | 200 | 36 | 42 | 48 | 0.3 |

Meta del E1 (RNF-REN-02): las rutas principales de la API bajo 500 ms con los datos del seeder. **Se cumple:** el peor caso es `booking/days` (calcula 12 días de horarios libres), con un percentil 95 de 142 ms.
