<div class="drafts-modal">
    <div class="drafts-modal-header">
        <h3>{{ __('pos.Daftar Draft') }}</h3>
        <button type="button" wire:click="closeDraftsModal" class="close-desktop-btn">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div class="drafts-list">
        @if(count($this->draftOrders) === 0)
            <p class="no-drafts-message"></p>
        @else
            @foreach($this->draftOrders as $draft)
                <div class="draft-item" wire:click="loadDraft({{ $draft->id }})" x-data="{ open: false }">
                    <div class="draft-item-row">
                        <div class="draft-info">
                            <div class="draft-title">
                                <span class="draft-id">{{ __('pos.Draft') }} {{ $draft->id }}</span>
                                <span class="draft-customer">{{ $draft->customer_name ?? __('pos.Pelanggan Tidak Diketahui') }}</span>
                            </div>
                            <span class="draft-total">Rp {{ number_format($draft->total_amount, 0, ',', '.') }}</span>
                        </div>
                        <div class="draft-actions">
                            <button wire:click.stop="closeDraftsModal(); finalizeTableOrder({{ $draft->id }})" title="{{ __('pos.Bayar') }}">
                                <i class="bi bi-credit-card"></i>
                            </button>
                            <button wire:click.stop="deleteDraft({{ $draft->id }})" title="{{ __('pos.Hapus Draft') }}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>

                    <button type="button" class="draft-items-toggle" @click.stop="open = ! open">
                        <i class="bi" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                        {{ $draft->items->count() }} {{ __('pos.item') }}
                    </button>

                    <div class="draft-items-preview" :class="{ 'is-open': open }" @click.stop>
                        <div class="draft-items-preview-inner">
                            @foreach ($draft->items as $item)
                                <div class="draft-items-preview-row">
                                    <span class="draft-items-preview-name">{{ $item->quantity }}x {{ $item->product->name ?? __('pos.Produk dihapus') }}</span>
                                    <span>Rp {{ number_format($item->subtotal, 0, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>