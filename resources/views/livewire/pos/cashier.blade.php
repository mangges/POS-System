<div class="pos-layout">
    <!-- Top Header -->
    <header class="pos-header">
        <div class="header-brand">
            <div class="brand-logo">
                <i class="bi bi-house-door-fill"></i>
            </div>
            <h1>POS F&B</h1>
        </div>

        <div class="header-user">
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
    </div>
</div>

<script>
    document.addEventListener('livewire:init', () => {
        window.Echo.channel('orders').listen('.OrderPlaced', () => {
            Livewire.dispatch('order-placed');
        });
    });
</script>
