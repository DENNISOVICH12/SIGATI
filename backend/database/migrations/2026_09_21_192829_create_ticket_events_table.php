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
        Schema::create('ticket_events', function (Blueprint $table) {
            $table->id();

            // Ticket al que pertenece el evento
            $table->foreignId('ticket_id')
                ->constrained('tickets')
                ->cascadeOnDelete();

            // Usuario interno que realizó la acción.
            // Puede ser NULL cuando el evento proviene del portal público
            // o de una acción automática del sistema.
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Tipo de evento
            // Ejemplos:
            // created, claimed, released, assigned,
            // started, updated, resolved, closed, reopened
            $table->string('event_type', 50);

            // Estado anterior y nuevo del ticket
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30)->nullable();

            // Técnico que tenía asignado el ticket antes del evento
            $table->foreignId('old_assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Técnico asignado después del evento
            $table->foreignId('new_assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Descripción legible del evento
            $table->text('description')->nullable();

            // Motivo obligatorio para determinadas acciones,
            // especialmente liberación/desistimiento, reasignación, etc.
            $table->text('reason')->nullable();

            // Información adicional del evento.
            // Ej.: cambios realizados, diagnóstico, metadatos, etc.
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Índices para consultar rápidamente la línea de tiempo
            $table->index(['ticket_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_events');
    }
};