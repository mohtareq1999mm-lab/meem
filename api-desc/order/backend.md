# Backend - Order Feature

There are **two** order controllers with different responsibilities.

---

## Controller (Admin) - `packages/marvel/src/Http/Controllers/Order/OrderController.php`

Located in the Marvel package namespace `Marvel\Http\Controllers\Order`. Extends `CoreController`, uses `ApiResponse` trait.

### Constructor

```php
$this->middleware('permission:'.Permission::VIEW_ORDERS)->only(['index']);
$this->middleware('permission:'.Permission::VIEW_ORDER)->only(['show']);
```

### index(Request $request)

1. Extract `limit` via `getLimit()` — default 15, max 100, min 1
2. Build query: `Order::query()->with(relations)`
3. Apply 10 conditional filters:
   - `status` — exact match
   - `user_id` — exact match
   - `user_email` — `LIKE %...%`
   - `promotion_id` — exact match
   - `promotion_name` — subquery on `promotion_id WHERE promotion.name LIKE %...%`
   - `product_id` — `whereHas('orderItems.product')`
   - `product_name` — `whereHas('orderItems.product')` with `name LIKE %...%`
   - `flash_sale_name` — 3-level nested `whereHas` (orderItems.product.flash_sales)
   - `shipping_method` — exact match
   - `created_from` / `created_to` — date range
   - `search` — `WHERE name LIKE %...% OR user_email LIKE %...% OR user_phone LIKE %...%`
4. Paginate: `->paginate($limit)->withQueryString()`
5. Return `apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, new OrderCollection($orders))`

### show(Request $request, string $param)

1. `Order::query()->with(relations)->findOrFail($param)` — resolves by ID or tracking number
2. Return `apiResponse(..., new OrderResource($order))`

### relations(): array

```php
['user', 'orderItems.product', 'orderItems.productVariant.attributeProducts.attributeValue', 'transactions', 'pickupLocation']
```

### getLimit(Request $request): int

```php
$limit = (int) $request->get('limit', 15);
if ($limit <= 0) return 15;
return min($limit, 100);
```

---

## Controller (Customer) - `app/Http/Controllers/Api/General/OrderController.php`

Located in `App\Http\Controllers\Api\General`. Uses `ApiResponse`, `HasCache`; depends on `OrderService`, `CartInventoryService`, `PaymentGatewayFactory`, `PaymentCheckoutHandler`.

### index(Request $request): JsonResponse

1. `OrderService::paginateForUser($request)` — scopes to `Order::forUser(userId)`, applies optional `status` filter, eager loads relations (see below), paginates (default 15, max 100)
2. Enriches each order item's product with pricing (`ProductService::enrichProductWithPricing`)
3. Caches the result (`FrontendResource::ORDERS->value` + md5(fullUrl))
4. Returns `apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, new App\OrderCollection($orders))`

### orderListRelations(): array

```php
[
    'orderItems.product' => fn($q) => $q->withAvg('reviews', 'rating'),
    'orderItems.product.media',
    'orderItems.productVariant.attributeProducts.attributeValue',
    'transactions',
    'pickupLocation',
    'latestInvoice',
]
```

### invoice(Request $request, string $uuid): JsonResponse

1. `Invoice::where('uuid', $uuid)->firstOrFail()`
2. Owner check: `$invoice->order->user_id !== $request->user()->id` → throws `AuthorizationException` (403, `NOT_AUTHORIZED`)
3. Returns `apiResponse(..., CustomerInvoiceResource::make($invoice))`

---

## API Resources

### Marvel (admin)

#### OrderCollection

Extends `ResourceCollection`, wraps `Marvel\OrderResource`. Returns `data` array + `links` (`current_page`, `from`, `to`, `last_page`, `path`, `per_page`, `total`, `next_page_url`, `prev_page_url`).

#### OrderResource

| Scope | Fields |
|-------|--------|
| Always | `id`, `order_number`, `status`, `payment_status`, `shipping_method`, `expected_delivery_at`, `customer` (when `user` relation loaded → `{id, name, email, phone}`), `created_at`, `updated_at`, `fast_shipping_fee`, `pickup_location` (when `fulfillment_type === 'pickup'`) |
| Only on `orders.show` | `customer_name`, `customer_phone`, `customer_email`, `address`, `notes`, `price`, `shipping_price`, `total_price`, `coupon`, `coupon_discount`, `promotion`, `order_items`, `transactions` |

### App (customer)

#### OrderCollection

Extends `ResourceCollection`, wraps `App\OrderResource`. Same `links` as Marvel plus `last_page_url` and `first_page_url`.

#### OrderResource

| Field | Source |
|-------|--------|
| `id`, `order_number`, `status` | order columns |
| `subtotal` | `price` (rounded) |
| `discount` | `coupon_discount + promotion_discount` (rounded) |
| `coupon`, `coupon_discount`, `coupon_discount_type` | order columns |
| `promotion_discount` | order column (rounded) |
| `total` | `total_price` (rounded) |
| `converted_total` | `converted_total_price` (rounded) |
| `currency` / `base_currency` / `catalog_currency` | `currency_code` / `base_currency_code` / `catalog_currency_code`, fallback via `CurrencyService::getBaseCode()` |
| `exchange_rate` | `currency_rate` |
| `promotion` | `{id, type, code}` when `promotion_id` set, else `null` |
| `fulfillment_type`, `payment_method`, `payment_gateway` | order columns |
| `shipping_price`, `fast_shipping_fee` | order columns (rounded) |
| `pickup_location` | only when `fulfillment_type === 'pickup'` |
| `invoice_summary` | only when `invoices` relation loaded → `{uuid, invoice_number, status, total, currency, verification_url}` |
| `order_items` | `App\OrderItemResource` collection |
| `order_has_invoice` | `latestInvoice !== null` |
| `invoice_id` | `latestInvoice?->uuid` |
| `created_at` | ISO8601 |

#### OrderItemResource (App)

`id`, `quantity`, `unit_price`, `total_price`, `converted_unit_price`, `converted_total_price` (converted using order `currency_rate`), `promotion_discount_amount`, `is_gift`, `promotion_id`, `product` (`ProductMiniResource` or `{id, name, sku}` fallback), `variant` (`OrderProductVariantResource` or `{id, attributes}` fallback, `null` if no variant).

#### OrderProductVariantResource

`id`, `price`, `current_price`, `in_stock`, `attributes` (when `attributeProducts` loaded → `{value_id, value}` list).

#### CustomerInvoiceResource

Used by `GET /api/v1/general/orders/invoice/{uuid}` — see the Invoice documentation. Returns `uuid`, `invoice_number`, `status`, `subtotal`, `shipping_price`, `total_discount`, `total`, `currency`, `payment_method`, `payment_gateway`, `generated_at`, `pdf_generated_at`, `verification_url`, `download_url`, `snapshot`.

---

## Routes

### Admin — `packages/marvel/src/Rest/Routes.php:165-166` (loaded under `api/v1`)

```php
Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('orders/{id}', [OrderController::class, 'show'])->name('orders.show');
```

### Customer — `routes/api.php:125-126` (under `v1/general`, `auth:sanctum` + `throttle:authenticated`)

```php
Route::get('orders', [OrderController::class, 'index']);
Route::get('orders/invoice/{uuid}', [OrderController::class, 'invoice']);
```

---

## Enums

- `Permission::VIEW_ORDERS` — Spatie permission for admin list
- `Permission::VIEW_ORDER` — Spatie permission for admin detail

## Model - `packages/marvel/src/Database/Models/Order.php`

Key columns:

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `order_number` | string | Unique order number |
| `tracking_number` | string | Unique tracking number |
| `status` | string | Order status |
| `payment_status` | string | Payment status |
| `user_id` | bigint | FK to users |
| `name` | string | Customer name |
| `user_email` | string | Customer email |
| `user_phone` | string | Customer phone |
| `price` | decimal | Subtotal |
| `shipping_price` | decimal | Shipping cost |
| `total_price` | decimal | Grand total |
| `coupon_discount` | decimal | Coupon discount |
| `promotion_discount` | decimal | Promotion discount |
| `shipping_method` | string | Shipping method |
| `expected_delivery_at` | timestamp | ETA |
| `notes` | text | Order notes |
| `fulfillment_type` | string | `delivery` or `pickup` |