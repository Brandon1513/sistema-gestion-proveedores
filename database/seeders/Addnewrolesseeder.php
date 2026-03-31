<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * AddNewRolesSeeder
 *
 * Agrega los roles:
 *  - seguridad          → confirmar entrada de proveedores
 *  - ingeniero_alimentos → registrar recepción de productos
 *
 * Uso: php artisan db:seed --class=AddNewRolesSeeder
 */
class AddNewRolesSeeder extends Seeder
{
    public function run(): void
    {
        // Crear permisos específicos
        $permissions = [
            'appointments.view.today',      // ver citas del día
            'appointments.view.week',       // ver citas de la semana
            'appointments.confirm.entry',   // confirmar entrada (Seguridad)
            'appointments.register.reception', // registrar recepción (Ing. Alimentos)
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // ─── Rol Seguridad ────────────────────────────────────────────────────
        $seguridad = Role::firstOrCreate(['name' => 'seguridad', 'guard_name' => 'web']);
        $seguridad->syncPermissions([
            'appointments.view.today',
            'appointments.view.week',
            'appointments.confirm.entry',
        ]);

        // ─── Rol Ingeniero de Alimentos ───────────────────────────────────────
        $ingeniero = Role::firstOrCreate(['name' => 'ingeniero_alimentos', 'guard_name' => 'web']);
        $ingeniero->syncPermissions([
            'appointments.view.today',
            'appointments.register.reception',
        ]);

        $this->command->info('✅ Roles "seguridad" e "ingeniero_alimentos" creados correctamente');
        $this->command->info('   Permisos asignados: ' . implode(', ', $permissions));
    }
}