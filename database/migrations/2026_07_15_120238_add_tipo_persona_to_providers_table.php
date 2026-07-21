<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('providers', function (Blueprint $table) {
        $table->enum('tipo_persona', ['moral', 'fisica'])
              ->default('moral')
              ->after('rfc')
              ->comment('Tipo de persona: moral (12 chars RFC) o fisica (13 chars RFC)');
    });
}

public function down(): void
{
    Schema::table('providers', function (Blueprint $table) {
        $table->dropColumn('tipo_persona');
    });
}
};
