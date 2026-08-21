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
                            x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $item['id'] }}')">
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
                x-on:dragover.prevent
                x-on:drop.prevent="$wire.assignUnitToGroup({{ $groupIndex }}, parseInt($event.dataTransfer.getData('text/plain')))">
                <div class="split-group-header">
                    <span>{{ $group['name'] }}</span>
                    <button type="button" wire:click="removeSplitGroup({{ $groupIndex }})" title="Hapus grup">&times;</button>
                </div>

                @forelse($group['assignments'] as $productId => $qty)
                    @php $product = collect($cart)->firstWhere('id', $productId); @endphp
                    @if($product)
                        <div class="split-group-item" wire:click="unassignUnitFromGroup({{ $groupIndex }}, {{ $productId }})" title="Klik untuk batalkan">
                            {{ $qty }}x {{ $product['name'] }}
                        </div>
                    @endif
                @empty
                    <p class="split-group-empty">Drag item ke sini</p>
                @endforelse

                <div class="split-group-subtotal">Rp {{ number_format($this->splitGroupSubtotal($groupIndex), 0, ',', '.') }}</div>
            </div>
        @endforeach

        <div class="split-add-group" x-data="{ name: '' }">
            <input type="text" x-model="name" placeholder="Nama orang" @keydown.enter="$wire.addSplitGroup(name); name = ''">
            <button type="button" @click="$wire.addSplitGroup(name); name = ''">+ Tambah Orang</button>
        </div>
    </div>
</div>
