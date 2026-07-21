<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')
                  ->constrained('document_types')
                  ->onDelete('cascade');
            $table->string('template_name');        // Ej: "General", "Cumarina", "Melamina"
            $table->string('product_name');         // Ej: "Aceite de canola", "Extracto de vainilla"
            $table->string('file_path');            // Ruta en storage
            $table->string('original_filename');    // Nombre original del archivo
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Un producto solo puede tener un template por tipo de documento
            $table->unique(['document_type_id', 'product_name'], 'unique_template_per_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};