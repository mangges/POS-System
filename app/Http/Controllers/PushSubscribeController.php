<?php

namespace App\Http\Controllers;

use App\Models\GuestSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscribeController extends Controller
{
    public function __invoke(Request $request, string $session_token): JsonResponse
    {
        $guestSession = GuestSession::where('token', $session_token)->firstOrFail();

        $data = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys.p256dh' => ['nullable', 'string'],
            'keys.auth' => ['nullable', 'string'],
        ]);

        $guestSession->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'] ?? null,
            $data['keys']['auth'] ?? null,
        );

        return response()->json(['status' => 'ok']);
    }
}
