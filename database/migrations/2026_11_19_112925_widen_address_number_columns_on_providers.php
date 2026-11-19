<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Amplía número exterior/interior en providers.
     * Caso real: "PISO 18 OFICINA 1801-A" (22 chars) rompía el registro con varchar(20).
     * Se usa ALTER ... TYPE directo para cambiar SOLO la longitud y no tocar
     * nullable/default (->change() en Laravel 12 obliga a redeclarar todo).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE providers ALTER COLUMN exterior_number TYPE varchar(50)');
        DB::statement('ALTER TABLE providers ALTER COLUMN interior_number TYPE varchar(100)');
    }

    public function down(): void
    {
        // Ojo: fallará si ya hay valores de más de 20 caracteres guardados.
        DB::statement('ALTER TABLE providers ALTER COLUMN exterior_number TYPE varchar(20)');
        DB::statement('ALTER TABLE providers ALTER COLUMN interior_number TYPE varchar(20)');
    }
};