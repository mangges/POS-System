@if ($this->orderId)
<div class="receipt-container">
    <div class="receipt-preview-wrapper">
        <div class="receipt-scroll-area" id="ticket-cashier">
            @include('livewire.pos.receipts._thermal')
        </div>
    </div>

    @if($this->barItems->isNotEmpty())
        <div id="ticket-bar" style="display:none">
            @include('livewire.pos.receipts._station_ticket', ['items' => $this->barItems, 'stationLabel' => 'BAR'])
        </div>
    @endif

    @if($this->kitchenItems->isNotEmpty())
        <div id="ticket-kitchen" style="display:none">
            @include('livewire.pos.receipts._station_ticket', ['items' => $this->kitchenItems, 'stationLabel' => 'DAPUR'])
        </div>
    @endif

    <div class="receipt-sidebar">
        <div class="panel-card">
            <div class="panel-header">Ringkasan Transaksi</div>
            <div class="summary-grid">
                <div class="summary-item">
                    <label>Total Belanja</label>
                    <span>Rp {{ number_format($this->order->total_amount, 0, ',', '.') }}</span>
                </div>
                <div class="summary-item">
                    <label>Metode Pembayaran</label>
                    <span>{{ ucfirst($this->payment->payment_method ?? '-') }}</span>
                </div>
                <div class="summary-item">
                    <label>Status</label>
                    <span style="color: {{ match($this->payment->status->getColor()) { 'success' => 'var(--color-success-text)', 'warning' => 'var(--color-warning-text)', 'danger' => 'var(--color-danger-text)', default => 'var(--color-text-muted)' } }}; font-weight: 600;">{{ $this->payment->status->getLabel() ?? '-' }}</span>
                </div>
                <div class="summary-item">
                    <label>Pelanggan</label>
                    <span>{{ $this->order->customer_name ?? '-' }}</span>
                </div>
            </div>
        </div>

        <div class="panel-card">
            <div class="action-grid">
                <button class="btn btn-primary" onclick="printAllReceipts()">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Cetak Struk
                </button>
{{--
                <button class="btn btn-success">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                    Kirim WA
                </button>

                <button class="btn btn-outline">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    Download PDF
                </button> --}}

                <a href="{{ route('filament.admin.pages.cashier') }}" wire:navigate class="btn btn-draft">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                    Simpan dan Kembali
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    function printAllReceipts() {
        const ids = ['ticket-cashier', 'ticket-bar', 'ticket-kitchen']
            .filter(id => document.getElementById(id) !== null);
        let i = 0;

        function showOnly(activeId) {
            ids.forEach(id => {
                document.getElementById(id).style.display = id === activeId ? 'block' : 'none';
            });
        }

        function afterPrint() {
            i++;
            if (i < ids.length) {
                showOnly(ids[i]);
                window.print();
            } else {
                showOnly('ticket-cashier');
                window.removeEventListener('afterprint', afterPrint);
            }
        }

        window.addEventListener('afterprint', afterPrint);
        showOnly(ids[0]);
        window.print();
    }
</script>
@else
    <div class="receipt-solo">
        @include('livewire.pos.receipts._thermal')

        @if ($this->order->table?->qr_token)
            <a href="{{ route('order', $this->order->table->qr_token) }}" wire:navigate class="btn btn-draft receipt-exit-btn">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                Keluar
            </a>
        @endif
    </div>
@endif
