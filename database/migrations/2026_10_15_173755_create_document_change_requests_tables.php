<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained('document_types')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description');
            $table->string('status', 20)->default('pending'); // pending | attended | rejected
            $table->text('response_notes')->nullable();
            $table->string('response_file_path')->nullable();
            $table->string('response_original_filename')->nullable();
            $table->foreignId('attended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('attended_at')->nullable();
            $table->timestamps();

            $table->index(['provider_id', 'document_type_id', 'status']);
            $table->index('status');
        });

        // Solo una solicitud abierta por proveedor y documento (PostgreSQL)
        DB::statement("
            CREATE UNIQUE INDEX document_change_requests_one_pending
            ON document_change_requests (provider_id, document_type_id)
            WHERE status = 'pending'
        ");

        DB::statement("
            ALTER TABLE document_change_requests
            ADD CONSTRAINT document_change_requests_status_check
            CHECK (status IN ('pending', 'attended', 'rejected'))
        ");

        Schema::create('document_change_request_images', function (Blueprint $table) {
            $table->id();
            // Nombre corto a propósito: el nombre automático de la FK pasaría
            // de 63 caracteres y PostgreSQL lo truncaría.
            $table->foreignId('change_request_id')
                ->constrained('document_change_requests')
                ->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_kb');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_change_request_images');
        Schema::dropIfExists('document_change_requests');
    }
};