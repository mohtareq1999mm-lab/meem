<?php

namespace Marvel\Http\Resources;

use Illuminate\Http\Request;


class CategoryResource extends Resource
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
            'id'                   => $this->id,
            'name'                 => request()->routeIs('categories.index') ? $this->getTranslation('name', app()->getLocale()) : $this->getRawOriginal('name'),
            'slug'                 => $this->slug,
            'parent_id'            => $this->parent_id,
            'level'                => $this->level,
            'image'                => [
                'desktop' => $this->getFirstMediaUrl('categories-desktop') ?: null,
                'mobile'  => $this->getFirstMediaUrl('categories-mobile') ?: null,
            ],
            'is_featured'          => (bool) $this->is_featured,
            'products_count'       => (int) ($this->products_count ?? $this->products()->count()),
            'status'               => (bool)$this->status,
            $this->mergeWhen(!request()->routeIs('categories.index'), [
                'details' => $this->getRawOriginal('details'),
            ]),
            $this->mergeWhen($this->relationLoaded('children') && $this->children->isNotEmpty(), [
                'children' => ChildrenCategoryResource::collection($this->children),
            ]),
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
