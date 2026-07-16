<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('fulfillment_type', 20)->nullable()->after('shipping_method')
                ->comment('delivery or pickup');
            $table->string('payment_method', 30)->nullable()->after('fulfillment_type')
                ->comment('online, cod, pay_at_cashier');
            $table->string('payment_gateway', 50)->nullable()->after('payment_method')
                ->comment('myfatoorah, etc.');
            $table->unsignedBigInteger('pickup_location_id')->nullable()->after('payment_gateway')
                ->comment('references resources.id — FK omitted: resources table has no migration in this repo');
        });

        DB::statement("UPDATE orders SET fulfillment_type = 'delivery' WHERE fulfillment_type IS NULL");
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['fulfillment_type', 'payment_method', 'payment_gateway', 'pickup_location_id']);
        });
    }
};
