<?php

namespace Tests\Feature\Api;

use App\Models\AssetHistory;
use Illuminate\Support\Carbon;

class AssetHistoryTest extends AssetApiTestCase
{
    public function test_user_with_view_permission_can_view_created_event_and_pagination_contract(): void
    {
        $this->actingAsUserWith('assets.view');
        $area = $this->area();
        $asset = $this->asset($area);
        $asset->history()->create([
            'action' => 'created',
            'description' => 'Activo registrado en SIGATI.',
            'new_values' => ['status' => 'operational'],
        ]);

        $this->getJson("/api/assets/{$asset->id}/history")
            ->assertOk()
            ->assertJsonPath('asset.id', $asset->id)
            ->assertJsonPath('history.current_page', 1)
            ->assertJsonPath('history.per_page', 20)
            ->assertJsonPath('history.total', 1)
            ->assertJsonPath('history.data.0.action', 'created');
    }

    public function test_user_without_view_permission_receives_forbidden(): void
    {
        $this->actingAsUserWith();
        $asset = $this->asset($this->area());

        $this->getJson("/api/assets/{$asset->id}/history")->assertForbidden();
    }

    public function test_unauthenticated_user_receives_unauthorized(): void
    {
        $asset = $this->asset($this->area());

        $this->getJson("/api/assets/{$asset->id}/history")->assertUnauthorized();
    }

    public function test_history_returns_newest_events_first(): void
    {
        $this->actingAsUserWith('assets.view');
        $asset = $this->asset($this->area());

        Carbon::setTestNow('2026-09-28 10:00:00');
        $asset->history()->create(['action' => 'created', 'description' => 'Creado']);
        Carbon::setTestNow('2026-09-28 11:00:00');
        $asset->history()->create(['action' => 'updated', 'description' => 'Actualizado']);
        Carbon::setTestNow();

        $this->getJson("/api/assets/{$asset->id}/history")
            ->assertOk()
            ->assertJsonPath('history.data.0.action', 'updated')
            ->assertJsonPath('history.data.1.action', 'created');
    }

    public function test_transfer_history_exposes_actor_reason_old_and_new_values_once(): void
    {
        $user = $this->actingAsUserWith('assets.transfer', 'assets.view');
        $area = $this->area();
        $asset = $this->asset($area, null, ['responsible_name' => 'Juan']);

        $this->postJson("/api/assets/{$asset->id}/transfer", [
            'area_id' => $area->id,
            'location_id' => null,
            'responsible_name' => 'Pedro',
            'reason' => 'Rotación interna',
        ])->assertOk();

        $this->assertSame(1, AssetHistory::where('asset_id', $asset->id)->where('action', 'transferred')->count());
        $this->getJson("/api/assets/{$asset->id}/history")
            ->assertOk()
            ->assertJsonPath('history.data.0.action', 'transferred')
            ->assertJsonPath('history.data.0.user.id', $user->id)
            ->assertJsonPath('history.data.0.reason', 'Rotación interna')
            ->assertJsonPath('history.data.0.old_values.responsible_name', 'Juan')
            ->assertJsonPath('history.data.0.new_values.responsible_name', 'Pedro');
    }

    public function test_status_history_exposes_actor_reason_old_and_new_values_once(): void
    {
        $user = $this->actingAsUserWith('assets.change_status', 'assets.view');
        $asset = $this->asset($this->area());

        $this->patchJson("/api/assets/{$asset->id}/status", [
            'status' => 'maintenance',
            'reason' => 'Mantenimiento programado',
        ])->assertOk();

        $this->assertSame(1, AssetHistory::where('asset_id', $asset->id)->where('action', 'status_changed')->count());
        $this->getJson("/api/assets/{$asset->id}/history")
            ->assertOk()
            ->assertJsonPath('history.data.0.action', 'status_changed')
            ->assertJsonPath('history.data.0.user.id', $user->id)
            ->assertJsonPath('history.data.0.reason', 'Mantenimiento programado')
            ->assertJsonPath('history.data.0.old_values.status', 'operational')
            ->assertJsonPath('history.data.0.new_values.status', 'maintenance');
    }

    public function test_asset_creation_is_visible_as_exactly_one_created_event(): void
    {
        $this->actingAsUserWith('assets.create', 'assets.view');
        $area = $this->area();
        $response = $this->postJson('/api/assets', $this->validAssetPayload($area))->assertCreated();
        $assetId = $response->json('asset.id');

        $this->assertSame(1, AssetHistory::where('asset_id', $assetId)->where('action', 'created')->count());
        $this->getJson("/api/assets/{$assetId}/history")
            ->assertOk()
            ->assertJsonPath('history.data.0.action', 'created');
    }

    public function test_history_paginates_twenty_events_per_page(): void
    {
        $this->actingAsUserWith('assets.view');
        $asset = $this->asset($this->area());

        for ($index = 1; $index <= 21; $index++) {
            $asset->history()->create([
                'action' => 'updated',
                'description' => "Evento {$index}",
            ]);
        }

        $this->getJson("/api/assets/{$asset->id}/history?page=2")
            ->assertOk()
            ->assertJsonPath('history.current_page', 2)
            ->assertJsonPath('history.per_page', 20)
            ->assertJsonPath('history.total', 21)
            ->assertJsonCount(1, 'history.data');
    }
}
