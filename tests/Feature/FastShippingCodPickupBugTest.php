<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Marvel\Database\Models\User;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Cart;
use Marvel\Enums\ProductType;
use Tests\Concerns\CreatesTestTables;
use Tests\TestCase;
use Illuminate\Support\Str;

class FastShippingCodPickupBugTest extends TestCase
{
    use DatabaseTransactions, CreatesTestTables;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
        RateLimiter::for('public-api', fn () => Limit::none());
        RateLimiter::for('authenticated', fn () => Limit::none());
        RateLimiter::for('cart', fn () => Limit::none());
        RateLimiter::for('api', fn () => Limit::none());
        $this->createAllTestTables();
        if (!Schema::hasTable('fast_shipping_settings')) {
            Schema::create('fast_shipping_settings', function ($t) {
                $t->id();
                $t->boolean('is_enabled')->default(true);
                $t->decimal('extra_fee',10,2)->default(0);
                $t->timestamps();
            });
            DB::table('fast_shipping_settings')->insert(['is_enabled'=>true,'extra_fee'=>10,'created_at'=>now(),'updated_at'=>now()]);
        }
        if (!Schema::hasTable('governorates')) {
            Schema::create('governorates', function ($t) { $t->id(); $t->string('name'); $t->timestamps(); });
            DB::table('governorates')->insert(['name'=>'Cairo','created_at'=>now(),'updated_at'=>now()]);
        }
        if (!Schema::hasTable('pickup_locations')) {
            Schema::create('pickup_locations', function ($t) {
                $t->id(); $t->string('store_name'); $t->text('address'); $t->string('phone'); $t->timestamps();
            });
            DB::table('pickup_locations')->insert(['store_name'=>'Test','address'=>'addr','phone'=>'0100','created_at'=>now(),'updated_at'=>now()]);
        }
    }

    public function test_cod_with_pickup_creates_order_before_validation_bug(): void
    {
        $user = User::create([
            'name'=>'Bug User','email'=>'bug-'.Str::random(6).'@example.com','password'=>Hash::make('password'),
            'is_active'=>true,'type'=>'user','phone_number'=>'0109'.rand(1000000,9999999)
        ]);
        Sanctum::actingAs($user);
        $product = Product::create([
            'name'=>'FastProd','slug'=>'fast-'.Str::random(6),'price'=>100,'product_type'=>ProductType::SIMPLE,'status'=>true,'in_stock'=>true,'stock_quantity'=>20,'quantity'=>20
        ]);
        $cart = Cart::create(['user_id'=>$user->id,'status'=>'active','total_price'=>100]);
        DB::table('cart_items')->insert(['cart_id'=>$cart->id,'product_id'=>$product->id,'quantity'=>1,'price'=>100,'total_price'=>100,'created_at'=>now(),'updated_at'=>now()]);

        $beforeCount = DB::table('orders')->where('user_id',$user->id)->count();
        $res = $this->postJson('/api/v1/general/fast-shipping/checkout', [
            'payment_method'=>'cod',
            'fulfillment_type'=>'pickup',
            'pickup_location_id'=>1,
            'governorate_id'=>1,
            'address'=>'test','user_phone'=>'01000000000','user_email'=>'a@a.com','name'=>'Test'
        ]);
        $afterCount = DB::table('orders')->where('user_id',$user->id)->count();

        // BUG: order is created even though cod+pickup should be rejected BEFORE order creation
        // Expected: 422 and afterCount == beforeCount (no order created)
        // Actual: 422 but afterCount == beforeCount+1 (order leaked)
        // If bug exists, this test will show afterCount > beforeCount
        // We assert the *expected* correct behavior; failure proves bug
        $res->assertStatus(422);
        $this->assertEquals($beforeCount, $afterCount, "BUG CONFIRMED: Order was created despite cod+pickup rejection. Before=$beforeCount After=$afterCount");
    }
}
