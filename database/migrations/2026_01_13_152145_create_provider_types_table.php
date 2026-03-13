<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique(); // mp_me, residuos, laboratorios, sustancias_quimicas, insumos
            $table->string('name');
            $table->string('form_code', 20); // F-COM-02, F-COM-12
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_types');
    }
};