<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Crear permisos
        $permissions = [
            // Proveedores
            'providers.view',
            'providers.create',
            'providers.edit',
            'providers.delete',
            'providers.invite',
            
            // Documentos
            'documents.view',
            'documents.upload',
            'documents.validate',
            'documents.reject',
            'documents.delete',
            
            // Reportes
            'reports.view',
            'reports.export',
            
            // Configuración
            'settings.manage',
            'users.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Crear roles y asignar permisos

        // 1. Super Admin
        $superAdmin = Role::create(['name' => 'super_admin']);
        $superAdmin->givePermissionTo(Permission::all());

        // 2. Departamento de Compras
        $compras = Role::create(['name' => 'compras']);
        $compras->givePermissionTo([
            'providers.view',
            'providers.create',
            'providers.invite',
            'documents.view',
            'reports.view',
            'reports.export',
        ]);

        // 3. Departamento de Calidad
        $calidad = Role::create(['name' => 'calidad']);
        $calidad->givePermissionTo([
            'providers.view',
            'documents.view',
            'documents.validate',
            'documents.reject',
            'reports.view',
        ]);

        // 4. Proveedor
        $proveedor = Role::create(['name' => 'proveedor']);
        $proveedor->givePermissionTo([
            'documents.view',
            'documents.upload',
        ]);

        // 5. Administrador
        $admin = Role::create(['name' => 'admin']);
        $admin->givePermissionTo([
            'providers.view',
            'providers.create',
            'providers.edit',
            'providers.delete',
            'providers.invite',
            'documents.view',
            'documents.validate',
            'documents.reject',
            'documents.delete',
            'reports.view',
            'reports.export',
            'settings.manage',
            'users.manage',
        ]);
    }
}   