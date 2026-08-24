<?php

namespace App\Http\Middleware;

use App\Enum\Shifts\ShiftStatus;
use App\Models\Shift;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventLogoutWithOpenShift
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('filament.admin.auth.logout')) {
            $hasOpenShift = Shift::where('user_id', auth()->id())
                ->where('status', ShiftStatus::Open)
                ->exists();

            if ($hasOpenShift) {
                Notification::make()
                    ->danger()
                    ->title('Tutup shift dulu sebelum logout')
                    ->send();

                return redirect()->back();
            }
        }

        return $next($request);
    }
}
