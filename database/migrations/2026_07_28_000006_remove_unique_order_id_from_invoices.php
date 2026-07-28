<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('uq_invoices_order_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index('order_id', 'idx_invoices_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoices_order_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unique('order_id', 'uq_invoices_order_id');
        });
    }
};
