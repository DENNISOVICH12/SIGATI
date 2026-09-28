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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            /*
             * Código visible institucional del ticket.
             * Ejemplo: TCK-000001
             */
            $table->string('code', 30)->unique();

            /*
             * Activo relacionado.
             *
             * Puede ser NULL porque eventualmente puede existir
             * una solicitud que no esté asociada a un activo.
             */
            $table->foreignId('asset_id')
                ->nullable()
                ->constrained('assets')
                ->restrictOnDelete();

            /*
             * Técnico actualmente responsable.
             *
             * NULL = ticket libre y disponible para ser tomado.
             */
            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
             * Información de la persona que reporta.
             *
             * El usuario público del QR no necesita una cuenta
             * interna de SIGATI.
             */
            $table->string('reporter_name', 150);
            $table->string('reporter_email', 150)->nullable();
            $table->string('reporter_phone', 30)->nullable();

            /*
             * Información de la falla.
             */
            $table->string('title', 200);
            $table->text('description');

            /*
             * Categoría del incidente.
             *
             * Inicialmente será texto para permitir que el catálogo
             * evolucione durante la validación con el hospital.
             */
            $table->string('category', 80)->nullable();

            /*
             * Prioridad.
             *
             * low      = Baja
             * medium   = Media
             * high     = Alta
             * critical = Crítica
             */
            $table->string('priority', 20)->default('medium');

            /*
             * Estado actual.
             *
             * new         = Nuevo
             * assigned    = Asignado
             * in_progress = En proceso
             * on_hold     = En espera
             * resolved    = Resuelto
             * closed      = Cerrado
             * reopened    = Reabierto
             */
            $table->string('status', 30)->default('new');

            /*
             * Origen de la solicitud.
             *
             * qr       = Portal QR
             * internal = Personal de Sistemas
             * manual   = Registro manual
             */
            $table->string('source', 30)->default('internal');

            /*
             * Fechas importantes para trazabilidad y SLA.
             */
            $table->timestamp('reported_at')->useCurrent();

            $table->timestamp('assigned_at')->nullable();

            /*
             * Primera atención real del técnico.
             * Nos permitirá medir tiempo de primera respuesta.
             */
            $table->timestamp('first_response_at')->nullable();

            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            /*
             * Fecha límite calculada según el SLA.
             *
             * NO debe reiniciarse cuando un técnico libera
             * o reasigna el ticket.
             */
            $table->timestamp('response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();

            /*
             * Información final de resolución.
             */
            $table->text('resolution')->nullable();

            $table->timestamps();

            /*
             * Índices para las consultas más frecuentes.
             */
            $table->index(['status', 'priority']);
            $table->index(['assigned_to', 'status']);
            $table->index(['asset_id', 'status']);
            $table->index('reported_at');
            $table->index('response_due_at');
            $table->index('resolution_due_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};