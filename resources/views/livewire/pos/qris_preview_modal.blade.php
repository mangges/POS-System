<div class="qris-preview-card">
    <div class="drafts-modal-header">
        <h3>QRIS Pembayaran</h3>
        <button type="button" wire:click="closeQrisPreviewModal" class="qris-preview-shrink-btn">
            <i class="bi bi-fullscreen-exit"></i> Perkecil
        </button>
    </div>

    @php
        $previewImage = $previewQrisAmount !== null ? $this->qrisImageForOrderAmount($previewQrisAmount) : $this->qrisImage;
        $previewAmount = $previewQrisAmount ?? $this->total;
    @endphp

    <div class="qris-preview-body">
        @if($previewImage)
            <div class="qris-preview-image">{!! $previewImage !!}</div>
            <p class="qris-preview-amount">Rp {{ number_format($previewAmount, 0, ',', '.') }}</p>
            <p class="qris-preview-hint">Scan QR di atas untuk membayar.</p>
        @else
            <p class="qris-preview-hint">QRIS dinamis belum tersedia. Pilih metode QRIS dan pastikan mode Static &rarr; Dynamic aktif di Payment Method Settings.</p>
        @endif
    </div>
</div>
