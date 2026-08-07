<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_invitations', function (Blueprint $table) {
            $table->foreignId('provider_request_id')->nullable()->after('provider_type_id')
                ->constrained('provider_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('provider_invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('provider_request_id');
        });
    }
};