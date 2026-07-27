<div class="drafts-modal">
    <div class="drafts-modal-header">
        <h3>Daftar Draft</h3>
        <button type="button" wire:click="closeDraftsModal" class="close-desktop-btn">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div class="drafts-list">
        @if(count($this->draftOrders) === 0)
            <p class="no-drafts-message">Tidak ada draft yang tersedia.</p>
        @else
            @foreach($this->draftOrders as $draft)
                <div class="draft-item" wire:click="loadDraft({{ $draft->id }})">
                    <div class="draft-info">
                        <div class="draft-title">
                            <span class="draft-id">#Draft {{ $draft->id }}</span>
                            <span class="draft-customer">{{ $draft->customer_name ?? 'Pelanggan Tidak Diketahui' }}</span>
                        </div>
                        <span class="draft-total">Rp {{ number_format($draft->total_amount, 0, ',', '.') }}</span>
                    </div>
                    <div class="draft-actions">
                        <button wire:click.stop="deleteDraft({{ $draft->id }})" title="Hapus Draft">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>