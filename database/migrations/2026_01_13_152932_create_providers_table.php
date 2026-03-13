<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_type_id')->constrained()->onDelete('restrict');
            $table->string('business_name'); // Razón Social
            $table->string('rfc', 13)->unique();
            $table->string('legal_representative')->nullable();
            
            // Dirección
            $table->string('street');
            $table->string('exterior_number', 20);
            $table->string('interior_number', 20)->nullable();
            $table->string('neighborhood'); // Colonia
            $table->string('city');
            $table->string('state');
            $table->string('postal_code', 10);
            $table->string('phone', 20)->nullable();
            $table->string('email');
            
            // Información bancaria
            $table->string('bank')->nullable();
            $table->string('bank_branch')->nullable(); // Sucursal
            $table->string('account_number')->nullable();
            $table->string('clabe', 18)->nullable();
            
            // Información crediticia
            $table->decimal('credit_amount', 15, 2)->nullable();
            $table->integer('credit_days')->nullable();
            
            // Para proveedores de MP y ME
            $table->text('products')->nullable(); // Productos que provee
            $table->text('services')->nullable(); // Para proveedores de servicios
            
            // Estado del proveedor
            $table->enum('status', ['pending', 'active', 'inactive', 'rejected'])->default('pending');
            $table->text('observations')->nullable();
            
            // Usuario que creó el registro
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};