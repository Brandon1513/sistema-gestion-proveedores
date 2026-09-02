<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('netsuite_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('module');            // invoices, payments, credit_memos
            $table->integer('records_synced')->default(0);
            $table->string('status');            // exitoso, parcial, error
            $table->text('message')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('netsuite_sync_logs');
    }
};