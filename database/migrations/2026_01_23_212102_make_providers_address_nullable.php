<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            // Hacer nullable los campos de dirección que actualmente son NOT NULL
            $table->string('street', 255)->nullable()->change();
            $table->string('exterior_number', 20)->nullable()->change();
            $table->string('neighborhood', 255)->nullable()->change();
            $table->string('city', 255)->nullable()->change();
            $table->string('state', 255)->nullable()->change();
            $table->string('postal_code', 10)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            // Revertir a NOT NULL (solo si es necesario)
            $table->string('street', 255)->nullable(false)->change();
            $table->string('exterior_number', 20)->nullable(false)->change();
            $table->string('neighborhood', 255)->nullable(false)->change();
            $table->string('city', 255)->nullable(false)->change();
            $table->string('state', 255)->nullable(false)->change();
            $table->string('postal_code', 10)->nullable(false)->change();
        });
    }
};