<?php

use App\Http\Controllers\Site\AppointmentCalendarController;
use App\Http\Controllers\Site\SitemapController;
use App\Http\Controllers\Site\SpaController;
use Illuminate\Support\Facades\Route;

/*
 * Web pública. Las páginas las pinta el frontend (React, en frontend/): aquí cada dirección entrega el documento
 * que lo carga, con el título y los datos de la página ya escritos (SpaController). Lo que el paciente hace en
 * ellas (reservar, confirmar, cancelar…) va por la API: routes/api.php.
 *
 * Los nombres de las rutas se usan en todo el sistema para armar enlaces (mensajes de WhatsApp, el panel, el
 * mapa del sitio): si cambia una dirección aquí, cambia también en frontend/src/lib/paths.ts.
 */
Route::get('/', SpaController::class)->name('site.home');
Route::get('tratamientos', SpaController::class)->name('site.treatments');
Route::get('tratamientos/{slug}', SpaController::class)->name('site.treatment');
Route::get('promociones', SpaController::class)->name('site.promotions');
Route::get('promociones/{slug}', SpaController::class)->name('site.promotion');
Route::get('equipo', SpaController::class)->name('site.team');
Route::get('antes-y-despues', SpaController::class)->name('site.cases');
Route::get('opiniones', SpaController::class)->name('site.reviews');
Route::get('preguntas-frecuentes', SpaController::class)->name('site.faq');
Route::get('contacto', SpaController::class)->name('site.contact');
Route::get('privacidad', SpaController::class)->name('site.privacy');
Route::get('reservar', SpaController::class)->name('site.booking');

// "Guardar en mi calendario" al terminar de reservar. Firma relativa: vale igual detrás del proxy del hosting.
Route::get('reservar/cita/{appointment}/calendario.ics', AppointmentCalendarController::class)
    ->middleware(['signed:relative', 'throttle:30,1'])
    ->name('site.booking.calendar');
Route::get('sitemap.xml', [SitemapController::class, 'sitemap'])->name('site.sitemap');
Route::get('robots.txt', [SitemapController::class, 'robots'])->name('site.robots');

// Mi cuenta: entra con el celular recordado (sin contraseña).
Route::prefix('mi-cuenta')
    ->middleware('throttle:patient-pages')
    ->group(function () {
        Route::get('/', SpaController::class)->name('site.account');
        Route::get('citas/{appointment}/cambiar', SpaController::class)->name('site.account.change');
        Route::get('pacientes/{patient}/salud', SpaController::class)->name('site.account.health');
    });

// Enlace personal que viaja por WhatsApp (de una cita o de acceso a Mi cuenta).
Route::prefix('c/{token}')
    ->where(['token' => '[A-Za-z0-9]{32}'])
    ->middleware('throttle:patient-pages')
    ->group(function () {
        Route::get('/', SpaController::class)->name('patient.link');
        Route::get('cambiar', SpaController::class)->name('patient.link.change');
    });
