<?php

namespace App\Services\Dashboard;

use App\Models\PaymentReconciliationResult;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Transaction;
use Marvel\Database\Models\User;

class DashboardService
{
    // =========================================================================
    // Existing Endpoints
    // =========================================================================

    public function getOverview(Request $request): array
    {
        return Cache::remember('dashboard_overview', 300, function () {
            $totalRevenue = (float) Order::where('status', 'completed')
                ->whereDate('created_at', '<=', Carbon::now())
                ->sum('total_price');

            $todaysRevenue = (float) Order::where('status', 'completed')
                ->whereDate('created_at', '>', Carbon::now()->subDays(1))
                ->sum('total_price');

            $totalRefunds = (float) DB::table('refunds')
                ->whereDate('created_at', '<', Carbon::now())
                ->sum('amount');

            $totalOrders = Order::whereDate('created_at', '<=', Carbon::now())->count();

            $totalProducts = Product::count();

            $totalCustomers = User::where('type', 'user')->count();
            $newCustomers = User::where('type', 'user')
                ->whereDate('created_at', '>', Carbon::now()->subDays(30))
                ->count();

            return [
                'total_revenue'     => round($totalRevenue, 2),
                'todays_revenue'    => round($todaysRevenue, 2),
                'total_refunds'     => round($totalRefunds, 2),
                'total_orders'      => $totalOrders,
                'total_products'    => $totalProducts,
                'total_customers'   => $totalCustomers,
                'new_customers'     => $newCustomers,
            ];
        });
    }

    public function getRevenueOverview(Request $request): array
    {
        return Cache::remember('dashboard_revenue', 300, function () {
            $totalRevenue = (float) Order::where('status', 'completed')
                ->whereDate('created_at', '<=', Carbon::now())
                ->sum('total_price');

            $todaysRevenue = (float) Order::where('status', 'completed')
                ->whereDate('created_at', '>', Carbon::now()->subDays(1))
                ->sum('total_price');

            $months = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December',
            ];

            $salesByMonth = Order::select(
                    DB::raw("SUM(total_price) as total"),
                    DB::raw($this->dateFormat('%c') . " as month_num")
                )
                ->where('status', 'completed')
                ->whereYear('created_at', Carbon::now()->year)
                ->groupBy('month_num')
                ->pluck('total', 'month_num')
                ->toArray();

            $monthlyBreakdown = array_map(fn ($index, $month) => [
                'month' => $month,
                'total' => round((float) ($salesByMonth[$index + 1] ?? 0), 2),
            ], array_keys($months), $months);

            return [
                'total_revenue'      => round($totalRevenue, 2),
                'todays_revenue'     => round($todaysRevenue, 2),
                'monthly_breakdown'  => $monthlyBreakdown,
            ];
        });
    }

    public function getOrderStatusOverview(Request $request): array
    {
        return Cache::remember('dashboard_order_stats', 300, function () {
            $countByDays = function (int $days): array {
                $results = Order::select('status', DB::raw('count(*) as order_count'))
                    ->whereDate('created_at', '>', Carbon::now()->subDays($days))
                    ->groupBy('status')
                    ->pluck('order_count', 'status');

                return [
                    'pending'           => (int) ($results['pending'] ?? 0),
                    'processing'        => 0,
                    'completed'         => (int) ($results['completed'] ?? 0),
                    'cancelled'         => (int) ($results['cancelled'] ?? 0),
                    'refunded'          => 0,
                    'failed'            => 0,
                    'local_facility'    => 0,
                    'out_for_delivery'  => 0,
                ];
            };

            return [
                'today'   => $countByDays(1),
                'weekly'  => $countByDays(7),
                'monthly' => $countByDays(30),
                'yearly'  => $countByDays(365),
            ];
        });
    }

    public function getRecentOrders(Request $request, int $limit = 10)
    {
        return Cache::remember("dashboard_recent_orders_{$limit}", 300, function () use ($limit) {
            return Order::with(['user', 'pickupLocation'])
                ->take($limit)
                ->get();
        });
    }

    public function getTopSellingProducts(Request $request, int $limit = 10)
    {
        return Cache::remember("dashboard_top_products_{$limit}", 300, function () use ($limit) {
            return Product::where('sold_quantity', '>', 0)
                ->orderBy('sold_quantity', 'desc')
                ->take($limit)
                ->get(['id', 'name', 'slug', 'price', 'sold_quantity']);
        });
    }

    public function getCategoryStats(Request $request): array
    {
        return Cache::remember('dashboard_category_stats', 300, function () {
            $productCounts = DB::table('category_product')
                ->select(
                    'categories.id as category_id',
                    'categories.name as category_name',
                    DB::raw('COUNT(category_product.product_id) as product_count')
                )
                ->join('products', 'category_product.product_id', '=', 'products.id')
                ->join('categories', 'category_product.category_id', '=', 'categories.id')
                ->groupBy('categories.id', 'categories.name')
                ->orderBy('product_count', 'desc')
                ->limit(15)
                ->get();

            $salesData = DB::table('categories')
                ->select(
                    'categories.id as category_id',
                    'categories.name as category_name',
                    DB::raw('COALESCE(SUM(order_products.product_quantity), 0) as total_sales')
                )
                ->leftJoin('category_product', 'category_product.category_id', '=', 'categories.id')
                ->leftJoin('products', 'category_product.product_id', '=', 'products.id')
                ->leftJoin('order_products', 'order_products.product_id', '=', 'products.id')
                ->leftJoin('orders', 'order_products.order_id', '=', 'orders.id')
                ->where('orders.status', 'completed')
                ->groupBy('categories.id', 'categories.name')
                ->orderBy('total_sales', 'desc')
                ->limit(15)
                ->get();

            return [
                'product_distribution' => $productCounts,
                'sales_distribution'   => $salesData,
            ];
        });
    }

    public function getLowStockProducts(Request $request, int $limit = 10)
    {
        return Cache::remember("dashboard_low_stock_{$limit}", 300, function () use ($limit) {
            return Product::with('type')
                ->where('stock_quantity', '<', 10)
                ->take($limit)
                ->get();
        });
    }

    // =========================================================================
    // 1. Sales Analytics
    // =========================================================================

    public function getSalesAnalytics(Request $request): array
    {
        return Cache::remember('dashboard_sales_analytics', 300, function () {
            $now = Carbon::now();

            $today = (float) Order::where('status', 'completed')
                ->whereDate('created_at', $now->toDateString())
                ->sum('total_price');

            $yesterday = (float) Order::where('status', 'completed')
                ->whereDate('created_at', $now->copy()->subDay()->toDateString())
                ->sum('total_price');

            $last7Days = (float) Order::where('status', 'completed')
                ->whereDate('created_at', '>', $now->copy()->subDays(7))
                ->sum('total_price');

            $last30Days = (float) Order::where('status', 'completed')
                ->whereDate('created_at', '>', $now->copy()->subDays(30))
                ->sum('total_price');

            $todayVsYesterday = $this->percentageChange($yesterday, $today);

            $thisMonth = (float) Order::where('status', 'completed')
                ->whereYear('created_at', $now->year)
                ->whereMonth('created_at', $now->month)
                ->sum('total_price');

            $lastMonth = (float) Order::where('status', 'completed')
                ->whereYear('created_at', $now->copy()->subMonth()->year)
                ->whereMonth('created_at', $now->copy()->subMonth()->month)
                ->sum('total_price');

            $thisYear = (float) Order::where('status', 'completed')
                ->whereYear('created_at', $now->year)
                ->sum('total_price');

            $lastYear = (float) Order::where('status', 'completed')
                ->whereYear('created_at', $now->copy()->subYear()->year)
                ->sum('total_price');

            $completedOrders = Order::where('status', 'completed')->count();
            $completedRevenue = (float) Order::where('status', 'completed')->sum('total_price');
            $aov = $completedOrders > 0 ? round($completedRevenue / $completedOrders, 2) : 0;

            $revenueByPayment = DB::table('transactions')
                ->join('orders', 'transactions.order_id', '=', 'orders.id')
                ->where('orders.status', 'completed')
                ->select('transactions.payment_method', DB::raw('SUM(orders.total_price) as total'))
                ->groupBy('transactions.payment_method')
                ->pluck('total', 'payment_method');

            $revenueByPaymentMethod = $revenueByPayment->map(function ($total, $method) {
                return ['method' => $method, 'total' => round((float) $total, 2)];
            })->values();

            $revenueByFulfillment = Order::where('status', 'completed')
                ->select('fulfillment_type', DB::raw('SUM(total_price) as total'))
                ->groupBy('fulfillment_type')
                ->pluck('total', 'fulfillment_type');

            $revenueByFulfillmentType = $revenueByFulfillment->map(function ($total, $type) {
                return ['fulfillment_type' => $type ?: 'delivery', 'total' => round((float) $total, 2)];
            })->values();

            return [
                'daily_revenue' => [
                    'today'      => round($today, 2),
                    'yesterday'  => round($yesterday, 2),
                    'last_7_days' => round($last7Days, 2),
                    'last_30_days' => round($last30Days, 2),
                ],
                'revenue_comparison' => [
                    'today_vs_yesterday' => [
                        'today'    => round($today, 2),
                        'yesterday' => round($yesterday, 2),
                        'change'   => $todayVsYesterday,
                    ],
                    'this_month_vs_last_month' => [
                        'this_month' => round($thisMonth, 2),
                        'last_month' => round($lastMonth, 2),
                        'change'     => $this->percentageChange($lastMonth, $thisMonth),
                    ],
                    'this_year_vs_last_year' => [
                        'this_year' => round($thisYear, 2),
                        'last_year' => round($lastYear, 2),
                        'change'    => $this->percentageChange($lastYear, $thisYear),
                    ],
                ],
                'average_order_value' => $aov,
                'revenue_by_payment_method' => $revenueByPaymentMethod,
                'revenue_by_fulfillment_type' => $revenueByFulfillmentType,
            ];
        });
    }

    // =========================================================================
    // 2. Customer Analytics
    // =========================================================================

    public function getCustomerAnalytics(Request $request): array
    {
        return Cache::remember('dashboard_customer_analytics', 300, function () {
            $now = Carbon::now();

            $totalCustomers = User::where('type', 'user')->count();

            $customersBefore = User::where('type', 'user')
                ->whereDate('created_at', '<=', $now->copy()->subDays(30))
                ->count();

            $customersInLast30 = User::where('type', 'user')
                ->whereDate('created_at', '>', $now->copy()->subDays(30))
                ->count();

            $returningCustomers = 0;
            if ($totalCustomers > 0) {
                $customerIds = User::where('type', 'user')->pluck('id');
                $returningCustomers = Order::whereIn('user_id', $customerIds)
                    ->whereDate('created_at', '>', $now->copy()->subDays(30))
                    ->distinct('user_id')
                    ->count('user_id');
            }

            $monthlyGrowth = User::where('type', 'user')
                ->select(
                    DB::raw($this->dateFormat('%Y-%m') . " as month"),
                    DB::raw('COUNT(*) as count')
                )
                ->whereDate('created_at', '>', $now->copy()->subMonths(12))
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(fn ($row) => ['month' => $row->month, 'count' => (int) $row->count]);

            $topByOrders = User::where('type', 'user')
                ->withoutGlobalScope('order')
                ->withCount(['orders' => fn ($q) => $q->where('status', 'completed')])
                ->reorder('orders_count', 'desc')
                ->take(10)
                ->get(['id', 'name', 'email'])
                ->map(fn ($u) => [
                    'id'     => $u->id,
                    'name'   => $u->name,
                    'email'  => $u->email,
                    'orders' => (int) $u->orders_count,
                ]);

            $topByRevenue = User::where('type', 'user')
                ->withoutGlobalScope('order')
                ->select('users.id', 'users.name', 'users.email')
                ->join('orders', 'users.id', '=', 'orders.user_id')
                ->where('orders.status', 'completed')
                ->selectRaw('SUM(orders.total_price) as total_revenue')
                ->groupBy('users.id', 'users.name', 'users.email')
                ->reorder('total_revenue', 'desc')
                ->take(10)
                ->get()
                ->map(fn ($u) => [
                    'id'     => $u->id,
                    'name'   => $u->name,
                    'email'  => $u->email,
                    'revenue' => round((float) $u->total_revenue, 2),
                ]);

            $clv = User::where('type', 'user')
                ->withoutGlobalScope('order')
                ->select('users.id', 'users.name', 'users.email')
                ->join('orders', 'users.id', '=', 'orders.user_id')
                ->where('orders.status', 'completed')
                ->selectRaw('SUM(orders.total_price) as lifetime_value')
                ->groupBy('users.id', 'users.name', 'users.email')
                ->reorder('lifetime_value', 'desc')
                ->take(10)
                ->get()
                ->map(fn ($u) => [
                    'id'             => $u->id,
                    'name'           => $u->name,
                    'email'          => $u->email,
                    'lifetime_value' => round((float) $u->lifetime_value, 2),
                ]);

            $active7 = User::where('type', 'user')
                ->whereHas('orders', fn ($q) => $q->whereDate('created_at', '>', $now->copy()->subDays(7)))
                ->count();

            $active30 = User::where('type', 'user')
                ->whereHas('orders', fn ($q) => $q->whereDate('created_at', '>', $now->copy()->subDays(30)))
                ->count();

            $active90 = User::where('type', 'user')
                ->whereHas('orders', fn ($q) => $q->whereDate('created_at', '>', $now->copy()->subDays(90)))
                ->count();

            return [
                'new_vs_returning' => [
                    'new_customers'       => $customersInLast30,
                    'returning_customers' => $returningCustomers,
                ],
                'monthly_growth'        => $monthlyGrowth,
                'top_customers'         => [
                    'by_orders' => $topByOrders,
                    'by_revenue' => $topByRevenue,
                ],
                'customer_lifetime_value' => $clv,
                'active_customers'        => [
                    'last_7_days'  => $active7,
                    'last_30_days' => $active30,
                    'last_90_days' => $active90,
                ],
            ];
        });
    }

    // =========================================================================
    // 3. Product Analytics
    // =========================================================================

    public function getProductAnalytics(Request $request): array
    {
        return Cache::remember('dashboard_product_analytics', 300, function () {
            $limit = 10;

            $bestSelling = Product::where('sold_quantity', '>', 0)
                ->orderBy('sold_quantity', 'desc')
                ->take($limit)
                ->get(['id', 'name', 'slug', 'price', 'sold_quantity']);

            $worstSelling = Product::where('sold_quantity', '>', 0)
                ->orderBy('sold_quantity', 'asc')
                ->take($limit)
                ->get(['id', 'name', 'slug', 'price', 'sold_quantity']);

            $neverSold = Product::where(function ($q) {
                $q->where('sold_quantity', 0)->orWhereNull('sold_quantity');
            })->take($limit)->get(['id', 'name', 'slug', 'price', 'sold_quantity']);

            $outOfStock = Product::where('quantity', 0)
                ->take($limit)
                ->get(['id', 'name', 'slug', 'price', 'quantity']);

            $inventoryValue = (float) Product::selectRaw('SUM(price * quantity) as total')
                ->where('quantity', '>', 0)
                ->value('total');

            return [
                'best_selling'    => $bestSelling,
                'worst_selling'   => $worstSelling,
                'never_sold'      => $neverSold,
                'out_of_stock'    => $outOfStock,
                'inventory_value' => round($inventoryValue, 2),
            ];
        });
    }

    // =========================================================================
    // 4. Order Analytics
    // =========================================================================

    public function getOrderAnalytics(Request $request): array
    {
        return Cache::remember('dashboard_order_analytics', 300, function () {
            $now = Carbon::now();

            $timelineDaily = Order::select(
                    DB::raw("DATE(created_at) as date"),
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(total_price) as revenue')
                )
                ->whereDate('created_at', '>', $now->copy()->subDays(30))
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(fn ($r) => [
                    'date'    => $r->date,
                    'orders'  => (int) $r->count,
                    'revenue' => round((float) $r->revenue, 2),
                ]);

            $timelineWeekly = Order::select(
                    DB::raw($this->dateFormat('%Y-%u') . " as week"),
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(total_price) as revenue')
                )
                ->whereDate('created_at', '>', $now->copy()->subMonths(6))
                ->groupBy('week')
                ->orderBy('week')
                ->get()
                ->map(fn ($r) => [
                    'week'    => (int) $r->week,
                    'orders'  => (int) $r->count,
                    'revenue' => round((float) $r->revenue, 2),
                ]);

            $timelineMonthly = Order::select(
                    DB::raw($this->dateFormat('%Y-%m') . " as month"),
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(total_price) as revenue')
                )
                ->whereDate('created_at', '>', $now->copy()->subYears(2))
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(fn ($r) => [
                    'month'   => $r->month,
                    'orders'  => (int) $r->count,
                    'revenue' => round((float) $r->revenue, 2),
                ]);

            $totalOrders = Order::count();
            $completedOrders = Order::where('status', 'completed')->count();
            $cancelledOrders = Order::where('status', 'cancelled')->count();

            $refundedCount = DB::table('refunds')
                ->where('status', 'approved')
                ->distinct('order_id')
                ->count('order_id');

            $successRate = $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 2) : 0;
            $cancelledRate = $totalOrders > 0 ? round(($cancelledOrders / $totalOrders) * 100, 2) : 0;
            $refundRate = $completedOrders > 0 ? round(($refundedCount / $completedOrders) * 100, 2) : 0;

            return [
                'timeline' => [
                    'daily'   => $timelineDaily,
                    'weekly'  => $timelineWeekly,
                    'monthly' => $timelineMonthly,
                ],
                'success_rate' => [
                    'completed' => $successRate,
                    'cancelled' => $cancelledRate,
                    'refunded'  => $refundRate,
                    'total'     => $totalOrders,
                ],
                'refund_rate' => $refundRate,
            ];
        });
    }

    // =========================================================================
    // 5. Category Analytics
    // =========================================================================

    public function getCategoryAnalytics(Request $request): array
    {
        return Cache::remember('dashboard_category_analytics', 300, function () {
            $now = Carbon::now();

            $productCounts = DB::table('category_product')
                ->select(
                    'categories.id as category_id',
                    'categories.name as category_name',
                    DB::raw('COUNT(category_product.product_id) as product_count')
                )
                ->join('products', 'category_product.product_id', '=', 'products.id')
                ->join('categories', 'category_product.category_id', '=', 'categories.id')
                ->groupBy('categories.id', 'categories.name')
                ->orderBy('product_count', 'desc')
                ->limit(15)
                ->get();

            $revenueByCategory = DB::table('categories')
                ->select(
                    'categories.id as category_id',
                    'categories.name as category_name',
                    DB::raw('COALESCE(SUM(order_products.product_quantity * order_products.product_price), 0) as revenue')
                )
                ->leftJoin('category_product', 'category_product.category_id', '=', 'categories.id')
                ->leftJoin('products', 'category_product.product_id', '=', 'products.id')
                ->leftJoin('order_products', 'order_products.product_id', '=', 'products.id')
                ->leftJoin('orders', 'order_products.order_id', '=', 'orders.id')
                ->where('orders.status', 'completed')
                ->groupBy('categories.id', 'categories.name')
                ->orderBy('revenue', 'desc')
                ->limit(15)
                ->get()
                ->map(fn ($r) => [
                    'category_id'   => $r->category_id,
                    'category_name' => $r->category_name,
                    'revenue'       => round((float) $r->revenue, 2),
                ]);

            $currentMonthRevenue = DB::table('categories')
                ->select(
                    'categories.id as category_id',
                    'categories.name as category_name',
                    DB::raw('COALESCE(SUM(order_products.product_quantity * order_products.product_price), 0) as revenue')
                )
                ->leftJoin('category_product', 'category_product.category_id', '=', 'categories.id')
                ->leftJoin('products', 'category_product.product_id', '=', 'products.id')
                ->leftJoin('order_products', 'order_products.product_id', '=', 'products.id')
                ->leftJoin('orders', 'order_products.order_id', '=', 'orders.id')
                ->where('orders.status', 'completed')
                ->whereYear('orders.created_at', $now->year)
                ->whereMonth('orders.created_at', $now->month)
                ->groupBy('categories.id', 'categories.name')
                ->pluck('revenue', 'category_id');

            $prevMonthRevenue = DB::table('categories')
                ->select(
                    'categories.id as category_id',
                    DB::raw('COALESCE(SUM(order_products.product_quantity * order_products.product_price), 0) as revenue')
                )
                ->leftJoin('category_product', 'category_product.category_id', '=', 'categories.id')
                ->leftJoin('products', 'category_product.product_id', '=', 'products.id')
                ->leftJoin('order_products', 'order_products.product_id', '=', 'products.id')
                ->leftJoin('orders', 'order_products.order_id', '=', 'orders.id')
                ->where('orders.status', 'completed')
                ->whereYear('orders.created_at', $now->copy()->subMonth()->year)
                ->whereMonth('orders.created_at', $now->copy()->subMonth()->month)
                ->groupBy('categories.id')
                ->pluck('revenue', 'category_id');

            $categoryGrowth = $revenueByCategory->map(function ($cat) use ($currentMonthRevenue, $prevMonthRevenue) {
                $current = (float) ($currentMonthRevenue[$cat['category_id']] ?? 0);
                $previous = (float) ($prevMonthRevenue[$cat['category_id']] ?? 0);
                return [
                    'category_id'   => $cat['category_id'],
                    'category_name' => $cat['category_name'],
                    'current_month' => round($current, 2),
                    'previous_month' => round($previous, 2),
                    'change'        => $this->percentageChange($previous, $current),
                ];
            })->values();

            $highestRevenue = $revenueByCategory->take(5)->values();
            $lowestRevenue = $revenueByCategory->reverse()->take(5)->values();

            return [
                'product_distribution' => $productCounts,
                'highest_revenue'      => $highestRevenue,
                'lowest_revenue'       => $lowestRevenue,
                'category_growth'      => $categoryGrowth,
            ];
        });
    }

    // =========================================================================
    // 6. Coupon Analytics
    // =========================================================================

    public function getCouponAnalytics(Request $request): array
    {
        return Cache::remember('dashboard_coupon_analytics', 300, function () {
            $totalUsage = DB::table('coupon_usages')->count();

            $topCoupons = DB::table('coupon_usages')
                ->join('coupons', 'coupon_usages.coupon_id', '=', 'coupons.id')
                ->select('coupons.id', 'coupons.code', 'coupons.name', DB::raw('COUNT(*) as usage_count'))
                ->groupBy('coupons.id', 'coupons.code', 'coupons.name')
                ->orderBy('usage_count', 'desc')
                ->take(10)
                ->get();

            $revenueByCoupon = DB::table('orders')
                ->whereNotNull('coupon')
                ->where('status', 'completed')
                ->select('coupon', DB::raw('SUM(total_price) as revenue'))
                ->groupBy('coupon')
                ->orderBy('revenue', 'desc')
                ->take(10)
                ->get()
                ->map(fn ($r) => [
                    'code'    => $r->coupon,
                    'revenue' => round((float) $r->revenue, 2),
                ]);

            $totalDiscount = (float) Order::whereNotNull('coupon_discount')
                ->sum('coupon_discount');

            return [
                'total_usage'        => $totalUsage,
                'top_coupons'        => $topCoupons,
                'revenue_by_coupon'  => $revenueByCoupon,
                'total_coupon_discount' => round($totalDiscount, 2),
            ];
        });
    }

    // =========================================================================
    // 7. Cart Analytics
    // =========================================================================

    public function getCartAnalytics(Request $request): array
    {
        return Cache::remember('dashboard_cart_analytics', 300, function () {
            $totalCarts = DB::table('carts')->count();
            $abandonedCarts = DB::table('carts')
                ->whereIn('status', ['active', 'expired'])
                ->count();

            $abandonmentRate = $totalCarts > 0
                ? round(($abandonedCarts / $totalCarts) * 100, 2)
                : 0;

            $mostAdded = DB::table('cart_items')
                ->join('products', 'cart_items.product_id', '=', 'products.id')
                ->select('products.id', 'products.name', 'products.slug', 'products.price',
                    DB::raw('SUM(cart_items.quantity) as total_added'))
                ->groupBy('products.id', 'products.name', 'products.slug', 'products.price')
                ->orderBy('total_added', 'desc')
                ->take(10)
                ->get()
                ->map(fn ($r) => [
                    'id'          => $r->id,
                    'name'        => $r->name,
                    'slug'        => $r->slug,
                    'price'       => round((float) $r->price, 2),
                    'total_added' => (int) $r->total_added,
                ]);

            $avgCartValue = (float) DB::table('carts')
                ->whereIn('status', ['active', 'checked_out'])
                ->avg('total_price');

            $totalCheckouts = DB::table('carts')
                ->where('status', 'checked_out')
                ->count();

            $totalCartCreations = DB::table('carts')->count();
            $checkoutDropoffRate = $totalCartCreations > 0
                ? round((1 - ($totalCheckouts / $totalCartCreations)) * 100, 2)
                : 0;

            return [
                'abandonment_rate'      => $abandonmentRate,
                'most_added_products'   => $mostAdded,
                'average_cart_value'    => round($avgCartValue, 2),
                'checkout_dropoff_rate' => $checkoutDropoffRate,
            ];
        });
    }

    // =========================================================================
    // 8. Finance Analytics
    // =========================================================================

    public function getFinanceAnalytics(Request $request): array
    {
        return Cache::remember('dashboard_finance_analytics', 300, function () {
            $grossRevenue = (float) Order::where('status', 'completed')
                ->sum('total_price');

            $refundAmount = (float) DB::table('refunds')
                ->where('status', 'approved')
                ->sum('amount');

            $couponDiscount = (float) Order::whereNotNull('coupon_discount')
                ->sum('coupon_discount');
            $promotionDiscount = (float) Order::where('promotion_discount', '>', 0)
                ->sum('promotion_discount');
            $totalDiscount = $couponDiscount + $promotionDiscount;

            $netRevenue = $grossRevenue - $refundAmount;

            $shippingRevenue = (float) Order::where('status', 'completed')
                ->selectRaw('COALESCE(SUM(shipping_price), 0) + COALESCE(SUM(fast_shipping_fee), 0) as total')
                ->value('total');

            return [
                'gross_revenue'    => round($grossRevenue, 2),
                'net_revenue'      => round(max($netRevenue, 0), 2),
                'refund_amount'    => round($refundAmount, 2),
                'total_discount'   => round($totalDiscount, 2),
                'shipping_revenue' => round($shippingRevenue, 2),
            ];
        });
    }

    // =========================================================================
    // 9. Payment Reconciliation
    // =========================================================================

    public function getReconciliationSummary(): array
    {
        $totalChecked = Transaction::query()
            ->whereNotNull('gateway_transaction_id')
            ->where('status', '!=', 'failed')
            ->count();

        $totalMismatches = PaymentReconciliationResult::count();
        $pendingMismatches = PaymentReconciliationResult::unresolved()->count();
        $resolvedMismatches = PaymentReconciliationResult::resolved()->count();
        $lastRun = PaymentReconciliationResult::query()
            ->latest('created_at')
            ->first()
            ?->created_at;

        return [
            'total_checked' => $totalChecked,
            'total_mismatches' => $totalMismatches,
            'pending_mismatches' => $pendingMismatches,
            'resolved_mismatches' => $resolvedMismatches,
            'last_run' => $lastRun,
        ];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function dateFormat(string $format): string
    {
        $driver = DB::connection()->getDriverName();

        $map = [
            '%M' => $driver === 'sqlite' ? "CAST(strftime('%m', created_at) AS INTEGER)" : "DATE_FORMAT(created_at, '%M')",
            '%Y-%m' => $driver === 'sqlite' ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')",
            '%Y-%u' => $driver === 'sqlite' ? "strftime('%Y-%W', created_at)" : "YEARWEEK(created_at, 1)",
            '%c' => $driver === 'sqlite' ? "CAST(strftime('%m', created_at) AS INTEGER)" : "DATE_FORMAT(created_at, '%c')",
        ];

        return $map[$format] ?? "strftime('{$format}', created_at)";
    }

    private function percentageChange(float $previous, float $current): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 2);
    }
}
