<?php

namespace App\Services\Checkout;

use App\DTOs\CheckoutTotals;
use App\DTOs\CurrencyConversionResult;
use App\Events\OrderCreated;
use App\Exceptions\CurrencyRateNotFoundException;
use App\Services\Currency\CurrencyService;
use Illuminate\Support\Facades\Schema;
use Marvel\Database\Models\Cart;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\PickupLocation;

class OrderCreationService
{
    public function __construct(
        private \App\Services\General\PromotionService $promotionService,
        private CurrencyService $currencyService,
    ) {}

    public function findPendingOrderForUser(int $userId): ?Order
    {
        return Order::query()
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->lockForUpdate()
            ->first();
    }

    public function createOrder(array $orderData, Cart $cart, CheckoutTotals $checkoutTotals, ?string $shippingMethod = null, ?\DateTime $eta = null, ?float $fastShippingFee = null, ?float $shippingPrice = null, ?int $governorateId = null): ?Order
    {
        $shippingPrice = $shippingPrice ?? 0;
        $totalPrice = round((float) $checkoutTotals->finalTotal + $shippingPrice + ($fastShippingFee ?? 0), 2);

        $currencySnapshot = $this->resolveCurrencySnapshot($totalPrice);

        $pickupLocationId = $orderData['pickup_location_id'] ?? null;
        $pickupSnapshot = $this->resolvePickupLocationSnapshot($pickupLocationId);

        $orderDataForCreate = [
            'user_id' => $orderData['user_id'] ?? auth()->id(),
            'governorate_id' => $governorateId ?? $orderData['governorate_id'] ?? null,
            'name' => $orderData['name'] ?? null,
            'user_phone' => $orderData['user_phone'] ?? null,
            'user_email' => $orderData['user_email'] ?? null,
            'address' => $orderData['address'] ?? null,
            'notes' => $orderData['notes'] ?? null,
'shipping_method' => $shippingMethod ?? 'SCHEDULED',
            'expected_delivery_at' => $eta,
            'fast_shipping_fee' => $this->convertToEffective($fastShippingFee ?? 0),
            'fulfillment_type' => $orderData['fulfillment_type'] ?? 'delivery',
            'payment_method' => $orderData['payment_method'] ?? 'online',
            'payment_gateway' => $orderData['payment_gateway'] ?? null,
            'pickup_location_id' => $pickupLocationId,
            'pickup_location_name' => $pickupSnapshot['name'],
            'pickup_location_address' => $pickupSnapshot['address'],
            'pickup_location_phone' => $pickupSnapshot['phone'],
'pickup_location_coordinates' => $pickupSnapshot['coordinates'],
            'price' => $this->convertToEffective($checkoutTotals->subtotal),
            'shipping_price' => $this->convertToEffective($shippingPrice),
            'total_price' => $totalPrice,
            'coupon' => $checkoutTotals->coupon ?? $cart->coupon ?? null,
            'coupon_discount' => $this->convertToEffective($checkoutTotals->couponDiscount ?: null),
            'coupon_discount_type' => $checkoutTotals->couponDiscountType,
            'coupon_discount_max_amount' => $checkoutTotals->couponDiscountMaxAmount,
            'promotion_id' => $checkoutTotals->promotionId(),
            'promotion_code' => $checkoutTotals->promotionCode(),
            'promotion_type' => $checkoutTotals->promotionType(),
            'promotion_discount' => $this->convertToEffective($checkoutTotals->promotionDiscount),
            'status' => Order::ORDER_STATUS_PENDING,
        ];

        if (Schema::hasColumn('orders', 'currency_code')) {
            $orderDataForCreate = array_merge($orderDataForCreate, [
                'total_price' => $currencySnapshot['total_price'],
                'currency_code' => $currencySnapshot['currency_code'],
                'base_currency_code' => $currencySnapshot['base_currency_code'],
                'catalog_currency_code' => $currencySnapshot['catalog_currency_code'],
                'currency_rate' => $currencySnapshot['currency_rate'],
                'currency_rate_date' => $currencySnapshot['currency_rate_date'],
                'converted_total_price' => $currencySnapshot['converted_total_price'],
            ]);
        }

        if (Schema::hasColumn('orders', 'payment_status')) {
            $orderDataForCreate['payment_status'] = Order::PAYMENT_STATUS_PENDING;
        }
        if (Schema::hasColumn('orders', 'fulfillment_status')) {
            $orderDataForCreate['fulfillment_status'] = Order::FULFILLMENT_STATUS_PENDING;
        }

        $order = Order::create($orderDataForCreate);

        if (!$order) {
            return null;
        }

        return $order;
    }

    public function updateOrder(Order $order, array $orderData, Cart $cart, CheckoutTotals $checkoutTotals, ?string $shippingMethod = null, ?\DateTime $eta = null, ?float $fastShippingFee = null, ?float $shippingPrice = null, ?int $governorateId = null): Order
    {
        $shippingPrice = $shippingPrice ?? 0;
        $totalPrice = round((float) $checkoutTotals->finalTotal + $shippingPrice + ($fastShippingFee ?? 0), 2);

        $currencySnapshot = $this->resolveCurrencySnapshot($totalPrice);

        $pickupLocationId = $orderData['pickup_location_id'] ?? $order->pickup_location_id;
        $pickupSnapshot = $this->resolvePickupLocationSnapshot($pickupLocationId);

        $updateData = [
            'governorate_id' => $governorateId ?? $orderData['governorate_id'] ?? $order->governorate_id,
            'name' => $orderData['name'] ?? $order->name,
            'user_phone' => $orderData['user_phone'] ?? $order->user_phone,
            'user_email' => $orderData['user_email'] ?? $order->user_email,
            'address' => $orderData['address'] ?? $order->address,
            'notes' => $orderData['notes'] ?? $order->notes,
            'shipping_method' => $shippingMethod ?? $order->shipping_method,
'expected_delivery_at' => $eta ?? $order->expected_delivery_at,
            'fast_shipping_fee' => $fastShippingFee !== null
                ? $this->convertToEffective($fastShippingFee)
                : $order->fast_shipping_fee,
            'fulfillment_type' => $orderData['fulfillment_type'] ?? $order->fulfillment_type,
            'payment_method' => $orderData['payment_method'] ?? $order->payment_method,
            'payment_gateway' => $orderData['payment_gateway'] ?? $order->payment_gateway,
            'pickup_location_id' => $pickupLocationId,
            'pickup_location_name' => $pickupSnapshot['name'],
            'pickup_location_address' => $pickupSnapshot['address'],
            'pickup_location_phone' => $pickupSnapshot['phone'],
'pickup_location_coordinates' => $pickupSnapshot['coordinates'],
            'price' => $this->convertToEffective($checkoutTotals->subtotal),
            'shipping_price' => $this->convertToEffective($shippingPrice),
            'total_price' => $totalPrice,
            'coupon' => $checkoutTotals->coupon ?? $cart->coupon ?? $order->coupon,
            'coupon_discount' => $checkoutTotals->couponDiscount !== null
                ? $this->convertToEffective($checkoutTotals->couponDiscount)
                : $order->coupon_discount,
            'coupon_discount_type' => $checkoutTotals->couponDiscountType ?? $order->coupon_discount_type,
            'coupon_discount_max_amount' => $checkoutTotals->couponDiscountMaxAmount ?? $order->coupon_discount_max_amount,
            'promotion_id' => $checkoutTotals->promotionId() ?? $order->promotion_id,
            'promotion_code' => $checkoutTotals->promotionCode() ?? $order->promotion_code,
            'promotion_type' => $checkoutTotals->promotionType() ?? $order->promotion_type,
            'promotion_discount' => $checkoutTotals->promotionDiscount !== null
                ? $this->convertToEffective($checkoutTotals->promotionDiscount)
                : $order->promotion_discount,
        ];

        if (Schema::hasColumn('orders', 'currency_code')) {
            $updateData = array_merge($updateData, [
                'total_price' => $currencySnapshot['total_price'],
                'currency_code' => $currencySnapshot['currency_code'],
                'base_currency_code' => $currencySnapshot['base_currency_code'],
                'catalog_currency_code' => $currencySnapshot['catalog_currency_code'],
                'currency_rate' => $currencySnapshot['currency_rate'],
                'currency_rate_date' => $currencySnapshot['currency_rate_date'],
                'converted_total_price' => $currencySnapshot['converted_total_price'],
            ]);
        }

        $order->update($updateData);

        return $order->fresh();
    }

    public function createOrderItems(Order $order, Cart $cart): bool
    {
        foreach ($cart->items as $item) {
            try {
$quantity = max(1, (int) ($item->quantity ?? 0));
                $lineTotal = (float) ($item->total_price ?? 0);
                $catalogUnitPrice = $quantity > 0 ? $lineTotal / $quantity : 0;
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

                $orderItemData = [
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => $productName,
                    'product_quantity' => $quantity,
                    'product_price' => $this->convertToEffective($catalogUnitPrice, $order->currency_code),
                    'product_total_price' => $this->convertToEffective($lineTotal, $order->currency_code),
                    'product_sku' => $productSku,
                    'product_flash_sale_price' => $flashSalePrice !== null ? $this->convertToEffective($flashSalePrice, $order->currency_code) : null,
                    'product_discount_price' => $discountPrice !== null ? $this->convertToEffective($discountPrice, $order->currency_code) : null,
                    'promotion_discount_amount' => $this->convertToEffective($promotionDiscountAmount, $order->currency_code),
                    'attributes' => $item->attributes ?? null,
                    'is_gift' => (bool) ($item->is_gift ?? false),
                    'promotion_id' => $item->promotion_id,
                ];

                // Rolling-deploy safety: only snapshot when the column exists.
                if (\Illuminate\Support\Facades\Schema::hasColumn('order_products', 'item_type')) {
                    $orderItemData['item_type'] = $product?->item_type ?? \Marvel\Enums\ItemType::PHYSICAL;
                }

                if (Schema::hasColumn('order_products', 'currency_code')) {
                    $orderItemData = array_merge($orderItemData, [
                        'currency_code' => $order->currency_code ?? $this->currencyService->getEffectiveCode(),
                        'catalog_currency_code' => $this->currencyService->getCatalogCode(),
                        'catalog_price' => round($catalogUnitPrice, 2),
                        'catalog_total_price' => round($lineTotal, 2),
                    ]);
                }

                $orderItem = $order->orderItems()->create($orderItemData);

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

    public function syncOrderItems(Order $order, Cart $cart): bool
    {
        $order->orderItems()->delete();

        return $this->createOrderItems($order, $cart);
    }

    public function updateTransactionAmount(Order $order): void
    {
        $pendingTransaction = $order->transactions()
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($pendingTransaction) {
            $pendingTransaction->update([
                'amount' => (float) $order->total_price,
            ]);
        }
    }

private function resolveCurrencySnapshot(float $totalPrice): array
    {
        if (!Schema::hasColumn('orders', 'currency_code')) {
            return [
                'currency_code' => null,
                'base_currency_code' => null,
                'catalog_currency_code' => null,
                'currency_rate' => null,
                'currency_rate_date' => null,
                'total_price' => $totalPrice,
                'converted_total_price' => $totalPrice,
            ];
        }

$catalogCode = $this->currencyService->getCatalogCode();
        $baseCode = $this->currencyService->getBaseCode();
        $effectiveCode = $this->currencyService->getEffectiveCode();

        $effectiveConversion = $this->safeConvert($totalPrice, $catalogCode, $effectiveCode);
        $effectiveTotal = round((float) $effectiveConversion->convertedAmount, 2);
        $baseConversion = $this->safeConvert($effectiveTotal, $effectiveCode, $baseCode);

        return [
            'currency_code' => $effectiveCode,
            'base_currency_code' => $baseCode,
            'catalog_currency_code' => $catalogCode,
            'currency_rate' => $baseConversion->rate,
            'currency_rate_date' => $baseConversion->effectiveDate,
            'total_price' => $effectiveTotal,
            'converted_total_price' => round((float) $baseConversion->convertedAmount, 2),
        ];
    }

    private function safeConvert(float $amount, string $fromCode, string $toCode): CurrencyConversionResult
    {
        try {
            return $this->currencyService->convert($amount, $fromCode, $toCode);
        } catch (CurrencyRateNotFoundException $e) {
            throw new \InvalidArgumentException(
                __('message.' . CURRENCY_RATE_UNAVAILABLE_AT_CHECKOUT, ['currency' => $toCode]),
                0,
                $e
            );
        }
    }

    private function convertToEffective(float|string|null $catalogAmount, ?string $toCode = null): ?float
    {
        if ($catalogAmount === null || $catalogAmount === '') {
            return null;
        }

        $toCode = strtoupper($toCode ?? $this->currencyService->getEffectiveCode());

        try {
            return $this->currencyService->convertPrice(
                (string) $catalogAmount,
                $this->currencyService->getCatalogCode(),
                $toCode
            );
        } catch (CurrencyRateNotFoundException $e) {
            throw new \InvalidArgumentException(
                __('message.' . CURRENCY_RATE_UNAVAILABLE_AT_CHECKOUT, ['currency' => $toCode]),
                0,
                $e
            );
        }
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
        try {
            OrderCreated::dispatch($order);
        } catch (\Throwable $e) {
            report($e);
        }
    }

}
