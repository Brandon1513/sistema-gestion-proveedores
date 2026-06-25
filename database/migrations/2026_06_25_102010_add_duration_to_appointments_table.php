<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('appointments', function (Blueprint $table) {
        $table->unsignedSmallInteger('duration_minutes')->default(60)->after('appointment_time');
    });
}

public function down(): void
{
    Schema::table('appointments', function (Blueprint $table) {
        $table->dropColumn('duration_minutes');
    });
}
};
