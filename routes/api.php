<?php

use App\Http\Controllers\Api\General\BannerController;
use App\Http\Controllers\Api\General\BrandController;
use App\Http\Controllers\Api\General\CategoryController;
use App\Http\Controllers\Api\General\ContentPageController;
use App\Http\Controllers\Api\General\CouponController;
use App\Http\Controllers\Api\General\FAQController;
use App\Http\Controllers\Api\General\FastShippingController;
use App\Http\Controllers\Api\General\FlashSaleController;
use App\Http\Controllers\Api\General\GovernorateController;
use App\Http\Controllers\Api\General\HomeController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\General\OrderController;
use App\Http\Controllers\Api\General\PickupLocationController;
use App\Http\Controllers\Api\General\ProductController;
use App\Http\Controllers\Api\General\PromotionController;
use App\Http\Controllers\Api\General\SearchController;
use App\Http\Controllers\Api\General\SettingController;
use App\Http\Controllers\Api\General\SliderController;
use App\Http\Controllers\Api\General\TagController;
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
    //======================== nav data ========================/
    Route::get('nav-data', [HomeController::class, 'navData']);

    //======================== category ========================/
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{slug}', [CategoryController::class, 'getCategoryBySlug']);

    //======================== brand ========================/
    Route::get('brands', [BrandController::class, 'index']);
    Route::get('brands/{slug}', [BrandController::class, 'getBrandBySlug']);
    Route::get('brands-products', [BrandController::class, 'getBrandsProductsByQtySet']);

    //======================== banner ========================/
    Route::get('banners', [BannerController::class, 'index']);
    Route::get('banners/{slug}', [BannerController::class, 'getBannerBySlug']);

    //======================== slider ========================/
    Route::get('sliders', [SliderController::class, 'index']);
    Route::get('sliders/{slug}', [SliderController::class, 'getSliderBySlug']);


    //======================== tags ========================/
    Route::get('tags', [TagController::class, 'index']);

    //======================== promotions ========================/
    Route::get('promotions', [PromotionController::class, 'index']);
    Route::get('promotions/{slug}', [PromotionController::class, 'getPromotionBySlug']);

    //======================== coupons ========================/
    Route::get('coupons', [CouponController::class, 'index']);
    Route::post('coupons/apply', [CouponController::class, 'applyCoupon'])->middleware('auth:sanctum');

    //======================== pages ========================/
    Route::controller(ContentPageController::class)->group(function () {
        Route::get('content-pages', 'index')->name('general-content-page-index');
        Route::get('content-pages/{slug}', 'show')->name('general-content-page-show');
    });

    Route::get('checkout/promotions', [OrderController::class, 'eligiblePromotions'])->middleware('auth:sanctum');
    Route::post('checkout', [OrderController::class, 'checkout'])->middleware('auth:sanctum');
    Route::post('checkout/cod/{orderId}/mark-paid', [OrderController::class, 'markCodAsPaid'])->middleware(['auth:sanctum', 'permission:update-order-status']);
    Route::post('checkout/cashier/{orderId}/mark-paid', [OrderController::class, 'markCashierPaid'])->middleware(['auth:sanctum', 'permission:update-order-status']);
    Route::get('checkout/transaction-qr/{uuid}', [OrderController::class, 'getTransactionQr'])->middleware('auth:sanctum');
    Route::any('checkout/callback', [OrderController::class, 'checkoutCallback'])->name('api.checkout.callback');
    Route::any('checkout/error-callback', [OrderController::class, 'checkoutErrorCallback'])->name('api.checkout.errorCallback');

    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{slug}', [ProductController::class, 'getProductBySlug']);

  //========================= product reviews =========================//
        Route::post('products/{id}/reviews',[ProductController::class, 'addProductReview'])->middleware('auth:sanctum');
        Route::put('products/reviews/{id}', [ProductController::class, 'updateProductReview'])->middleware('auth:sanctum');

    Route::get('flash-sales', [FlashSaleController::class, 'index']);
    Route::get('flash-sales/{slug}', [FlashSaleController::class, 'getFlashSaleBySlug']);
    Route::get('flash-sale-products', [FlashSaleController::class, 'getFlashSalesAndHereProductsByQtySet']);
    Route::get('flash-sale-products-ending-this-week', [FlashSaleController::class, 'getFlashSaleProductsEndingThisWeek']);
    Route::get('flash-sale-products-ending-today', [FlashSaleController::class, 'getFlashSaleProductsEndingToday']);

    Route::get('settings', [SettingController::class, 'index']);
    Route::get('faqs', [FAQController::class, 'index']);

    //======================== governorates ========================/
    Route::get('governorates', [GovernorateController::class, 'index']);

    Route::get('search', [SearchController::class, 'index']);
    Route::get('pickup-locations', [PickupLocationController::class, 'index']);
    Route::get('pickup-locations/{id}', [PickupLocationController::class, 'show']);
    Route::get('fast-shipping/status', [FastShippingController::class, 'status']);
    Route::get('fast-shipping/products', [FastShippingController::class, 'products']);
    Route::post('fast-shipping/checkout', [FastShippingController::class, 'checkout'])->middleware('auth:sanctum');
    Route::get('fast-shipping/orders', [FastShippingController::class, 'orders'])->middleware('auth:sanctum');

    Route::get('orders', [OrderController::class, 'index'])->middleware('auth:sanctum');

    //======================== shipments ========================/
    Route::prefix('shipments')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ShipmentController::class, 'index']);
        Route::get('uuid/{uuid}', [\App\Http\Controllers\Api\ShipmentController::class, 'showByUuid']);
        Route::get('{id}', [\App\Http\Controllers\Api\ShipmentController::class, 'show']);
        Route::post('/', [\App\Http\Controllers\Api\ShipmentController::class, 'store']);
        Route::put('{id}/status', [\App\Http\Controllers\Api\ShipmentController::class, 'updateStatus']);
        Route::put('{id}', [\App\Http\Controllers\Api\ShipmentController::class, 'update']);
    });

    //======================== invoices ========================/
    Route::prefix('invoices')->group(function () {
        Route::get('my-invoices', [InvoiceController::class, 'myInvoices'])->middleware('auth:sanctum');
        Route::get('verify/{uuid}', [InvoiceController::class, 'verify'])->middleware('throttle:60,1');
        Route::get('uuid/{uuid}', [InvoiceController::class, 'showByUuid'])->middleware('auth:sanctum');

        Route::middleware(['auth:sanctum'])->group(function () {
            Route::get('/', [InvoiceController::class, 'index']);
            Route::get('{uuid}/download', [InvoiceController::class, 'download'])->whereUuid('uuid')->middleware('throttle:30,1');
            Route::get('{id}', [InvoiceController::class, 'show']);
            Route::post('{id}/regenerate', [InvoiceController::class, 'regenerate']);
            Route::post('{id}/correct', [InvoiceController::class, 'correct']);
            Route::post('{id}/cancel', [InvoiceController::class, 'cancel']);
            Route::post('{id}/debit-note', [InvoiceController::class, 'issueDebitNote']);
        });
    });
});
