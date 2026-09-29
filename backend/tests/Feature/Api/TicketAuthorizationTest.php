<?php

namespace Tests\Feature\Api;

use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_engineer_can_close_resolved_ticket_but_technician_cannot(): void
    {
        $assigned = $this->userWithRole('technician');
        $ticket = $this->ticket(['status' => 'resolved', 'assigned_to' => $assigned->id, 'resolved_at' => now()]);

        Sanctum::actingAs($this->userWithRole('technician'));
        config(['app.debug' => false]);
        $eventCount = $ticket->events()->count();
        $this->postJson("/api/tickets/{$ticket->id}/close", ['reason' => 'Cierre validado'])
            ->assertForbidden()
            ->assertJsonStructure(['message'])
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('line')
            ->assertJsonMissingPath('trace');
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'status' => 'resolved',
            'closed_at' => null,
        ]);
        $this->assertSame($eventCount, $ticket->events()->count());

        Sanctum::actingAs($this->userWithRole('engineer'));
        $this->postJson("/api/tickets/{$ticket->id}/close", ['reason' => 'Cierre validado'])
            ->assertOk()
            ->assertJsonPath('ticket.status', 'closed');
    }

    public function test_technician_can_view_create_and_claim_tickets(): void
    {
        $technician = $this->userWithRole('technician');
        Sanctum::actingAs($technician);

        $this->getJson('/api/tickets')->assertOk();
        $created = $this->postJson('/api/tickets', [
            'reporter_name' => 'Mesa de ayuda',
            'title' => 'Impresora sin conexión',
            'description' => 'No responde en la red.',
            'category' => 'Impresoras',
        ])->assertCreated();

        $this->postJson('/api/tickets/'.$created->json('ticket.id').'/claim')
            ->assertOk()
            ->assertJsonPath('ticket.assigned_to', $technician->id)
            ->assertJsonPath('ticket.status', 'assigned');
    }

    public function test_engineer_can_assign_only_active_technicians(): void
    {
        $engineer = $this->userWithRole('engineer');
        $activeTechnician = $this->userWithRole('technician');
        $inactiveTechnician = $this->userWithRole('technician', ['active' => false]);
        $nonTechnician = User::factory()->create();
        Sanctum::actingAs($engineer);

        $ticket = $this->ticket();
        $this->postJson("/api/tickets/{$ticket->id}/assign", [
            'technician_id' => $activeTechnician->id,
            'reason' => 'Disponible para atención.',
        ])->assertOk()->assertJsonPath('ticket.assigned_to', $activeTechnician->id);

        $this->postJson('/api/tickets/'.$this->ticket()->id.'/assign', [
            'technician_id' => $nonTechnician->id,
            'reason' => 'Intento inválido.',
        ])->assertUnprocessable();

        $this->postJson('/api/tickets/'.$this->ticket()->id.'/assign', [
            'technician_id' => $inactiveTechnician->id,
            'reason' => 'Intento inválido.',
        ])->assertUnprocessable();
    }

    public function test_assigned_technician_can_start_and_resolve_but_another_cannot_resolve(): void
    {
        $assigned = $this->userWithRole('technician');
        $other = $this->userWithRole('technician');
        $ticket = $this->ticket(['status' => 'assigned', 'assigned_to' => $assigned->id, 'assigned_at' => now()]);

        Sanctum::actingAs($assigned);
        $this->postJson("/api/tickets/{$ticket->id}/start", [])->assertOk()
            ->assertJsonPath('ticket.status', 'in_progress');

        Sanctum::actingAs($other);
        $this->postJson("/api/tickets/{$ticket->id}/resolve", $this->resolutionPayload())
            ->assertForbidden();

        Sanctum::actingAs($assigned);
        $this->postJson("/api/tickets/{$ticket->id}/resolve", $this->resolutionPayload())
            ->assertOk()
            ->assertJsonPath('ticket.status', 'resolved');
    }

    public function test_assignable_technicians_endpoint_is_minimal_filtered_and_authorized(): void
    {
        $engineer = $this->userWithRole('engineer');
        $active = $this->userWithRole('technician', ['name' => 'Técnico Activo']);
        $this->userWithRole('technician', ['active' => false]);
        $this->userWithRole('engineer');

        Sanctum::actingAs($engineer);
        $this->getJson('/api/users/technicians')
            ->assertOk()
            ->assertJsonCount(1, 'technicians')
            ->assertJsonPath('technicians.0.id', $active->id)
            ->assertExactJson(['technicians' => [[
                'id' => $active->id,
                'name' => $active->name,
                'email' => $active->email,
            ]]]);

        Sanctum::actingAs($active);
        $this->getJson('/api/users/technicians')->assertForbidden();
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    private function ticket(array $attributes = []): Ticket
    {
        static $sequence = 0;
        $sequence++;

        return Ticket::create(array_merge([
            'code' => "TCK-TEST-{$sequence}",
            'reporter_name' => 'Usuario de prueba',
            'title' => 'Incidente de prueba',
            'description' => 'Descripción del incidente.',
            'category' => 'Soporte',
            'priority' => 'medium',
            'status' => 'new',
            'source' => 'internal',
            'reported_at' => now(),
        ], $attributes));
    }

    private function resolutionPayload(): array
    {
        return [
            'diagnosis' => 'Configuración incorrecta.',
            'solution' => 'Se corrigió la configuración.',
        ];
    }
}
