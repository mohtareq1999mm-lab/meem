<?php

namespace Marvel\Http\Resources;

use App\Http\Resources\Coupons\CouponResource;
use App\Services\General\PromotionService;
use Illuminate\Http\Request;
use Marvel\Database\Models\Coupon;
use Marvel\Enums\ShippingMethod;

class CartResource extends Resource
{
    public function toArray(Request $request)
    {
        $items = $this->whenLoaded('items');

        if ($items) {
            $normalItems = $items->where('shipping_method', ShippingMethod::SCHEDULED)->values();
            $fastItems = $items->where('shipping_method', ShippingMethod::FAST)->values();
        } else {
            $normalItems = collect();
            $fastItems = collect();
        }

        $couponModel = $this->coupon ? Coupon::where('code', $this->coupon)->first() : null;
        $couponObject = $couponModel ? CouponResource::make($couponModel) : null;

        $promotionService = app(PromotionService::class);

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'coupon' => $couponObject,
            'coupon_code' => $this->coupon,
            'status' => $this->status,
            'reserved_at' => $this->reserved_at,
            'expires_at' => $this->expires_at,
            'total_items' => $items ? $items->count() : null,
            'total_quantity' => $items ? $items->sum('quantity') : null,
            'total_price' => $items ? $items->sum('total_price') : null,
            'normal_items_count' => $normalItems->count(),
            'fast_items_count' => $fastItems->count(),
            'normal_items' => CartItemResource::collection($normalItems),
            'fast_items' => CartItemResource::collection($fastItems),
            'has_eligible_promotion' => $items && $items->isNotEmpty()
                ? $promotionService->hasEligiblePromotion($this->resource)
                : false,
        ];
    }
}
