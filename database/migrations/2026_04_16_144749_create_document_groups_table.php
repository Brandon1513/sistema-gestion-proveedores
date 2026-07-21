<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Migrar grupos existentes desde document_types.group_name
        // para no perder datos ya ingresados
        $existing = DB::table('document_types')
            ->whereNotNull('group_name')
            ->where('group_name', '!=', '')
            ->distinct()
            ->pluck('group_name');

        foreach ($existing as $i => $name) {
            DB::table('document_groups')->insert([
                'name'       => $name,
                'sort_order' => $i,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_groups');
    }
};