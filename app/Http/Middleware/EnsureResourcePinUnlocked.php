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

        foreach (self::PROTECTED as $slug => $permission) {
            if (str_contains($routeName, "resources.{$slug}.") && $user?->cannot($permission)) {
                return redirect(route('filament.admin.pages.admin-pin-gate', [
                    'resource' => $slug,
                    'redirect' => $request->getRequestUri(),
                ]));
            }
        }

        return $next($request);
    }
}
