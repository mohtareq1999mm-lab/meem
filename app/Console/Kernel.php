<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\CancelUnpaidOrders::class,
        \App\Console\Commands\ExpireCarts::class,
        \App\Console\Commands\NotifyAbandonedCarts::class,
        \App\Console\Commands\NotifyPromotionsEndingSoon::class,
        \App\Console\Commands\NotifyFlashSalesEndingSoon::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        $schedule->command('orders:cancel-unpaid')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('carts:expire')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('cart:notify-abandoned')->hourly()->withoutOverlapping();
        $schedule->command('promotions:notify-ending-soon')->daily()->withoutOverlapping();
        $schedule->command('flash-sales:notify-ending-soon')->daily()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
