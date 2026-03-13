<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->onDelete('cascade');
            $table->enum('certification_type', [
                'haccp',
                'iso_fssc_22000',
                'bmp',
                'kosher',
                'halal',
                'organic',
                'gluten_free',
                'other'
            ]);
            $table->string('other_name')->nullable(); // Si es "other"
            $table->string('certification_number')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('certifying_body')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_certifications');
    }
};