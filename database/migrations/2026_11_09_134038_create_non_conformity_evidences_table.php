<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Sección 3: evidencia de cada acción correctiva ───────────────────
        // Cada intento queda guardado; el ingeniero lo marca Cumple / No cumple.
        Schema::create('non_conformity_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('non_conformity_action_id')->constrained('non_conformity_actions')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt');                 // 1, 2, 3... por acción
            $table->string('path');
            $table->string('original_name');
            $table->string('file_extension', 10)->nullable();
            $table->unsignedInteger('size_kb')->nullable();
            $table->text('provider_comment')->nullable();
            $table->timestamp('submitted_at');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('review_status', 20)->default('pending'); // pending | accepted | rejected
            $table->text('review_comment')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->unique(['non_conformity_action_id', 'attempt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('non_conformity_evidences');
    }
};