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
                @unless($splitMode)
                <div class="form-field">
                    <label for="customerName" class="form-label">Nama Anda</label>
                    <input type="text" id="customerName" wire:model.live.debounce.300ms="customerName" placeholder="Masukkan nama" class="form-input" autocomplete="off">
                </div>
                @else
                <div class="form-field">
                    <span class="form-label">Pesanan akan dipecah jadi:</span>
                    @foreach($splitGroups as $group)
                        <div class="order-summary-row">
                            <span>{{ $group['name'] }}</span>
                            <span>Rp {{ number_format($this->splitGroupSubtotal($loop->index), 0, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>
                @endunless

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

                @if($splitMode)
                    <p class="payment-notice">
                        @if ($paymentMethod === 'qris')
                            Tiap orang akan dapat kode QR sendiri sesuai jumlahnya setelah pesanan dikonfirmasi.
                        @elseif ($paymentMethod === 'cash')
                            Kasir kami akan membawakan bills ke meja Anda untuk pembayaran.
                        @else
                            Kasir kami akan membawakan mesin EDC ke meja Anda untuk pembayaran.
                        @endif
                    </p>
                @else
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
                @endif
            </div>

            <div class="payment-modal-footer">
                @unless($splitMode)
                <button type="button" wire:click="checkout" class="payment-submit-btn" @if (empty($customerName)) disabled @endif>
                    Konfirmasi &amp; Pesan
                </button>
                @else
                <button type="button" wire:click="checkoutSplit" class="payment-submit-btn" @if (! $this->canCheckoutSplit()) disabled @endif>
                    Konfirmasi &amp; Pesan Semua
                </button>
                @endunless
            </div>
        @else
            @php
                $successQrisImage = $paymentMethod === 'qris' ? $this->qrisImageForAmount($lastOrderTotal) : null;
            @endphp
            <div class="payment-success" x-data="{ showQr: false }">
                <div class="payment-success-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h2 class="payment-success-title">Pesanan Diterima</h2>

                @if(empty($splitOrderDetails))
                    <p class="payment-success-order">{{ $lastOrderNumber }}</p>
                    <p class="payment-success-message">
                        @if ($paymentMethod === 'cash')
                            Kasir kami akan segera membawakan struk pembayaran ke meja Anda.
                        @elseif ($paymentMethod === 'qris')
                            Silakan selesaikan pembayaran QRIS Anda. Kasir akan memverifikasi pembayaran sebelum pesanan diproses.
                        @else
                            Kasir kami akan segera membawakan mesin EDC ke meja Anda untuk proses pembayaran.
                        @endif
                    </p>
                    @if ($successQrisImage)
                        <button type="button" x-show="!showQr" x-on:click="showQr = true" class="qris-download-btn">
                            <i class="bi bi-qr-code"></i> Lihat QRIS Lagi
                        </button>
                        <template x-if="showQr">
                            <div>
                                <div class="qris-qr-wrap">{!! $successQrisImage !!}</div>
                                <a href="data:image/svg+xml;base64,{{ base64_encode($successQrisImage) }}" download="qris-pembayaran.svg" class="qris-download-btn">
                                    <i class="bi bi-download"></i> Download QRIS
                                </a>
                            </div>
                        </template>
                    @endif
                @elseif ($paymentMethod === 'qris' && count($splitOrderDetails) > 1)
                    <p class="payment-success-message">
                        Tiap orang bayar sendiri sesuai jumlahnya. Ketuk nama untuk lihat kode QR masing-masing.
                    </p>
                    <div class="payment-split-tabs">
                        @foreach($splitOrderDetails as $index => $detail)
                            <button type="button"
                                class="payment-split-tab {{ $activeSuccessTabIndex === $index ? 'active' : '' }}"
                                wire:click="switchSuccessTab({{ $index }})">
                                {{ $detail['name'] }}
                            </button>
                        @endforeach
                    </div>
                    @php $activeDetail = $splitOrderDetails[$activeSuccessTabIndex] ?? $splitOrderDetails[0]; @endphp
                    @php $activeQrisImage = $this->qrisImageForAmount($activeDetail['total']); @endphp
                    <div class="payment-success-qris-card">
                        <p class="payment-success-qris-name">{{ $activeDetail['name'] }}</p>
                        <p class="payment-success-qris-amount">Rp {{ number_format($activeDetail['total'], 0, ',', '.') }}</p>
                        <p class="payment-success-order">{{ $activeDetail['order_number'] }}</p>
                        <div class="qris-qr-wrap">{!! $activeQrisImage !!}</div>
                        <a href="data:image/svg+xml;base64,{{ base64_encode($activeQrisImage) }}" download="qris-pembayaran-{{ $activeDetail['name'] }}.svg" class="qris-download-btn">
                            <i class="bi bi-download"></i> Download QRIS
                        </a>
                    </div>
                    <p class="payment-success-message payment-success-message-spaced">
                        Kasir akan memverifikasi tiap pembayaran sebelum pesanan diproses.
                    </p>
                @else
                    <div class="payment-success-split-list">
                        @foreach($splitOrderDetails as $detail)
                            <div class="payment-success-split-row">
                                <span class="split-row-name">{{ $detail['name'] }}</span>
                                <span class="split-row-code">{{ $detail['order_number'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    <p class="payment-success-message">
                        @if ($paymentMethod === 'cash')
                            Kasir kami akan segera membawakan struk pembayaran ke meja Anda.
                        @else
                            Kasir kami akan segera membawakan mesin EDC ke meja Anda untuk proses pembayaran.
                        @endif
                    </p>
                @endif

                <button type="button" wire:click="closePaymentModal" class="payment-submit-btn">Tutup</button>
            </div>
        @endif

    </div>
</div>
