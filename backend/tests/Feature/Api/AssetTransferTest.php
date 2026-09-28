<?php

namespace Tests\Feature\Api;

use App\Models\AssetHistory;

class AssetTransferTest extends AssetApiTestCase
{
    public function test_transfer_can_change_only_responsible_and_records_traceability(): void
    {
        $user = $this->actingAsUserWith('assets.transfer');
        $area = $this->area();
        $location = $this->location($area);
        $asset = $this->asset($area, $location, ['responsible_name' => 'Juan']);

        $this->postJson("/api/assets/{$asset->id}/transfer", [
            'area_id' => $area->id,
            'location_id' => $location->id,
            'responsible_name' => '  Pedro  ',
            'reason' => 'Cambio de responsable',
        ])->assertOk()->assertJsonPath('asset.responsible_name', 'Pedro');

        $event = AssetHistory::where('asset_id', $asset->id)->where('action', 'transferred')->sole();
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame('Cambio de responsable', $event->reason);
        $this->assertSame('Juan', $event->old_values['responsible_name']);
        $this->assertSame('Pedro', $event->new_values['responsible_name']);
    }

    public function test_transfer_can_change_location_within_same_area(): void
    {
        $this->actingAsUserWith('assets.transfer');
        $area = $this->area();
        $oldLocation = $this->location($area);
        $newLocation = $this->location($area);
        $asset = $this->asset($area, $oldLocation);

        $this->postJson("/api/assets/{$asset->id}/transfer", $this->transferPayload($area->id, $newLocation->id))
            ->assertOk()
            ->assertJsonPath('asset.location_id', $newLocation->id);

        $this->assertSame(1, $asset->history()->where('action', 'transferred')->count());
    }

    public function test_transfer_can_change_area_and_location(): void
    {
        $this->actingAsUserWith('assets.transfer');
        $oldArea = $this->area();
        $asset = $this->asset($oldArea, $this->location($oldArea));
        $newArea = $this->area();
        $newLocation = $this->location($newArea);

        $this->postJson("/api/assets/{$asset->id}/transfer", $this->transferPayload($newArea->id, $newLocation->id))
            ->assertOk()
            ->assertJsonPath('asset.area_id', $newArea->id)
            ->assertJsonPath('asset.location_id', $newLocation->id);
    }

    public function test_no_change_and_reason_only_transfers_are_rejected_without_side_effects(): void
    {
        $this->actingAsUserWith('assets.transfer');
        $area = $this->area();
        $location = $this->location($area);
        $asset = $this->asset($area, $location, ['responsible_name' => 'Juan']);

        foreach (['Juan', '  Juan  '] as $responsible) {
            $this->postJson("/api/assets/{$asset->id}/transfer", $this->transferPayload(
                $area->id,
                $location->id,
                $responsible
            ))->assertUnprocessable()->assertJsonValidationErrors('transfer');
        }

        $this->assertAssetUnchangedAndNoTransfer($asset->id, $area->id, $location->id, 'Juan');
    }

    public function test_null_to_empty_or_whitespace_responsible_is_not_a_change(): void
    {
        $this->actingAsUserWith('assets.transfer');
        $area = $this->area();
        $location = $this->location($area);
        $asset = $this->asset($area, $location);

        foreach (['', '   '] as $responsible) {
            $this->postJson("/api/assets/{$asset->id}/transfer", $this->transferPayload(
                $area->id,
                $location->id,
                $responsible
            ))->assertUnprocessable();
        }

        $this->assertAssetUnchangedAndNoTransfer($asset->id, $area->id, $location->id, null);
    }

    public function test_transfer_rejects_location_from_another_area(): void
    {
        $this->actingAsUserWith('assets.transfer');
        $area = $this->area();
        $location = $this->location($area);
        $asset = $this->asset($area, $location);
        $foreignLocation = $this->location($this->area());

        $this->postJson("/api/assets/{$asset->id}/transfer", $this->transferPayload($area->id, $foreignLocation->id))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('location_id');

        $this->assertAssetUnchangedAndNoTransfer($asset->id, $area->id, $location->id, null);
    }

    public function test_transfer_rejects_inactive_area(): void
    {
        $this->actingAsUserWith('assets.transfer');
        $area = $this->area();
        $asset = $this->asset($area);
        $inactiveArea = $this->area(['active' => false]);

        $this->postJson("/api/assets/{$asset->id}/transfer", $this->transferPayload($inactiveArea->id))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('area_id');

        $this->assertAssetUnchangedAndNoTransfer($asset->id, $area->id, null, null);
    }

    public function test_transfer_rejects_inactive_location(): void
    {
        $this->actingAsUserWith('assets.transfer');
        $area = $this->area();
        $asset = $this->asset($area);
        $inactiveLocation = $this->location($area, ['active' => false]);

        $this->postJson("/api/assets/{$asset->id}/transfer", $this->transferPayload($area->id, $inactiveLocation->id))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('location_id');

        $this->assertAssetUnchangedAndNoTransfer($asset->id, $area->id, null, null);
    }

    public function test_transfer_requires_non_whitespace_reason(): void
    {
        $this->actingAsUserWith('assets.transfer');
        $area = $this->area();
        $asset = $this->asset($area);

        foreach ([null, '   '] as $reason) {
            $payload = $this->transferPayload($area->id, null, 'Pedro');
            if ($reason === null) {
                unset($payload['reason']);
            } else {
                $payload['reason'] = $reason;
            }

            $this->postJson("/api/assets/{$asset->id}/transfer", $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('reason');
        }

        $this->assertAssetUnchangedAndNoTransfer($asset->id, $area->id, null, null);
    }

    public function test_authenticated_user_without_transfer_permission_receives_forbidden(): void
    {
        $this->actingAsUserWith();
        $area = $this->area();
        $asset = $this->asset($area);

        $this->postJson("/api/assets/{$asset->id}/transfer", $this->transferPayload($area->id, null, 'Pedro'))
            ->assertForbidden();
    }

    public function test_unauthenticated_user_receives_unauthorized(): void
    {
        $area = $this->area();
        $asset = $this->asset($area);

        $this->postJson("/api/assets/{$asset->id}/transfer", $this->transferPayload($area->id, null, 'Pedro'))
            ->assertUnauthorized();
    }

    public function test_transfer_reads_current_persisted_state_instead_of_a_stale_model(): void
    {
        $this->actingAsUserWith('assets.transfer');
        $area = $this->area();
        $asset = $this->asset($area, null, ['responsible_name' => 'Juan']);

        $asset->newQuery()->whereKey($asset->id)->update(['responsible_name' => 'Pedro']);

        $this->postJson("/api/assets/{$asset->id}/transfer", $this->transferPayload($area->id, null, 'Pedro'))
            ->assertUnprocessable();

        $this->assertSame(0, AssetHistory::where('asset_id', $asset->id)->where('action', 'transferred')->count());
    }

    private function transferPayload(int $areaId, ?int $locationId = null, ?string $responsible = null): array
    {
        return [
            'area_id' => $areaId,
            'location_id' => $locationId,
            'responsible_name' => $responsible,
            'reason' => 'Necesidad operativa',
        ];
    }

    private function assertAssetUnchangedAndNoTransfer(
        int $assetId,
        int $areaId,
        ?int $locationId,
        ?string $responsible
    ): void {
        $this->assertDatabaseHas('assets', [
            'id' => $assetId,
            'area_id' => $areaId,
            'location_id' => $locationId,
            'responsible_name' => $responsible,
        ]);
        $this->assertSame(0, AssetHistory::where('asset_id', $assetId)->where('action', 'transferred')->count());
    }
}
