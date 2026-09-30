<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->restrictOnDelete();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->enum('origin', ['manual', 'ticket', 'preventive_plan']);
            $table->enum('maintenance_type', ['preventive', 'corrective', 'additional']);
            $table->timestamp('performed_at');
            $table->enum('result', ['operational', 'follow_up_required', 'fault_persists']);
            $table->text('observations')->nullable();
            $table->boolean('preventive_cycle_completed')->default(false);
            $table->enum('status', ['completed', 'voided'])->default('completed');
            $table->timestamps();

            $table->index(['asset_id', 'performed_at']);
            $table->index(['ticket_id', 'created_at']);
            $table->index(['performed_by', 'performed_at']);
            $table->index(['result', 'performed_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE maintenances
                ADD CONSTRAINT maintenances_ticket_origin_check
                CHECK (origin <> 'ticket' OR ticket_id IS NOT NULL)
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenances');
    }
};
