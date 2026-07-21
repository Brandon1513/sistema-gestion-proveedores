<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // ─── Seguridad: confirmación de entrada ───────────────────────────
            $table->timestamp('entry_confirmed_at')->nullable()->after('provider_notes');
            $table->foreignId('entry_confirmed_by')->nullable()->after('entry_confirmed_at')
                ->constrained('users')->onDelete('set null');
            $table->string('entry_notes')->nullable()->after('entry_confirmed_by');

            // ─── Ingeniero de Alimentos: recepción del producto ───────────────
            $table->enum('reception_status', ['pending', 'accepted', 'rejected'])
                ->default('pending')->after('entry_notes');
            $table->text('reception_notes')->nullable()->after('reception_status');
            $table->json('reception_photos')->nullable()->after('reception_notes');
            $table->foreignId('reception_reviewed_by')->nullable()->after('reception_photos')
                ->constrained('users')->onDelete('set null');
            $table->timestamp('reception_reviewed_at')->nullable()->after('reception_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['entry_confirmed_by']);
            $table->dropForeign(['reception_reviewed_by']);
            $table->dropColumn([
                'entry_confirmed_at', 'entry_confirmed_by', 'entry_notes',
                'reception_status', 'reception_notes', 'reception_photos',
                'reception_reviewed_by', 'reception_reviewed_at',
            ]);
        });
    }
};