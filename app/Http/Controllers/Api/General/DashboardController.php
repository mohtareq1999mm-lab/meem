<?php

namespace App\Http\Controllers\Api\General;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    public function overview(Request $request): JsonResponse
    {
        try {
            $data = $this->dashboardService->getOverview($request);

            return response()->json([
                'success' => true,
                'message' => __('message.DASHBOARD.OVERVIEW_FETCHED'),
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function revenue(Request $request): JsonResponse
    {
        try {
            $data = $this->dashboardService->getRevenueOverview($request);

            return response()->json([
                'success' => true,
                'message' => __('message.DASHBOARD.REVENUE_FETCHED'),
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function orderStats(Request $request): JsonResponse
    {
        try {
            $data = $this->dashboardService->getOrderStatusOverview($request);

            return response()->json([
                'success' => true,
                'message' => __('message.DASHBOARD.ORDER_STATS_FETCHED'),
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function recentOrders(Request $request): JsonResponse
    {
        try {
            $limit = min((int) ($request->limit ?? 10), 50);
            $orders = $this->dashboardService->getRecentOrders($request, $limit);

            return response()->json([
                'success' => true,
                'message' => __('message.DASHBOARD.RECENT_ORDERS_FETCHED'),
                'data'    => $orders,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function topProducts(Request $request): JsonResponse
    {
        try {
            $limit = min((int) ($request->limit ?? 10), 50);
            $products = $this->dashboardService->getTopSellingProducts($request, $limit);

            return response()->json([
                'success' => true,
                'message' => __('message.DASHBOARD.TOP_PRODUCTS_FETCHED'),
                'data'    => $products,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function categoryStats(Request $request): JsonResponse
    {
        try {
            $data = $this->dashboardService->getCategoryStats($request);

            return response()->json([
                'success' => true,
                'message' => __('message.DASHBOARD.CATEGORY_STATS_FETCHED'),
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function lowStock(Request $request): JsonResponse
    {
        try {
            $limit = min((int) ($request->limit ?? 10), 50);
            $products = $this->dashboardService->getLowStockProducts($request, $limit);

            return response()->json([
                'success' => true,
                'message' => __('message.DASHBOARD.LOW_STOCK_FETCHED'),
                'data'    => $products,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    // =========================================================================
    // Advanced Analytics Endpoints
    // =========================================================================

    public function salesAnalytics(Request $request): JsonResponse
    {
        try {
            $data = $this->dashboardService->getSalesAnalytics($request);

            return response()->json([
                'success' => true,
                'message' => __('message.DASHBOARD.SALES_ANALYTICS_FETCHED'),
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function customerAnalytics(Request $request): JsonResponse
    {
        try {
            $data = $this->dashboardService->getCustomerAnalytics($request);

            return response()->json([
                'success' => true,
                'message' => __('message.DASHBOARD.CUSTOMER_ANALYTICS_FETCHED'),
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function productAnalytics(Request $request): JsonResponse
    {
        try {
            $data = $this->dashboardService->getProductAnalytics($request);

            return response()->json([
                'success' => true,
                'message' => __('message.DASHBOARD.PRODUCT_ANALYTICS_FETCHED'),
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function orderAnalytics(Request $request): JsonResponse
    {
        try {
            $data = $this->dashboardService->getOrderAnalytics($request);

            return response()->json([
                'success' => true,
                'message' => __('message.DASHBOARD.ORDER_ANALYTICS_FETCHED'),
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function categoryAnalytics(Request $request): JsonResponse
    {
        try {
            $data = $this->dashboardService->getCategoryAnalytics($request);

            return response()->json([
                'success' => true,
                'message' => __('message.DASHBOARD.CATEGORY_ANALYTICS_FETCHED'),
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function couponAnalytics(Request $request): JsonResponse
    {
        try {
            $data = $this->dashboardService->getCouponAnalytics($request);

            return response()->json([
                'success' => true,
                'message' => __('message.DASHBOARD.COUPON_ANALYTICS_FETCHED'),
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function cartAnalytics(Request $request): JsonResponse
    {
        try {
            $data = $this->dashboardService->getCartAnalytics($request);

            return response()->json([
                'success' => true,
                'message' => __('message.DASHBOARD.CART_ANALYTICS_FETCHED'),
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function reconciliation(): JsonResponse
    {
        try {
            $data = $this->dashboardService->getReconciliationSummary();

            return response()->json([
                'success' => true,
                'message' => __('message.DASHBOARD.RECONCILIATION_FETCHED'),
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function financeAnalytics(Request $request): JsonResponse
    {
        try {
            $data = $this->dashboardService->getFinanceAnalytics($request);

            return response()->json([
                'success' => true,
                'message' => __('message.DASHBOARD.FINANCE_ANALYTICS_FETCHED'),
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    protected function errorResponse(Throwable $e): JsonResponse
    {
        $message = __('message.ERROR.SOMETHING_WENT_WRONG');
        if ($e instanceof QueryException) {
            $message = __('message.DASHBOARD.DATABASE_ERROR');
        }

        return response()->json([
            'success' => false,
            'message' => $message,
        ], 409);
    }
}
