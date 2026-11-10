<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Sección 2: cada envío del proveedor es una "vuelta" ─────────────
        Schema::create('non_conformity_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('non_conformity_id')->constrained('non_conformities')->cascadeOnDelete();
            $table->unsignedSmallInteger('round');                 // 1, 2, 3...
            $table->string('team_leader');
            $table->string('area');
            $table->text('root_cause');                            // causa raíz (Ishikawa, 5 porqués, etc.)
            $table->timestamp('submitted_at');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();

            // Revisión del ingeniero
            $table->string('review_status', 20)->default('pending'); // pending | accepted | returned
            $table->text('review_comment')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->unique(['non_conformity_id', 'round']);
        });

        // ── Acciones correctivas de cada vuelta ─────────────────────────────
        // (en la fase 3, cada acción de la vuelta aceptada recibe su evidencia)
        Schema::create('non_conformity_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('non_conformity_response_id')->constrained('non_conformity_responses')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('action');
            $table->string('responsible');
            $table->date('commitment_date');
            $table->date('completion_date')->nullable();
            $table->text('evidence_to_deliver');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('non_conformity_actions');
        Schema::dropIfExists('non_conformity_responses');
    }
};