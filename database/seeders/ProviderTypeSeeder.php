<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProviderTypeSeeder extends Seeder
{
    public function run(): void
    {
        $providerTypes = [
            [
                'code' => 'mp_me',
                'name' => 'Materias Primas y Material de Empaque',
                'form_code' => 'F-COM-02',
                'description' => 'Proveedores de materias primas y materiales de empaque',
                'is_active' => true,
            ],
            [
                'code' => 'residuos',
                'name' => 'Proveedores de Residuos',
                'form_code' => 'F-COM-12',
                'description' => 'Empresas autorizadas para manejo de residuos',
                'is_active' => true,
            ],
            [
                'code' => 'laboratorios',
                'name' => 'Laboratorios y Calibraciones',
                'form_code' => 'F-COM-12',
                'description' => 'Servicios de laboratorio y calibración de equipos',
                'is_active' => true,
            ],
            [
                'code' => 'sustancias_quimicas',
                'name' => 'Sustancias Químicas',
                'form_code' => 'F-COM-12',
                'description' => 'Proveedores de sustancias químicas',
                'is_active' => true,
            ],
            [
                'code' => 'insumos',
                'name' => 'Insumos Generales',
                'form_code' => 'F-COM-12',
                'description' => 'Uniformes, papelería y otros insumos',
                'is_active' => true,
            ],
        ];

        foreach ($providerTypes as $type) {
            DB::table('provider_types')->insert(array_merge($type, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}