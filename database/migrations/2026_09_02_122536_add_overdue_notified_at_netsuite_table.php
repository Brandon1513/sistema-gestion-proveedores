<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('netsuite_vendor_invoices', function (Blueprint $table) {
            $table->timestamp('overdue_notified_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('netsuite_vendor_invoices', function (Blueprint $table) {
            $table->dropColumn('overdue_notified_at');
        });
    }
};