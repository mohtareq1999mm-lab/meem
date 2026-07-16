<?php

namespace Marvel\Http\Resources;

class WishlistResource extends Resource
{
    public function toArray($request): array
    {
        return [
            'id'                     => $this->id,
            'name'                   => $this->getTranslation('name', app()->getLocale()),
            'slug'                   => $this->slug,
            'price'                 => $this->product_type === 'simple'
                ? $this->current_price
                : $this->variations[0]->current_price ?? $this->variations[0]->price ?? null,
            'current_price'          => $this->current_price,
            'price_after_discount'   => $this->price_after_discount,
            'price_after_flash_sale' => $this->price_after_flash_sale,
            'in_stock'               => $this->in_stock,
            'has_flash_sale'         => $this->has_flash_sale,
            'has_discount'           => $this->has_discount,
            "images"                 => $this->getFirstMediaUrl('products'),
            "variants"                => $this->whenLoaded('variations', function () {
                return $this->variations->map(function ($variant) {
                    return [
                        'id' => $variant->id,
                        'price' => $variant->price,
                        'current_price' => $variant->current_price,
                        'height' => $variant->height,
                        'width' => $variant->width,
                        'length' => $variant->length,
                        'weight' => $variant->weight,
                        'attributes' => $variant->attributeProducts->map(function ($attrProduct) {
                            return [
                                'attribute_name' => optional(optional($attrProduct->attributeValue)->attribute)->name,
                                'value' => optional($attrProduct->attributeValue)->value,
                            ];
                        }),
                    ];
                });
            }),
        ];
    }
}
