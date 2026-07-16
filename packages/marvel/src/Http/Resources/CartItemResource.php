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
            'price' => $this->price,
            'total_price' => $this->total_price,
            'attributes' => $this?->attributes,
            'shipping_method' => $this->shipping_method,
            'product' => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'slug' => $this->product->slug,
                'thumbnail' => $this->product->getFirstMediaUrl('products'),
            ],
        ];
    }
}
