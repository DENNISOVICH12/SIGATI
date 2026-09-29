<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('reporter_email', 254)->nullable()->change();
            $table->string('category', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('reporter_email', 150)->nullable()->change();
            $table->string('category', 80)->nullable()->change();
        });
    }
};
