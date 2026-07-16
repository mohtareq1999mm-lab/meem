<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Marvel\Database\Models\Cart;
use Marvel\Database\Models\CartItem;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\User;

class CartSeeder extends Seeder
{
    public function run()
    {
        $user = User::first();
        $products = Product::take(2)->get();

        if (!$user || $products->isEmpty()) {
            return;
        }

        $cart = Cart::updateOrCreate([
            'user_id' => $user->id,
        ], [
            'status' => 'active',
            'reserved_at' => now(),
            'expires_at' => now()->addDays(3),
            'total_price' => 0,
        ]);

        CartItem::where('cart_id', $cart->id)->delete();

        foreach ($products as $product) {
            $quantity = 1;
            $price = $product->getCurrentPrice();

            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'reserved_quantity' => $quantity,
                'price' => $price,
                'total_price' => $price * $quantity,
            ]);
        }

        $cart->update([
            'total_price' => $cart->items()->sum('total_price'),
        ]);
    }
}
