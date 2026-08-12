<?php

namespace App\Services\Currency;

use App\DTOs\CurrencyConversionResult;
use App\Enums\FrontendResource;
use App\Exceptions\CurrencyInactiveException;
use App\Exceptions\CurrencyInUseException;
use App\Exceptions\CurrencyRateNotFoundException;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Traits\HasCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Marvel\Database\Models\Settings;
use Marvel\Database\Models\User;

class CurrencyService
{
    use HasCache;
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
    private ?string $effectiveCode = null;
    private ?bool $currencySelectionEnabled = null;
    private ?Currency $baseCurrency = null;
    private ?Currency $catalogCurrency = null;
    private ?Currency $effectiveCurrency = null;
    private array $rateCache = [];

    public function __construct(
        private CurrencyConversionService $conversionService,
        private UserCurrencyPreferenceService $preferenceService,
    ) {}

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

    public function getEffectiveCode(?User $user = null): string
    {
        if ($this->effectiveCode !== null) {
            return $this->effectiveCode;
        }

        // Currency selection is disabled by default. When disabled the customer
        // cannot select a display/checkout currency, so the effective currency
        // always resolves to the catalog currency regardless of any stored user
        // preference or guest cookie.
        if (!$this->isCurrencySelectionEnabled()) {
            return $this->effectiveCode = $this->getCatalogCode();
        }

$user ??= auth()->user() ?? auth('sanctum')->user();

        // The user/guest preference feature is additive. When its tables are not
        // present yet (legacy install or test schema without them) fall back to
        // the catalog code instead of failing checkout.
        if (Schema::hasTable('user_preferences') && Schema::hasTable('currencies')) {
            $preferenceCode = $this->preferenceService->getUserPreference($user);
            if ($preferenceCode !== null && !$this->preferenceService->isValidActiveCurrency($preferenceCode)) {
                if ($user) {
                    $this->preferenceService->clearUserPreference($user);
                }
                $preferenceCode = null;
            }

            if ($preferenceCode !== null) {
                return $this->effectiveCode = $preferenceCode;
            }

            $guestCode = $this->preferenceService->getGuestCurrencyCode();
            if ($guestCode !== null && !$this->preferenceService->isValidActiveCurrency($guestCode)) {
                $this->preferenceService->clearGuestCurrencyCode();
                $guestCode = null;
            }

            if ($guestCode !== null) {
                return $this->effectiveCode = $guestCode;
            }
        }

        return $this->effectiveCode = $this->getCatalogCode();
    }

    public function getEffectiveCurrency(): ?Currency
    {
        return $this->effectiveCurrency ??= Currency::query()->where('code', $this->getEffectiveCode())->first();
    }

    public function isCurrencySelectionEnabled(): bool
    {
        if ($this->currencySelectionEnabled !== null) {
            return $this->currencySelectionEnabled;
        }

        $options = $this->settingsOptions();

        return $this->currencySelectionEnabled = (bool) ($options['currency_selection_enabled'] ?? false);
    }

    public function forgetEffectiveCode(): void
    {
        $this->effectiveCode = null;
        $this->effectiveCurrency = null;
        $this->currencySelectionEnabled = null;
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

         $this->flushTag(FrontendResource::SETTINGS->value);
        });

        $this->baseCode = $currency->code;
        $this->baseCurrency = $currency;

        $this->invalidatePriceCaches(flushSettings: true);
    }
    public function setCatalogCurrency(Currency $currency): void
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
                throw CurrencyRateNotFoundException::forCurrency(
                    $currency->code,
                    now()->toDateString()
                );
            }

            $options = $settings->options ?? [];

            // Change ONLY the Catalog Currency
            $options['catalog_currency_code'] = $currency->code;

            $settings->options = $options;
            $settings->save();

            $this->flushTag(FrontendResource::SETTINGS->value);
        });

        $this->catalogCode = $currency->code;
        $this->catalogCurrency = $currency;

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
            fn(string $type) => FrontendResource::PRODUCTS->value . '_' . $type,
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
            ->whereHas('currency', fn($query) => $query->where('code', $currencyCode))
            ->whereDate('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->value('exchange_rate');

        if ($rate === null) {
            throw CurrencyRateNotFoundException::forCurrency($currencyCode, $date);
        }

        return $this->rateCache[$cacheKey] = (string) $rate;
    }
}