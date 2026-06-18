<div class="landing-page" x-data="{ cartOpen: false, scrolled: false }" @scroll.window="scrolled = (window.pageYOffset > 40)">
    @vite('resources/css/landing-page.css')

    <!-- Navbar -->
    <nav :class="scrolled ? 'navbar' : 'nav-bar'">
        <a href="#" class="brand-text">SavorPOS</a>
        <button @click="cartOpen = true" class="cart-toggle-btn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            @if(count($cart) > 0)
                <span class="cart-badge">{{ count($cart) }}</span>
            @endif
        </button>
    </nav>

    <!-- Fixed Table Number Banner -->
    @if($table)
    <div class="table-banner">
        Memesan untuk Meja: <span>{{ $table }}</span>
    </div>
    @endif

    <div class="main-container">
        
        <!-- Hero -->
        <div class="hero">
            <h1 class="hero-title">Eksplorasi Rasa Sempurna</h1>
            <p class="hero-subtitle">Temukan menu favoritmu, pesan langsung dari meja, dan nikmati hidangan dengan elegan tanpa perlu mengantri.</p>
        </div>

        <!-- Search & Filter -->
        <div class="search-filter-section">
            <div class="search-container">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari menu favorit..." class="search-input">
                <svg xmlns="http://www.w3.org/2000/svg" class="search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            
            <div class="category-chips">
                <button wire:click="setCategory(null)" class="chip-btn {{ $selectedCategory === null ? 'active' : '' }}">Semua</button>
                @foreach($categories as $category)
                    <button wire:click="setCategory({{ $category->id }})" class="chip-btn {{ $selectedCategory == $category->id ? 'active' : '' }}">
                        {{ $category->name }}
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Product Grid -->
        @if($products->isEmpty())
            <div class="empty-state">
                <p>Menu tidak ditemukan. Silakan coba pencarian lain.</p>
            </div>
        @else
            <div class="product-grid">
                @foreach($products as $product)
                    <div class="product-card" wire:click="selectProduct({{ $product->id }})">
                        <div class="product-img-wrapper">
                            @if($product->image)
                                <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}" class="product-img">
                            @else
                                <div class="product-placeholder">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            @endif
                            @if($product->is_out_of_stock)
                                <div class="out-of-stock-overlay">
                                    <span class="out-of-stock-badge">Habis</span>
                                </div>
                            @endif
                        </div>
                        <div class="product-content">
                            <h3 class="product-title">{{ $product->name }}</h3>
                            <p class="product-desc">{{ $product->description }}</p>
                            <div class="product-footer">
                                <span class="product-price">Rp {{ number_format($product->price, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- FAQ Section -->
        <div class="faq-section">
            <h2 class="section-title">Pertanyaan Umum</h2>
            
            <div x-data="{ expanded: false }" class="faq-item">
                <button @click="expanded = !expanded" class="faq-question" :class="expanded ? 'expanded' : ''">
                    <span>Bagaimana cara memesan?</span>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
                <div x-show="expanded" x-collapse style="display: none;">
                    <div class="faq-answer">
                        Pilih menu yang Anda inginkan dari daftar di atas, klik tombol tambah, lalu buka keranjang di pojok kanan atas untuk menyelesaikan pesanan Anda.
                    </div>
                </div>
            </div>

            <div x-data="{ expanded: false }" class="faq-item">
                <button @click="expanded = !expanded" class="faq-question" :class="expanded ? 'expanded' : ''">
                    <span>Metode pembayaran apa saja yang didukung?</span>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
                <div x-show="expanded" x-collapse style="display: none;">
                    <div class="faq-answer">
                        Kami mendukung pembayaran non-tunai melalui QRIS, Transfer Bank, maupun pembayaran Tunai di meja Kasir.
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Section -->
        <div class="profile-section">
            <h2 class="section-title">Tentang SavorPOS</h2>
            <div class="profile-card">
                <div class="profile-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M21 21v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7h18zm-3-9v-2a2 2 0 00-2-2H8a2 2 0 00-2 2v2h12z" />
                    </svg>
                </div>
                <h3>Elegansi dalam Setiap Pesanan</h3>
                <p>SavorPOS menghadirkan pengalaman bersantap yang modern. Pesan langsung dari meja Anda, nikmati waktu berkualitas Anda, dan biarkan kami yang mengurus sisanya dengan cepat dan tepat.</p>
            </div>
        </div>

    </div>

    <!-- Footer Section -->
    <footer class="site-footer">
        <div class="footer-content">
            <div class="footer-profile">
                <h3 class="footer-brand">SavorPOS</h3>
                <p class="footer-desc">Menghadirkan pengalaman bersantap yang elegan, modern, dan tanpa hambatan. SavorPOS adalah solusi point of sale terpercaya untuk bisnis kuliner Anda.</p>
            </div>
            <div class="footer-links">
                <h4 class="footer-heading">Sosial Media</h4>
                <div class="social-icons">
                    <a href="#" class="social-link" aria-label="Instagram">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                    </a>
                    <a href="#" class="social-link" aria-label="Facebook">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>
                    </a>
                    <a href="#" class="social-link" aria-label="Twitter">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"></path></svg>
                    </a>
                </div>
            </div>
            <div class="footer-address">
                <h4 class="footer-heading">Alamat</h4>
                <p>Jl. Jendral Sudirman No. 123<br>Kawasan SCBD, Jakarta Selatan<br>DKI Jakarta, 12190<br>Indonesia</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; {{ date('Y') }} SavorPOS. Hak Cipta Dilindungi.</p>
        </div>
    </footer>

    <!-- Cart Slide-over Menu -->
    <div x-show="cartOpen" class="cart-overlay" style="display: none;">
        <!-- Backdrop -->
        <div class="cart-overlay" @click="cartOpen = false" x-show="cartOpen" x-transition.opacity></div>

        <div class="cart-panel" 
             x-show="cartOpen" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="transform translate-x-full"
             x-transition:enter-end="transform translate-x-0"
             x-transition:leave="transition ease-in duration-300"
             x-transition:leave-start="transform translate-x-0"
             x-transition:leave-end="transform translate-x-full">
            
            <div class="cart-header">
                <h2 class="cart-title">Keranjang Anda</h2>
                <button @click="cartOpen = false" class="close-cart-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="cart-body">
                @if(count($cart) === 0)
                    <div class="cart-empty">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <p>Keranjang Anda masih kosong.</p>
                        <button @click="cartOpen = false" class="start-order-btn">Mulai Pesan</button>
                    </div>
                @else
                    <div class="cart-items">
                        @foreach($cart as $key => $item)
                            <div class="cart-item">
                                @if($item['image'])
                                    <img src="{{ Storage::url($item['image']) }}" alt="{{ $item['name'] }}" class="cart-item-img">
                                @else
                                    <div class="cart-item-img" style="display:flex; align-items:center; justify-content:center; color:#ccc;">
                                        <svg xmlns="http://www.w3.org/2000/svg" style="width:32px; height:32px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    </div>
                                @endif

                                <div class="cart-item-body">
                                    <h3 class="cart-item-name">{{ $item['name'] }}</h3>
                                    <span class="cart-item-price">Rp {{ number_format($item['price'] * $item['quantity'], 0, ',', '.') }}</span>
                                </div>
                                <div class="cart-item-actions">
                                    <div class="quantity-control">
                                        <button wire:click="decrementQuantity({{ $key }})" class="qty-btn">-</button>
                                        <span class="qty-value">{{ $item['quantity'] }}</span>
                                        <button wire:click="incrementQuantity({{ $key }})" class="qty-btn">+</button>
                                    </div>
                                    <button wire:click="removeFromCart({{ $key }})" class="remove-btn" aria-label="Hapus">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            @if(count($cart) > 0)
                <div class="cart-footer">
                    <div class="cart-total-row">
                        <span>Total Keseluruhan</span>
                        <span>Rp {{ number_format($this->cartTotal, 0, ',', '.') }}</span>
                    </div>
                    <a href="#" class="checkout-btn">Pesan Sekarang</a>
                    @if($table)
                        <p class="cart-table-info">Pesanan akan diantar ke Meja <strong>{{ $table }}</strong></p>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <!-- Product Detail Modal -->
    @if($selectedProduct)
        <div class="product-modal-overlay" wire:click.self="closeProductModal">
            <div class="product-modal-content">
                <button class="product-modal-close" wire:click="closeProductModal">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
                
                <div class="product-modal-img-container">
                    @if($selectedProduct->image)
                        <img src="{{ Storage::url($selectedProduct->image) }}" alt="{{ $selectedProduct->name }}" class="product-modal-img">
                    @else
                        <div class="product-placeholder">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                    @endif
                    @if($selectedProduct->is_out_of_stock)
                        <div class="out-of-stock-overlay">
                            <span class="out-of-stock-badge">Habis</span>
                        </div>
                    @endif
                </div>

                <div class="product-modal-details">
                    <h2 class="product-modal-title">{{ $selectedProduct->name }}</h2>
                    <div class="product-modal-price">Rp {{ number_format($selectedProduct->price, 0, ',', '.') }}</div>
                    <div class="product-modal-desc">
                        {{ $selectedProduct->description }}
                    </div>
                    
                    <button 
                        wire:click="addToCart({{ $selectedProduct->id }}); closeProductModal()" 
                        @if($selectedProduct->is_out_of_stock) disabled @endif
                        class="modal-add-btn"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" style="width:24px; height:24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ $selectedProduct->is_out_of_stock ? 'Stok Habis' : 'Tambah ke Keranjang' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
