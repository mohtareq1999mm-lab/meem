<?php

namespace App\Providers;

use App\Events\UserRolesUpdated;
use App\Listeners\LogUserRolesUpdated;
use App\Observers\BrandObserver;
use App\Observers\CategoryObserver;
use App\Observers\CouponObserver;
use App\Observers\FlashSaleObserver;
use App\Observers\PickupLocationObserver;
use App\Observers\ProductObserver;
use App\Observers\PromotionObserver;
use App\Observers\RoleObserver;
use App\Observers\UserObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Marvel\Database\Models\Brand;
use Marvel\Database\Models\Category;
use Marvel\Database\Models\Coupon;
use Marvel\Database\Models\FlashSale;
use Marvel\Database\Models\PickupLocation;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Promotion;
use Marvel\Database\Models\Role;
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
    ];

    /**
     * The model observers for the application.
     *
     * @var array
     */
    protected $observers = [
        Product::class => [ProductObserver::class],
        Category::class => [CategoryObserver::class],
        Brand::class => [BrandObserver::class],
        Coupon::class => [CouponObserver::class],
        FlashSale::class => [FlashSaleObserver::class],
        Promotion::class => [PromotionObserver::class],
        Role::class => [RoleObserver::class],
        User::class => [UserObserver::class],
        PickupLocation::class => [PickupLocationObserver::class],
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
