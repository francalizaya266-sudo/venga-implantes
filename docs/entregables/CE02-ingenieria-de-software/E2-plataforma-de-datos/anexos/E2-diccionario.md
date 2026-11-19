# Anexo A. Diccionario de datos

Generado con `python3 docs/entregables/CE02-ingenieria-de-software/E2-plataforma-de-datos/generar-modelo.py` desde `sql/ddl.sql` (lo que MySQL 8 tiene de verdad), los comentarios de las migraciones y los enums de los modelos. En **Llave**, PK es la llave primaria, UK un valor único y FK una llave foránea con su acción al borrar la fila referida. Los nulos se marcan con **Sí**. Los dominios siguen el orden de la tabla 1 del documento.

## Pacientes, cuentas y avisos {-}

### `accounts` {-}

Una cuenta = un celular. Puede tener varios pacientes a cargo (familia).

*Tabla A1. Columnas de `accounts`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `phone` | varchar(20) |  |  | UK | E.164, ej. +51987654321 |
| `phone_verified_at` | timestamp | Sí |  |  | Cuándo se verificó el celular. |
| `accepts_promotions` | tinyint(1) |  | 0 |  | Acepta recibir promociones. |
| `promotions_consent_at` | timestamp | Sí |  |  | Cuándo aceptó recibir promociones. |
| `privacy_accepted_at` | timestamp | Sí |  |  | Consentimiento de tratamiento de datos (Ley 29733). |
| `privacy_version` | varchar(20) | Sí |  |  | Versión de la política de privacidad aceptada. |
| `blocked_at` | timestamp | Sí |  |  | Cuándo se bloqueó la cuenta. |
| `blocked_reason` | varchar(255) | Sí |  |  | Motivo del bloqueo. |
| `blocked_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que bloqueó la cuenta. |
| `web_booking_limit` | tinyint unsigned | Sí |  |  | Tope de citas web futuras para todo el celular; null = valor general. |
| `last_seen_at` | timestamp | Sí |  |  | Último ingreso a la web. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |
| `deleted_at` | timestamp | Sí |  |  | Borrado lógico (SoftDeletes): la fila queda y se oculta. |

### `patients` {-}

*Tabla A2. Columnas de `patients`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `account_id` | bigint unsigned | Sí |  | FK → `accounts` (pone NULL) | Cuenta (celular) a la que pertenece. |
| `relationship` | varchar(20) |  | self |  | Valores: `self` (Titular), `child` (Hijo(a)), `parent` (Padre / madre), `spouse` (Esposo(a) / pareja), `sibling` (Hermano(a)), `grandparent` (Abuelo(a)), `grandchild` (Nieto(a)), `other` (Otro). |
| `first_name` | varchar(255) |  |  |  | Nombres. |
| `last_name` | varchar(255) |  |  |  | Apellidos. |
| `document_type` | varchar(10) | Sí |  | UK | Valores: `dni` (DNI), `ce` (Carné de extranjería), `passport` (Pasaporte), `other` (Otro). |
| `document_number` | varchar(20) | Sí |  | UK | Número del documento de identidad. |
| `birth_date` | date | Sí |  |  | Fecha de nacimiento. |
| `sex` | char(1) | Sí |  |  | Valores: `F` (Femenino), `M` (Masculino). |
| `email` | varchar(255) | Sí |  |  | Correo electrónico. |
| `address` | varchar(255) | Sí |  |  | Dirección. |
| `district` | varchar(255) | Sí |  |  | Distrito. |
| `occupation` | varchar(255) | Sí |  |  | Ocupación. |
| `emergency_contact_name` | varchar(255) | Sí |  |  | Contacto de emergencia: nombre. |
| `emergency_contact_phone` | varchar(20) | Sí |  |  | Contacto de emergencia: celular. |
| `emergency_contact_relationship` | varchar(50) | Sí |  |  | Contacto de emergencia: parentesco. |
| `guardian_name` | varchar(150) | Sí |  |  | Apoderado (menor de edad): nombre. |
| `guardian_document` | varchar(20) | Sí |  |  | Apoderado: documento de identidad. |
| `guardian_relationship` | varchar(20) | Sí |  |  | Valores: `self` (Titular), `child` (Hijo(a)), `parent` (Padre / madre), `spouse` (Esposo(a) / pareja), `sibling` (Hermano(a)), `grandparent` (Abuelo(a)), `grandchild` (Nieto(a)), `other` (Otro). |
| `origin` | varchar(30) | Sí |  |  | Valores: `google` (Google), `facebook` (Facebook), `instagram` (Instagram), `tiktok` (TikTok), `whatsapp` (WhatsApp), `campaign` (Campaña), `referral` (Referido), `recommendation` (Recomendación), `walk_in` (Pasaba por aquí), `other` (Otro). |
| `referral_code` | varchar(12) | Sí |  | UK | Código para recomendar a otros pacientes. |
| `referred_by_patient_id` | bigint unsigned | Sí |  | FK → `patients` (pone NULL) | Paciente que lo recomendó. |
| `admin_notes` | text | Sí |  |  | Notas internas del personal sobre el paciente. |
| `first_visit_at` | timestamp | Sí |  |  | Primera atención del paciente. |
| `last_visit_at` | timestamp | Sí |  |  | Última atención del paciente. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |
| `deleted_at` | timestamp | Sí |  |  | Borrado lógico (SoftDeletes): la fila queda y se oculta. |

Únicos compuestos: (document_type, document_number).

### `remembered_devices` {-}

Dispositivos recordados: reservar con un toque y ver próximas citas.

*Tabla A3. Columnas de `remembered_devices`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `account_id` | bigint unsigned |  |  | FK → `accounts` (en cascada) | Cuenta (celular) a la que pertenece. |
| `token_hash` | char(64) |  |  | UK | Hash del enlace o dispositivo (el valor original no se guarda). |
| `user_agent` | varchar(255) | Sí |  |  | Navegador del celular recordado. |
| `ip_address` | varchar(45) | Sí |  |  | Dirección IP. |
| `last_used_at` | timestamp | Sí |  |  | Último uso. |
| `expires_at` | timestamp |  |  |  | Vence el. |
| `revoked_at` | timestamp | Sí |  |  | Cuándo se revocó. |
| `verified_at` | timestamp | Sí |  |  | Cuándo se verificó (verificación reforzada). |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `magic_links` {-}

Enlaces personales que viajan en los mensajes de WhatsApp. Solo se guarda el hash: el enlace se arma en el momento de enviar.

*Tabla A4. Columnas de `magic_links`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `account_id` | bigint unsigned |  |  | FK → `accounts` (en cascada) | Cuenta (celular) a la que pertenece. |
| `appointment_id` | bigint unsigned | Sí |  | FK → `appointments` (en cascada) | Cita. |
| `purpose` | varchar(20) |  |  |  | Valores: `appointment` (Enlace de la cita), `access` (Acceso a Mi cuenta), `verify` (Verificación de identidad). |
| `token_hash` | char(64) |  |  | UK | Hash del enlace o dispositivo (el valor original no se guarda). |
| `expires_at` | timestamp |  |  |  | Vence el. |
| `first_used_at` | timestamp | Sí |  |  | Primer uso. |
| `last_used_at` | timestamp | Sí |  |  | Último uso. |
| `use_count` | int unsigned |  | 0 |  | Veces que se usó. |
| `revoked_at` | timestamp | Sí |  |  | Cuándo se revocó. |
| `created_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que lo registró. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `referrals` {-}

*Tabla A5. Columnas de `referrals`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `referrer_patient_id` | bigint unsigned |  |  | FK → `patients` (en cascada) | Paciente que recomienda. |
| `referred_patient_id` | bigint unsigned |  |  | UK FK → `patients` (en cascada) | Paciente recomendado. |
| `appointment_id` | bigint unsigned | Sí |  | FK → `appointments` (pone NULL) | Cita. |
| `status` | varchar(20) |  | pending |  | Valores: `pending` (Por asistir), `qualified` (Beneficio ganado), `redeemed` (Beneficio usado). |
| `qualified_at` | timestamp | Sí |  |  | Cuándo la recomendación cumplió las condiciones. |
| `referrer_redeemed_at` | timestamp | Sí |  |  | Cuándo usó su beneficio quien recomendó. |
| `referred_redeemed_at` | timestamp | Sí |  |  | Cuándo usó su beneficio el recomendado. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `message_templates` {-}

Textos de los avisos, editables por el administrador.

*Tabla A6. Columnas de `message_templates`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `key` | varchar(50) |  |  | UK | Clave. Valores: `booking_confirmation` (Confirmación de reserva web), `appointment_scheduled` (Cita programada por el consultorio), `appointment_reminder` (Recordatorio de la cita), `appointment_rescheduled` (Cambio de horario), `appointment_cancelled` (Cita cancelada por el consultorio), `reschedule_approved` (Solicitud de cambio aprobada), `reschedule_rejected` (Solicitud de cambio no aprobada), `access_link` (Enlace de acceso a Mi cuenta), `prescription` (Receta e indicaciones), `treatment_plan` (Plan de tratamiento (presupuesto)), `waitlist_offer` (Horario libre para lista de espera), `installment_reminder` (Aviso de cuota por vencer), `installment_overdue` (Cuota atrasada), `plan_follow_up` (Presupuesto sin respuesta), `plan_next_session` (Plan sin próxima cita), `checkup_recall` (Le toca control), `birthday` (Saludo de cumpleaños), `payment_receipt` (Constancia de pago), `review_request` (Pedido de opinión en Google), `campaign_announcement` (Promoción a quien acepta recibirlas), `aftercare` (Cuidados después de la atención), `missed_visit` (Faltó o canceló y no tiene otra cita). |
| `name` | varchar(255) |  |  |  | Nombre. |
| `body` | text |  |  |  | Texto del aviso. |
| `api_template_name` | varchar(255) | Sí |  |  | Plantilla aprobada por Meta, para cuando se active la API oficial. |
| `api_language` | varchar(10) |  | es |  | Idioma de la plantilla en la API de Meta. |
| `is_active` | tinyint(1) |  | 1 |  | Activo. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `whatsapp_messages` {-}

Bandeja de avisos: el panel los prepara y recepción presiona Enviar. Con la API oficial, el mismo registro se envía solo.

*Tabla A7. Columnas de `whatsapp_messages`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `account_id` | bigint unsigned | Sí |  | FK → `accounts` (pone NULL) | Cuenta (celular) a la que pertenece. |
| `patient_id` | bigint unsigned | Sí |  | FK → `patients` (pone NULL) | Paciente. |
| `appointment_id` | bigint unsigned | Sí |  | FK → `appointments` (pone NULL) | Cita. |
| `template_key` | varchar(50) |  |  |  | Valores: `booking_confirmation` (Confirmación de reserva web), `appointment_scheduled` (Cita programada por el consultorio), `appointment_reminder` (Recordatorio de la cita), `appointment_rescheduled` (Cambio de horario), `appointment_cancelled` (Cita cancelada por el consultorio), `reschedule_approved` (Solicitud de cambio aprobada), `reschedule_rejected` (Solicitud de cambio no aprobada), `access_link` (Enlace de acceso a Mi cuenta), `prescription` (Receta e indicaciones), `treatment_plan` (Plan de tratamiento (presupuesto)), `waitlist_offer` (Horario libre para lista de espera), `installment_reminder` (Aviso de cuota por vencer), `installment_overdue` (Cuota atrasada), `plan_follow_up` (Presupuesto sin respuesta), `plan_next_session` (Plan sin próxima cita), `checkup_recall` (Le toca control), `birthday` (Saludo de cumpleaños), `payment_receipt` (Constancia de pago), `review_request` (Pedido de opinión en Google), `campaign_announcement` (Promoción a quien acepta recibirlas), `aftercare` (Cuidados después de la atención), `missed_visit` (Faltó o canceló y no tiene otra cita). |
| `phone` | varchar(20) |  |  |  | Celular al que se envía. |
| `params` | json | Sí |  |  | Datos que completan la plantilla (JSON). |
| `status` | varchar(20) |  | pending |  | Valores: `pending` (Por enviar), `sent` (Enviado), `cancelled` (Descartado), `failed` (Falló). |
| `channel` | varchar(20) |  | click_to_chat |  | Valores: `click_to_chat` (WhatsApp del consultorio), `cloud_api` (API oficial de WhatsApp). |
| `due_at` | datetime | Sí |  |  | Cuándo se debe enviar. |
| `opened_at` | timestamp | Sí |  |  | Cuándo se abrió para enviar. |
| `opened_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que lo abrió. |
| `sent_at` | timestamp | Sí |  |  | Cuándo se envió. |
| `sent_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que lo envió. |
| `provider_message_id` | varchar(255) | Sí |  |  | Identificador del mensaje en la API de Meta. |
| `error` | text | Sí |  |  | Error al enviar. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

## Catálogo, doctores y agenda {-}

### `treatments` {-}

*Tabla A8. Columnas de `treatments`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `name` | varchar(255) |  |  |  | Nombre. |
| `slug` | varchar(255) |  |  | UK | Identificador para la dirección web. |
| `kind` | varchar(20) | Sí |  |  | Valores: `evaluation` (Evaluación), `orthodontics` (Ortodoncia), `implant` (Implante), `root_canal` (Endodoncia), `extraction` (Extracción o cirugía), `whitening` (Blanqueamiento), `cleaning` (Limpieza), `filling` (Curación o resina), `other` (Otro). |
| `summary` | varchar(500) | Sí |  |  | Resumen. |
| `description` | text | Sí |  |  | Descripción. |
| `price_from` | decimal(10,2) | Sí |  |  | Precio "desde" (S/). |
| `show_price` | tinyint(1) |  | 1 |  | Muestra el precio en la web. |
| `duration_minutes` | smallint unsigned |  | 30 |  | Duración en minutos. |
| `buffer_minutes` | smallint unsigned | Sí |  |  | Tiempo de limpieza y esterilización; null = valor general del consultorio. |
| `sessions_label` | varchar(50) | Sí |  |  | Texto de sesiones (por ejemplo, "3 a 4 sesiones"). |
| `checkup_months` | tinyint unsigned | Sí |  |  | Meses hasta el próximo control. |
| `next_session_days` | smallint unsigned | Sí |  |  | Días hasta la siguiente sesión. |
| `before_instructions` | varchar(300) | Sí |  |  | Indicaciones antes de la atención. |
| `aftercare` | text | Sí |  |  | Cuidados después de la atención. |
| `is_evaluation` | tinyint(1) |  | 0 |  | Es una evaluación. |
| `online_bookable` | tinyint(1) |  | 0 |  | El paciente puede reservarlo solo en la web como primera cita. |
| `is_public` | tinyint(1) |  | 1 |  | Se muestra en la web. |
| `image_path` | varchar(255) | Sí |  |  | Ruta de la imagen. |
| `seo_title` | varchar(255) | Sí |  |  | Título para buscadores. |
| `seo_description` | varchar(300) | Sí |  |  | Descripción para buscadores. |
| `sort_order` | smallint unsigned |  | 0 |  | Orden en que se muestra. |
| `is_active` | tinyint(1) |  | 1 |  | Activo. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |
| `deleted_at` | timestamp | Sí |  |  | Borrado lógico (SoftDeletes): la fila queda y se oculta. |

### `doctors` {-}

*Tabla A9. Columnas de `doctors`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `user_id` | bigint unsigned | Sí |  | UK FK → `users` (pone NULL) | Usuario del panel. |
| `name` | varchar(255) |  |  |  | Nombre. |
| `slug` | varchar(255) |  |  | UK | Identificador para la dirección web. |
| `specialty` | varchar(255) | Sí |  |  | Especialidad. |
| `cop_number` | varchar(20) | Sí |  |  | Colegio Odontológico del Perú y Registro Nacional de Especialista. |
| `rne_number` | varchar(20) | Sí |  |  | Número de Registro Nacional de Especialista. |
| `bio` | text | Sí |  |  | Presentación para la web. |
| `education` | text | Sí |  |  | Formación. |
| `photo_path` | varchar(255) | Sí |  |  | Ruta de la foto. |
| `agenda_color` | varchar(7) |  | #0ea5e9 |  | Color en la agenda. |
| `is_public` | tinyint(1) |  | 1 |  | Se muestra en la web. |
| `accepts_online_booking` | tinyint(1) |  | 1 |  | Recibe reservas en línea. |
| `sort_order` | smallint unsigned |  | 0 |  | Orden en que se muestra. |
| `is_active` | tinyint(1) |  | 1 |  | Activo. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |
| `deleted_at` | timestamp | Sí |  |  | Borrado lógico (SoftDeletes): la fila queda y se oculta. |

### `doctor_treatment` {-}

Qué tratamientos hace cada doctor y, si quiere, con su propio tiempo.

*Tabla A10. Columnas de `doctor_treatment`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `doctor_id` | bigint unsigned |  |  | UK FK → `doctors` (en cascada) | Doctor. |
| `treatment_id` | bigint unsigned |  |  | UK FK → `treatments` (en cascada) | Tratamiento. |
| `duration_minutes` | smallint unsigned | Sí |  |  | Duración propia de este doctor para el tratamiento (minutos). |
| `learned_minutes` | smallint unsigned | Sí |  |  | Duración aprendida de las atenciones reales (minutos). |
| `learned_samples` | smallint unsigned | Sí |  |  | Atenciones medidas para aprender la duración. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

Únicos compuestos: (doctor_id, treatment_id).

### `doctor_schedules` {-}

Horario semanal de atención (puede haber varios tramos por día).

*Tabla A11. Columnas de `doctor_schedules`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `doctor_id` | bigint unsigned |  |  | FK → `doctors` (en cascada) | Doctor. |
| `weekday` | tinyint unsigned |  |  |  | ISO: 1 = lunes … 7 = domingo |
| `starts_at` | time |  |  |  | Hora de inicio del tramo. |
| `ends_at` | time |  |  |  | Hora de fin del tramo. |
| `valid_from` | date | Sí |  |  | Vigente desde. |
| `valid_until` | date | Sí |  |  | Vigente hasta. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `schedule_blocks` {-}

Horarios bloqueados: almuerzo, cursos, vacaciones. Sin doctor = todo el consultorio.

*Tabla A12. Columnas de `schedule_blocks`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `doctor_id` | bigint unsigned | Sí |  | FK → `doctors` (en cascada) | Doctor. |
| `type` | varchar(20) |  | other |  | Tipo. Valores: `lunch` (Descanso), `course` (Curso), `vacation` (Vacaciones), `holiday` (Feriado), `other` (Otro). |
| `starts_at` | datetime |  |  |  | Inicio. |
| `ends_at` | datetime |  |  |  | Fin. |
| `reason` | varchar(255) | Sí |  |  | Motivo del bloqueo. |
| `created_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que lo registró. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `campaigns` {-}

*Tabla A13. Columnas de `campaigns`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `name` | varchar(255) |  |  |  | Nombre. |
| `slug` | varchar(255) |  |  | UK | Identificador para la dirección web. |
| `summary` | varchar(500) | Sí |  |  | Resumen. |
| `description` | text | Sí |  |  | Descripción. |
| `image_path` | varchar(255) | Sí |  |  | Ruta de la imagen. |
| `treatment_id` | bigint unsigned | Sí |  | FK → `treatments` (pone NULL) | Tratamiento. |
| `treatment_package_id` | bigint unsigned | Sí |  | FK → `treatment_packages` (pone NULL) | Una campaña puede ofrecer un paquete. |
| `discount_type` | varchar(10) |  | none |  | Valores: `none` (Sin descuento), `percent` (Porcentaje), `amount` (Monto fijo (S/)). |
| `discount_value` | decimal(10,2) | Sí |  |  | Valor del descuento. |
| `starts_at` | datetime |  |  |  | Se publica y se retira sola según estas fechas. |
| `ends_at` | datetime |  |  |  | Fin de la campaña. |
| `promo_code` | varchar(30) | Sí |  | UK | Código promocional. |
| `max_redemptions` | int unsigned | Sí |  |  | Máximo de usos. |
| `show_on_home` | tinyint(1) |  | 1 |  | Se muestra en la portada. |
| `whatsapp_text` | text | Sí |  |  | Texto del mensaje de WhatsApp. |
| `views_count` | int unsigned |  | 0 |  | Veces que se vio. |
| `is_active` | tinyint(1) |  | 1 |  | Activo. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |
| `deleted_at` | timestamp | Sí |  |  | Borrado lógico (SoftDeletes): la fila queda y se oculta. |

### `appointments` {-}

*Tabla A14. Columnas de `appointments`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `patient_id` | bigint unsigned |  |  | FK → `patients` (restringe) | Paciente. |
| `doctor_id` | bigint unsigned |  |  | FK → `doctors` (restringe) | Doctor. |
| `treatment_id` | bigint unsigned |  |  | FK → `treatments` (restringe) | Tratamiento. |
| `treatment_plan_item_id` | bigint unsigned | Sí |  | FK → `treatment_plan_items` (pone NULL) | Sesión del plan de tratamiento (la llave foránea se agrega con las tablas clínicas). |
| `series_id` | char(36) | Sí |  |  | Serie de citas repetidas a la que pertenece. |
| `starts_at` | datetime |  |  |  | Inicio. |
| `ends_at` | datetime |  |  |  | Fin. |
| `buffer_minutes` | smallint unsigned |  | 0 |  | Tiempo de esterilización reservado después de la cita. |
| `custom_duration` | tinyint(1) |  | 0 |  | La duración se cambió a mano. |
| `status` | varchar(20) |  | pending |  | Valores: `pending` (Pendiente), `confirmed` (Confirmada), `checked_in` (En sala), `in_progress` (En atención), `completed` (Atendida), `no_show` (No asistió), `cancelled` (Cancelada). |
| `booked_via` | varchar(20) |  |  |  | Valores: `web` (Web (paciente)), `reception` (Recepción), `doctor` (Doctor), `assistant` (Asistente IA). |
| `origin` | varchar(30) | Sí |  |  | Valores: `google` (Google), `facebook` (Facebook), `instagram` (Instagram), `tiktok` (TikTok), `whatsapp` (WhatsApp), `campaign` (Campaña), `referral` (Referido), `recommendation` (Recomendación), `walk_in` (Pasaba por aquí), `other` (Otro). |
| `campaign_id` | bigint unsigned | Sí |  | FK → `campaigns` (pone NULL) | Campaña. |
| `promo_code` | varchar(30) | Sí |  |  | Código promocional. |
| `is_new_patient` | tinyint(1) |  | 0 |  | Paciente nuevo. |
| `fixed_date` | tinyint(1) |  | 0 |  | El doctor la marcó con fecha fija: el paciente no la mueve solo. |
| `is_overbooked` | tinyint(1) |  | 0 |  | Sobrecupo. |
| `from_verified_device` | tinyint(1) |  | 0 |  | Se reservó desde un celular verificado. |
| `patient_note` | varchar(500) | Sí |  |  | Nota del paciente al reservar. |
| `internal_notes` | text | Sí |  |  | Notas internas. |
| `confirmed_at` | timestamp | Sí |  |  | Cuándo se confirmó. |
| `confirmed_via` | varchar(20) | Sí |  |  | Valores: `patient_link` (Enlace del paciente), `reception` (Recepción), `doctor` (Doctor). |
| `unconfirmed_flagged_at` | timestamp | Sí |  |  | El día anterior sin confirmar: recepción debe llamar. |
| `checked_in_at` | timestamp | Sí |  |  | Cuándo llegó el paciente. |
| `started_at` | timestamp | Sí |  |  | Cuándo empezó la atención. |
| `finished_at` | timestamp | Sí |  |  | Cuándo terminó. |
| `no_show_at` | timestamp | Sí |  |  | Cuándo se marcó que no asistió. |
| `cancelled_at` | timestamp | Sí |  |  | Cuándo se canceló. |
| `cancelled_by` | varchar(20) | Sí |  |  | Valores: `patient` (Paciente), `clinic` (Consultorio). |
| `cancelled_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que canceló. |
| `cancellation_reason` | varchar(255) | Sí |  |  | Motivo de la cancelación. |
| `reschedule_count` | tinyint unsigned |  | 0 |  | Veces que se cambió de horario. |
| `created_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que lo registró. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |
| `deleted_at` | timestamp | Sí |  |  | Borrado lógico (SoftDeletes): la fila queda y se oculta. |

### `appointment_events` {-}

Línea de tiempo legible de cada cita (creada, confirmada, movida…).

*Tabla A15. Columnas de `appointment_events`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `appointment_id` | bigint unsigned |  |  | FK → `appointments` (en cascada) | Cita. |
| `type` | varchar(30) |  |  |  | Qué pasó con la cita. |
| `actor` | varchar(20) |  |  |  | patient \| staff \| system |
| `user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario del panel. |
| `from_starts_at` | datetime | Sí |  |  | Horario anterior. |
| `to_starts_at` | datetime | Sí |  |  | Horario nuevo. |
| `notes` | varchar(255) | Sí |  |  | Notas del evento. |
| `meta` | json | Sí |  |  | Datos adicionales del evento (JSON). |
| `created_at` | timestamp |  | CURRENT_TIMESTAMP |  | Cuándo se creó (Laravel). |

### `reschedule_requests` {-}

Cambio de horario que necesita aprobación (nivel 2).

*Tabla A16. Columnas de `reschedule_requests`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `appointment_id` | bigint unsigned |  |  | FK → `appointments` (en cascada) | Cita. |
| `requested_by` | varchar(20) |  |  |  | Valores: `patient` (Paciente), `doctor` (Doctor), `reception` (Recepción). |
| `requested_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que pidió el cambio. |
| `status` | varchar(20) |  | pending |  | Valores: `pending` (Pendiente), `approved` (Aprobada), `rejected` (Rechazada), `withdrawn` (Retirada), `expired` (Cerrada). |
| `requested_doctor_id` | bigint unsigned | Sí |  | FK → `doctors` (pone NULL) | Doctor pedido. |
| `requested_treatment_id` | bigint unsigned | Sí |  | FK → `treatments` (pone NULL) | Tratamiento pedido. |
| `reason` | varchar(500) | Sí |  |  | Motivo del pedido. |
| `approved_option_id` | bigint unsigned | Sí |  |  | Horario propuesto que se aprobó. |
| `resolved_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que resolvió. |
| `resolved_at` | timestamp | Sí |  |  | Cuándo se resolvió. |
| `response_note` | varchar(500) | Sí |  |  | Respuesta al paciente. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `reschedule_request_options` {-}

Hasta 3 horarios propuestos por el paciente.

*Tabla A17. Columnas de `reschedule_request_options`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `reschedule_request_id` | bigint unsigned |  |  | FK → `reschedule_requests` (en cascada) | Pedido de cambio. |
| `doctor_id` | bigint unsigned |  |  | FK → `doctors` (en cascada) | Doctor. |
| `starts_at` | datetime |  |  |  | Horario propuesto por el paciente. |
| `ends_at` | datetime |  |  |  | Fin. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `waitlist_entries` {-}

*Tabla A18. Columnas de `waitlist_entries`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `patient_id` | bigint unsigned |  |  | FK → `patients` (en cascada) | Paciente. |
| `treatment_id` | bigint unsigned | Sí |  | FK → `treatments` (pone NULL) | Tratamiento. |
| `doctor_id` | bigint unsigned | Sí |  | FK → `doctors` (pone NULL) | Doctor. |
| `earliest_date` | date | Sí |  |  | Desde qué fecha puede. |
| `latest_date` | date | Sí |  |  | Hasta qué fecha puede. |
| `preferred_period` | varchar(20) |  | any |  | any \| morning \| afternoon Valores: `any` (A cualquier hora), `morning` (En la mañana), `afternoon` (En la tarde). |
| `notes` | varchar(500) | Sí |  |  | Notas. |
| `status` | varchar(20) |  | waiting |  | Valores: `waiting` (En espera), `offered` (Avisado), `booked` (Agendado), `removed` (Retirado). |
| `offered_at` | timestamp | Sí |  |  | Cuándo se le ofreció un horario. |
| `offered_starts_at` | timestamp | Sí |  |  | Horario ofrecido. |
| `offered_until` | timestamp | Sí |  |  | La oferta vence el. |
| `offered_doctor_id` | bigint unsigned | Sí |  | FK → `doctors` (pone NULL) | Doctor del horario ofrecido. |
| `held_until` | timestamp | Sí |  |  | Horario apartado hasta. |
| `appointment_id` | bigint unsigned | Sí |  | FK → `appointments` (pone NULL) | Cita. |
| `created_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que lo registró. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `desk_notes` {-}

Pendientes de recepción: lo que hay que acordarse de hacer un día, para que no dependa de un papel ni de quién esté de turno.

*Tabla A19. Columnas de `desk_notes`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `body` | varchar(300) |  |  |  | Texto de la nota. |
| `due_on` | date |  |  |  | Para cuándo es la nota. |
| `patient_id` | bigint unsigned | Sí |  | FK → `patients` (pone NULL) | Paciente. |
| `done_at` | timestamp | Sí |  |  | Cuándo se hizo. |
| `done_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que la hizo. |
| `created_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que lo registró. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

## Atención clínica {-}

### `medical_backgrounds` {-}

Antecedentes médicos: una ficha vigente por paciente (los cambios quedan en auditoría).

*Tabla A20. Columnas de `medical_backgrounds`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `patient_id` | bigint unsigned |  |  | UK FK → `patients` (en cascada) | Paciente. |
| `conditions` | json | Sí |  |  | diabetes, hipertensión, cardiopatía… |
| `allergies` | text | Sí |  |  | Alergias. |
| `medications` | text | Sí |  |  | Medicamentos que toma. |
| `surgeries` | text | Sí |  |  | Cirugías previas. |
| `is_pregnant` | tinyint(1) | Sí |  |  | Está embarazada. |
| `pregnancy_weeks` | tinyint unsigned | Sí |  |  | Semanas de embarazo. |
| `is_breastfeeding` | tinyint(1) | Sí |  |  | Da de lactar. |
| `smoker` | tinyint(1) | Sí |  |  | Fuma. |
| `notes` | text | Sí |  |  | Notas. |
| `filled_by_patient_at` | timestamp | Sí |  |  | Cuándo lo llenó el propio paciente. |
| `updated_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Último usuario que lo modificó. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `consultations` {-}

Cada atención: motivo, examen, diagnóstico, evolución e indicaciones.

*Tabla A21. Columnas de `consultations`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `patient_id` | bigint unsigned |  |  | FK → `patients` (restringe) | Paciente. |
| `doctor_id` | bigint unsigned |  |  | FK → `doctors` (restringe) | Doctor. |
| `appointment_id` | bigint unsigned | Sí |  | FK → `appointments` (pone NULL) | Cita. |
| `attended_at` | datetime |  |  |  | Fecha y hora de la atención. |
| `chief_complaint` | text | Sí |  |  | Motivo de consulta. |
| `current_illness` | text | Sí |  |  | Enfermedad actual. |
| `clinical_exam` | text | Sí |  |  | Examen clínico. |
| `vital_signs` | json | Sí |  |  | Signos vitales. |
| `diagnosis_notes` | text | Sí |  |  | Notas del diagnóstico. |
| `procedures` | text | Sí |  |  | Procedimientos realizados. |
| `procedure_details` | json | Sí |  |  | Los datos propios del tratamiento en la evolución: {"tooth": "36", "surfaces": "MO", "shade": "A2"}. |
| `indications` | text | Sí |  |  | Indicaciones al paciente. |
| `next_steps` | text | Sí |  |  | Próximos pasos. |
| `prices` | json | Sí |  |  | El precio que puso el dentista en lo que se hizo, cuando no es el sugerido: {"item-12": 250, "treatment-5": 180}. |
| `closed_at` | timestamp | Sí |  |  | Cuándo se cerró la atención. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |
| `deleted_at` | timestamp | Sí |  |  | Borrado lógico (SoftDeletes): la fila queda y se oculta. |

### `icd10_codes` {-}

*Tabla A22. Columnas de `icd10_codes`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `code` | varchar(10) |  |  | UK | Código. |
| `description` | varchar(255) |  |  |  | Descripción. |
| `is_dental` | tinyint(1) |  | 1 |  | Es un diagnóstico dental. |

### `consultation_diagnoses` {-}

*Tabla A23. Columnas de `consultation_diagnoses`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `consultation_id` | bigint unsigned |  |  | FK → `consultations` (en cascada) | Atención. |
| `icd10_code_id` | bigint unsigned | Sí |  | FK → `icd10_codes` (pone NULL) | Diagnóstico CIE-10. |
| `description` | varchar(255) | Sí |  |  | Descripción del diagnóstico. |
| `tooth` | varchar(10) | Sí |  |  | Pieza dental. |
| `type` | varchar(20) |  | definitive |  | Tipo de diagnóstico. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `consultation_treatments` {-}

Lo que se hizo en la atención fuera del plan (lo programado en la cita y lo que se hizo de más): de aquí salen el cobro y los materiales, no de lo que estaba programado.

*Tabla A24. Columnas de `consultation_treatments`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `consultation_id` | bigint unsigned |  |  | UK FK → `consultations` (en cascada) | Atención. |
| `treatment_id` | bigint unsigned |  |  | UK FK → `treatments` (restringe) | Tratamiento. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

Únicos compuestos: (consultation_id, treatment_id).

### `odontogram_conditions` {-}

Catálogo de hallazgos del odontograma (norma técnica del MINSA).

*Tabla A25. Columnas de `odontogram_conditions`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `code` | varchar(40) |  |  | UK | Código. |
| `name` | varchar(255) |  |  |  | Nombre. |
| `abbreviation` | varchar(10) | Sí |  |  | Abreviatura. |
| `applies_to` | varchar(10) |  | tooth |  | surface \| tooth \| span |
| `abbreviations` | json | Sí |  |  | siglas permitidas, ej. AM, R, IV |
| `allows_good_state` | tinyint(1) |  | 1 |  | Admite estado bueno. |
| `allows_bad_state` | tinyint(1) |  | 1 |  | Admite estado malo. |
| `sort_order` | smallint unsigned |  | 0 |  | Orden en que se muestra. |
| `is_active` | tinyint(1) |  | 1 |  | Activo. |

### `odontograms` {-}

Una versión por consulta para ver la evolución.

*Tabla A26. Columnas de `odontograms`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `patient_id` | bigint unsigned |  |  | FK → `patients` (restringe) | Paciente. |
| `consultation_id` | bigint unsigned | Sí |  | FK → `consultations` (pone NULL) | Atención. |
| `doctor_id` | bigint unsigned | Sí |  | FK → `doctors` (pone NULL) | Doctor. |
| `type` | varchar(20) |  | evolution |  | Tipo. Valores: `initial` (Inicial), `evolution` (Evolución). |
| `dentition` | varchar(20) |  | permanent |  | Valores: `permanent` (Adulto (permanente)), `primary` (Niño (decidua)), `mixed` (Mixta). |
| `specifications` | text | Sí |  |  | Especificaciones del odontograma. |
| `observations` | text | Sí |  |  | Observaciones. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |
| `deleted_at` | timestamp | Sí |  |  | Borrado lógico (SoftDeletes): la fila queda y se oculta. |

### `odontogram_entries` {-}

*Tabla A27. Columnas de `odontogram_entries`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `odontogram_id` | bigint unsigned |  |  | FK → `odontograms` (en cascada) | Odontograma. |
| `tooth` | tinyint unsigned |  |  |  | numeración FDI: 11–48 adultos, 51–85 niños |
| `surface` | varchar(2) | Sí |  |  | V, L, P, M, D, O, I |
| `odontogram_condition_id` | bigint unsigned |  |  | FK → `odontogram_conditions` (restringe) | Hallazgo del catálogo. |
| `state` | varchar(10) |  |  |  | bad = rojo (por tratar) \| good = azul (tratado) Valores: `bad` (Por tratar (rojo)), `good` (Tratado (azul)). |
| `abbreviation` | varchar(10) | Sí |  |  | Abreviatura. |
| `to_tooth` | tinyint unsigned | Sí |  |  | prótesis o aparatos que abarcan varias piezas |
| `notes` | varchar(255) | Sí |  |  | Notas. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `prescriptions` {-}

*Tabla A28. Columnas de `prescriptions`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `patient_id` | bigint unsigned |  |  | FK → `patients` (restringe) | Paciente. |
| `doctor_id` | bigint unsigned |  |  | FK → `doctors` (restringe) | Doctor. |
| `consultation_id` | bigint unsigned | Sí |  | FK → `consultations` (pone NULL) | Atención. |
| `issued_at` | datetime |  |  |  | Fecha de emisión. |
| `indications` | text | Sí |  |  | Indicaciones generales de la receta. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |
| `deleted_at` | timestamp | Sí |  |  | Borrado lógico (SoftDeletes): la fila queda y se oculta. |

### `prescription_items` {-}

*Tabla A29. Columnas de `prescription_items`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `prescription_id` | bigint unsigned |  |  | FK → `prescriptions` (en cascada) | Receta. |
| `medication` | varchar(255) |  |  |  | Medicamento. |
| `presentation` | varchar(255) | Sí |  |  | Presentación. |
| `dose` | varchar(255) | Sí |  |  | Dosis. |
| `frequency` | varchar(255) | Sí |  |  | Frecuencia. |
| `duration` | varchar(255) | Sí |  |  | Duración del tratamiento. |
| `instructions` | varchar(255) | Sí |  |  | Indicaciones. |
| `sort_order` | smallint unsigned |  | 0 |  | Orden en que se muestra. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `consent_templates` {-}

*Tabla A30. Columnas de `consent_templates`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `name` | varchar(255) |  |  |  | Nombre. |
| `treatment_id` | bigint unsigned | Sí |  | FK → `treatments` (pone NULL) | Tratamiento. |
| `body` | longtext |  |  |  | Texto del consentimiento. |
| `version` | smallint unsigned |  | 1 |  | Versión. |
| `is_active` | tinyint(1) |  | 1 |  | Activo. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `consents` {-}

*Tabla A31. Columnas de `consents`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `patient_id` | bigint unsigned |  |  | FK → `patients` (restringe) | Paciente. |
| `consent_template_id` | bigint unsigned | Sí |  | FK → `consent_templates` (pone NULL) | Modelo de consentimiento. |
| `treatment_id` | bigint unsigned | Sí |  | FK → `treatments` (pone NULL) | Tratamiento. |
| `doctor_id` | bigint unsigned | Sí |  | FK → `doctors` (pone NULL) | Doctor. |
| `title` | varchar(255) |  |  |  | Título del consentimiento. |
| `body_snapshot` | longtext |  |  |  | Texto del consentimiento tal como se firmó. |
| `signer_name` | varchar(255) |  |  |  | Quién firma: nombre. |
| `signer_document` | varchar(20) | Sí |  |  | Quién firma: documento de identidad. |
| `signer_relationship` | varchar(20) |  | self |  | Valores: `self` (Titular), `child` (Hijo(a)), `parent` (Padre / madre), `spouse` (Esposo(a) / pareja), `sibling` (Hermano(a)), `grandparent` (Abuelo(a)), `grandchild` (Nieto(a)), `other` (Otro). |
| `signature_path` | varchar(255) | Sí |  |  | Ruta de la imagen de la firma. |
| `clinical_file_id` | bigint unsigned | Sí |  | FK → `clinical_files` (pone NULL) | Archivo clínico (el documento firmado). |
| `signed_at` | timestamp | Sí |  |  | Cuándo se firmó. |
| `revoked_at` | timestamp | Sí |  |  | Cuándo se revocó. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `clinical_files` {-}

Radiografías, fotos clínicas y documentos (disco privado, nunca público).

*Tabla A32. Columnas de `clinical_files`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `patient_id` | bigint unsigned |  |  | FK → `patients` (restringe) | Paciente. |
| `consultation_id` | bigint unsigned | Sí |  | FK → `consultations` (pone NULL) | Atención. |
| `type` | varchar(20) |  |  |  | Tipo. Valores: `xray` (Radiografía), `photo` (Foto clínica), `consent` (Consentimiento), `document` (Documento), `other` (Otro). |
| `title` | varchar(255) | Sí |  |  | Título del archivo. |
| `disk` | varchar(20) |  | local |  | Disco de almacenamiento. |
| `path` | varchar(255) |  |  |  | Ruta del archivo. |
| `mime_type` | varchar(100) | Sí |  |  | Tipo de archivo (MIME). |
| `size_bytes` | int unsigned |  | 0 |  | Tamaño en bytes. |
| `taken_at` | date | Sí |  |  | Cuándo se tomó. |
| `uploaded_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que lo subió. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |
| `deleted_at` | timestamp | Sí |  |  | Borrado lógico (SoftDeletes): la fila queda y se oculta. |

### `lab_orders` {-}

Trabajos enviados al laboratorio dental (coronas, prótesis, férulas): cuándo salieron, cuándo llegan y cuánto cuestan.

*Tabla A33. Columnas de `lab_orders`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `patient_id` | bigint unsigned |  |  | FK → `patients` (restringe) | Paciente. |
| `doctor_id` | bigint unsigned | Sí |  | FK → `doctors` (pone NULL) | Doctor. |
| `description` | varchar(200) |  |  |  | Descripción. |
| `laboratory` | varchar(120) |  |  |  | Laboratorio. |
| `sent_on` | date |  |  |  | Fecha de envío. |
| `due_on` | date |  |  |  | Fecha en que se espera el trabajo. |
| `received_on` | date | Sí |  |  | Fecha de recepción. |
| `cost` | decimal(10,2) | Sí |  |  | Costo (S/). |
| `expense_id` | bigint unsigned | Sí |  | FK → `expenses` (pone NULL) | Gasto con que se pagó. |
| `notes` | varchar(500) | Sí |  |  | Notas. |
| `created_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que lo registró. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `clinical_templates` {-}

Plantillas por tratamiento: precargan los campos habituales.

*Tabla A34. Columnas de `clinical_templates`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `treatment_id` | bigint unsigned | Sí |  | FK → `treatments` (pone NULL) | Tratamiento. |
| `user_id` | bigint unsigned | Sí |  | FK → `users` (en cascada) | Usuario del panel. |
| `name` | varchar(255) |  |  |  | Nombre. |
| `content` | json |  |  |  | Contenido. |
| `is_active` | tinyint(1) |  | 1 |  | Activo. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `text_snippets` {-}

Opciones rápidas y atajos (/limp). Sin usuario = compartida por todo el equipo.

*Tabla A35. Columnas de `text_snippets`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `user_id` | bigint unsigned | Sí |  | FK → `users` (en cascada) | Usuario del panel. |
| `field` | varchar(30) |  | any |  | Valores: `any` (Cualquier campo), `chief_complaint` (Motivo de consulta), `clinical_exam` (Examen clínico), `diagnosis` (Diagnóstico), `procedures` (Evolución (qué se hizo)), `indications` (Indicaciones), `prescription` (Receta). |
| `label` | varchar(255) |  |  |  | Etiqueta. |
| `body` | text |  |  |  | Texto que se inserta. |
| `shortcut` | varchar(30) | Sí |  |  | Atajo (por ejemplo, /limp). |
| `status` | varchar(20) |  | active |  | Valores: `active` (Activa), `proposed` (Propuesta para todos), `archived` (Archivada). |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `text_snippet_usages` {-}

Cuántas veces usa cada doctor cada opción: las más usadas salen primero.

*Tabla A36. Columnas de `text_snippet_usages`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `text_snippet_id` | bigint unsigned |  |  | UK FK → `text_snippets` (en cascada) | Opción rápida. |
| `user_id` | bigint unsigned |  |  | UK FK → `users` (en cascada) | Usuario del panel. |
| `count` | int unsigned |  | 0 |  | Veces que se usó. |
| `last_used_at` | timestamp | Sí |  |  | Último uso. |

Únicos compuestos: (text_snippet_id, user_id).

## Presupuestos y planes de tratamiento {-}

### `treatment_packages` {-}

Paquete que arma el doctor: varios tratamientos a un precio, y cómo se puede pagar.

*Tabla A37. Columnas de `treatment_packages`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `name` | varchar(120) |  |  |  | Nombre. |
| `description` | text | Sí |  |  | Descripción. |
| `price` | decimal(10,2) |  |  |  | Precio (S/). |
| `duration_months` | tinyint unsigned | Sí |  |  | Duración en meses. |
| `for_two` | tinyint(1) |  | 0 |  | 2x1: con el mismo pago se atiende otra persona. |
| `allows_upfront` | tinyint(1) |  | 1 |  | Se puede pagar al contado. |
| `upfront_discount` | decimal(10,2) |  | 0.00 |  | Descuento por pagar al contado. |
| `allows_installments` | tinyint(1) |  | 0 |  | Se puede pagar en cuotas. |
| `down_payment` | decimal(10,2) |  | 0.00 |  | Pago inicial. |
| `installments` | tinyint unsigned | Sí |  |  | Número de cuotas. |
| `monthly_amount` | decimal(10,2) | Sí |  |  | Si el doctor fijó la cuota mensual; si no, el resto se reparte en partes iguales. |
| `is_active` | tinyint(1) |  | 1 |  | Activo. |
| `created_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que lo registró. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |
| `deleted_at` | timestamp | Sí |  |  | Borrado lógico (SoftDeletes): la fila queda y se oculta. |

### `treatment_package_items` {-}

*Tabla A38. Columnas de `treatment_package_items`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `treatment_package_id` | bigint unsigned |  |  | FK → `treatment_packages` (en cascada) | Paquete de tratamiento. |
| `treatment_id` | bigint unsigned |  |  | FK → `treatments` (restringe) | Tratamiento. |
| `description` | varchar(255) | Sí |  |  | Descripción. |
| `quantity` | smallint unsigned |  | 1 |  | Cantidad incluida en el paquete. |
| `sessions_planned` | smallint unsigned |  | 1 |  | Sesiones previstas. |
| `sort_order` | smallint unsigned |  | 0 |  | Orden en que se muestra. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `treatment_plans` {-}

Plan de tratamiento con costos (presupuesto informativo).

*Tabla A39. Columnas de `treatment_plans`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `patient_id` | bigint unsigned |  |  | FK → `patients` (restringe) | Paciente. |
| `doctor_id` | bigint unsigned |  |  | FK → `doctors` (restringe) | Doctor. |
| `consultation_id` | bigint unsigned | Sí |  | FK → `consultations` (pone NULL) | Atención. |
| `included_in_plan_id` | bigint unsigned | Sí |  | FK → `treatment_plans` (pone NULL) | El plan de la otra persona del 2x1 apunta al plan de quien paga. |
| `campaign_id` | bigint unsigned | Sí |  | FK → `campaigns` (pone NULL) | El plan que se armó con el paquete de una campaña: para sus cupos y su reporte. |
| `title` | varchar(255) | Sí |  |  | Nombre del plan. |
| `status` | varchar(20) |  | draft |  | Valores: `draft` (Presupuesto en borrador), `proposed` (Presupuesto presentado), `accepted` (Plan aceptado), `in_progress` (En tratamiento), `completed` (Terminado), `cancelled` (Cancelado). |
| `proposed_at` | timestamp | Sí |  |  | Desde cuándo el presupuesto espera respuesta del paciente. |
| `payment_mode` | varchar(20) | Sí |  |  | Cómo paga el paciente lo que aceptó: por sesión, todo junto o en cuotas. Valores: `per_session` (Por sesión), `upfront` (Pago único), `installments` (En cuotas). |
| `notes` | text | Sí |  |  | Notas. |
| `accepted_at` | timestamp | Sí |  |  | Cuándo se aceptó. |
| `completed_at` | timestamp | Sí |  |  | Cuándo se terminó. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |
| `deleted_at` | timestamp | Sí |  |  | Borrado lógico (SoftDeletes): la fila queda y se oculta. |

### `treatment_plan_items` {-}

*Tabla A40. Columnas de `treatment_plan_items`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `treatment_plan_id` | bigint unsigned |  |  | FK → `treatment_plans` (en cascada) | Plan de tratamiento. |
| `treatment_id` | bigint unsigned | Sí |  | FK → `treatments` (pone NULL) | Tratamiento. |
| `treatment_package_id` | bigint unsigned | Sí |  | FK → `treatment_packages` (pone NULL) | De qué paquete vino cada procedimiento del plan. |
| `description` | varchar(255) |  |  |  | Descripción. |
| `tooth` | varchar(10) | Sí |  |  | Pieza dental. |
| `surfaces` | varchar(10) | Sí |  |  | Caras de la pieza. |
| `quantity` | tinyint unsigned |  | 1 |  | Cantidad. |
| `unit_price` | decimal(10,2) |  | 0.00 |  | Precio unitario acordado (S/). |
| `discount` | decimal(10,2) |  | 0.00 |  | Descuento del ítem (S/). |
| `sessions_planned` | tinyint unsigned |  | 1 |  | Sesiones previstas. |
| `sessions_done` | tinyint unsigned |  | 0 |  | Sesiones hechas. |
| `session_interval_days` | smallint unsigned | Sí |  |  | Días entre sesiones. |
| `status` | varchar(20) |  | pending |  | Valores: `pending` (Por hacer), `in_progress` (En curso), `done` (Hecho), `cancelled` (Cancelado). |
| `accepted_at` | timestamp | Sí |  |  | Cuándo se aceptó. |
| `in_payment_schedule` | tinyint(1) |  | 0 |  | Está dentro del cronograma de pagos firmado. |
| `sort_order` | smallint unsigned |  | 0 |  | Orden en que se muestra. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `consultation_plan_items` {-}

Qué procedimientos del plan se trabajaron en cada atención: de aquí sale el avance del plan.

*Tabla A41. Columnas de `consultation_plan_items`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `consultation_id` | bigint unsigned |  |  | UK FK → `consultations` (en cascada) | Atención. |
| `treatment_plan_item_id` | bigint unsigned |  |  | UK FK → `treatment_plan_items` (en cascada) | Ítem del plan de tratamiento. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

Únicos compuestos: (consultation_id, treatment_plan_item_id).

### `treatment_plan_signatures` {-}

Cada firma del paciente: la aceptación del plan y la de lo que se agregó después.

*Tabla A42. Columnas de `treatment_plan_signatures`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `treatment_plan_id` | bigint unsigned |  |  | FK → `treatment_plans` (en cascada) | Plan de tratamiento. |
| `signer_name` | varchar(255) |  |  |  | Quién firma: nombre. |
| `signer_document` | varchar(20) | Sí |  |  | Quién firma: documento de identidad. |
| `signer_relationship` | varchar(20) |  |  |  | Valores: `self` (Titular), `child` (Hijo(a)), `parent` (Padre / madre), `spouse` (Esposo(a) / pareja), `sibling` (Hermano(a)), `grandparent` (Abuelo(a)), `grandchild` (Nieto(a)), `other` (Otro). |
| `signature_path` | varchar(255) |  |  |  | Ruta de la imagen de la firma. |
| `summary` | text |  |  |  | Lo que se firmó, tal como estaba: procedimientos, precios y forma de pago. |
| `total` | decimal(10,2) |  |  |  | Total firmado (S/). |
| `signed_at` | datetime |  |  |  | Cuándo se firmó. |
| `created_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que lo registró. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `treatment_plan_installments` {-}

Cronograma de pagos: la inicial (número 0) o el pago único, y las cuotas. Se cargan al vencer.

*Tabla A43. Columnas de `treatment_plan_installments`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `treatment_plan_id` | bigint unsigned |  |  | FK → `treatment_plans` (en cascada) | Plan de tratamiento. |
| `number` | smallint unsigned |  |  |  | Número de la cuota. |
| `due_on` | date |  |  |  | Vence el. |
| `amount` | decimal(10,2) |  |  |  | Monto de la cuota (S/). |
| `discount` | decimal(10,2) |  | 0.00 |  | Descuento de la cuota (S/). |
| `charge_id` | bigint unsigned | Sí |  | FK → `charges` (pone NULL) | Cargo con que se cobró. |
| `reminded_at` | timestamp | Sí |  |  | Cuándo se preparó el aviso de la cuota, para no avisar dos veces. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

## Caja {-}

### `charges` {-}

Lo que el paciente debe por lo realizado. Saldo = cargos − pagos.

*Tabla A44. Columnas de `charges`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `patient_id` | bigint unsigned |  |  | FK → `patients` (restringe) | Paciente. |
| `appointment_id` | bigint unsigned | Sí |  | FK → `appointments` (pone NULL) | Cita. |
| `consultation_id` | bigint unsigned | Sí |  | FK → `consultations` (pone NULL) | Lo cargado solo al terminar una atención: así no se carga dos veces si se reabre. |
| `treatment_plan_item_id` | bigint unsigned | Sí |  | FK → `treatment_plan_items` (pone NULL) | Ítem del plan de tratamiento. |
| `treatment_id` | bigint unsigned | Sí |  | FK → `treatments` (pone NULL) | Tratamiento. |
| `doctor_id` | bigint unsigned | Sí |  | FK → `doctors` (pone NULL) | Doctor. |
| `description` | varchar(255) |  |  |  | Descripción. |
| `amount` | decimal(10,2) |  |  |  | Monto (S/). |
| `discount` | decimal(10,2) |  | 0.00 |  | Descuento (S/). |
| `discount_reason` | varchar(255) | Sí |  |  | Motivo del descuento. |
| `suggested_amount` | decimal(10,2) | Sí |  |  | Lo que sugería el sistema cuando el dentista cobró otro precio. Con él, el plan sigue cuadrando: la diferencia de una sesión no se cobra (ni se descuenta) en las siguientes. |
| `campaign_id` | bigint unsigned | Sí |  | FK → `campaigns` (pone NULL) | Campaña. |
| `charged_at` | datetime |  |  |  | Fecha y hora del cargo. |
| `created_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que lo registró. |
| `voided_at` | timestamp | Sí |  |  | No se borran: se anulan con motivo. |
| `voided_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que anuló. |
| `void_reason` | varchar(255) | Sí |  |  | Motivo de la anulación. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `payments` {-}

Pagos hechos en el consultorio (no hay pagos online).

*Tabla A45. Columnas de `payments`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `patient_id` | bigint unsigned |  |  | FK → `patients` (restringe) | Paciente. |
| `appointment_id` | bigint unsigned | Sí |  | FK → `appointments` (pone NULL) | Cita. |
| `doctor_id` | bigint unsigned | Sí |  | FK → `doctors` (pone NULL) | Doctor. |
| `treatment_id` | bigint unsigned | Sí |  | FK → `treatments` (pone NULL) | Tratamiento. |
| `amount` | decimal(10,2) |  |  |  | Monto (S/). |
| `method` | varchar(20) |  |  |  | Valores: `cash` (Efectivo), `card` (Tarjeta), `yape` (Yape), `plin` (Plin), `transfer` (Transferencia), `other` (Otro). |
| `reference` | varchar(50) | Sí |  |  | n.º de operación Yape/Plin/tarjeta |
| `paid_at` | datetime |  |  |  | Fecha y hora del pago. |
| `received_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que recibió. |
| `notes` | varchar(255) | Sí |  |  | Notas. |
| `voided_at` | timestamp | Sí |  |  | Cuándo se anuló (no se borra). |
| `voided_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que anuló. |
| `void_reason` | varchar(255) | Sí |  |  | Motivo de la anulación. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `daily_closings` {-}

El cierre de caja de cada día: el arqueo del efectivo y quién lo cerró. Cerrado, el día ya no se cambia sin la administradora.

*Tabla A46. Columnas de `daily_closings`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `date` | date |  |  | UK | Día del cierre. |
| `opening_cash` | decimal(10,2) |  | 0.00 |  | Efectivo con que abrió la caja (S/). |
| `expected_cash` | decimal(10,2) |  |  |  | Efectivo que debía haber al cerrar (S/). |
| `counted_cash` | decimal(10,2) |  |  |  | Efectivo contado al cerrar (S/). |
| `collected` | decimal(10,2) |  |  |  | Cobrado en el día (S/). |
| `pending` | decimal(10,2) |  | 0.00 |  | Lo atendido en el día que quedó por cobrar (S/). |
| `notes` | varchar(255) | Sí |  |  | Notas. |
| `closed_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que cerró la caja. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `expenses` {-}

Gastos del consultorio (alquiler, sueldos, laboratorio…): lo que sale de caja, para saber cuánto queda.

*Tabla A47. Columnas de `expenses`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `spent_at` | datetime |  |  |  | Fecha del gasto. |
| `category` | varchar(30) |  |  |  | Valores: `laboratory` (Laboratorio dental), `salaries` (Sueldos y honorarios), `rent` (Alquiler), `utilities` (Luz, agua, internet y teléfono), `maintenance` (Mantenimiento y limpieza), `marketing` (Publicidad), `taxes` (Impuestos y trámites), `other` (Otros gastos). |
| `description` | varchar(200) |  |  |  | Descripción. |
| `amount` | decimal(10,2) |  |  |  | Monto (S/). |
| `method` | varchar(20) |  |  |  | Valores: `cash` (Efectivo), `card` (Tarjeta), `yape` (Yape), `plin` (Plin), `transfer` (Transferencia), `other` (Otro). |
| `paid_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que pagó. |
| `voided_at` | timestamp | Sí |  |  | Cuándo se anuló (no se borra). |
| `voided_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que anuló. |
| `void_reason` | varchar(250) | Sí |  |  | Motivo de la anulación. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

## Inventario {-}

### `suppliers` {-}

A quién se le compra: el pedido sale por su WhatsApp.

*Tabla A48. Columnas de `suppliers`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `name` | varchar(120) |  |  |  | Nombre. |
| `contact_name` | varchar(120) | Sí |  |  | Persona de contacto. |
| `phone` | varchar(20) | Sí |  |  | Teléfono del proveedor. |
| `email` | varchar(255) | Sí |  |  | Correo electrónico. |
| `notes` | text | Sí |  |  | Notas. |
| `is_active` | tinyint(1) |  | 1 |  | Activo. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `supplies` {-}

Cada material, con lo que hay (la suma de sus movimientos) y desde cuánto hay que pedir.

*Tabla A49. Columnas de `supplies`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `name` | varchar(120) |  |  |  | Nombre. |
| `category` | varchar(30) |  |  |  | Valores: `anesthesia` (Anestésicos), `restorative` (Resinas y restauración), `endodontics` (Endodoncia), `prevention` (Prevención y limpieza), `surgery` (Cirugía), `implants` (Implantes), `orthodontics` (Ortodoncia), `biosafety` (Bioseguridad), `laboratory` (Laboratorio), `other` (Otros). |
| `unit` | varchar(30) |  | unidad |  | Unidad de medida. |
| `stock` | decimal(10,2) |  | 0.00 |  | Existencias (se actualiza con cada movimiento). |
| `min_stock` | decimal(10,2) |  | 0.00 |  | Existencias mínimas para avisar. |
| `unit_cost` | decimal(10,2) | Sí |  |  | Costo unitario (S/). |
| `supplier_id` | bigint unsigned | Sí |  | FK → `suppliers` (pone NULL) | Proveedor. |
| `tracks_serial` | tinyint(1) |  | 0 |  | Implantes: cada unidad tiene su número de serie y queda registrada en el paciente. |
| `notes` | text | Sí |  |  | Notas. |
| `is_active` | tinyint(1) |  | 1 |  | Activo. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `supply_batches` {-}

Cada lote que entró, con su vencimiento y lo que queda. Sale primero lo que vence antes.

*Tabla A50. Columnas de `supply_batches`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `supply_id` | bigint unsigned |  |  | FK → `supplies` (en cascada) | Material. |
| `lot` | varchar(60) | Sí |  |  | Número de lote. |
| `serial` | varchar(80) | Sí |  |  | Número de serie. |
| `expires_on` | date | Sí |  |  | Vence el. |
| `quantity` | decimal(10,2) |  |  |  | Cantidad recibida en el lote. |
| `remaining` | decimal(10,2) |  |  |  | Lo que queda del lote. |
| `unit_cost` | decimal(10,2) | Sí |  |  | Costo unitario (S/). |
| `received_at` | timestamp |  |  |  | Cuándo se recibió. |
| `purchase_order_id` | bigint unsigned | Sí |  | FK → `purchase_orders` (pone NULL) | Orden de compra. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `purchase_orders` {-}

*Tabla A51. Columnas de `purchase_orders`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `supplier_id` | bigint unsigned |  |  | FK → `suppliers` (restringe) | Proveedor. |
| `status` | varchar(20) |  | sent |  | Valores: `sent` (Pedido), `partial` (Llegó una parte), `received` (Recibido), `cancelled` (Anulado). |
| `notes` | text | Sí |  |  | Notas. |
| `sent_at` | timestamp | Sí |  |  | Cuándo se envió. |
| `received_at` | timestamp | Sí |  |  | Cuándo se recibió. |
| `created_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que lo registró. |
| `received_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que recibió. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `purchase_order_items` {-}

*Tabla A52. Columnas de `purchase_order_items`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `purchase_order_id` | bigint unsigned |  |  | FK → `purchase_orders` (en cascada) | Orden de compra. |
| `supply_id` | bigint unsigned |  |  | FK → `supplies` (restringe) | Material. |
| `quantity` | decimal(10,2) |  |  |  | Cantidad pedida. |
| `received_quantity` | decimal(10,2) | Sí |  |  | Cantidad recibida. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `treatment_supplies` {-}

La receta: qué y cuánto usa cada tratamiento en una atención.

*Tabla A53. Columnas de `treatment_supplies`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `treatment_id` | bigint unsigned |  |  | UK FK → `treatments` (en cascada) | Tratamiento. |
| `supply_id` | bigint unsigned |  |  | UK FK → `supplies` (en cascada) | Material. |
| `quantity` | decimal(8,2) |  |  |  | Cantidad que usa el tratamiento. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

Únicos compuestos: (treatment_id, supply_id).

### `stock_movements` {-}

Cada entrada, uso, devolución, ajuste o baja, con quién lo hizo y por qué. Nada se borra.

*Tabla A54. Columnas de `stock_movements`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `supply_id` | bigint unsigned |  |  | FK → `supplies` (restringe) | Material. |
| `supply_batch_id` | bigint unsigned | Sí |  | FK → `supply_batches` (pone NULL) | Lote. |
| `type` | varchar(20) |  |  |  | Tipo. Valores: `receipt` (Entrada), `use` (Uso en atención), `return` (Devuelto), `adjustment` (Ajuste de conteo), `write_off` (Baja). |
| `quantity` | decimal(10,2) |  |  |  | Cantidad que entra o sale. |
| `unit_cost` | decimal(10,2) | Sí |  |  | Costo unitario (S/). |
| `reason` | varchar(255) | Sí |  |  | Motivo del movimiento. |
| `patient_id` | bigint unsigned | Sí |  | FK → `patients` (pone NULL) | Paciente. |
| `consultation_id` | bigint unsigned | Sí |  | FK → `consultations` (pone NULL) | Atención. |
| `appointment_id` | bigint unsigned | Sí |  | FK → `appointments` (pone NULL) | Cita. |
| `purchase_order_id` | bigint unsigned | Sí |  | FK → `purchase_orders` (pone NULL) | Orden de compra. |
| `user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario del panel. |
| `reverted_at` | timestamp | Sí |  |  | Un uso que se devolvió al reabrir la atención. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

## Web pública {-}

### `faqs` {-}

Preguntas frecuentes: generales o de un tratamiento (salen en su página).

*Tabla A55. Columnas de `faqs`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `question` | varchar(255) |  |  |  | Pregunta. |
| `answer` | text |  |  |  | Respuesta. |
| `treatment_id` | bigint unsigned | Sí |  | FK → `treatments` (pone NULL) | Tratamiento. |
| `is_published` | tinyint(1) |  | 1 |  | Publicado en la web. |
| `sort_order` | smallint unsigned |  | 0 |  | Orden en que se muestra. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |
| `deleted_at` | timestamp | Sí |  |  | Borrado lógico (SoftDeletes): la fila queda y se oculta. |

### `reviews` {-}

Opiniones reales de pacientes (copiadas de Google u otra fuente), nunca inventadas.

*Tabla A56. Columnas de `reviews`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `author_name` | varchar(80) |  |  |  | Nombre de quien opina. |
| `rating` | tinyint unsigned |  |  |  | Calificación. |
| `body` | text |  |  |  | Texto. |
| `source` | varchar(20) |  | google |  | Valores: `google` (Google), `facebook` (Facebook), `clinic` (Encuesta del consultorio). |
| `reviewed_on` | date | Sí |  |  | Fecha de la opinión. |
| `url` | varchar(255) | Sí |  |  | Dirección web. |
| `treatment_id` | bigint unsigned | Sí |  | FK → `treatments` (pone NULL) | Tratamiento. |
| `is_published` | tinyint(1) |  | 1 |  | Publicado en la web. |
| `sort_order` | smallint unsigned |  | 0 |  | Orden en que se muestra. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |
| `deleted_at` | timestamp | Sí |  |  | Borrado lógico (SoftDeletes): la fila queda y se oculta. |

### `smile_cases` {-}

Antes y después: solo con la autorización del paciente para publicar sus fotos.

*Tabla A57. Columnas de `smile_cases`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `title` | varchar(255) |  |  |  | Título del caso. |
| `description` | text | Sí |  |  | Descripción. |
| `treatment_id` | bigint unsigned | Sí |  | FK → `treatments` (pone NULL) | Tratamiento. |
| `doctor_id` | bigint unsigned | Sí |  | FK → `doctors` (pone NULL) | Doctor. |
| `patient_id` | bigint unsigned | Sí |  | FK → `patients` (pone NULL) | Paciente. |
| `before_path` | varchar(255) |  |  |  | Ruta de la foto de antes. |
| `after_path` | varchar(255) |  |  |  | Ruta de la foto de después. |
| `consent_confirmed_at` | timestamp |  |  |  | Cuándo se confirmó el consentimiento escrito del paciente. |
| `consent_confirmed_by_user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario que confirmó el consentimiento. |
| `is_published` | tinyint(1) |  | 1 |  | Publicado en la web. |
| `sort_order` | smallint unsigned |  | 0 |  | Orden en que se muestra. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |
| `deleted_at` | timestamp | Sí |  |  | Borrado lógico (SoftDeletes): la fila queda y se oculta. |

## Acceso, auditoría y sistema {-}

### `users` {-}

*Tabla A58. Columnas de `users`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `name` | varchar(255) |  |  |  | Nombre. |
| `email` | varchar(255) |  |  | UK | Correo electrónico. |
| `email_verified_at` | timestamp | Sí |  |  | Cuándo se verificó el correo. |
| `password` | varchar(255) |  |  |  | Hash bcrypt de la contraseña. |
| `must_change_password` | tinyint(1) |  | 0 |  | Debe cambiar la contraseña al entrar. |
| `role` | varchar(20) |  | reception |  | admin \| reception \| doctor Valores: `admin` (Administrador), `reception` (Recepción), `doctor` (Doctor). |
| `is_dentist` | tinyint(1) |  | 0 |  | Un administrador solo ve lo clínico si además es odontólogo. |
| `phone` | varchar(20) | Sí |  |  | Celular del usuario. |
| `is_active` | tinyint(1) |  | 1 |  | Activo. |
| `last_login_at` | timestamp | Sí |  |  | Último ingreso al panel. |
| `remember_token` | varchar(100) | Sí |  |  | Sesión recordada del panel (Laravel). |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `password_reset_tokens` {-}

*Tabla A59. Columnas de `password_reset_tokens`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `email` | varchar(255) |  |  | PK | Correo del usuario. |
| `token` | varchar(255) |  |  |  | Token del enlace para cambiar la clave. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |

### `sessions` {-}

*Tabla A60. Columnas de `sessions`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | varchar(255) |  |  | PK | Identificador de la sesión. |
| `user_id` | bigint unsigned | Sí |  |  | Usuario del panel (vacío si no entró). |
| `ip_address` | varchar(45) | Sí |  |  | Dirección IP. |
| `user_agent` | text | Sí |  |  | Navegador o aplicación (user agent). |
| `payload` | longtext |  |  |  | Contenido serializado. |
| `last_activity` | int |  |  |  | Última actividad (marca de tiempo Unix). |

### `settings` {-}

Configuración editable desde el panel (plazos, topes, datos del consultorio).

*Tabla A61. Columnas de `settings`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `key` | varchar(255) |  |  | UK | Nombre de la opción. |
| `value` | json | Sí |  |  | Valor de la opción (JSON). |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `audit_logs` {-}

"Nada se borra": cada cambio queda registrado con fecha y usuario.

*Tabla A62. Columnas de `audit_logs`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `user_id` | bigint unsigned | Sí |  | FK → `users` (pone NULL) | Usuario del panel. |
| `event` | varchar(20) |  |  |  | Qué pasó (creado, actualizado, borrado…). |
| `auditable_type` | varchar(255) |  |  |  | Clase del modelo cambiado. |
| `auditable_id` | bigint unsigned |  |  |  | Fila cambiada. |
| `old_values` | json | Sí |  |  | Valores anteriores (JSON). |
| `new_values` | json | Sí |  |  | Valores nuevos (JSON). |
| `ip_address` | varchar(45) | Sí |  |  | Dirección IP. |
| `user_agent` | varchar(255) | Sí |  |  | Navegador o aplicación (user agent). |
| `url` | varchar(255) | Sí |  |  | Dirección desde donde se hizo el cambio. |
| `created_at` | timestamp |  | CURRENT_TIMESTAMP |  | Cuándo se creó (Laravel). |

### `payment_audit_logs` {-}

Lo que los triggers de MySQL anotan cuando un pago se cambia o se borra por fuera de la aplicación (desde phpMyAdmin, la consola o un script). La aplicación nunca hace eso: solo anula, y eso ya queda en audit_logs. Sin llave foránea al pago: el registro tiene que quedar aunque el pago se haya borrado.

*Tabla A63. Columnas de `payment_audit_logs`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `payment_id` | bigint unsigned |  |  |  | Pago afectado (sin llave foránea: el registro queda aunque se borre el pago). |
| `event` | varchar(10) |  |  |  | updated (cambio) o deleted (borrado). |
| `old_values` | json | Sí |  |  | Valores anteriores de las columnas que cambiaron, o la fila completa si se borró (JSON). |
| `new_values` | json | Sí |  |  | Valores nuevos de las columnas que cambiaron (JSON); vacío si se borró. |
| `database_user` | varchar(300) |  |  |  | Quién lo hizo, como lo ve MySQL: "usuario@equipo" de la conexión (USER(), no el dueño del trigger). |
| `changed_at` | datetime |  |  |  | Cuándo (hora de Lima). |

### `notifications` {-}

*Tabla A64. Columnas de `notifications`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | char(36) |  |  | PK | Identificador (UUID). |
| `type` | varchar(255) |  |  |  | Clase de la notificación. |
| `notifiable_type` | varchar(255) |  |  |  | Clase de quien recibe la notificación. |
| `notifiable_id` | bigint unsigned |  |  |  | Quién recibe la notificación. |
| `data` | text |  |  |  | Datos (JSON). |
| `read_at` | timestamp | Sí |  |  | Cuándo se leyó. |
| `created_at` | timestamp | Sí |  |  | Cuándo se creó (Laravel). |
| `updated_at` | timestamp | Sí |  |  | Cuándo se modificó por última vez (Laravel). |

### `cache` {-}

*Tabla A65. Columnas de `cache`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `key` | varchar(255) |  |  | PK | Clave de la caché. |
| `value` | mediumtext |  |  |  | Valor guardado. |
| `expiration` | bigint |  |  |  | Vence (marca de tiempo Unix). |

### `cache_locks` {-}

*Tabla A66. Columnas de `cache_locks`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `key` | varchar(255) |  |  | PK | Nombre del bloqueo. |
| `owner` | varchar(255) |  |  |  | Proceso dueño del bloqueo. |
| `expiration` | bigint |  |  |  | Vence (marca de tiempo Unix). |

### `jobs` {-}

*Tabla A67. Columnas de `jobs`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `queue` | varchar(255) |  |  |  | Cola. |
| `payload` | longtext |  |  |  | Contenido serializado. |
| `attempts` | smallint unsigned |  |  |  | Intentos. |
| `reserved_at` | int unsigned | Sí |  |  | Cuándo lo tomó un proceso. |
| `available_at` | int unsigned |  |  |  | Desde cuándo se puede procesar. |
| `created_at` | int unsigned |  |  |  | Cuándo se creó (Laravel). |

### `job_batches` {-}

*Tabla A68. Columnas de `job_batches`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | varchar(255) |  |  | PK | Identificador del lote. |
| `name` | varchar(255) |  |  |  | Nombre del lote. |
| `total_jobs` | int |  |  |  | Trabajos del lote. |
| `pending_jobs` | int |  |  |  | Trabajos pendientes. |
| `failed_jobs` | int |  |  |  | Trabajos fallidos. |
| `failed_job_ids` | longtext |  |  |  | Identificadores de los trabajos fallidos. |
| `options` | mediumtext | Sí |  |  | Opciones del lote. |
| `cancelled_at` | int | Sí |  |  | Cuándo se canceló el lote. |
| `created_at` | int |  |  |  | Cuándo se creó (Laravel). |
| `finished_at` | int | Sí |  |  | Cuándo terminó el lote. |

### `failed_jobs` {-}

*Tabla A69. Columnas de `failed_jobs`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | bigint unsigned |  |  | PK | Identificador. |
| `uuid` | varchar(255) |  |  | UK | Identificador único universal. |
| `connection` | varchar(255) |  |  |  | Conexión de la cola. |
| `queue` | varchar(255) |  |  |  | Cola. |
| `payload` | longtext |  |  |  | Contenido serializado. |
| `exception` | longtext |  |  |  | Error que hizo fallar el trabajo. |
| `failed_at` | timestamp |  | CURRENT_TIMESTAMP |  | Cuándo falló. |

### `migrations` {-}

*Tabla A70. Columnas de `migrations`.*

| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |
| --- | --- | :-: | --- | --- | --- |
| `id` | int unsigned |  |  | PK | Identificador. |
| `migration` | varchar(255) |  |  |  | Nombre de la migración. |
| `batch` | int |  |  |  | Lote en que se corrió. |
