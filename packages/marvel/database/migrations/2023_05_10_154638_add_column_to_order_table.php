<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnToOrderTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('cancelled_tax')->default(0);
            $table->decimal('cancelled_delivery_fee')->default(0);
            $table->longText('note')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('note');
            $table->dropColumn('cancelled_tax');
            $table->dropColumn('cancelled_delivery_fee');
        });
    }
}
;
