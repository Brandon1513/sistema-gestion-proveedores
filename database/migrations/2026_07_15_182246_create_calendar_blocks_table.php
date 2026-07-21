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
    Schema::create('calendar_blocks', function (Blueprint $table) {
        $table->id();
        $table->date('block_date');
        $table->time('start_time');
        $table->time('end_time');
        $table->string('title', 255);
        $table->text('observations')->nullable();
        $table->foreignId('created_by')->constrained('users');
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('calendar_blocks');
}

};
