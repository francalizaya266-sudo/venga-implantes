<?php

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
