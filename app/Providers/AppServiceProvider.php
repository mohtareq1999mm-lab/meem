<?php

namespace App\Providers;

use App\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(\App\Contexts\ChannelContext::class);

        $this->app->bind(
            \App\Services\Invoice\InvoiceSnapshotValidator::class,
            fn($app) => new \App\Services\Invoice\InvoiceSnapshotValidator(
                $app->make(\App\Services\Invoice\Validators\StructureValidator::class),
                $app->make(\App\Services\Invoice\Validators\FinancialInvariantValidator::class),
                $app->make(\App\Services\Invoice\Validators\CurrencyValidator::class),
                $app->make(\App\Services\Invoice\Validators\MoneyValidator::class),
                $app->make(\App\Services\Invoice\Validators\MetadataValidator::class),
                $app->make(\App\Services\Invoice\Validators\SnapshotVersionValidator::class),
            )
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        ini_set('serialize_precision', '-1');

        Schema::defaultStringLength(191);
        if (!\App::environment('local')) {
            $this->app['request']->server->set('HTTPS', true);
        }

        Mail::extend('resend', function (array $config) {
            $apiKey = $config['key'] ?? config('services.resend.key');

            return new ResendTransport(\Resend::client($apiKey));
        });
    }
}
