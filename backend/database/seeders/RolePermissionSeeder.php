<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        'assets.view', 'assets.create', 'assets.update', 'assets.transfer',
        'assets.change_status', 'assets.decommission',
        'tickets.view', 'tickets.create', 'tickets.claim', 'tickets.assign',
        'tickets.update', 'tickets.close',
        'maintenance.view', 'maintenance.create', 'maintenance.update',
        'disposals.view', 'disposals.request', 'disposals.approve', 'disposals.reject',
        'licenses.view', 'licenses.create', 'licenses.update', 'licenses.assign', 'licenses.renew',
        'users.view', 'users.create', 'users.update', 'users.deactivate', 'users.assign_roles',
        'reports.view', 'reports.export', 'audit.view', 'settings.manage',
    ];

    public const TECHNICIAN_PERMISSIONS = [
        'assets.view', 'assets.create', 'assets.update', 'assets.transfer', 'assets.change_status',
        'tickets.view', 'tickets.create', 'tickets.claim', 'tickets.update',
        'maintenance.view', 'maintenance.create',
        'disposals.view', 'disposals.request', 'licenses.view', 'reports.view',
    ];

    public function run(): void
    {
        // Limpiar caché de permisos de Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        /*
        |--------------------------------------------------------------------------
        | Permisos del sistema SIGATI
        |--------------------------------------------------------------------------
        */

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Roles
        |--------------------------------------------------------------------------
        */

        $engineer = Role::firstOrCreate([
            'name' => 'engineer',
            'guard_name' => 'web',
        ]);

        $technician = Role::firstOrCreate([
            'name' => 'technician',
            'guard_name' => 'web',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Ingeniero
        |--------------------------------------------------------------------------
        | El ingeniero tiene control administrativo completo.
        */

        $engineer->syncPermissions(array_values(array_diff(
            self::PERMISSIONS,
            ['tickets.claim', 'maintenance.update']
        )));

        /*
        |--------------------------------------------------------------------------
        | Técnico
        |--------------------------------------------------------------------------
        | Puede realizar las operaciones técnicas del día a día,
        | pero no administrar usuarios, aprobar bajas ni modificar
        | la configuración general del sistema.
        */

        $technician->syncPermissions(self::TECHNICIAN_PERMISSIONS);

        // Limpiar nuevamente la caché
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
