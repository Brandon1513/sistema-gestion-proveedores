<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('netsuite_vendor_invoices', function (Blueprint $table) {
            $table->string('pdf_file_id')->nullable()->after('memo');
        });

        Schema::table('netsuite_vendor_payments', function (Blueprint $table) {
            $table->string('receipt_file_id')->nullable()->after('reference_number');
        });
    }

    public function down(): void
    {
        Schema::table('netsuite_vendor_invoices', function (Blueprint $table) {
            $table->dropColumn('pdf_file_id');
        });
        Schema::table('netsuite_vendor_payments', function (Blueprint $table) {
            $table->dropColumn('receipt_file_id');
        });
    }
};