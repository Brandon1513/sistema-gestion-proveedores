<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_credit_note_requests', function (Blueprint $table) {
            $table->string('provider_response_file_path')->nullable()->after('file_path');
            $table->timestamp('provider_responded_at')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('provider_credit_note_requests', function (Blueprint $table) {
            $table->dropColumn(['provider_response_file_path', 'provider_responded_at']);
        });
    }
};