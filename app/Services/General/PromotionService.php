<?php

declare(strict_types=1);

namespace App\Services\General;

use App\DTOs\CheckoutTotals;
use App\Services\General\PromotionEngine\PromotionEligibilityResolver;
use App\Services\General\PromotionEngine\PromotionApplicator;
use App\Services\General\PromotionEngine\Outcome\DiscountOutcome;
use App\Services\General\PromotionEngine\Outcome\GiftOutcome;
use App\Services\General\PromotionEngine\DTOs\GiftItem;
use App\Services\General\PromotionEngine\PromotionResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\Cart;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Promotion;
use Marvel\Enums\ShippingMethod;

class PromotionService
{
    public function __construct(
        private PromotionEligibilityResolver $resolver,
        private CartInventoryService $inventoryService,
        private PromotionApplicator $applicator,
    ) {}

    public function eligiblePromotions(Cart $cart): Collection
    {
        $cart->load(['items.product', 'items.productVariant']);

        $subtotal = $this->subtotal($cart);
        $subtotalCents = (int) round((float) $subtotal * 100);
        $promotions = Promotion::valid()
            ->with([
                'products:id',
                'giftProducts:id,name,sku,product_type,stock_quantity,reserved_quantity',
                'giftProducts.variations:id,product_id,stock_quantity,reserved_quantity,price,height,width,length,weight',
                'giftProducts.variations.attributeProducts.attributeValue.attribute',
            ])
            ->get();

        return $this->resolver->eligible($cart, $promotions, $subtotalCents);
    }

    public function eligiblePromotionsPayload(Cart $cart): array
    {
        return [
            'eligible_promotions' => $this->eligiblePromotions($cart)
                ->map(fn(PromotionResult $result) => $result->toArray())
                ->values()
                ->all(),
        ];
    }

    public function applySelectedPromotion(Cart $cart, ?int $promotionId, ?int $selectedGiftProductId = null, ?string $shippingMethod = null): CheckoutTotals
    {
        $this->removeGiftItems($cart);
        $cart->items->load(['product', 'productVariant']);

        $subtotal = $this->subtotal($cart);
        $subtotalCents = (int) round((float) $subtotal * 100);
        $result = null;
        $discountDetails = ['discount' => 0.0, 'gift_items' => []];
        $giftDetails = ['discount' => 0.0, 'gift_items' => []];

        if ($promotionId) {
            $promotion = Promotion::valid()
                ->whereKey($promotionId)
                ->with([
                    'products:id',
                    'giftProducts:id,name,sku,product_type,stock_quantity,reserved_quantity',
                    'giftProducts.variations:id,product_id,stock_quantity,reserved_quantity,price,height,width,length,weight',
                    'giftProducts.variations.attributeProducts.attributeValue.attribute',
                ])
                ->lockForUpdate()
                ->first();

            if (!$promotion) {
                throw new \InvalidArgumentException('Selected promotion is not valid.');
            }

            // Evaluate promotion (read-only)
            $result = $this->resolver->resolve($cart, $promotion, $subtotalCents);

            if (!$result) {
                throw new \InvalidArgumentException('Selected promotion is not eligible for this cart.');
            }

            // Build PromotionEvaluation to pass to strategy (resolver already computed scoped outcome via resolver->resolve returning PromotionResult for backward compatibility)
            // Determine outcome via strategy by re-evaluating (resolver returned PromotionResult for compatibility). Use resolver->eligible to fetch evaluation if needed.
            $evaluation = $this->resolver->matchedEligibility($cart, $promotion, $subtotalCents);

            // Use strategy to compute outcome (we already performed computeOutcome in resolver for compatibility; map to Outcome types)
            // For backward compatibility we reuse PromotionResult structure: if it has giftItems, treat as GiftOutcome; else Discount
            $amountCents = (int) round((float) ($result->discount ?? 0) * 100);

            if ($amountCents > 0) {
                $discountOutcome = new DiscountOutcome($amountCents, $evaluation->matchedSubtotalCents);
                $discountDetails = $this->applicator->applyOutcome($cart, $promotion, $discountOutcome);
                $itemIds = $cart->items->pluck('id');
                $cart->refresh();
                $cart->load(['items' => fn($q) => $q->whereIn('id', $itemIds), 'items.product', 'items.productVariant']);
            }

            if (!empty($result->giftItems)) {
                $selectedGiftItem = $this->resolveSelectedGiftItem($result->giftItems, $selectedGiftProductId);
                $giftOutcome = new GiftOutcome([$selectedGiftItem]);
                $giftDetails = $this->applicator->applyOutcome($cart, $promotion, $giftOutcome, $shippingMethod);
                $itemIds = $cart->items->pluck('id');
                $cart->refresh();
                $cart->load(['items' => fn($q) => $q->whereIn('id', $itemIds), 'items.product', 'items.productVariant']);
            }
        } else {
            return $this->clearPromotionFromCart($cart);
        }
        return new CheckoutTotals(
            subtotal: round((float) $subtotal, 2),
            promotionDiscount: round((float) ($discountDetails['discount'] ?? 0), 2),
            couponDiscount: 0,
            finalTotal: round(
                (float) $cart->items
                    ->reject(fn($item) => (bool) ($item->is_gift ?? false))
                    ->sum('total_price'),
                2
            ),
            promotion: $result ? [
                'id' => $result->promotion->id,
                'type' => $result->promotion->type_amount,
                'code' => $result->promotion->code,
            ] : null,
            giftItems: $giftDetails['gift_items'] ?? [],
        );
    }

    public function clearPromotionFromCart(Cart $cart): CheckoutTotals
    {
        $this->removeGiftItems($cart);
        $cart->items()
            ->where(function ($q) {
                $q->whereNotNull('promotion_id')->orWhere('discount_amount', '>', 0);
            })
            ->update([
                'promotion_id' => null,
                'discount_amount' => 0,
                'total_price' => DB::raw('price * quantity'),
            ]);
        $cart->refresh();
        $cart->load(['items.product', 'items.productVariant']);

        $subtotal = $this->subtotal($cart);
        $undiscountedTotal = round((float) $cart->items
            ->reject(fn($item) => (bool) ($item->is_gift ?? false))
            ->sum(fn($item) => ((float) ($item->price ?? 0)) * ((int) ($item->quantity ?? 0))), 2);
        $cart->forceFill(['total_price' => $undiscountedTotal])->save();

        return new CheckoutTotals(
            subtotal: $subtotal,
            promotionDiscount: 0.0,
            couponDiscount: 0.0,
            finalTotal: $undiscountedTotal,
            promotion: null,
            giftItems: [],
        );
    }

    public function incrementUsage(?int $promotionId): void
    {
        if (!$promotionId) {
            return;
        }

        Promotion::query()
            ->whereKey($promotionId)
            ->where(function ($query) {
                $query->whereNull('limiter')
                    ->orWhereColumn('usage', '<', 'limiter');
            })
            ->lockForUpdate()
            ->first()
            ?->increment('usage');
    }

    public function hasEligiblePromotion(Cart $cart): bool
    {
        if ($cart->items->isEmpty()) {
            return false;
        }

        return $this->eligiblePromotions($cart)->isNotEmpty();
    }

    public function decrementUsage(?int $promotionId): void
    {
        if (!$promotionId) {
            return;
        }

        Promotion::query()
            ->whereKey($promotionId)
            ->where('usage', '>', 0)
            ->lockForUpdate()
            ->first()
            ?->decrement('usage');
    }

    private function removeGiftItems(Cart $cart): void
    {
        $cart->items()
            ->where('is_gift', true)
            ->get()
            ->each(fn($item) => $this->inventoryService->releaseItem($item, true));
    }

    private function applyGiftItems(Cart $cart, PromotionResult $result): void
    {
        foreach ($result->giftItems as $giftItem) {
            $product = Product::query()
                ->whereKey($giftItem['product_id'])
                ->lockForUpdate()
                ->first();

            if (!$product) {
                continue;
            }

            $this->inventoryService->reserveGiftItem(
                $cart,
                $product,
                $result->promotion,
                max(1, (int) $giftItem['quantity']),
                $giftItem['product_variant_id'] ?? null
            );
        }
    }

    private function subtotal(Cart $cart): float
    {
        return round((float) $cart->items
            ->reject(fn($item) => (bool) ($item->is_gift ?? false))
            ->sum(function ($item) {
                $baseLineTotal = ((float) ($item->price ?? 0)) * ((int) ($item->quantity ?? 0));

                if ($baseLineTotal > 0) {
                    return $baseLineTotal;
                }

                return (float) ($item->total_price ?? 0);
            }), 2);
    }

    private function resolveSelectedGiftItem(array $giftItems, ?int $selectedGiftProductId): GiftItem
    {
        $availableGiftItems = collect($giftItems)
            ->filter(fn($giftItem) => (int) ($giftItem['price_cents'] ?? 0) === 0)
            ->values();

        if ($availableGiftItems->isEmpty()) {
            throw new \InvalidArgumentException('No available gift products for this promotion.');
        }

        if ($selectedGiftProductId) {
            $selectedGiftItem = $availableGiftItems->firstWhere('product_id', $selectedGiftProductId);

            if (!$selectedGiftItem) {
                throw new \InvalidArgumentException('Selected gift product is not available for this promotion.');
            }

            return $selectedGiftItem;
        }

        return $availableGiftItems->first();
    }
}
