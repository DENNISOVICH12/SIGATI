<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Asset;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

abstract class AssetApiTestCase extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as protected runRefreshDatabase;
    }

    public function refreshDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        $this->runRefreshDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function actingAsUserWith(string ...$permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
            $user->givePermissionTo($permission);
        }

        Sanctum::actingAs($user);

        return $user;
    }

    protected function area(array $attributes = []): Area
    {
        static $sequence = 0;
        $sequence++;

        return Area::create(array_merge([
            'name' => "Test Area {$sequence}",
            'code' => "AREA-{$sequence}",
            'active' => true,
        ], $attributes));
    }

    protected function location(Area $area, array $attributes = []): Location
    {
        static $sequence = 0;
        $sequence++;

        return Location::create(array_merge([
            'area_id' => $area->id,
            'name' => "Test Location {$sequence}",
            'code' => "LOC-{$sequence}",
            'active' => true,
        ], $attributes));
    }

    protected function asset(Area $area, ?Location $location = null, array $attributes = []): Asset
    {
        static $sequence = 0;
        $sequence++;

        return Asset::create(array_merge([
            'code' => "ASSET-{$sequence}",
            'name' => "Test Asset {$sequence}",
            'category' => 'Computer',
            'area_id' => $area->id,
            'location_id' => $location?->id,
            'responsible_name' => null,
            'status' => 'operational',
        ], $attributes));
    }

    protected function validAssetPayload(Area $area, ?Location $location = null, array $attributes = []): array
    {
        static $sequence = 0;
        $sequence++;

        return array_merge([
            'code' => "NEW-ASSET-{$sequence}",
            'name' => "New Asset {$sequence}",
            'category' => 'Computer',
            'area_id' => $area->id,
            'location_id' => $location?->id,
        ], $attributes);
    }
}
