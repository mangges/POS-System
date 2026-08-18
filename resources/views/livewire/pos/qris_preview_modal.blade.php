<div class="qris-preview-card">
    <div class="drafts-modal-header">
        <h3>QRIS Pembayaran</h3>
        <button type="button" wire:click="closeQrisPreviewModal" class="qris-preview-shrink-btn">
            <i class="bi bi-fullscreen-exit"></i> Perkecil
        </button>
    </div>

    <div class="qris-preview-body">
        @if($this->qrisImage)
            <div class="qris-preview-image">{!! $this->qrisImage !!}</div>
            <p class="qris-preview-amount">Rp {{ number_format($this->total, 0, ',', '.') }}</p>
            <p class="qris-preview-hint">Scan QR di atas untuk membayar.</p>
        @else
            <p class="qris-preview-hint">QRIS dinamis belum tersedia. Pilih metode QRIS dan pastikan mode Static &rarr; Dynamic aktif di Payment Method Settings.</p>
        @endif
    </div>
</div>
