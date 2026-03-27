<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── provider_documents: campo para nombre del producto ───────────────
        Schema::table('provider_documents', function (Blueprint $table) {
            // Nombre del producto específico al que corresponde este documento
            // Aplica para: MP y ME, Químicos, Insumos, Control de Plagas
            $table->string('product_name')->nullable()->after('notes');
        });

        // ─── document_types: flag para permitir múltiples cargas ─────────────
        Schema::table('document_types', function (Blueprint $table) {
            // true = el proveedor puede subir varios archivos del mismo tipo
            // (ej. una ficha técnica por cada producto)
            $table->boolean('allows_multiple')->default(false)->after('is_required');
        });
    }

    public function down(): void
    {
        Schema::table('provider_documents', function (Blueprint $table) {
            $table->dropColumn('product_name');
        });

        Schema::table('document_types', function (Blueprint $table) {
            $table->dropColumn('allows_multiple');
        });
    }
};