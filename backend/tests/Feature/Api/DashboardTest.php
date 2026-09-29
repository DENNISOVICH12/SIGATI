<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Asset;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_engineer_receives_global_aggregates_and_combined_activity(): void
    {
        $engineer = $this->user('engineer');
        $technician = $this->user('technician');
        $area = Area::create(['name' => 'Sistemas', 'code' => 'SIS', 'active' => true]);
        $asset = Asset::create([
            'code' => 'PC-SIS-001', 'name' => 'Equipo', 'category' => 'Computador',
            'area_id' => $area->id, 'status' => 'faulty',
        ]);
        $asset->history()->create([
            'user_id' => $engineer->id, 'action' => 'status_changed',
            'description' => 'El activo cambió de estado.',
            'old_values' => ['status' => 'operational'], 'new_values' => ['status' => 'faulty'],
        ]);

        $new = $this->ticket('TCK-001', ['response_due_at' => now()->subMinute()]);
        $assigned = $this->ticket('TCK-002', ['status' => 'assigned', 'assigned_to' => $technician->id]);
        $resolved = $this->ticket('TCK-003', ['status' => 'resolved', 'assigned_to' => $technician->id, 'resolved_at' => now()]);
        $assigned->events()->create([
            'user_id' => $engineer->id, 'event_type' => 'assigned', 'description' => 'Servicio asignado.',
            'new_status' => 'assigned', 'new_assigned_to' => $technician->id,
        ]);

        Sanctum::actingAs($engineer);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('scope', 'global')
            ->assertJsonPath('assets.total', 1)
            ->assertJsonPath('assets.by_status.faulty', 1)
            ->assertJsonPath('tickets.open', 2)
            ->assertJsonPath('tickets.unassigned', 1)
            ->assertJsonPath('tickets.sla_breached', 1)
            ->assertJsonPath('tickets.resolved_pending_closure', 1)
            ->assertJsonCount(2, 'recent_activity');
    }

    public function test_technician_dashboard_is_scoped_to_assigned_work_but_exposes_claimable_queue(): void
    {
        $technician = $this->user('technician');
        $other = $this->user('technician');
        $mine = $this->ticket('TCK-MINE', [
            'status' => 'in_progress', 'assigned_to' => $technician->id,
            'resolution_due_at' => now()->subMinute(),
        ]);
        $theirs = $this->ticket('TCK-THEIRS', ['status' => 'assigned', 'assigned_to' => $other->id]);
        $this->ticket('TCK-FREE');
        $mine->events()->create(['user_id' => $technician->id, 'event_type' => 'started', 'description' => 'Atención iniciada.']);
        $theirs->events()->create(['user_id' => $other->id, 'event_type' => 'assigned', 'description' => 'Otro servicio.']);

        Sanctum::actingAs($technician);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('scope', 'assigned')
            ->assertJsonPath('tickets.total', 1)
            ->assertJsonPath('tickets.open', 1)
            ->assertJsonPath('tickets.by_status.in_progress', 1)
            ->assertJsonPath('tickets.by_status.assigned', 0)
            ->assertJsonPath('tickets.unassigned', 1)
            ->assertJsonPath('tickets.sla_breached', 1)
            ->assertJsonPath('attention.resolved_pending_closure', 0)
            ->assertJsonCount(1, 'recent_activity')
            ->assertJsonPath('recent_activity.0.resource_code', 'TCK-MINE');
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function ticket(string $code, array $attributes = []): Ticket
    {
        return Ticket::create(array_merge([
            'code' => $code,
            'reporter_name' => 'Solicitante',
            'title' => 'Incidencia técnica',
            'description' => 'Descripción de la incidencia.',
            'category' => 'Hardware',
            'priority' => 'medium',
            'status' => 'new',
            'source' => 'internal',
            'reported_at' => now(),
        ], $attributes));
    }
}
