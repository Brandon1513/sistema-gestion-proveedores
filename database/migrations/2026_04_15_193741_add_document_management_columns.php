<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agregar columnas de gestión a document_types
        Schema::table('document_types', function (Blueprint $table) {
            $table->string('group_name')->nullable()->after('category')
                  ->comment('Agrupación visual dentro de su categoría');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('group_name')
                  ->comment('Orden dentro del grupo');
            $table->boolean('is_active')->default(true)->after('sort_order')
                  ->comment('Si el documento está activo en el sistema');
        });

        // Agregar columnas de gestión al pivot
        Schema::table('document_type_provider_type', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_required')
                  ->comment('Orden de presentación para este tipo de proveedor');
            $table->boolean('applies_to_existing')->default(true)->after('sort_order')
                  ->comment('Si aplica a proveedores ya registrados de este tipo');
        });
    }

    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->dropColumn(['group_name', 'sort_order', 'is_active']);
        });

        Schema::table('document_type_provider_type', function (Blueprint $table) {
            $table->dropColumn(['sort_order', 'applies_to_existing']);
        });
    }
};