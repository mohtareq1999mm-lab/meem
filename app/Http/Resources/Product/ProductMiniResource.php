<?php

namespace App\Http\Resources\Product;

use App\Traits\HasProductFilters;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductMiniResource extends JsonResource
{
    use HasProductFilters;
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslation('name', app()->getLocale()),
            'slug' => $this->slug,
            'price' => $this->roundMoney($this->price),
            'has_variants' => $this->product_type !== 'simple' ? true : false,
            'current_price' => $this->roundMoney($this->current_price),
            'quantity' => (int) $this->stock_quantity,
            'in_stock'               =>(bool) $this->in_stock,
            'discount_active' => (bool) $this->discount_active,
            'flash_sale_active' => (bool) $this->flash_sale_active,
            'is_fast_shipping_available' =>(bool)$this->is_fast_shipping_available,
            'ratings' => round((float) ($this->reviews_avg_rating ?? 0), 2),
            'image' => [
                'thumbnail' => $this->getFirstMediaUrl('products'),
                'original' => $this->getMediaImages('products'),
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

    private function getMediaImages($collection)
    {
        $media = $this->getMedia($collection);


        // Return all media URLs except the first (used as 'original')
        return $media->slice(1)
            ->map(function ($m) {
                return $m->getUrl();
            });
    }
}