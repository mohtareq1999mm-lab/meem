<?php

namespace App\Http\Controllers\Api\Currency;

use App\Enums\FrontendResource;
use App\Http\Controllers\Controller;
use App\Http\Resources\Currency\CurrencyResource;
use App\Models\Currency;
use App\Traits\HasCache;
use Marvel\Traits\ApiResponse;

class CurrencyController extends Controller
{
    use ApiResponse, HasCache;

    public function index()
    {
        $currencies = Currency::query()->active()->orderBy('sort_order')->orderBy('code')->get();

        $currenciesCache = $this->remember(
            FrontendResource::CURRENCIES->value,
            md5(request()->fullUrl()),
            CurrencyResource::collection($currencies),
        );

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, $currenciesCache);
    }
}
