<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('category', ['fiscal', 'technical', 'quality', 'legal']);
            $table->boolean('requires_expiry')->default(false);
            $table->integer('expiry_alert_days')->nullable(); // Días de anticipación para alertar
            $table->boolean('is_required')->default(true);
            $table->json('allowed_extensions')->nullable(); // ['pdf', 'jpg', 'png']
            $table->integer('max_file_size_mb')->default(10);
            $table->timestamps();
        });

        // Tabla pivote para relacionar tipos de documentos con tipos de proveedores
        Schema::create('document_type_provider_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained()->onDelete('cascade');
            $table->foreignId('provider_type_id')->constrained()->onDelete('cascade');
            $table->boolean('is_required')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_type_provider_type');
        Schema::dropIfExists('document_types');
    }
};