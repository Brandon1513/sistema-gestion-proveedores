<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS provider_invoice_submissions_uuid_active_unique');
        DB::statement(
            "CREATE UNIQUE INDEX provider_invoice_submissions_uuid_active_unique
             ON provider_invoice_submissions (uuid) WHERE status NOT IN ('rejected', 'cancelled')"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS provider_invoice_submissions_uuid_active_unique');
        DB::statement(
            "CREATE UNIQUE INDEX provider_invoice_submissions_uuid_active_unique
             ON provider_invoice_submissions (uuid) WHERE status <> 'rejected'"
        );
    }
};