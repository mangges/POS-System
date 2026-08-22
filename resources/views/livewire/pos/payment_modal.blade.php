<div class="payment-modal-card">
    @php
        $displaySubtotal = $activeSplitIndex !== null ? $this->splitGroupSubtotal($activeSplitIndex) : $this->subtotal;
        $displayTax = $activeSplitIndex !== null ? $this->splitGroupTax($activeSplitIndex) : $this->taxAmount;
        $displayTotal = $activeSplitIndex !== null ? $this->splitGroupTotal($activeSplitIndex) : $this->total;
        $displayName = $activeSplitIndex !== null ? $splitGroups[$activeSplitIndex]['name'] : $this->customerName;
        $displayChange = $this->cashReceived !== null ? max(0, $this->cashReceived - $displayTotal) : null;
    @endphp
    <div class="modal-left-content">
        @if(count($splitGroups) > 1)
            <div class="payment-split-tabs">
                @foreach($splitGroups as $index => $group)
                    <button type="button"
                        class="payment-split-tab {{ $activeSplitIndex === $index ? 'active' : '' }} {{ $group['paid'] ? 'is-paid' : '' }}"
                        wire:click="switchSplitTab({{ $index }})">
                        @if($group['paid'])<i class="bi bi-check-circle-fill"></i>@endif
                        {{ $group['name'] }}
                    </button>
                @endforeach
            </div>
        @endif
        <div class="modal-header">
            <h3 class="modal-title">Metode Pembayaran</h3>
            <button wire:click="closePaymentModal" class="close-mobile-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="payment-methods-grid">
            @if(in_array('cash', $this->activeMethods))
            <label class="method-option {{ $this->paymentMethod === 'cash' ? 'active' : '' }}">
                <input type="radio" name="payment_method" wire:model.live="paymentMethod" value="cash" class="hidden-radio">
                <i class="fas fa-money-bill-wave icon-cash"></i>
                <span class="method-label label-cash">Tunai</span>
            </label>
            @endif
            @if(in_array('qris', $this->activeMethods))
            <label class="method-option {{ $this->paymentMethod === 'qris' ? 'active' : '' }}">
                <input type="radio" name="payment_method" wire:model.live="paymentMethod" value="qris" class="hidden-radio">
                <i class="fas fa-qrcode icon-gray"></i>
                <span class="method-label label-gray">QRIS</span>
            </label>
            @endif
            @if(in_array('transfer', $this->activeMethods))
            <label class="method-option {{ $this->paymentMethod === 'transfer' ? 'active' : '' }}">
                <input type="radio" name="payment_method" wire:model.live="paymentMethod" value="transfer" class="hidden-radio">
                <i class="fas fa-credit-card icon-gray"></i>
                <span class="method-label label-gray">Debit/Transfer</span>
            </label>
            @endif
        </div>

        <div class="order-type-section">
            <h4 class="section-subtitle">Tipe Pesanan</h4>
            <div class="order-type-grid">
                <label class="method-option {{ $this->orderType === 'dine-in' ? 'active' : '' }}">
                    <input type="radio" name="order_type" wire:model.live="orderType" value="dine-in" class="hidden-radio">
                    <i class="bi bi-cash icon-gray"></i>
                    <span class="method-label label-cash">Dine-in</span>
                </label>
                <label class="method-option {{ $this->orderType === 'takeaway' ? 'active' : '' }}">
                    <input type="radio" name="order_type" wire:model.live="orderType" value="takeaway" class="hidden-radio">
                    <i class="bi bi-bag icon-gray"></i>
                    <span class="method-label label-gray">Takeaway</span>
                </label>
            </div>
        </div>

        @if($this->paymentMethod !== 'cash')
            @if($this->qrisImageForAmount($displayTotal))
                <div class="payment-notice">
                    <i class="bi bi-qr-code"></i>
                    <p>Minta pelanggan scan QR ini untuk membayar Rp {{ number_format($displayTotal) }}.</p>
                </div>
                <div class="qris-qr-wrap">{!! $this->qrisImageForAmount($displayTotal) !!}</div>
                <button type="button" wire:click="openQrisPreviewModal" class="btn-preview-qris">
                    <i class="bi bi-arrows-fullscreen"></i> Tampilkan QRIS ke Pelanggan
                </button>
            @else
                <div class="payment-notice">
                    <i class="bi bi-exclamation-circle"></i>
                    <p>Untuk metode pembayaran selain tunai, silakan selesaikan pembayaran melalui aplikasi terkait.</p>
                </div>
            @endif

            <label class="payment-confirm-checkbox">
                <input type="checkbox" wire:model.live="paymentConfirmed">
                <span>Konfirmasi pembayaran diterima.</span>
            </label>
        @endif

        @if($this->paymentMethod === 'cash')
        <div id="cashDenominations">
            <h4 class="section-subtitle">Uang Pecahan</h4>
            <div class="denominations-row-3">
                <button type="button" data-select-cash="100000" class="btn-denom long-denom">Rp 100.000</button>
                <button type="button" data-select-cash="50000" class="btn-denom long-denom">Rp 50.000</button>
                <button type="button" data-select-cash="20000" class="btn-denom long-denom">Rp 20.000</button>
            </div>
            <div class="denominations-row-4">
                <button type="button" data-select-cash="10000" class="btn-denom short-denom">Rp 10.000</button>
                <button type="button" data-select-cash="5000" class="btn-denom short-denom">Rp 5.000</button>
                <button type="button" data-select-cash="2000" class="btn-denom short-denom">Rp 2.000</button>
                <button type="button" data-select-cash="1000" class="btn-denom short-denom">Rp 1.000</button>
            </div>

            <div class="reset-denominations-wrapper">
                <button type="button" id="resetDenominations" class="btn-reset-denom">Reset</button>
            </div>
        </div>

        <div class="input-received-group">
            <label class="input-label">Uang Diterima (Cash Received)</label>
            <div class="input-wrapper">
                <x-currency-input
                    model="cashReceived"
                    id="cashReceived"
                    class="input-cash using-price-input"
                    placeholder="Rp 0"
                    autofocus
                />
            </div>
        </div>
        @endif
    </div>

    <div class="modal-right-content">
        <div class="summary-top-wrapper">
            <div class="close-desktop-wrapper">
                <button type="button" wire:click="closePaymentModal" class="close-desktop-btn">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            
            <h3 class="summary-title">Ringkasan Transaksi</h3>

            <div class="price-details">
                <div class="price-row text-secondary">
                    <span>Pelanggan</span>
                    <span id="summaryCustomer">{{ $displayName }}</span>
                </div>
                <div class="price-row text-secondary">
                    <span>Subtotal</span>
                    <span id="summarySubtotal">Rp {{ number_format($displaySubtotal) }}</span>
                </div>
                <div class="price-row text-secondary">
                    <span>Tax (11%)</span>
                    <span id="summaryTax">Rp {{ number_format($displayTax) }}</span>
                </div>
                <div class="price-row total-row">
                    <span>Total Tagihan</span>
                    <span id="summaryTotal" class="text-blue">Rp {{ number_format($displayTotal) }}</span>
                </div>
            </div>

            @php
                $isInsufficient = $this->cashReceived !== null && $this->cashReceived < $displayTotal;
                $isPaidSplitTab = $activeSplitIndex !== null && ($splitGroups[$activeSplitIndex]['paid'] ?? false);
                $canFinalize = ! $isPaidSplitTab && ($this->paymentMethod === 'cash'
                    ? (! $isInsufficient && $cashReceived !== null)
                    : $paymentConfirmed);
            @endphp

            <div class="@if($isInsufficient) change-box-danger @else change-box-success @endif">
                <div class="@if($isInsufficient) change-title-danger @else change-title-success @endif">Kembalian</div>

                @if($isInsufficient)
                    <p class="change-notice-danger">Jumlah uang diterima kurang.</p>
                @endif

                <div id="summaryChange" class="@if($isInsufficient) change-amount-danger @else change-amount-success @endif">
                    Rp {{ number_format($displayChange ?? 0) }}
                </div>
            </div>
        </div>

        <div class="submit-action-wrapper">
            <button type="button" wire:click="finalizeOrder" class="btn-submit-payment" @if(! $canFinalize) disabled @endif>
                <i class="fas fa-check-circle"></i>
                <span>Selesai & Cetak Struk</span>
            </button>
        </div>
    </div>
</div>