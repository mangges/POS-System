@php
    $order = $this->viewingOrder;
    $qrisImage = $order && ($order->payment->payment_method ?? null) === 'qris'
        ? $this->qrisImageForOrderAmount($order->total_amount)
        : null;
@endphp
<div class="payment-modal-overlay" wire:click.self="closeOrderDetailModal">
    <div class="payment-modal-content">
        <div class="payment-modal-header">
            <h2 class="payment-modal-title">Rincian Pesanan</h2>
            <button class="payment-modal-close" wire:click="closeOrderDetailModal" aria-label="Tutup">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        @if ($order)
            <div class="payment-modal-body">
                <p class="payment-success-order">{{ $order->order_number }}</p>

                <div class="order-summary">
                    @foreach ($order->items as $item)
                        <div class="order-summary-row">
                            <span>{{ $item->quantity }}x {{ $item->product->name ?? 'Produk dihapus' }}</span>
                            <span>Rp {{ number_format($item->subtotal, 0, ',', '.') }}</span>
                        </div>
                    @endforeach
                    <div class="order-summary-row order-summary-total">
                        <span>Total</span>
                        <span>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</span>
                    </div>
                </div>

                @if ($qrisImage)
                    <p class="payment-notice">Scan QR di bawah ini untuk membayar langsung dari HP Anda.</p>
                    <div class="qris-qr-wrap">{!! $qrisImage !!}</div>
                    <a href="data:image/svg+xml;base64,{{ base64_encode($qrisImage) }}" download="qris-pembayaran.svg" class="qris-download-btn">
                        <i class="bi bi-download"></i> Download QRIS
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>
