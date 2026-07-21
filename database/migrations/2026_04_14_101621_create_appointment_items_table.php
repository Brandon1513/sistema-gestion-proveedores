<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')
                ->constrained('appointments')
                ->onDelete('cascade');
            $table->foreignId('product_service_id')
                ->constrained('products_services')
                ->onDelete('cascade');

            // Lo que Compras agenda traer
            $table->decimal('quantity_expected', 10, 2)->nullable();
            $table->foreignId('unit_id')->nullable()->constrained('units')->onDelete('set null');
            $table->text('notes')->nullable();

            // Lo que el Ingeniero registra al recibir
            $table->decimal('quantity_received', 10, 2)->nullable();
            $table->decimal('quantity_rejected', 10, 2)->nullable();
            $table->foreignId('received_unit_id')->nullable()->constrained('units')->onDelete('set null');
            $table->enum('reception_status', ['pending', 'accepted', 'rejected', 'partial'])
                ->default('pending');
            $table->string('rejection_reason')->nullable(); // inocuidad | calidad
            $table->text('reception_notes')->nullable();

            $table->timestamps();
        });

        // Agregar estado no_show a appointments
        Schema::table('appointments', function (Blueprint $table) {
            // Modificar el campo status para incluir no_show
            // En PostgreSQL usamos raw SQL para agregar el valor al enum
            // Se hace via DB::statement en el seeder o con una migración raw
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_items');
    }
};