<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('pickup_location_name')->nullable()->after('pickup_location_id');
            $table->text('pickup_location_address')->nullable()->after('pickup_location_name');
            $table->string('pickup_location_phone')->nullable()->after('pickup_location_address');
            $table->string('pickup_location_coordinates')->nullable()->after('pickup_location_phone');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_location_name',
                'pickup_location_address',
                'pickup_location_phone',
                'pickup_location_coordinates',
            ]);
        });
    }
};
