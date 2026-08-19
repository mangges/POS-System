<?php

namespace App\Providers;

use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\StockMovement;
use App\Observers\StockMovementObserver;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        Relation::morphMap([
            'product' => Product::class,
            'raw_material' => RawMaterial::class,
        ]);

        StockMovement::observe(StockMovementObserver::class);
    }
}
