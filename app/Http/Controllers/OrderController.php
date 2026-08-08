<?php

namespace App\Http\Controllers;

use App\Models\Table;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Resolve a QR-code scan and display the customer-facing menu page.
     *
     * Route: GET /order/{table_token}
     *
     * Security rules:
     *  - The qr_token must exist in the tables table.
     *  - Meja with status "reserved" is treated as unavailable → 404.
     *  - On success, table_id is written to the session so that Livewire
     *    self-order components can read it without re-validating the token.
     *
     * @param  string  $table_token  The qr_token encoded in the QR image.
     */
    public function menu(Request $request, string $table_token)
    {
        // Resolve the table by token — never expose the raw primary key in URLs
        $table = Table::where('qr_token', $table_token)->first();

        // Token not found or meja is reserved → hard 404
        if (! $table || $table->status === 'reserved') {
            abort(404, 'Meja tidak ditemukan atau tidak tersedia.');
        }

        // Persist identifiers in session so Livewire components can trust them
        session([
            'order_table_id'    => $table->id,
            'order_table_name'  => $table->name,
            'order_table_token' => $table->qr_token,
        ]);

        // Delegate rendering to the LandingPage Livewire component which already
        // contains the menu UI. Pass table context via route/session.
        return view('livewire.order.menu', compact('table'));
    }
}
