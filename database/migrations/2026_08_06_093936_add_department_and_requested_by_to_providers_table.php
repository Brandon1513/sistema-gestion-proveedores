<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            // Nullable a propósito: se puede clasificar después, incluso
            // para proveedores que ya estaban activos antes de este cambio.
            $table->foreignId('department_id')->nullable()->after('provider_type_id')
                ->constrained('departments')->nullOnDelete();

            // Usuario interno que originó la solicitud de este proveedor
            // (null si fue creado manualmente por Compras, como antes).
            $table->foreignId('requested_by')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('requested_by');
        });
    }
};