<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asset_history', function (Blueprint $table) {
            $table->id();

            // Activo al que pertenece el evento
            $table->foreignId('asset_id')
                ->constrained('assets')
                ->cascadeOnDelete();

            // Usuario que realizó la acción
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Tipo de acción realizada
            // Ej: created, updated, status_changed, transferred
            $table->string('action', 50);

            // Descripción legible del evento
            $table->text('description');

            // Motivo informado por el usuario
            $table->text('reason')->nullable();

            // Valores antes y después de la modificación
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->timestamps();

            // Índices para consultar rápidamente el historial
            $table->index(['asset_id', 'created_at']);
            $table->index('action');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_history');
    }
};