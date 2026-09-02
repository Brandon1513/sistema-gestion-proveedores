<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_credit_note_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_invoice_id')->nullable()
                ->constrained('netsuite_vendor_invoices')->nullOnDelete();
            $table->enum('type', ['faltante', 'devolucion']);
            $table->text('description');
            $table->decimal('amount_requested', 14, 2)->nullable();
            $table->string('file_path')->nullable();
            $table->enum('status', ['pending', 'in_review', 'approved', 'rejected', 'sent_to_netsuite'])
                ->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['provider_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_credit_note_requests');
    }
};