<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Cierre de la cita por Seguridad: hora de salida del proveedor
            $table->time('actual_exit_time')->nullable()->after('delay_minutes');
            $table->timestamp('exit_registered_at')->nullable()->after('actual_exit_time');
            $table->foreignId('exit_registered_by')->nullable()->after('exit_registered_at')
                ->constrained('users')->nullOnDelete();
            $table->string('exit_notes', 500)->nullable()->after('exit_registered_by');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exit_registered_by');
            $table->dropColumn(['actual_exit_time', 'exit_registered_at', 'exit_notes']);
        });
    }
};