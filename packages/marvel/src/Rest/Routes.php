<?php

use App\Http\Controllers\Api\General\DashboardController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Marvel\Enums\Role;
use Marvel\Http\Controllers\AbusiveReportController;
use Marvel\Http\Controllers\ActivityLogController;
use Marvel\Http\Controllers\AddressController;
use Marvel\Http\Controllers\AiController;
use Marvel\Http\Controllers\AnalyticsController;
use Marvel\Http\Controllers\AttachmentController;
use Marvel\Http\Controllers\AttributeController;
use Marvel\Http\Controllers\AttributeValueController;
use Marvel\Http\Controllers\AuthorController;
use Marvel\Http\Controllers\BannerController;
use Marvel\Http\Controllers\BecameSellerController;
use Marvel\Http\Controllers\BrandController;
use Marvel\Http\Controllers\ContactController;
use Marvel\Http\Controllers\CartController;
use Marvel\Http\Controllers\CategoryController;
use Marvel\Http\Controllers\CheckoutController;
use Marvel\Http\Controllers\CityController;
use Marvel\Http\Controllers\ConversationController;
use Marvel\Http\Controllers\CouponController;
use Marvel\Http\Controllers\CmsPageController;
use Marvel\Http\Controllers\DeliveryTimeController;
use Marvel\Http\Controllers\DownloadController;
use Marvel\Http\Controllers\FaqsController;
use Marvel\Http\Controllers\FeedbackController;
use Marvel\Http\Controllers\FlashSaleController;
use Marvel\Http\Controllers\FlashSaleVendorRequestController;
use Marvel\Http\Controllers\ManufacturerController;
use Marvel\Http\Controllers\MessageController;
use Marvel\Http\Controllers\Order\OrderController;
use Marvel\Http\Controllers\PaymentIntentController;
use Marvel\Http\Controllers\PaymentMethodController;
use Marvel\Http\Controllers\ProductController;
use Marvel\Http\Controllers\ProductImportController;
use Marvel\Http\Controllers\PromotionController;
use Marvel\Http\Controllers\QuestionController;
use Marvel\Http\Controllers\RefundController;
use Marvel\Http\Controllers\ResourceController;
use Marvel\Http\Controllers\ReviewController;
use Marvel\Http\Controllers\RoleAndPermissionController;
use Marvel\Http\Controllers\SettingsController;
use Marvel\Http\Controllers\ShippingController;
use Marvel\Http\Controllers\ShopController;
use Marvel\Http\Controllers\SliderController;
use Marvel\Http\Controllers\SectionController;
use Marvel\Http\Controllers\SectionTypeController;
use Marvel\Http\Controllers\TagController;
use Marvel\Http\Controllers\TaxController;
use Marvel\Http\Controllers\TypeController;
use Marvel\Http\Controllers\UserController;
use Marvel\Http\Controllers\WebHookController;
use Marvel\Http\Controllers\WishlistController;
use Marvel\Http\Controllers\WithdrawController;
use Marvel\Http\Controllers\LanguageController;
use Marvel\Http\Controllers\NotifyLogsController;
use Marvel\Http\Controllers\OwnershipTransferController;
use Marvel\Http\Controllers\RefundPolicyController;
use Marvel\Http\Controllers\RefundReasonController;
use Marvel\Http\Controllers\StoreNoticeController;
use Marvel\Http\Controllers\TermsAndConditionsController;
use Marvel\Http\Controllers\ComponentDataController;
use Marvel\Http\Controllers\ContentPageController;
use Marvel\Http\Controllers\CountryController;
use Marvel\Http\Controllers\FastShippingController;
use Marvel\Http\Controllers\GovernorateController;
use Marvel\Http\Controllers\NotificationController;
use Marvel\Http\Controllers\PickupLocationController;
use Marvel\Http\Controllers\ProductExportController;
use Marvel\Http\Controllers\ShippingPriceController;

// use Illuminate\Support\Facades\Auth;

/**
 * ******************************************
 * Available Public Routes
 * ******************************************
 */

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::get('/email/verify/{id}/{hash}', [UserController::class, 'verifyEmail'])->name('verification.verify');

/**
 * Authentication Routes - Rate Limited (10/min per IP)
 * Protects against brute force and credential stuffing
 */
Route::middleware(['throttle:auth'])->group(function () {
    Route::post('/register', [UserController::class, 'register']);
    Route::post('/token', [UserController::class, 'token']);
    Route::post('/admin-login', [UserController::class, 'adminToken']);
    Route::post('/social-login-token', [UserController::class, 'socialLogin']);
});
Route::get('me', [UserController::class, 'me'])->middleware('auth:sanctum');


// Logout is not rate limited - users should always be able to log out
Route::post('/logout', [UserController::class, 'logout'])->middleware('auth:sanctum');

/**
 * Password Reset Routes - Rate Limited (5/min per IP)
 * Protects against email bombing and account takeover
 */
Route::middleware(['throttle:sensitive'])->group(function () {
    Route::post('/forget-password', [UserController::class, 'forgetPassword']);
    Route::post('/verify-forget-password-token', [UserController::class, 'verifyForgetPasswordToken']);
    Route::post('/reset-password', [UserController::class, 'resetPassword']);
    Route::post('/contact-us', [ContactController::class, 'store']);
});

/**
 * OTP Routes - DISABLED
 * Uncomment if you need phone-based authentication
 */
Route::middleware(['throttle:otp'])->group(function () {
    Route::post('/send-otp-code', [UserController::class, 'sendUserOtp']);
    // Route::post('/verify-otp-code', [UserController::class, 'verifyOtpCode']);
    Route::post('/otp-login', [UserController::class, 'otpLogin']);
});

Route::get('top-authors', [AuthorController::class, 'topAuthor']);
Route::get('top-manufacturers', [ManufacturerController::class, 'topManufacturer']);
Route::get('popular-products', [ProductController::class, 'popularProducts']);
Route::get('best-selling-products', [ProductController::class, 'bestSellingProducts']);
Route::get('check-availability', [ProductController::class, 'checkAvailability']);
Route::get("products/calculate-rental-price", [ProductController::class, 'calculateRentalPrice']);

/**
 * Import/Export Routes - Rate Limited (uploads)
 * Protects against storage and processing abuse
 */
Route::get('samples/product-import', [ProductImportController::class, 'downloadSample']);
Route::middleware(['throttle:uploads'])->group(function () {
    Route::post('import-products', [ProductController::class, 'importProducts']);
    Route::post('import-variation-options', [ProductController::class, 'importVariationOptions']);
    Route::post('import-attributes', [AttributeController::class, 'importAttributes']);
});

Route::get('export-products/{shop_id}', [ProductController::class, 'exportProducts']);
Route::get('export-variation-options/{shop_id}', [ProductController::class, 'exportVariableOptions']);
Route::post('generate-description', [ProductController::class, 'generateDescription']);
Route::get('export-attributes/{shop_id}', [AttributeController::class, 'exportAttributes']);
Route::get('download_url/token/{token}', [DownloadController::class, 'downloadFile'])->name('download_url.token');

Route::post('subscribe-to-newsletter', [UserController::class, 'subscribeToNewsletter'])->name('subscribeToNewsletter');
Route::get('download-invoice/token/{token}', [OrderController::class, 'downloadInvoice'])->name('download_invoice.token');

/**
 * Payment Webhooks - NOT rate limited
 * Payment providers need unrestricted access
 */
Route::post('webhooks/razorpay', [WebHookController::class, 'razorpay']);
Route::post('webhooks/stripe', [WebHookController::class, 'stripe']);
Route::post('webhooks/paypal', [WebHookController::class, 'paypal']);
Route::post('webhooks/mollie', [WebHookController::class, 'mollie']);
Route::post('webhooks/paystack', [WebHookController::class, 'paystack']);
Route::post('webhooks/paymongo', [WebHookController::class, 'paymongo']);
Route::post('webhooks/xendit', [WebHookController::class, 'xendit']);
Route::post('webhooks/iyzico', [WebHookController::class, 'iyzico']);
Route::post('webhooks/bkash', [WebHookController::class, 'bkash']);
Route::post('webhooks/flutterwave', [WebHookController::class, 'flutterwave']);

Route::post('license-key/verify', [UserController::class, 'verifyLicenseKey']);

Route::get('callback/flutterwave', [WebHookController::class, 'callback'])->name('callback.flutterwave');

Route::get('near-by-shop/{lat}/{lng}', [ShopController::class, 'nearByShop']);

Route::get('store-notices', [StoreNoticeController::class, 'index'])->name('store-notices.index');

Route::get('products/export', [ProductExportController::class, 'export'])->name('admin.products.export');
Route::apiResource('products', ProductController::class, [
    'only' => ['index', 'show'],
]);
Route::apiResource('types', TypeController::class, [
    'only' => ['index', 'show'],
]);
Route::apiResource('attachments', AttachmentController::class, [
    'only' => ['index', 'show'],
]);
Route::apiResource('categories', CategoryController::class, [
    'only' => ['index', 'show'],
]);
Route::apiResource('brands', BrandController::class, [
    'only' => ['index', 'show'],
]);
Route::delete('contacts/delete-all', [ContactController::class, 'deleteAll']);
Route::delete('contacts/delete-all-read', [ContactController::class, 'deleteAllReadContacts']);
Route::apiResource('contacts', ContactController::class)->except(['update']);
Route::post('contacts/{id}/replay', [ContactController::class, 'sendReplay']);
Route::apiResource('delivery-times', DeliveryTimeController::class, [
    'only' => ['index', 'show']
]);
Route::apiResource('languages', LanguageController::class, [
    'only' => ['index', 'show']
]);
Route::apiResource('tags', TagController::class, [
    'only' => ['index', 'show'],
]);
Route::apiResource('refund-reasons', RefundReasonController::class, [
    'only' => ['index', 'show'],
]);
Route::apiResource('resources', ResourceController::class, [
    'only' => ['index', 'show']
]);


Route::get('featured-categories', 'Marvel\Http\Controllers\CategoryController@fetchFeaturedCategories');

Route::apiResource('coupons', CouponController::class, [
    'only' => ['index', 'show'],
]);
Route::apiResource('promotions', PromotionController::class, [
    'only' => ['index', 'show'],
]);
Route::post('coupons/verify', [CouponController::class, 'verify']);
Route::post('coupons/add-to-cart', [CouponController::class, 'addCouponToCart']);
Route::apiResource('attributes', AttributeController::class, [
    'only' => ['index', 'show'],
]);
Route::apiResource('attribute-values', AttributeValueController::class, [
    'only' => ['index', 'show'],
]);
Route::apiResource('shops', ShopController::class, [
    'only' => ['index', 'show'],
]);
Route::apiResource('settings', SettingsController::class, [
    'only' => ['index'],
]);
Route::apiResource('reviews', ReviewController::class, [
    'only' => ['index', 'show'],
]);
Route::apiResource('questions', QuestionController::class, [
    'only' => ['index', 'show'],
]);
Route::apiResource('feedbacks', FeedbackController::class, [
    'only' => ['index', 'show'],
]);
Route::apiResource('authors', AuthorController::class, [
    'only' => ['index', 'show'],
]);
Route::apiResource('manufacturers', ManufacturerController::class, [
    'only' => ['index', 'show'],
]);
Route::apiResource('banners', BannerController::class, [
    'only' => ['index', 'show'],
]);
Route::apiResource('sliders', SliderController::class, [
    'only' => ['index'],
]);
Route::post('orders/checkout/verify', [CheckoutController::class, 'verify']);

/**
 * Order Creation - Rate Limited (10/min per user)
 * Protects against order spam and inventory locking attacks
 */
Route::middleware(['throttle:orders'])->group(function () {
    // Route::apiResource('orders', OrderController::class, [
    //     'only' => ['store'],
    // ]);
});

// Order viewing is not rate limited - users need to check their order status
// Route::apiResource('orders', OrderController::class, [
//     'only' => ['show'],
// ]);

Route::post('/email/verification-notification', [UserController::class, 'sendVerificationEmail'])
    ->middleware(['auth:sanctum', 'throttle:6,1'])
    ->name('verification.send');

Route::post('orders/payment', [OrderController::class, 'submitPayment']);
Route::post('generate-descriptions', [AiController::class, 'generateDescription']);
Route::get('/payment-intent', [PaymentIntentController::class, 'getPaymentIntent']);

Route::apiResource('faqs', FaqsController::class, [
    'only' => ['index', 'show'],
]);

Route::apiResource('terms-and-conditions', TermsAndConditionsController::class, [
    'only' => ['index', 'show'],
]);

Route::get('cms-pages', [CmsPageController::class, 'index']);
Route::get('cms-pages/{slug}', [CmsPageController::class, 'show']);

// Puck page builder endpoints
Route::get('puck/page', [CmsPageController::class, 'showByPath']);

// Component data endpoints for Puck page builder
Route::get('component-data/flash-sale-products', [ComponentDataController::class, 'flashSaleProducts']);
Route::get('component-data/categories', [ComponentDataController::class, 'categories']);
Route::get('component-data/collections', [ComponentDataController::class, 'collections']);
Route::get('component-data/popular-products', [ComponentDataController::class, 'popularProducts']);
Route::get('component-data/best-selling-products', [ComponentDataController::class, 'bestSellingProducts']);

Route::apiResource('flash-sale', FlashSaleController::class, [
    'only' => ['index', 'show'],
]);

Route::resource('refund-policies', RefundPolicyController::class, [
    'only' => ['index', 'show'],
]);


Route::post('shop-maintenance-event', [ShopController::class, 'shopMaintenanceEvent']);

/**
 * ******************************************
 * Authorized Route for Customers only
 * ******************************************
 */

Route::group(
    ['middleware' => ['role:' . Role::SUPER_ADMIN . "|" . Role::EDITOR, 'auth:sanctum', 'email.verified']],
    function () {
        Route::post('cms-pages', [CmsPageController::class, 'store']);
        Route::put('cms-pages/{id}', [CmsPageController::class, 'update']);
        Route::delete('cms-pages/{id}', [CmsPageController::class, 'destroy']);

        // Puck page builder save endpoint (with upsert)
        Route::post('puck/page', [CmsPageController::class, 'storePuckPage']);
        Route::post('content-pages/{content_page}/attach-sections', [ContentPageController::class, 'attachSections']);
        Route::patch('content-pages/{content_page}/toggle-active', [ContentPageController::class, 'toggleActive']);
        Route::apiResource('content-pages', ContentPageController::class);
        Route::post('sections/reorder', [SectionController::class, 'reorder']);
        Route::get('sections/types', [SectionController::class, 'getTypeSection']);
        Route::patch('sections/{section}/toggle-active', [SectionController::class, 'toggleStatus']);
        Route::apiResource('sections', SectionController::class);
        Route::apiResource('section-types', SectionTypeController::class);
        Route::post('section-types/{type}/settings', [SectionTypeController::class, 'updateSettings']);
        Route::get('section-types/{type}/settings', [SectionTypeController::class, 'settings']);
    }
);

Route::group(['middleware' => ['auth:sanctum', 'email.verified']], function () {
    Route::post('/update-email', [UserController::class, 'updateUserEmail']);
    // Route::get('me', [UserController::class, 'me']);
    // Route::apiResource('orders', OrderController::class, [
    //     'only' => ['index'],
    // ]);
// });

    /**
     * Content Creation Routes - Rate Limited (5/min per user)
     * Protects against review bombing, spam, and fake content
     */
    Route::middleware(['throttle:content'])->group(function () {
        Route::apiResource('reviews', ReviewController::class, [
            'only' => ['store', 'update']
        ]);
        Route::apiResource('questions', QuestionController::class, [
            'only' => ['store'],
        ]);
        Route::apiResource('feedbacks', FeedbackController::class, [
            'only' => ['store'],
        ]);
        Route::apiResource('abusive_reports', AbusiveReportController::class, [
            'only' => ['store'],
        ]);
        Route::post('messages/conversations/{conversation_id}', [MessageController::class, 'store']);
    });

    Route::apiResource('conversations', ConversationController::class, [
        'only' => ['index', 'store'],
    ]);
    Route::get('conversations/{conversation_id}', [ConversationController::class, 'show']);
    Route::get('messages/conversations/{conversation_id}', [MessageController::class, 'index']);
    Route::post('messages/seen/{conversation_id}', [MessageController::class, 'seen']);
    Route::get('my-questions', [QuestionController::class, 'myQuestions']);
    Route::get('my-reports', [AbusiveReportController::class, 'myReports']);
    Route::post('wishlists/toggle', [WishlistController::class, 'toggle']);
    Route::apiResource('wishlists', WishlistController::class, [
        'only' => ['index', 'store', 'destroy'],
    ]);
    Route::get('wishlists/in_wishlist/{product_id}', [WishlistController::class, 'in_wishlist']);
    Route::get('my-wishlists', [ProductController::class, 'myWishlists']);
    Route::get('orders/tracking-number/{tracking_number}', 'Marvel\Http\Controllers\OrderController@findByTrackingNumber');

    /**
     * File Upload Routes - Rate Limited (10/min per user)
     */
    Route::middleware(['throttle:uploads'])->group(function () {
        Route::apiResource('attachments', AttachmentController::class, [
            'only' => ['store', 'update', 'destroy'],
        ]);
    });

    Route::put('users/{id}', [UserController::class, 'update']);
    Route::post('/change-password', [UserController::class, 'changePassword']);
    Route::post('/update-contact', [UserController::class, 'updateContact']);
    Route::apiResource('address', AddressController::class);

    /**
     * Refund Routes - Rate Limited (5/min per user)
     * Protects against refund fraud attempts
     */
    Route::middleware(['throttle:refunds'])->group(function () {
        Route::apiResource('refunds', RefundController::class, [
            'only' => ['store'],
        ]);
    });

    // Refund viewing is not rate limited
    Route::apiResource('refunds', RefundController::class, [
        'only' => ['index', 'show'],
    ]);

    Route::get('downloads', [DownloadController::class, 'fetchDownloadableFiles']);
    Route::post('downloads/digital_file', [DownloadController::class, 'generateDownloadableUrl']);
    Route::get('/followed-shops-popular-products', [ShopController::class, 'followedShopsPopularProducts']);
    Route::get('/followed-shops', [ShopController::class, 'userFollowedShops']);
    Route::get('/follow-shop', [ShopController::class, 'userFollowedShop']);
    Route::post('/follow-shop', [ShopController::class, 'handleFollowShop']);
    Route::apiResource('cards', PaymentMethodController::class, [
        'only' => ['index', 'store', 'update', 'destroy'],
    ]);
    Route::post('/set-default-card', [PaymentMethodController::class, 'setDefaultCard']);
    Route::post('/save-payment-method', [PaymentMethodController::class, 'savePaymentMethod']);
    Route::apiResource('faqs', FaqsController::class, [
        'only' => ['index', 'show'],
    ]);
    Route::apiResource('notify-logs', NotifyLogsController::class, [
        'only' => ['index', 'show'],
    ]);
    Route::post('notify-log-seen', [NotifyLogsController::class, 'readNotifyLogs']);
    Route::post('notify-log-read-all', [NotifyLogsController::class, 'readAllNotifyLogs']);
});

// Todo : Popular product on analytics route
// chawkbazar old code
// Route::get('popular-products', 'Marvel\Http\Controllers\AnalyticsController@popularProducts');
// Route::get('popular-products', 'Marvel\Http\Controllers\AnalyticsController@popularProducts');

// chawkbazar new code
Route::get('popular-products', 'Marvel\Http\Controllers\ProductController@popularProducts');

/**
 * ******************************************
 * Authorized Route for Staff & Store Owner
 * ******************************************
 */


Route::group(
    ['middleware' => ['role:' . Role::SUPER_ADMIN, 'auth:sanctum', 'email.verified']],
    function () {
        Route::post('products/bulk-delete', [ProductController::class, 'destroyBulk']);
        Route::delete('products/all', [ProductController::class, 'destroyAll']);
        Route::apiResource('products', ProductController::class, [
            'only' => ['store', 'show', 'update', 'destroy'],
        ]);
        Route::put('products/{id}/fast-shipping', [ProductController::class, 'toggleFastShipping']);
        Route::apiResource('resources', ResourceController::class, [
            'only' => ['store']
        ]);
        Route::apiResource('attributes', AttributeController::class, [
            'only' => ['store', 'update', 'destroy'],
        ]);
        Route::apiResource('attribute-values', AttributeValueController::class, [
            'only' => ['store', 'update', 'destroy'],
        ]);
        /**
         * GET /api/v1/orders
         * List all orders (paginated). Super Admins see all orders. Store Owners/Staff
         * see orders scoped to their shop. Customers see only their own orders.
         * Supports filtering by shop_id and tracking_number.
         * Middleware: role:super_admin | auth:sanctum | email.verified
         */
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');

        /**
         * GET /api/v1/orders/{id}
         * Get a single order by ID or tracking number. Includes eager-loaded
         * relations: products, shop, children.shop, wallet_point.
         * Authorization is role-based: Super Admin (all), Owner/Staff (shop-scoped),
         * Customer (own only).
         * Middleware: role:super_admin | auth:sanctum | email.verified
         */
        Route::get('orders/{id}', [OrderController::class, 'show'])->name('orders.show');


        Route::post('banner/change-status', [BannerController::class, 'changeStatus']);
        Route::post('banner/reorder', [BannerController::class, 'reorder']);
        Route::patch('sliders/change-status', [SliderController::class, 'changeStatus']);
        Route::put('sliders/reorder', [SliderController::class, 'reorder']);
        Route::apiResource('banners', BannerController::class);
        Route::apiResource('sliders', SliderController::class);

        Route::apiResource('countries', CountryController::class);
        Route::get('countries/{id}/governorates', [CountryController::class, 'governorates']);
        Route::post('countries/change-status', [CountryController::class, 'bulkStatus']);

        Route::apiResource('governorates', GovernorateController::class);
        Route::get('governorates/{id}/cities', [GovernorateController::class, 'cities']);
        Route::post('governorates/change-status', [GovernorateController::class, 'bulkStatus']);
        Route::put('governorates/{id}/fast-shipping', [GovernorateController::class, 'toggleFastShipping']);

        Route::apiResource('cities', CityController::class);

        // Route::get('shop-notification/{id}', [ShopNotificationController::class, 'show']);
        // Route::put('shop-notification/{id}', [ShopNotificationController::class, 'update']);
        // Route::get('popular-products', [AnalyticsController::class, 'popularProducts']);
        // Route::get('shops/refunds', 'Marvel\Http\Controllers\ShopController@refunds');
        Route::apiResource('questions', QuestionController::class, [
            'only' => ['update'],
        ]);
        Route::apiResource('authors', AuthorController::class, [
            'only' => ['store'],
        ]);
        Route::apiResource('manufacturers', ManufacturerController::class, [
            'only' => ['store'],
        ]);
        Route::get('store-notices/getStoreNoticeType', [StoreNoticeController::class, 'getStoreNoticeType']);
        Route::get('store-notices/getUsersToNotify', [StoreNoticeController::class, 'getUsersToNotify']);
        Route::post('store-notices/read/', [StoreNoticeController::class, 'readNotice']);
        Route::post('store-notices/read-all', [StoreNoticeController::class, 'readAllNotice']);
        Route::apiResource('store-notices', StoreNoticeController::class, [
            'only' => ['show', 'store', 'update', 'destroy']
        ]);

        Route::get('export-order-url/{shop_id?}', 'Marvel\Http\Controllers\OrderController@exportOrderUrl');
        Route::post('download-invoice-url', 'Marvel\Http\Controllers\OrderController@downloadInvoiceUrl');
        Route::apiResource('faqs', FaqsController::class, [
            'only' => ['store', 'update', 'destroy'],
        ]);
        Route::get('analytics', [AnalyticsController::class, 'analytics']);
        Route::get('low-stock-products', [AnalyticsController::class, 'lowStockProducts']);
        Route::get('category-wise-product', [AnalyticsController::class, 'categoryWiseProduct']);
        Route::get('category-wise-product-sale', [AnalyticsController::class, 'categoryWiseProductSale']);
        Route::get('draft-products', [ProductController::class, 'draftedProducts']);
        Route::get('products-stock', [ProductController::class, 'productStock']);
        Route::get('products-by-flash-sale', [FlashSaleController::class, 'getProductsByFlashSale']);
        Route::get('top-rate-product', [AnalyticsController::class, 'topRatedProducts']);
        Route::apiResource('coupons', CouponController::class, [
            'only' => ['update'],
        ]);
        Route::apiResource('promotions', PromotionController::class, [
            'only' => ['update'],
        ]);
        // Route::get('products-requested-for-flash-sale-by-vendor', [FlashSaleVendorRequestController::class, 'getProductsByFlashSaleVendorRequest']);
        Route::get('requested-products-for-flash-sale', [FlashSaleVendorRequestController::class, 'getRequestedProductsForFlashSale']);
        Route::apiResource('vendor-requests-for-flash-sale', FlashSaleVendorRequestController::class, [
            'only' => ['index', 'show', 'store', 'destroy'],
        ]);
    }
);


/**
 * *****************************************
 * Authorized Route for Store owner Only
 * *****************************************
 */

Route::group(
    ['middleware' => ['role:' . Role::SUPER_ADMIN, 'auth:sanctum', 'email.verified']],
    function () {
        Route::apiResource('shops', ShopController::class);
        Route::post('shops/{id}/relations/{relation}', [ShopController::class, 'syncShopRelation']);

        // Route::get('analytics', [AnalyticsController::class, 'analytics']);
        Route::apiResource('withdraws', WithdrawController::class, [
            'only' => ['store', 'index', 'show'],
        ]);
        Route::post('staffs', [ShopController::class, 'addStaff']);
        Route::delete('staffs/{id}', [ShopController::class, 'deleteStaff']);
        Route::get('staffs', [UserController::class, 'staffs']);
        Route::get('my-shops', [ShopController::class, 'myShops']);
        Route::post('transfer-shop-ownership', [ShopController::class, 'transferShopOwnership']);

        // Route::get('/admin/list', [UserController::class, 'admins']);
        // Route::apiResource('notify-logs', NotifyLogsController::class, [
        //     'only' => ['index'],
        // ]);

        // Route::post('notify-log-seen', [NotifyLogsController::class, 'readNotifyLogs']);
        // Route::post('notify-log-read-all', [NotifyLogsController::class, 'readAllNotifyLogs']);

        Route::apiResource('faqs', FaqsController::class, [
            'only' => ['store', 'update', 'destroy'],
        ]);

        Route::apiResource('flash-sale', FlashSaleController::class, [
            'only' => ['store', 'update', 'destroy'],
        ]);

        Route::put('flash-sale/reorder', [FlashSaleController::class, 'reorder']);

        Route::get('product-flash-sale-info', [FlashSaleController::class, 'getFlashSaleInfoByProductID']);

        Route::apiResource('terms-and-conditions', TermsAndConditionsController::class, [
            'only' => ['store', 'update', 'destroy'],
        ]);

        Route::apiResource('coupons', CouponController::class, [
            'only' => ['store', 'destroy'],
        ]);
        Route::apiResource('promotions', PromotionController::class, [
            'only' => ['store', 'destroy'],
        ]);

        Route::apiResource('terms-and-conditions', TermsAndConditionsController::class, [
            'only' => ['store', 'update', 'destroy'],
        ]);
        Route::get('/vendors/list', [UserController::class, 'vendors']);
        // Route::post('products-request-for-flash-sale', [FlashSaleVendorRequestController::class, 'productsRequestForFlashSale']);

        Route::apiResource('ownership-transfer', OwnershipTransferController::class, [
            'only' => ['index', 'show'],
        ]);
    }
);

/**
 * *****************************************
 * Authorized Route for Super Admin only
 * *****************************************
 */

Route::group([
    'middleware' => [
        'auth:sanctum',
        'verified',
        // 'role:' . Role::SUPER_ADMIN,
    ]
], function () {
    // Route::get('messages/get-conversations/{shop_id}', [ConversationController::class, 'getConversationByShopId']);
    // Route::get('analytics', [AnalyticsController::class, 'analytics']);
    Route::apiResource('types', TypeController::class, [
        'only' => ['store', 'update', 'destroy'],
    ]);
    Route::apiResource('withdraws', WithdrawController::class, [
        'only' => ['update', 'destroy'],
    ]);
    Route::put('categories/feature', [CategoryController::class, 'addOrRemoveCategoryFromFeature']);
    Route::apiResource('categories', CategoryController::class);
    Route::get('categories-parent', [CategoryController::class, 'fetchOnlyParent']);
    Route::put('brands/reorder', [BrandController::class, 'reorder']);
    Route::apiResource('brands', BrandController::class);

    Route::apiResource('delivery-times', DeliveryTimeController::class, [
        'only' => ['store', 'update', 'destroy']
    ]);
    Route::apiResource('languages', LanguageController::class, [
        'only' => ['store', 'update', 'destroy']
    ]);
    Route::apiResource('tags', TagController::class, [
        'only' => ['store', 'update', 'destroy'],
    ]);
    Route::apiResource('refund-reasons', RefundReasonController::class, [
        'only' => ['store', 'update', 'destroy'],
    ]);
    Route::apiResource('resources', ResourceController::class, [
        'only' => ['update', 'destroy']
    ]);

    // Route::apiResource('coupons', CouponController::class, [
    //     'only' => ['store', 'update', 'destroy'],
    // ]);
    // Route::apiResource('order-status', OrderStatusController::class, [
    //     'only' => ['store', 'update', 'destroy'],
    // ]);
    Route::post('reviews/{id}/toggle-approve', [ReviewController::class, 'toggleApproveReview']);
    Route::apiResource('reviews', ReviewController::class, [
        'only' => ['destroy']
    ]);
    Route::apiResource('questions', QuestionController::class, [
        'only' => ['destroy'],
    ]);
    Route::apiResource('feedbacks', FeedbackController::class, [
        'only' => ['update', 'destroy'],
    ]);
    Route::apiResource('abusive_reports', AbusiveReportController::class, [
        'only' => ['index', 'show', 'update', 'destroy'],
    ]);
    Route::post('abusive_reports/accept', [AbusiveReportController::class, 'accept']);
    Route::post('abusive_reports/reject', [AbusiveReportController::class, 'reject']);
    Route::apiResource('settings', SettingsController::class, [
        'only' => ['update'],
    ]);
    Route::get('fast-shipping/settings', [FastShippingController::class, 'getSettings']);
    Route::put('fast-shipping/settings', [FastShippingController::class, 'updateSettings']);
    Route::apiResource('users', UserController::class);
    Route::apiResource('authors', AuthorController::class, [
        'only' => ['update', 'destroy'],
    ]);
    Route::apiResource('manufacturers', ManufacturerController::class, [
        'only' => ['update', 'destroy'],
    ]);
    Route::post('users/block-user', [UserController::class, 'banUser']);
    Route::post('users/unblock-user', [UserController::class, 'activeUser']);
    Route::apiResource('taxes', TaxController::class);
    Route::apiResource('shippings', ShippingController::class);
    Route::apiResource('shipping-prices', ShippingPriceController::class);
    Route::apiResource('pickup-locations', PickupLocationController::class);
    Route::post('approve-shop', [ShopController::class, 'approveShop']);
    Route::post('disapprove-shop', [ShopController::class, 'disApproveShop']);
    Route::post('approve-withdraw', [WithdrawController::class, 'approveWithdraw']);
    Route::post('add-points', [UserController::class, 'addPoints']);
    Route::post('users/make-admin', [UserController::class, 'makeOrRevokeAdmin']);
    Route::apiResource(
        'refunds',
        RefundController::class,
        [
            'only' => ['destroy', 'update'],
        ]
    );
    Route::apiResource('notify-logs', NotifyLogsController::class, [
        'only' => ['destroy'],
    ]);

    Route::put('faqs/reorder', [FaqsController::class, 'reorder']);
    Route::apiResource('faqs', FaqsController::class);
    Route::get('new-shops', [ShopController::class, 'newOrInActiveShops']);
    Route::post('approve-terms-and-conditions', [TermsAndConditionsController::class, 'approveTerm']);
    Route::post('disapprove-terms-and-conditions', [TermsAndConditionsController::class, 'disApproveTerm']);
    Route::post('admin-users/add', [UserController::class, 'adminAddUsers']);
    Route::put('admin-users/update-activation', [UserController::class, 'adminUpdateActivationUsers']);
    Route::delete('admin-users/delete/{id}', [UserController::class, 'adminDeleteUsers']);
    Route::put('admin-users/restore/{id}', [UserController::class, 'adminRestoreUser']);
    Route::delete('admin-users/delete-forever/{id}', [UserController::class, 'adminDeleteUsersForever']);
    Route::get('logs/activity', [ActivityLogController::class, 'index']);

    // Notifications
    Route::prefix('admin')->controller(NotificationController::class)->group(function () {
        Route::get('notifications', 'index');
        Route::get('notifications/unread', 'unread');
        Route::patch('notifications/{id}/read', 'markAsRead');
        Route::patch('notifications/read-all', 'markAllAsRead');
        Route::delete('notifications/{id}', 'destroy');
        Route::delete('notifications', 'destroyAll');
    });

    Route::get('/customers/list', [UserController::class, 'customers']);
    Route::get('my-staffs', [UserController::class, 'myStaffs']);
    Route::get('all-staffs', [UserController::class, 'allStaffs']);
    Route::resource('refund-policies', RefundPolicyController::class, [
        'only' => ['store', 'update', 'destroy'],
    ]);
    Route::post('approve-coupon', [CouponController::class, 'approveCoupon']);
    Route::post('disapprove-coupon', [CouponController::class, 'disApproveCoupon']);
    // Route::get('requested-products-for-flash-sale', [FlashSaleVendorRequestController::class, 'getRequestedProductsForFlashSale']);
    Route::post('approve-flash-sale-requested-products', [FlashSaleVendorRequestController::class, 'approveFlashSaleProductsRequest']);
    Route::post('disapprove-flash-sale-requested-products', [FlashSaleVendorRequestController::class, 'disapproveFlashSaleProductsRequest']);
    Route::apiResource('vendor-requests-for-flash-sale', FlashSaleVendorRequestController::class, [
        'only' => ['update'],
    ]);


    Route::get('/roles', [RoleAndPermissionController::class, 'getAllRoles']);
    Route::get('/roles/{id}', [RoleAndPermissionController::class, 'showRole']);
    Route::post('/roles', [RoleAndPermissionController::class, 'addRole']);
    Route::put('/roles/{id}', [RoleAndPermissionController::class, 'updateRole']);
    Route::delete('/roles/{id}', [RoleAndPermissionController::class, 'destroyRole']);
    Route::post('/users/{userId}/assign-role', [RoleAndPermissionController::class, 'assignRole']);
    Route::post('/users/{userId}/remove-role', [RoleAndPermissionController::class, 'removeRoleFromUser']);

    Route::get('/permissions', [RoleAndPermissionController::class, 'getAllPermissions']);
    Route::post('/roles/{roleId}/permissions', [RoleAndPermissionController::class, 'assignPermissionToRole']);
    Route::post('/users/{userId}/permissions', [RoleAndPermissionController::class, 'givePermission']);
    Route::put('/users/{userId}/permissions', [RoleAndPermissionController::class, 'syncPermissions']);
    Route::delete('/users/{userId}/permissions', [RoleAndPermissionController::class, 'removePermission']);

    Route::apiResource('ownership-transfer', OwnershipTransferController::class, [
        'only' => ['update', 'destroy'],
    ]);
    Route::post('products/import', [ProductImportController::class, 'import'])->name('admin.products.import');
    Route::post('products/import/{id}/cancel', [ProductImportController::class, 'cancel'])->name('admin.products.import.cancel');
    Route::get('products/import/{id}', [ProductImportController::class, 'status'])->name('admin.products.import.status');
    Route::get('products/import/{id}/download-errors', [ProductImportController::class, 'downloadErrors'])->name('admin.products.import.download-errors');

    /**
     * Dashboard API — platform-wide metrics
     */
    Route::middleware(['throttle:analytics'])->prefix('dashboard')->group(function () {
        Route::get('overview', [DashboardController::class, 'overview']);
        Route::get('revenue', [DashboardController::class, 'revenue']);
        Route::get('order-stats', [DashboardController::class, 'orderStats']);
        Route::get('recent-orders', [DashboardController::class, 'recentOrders']);
        Route::get('top-products', [DashboardController::class, 'topProducts']);
        Route::get('category-stats', [DashboardController::class, 'categoryStats']);
        Route::get('low-stock', [DashboardController::class, 'lowStock']);
        Route::get('sales', [DashboardController::class, 'salesAnalytics']);
        Route::get('customers', [DashboardController::class, 'customerAnalytics']);
        Route::get('products', [DashboardController::class, 'productAnalytics']);
        Route::get('orders', [DashboardController::class, 'orderAnalytics']);
        Route::get('categories', [DashboardController::class, 'categoryAnalytics']);
        Route::get('coupons', [DashboardController::class, 'couponAnalytics']);
        Route::get('cart', [DashboardController::class, 'cartAnalytics']);
        Route::get('finance', [DashboardController::class, 'financeAnalytics']);
        Route::get('reconciliation', [DashboardController::class, 'reconciliation']);
    });
});
Route::middleware(['auth:sanctum', "throttle:cart"])->group(function () {
    Route::get('cart', [CartController::class, 'index']);
    Route::post('cart', [CartController::class, 'store']);
    Route::post('cart/bulk-items', [CartController::class, 'pluckItemsToCart']);
    Route::put('cart/update-item', [CartController::class, 'update']);
    Route::delete('cart/delete-item/{itemId}', [CartController::class, 'deleteItemFromCart']);
    Route::delete('cart/delete-items', [CartController::class, 'destroy']);
});


Route::apiResource('became-seller', BecameSellerController::class);
