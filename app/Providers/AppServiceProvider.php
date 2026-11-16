<?php

namespace App\Providers;

use App\Events\KotTicketChanged;
use App\Listeners\SendKotPushNotification;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Floor;
use App\Models\ItemCancellation;
use App\Models\Kitchen;
use App\Models\KitchenMenuItem;
use App\Models\KotItem;
use App\Models\KotTicket;
use App\Models\MenuItem;
use App\Models\MenuType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\StockTransaction;
use App\Models\User;
use App\Observers\OperationalAuditObserver;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
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
        foreach ([
            Company::class, Branch::class, Kitchen::class,
            Floor::class, RestaurantTable::class, Category::class,
            MenuType::class, MenuItem::class, KitchenMenuItem::class,
            User::class, Role::class, Order::class,
            OrderItem::class, KotTicket::class, KotItem::class,
            ItemCancellation::class, StockTransaction::class,
        ] as $model) {
            $model::observe(OperationalAuditObserver::class);
        }
    }
}
