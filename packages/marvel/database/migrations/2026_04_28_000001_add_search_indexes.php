<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSearchIndexes extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index('price');
            $table->index('sold_quantity');
            $table->index('name');
            $table->index('slug');
            $table->index('sku');
            $table->index('is_fast_shipping_available');
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->index('name');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->index('rating');
            $table->index(['rating', 'product_id']);
        });


        Schema::table('category_product', function (Blueprint $table) {
            $table->index(['category_id', 'product_id']);
        });
        Schema::table('product_variants', function (Blueprint $table) {
            $table->index('product_id');
            $table->index('sku');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
        Schema::table('cart_items', function (Blueprint $table) {
            $table->index(['cart_id', 'product_id', 'product_variant_id']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'orders_user_id_created_at_index');
            $table->index('status', 'orders_status_index');
            $table->index('shipping_method');
        });

        Schema::table('order_products', function (Blueprint $table) {
            $table->index('order_id', 'order_products_order_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['price']);
            $table->dropIndex(['sold_quantity']);
            $table->dropIndex(['name']);
            $table->dropIndex(['slug']);
            $table->dropIndex(['sku']);
            $table->dropIndex(['is_fast_shipping_available']);
        });
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
            $table->dropIndex(['sku']);
        });
        Schema::table('carts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['status', 'expires_at']);
        });
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex(['cart_id', 'product_id', 'product_variant_id']);
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['rating']);
            $table->dropIndex(['rating', 'product_id']);
        });

        Schema::table('category_product', function (Blueprint $table) {
            $table->dropIndex(['category_id', 'product_id']);
            $table->dropIndex(['product_id', 'category_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_user_id_created_at_index');
            $table->dropIndex('orders_status_index');
            $table->dropIndex('shipping_method');
        });

        Schema::table('order_products', function (Blueprint $table) {
            $table->dropIndex('order_products_order_id_index');
        });
    }
}
