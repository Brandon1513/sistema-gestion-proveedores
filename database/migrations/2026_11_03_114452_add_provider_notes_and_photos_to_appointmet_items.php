<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Observaciones que SÍ ve el proveedor (reception_notes queda como interna)
        Schema::table('appointment_items', function (Blueprint $table) {
            $table->text('provider_notes')->nullable();
        });

        // Fotos de evidencia por ítem de la recepción (máx. 3 por ítem, se valida en el controller)
        Schema::create('appointment_item_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_item_id')->constrained('appointment_items')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->unsignedInteger('size_kb')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_item_photos');

        Schema::table('appointment_items', function (Blueprint $table) {
            $table->dropColumn('provider_notes');
        });
    }
};