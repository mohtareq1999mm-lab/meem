<?php

namespace App\Services\Currency;

use App\Models\CurrencyRate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CurrencyRateService
{
    public function __construct(private CurrencyService $currencyService)
    {
    }

    public function store(array $data): CurrencyRate
    {
        $rate = CurrencyRate::query()
            ->where('currency_id', $data['currency_id'])
            ->whereDate('effective_date', $data['effective_date'])
            ->first();

        if ($rate) {
            $rate->update(['exchange_rate' => $data['exchange_rate']]);
        } else {
            $rate = CurrencyRate::create($data);
        }

        $this->currencyService->invalidatePriceCaches();

        return $rate->fresh();
    }

    public function update(CurrencyRate $rate, array $data): CurrencyRate
    {
        $rate->update(['exchange_rate' => $data['exchange_rate']]);

        $this->currencyService->invalidatePriceCaches();

        return $rate->fresh();
    }

    public function delete(CurrencyRate $rate): void
    {
        $rate->delete();

        $this->currencyService->invalidatePriceCaches();
    }

    public function list(?int $currencyId, ?string $effectiveDate, int $limit): LengthAwarePaginator
    {
        return CurrencyRate::query()
            ->with('currency')
            ->when($currencyId, fn ($query) => $query->where('currency_id', $currencyId))
            ->when($effectiveDate, fn ($query) => $query->whereDate('effective_date', $effectiveDate))
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->paginate($limit);
    }
}
