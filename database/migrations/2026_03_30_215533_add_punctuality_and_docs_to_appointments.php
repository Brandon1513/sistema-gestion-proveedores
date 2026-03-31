<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Puntualidad
            $table->boolean('arrived_on_time')->nullable()->after('entry_notes');
            $table->time('actual_arrival_time')->nullable()->after('arrived_on_time');
            $table->integer('delay_minutes')->nullable()->after('actual_arrival_time');

            // Documentos físicos presentados al llegar
            $table->json('physical_docs_status')->nullable()->after('delay_minutes');
            $table->boolean('has_missing_docs')->default(false)->after('physical_docs_status');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn([
                'arrived_on_time',
                'actual_arrival_time',
                'delay_minutes',
                'physical_docs_status',
                'has_missing_docs',
            ]);
        });
    }
};