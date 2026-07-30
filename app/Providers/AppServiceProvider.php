<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        // Aliases used by `app_notifications.record_type` (deep-link target) and
        // `app_notification_recipients.recipient_type` (polymorphic recipient) —
        // keeps both columns human-readable instead of storing fully-qualified class names.
        // Applies to every morph relation on these models, so both columns stay consistent.
        Relation::morphMap([
            'order' => Order::class,
            'product' => Product::class,
            'user' => User::class,
        ]);
    }
}
