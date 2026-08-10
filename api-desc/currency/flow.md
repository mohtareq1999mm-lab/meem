# Data Flow - Currency Feature

## Flow 1: Create Currency (Admin)

```
Admin Client
  |
  POST /api/v1/currencies  { code: "EGP", name: {...}, symbol: {...}, ... }
  Authorization: Bearer <token>
  |
  v
auth:sanctum middleware
  |
  v
permission:create-currency middleware (Spatie)
  |
  v
CurrencyController@store(StoreCurrencyRequest)
  |
  +-- prepareForValidation(): code = strtoupper("egp") -> "EGP"
  +-- validate: size:3, regex ^[A-Za-z]{3}$, unique:currencies,code
  |     Fail? -> 422 field errors
  |
  v
CurrencyService::storeCurrency($validated)
  |
  +-- Currency::create($data)   [setCodeAttribute uppercases]
  +-- invalidatePriceCaches()
  |     flush tags: currencies, products, products_{type}...
  |
  v
CurrencyResource::make($currency)
  |
  v
{ status:200, message: 'Currency created successfully', success:true, data }
```

## Flow 2: Delete Currency with Guards (Admin)

```
Admin Client
  |
  DELETE /api/v1/currencies/5
  |
  v
auth:sanctum -> permission:delete-currency
  |
  v
CurrencyController@destroy(5)
  |
  +-- Currency::withTrashed()->find(5)      [not found -> 404]
  |
  v
CurrencyService::deleteCurrency($currency)
  |
  +-- code === getBaseCode()?
  |     YES -> CurrencyInUseException(REASON_BASE_CURRENCY)
  |            -> 409 CANNOT_DELETE_BASE_CURRENCY
  |
  +-- $currency->rates()->exists()?
  |     YES -> CurrencyInUseException(REASON_REFERENCED_BY_RATES)
  |            -> 409 CANNOT_DELETE_CURRENCY_IN_USE
  |
  +-- $currency->delete()   [soft delete -> deleted_at]
  +-- invalidatePriceCaches()
  |
  v
{ status:200, message: 'Currency deleted successfully', success:true }
```

## Flow 3: Set Base Currency (Admin)

```
Admin Client
  |
  POST /api/v1/currencies/2/set-base
  |
  v
auth:sanctum -> permission:set-base-currency
  |
  v
CurrencyController@setBase(2)
  |
  +-- Currency::withTrashed()->find(2)      [not found -> 404]
  |
  v
CurrencyService::setBaseCurrency($currency)
  |
  +-- DB::transaction
  |     +-- Settings::lockForUpdate()->first()
  |     +-- !$currency->is_active -> CurrencyInactiveException -> 422 CURRENCY_INACTIVE
  |     +-- CurrencyRate where currency_id=2 and whereDate(effective_date <= today) exists?
  |           NO -> CurrencyRateNotFoundException -> 422 EXCHANGE_RATE_NOT_FOUND
  |     +-- options['base_currency_code'] = code
  |     +-- options['currency'] = code
  |     +-- settings->save()
  |     +-- Cache::forget('cached_settings_{locale}' / _en / _ar)
  |
  +-- memoize $this->baseCode / $this->baseCurrency
  +-- invalidatePriceCaches(flushSettings: true)
  |
  v
{ status:200, message: 'Base currency updated successfully', success:true, data }
```

## Flow 4: Convert Currency (internal engine)

```
Caller (Order snapshot / Product price / direct)
  |
  v
CurrencyService::convert(amount, fromCode, toCode, ?date)
  |
  v
CurrencyConversionService::convert()
  |
  +-- uppercase codes; date ?? today
  +-- from === to? -> identity result (rate '1', no DB query)
  |
  v
resolveRate(fromCode, date)      [CurrencyRate: whereDate(effective_date <= date) orderByDesc -> first]
  |                                none -> CurrencyRateNotFoundException
  v
resolveRate(toCode, date)
  |
  v
converted = bcdiv(bcmul(amount, targetRate, 6), sourceRate, 6)
rate      = bcdiv(targetRate, sourceRate, 6)
  |
  v
CurrencyConversionResult(amount, round(converted,2), rate, date, from, to, sourceRate, targetRate)
```

## Flow 5: Order Price Snapshot

```
OrderCreationService::createOrder / updateOrder
  |
  +-- totalPrice = round(finalTotal + shipping + fastFee, 2)
  +-- resolveCurrencySnapshot($totalPrice)
  |     +-- catalogCode = CurrencyService::getCatalogCode()
  |     +-- baseCode    = CurrencyService::getBaseCode()
  |     +-- conversion  = convert($totalPrice, catalogCode, baseCode)
  |     +-- [currency_code, base_currency_code, currency_rate=rate,
  |          currency_rate_date=effectiveDate, converted_total_price=convertedAmount]
  |
  +-- Schema::hasColumn('orders','currency_code') ? merge into create/update data
  |
  v
Order::create / update  ->  OrderResource exposes currency/base_currency/exchange_rate/converted_total
```

## Flow 6: Create Exchange Rate (upsert)

```
Admin Client
  |
  POST /api/v1/currency-rates  { currency_id:2, exchange_rate:"0.25", effective_date:"2026-08-10" }
  |
  v
auth:sanctum -> permission:create-exchange-rate
  |
  v
CurrencyRateController@store(StoreCurrencyRateRequest)
  |
  +-- validate: currency_id exists, exchange_rate numeric > 0, effective_date date
  |
  v
CurrencyRateService::store($validated)
  |
  +-- CurrencyRate where currency_id=2 AND whereDate(effective_date='2026-08-10') first?
  |     EXISTS -> update exchange_rate        (upsert)
  |     MISSING -> CurrencyRate::create        (insert)
  +-- invalidatePriceCaches()
  |
  v
{ status:200, message: 'Exchange rate created successfully', success:true, data+currency }
```

## Flow 7: List Currencies (Public, cached)

```
Storefront
  |
  GET /api/v1/general/currencies      [no auth, throttle:public-api]
  |
  v
App\CurrencyController@index()
  |
  +-- Currency::active()->orderBy('sort_order')->orderBy('code')->get()
  +-- HasCache::remember('currencies', md5(fullUrl), CurrencyResource::collection(...), 4h)
  |     cache hit? -> return cached
  |     cache miss? -> store & return
  |
  v
{ status:200, message, success:true, data: [...] }
  |
  (Cache invalidated on any currency/rate write via invalidatePriceCaches)
```
