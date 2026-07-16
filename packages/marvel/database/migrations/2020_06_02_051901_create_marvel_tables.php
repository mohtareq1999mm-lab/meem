<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Marvel\Enums\DiscountType;
use Marvel\Enums\ProductType;
use Marvel\Enums\ShippingType;

class CreateMarvelTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Schema::create('shipping_classes', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('name');
        //     $table->double('amount');
        //     $table->string('is_global')->default(true);
        //     $table->enum('type', ShippingType::getValues())->default(ShippingType::FIXED);
        //     $table->timestamps();
        // });
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('slug');
            $table->enum('discount_type', DiscountType::getValues())->nullable();
            $table->decimal('discount', 8, 3)->nullable();
            $table->decimal('max_discount_amount', 10, 2)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('limiter')->nullable();
            $table->integer('used')->default(0);
            $table->boolean('status')->default(true);
            $table->string('border_color')->nullable();
            $table->boolean('borderless')->default(false);
            $table->timestamps();
        });


        // Schema::create('types', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('name');
        //     $table->string('slug');
        //     $table->string('icon')->nullable();
        //     $table->json('promotional_sliders')->nullable();
        //     $table->json('images')->nullable();
        //     $table->timestamps();
        // });

        // Schema::create('authors', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('name');
        //     $table->boolean('is_approved')->default(false);
        //     $table->json('image')->nullable();
        //     $table->json('cover_image')->nullable();
        //     $table->string('slug');
        //     $table->text('bio')->nullable();
        //     $table->text('quote')->nullable();
        //     $table->string('born')->nullable();
        //     $table->string('death')->nullable();
        //     $table->string('languages')->nullable();
        //     $table->json('socials')->nullable();
        //     $table->timestamps();
        // });

        // Schema::create('manufacturers', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('name');
        //     $table->boolean('is_approved')->default(false);
        //     $table->json('image')->nullable();
        //     $table->json('cover_image')->nullable();
        //     $table->string('slug');
        //     $table->unsignedBigInteger('type_id');
        //     $table->foreign('type_id')->references('id')->on('types')->onDelete('cascade');
        //     $table->text('description')->nullable();
        //     $table->string('website')->nullable();
        //     $table->json('socials')->nullable();
        //     $table->timestamps();
        // });
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug');
            $table->integer('order');
            $table->text('description')->nullable();
            $table->boolean('status')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('sliders', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('slug');
            $table->integer('order');
            $table->boolean('status')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('sku')->nullable();
            $table->integer('quantity')->default(0);
            $table->integer('sold_quantity')->default(0);
            $table->boolean('in_stock')->default(true);
            $table->boolean('status')->default(false);
            $table->enum('product_type', ProductType::getValues())->default(ProductType::SIMPLE);
            $table->string('height')->nullable();
            $table->string('width')->nullable();
            $table->string('length')->nullable();
            $table->string('weight')->nullable();
            $table->integer('pieces')->default(1);
            $table->boolean('has_flash_sale')->default(false);
            $table->boolean('has_discount')->default(false);
            $table->enum('discount_type', DiscountType::getValues())->default(DiscountType::PERCENTAGE);
            $table->double('discount_amount', 10, 2)->default(0);
            $table->boolean('discount_status')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('price_after_discount', 10, 2)->nullable();
            $table->decimal('price_after_flash_sale', 10, 2)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->boolean('is_fast_shipping_available')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->nullable()->unique();
            $table->double('price', 10, 2);
            $table->double('sale_price', 10, 2)->nullable();
            $table->boolean('in_stock')->default(true);
            $table->integer('quantity')->default(0);
            $table->string('height')->nullable();
            $table->string('width')->nullable();
            $table->string('weight')->nullable();
            $table->string('length')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->integer('stock_quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->integer('sold_quantity')->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('user_phone');
            $table->string('user_email');
            $table->json('address');
            $table->string('notes')->nullable();
            $table->decimal('shipping_price', 8, 3)->nullable();
            $table->decimal('total_price', 8, 3);
            $table->decimal('price', 8, 3);
            $table->string('coupon')->nullable();
            $table->decimal('coupon_discount', 10, 3)->nullable();
            $table->string('coupon_discount_type')->nullable();
            $table->decimal('coupon_discount_max_amount', 10, 3)->nullable();
            $table->enum('status', ['pending', 'completed', 'delivered', 'cancelled'])->default('pending');
            $table->enum('shipping_method', ['SCHEDULED', 'FAST'])->default('SCHEDULED');
            $table->dateTime('expected_delivery_at')->nullable();
            $table->decimal('fast_shipping_fee', 12, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('order_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('product_sku')->nullable();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->json('attributes')->nullable();
            $table->integer('product_quantity');
            $table->decimal('product_price', 8, 3);
            $table->decimal('product_total_price', 8, 3);
            $table->decimal('product_discount_price', 10, 3)->nullable();
            $table->decimal('product_flash_sale_price', 10, 3)->nullable();
            $table->decimal('promotion_discount_amount', 10, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->integer('invoice_id');
            $table->bigInteger('user_id');
            $table->string('payment_method');
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug');
            $table->text('details')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('categories')->restrictOnDelete();
            $table->boolean('status')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('category_product', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('category_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->string('coupon')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('total_price', 10, 2)->default(0);
            $table->enum('status', ['active', 'expired', 'checked_out'])->default('active');
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unique('user_id');
            $table->timestamps();
        });


        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->unsignedBigInteger('attribute_id');
            $table->foreign('attribute_id')->references('id')->on('attributes')->onDelete('cascade');
            $table->string('value');
            $table->timestamps();
        });


        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->json('attributes')->nullable();
            $table->integer('reserved_quantity')->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->string('shipping_method', 20)->default('scheduled');
            $table->timestamps();
        });

        Schema::create('attribute_product', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attribute_value_id');
            $table->foreign('attribute_value_id')->references('id')->on('attribute_values')->onDelete('cascade');
            $table->unsignedBigInteger('product_variant_id');
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->onDelete('cascade');
            $table->timestamps();
        });


        // Schema::create('tax_classes', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('country')->nullable();
        //     $table->string('state')->nullable();
        //     $table->string('zip')->nullable();
        //     $table->string('city')->nullable();
        //     $table->double('rate');
        //     $table->string('name')->nullable();
        //     $table->integer('is_global')->nullable();
        //     $table->integer('priority')->nullable();
        //     $table->boolean('on_shipping')->default(1);
        //     $table->timestamps();
        // });

        Schema::create('address', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type');
            $table->boolean('default')->default(false);
            $table->json('address');
            $table->json('location')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('users');
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name');
            $table->text('site_desc')->nullable();
            $table->text('meta_desc')->nullable();
            $table->string('site_copy_right')->nullable();
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->string('site_email')->nullable();
            $table->string('email_support')->nullable();
            $table->string('facebook')->nullable();
            $table->string('instagram')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('promotion_video_url')->nullable();
            $table->string('youtube')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('fast_shipping_page_publish')->default(true);
            $table->json('options')->nullable();
            $table->timestamps();
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->json('avatar')->nullable();
            $table->text('bio')->nullable();
            $table->json('socials')->nullable();
            $table->string('contact')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('users');
            $table->timestamps();
        });



        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->string('url')->default('');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Schema::dropIfExists('shipping_classes');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('types');
        Schema::dropIfExists('products');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('order_products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attribute_product');
        // Schema::dropIfExists('tax_classes');
        Schema::dropIfExists('address');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('attachments');
        // Schema::dropIfExists('authors');
        // Schema::dropIfExists('manufacturers');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('sliders');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('cart_items');
    }
}
