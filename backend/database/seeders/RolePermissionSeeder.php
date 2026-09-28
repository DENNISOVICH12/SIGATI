<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiar caché de permisos de Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        /*
        |--------------------------------------------------------------------------
        | Permisos del sistema SIGATI
        |--------------------------------------------------------------------------
        */

        $permissions = [

            // Activos
            'assets.view',
            'assets.create',
            'assets.update',
            'assets.transfer',
            'assets.change_status',
            'assets.decommission',

            // Tickets
            'tickets.view',
            'tickets.create',
            'tickets.claim',
            'tickets.assign',
            'tickets.update',
            'tickets.close',

            // Mantenimiento
            'maintenance.view',
            'maintenance.create',
            'maintenance.update',

            // Solicitudes de baja
            'disposals.view',
            'disposals.request',
            'disposals.approve',
            'disposals.reject',

            // Licencias de software
            'licenses.view',
            'licenses.create',
            'licenses.update',
            'licenses.assign',
            'licenses.renew',

            // Usuarios
            'users.view',
            'users.create',
            'users.update',
            'users.deactivate',
            'users.assign_roles',

            // Reportes
            'reports.view',
            'reports.export',

            // Auditoría
            'audit.view',

            // Configuración
            'settings.manage',
        ];

        foreach ($permissions as $permission) {
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

        $engineer->syncPermissions(Permission::all());

        /*
        |--------------------------------------------------------------------------
        | Técnico
        |--------------------------------------------------------------------------
        | Puede realizar las operaciones técnicas del día a día,
        | pero no administrar usuarios, aprobar bajas ni modificar
        | la configuración general del sistema.
        */

        $technician->syncPermissions([

            // Activos
            'assets.view',
            'assets.create',
            'assets.update',
            'assets.transfer',
            'assets.change_status',

            // Tickets
            'tickets.view',
            'tickets.create',
            'tickets.claim',
            'tickets.update',
            'tickets.close',

            // Mantenimiento
            'maintenance.view',
            'maintenance.create',
            'maintenance.update',

            // Bajas
            'disposals.view',
            'disposals.request',

            // Licencias
            'licenses.view',

            // Reportes
            'reports.view',
        ]);

        // Limpiar nuevamente la caché
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}