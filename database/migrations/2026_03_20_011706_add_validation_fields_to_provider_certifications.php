<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_certifications', function (Blueprint $table) {
            // Estado de validación
            $table->string('status')->default('pending')->after('certifying_body');
            $table->text('validation_comments')->nullable()->after('status');
            $table->unsignedBigInteger('validated_by')->nullable()->after('validation_comments');
            $table->timestamp('validated_at')->nullable()->after('validated_by');

            // Archivo adjunto
            $table->string('file_path')->nullable()->after('validated_at');
            $table->string('file_name')->nullable()->after('file_path');
            $table->integer('file_size_kb')->nullable()->after('file_name');
            $table->string('file_extension')->nullable()->after('file_size_kb');

            $table->foreign('validated_by')->references('id')->on('users')->nullOnDelete();
        });

        // Marcar como 'approved' las certificaciones existentes para no bloquear al proveedor
        DB::table('provider_certifications')->update(['status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('provider_certifications', function (Blueprint $table) {
            $table->dropForeign(['validated_by']);
            $table->dropColumn([
                'status', 'validation_comments', 'validated_by', 'validated_at',
                'file_path', 'file_name', 'file_size_kb', 'file_extension',
            ]);
        });
    }
};