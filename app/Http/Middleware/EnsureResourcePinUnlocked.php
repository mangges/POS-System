<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureResourcePinUnlocked
{
    public const array PROTECTED = [
        'categories' => 'access-categories',
        'products' => 'access-products',
        'raw-materials' => 'access-raw-materials',
        'recipes' => 'access-recipes',
        'stock-movements' => 'access-stock-movements',
        'units' => 'access-units',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        $routeName = $request->route()?->getName() ?? '';

        $this->forgetUnlocksForResourcesLeftBehind($routeName);

        foreach (self::PROTECTED as $slug => $permission) {
            if (str_contains($routeName, "resources.{$slug}.") && $user?->cannot($permission) && ! session("pin_unlocked.{$slug}")) {
                $url = route('filament.admin.pages.admin-pin-gate', [
                    'resource' => $slug,
                    'redirect' => $request->getRequestUri(),
                ]);

                if ($request->header('X-Livewire-Navigate')) {
                    return response('<script>window.location.href = '.json_encode($url).';</script>');
                }

                return redirect($url);
            }
        }

        return $next($request);
    }

    protected function forgetUnlocksForResourcesLeftBehind(string $routeName): void
    {
        foreach (array_keys((array) session('pin_unlocked', [])) as $slug) {
            if (! str_contains($routeName, "resources.{$slug}.")) {
                session()->forget("pin_unlocked.{$slug}");
            }
        }
    }
}
