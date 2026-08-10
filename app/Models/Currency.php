<?php

namespace App\Models;

use App\Services\Currency\CurrencyService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Currency extends Model
{
    use HasTranslations, SoftDeletes;

    public array $translatable = ['name', 'symbol', 'country_name'];

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'country_name',
        'numeric_code',
        'decimal_places',
        'icon',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'decimal_places' => 'integer',
        'sort_order' => 'integer',
    ];

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper($value);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(CurrencyRate::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isBaseCurrency(): bool
    {
        return $this->code === app(CurrencyService::class)->getBaseCode();
    }

    public function isCatalogCurrency(): bool
    {
        return $this->code === app(CurrencyService::class)->getCatalogCode();
    }
}
