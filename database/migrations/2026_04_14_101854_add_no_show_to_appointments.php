<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('no_show_at')->nullable()->after('has_missing_docs');
            $table->foreignId('no_show_registered_by')->nullable()->after('no_show_at')
                ->constrained('users')->onDelete('set null');
            $table->text('no_show_notes')->nullable()->after('no_show_registered_by');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['no_show_registered_by']);
            $table->dropColumn(['no_show_at', 'no_show_registered_by', 'no_show_notes']);
        });
    }
};