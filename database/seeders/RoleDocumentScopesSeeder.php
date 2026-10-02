<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleDocumentScopesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('role_document_scopes')->updateOrInsert(
            ['role' => 'cuentas_por_pagar', 'category' => 'fiscal'],
            ['updated_at' => now(), 'created_at' => now()]
        );
    }
}