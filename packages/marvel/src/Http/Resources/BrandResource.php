<?php

namespace Marvel\Http\Resources;

use Illuminate\Http\Request;

class BrandResource extends Resource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray(Request $request)
    {
        return [
            'id' => $this->id,
            'name' =>request()->routeIs('brands.index') ? $this->getTranslation('name', app()->getLocale()) :$this->getRawOriginal('name'),
            'slug' => $this->slug,
            'image' => [
                'desktop' => $this->getFirstMediaUrl('brands-desktop'),
                'mobile' => $this->getFirstMediaUrl('brands-mobile'),
            ],
            'details' => $this->getTranslation('details', app()->getLocale()),
            'status' => (bool) $this->status,
            $this->mergeWhen($this->relationLoaded('products'), [
                'products' => $this->products->map(fn($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'status' => $product->status,
                    'image' => [
                        'thumbnail' => $product->getFirstMediaUrl('products'),
                    ],
                ]),
            ]),
        ];
    }
}