<div class="receipt-container">
    <div class="receipt-preview-wrapper">
        <div class="receipt-scroll-area">
            <div class="thermal-receipt" id="printable-receipt">
                <div class="receipt-header">
                    <h2>Pos cafee</h2>
                    <p>Jl. Raya Tanah lot No. 0, Bali</p>
                    <p>Telp: 0812-3456-***</p>
                    <p>www.poscafee.com</p>
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
                        <span>PPN (11%)</span>
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
    
                <div class="receipt-divider"></div>
    
                <div class="receipt-qr">
                    {!! QrCode::size(150)->generate(route('receipt.show', $this->order->token)) !!}
                    <p>Scan untuk e-receipt</p>
                </div>
                
                <div class="receipt-footer">
                    <p>Terima kasih atas kunjungan Anda!</p>
                    <p>Barang yang sudah dibeli tidak dapat ditukar/dikembalikan.</p>
                </div>
            </div>
        </div>
    </div>

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
                    <span style="color: {{ $this->payment->status->color() }}; font-weight: 600;">{{ $this->payment->status->label() ?? '-' }}</span>
                </div>
                <div class="summary-item">
                    <label>Pelanggan</label>
                    <span>{{ $this->order->customer_name ?? '-' }}</span>
                </div>
            </div>
        </div>

        <div class="panel-card">
            <div class="action-grid">
                <button class="btn btn-primary" onclick="window.print()">
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

                <a href="{{ route('cashier.index') }}" wire:navigate class="btn btn-draft">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                    Simpan dan Kembali
                </a>
            </div>
        </div>
    </div>
</div>