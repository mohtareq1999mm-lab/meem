<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Marvel\Enums\ItemType;

return new class extends Migration
{
    /**
     * Shrink products.item_type to the approved PHYSICAL|DIGITAL domain.
     * Existing SERVICE rows are converted to PHYSICAL before the shrink so
     * no data is lost and the column never violates its own enum.
     */
    public function up(): void
    {
        $serviceCount = DB::table('products')->where('item_type', 'SERVICE')->count();

        if ($serviceCount > 0) {
            Log::warning('item_type migration: converting SERVICE products to PHYSICAL.', [
                'converted_rows' => $serviceCount,
            ]);

            DB::table('products')
                ->where('item_type', 'SERVICE')
                ->update(['item_type' => ItemType::PHYSICAL]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->enum('item_type', ItemType::getValues())
                ->default(ItemType::PHYSICAL)
                ->change();
        });
    }

    /**
     * Restore the previous (wider) enum domain. Rows already converted to
     * PHYSICAL keep their value; only the allowed domain is widened back.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('item_type', ['PHYSICAL', 'DIGITAL', 'SERVICE'])
                ->default(ItemType::PHYSICAL)
                ->change();
        });
    }
};
