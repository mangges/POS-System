<div class="thermal-receipt" id="printable-receipt">
    <div class="receipt-header">
        @if($this->receiptSettings->logo_path)
        <img src="{{ Storage::disk('public')->url($this->receiptSettings->logo_path) }}" alt="Logo" style="max-width: 80px; margin: 0 auto 8px; filter: grayscale(1) contrast(1.2);">
        @endif
        <h2>{{ $this->receiptSettings->store_name }}</h2>
        @if($this->receiptSettings->address)
        <p>{{ $this->receiptSettings->address }}</p>
        @endif
        @if($this->receiptSettings->phone)
        <p>Telp: {{ $this->receiptSettings->phone }}</p>
        @endif
        @if($this->receiptSettings->website)
        <p>{{ $this->receiptSettings->website }}</p>
        @endif
        <p>{{ $this->order->created_at->format('d/m/Y') }}</p>
    </div>

    <div class="receipt-divider"></div>

    <div class="receipt-row">
        <span>No: #{{ $this->order->order_number }}</span>
    </div>
    <div class="receipt-row">
        <span>Kasir: </span>
        <span>{{ $this->order->cashier_name ?? 'Admin' }}</span>
    </div>
    <div class="receipt-row">
        <span>Pelanggan: </span>
        <span>{{ $this->order->customer_name ?? '-' }}</span>
    </div>

    <div class="receipt-divider"></div>

    <div class="receipt-items">
        @foreach($this->orderItems as $item)
        <div class="receipt-item">
            <div class="receipt-item-details">
                <span class="receipt-item-name">{{ $item->product->name }}</span>
                <span class="receipt-item-qty">{{ $item->quantity }} x {{ number_format($item->price, 0, ',', '.') }}</span>
            </div>
            <span>{{ number_format($item->quantity * $item->price, 0, ',', '.') }}</span>
        </div>
        @endforeach
    </div>

    <div class="receipt-divider"></div>

    <div class="receipt-total-section">
        <div class="receipt-row">
            <span>Subtotal</span>
            <span>{{ number_format($this->subtotal, 0, ',', '.') }}</span>
        </div>
        <div class="receipt-row">
            <span>PPN ({{ $this->subtotal > 0 ? round($this->order->tax / $this->subtotal * 100) : 0 }}%)</span>
            <span>{{ number_format($this->order->tax, 0, ',', '.') }}</span>
        </div>
        <div class="receipt-row grand-total">
            <span>TOTAL</span>
            <span>Rp {{ number_format($this->order->total_amount, 0, ',', '.') }}</span>
        </div>
        <div class="receipt-row" style="margin-top: 8px;">
            <span>Tunai</span>
            <span>{{ number_format($this->payment->amount, 0, ',', '.') }}</span>
        </div>
        <div class="receipt-row">
            <span>Kembali</span>
            <span>{{ number_format($this->change, 0, ',', '.') }}</span>
        </div>
    </div>

    @if($this->receiptSettings->show_qr)
    <div class="receipt-divider"></div>

    <div class="receipt-qr">
        {!! QrCode::size(150)->generate(route('receipt.show', $this->order->token)) !!}
        <p>Scan untuk e-receipt</p>
    </div>
    @endif

    @if($this->receiptSettings->footer_text)
    <div class="receipt-divider"></div>

    <div class="receipt-footer">
        @foreach(explode("\n", $this->receiptSettings->footer_text) as $line)
        <p>{{ trim($line) }}</p>
        @endforeach
    </div>
    @endif
</div>
