<?php

use App\Http\Controllers\Api\General\BannerController;
use App\Http\Controllers\Api\General\BrandController;
use App\Http\Controllers\Api\General\CategoryController;
use App\Http\Controllers\Api\Currency\CurrencyController;
use App\Http\Controllers\Api\General\CityController;
use App\Http\Controllers\Api\General\ContentPageController;
use App\Http\Controllers\Api\General\StaticPageController;
use App\Http\Controllers\Api\General\CountryController;
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
use App\Http\Controllers\Api\General\SettingController;
use App\Http\Controllers\Api\General\SiteReviewController;
use App\Http\Controllers\Api\General\SliderController;
use App\Http\Controllers\Api\General\TagController;
use App\Http\Controllers\Api\ShipmentController;
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


Route::prefix('v1/general')->group(function () {
    Route::middleware(['api', 'throttle:public-api'])->group(function () {
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
        Route::get('tags/{slug}', [TagController::class, 'show']);
        //======================== promotions ========================/
        Route::get('promotions', [PromotionController::class, 'index']);
        Route::get('promotions/{slug}', [PromotionController::class, 'getPromotionBySlug']);
        //======================== coupons ========================/
        Route::get('coupons', [CouponController::class, 'index']);
        //======================== pages ========================/
        Route::controller(ContentPageController::class)->group(function () {
            Route::get('content-pages', 'index')->name('general-content-page-index');
            Route::get('content-pages/{slug}', 'show')->name('general-content-page-show');
        });
        //======================== static pages ========================/
        Route::controller(StaticPageController::class)->group(function () {
            Route::get('static-pages', 'index')->name('general-static-page-index');
            Route::get('static-pages/{slug}', 'show')->name('general-static-page-show');
        });
        //======================== products ========================/
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{slug}', [ProductController::class, 'getProductBySlug']);
        //======================== flash sales ========================/
        Route::get('flash-sales', [FlashSaleController::class, 'index']);
        Route::get('flash-sales/{slug}', [FlashSaleController::class, 'getFlashSaleBySlug']);
        Route::get('flash-sale-products', [FlashSaleController::class, 'getFlashSalesAndHereProductsByQtySet']);
        Route::get('flash-sale-products-ending-this-week', [FlashSaleController::class, 'getFlashSaleProductsEndingThisWeek']);
        Route::get('flash-sale-products-ending-today', [FlashSaleController::class, 'getFlashSaleProductsEndingToday']);
        //======================== settings ========================/
        Route::get('settings', [SettingController::class, 'index'])->name('settings.front');
        //======================== faqs ========================/
        Route::get('faqs', [FAQController::class, 'index']);
        //======================== governorates ========================/
        Route::get('governorates', [GovernorateController::class, 'index']);
        Route::get('governorates/{id}', [GovernorateController::class, 'show']);
        //======================== countries ========================/
        Route::get('countries', [CountryController::class, 'index']);
        Route::get('countries/{id}', [CountryController::class, 'show']);
        //======================== cities ========================/
        Route::get('cities', [CityController::class, 'index']);
        Route::get('cities/{id}', [CityController::class, 'show']);
        //============================ pickup locations ========================/
        Route::get('pickup-locations', [PickupLocationController::class, 'index'])->name('pickup-locations.index');
        Route::get('pickup-locations/{id}', [PickupLocationController::class, 'show']);
        //============================ fast shipping ========================/
        Route::get('fast-shipping/status', [FastShippingController::class, 'status']);
        //============================ site reviews ========================/
        Route::get('site-reviews', [SiteReviewController::class, 'index']);
        //============================ currencies ========================/
        Route::get('currencies', [CurrencyController::class, 'index']);
        Route::post('currencies/select', [CurrencyController::class, 'select']);
        //======================== payment callbacks (gateway redirect, public) ========================/
        Route::any('checkout/callback', [OrderController::class, 'checkoutCallback'])->name('api.checkout.callback');
        Route::any('checkout/error-callback', [OrderController::class, 'checkoutErrorCallback'])->name('api.checkout.errorCallback');
    });

    Route::middleware(['api', 'auth:sanctum', 'throttle:authenticated'])->group(function () {
        //======================== coupons ========================/
        Route::post('coupons/apply', [CouponController::class, 'applyCoupon']);
        //======================== checkout ========================//
        Route::get('checkout/promotions', [OrderController::class, 'eligiblePromotions']);
        Route::post('checkout', [OrderController::class, 'checkout']);
        Route::post('checkout/cod/{orderId}/mark-paid', [OrderController::class, 'markCodAsPaid'])->middleware(['permission:update-order-status']);
        Route::post('checkout/cashier/{orderId}/mark-paid', [OrderController::class, 'markCas
        hierPaid'])->middleware(['permission:update-order-status']);
        //======================== fast shipping checkout ========================/
        Route::post('fast-shipping/checkout', [FastShippingController::class, 'checkout']);
        //======================== orders ========================//
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{orderId}/invoice', [OrderController::class, 'invoiceByOrderId'])->whereNumber('orderId');
        Route::get('orders/{id}', [OrderController::class, 'show'])->whereNumber('id');
        //========================= product reviews =========================//
        Route::post('products/{id}/reviews', [ProductController::class, 'addProductReview']);
        Route::put('products/reviews/{id}', [ProductController::class, 'updateProductReview']);
        //========================= site reviews =========================//
        Route::post('site-reviews', [SiteReviewController::class, 'store']);
        //======================== invoices ========================/
        Route::prefix('invoices')->group(function () {
            Route::get('my-invoices', [InvoiceController::class, 'myInvoices']);
            // verify route — required by InvoiceVerifyEndpointTest; do not drop on merge
            Route::get('verify/{uuid}', [InvoiceController::class, 'verify'])->middleware('throttle:5,1');
        });
    });
});

// Customer invoice PDF VIEW/DOWNLOAD via temporary SIGNED urls (no Sanctum).
// Ownership is enforced when the urls are generated (my-invoices / order invoice).
Route::prefix('v1/general/invoices')->middleware('signed')->group(function () {
    Route::get('view/{uuid}', [\App\Http\Controllers\Api\InvoiceController::class, 'viewByUuidSigned'])
        ->whereUuid('uuid')->name('general.invoices.view');
    Route::get('download/{uuid}', [\App\Http\Controllers\Api\InvoiceController::class, 'downloadByUuidSigned'])
        ->whereUuid('uuid')->name('general.invoices.download');
});
        // //======================== shipments ========================/
        // Route::get('shipments/track/{trackingNumber}', [ShipmentController::class, 'trackShipment'])->name('shipments.track');
        // Route::get('shipments/{id}', [ShipmentController::class, 'show'])->middleware('auth:sanctum');
