<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_document_id')->constrained()->onDelete('cascade');
            $table->foreignId('validated_by')->constrained('users')->onDelete('restrict');
            $table->enum('action', ['approved', 'rejected']);
            $table->text('comments')->nullable();
            $table->timestamp('validated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_validations');
    }
};