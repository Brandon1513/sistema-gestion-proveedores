<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('rescheduled_from_id')->nullable()
                ->constrained('appointments')->nullOnDelete();
            $table->foreignId('rescheduled_to_id')->nullable()
                ->constrained('appointments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rescheduled_from_id');
            $table->dropConstrainedForeignId('rescheduled_to_id');
        });
    }
};