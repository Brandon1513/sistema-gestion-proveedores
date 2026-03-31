<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnitsSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['name' => 'Kilogramo',  'abbreviation' => 'kg',     'sort_order' => 1],
            ['name' => 'Gramo',      'abbreviation' => 'g',      'sort_order' => 2],
            ['name' => 'Litro',      'abbreviation' => 'L',      'sort_order' => 3],
            ['name' => 'Mililitro',  'abbreviation' => 'ml',     'sort_order' => 4],
            ['name' => 'Pieza',      'abbreviation' => 'pza',    'sort_order' => 5],
            ['name' => 'Caja',       'abbreviation' => 'caja',   'sort_order' => 6],
            ['name' => 'Saco',       'abbreviation' => 'saco',   'sort_order' => 7],
            ['name' => 'Tambo',      'abbreviation' => 'tambo',  'sort_order' => 8],
        ];

        foreach ($units as $unit) {
            DB::table('units')->insertOrIgnore([
                ...$unit,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✅ Unidades de medida creadas: ' . count($units));
    }
}