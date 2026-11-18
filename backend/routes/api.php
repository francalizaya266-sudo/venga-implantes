<?php

use App\Http\Controllers\Api\V1\Account\AccountController;
use App\Http\Controllers\Api\V1\Account\HealthController;
use App\Http\Controllers\Api\V1\Account\LinkController;
use App\Http\Controllers\Api\V1\Booking\BookingController;
use App\Http\Controllers\Api\V1\Site\FaqController;
use App\Http\Controllers\Api\V1\Site\HomeController;
use App\Http\Controllers\Api\V1\Site\PageController;
use App\Http\Controllers\Api\V1\Site\PromotionController;
use App\Http\Controllers\Api\V1\Site\ReviewController;
use App\Http\Controllers\Api\V1\Site\SiteController;
use App\Http\Controllers\Api\V1\Site\SmileCaseController;
use App\Http\Controllers\Api\V1\Site\TeamController;
use App\Http\Controllers\Api\V1\Site\TreatmentController;
use Illuminate\Support\Facades\Route;

/*
 * API de la web pública (versión 1). La consume el frontend en React, que vive en el mismo dominio: por eso usa
 * las cookies y la sesión de siempre (el celular recordado) y no tokens. Cada respuesta trae "data" (lo que la
 * pantalla muestra, con los textos ya listos) y, en las páginas, "meta" (título, descripción y datos para Google).
 */

// Lo que comparten todas las páginas y el contenido de cada una.
Route::get('site', SiteController::class)->name('site');
Route::get('home', HomeController::class)->name('home');
Route::get('treatments', [TreatmentController::class, 'index'])->name('treatments.index');
Route::get('treatments/{slug}', [TreatmentController::class, 'show'])->name('treatments.show');
Route::get('promotions', [PromotionController::class, 'index'])->name('promotions.index');
Route::get('promotions/{slug}', [PromotionController::class, 'show'])->name('promotions.show');
Route::get('team', TeamController::class)->name('team');
Route::get('cases', SmileCaseController::class)->name('cases');
Route::get('reviews', ReviewController::class)->name('reviews');
Route::get('faqs', FaqController::class)->name('faqs');
Route::get('pages/contact', [PageController::class, 'contact'])->name('pages.contact');
Route::get('pages/privacy', [PageController::class, 'privacy'])->name('pages.privacy');

// Reservar cita.
Route::controller(BookingController::class)
    ->prefix('booking')
    ->name('booking.')
    ->group(function () {
        Route::get('options', 'options')->name('options');
        Route::get('days', 'days')->name('days');
        Route::get('slots', 'slots')->name('slots');
        Route::post('appointments', 'store')->name('store');
        // "¿Cómo nos conociste?" al terminar de reservar. Firma relativa: solo la tiene quien acaba de reservar.
        Route::post('appointments/{appointment}/origin', 'origin')
            ->middleware(['signed:relative', 'throttle:30,1'])
            ->name('origin');
    });

// Mi cuenta: entra con el celular recordado (sin contraseña).
Route::prefix('account')
    ->name('account.')
    ->middleware('throttle:patient-api')
    ->group(function () {
        Route::controller(AccountController::class)->group(function () {
            Route::get('/', 'show')->name('show');
            Route::post('access', 'requestAccess')->name('access');
            Route::post('verification', 'requestVerification')->name('verification');
            Route::post('logout', 'logout')->name('logout');
            Route::post('appointments/{appointment}/confirm', 'confirm')->name('appointments.confirm');
            Route::post('appointments/{appointment}/cancel', 'cancel')->name('appointments.cancel');
            Route::get('appointments/{appointment}/change', 'change')->name('appointments.change');
            Route::get('appointments/{appointment}/change/slots', 'changeSlots')->name('appointments.change.slots');
            Route::post('appointments/{appointment}/change', 'saveChange')->name('appointments.change.save');
            Route::post('patients/{patient}/next-session', 'requestSession')->name('patients.session');
        });

        Route::controller(HealthController::class)->group(function () {
            Route::get('patients/{patient}/health', 'show')->name('patients.health');
            Route::post('patients/{patient}/health', 'store')->name('patients.health.store');
        });
    });

// Enlace personal que viaja por WhatsApp (de una cita o de acceso a Mi cuenta).
Route::controller(LinkController::class)
    ->prefix('links/{token}')
    ->name('links.')
    ->where(['token' => '[A-Za-z0-9]{32}'])
    ->middleware('throttle:patient-api')
    ->group(function () {
        Route::get('/', 'show')->name('show');
        Route::post('confirm', 'confirm')->name('confirm');
        Route::post('cancel', 'cancel')->name('cancel');
        Route::post('enter', 'enter')->name('enter');
        Route::get('change', 'change')->name('change');
        Route::get('change/slots', 'changeSlots')->name('change.slots');
        Route::post('change', 'saveChange')->name('change.save');
    });
