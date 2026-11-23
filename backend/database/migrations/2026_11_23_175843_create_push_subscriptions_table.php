<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Avisos en el celular: el permiso que dio el navegador de un celular recordado para mostrarle los avisos de sus
     * citas y cuotas en la pantalla. Se va con el celular: al salir, al cerrarle Mi cuenta o cuando el navegador
     * deja de aceptarlos.
     */
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('remembered_device_id')->constrained()->cascadeOnDelete();
            // La dirección del navegador es larga (más de 255 en algunos): se busca por su huella.
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->string('public_key', 120);
            $table->string('auth_token', 60);
            $table->string('content_encoding', 20);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
