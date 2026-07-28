<?php

namespace Marvel\Http\Resources;

use App\Http\Resources\Product\ProductMiniResource;
use Illuminate\Http\Request;
use Marvel\Http\Resources\ProductVariantResource;

class CartItemResource extends Resource
{
    public function toArray(Request $request)
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'quantity' => $this->quantity,
            'price' => round((float) $this->price, 2),
            'total_price' => round((float) $this->total_price, 2),
            'attributes' => $this?->attributes,
            'shipping_method' => $this->shipping_method,
            'promotion_id' => $this->promotion_id,
            'discount_amount' => round((float) $this->discount_amount, 2),
            'is_gift' => $this->is_gift ?? false,
            'product' => $this->product ? [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'slug' => $this->product->slug,
                'thumbnail' => $this->product->getFirstMediaUrl('products'),
            ] : null,
        ];
    }
}
