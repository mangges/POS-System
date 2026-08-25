<div class="qris-preview-card">
    <div class="drafts-modal-header">
        <h3>{{ __('pos.QRIS Pembayaran') }}</h3>
        <button type="button" wire:click="closeQrisPreviewModal" class="qris-preview-shrink-btn">
            <i class="bi bi-fullscreen-exit"></i> {{ __('pos.Perkecil') }}
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
            <p class="qris-preview-hint">{{ __('pos.Scan QR di atas untuk membayar') }}</p>
        @else
            <p class="qris-preview-hint">{{ __('pos.QRIS dinamis belum tersedia') }}</p>
        @endif
    </div>
</div>
