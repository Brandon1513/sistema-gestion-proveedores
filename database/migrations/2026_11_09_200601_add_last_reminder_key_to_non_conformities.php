<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('non_conformities', function (Blueprint $table) {
            // Último recordatorio enviado (p. ej. "2026-12-07:7"), para no repetirlo
            $table->string('last_reminder_key', 40)->nullable()->after('closing_comment');
        });
    }

    public function down(): void
    {
        Schema::table('non_conformities', function (Blueprint $table) {
            $table->dropColumn('last_reminder_key');
        });
    }
};