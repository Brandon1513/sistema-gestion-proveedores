<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('netsuite_vendor_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('netsuite_internal_id')->unique();
            $table->string('tran_id')->nullable();
            $table->date('tran_date')->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('currency', 10)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('provider_id');
        });

        // Pivot: qué facturas cubrió cada pago (un pago puede aplicar a varias facturas)
        Schema::create('netsuite_payment_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('netsuite_vendor_payments')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('netsuite_vendor_invoices')->cascadeOnDelete();
            $table->decimal('amount_applied', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('netsuite_payment_applications');
        Schema::dropIfExists('netsuite_vendor_payments');
    }
};