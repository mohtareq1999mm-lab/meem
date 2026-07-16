<?php

namespace App\Http\Resources\Brand;

use App\Http\Resources\Product\ProductMiniResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'       => $this->getTranslation('name', app()->getLocale()),
            'slug'       => $this->slug,
            'image'       => [
                'desktop' => $this?->getFirstMediaUrl('brands-desktop'),
                'mobile' => $this?->getFirstMediaUrl('brands-mobile'),
            ],
            "status"   => (bool)$this->status,
            $this->mergeWhen($this->relationLoaded('products'), [
                'products' => ProductMiniResource::collection($this->products),
            ]),

        ];
    }
}