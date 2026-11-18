# Diagnóstico frente a la rúbrica (FASE 0)

> Parte del análisis previo (`00-analisis-del-proyecto.md`). Cifras medidas el 18/11/2026 sobre `main` (`b91fdee`) de `venga-implantes`. En esta fase no se cambió código. Fuentes de la rúbrica: `docs/transcripcion.md` (sección "C. Área de Ingeniería de Software") y `docs/lineas/software/evidencias.md` del repositorio oficial `upeuoficial/planb` (último cambio 09/09/2026).

## Cifras medidas

### Pruebas y calidad

*Tabla 10. Pruebas y calidad medidas.*

| Qué | Valor | Comando |
| --- | --- | --- |
| Pruebas del backend | **1 037 pruebas, 8 887 verificaciones, todas pasan** (2 min 39 s) | `cd backend && php artisan test` |
| Formato del backend | Sin diferencias (`passed`) | `vendor/bin/pint --test` |
| Pruebas del frontend | 126 pruebas en 16 archivos, todas pasan (7,24 s) | `cd frontend && npm test` |
| ESLint / TypeScript / Prettier | Sin errores / sin errores / sin diferencias | `npm run lint`, `npm run typecheck`, `npm run format:check` |
| Compilación del frontend | Correcta. JS 458,87 kB (138,55 kB gzip), CSS 82,94 kB (17,97 kB gzip), un solo paquete JS | `npm run build` |
| Cobertura | No se mide (workflow con `coverage: none`; sin `@vitest/coverage-v8`) | — |
| Análisis estático PHP | No hay (sin Larastan/PHPStan) | — |

Pruebas del backend por carpeta (conteo de métodos de prueba en el código):

*Tabla 11. Pruebas del backend por carpeta.*

| Carpeta | Archivos | Métodos |
| --- | ---: | ---: |
| `tests/Feature/Filament` (pantallas del panel y permisos) | 82 | 574 |
| `tests/Feature/Services` (reglas del negocio) | 30 | 210 |
| `tests/Feature/Http` (API y controladores) | 9 | 86 |
| `tests/Feature/Console` (tareas programadas) | 6 | 15 |
| `tests/Feature/Models` | 4 | 8 |
| `tests/Unit` | 11 | 30 |
| **Total** | **142** | **923** |

La ejecución reporta 1 037 pruebas y no 923 porque 13 métodos usan `#[DataProvider]` (en 12 archivos): cada uno se corre una vez por cada caso de datos.

### Tamaño del sistema

*Tabla 12. Tamaño del sistema.*

| Elemento | Cantidad | Dónde |
| --- | ---: | --- |
| Líneas de PHP en `backend/app` | 55 990 | `find app -name '*.php'` |
| Líneas de PHP en `backend/tests` | 25 160 | `find tests -name '*.php'` |
| Líneas de TypeScript en `frontend/src` (sin pruebas) | 6 758 | `*.ts`, `*.tsx` |
| Líneas de pruebas del frontend | 2 306 | `*.test.ts(x)` |
| Componentes y páginas React (`.tsx` sin pruebas) | 56 | 27 en `pages/` (páginas y sus partes), 24 en `components/`, 5 en `app/`, `lib/`, `test/` y raíz |
| Rutas de la web (React Router) | 17 + 404 | `frontend/src/app/router.tsx` |
| Rutas de la API `/api/v1` | 36 | `php artisan route:list --path=api` |
| Rutas totales de Laravel | 149 (78 del panel, 36 de la API, 24 de la web y otras, 11 internas de Livewire y Filament) | `php artisan route:list` |
| Controladores | 26 (13 de la API) | `app/Http/Controllers` |
| Form Requests / API Resources | 3 / 10 | `app/Http/Requests`, `app/Http/Resources/V1` |
| Modelos | 58 | `app/Models` |
| Migraciones | 49 | `database/migrations` |
| Tablas | 68 | `Schema::create`; `php artisan migrate` deja 69 contando `migrations` |
| Llaves foráneas / índices / índices únicos | 143 / 39 / 24 | migraciones |
| Recursos de Filament | 29 (45 páginas de recurso, 9 gestores de relaciones) | `app/Filament/Resources` |
| Páginas propias de Filament | 18 | `app/Filament/Pages` |
| Widgets del panel | 26 | `app/Filament/Widgets` |
| Servicios | 67 clases en 12 módulos + `StaffAlerts` | `app/Services` |
| Políticas | 35 | `app/Policies` |
| Enums | 40 | `app/Enums` |
| Comandos de consola propios | 10 | `app/Console/Commands` |
| Tareas programadas | 10 | `routes/console.php`, `php artisan schedule:list` |
| Factories / seeders | 21 / 3 | `database/factories`, `database/seeders` |
| Historial | 275 commits del 22/10/2026 al 18/11/2026; 97 PR integrados | `git log` |

### Módulos funcionales

Web pública, reserva en 3 pasos, Mi cuenta y enlace por WhatsApp, agenda inteligente, historia clínica con odontograma, presupuestos y planes firmados, consentimientos, laboratorio, caja y cobros, inventario, pacientes y familias, avisos de WhatsApp, reportes y resumen, configuración, copias de seguridad e historial de cambios. Detalle y ubicación en el análisis previo (`00-analisis-del-proyecto.md`), apartado "Módulos y funcionalidades".

### Roles

Administrador (con la marca de odontólogo para ver lo clínico), Recepción y Doctor en el panel (`app/Enums/UserRole.php`); el paciente entra a la web sin contraseña (celular recordado o enlace por WhatsApp).

## Evidencias CE0211 a CE0244

Leyenda: **Cubierta** = la evidencia existe y es verificable; **Parcial** = hay base real pero falta un artefacto que pide la rúbrica; **Falta** = no existe todavía; **No aplica** = modalidad no elegida (la EPE no exige todas las de CE023).

### E1 · CE021 Ingeniería de Requerimientos

*Tabla 13. Evidencias de CE021 (E1).*

| Evidencia | Estado | Qué existe | Qué falta | Cómo se cubre |
| --- | --- | --- | --- | --- |
| CE0211 Especificación de requerimientos validada | Parcial | Reglas del negocio en el `README.md`; requerimientos iniciales fuera del repo (`contexto-sistema-dental.md`, `Propuesta-sistema-dental-v2.docx` y `Sistema-dental-documentacion-completa.docx` en los archivos del proyecto); las reglas están probadas en `tests/Feature/Services`. | SRS (IEEE 830 / ISO 29148) con RF y RNF numerados, historias de usuario con criterios Gherkin y MoSCoW, acta de validación. | FASE 1: SRS extraído del código y de las pruebas; acta como plantilla `[PENDIENTE – firma]`. |
| CE0212 Prototipo navegable validado | Parcial | El sistema funciona con datos de demostración (prototipo de alta fidelidad); más de 100 capturas de iteraciones de diseño en los archivos del proyecto (`pagina-web/`, `agenda/`, `hoy/`, `configuracion/`…). | Diagrama de flujo de navegación por rol, selección de pantallas principales, guion y evidencia de validación con usuarios de la clínica. | FASE 1: flujo de navegación desde `router.tsx` y el panel; guion de validación `[PENDIENTE – estudiante]`. |
| CE0213 Diseño arquitectónico | Parcial | Arquitectura descrita en el `README.md` (backend/frontend, capa de servicios). | Diagramas C4 (contexto, contenedores, componentes) y ADR. | FASE 1: C4 en PlantUML/Mermaid y 10 ADR. |
| CE0214 Diseño detallado | Falta | Modelos, relaciones y enums de estado en el código (`AppointmentStatus`, `TreatmentPlanStatus`). | Casos de uso, diagrama de clases, secuencias y estados. | FASE 1: UML generado a partir de los modelos y servicios reales. |

### E2 · CE022 Ingeniería de la Información

*Tabla 14. Evidencias de CE022 (E2).*

| Evidencia | Estado | Qué existe | Qué falta | Cómo se cubre |
| --- | --- | --- | --- | --- |
| CE0221 Modelo de datos documentado y validado | Parcial | 49 migraciones, 68 tablas, 143 llaves foráneas. | Diagrama ER, modelo lógico, justificación de 3FN y diccionario de datos. | FASE 2: ER y diccionario generados desde las migraciones. |
| CE0222 Base de datos implementada y consultable | Parcial | Base en MySQL con migraciones y seeders (`DemoSeeder` con 11 meses de datos). | `ddl.sql` y `dml.sql` exportados, 10 a 15 consultas del negocio con su resultado. | FASE 2: `mysqldump --no-data`, consultas en `sql/consultas.sql` con salidas. |
| CE0223 Motor transaccional programado y optimizado | Falta | 48 bloques `DB::transaction` en la aplicación, 39 índices y 24 únicos. | Vistas, funciones, procedimientos, trigger y planes de ejecución (`EXPLAIN` antes y después). Hoy la base no tiene ninguno. | FASE 2: migraciones solo para `mysql` + job de CI con MySQL 8. |
| CE0224 Base de datos administrada, asegurada y operativa | Parcial | Copia diaria y `respaldo:restaurar` (restauración probada sobre MariaDB 10.11 según el README); historial de cambios de 40 modelos (`audit_logs`); permisos por rol y Ley N.º 29733 en la aplicación. | Usuarios y roles **de la base** con mínimo privilegio, tiempo de restauración medido, monitoreo (consultas lentas, tamaño de tablas). | FASE 2: `seguridad.sql`, prueba cronometrada de restauración, consultas de monitoreo. |

### E3 · CE023 Programación

*Tabla 15. Evidencias de CE023 (E3).*

| Evidencia | Estado | Qué existe | Qué falta | Cómo se cubre |
| --- | --- | --- | --- | --- |
| CE0231 Consola | No aplica | (Hay 10 comandos Artisan, pero no es la modalidad elegida.) | — | — |
| CE0232 Desktop | No aplica | — | — | — |
| CE0233 Web integrada con base de datos | Cubierta (código) | Sistema web completo: web del paciente, panel interno, MySQL, reglas del negocio y pruebas. | El documento técnico E3 y las capturas de funcionamiento. | FASE 3: documento + guion de capturas `[PENDIENTE – capturas]`. |
| CE0234 Full-stack desplegable | Parcial | Frontend React, backend Laravel y API `/api/v1` (36 rutas, 69 pruebas de la API); paquete para el hosting en CI. | API documentada (OpenAPI) y evidencia de despliegue (URL). | FASE 3: OpenAPI de la API existente; URL `[PENDIENTE – estudiante]`. |
| CE0235 Distribuido con servicios interoperables | Falta | Integración con WhatsApp (wa.me hoy, API de Meta preparada en `CloudApiChannel`). | Un servicio propio separado, con API, prueba de integración y de resiliencia. | FASE 3: servicio de IA de inasistencias (FastAPI + Docker). |
| CE0236 Móvil | Falta | La web es mobile-first, pero no es una app. | App móvil que consuma la API. | FASE 3: app móvil confirmada por el estudiante (ver "Decisiones del estudiante"). |

### E4 · CE024 Calidad de Software

*Tabla 16. Evidencias de CE024 (E4).*

| Evidencia | Estado | Qué existe | Qué falta | Cómo se cubre |
| --- | --- | --- | --- | --- |
| CE0241 Suite de pruebas automatizadas | Parcial | Backend 1 037 pruebas en verde; frontend 126 pruebas; CI las corre en cada PR. | Reporte de cobertura y clasificación por tipo y flujo crítico. | FASE 4: pcov + `@vitest/coverage-v8` (dependencia nueva: requiere aprobación) y reporte como artefacto. |
| CE0242 Pipeline de integración, despliegue y operación continua | Parcial | CI en GitHub Actions (backend, frontend y paquete para el hosting); `/up`; copias y restauración. | Despliegue automático, observabilidad en producción (logs, métricas, alertas) y rollback probado. | FASE 4: job de despliegue desde `main` con secrets, monitoreo de `/up` y alertas `[PENDIENTE – credenciales]`. |
| CE0243 Gestión ágil del proyecto | Falta | Historial real: 275 commits y 97 PR en 4 semanas con actividad (22/10 a 18/11/2026). | Project Charter, Product Backlog, Sprint Backlogs, métricas, retrospectivas y comparación con OpenUP. | FASE 4: sprints reconstruidos desde los PR con sus fechas reales. |
| CE0244 Aseguramiento y auditoría de la calidad | Falta | Pint, ESLint, Prettier y TypeScript en CI; refactorizaciones reales en el historial (por ejemplo `46395b0` y `e0f8eb2`). | Matriz ISO/IEC 25010, checklists de revisión, registro de code smells y deuda, checklist CMMI/SCAMPI con ISO/IEC 29110 y plan de mejora. | FASE 4: matriz y checklists con valores medidos; Larastan para hallar deuda. |

### Resumen

*Tabla 17. Resumen por entregable.*

| Entregable | Peso | Cubierta | Parcial | Falta | Brecha principal |
| --- | ---: | ---: | ---: | ---: | --- |
| E1 Requerimientos y diseño | 25 % | 0 | 3 | 1 | Todo el dossier (SRS, C4, ADR, UML, trazabilidad, validación). |
| E2 Plataforma de datos | 20 % | 0 | 3 | 1 | Programación en la base (vistas, funciones, procedimientos, trigger) y usuarios de la base. |
| E3 Sistema integrado | 30 % | 1 | 1 | 2 | OpenAPI, evidencia de despliegue y servicio distribuido. |
| E4 Gestión y calidad | 15 % | 0 | 2 | 2 | Cobertura, despliegue y observabilidad, gestión ágil y auditoría. |

El código está mucho más avanzado que la documentación: la mayor parte del trabajo de las FASES 1 a 5 es documentar y medir lo que ya existe; los cambios de código se concentran en el E2 (objetos de MySQL), el E3 (OpenAPI y, si se decide, el servicio de IA) y el E4 (cobertura, Larastan, despliegue y monitoreo).

## Topics del repositorio

Según `docs/anexos/estandar-topics-repositorios.md` de `upeuoficial/planb` (solo se usan nombres del catálogo oficial):

- `campus-juliaca`
- `semestre-2026-2`
- `linea-software`
- `tipo-epe`
- `seccion-…` con la sección del estudiante (`gu`, `g1`, `g2`, `g3` o `g4`): [PENDIENTE – estudiante: sección]
- `grupo-<número>-<nombre-del-proyecto>`: [PENDIENTE – estudiante: número de grupo y nombre corto del proyecto]

El estándar permite agregar los cursos que participan (por ejemplo `req`, `ads`, `bd1`, `bd2`, `lp1`, `lp2`, `is1`, `is2`, `pds`); el ejemplo oficial de EPE no los incluye. `[PENDIENTE – estudiante: confirmar si se agregan]`. Los topics se configuran en GitHub (Settings del repositorio → About → Topics) y se aplican cuando el estudiante confirme la sección y el grupo.

## Decisiones del estudiante (sección 7 del prompt)

Respondidas el 18/11/2026:

1. **Repositorio de la EPE:** `venga-implantes`.
2. **App móvil (CE0236):** sí se implementa, además de la web, la API y el servicio de IA.
3. **Despliegue:** en Microsoft Azure (coherente con la línea CE03 del mismo equipo).
4. **Integrantes (ciclo 10):** Fran Franklin Calizaya Apaza, Jhoel Hairton Apaza Quispe y Joselyn Yucra Mamani.

## Lista de `[PENDIENTE]`

- `[PENDIENTE – estudiante]` Sección (`seccion-…`) y número de grupo (`grupo-n-nombre`).
- `[PENDIENTE – estudiante]` URL del despliegue en Azure, cuando exista.
- `[PENDIENTE – estudiante]` Validación con el Dr. Vega y recepción (actas y firmas, en FASE 1).
