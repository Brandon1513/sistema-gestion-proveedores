<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('netsuite_vendor_invoices', function (Blueprint $table) {
            // Quitamos amount_paid/amount_remaining — NetSuite ya nos da un status
            // legible (ej. "Pagado por completo") en vez de tener que calcularlo.
            $table->dropColumn(['amount_paid', 'amount_remaining']);
        });
    }

    public function down(): void
    {
        Schema::table('netsuite_vendor_invoices', function (Blueprint $table) {
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('amount_remaining', 14, 2)->default(0);
        });
    }
};