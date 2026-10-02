<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_invoice_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            // Datos leídos del XML (CFDI)
            $table->string('uuid', 36);
            $table->string('serie', 25)->nullable();
            $table->string('folio', 40)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->decimal('total', 14, 2);
            $table->string('currency', 10)->default('MXN');
            $table->string('payment_method', 5)->nullable(); // PUE / PPD
            $table->string('issuer_rfc', 13);
            $table->string('receiver_rfc', 13);

            $table->string('pdf_path');
            $table->string('xml_path');

            $table->string('status')->default('submitted'); // submitted, captured, rejected
            $table->foreignId('netsuite_vendor_invoice_id')->nullable()
                ->constrained('netsuite_vendor_invoices')->nullOnDelete();
            $table->timestamp('captured_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['provider_id', 'status']);
        });

        // UUID único entre facturas NO rechazadas: una rechazada se puede volver a subir corregida.
        DB::statement(
            "CREATE UNIQUE INDEX provider_invoice_submissions_uuid_active_unique
             ON provider_invoice_submissions (uuid) WHERE status <> 'rejected'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_invoice_submissions');
    }
};