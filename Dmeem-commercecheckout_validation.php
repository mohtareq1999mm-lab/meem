<?php
/**
 * Checkout Validation Script
 * Tests real checkout flows with real database data.
 * Rollback: Cleans up created carts and test data.
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Cart;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\Transaction;
use Marvel\Database\Models\Coupon;
use Marvel\Database\Models\Promotion;
use Marvel\Database\Models\CartItem;
use Marvel\Enums\ShippingMethod;

echo str_repeat('=', 80) . PHP_EOL;
echo 'CHECKOUT PRODUCTION VALIDATION' . PHP_EOL;
echo 'Date: ' . date('Y-m-d H:i:s') . PHP_EOL;
echo str_repeat('=', 80) . PHP_EOL . PHP_EOL;

// ===== TEST DATA =====
$userId = 3;
$productId = 79; // Matte Bronzer, $32.00, stock=106, no discount, no flash sale
$couponCode = 'SUMMER20'; // 20% off, max $100
$governorateId = 1; // Cairo, shipping=$50, free over $500
$quantity = 2;

echo "--- TEST DATA ---" . PHP_EOL;
echo "User: ID $userId (Test Customer)" . PHP_EOL;
$product = Product::find($productId);
echo "Product: {$product->id} - \${$product->price} (stock: {$product->stock_quantity}, reserved: {$product->reserved_quantity})\n";
echo "Coupon: $couponCode\n";
echo "Quantity: $quantity\n";
echo "Shipping: Governorate $governorateId (shipping fee query)\n\n";

// ===== STEP 1: BEGIN TEST - Capture state BEFORE =====
echo str_repeat('-', 60) . PHP_EOL;
echo "STEP 1: PRE-TEST STATE" . PHP_EOL;
echo str_repeat('-', 60) . PHP_EOL;

$initialProduct = Product::find($productId);
echo "Product 79 BEFORE: stock={$initialProduct->stock_quantity} reserved={$initialProduct->reserved_quantity} sold={$initialProduct->sold_quantity}\n";

// Check if user has active cart
$user = \Marvel\Database\Models\User::find($userId);
$existingCart = $user->cart;
if ($existingCart && $existingCart->status === 'active') {
    echo "Existing active cart found (id={$existingCart->id}), clearing...\n";
    // Release reservations
    $existingCart->items()->delete();
    $existingCart->update(['total_price' => 0, 'coupon' => null]);
}
echo PHP_EOL;

// ===== STEP 2: ADD ITEM TO CART =====
echo str_repeat('-', 60) . PHP_EOL;
echo "STEP 2: ADD ITEM TO CART" . PHP_EOL;
echo str_repeat('-', 60) . PHP_EOL;

try {
    $cartService = app(\App\Services\General\CartInventoryService::class);
    $cartRepo = app(\Marvel\Database\Repositories\CartRepository::class);
    
    $request = new Illuminate\Http\Request();
    $request->setUserResolver(function() use ($user) { return $user; });
    $request->merge([
        'item' => [[
            'product_id' => $productId,
            'quantity' => $quantity,
        ]],
    ]);
    
    // Add item to cart via the storeCart method
    $cart = $cartRepo->storeCart($request);
    echo "Cart created/updated: id={$cart->id}, status={$cart->status}, total_price={$cart->total_price}\n";
    
    foreach ($cart->items as $item) {
        echo "  Item: product_id={$item->product_id}, qty={$item->quantity}, price={$item->price}, total={$item->total_price}, discount={$item->discount_amount}\n";
    }
    
    // Check inventory after add
    $productAfterAdd = Product::find($productId);
    echo "Product 79 AFTER ADD: stock={$productAfterAdd->stock_quantity} reserved={$productAfterAdd->reserved_quantity} sold={$productAfterAdd->sold_quantity}\n";
    
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
echo PHP_EOL;

// ===== STEP 3: APPLY COUPON =====
echo str_repeat('-', 60) . PHP_EOL;
echo "STEP 3: APPLY COUPON {$couponCode}" . PHP_EOL;
echo str_repeat('-', 60) . PHP_EOL;

try {
    $couponService = app(\Marvel\Database\Repositories\CouponRepository::class);
    
    // Act as the user
    auth()->login($user);
    
    $result = $couponService->addCouponToCart($couponCode);
    
    // Reload cart
    $cart = $user->cart->load('items.product');
    echo "Cart after coupon: total_price={$cart->total_price}, coupon={$cart->coupon}\n";
    
    $coupon = Coupon::where('code', $couponCode)->first();
    echo "Coupon {$couponCode}: discount_type={$coupon->discount_type}, discount={$coupon->discount}, max_discount={$coupon->max_discount_amount}, used={$coupon->used}\n";
    
} catch (\Throwable $e) {
    echo "ERROR applying coupon: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
echo PHP_EOL;

// ===== STEP 4: CHECKOUT (COD - cash on delivery) =====
echo str_repeat('-', 60) . PHP_EOL;
echo "STEP 4: CHECKOUT" . PHP_EOL;
echo str_repeat('-', 60) . PHP_EOL;

try {
    $orderController = app(\App\Http\Controllers\Api\General\OrderController::class);
    
    $checkoutRequest = new \Marvel\Http\Requests\OrderCreateRequest();
    $checkoutRequest->setUserResolver(function() use ($user) { return $user; });
    $checkoutRequest->merge([
        'name' => 'Test Customer',
        'user_phone' => '1234567890',
        'user_email' => 'test@g.com',
        'address' => ['street' => '123 Test St', 'city' => 'Cairo', 'country' => 'Egypt'],
        'payment_method' => 'cod',
        'fulfillment_type' => 'delivery',
        'governorate_id' => $governorateId,
    ]);
    
    $response = $orderController->checkout($checkoutRequest);
    $responseData = json_decode($response->getContent(), true);
    
    echo "Checkout response: " . json_encode($responseData, JSON_PRETTY_PRINT) . PHP_EOL . PHP_EOL;
    
    // The response for COD should have success=true and order data
    if (isset($responseData['success']) && $responseData['success']) {
        echo "CHECKOUT SUCCESSFUL" . PHP_EOL;
        
        // Find the created order
        $latestOrder = Order::where('user_id', $userId)->latest()->first();
        echo "Order: id={$latestOrder->id}, status={$latestOrder->status}, total={$latestOrder->total_price}, payment_method={$latestOrder->payment_method}\n";
        
        // Check order items
        foreach ($latestOrder->orderItems as $oi) {
            echo "  OrderItem: product_id={$oi->product_id}, qty={$oi->product_quantity}, price={$oi->product_price}, total={$oi->product_total_price}\n";
            echo "    discount_price={$oi->product_discount_price}, promo_discount={$oi->promotion_discount_amount}\n";
        }
        
        // Check transaction
        $transaction = Transaction::where('order_id', $latestOrder->id)->first();
        if ($transaction) {
            echo "Transaction: id={$transaction->id}, status={$transaction->status}, amount={$transaction->amount}, method={$transaction->payment_method}\n";
        } else {
            echo "Transaction: NONE\n";
        }
        
        // Check cart state after checkout
        $cart = Cart::find($initialProduct->id ?? $cart->id ?? 0);
        if (!$cart) {
            $cart = $user->cart;
        }
        if ($cart) {
            echo "Cart after checkout: id={$cart->id}, status={$cart->status}\n";
            echo "Cart items count: " . $cart->items()->count() . "\n";
        } else {
            echo "Cart after checkout: NOT FOUND\n";
        }
        
        // Check inventory after checkout
        $productAfterCheckout = Product::find($productId);
        echo "Product 79 AFTER CHECKOUT: stock={$productAfterCheckout->stock_quantity} reserved={$productAfterCheckout->reserved_quantity} sold={$productAfterCheckout->sold_quantity}\n";
        
        // Manual calculation
        echo PHP_EOL . "--- MANUAL CALCULATION ---" . PHP_EOL;
        $basePrice = (float) $initialProduct->price;
        $lineTotal = $basePrice * $quantity;
        echo "Base price: \${$basePrice} x {$quantity} = \${$lineTotal}\n";
        
        // Coupon: SUMMER20 = 20% off, max discount $100
        $coupon = Coupon::where('code', $couponCode)->first();
        $couponDiscountPct = (float) $coupon->discount; // 20%
        $couponMax = (float) $coupon->max_discoun
