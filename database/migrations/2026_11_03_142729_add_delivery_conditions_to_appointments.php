<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Checklist de condiciones de la entrega que llena el ingeniero de alimentos
        // al registrar la recepción: [{key, label, passed, notes}]. Visible para el proveedor.
        Schema::table('appointments', function (Blueprint $table) {
            $table->json('delivery_conditions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('delivery_conditions');
        });
    }
};