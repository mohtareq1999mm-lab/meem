<?php

namespace App\Http\Resources\Brand;

use Illuminate\Http\Resources\Json\JsonResource;

class BrandProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslation('name', app()->getLocale()),
            'slug' => $this->slug,
            'price' => $this->roundMoney($this->price),
            'price_after_discount' => $this->roundMoney($this->current_price),
            'rating' => round((float) ($this->reviews_avg_rating ?? 0), 2),
            'image' => [
                'thumbnail' => $this->getFirstMediaUrl('products'),
            ],
        ];
    }

    private function roundMoney($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }
}
