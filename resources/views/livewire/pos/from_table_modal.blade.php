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
                <div class="draft-item table-order-item" x-data="{ open: false }">
                    <div class="draft-info">
                        <div class="draft-title">
                            <span class="draft-id">Meja {{ $order->table->name ?? '-' }}</span>
                            <span class="draft-customer">{{ $order->customer_name ?? 'Pelanggan Tidak Diketahui' }}</span>
                        </div>
                        <span class="draft-total">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</span>
                    </div>

                    <button type="button" class="draft-items-toggle" @click.stop="open = ! open">
                        <i class="bi" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                        {{ $order->items->count() }} item
                    </button>

                    <div class="draft-items-preview" :class="{ 'is-open': open }" @click.stop>
                        <div class="draft-items-preview-inner">
                            @foreach ($order->items as $item)
                                <div class="draft-items-preview-row">
                                    <span class="draft-items-preview-name">{{ $item->quantity }}x {{ $item->product->name ?? 'Produk dihapus' }}</span>
                                    <span>Rp {{ number_format($item->subtotal, 0, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @if(($order->payment->payment_method ?? null) == 'qris')
                        <button type="button" wire:click.stop="openQrisPreviewForOrder({{ $order->id }})" class="btn-preview-qris">
                            <i class="bi bi-qr-code"></i> Lihat QRIS
                        </button>
                        <label class="payment-confirm-checkbox-inline">
                            <input type="checkbox" wire:model.live="confirmedPayments.{{ $order->id }}">
                            <span>Konfirmasi QRIS diterima.</span>
                        </label>
                    @endif
                    <div class="draft-actions table-order-actions">
                        <button wire:click.stop="declineTableOrder({{ $order->id }})" class="decline-btn" title="Tolak Pesanan">
                            <i class="bi bi-x-lg"></i> Decline
                        </button>
                        <button wire:click.stop="acceptTableOrder({{ $order->id }})" class="accept-btn" title="Terima Pesanan"
                            @if(($order->payment->payment_method ?? null) == 'qris' && ! ($confirmedPayments[$order->id] ?? false)) disabled @endif>
                            <i class="bi bi-check-lg"></i> Accept
                        </button>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>
