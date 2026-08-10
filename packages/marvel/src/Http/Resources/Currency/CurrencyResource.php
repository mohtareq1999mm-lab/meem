<?php

namespace Marvel\Http\Resources\Currency;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->getTranslations('name'),
            'symbol' => $this->getTranslations('symbol'),
            'country_name' => $this->getTranslations('country_name'),
            'numeric_code' => $this->numeric_code,
            'decimal_places' => (int) $this->decimal_places,
            'icon' => $this->icon,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'is_base' => $this->isBaseCurrency(),
            'is_catalog' => $this->isCatalogCurrency(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
