<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
    {
        DB::statement('ALTER TABLE appointment_items DROP CONSTRAINT IF EXISTS appointment_items_reception_status_check');

        DB::statement("ALTER TABLE appointment_items ADD CONSTRAINT appointment_items_reception_status_check 
            CHECK (reception_status IN ('pending', 'accepted', 'rejected', 'partial', 'not_delivered'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE appointment_items DROP CONSTRAINT IF EXISTS appointment_items_reception_status_check');

        DB::statement("ALTER TABLE appointment_items ADD CONSTRAINT appointment_items_reception_status_check 
            CHECK (reception_status IN ('pending', 'accepted', 'rejected', 'partial'))");
    }
};
