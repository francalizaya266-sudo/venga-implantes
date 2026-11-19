# Sistema web para consultorio dental

Hecho para **Clínica Vega Implant** (Jr. Apurímac 648, Juliaca). Su nombre, dirección, WhatsApp y lema vienen ya puestos en `backend/config/consultorio.php` y se cambian desde **Ajustes**.

Reservas online verificadas por WhatsApp, agenda inteligente, historia clínica con odontograma y paneles para administrador, recepción y doctores. Pagos solo informativos (se cobra en el consultorio).

**Stack:** Laravel 13 (PHP 8.3+) · MySQL · Filament 5 para los paneles · React 19 + TypeScript + Vite para la web pública, que habla con Laravel por una API. Pensado para hosting compartido con PHP: sin Node en producción (la web se sube ya compilada) y con un solo cron.

## Backend y frontend

| Carpeta | Qué es |
| --- | --- |
| `backend/` | Laravel. La API de la web (`/api/v1`), los paneles (`/panel`), las hojas para imprimir, los avisos por WhatsApp y toda la lógica del consultorio. También entrega el documento de cada página de la web con su título y sus datos ya escritos: es lo que leen Google y la vista previa de WhatsApp. |
| `frontend/` | React. La web del paciente: inicio, tratamientos, promociones, equipo, reservar cita, Mi cuenta y el enlace personal de WhatsApp. Se compila con Vite dentro de `backend/public/app`, así que en el hosting solo corre PHP. |

Todo vive en un solo dominio: la web usa las cookies y la sesión de Laravel (el celular recordado), sin tokens que guardar ni CORS que configurar.

## Puesta en marcha local

Hace falta PHP 8.3+, Composer, MySQL y Node 22+ (Node solo en la computadora donde se desarrolla).

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
# crear la base de datos MySQL y completar DB_* en .env
php artisan migrate --seed
npm --prefix ../frontend install
composer dev
```

`composer dev` levanta Laravel en `http://localhost:8000` junto con el servidor de Vite: la web se actualiza sola al guardar un archivo de `frontend/`. Para verla como en el hosting, sin el servidor de Vite: `npm --prefix ../frontend run build` y `php artisan serve`.

En local, `--seed` crea datos de demostración, con 11 meses de historia para que Resumen y Ganancias tengan qué mostrar (tarda unos segundos). Panel: `/panel` con `admin@consultorio.test` (el Dr. Vega: administrador y único doctor) o `recepcion@consultorio.test` (clave `password`).

### Pruebas

| Dónde | Comando | Qué revisa |
| --- | --- | --- |
| `backend/` | `php artisan test` | La API, los paneles y las reglas del consultorio. Usan SQLite en memoria; también pasan sobre MySQL. |
| `backend/` | `vendor/bin/pint --test` | El formato del código PHP. |
| `frontend/` | `npm test` | Las páginas y piezas de la web, con Vitest y Testing Library, contra una API de mentira. |
| `frontend/` | `npm run lint`, `npm run typecheck` y `npm run format:check` | ESLint, los tipos de TypeScript y el formato (Prettier). |

GitHub Actions corre todo eso en cada pull request y en cada cambio de `main` (`.github/workflows/tests.yml`).

## Publicación en hosting compartido

El hosting no necesita Node: la web se compila antes de subirla. Hay dos formas de tener el sistema listo para subir:

- **Descargar el paquete.** En cada cambio de `main`, GitHub Actions deja el paquete `consultorio-para-hosting` (pestaña **Actions** → la última ejecución de **Pruebas** → **Artifacts**). Trae la carpeta `backend/` con la web ya compilada.
- **Compilar en la computadora.** `npm --prefix frontend ci` y `npm --prefix frontend run build`, y subir la carpeta `backend/` completa. La web compilada queda en `backend/public/app`, que no está en git: hay que subirla también.

De aquí en adelante, las carpetas y los comandos son dentro de `backend/`.

1. La raíz pública del dominio apunta a la carpeta `public/`.
2. `.env` con `APP_ENV=production`, `APP_DEBUG=false`, los datos de MySQL y `APP_URL` con `https://`.
3. `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force` y `php artisan db:seed --class=CatalogSeeder --force` (solo catálogos, sin datos de prueba).
4. Un único cron cada minuto:
   `* * * * * php /ruta/al/proyecto/artisan schedule:run >> /dev/null 2>&1`
   Ese cron prepara los recordatorios del día anterior, marca las citas sin confirmar, procesa la cola, limpia enlaces vencidos y guarda cada madrugada una copia de la base de datos.
5. PHP con la extensión **GD** (casi todos los hostings la traen): comprime radiografías y fotos al subirlas. Esos archivos y las firmas quedan en `storage/app/private/clinica`, fuera de `public/`; inclúyanlo en las copias de seguridad junto con la base de datos.
6. Las fotos de la web (doctores, tratamientos, promociones, antes y después) se guardan en `public/media`, achicadas y sin datos de ubicación. También van en las copias de seguridad.
7. **Copias de seguridad:** cada día a las 3:00 a. m. se guarda una copia de la base de datos en `storage/app/private/respaldos` (se conservan las últimas 14). Cualquier administrador ve en **Configuración → Copias de seguridad** si están al día, crea una, borra las viejas y cambia cuántas se conservan (`BACKUPS_KEEP` es solo el valor con que nace ese número). Descargarlas, y bajar en `.zip` las radiografías, fotos, documentos y firmas de los puntos 5 y 6, solo puede el administrador odontólogo, porque traen historias clínicas. Quién descarga o borra cada copia queda en **Configuración → Historial de cambios**.

   **Restaurar una copia.** Probado sobre MariaDB 10.11 con una base vacía (hosting nuevo) y con una base dañada: las 66 tablas volvieron con las mismas filas, índices y llaves foráneas, y el panel abrió con normalidad.
   1. `php artisan down`, para que nadie registre nada mientras tanto.
   2. En un hosting nuevo, instalar el sistema como en los puntos 1 a 4 con la base de datos creada y vacía. No hace falta `migrate` ni el seeder: la copia trae las tablas.
   3. Subir la copia `respaldo_AAAA-MM-DD_HHMMSS.sql.gz` a `storage/app/private/respaldos/` y correr `php artisan respaldo:restaurar respaldo_AAAA-MM-DD_HHMMSS.sql.gz` (sin nombre, toma la más reciente de esa carpeta). Reemplaza **toda** la base de datos: lo registrado después de esa copia se pierde.
   4. Sin consola, importar ese mismo archivo en phpMyAdmin con el conjunto de caracteres `utf-8`. Con la consola de MySQL: `gunzip -c respaldo_….sql.gz | mysql --default-character-set=utf8mb4 -u usuario -p base`. Las copias empiezan con `SET NAMES utf8mb4;`; una de una versión anterior del sistema no trae esa línea, y si se importa sin `utf8mb4` quedan dañadas las tildes y las ñ.
   5. Descomprimir **todos** los `.zip` de archivos, del más antiguo al más nuevo: la carpeta `clinica` va dentro de `storage/app/private/` y la carpeta `web` es `public/media/`.
   6. `php artisan migrate --force` (por si el sistema es más nuevo que la copia), `php artisan optimize:clear` y `php artisan up`.
   7. Las sesiones vienen vacías: todos vuelven a entrar con su correo y su contraseña. Revisar la agenda de hoy, un paciente con radiografías y la caja del día.
8. En **Ajustes** se revisan los datos de la clínica (vienen los del recetario) y se agregan el correo, las redes y los textos del inicio. El horario de atención que muestra la web sale solo de los horarios de los doctores.
9. **Correo para recuperar contraseñas:** en `.env`, `MAIL_MAILER=smtp` con los datos del buzón que da el hosting (`MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME` y `MAIL_FROM_ADDRESS`). Con eso, "¿Olvidaste tu contraseña?" en el inicio de sesión envía al correo del usuario un enlace que sirve una hora (sale con el cron del punto 4, en menos de un minuto). Mientras siga en `log`, esa página solo dice que se le pida la contraseña al administrador, que la cambia en **Configuración → Usuarios**. Por eso conviene que el correo del administrador sea uno que de verdad revise. Si el administrador olvida la suya y no hay correo, quien tenga acceso al servidor le pone una temporal con `php artisan usuario:clave su@correo.com`.

## Cómo está organizado

### `backend/`

| Carpeta | Qué hay |
| --- | --- |
| `app/Models` | Modelos. Los datos clínicos, citas y pagos registran cada cambio (`Concerns/Auditable`) y no se borran. |
| `app/Enums` | Estados y opciones con su texto en español (estado de la cita, medio de pago, parentesco…). |
| `app/Services/Agenda` | Agenda inteligente: horarios donde cabe la duración completa más la esterilización, con prioridad a los pegados a otras citas. |
| `app/Services/Booking` | Reservas, confirmación, cambios de horario en dos niveles y cancelaciones. Toda escritura en la agenda pasa por aquí. |
| `app/Services/Access` | Enlaces personales por WhatsApp y dispositivos recordados (sin contraseñas ni códigos). |
| `app/Services/WhatsApp` | Bandeja de avisos: hoy se envían con un clic desde el WhatsApp del consultorio; mañana, por la API oficial sin cambiar nada más. |
| `app/Services/Billing` | Cargos y pagos: lo realizado se carga solo al terminar la atención y recepción cobra desde la cita. Saldo = cargos − pagos. |
| `app/Services/Clinical` | Historia clínica: atención, odontograma, plan de tratamiento, recetas, radiografías y fotos (comprimidas) y consentimientos firmados en pantalla. |
| `app/Services/Reports` | Ganancias (cobrado y realizado, por día, doctor, tratamiento y medio de pago) y los números del Resumen. Las mismas tablas alimentan el panel, la hoja para imprimir o guardar en PDF y el Excel (`app/Support/Spreadsheet`, sin librerías extra). |
| `app/Services/Backup` | Copia de seguridad de la base de datos en `.sql.gz`, sin depender de `mysqldump`, y su restauración. |
| `app/Http/Controllers/Api/V1`, `app/Http/Resources/V1`, `routes/api.php` | La API de la web: el contenido de cada página (inicio con el próximo horario libre, tratamientos con precio "desde", equipo con COP, promociones, antes y después, opiniones, preguntas frecuentes), reservar en 3 pasos, Mi cuenta y el enlace personal de WhatsApp. El contenido se edita en el panel, grupo **Web** (solo administrador). |
| `app/Http/Controllers/Site`, `app/Services/Web`, `routes/web.php` | El documento de cada página de la web (`SpaController`, `SpaDocument`): carga el frontend y lleva escritos el título, la descripción, los datos para Google (schema.org `Dentist`, `FAQPage`) y los datos de la página, para que se pinte sin esperar. También `sitemap.xml`, `robots.txt` y el archivo para guardar la cita en el calendario. |
| `config/consultorio.php` | Valores por defecto (plazos, topes, tiempos). Se pueden cambiar desde el panel (tabla `settings`). |

### `frontend/src/`

| Carpeta | Qué hay |
| --- | --- |
| `api/` | El cliente de la API (`client.ts`), las consultas con caché (`queries.ts`), las acciones del paciente como confirmar o cancelar (`actions.ts`) y la forma de cada respuesta (`types.ts`). |
| `app/` | Las direcciones de la web (`router.tsx`), que son las mismas de `backend/routes/web.php`, y lo que envuelve a todas las páginas. |
| `pages/` | Las páginas: inicio, tratamientos, promociones, equipo, antes y después, opiniones, preguntas frecuentes, contacto y privacidad; reservar cita (`booking/`), Mi cuenta (`account/`), el enlace de WhatsApp (`link/`) y cambiar el horario (`change/`). |
| `components/` | Las piezas que comparten: el marco de la página (`layout/`), las de contenido como la tarjeta de una cita o los horarios libres (`site/`) y las básicas (`ui/`). |
| `lib/` | Direcciones, avisos, el título y los datos para Google de cada página, y los enlaces de WhatsApp. |
| `styles/site.css` | Los estilos de la web, escritos a mano. |
| `test/` | Lo que comparten las pruebas: la API de mentira y datos de ejemplo. Cada prueba va junto a lo que prueba (`*.test.tsx`). |

## La API de la web

La web le pide todo a Laravel en `/api/v1`, dentro del mismo dominio. `php artisan route:list --path=api` muestra la lista completa.

| Grupo | Para qué |
| --- | --- |
| `GET site`, `home`, `treatments`, `promotions`, `team`, `cases`, `reviews`, `faqs`, `pages/…` | Los datos del consultorio y el contenido de cada página. |
| `booking/…` | Reservar: los tratamientos, los días y horarios libres, y crear la cita. |
| `account/…` | Mi cuenta desde el celular recordado: citas, confirmar, cancelar, cambiar el horario, la salud antes de la cita. |
| `links/{token}/…` | El enlace personal que llega por WhatsApp: ver la cita, confirmarla, cambiarla, cancelarla o entrar a Mi cuenta. |

- Las páginas responden `{ "data": …, "meta": … }`: lo que la pantalla muestra, con los textos ya listos, y el título, la descripción y los datos para Google.
- Las acciones (`POST`) responden con lo que se le dice al paciente (`message`) o con lo que la pantalla muestra después (`data`), y llevan el encabezado `X-XSRF-TOKEN` con el valor de la cookie `XSRF-TOKEN`.
- Los errores llegan con su estado: `422` con `errors` por campo, `409` con un `code` cuando algo cambió mientras tanto (`slot_taken`: el horario se ocupó), `403` y `404` cuando la página no es para ese celular o no existe, y `429` cuando hay demasiados intentos seguidos.

## Reglas del negocio que implementa

- **Familias:** un celular (`accounts`) puede tener varios pacientes a cargo; cada uno con su propia historia y odontograma.
- **Reserva web al instante** si el horario está libre. Queda *pendiente* hasta que el paciente confirma con su enlace; el día anterior, si no confirmó, se marca *sin confirmar* para que recepción llame.
- **Topes:** 2 citas web futuras por paciente y un tope por celular (configurable). Recepción puede bloquear números.
- **Primera cita** en la web (evaluación o tratamiento simple). Las siguientes las programa el doctor; un paciente con plan en curso no reserva sesiones solo.
- **Mi cuenta sin contraseñas:** el celular queda recordado al tocar el enlace personal que llega por WhatsApp, o al reservar la primera cita con un número que el consultorio no tenía. Ese celular queda sin verificar hasta que toque un enlace: sus reservas no cuentan como hechas desde el celular de la familia, y si el dueño del número toca un enlace desde otro celular, deja de estar recordado. Las citas pasadas, los pagos y los datos siempre piden un enlace nuevo.
- **Cambios de horario:** con más de 24 h, mismo doctor y tratamiento, el cambio es automático; si no (o la sesión tiene *fecha fija*), el paciente propone hasta 3 horarios y recepción o el doctor aprueba uno.
- **Cancelación** directa hasta el plazo que define el consultorio; después, debe comunicarse.
- **Doctores** solo manejan su propia agenda; cada cambio prepara el aviso al paciente y alerta a recepción.
- **Datos clínicos** solo los ven los doctores y el administrador si es odontólogo (Ley N.º 29733).
- **Cobros:** al terminar la atención se carga solo lo realizado: la sesión del procedimiento del plan (su parte del precio acordado; la última sesión, lo que falta) o el tratamiento de la cita a su precio, con el descuento de la promoción. Recepción lo revisa en "Cobrar", corrige o agrega conceptos y registra el pago (efectivo, tarjeta, Yape, Plin, transferencia). Los doctores no manejan dinero: si el paciente debe algo al terminar, recepción recibe el aviso solo. La administradora que también atiende elige entre cobrar ella o **Pasar a recepción**. Cargos y pagos no se borran: se anulan con motivo.
- **Ganancias y gráficos:** los ve el administrador. Recepción no. Cada doctor puede ver solo los suyos si se activa en **Ajustes → Reportes** (apagado al inicio). Lo anulado nunca suma.
- **Antes y después** solo se publica si el paciente lo autorizó por escrito; queda registrado quién lo confirmó y cuándo. Las opiniones se copian de Google tal cual: la web no inventa reseñas ni resultados.

## WhatsApp: etapa 1 y etapa 2

- **Etapa 1 (`WHATSAPP_DRIVER=click_to_chat`):** el panel arma el mensaje con un enlace personal nuevo y abre WhatsApp ya escrito; recepción solo presiona Enviar. Sin programas no oficiales.
- **Etapa 2 (`WHATSAPP_DRIVER=cloud_api`):** con el trámite de Meta listo, se completan `WHATSAPP_CLOUD_TOKEN` y `WHATSAPP_PHONE_NUMBER_ID`, y en cada texto del panel el nombre de su plantilla aprobada. `whatsapp:enviar` los manda solos cada 5 minutos.
