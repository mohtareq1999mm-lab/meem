<?php

namespace App\Services\Checkout;

use App\DTOs\CheckoutTotals;
use App\Events\OrderCreated;
use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\Cart;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\PickupLocation;
use Marvel\Database\Models\Promotion;

class OrderCreationService
{
    public function __construct(
        private \App\Services\General\PromotionService $promotionService,
    ) {}

    public function createOrder(array $orderData, Cart $cart, CheckoutTotals $checkoutTotals, ?string $shippingMethod = null, ?\DateTime $eta = null, ?float $fastShippingFee = null, ?float $shippingPrice = null, ?int $governorateId = null): ?Order
    {
        $shippingPrice = $shippingPrice ?? 0;
        $totalPrice = round((float) $checkoutTotals->finalTotal + $shippingPrice + ($fastShippingFee ?? 0), 2);

        $pickupLocationId = $orderData['pickup_location_id'] ?? null;
        $pickupSnapshot = $this->resolvePickupLocationSnapshot($pickupLocationId);

        $order = Order::create([
            'user_id' => $orderData['user_id'] ?? auth()->id(),
            'governorate_id' => $governorateId ?? $orderData['governorate_id'] ?? null,
            'name' => $orderData['name'] ?? null,
            'user_phone' => $orderData['user_phone'] ?? null,
            'user_email' => $orderData['user_email'] ?? null,
            'address' => $orderData['address'] ?? null,
            'notes' => $orderData['notes'] ?? null,
            'shipping_method' => $shippingMethod ?? 'SCHEDULED',
            'expected_delivery_at' => $eta,
            'fast_shipping_fee' => $fastShippingFee ?? 0,
            'fulfillment_type' => $orderData['fulfillment_type'] ?? 'delivery',
            'payment_method' => $orderData['payment_method'] ?? 'online',
            'payment_gateway' => $orderData['payment_gateway'] ?? null,
            'pickup_location_id' => $pickupLocationId,
            'pickup_location_name' => $pickupSnapshot['name'],
            'pickup_location_address' => $pickupSnapshot['address'],
            'pickup_location_phone' => $pickupSnapshot['phone'],
            'pickup_location_coordinates' => $pickupSnapshot['coordinates'],
            'price' => $checkoutTotals->subtotal,
            'shipping_price' => $shippingPrice,
            'total_price' => $totalPrice,
            'coupon' => $checkoutTotals->coupon ?? $cart->coupon ?? null,
            'coupon_discount' => $checkoutTotals->couponDiscount ?: null,
            'coupon_discount_type' => $checkoutTotals->couponDiscountType,
            'coupon_discount_max_amount' => $checkoutTotals->couponDiscountMaxAmount,
            'promotion_id' => $checkoutTotals->promotionId(),
            'promotion_code' => $checkoutTotals->promotionCode(),
            'promotion_type' => $checkoutTotals->promotionType(),
            'promotion_discount' => $checkoutTotals->promotionDiscount,
            'status' => 'pending',
        ]);

        if (!$order) {
            return null;
        }

        return $order;
    }

    public function createOrderItems(Order $order, Cart $cart): bool
    {
        foreach ($cart->items as $item) {
            try {
                $quantity = max(1, (int) ($item->quantity ?? 0));
                $lineTotal = (float) ($item->total_price ?? 0);
                $effectiveUnitPrice = $quantity > 0 ? $lineTotal / $quantity : 0;
                $promotionDiscountAmount = round(max(0, ((float) ($item->price ?? 0) * $quantity) - $lineTotal), 2);

                $product = $item->product ?? null;
                $variant = $item->productVariant ?? null;
                $productName = $product->name ?? 'No Name';
                $productSku = $product->sku ?? null;

                $pricingService = app(\Marvel\Services\Pricing\ProductPricingService::class);
                $flashSale = $pricingService->resolveActiveFlashSale($product);

                if ($variant && $variant->price !== null) {
                    $basePrice = (float) $variant->price;
                    $flashSalePrice = $pricingService->calculateFlashSalePrice($flashSale, $basePrice);
                    $discountPrice = $flashSalePrice === null && $product->has_discount && $pricingService->isDiscountActive($product)
                        ? $pricingService->calculateDiscountedPrice($basePrice, $product->discount_type ?? 'percentage', $product->discount_amount ?? 0)
                        : null;
                } else {
                    $pricing = $pricingService->calculateProductPricing($product, $flashSale);
                    $flashSalePrice = $pricing['price_after_flash_sale'];
                    $discountPrice = $flashSalePrice === null && $product->has_discount && $pricingService->isDiscountActive($product)
                        ? $pricingService->calculateDiscountedPrice($product->price, $product->discount_type ?? 'percentage', $product->discount_amount ?? 0)
                        : null;
                }

                $orderItem = $order->orderItems()->create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => $productName,
                    'product_quantity' => $quantity,
                    'product_price' => $effectiveUnitPrice,
                    'product_total_price' => round($lineTotal, 2),
                    'product_sku' => $productSku,
                    'product_flash_sale_price' => $flashSalePrice,
                    'product_discount_price' => $discountPrice,
                    'promotion_discount_amount' => $promotionDiscountAmount,
                    'attributes' => $item->attributes ?? null,
                    'is_gift' => (bool) ($item->is_gift ?? false),
                    'promotion_id' => $item->promotion_id,
                ]);

                if (!$orderItem) {
                    return false;
                }
            } catch (\Exception $e) {
                report($e);
                return false;
            }
        }
        return true;
    }

    private function resolvePickupLocationSnapshot(?int $pickupLocationId): array
    {
        if (!$pickupLocationId) {
            return [
                'name' => null,
                'address' => null,
                'phone' => null,
                'coordinates' => null,
            ];
        }

        $location = PickupLocation::withTrashed()->find($pickupLocationId);

        if (!$location) {
            return [
                'name' => null,
                'address' => null,
                'phone' => null,
                'coordinates' => null,
            ];
        }

        $coordinates = null;
        if ($location->latitude && $location->longitude) {
            $coordinates = $location->latitude . ',' . $location->longitude;
        }

        return [
            'name' => $location->store_name,
            'address' => $location->address,
            'phone' => $location->phone,
            'coordinates' => $coordinates,
        ];
    }

    public function finalizeOrder(Order $order, CheckoutTotals $checkoutTotals): void
    {
        $this->promotionService->incrementUsage($checkoutTotals->promotionId());

        try {
            OrderCreated::dispatch($order);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
