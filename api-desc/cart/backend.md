# Cart Module — Backend Architecture

## Overview

The Cart module manages authenticated user shopping carts with inventory reservation. Each user has one active cart. Items support two shipping methods (SCHEDULED / FAST), variant products, price snapshotting, coupon application, and promotion-based gift items. Inventory is reserved at add time and released at delete/expiry/checkout.

## Endpoints

| Method | URL | Auth | Purpose |
|--------|-----|------|---------|
| GET | `/api/v1/cart` | `auth:sanctum` | List user carts (paginated) |
| POST | `/api/v1/cart` | `auth:sanctum` | Add item to cart |
| GET | `/api/v1/cart/{id}` | `auth:sanctum` | Show specific cart |
| POST | `/api/v1/cart/bulk-items` | `auth:sanctum` | Bulk add items |
| PUT | `/api/v1/cart/update-item` | `auth:sanctum` | Update item quantity (set mode) |
| DELETE | `/api/v1/cart/delete-item/{itemId}` | `auth:sanctum` | Remove single item |
| DELETE | `/api/v1/cart/delete-items` | `auth:sanctum` | Clear entire cart |

## Route Definitions

**File:** `packages/marvel/src/Rest/Routes.php` (lines 160-168)

```php
Route::middleware(['auth:sanctum', "throttle:cart"])->group(function () {
    Route::get('cart', [CartController::class, 'index']);
    Route::post('cart', [CartController::class, 'store']);
    Route::get('cart/{id}', [CartController::class, 'show'])->whereNumber('id');
    Route::post('cart/bulk-items', [CartController::class, 'pluckItemsToCart']);
    Route::put('cart/update-item', [CartController::class, 'update']);
    Route::delete('cart/delete-item/{itemId}', [CartController::class, 'deleteItemFromCart']);
    Route::delete('cart/delete-items', [CartController::class, 'destroy']);
});
```

## Middleware

| Middleware | Applied At | Purpose |
|------------|------------|---------|
| `auth:sanctum` | Route group | Require authentication |
| `throttle:cart` | Route group | Rate limit (20 req/min per user) |

**Rate Limiter Definition** (`app/Providers/RouteServiceProvider.php`):
```php
RateLimiter::for('cart', function (Request $request) {
    return Limit::perMinute(20)->by(optional($request->user())->id ?: $request->ip());
});
```

## Controller Flow

**File:** `packages/marvel/src/Http/Controllers/CartController.php`

**Constructor injection:**
- `CartRepository $repository`
- `CartInventoryService $inventoryService`

```
GET /cart
  → CartController::index(Request)
    → Cart::where('user_id', auth()->id())
    → Eager load: items.product, items.productVariant.attributeProducts.attributeValue.attribute
    → Paginate (default 15)
    → CartResource::collection()
    → apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, ...)

POST /cart
  → CartController::store(CartCreateRequest)
    → $this->repository->storeCart($request)
      → Delegates to persistCart($request, mode='add')
    → $this->repository->revalidatePromotion($cart)
    → CartResource::make($cart)
    → apiResponse(CREATE_CART_SUCCESSFULLY, 201, true, ...)
    → On exception: apiResponse(errorMessage, 400, false)

GET /cart/{id}
  → CartController::show(Request, $id)
    → Find cart or fail
    → Authorize: $cart->user_id === auth()->id() (else AuthorizationException 403)
    → CartResource::make($cart)
    → apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, ...)

PUT /cart/update-item
  → CartController::update(CartUpdateRequest)
    → $this->repository->updateCart($request)
      → Delegates to persistCart($request, mode='set')
    → $this->repository->revalidatePromotion($cart)
    → CartResource::make($cart)
    → apiResponse(UPDATE_CART_SUCCESSFULLY, 200, true, ...)

DELETE /cart/delete-item/{itemId}
  → CartController::deleteItemFromCart(Request, $itemId)
    → Get user's cart via auth()->user()->cart
    → Find item in cart items
    → $this->inventoryService->releaseItem($item, true)
      → lockForUpdate, releaseStock, delete item, clear coupon if last
    → $this->repository->revalidatePromotion($cart)
    → Recalculate cart total_price
    → apiResponse(DELETE_CART_ITEM_SUCCESSFULLY, 200, true, ...)
    → On failure: apiResponse(DELETE_CART_ITEM_FAILED, 400, false)

DELETE /cart/delete-items
  → CartController::destroy(Request)
    → Get user's cart via auth()->user()->cart
    → If coupon applied AND no confirm param:
        return apiResponse(COUPON_DELETE_CART_WARNING, 400, false)
    → $this->inventoryService->releaseCart($cart, true)
      → For each item: lockForUpdate, releaseStock, delete
      → Reset cart totals
    → apiResponse(DELETE_CART_SUCCESSFULLY, 200, true, ...)
    → On fail: apiResponse(DELETE_CART_ITEM_FAILED, 400, false)

POST /cart/bulk-items
  → CartController::pluckItemsToCart(Request)
    → Inline validation of items array
    → Filter out non-existent / soft-deleted products
    → DB::transaction
      → For each valid item: clone request, call storeCart()
    → apiResponse(CREATE_CART_SUCCESSFULLY, 200, true, {..., skipped_product_ids: [...]})
```

## Repository

**File:** `packages/marvel/src/Database/Repositories/CartRepository.php`
**Extends:** `BaseRepository` (Prettus repository + caching)

| Method | Description |
|--------|-------------|
| `model()` | Returns `Cart::class` |
| `storeCart($request)` | Add mode — delegates to `persistCart($request, 'add')` |
| `updateCart($request)` | Set mode — delegates to `persistCart($request, 'set')` |
| `persistCart($request, $mode)` | Core logic: create/find cart, sync items, recalculate total |
| `syncItems($cart, $item, $mode)` | Validate product/variant, check stock, call inventory service |
| `revalidatePromotion($cart)` | Clear promotion_id/discount_amount on all items |

### `persistCart()` Flow
```
1. Get userId from auth user (401 if missing)
2. lockForUpdate on Cart where user_id = userId
3. Create cart if not exists (status = 'active')
4. Always reset status to 'active'
5. If item data present: syncItems($cart, $item, $mode)
6. Recalculate total_price = sum of items total_price
7. Return cart with eager loads
8. On exception: rollback, throw HttpException
```

### `syncItems()` Flow
```
1. Extract product_id, quantity, product_variant_id, attributes, shipping_method
2. Normalize shipping_method to uppercase
3. If mode='set' and no shipping_method provided: preserve existing item's method
4. Product::findOrFail($productId) — validate exists
5. Check FAST shipping eligibility (if FAST and !is_fast_shipping_available → throw)
6. Variant path:
   - Find variant by product_id + variant_id
   - Check variant->available_stock >= quantity
   - Call inventoryService->reserveItem() with variant
7. Simple product path:
   - Check product->available_stock >= quantity
   - Call inventoryService->reserveItem() with null variant
```

## Inventory Service

**File:** `app/Services/General/CartInventoryService.php` (550 lines)

The heart of the cart module. Manages all inventory operations with row-level locks.

### Constants

```php
private const CART_TTL_DAYS = 3;
```

### Public Methods

| Method | Description |
|--------|-------------|
| `reserveItem($cart, $product, $variant, $qty, $mode, $attrs, $shipping)` | Reserve inventory, create/update cart item, snapshot price |
| `reserveGiftItem($cart, $product, $promotion, $qty, $variantId, $shipping)` | Reserve inventory for a free gift item (price = 0) |
| `releaseItem($item, $deleteItem)` | Release reserved inventory, optionally delete item |
| `releaseCart($cart, $deleteItems)` | Release all inventory in a cart |
| `finalizeCart($cart)` | Convert reserved inventory to sold (at checkout) |
| `finalizeItemsByShippingMethod($cart, $shippingMethod)` | Finalize one shipping group, release the other |
| `ensureCartReservation($cart)` | Sync reservation quantities to current item quantities |
| `syncCartItemReservation($item)` | Reconcile reserved_quantity with quantity |
| `getActiveCartForUser($user)` | Get active cart with eager loaded relations |
| `expireCarts()` | Chunked expiration of all expired carts |
| `expireSingleCart($cart)` | Expire a single cart (release stock, delete items) |
| `deductStockForOrder($order)` | Legacy: directly decrement stock for orders bypassing cart |

### `reserveItem()` Detailed Flow
```
1. lockForUpdate on cart row
2. findCartItemForLock() — existing non-gift item with same product+variant+shipping
3. Calculate desiredQuantity:
   - mode='add': existing.quantity + quantity
   - mode='set': quantity
4. Check desiredQuantity >= 1 (throw QUANTITY_MINIMUM if violated)
5. lockForUpdate on product or variant inventory row
6. Calculate delta = desiredQuantity - existingReservedQuantity
7. If delta > 0: reserveStock($stock, delta)
   If delta < 0: releaseStock($stock, abs(delta))
8. Snapshot price via ProductPricingService
9. Build payload: product_id, variant_id, quantity, reserved_quantity, price,
   total_price = round(price * quantity, 2), attributes, shipping_method,
   promotion_id = null, discount_amount = 0
10. Update or create CartItem
11. touchCartReservation() — set expires_at = now() + 3 days
12. Return refreshed item
```

### `reserveGiftItem()` Flow
```
1. lockForUpdate on cart row
2. Find existing gift item by cart_id + product_id + promotion_id + is_gift = true
3. Resolve variant:
   - If product has variations and variantId given: lock and verify
   - If existing item had variant: re-lock it
   - If no variant: auto-select first variant with available stock
4. Price = 0, total_price = 0, is_gift = true, promotion_id set
5. Update or create gift item
6. touchCartReservation()
```

### `releaseItem()` Flow
```
1. lockForUpdate on cart item
2. If reserved_quantity > 0: lockInventoryRow, releaseStock
3. If deleteItem: delete cart item, clear coupon if last item
```

### `finalizeItem()` Flow
```
1. lockForUpdate on inventory row
2. Verify reserved_quantity >= quantity (throw RESERVED_STOCK_INSUFFICIENT)
3. Verify stock_quantity >= quantity (throw PHYSICAL_STOCK_INSUFFICIENT)
4. Decrement stock_quantity by quantity
5. Decrement reserved_quantity by quantity
6. Increment sold_quantity by quantity
7. Update in_stock flag
```

### Stock Helper Methods
| Method | Description |
|--------|-------------|
| `lockInventoryRow($product, $variant)` | Row-lock product or variant inventory row |
| `lockInventoryRowByItem($item)` | Row-lock inventory from a CartItem reference |
| `getAvailableStock($stock)` | `max(0, stock_quantity - reserved_quantity)` |
| `reserveStock($stock, $qty)` | Check availability, increment reserved_quantity |
| `releaseStock($stock, $qty)` | Decrement reserved_quantity (floor 0) |
| `touchCartReservation($cart)` | Set status=active, reserved_at=now, expires_at=now+3days |
| `getVariantAttributes($variant)` | Map attributeProducts to key-value array |

## Transaction Concurrency Model

Every inventory operation runs in a `DB::transaction()` with `lockForUpdate()`:

| Lock Target | Method | Reason |
|-------------|--------|--------|
| Cart row | `persistCart()`, `reserveItem()` | Prevent double-creation, serialize mutations |
| Cart item row | `findCartItemForLock()`, `releaseItem()` | Prevent race on same product+variant+shipping |
| Product/ProductVariant | `lockInventoryRow()` | Serialize stock mutations |

## Model

### Cart Model
**File:** `packages/marvel/src/Database/Models/Cart.php`
**Table:** `carts`

| Property | Details |
|----------|---------|
| Fillable | `user_id`, `coupon`, `total_price`, `status`, `reserved_at`, `expires_at` |
| Casts | `reserved_at` → datetime, `expires_at` → datetime |

**Relationships:**
| Relation | Type | Foreign |
|----------|------|---------|
| `user()` | BelongsTo | `user_id` |
| `items()` | HasMany | `cart_id` |
| `scheduledItems()` | HasMany | `cart_id` where `shipping_method = SCHEDULED` |
| `fastItems()` | HasMany | `cart_id` where `shipping_method = FAST` |

### CartItem Model
**File:** `packages/marvel/src/Database/Models/CartItem.php`
**Table:** `cart_items`

| Property | Details |
|----------|---------|
| Fillable | All columns: `cart_id`, `product_id`, `product_variant_id`, `quantity`, `price`, `total_price`, `attributes`, `reserved_quantity`, `discount_amount`, `shipping_method`, `is_gift`, `promotion_id` |
| Casts | `price` → float, `total_price` → float, `discount_amount` → float, `attributes` → array, `is_gift` → boolean |

**Relationships:**
| Relation | Type | Foreign |
|----------|------|---------|
| `cart()` | BelongsTo | `cart_id` |
| `product()` | BelongsTo (withTrashed) | `product_id` |
| `productVariant()` | BelongsTo | `product_variant_id` |
| `promotion()` | BelongsTo | `promotion_id` |

## Resources

### CartResource
**File:** `packages/marvel/src/Http/Resources/CartResource.php`

```json
{
  "id": "integer",
  "user_id": "integer",
  "coupon": "object | null",
  "coupon_code": "string | null",
  "status": "string",
  "reserved_at": "datetime | null",
  "expires_at": "datetime | null",
  "total_items": "integer",
  "total_quantity": "integer",
  "subtotal": "float (rounded 2dp)",
  "total_price": "float (same as subtotal)",
  "coupon_discount": "float",
  "total_after_coupon": "float",
  "normal_items_count": "integer",
  "fast_items_count": "integer",
  "normal_items": "CartItemResource[] (SCHEDULED)",
  "fast_items": "CartItemResource[] (FAST)",
  "has_eligible_promotion": "boolean"
}
```

### CartItemResource
**File:** `packages/marvel/src/Http/Resources/CartItemResource.php`

```json
{
  "id": "integer",
  "product_id": "integer",
  "product_variant_id": "integer | null",
  "quantity": "integer",
  "price": "float (rounded 2dp)",
  "total_price": "float (rounded 2dp)",
  "attributes": "array | null",
  "shipping_method": "string",
  "promotion_id": "integer | null",
  "discount_amount": "float (rounded 2dp)",
  "is_gift": "boolean",
  "product": "object { id, name, slug, image: { thumbnail } } | null"
}
```

## Request Validation

### CartCreateRequest

| Field | Rules |
|-------|-------|
| `item` | `required`, `array`, `min:1` |
| `item.product_id` | `required`, `integer`, `exists:products,id` |
| `item.quantity` | `required`, `integer`, `min:1` |
| `item.product_variant_id` | `sometimes`, `nullable`, `integer`, `exists:product_variants,id` |
| `item.attributes` | `sometimes`, `array` |
| `item.shipping_method` | `required`, `string`, `Rule::in([ShippingMethod::SCHEDULED, ShippingMethod::FAST, 'scheduled', 'fast'])` |

### CartUpdateRequest

| Field | Rules |
|-------|-------|
| `item` | `required`, `array`, `min:1` |
| `item.product_id` | `required_with:item`, `integer`, `exists:products,id` |
| `item.quantity` | `required_with:item`, `integer`, `min:1` |
| `item.product_variant_id` | `sometimes`, `nullable`, `integer`, `exists:product_variants,id` |
| `item.attributes` | `sometimes`, `array` |
| `item.shipping_method` | `sometimes`, `string`, `Rule::in(...)` |

**Key difference:** `shipping_method` is `required` on create, `sometimes` on update. On update, if omitted, the existing item's shipping method is preserved.

## Inventory Lifecycle

```
ADD ITEM:
  1. syncItems() — validate product/variant/stock/FAST eligibility
  2. reserveItem() — lock cart/inventory, delta reserve, snapshot price, create/update item, touch TTL

UPDATE ITEM:
  Same as add but mode='set' (absolute quantity), preserves shipping method if omitted

DELETE ITEM:
  1. releaseItem($item, true) — lock item/inventory, releaseStock, delete item, clear coupon if last
  2. Recalculate cart total_price, revalidate promotion

CLEAR CART:
  1. releaseCart($cart, true) — releaseItem() for each item, reset cart totals

EXPIRE (TTL = 3 days):
  Scheduled command (carts:expire / cart:expire) every 5 min
  1. Chunk carts where expires_at <= now() AND status = active
  2. For each: lock cart, release all stock, delete items, set status = expired

FINALIZE (at checkout):
  1. finalizeCart() — finalizeStock() for all items (reserved → sold)
  2. finalizeItemsByShippingMethod() — finalize one shipping group, release the other
```

## Shipping Method Handling

**Enum:** `Marvel\Enums\ShippingMethod`
- `SCHEDULED` — default, normal delivery
- `FAST` — requires `product.is_fast_shipping_available === true`

Normalized to uppercase in `syncItems()`. Items are split into `normal_items` and `fast_items` in CartResource.

## Coupon on Cart

- `carts.coupon` stores the coupon code as a string
- Applied via `POST /api/v1/coupons/add-to-cart` (CouponController)
- Cleared automatically when last item is removed from cart
- `CartResource` calculates `coupon_discount` dynamically via `CouponCalculator::calculate()`

## Promotion on Cart

- CartResource calls `PromotionService::hasEligiblePromotion($cart)` for `has_eligible_promotion` flag
- Applied via `PromotionService::applySelectedPromotion()` — sets discount amounts, adds gift items
- Cleared on every cart mutation by `revalidatePromotion()`:
  ```php
  items()->whereNotNull('promotion_id')->orWhere('discount_amount', '>', 0)
    ->update(['promotion_id' => null, 'discount_amount' => 0, 'total_price' => DB::raw('ROUND(price * quantity, 2)')]);
  ```

## Price Calculation

- Prices are **snapshotted at reservation time** (not recalculated at checkout)
- `ProductPricingService::calculateProductCurrentPrice()` / `calculateVariantCurrentPrice()`
- Cart item `total_price = round(price * quantity, 2)`
- Cart `total_price = ROUND(SUM(item_total_price), 2)` in SQL
- CartResource `subtotal = round(items->sum('total_price'), 2)`
- `total_after_coupon = max(0, subtotal - coupon_discount)`

## Scheduled Commands

| Command | File | Schedule | Purpose |
|---------|------|----------|---------|
| `carts:expire` | `app/Console/Commands/ExpireCarts.php` | Every 5 min | Expire abandoned carts |
| `cart:expire` | `app/Console/Commands/ExpireAbandonedCarts.php` | Every 5 min | Same logic, duplicate |

Both call `CartInventoryService::expireCarts()` which chunks by 100 and expires each cart.

## Database Schema

### Table: `carts`

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint unsigned | PK, auto-increment |
| `user_id` | bigint unsigned | FK → users.id (nullOnDelete) |
| `coupon` | string | NULLABLE |
| `total_price` | decimal | NULLABLE |
| `status` | string | DEFAULT 'active' |
| `reserved_at` | datetime | NULLABLE |
| `expires_at` | datetime | NULLABLE |
| `created_at` | timestamp | NULLABLE |
| `updated_at` | timestamp | NULLABLE |

### Table: `cart_items`

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint unsigned | PK, auto-increment |
| `cart_id` | bigint unsigned | FK → carts.id |
| `product_id` | bigint unsigned | FK → products.id (nullOnDelete) |
| `product_variant_id` | bigint unsigned | FK → product_variants.id, NULLABLE |
| `quantity` | integer | NOT NULL |
| `price` | decimal | Snapshotted unit price |
| `total_price` | decimal | `ROUND(price * quantity, 2)` |
| `attributes` | json | NULLABLE |
| `reserved_quantity` | integer | Quantity held in stock |
| `discount_amount` | decimal | Promotion discount |
| `shipping_method` | string | `SCHEDULED` or `FAST` |
| `is_gift` | boolean | DEFAULT false |
| `promotion_id` | bigint unsigned | FK → promotions.id, NULLABLE |

### Foreign Key Fix Migration
Changes both `carts.user_id` and `cart_items.product_id` FK constraints to `nullOnDelete()` (were `cascadeOnDelete()`).

## User Relationship

**File:** `packages/marvel/src/Database/Models/User.php` (line 148)
```php
public function cart()
{
    return $this->hasOne(Cart::class);
}
```

Each user has one active cart at a time.

## Translation Keys Used

| Key | Context |
|-----|---------|
| `MESSAGE.FETCH_DATA_SUCCESSFULLY` | GET response |
| `MESSAGE.CREATE_CART_SUCCESSFULLY` | POST response |
| `MESSAGE.UPDATE_CART_SUCCESSFULLY` | PUT response |
| `MESSAGE.DELETE_CART_SUCCESSFULLY` | DELETE /delete-items response |
| `MESSAGE.DELETE_CART_ITEM_SUCCESSFULLY` | DELETE /delete-item response |
| `ERROR.DELETE_CART_ITEM_FAILED` | DELETE /delete-item failure |
| `ERROR.CART_NOT_FOUND` | Cart not found (destroy/show) |
| `MESSAGE.COUPON_DELETE_CART_WARNING` | Clear cart with coupon without confirm |
| `cart.inventory.quantity_minimum` | Quantity < 1 |
| `cart.inventory.quantity_exceeds_stock` | Requested > available |
| `cart.inventory.reserved_stock_insufficient` | Reserved stock < quantity |
| `cart.inventory.physical_stock_insufficient` | Physical stock < quantity |

## Dependencies

| File | Role |
|------|------|
| `packages/marvel/src/Rest/Routes.php` | Cart route definitions |
| `packages/marvel/src/Http/Controllers/CartController.php` | Cart controller |
| `packages/marvel/src/Http/Requests/CartCreateRequest.php` | Create validation |
| `packages/marvel/src/Http/Requests/CartUpdateRequest.php` | Update validation |
| `packages/marvel/src/Http/Resources/CartResource.php` | Cart API resource |
| `packages/marvel/src/Http/Resources/CartItemResource.php` | Cart item API resource |
| `packages/marvel/src/Database/Models/Cart.php` | Cart model |
| `packages/marvel/src/Database/Models/CartItem.php` | Cart item model |
| `packages/marvel/src/Database/Repositories/CartRepository.php` | Cart repository |
| `packages/marvel/src/Database/Repositories/CouponRepository.php` | Coupon repository |
| `app/Services/General/CartInventoryService.php` | Inventory management |
| `app/Services/General/PromotionService.php` | Promotion eligibility & application |
| `app/Services/Coupon/CouponCalculator.php` | Coupon discount calculation |
| `packages/marvel/src/Services/Pricing/ProductPricingService.php` | Price snapshotting |
| `packages/marvel/src/Enums/ShippingMethod.php` | Shipping method enum |
| `app/DTOs/CheckoutTotals.php` | Checkout totals DTO |
| `app/Providers/RouteServiceProvider.php` | Rate limiter config |
| `app/Console/Commands/ExpireCarts.php` | Cart expiration command |
| `app/Console/Commands/ExpireAbandonedCarts.php` | Cart expiration command (duplicate) |
| `packages/marvel/database/migrations/..._create_carts_table.php` | Carts migration |
| `packages/marvel/database/migrations/..._create_cart_items_table.php` | Cart items migration |
| `packages/marvel/database/migrations/2026_07_17_000001_fix_cart_foreign_key_cascades.php` | FK fix migration |
| `database/seeders/CartSeeder.php` | Cart seeder |
| `tests/Feature/CartApiTest.php` | Feature tests |
| `tests/Feature/CartExpirationTest.php` | Expiration tests |
