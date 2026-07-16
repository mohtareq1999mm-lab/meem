<?php

namespace App\Http\Resources\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Marvel\Http\Resources\Order\OrderTransactionResource;
use Marvel\Http\Resources\ShopResource;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'subtotal' => $this->roundMoney($this->price),
            'discount' => $this->roundMoney(((float) ($this->coupon_discount ?? 0)) + ((float) ($this->promotion_discount ?? 0))),
            'coupon' => $this->coupon,
            'coupon_discount' => $this->roundMoney($this->coupon_discount),
            'coupon_discount_type' => $this->coupon_discount_type,
            'promotion_discount' => $this->roundMoney($this->promotion_discount),
            'total' => $this->roundMoney($this->total_price),
            'promotion' => $this->promotion_id ? [
                'id' => $this->promotion_id,
                'type' => $this->promotion_type,
                'code' => $this->promotion_code,
            ] : null,
            'fulfillment_type' => $this->fulfillment_type,
            'payment_method' => $this->payment_method,
            'shipping_price' => $this->roundMoney($this->shipping_price),
            'fast_shipping_fee' => $this->roundMoney($this->fast_shipping_fee),
            'pickup_location' => $this->when($this->fulfillment_type === 'pickup', fn() => $this->resolvePickupLocation()),
            'created_at' => $this->created_at?->toIso8601String(),
            'order_items' => OrderItemResource::collection($this->whenLoaded('orderItems')),
            // 'pickup_location_id' => $this->pickup_location_id,
            'payment_gateway' => $this->payment_gateway,
            // 'transactions' => OrderTransactionResource::collection($this->whenLoaded('transactions')),
        ];
    }

    private function resolvePickupLocation(): ?array
    {
        if ($this->relationLoaded('pickupLocation') && $this->pickupLocation) {
            return [
                'id' => $this->pickupLocation->id,
                'store_name' => $this->pickupLocation->store_name,
            ];
        }

        if ($this->pickup_location_name) {
            return [
                'id' => $this->pickup_location_id,
                'store_name' => $this->pickup_location_name,
            ];
        }

        return null;
    }

    private function roundMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }
}
