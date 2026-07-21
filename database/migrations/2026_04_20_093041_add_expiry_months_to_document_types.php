<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->unsignedSmallInteger('expiry_months')
                  ->nullable()
                  ->after('expiry_alert_days')
                  ->comment('Duración en meses del documento. Si se define, la fecha de vencimiento se calcula automáticamente desde la fecha de emisión.');
        });
    }

    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->dropColumn('expiry_months');
        });
    }
};