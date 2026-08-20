<?php

namespace App\Http\Controllers;

use App\Models\GuestSession;
use App\Models\Table;
use Illuminate\Http\RedirectResponse;

class OrderController extends Controller
{
    /**
     * Resolve a QR-code scan into a fresh guest session and redirect to the menu.
     *
     * Route: GET /order/{table_token}  (the URL printed on the physical QR — never changes)
     *
     * A new GuestSession is minted on every scan, valid for 1 hour. The menu
     * and checkout flows trust the session token from here on, not the
     * table_token or any table/qr id the client might send.
     */
    public function scan(string $table_token): RedirectResponse
    {
        $table = Table::where('qr_token', $table_token)->firstOrFail();

        $qrCode = $table->qrCode;

        if (! $qrCode) {
            abort(404, 'QR code not found for this table.');
        }

        $session = GuestSession::startFor($qrCode);

        return redirect()->route('menu', ['session_token' => $session->token]);
    }
}
