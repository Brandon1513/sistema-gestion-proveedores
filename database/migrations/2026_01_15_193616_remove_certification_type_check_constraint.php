<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE provider_certifications DROP CONSTRAINT IF EXISTS provider_certifications_certification_type_check');
    }

    public function down(): void
    {
        // No hacer nada
    }
};