<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['sales', 'billing', 'quality']); // ventas, cobranza, calidad
            $table->string('name');
            $table->string('email');
            $table->string('phone', 20);
            $table->string('extension', 10)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_contacts');
    }
};