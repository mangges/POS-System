<div class="payment-modal-overlay" wire:click.self="closePaymentModal">
    <div class="payment-modal-content" wire:key="payment-modal-{{ $orderSubmitted ? 'success' : 'form' }}">

        @if (!$orderSubmitted)
            <div class="payment-modal-header">
                <h2 class="payment-modal-title">Konfirmasi Pesanan</h2>
                <button class="payment-modal-close" wire:click="closePaymentModal" aria-label="Tutup">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="payment-modal-body">
                <div class="form-field">
                    <label for="customerName" class="form-label">Nama Anda</label>
                    <input type="text" id="customerName" wire:model.live.debounce.300ms="customerName" placeholder="Masukkan nama" class="form-input" autocomplete="off">
                </div>

                <div class="form-field">
                    <span class="form-label">Metode Pembayaran</span>
                    <div class="payment-method-list">
                        @if(in_array('cash', $this->activeMethods))
                        <label class="payment-method-option {{ $paymentMethod === 'cash' ? 'active' : '' }}">
                            <input type="radio" name="payment_method" wire:model.live="paymentMethod" value="cash" class="hidden-radio">
                            <span class="payment-method-label">Tunai</span>
                        </label>
                        @endif
                        @if(in_array('qris', $this->activeMethods))
                        <label class="payment-method-option {{ $paymentMethod === 'qris' ? 'active' : '' }}">
                            <input type="radio" name="payment_method" wire:model.live="paymentMethod" value="qris" class="hidden-radio">
                            <span class="payment-method-label">QRIS</span>
                        </label>
                        @endif
                        @if(in_array('transfer', $this->activeMethods))
                        <label class="payment-method-option {{ $paymentMethod === 'transfer' ? 'active' : '' }}">
                            <input type="radio" name="payment_method" wire:model.live="paymentMethod" value="transfer" class="hidden-radio">
                            <span class="payment-method-label">Debit</span>
                        </label>
                        @endif
                    </div>
                </div>

                <div class="order-summary">
                    <div class="order-summary-row">
                        <span>Subtotal</span>
                        <span>Rp {{ number_format($this->subtotal, 0, ',', '.') }}</span>
                    </div>
                    <div class="order-summary-row">
                        <span>PPN (11%)</span>
                        <span>Rp {{ number_format($this->taxAmount, 0, ',', '.') }}</span>
                    </div>
                    <div class="order-summary-row order-summary-total">
                        <span>Total</span>
                        <span>Rp {{ number_format($this->total, 0, ',', '.') }}</span>
                    </div>
                </div>

                <p class="payment-notice">
                    @if ($paymentMethod === 'cash')
                        Kasir kami akan membawakan bills ke meja Anda untuk pembayaran.
                    @elseif ($this->qrisImage)
                        Scan QR di bawah ini untuk membayar langsung dari HP Anda.
                    @else
                        Kasir kami akan membawakan mesin EDC ke meja Anda untuk pembayaran.
                    @endif
                </p>
                @if ($this->qrisImage)
                    <div class="qris-qr-wrap">{!! $this->qrisImage !!}</div>
                    <a href="data:image/svg+xml;base64,{{ base64_encode($this->qrisImage) }}" download="qris-pembayaran.svg" class="qris-download-btn">
                        <i class="bi bi-download"></i> Download QRIS
                    </a>
                @endif
            </div>

            <div class="payment-modal-footer">
                <button type="button" wire:click="checkout" class="payment-submit-btn" @if (empty($customerName)) disabled @endif>
                    Konfirmasi &amp; Pesan
                </button>
            </div>
        @else
            @php
                $successQrisImage = $paymentMethod === 'qris' ? $this->qrisImageForAmount($lastOrderTotal) : null;
            @endphp
            <div class="payment-success">
                <div class="payment-success-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h2 class="payment-success-title">Pesanan Diterima</h2>
                <p class="payment-success-order">{{ $lastOrderNumber }}</p>
                <p class="payment-success-message">
                    @if ($paymentMethod === 'cash')
                        Kasir kami akan segera membawakan struk pembayaran ke meja Anda.
                    @elseif ($paymentMethod === 'qris' && $successQrisImage)
                        Silakan scan QR di bawah ini untuk menyelesaikan pembayaran. Struk akan diantar kasir setelah pembayaran diterima.
                    @else
                        Kasir kami akan segera membawakan mesin EDC ke meja Anda untuk proses pembayaran.
                    @endif
                </p>
                @if ($successQrisImage)
                    <div class="qris-qr-wrap">{!! $successQrisImage !!}</div>
                    <a href="data:image/svg+xml;base64,{{ base64_encode($successQrisImage) }}" download="qris-pembayaran.svg" class="qris-download-btn">
                        <i class="bi bi-download"></i> Download QRIS
                    </a>
                @endif
                <button type="button" wire:click="closePaymentModal" class="payment-submit-btn">Tutup</button>
            </div>
        @endif

    </div>
</div>
