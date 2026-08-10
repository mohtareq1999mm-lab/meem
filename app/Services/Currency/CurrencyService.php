<?php

namespace App\Services\Currency;

use App\DTOs\CurrencyConversionResult;
use App\Enums\FrontendResource;
use App\Exceptions\CurrencyInactiveException;
use App\Exceptions\CurrencyInUseException;
use App\Exceptions\CurrencyRateNotFoundException;
use App\Models\Currency;
use App\Models\CurrencyRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\Settings;

class CurrencyService
{
    public const PRODUCT_STRATEGY_TYPES = [
        'best_product_sales',
        'brands_product',
        'new_arrivals',
        'all_product_discounts',
        'product_discount_today_or_low_qty',
        'flash_sales_product',
        'flash_sales_end_today',
        'product_for_parent_category',
        'flash_sales_end_week',
    ];

    private const SCALE = 6;

    private ?string $baseCode = null;
    private ?string $catalogCode = null;
    private ?Currency $baseCurrency = null;
    private ?Currency $catalogCurrency = null;
    private array $rateCache = [];

    public function __construct(private CurrencyConversionService $conversionService)
    {
    }

    public function getBaseCode(): string
    {
        if ($this->baseCode !== null) {
            return $this->baseCode;
        }

        $options = $this->settingsOptions();

        return $this->baseCode = strtoupper((string) ($options['base_currency_code'] ?? config('shop.default_currency', 'USD')));
    }

    public function getCatalogCode(): string
    {
        if ($this->catalogCode !== null) {
            return $this->catalogCode;
        }

        $options = $this->settingsOptions();

        return $this->catalogCode = strtoupper((string) ($options['catalog_currency_code'] ?? config('shop.default_currency', 'USD')));
    }

    public function getBaseCurrency(): ?Currency
    {
        return $this->baseCurrency ??= Currency::query()->where('code', $this->getBaseCode())->first();
    }

    public function getCatalogCurrency(): ?Currency
    {
        return $this->catalogCurrency ??= Currency::query()->where('code', $this->getCatalogCode())->first();
    }

    public function convert(float|string $amount, string $fromCode, string $toCode, ?string $date = null): CurrencyConversionResult
    {
        return $this->conversionService->convert($amount, $fromCode, $toCode, $date);
    }

    public function convertPrice(float|string $amount, string $fromCode, string $toCode, ?string $date = null): float
    {
        $fromCode = strtoupper($fromCode);
        $toCode = strtoupper($toCode);
        $date = $date ?? now()->toDateString();
        $amount = (string) $amount;

        if ($fromCode === $toCode) {
            return round((float) $amount, 2);
        }

        $targetRate = $this->resolveRate($toCode, $date);
        $sourceRate = $this->resolveRate($fromCode, $date);

        return round((float) bcdiv(bcmul($amount, $targetRate, self::SCALE), $sourceRate, self::SCALE), 2);
    }

    public function storeCurrency(array $data): Currency
    {
        $currency = Currency::create($data);
        $this->invalidatePriceCaches();

        return $currency;
    }

    public function updateCurrency(Currency $currency, array $data): Currency
    {
        $currency->update($data);
        $this->invalidatePriceCaches();

        return $currency->fresh();
    }

    public function deleteCurrency(Currency $currency): void
    {
        if ($currency->code === $this->getBaseCode()) {
            throw CurrencyInUseException::isBaseCurrency();
        }

        if ($currency->rates()->exists()) {
            throw CurrencyInUseException::referencedByRates();
        }

        $currency->delete();
        $this->invalidatePriceCaches();
    }

    public function setBaseCurrency(Currency $currency): void
    {
        DB::transaction(function () use ($currency) {
            $settings = Settings::query()->lockForUpdate()->first();

            if (!$settings) {
                throw new \RuntimeException('Settings record not found.');
            }

            if (!$currency->is_active) {
                throw CurrencyInactiveException::forCurrency($currency->code);
            }

            $hasRate = CurrencyRate::query()
                ->where('currency_id', $currency->getKey())
                ->whereDate('effective_date', '<=', now()->toDateString())
                ->exists();

            if (!$hasRate) {
                throw CurrencyRateNotFoundException::forCurrency($currency->code, now()->toDateString());
            }

            $options = $settings->options ?? [];
            $options['base_currency_code'] = $currency->code;
            $options['currency'] = $currency->code;

            $settings->options = $options;
            $settings->save();

            Cache::forget('cached_settings_' . app()->getLocale());
            Cache::forget('cached_settings_en');
            Cache::forget('cached_settings_ar');
        });

        $this->baseCode = $currency->code;
        $this->baseCurrency = $currency;

        $this->invalidatePriceCaches(flushSettings: true);
    }

    public function invalidatePriceCaches(bool $flushSettings = false): void
    {
        $tags = array_merge(
            [FrontendResource::CURRENCIES->value, FrontendResource::PRODUCTS->value],
            $this->productStrategyTags(),
        );

        if ($flushSettings) {
            $tags[] = FrontendResource::SETTINGS->value;
        }

        Cache::tags(array_values(array_unique($tags)))->flush();
    }

    private function productStrategyTags(): array
    {
        return array_map(
            fn (string $type) => FrontendResource::PRODUCTS->value . '_' . $type,
            self::PRODUCT_STRATEGY_TYPES,
        );
    }

    private function settingsOptions(): array
    {
        $settings = Settings::query()->first();

        return $settings ? ($settings->options ?? []) : [];
    }

    private function resolveRate(string $currencyCode, string $date): string
    {
        $cacheKey = $currencyCode . '|' . $date;

        if (array_key_exists($cacheKey, $this->rateCache)) {
            return $this->rateCache[$cacheKey];
        }

        $rate = CurrencyRate::query()
            ->whereHas('currency', fn ($query) => $query->where('code', $currencyCode))
            ->whereDate('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->value('exchange_rate');

        if ($rate === null) {
            throw CurrencyRateNotFoundException::forCurrency($currencyCode, $date);
        }

        return $this->rateCache[$cacheKey] = (string) $rate;
    }
}
