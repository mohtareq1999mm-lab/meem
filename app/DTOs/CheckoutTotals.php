<?php

namespace App\DTOs;

class CheckoutTotals
{
    public function __construct(
        public readonly float $subtotal,
        public readonly float $promotionDiscount,
        public readonly float $couponDiscount,
        public readonly float $finalTotal,
        public readonly ?array $promotion = null,
        public readonly array $giftItems = [],
        public readonly ?string $coupon = null,
        public readonly ?string $couponDiscountType = null,
        public readonly ?float $couponDiscountMaxAmount = null,
        public readonly string $currency = 'EGP',
    ) {}

    public function getTotalDiscount(): float
    {
        return round($this->promotionDiscount + $this->couponDiscount, 2);
    }

    public function hasCoupon(): bool
    {
        return $this->coupon !== null;
    }

    public function hasPromotion(): bool
    {
        return $this->promotion !== null;
    }

    public function promotionId(): ?int
    {
        return $this->promotion['id'] ?? null;
    }

    public function promotionCode(): ?string
    {
        return $this->promotion['code'] ?? null;
    }

    public function promotionType(): ?string
    {
        return $this->promotion['type'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'subtotal' => $this->subtotal,
            'promotion_discount' => $this->promotionDiscount,
            'coupon_discount' => $this->couponDiscount,
            'final_total' => $this->finalTotal,
            'promotion' => $this->promotion,
            'gift_items' => $this->giftItems,
            'coupon' => $this->coupon,
            'coupon_discount_type' => $this->couponDiscountType,
            'coupon_discount_max_amount' => $this->couponDiscountMaxAmount,
        ];
    }

    public static function fromPromotionService(array $promotionTotals, float $couponDiscount = 0, ?string $coupon = null, ?string $couponDiscountType = null, ?float $couponDiscountMaxAmount = null): self
    {
        return new self(
            subtotal: (float) ($promotionTotals['subtotal'] ?? 0),
            promotionDiscount: (float) ($promotionTotals['discount'] ?? 0),
            couponDiscount: $couponDiscount,
            finalTotal: (float) ($promotionTotals['final_total'] ?? 0),
            promotion: $promotionTotals['promotion'] ?? null,
            giftItems: $promotionTotals['gift_items'] ?? [],
            coupon: $coupon,
            couponDiscountType: $couponDiscountType,
            couponDiscountMaxAmount: $couponDiscountMaxAmount,
        );
    }
}
