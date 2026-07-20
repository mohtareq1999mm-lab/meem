# Backend - Dashboard Feature

## Key Files

### Controller - `app/Http/Controllers/Api/General/DashboardController.php`

16 methods, all following the same pattern:
```php
public function endpoint(Request $request)
{
    try {
        $data = $this->dashboardService->getMethod($request);
        return response()->json([
            'success' => true,
            'message' => __('message.DASHBOARD.KEY_FETCHED'),
            'data' => $data
        ]);
    } catch (QueryException $e) {
        return response()->json([...], 500);
    } catch (\Exception $e) {
        return response()->json([...], 500);
    }
}
```

### Service - `app/Services/Dashboard/DashboardService.php`

16 methods with direct Eloquent queries. No repository layer.

**Caching Strategy:**
```php
Cache::remember("dashboard_{key}", 300, fn() => $this->query());
```

**Key Data Sources:**

| Method | Model | Query Pattern |
|--------|-------|---------------|
| `getOverview()` | Order, Product, User | Counts + SUM aggregations |
| `getRevenueOverview()` | Order | SUM of completed orders |
| `getOrderStatusOverview()` | Order | GROUP BY status (4 statuses queried, 5 hardcoded to 0) |
| `getRecentOrders()` | Order | Order::with('user','pickupLocation')->take($limit) |
| `getTopSellingProducts()` | Product | WHERE sold_quantity > 0 ORDER BY sold_quantity DESC |
| `getCategoryStats()` | Category | category_product pivot counts |
| `getLowStockProducts()` | Product | WHERE stock_quantity < 10 |
| `getSalesAnalytics()` | Order | Revenue comparisons + breakdowns |
| `getCustomerAnalytics()` | User, Order | Customer segmentation |
| `getProductAnalytics()` | Product | Best/worst/never sold + out of stock |
| `getOrderAnalytics()` | Order | Timeline + success/refund rates |
| `getCategoryAnalytics()` | Category | Distribution + revenue + growth |
| `getCouponAnalytics()` | Coupon, CouponUsage | Usage + revenue by coupon |
| `getCartAnalytics()` | Cart, CartItem | Abandonment rate + values |
| `getReconciliationSummary()` | PaymentReconciliationResult | Mismatch counts |
| `getFinanceAnalytics()` | Order, Transaction | Gross/net revenue, refunds, discounts |

### Known Issues

1. **Only `pending`, `completed`, `cancelled` statuses are queried.** `processing`, `refunded`, `failed`, `local_facility`, `out_for_delivery` are hardcoded to 0.
2. **No pagination** on any endpoint.
3. **`getRecentOrders()` missing `orderBy('created_at', 'desc')`** — ordering depends on global scope.
4. **Magic numbers** throughout: low stock threshold (10), category limits (15), product limits (10).
