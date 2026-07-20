<div class="payment-modal-card">
    <div class="modal-left-content">
        <div class="modal-header">
            <h3 class="modal-title">Metode Pembayaran</h3>
            <button wire:click="closePaymentModal" class="close-mobile-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="payment-methods-grid">
            <label class="method-option {{ $this->paymentMethod === 'cash' ? 'active' : '' }}">
                <input type="radio" name="payment_method" wire:model.live="paymentMethod" value="cash" checked class="hidden-radio">
                <i class="fas fa-money-bill-wave icon-cash"></i>
                <span class="method-label label-cash">Tunai</span>
            </label>
            <label class="method-option {{ $this->paymentMethod === 'qris' ? 'active' : '' }}">
                <input type="radio" name="payment_method" wire:model.live="paymentMethod" value="qris" class="hidden-radio">
                <i class="fas fa-qrcode icon-gray"></i>
                <span class="method-label label-gray">QRIS</span>
            </label>
            <label class="method-option {{ $this->paymentMethod === 'transfer' ? 'active' : '' }}">
                <input type="radio" name="payment_method" wire:model.live="paymentMethod" value="transfer" class="hidden-radio">
                <i class="fas fa-credit-card icon-gray"></i>
                <span class="method-label label-gray">Debit/Transfer</span>
            </label>
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
            <div class="payment-notice">
                <i class="bi bi-exclamation-circle"></i>
                <p>Untuk metode pembayaran selain tunai, silakan selesaikan pembayaran melalui aplikasi terkait.</p>
            </div>
        @endif

        <div id="cashDenominations" class="{{ $this->paymentMethod !== 'cash' ? 'section-disabled' : '' }}">
            <h4 class="section-subtitle">Uang Pecahan</h4>
            <div class="denominations-row-3">
                <button type="button" data-select-cash="100000" class="btn-denom long-denom" @if($this->paymentMethod !== 'cash') disabled @endif>Rp 100.000</button>
                <button type="button" data-select-cash="50000" class="btn-denom long-denom" @if($this->paymentMethod !== 'cash') disabled @endif>Rp 50.000</button>
                <button type="button" data-select-cash="20000" class="btn-denom long-denom" @if($this->paymentMethod !== 'cash') disabled @endif>Rp 20.000</button>
            </div>
            <div class="denominations-row-4">
                <button type="button" data-select-cash="10000" class="btn-denom short-denom" @if($this->paymentMethod !== 'cash') disabled @endif>Rp 10.000</button>
                <button type="button" data-select-cash="5000" class="btn-denom short-denom" @if($this->paymentMethod !== 'cash') disabled @endif>Rp 5.000</button>
                <button type="button" data-select-cash="2000" class="btn-denom short-denom" @if($this->paymentMethod !== 'cash') disabled @endif>Rp 2.000</button>
                <button type="button" data-select-cash="1000" class="btn-denom short-denom" @if($this->paymentMethod !== 'cash') disabled @endif>Rp 1.000</button>
            </div>

            <div class="reset-denominations-wrapper">
                <button type="button" id="resetDenominations" class="btn-reset-denom" @if($this->paymentMethod !== 'cash') disabled @endif>Reset</button>
            </div>
        </div>

        <div class="input-received-group {{ $this->paymentMethod !== 'cash' ? 'section-disabled' : '' }}">
            <label class="input-label">Uang Diterima (Cash Received)</label>
            <div class="input-wrapper">
                <div class="input-prefix">
                    <span>Rp</span>
                </div>
                <input 
                    type="text" 
                    inputmode="numeric" 
                    id="cashReceived" 
                    class="input-cash using-price-input" 
                    placeholder="0"
                    @if($this->paymentMethod !== 'cash') disabled @endif
                    x-on:input="$wire.set('cashReceived', unformatPrice($event.target.value))">
                <input type="hidden" id="cashReceived_raw" wire:model="cashReceived">
            </div>
        </div>
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
                    <span id="summaryCustomer">{{ $this->customerName }}</span>
                </div>
                <div class="price-row text-secondary">
                    <span>Subtotal</span>
                    <span id="summarySubtotal">Rp {{ number_format($this->subtotal) }}</span>
                </div>
                <div class="price-row text-secondary">
                    <span>Tax (11%)</span>
                    <span id="summaryTax">Rp {{ number_format($this->taxAmount) }}</span>
                </div>
                <div class="price-row total-row">
                    <span>Total Tagihan</span>
                    <span id="summaryTotal" class="text-blue">Rp {{ number_format($this->total) }}</span>
                </div>
            </div>

            @php
                $isInsufficient = $this->cashReceived !== null && $this->cashReceived < $this->total;
            @endphp

            <div class="@if($isInsufficient) change-box-danger @else change-box-success @endif">
                <div class="@if($isInsufficient) change-title-danger @else change-title-success @endif">Kembalian</div>

                @if($isInsufficient)
                    <p class="change-notice-danger">Jumlah uang diterima kurang.</p>
                @endif

                <div id="summaryChange" class="@if($isInsufficient) change-amount-danger @else change-amount-success @endif">
                    Rp {{ number_format($this->change ?? 0) }}
                </div>
            </div>
        </div>

        <div class="submit-action-wrapper">
            <button type="button" wire:click="finalizeOrder" class="btn-submit-payment">
                <i class="fas fa-check-circle"></i>
                <span>Selesai & Cetak Struk</span>
            </button>
        </div>
    </div>
</div>