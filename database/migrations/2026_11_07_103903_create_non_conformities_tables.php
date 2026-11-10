<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Catálogo de tipos de desviación ──────────────────────────────────
        Schema::create('non_conformity_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $types = ['Materia extraña', 'Inocuidad', 'Calidad', 'Calidad sensorial', 'Plaga', 'Logística', 'Empaque'];
        foreach ($types as $i => $name) {
            DB::table('non_conformity_types')->insert([
                'name' => $name, 'sort_order' => $i + 1, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // ── No Conformidades (formato F-CAL-24) ─────────────────────────────
        Schema::create('non_conformities', function (Blueprint $table) {
            $table->id();

            // Folio NC{consecutivo}{año}, p. ej. NC122026
            $table->string('folio', 30)->unique();
            $table->unsignedSmallInteger('folio_year');
            $table->unsignedInteger('folio_sequence');

            // ── Sección 1 (DASAVENA) ──
            $table->foreignId('provider_id')->constrained('providers');
            $table->foreignId('product_service_id')->constrained('products_services');
            $table->string('lot', 100)->nullable();
            $table->date('deviation_date');
            $table->date('reception_date')->nullable();
            $table->decimal('quantity_involved', 12, 2)->nullable();
            $table->foreignId('unit_id')->nullable()->constrained('units');
            $table->foreignId('non_conformity_type_id')->constrained('non_conformity_types');
            $table->text('description');

            // Contención de DASAVENA (seguimiento interno, no lo ve el proveedor)
            $table->string('immediate_action', 500)->nullable();
            $table->date('immediate_action_date')->nullable();

            // ── Flujo y plazos ──
            // awaiting_response → (plan_returned ↔ en revisión) → awaiting_evidence → closed
            $table->string('status', 30)->default('awaiting_response');
            $table->date('issued_at');                       // fecha de emisión de la NC
            $table->date('response_due_date');               // issued_at + 28 días (no se reinicia con las vueltas)
            $table->date('response_received_at')->nullable(); // primera vez que el proveedor envía su acción correctiva
            $table->date('evidence_due_date')->nullable();   // response_received_at + 28 días

            // ── Cierre (firma electrónica = usuario + fecha) ──
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('closing_comment')->nullable();

            $table->boolean('is_imported')->default(false);  // histórico importado del Excel
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['folio_year', 'folio_sequence']);
            $table->index(['provider_id', 'status']);
        });

        // ── Fotos de la Sección 1 ───────────────────────────────────────────
        Schema::create('non_conformity_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('non_conformity_id')->constrained('non_conformities')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->unsignedInteger('size_kb')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('non_conformity_photos');
        Schema::dropIfExists('non_conformities');
        Schema::dropIfExists('non_conformity_types');
    }
};