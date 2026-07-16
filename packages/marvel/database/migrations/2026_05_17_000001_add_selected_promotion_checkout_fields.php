<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            if (!Schema::hasColumn('promotions', 'discount')) {
                $table->decimal('discount', 10, 2)->nullable()->after('value');
            }

            if (!Schema::hasColumn('promotions', 'minimum_order_amount')) {
                $table->decimal('minimum_order_amount', 10, 2)->default(0)->after('required_quantity_type');
            }

            if (!Schema::hasColumn('promotions', 'apply_to')) {
                $table->string('apply_to')->default('specific_products')->after('minimum_order_amount');
            }

            $table->index(['status', 'start_at', 'end_at'], 'promotions_validity_index');
            $table->index(['usage', 'limiter'], 'promotions_usage_limiter_index');
        });

        if (!Schema::hasTable('promotion_gift_products')) {
            Schema::create('promotion_gift_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
                $table->unsignedInteger('quantity')->default(1);
                $table->timestamps();

                $table->unique(['promotion_id', 'product_id']);
                $table->index('product_id');
                $table->index('product_variant_id');
            });
        }

        Schema::table('cart_items', function (Blueprint $table) {
            if (!Schema::hasColumn('cart_items', 'is_gift')) {
                $table->boolean('is_gift')->default(false)->after('attributes');
            }

            if (!Schema::hasColumn('cart_items', 'promotion_id')) {
                $table->foreignId('promotion_id')->nullable()->after('is_gift')->constrained('promotions')->nullOnDelete();
            }

            $table->index(['cart_id', 'is_gift'], 'cart_items_cart_gift_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'promotion_id')) {
                $table->foreignId('promotion_id')->nullable()->after('coupon_discount_max_amount')->constrained('promotions')->nullOnDelete();
            }

            if (!Schema::hasColumn('orders', 'promotion_code')) {
                $table->string('promotion_code')->nullable()->after('promotion_id');
            }

            if (!Schema::hasColumn('orders', 'promotion_type')) {
                $table->string('promotion_type')->nullable()->after('promotion_code');
            }

            if (!Schema::hasColumn('orders', 'promotion_discount')) {
                $table->decimal('promotion_discount', 10, 3)->default(0)->after('promotion_type');
            }
        });

        Schema::table('order_products', function (Blueprint $table) {
            if (!Schema::hasColumn('order_products', 'is_gift')) {
                $table->boolean('is_gift')->default(false)->after('product_flash_sale_price');
            }

            if (!Schema::hasColumn('order_products', 'promotion_id')) {
                $table->foreignId('promotion_id')->nullable()->after('is_gift')->constrained('promotions')->nullOnDelete();
            }

            $table->index(['order_id', 'is_gift'], 'order_products_order_gift_index');
        });
    }

    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            $table->dropIndex('order_products_order_gift_index');
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn('is_gift');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn(['promotion_code', 'promotion_type', 'promotion_discount']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex('cart_items_cart_gift_index');
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn('is_gift');
        });

        Schema::dropIfExists('promotion_gift_products');

        Schema::table('promotions', function (Blueprint $table) {
            $table->dropIndex('promotions_validity_index');
            $table->dropIndex('promotions_usage_limiter_index');
            $table->dropColumn(['discount', 'minimum_order_amount', 'apply_to']);
        });
        Schema::table('promotion_gift_products', function (Blueprint $table) {
            $table->dropIndex('product_variant_id');
            $table->dropIndex('product_id');
        });
    }
};
