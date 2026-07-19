<?php

namespace App\Providers;

use App\Events\OrderCancelled;
use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Events\PaymentFailed;
use App\Events\PaymentSucceeded;
use App\Events\UserRolesUpdated;
use App\Listeners\LogUserRolesUpdated;
use App\Listeners\RestoreProductInventory;
use App\Listeners\SendNewOrderNotification;
use App\Listeners\SendOrderCancelledNotification;
use App\Listeners\SendOrderStatusChangedNotification;
use App\Listeners\SendPaymentFailedNotification;
use App\Listeners\SendPaymentSucceededNotification;
use App\Observers\BrandObserver;
use App\Observers\CategoryObserver;
use App\Observers\CouponObserver;
use App\Observers\FlashSaleObserver;
use App\Observers\MediaCleanupObserver;
use App\Observers\PickupLocationObserver;
use App\Observers\ProductObserver;
use App\Observers\PromotionObserver;
use App\Observers\RoleObserver;
use App\Observers\UserObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Marvel\Database\Models\Banner;
use Marvel\Database\Models\Brand;
use Marvel\Database\Models\Category;
use Marvel\Database\Models\Coupon;
use Marvel\Database\Models\FlashSale;
use Marvel\Database\Models\PickupLocation;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Promotion;
use Marvel\Database\Models\Review;
use Marvel\Database\Models\Role;
use Marvel\Database\Models\Shop;
use Marvel\Database\Models\Slider;
use Marvel\Database\Models\User;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        UserRolesUpdated::class => [
            LogUserRolesUpdated::class,
        ],
        OrderCancelled::class => [
            RestoreProductInventory::class,
            SendOrderCancelledNotification::class,
        ],
        \Marvel\Events\OrderCancelled::class => [
            RestoreProductInventory::class,
        ],
        OrderCreated::class => [
            SendNewOrderNotification::class,
        ],
        OrderStatusChanged::class => [
            SendOrderStatusChangedNotification::class,
        ],
        PaymentFailed::class => [
            SendPaymentFailedNotification::class,
        ],
        PaymentSucceeded::class => [
            SendPaymentSucceededNotification::class,
        ],
    ];

    /**
     * The model observers for the application.
     *
     * @var array
     */
    protected $observers = [
        Product::class        => [ProductObserver::class, MediaCleanupObserver::class],
        Category::class       => [CategoryObserver::class, MediaCleanupObserver::class],
        Brand::class          => [BrandObserver::class, MediaCleanupObserver::class],
        Coupon::class         => [CouponObserver::class],
        FlashSale::class      => [FlashSaleObserver::class, MediaCleanupObserver::class],
        Promotion::class      => [PromotionObserver::class],
        Role::class           => [RoleObserver::class],
        User::class           => [UserObserver::class, MediaCleanupObserver::class],
        PickupLocation::class => [PickupLocationObserver::class],
        Banner::class         => [MediaCleanupObserver::class],
        Review::class         => [MediaCleanupObserver::class],
        Shop::class           => [MediaCleanupObserver::class],
        Slider::class         => [MediaCleanupObserver::class],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
