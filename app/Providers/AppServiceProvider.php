<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\StockMovement;
use App\Models\User;
use App\Observers\OrderObserver;
use App\Observers\StockMovementObserver;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\Filament\Auth\Http\Responses\Contracts\LogoutResponse::class, function () {
            return new class implements \Filament\Auth\Http\Responses\Contracts\LogoutResponse {
                public function toResponse($request)
                {
                    return redirect()->route('login');
                }
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(fn (User $user) => $user->hasRole('admin') ? true : null);

        Relation::morphMap([
            'product' => Product::class,
            'raw_material' => RawMaterial::class,
        ]);

        StockMovement::observe(StockMovementObserver::class);
        Order::observe(OrderObserver::class);
    }
}
