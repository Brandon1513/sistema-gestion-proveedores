<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Categorías del catálogo
        Schema::create('product_service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['product', 'service']);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Catálogo global de productos y servicios
        Schema::create('products_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                ->constrained('product_service_categories')
                ->onDelete('cascade');
            $table->enum('type', ['product', 'service']);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Relación proveedor ↔ producto/servicio
        Schema::create('provider_products_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')
                ->constrained('providers')
                ->onDelete('cascade');
            $table->foreignId('product_service_id')
                ->constrained('products_services')
                ->onDelete('cascade');
            $table->timestamps();

            $table->unique(['provider_id', 'product_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_products_services');
        Schema::dropIfExists('products_services');
        Schema::dropIfExists('product_service_categories');
    }
};