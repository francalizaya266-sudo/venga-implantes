# Resumen ejecutivo

**Qué protege el SGSI.** El sistema de gestión de seguridad de la información (SGSI) de Clínica Vega Implant cubre el sistema web de la clínica (la web de reservas, el panel del personal y la API), la infraestructura en Azure que lo aloja y la sede de la clínica unida por VPN. Lo más valioso son las historias clínicas, las radiografías y los datos personales de los pacientes, que la Ley N.º 29733 trata como datos sensibles.

**Cómo se construyó.** Siguiendo ISO/IEC 27001:2022 y el ciclo Planificar-Hacer-Verificar-Actuar: se inventariaron **26 activos** con su valoración de confidencialidad, integridad y disponibilidad; se evaluaron **16 riesgos** con el método de ISO/IEC 27005 (probabilidad × impacto); se eligieron controles de ISO/IEC 27002:2022 y se declaró la aplicabilidad de los **93 controles** del anexo A (63 implementados, 23 parciales, 5 planificados y 2 que no aplican).

**Qué está funcionando.** Panel con autenticación de dos factores obligatoria; base de datos sin acceso público y con TLS; secretos en Key Vault y ninguna clave en el código; red segmentada con firewalls y política *drop*; VPN IPsec; tres niveles de copia con restauración probada (12 segundos, 19/11/2026); y el SIEM Wazuh con 6 agentes activos y 3 442 alertas registradas en el día.

**Qué falta.** La auditoría interna del 19/11/2026 encontró **6 no conformidades** (sección 13): la más importante es que dos credenciales expuestas durante el despliegue aún no se rotan. Todas tienen responsable y plazo. Con los controles aplicados, ningún riesgo residual supera el nivel medio.

![](diagramas/phva.png)

*Figura 1. Ciclo PHVA del SGSI y dónde se documenta cada fase.*

# Contexto de la organización

| Aspecto | Descripción |
| --- | --- |
| Organización | Clínica Vega Implant, consultorio odontológico de implantes en Juliaca (Puno) |
| Personas | El Dr. Vega (dueño, administrador y doctor), una recepcionista y los pacientes |
| Sistema | Laravel 13 con la web en React y el panel en Filament, sobre MySQL 8 |
| Infraestructura | Azure: producción en Chile Central y sede en North Central US, unidas por VPN |
| Equipo técnico | Tres estudiantes de Ingeniería de Sistemas (UPeU): software, infraestructura y monitoreo |

**Cuestiones internas.** Personal reducido sin especialista en seguridad; presupuesto limitado al crédito de Azure for Students; el sistema reemplaza un cuaderno, así que el personal está aprendiendo a usarlo.

**Cuestiones externas.** Ley N.º 29733 de Protección de Datos Personales y su reglamento (D. S. N.º 016-2024-JUS), Ley N.º 30024 de historias clínicas electrónicas, ataques automatizados contra cualquier web pública (los registros muestran escaneos diarios), y dependencia de proveedores en la nube.

**Partes interesadas y sus requisitos.**

| Parte interesada | Requisito de seguridad |
| --- | --- |
| Pacientes | Confidencialidad de su historia y de sus datos; poder reservar a cualquier hora |
| Dr. Vega | Que nada se pierda ni se filtre; poder demostrar quién hizo cada cambio |
| Recepcionista | Entrar al panel sin complicaciones, también desde fuera de la clínica |
| Autoridad de protección de datos | Medidas de seguridad proporcionales a datos sensibles |
| Docentes de la UPeU | Evidencia verificable, sin credenciales ni datos personales reales |

# Alcance del SGSI

**Dentro del alcance:** la información de pacientes, citas, pagos y archivos clínicos; la aplicación y su código; las 6 máquinas virtuales, la base de datos, Key Vault, Blob Storage y la VPN; las cuentas del panel, de Azure y de GitHub; y los procesos de despliegue, copia, monitoreo y respuesta a incidentes.

**Fuera del alcance:** los equipos de rayos X y su software (no se conectan al sistema), la red Wi-Fi de pacientes, la contabilidad de la clínica y la seguridad física de los centros de datos de Azure, que se apoya en las certificaciones del proveedor (ISO/IEC 27001, 27017 y 27018).

**Interfaces.** Los servicios externos (WhatsApp, correo, consulta de DNI, registrador del dominio, Let's Encrypt) se tratan como proveedores (control 5.19).

# Liderazgo, política y roles

## Política de seguridad de la información

> Clínica Vega Implant protege la confidencialidad, integridad y disponibilidad de la información de sus pacientes. Para ello: (1) solo quien atiende ve la información clínica; (2) todo acceso al sistema exige identidad propia y, al panel, un segundo factor; (3) ningún secreto se guarda en el código ni se comparte por mensajes; (4) los datos se copian a diario y cada hora, y la restauración se prueba; (5) los cambios al sistema pasan por revisión y pruebas automáticas; (6) los incidentes se registran y se aprende de ellos; y (7) se cumplen la Ley N.º 29733 y la normativa de historias clínicas. Esta política se revisa cada año o tras un incidente grave.

## Roles y responsabilidades

*Tabla 1. Roles del SGSI.*

| Rol | Persona | Responsabilidades |
| --- | --- | --- |
| Dirección | Dr. Vega | Aprueba la política, el alcance y el riesgo residual; asigna recursos |
| Responsable de seguridad e infraestructura | Jhoel Hairton Apaza Quispe | Riesgos, controles de red y nube, secretos, copias, respuesta a incidentes |
| Responsable de la aplicación | Fran Franklin Calizaya Apaza | Desarrollo seguro, permisos del panel, pruebas, gestión de cambios |
| Responsable de monitoreo y cumplimiento | Joselyn Yucra Mamani | SIEM, métricas, auditoría interna, protección de datos personales |
| Usuarios | Recepcionista y doctores | Cuidar su contraseña y su celular, avisar cualquier evento extraño |

# Inventario y clasificación de la información

El inventario completo, con ubicación y propietario, está en `CE0321-Inventario-de-activos.xlsx`. Cada activo se valora de 1 a 3 en confidencialidad (C), integridad (I) y disponibilidad (D); su valor es el mayor de los tres. La clasificación usa cuatro niveles: **Público** (la web), **Interno** (operación de la clínica), **Confidencial** (datos personales y de salud) y **Secreto** (credenciales y claves).

*Tabla 2. Inventario de activos.*

<!-- inicio: activos -->

| ID | Activo | Tipo | C | I | D | Clasificación |
| --- | --- | --- | --: | --: | --: | --- |
| A01 | Historias clínicas, odontogramas y planes de tratamiento | Información | 3 | 3 | 2 | Confidencial (dato sensible de salud) |
| A02 | Datos personales de pacientes (DNI, teléfono, correo) | Información | 3 | 3 | 2 | Confidencial |
| A03 | Radiografías, fotos clínicas y firmas de consentimiento | Información | 3 | 3 | 2 | Confidencial (dato sensible de salud) |
| A04 | Agenda de citas | Información | 2 | 3 | 3 | Interno |
| A05 | Pagos, caja y comprobantes | Información | 2 | 3 | 2 | Interno |
| A06 | Historial de auditoría (quién cambió qué) | Información | 2 | 3 | 2 | Interno |
| A07 | Copias de seguridad de la base de datos | Información | 3 | 3 | 3 | Confidencial |
| A08 | Secretos (clave de la app, claves de MySQL, clave de la VPN) | Información | 3 | 3 | 2 | Secreto |
| A09 | Contenido de la web pública (servicios, precios, preguntas) | Información | 1 | 2 | 2 | Público |
| A10 | Aplicación Laravel (web, panel y API) | Software | 2 | 3 | 3 | Interno |
| A11 | Código de infraestructura (Bicep, reglas, scripts) | Software | 2 | 3 | 2 | Interno |
| A12 | vm-fw de producción (firewall, VPN, IDS) | Infraestructura | 2 | 3 | 3 | Interno |
| A13 | vm-proxy (WAF y TLS) | Infraestructura | 2 | 3 | 3 | Interno |
| A14 | vm-app (servidor de la aplicación) | Infraestructura | 3 | 3 | 3 | Confidencial |
| A15 | MySQL Flexible Server | Infraestructura | 3 | 3 | 3 | Confidencial |
| A16 | Key Vault | Infraestructura | 3 | 3 | 2 | Secreto |
| A17 | Blob Storage (copias y paquetes) | Infraestructura | 3 | 3 | 2 | Confidencial |
| A18 | vm-fw de la sede (firewall, VPN, DNS, jump host) | Infraestructura | 2 | 3 | 2 | Interno |
| A19 | vm-wazuh (SIEM y respaldo) | Infraestructura | 3 | 3 | 2 | Confidencial |
| A20 | Túnel VPN IPsec entre producción y sede | Servicio | 3 | 3 | 2 | Interno |
| A21 | Dominio vegaimplantes.me y certificado TLS | Servicio | 1 | 3 | 3 | Público |
| A22 | Cuentas del panel (administrador, recepción, doctores) | Identidad | 3 | 3 | 2 | Confidencial |
| A23 | Cuentas de Azure y GitHub del equipo | Identidad | 3 | 3 | 2 | Secreto |
| A24 | Identidades administradas (vm-app, GitHub Actions) | Identidad | 2 | 3 | 2 | Interno |
| A25 | Personal de la clínica (doctor y recepción) | Persona | 2 | 2 | 3 | Interno |
| A26 | Servicios externos (WhatsApp, correo, consulta de DNI) | Servicio | 2 | 2 | 2 | Interno |

<!-- fin: activos -->

# Evaluación de riesgos

**Método (ISO/IEC 27005).** Para cada amenaza sobre un activo se estima la probabilidad (P, de 1 a 5) y el impacto (I, de 1 a 5) sin controles; el riesgo es P × I. Niveles: 1-4 bajo, 5-9 medio, 10-16 alto y 20-25 crítico. **Criterio de aceptación:** la dirección acepta riesgos residuales de hasta 9 (medio); los demás se tratan. Las escalas detalladas están en la hoja "Criterios" de `CE0321-Matriz-de-riesgos.xlsx`.

*Tabla 3. Riesgos inherentes y residuales.*

<!-- inicio: riesgos -->

| ID | Amenaza | P×I | Nivel | Tratamiento | Residual | Nivel residual |
| --- | --- | --- | --- | --- | --- | --- |
| R01 | Robo de la contraseña de un usuario del panel (phishing, reutilización) | 4×5=20 | Crítico | Mitigar | 2×4=8 | Medio |
| R02 | Ataque a la aplicación web (inyección SQL, XSS, rutas sensibles) | 4×4=16 | Alto | Mitigar | 2×4=8 | Medio |
| R03 | Acceso directo a la base de datos desde internet | 3×5=15 | Alto | Mitigar | 1×5=5 | Medio |
| R04 | Pérdida de datos por borrado, corrupción o ransomware | 3×5=15 | Alto | Mitigar | 1×4=4 | Bajo |
| R05 | Filtración de secretos o credenciales (en el repositorio, capturas o salidas de consola) | 4×5=20 | Crítico | Mitigar | 2×4=8 | Medio |
| R06 | Acceso no autorizado por SSH a los servidores | 3×5=15 | Alto | Mitigar | 1×5=5 | Medio |
| R07 | Interceptación del tráfico entre la sede y producción | 3×4=12 | Alto | Mitigar | 1×4=4 | Bajo |
| R08 | Caída del servicio por falla de una VM o de la región | 3×3=9 | Medio | Aceptar con controles | 3×3=9 | Medio |
| R09 | Un cambio defectuoso llega a producción | 3×3=9 | Medio | Mitigar | 2×2=4 | Bajo |
| R10 | Vulnerabilidad del sistema operativo o de un paquete sin parchear | 3×4=12 | Alto | Mitigar | 2×3=6 | Medio |
| R11 | Un ataque pasa sin ser visto | 3×4=12 | Alto | Mitigar | 2×3=6 | Medio |
| R12 | Personal accede a información que no le corresponde | 2×4=8 | Medio | Mitigar | 1×3=3 | Bajo |
| R13 | Agotamiento del crédito de Azure y suspensión de la suscripción | 3×4=12 | Alto | Mitigar | 2×3=6 | Medio |
| R14 | El personal pierde el celular con la app autenticadora | 3×2=6 | Medio | Mitigar | 2×1=2 | Bajo |
| R15 | Uso de datos reales de pacientes en pruebas o en este informe | 2×5=10 | Alto | Mitigar | 1×5=5 | Medio |
| R16 | Vencimiento del certificado TLS o del dominio | 2×4=8 | Medio | Mitigar | 1×4=4 | Bajo |

<!-- fin: riesgos -->

Antes de los controles había 11 riesgos altos o críticos; después, ninguno pasa de 9: quedan 10 medios y 6 bajos. El riesgo más alto que queda es R08 (una sola VM por servicio, 3 × 3 = 9), aceptado por presupuesto con la condición de poder reconstruir en menos de 2 horas, cosa que el S2-E3 comprueba.

# Tratamiento de riesgos

*Tabla 4. Controles aplicados a cada riesgo.*

<!-- inicio: riesgos-controles -->

| ID | Controles aplicados | Responsable |
| --- | --- | --- |
| R01 | MFA obligatorio con app autenticadora y códigos de recuperación (5.17, 8.5); contraseña propia al primer ingreso; límite de intentos de Filament y de nginx (429); auditoría de accesos | Jhoel |
| R02 | WAF ModSecurity + OWASP CRS (8.20, 8.23); Eloquent con consultas preparadas (8.28); cabeceras de seguridad; límites por IP en la API; 1 105 pruebas automáticas en la CI (8.29) | Fran |
| R03 | MySQL sin acceso público, en subred delegada (8.22); TLS obligatorio (8.24); cuatro usuarios de mínimo privilegio (8.2, 8.3); NSG y nftables solo dejan llegar a vm-app | Jhoel |
| R04 | Tres copias independientes: MySQL 7 días con restauración a un punto, Blob diario con versiones y borrado suave, sede cada hora (8.13); restauración probada el 19/11 en 12 s | Jhoel |
| R05 | Secretos solo en Key Vault (5.17, 8.24); GitHub entra a Azure por OIDC, sin claves; regla de equipo "ningún secreto en el repositorio"; rotación tras exposición (INC-01) | Jhoel |
| R06 | Sin SSH desde internet en las VM internas; solo llaves; firewall de la sede como jump host; puerto 2200 solo desde la IP de administración (8.2, 8.20); alternativa run-command sin abrir puertos | Jhoel |
| R07 | VPN IPsec IKEv2 con AES-256-GCM (8.24, 8.21); reconexión automática medida en 1,4 s | Jhoel |
| R08 | Infraestructura como código para recrear en minutos (8.9); alertas de VM caída y CPU (8.16); vigilancia de /up desde GitHub; copia en otra región (5.30) | Jhoel |
| R09 | Pull requests con CI (pruebas, Larastan, MySQL 8) antes del merge (8.32); acción "volver" a la versión anterior en segundos | Fran |
| R10 | Ubuntu 24.04 LTS con actualizaciones desatendidas; Wazuh detecta vulnerabilidades; reconstrucción con cloud-init (8.8, 8.19) | Jhoel |
| R11 | Wazuh con 6 agentes activos, Suricata en los dos firewalls, registros de nftables y del WAF, honeypot (8.15, 8.16) | Joselyn |
| R12 | 35 políticas de Laravel; lo clínico solo para quien atiende; auditoría de cambios e impresiones (5.15, 5.18, 8.3) | Fran |
| R13 | Presupuesto de 40 USD con avisos al 50 % y 80 %; apagado de las VM entre sesiones (5.23) | Jhoel |
| R14 | Códigos de recuperación; el administrador puede quitar el segundo factor, con registro en la auditoría (5.17) | Fran |
| R15 | Solo datos de prueba generados (DemoSeeder); nada de datos reales en capturas ni en el repositorio (8.33, 5.34) | Joselyn |
| R16 | Renovación automática de certbot (vence el 17/02/2027, se renueva 30 días antes); dominio pagado por un año | Jhoel |

<!-- fin: riesgos-controles -->

# Declaración de Aplicabilidad

La Declaración de Aplicabilidad (SoA) recorre los 93 controles del anexo A de ISO/IEC 27001:2022 y dice, para cada uno, si aplica, en qué estado está y con qué evidencia. El detalle está en `CE0322-Declaracion-de-Aplicabilidad.xlsx` y en el anexo A de este informe.

*Tabla 5. Resumen de la SoA por tema.*

<!-- inicio: soa-resumen -->

| Tema | Implementado | Parcial | Planificado | No aplica | Total |
| --- | --: | --: | --: | --: | --: |
| 5. Organizacionales | 27 | 9 | 1 | 0 | 37 |
| 6. Personas | 2 | 3 | 3 | 0 | 8 |
| 7. Físicos | 9 | 3 | 1 | 1 | 14 |
| 8. Tecnológicos | 25 | 8 | 0 | 1 | 34 |
| Total | 63 | 23 | 5 | 2 | 93 |

<!-- fin: soa-resumen -->

Los dos controles que no aplican son 7.6 (trabajo en áreas seguras: la clínica no opera un centro de datos propio) y 8.30 (desarrollo externalizado: el equipo desarrolla todo). Los controles físicos de la nube se apoyan en Azure; los de la sede física quedan diseñados en el S1-E2.

# Políticas y procedimientos

*Tabla 6. Políticas temáticas del SGSI.*

| Política | Regla principal | Control técnico que la hace cumplir |
| --- | --- | --- |
| Control de acceso | Cada persona tiene su propia cuenta y solo ve lo de su rol | 35 políticas de Laravel; lo clínico solo para quien atiende |
| Contraseñas y MFA | Contraseña de 8 o más caracteres con letras y números, propia desde el primer ingreso, y código de la app en cada inicio de sesión | `Password::defaults`, `RequireOwnPassword`, MFA obligatorio de Filament |
| Uso aceptable | El panel es solo para atender a los pacientes de la clínica; no se exportan datos sin motivo | Auditoría de cambios, exportaciones e impresiones |
| Secretos | Ningún secreto en el código, en chats ni en capturas | Key Vault, OIDC, identidades administradas |
| Copias de seguridad | Diaria a Blob y horaria a la sede; restauración probada cada trimestre | Cron de vm-app; simulacro del S2-E3 |
| Gestión de cambios | Todo cambio es un pull request con la CI en verde | GitHub Actions; workflow de despliegue con registro |
| Red | Nada abierto que no sea necesario; cada segmento solo habla con lo que necesita | nftables (política *drop*), NSG |
| Registro y monitoreo | Todo equipo envía sus registros al SIEM; alertas de nivel 10 o más se revisan en el día | Agentes de Wazuh, Azure Monitor |
| Datos de prueba | Nunca datos reales fuera de producción | `DemoSeeder` |
| Escritorio limpio | Bloquear la pantalla al dejar el puesto; la sesión del panel expira a las 2 horas | `SESSION_LIFETIME=120` |

**Procedimientos documentados.** Despliegue, encendido y apagado, entrada a las VM, cambio de reglas del firewall, copia y restauración, ejercicio de recuperación y pruebas: `infra/README.md` y `infra/firewall/README.md`.

# Identidades, accesos y protección de datos

| Ámbito | Cómo se controla |
| --- | --- |
| Panel de la clínica | Cuenta propia por persona; contraseña temporal que el usuario cambia al entrar; **MFA obligatorio** con app autenticadora y códigos de recuperación; el administrador puede quitar el segundo factor a quien pierde el celular (queda en la auditoría como `mfa_disabled`); límite de intentos de inicio de sesión en Filament y de peticiones por IP en nginx (429) |
| Roles del panel | Administrador, recepción y doctor, con 35 políticas; lo clínico solo para quien atiende |
| Servidores | Sin contraseña: solo llave SSH; sin SSH desde internet en las VM internas; jump host en la sede; `az vm run-command` queda en el registro de actividad de Azure |
| Azure | RBAC de mínimo privilegio: vm-app solo lee secretos y escribe copias; GitHub Actions solo puede publicar en las VM de producción y subir paquetes |
| GitHub a Azure | OIDC: un token firmado por GitHub, válido solo para la rama `main`; no hay clave guardada en GitHub |
| Base de datos | Cuatro usuarios: aplicación (datos), despliegue (esquema), reportes (solo vistas) y respaldo (solo lectura) |
| Secretos | 10 secretos en Key Vault, leídos por la identidad de la VM |
| Protección de datos | Consentimiento informado firmado por el paciente; nada clínico se borra (se anula con motivo); datos de prueba en el entorno de demostración; el secreto MFA y los códigos de recuperación se guardan cifrados y no entran a la auditoría |

**Revisión de accesos.** Cada tres meses, la dirección revisa la lista de usuarios del panel (Configuración → Usuarios, con su último ingreso) y el responsable de seguridad revisa las asignaciones de roles de Azure.

# Gestión de incidentes

**Procedimiento.** (1) **Detectar**: alerta de Wazuh de nivel 10 o más, alerta de Azure Monitor o aviso del personal. (2) **Evaluar**: el responsable de seguridad decide si es un incidente y su severidad. (3) **Contener**: bloquear la IP en nftables, desactivar la cuenta en el panel o rotar el secreto en Key Vault. (4) **Erradicar y recuperar**: corregir la causa, restaurar desde las copias si hace falta. (5) **Notificar**: si hay datos personales afectados, avisar a la dirección y evaluar la comunicación a la autoridad y a los pacientes. (6) **Aprender**: registrar el incidente y su acción correctiva.

*Tabla 7. Registro de incidentes.*

<!-- inicio: incidentes -->

| ID | Fecha | Qué pasó | Severidad | Acción | Estado |
| --- | --- | --- | --- | --- | --- |
| INC-01 | 19/11/2026 | La clave del administrador del tablero de Wazuh se mostró en la salida de una consola durante el despliegue | Medio | Rotar la clave con wazuh-passwords-tool y guardarla solo en Key Vault | Abierto |
| INC-02 | 19/11/2026 | Una captura de pantalla compartida durante la compra del dominio mostró la contraseña de la cuenta del registrador | Medio | Cambiar esa contraseña, activar MFA en el registrador y revisar capturas antes de compartirlas | Abierto |
| INC-03 | 19/11/2026 | El NAT de destino del honeypot capturaba también el SSH que llegaba por la VPN | Bajo | El DNAT solo se aplica a lo que llega a la IP de la WAN (ip daddr $WAN_IP) | Cerrado |

<!-- fin: incidentes -->

# Continuidad y recuperación

| Escenario | RPO | RTO objetivo | Resultado de la prueba (19/11/2026) |
| --- | --- | --- | --- |
| Restaurar la base de datos desde la copia de Blob | 24 h (copia diaria) | 1 h | Restaurada en 12 s en una base aparte; mismos totales que producción (S2-E3) |
| Copia horaria a la sede | 1 h | — | Copias de cada hora en vm-wazuh; la última a las 10:15 |
| Corte de la VPN | 0 | 1 min | Volvió sola en 1,4 s |
| Volver a la versión anterior de la app | 0 | 5 min | Una publicación completa tarda 65 s; la acción "volver" solo cambia un enlace |
| Recrear una VM | 0 | 2 h | Procedimiento documentado; no se ejecutó sobre producción en esta ronda |

El detalle de las pruebas está en el S2-E3, sección de continuidad.

# Ética de la seguridad

El equipo sigue el Código de Ética y Conducta Profesional de la ACM (2018), en especial los principios 1.6 (respetar la privacidad), 1.7 (respetar la confidencialidad), 2.8 (acceder a sistemas solo cuando se está autorizado) y 2.9 (diseñar sistemas robustos y seguros).

| Principio | Cómo se aplicó en el proyecto |
| --- | --- |
| Confidencialidad | Ningún informe, captura o archivo del repositorio contiene datos de pacientes reales, credenciales ni llaves; las IP públicas se reemplazan en las evidencias |
| Privacidad | Solo datos generados en las demostraciones; lo clínico solo lo ve quien atiende |
| Consentimiento | El paciente firma su consentimiento informado en el sistema; el sistema se construye con la clínica del Dr. Vega, que participa en su validación (E1 de CE02) |
| Alcance ético de las pruebas | Las pruebas de seguridad se hicieron solo sobre la infraestructura propia del equipo, sin escanear ni atacar a terceros; el honeypot solo registra, no responde ataques |
| Divulgación responsable | Si el equipo encuentra una vulnerabilidad en un componente de terceros (Laravel, Filament, Wazuh), la reporta al proyecto por su canal privado antes de publicarla |
| Transparencia | Este informe declara lo que falta (no conformidades e incidentes abiertos) |

**Declaración de responsabilidad ética.** Los integrantes del equipo declaramos que las pruebas descritas se realizaron sobre recursos propios, con datos de prueba, sin afectar a terceros, y que no publicamos credenciales ni datos personales reales.

*Tabla 8. Firmas del equipo.*

| Integrante | Rol | Fecha | Firma |
| --- | --- | --- | --- |
| Fran Franklin Calizaya Apaza | Responsable de la aplicación | 19/11/2026 | |
| Jhoel Hairton Apaza Quispe | Responsable de seguridad e infraestructura | 19/11/2026 | |
| Joselyn Yucra Mamani | Responsable de monitoreo y cumplimiento | 19/11/2026 | |

# Monitoreo, medición y auditoría interna

## Métricas

*Tabla 9. Métricas del SGSI medidas el 19/11/2026.*

| Métrica | Meta | Valor | Fuente |
| --- | --- | --- | --- |
| Usuarios del panel con MFA activo | 100 % tras su primer ingreso | 0 de 2: el MFA se activó hoy y nadie ha vuelto a entrar | `users.app_authentication_secret` |
| Agentes de Wazuh activos | 6 de 6 | 6 de 6 | `agent_control -l` |
| Alertas de Wazuh de nivel 10 o más revisadas | 100 % | 82 en el día, todas de la regla "Multiple web server 400 error codes from same source ip" (escaneos automáticos de internet) | Wazuh |
| Copias horarias recibidas en la sede | 24 por día | 5 desde que se encendió la sede hoy, sin huecos | `/srv/respaldos` |
| Restauración probada | Cada trimestre | 1 (19/11/2026) | S2-E3 |
| Riesgos sobre el nivel aceptado | 0 | 0 | Matriz de riesgos |
| Controles de la SoA implementados | ≥ 70 % | 63 de 91 aplicables (69 %) | SoA |
| Incidentes abiertos con más de 7 días | 0 | 0 (dos abiertos hoy) | Registro de incidentes |
| Días del certificado TLS antes de vencer | > 30 | 89 | certbot |

## Auditoría interna

El 19/11/2026, la responsable de monitoreo y cumplimiento revisó los controles de la SoA contra la configuración real (salidas en `evidencias/`), con una lista de verificación basada en ISO/IEC 27001:2022 (cláusulas 4 a 10) y el anexo A. Hallazgos:

*Tabla 10. No conformidades y acciones correctivas.*

<!-- inicio: no-conformidades -->

| ID | Control | Hallazgo | Tipo | Acción correctiva | Responsable | Plazo |
| --- | --- | --- | --- | --- | --- | --- |
| NC-01 | 8.4, 8.32 | La rama main no tiene protección: se podría fusionar sin revisión ni CI en verde | Menor | Activar la protección de main con revisión obligatoria y las cuatro comprobaciones de la CI | Jhoel | 26/11/2026 |
| NC-02 | 8.23 | El WAF está en modo detección (DetectionOnly) | Menor | Revisar los 615 eventos del registro de auditoría, ajustar exclusiones y pasar a SecRuleEngine On | Jhoel | 03/12/2026 |
| NC-03 | 5.17, 8.24 | Key Vault acepta conexiones de red pública y no tiene protección de purga | Menor | Activar la protección de purga y limitar la red a la VNet y a la IP de administración | Jhoel | 26/11/2026 |
| NC-04 | 5.26 | Dos incidentes con credenciales expuestas siguen abiertos (INC-01 e INC-02) | Mayor | Rotar las dos credenciales y cerrar los incidentes con evidencia | Jhoel | 20/11/2026 |
| NC-05 | 8.16 | La vigilancia de /up desde GitHub está desactivada (variable VIGILAR_PRODUCCION vacía) | Menor | Definir VIGILAR_PRODUCCION=true mientras producción esté encendida | Joselyn | 20/11/2026 |
| NC-06 | 8.7, 8.20 | Suricata trabaja como IDS, no bloquea | Observación | Probar el modo IPS en una rama y medir la memoria del firewall | Joselyn | 10/12/2026 |

<!-- fin: no-conformidades -->

## Revisión por la dirección

**Acta de revisión por la dirección.** Fecha: 19/11/2026. Participan: Dr. Vega (dirección) y el equipo técnico.

| Entrada | Conclusión |
| --- | --- |
| Estado de las acciones anteriores | Primera revisión |
| Cambios en el contexto | El panel se abre a internet para el trabajo fuera de la clínica: se exige MFA |
| Desempeño (métricas y auditoría) | 6 no conformidades, una mayor (credenciales expuestas) |
| Riesgos | Ninguno sobre el nivel aceptado; R08 aceptado |
| Oportunidades de mejora | WAF en bloqueo, Suricata como IPS, protección de la rama main |
| Decisiones | Se aprueba la política, el alcance y el riesgo residual; las acciones correctivas se cumplen en los plazos de la tabla 10; próxima revisión en marzo de 2027 |

*Tabla 11. Firmas del acta.*

| Participante | Rol | Fecha | Firma |
| --- | --- | --- | --- |
| Dr. Jassmanny Neil Vega Jaen | Dirección | | |
| Jhoel Hairton Apaza Quispe | Responsable de seguridad | 19/11/2026 | |

# Matriz maestra de trazabilidad

*Tabla 12. De la evidencia de la rúbrica al documento, el control y la prueba.*

| Evidencia | Requisito | Dónde se cumple | Control | Cómo se verifica |
| --- | --- | --- | --- | --- |
| CE0321 | Alcance, activos, C-I-D | Secciones 2 a 4, inventario en Excel | 5.9, 5.12 | Revisión del inventario |
| CE0321 | Riesgos y objetivos | Secciones 5 y 6, matriz en Excel | — | Riesgo residual ≤ 9 |
| CE0322 | Controles y SoA | Sección 7, SoA en Excel | Anexo A | Estado de los 93 controles |
| CE0322 | Políticas | Sección 8 | 5.1 | Control técnico de cada política |
| CE0322 | Control de accesos y MFA | Sección 9 | 5.15-5.18, 8.5 | `PanelMultiFactorTest` (8 pruebas); `/panel/login` pide código |
| CE0322 | Respaldo | Sección 11 | 8.13 | Simulacro de restauración (S2-E3) |
| CE0323 | Métricas | Sección 13.1 | 9.1 de la norma | Tabla 9 |
| CE0323 | Auditoría y no conformidades | Sección 13.2 | 9.2, 10.2 | Tabla 10 con responsables y plazos |
| CE0323 | Revisión por la dirección | Sección 13.3 | 9.3 | Acta firmada |
| CE0324 | Ética ACM | Sección 12 | 5.34 | Declaración firmada |

# Referencias

[1] ISO/IEC, "ISO/IEC 27001:2022 Information security, cybersecurity and privacy protection — Information security management systems — Requirements," 2022.

[2] ISO/IEC, "ISO/IEC 27002:2022 Information security, cybersecurity and privacy protection — Information security controls," 2022.

[3] ISO/IEC, "ISO/IEC 27005:2022 Information security, cybersecurity and privacy protection — Guidance on managing information security risks," 2022.

[4] NIST, "The NIST Cybersecurity Framework (CSF) 2.0," NIST CSWP 29, feb. 2024.

[5] ACM, "ACM Code of Ethics and Professional Conduct," 2018. [En línea]. Disponible: https://www.acm.org/code-of-ethics

[6] Congreso de la República del Perú, "Ley N.º 29733, Ley de Protección de Datos Personales," 2011.

[7] Ministerio de Justicia y Derechos Humanos del Perú, "Decreto Supremo N.º 016-2024-JUS, Reglamento de la Ley N.º 29733," 2024.

[8] Congreso de la República del Perú, "Ley N.º 30024, Ley que crea el Registro Nacional de Historias Clínicas Electrónicas," 2013.

[9] Microsoft, "Microsoft Azure compliance offerings," Microsoft Learn, 2026. [En línea]. Disponible: https://learn.microsoft.com/azure/compliance/

# Anexo A. Declaración de Aplicabilidad completa {-}

*Tabla 13. Los 93 controles del anexo A de ISO/IEC 27001:2022.*

<!-- inicio: soa -->

| Control | Nombre | Estado | Justificación o evidencia |
| --- | --- | --- | --- |
| 5.1 | Políticas de seguridad de la información | Implementado | Política general y políticas temáticas del SGSI (sección 8 del informe) |
| 5.2 | Roles y responsabilidades de seguridad | Implementado | Tabla de roles del SGSI: dirección (Dr. Vega), responsable de seguridad (Jhoel), software (Fran), monitoreo (Joselyn) |
| 5.3 | Segregación de funciones | Parcial | Roles distintos en el panel; un equipo pequeño concentra funciones técnicas, compensado con revisión de pull requests |
| 5.4 | Responsabilidades de la dirección | Parcial | La política y el riesgo residual esperan la firma del Dr. Vega (acta de la sección 13) |
| 5.5 | Contacto con las autoridades | Implementado | Autoridad Nacional de Protección de Datos Personales (MINJUSDH) en el procedimiento de incidentes |
| 5.6 | Contacto con grupos de interés especial | Parcial | Avisos de seguridad de Ubuntu, Laravel y Wazuh; PeCERT como referencia |
| 5.7 | Inteligencia de amenazas | Parcial | Reglas ET Open de Suricata y OWASP CRS actualizadas; sin fuente propia |
| 5.8 | Seguridad en la gestión de proyectos | Implementado | Cada cambio de infraestructura es un pull request con revisión (S2-E3, control de cambios) |
| 5.9 | Inventario de información y otros activos | Implementado | Inventario de 26 activos (anexo en Excel) |
| 5.10 | Uso aceptable de la información | Implementado | Política de uso aceptable del panel |
| 5.11 | Devolución de activos | Implementado | Al salir, se apaga "Puede entrar al panel" y se quita el acceso a Azure y GitHub |
| 5.12 | Clasificación de la información | Implementado | Esquema Público, Interno, Confidencial y Secreto (inventario) |
| 5.13 | Etiquetado de la información | Parcial | Etiquetas en los recursos de Azure; los documentos impresos llevan "Confidencial" |
| 5.14 | Transferencia de información | Implementado | HTTPS, VPN IPsec y SSH; nada por correo sin cifrar |
| 5.15 | Control de acceso | Implementado | 35 políticas de Laravel por rol; NSG y nftables por red |
| 5.16 | Gestión de identidades | Implementado | Altas y bajas en Configuración → Usuarios; identidades administradas en Azure |
| 5.17 | Información de autenticación | Implementado | Contraseña propia al primer ingreso, MFA obligatorio, secretos en Key Vault |
| 5.18 | Derechos de acceso | Implementado | Roles en el panel; RBAC de Azure con mínimo privilegio; revisión trimestral |
| 5.19 | Seguridad en las relaciones con proveedores | Parcial | Proveedores evaluados (Azure, GitHub, Meta, registrador); sin contratos propios |
| 5.20 | Seguridad en los acuerdos con proveedores | Parcial | Se aceptan los términos estándar de Azure (con su acuerdo de protección de datos) y GitHub |
| 5.21 | Seguridad en la cadena de suministro TIC | Parcial | Dependencias fijadas en composer.lock y package-lock.json; versión de Wazuh fijada |
| 5.22 | Seguimiento de servicios de proveedores | Parcial | Saldo de Azure revisado cada semana; estado de servicios de Azure |
| 5.23 | Seguridad en el uso de servicios en la nube | Implementado | Azure con RBAC, identidades administradas, recursos privados y presupuesto |
| 5.24 | Planificación de la gestión de incidentes | Implementado | Procedimiento de incidentes (sección 10) |
| 5.25 | Evaluación de eventos de seguridad | Implementado | Niveles de Wazuh: 10 o más se revisan en el día |
| 5.26 | Respuesta a incidentes | Implementado | Contener, erradicar, recuperar y registrar (sección 10) |
| 5.27 | Aprendizaje de los incidentes | Implementado | Registro de incidentes con acción correctiva (INC-01 a INC-03) |
| 5.28 | Recopilación de evidencias | Implementado | Registros en Wazuh, auditoría de la app y salidas guardadas en evidencias/ |
| 5.29 | Seguridad durante una interrupción | Implementado | Las copias y la VPN conservan el cifrado durante una recuperación |
| 5.30 | Preparación de las TIC para la continuidad | Implementado | RTO y RPO definidos y probados (S2-E3) |
| 5.31 | Requisitos legales y contractuales | Implementado | Ley 29733 y su reglamento (D. S. 016-2024-JUS), Ley 30024 (historia clínica) |
| 5.32 | Derechos de propiedad intelectual | Implementado | Software libre con licencias compatibles; código propio del equipo |
| 5.33 | Protección de los registros | Implementado | Nada clínico se borra: se anula con motivo; auditoría inmutable desde el panel |
| 5.34 | Privacidad y protección de datos personales | Implementado | Consentimiento informado firmado; lo clínico solo para quien atiende; datos de prueba |
| 5.35 | Revisión independiente | Planificado | Revisión por el docente evaluador y por la otra línea (CE02) |
| 5.36 | Cumplimiento de políticas y normas | Implementado | Auditoría interna del 19/11/2026 con 6 no conformidades (sección 13) |
| 5.37 | Procedimientos operativos documentados | Implementado | infra/README.md y firewall/README.md |
| 6.1 | Investigación de antecedentes | Parcial | El Dr. Vega contrata personalmente; no hay verificación formal |
| 6.2 | Términos y condiciones de empleo | Planificado | Cláusula de confidencialidad en el contrato de la recepcionista |
| 6.3 | Concienciación y formación | Planificado | Inducción de 30 minutos sobre MFA, phishing y datos de salud al activar el panel |
| 6.4 | Proceso disciplinario | Planificado | Reglamento interno de la clínica |
| 6.5 | Responsabilidades tras el cambio de empleo | Implementado | Se desactiva el acceso; la historia clínica que firmó se conserva |
| 6.6 | Acuerdos de confidencialidad | Parcial | Declaración de ética del equipo técnico (sección 12); falta la del personal |
| 6.7 | Trabajo remoto | Implementado | Panel desde internet solo con MFA; administración solo por jump host o run-command |
| 6.8 | Notificación de eventos de seguridad | Parcial | Definido en el procedimiento de incidentes: el personal avisa al responsable de seguridad por WhatsApp |
| 7.1 | Perímetros de seguridad física | Implementado | Centros de datos de Azure (certificación ISO 27001 del proveedor) |
| 7.2 | Controles físicos de entrada | Implementado | Responsabilidad de Azure; en la sede, recepción con llave |
| 7.3 | Seguridad de oficinas e instalaciones | Planificado | Pantalla del puesto de recepción orientada hacia dentro (política de escritorio limpio) |
| 7.4 | Monitoreo de la seguridad física | Implementado | Responsabilidad de Azure |
| 7.5 | Protección contra amenazas físicas | Implementado | Responsabilidad de Azure; sede física de referencia con UPS (S1-E2) |
| 7.6 | Trabajo en áreas seguras | No aplica | No hay áreas seguras propias: el centro de datos es de Azure |
| 7.7 | Escritorio y pantalla limpios | Parcial | La sesión del panel expira a los 120 minutos; el bloqueo de pantalla del puesto es política |
| 7.8 | Ubicación y protección de equipos | Implementado | Responsabilidad de Azure; diseño de rack para la sede (S1-E2) |
| 7.9 | Seguridad de activos fuera de las instalaciones | Parcial | Política: el celular con la app autenticadora debe tener bloqueo de pantalla |
| 7.10 | Medios de almacenamiento | Parcial | Discos administrados por Azure cifrados en reposo; sin medios extraíbles en uso |
| 7.11 | Servicios de suministro | Implementado | Responsabilidad de Azure |
| 7.12 | Seguridad del cableado | Implementado | Responsabilidad de Azure |
| 7.13 | Mantenimiento de equipos | Implementado | Responsabilidad de Azure; parches del sistema operativo por el equipo |
| 7.14 | Eliminación segura de equipos | Implementado | Al borrar un disco, Azure lo sanea según NIST 800-88 |
| 8.1 | Dispositivos de usuario final | Parcial | Navegador actualizado y bloqueo de pantalla; sin gestión centralizada |
| 8.2 | Derechos de acceso privilegiado | Implementado | Administrador del panel solo el Dr. Vega; sudo solo con llave; RBAC mínimo en Azure |
| 8.3 | Restricción del acceso a la información | Implementado | Políticas por rol; usuario de reportes solo con vistas |
| 8.4 | Acceso al código fuente | Parcial | Escritura solo para el equipo; la rama main aún no exige revisión obligatoria (NC-01) |
| 8.5 | Autenticación segura | Implementado | MFA obligatorio en el panel; llaves SSH; OIDC para GitHub |
| 8.6 | Gestión de la capacidad | Implementado | Uso medido de RAM y disco (S1-E2); alerta de CPU |
| 8.7 | Protección contra malware | Parcial | Wazuh (integridad de archivos y rootcheck); sin antivirus en los servidores Linux |
| 8.8 | Gestión de vulnerabilidades técnicas | Parcial | Actualizaciones desatendidas de Ubuntu; detector de vulnerabilidades de Wazuh |
| 8.9 | Gestión de la configuración | Implementado | Toda la configuración es código: Bicep, cloud-init, nftables, nginx |
| 8.10 | Eliminación de información | Implementado | Copias rotadas (48 en la sede); borrado suave de 14 días en Blob |
| 8.11 | Enmascaramiento de datos | Parcial | El usuario de reportes de MySQL solo lee las vistas de reportes |
| 8.12 | Prevención de fuga de datos | Parcial | Salida a internet de las subredes solo por 80, 443, 587 y 123 |
| 8.13 | Copias de seguridad | Implementado | Tres niveles de copia; restauración probada |
| 8.14 | Redundancia de las instalaciones | Parcial | Copia en otra región; una sola VM por servicio (riesgo R08 aceptado) |
| 8.15 | Registro de eventos | Implementado | Wazuh, auditoría de Laravel, registros de nginx, nftables y Suricata |
| 8.16 | Actividades de monitoreo | Implementado | Wazuh y Azure Monitor con alertas por correo |
| 8.17 | Sincronización de relojes | Implementado | NTP de Azure y zona America/Lima en todas las VM |
| 8.18 | Uso de programas utilitarios privilegiados | Implementado | run-command queda en el registro de actividad de Azure |
| 8.19 | Instalación de software en sistemas operativos | Implementado | Solo por cloud-init o scripts revisados del repositorio |
| 8.20 | Seguridad de redes | Implementado | Firewalls con política drop, NSG en cada subred |
| 8.21 | Seguridad de los servicios de red | Implementado | TLS 1.2+ en la web, HSTS, IPsec con AES-256-GCM |
| 8.22 | Segregación de redes | Implementado | 5 subredes en producción y 6 en la sede; pruebas negativas |
| 8.23 | Filtrado web | Parcial | WAF en modo detección; pasa a bloqueo tras medir falsos positivos |
| 8.24 | Uso de criptografía | Implementado | TLS, IPsec, cifrado de MySQL y Blob en reposo, secreto MFA cifrado con la clave de la app |
| 8.25 | Ciclo de vida de desarrollo seguro | Implementado | Ramas, pull requests, CI con pruebas y Larastan |
| 8.26 | Requisitos de seguridad de las aplicaciones | Implementado | Requerimientos no funcionales de seguridad del E1 de CE02 |
| 8.27 | Arquitectura y principios de ingeniería seguros | Implementado | Defensa en capas, mínimo privilegio, nada expuesto que no haga falta |
| 8.28 | Codificación segura | Implementado | Eloquent con consultas preparadas, validación de entradas, Larastan |
| 8.29 | Pruebas de seguridad en desarrollo y aceptación | Implementado | Pruebas de MFA, proxies de confianza, permisos; pruebas negativas de red |
| 8.30 | Desarrollo externalizado | No aplica | Todo el desarrollo lo hace el equipo |
| 8.31 | Separación de entornos | Implementado | Local, CI (base de datos efímera) y producción separados |
| 8.32 | Gestión de cambios | Implementado | Pull request, CI, merge y despliegue registrado en GitHub Actions |
| 8.33 | Información de prueba | Implementado | Solo datos generados (DemoSeeder); ningún dato real |
| 8.34 | Protección durante las auditorías | Implementado | Las pruebas se hacen en horario acordado y sobre datos de prueba |

<!-- fin: soa -->
