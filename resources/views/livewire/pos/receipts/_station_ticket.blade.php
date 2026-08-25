<div class="thermal-receipt">
    <div class="receipt-header">
        <h2>{{ $stationLabel }}</h2>
        <p>{{ $this->order->created_at->format('d/m/Y H:i') }}</p>
    </div>

    <div class="receipt-divider"></div>

    <div class="receipt-row">
        <span>No: #{{ $this->order->order_number }}</span>
    </div>
    <div class="receipt-row">
        <span>Meja: </span>
        <span>{{ $this->order->table->name ?? $this->order->customer_name ?? '-' }}</span>
    </div>

    <div class="receipt-divider"></div>

    <div class="receipt-items">
        @foreach($items as $item)
        <div class="receipt-item">
            <div class="receipt-item-details">
                <span class="receipt-item-name">{{ $item->product->name }}</span>
                @if($item->notes)
                <span class="receipt-item-qty">{{ $item->notes }}</span>
                @endif
            </div>
            <span>{{ $item->quantity }}x</span>
        </div>
        @endforeach
    </div>
</div>
