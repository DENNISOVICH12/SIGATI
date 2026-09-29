<?php

namespace Tests\Feature\Api;

use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Sanctum::actingAs($this->userWithRole('technician'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_ticket_creation_rejects_required_fields_containing_only_whitespace(): void
    {
        foreach (['reporter_name', 'title', 'description', 'category'] as $field) {
            $payload = $this->validTicketPayload();
            $payload[$field] = " \t\n ";

            $this->postJson('/api/tickets', $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_ticket_creation_trims_text_without_changing_optional_nulls(): void
    {
        $response = $this->postJson('/api/tickets', [
            ...$this->validTicketPayload(),
            'reporter_name' => '  Persona solicitante  ',
            'title' => '  Falla de red  ',
            'description' => '  No hay conectividad.  ',
            'category' => '  Redes  ',
            'reporter_email' => null,
            'reporter_phone' => null,
        ])->assertCreated();

        $this->assertDatabaseHas('tickets', [
            'id' => $response->json('ticket.id'),
            'reporter_name' => 'Persona solicitante',
            'title' => 'Falla de red',
            'description' => 'No hay conectividad.',
            'category' => 'Redes',
            'reporter_email' => null,
            'reporter_phone' => null,
        ]);
    }

    public function test_resolution_warning_is_consistent_in_accessor_filter_and_stats(): void
    {
        Carbon::setTestNow('2026-09-29 12:00:00');

        $ticket = $this->ticket([
            'response_due_at' => now()->addMinutes(30),
            'resolution_due_at' => now()->addMinutes(45),
        ]);

        $this->assertSame('warning', $ticket->resolution_sla_status);

        $this->getJson('/api/tickets?resolution_sla=warning')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ticket->id);

        $this->getJson('/api/tickets?resolution_sla=on_time')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/tickets/stats')
            ->assertOk()
            ->assertJsonPath('resolution_sla.warning', 1)
            ->assertJsonPath('resolution_sla.on_time', 0)
            ->assertJsonPath('sla.warning', 1);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function validTicketPayload(): array
    {
        return [
            'reporter_name' => 'Persona solicitante',
            'title' => 'Falla de red',
            'description' => 'No hay conectividad.',
            'category' => 'Redes',
        ];
    }

    private function ticket(array $attributes = []): Ticket
    {
        return Ticket::create(array_merge([
            'code' => 'TCK-SLA-TEST',
            ...$this->validTicketPayload(),
            'priority' => 'medium',
            'status' => 'new',
            'source' => 'internal',
            'reported_at' => now(),
        ], $attributes));
    }
}
