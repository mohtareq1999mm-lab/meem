<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$request = Illuminate\Http\Request::capture();
$request->server->set('REMOTE_ADDR', '127.0.0.1');
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);

use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Cart;
use Marvel\Database\Models\CartItem;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\Transaction;

$userId = 3;
$productId = 79;
$couponCode = 'SUMMER20';
$quantity = 2;

$user = \Marvel\Database\Models\User::find($userId);
Auth::guard('web')->loginUsingId($userId);

echo "=== DIRECT SERVICE TEST ===\n\n";

// STEP 1: Clean cart
echo "--- STEP 1: CLEAN CART ---\n";
$cart = $user->cart;
if ($cart) {
    try {
        $inv = app(\App\Services\General\CartInventoryService::class);
        $inv->releaseCart($cart, true);
    } catch (\Throwable $e) { echo "releaseCart: " . $e->getMessage() . "\n"; }
    $cart->items()->delete();
    $cart->forceDelete();
    echo "Old cart removed.\n";
}
echo "Cart count: " . Cart::where('user_id', $userId)->count() . "\n\n";

// STEP 2: Create cart and add item
echo "--- STEP 2: ADD TO CART ---\n";
$product = Product::find($productId);
echo "Product: price={$product->price} stock={$product->stock_quantity} reserved={$product->reserved_quantity}\n";

$newCart = Cart::create([
    'user_id' => $userId,
    'status' => 'active',
    'total_price' => 0,
]);

$cartItem = CartItem::create([
    'cart_id' => $newCart->id,
    'product_id' => $productId,
    'quantity' => $quantity,
    'price' => $product->price,
    'total_price' => $product->price * $quantity,
    'reserved_quantity' => $quantity,
    'shipping_method' => 'SCHEDULED',
]);
$newCart->update(['total_price' => $product->price * $quantity]);

$product->reserved_quantity = $product->reserved_quantity + $quantity;
$product->save();

$product = Product::find($productId);
echo "Product after add: stock={$product->stock_quantity} reserved={$product->reserved_quantity}\n";
echo "Reservation: " . ($product->reserved_quantity == $quantity ? "PASS" : "FAIL") . "\n\n";

// STEP 3: Apply coupon
echo "--- STEP 3: APPLY COUPON ---\n";
$newCart->update(['coupon' => $couponCode]);
echo "Cart coupon: " . $newCart->fresh()->coupon . "\n\n";

// STEP 4: Checkout
echo "--- STEP 4: CHECKOUT ---\n";
$orderService = app(\App\Services\General\OrderService::class);
$request = new Illuminate\Http\Request();
$request->setUserResolver(function() use ($user) { return $user; });
$request->merge([
    'name' => 'Test Customer',
    'user_phone' => '1234567890',
    'user_email' => 'test@g.com',
    'address' => ['street' => '123 Test St', 'city' => 'Cairo', 'country' => 'Egypt'],
    'payment_method' => 'cod',
    'fulfillment_type' => 'delivery',
    'governorate_id' => 1,
]);

try {
    $order = $orderService->addItemsInOrder($request);
    if ($order) {
        echo "ORDER: id={$order->id} status={$order->status}\n";
        echo "  price={$order->price} shipping={$order->shipping_price} total={$order->total_price}\n";
        echo "  coupon={$order->coupon} coupon_discount={$order->coupon_discount}\n";
        
        foreach ($order->orderItems as $oi) {
            echo "  Item: pid={$oi->product_id} qty={$oi->product_quantity} price={$oi->product_price} total={$oi->product_total_price}\n";
        }
        
        // Manual check
        $lineTotal = (float)$product->price * $quantity;
        $couponDiscount = min($lineTotal * 0.20, 100.0);
        $afterCoupon = $lineTotal - $couponDiscount;
        $shippingPrice = DB::table('shipping_prices')->where('governorate_id', 1)->first();
        $shipping = (float)$shippingPrice->price;
        if ($afterCoupon >= (float)$shippingPrice->free_shipping_over) $shipping = 0;
        $grandTotal = $afterCoupon + $shipping;
        
        echo "\nManual: lineTotal={$lineTotal} couponDiscount={$couponDiscount} afterCoupon={$afterCoupon} shipping={$shipping} grandTotal={$grandTotal}\n";
        echo "Order : price={$order->price} coupon_discount={$order->coupon_discount} shipping={$order->shipping_price} total={$order->total_price}\n\n";
        
        $priceOk = abs((float)$order->price - $afterCoupon) < 0.01;
        $couponOk = abs((float)$order->coupon_discount - $couponDiscount) < 0.01;
        echo "Price check: " . ($priceOk ? "PASS" : "FAIL") . "\n";
        echo "Coupon check: " . ($couponOk ? "PASS" : "FAIL") . "\n";
        
        // Inventory
        $pa = Product::find($productId);
        echo "\nInventory: stock={$pa->stock_quantity} reserved={$pa->reserved_quantity} sold={$pa->sold_quantity}\n";
        echo "Expected stock: " . (106 - $quantity) . " actual: {$pa->stock_quantity} ";
        echo (abs($pa->stock_quantity - (106 - $quantity)) < 0.01 ? "PASS" : "FAIL") . "\n";
        
        // Cart
        $cartAfter = Cart::find($newCart->id);
        if ($cartAfter) {
            echo "Cart: status={$cartAfter->status} items=" . $cartAfter->items()->count() . "\n";
        }
        
        // Transaction
        $txn = Transaction::where('order_id', $order->id)->first();
        if ($txn) {
            echo "Transaction: status={$txn->status} amount={$txn->amount}\n";
        }
        
        // Coupon usage
        $coupon = \Marvel\Database\Models\Coupon::where('code', $couponCode)->first();
        echo "Coupon usage: used={$coupon->used}\n";
        $couponUsageCount = DB::table('coupon_usages')->where('coupon_id', $coupon->id)->where('order_id', $order->id)->count();
        echo "Coupon usages table rows: {$couponUsageCount} (coupon consumed only on 'completed' status)\n";
        
    } else {
        echo "ORDER: null\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getFile() . ":" . $e->getLine() . "\n";
}

// Check the RestoreProductInventory listener file
echo "\n\n=== LISTENER CHECK ===\n";
$listenerPath = __DIR__ . '/app/Listeners/RestoreProductInventory.php';
$listenerContent = file_get_contents($listenerPath);
if (preg_match('/function handle/', $listenerContent)) {
    echo "RestoreProductInventory has handle()\n";
}
if (strpos($listenerContent, 'inventory_restored_at') !== false) {
    echo "Uses inventory_restored_at for lock\n";
}

// Check EventServiceProvider for OrderCancelled listeners
$espPath = __DIR__ . '/app/Providers/EventServiceProvider.php';
$espContent = file_get_contents($espPath);
preg_match_all('/OrderCancelled::class\s*=>\s*\[([^\]]+)\]/s', $espContent, $matches);
foreach ($matches[1] ?? [] as $listeners) {
    echo "OrderCancelled listeners: $listeners\n";
}

echo "\n=== CLEANUP ===\n";
// Don't clean up - let the user inspect the state
// The order can be cleaned manually if needed

echo "\nDONE\n";
