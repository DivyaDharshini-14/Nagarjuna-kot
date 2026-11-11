<?php

namespace App\Providers;

use App\Events\KotTicketChanged;
use App\Listeners\SendKotPushNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Event::listen(KotTicketChanged::class, SendKotPushNotification::class);
    }
}
