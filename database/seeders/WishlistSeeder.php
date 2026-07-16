<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\ProductVariant;
use Marvel\Database\Models\User;
use Marvel\Database\Models\Wishlist;

class WishlistSeeder extends Seeder
{
    public function run()
    {
        $customer = User::where('email', 'test@g.com')->first();
        if (!$customer) {
            return;
        }

        $products = Product::inRandomOrder()->take(5)->get();
        if ($products->isEmpty()) {
            return;
        }

        foreach ($products as $product) {
            $variant = ProductVariant::where('product_id', $product->id)->first();

            Wishlist::create([
                'user_id' => $customer->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
            ]);
        }
    }
}
