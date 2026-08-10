<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            // ✅ Reemplaza la necesidad de listas hardcodeadas de código
            // (PRODUCT_SELECTOR_CODES) para decidir si un documento debe
            // pedirle al proveedor seleccionar uno de SUS productos
            // registrados, y usar plantillas asignadas por producto.
            $table->boolean('is_product_specific')->default(false)->after('allows_multiple');
        });
    }

    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->dropColumn('is_product_specific');
        });
    }
};