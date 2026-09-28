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
        Schema::create('locations', function (Blueprint $table) {
    $table->id();

    $table->foreignId('area_id')
        ->constrained('areas')
        ->restrictOnDelete();

    $table->string('name', 120);
    $table->string('code', 30)->nullable();
    $table->text('description')->nullable();

    $table->boolean('active')->default(true);

    $table->timestamps();

    $table->unique(['area_id', 'name']);
    $table->index(['area_id', 'active']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
