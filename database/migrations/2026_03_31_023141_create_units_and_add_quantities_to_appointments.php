<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla de unidades de medida
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');        // Kilogramo
            $table->string('abbreviation');// kg
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Campos de cantidad en appointments
        Schema::table('appointments', function (Blueprint $table) {
            $table->decimal('quantity_received', 10, 2)->nullable()->after('has_missing_docs');
            $table->decimal('quantity_rejected', 10, 2)->nullable()->after('quantity_received');
            $table->foreignId('unit_id')->nullable()->after('quantity_rejected')
                ->constrained('units')->onDelete('set null');
            $table->string('rejection_reason')->nullable()->after('unit_id'); // inocuidad | calidad
            $table->boolean('is_partial_rejection')->default(false)->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn([
                'quantity_received', 'quantity_rejected',
                'unit_id', 'rejection_reason', 'is_partial_rejection',
            ]);
        });
        Schema::dropIfExists('units');
    }
};