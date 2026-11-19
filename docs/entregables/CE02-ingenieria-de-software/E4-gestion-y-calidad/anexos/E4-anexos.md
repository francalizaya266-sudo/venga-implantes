# Anexo A. Reportes de pruebas

## Cobertura del backend por carpeta {-}

Este reporte sale de la misma ejecución que la sección 1.4 del documento: la suite completa con pcov el 19/11/2026, sobre la rama del E4. La CI escribe esta tabla en el resumen del job "Backend" en cada ejecución y guarda el reporte HTML 30 días como artefacto `cobertura-backend` (`.github/workflows/tests.yml`, paso "Cobertura mínima"). Fuente: `E4-gestion-y-calidad/pruebas/cobertura-backend.md`.

*Tabla A1. Cobertura de líneas por carpeta de `backend/app`.*

| Carpeta | Líneas | Cubiertas | % |
| --- | ---: | ---: | ---: |
| `app/Filament/Resources` | 8 533 | 8 151 | 95,5 |
| `app/Filament/Pages` | 1 686 | 1 671 | 99,1 |
| `app/Filament/Actions` | 1 477 | 1 448 | 98,0 |
| `app/Services/Clinical` | 1 371 | 1 319 | 96,2 |
| `app/Models` | 1 196 | 1 143 | 95,6 |
| `app/Filament/Widgets` | 1 125 | 1 112 | 98,8 |
| `app/Filament/Support` | 1 115 | 1 097 | 98,4 |
| `app/Http/Controllers` | 1 053 | 1 045 | 99,2 |
| `app/Services/Booking` | 836 | 809 | 96,8 |
| `app/Services/Billing` | 578 | 567 | 98,1 |
| `app/Enums` | 541 | 479 | 88,5 |
| `app/Support` | 533 | 509 | 95,5 |
| `app/Services/Reports` | 477 | 476 | 99,8 |
| `app/Services/Inventory` | 425 | 414 | 97,4 |
| `app/Services/Agenda` | 400 | 392 | 98,0 |
| `app/Services/Web` | 392 | 381 | 97,2 |
| `app/Filament/Forms` | 309 | 305 | 98,7 |
| `app/Livewire` | 281 | 260 | 92,5 |
| `app/Services/Backup` | 253 | 246 | 97,2 |
| `app/Policies` | 240 | 150 | 62,5 |
| `app/Services/Patients` | 237 | 223 | 94,1 |
| `app/Services/Dashboard` | 227 | 224 | 98,7 |
| `app/Http/Resources` | 189 | 189 | 100,0 |
| `app/Services/WhatsApp` | 158 | 139 | 88,0 |
| `app/Console/Commands` | 124 | 107 | 86,3 |
| `app/Providers/Filament` | 116 | 116 | 100,0 |
| `app/Services/Access` | 104 | 97 | 93,3 |
| `app/Http/Requests` | 85 | 85 | 100,0 |
| `app/Services/Database` | 61 | 26 | 42,6 |
| `app/Exceptions` | 51 | 46 | 90,2 |
| `app/Models/Concerns` | 38 | 38 | 100,0 |
| `app/Services` | 26 | 25 | 96,2 |
| `app/Http/Middleware` | 23 | 23 | 100,0 |
| `app/Providers` | 23 | 23 | 100,0 |
| `app/Filament/Concerns` | 19 | 19 | 100,0 |

Resumen de PHPUnit (`pruebas/cobertura-backend-resumen.txt`):

<!-- archivo: ../pruebas/cobertura-backend-resumen.txt -->

## Cobertura de la web por carpeta {-}

Se mide con `@vitest/coverage-v8` desde el 19/11/2026 (`npm run test:coverage` en `frontend/`). La CI corre lo mismo en el job "Frontend": falla si las líneas, las sentencias o las funciones bajan de 85 %, o las ramas de 70 % (`frontend/vite.config.ts`), y guarda el reporte HTML 30 días como artefacto `cobertura-web`.

*Tabla A2. Cobertura de la web por carpeta de `frontend/src` (19/11/2026).*

| Carpeta | Líneas | Cubiertas | % | Ramas | Cubiertas | % |
| --- | --: | --: | --: | --: | --: | --: |
| `src/pages` | 451 | 402 | 89,1 | 815 | 646 | 79,3 |
| `src/components` | 160 | 139 | 86,9 | 332 | 221 | 66,6 |
| `src/api` | 120 | 120 | 100,0 | 97 | 91 | 93,8 |
| `src/lib` | 101 | 100 | 99,0 | 44 | 41 | 93,2 |
| `src/app` | 9 | 8 | 88,9 | 7 | 7 | 100,0 |
| `src/main.tsx` | 9 | 0 | 0,0 | 4 | 0 | 0,0 |
| **Total** | **850** | **769** | **90,5** | **1 299** | **1 006** | **77,4** |

`main.tsx` solo monta la aplicación en el navegador y no tiene pruebas propias. Las ramas de `src/components` son las menos cubiertas y quedan en el registro de deuda (tabla D4). Resumen de Vitest (`pruebas/cobertura-web-resumen.txt`):

<!-- archivo: ../pruebas/cobertura-web-resumen.txt -->

## Casos de prueba de los flujos críticos {-}

Las tablas A2 a A8 tienen todos los casos de los archivos principales de cada flujo crítico (tabla 2 del documento). Cada nombre describe la regla que comprueba. Están en inglés porque así se escribe el código del repositorio. Los demás archivos y casos se listan con `php artisan test --list-tests` en `backend/`.

*Tabla A3. Casos del flujo "Reservar en la web".*

| Archivo | Caso |
| --- | --- |
| `BookingControllerTest` | `test_the_first_step_offers_only_what_is_booked_online_with_its_price_and_duration` |
|  | `test_a_new_patient_books_with_spanish_errors` |
|  | `test_a_first_booking_with_a_new_number_enters_my_account_without_waiting_for_the_link` |
|  | `test_a_number_the_clinic_already_has_is_not_remembered_without_its_link` |
|  | `test_the_birth_date_comes_in_three_fields_and_an_incomplete_one_is_rejected` |
|  | `test_the_first_available_doctor_takes_the_time_and_a_chosen_doctor_only_offers_theirs` |
|  | `test_a_day_tapped_on_the_website_arrives_chosen_unless_it_has_no_free_times` |
|  | `test_arriving_from_a_campaign_shows_its_price_books_with_it_and_the_whatsapp_says_it` |
|  | `test_a_promotion_for_a_treatment_that_is_not_booked_online_starts_with_the_evaluation` |
|  | `test_a_promo_code_that_does_not_exist_stops_the_booking_and_a_valid_one_is_applied` |
|  | `test_a_time_taken_meanwhile_sends_the_patient_back_to_choose_another` |
|  | `test_a_treatment_that_is_not_booked_online_cannot_be_booked` |
|  | `test_the_free_times_are_asked_for_a_day_written_as_a_date` |
|  | `test_a_remembered_phone_books_for_the_family_without_typing_the_number` |
|  | `test_someone_in_treatment_is_told_and_cannot_book_but_the_family_can` |
|  | `test_someone_who_books_with_a_familys_number_from_another_phone_is_not_offered_until_the_family_books_for_them` |
|  | `test_relatives_with_the_same_first_name_are_told_apart_by_their_surname` |
|  | `test_a_family_that_already_accepted_the_privacy_policy_and_the_promotions_is_not_asked_again` |
|  | `test_after_booking_the_appointment_can_be_saved_to_the_calendar` |
|  | `test_how_they_found_us_is_asked_once_after_booking` |
|  | `test_many_bookings_in_a_row_from_the_same_connection_are_stopped` |
| `AppointmentServiceTest` | `test_web_booking_is_reserved_instantly_and_creates_the_account_and_patient` |
|  | `test_web_booking_prepares_the_confirmation_message_and_alerts_reception` |
|  | `test_rejects_a_web_booking_on_a_taken_slot` |
|  | `test_rejects_web_booking_of_a_treatment_that_needs_evaluation` |
|  | `test_rejects_web_booking_inside_the_minimum_lead_time` |
|  | `test_rejects_web_booking_from_a_blocked_number` |
|  | `test_reuses_the_patient_when_the_name_comes_without_accents_or_with_extra_spaces` |
|  | `test_limits_each_patient_to_two_future_web_appointments` |
|  | `test_lets_a_family_book_for_several_members_up_to_the_phone_limit` |
|  | `test_reuses_the_family_member_with_the_same_name_instead_of_duplicating` |
|  | `test_forbids_choosing_a_registered_family_member_from_an_unrecognized_device` |
|  | `test_books_for_a_registered_family_member_from_a_remembered_device` |
|  | `test_a_phone_remembered_by_its_first_booking_books_for_its_family_without_counting_as_verified` |
|  | `test_patients_in_treatment_cannot_book_sessions_on_the_web` |
|  | `test_doctor_schedules_the_next_session_confirmed_and_reception_is_alerted` |
|  | `test_forbids_a_doctor_from_booking_on_another_doctors_agenda` |
|  | `test_staff_can_book_outside_working_hours_but_never_on_top_of_another_appointment` |
|  | `test_a_clash_says_which_appointment_or_which_block_is_in_the_way` |
|  | `test_patient_link_confirmation_marks_the_appointment_confirmed` |
|  | `test_patient_cancels_before_the_deadline_and_frees_the_slot` |
|  | `test_patient_cannot_cancel_after_the_deadline` |
|  | `test_patient_moves_their_own_appointment_more_than_a_day_ahead` |
|  | `test_patient_cannot_move_a_fixed_date_session_directly` |
|  | `test_reception_move_asks_the_patient_to_confirm_the_new_time` |
| `OverbookingTest` | `test_booking_over_another_appointment_only_passes_when_it_is_asked_as_an_overbooking` |
|  | `test_a_free_time_asked_as_an_overbooking_is_a_normal_appointment` |
|  | `test_an_overbooking_never_goes_over_a_block_or_a_time_held_for_the_waitlist` |
|  | `test_the_time_of_an_overbooking_is_still_taken_for_the_agenda_and_the_web` |
|  | `test_moving_an_overbooking_takes_it_to_a_free_time_and_clears_the_mark` |
|  | `test_a_cancelled_overbooking_comes_back_with_undo_unless_the_time_was_blocked_meanwhile` |

*Tabla A4. Casos del flujo "Confirmar o cambiar desde el enlace".*

| Archivo | Caso |
| --- | --- |
| `LinkControllerTest` | `test_opening_the_link_shows_the_appointment_without_confirming_it` |
|  | `test_confirming_from_the_link_verifies_the_phone_and_remembers_the_device` |
|  | `test_a_phone_remembered_by_its_first_booking_is_verified_when_it_uses_a_link_of_its_number` |
|  | `test_the_owner_of_the_number_using_its_link_on_another_phone_forgets_the_phones_that_never_used_one` |
|  | `test_the_link_of_an_appointment_the_family_has_not_recognized_names_who_it_is_for_and_asks` |
|  | `test_cancelling_from_the_link_cancels_the_appointment` |
|  | `test_a_reason_that_is_not_text_is_rejected_before_the_link_is_used` |
|  | `test_too_close_to_the_appointment_it_cannot_be_cancelled_from_the_link` |
|  | `test_an_expired_link_says_so_and_no_longer_acts` |
|  | `test_actions_on_an_unknown_link_return_404` |
|  | `test_the_link_of_an_appointment_does_not_open_my_account` |
|  | `test_more_than_a_day_ahead_the_patient_moves_the_appointment_at_once` |
|  | `test_close_to_the_appointment_the_patient_proposes_up_to_three_times` |
|  | `test_a_fixed_date_session_always_needs_the_clinic` |
|  | `test_a_time_that_is_not_free_is_not_accepted` |
|  | `test_a_link_only_opens_its_own_appointment` |
| `RescheduleRequestServiceTest` | `test_changes_less_than_a_day_ahead_need_approval` |
|  | `test_changing_doctor_needs_approval_even_with_time_ahead` |
|  | `test_patient_proposes_up_to_three_times_and_the_original_appointment_stays` |
|  | `test_rejects_more_than_three_proposed_times` |
|  | `test_approving_an_option_moves_the_appointment_and_notifies_the_patient` |
|  | `test_rejecting_keeps_the_original_time_and_tells_the_patient` |
|  | `test_a_new_request_replaces_the_previous_pending_one` |
|  | `test_moving_the_appointment_another_way_closes_the_pending_request_with_the_reason` |
|  | `test_cancelling_the_appointment_closes_the_pending_request` |
|  | `test_a_request_is_closed_once_the_patient_shows_up_for_the_original_time` |
|  | `test_requests_of_appointments_that_already_passed_are_closed_on_the_next_catch_up` |
|  | `test_offering_another_time_moves_the_appointment_and_asks_the_patient_to_confirm` |
|  | `test_another_time_already_agreed_with_the_patient_stays_confirmed_without_a_notice` |
|  | `test_another_time_cannot_be_offered_on_a_request_already_resolved` |

*Tabla A5. Casos del flujo "Atender".*

| Archivo | Caso |
| --- | --- |
| `ClinicalServicesTest` | `test_attending_an_appointment_opens_the_consultation_with_the_treatment_template_and_a_first_odontogram` |
|  | `test_the_template_leaves_out_a_medication_that_does_not_suit_the_patient` |
|  | `test_the_next_consultation_starts_from_the_previous_odontogram` |
|  | `test_only_the_doctor_of_the_appointment_can_attend_it` |
|  | `test_users_without_a_doctor_profile_cannot_open_consultations` |
|  | `test_closing_completes_the_appointment_and_reopening_is_allowed_for_24_hours` |
|  | `test_applying_a_template_fills_empty_fields_and_appends_to_written_ones` |
|  | `test_tapping_a_surface_marks_it_and_tapping_again_removes_it` |
|  | `test_marking_a_finding_treated_leaves_what_the_treatment_leaves` |
|  | `test_the_odontogram_validates_teeth_surfaces_spans_and_colors` |

*Tabla A6. Casos del flujo "Cobrar y cerrar caja".*

| Archivo | Caso |
| --- | --- |
| `BillingServiceTest` | `test_each_session_of_the_plan_charges_its_part_and_the_last_one_what_is_left` |
|  | `test_correcting_a_consultation_does_not_charge_twice_and_voids_what_was_removed` |
|  | `test_an_appointment_outside_the_plan_charges_its_treatment_with_the_promotion` |
|  | `test_reception_completing_an_appointment_charges_it_once` |
|  | `test_at_checkout_reception_corrects_adds_and_removes_and_the_payment_settles_the_balance` |
|  | `test_patients_can_be_listed_with_their_balance` |
| `TreatmentPlanPaymentsTest` | `test_once_signed_the_agreed_prices_no_longer_change_and_additions_are_signed_apart` |
|  | `test_installments_are_whole_soles_once_a_month_and_the_last_one_adjusts` |
|  | `test_in_installments_each_payment_is_charged_when_due_and_the_sessions_are_not_charged_apart` |
|  | `test_upfront_charges_everything_today_with_the_discount_for_paying_at_once` |
|  | `test_what_is_added_later_to_a_plan_in_installments_is_charged_per_session` |
|  | `test_a_plan_paid_per_session_shows_what_was_charged_and_what_is_left` |
|  | `test_cancelling_a_plan_in_installments_stops_its_cuotas_and_can_charge_what_was_done` |
|  | `test_the_appointment_says_what_will_be_charged_that_day` |
|  | `test_a_plan_session_booked_as_a_loose_appointment_is_worked_and_charged_from_the_plan` |
| `CashClosingPanelTest` | `test_the_day_adds_up_by_method_and_by_person_without_what_was_voided` |
|  | `test_one_persons_closing_only_counts_what_they_received` |
|  | `test_a_future_or_invalid_day_closes_today` |
|  | `test_reception_sees_the_closing_and_prints_it` |
|  | `test_doctors_do_not_see_the_cash` |
|  | `test_collected_today_leads_to_the_closing_when_nobody_left_owing` |

*Tabla A7. Casos del flujo "Permisos".*

| Archivo | Caso |
| --- | --- |
| `PanelAccessTest` | `test_each_role_only_opens_what_it_needs` |
|  | `test_validation_messages_are_in_spanish` |
|  | `test_inactive_users_cannot_enter` |
|  | `test_the_panel_always_says_who_is_signed_in_and_with_which_role` |
|  | `test_each_person_changes_their_own_password_from_my_profile` |
|  | `test_my_profile_says_plainly_when_the_two_new_passwords_do_not_match` |
|  | `test_errors_explain_what_happened_in_spanish_and_offer_a_way_out` |
|  | `test_pages_tell_the_browser_how_to_protect_them` |
|  | `test_a_doctor_profile_page_lists_its_treatments` |

*Tabla A8. Casos del flujo "Copias y restauración".*

| Archivo | Caso |
| --- | --- |
| `DatabaseBackupTest` | `test_a_backup_brings_back_everything_as_it_was` |
|  | `test_a_mysql_copy_says_its_encoding_so_another_program_does_not_break_the_accents` |
|  | `test_only_the_last_copies_are_kept` |
|  | `test_the_commands_create_and_restore_the_latest_copy` |
| `BackupsPanelTest` | `test_the_dentist_admin_creates_lists_and_downloads_copies` |
|  | `test_copies_have_clinical_data_so_only_the_dentist_admin_gets_them` |
|  | `test_any_admin_sees_if_the_copies_are_up_to_date_and_whom_to_ask_for_the_download` |
|  | `test_when_no_admin_is_a_dentist_the_page_says_how_to_let_someone_download` |
|  | `test_old_copies_are_deleted_but_the_latest_always_stays` |
|  | `test_an_admin_chooses_how_many_copies_are_kept_and_the_extra_ones_go_with_the_next_copy` |
|  | `test_too_few_or_too_many_copies_are_not_accepted` |
|  | `test_who_downloaded_each_copy_shows_on_the_page_and_stays_in_the_change_history` |
|  | `test_radiographs_photos_and_signatures_are_downloaded_apart_and_only_the_new_ones` |
|  | `test_after_a_week_without_downloading_the_page_and_the_home_remind_it` |

*Tabla A9. Casos del flujo "Salud del sistema".*

| Archivo | Caso |
| --- | --- |
| `ObservabilityTest` | `test_up_answers_when_laravel_and_the_database_answer` |
|  | `test_up_fails_when_the_database_does_not_answer` |
|  | `test_each_request_gets_its_own_id_in_the_answer` |
|  | `test_a_json_log_line_says_which_request_route_and_user_it_came_from` |

## Ejecuciones de la CI {-}

La tabla A10 lista todas las ejecuciones de GitHub Actions en `venga-implantes` hasta el 19/11/2026 a las 7:55, con hora de Lima. Cada una se puede abrir en https://github.com/francalizaya266-sudo/venga-implantes/actions. Los motivos de las fallas están en la tabla 5 del documento.

*Tabla A10. Ejecuciones de GitHub Actions.*

| N.º | Inicio (Lima) | Workflow | Evento | Rama | Commit | Resultado | Duración |
| --: | --- | --- | --- | --- | --- | --- | --: |
| 1 | 18/11 02:04 | Pruebas | PR | `feat/separar-backend-frontend` | `8cf0595` | En verde | 3 min 16 s |
| 2 | 18/11 03:15 | Pruebas | push | `main` | `b91fdee` | En verde | 3 min 24 s |
| 3 | 18/11 08:18 | Pruebas | PR | `claude/…2tzlbx` | `8e78cf8` | En verde | 2 min 14 s |
| 4 | 18/11 11:42 | Pruebas | PR | `claude/…2tzlbx` | `65baaa5` | En verde | 2 min 33 s |
| 5 | 18/11 11:48 | Pruebas | PR | `claude/…2tzlbx` | `1f3db64` | En verde | 2 min 12 s |
| 6 | 18/11 13:02 | Pruebas | PR | `claude/…2tzlbx` | `1d6b839` | En verde | 3 min 31 s |
| 7 | 18/11 20:27 | Pruebas | PR | `feat/paciente-nuevo-sin-enlace` | `0236661` | En verde | 1 min 58 s |
| 8 | 18/11 20:43 | Pruebas | push | `main` | `ee380bd` | En verde | 3 min 36 s |
| 9 | 18/11 20:43 | Pruebas | push | `main` | `3c66e25` | En verde | 3 min 27 s |
| 10 | 18/11 21:29 | Pruebas | PR | `feat/volver-al-inicio-al-reservar` | `b6eddf3` | En verde | 3 min 27 s |
| 11 | 18/11 22:14 | Pruebas | PR | `claude/…2tzlbx` | `fb8886e` | En verde | 5 min 40 s |
| 12 | 18/11 22:21 | Pruebas | PR | `claude/…2tzlbx` | `c4710e8` | En verde | 5 min 26 s |
| 13 | 18/11 23:02 | Pruebas | push | `main` | `3b61528` | En verde | 3 min 45 s |
| 14 | 19/11 00:13 | Pruebas | PR | `feat/pasar-a-recepcion` | `a535d11` | En verde | 3 min 39 s |
| 15 | 19/11 00:13 | Pruebas | PR | `feat/horario-mas-facil` | `ad2608d` | En verde | 4 min 23 s |
| 16 | 19/11 00:13 | Pruebas | PR | `feat/pendientes-en-el-menu` | `12b4548` | En verde | 2 min 23 s |
| 17 | 19/11 00:29 | Pruebas | push | `main` | `cf3e233` | En verde | 2 min 27 s |
| 18 | 19/11 00:29 | Pruebas | push | `main` | `f2e84ec` | En verde | 4 min 35 s |
| 19 | 19/11 00:39 | Pruebas | push | `main` | `25ac6ea` | En verde | 3 min 27 s |
| 20 | 19/11 00:54 | Pruebas | PR | `feat/consulta-dni` | `8ba44b3` | En verde | 3 min 10 s |
| 21 | 19/11 01:00 | Pruebas | push | `main` | `dd11e1b` | En verde | 4 min 42 s |
| 22 | 19/11 01:01 | Pruebas | PR | `feat/feriados-nager-date` | `924c52e` | En verde | 2 min 51 s |
| 23 | 19/11 01:05 | Pruebas | push | `main` | `95991e2` | En verde | 3 min 16 s |
| 24 | 19/11 01:26 | Pruebas | PR | `fix/registrar-fallas-de-conexion` | `16ed9cf` | En verde | 2 min 57 s |
| 25 | 19/11 01:29 | Pruebas | push | `main` | `bfae744` | En verde | 4 min 48 s |
| 26 | 19/11 01:30 | Pruebas | push | `main` | `ac47050` | Falló | 7 min 00 s |
| 27 | 19/11 02:08 | Pruebas | PR | `claude/…2tzlbx` | `8563765` | En verde | 5 min 11 s |
| 28 | 19/11 02:14 | Pruebas | push | `main` | `47a3fc5` | En verde | 5 min 15 s |
| 29 | 19/11 02:25 | Pruebas | PR | `infra/azure` | `c1e5ae3` | En verde | 7 min 20 s |
| 30 | 19/11 02:28 | Pruebas | PR | `claude/…2tzlbx` | `8576e89` | Falló | 4 min 07 s |
| 31 | 19/11 02:35 | Pruebas | PR | `claude/…2tzlbx` | `4990c3d` | Falló | 5 min 56 s |
| 32 | 19/11 02:36 | Pruebas | push | `main` | `7a1a558` | En verde | 8 min 06 s |
| 33 | 19/11 02:49 | Pruebas | PR | `claude/…2tzlbx` | `1683fc6` | Falló | 6 min 54 s |
| 34 | 19/11 02:57 | Pruebas | PR | `claude/…2tzlbx` | `4f5e045` | Falló | 5 min 28 s |
| 35 | 19/11 03:06 | Pruebas | PR | `claude/…2tzlbx` | `8c20db0` | En verde | 7 min 48 s |
| 36 | 19/11 03:07 | Pruebas | PR | `infra/chile-central` | `578868b` | En verde | 7 min 48 s |
| 37 | 19/11 03:16 | Pruebas | push | `main` | `feecf8e` | En verde | 6 min 47 s |
| 38 | 19/11 03:16 | Pruebas | PR | `claude/…2tzlbx` | `e0af366` | En verde | 7 min 43 s |
| 39 | 19/11 03:30 | Pruebas | PR | `infra/firewall-linux` | `098b6f5` | En verde | 6 min 04 s |
| 40 | 19/11 03:40 | Pruebas | push | `main` | `e3cf66c` | En verde | 6 min 55 s |
| 41 | 19/11 05:07 | Pruebas | PR | `infra/ajustes-despliegue` | `f866365` | En verde | 8 min 03 s |
| 42 | 19/11 05:11 | Pruebas | PR | `infra/ajustes-despliegue` | `78d2c4f` | En verde | 7 min 20 s |
| 43 | 19/11 05:28 | Desplegar en Azure | a mano | `main` | `e3cf66c` | Falló | 16 min 00 s |
| 44 | 19/11 06:06 | Pruebas | PR | `infra/ajustes-despliegue` | `2c1e51b` | En verde | 5 min 08 s |
| 45 | 19/11 06:11 | Pruebas | push | `main` | `35b41e3` | En verde | 5 min 22 s |
| 46 | 19/11 06:31 | Pruebas | push | `main` | `e3ccd05` | En verde | 7 min 40 s |
| 47 | 19/11 07:09 | Pruebas | PR | `infra/https-y-operacion` | `ff90de6` | En verde | 7 min 16 s |
| 48 | 19/11 07:20 | Pruebas | PR | `claude/…2tzlbx` | `efa8cbb` | Falló | 6 min 47 s |
| 49 | 19/11 07:21 | Pruebas | PR | `infra/https-y-operacion` | `a72be2a` | En verde | 7 min 30 s |
| 50 | 19/11 07:26 | Pruebas | PR | `claude/…2tzlbx` | `eaccb4c` | En verde | 7 min 06 s |
| 51 | 19/11 07:35 | Pruebas | push | `main` | `0805fe8` | En verde | 9 min 27 s |
| 52 | 19/11 07:40 | Pruebas | PR | `claude/…2tzlbx` | `4993b7b` | Falló | 9 min 01 s |
| 53 | 19/11 07:45 | Desplegar en Azure | tras la CI | `main` | `0805fe8` | Omitida | 0 min 01 s |
| 54 | 19/11 07:46 | Pruebas | PR | `claude/…2tzlbx` | `b83443d` | Falló | 9 min 21 s |
| 55 | 19/11 07:48 | Pruebas | PR | `claude/…2tzlbx` | `1ef7cc9` | Falló | 7 min 12 s |
| 56 | 19/11 07:51 | Pruebas | PR | `claude/…2tzlbx` | `a2f8c8a` | Falló | 8 min 21 s |
| 57 | 19/11 07:52 | Pruebas | PR | `claude/…2tzlbx` | `21f2782` | Falló | 7 min 32 s |
| 58 | 19/11 07:54 | Pruebas | PR | `claude/…2tzlbx` | `9daddc1` | En verde | 9 min 27 s |

La ejecución 50 es la del PR del E4 en verde, y la 51, su merge a `main`. Las dos guardan el artefacto `cobertura-backend` (unos 16 MB, con el reporte HTML), que se descarga desde la página de cada ejecución hasta el 19/12/2026. Las ejecuciones 52 y 54 a 58 son las del PR #20: la primera corrida de Larastan y los ajustes de su línea base hasta que coincidió con la CI. La 53, el despliegue que sigue al merge, se omitió porque el despliegue automático está apagado (`DESPLIEGUE_AUTOMATICO` vacía).

# Anexo B. Evidencias del pipeline y de la operación

## Primera publicación en Azure {-}

*Tabla B1. Pasos de la ejecución `44505713594` de "Desplegar en Azure" (19/11/2026, segundo intento, hora de Lima).*

| Paso | Inicio | Duración | Resultado | Qué hizo |
| --- | --- | --: | --- | --- |
| Preparar y descargar el código | 05:28:23 | 6 s | En verde | `actions/checkout` y `setup-node` con Node 22 |
| Compilar la web | 05:28:29 | 7 s | En verde | `npm ci` y `npm run build` en `frontend/` |
| Armar el paquete | 05:28:36 | 0 s | En verde | `paquete-20261119-102836-e3cf66c.zip` sin pruebas, `vendor` ni `.env` |
| Entrar a Azure | 05:28:36 | 6 s | En verde | `azure/login` con OIDC, sin claves |
| Subir a Blob | 05:28:42 | 5 s | En verde | El paquete y la carpeta `infra` |
| Publicar en vm-app | 05:28:47 | 1 min 37 s | En verde | `deploy-app.sh`: Composer, migraciones con `consultorio_despliegue`, `nginx -t` y el enlace `current` |
| Comprobar la web | 05:30:24 | 13 min 50 s | Falló | `/up` no respondió desde internet: el NSG descartaba la respuesta. Lo corrigió el PR #17 |

Después de esa ejecución se acotó la espera de `/up`, que ahora corta cada intento a los 20 s y hace cinco reintentos. También se agregó la acción `volver`.

## Configuración de la automatización {-}

El workflow de despliegue completo, con sus dos acciones:

<!-- archivo: ../../../../../.github/workflows/desplegar-azure.yml yaml -->

La vigilancia de `/up` cada 15 minutos:

<!-- archivo: ../../../../../.github/workflows/vigilar-produccion.yml yaml -->

## Prueba de la vuelta atrás {-}

`infra/scripts/volver-version.sh` es lo que corre la acción `volver` en `vm-app`:

<!-- archivo: ../../../../../infra/scripts/volver-version.sh bash -->

`evidencias/probar-volver-version.sh` lo prueba en una carpeta temporal con tres versiones de mentira. Reemplaza `sudo` y `systemctl` por funciones que anotan lo que se les pide. Resultado del 19/11/2026:

<!-- archivo: ../evidencias/prueba-volver-version.txt -->

## Registros estructurados y salud {-}

`tests/Feature/ObservabilityTest.php` prueba lo que mira quien opera el sistema. Las cuatro pruebas pasan en la suite del 19/11/2026:

*Tabla B2. Pruebas de observabilidad.*

| Prueba | Qué demuestra |
| --- | --- |
| `test_up_answers_when_laravel_and_the_database_answer` | `/up` responde 200 y `{"status":"up"}` cuando todo funciona. |
| `test_up_fails_when_the_database_does_not_answer` | Con la base de datos inaccesible, `/up` responde 500 y la vigilancia avisa. |
| `test_each_request_gets_its_own_id_in_the_answer` | Cada respuesta trae un `X-Request-Id` distinto. |
| `test_a_json_log_line_says_which_request_route_and_user_it_came_from` | Una línea del canal `json` trae `request_id` (el mismo de la cabecera), método, nombre de la ruta y usuario, y no guarda la dirección con el token del paciente. |

## Capturas de producción {-}

Estas evidencias se toman con producción encendida. Ninguna debe mostrar claves, la IP pública ni datos que no sean del seeder.

*Tabla B3. Evidencias de operación pendientes.*

| N.º | Qué capturar | Dónde |
| --: | --- | --- |
| 1 | `[PENDIENTE – estudiante (Jhoel): "Desplegar en Azure" con la acción publicar, en verde, con "Listo: /var/www/vega/releases/…" y /up en 200]` | GitHub, Actions |
| 2 | `[PENDIENTE – estudiante (Jhoel): "Desplegar en Azure" con la acción volver, en verde]` | GitHub, Actions |
| 3 | `[PENDIENTE – estudiante (Jhoel): resumen de una ejecución de "Vigilar producción" con el código y el tiempo de /up]` | GitHub, Actions |
| 4 | `[PENDIENTE – estudiante (Jhoel): las alertas alerta-cpu-vm-app y alerta-vm-caida y el grupo de acciones]` | Portal de Azure, Monitor |
| 5 | `[PENDIENTE – estudiante (Jhoel): métricas de CPU de vm-app de un día de uso]` | Portal de Azure, Monitor |
| 6 | `[PENDIENTE – estudiante (Jhoel): tablero de Wazuh con eventos de vm-app, uno del canal json]` | Wazuh |
| 7 | `[PENDIENTE – estudiante: artefacto cobertura-backend abierto (index.html)]` | GitHub, Actions |

# Anexo C. Artefactos ágiles

## Project Charter {-}

*Tabla C1. Project Charter del sistema web de Clínica Vega Implant.*

| Campo | Contenido |
| --- | --- |
| Nombre | Sistema web de Clínica Vega Implant |
| Organización | Clínica Vega Implant, Jr. Apurímac 648, Juliaca |
| Patrocinador | Dr. Vega, administrador y odontólogo `[PENDIENTE – estudiante: nombre completo]` |
| Equipo | Fran Franklin Calizaya Apaza, Jhoel Hairton Apaza Quispe y Joselyn Yucra Mamani (UPeU, campus Juliaca, ciclo 10) |
| Justificación | La clínica atiende con dos doctores y una recepcionista. Las reservas por llamada ocupan a recepción, las agendas se cruzan, y la historia clínica, los presupuestos y los cobros no se ven juntos. Además, los datos de salud deben protegerse según la Ley N.º 29733. |
| Objetivo general | Que la clínica reserve, atienda y cobre en un solo sistema web, con los datos de salud protegidos. |
| Alcance | 87 RF en 13 módulos, 17 RNF, 24 reglas de negocio y 12 HU (E1, tablas 2 a 5). Web del paciente (React), panel del personal (Filament), API `/api/v1` documentada, servicios externos (WhatsApp, RENIEC por Decolecta y Nager.Date) y despliegue en Azure. |
| Fuera del alcance | App móvil y servicio de IA de inasistencias (descartados el 19/11/2026, ADR-12 y ADR-13), pasarela de pagos y facturación electrónica (ADR-09), migración de datos reales. |
| Supuestos | La clínica valida los flujos con el guion del E1. El crédito de Azure for Students alcanza con producción apagada fuera de las demostraciones. Las demostraciones usan solo datos del seeder. |
| Restricciones | Stack existente (Laravel 13, PHP 8.3, React 19, MySQL 8), sin dependencias nuevas sin aprobación del equipo, repositorio público sin secretos, Ley N.º 29733 y plazos del semestre 2026-2. |
| Presupuesto | 100 USD de crédito de Azure por integrante, con alertas al 50 % y al 80 % de 40 USD al mes (`infra/azure/presupuesto.bicep`). Herramientas libres o gratuitas para estudiantes. |
| Criterios de éxito | Los flujos de las 12 HU funcionan y tienen prueba; la CI está en verde con cobertura ≥ 90 %; el sistema publicado en Azure responde `/up`; la clínica firma la validación; los cuatro entregables alcanzan "Excelente" en su rúbrica. |

*Tabla C2. Objetivos y cómo se miden.*

| Objetivo | Indicador | Valor al 19/11/2026 |
| --- | --- | --- |
| O1. Reservar sin llamar | Reserva en tres pasos desde el celular (HU-01) | Funciona y tiene prueba (`BookingControllerTest`) |
| O2. Agenda sin cruces | Reservas simultáneas no toman el mismo horario (RNF-REN-01) | Probado (`test_a_time_taken_meanwhile_sends_the_patient_back_to_choose_another`) |
| O3. Todo en un lugar | Historia, presupuestos y cobros en el panel (HU-07 a HU-11) | Funciona; 598 casos del panel |
| O4. Datos de salud protegidos | Cada rol ve solo lo suyo | 35 políticas y 84 pruebas de permisos |
| O5. Operación continua | Publicación automática, vuelta atrás y vigilancia | Implementado; evidencia en producción pendiente (anexo B) |

*Tabla C3. Interesados.*

| Interesado | Interés | Participación |
| --- | --- | --- |
| Dr. Vega (administrador) | Un sistema que ordene la clínica y proteja los datos | Patrocinador; valida y acepta `[PENDIENTE – estudiante: actas]` |
| Recepción | Agendar, avisar y cobrar rápido | Usuaria; valida con el guion del E1 |
| Doctores | Su agenda y la historia de sus pacientes | Usuarios; validan la atención |
| Pacientes | Reservar y confirmar sin llamar | Usuarios finales de la web |
| Docente de la EPE | Evidencias de cada competencia | Evalúa E1 a E4 y la sustentación |

*Tabla C4. Entregables e hitos.*

| Hito | Entregable | Evidencia | Fecha |
| --- | --- | --- | --- |
| Inicio | Diagnóstico frente a la rúbrica (FASE 0) | PR VI-2 | 18/11/2026 |
| Arquitectura | E1 Requerimientos y diseño | PR VI-2 y VI-12 | 18 y 19/11/2026 |
| Datos | E2 Plataforma de datos | PR VI-5 y VI-12 | 19/11/2026 |
| Capacidad operativa | E3 Sistema funcional integrado | PR VI-14 | 19/11/2026 |
| Despliegue | Infraestructura en Azure | PR VI-13, VI-15, VI-16 y VI-17 | 19/11/2026 |
| Entrega | E4 Gestión y calidad | PR VI-19, VI-20 y VI-21 | 19/11/2026 |
| Cierre | Sustentación y aceptación de la clínica | Anexo E | `[PENDIENTE – estudiante]` |

*Tabla C5. Registro de riesgos completo.*

| N.º | Riesgo | Prob. | Impacto | Respuesta | Responsable | Estado |
| --: | --- | :-: | :-: | --- | --- | --- |
| R1 | La cuota de Azure for Students no alcanza para todas las VM | Alta | Alto | Mitigar: producción y sede en regiones distintas, una VM de laboratorio a la vez | Jhoel | Ocurrió y se resolvió (PR VI-17) |
| R2 | El alcance cambia tarde | Media | Alto | Aceptar y registrar: MoSCoW y ADR | Fran | Ocurrió (ADR-12 y ADR-13) |
| R3 | Un servicio externo no responde | Media | Medio | Mitigar: 5 s de espera y alternativa propia (ADR-14) | Fran | Controlado con pruebas |
| R4 | Se filtran datos de salud | Baja | Muy alto | Evitar: políticas, auditoría, datos de prueba, sin secretos | Todo el equipo | Sin incidentes |
| R5 | Código de un asistente de IA sin revisar | Media | Alto | Mitigar: PR con pruebas, CI y revisión | Fran | Controlado (112 de 115 PR describen sus pruebas) |
| R6 | Una prueba inestable bloquea `main` | Media | Bajo | Mitigar: corregir la causa | Fran | Ocurrió y se corrigió |
| R7 | Una sola persona integra todo | Alta | Medio | Mitigar: plantilla de PR y revisión de un segundo integrante | Todo el equipo | Abierto (plan de mejora) |
| R8 | Se acaba el crédito de Azure antes de la sustentación | Media | Alto | Mitigar: apagar producción fuera de las demostraciones, alertas de presupuesto | Jhoel | Abierto |
| R9 | La clínica no valida a tiempo | Media | Medio | Mitigar: guion de validación listo por rol (E1) | Joselyn `[PENDIENTE]` | Abierto |

*Tabla C6. EDT con sus paquetes de trabajo y su evidencia.*

| Entrega | Paquete de trabajo | Evidencia en el repositorio |
| --- | --- | --- |
| Gestión | Charter y backlog; sprints; seguimiento | Este anexo; historial de PR; `agil/` |
| Requerimientos y diseño | SRS; prototipo y validación; C4 y ADR | `E1-requerimientos-y-diseno/` |
| Plataforma de datos | Modelo y migraciones; objetos de MySQL; usuarios y copias | `backend/database/`, `E2-plataforma-de-datos/` |
| Construcción | Web del paciente; panel; API y servicios externos | `frontend/`, `backend/app/`, `docs/api/openapi.json` |
| Calidad | Pruebas y cobertura; revisión y métricas; auditoría y mejora | `backend/tests/`, `frontend/src/**/*.test.tsx`, `E4-gestion-y-calidad/` |
| Despliegue y operación | Azure como código; pipeline; monitoreo y respaldo | `infra/`, `.github/workflows/` |

*Aprobación del Charter.* `[PENDIENTE – firma del Dr. Vega y de los tres integrantes]`

## Historias de usuario {-}

Las 12 historias del E1 (tabla 5 de ese documento), con el sprint en que se construyó lo principal de cada una. Sus criterios de aceptación en Gherkin están en el anexo D del E1.

*Tabla C7. Historias de usuario del Product Backlog.*

| HU | Como… | quiero… | Prior. | Sprint |
| --- | --- | --- | :-: | --- |
| HU-01 | paciente | reservar en línea en tres pasos | M | S1 (#9), mejorada en S2 y S3 |
| HU-02 | paciente | confirmar o cancelar desde el enlace de WhatsApp | M | S1 (#9) |
| HU-03 | paciente | cambiar mi cita | M | S1 (#9) |
| HU-04 | titular del celular | ver las citas de mi familia sin contraseña | M | S1 (#9), S4 (VI-3) |
| HU-05 | recepción | agendar desde la agenda sin cruces | M | S1 (#4), S3 (#74 a #76) |
| HU-06 | recepción | marcar llegada o inasistencia y deshacer un error | M | S1 (#2), S3 (#74) |
| HU-07 | recepción | cobrar en un paso | M | S1 (#11), S2 (#51) |
| HU-08 | recepción | cerrar la caja del día | M | S2 (#27) |
| HU-09 | doctor | atender y cerrar la atención | M | S1 (#5), S2 (#46, #47) |
| HU-10 | doctor | presentar un presupuesto que el paciente firma | M | S1 (#6, #19) |
| HU-11 | doctor | recetar con avisos de seguridad | M | S1 (#5, #6) |
| HU-12 | administrador | cambiar las reglas de reserva | M | S3 (#77 a #81) |

## Sprint Backlogs {-}

Cada Sprint Backlog es la lista de PR fusionados en esa semana, con la hora de Lima. Los números con `#` son de `consultorio-dental`, y los que empiezan con `VI-`, de `venga-implantes`. Los generó `agil/sprints-desde-git.py` desde la API de GitHub y `git log`.

## Sprint 1 (19 al 25 de octubre de 2026) {-}

*Tabla C8. Sprint Backlog del S1: 25 PR fusionados.*

| PR | Fecha | Autor | Título |
| --- | --- | --- | --- |
| #1 | 22/10 | Fran | Modelo de datos y lógica central de reservas |
| #2 | 22/10 | Fran | Paneles por rol: agenda del día, avisos, pacientes, caja y configuración |
| #3 | 22/10 | Fran | Claude/paneles |
| #4 | 22/10 | Fran | Agenda en calendario: por día y por semana, arrastrar para mover citas |
| #5 | 22/10 | Fran | Historia clínica: atención desde la cita, odontograma interactivo y escritura rápida |
| #6 | 22/10 | Fran | Plan de tratamiento con presupuesto y recetas por WhatsApp e impresas |
| #7 | 22/10 | Fran | Pasar a main la historia clínica y el plan de tratamiento (#5 y #6) |
| #8 | 22/10 | Fran | Radiografías y fotos, consentimientos firmados en pantalla e historia en PDF |
| #9 | 22/10 | Fran | Reserva en línea, Mi cuenta y cambio de horario desde el enlace |
| #10 | 23/10 | Fran | Páginas públicas: inicio, tratamientos, equipo, promociones, opiniones y contacto |
| #11 | 23/10 | Fran | Cobros y saldos: cargos automáticos, cobrar desde la cita y cuenta del paciente |
| #12 | 23/10 | Fran | Reportes: ganancias con Excel e impresión, y resumen con gráficos |
| #13 | 23/10 | Fran | Copias de seguridad diarias y pacientes para llamar a control |
| #14 | 23/10 | Fran | Datos de Clínica Vega Implant y un solo doctor |
| #15 | 23/10 | Fran | WhatsApp de la clínica: 918 874 568 |
| #16 | 23/10 | Fran | Panel de inicio: un solo doctor, azul de la clínica y celular |
| #17 | 23/10 | Fran | Odontograma más rápido: menos clics y sin esperar al servidor |
| #18 | 23/10 | Fran | Odontograma: siglas con significado, sugerencias para el plan, lupa en tablet y dictado |
| #19 | 23/10 | Fran | Plan firmado en la tablet, precios fijos y pago en cuotas |
| #20 | 23/10 | Fran | Paquetes del doctor con precio y cuotas, y precios del catálogo para doctores |
| #21 | 23/10 | Fran | 2x1 en los paquetes y campañas que ofrecen un paquete |
| #22 | 23/10 | Fran | Reporte por campaña y cupos que cuentan a quienes aceptan el paquete |
| #23 | 23/10 | Fran | Cuotas: aviso por WhatsApp, cuotas por cobrar y plan en Mi cuenta |
| #24 | 23/10 | Fran | Precio con campaña en la web y en el WhatsApp de confirmación |
| #25 | 24/10 | Fran | Agenda con panel al costado, atención con barra fija, menú de 7 y web más clara en el celular |

## Sprint 2 (2 al 8 de noviembre) {-}

*Tabla C9. Sprint Backlog del S2: 28 PR fusionados.*

| PR | Fecha | Autor | Título |
| --- | --- | --- | --- |
| #26 | 05/11 | Fran | Hoy por rol: el mes de un vistazo, por hacer hoy, huecos de la semana y próximo paciente |
| #27 | 05/11 | Fran | Lista de espera, presupuestos de todos los pacientes y cierre de caja del día |
| #28 | 05/11 | Fran | Hoy como dashboard moderno: números del día, agenda en línea de tiempo y el mes con gráfico |
| #29 | 05/11 | Fran | Ficha del paciente con etiquetas, celular cómodo y colores con sentido |
| #30 | 05/11 | Fran | Inventario: materiales, lotes, compras por WhatsApp y descuento al atender |
| #31 | 05/11 | Fran | Rediseño de la web pública (etapa 1) |
| #32 | 05/11 | Fran | Rediseño de la reserva y Mi cuenta (etapa 2) |
| #33 | 05/11 | Fran | Rediseño del panel: color, agenda y nueva cita (etapa 3) |
| #34 | 05/11 | Fran | Rediseño: reserva, Mi cuenta y panel a main (etapas 2 y 3) |
| #35 | 05/11 | Fran | Panel: botones en el azul de la marca |
| #36 | 05/11 | Fran | Logo desde Ajustes: sale en la web y en el panel |
| #37 | 05/11 | Fran | Pulido: preguntas frecuentes y Resumen en el celular |
| #38 | 05/11 | Fran | Movimiento y toques según el skill de Emil Kowalski |
| #39 | 05/11 | Fran | Revisión del panel: citas sin cerrar, menos duplicados y Ajustes en pestañas |
| #40 | 05/11 | Fran | Cita conectada con la ficha del paciente |
| #41 | 05/11 | Fran | Auditoría: borrar sin romper la agenda, seguridad rápida y atención según lo programado |
| #42 | 06/11 | Fran | Agenda más clara: color del estado, citas sin cerrar y "Nueva cita" a la vista |
| #43 | 06/11 | Fran | Precio a criterio del dentista en lo que se hizo |
| #44 | 06/11 | Fran | Agenda con diseño moderno |
| #45 | 06/11 | Fran | Agenda: buscador de huecos al costado y el día en lista para el celular |
| #46 | 06/11 | Fran | Atender: barra fija como encabezado, antecedentes sin salir y la pantalla en tres pasos |
| #47 | 06/11 | Fran | Atender se adapta al tratamiento y al paciente |
| #48 | 06/11 | Fran | Odontograma nuevo: dientes con su forma, ficha de la pieza, buscador, filtros y pantalla completa |
| #49 | 06/11 | Fran | Tanda 1: que el panel no se equivoque |
| #50 | 06/11 | Fran | Tanda 2: cada paciente con su siguiente paso |
| #51 | 07/11 | Fran | Tanda 3: cerrar el ciclo de la visita (cobro, constancia y cierre del día) |
| #52 | 07/11 | Fran | Tanda 4: menú en orden, un nombre por cosa, sin repetidos y estética pareja |
| #53 | 07/11 | Fran | Tanda 5: el sistema se adelanta (agenda, inventario, opiniones, presupuesto, cobros, puesta a punto y web) |

## Sprint 3 (9 al 15 de noviembre) {-}

*Tabla C10. Sprint Backlog del S3: 44 PR fusionados.*

| PR | Fecha | Autor | Título |
| --- | --- | --- | --- |
| #54 | 10/11 | Fran | Tanda 6: lo que quedó fuera |
| #55 | 10/11 | Fran | Celular: 9 dígitos que empiezan con 9; 10 dígitos ya no pasan |
| #56 | 10/11 | Fran | Tandas 7 y 8: seguimiento, reglas por tratamiento, gastos y laboratorio |
| #57 | 11/11 | Fran | Recepción: cinco mejoras para el día a día (tanda 9) |
| #58 | 11/11 | Fran | Usuario conectado a la vista y "Atendido" a cargo del doctor |
| #59 | 11/11 | Fran | El cobro vuelve a guardar, y el presupuesto de la evaluación queda a la vista |
| #60 | 11/11 | Fran | Paquetes: formas de pago claras, lo del diagnóstico dentro del paquete y el paquete en un renglón |
| #61 | 11/11 | Fran | Paquetes: sin cobro por cita, cuánto duran y control según los brackets |
| #62 | 11/11 | Fran | Paquetes: un solo precio a la vista, firma antes de cobrar y sumar tratamientos sin repetirlo |
| #63 | 11/11 | Fran | Presupuestos y planes: cuánto pagó y cuánto debe, bajo el monto |
| #64 | 11/11 | Fran | Buscar hueco: mañana o tarde, día de la semana y más días |
| #65 | 11/11 | Fran | Campanita al instante y recordatorios que no dependen de una hora exacta |
| #66 | 11/11 | Fran | Panel con identidad visual y página de la cita más clara |
| #67 | 11/11 | Fran | Lo que confundía: total en Cobrar, Reportes, avisos, listas y fecha de nacimiento |
| #68 | 11/11 | Fran | Recuperar la contraseña desde el inicio de sesión |
| #69 | 11/11 | Fran | Repaso con el estilo nuevo: gráficos con el azul de la marca y listas que entran en una laptop |
| #70 | 12/11 | Fran | Pacientes, Atenciones y Presupuestos: cada opción abre lo suyo |
| #71 | 12/11 | Fran | Hoy: los cuatro botones avisan lo que ya hay y no sacan del inicio |
| #72 | 12/11 | Fran | Hoy: las tarjetas se actualizan juntas y sus números coinciden con su lista |
| #73 | 12/11 | Fran | Tiempo real: lo que guarda una persona lo ven las demás en 3 segundos |
| #74 | 12/11 | Fran | Agenda, etapa 1: ocho correcciones que evitan datos y avisos equivocados |
| #75 | 12/11 | Fran | Agenda, etapa 2: una sola regla, una sola Nueva cita y los mismos botones en todos lados |
| #76 | 12/11 | Fran | Agenda, etapa 3: sobrecupo, citas que se repiten, bloqueos de día completo y semana del consultorio |
| #77 | 13/11 | Fran | Configuración, etapa 1: las 21 fallas de la auditoría y copias de seguridad con archivos |
| #78 | 13/11 | Fran | Configuración, etapa 2: quitar duplicados, lo que sobraba y ordenar las pantallas |
| #79 | 13/11 | Fran | Configuración, etapa 3: agregar lo que falta |
| #80 | 13/11 | Fran | Configuración, etapa 3 (segunda parte): paquetes, doctores, usuarios, textos y copias |
| #81 | 14/11 | Fran | Configuración: tratamientos por tipo y cuántas copias se guardan |
| #82 | 14/11 | Fran | Página web, etapa 1: fallas de la reserva y nombres de la familia |
| #83 | 14/11 | Fran | Página web, etapa 2 (primera parte): la cita |
| #84 | 14/11 | Fran | Página web, etapa 2 (segunda parte): el resto del sitio |
| #85 | 14/11 | Fran | Página web, etapa 3: estética con imágenes 3D |
| #86 | 14/11 | Fran | Página web: quien reserva con el número de otra familia queda por confirmar |
| #87 | 14/11 | Fran | Página web: el inicio con la estética de la maqueta |
| #88 | 14/11 | Fran | Página web: la foto del inicio sube aunque pese y la portada queda como la maqueta |
| #89 | 14/11 | Fran | Página web: una luz azul alrededor de la imagen de cada tratamiento |
| #90 | 14/11 | Fran | Página web: el implante de la maqueta, la frase editable y "servicios" en los textos |
| #91 | 14/11 | Fran | Portada e imágenes más nítidas, azules más vivos y las letras de la maqueta |
| #92 | 14/11 | Fran | Inicio más ordenado: quién te atiende en breve, precios resaltados y pie más corto |
| #93 | 14/11 | Fran | Implante que parece diente, franja de la especialidad en azul vivo y cierre con "Reservar cita" |
| #94 | 14/11 | Fran | Página más ancha en la laptop y encabezado editable en cada página |
| #95 | 14/11 | Fran | Tarjetas en Servicios, Equipo y Contacto |
| #96 | 14/11 | Fran | Buscador y temas en Preguntas frecuentes, y Mi cuenta con su encabezado y sus tarjetas |
| #97 | 14/11 | Fran | Reservar cita con su encabezado, pasos numerados y el resumen de la cita |

## Sprint 4 (16 al 22 de noviembre, en curso) {-}

*Tabla C11. Sprint Backlog del S4: 18 PR fusionados.*

| PR | Fecha | Autor | Título |
| --- | --- | --- | --- |
| #98 | 17/11 | Fran | En "Otra hora", la hora se elige con botones en vez de escribirse |
| VI-1 | 18/11 | Fran | feat: separar el sistema en backend (Laravel) y frontend (React) |
| VI-2 | 18/11 | Fran | CE02 FASE 0 y FASE 1: diagnóstico y E1 Requerimientos y Diseño |
| VI-3 | 18/11 | Fran | feat(web): el paciente nuevo entra a Mi cuenta sin esperar el enlace |
| VI-4 | 18/11 | Fran | feat(web): "Volver al inicio" en el encabezado de Reservar cita |
| VI-5 | 19/11 | Fran | CE02 FASE 2: E2 Plataforma de Datos del Sistema (CE0221–CE0224) |
| VI-6 | 19/11 | Fran | feat(panel): pasar el cobro a recepción y avisarle al terminar la atención |
| VI-7 | 19/11 | Fran | feat(panel): elegir el horario de una cita nueva con menos toques |
| VI-8 | 19/11 | Fran | feat(panel): los pendientes de cada opción, a la vista en el menú |
| VI-9 | 19/11 | Fran | feat(panel): traer de RENIEC los nombres al registrar un paciente |
| VI-10 | 19/11 | Fran | feat(agenda): sumar los feriados de Nager.Date al cargar los del año |
| VI-11 | 19/11 | Fran | fix: registrar por qué no se pudo conectar con RENIEC o Nager.Date |
| VI-12 | 19/11 | Fran | Corregir el E1 y el E2 frente a la rúbrica oficial |
| VI-13 | 19/11 | Jhoel | feat(infra): despliegue en Azure alineado con el E1 (CE03) |
| VI-14 | 19/11 | Fran | E3: sistema funcional integrado (API documentada, pruebas y documento) |
| VI-15 | 19/11 | Jhoel | fix(infra): desplegar en Chile Central con la serie B v2 |
| VI-16 | 19/11 | Jhoel | feat(infra): firewall Linux automático en lugar de OPNsense |
| VI-17 | 19/11 | Jhoel | fix(infra): ajustes del primer despliegue real en Azure |

## Retrospectivas {-}

`[PENDIENTE – validar con el estudiante]` Estos borradores salen de los datos de cada sprint. Lo que pasó es un hecho del historial. Lo que se aprendió es una propuesta que el equipo debe confirmar o corregir en la reunión de retrospectiva.

*Tabla C12. Borradores de retrospectiva.*

| Sprint | Qué funcionó (dato) | Qué mejorar (dato) | Acción propuesta |
| --- | --- | --- | --- |
| S1 | 25 PR en tres días cubrieron todos los módulos, con 265 métodos de prueba | 24 de 25 PR describen sus pruebas: uno no. En S2 y S3 hubo muchas correcciones de lo construido aquí | Describir cada PR con la plantilla; mostrar cada módulo a recepción antes de darlo por terminado |
| S2 | La revisión #41 encontró fallas antes de que la clínica las viera; las pruebas subieron a 453 | El rediseño entró en cuatro PR el mismo día (#31 a #34) | Partir los cambios grandes en etapas que se puedan revisar por separado |
| S3 | Tres revisiones por módulo (agenda, configuración y web) y dos refactorizaciones (#75 y #78); las pruebas llegaron a 941 | 16 PR de la web en un día (#82 a #97), casi todos ajustes visuales frente a la maqueta | Acordar la maqueta con la clínica antes de construir la pantalla |
| S4 | 13 PR salieron de ramas del equipo (Fran y Jhoel); la CI detectó la dependencia de `APP_DEBUG` antes de fusionar | Una prueba inestable llegó a `main`; el primer despliegue esperó 14 min por una falla de red | Esperas acotadas (hecho) y un ensayo de despliegue antes de la semana de entrega |

`[PENDIENTE – estudiante: fecha, asistentes y acuerdos de cada retrospectiva]`

# Anexo D. Matriz de calidad y checklists de auditoría

La matriz de calidad completa es la tabla 15 del documento. Este anexo trae los checklists con que se evaluó el proceso y el registro de la deuda técnica.

## Checklist de revisión de cada PR {-}

Es el contenido de `.github/pull_request_template.md`. Quien revisa marca cada punto antes de aprobar.

<!-- archivo: ../../../../../.github/pull_request_template.md markdown -->

## Checklist CMMI-DEV v1.3, nivel 2 {-}

Calificación por práctica específica (SCAMPI C): TI totalmente, MI mayormente, PI parcialmente y NI no implementada. La evidencia es lo que se puede abrir en el repositorio o en GitHub.

*Tabla D1. Gestión de proyecto: REQM, PP, PMC y MA.*

| Práctica | Evidencia | Calif. |
| --- | --- | :-: |
| REQM SP 1.1 Entender los requerimientos | SRS del E1 con 87 RF, 17 RNF y 24 RN, con su fuente | TI |
| REQM SP 1.2 Obtener el compromiso con los requerimientos | Prioridad MoSCoW acordada por el equipo; firma de la clínica pendiente | MI |
| REQM SP 1.3 Gestionar los cambios | Cambios de alcance como ADR (ADR-12, ADR-13, ADR-14) y en los PR | TI |
| REQM SP 1.4 Trazabilidad bidireccional | Matriz RF → HU → código → prueba (anexo A del E1) y `verificar-trazabilidad.py` | TI |
| REQM SP 1.5 Alinear el trabajo con los requerimientos | Cada PR cita lo que cambia; la matriz se verifica por script | TI |
| PP SP 1.1 Estimar el alcance (EDT) | EDT de la figura 3 y la tabla C6, hechas al cierre | MI |
| PP SP 1.2 Estimar atributos de productos y tareas | Sin estimación de tamaño | NI |
| PP SP 1.3 Definir el ciclo de vida | Sprints semanales y fases de OpenUP (sección 3.4) | MI |
| PP SP 1.4 Estimar esfuerzo y costo | Costo de Azure estimado (40 USD al mes); esfuerzo sin estimar | PI |
| PP SP 2.1 Presupuesto y calendario | Presupuesto con alertas; calendario dado por los entregables de la EPE | PI |
| PP SP 2.2 Identificar riesgos | Registro de riesgos (tabla C5), hecho al cierre | MI |
| PP SP 2.3 Planificar la gestión de datos | Todo en git; evidencias en `docs/entregables` | TI |
| PP SP 2.4 Planificar recursos | Infraestructura definida como código (`infra/`) | MI |
| PP SP 2.5 Planificar conocimientos | Sin plan de capacitación | NI |
| PP SP 2.6 Planificar la participación de interesados | Guion de validación por rol (E1) | PI |
| PP SP 2.7 Establecer el plan del proyecto | Sin plan escrito antes de empezar; el Charter es retrospectivo | PI |
| PP SP 3.1 a 3.3 Revisar, conciliar y comprometer el plan | Sin evidencia | NI |
| PMC SP 1.1 Monitorear los parámetros del plan | Avance en PR y pruebas, sin plan con qué comparar | PI |
| PMC SP 1.2 Monitorear los compromisos | Sin evidencia | NI |
| PMC SP 1.3 Monitorear los riesgos | Riesgos atendidos al ocurrir (R1, R6) | PI |
| PMC SP 1.4 Monitorear la gestión de datos | CI y artefactos con retención | MI |
| PMC SP 1.5 Monitorear la participación de interesados | Sin registro | NI |
| PMC SP 1.6 Revisiones de avance | Descripción de cada PR; sin reunión registrada | PI |
| PMC SP 1.7 Revisiones de hitos | Cada entregable cerrado con su PR y su revisión | MI |
| PMC SP 2.1 a 2.3 Analizar problemas y acciones correctivas | Fallas de la CI y del despliegue con causa y corrección (tabla 5) | MI |
| MA SP 1.1 Objetivos de medición | Metas de la matriz (tabla 15), fijadas en el E4 | PI |
| MA SP 1.2 Especificar las medidas | Métricas con fórmula: cobertura, complejidad, tiempos | MI |
| MA SP 1.3 Recolección y almacenamiento | CI (cobertura y avisos de Larastan en cada cambio), scripts de `calidad/` y `agil/` | MI |
| MA SP 1.4 Procedimientos de análisis | Umbrales de code smells y mínimo de cobertura | MI |
| MA SP 2.1 a 2.4 Obtener, analizar, guardar y comunicar | Medidas del E3 y del E4 guardadas en el repositorio; no se midió de forma periódica antes del E4 | PI |

*Tabla D2. Soporte: PPQA y CM.*

| Práctica | Evidencia | Calif. |
| --- | --- | :-: |
| PPQA SP 1.1 Evaluar objetivamente los procesos | La CI aplica las mismas reglas a todos; el proceso no se auditaba antes del E4 | PI |
| PPQA SP 1.2 Evaluar objetivamente los productos | Pruebas, formato, cobertura, análisis estático e inspecciones (#41, #74, #77) | TI |
| PPQA SP 2.1 Comunicar y resolver no conformidades | Cada falla de la CI bloquea el merge hasta corregirse | TI |
| PPQA SP 2.2 Mantener registros | Historial de Actions y de PR | MI |
| CM SP 1.1 Identificar los elementos de configuración | Código, base (migraciones y SQL), infraestructura, workflows y documentos en git | TI |
| CM SP 1.2 Sistema de gestión de configuración | GitHub con ramas y PR | TI |
| CM SP 1.3 Crear líneas base | `main` y versiones numeradas en producción (cinco guardadas); etiquetas de entrega pendientes | MI |
| CM SP 2.1 Seguir los pedidos de cambio | Cada cambio en un PR con su descripción | TI |
| CM SP 2.2 Controlar los elementos | Solo se fusiona con la CI en verde; solo `main` se publica | TI |
| CM SP 3.1 Registros de configuración | Historial de git y de despliegues | TI |
| CM SP 3.2 Auditorías de configuración | Este informe; sin auditorías periódicas | PI |

## Checklist ISO/IEC 29110, perfil básico {-}

*Tabla D3. Procesos de ISO/IEC 29110-5-1-2.*

| Proceso | Evidencia | Resultado |
| --- | --- | --- |
| PM.1 Planificación del proyecto | Charter, EDT y backlog al cierre; sin plan ni estimación previos | Parcial |
| PM.2 Ejecución del plan | Sprints, PR, CI y registros de avance | Cumple |
| PM.3 Evaluación y control | Fallas analizadas y corregidas; sin comparación con un plan | Parcial |
| PM.4 Cierre del proyecto | Pendiente: aceptación de la clínica y sustentación | Pendiente |
| SI.1 Inicio de la implementación | Repositorio, entorno local y CI | Cumple |
| SI.2 Análisis de requerimientos | SRS, HU y prototipo; validación firmada pendiente | Parcial |
| SI.3 Arquitectura y diseño detallado | C4, ADR, clases, secuencia y estados (E1) | Cumple |
| SI.4 Construcción | Código con pruebas por componente | Cumple |
| SI.5 Integración y pruebas | CI, contrato OpenAPI y suite en MySQL 8 | Cumple |
| SI.6 Entrega del producto | Manual técnico (E3) y despliegue automatizado; publicación en verde pendiente | Parcial |

## Registro de deuda técnica {-}

El esfuerzo es una estimación del equipo (S: horas, M: un día, L: varios días) `[PENDIENTE – validar con el estudiante]`. Las decisiones son: pagar ahora, pagar al tocar el código o aceptar con motivo.

*Tabla D4. Deuda técnica.*

| N.º | Partida | Dónde | Esfuerzo | Decisión |
| --: | --- | --- | :-: | --- |
| 1 | Formulario de atención de 1 336 líneas; `addPackageAction` con complejidad 39 | `ConsultationForm` | L | Pagar al tocarlo (plan de mejora, acción 8) |
| 2 | Página de agenda de 1 289 líneas; `rail` con complejidad 28 | `Filament/Pages/Agenda` | L | Pagar al tocarla |
| 3 | `read` con complejidad 33 y 5 parámetros | `FindingsDictation` | M | Plan de mejora, acción 8 |
| 4 | `for` con complejidad 41 | `TreatmentRisks` | | Aceptar: tabla plana de reglas por tratamiento, se lee de arriba abajo |
| 5 | `overlappingRanges` con complejidad 31 | `DoctorForm` | S | Pagar al tocarlo |
| 6 | 29 métodos con más de 5 parámetros | `BillingService::charge`, `ConsentService::fill` y otros | M | Agrupar en objetos de datos, como `WebBookingData` |
| 7 | `store` con complejidad 15 tras la refactorización | `BookingController` | | Aceptar: cada condición es una respuesta distinta para el paciente (422, 429, 409 o 403) |
| 8 | Servicios de más de 900 líneas | `TreatmentPlanService`, `AppointmentService` | L | Partir por responsabilidad al tocarlos |
| 9 | 45 métodos de borrado de políticas sin prueba | `app/Policies` | S | Plan de mejora, acción 7 |
| 10 | Dos listas del panel sin prueba | `PatientsRelationManager`, `BatchesRelationManager` | S | Pagar en el próximo sprint |
| 11 | 12 verificaciones negativas con una sola palabra | `tests/Feature/Filament` | S | Plan de mejora, acción 9 |
| 12 | Ramas de los componentes de la web al 66,6 % (77,4 % en toda la web) | `frontend/src/components` | M | Pagar al tocar cada componente y subir el mínimo de ramas de 70 % a 75 % |
| 13 | 2 174 avisos de Larastan en la línea base (tabla D6) | `backend/phpstan-baseline.php` | L | Plan de mejora, acción 11 |
| 14 | Acciones de GitHub con Node 20 | `.github/workflows` | S | Plan de mejora, acción 10 |

## Análisis estático con Larastan {-}

Larastan 3.15 (PHPStan 2.3 para Laravel) revisa `backend/app` en nivel 5 en el job "Análisis estático (Larastan)" de cada PR (`backend/phpstan.neon`). La primera corrida, el 19/11/2026, dio 2 178 avisos en 220 archivos. Cuatro venían de tres fallas reales, corregidas con su prueba (tabla D5). Los otros 2 174 quedan en `backend/phpstan-baseline.php` (tabla D6): la CI falla con cualquier aviso nuevo, y también cuando uno de la lista desaparece sin quitarlo, para que la lista solo baje.

*Tabla D5. Fallas reales que encontró Larastan.*

| Aviso | Dónde | Qué pasaba | Corrección |
| --- | --- | --- | --- |
| `prunable()` declara devolver `App\Models\Builder`, que no existe | `Models/MagicLink.php` | La limpieza diaria (`model:prune`) terminaba con un error de tipo al llegar a los enlaces de WhatsApp, y los vencidos hace más de 30 días nunca se borraban | Se importa el `Builder` de Eloquent. `MagicLinkPruningTest` fallaba antes del cambio y ahora pasa |
| `persistent()` recibe una condición, pero no acepta argumentos | `RegisterPaymentAction`, `LabOrderResource` | Los avisos "Pago registrado" y "Trabajo recibido" quedaban siempre en pantalla, porque Filament ignoraba la condición | Se quita la condición y queda lo que la clínica ya usa. La prueba de cobro lo verifica también sin nada que agendar |

*Tabla D6. Avisos de la línea base por causa (`calidad/clasificar-larastan.py`).*

| Causa | Avisos | Archivos | Ejemplo | Origen y qué hacer |
| --- | --: | --: | --- | --- |
| Relaciones sin tipo genérico | 1 163 | 167 | `Access to an undefined property Model::$patient` | Las relaciones se declaran `BelongsTo` sin `<Patient, $this>` y el analizador ve un `Model` cualquiera. Agregar el tipo (plan de mejora, acción 11) |
| Casts leídos como texto | 728 | 130 | `Cannot call method format() on string` | Toma como texto las fechas, enums y arreglos de `casts()` y deduce condiciones "siempre falsas". Revisar por qué no lee `casts()` |
| Accessors y columnas calculadas | 81 | 43 | `Patient::$full_name` | No reconoce los accessors `Attribute` ni columnas como `pivot` o `low_count` |
| Scopes con `#[Scope]` | 60 | 36 | `Builder::active()` | No reconoce el atributo `#[Scope]` de Laravel 12 en adelante |
| Otros | 142 | 48 | Formas de arreglos, closures de Filament | Revisar al tocar cada archivo |
| **Total** | **2 174** | **220** | | |

## Métricas de código {-}

Salida de `calidad/medir-codigo.php` el 19/11/2026, que es la fuente de las tablas 16 y D4:

<!-- archivo: ../calidad/metricas-codigo.txt -->

# Anexo E. Evidencia de la sustentación

Este anexo guarda la evidencia de que el entregable se sustentó ante el docente, como pide la plantilla del E4. Se llena el día de la sustentación `[PENDIENTE – estudiante: llenar el día de la sustentación]`.

*Tabla E1. Registro de la sustentación del E4.*

| Campo | Registro |
| --- | --- |
| Fecha y hora | `[PENDIENTE – estudiante]` |
| Docente evaluador | `[PENDIENTE – estudiante]` |
| Integrantes que sustentaron y parte que expuso cada uno | `[PENDIENTE – estudiante]` |
| Demostración en vivo (CI de un PR, publicación y vuelta atrás, vigilancia de `/up`) | `[PENDIENTE – estudiante]` |
| Preguntas del docente y respuesta resumida | `[PENDIENTE – estudiante]` |
| Observaciones y compromisos de corrección | `[PENDIENTE – estudiante]` |

**Preguntas que conviene preparar.** ¿Por qué la meta de cobertura es 90 % y no 100 %? ¿Por qué Scrum adaptado y no OpenUP puro? ¿Por qué el proceso queda en nivel 1 si la ingeniería es fuerte? ¿Cómo se revisó el código escrito con el asistente de IA? ¿Qué pasa si una versión nueva rompe producción?

**Capturas de la sesión.** `[PENDIENTE – estudiante: una captura de la sustentación con los integrantes visibles y otra de la demostración]`

**Firma del docente.** `[PENDIENTE – firma]`

