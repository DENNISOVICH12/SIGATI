<?php

namespace Tests\Feature\Api;

use App\Models\AssetHistory;

class AssetStatusTest extends AssetApiTestCase
{
    public function test_operational_asset_can_change_to_each_other_valid_status(): void
    {
        foreach (['pending_review', 'faulty', 'maintenance'] as $status) {
            $this->actingAsUserWith('assets.change_status');
            $asset = $this->asset($this->area());

            $this->patchJson("/api/assets/{$asset->id}/status", [
                'status' => $status,
                'reason' => 'Diagnóstico técnico',
            ])->assertOk()->assertJsonPath('asset.status', $status);

            $this->assertSame(1, $asset->history()->where('action', 'status_changed')->count());
        }
    }

    public function test_valid_status_change_records_actor_reason_and_old_and_new_status(): void
    {
        $user = $this->actingAsUserWith('assets.change_status');
        $asset = $this->asset($this->area());

        $this->patchJson("/api/assets/{$asset->id}/status", [
            'status' => 'faulty',
            'reason' => 'Falla verificada',
        ])->assertOk();

        $event = AssetHistory::where('asset_id', $asset->id)->where('action', 'status_changed')->sole();
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame('Falla verificada', $event->reason);
        $this->assertSame(['status' => 'operational'], $event->old_values);
        $this->assertSame(['status' => 'faulty'], $event->new_values);
    }

    public function test_same_status_is_rejected_without_changing_asset_or_history(): void
    {
        $this->actingAsUserWith('assets.change_status');
        $asset = $this->asset($this->area());

        $this->patchJson("/api/assets/{$asset->id}/status", [
            'status' => 'operational',
            'reason' => 'Sin cambio real',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertUnchangedWithoutStatusEvent($asset->id);
    }

    public function test_invalid_status_is_rejected_without_changing_asset_or_history(): void
    {
        $this->actingAsUserWith('assets.change_status');
        $asset = $this->asset($this->area());

        $this->patchJson("/api/assets/{$asset->id}/status", [
            'status' => 'retired',
            'reason' => 'Estado no permitido',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertUnchangedWithoutStatusEvent($asset->id);
    }

    public function test_status_change_requires_non_whitespace_reason(): void
    {
        $this->actingAsUserWith('assets.change_status');
        $asset = $this->asset($this->area());

        foreach ([null, '   '] as $reason) {
            $payload = ['status' => 'faulty'];
            if ($reason !== null) {
                $payload['reason'] = $reason;
            }
            $this->patchJson("/api/assets/{$asset->id}/status", $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('reason');
        }

        $this->assertUnchangedWithoutStatusEvent($asset->id);
    }

    public function test_authenticated_user_without_change_status_permission_receives_forbidden(): void
    {
        $this->actingAsUserWith();
        $asset = $this->asset($this->area());

        $this->patchJson("/api/assets/{$asset->id}/status", [
            'status' => 'faulty',
            'reason' => 'Diagnóstico',
        ])->assertForbidden();
    }

    public function test_unauthenticated_user_receives_unauthorized(): void
    {
        $asset = $this->asset($this->area());

        $this->patchJson("/api/assets/{$asset->id}/status", [
            'status' => 'faulty',
            'reason' => 'Diagnóstico',
        ])->assertUnauthorized();
    }

    public function test_status_change_reads_current_persisted_state_instead_of_a_stale_model(): void
    {
        $this->actingAsUserWith('assets.change_status');
        $asset = $this->asset($this->area());
        $asset->newQuery()->whereKey($asset->id)->update(['status' => 'faulty']);

        $this->patchJson("/api/assets/{$asset->id}/status", [
            'status' => 'faulty',
            'reason' => 'Ya aplicado por otro proceso',
        ])->assertUnprocessable();

        $this->assertSame(0, AssetHistory::where('asset_id', $asset->id)->where('action', 'status_changed')->count());
    }

    private function assertUnchangedWithoutStatusEvent(int $assetId): void
    {
        $this->assertDatabaseHas('assets', ['id' => $assetId, 'status' => 'operational']);
        $this->assertSame(0, AssetHistory::where('asset_id', $assetId)->where('action', 'status_changed')->count());
    }
}
