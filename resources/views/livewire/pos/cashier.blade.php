<div class="pos-layout">
@if($activeShift)
    <!-- Top Header -->
    <header class="pos-header">
        <div class="header-brand">
            <div class="brand-logo">
                <i class="bi bi-house-door-fill"></i>
            </div>
            <h1>POS F&B</h1>
        </div>

        <div class="header-user">
            <div class="shift-badge">
                <span class="shift-badge-dot"></span>
                <span>Shift Aktif &middot; Rp {{ number_format($activeShift->opening_cash, 0, ',', '.') }} &middot; {{ $activeShift->opened_at->format('H:i') }}</span>
            </div>
            <button type="button" wire:click="openCashMovementModal" class="secondary-btn shift-header-btn">
                <i class="bi bi-cash-coin"></i>
                <span>Cash In/Out</span>
            </button>
            <div class="user-avatar">
                {{ $this->userInitials }}
            </div>
        </div>
    </header>

    <div class="pos-container">
        <!-- Left Main Area -->
        <div class="pos-main">
            <!-- Categories & Search -->
            <div class="main-controls">
                <div class="pos-categories">
                    <button wire:click="filterCategory(null)" class="category-btn {{ $selectedCategory === null ? 'active' : '' }}">Semua Menu</button>
                    @foreach($categories as $category)
                        <button wire:click="filterCategory({{ $category->id }})" class="category-btn {{ $selectedCategory === $category->id ? 'active' : '' }}">
                            {{ $category->name }}
                        </button>
                    @endforeach
                </div>
                
                <div class="product-search">
                    <div class="search-wrapper">
                        <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari menu produk..." class="search-input">
                        @if($search)
                            <button wire:click="$set('search', '')" class="search-clear">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Product Grid -->
            <div class="pos-products">
                @foreach($products as $product)
                    @php
                        $isOut = $product->is_out_of_stock || (!$product->has_recipe && $product->stock !== null && $product->stock <= 0);
                        
                        // Extract Initials
                        $words = explode(' ', $product->name);
                        $initials = '';
                        if (count($words) >= 2) {
                            $initials = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
                        } else {
                            $initials = strtoupper(substr($product->name, 0, 2));
                        }
                    @endphp
                    <div class="product-card {{ $isOut ? 'out-of-stock' : '' }}" wire:click="addToCart({{ $product->id }})">
                        <div class="product-initial">
                            {{ $initials }}
                        </div>
                        <div class="product-info">
                            <h3 class="product-name">{{ $product->name }}</h3>
                            <p class="product-price">Rp {{ number_format($product->price, 0, ',', '.') }}</p>
                        </div>
                        @if($isOut)
                            <div class="product-badge">Habis</div>
                        @endif
                    </div>
                @endforeach
                
                @if(count($products) === 0)
                    <div class="no-products">
                        <p>Menu tidak ditemukan.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Right Sidebar / Cart -->
        <div class="pos-sidebar">
            <div class="cart-header">
                <h2>Pesanan Saat Ini</h2>
                <div class="button-wrapper">
                    <button wire:click="openFromTableModal" class="cashier-header-button table-order-lists-button">
                        <i  class="bi bi-clipboard-fill"> </i>
                        @if(count($this->fromTableOrders) > 0)
                            <span class="header-button-badge">{{ count($this->fromTableOrders) }}</span>
                        @endif
                    </button>
                    <button wire:click="openKitchenOrdersModal" class="cashier-header-button kitchen-orders-button">
                        <i class="bi bi-fire"></i>
                        @php $kitchenCount = count($this->processingTableOrders) + count($this->readyTableOrders); @endphp
                        @if($kitchenCount > 0)
                            <span class="header-button-badge">{{ $kitchenCount }}</span>
                        @endif
                    </button>
                    <button wire:click="openDraftsModal" class="cashier-header-button draft-lists-button">
                        <i class="bi bi-archive"></i>
                        @if(count($this->draftOrders) > 0)
                            <span class="header-button-badge">{{ count($this->draftOrders) }}</span>
                        @endif
                    </button>
                </div>
            </div>
            
            <div class="cart-items">
                @if(count($cart) === 0)
                    <div class="empty-cart">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                        <p>Belum ada pesanan</p>
                    </div>
                @elseif($splitMode)
                    @include('livewire.pos.split_bill_panel')
                @else
                    @foreach($cart as $index => $item)
                        <div class="cart-item">
                            <div class="cart-item-top">
                                <span class="item-name">{{ $item['name'] }}</span>
                                <div class="cart-item-actions">
                                    <div class="qty-controls">
                                        <button class="qty-btn" wire:click="decrementQuantity({{ $index }})">-</button>
                                        <span class="qty-display">{{ $item['qty'] }}</span>
                                        <button class="qty-btn" wire:click="incrementQuantity({{ $index }})">+</button>
                                    </div>
                                    <button class="remove-btn" wire:click="removeFromCart({{ $index }})" title="Hapus">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </div>
                            <div class="cart-item-bottom">
                                <span class="item-price">Rp {{ number_format($item['price'], 0, ',', '.') }}</span>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            <div class="cart-summary">
                @unless($splitMode)
                <div class="summary-row">
                    <span>Nama Pelanggan</span>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="customerName"
                        placeholder="Masukkan nama pelanggan"
                        x-data
                        @input="$el.value = $el.value.replace(/[^a-zA-Z\s]/g, '')">
                </div>
                @endunless
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>Rp {{ number_format($this->subtotal, 0, ',', '.') }}</span>
                </div>
                <div class="summary-row">
                    <span>Pajak (11%)</span>
                    <span>Rp {{ number_format($this->taxAmount, 0, ',', '.') }}</span>
                </div>
                <div class="summary-row total-row">
                    <span>Total</span>
                    <span>Rp {{ number_format($this->total, 0, ',', '.') }}</span>
                </div>

                <div class="buttons-row">
                    <div class="buttons-row-secondary">
                        <button class="secondary-btn void-btn" wire:click="voidCart" @if(count($cart) === 0) disabled @endif>
                            <i class="bi bi-trash3"></i>
                            <span>Kosongkan</span>
                        </button>
                        <button class="secondary-btn split-toggle-btn" wire:click="toggleSplitMode" @if(count($cart) === 0) disabled @endif>
                            <i class="bi bi-people-fill"></i>
                            <span>{{ $splitMode ? 'Batal Split' : 'Split Bill' }}</span>
                        </button>
                        @unless($splitMode)
                        <button class="secondary-btn draft-btn" wire:click="saveDraft" @if(count($cart) === 0 || empty($customerName)) disabled @endif>
                            <i class="bi bi-save2"></i>
                            <span>Draft</span>
                        </button>
                        @endunless
                    </div>
                    @unless($splitMode)
                    <button class="checkout-btn" wire:click="checkout" @if(count($cart) === 0 || empty($customerName)) disabled @endif>
                        <i class="bi bi-credit-card-fill"></i>
                        <span>Proses Pembayaran</span>
                    </button>
                    @endunless
                </div>
            </div>

            <div class="drafts-modal-overlay @if(!$showDraftsModal) closed @endif">
                @include('livewire.pos.drafts_modal')
            </div>

            <div class="drafts-modal-overlay @if(!$showFromTableModal) closed @endif">
                @include('livewire.pos.from_table_modal')
            </div>

            <div class="drafts-modal-overlay @if(!$showKitchenOrdersModal) closed @endif">
                @include('livewire.pos.kitchen_orders_modal')
            </div>
        </div>

        <div class="payment-modal-overlay @if(!$showPaymentModal) closed @endif">
            @if($showPaymentModal)
                @include('livewire.pos.payment_modal')
            @endif
        </div>

        <div class="qris-preview-overlay @if(!$showQrisPreviewModal) closed @endif">
            @if($showQrisPreviewModal)
                @include('livewire.pos.qris_preview_modal')
            @endif
        </div>

        <div class="qris-preview-overlay @if(!$showCashMovementModal) closed @endif">
            @if($showCashMovementModal)
                @include('livewire.pos.cash_movement_modal')
            @endif
        </div>
    </div>
@else
    <div class="shift-gate">
        <div class="shift-gate-card">
            <div class="shift-gate-icon"><i class="bi bi-safe2-fill"></i></div>
            <h2>Buka Shift</h2>
            <p>Masukkan modal awal kas sebelum mulai melayani transaksi.</p>

            @error('shiftOpeningCash')
                <div class="shift-gate-error">{{ $message }}</div>
            @enderror

            <form wire:submit.prevent="openShift" class="shift-gate-form">
                <label for="shiftOpeningCash">Modal Awal (Rp)</label>
                <input type="number" step="0.01" min="0" id="shiftOpeningCash" wire:model="shiftOpeningCash" class="shift-gate-input" placeholder="0" autofocus>
                <button type="submit" class="checkout-btn shift-gate-submit">
                    <i class="bi bi-unlock-fill"></i>
                    <span>Buka Shift</span>
                </button>
            </form>
        </div>
    </div>
@endif
</div>

<script>
    document.addEventListener('livewire:init', () => {
        window.Echo.channel('orders').listen('.OrderPlaced', () => {
            Livewire.dispatch('order-placed');
        });
    });

    let splitDragProductId = null;
    let splitGhostEl = null;
    let splitOverlayEl = null;
    let splitCurrentDropTarget = null;
    let splitScrollParent = null;
    let splitAutoScrollSpeed = 0;
    let splitAutoScrollRAF = null;

    function splitGetOverlay() {
        if (!splitOverlayEl) {
            splitOverlayEl = document.createElement('div');
            splitOverlayEl.className = 'split-drag-overlay';
            document.body.appendChild(splitOverlayEl);
        }
        return splitOverlayEl;
    }

    // `transform: translate()` only, never left/top — left/top forces a layout
    // recalc on every touchmove, which is what made the ghost visibly lag
    // behind the finger. transform is compositor-only, no reflow.
    function splitPositionGhost(x, y) {
        if (!splitGhostEl) return;
        splitGhostEl.style.transform = `translate(${x}px, ${y}px) translate(-50%, -50%) scale(1.1)`;
    }

    function splitFindScrollParent(el) {
        let node = el.parentElement;
        while (node && node !== document.body) {
            const style = getComputedStyle(node);
            if ((style.overflowY === 'auto' || style.overflowY === 'scroll') && node.scrollHeight > node.clientHeight) {
                return node;
            }
            node = node.parentElement;
        }
        return null;
    }

    // With many split groups the ticket list overflows and scrolls — while a
    // finger is busy holding a chip there's no free finger to scroll toward a
    // ticket that's off-screen, so auto-scroll the list when the drag nears
    // its top/bottom edge, same idea as Trello-style drag-and-drop.
    function splitAutoScrollTick() {
        if (splitScrollParent && splitAutoScrollSpeed !== 0) {
            splitScrollParent.scrollTop += splitAutoScrollSpeed;
        }
        splitAutoScrollRAF = requestAnimationFrame(splitAutoScrollTick);
    }

    function splitUpdateAutoScroll(clientY) {
        if (!splitScrollParent) {
            splitAutoScrollSpeed = 0;
            return;
        }

        const rect = splitScrollParent.getBoundingClientRect();
        const edge = 60;
        const maxSpeed = 14;

        if (clientY < rect.top + edge) {
            splitAutoScrollSpeed = -maxSpeed * Math.min(1, (rect.top + edge - clientY) / edge);
        } else if (clientY > rect.bottom - edge) {
            splitAutoScrollSpeed = maxSpeed * Math.min(1, (clientY - (rect.bottom - edge)) / edge);
        } else {
            splitAutoScrollSpeed = 0;
        }
    }

    // Mouse-driven native HTML5 drag never touches the touch functions above,
    // so it needs its own hook into the same auto-scroll: dragstart primes the
    // scroll parent + RAF loop (mirrors touchstart), a document-wide dragover
    // listener feeds it the live cursor position (dragover doesn't fire on the
    // chip itself once the cursor leaves it), dragend tears both down.
    function splitDragStart(event) {
        splitScrollParent = splitFindScrollParent(event.currentTarget);
        splitAutoScrollSpeed = 0;
        cancelAnimationFrame(splitAutoScrollRAF);
        splitAutoScrollRAF = requestAnimationFrame(splitAutoScrollTick);
    }

    function splitDragEnd() {
        cancelAnimationFrame(splitAutoScrollRAF);
        splitAutoScrollSpeed = 0;
        splitScrollParent = null;
    }

    document.addEventListener('dragover', (event) => {
        if (splitScrollParent) splitUpdateAutoScroll(event.clientY);
    });

    function splitTouchStart(event, productId) {
        splitDragProductId = productId;
        const chip = event.currentTarget;
        const rect = chip.getBoundingClientRect();

        splitGhostEl = chip.cloneNode(true);
        splitGhostEl.classList.add('split-unit-chip-ghost');
        splitGhostEl.style.width = rect.width + 'px';
        splitGhostEl.style.left = '0px';
        splitGhostEl.style.top = '0px';
        document.body.appendChild(splitGhostEl);
        splitPositionGhost(rect.left + rect.width / 2, rect.top + rect.height / 2);

        chip.classList.add('split-unit-chip-lifted');
        splitGetOverlay().classList.add('is-visible');

        splitScrollParent = splitFindScrollParent(chip);
        splitAutoScrollSpeed = 0;
        cancelAnimationFrame(splitAutoScrollRAF);
        splitAutoScrollRAF = requestAnimationFrame(splitAutoScrollTick);
    }

    function splitTouchMove(event) {
        if (splitDragProductId === null) return;
        event.preventDefault();
        const touch = event.touches[0];

        splitPositionGhost(touch.clientX, touch.clientY);
        splitUpdateAutoScroll(touch.clientY);

        const target = document.elementFromPoint(touch.clientX, touch.clientY)?.closest('.split-group') || null;
        if (target !== splitCurrentDropTarget) {
            if (splitCurrentDropTarget) splitCurrentDropTarget.classList.remove('is-touch-drop-target');
            if (target) target.classList.add('is-touch-drop-target');
            splitCurrentDropTarget = target;
        }
    }

    function splitTouchEnd(event, wire) {
        if (splitDragProductId === null) return;

        cancelAnimationFrame(splitAutoScrollRAF);
        splitAutoScrollSpeed = 0;
        splitScrollParent = null;

        if (splitCurrentDropTarget) {
            wire.assignUnitToGroup(parseInt(splitCurrentDropTarget.dataset.groupIndex), splitDragProductId);
            splitCurrentDropTarget.classList.remove('is-touch-drop-target');
            splitCurrentDropTarget = null;
        }

        if (splitGhostEl) {
            splitGhostEl.remove();
            splitGhostEl = null;
        }
        document.querySelectorAll('.split-unit-chip-lifted').forEach(el => el.classList.remove('split-unit-chip-lifted'));
        if (splitOverlayEl) splitOverlayEl.classList.remove('is-visible');

        splitDragProductId = null;
    }
</script>
