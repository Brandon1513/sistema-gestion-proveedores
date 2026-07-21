<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Eliminar constraints CHECK de PostgreSQL que bloquean nuevos valores de status
        DB::statement("ALTER TABLE appointments DROP CONSTRAINT IF EXISTS appointments_status_check");
        DB::statement("ALTER TABLE appointments DROP CONSTRAINT IF EXISTS appointments_reception_status_check");
        DB::statement("ALTER TABLE appointments DROP CONSTRAINT IF EXISTS appointments_type_check");

        // Convertir columnas enum a VARCHAR para permitir nuevos valores sin migraciones adicionales
        DB::statement("ALTER TABLE appointments ALTER COLUMN status TYPE VARCHAR(50)");
        DB::statement("ALTER TABLE appointments ALTER COLUMN reception_status TYPE VARCHAR(50)");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE appointments ALTER COLUMN status TYPE VARCHAR(50)");
        DB::statement("ALTER TABLE appointments ALTER COLUMN reception_status TYPE VARCHAR(50)");
    }
};