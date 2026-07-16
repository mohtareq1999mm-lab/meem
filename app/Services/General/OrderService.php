<?php

namespace App\Services\General;

use App\DTOs\CheckoutTotals;
use App\Events\AssignedCouponConsumed;
use App\Events\OrderCreated;
use App\Services\Checkout\OrderCreationService;
use App\Services\General\CartInventoryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Marvel\Database\Models\Coupon;
use Marvel\Database\Models\CouponAssignment;
use Marvel\Database\Models\CouponAssignmentUsage;
use Marvel\Database\Models\CouponUsage;
use Marvel\Database\Models\Cart;
use Marvel\Database\Models\CartItem;
use Marvel\Database\Models\Governorate;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\Promotion;
use Marvel\Database\Models\ShippingPrice;
use Marvel\Database\Models\Transaction;
use Marvel\Enums\ShippingMethod;
use App\Events\OrderCancelled;
use App\Events\OrderStatusChanged;
use App\Services\Coupon\CouponCalculator;
use App\Services\Coupon\CouponOrchestrator;
use Marvel\Enums\DiscountType;

class OrderService
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    protected $dataArray = [
        'name',
        'user_phone',
        'user_email',
        'address',
        'notes',
        'governorate_id',
    ];

    public function __construct(
        private PromotionService $promotionService,
        private OrderCreationService $orderCreationService,
        private CartInventoryService $cartInventoryService,
    ) {}

    public function paginateForUser(Request $request): LengthAwarePaginator
    {
        $limit = $this->getLimit($request);
        $userId = (int) $request->user()->id;

        $orders = Order::query()
            ->forUser($userId)
            ->with($this->orderListRelations())
            ->paginate($limit)
            ->withQueryString();

        $orders->getCollection()->each(function (Order $order) {
            $order->orderItems->each(function ($item) {
                if ($item->relationLoaded('product') && $item->product) {
                    app(ProductService::class)->enrichProductWithPricing($item->product);
                }
            });
        });

        return $orders;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function orderListRelations(): array
    {
        return [
            'orderItems.product' => fn($q) => $q->withAvg('reviews', 'rating'),
            'orderItems.product.media',
            'orderItems.productVariant.attributeProducts.attributeValue',
            'transactions',
            'pickupLocation',
        ];
    }

    private function getLimit(Request $request): int
    {
        $limit = (int) $request->get('limit', self::DEFAULT_PER_PAGE);

        if ($limit <= 0) {
            return self::DEFAULT_PER_PAGE;
        }

        return min($limit, self::MAX_PER_PAGE);
    }

    public function calcInvoicePrice($request)
    {
        try {
            DB::beginTransaction();
            $cart = $this->getCartUser();
            if (!$cart) {
                DB::rollBack();
                throw new \InvalidArgumentException(__('checkout.cart_not_found')); 
            }
            if ($cart->items->isEmpty()) {
                DB::rollBack();
                throw new \InvalidArgumentException(__('checkout.cart_empty'));
            }
            $checkoutTotals = $this->calculateCheckoutTotals(
                $cart,
                (int) $request->input('selected_promotion_id') ?: null,
                (int) $request->input('selected_gift_product_id') ?: null,
                ShippingMethod::SCHEDULED,
            );

            $shippingInfo = $this->resolveShippingPrice((int) $request->input('governorate_id') ?: null);
            $shippingPrice = $this->resolveFreeShippingByThreshold($checkoutTotals->subtotal, $shippingInfo['free_shipping_over'], $shippingInfo['price']);
            $shippingPrice = $this->resolveFreeShippingByCoupon($checkoutTotals->couponDiscountType, $shippingPrice);

            $finalTotal = round((float) $checkoutTotals->finalTotal + $shippingPrice, 2);
            $cart->update(['total_price' => $finalTotal]);
            DB::commit();
            return $cart->total_price;
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            throw new \InvalidArgumentException($e->getMessage(), 0, $e);
        }
    }

    public function addItemsInOrder($request)
    {
        try {
            DB::beginTransaction();

            $cart = Cart::query()
                ->where('user_id', auth()->id())
                ->where('status', 'active')
                ->lockForUpdate()
                ->with(['items' => fn($q) => $q->where('shipping_method', ShippingMethod::SCHEDULED), 'items.product.flash_sales' => fn($q) => $q->valid(), 'items.productVariant'])
                ->first();

            if (!$cart || $cart->items->isEmpty()) {
                DB::rollBack();
                return null;
            }

            $freeShippingCoupon = false;
            if ($cart->coupon) {
                $validation = CouponOrchestrator::validateByCode($cart->coupon, $request->user(), $cart->items);
                if (!$validation['valid']) {
                    $cart->update(['coupon' => null]);
                } elseif ($validation['coupon'] && $validation['coupon']->discount_type === DiscountType::FREE_SHIPPING) {
                    $freeShippingCoupon = true;
                }
            }

            $selectedPromotionId = $cart->items
                ->firstWhere(fn($item) => !is_null($item->promotion_id))
                ?->promotion_id;

            $selectedGiftVariantId = $cart->items
                ->firstWhere('is_gift', true)
                ?->product_variant_id;

            $checkoutTotals = $this->calculateCheckoutTotals(
                $cart,
                $selectedPromotionId ? (int) $selectedPromotionId : null,
                $selectedGiftVariantId ? (int) $selectedGiftVariantId : null,
                ShippingMethod::SCHEDULED,
            );

            $orderData = $request->only(array_merge($this->dataArray, [
                'fulfillment_type', 'payment_method', 'payment_gateway', 'pickup_location_id',
            ]));
            $orderData['user_id'] = $request->user()->id;

            $shippingInfo = $this->resolveShippingPrice((int) ($orderData['governorate_id'] ?? null));
            $shippingPrice = $this->resolveFreeShippingByThreshold($checkoutTotals->subtotal, $shippingInfo['free_shipping_over'], $shippingInfo['price']);
            if ($freeShippingCoupon) {
                $shippingPrice = 0;
            }
            $governorateId = $shippingInfo['governorate_id'];

            $order = $this->orderCreationService->createOrder(
                $orderData, $cart, $checkoutTotals, null, null, null, $shippingPrice, $governorateId,
            );
            if (!$order) {
                DB::rollBack();
                return null;
            }
            if (!$this->orderCreationService->createOrderItems($order, $cart)) {
                DB::rollBack();
                return null;
            }
            $this->orderCreationService->finalizeOrder($order, $checkoutTotals);
            $this->cartInventoryService->finalizeItemsByShippingMethod($cart, ShippingMethod::SCHEDULED);
            DB::commit();

            return $order->load(['orderItems.product', 'orderItems.productVariant']);
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return null;
        }
    }

    public function eligiblePromotionsForUser(): ?array
    {
        $cart = $this->getCartUser();
        if (!$cart || !$cart->items()->exists()) {
            return null;
        }

        return $this->promotionService->eligiblePromotionsPayload($cart);
    }



    public function getGovernorateShippingInfo(?int $governorateId): array
    {
        return $this->resolveShippingPrice($governorateId);
    }

    public function resolveFreeShippingByThreshold(float $subtotal, ?float $freeShippingOver, float $shippingPrice): float
    {
        if ($freeShippingOver !== null && $subtotal > $freeShippingOver) {
            return 0;
        }
        return $shippingPrice;
    }

    public function resolveFreeShippingByCoupon(?string $couponDiscountType, float $shippingPrice): float
    {
        if ($couponDiscountType === DiscountType::FREE_SHIPPING) {
            return 0;
        }
        return $shippingPrice;
    }

    private function resolveShippingPrice(?int $governorateId): array
    {
        if (!$governorateId) {
            return ['price' => 0, 'free_shipping_over' => null, 'governorate_id' => null];
        }

        $governorate = Governorate::query()->where('id', $governorateId)->where('status', true)->first();
        if (!$governorate) {
            return ['price' => 0, 'free_shipping_over' => null, 'governorate_id' => null];
        }

        $shippingPrice = $governorate->shippingPrice()
            ->where('status', true)
            ->first();

        if (!$shippingPrice) {
            return ['price' => 0, 'free_shipping_over' => null, 'governorate_id' => $governorateId];
        }

        return [
            'price' => (float) $shippingPrice->price,
            'free_shipping_over' => $shippingPrice->free_shipping_over !== null ? (float) $shippingPrice->free_shipping_over : null,
            'governorate_id' => $governorateId,
        ];
    }

    private function getCartUser()
    {
        return Cart::query()
            ->where('user_id', auth()->id())
            ->where('status', 'active')
            ->with(['items' => fn($q) => $q->where('shipping_method', ShippingMethod::SCHEDULED), 'items.product.flash_sales' => fn($q) => $q->valid(), 'items.productVariant'])
            ->first();
    }

    private function calculatePriceByCoupon($cart, $totalPrice): array
    {
        if ($cart->coupon === null) {
            return [
                'finalPrice' => $totalPrice,
                'discountType' => null,
                'freeShipping' => false,
            ];
        }

        $coupon = Coupon::where('code', $cart->coupon)->first();
        if (!$coupon) {
            return [
                'finalPrice' => $totalPrice,
                'discountType' => null,
                'freeShipping' => false,
            ];
        }

        return CouponCalculator::calculate($coupon, (float) $totalPrice);
    }

    /** @deprecated Replaced by calculateCheckoutTotals() which re-validates promotion. Kept for backward compatibility. Remove after 1 release cycle. */
    private function getCheckoutTotalsFromCart(Cart $cart): CheckoutTotals
    {
        $items = $cart->items->reject(fn($item) => (bool) ($item->is_gift ?? false));

        $subtotal = round((float) $items->sum(function ($item) {
            $baseLineTotal = ((float) ($item->price ?? 0)) * ((int) ($item->quantity ?? 0));
            if ($baseLineTotal > 0) {
                return $baseLineTotal;
            }
            return (float) ($item->total_price ?? 0);
        }), 2);

        $promotionDiscount = round((float) $items->sum(fn($item) => (float) ($item->discount_amount ?? 0)), 2);
        $finalTotal = round((float) $items->sum('total_price'), 2);

        $promotionItem = $items->first(fn($item) => !is_null($item->promotion_id));
        $promotionData = null;
        if ($promotionItem) {
            $promotion = Promotion::query()->find((int) $promotionItem->promotion_id);
            $promotionData = $promotion ? [
                'id' => (int) $promotion->id,
                'type' => $promotion->type_amount,
                'code' => $promotion->code,
            ] : null;
        }

        $couponDiscountType = null;
        if ($cart->coupon) {
            $coupon = Coupon::where('code', $cart->coupon)->first();
            if ($coupon) {
                $couponDiscountType = $coupon->discount_type;
            }
        }

        return new CheckoutTotals(
            subtotal: $subtotal,
            promotionDiscount: $promotionDiscount,
            couponDiscount: round(max(0, $subtotal - $promotionDiscount - $finalTotal), 2),
            finalTotal: $finalTotal,
            promotion: $promotionData,
            giftItems: [],
            couponDiscountType: $couponDiscountType,
        );
    }

    public function calculateCheckoutTotals(Cart $cart, ?int $selectedPromotionId, ?int $selectedGiftProductId = null, ?string $shippingMethod = null): CheckoutTotals
    {
        $promotionTotals = $this->promotionService->applySelectedPromotion($cart, $selectedPromotionId, $selectedGiftProductId, $shippingMethod);
        $priceAfterPromotion = $promotionTotals->finalTotal;
        $couponResult = $this->calculatePriceByCoupon($cart, $priceAfterPromotion);
        $finalTotal = round(max(0, (float) $couponResult['finalPrice']), 2);

        $coupon = null;
        $couponDiscountMaxAmount = null;
        if ($cart->coupon) {
            $couponModel = Coupon::valid()->where('code', $cart->coupon)->first();
            if ($couponModel) {
                $coupon = $couponModel->code;
                $couponDiscountMaxAmount = $couponModel->max_discount_amount;
            }
        }

        return new CheckoutTotals(
            subtotal: $promotionTotals->subtotal,
            promotionDiscount: $promotionTotals->promotionDiscount,
            couponDiscount: round(max(0, (float) $priceAfterPromotion - (float) $finalTotal), 2),
            finalTotal: $finalTotal,
            promotion: $promotionTotals->promotion,
            giftItems: $promotionTotals->giftItems,
            coupon: $coupon,
            couponDiscountType: $couponResult['discountType'],
            couponDiscountMaxAmount: $couponDiscountMaxAmount,
        );
    }


    public function clearCart(?int $userId = null): bool
    {
        $targetUserId = $userId ?? auth()->id();
        if (!$targetUserId) {
            return false;
        }

        $cart = Cart::query()->where('user_id', $targetUserId)->first();
        if (!$cart) {
            return false;
        }

        return $this->cartInventoryService->releaseCart($cart, true);
    }

    public function changeOrderStatus($invoiceId, $status, $orderId = null)
    {
        return DB::transaction(function () use ($invoiceId, $status, $orderId) {
            $order = null;
            $transaction = null;

            if ($invoiceId) {
                $transaction = Transaction::where('invoice_id', $invoiceId)->first();
                if ($transaction) {
                    $order = $transaction->order()->lockForUpdate()->first();
                }
            }

            if (!$order && $orderId) {
                $order = Order::whereKey($orderId)->lockForUpdate()->first();
                if ($order) {
                    $transaction = $order->transactions()->latest()->first();
                }
            }

            if (!$order) {
                return false;
            }

            $previousStatus = $order->status;

            if (!$order->update(['status' => $status])) {
                return false;
            }

            if ($status === 'completed') {
                $this->recordCouponUsage($order);
            }

            if ($transaction) {
                if ($status === 'completed') {
                    $transaction->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                    ]);
                }

                if ($status === 'cancelled') {
                    $transaction->update([
                        'status' => 'failed',
                    ]);
                }
            }

            if ($status === 'cancelled' && $previousStatus !== 'cancelled') {
                $this->promotionService->decrementUsage($order->promotion_id ? (int) $order->promotion_id : null);
            }

            event(new OrderStatusChanged($order));

            if ($status === 'cancelled' && $previousStatus !== 'cancelled') {
                event(new OrderCancelled($order));
            }

            return $order;
        });
    }

    public function markCodAsPaid(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $transaction = $order->transactions()
                ->where('payment_method', 'cod')
                ->where('status', 'pending')
                ->latest()
                ->lockForUpdate()
                ->first();

            if (!$transaction) {
                throw new \RuntimeException('No pending COD transaction found.');
            }

            $transaction->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $order->update(['status' => 'completed']);

            $this->recordCouponUsage($order);

            event(new \App\Events\PaymentSucceeded($order));
        });
    }

    public function markCashierPaid(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $transaction = $order->transactions()
                ->where('payment_method', 'pay_at_cashier')
                ->where('status', 'pending')
                ->latest()
                ->lockForUpdate()
                ->first();

            if (!$transaction) {
                throw new \RuntimeException('No pending Pay at Cashier transaction found.');
            }

            $transaction->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $order->update(['status' => 'completed']);

            $this->recordCouponUsage($order);

            event(new \App\Events\PaymentSucceeded($order));
        });
    }

    /**
     * Record coupon usage after successful payment.
     *
     * Policy: Coupon quota is consumed when payment succeeds.
     * It is NEVER automatically returned on cancellation or refund.
     * This prevents abuse where a user could re-use the same quota
     * by repeatedly cancelling and re-ordering.
     *
     * For assigned coupons, usage is recorded in both:
     *   - coupon_assignment_usages (individual audit trail)
     *   - coupon_assignments.used (aggregate counter)
     *   - coupons.used (global counter)
     *
     * For public coupons, usage is recorded in coupon_usages
     * with firstOrCreate (one usage per user enforced by unique
     * constraint on coupon_id, user_id).
     *
     * Concurrency: The assignment row is locked (lockForUpdate)
     * before incrementing, so concurrent checkouts cannot
     * over-consume the quota.
     */
    private function recordCouponUsage($order): void
    {
        if (!$order->coupon) {
            return;
        }

        $coupon = Coupon::where('code', $order->coupon)->first();
        if (!$coupon) {
            return;
        }

        $hasAssignments = Schema::hasTable('coupon_assignments') && $coupon->assignments()->exists();

        if ($hasAssignments) {
            $assignment = CouponAssignment::where('coupon_id', $coupon->id)
                ->where('user_id', $order->user_id)
                ->lockForUpdate()
                ->first();

            if (!$assignment) {
                return;
            }

            if ($assignment->used >= $assignment->max_uses) {
                return;
            }

            $coupon->increment('used');

            $assignment->increment('used');

            CouponAssignmentUsage::create([
                'coupon_assignment_id' => $assignment->id,
                'order_id' => $order->id,
                'used_at' => now(),
            ]);

            DB::afterCommit(function () use ($coupon, $assignment, $order) {
                $remainingUses = max(0, $assignment->max_uses - $assignment->fresh()->used);
                event(new AssignedCouponConsumed(
                    coupon: $coupon,
                    couponAssignment: $assignment,
                    user: $order->user,
                    order: $order,
                    remainingUses: $remainingUses,
                    consumedAt: now(),
                ));
            });
        } else {
            $couponUsage = CouponUsage::firstOrCreate(
                [
                    'coupon_id' => $coupon->id,
                    'user_id' => $order->user_id,
                ],
                [
                    'order_id' => $order->id,
                    'used_at' => now(),
                ]
            );

            if ($couponUsage->wasRecentlyCreated) {
                $coupon->increment('used');
            }
        }
    }
}