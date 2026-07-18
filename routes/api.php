<?php

use App\Http\Controllers\Api\General\BannerController;
use App\Http\Controllers\Api\General\BrandController;
use App\Http\Controllers\Api\General\CategoryController;
use App\Http\Controllers\Api\General\ContentPageController;
use App\Http\Controllers\Api\General\CouponController;
use App\Http\Controllers\Api\General\DashboardController;
use App\Http\Controllers\Api\General\FAQController;
use App\Http\Controllers\Api\General\FastShippingController;
use App\Http\Controllers\Api\General\FlashSaleController;
use App\Http\Controllers\Api\General\HomeController;
use App\Http\Controllers\Api\General\OrderController;
use App\Http\Controllers\Api\General\PickupLocationController;
use App\Http\Controllers\Api\General\ProductController;
use App\Http\Controllers\Api\General\PromotionController;
use App\Http\Controllers\Api\General\SearchController;
use App\Http\Controllers\Api\General\SettingController;
use App\Http\Controllers\Api\General\SliderController;
use App\Http\Controllers\Api\General\TagController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::prefix('v1/general')->middleware('api')->group(function () {
    Route::get('home', [HomeController::class, 'index']);
    Route::get('nav-data', [HomeController::class, 'navData']);
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{slug}', [ProductController::class, 'getProductBySlug'])->name('general-product-show');
    Route::post('products/{id}/reviews', [ProductController::class, 'addProductReview']);
    Route::put('products/reviews/{id}', [ProductController::class, 'updateProductReview']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{slug}', [CategoryController::class, 'getCategoryBySlug']);
    Route::get('brands', [BrandController::class, 'index']);
    Route::get('brands/{slug}', [BrandController::class, 'getBrandBySlug']);
    Route::get('brands-products', [BrandController::class, 'getBrandsProductsByQtySet']);
    Route::get('banners', [BannerController::class, 'index']);
    Route::get('banners/{slug}', [BannerController::class, 'getBannerBySlug']);
    Route::get('sliders', [SliderController::class, 'index']);
    Route::get('sliders/{slug}', [SliderController::class, 'getSliderBySlug']);
    Route::get('tags', [TagController::class, 'index']);
    Route::get('tags/{slug}', [TagController::class, 'show']);
    Route::get('promotions', [PromotionController::class, 'index']);
    Route::get('promotions/{slug}', [PromotionController::class, 'getPromotionBySlug']);
    Route::get('flash-sales', [FlashSaleController::class, 'index']);
    Route::get('flash-sales/{slug}', [FlashSaleController::class, 'getFlashSaleBySlug']);
    Route::get('flash-sale-products', [FlashSaleController::class, 'getFlashSalesAndHereProductsByQtySet']);
    Route::get('flash-sale-products-ending-this-week', [FlashSaleController::class, 'getFlashSaleProductsEndingThisWeek']);
    Route::get('flash-sale-products-ending-today', [FlashSaleController::class, 'getFlashSaleProductsEndingToday']);
    Route::get('coupons', [CouponController::class, 'index']);
    Route::post('coupons/apply', [CouponController::class, 'applyCoupon'])->middleware('auth:sanctum');
    Route::get('pages', [ContentPageController::class, 'index']);
    Route::get('pages/{slug}', [ContentPageController::class, 'show']);
    Route::get('settings', [SettingController::class, 'index']);
    Route::get('search', [SearchController::class, 'index']);
    Route::get('faqs', [FAQController::class, 'index']);
    Route::get('pickup-locations', [PickupLocationController::class, 'index']);
    Route::get('pickup-locations/{id}', [PickupLocationController::class, 'show']);
    Route::get('fast-shipping/status', [FastShippingController::class, 'status']);
    Route::get('fast-shipping/products', [FastShippingController::class, 'products']);
    Route::post('fast-shipping/checkout', [FastShippingController::class, 'checkout'])->middleware('auth:sanctum');
    Route::get('fast-shipping/orders', [FastShippingController::class, 'orders'])->middleware('auth:sanctum');
    Route::get('checkout/promotions', [OrderController::class, 'eligiblePromotions'])->middleware('auth:sanctum');
    Route::post('checkout', [OrderController::class, 'checkout'])->middleware('auth:sanctum');
    Route::post('checkout/cod/{orderId}/mark-paid', [OrderController::class, 'markCodAsPaid'])->middleware(['auth:sanctum', 'permission:update-order-status']);
    Route::post('checkout/cashier/{orderId}/mark-paid', [OrderController::class, 'markCashierPaid'])->middleware(['auth:sanctum', 'permission:update-order-status']);
    Route::get('checkout/transaction-qr/{uuid}', [OrderController::class, 'getTransactionQr'])->middleware('auth:sanctum');
    Route::any('checkout/callback', [OrderController::class, 'checkoutCallback'])->name('api.checkout.callback');
    Route::any('checkout/error-callback', [OrderController::class, 'checkoutErrorCallback'])->name('api.checkout.errorCallback');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('dashboard/overview', [DashboardController::class, 'overview']);
        Route::get('dashboard/revenue', [DashboardController::class, 'revenue']);
        Route::get('dashboard/order-stats', [DashboardController::class, 'orderStats']);
        Route::get('dashboard/recent-orders', [DashboardController::class, 'recentOrders']);
        Route::get('dashboard/top-products', [DashboardController::class, 'topProducts']);
        Route::get('dashboard/category-stats', [DashboardController::class, 'categoryStats']);
        Route::get('dashboard/low-stock', [DashboardController::class, 'lowStock']);
        Route::get('dashboard/sales-analytics', [DashboardController::class, 'salesAnalytics']);
        Route::get('dashboard/customer-analytics', [DashboardController::class, 'customerAnalytics']);
        Route::get('dashboard/product-analytics', [DashboardController::class, 'productAnalytics']);
        Route::get('dashboard/order-analytics', [DashboardController::class, 'orderAnalytics']);
        Route::get('dashboard/category-analytics', [DashboardController::class, 'categoryAnalytics']);
        Route::get('dashboard/coupon-analytics', [DashboardController::class, 'couponAnalytics']);
        Route::get('dashboard/cart-analytics', [DashboardController::class, 'cartAnalytics']);
        Route::get('dashboard/reconciliation', [DashboardController::class, 'reconciliation']);
        Route::get('dashboard/finance-analytics', [DashboardController::class, 'financeAnalytics']);
    });
});