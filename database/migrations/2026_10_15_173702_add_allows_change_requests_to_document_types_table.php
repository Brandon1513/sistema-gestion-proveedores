<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convenios comerciales que admiten solicitud de cambios por parte del
     * proveedor. El filtro PF/PM ya lo resuelve applies_to_persona, aquí solo
     * se activa la bandera. Para agregar más documentos después basta con
     * poner allows_change_requests = true; no hay códigos en el controller.
     */
    private const CONVENIO_CODES = [
        'CC-MAT-PM', 'CC-MAT-PF',
        'CC-SER-PM', 'CC-SER-PF',
        'CC-QUIM-PM', 'CC-QUIM-PF',
        'CC-INS-PM', 'CC-INS-PF',
    ];

    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->boolean('allows_change_requests')->default(false);
        });

        DB::table('document_types')
            ->whereIn('code', self::CONVENIO_CODES)
            ->update(['allows_change_requests' => true]);
    }

    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->dropColumn('allows_change_requests');
        });
    }
};