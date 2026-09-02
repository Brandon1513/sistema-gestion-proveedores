<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('netsuite_vendor_invoices', function (Blueprint $table) {
            $table->date('netsuite_last_modified')->nullable()->after('memo');
        });

        Schema::table('netsuite_credit_memos', function (Blueprint $table) {
            $table->date('netsuite_last_modified')->nullable()->after('memo');
        });
    }

    public function down(): void
    {
        Schema::table('netsuite_vendor_invoices', function (Blueprint $table) {
            $table->dropColumn('netsuite_last_modified');
        });
        Schema::table('netsuite_credit_memos', function (Blueprint $table) {
            $table->dropColumn('netsuite_last_modified');
        });
    }
};