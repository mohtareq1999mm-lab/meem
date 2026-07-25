<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('minimum_order_amount', 10, 2)->nullable()->after('options');
        });

        DB::table('settings')->orderBy('id')->each(function ($setting) {
            $options = json_decode($setting->options ?? '{}', true);

            if (isset($options['minimumOrderAmount'])) {
                $minimumOrderAmount = $options['minimumOrderAmount'];
                unset($options['minimumOrderAmount']);

                DB::table('settings')->where('id', $setting->id)->update([
                    'minimum_order_amount' => $minimumOrderAmount,
                    'options' => json_encode($options),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('settings')->orderBy('id')->each(function ($setting) {
            $options = json_decode($setting->options ?? '{}', true);

            if ($setting->minimum_order_amount !== null && !isset($options['minimumOrderAmount'])) {
                $options['minimumOrderAmount'] = $setting->minimum_order_amount;

                DB::table('settings')->where('id', $setting->id)->update([
                    'options' => json_encode($options),
                ]);
            }
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('minimum_order_amount');
        });
    }
};
