<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_document_scopes', function (Blueprint $table) {
            $table->id();
            $table->string('role');      // ej. 'cuentas_por_pagar'
            $table->string('category');  // ej. 'fiscal' — debe coincidir con document_types.category
            $table->timestamps();

            $table->unique(['role', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_document_scopes');
    }
};