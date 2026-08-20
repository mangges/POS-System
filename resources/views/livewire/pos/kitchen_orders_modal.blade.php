<div class="drafts-modal">
    <div class="drafts-modal-header">
        <h3>Sedang Disiapkan</h3>
        <button type="button" wire:click="closeKitchenOrdersModal" class="close-desktop-btn">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div class="drafts-list">
        @if(count($this->processingTableOrders) === 0)
            <p class="no-drafts-message">Tidak ada pesanan yang sedang disiapkan.</p>
        @else
            @foreach($this->processingTableOrders as $order)
                <div class="draft-item table-order-item">
                    <div class="draft-info">
                        <div class="draft-title">
                            <span class="draft-id">Meja {{ $order->table->name ?? '-' }}</span>
                            <span class="draft-customer">{{ $order->customer_name ?? 'Pelanggan Tidak Diketahui' }}</span>
                        </div>
                        <span class="draft-total">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</span>
                    </div>
                    <div class="draft-actions table-order-actions">
                        <button wire:click.stop="markOrderReady({{ $order->id }})" class="accept-btn" title="Tandai Siap">
                            <i class="bi bi-check-circle"></i> Tandai Siap
                        </button>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    <div class="drafts-modal-header">
        <h3>Siap, Perlu Dibayar</h3>
    </div>
    <div class="drafts-list">
        @if(count($this->readyTableOrders) === 0)
            <p class="no-drafts-message">Tidak ada pesanan yang siap dibayar.</p>
        @else
            @foreach($this->readyTableOrders as $order)
                <div class="draft-item table-order-item">
                    <div class="draft-info">
                        <div class="draft-title">
                            <span class="draft-id">Meja {{ $order->table->name ?? '-' }}</span>
                            <span class="draft-customer">{{ $order->customer_name ?? 'Pelanggan Tidak Diketahui' }}</span>
                        </div>
                        <span class="draft-total">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</span>
                    </div>
                    <div class="draft-actions table-order-actions">
                        <button wire:click.stop="finalizeTableOrder({{ $order->id }})" class="accept-btn" title="Selesaikan Pembayaran">
                            <i class="bi bi-cash-coin"></i> Selesaikan
                        </button>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>
