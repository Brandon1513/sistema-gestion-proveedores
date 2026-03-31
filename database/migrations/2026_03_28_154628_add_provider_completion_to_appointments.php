<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Campos que completa el proveedor
            $table->timestamp('completed_by_provider_at')->nullable()->after('cancelled_at');
            $table->text('provider_notes')->nullable()->after('completed_by_provider_at');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['completed_by_provider_at', 'provider_notes']);
        });
    }
};