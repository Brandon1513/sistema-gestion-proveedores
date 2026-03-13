<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->onDelete('cascade');
            $table->foreignId('document_type_id')->constrained()->onDelete('restrict');
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('file_extension', 10);
            $table->integer('file_size_kb');
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired'])->default('pending');
            $table->text('notes')->nullable();
            $table->integer('version')->default(1); // Control de versiones del documento
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_documents');
    }
};