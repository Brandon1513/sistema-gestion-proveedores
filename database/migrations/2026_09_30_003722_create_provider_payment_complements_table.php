<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_payment_complements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('netsuite_vendor_payment_id')->constrained('netsuite_vendor_payments')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('uuid', 36);
            $table->timestamp('issued_at')->nullable();
            $table->decimal('total', 14, 2)->nullable();
            $table->string('currency', 10)->default('MXN');
            $table->string('issuer_rfc', 13);
            $table->string('receiver_rfc', 13);

            $table->string('pdf_path');
            $table->string('xml_path');

            $table->string('status')->default('submitted'); // submitted, approved, rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['provider_id', 'status']);
        });

        // UUID único entre complementos NO rechazados (igual patrón que facturas).
        DB::statement(
            "CREATE UNIQUE INDEX provider_payment_complements_uuid_active_unique
             ON provider_payment_complements (uuid) WHERE status <> 'rejected'"
        );

        // Un pago solo puede tener un complemento activo (no rechazado) a la vez.
        DB::statement(
            "CREATE UNIQUE INDEX provider_payment_complements_payment_active_unique
             ON provider_payment_complements (netsuite_vendor_payment_id) WHERE status <> 'rejected'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_payment_complements');
    }
};