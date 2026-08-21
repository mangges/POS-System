<div class="split-bill-panel">
    <div class="split-bill-pool">
        <h4>Belum Dibagi</h4>
        @php $poolItems = collect($cart)->filter(fn($item) => $this->unassignedQty($item['id']) > 0); @endphp
        @forelse($poolItems as $item)
            <div class="split-pool-row">
                <span class="split-pool-name">{{ $item['name'] }}</span>
                <div class="split-pool-chips">
                    @for($i = 0; $i < $this->unassignedQty($item['id']); $i++)
                        <span class="split-unit-chip"
                            draggable="true"
                            x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $item['id'] }}'); splitDragStart($event)"
                            x-on:dragend="splitDragEnd()"
                            x-on:touchstart="splitTouchStart($event, {{ $item['id'] }})"
                            x-on:touchmove="splitTouchMove($event)"
                            x-on:touchend="splitTouchEnd($event, $wire)">
                            <i class="bi bi-grip-vertical"></i>
                            Rp {{ number_format($item['price'], 0, ',', '.') }}
                        </span>
                    @endfor
                </div>
            </div>
        @empty
            <p class="split-pool-empty">Semua item sudah dibagi.</p>
        @endforelse
    </div>

    <div class="split-bill-groups">
        @foreach($splitGroups as $groupIndex => $group)
            <div class="split-group"
                data-group-index="{{ $groupIndex }}"
                x-data="{ over: false }"
                x-on:dragover.prevent="over = true"
                x-on:dragleave="over = false"
                x-on:drop.prevent="over = false; $wire.assignUnitToGroup({{ $groupIndex }}, parseInt($event.dataTransfer.getData('text/plain')))"
                :class="{ 'is-drop-target': over }">
                <div class="split-group-header">
                    <span class="split-group-name">{{ $group['name'] }}</span>
                    <button type="button" class="split-group-remove-btn" wire:click="removeSplitGroup({{ $groupIndex }})" title="Hapus grup">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                @forelse($group['assignments'] as $productId => $qty)
                    @php $product = collect($cart)->firstWhere('id', $productId); @endphp
                    @if($product)
                        <div class="split-group-item">
                            <span class="split-group-item-name">{{ $qty }}x {{ $product['name'] }}</span>
                            <span class="split-group-item-price">Rp {{ number_format($product['price'] * $qty, 0, ',', '.') }}</span>
                            <button type="button" class="split-item-remove-btn" wire:click="unassignUnitFromGroup({{ $groupIndex }}, {{ $productId }})" title="Batalkan">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                            </button>
                        </div>
                    @endif
                @empty
                    <p class="split-group-empty">Drag item ke sini</p>
                @endforelse

                <div class="split-group-subtotal">
                    <span>TOTAL</span>
                    <span>Rp {{ number_format($this->splitGroupSubtotal($groupIndex), 0, ',', '.') }}</span>
                </div>
            </div>
        @endforeach

        <div class="split-add-group" x-data="{ name: '' }">
            <input type="text" x-model="name" x-ref="splitNameInput" placeholder="Nama orang"
                @keydown.enter="name.trim() ? ($wire.addSplitGroup(name), name = '') : $refs.splitNameInput.focus()">
            <button type="button" class="split-add-btn"
                @click="name.trim() ? ($wire.addSplitGroup(name), name = '') : $refs.splitNameInput.focus()">
                <i class="bi bi-person-plus-fill"></i>
                <span>Tambah</span>
            </button>
        </div>
    </div>

    <button type="button" class="checkout-btn split-checkout-btn" wire:click="checkoutSplit" @if(! $this->canCheckoutSplit()) disabled @endif>
        <i class="bi bi-credit-card-fill"></i>
        <span>Checkout Split ({{ count($splitGroups) }} pesanan)</span>
    </button>
</div>
