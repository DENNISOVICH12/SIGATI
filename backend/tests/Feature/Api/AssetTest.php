<?php

namespace Tests\Feature\Api;

use App\Models\AssetHistory;

class AssetTest extends AssetApiTestCase
{
    public function test_authorized_user_can_create_asset_with_normalized_responsible_and_one_created_event(): void
    {
        $user = $this->actingAsUserWith('assets.create');
        $area = $this->area();
        $location = $this->location($area);

        $response = $this->postJson('/api/assets', $this->validAssetPayload($area, $location, [
            'responsible_name' => '  Juan Pérez  ',
        ]));

        $response->assertCreated()->assertJsonPath('asset.responsible_name', 'Juan Pérez');
        $assetId = $response->json('asset.id');
        $this->assertDatabaseHas('assets', ['id' => $assetId, 'responsible_name' => 'Juan Pérez']);
        $this->assertSame(1, AssetHistory::where('asset_id', $assetId)->count());
        $this->assertDatabaseHas('asset_history', [
            'asset_id' => $assetId,
            'user_id' => $user->id,
            'action' => 'created',
        ]);
    }

    public function test_empty_and_whitespace_responsible_are_persisted_as_null(): void
    {
        $this->actingAsUserWith('assets.create');
        $area = $this->area();

        foreach (['', '   '] as $responsible) {
            $response = $this->postJson('/api/assets', $this->validAssetPayload($area, null, [
                'responsible_name' => $responsible,
            ]));

            $response->assertCreated();
            $this->assertDatabaseHas('assets', [
                'id' => $response->json('asset.id'),
                'responsible_name' => null,
            ]);
        }
    }

    public function test_creation_rejects_inactive_area(): void
    {
        $this->actingAsUserWith('assets.create');
        $area = $this->area(['active' => false]);

        $this->postJson('/api/assets', $this->validAssetPayload($area))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('area_id');
    }

    public function test_creation_rejects_inactive_location(): void
    {
        $this->actingAsUserWith('assets.create');
        $area = $this->area();
        $location = $this->location($area, ['active' => false]);

        $this->postJson('/api/assets', $this->validAssetPayload($area, $location))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('location_id');
    }

    public function test_creation_rejects_location_from_another_area(): void
    {
        $this->actingAsUserWith('assets.create');
        $area = $this->area();
        $otherLocation = $this->location($this->area());

        $payload = $this->validAssetPayload($area, $otherLocation);

        $this->postJson('/api/assets', $payload)
            ->assertUnprocessable();

        $this->assertDatabaseMissing('assets', ['code' => $payload['code']]);
    }

    public function test_authenticated_user_without_create_permission_receives_forbidden(): void
    {
        $this->actingAsUserWith();
        $area = $this->area();

        $this->postJson('/api/assets', $this->validAssetPayload($area))->assertForbidden();
    }

    public function test_valid_general_patch_updates_asset_and_creates_one_updated_event(): void
    {
        $user = $this->actingAsUserWith('assets.update');
        $area = $this->area();
        $asset = $this->asset($area);

        $this->patchJson("/api/assets/{$asset->id}", ['name' => 'Updated name'])
            ->assertOk()
            ->assertJsonPath('asset.name', 'Updated name');

        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'name' => 'Updated name']);
        $this->assertDatabaseHas('asset_history', [
            'asset_id' => $asset->id,
            'user_id' => $user->id,
            'action' => 'updated',
        ]);
        $this->assertSame(1, AssetHistory::where('asset_id', $asset->id)->where('action', 'updated')->count());
    }

    public function test_patch_rejects_responsible_name_regardless_of_value(): void
    {
        $this->actingAsUserWith('assets.update');
        $asset = $this->asset($this->area(), null, ['responsible_name' => 'Juan']);

        foreach (['Pedro', null, ''] as $value) {
            $this->patchJson("/api/assets/{$asset->id}", ['responsible_name' => $value])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('responsible_name');
        }

        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'responsible_name' => 'Juan']);
        $this->assertSame(0, AssetHistory::where('asset_id', $asset->id)->count());
    }

    public function test_patch_without_changes_does_not_create_updated_event(): void
    {
        $this->actingAsUserWith('assets.update');
        $asset = $this->asset($this->area());

        $this->patchJson("/api/assets/{$asset->id}", ['name' => $asset->name])->assertOk();

        $this->assertSame(0, AssetHistory::where('asset_id', $asset->id)->where('action', 'updated')->count());
    }
}
