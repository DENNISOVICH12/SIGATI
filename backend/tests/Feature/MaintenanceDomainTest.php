<?php

namespace Tests\Feature;

use App\Domain\Maintenance\MaintenanceHistoryAction;
use App\Domain\Maintenance\MaintenanceOrigin;
use App\Domain\Maintenance\MaintenanceResult;
use App\Domain\Maintenance\MaintenanceStatus;
use App\Domain\Maintenance\MaintenanceType;
use App\Models\Area;
use App\Models\Asset;
use App\Models\AssetHistory;
use App\Models\Maintenance;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Maintenance\MaintenanceRegistrationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MaintenanceDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_registration_creates_relations_and_a_minimal_history_summary(): void
    {
        $actor = $this->actorWithCreatePermission();
        $asset = $this->asset();

        $maintenance = app(MaintenanceRegistrationService::class)->registerManual($asset->id, $actor, [
            'maintenance_type' => MaintenanceType::Preventive,
            'result' => MaintenanceResult::Operational,
            'observations' => 'Limpieza interna y comprobación extensa.',
            'preventive_cycle_completed' => true,
        ]);

        $this->assertMatchesRegularExpression('/^MNT-\d{8}-[A-Z0-9]{10}$/', $maintenance->code);
        $this->assertTrue($maintenance->asset->is($asset));
        $this->assertTrue($maintenance->performedBy->is($actor));
        $this->assertNull($maintenance->ticket);
        $this->assertSame(MaintenanceOrigin::Manual, $maintenance->origin);
        $this->assertSame(MaintenanceStatus::Completed, $maintenance->status);
        $this->assertTrue($maintenance->preventive_cycle_completed);
        $this->assertNotNull($maintenance->performed_at);
        $this->assertTrue($asset->maintenances->first()->is($maintenance));
        $this->assertTrue($actor->performedMaintenances->first()->is($maintenance));

        $history = AssetHistory::where('asset_id', $asset->id)->sole();
        $this->assertSame(MaintenanceHistoryAction::REGISTERED, $history->action);
        $this->assertSame([
            'maintenance_id' => $maintenance->id,
            'maintenance_code' => $maintenance->code,
            'ticket_id' => null,
            'result' => MaintenanceResult::Operational->value,
        ], $history->new_values);
        $this->assertStringNotContainsString('Limpieza interna', json_encode($history->toArray()));
    }

    public function test_maintenance_may_belong_to_a_ticket_without_being_one_to_one(): void
    {
        $actor = User::factory()->create();
        $asset = $this->asset();
        $ticket = $this->ticket($asset);

        $first = $this->maintenance($asset, $actor, ['ticket_id' => $ticket->id, 'code' => 'MNT-REL-1']);
        $second = $this->maintenance($asset, $actor, ['ticket_id' => $ticket->id, 'code' => 'MNT-REL-2']);

        $this->assertTrue($first->ticket->is($ticket));
        $this->assertCount(2, $ticket->maintenances);
        $this->assertTrue($ticket->maintenances->contains($second));
    }

    public function test_code_is_unique_and_database_rejects_invalid_vocabulary(): void
    {
        $actor = User::factory()->create();
        $asset = $this->asset();
        $this->maintenance($asset, $actor, ['code' => 'MNT-UNIQUE']);

        try {
            $this->maintenance($asset, $actor, ['code' => 'MNT-UNIQUE']);
            $this->fail('El código duplicado debía ser rechazado.');
        } catch (QueryException) {
            $this->assertDatabaseCount('maintenances', 1);
        }

        $this->expectException(QueryException::class);
        DB::table('maintenances')->insert($this->attributes($asset, $actor, [
            'code' => 'MNT-BAD-ENUM',
            'origin' => 'invented',
        ]));
    }

    public function test_completed_maintenance_cannot_be_updated_or_deleted(): void
    {
        $maintenance = $this->maintenance($this->asset(), User::factory()->create());

        try {
            $maintenance->update(['observations' => 'Reescritura']);
            $this->fail('La actualización debía ser rechazada.');
        } catch (LogicException) {
            $this->assertDatabaseMissing('maintenances', ['id' => $maintenance->id, 'observations' => 'Reescritura']);
        }

        $this->expectException(LogicException::class);
        $maintenance->delete();
    }

    public function test_registration_rolls_back_if_asset_history_fails(): void
    {
        $actor = $this->actorWithCreatePermission();
        $asset = $this->asset();
        $dispatcher = AssetHistory::getEventDispatcher();
        AssetHistory::setEventDispatcher(clone $dispatcher);
        AssetHistory::creating(static fn () => throw new \RuntimeException('Fallo de historial.'));

        try {
            app(MaintenanceRegistrationService::class)->registerManual($asset->id, $actor, [
                'maintenance_type' => MaintenanceType::Corrective,
                'result' => MaintenanceResult::Operational,
            ]);
            $this->fail('La creación del historial debía fallar.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Fallo de historial.', $exception->getMessage());
        } finally {
            AssetHistory::setEventDispatcher($dispatcher);
        }

        $this->assertDatabaseCount('maintenances', 0);
        $this->assertDatabaseCount('asset_history', 0);
    }

    private function actorWithCreatePermission(): User
    {
        $actor = User::factory()->create();
        $permission = Permission::create(['name' => 'maintenance.create', 'guard_name' => 'web']);
        $actor->givePermissionTo($permission);

        return $actor;
    }

    private function asset(): Asset
    {
        $area = Area::create(['name' => uniqid('Area '), 'code' => uniqid('A-'), 'active' => true]);

        return Asset::create([
            'code' => uniqid('ASSET-'),
            'name' => 'Activo de prueba',
            'category' => 'Computer',
            'area_id' => $area->id,
            'status' => 'operational',
        ]);
    }

    private function ticket(Asset $asset): Ticket
    {
        return Ticket::create([
            'code' => uniqid('TCK-'),
            'asset_id' => $asset->id,
            'reporter_name' => 'Solicitante',
            'title' => 'Incidente',
            'description' => 'Descripción',
            'priority' => 'medium',
            'status' => 'new',
            'source' => 'internal',
            'reported_at' => now(),
        ]);
    }

    private function maintenance(Asset $asset, User $actor, array $overrides = []): Maintenance
    {
        $maintenance = new Maintenance();
        $maintenance->forceFill($this->attributes($asset, $actor, $overrides));
        $maintenance->save();

        return $maintenance;
    }

    private function attributes(Asset $asset, User $actor, array $overrides = []): array
    {
        return array_merge([
            'code' => uniqid('MNT-'),
            'asset_id' => $asset->id,
            'ticket_id' => null,
            'performed_by' => $actor->id,
            'origin' => MaintenanceOrigin::Manual->value,
            'maintenance_type' => MaintenanceType::Corrective->value,
            'performed_at' => now(),
            'result' => MaintenanceResult::Operational->value,
            'observations' => null,
            'preventive_cycle_completed' => false,
            'status' => MaintenanceStatus::Completed->value,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }
}
