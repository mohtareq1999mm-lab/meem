<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('platform_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('shop_id')->constrained('shops')->onDelete('cascade');
            $table->decimal('order_total', 10, 2);
            $table->decimal('commission_rate', 5, 2)->comment('Commission percentage applied');
            $table->decimal('commission_amount', 10, 2)->comment('Platform commission in currency');
            $table->decimal('shop_earnings', 10, 2)->comment('Shop earnings after commission');
            $table->string('commission_type', 20)->comment('tier or custom');
            $table->timestamps();

            $table->index('order_id');
            $table->index('shop_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('platform_commissions');
    }
};
