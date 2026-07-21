<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('document_type_provider_type', function (Blueprint $table) {
        $table->enum('applies_to_persona', ['all', 'moral', 'fisica'])
              ->default('all')
              ->after('applies_to_existing')
              ->comment('A qué tipo de persona aplica este documento');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
{
    Schema::table('document_type_provider_type', function (Blueprint $table) {
        $table->dropColumn('applies_to_persona');
    });
}
};
