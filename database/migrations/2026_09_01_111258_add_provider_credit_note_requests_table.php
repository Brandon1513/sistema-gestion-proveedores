<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE provider_credit_note_requests DROP CONSTRAINT IF EXISTS provider_credit_note_requests_type_check");
        DB::statement("ALTER TABLE provider_credit_note_requests ADD CONSTRAINT provider_credit_note_requests_type_check CHECK (type IN ('faltante', 'devolucion', 'rechazo'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE provider_credit_note_requests DROP CONSTRAINT IF EXISTS provider_credit_note_requests_type_check");
        DB::statement("ALTER TABLE provider_credit_note_requests ADD CONSTRAINT provider_credit_note_requests_type_check CHECK (type IN ('faltante', 'devolucion'))");
    }
};