<div class="drafts-modal">
    <div class="drafts-modal-header">
        <h3>Pesanan dari Meja</h3>
        <button type="button" wire:click="closeFromTableModal" class="close-desktop-btn">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div class="drafts-list">
        @if(count($this->fromTableOrders) === 0)
            <p class="no-drafts-message">Tidak ada pesanan dari meja.</p>
        @else
            @foreach($this->fromTableOrders as $order)
                <div class="draft-item table-order-item">
                    <div class="draft-info">
                        <div class="draft-title">
                            <span class="draft-id">Meja {{ $order->table->name ?? '-' }}</span>
                            <span class="draft-customer">{{ $order->customer_name ?? 'Pelanggan Tidak Diketahui' }}</span>
                        </div>
                        <span class="draft-total">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</span>
                    </div>
                    <div class="draft-actions table-order-actions">
                        <button wire:click.stop="declineTableOrder({{ $order->id }})" class="decline-btn" title="Tolak Pesanan">
                            <i class="bi bi-x-lg"></i> Decline
                        </button>
                        <button wire:click.stop="acceptTableOrder({{ $order->id }})" class="accept-btn" title="Terima Pesanan">
                            <i class="bi bi-check-lg"></i> Accept
                        </button>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>
