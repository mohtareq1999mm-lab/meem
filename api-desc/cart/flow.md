# Request Flows — Cart Module

## Flow 1: List Carts

```
Client → GET /api/v1/cart?page=1&per_page=15
         ↓
    [auth:sanctum] middleware → authenticate token
         ↓
    [throttle:cart] → check rate limit (20/min)
         ↓
    CartController@index(Request)
         ↓
    Cart::where('user_id', auth()->id())
         ↓
    Eager load:
      - items.product
      - items.productVariant.attributeProducts.attributeValue.attribute
         ↓
    Paginate (default 15 per page)
         ↓
    CartResource::collection($carts) → transform each cart
         ↓
    Return: { status:200, message, success:true, data: { data[], pagination_meta } }
```

## Flow 2: Add Item to Cart

```
Client → POST /api/v1/cart { "item": { "product_id": 10, "quantity": 2, "shipping_method": "SCHEDULED" } }
         ↓
    [auth:sanctum] + [throttle:cart]
         ↓
    CartCreateRequest → validation rules:
      - item: required, array
      - product_id: required, integer, exists:products,id
      - quantity: required, integer, min:1
      - shipping_method: required, in:SCHEDULED,FAST,scheduled,fast
         ↓
    Fail? → 422 with field errors
         ↓
    CartController@store(CartCreateRequest)
         ↓
    CartRepository::storeCart($request) → persistCart($request, 'add')
         ↓
    DB::beginTransaction()
      ├─ lockForUpdate on Cart where user_id = auth()->id()
      ├─ Create cart if not exists (status='active')
      ├─ syncItems($cart, $item, 'add')
      │   ├─ Product::findOrFail($productId)
      │   ├─ Check FAST eligibility (if FAST → product.is_fast_shipping_required)
      │   ├─ Validate stock >= quantity
      │   └─ CartInventoryService::reserveItem(...)
      │       ├─ lockForUpdate on cart row
      │       ├─ findCartItemForLock() — existing item?
      │       ├─ lockForUpdate on product/variant inventory
      │       ├─ Calculate delta → reserveStock or releaseStock
      │       ├─ Snapshot price via ProductPricingService
      │       ├─ Create or update CartItem
      │       └─ touchCartReservation() → 3-day TTL
      └─ Recalculate total_price = SUM(items.total_price)
         ↓
    DB::commit()
         ↓
    CartRepository::revalidatePromotion($cart)
      → Clear promotion_id/discount_amount on all items
         ↓
    CartResource::make($cart)
         ↓
    Return: { status:201, message, success:true, data }
```

## Flow 3: Show Cart

```
Client → GET /api/v1/cart/1
         ↓
    [auth:sanctum] + [throttle:cart]
         ↓
    CartController@show(Request, $id)
         ↓
    $cart = Cart::findOrFail($id)
         ↓
    Authorize: $cart->user_id === auth()->id()?
      ├─ Yes → continue
      └─ No → AuthorizationException → 403
         ↓
    Eager load items + product + variant attributes
         ↓
    CartResource::make($cart)
         ↓
    Return: { status:200, message, success:true, data }
```

## Flow 4: Update Item Quantity

```
Client → PUT /api/v1/cart/update-item { "item": { "product_id": 10, "quantity": 5 } }
         ↓
    [auth:sanctum] + [throttle:cart]
         ↓
    CartUpdateRequest → validation (shipping_method is optional this time)
         ↓
    CartController@update(CartUpdateRequest)
         ↓
    CartRepository::updateCart($request) → persistCart($request, 'set')
         ↓
    DB::beginTransaction()
      ├─ lockForUpdate on Cart
      ├─ syncItems($cart, $item, 'set')
      │   ├─ If shipping_method omitted: preserve existing item's method
      │   ├─ Product::findOrFail(...)
      │   ├─ CartInventoryService::reserveItem(...)
      │   │   ├─ mode='set': desiredQuantity = quantity (absolute)
      │   │   ├─ delta = desiredQuantity - existingReservedQuantity
      │   │   ├─ delta > 0 → reserveStock(delta)
      │   │   └─ delta < 0 → releaseStock(|delta|)
      │   └─ Update CartItem
      └─ Recalculate total_price
         ↓
    DB::commit()
         ↓
    revalidatePromotion($cart)
         ↓
    Return: { status:200, message, success:true, data }
```

## Flow 5: Delete Single Item

```
Client → DELETE /api/v1/cart/delete-item/1
         ↓
    [auth:sanctum] + [throttle:cart]
         ↓
    CartController@deleteItemFromCart(Request, $itemId)
         ↓
    $cart = auth()->user()->cart
         ↓
    $item = $cart->items()->where('id', $itemId)->firstOrFail()
         ↓
    CartInventoryService::releaseItem($item, true)
      ├─ lockForUpdate on cart item
      ├─ If reserved_quantity > 0:
      │   ├─ lockInventoryRowByItem($item)
      │   └─ releaseStock($stock, reserved_quantity)
      ├─ Delete cart item
      └─ If no items remain: clear coupon ($cart->update(['coupon' => null]))
         ↓
    Recalculate cart total_price = SUM(remaining items)
         ↓
    revalidatePromotion($cart)
         ↓
    Return: { status:200, message, success:true }
```

## Flow 6: Clear Entire Cart

```
Client → DELETE /api/v1/cart/delete-items
         ↓
    [auth:sanctum] + [throttle:cart]
         ↓
    CartController@destroy(Request)
         ↓
    $cart = auth()->user()->cart
         ↓
    Has coupon? AND no 'confirm' param?
      ├─ Yes → Return: { status:400, message: COUPON_DELETE_CART_WARNING }
      └─ No → continue
         ↓
    CartInventoryService::releaseCart($cart, true)
      ├─ lockForUpdate on cart + eager load items
      ├─ For each item: releaseItem($item, true)
      │   ├─ lockForUpdate
      │   ├─ releaseStock
      │   └─ delete
      └─ Reset cart: total_price = 0, status = 'active'
         ↓
    Return: { status:200, message, success:true }
```

## Flow 7: Bulk Add Items

```
Client → POST /api/v1/cart/bulk-items { "items": [{...}, {...}] }
         ↓
    [auth:sanctum] + [throttle:cart]
         ↓
    CartController@pluckItemsToCart(Request)
         ↓
    Inline validation:
      - items: required|array
      - items.*.product_id: required|integer
      - items.*.quantity: required|integer|min:1
      - items.*.shipping_method: required|in:scheduled,fast,SCHEDULED,FAST
         ↓
    Fail? → 422
         ↓
    Query all given product_ids from DB (withTrashed)
         ↓
    Filter: only products that exist AND are not soft-deleted
         ↓
    DB::transaction
      └─ For each valid item:
          ├─ Clone a new Request with the item data
          └─ $this->repository->storeCart(clonedRequest) → persistCart('add')
         ↓
    Collect skipped_product_ids (products that didn't exist)
         ↓
    Return: { status:200, message, success:true, data: { ..., skipped_product_ids: [...] } }
```

## Flow 8: Cart Expiration (Scheduled Task)

```
Console → php artisan carts:expire (every 5 minutes)
         ↓
    ExpireCarts::handle()
         ↓
    CartInventoryService::expireCarts()
         ↓
    Chunk carts where status='active' AND expires_at <= now() (chunk size: 100)
         ↓
    For each expired cart (in a transaction):
      ├─ lockForUpdate on cart + items
      ├─ Double-check: if expires_at is now in future → skip
      ├─ For each item with reserved_quantity > 0:
      │   ├─ lockInventoryRowByItem
      │   └─ releaseStock
      ├─ Delete all items
      └─ Set cart: status='expired', expires_at=null, reserved_at=null, total_price=0
```

## Flow 9: Cart Finalization (at Checkout)

```
Checkout process → CartInventoryService::finalizeCart($cart)
         ↓
    lockForUpdate on cart + eager load items
         ↓
    For each item where reserved_quantity > 0:
      ├─ lockForUpdate on product/variant inventory
      ├─ Verify reserved_quantity >= quantity (throw if insufficient)
      ├─ Verify stock_quantity >= quantity (throw if insufficient)
      ├─ Decrement: stock_quantity -= quantity
      ├─ Decrement: reserved_quantity -= quantity
      ├─ Increment: sold_quantity += quantity
      └─ Update in_stock flag
         ↓
    Delete all cart items
         ↓
    Set cart: status='checked_out', expires_at=null, reserved_at=null, total_price=0
```
