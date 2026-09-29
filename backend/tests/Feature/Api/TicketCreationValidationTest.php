<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Asset;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketCreationValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('technician');
        Sanctum::actingAs($user);
    }

    public function test_valid_ticket_is_created_with_normalized_values_and_audit_event(): void
    {
        $response = $this->postJson('/api/tickets', [
            ...$this->validPayload(),
            'reporter_name' => "  María José D'Angelo  ",
            'reporter_email' => '  usuario@hospital.local  ',
            'reporter_phone' => '  +57 300 123 4567  ',
            'title' => '  Falla de conexión a internet  ',
            'category' => '  Red y conectividad  ',
            'priority' => 'critical',
        ])->assertCreated();

        $this->assertDatabaseHas('tickets', [
            'id' => $response->json('ticket.id'),
            'reporter_name' => "María José D'Angelo",
            'reporter_email' => 'usuario@hospital.local',
            'reporter_phone' => '+57 300 123 4567',
            'title' => 'Falla de conexión a internet',
            'category' => 'Red y conectividad',
            'priority' => 'critical',
        ]);
        $this->assertDatabaseCount('ticket_events', 1);
    }

    public function test_invalid_fields_are_rejected_without_partial_records(): void
    {
        $invalidCases = [
            'title empty' => ['title', ''],
            'title whitespace' => ['title', '   '],
            'title short' => ['title', 'PC'],
            'title long' => ['title', str_repeat('á', 151)],
            'description empty' => ['description', ''],
            'description whitespace' => ['description', " \t "],
            'description short' => ['description', 'error'],
            'description long' => ['description', str_repeat('ñ', 5001)],
            'name empty' => ['reporter_name', ''],
            'name whitespace' => ['reporter_name', '   '],
            'name numeric' => ['reporter_name', '123456'],
            'name symbols' => ['reporter_name', "-- ''"],
            'email invalid' => ['reporter_email', 'asdasdasd'],
            'phone letters' => ['reporter_phone', '300ABC123'],
            'phone symbols' => ['reporter_phone', '!!!'],
            'phone short' => ['reporter_phone', '123'],
            'phone too many digits' => ['reporter_phone', '1234567890123456'],
            'asset missing' => ['asset_id', 999999],
            'priority invalid' => ['priority', 'urgent'],
            'category whitespace' => ['category', '   '],
            'category long' => ['category', str_repeat('x', 101)],
        ];

        foreach ($invalidCases as $case => [$field, $value]) {
            $payload = $this->validPayload();
            $payload[$field] = $value;

            $this->postJson('/api/tickets', $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
            $this->assertDatabaseCount('tickets', 0, "A ticket was created for {$case}.");
            $this->assertDatabaseCount('ticket_events', 0, "An event was created for {$case}.");
        }
    }

    public function test_optional_contact_and_asset_fields_accept_empty_values(): void
    {
        $response = $this->postJson('/api/tickets', [
            ...$this->validPayload(),
            'reporter_email' => '   ',
            'reporter_phone' => '',
            'asset_id' => null,
        ])->assertCreated();

        $this->assertDatabaseHas('tickets', [
            'id' => $response->json('ticket.id'),
            'reporter_email' => null,
            'reporter_phone' => null,
            'asset_id' => null,
        ]);
    }

    public function test_supported_phone_formats_are_accepted(): void
    {
        foreach (['3001234567', '300 123 4567', '+57 300 123 4567', '(601) 1234567', '601-123-4567'] as $phone) {
            $this->postJson('/api/tickets', [
                ...$this->validPayload(),
                'reporter_phone' => $phone,
            ])->assertCreated();
        }

        $this->assertDatabaseCount('tickets', 5);
        $this->assertDatabaseCount('ticket_events', 5);
    }

    public function test_existing_asset_and_each_supported_priority_are_accepted(): void
    {
        $area = Area::query()->create(['name' => 'Sistemas', 'code' => 'SIS']);
        $asset = Asset::query()->create([
            'code' => 'PC-001',
            'name' => 'Equipo de prueba',
            'category' => 'Computador',
            'area_id' => $area->id,
            'public_token' => (string) Str::uuid(),
        ]);

        foreach (['low', 'medium', 'high', 'critical'] as $priority) {
            $this->postJson('/api/tickets', [
                ...$this->validPayload(),
                'asset_id' => $asset->id,
                'priority' => $priority,
            ])->assertCreated()->assertJsonPath('ticket.asset_id', $asset->id);
        }
    }

    private function validPayload(): array
    {
        return [
            'reporter_name' => 'Juan Pérez',
            'reporter_email' => 'usuario@hospital.local',
            'reporter_phone' => '3001234567',
            'title' => 'Falla de conexión a internet',
            'description' => 'El computador no tiene conexión a internet.',
            'category' => 'Redes',
            'priority' => 'medium',
            'asset_id' => null,
        ];
    }
}
