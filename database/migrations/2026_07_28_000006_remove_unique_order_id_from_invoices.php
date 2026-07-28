<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropUnique('uq_invoices_order_id');
            $table->index('order_id', 'idx_invoices_order_id');
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropIndex('idx_invoices_order_id');
            $table->unique('order_id', 'uq_invoices_order_id');
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
        });
    }
};
