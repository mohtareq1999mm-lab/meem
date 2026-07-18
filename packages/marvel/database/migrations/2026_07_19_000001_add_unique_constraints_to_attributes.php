<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attribute_values', function (Blueprint $table) {
            $table->unique(['attribute_id', 'slug'], 'attribute_values_attribute_id_slug_unique');
        });

        Schema::table('attribute_product', function (Blueprint $table) {
            $table->unique(['attribute_value_id', 'product_variant_id'], 'attribute_product_value_variant_unique');
        });
    }

    public function down(): void
    {
        Schema::table('attribute_values', function (Blueprint $table) {
            $table->dropUnique('attribute_values_attribute_id_slug_unique');
        });

        Schema::table('attribute_product', function (Blueprint $table) {
            $table->dropUnique('attribute_product_value_variant_unique');
        });
    }
};
