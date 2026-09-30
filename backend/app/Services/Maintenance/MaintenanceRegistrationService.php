<?php

namespace App\Services\Maintenance;

use App\Domain\Maintenance\MaintenanceHistoryAction;
use App\Domain\Maintenance\MaintenanceOrigin;
use App\Domain\Maintenance\MaintenanceStatus;
use App\Models\Asset;
use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MaintenanceRegistrationService
{
    public function registerManual(int $assetId, User $actor, array $data): Maintenance
    {
        if (! $actor->can('maintenance.create')) {
            throw new AuthorizationException('No tiene permiso para registrar mantenimientos.');
        }

        return DB::transaction(function () use ($assetId, $actor, $data): Maintenance {
            $asset = Asset::query()->lockForUpdate()->findOrFail($assetId);
            $performedAt = now();

            $maintenance = new Maintenance([
                'asset_id' => $asset->id,
                'ticket_id' => null,
                'origin' => MaintenanceOrigin::Manual,
                'maintenance_type' => $data['maintenance_type'],
                'performed_at' => $performedAt,
                'result' => $data['result'],
                'observations' => $data['observations'] ?? null,
                'preventive_cycle_completed' => $data['preventive_cycle_completed'] ?? false,
            ]);
            $maintenance->code = $this->newCode($performedAt->format('Ymd'));
            $maintenance->performed_by = $actor->id;
            $maintenance->status = MaintenanceStatus::Completed;
            $maintenance->save();

            $asset->history()->create([
                'user_id' => $actor->id,
                'action' => MaintenanceHistoryAction::REGISTERED,
                'description' => "Mantenimiento {$maintenance->code} registrado.",
                'new_values' => [
                    'maintenance_id' => $maintenance->id,
                    'maintenance_code' => $maintenance->code,
                    'ticket_id' => null,
                    'result' => $maintenance->result->value,
                ],
            ]);

            return $maintenance->refresh();
        });
    }

    private function newCode(string $date): string
    {
        // 60 bits of random entropy plus the database UNIQUE constraint avoid races.
        return 'MNT-'.$date.'-'.Str::upper(Str::random(10));
    }
}
