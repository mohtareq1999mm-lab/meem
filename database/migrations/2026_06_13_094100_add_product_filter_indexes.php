<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['status', 'deleted_at', 'price'], 'idx_products_status_deleted_price');
            $table->index('height', 'idx_products_height');
            $table->index('width', 'idx_products_width');
            $table->index('length', 'idx_products_length');
            $table->index('weight', 'idx_products_weight');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->index('price', 'idx_variants_price');
            $table->index('height', 'idx_variants_height');
            $table->index('width', 'idx_variants_width');
            $table->index('length', 'idx_variants_length');
            $table->index('weight', 'idx_variants_weight');
        });

        Schema::table('attribute_product', function (Blueprint $table) {
            $table->index(['product_variant_id', 'attribute_value_id'], 'idx_attr_prod_variant_value');
            $table->index(['attribute_value_id', 'product_variant_id'], 'idx_attr_prod_value_variant');
        });

        Schema::table('category_product', function (Blueprint $table) {
            $table->index(['product_id', 'category_id'], 'idx_cat_prod_product_category');
            $table->index(['category_id', 'product_id'], 'idx_cat_prod_category_product');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_status_deleted_price');
            $table->dropIndex('idx_products_height');
            $table->dropIndex('idx_products_width');
            $table->dropIndex('idx_products_length');
            $table->dropIndex('idx_products_weight');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex('idx_variants_price');
            $table->dropIndex('idx_variants_height');
            $table->dropIndex('idx_variants_width');
            $table->dropIndex('idx_variants_length');
            $table->dropIndex('idx_variants_weight');
        });

        Schema::table('attribute_product', function (Blueprint $table) {
            $table->dropIndex('idx_attr_prod_variant_value');
            $table->dropIndex('idx_attr_prod_value_variant');
        });

        Schema::table('category_product', function (Blueprint $table) {
            $table->dropIndex('idx_cat_prod_product_category');
            $table->dropIndex('idx_cat_prod_category_product');
        });
    }
};
