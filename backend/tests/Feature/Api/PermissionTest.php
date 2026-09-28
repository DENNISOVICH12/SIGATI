<?php

namespace Tests\Feature\Api;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_seeder_builds_the_expected_rbac_matrix(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $engineer = Role::findByName('engineer');
        $technician = Role::findByName('technician');

        $this->assertEqualsCanonicalizing(
            RolePermissionSeeder::PERMISSIONS,
            $engineer->permissions->pluck('name')->all()
        );
        $this->assertEqualsCanonicalizing(
            RolePermissionSeeder::TECHNICIAN_PERMISSIONS,
            $technician->permissions->pluck('name')->all()
        );

        foreach (['tickets.close', 'tickets.assign'] as $permission) {
            $this->assertTrue($engineer->hasPermissionTo($permission));
            $this->assertFalse($technician->hasPermissionTo($permission));
        }

        foreach (['tickets.view', 'tickets.create', 'tickets.claim', 'tickets.update'] as $permission) {
            $this->assertTrue($technician->hasPermissionTo($permission));
        }

        foreach (['assets.view', 'assets.create', 'assets.update', 'assets.transfer', 'assets.change_status'] as $permission) {
            $this->assertTrue($engineer->hasPermissionTo($permission));
            $this->assertTrue($technician->hasPermissionTo($permission));
        }

        $this->assertTrue($engineer->hasPermissionTo('assets.decommission'));
        $this->assertFalse($technician->hasPermissionTo('assets.decommission'));
    }
}
