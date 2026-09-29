<?php

namespace Tests\Feature\Api;

use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_claim_is_technician_only_and_a_second_claim_conflicts_without_a_second_event(): void
    {
        $engineer = $this->user('engineer');
        $first = $this->user('technician');
        $second = $this->user('technician');
        $ticket = $this->ticket();

        Sanctum::actingAs($engineer);
        $this->postJson("/api/tickets/{$ticket->id}/claim")->assertForbidden();

        Sanctum::actingAs($first);
        $this->postJson("/api/tickets/{$ticket->id}/claim")
            ->assertOk()
            ->assertJsonPath('ticket.status', 'assigned');

        Sanctum::actingAs($second);
        $this->postJson("/api/tickets/{$ticket->id}/claim")->assertConflict();

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'assigned_to' => $first->id, 'status' => 'assigned']);
        $this->assertDatabaseCount('ticket_events', 1);
        $this->assertDatabaseHas('ticket_events', ['ticket_id' => $ticket->id, 'event_type' => 'claimed', 'new_assigned_to' => $first->id]);
    }

    public function test_release_only_allows_assigned_owner_and_returns_ticket_to_new(): void
    {
        $technician = $this->user('technician');
        $ticket = $this->ticket(['status' => 'assigned', 'assigned_to' => $technician->id]);
        Sanctum::actingAs($technician);

        $this->postJson("/api/tickets/{$ticket->id}/release", ['reason' => 'Cambio de turno.'])
            ->assertOk()
            ->assertJsonPath('ticket.status', 'new')
            ->assertJsonPath('ticket.assigned_to', null);

        $inProgress = $this->ticket(['status' => 'in_progress', 'assigned_to' => $technician->id]);
        $this->postJson("/api/tickets/{$inProgress->id}/release", ['reason' => 'No aplica.'])->assertConflict();
        $this->assertDatabaseHas('tickets', ['id' => $inProgress->id, 'status' => 'in_progress']);
        $this->assertDatabaseMissing('ticket_events', ['ticket_id' => $inProgress->id]);
    }

    public function test_assignment_requires_active_technician_and_reassignment_preserves_state(): void
    {
        $engineer = $this->user('engineer');
        $first = $this->user('technician');
        $second = $this->user('technician');
        $inactive = $this->user('technician', ['active' => false]);
        Sanctum::actingAs($engineer);

        $new = $this->ticket();
        $this->postJson("/api/tickets/{$new->id}/assign", ['technician_id' => $first->id])
            ->assertOk()->assertJsonPath('ticket.status', 'assigned');

        $this->postJson("/api/tickets/{$new->id}/assign", ['technician_id' => $inactive->id])
            ->assertUnprocessable();

        $this->postJson("/api/tickets/{$new->id}/assign", ['technician_id' => $second->id])
            ->assertOk()->assertJsonPath('ticket.status', 'assigned');

        $progress = $this->ticket(['status' => 'in_progress', 'assigned_to' => $first->id]);
        $this->postJson("/api/tickets/{$progress->id}/assign", ['technician_id' => $second->id])
            ->assertUnprocessable();
        $this->postJson("/api/tickets/{$progress->id}/assign", [
            'technician_id' => $second->id,
            'reason' => 'Continuidad por cambio de turno.',
        ])->assertOk()->assertJsonPath('ticket.status', 'in_progress');

        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $progress->id,
            'event_type' => 'reassigned',
            'old_assigned_to' => $first->id,
            'new_assigned_to' => $second->id,
            'old_status' => 'in_progress',
            'new_status' => 'in_progress',
            'reason' => 'Continuidad por cambio de turno.',
        ]);
    }

    public function test_only_assigned_technician_can_start_and_invalid_start_has_no_effect(): void
    {
        $assigned = $this->user('technician');
        $other = $this->user('technician');
        $ticket = $this->ticket(['status' => 'assigned', 'assigned_to' => $assigned->id]);

        Sanctum::actingAs($other);
        $this->postJson("/api/tickets/{$ticket->id}/start")->assertForbidden();
        $this->assertDatabaseMissing('ticket_events', ['ticket_id' => $ticket->id]);

        Sanctum::actingAs($assigned);
        $this->postJson("/api/tickets/{$ticket->id}/start")
            ->assertOk()->assertJsonPath('ticket.status', 'in_progress');
        $this->assertDatabaseHas('ticket_events', ['ticket_id' => $ticket->id, 'event_type' => 'started']);
    }

    public function test_resolve_validates_content_owner_and_exposes_structured_resolution(): void
    {
        $assigned = $this->user('technician');
        $other = $this->user('technician');
        $ticket = $this->ticket(['status' => 'in_progress', 'assigned_to' => $assigned->id]);

        Sanctum::actingAs($assigned);
        $this->postJson("/api/tickets/{$ticket->id}/resolve", ['diagnosis' => '   ', 'solution' => 'Solución'])
            ->assertUnprocessable();
        $this->postJson("/api/tickets/{$ticket->id}/resolve", ['diagnosis' => 'Diagnóstico', 'solution' => '   '])
            ->assertUnprocessable();

        Sanctum::actingAs($other);
        $this->postJson("/api/tickets/{$ticket->id}/resolve", $this->resolution())->assertForbidden();

        Sanctum::actingAs($assigned);
        $this->postJson("/api/tickets/{$ticket->id}/resolve", $this->resolution())
            ->assertOk()
            ->assertJsonPath('ticket.status', 'resolved')
            ->assertJsonPath('ticket.resolution.diagnosis', 'Falla de configuración.')
            ->assertJsonPath('ticket.resolution.solution', 'Configuración corregida.')
            ->assertJsonPath('ticket.resolution.notes', 'Validado con el usuario.');

        $event = $ticket->events()->where('event_type', 'resolved')->firstOrFail();
        $this->assertSame('Falla de configuración.', $event->metadata['diagnosis']);
        $this->assertSame('Configuración corregida.', $event->metadata['solution']);
    }

    public function test_only_engineer_can_close_a_resolved_ticket(): void
    {
        $technician = $this->user('technician');
        $engineer = $this->user('engineer');
        $ticket = $this->ticket(['status' => 'resolved', 'assigned_to' => $technician->id, 'resolved_at' => now()]);

        Sanctum::actingAs($technician);
        $this->postJson("/api/tickets/{$ticket->id}/close", ['reason' => 'Validado.'])->assertForbidden();

        Sanctum::actingAs($engineer);
        $this->postJson("/api/tickets/{$ticket->id}/close", ['note' => 'Nombre anterior.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'resolved', 'closed_at' => null]);
        $this->assertDatabaseMissing('ticket_events', ['ticket_id' => $ticket->id]);

        $this->postJson("/api/tickets/{$ticket->id}/close", ['reason' => 'Validado.'])
            ->assertOk()->assertJsonPath('ticket.status', 'closed');
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->id,
            'user_id' => $engineer->id,
            'event_type' => 'closed',
            'old_status' => 'resolved',
            'new_status' => 'closed',
            'old_assigned_to' => $technician->id,
            'new_assigned_to' => $technician->id,
            'reason' => 'Validado.',
        ]);

        $invalid = $this->ticket(['status' => 'in_progress', 'assigned_to' => $technician->id]);
        $this->postJson("/api/tickets/{$invalid->id}/close", ['reason' => 'No válido.'])->assertConflict();
        $this->assertDatabaseMissing('ticket_events', ['ticket_id' => $invalid->id]);
    }

    public function test_workflow_reloads_persisted_state_instead_of_using_stale_route_model(): void
    {
        $technician = $this->user('technician');
        $ticket = $this->ticket(['status' => 'assigned', 'assigned_to' => $technician->id]);
        $stale = Ticket::findOrFail($ticket->id);
        Ticket::whereKey($ticket->id)->update(['status' => 'in_progress']);
        Sanctum::actingAs($technician);

        $this->postJson("/api/tickets/{$stale->id}/start")->assertConflict();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'in_progress']);
        $this->assertDatabaseMissing('ticket_events', ['ticket_id' => $ticket->id]);
    }

    public function test_ticket_change_rolls_back_when_audit_event_cannot_be_created(): void
    {
        $technician = $this->user('technician');
        $ticket = $this->ticket();
        $eventDispatcher = TicketEvent::getEventDispatcher();
        TicketEvent::setEventDispatcher(clone $eventDispatcher);
        TicketEvent::creating(static function (): void {
            throw new \RuntimeException('Fallo de auditoría simulado.');
        });
        Sanctum::actingAs($technician);

        $this->withoutExceptionHandling();

        try {
            $this->postJson("/api/tickets/{$ticket->id}/claim");
            $this->fail('La creación del evento debía fallar.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Fallo de auditoría simulado.', $exception->getMessage());
        } finally {
            TicketEvent::setEventDispatcher($eventDispatcher);
        }

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'status' => 'new',
            'assigned_to' => null,
        ]);
        $this->assertDatabaseMissing('ticket_events', ['ticket_id' => $ticket->id]);
    }

    private function user(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    private function ticket(array $attributes = []): Ticket
    {
        static $sequence = 0;

        return Ticket::create(array_merge([
            'code' => 'TCK-WF-'.++$sequence,
            'reporter_name' => 'Usuario de prueba',
            'title' => 'Incidente',
            'description' => 'Descripción',
            'category' => 'Soporte',
            'priority' => 'medium',
            'status' => 'new',
            'source' => 'internal',
            'reported_at' => now(),
        ], $attributes));
    }

    private function resolution(): array
    {
        return [
            'diagnosis' => 'Falla de configuración.',
            'solution' => 'Configuración corregida.',
            'notes' => 'Validado con el usuario.',
        ];
    }
}
