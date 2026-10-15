<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_payment_complement_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complement_id')->constrained('provider_payment_complements')->cascadeOnDelete();
            $table->foreignId('netsuite_vendor_payment_id')->constrained('netsuite_vendor_payments')->cascadeOnDelete();
            // false cuando el complemento fue rechazado: libera el pago para volver a subirlo
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['complement_id', 'netsuite_vendor_payment_id'], 'ppcp_complement_payment_unique');
        });

        // Un pago solo puede estar en un complemento vigente
        DB::statement(
            'CREATE UNIQUE INDEX ppcp_payment_active_unique
             ON provider_payment_complement_payments (netsuite_vendor_payment_id) WHERE is_active'
        );

        // Pasar lo ya existente (1 complemento = 1 pago) al nuevo esquema
        DB::statement(
            "INSERT INTO provider_payment_complement_payments
                (complement_id, netsuite_vendor_payment_id, is_active, created_at, updated_at)
             SELECT id, netsuite_vendor_payment_id, (status <> 'rejected'), now(), now()
             FROM provider_payment_complements
             WHERE netsuite_vendor_payment_id IS NOT NULL"
        );

        DB::statement('DROP INDEX IF EXISTS provider_payment_complements_payment_active_unique');

        Schema::table('provider_payment_complements', function (Blueprint $table) {
            $table->dropForeign(['netsuite_vendor_payment_id']);
            $table->dropColumn('netsuite_vendor_payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('provider_payment_complements', function (Blueprint $table) {
            $table->foreignId('netsuite_vendor_payment_id')->nullable()
                ->constrained('netsuite_vendor_payments')->nullOnDelete();
        });

        Schema::dropIfExists('provider_payment_complement_payments');
    }
};