<?php

namespace Tests\Feature\Api;

use App\Models\Asset;

class PublicAssetQrTest extends AssetApiTestCase
{
    public function test_assets_receive_distinct_stable_public_identifiers(): void
    {
        $area = $this->area();
        $first = $this->asset($area);
        $second = $this->asset($area);

        $this->assertNotNull($first->public_token);
        $this->assertNotSame($first->public_token, $second->public_token);

        $token = $first->public_token;
        $first->update(['name' => 'Nombre actualizado', 'brand' => 'Nueva marca']);
        $this->assertSame($token, $first->refresh()->public_token);
    }

    public function test_public_endpoint_needs_no_auth_and_only_returns_current_allowlisted_data(): void
    {
        $area = $this->area(['name' => 'Sistemas']);
        $location = $this->location($area, ['name' => 'Oficina TI']);
        $asset = $this->asset($area, $location, [
            'code' => 'PC-SIS-001', 'name' => 'Estación clínica', 'category' => 'Computador',
            'ip_address' => '10.0.0.8', 'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'hostname' => 'interno-01', 'serial_number' => 'SECRETO',
            'responsible_name' => 'Persona interna', 'notes' => 'Nota privada',
        ]);

        $response = $this->getJson("/api/public/assets/{$asset->public_token}")
            ->assertOk()
            ->assertExactJson(['asset' => [
                'code' => 'PC-SIS-001', 'name' => 'Estación clínica', 'category' => 'Computador',
                'area' => 'Sistemas', 'location' => 'Oficina TI', 'status' => 'operational',
            ]]);

        foreach (['id', 'public_token', 'ip_address', 'mac_address', 'hostname', 'serial_number', 'responsible_name', 'notes'] as $sensitive) {
            $this->assertArrayNotHasKey($sensitive, $response->json('asset'));
        }

        $asset->update(['name' => 'Estación actualizada']);
        $this->getJson("/api/public/assets/{$asset->public_token}")
            ->assertJsonPath('asset.name', 'Estación actualizada');
    }

    public function test_invalid_identifiers_return_a_neutral_not_found_response(): void
    {
        $this->getJson('/api/public/assets/not-a-token')
            ->assertNotFound()
            ->assertJsonMissing(['id']);
    }

    public function test_public_report_uses_token_asset_and_creates_qr_timeline_event(): void
    {
        $area = $this->area();
        $asset = $this->asset($area);
        $other = $this->asset($area);

        $response = $this->postJson("/api/public/assets/{$asset->public_token}/reports", [
            'problem' => 'No enciende',
            'description' => 'El equipo no responde al botón de encendido.',
            'reporter_name' => '  María Pérez  ',
            'reporter_email' => '   ',
            'reporter_phone' => '  +57 300 123 4567 ',
            'asset_id' => $other->id,
            'source' => 'internal',
            'priority' => 'critical',
            'assigned_to' => 999,
            'status' => 'closed',
        ])->assertCreated()->assertJsonStructure(['message', 'ticket_code']);

        $this->assertDatabaseHas('tickets', [
            'code' => $response->json('ticket_code'), 'asset_id' => $asset->id,
            'source' => 'qr', 'priority' => 'medium', 'status' => 'new',
            'assigned_to' => null, 'reporter_name' => 'María Pérez',
            'reporter_email' => null, 'reporter_phone' => '+57 300 123 4567',
        ]);
        $this->assertDatabaseHas('ticket_events', [
            'event_type' => 'created', 'user_id' => null, 'new_status' => 'new',
        ]);
    }

    public function test_public_report_validation_and_rate_limit_prevent_abuse(): void
    {
        $asset = $this->asset($this->area());
        $url = "/api/public/assets/{$asset->public_token}/reports";

        $this->postJson($url, ['problem' => '', 'description' => 'corta', 'reporter_name' => '12'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['problem', 'description', 'reporter_name']);

        $payload = ['problem' => 'Pantalla', 'description' => 'La pantalla permanece completamente apagada.', 'reporter_name' => 'Ana Ruiz'];
        $this->postJson($url, $payload)->assertCreated();
        $this->postJson($url, $payload)->assertCreated();
        $this->postJson($url, $payload)->assertTooManyRequests();
    }
}
