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
        Schema::create('assets', function (Blueprint $table) {
    $table->id();

    // Identificación institucional
    $table->string('code', 50)->unique();
    $table->string('name', 150);
    $table->string('category', 80);

    // Información del fabricante
    $table->string('brand', 100)->nullable();
    $table->string('model', 100)->nullable();
    $table->string('serial_number', 120)->nullable()->unique();

    // Ubicación actual
    $table->foreignId('area_id')
        ->constrained('areas')
        ->restrictOnDelete();

    $table->foreignId('location_id')
        ->nullable()
        ->constrained('locations')
        ->restrictOnDelete();

    // Responsable actual
    $table->string('responsible_name', 150)->nullable();

    // Información técnica
    $table->string('hostname', 120)->nullable();
    $table->string('ip_address', 45)->nullable();
    $table->string('mac_address', 17)->nullable();

    // Estado del activo
    $table->string('status', 40)->default('operational');

    // QR público seguro
    $table->uuid('public_token')->unique();

    // Información adicional
    $table->text('notes')->nullable();

    $table->timestamps();

    // Índices de consultas frecuentes
    $table->index(['area_id', 'status']);
    $table->index('category');
    $table->index('status');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
