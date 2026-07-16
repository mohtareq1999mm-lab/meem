<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\Coupon;
use Marvel\Database\Models\Product;

class CouponProductSeeder extends Seeder
{
    public function run(): void
    {
        $couponIds = Coupon::pluck('id')->toArray();
        $productIds = Product::pluck('id')->toArray();

        if (empty($couponIds) || empty($productIds)) {
            return;
        }

        foreach ($productIds as $productId) {
            $count = rand(1, min(3, count($couponIds)));
            $selected = (array) array_rand(array_flip($couponIds), $count);

            foreach ($selected as $couponId) {
                DB::table('coupon_product')->insertOrIgnore([
                    'coupon_id' => $couponId,
                    'product_id' => $productId,
                ]);
            }
        }
    }
}
