<?php

namespace App\Models;

use App\Domain\Maintenance\MaintenanceOrigin;
use App\Domain\Maintenance\MaintenanceResult;
use App\Domain\Maintenance\MaintenanceStatus;
use App\Domain\Maintenance\MaintenanceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Maintenance extends Model
{
    protected $fillable = [
        'asset_id',
        'ticket_id',
        'origin',
        'maintenance_type',
        'performed_at',
        'result',
        'observations',
        'preventive_cycle_completed',
    ];

    protected static function booted(): void
    {
        static::updating(function (Maintenance $maintenance): void {
            if ($maintenance->getRawOriginal('status') === MaintenanceStatus::Completed->value) {
                throw new LogicException('Un mantenimiento completado es un hecho histórico inmutable.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Los mantenimientos no pueden eliminarse físicamente.');
        });
    }

    protected function casts(): array
    {
        return [
            'origin' => MaintenanceOrigin::class,
            'maintenance_type' => MaintenanceType::class,
            'performed_at' => 'datetime',
            'result' => MaintenanceResult::class,
            'preventive_cycle_completed' => 'boolean',
            'status' => MaintenanceStatus::class,
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
