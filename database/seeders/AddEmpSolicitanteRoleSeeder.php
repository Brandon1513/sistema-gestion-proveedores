<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AddEmpSolicitanteRoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'emp_solicitante', 'guard_name' => 'web']);

        // ⚠️ Si tu sistema usa permisos granulares de Spatie (más allá del
        // slug de rol), agrega aquí los permisos específicos que necesite
        // este rol. Por ahora el control de acceso se hace por rol
        // directamente en las rutas (middleware 'role:emp_solicitante'),
        // igual que el resto de roles del sistema.
    }
}