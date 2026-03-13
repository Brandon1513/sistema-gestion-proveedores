<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        $superAdmin = User::create([
            'name' => 'Super Administrador',
            'email' => 'admin@sgp.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $superAdmin->assignRole('super_admin');

        // Usuario de Compras
        $compras = User::create([
            'name' => 'Juan Pérez',
            'email' => 'compras@sgp.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $compras->assignRole('compras');

        // Usuario de Calidad
        $calidad = User::create([
            'name' => 'María García',
            'email' => 'calidad@sgp.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $calidad->assignRole('calidad');

        // Usuario Administrador
        $admin = User::create([
            'name' => 'Carlos López',
            'email' => 'administrador@sgp.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');
    }
}