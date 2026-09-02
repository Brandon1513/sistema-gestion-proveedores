<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('netsuite_credit_memos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('netsuite_internal_id')->unique();
            $table->string('tran_id')->nullable();
            $table->date('tran_date')->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('amount_remaining', 14, 2)->default(0);
            $table->text('memo')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('provider_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('netsuite_credit_memos');
    }
};